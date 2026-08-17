<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     poller
 * @copyright  (C) Adam Armstrong
 *
 * STP poller: bridge snapshot, instances where applicable, ports per instance,
 * RRD updates, and change/event logging.
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

// Timer for concise runtime output
$__t_stp_start = microtime(TRUE);

// Quick feature test: look for a real scalar or the basePort table
$stp_bridge_present = (
  snmp_test_oid($device, 'dot1dBasePortIfIndex', 'BRIDGE-MIB') ||
  snmp_test_oid($device, 'dot1qBasePortIfIndex', 'Q-BRIDGE-MIB') ||
  snmp_test_oid($device, 'dot1dStpRootPort.0', 'BRIDGE-MIB') ||
  snmp_test_oid($device, 'dot1dStpPortRole', 'RSTP-MIB') ||
  snmp_test_oid($device, 'ieee8021MstpConfigName.0', 'IEEE8021-MSTP-MIB')
);

// CLEANUP: Remove erroneous STP entries for devices that don't support STP
if (!$stp_bridge_present) {
  // Check if this device has any existing STP database entries
  $existing_bridge = dbFetchRow("SELECT device_id FROM `stp_bridge` WHERE `device_id` = ?", [$device['device_id']]);
  $existing_instances = dbFetchCell("SELECT COUNT(*) FROM `stp_instances` WHERE `device_id` = ?", [$device['device_id']]);

  if ($existing_bridge || $existing_instances > 0) {
    print_debug("Device doesn't support STP but has database entries - cleaning up");

    // Remove all STP-related entries for this device
    dbDelete('stp_ports', '`device_id` = ?', [$device['device_id']]);
    dbDelete('stp_instances', '`device_id` = ?', [$device['device_id']]);
    dbDelete('stp_bridge', '`device_id` = ?', [$device['device_id']]);

    print_debug(sprintf("Cleaned up STP database entries for non-STP device %d", $device['device_id']));
  }

  return;
}
print_cli_data_field('STP');

// BASE PORT → PORT_ID MAP
$base_map_source = 'BRIDGE-MIB';
$base_map_raw = snmp_cache_table($device, 'dot1dBasePortIfIndex', [], 'BRIDGE-MIB');

$base_to_if   = [];
foreach ($base_map_raw as $base => $r) {
  $base_to_if[(int)$base] = (int)$r['dot1dBasePortIfIndex'];
}

$if_to_portid = [];
if (!empty($base_to_if)) {
  $ifindexes = array_values($base_to_if);
  $in_qs     = implode(',', array_fill(0, count($ifindexes), '?'));
  $rows = dbFetchRows("SELECT `port_id`,`ifIndex` FROM `ports` WHERE `device_id`=? AND `ifIndex` IN ($in_qs)",
                      array_merge([ $device['device_id'] ], $ifindexes));
  foreach ($rows as $r) { $if_to_portid[(int)$r['ifIndex']] = (int)$r['port_id']; }
}

$base_to_portid = [];
foreach ($base_to_if as $base => $ifIndex) {
  if (isset($if_to_portid[$ifIndex])) $base_to_portid[$base] = $if_to_portid[$ifIndex];
}

print_debug(sprintf('Built basePort mapping from %s: basePorts=%d, mappedPorts=%d',
  $base_map_source, count($base_to_if), count($base_to_portid)));

// BasePort mapping coverage diagnostics
$base_count = count($base_to_if);
$mapped_count = count($base_to_portid);
$coverage = $base_count > 0 ? ($mapped_count / $base_count) * 100 : 100;

if ($coverage < 95 && $base_count > 0) {
  // Find missing basePorts for diagnostic output
  $missing_bases = array_diff(array_keys($base_to_if), array_keys($base_to_portid));
  $missing_sample = array_slice($missing_bases, 0, 3); // Show up to 3 examples
  $missing_str = implode(',', $missing_sample);
  if (count($missing_bases) > 3) {
    $missing_str .= '+' . (count($missing_bases) - 3) . 'more';
  }

  print_warning(sprintf('STP basePort mapping coverage %.1f%% (%d/%d) - missing basePorts: %s',
    $coverage, $mapped_count, $base_count, $missing_str));
}

print_cli(sprintf(' map:%s=%d(%.1f%%)',
  ($base_map_source === 'BRIDGE-MIB' ? 'bridge' : 'q-bridge'),
  $mapped_count,
  $coverage
));

// Variant detection flags to set stp_bridge.variant based on actual data seen
$det_stp  = FALSE;
$det_rstp = FALSE;
$det_mstp = FALSE;
$det_pvst = FALSE;

// BRIDGE-LEVEL SNAPSHOT
$bridge = snmp_get_multi_oid($device, [
  'dot1dStpDesignatedRoot.0',
  'dot1dStpRootCost.0',
  'dot1dStpRootPort.0',
  'dot1dStpMaxAge.0',
  'dot1dStpHelloTime.0',
  'dot1dStpForwardDelay.0',
  'dot1dStpHoldTime.0',
  'dot1dStpTopChanges.0',
  'dot1dStpTimeSinceTopologyChange.0',
  'dot1dStpPriority.0',
  'dot1dBaseBridgeAddress.0'
], [], 'BRIDGE-MIB');

$before = dbFetchRow("SELECT * FROM `stp_bridge` WHERE `device_id` = ?", [ $device['device_id'] ]) ?: [];

$fields = [
  'priority'         => isset($bridge['0']['dot1dStpPriority']) ? (int)$bridge['0']['dot1dStpPriority'] : NULL,
  'designated_root'  => isset($bridge['0']['dot1dStpDesignatedRoot']) ? strtoupper($bridge['0']['dot1dStpDesignatedRoot']) : NULL,
  'root_cost'        => isset($bridge['0']['dot1dStpRootCost']) ? (int)$bridge['0']['dot1dStpRootCost'] : NULL,
  'root_port'        => isset($bridge['0']['dot1dStpRootPort']) ? (int)$bridge['0']['dot1dStpRootPort'] : NULL,
  'max_age_cs'       => isset($bridge['0']['dot1dStpMaxAge']) ? (int)$bridge['0']['dot1dStpMaxAge'] : NULL,
  'hello_time_cs'    => isset($bridge['0']['dot1dStpHelloTime']) ? (int)$bridge['0']['dot1dStpHelloTime'] : NULL,
  'fwd_delay_cs'     => isset($bridge['0']['dot1dStpForwardDelay']) ? (int)$bridge['0']['dot1dStpForwardDelay'] : NULL,
  'hold_time_cs'     => isset($bridge['0']['dot1dStpHoldTime']) ? (int)$bridge['0']['dot1dStpHoldTime'] : NULL,
  'top_changes'      => isset($bridge['0']['dot1dStpTopChanges']) ? (int)$bridge['0']['dot1dStpTopChanges'] : NULL,
  'time_since_tc_cs' => isset($bridge['0']['dot1dStpTimeSinceTopologyChange']) ?
    (timeticks_to_sec($bridge['0']['dot1dStpTimeSinceTopologyChange']) * 100) : NULL,
];

// Compute local BridgeID for precise cross-device linking
if (isset($fields['priority']) && isset($bridge['0']['dot1dBaseBridgeAddress'])) {
  $prio = (int)$fields['priority'];
  $mac_raw = $bridge['0']['dot1dBaseBridgeAddress'];

  // Convert MAC to bytes (inline stp_mac_to_bytes logic)
  $clean_mac = preg_replace('/[^0-9A-Fa-f]/', '', (string)$mac_raw);
  if (strlen($clean_mac) === 12) {
    $mac_bytes = pack('H*', $clean_mac);
    if (strlen($mac_bytes) === 6) {
      // Build bridge ID: priority (2 bytes) + MAC (6 bytes)
      $bridge_id_bin = pack('n', $prio) . $mac_bytes;
      $fields['bridge_id'] = bin2hex($bridge_id_bin);
    }
  }
}

$fields['device_id'] = $device['device_id'];
if (!empty($before)) {
  dbUpdate($fields, 'stp_bridge', '`device_id` = ?', [ $device['device_id'] ]);
} else {
  dbInsert($fields, 'stp_bridge');
}
$after = dbFetchRow("SELECT * FROM `stp_bridge` WHERE `device_id` = ?", [ $device['device_id'] ]);


// Check if PVST VLAN 1 exists to avoid CIST/PVST1 duplication
$pvst_vlan1_exists = dbFetchCell("SELECT stp_instance_id FROM stp_instances WHERE device_id = ? AND type = 'pvst' AND instance_key = 1", [$device['device_id']]);

if ($pvst_vlan1_exists) {
    // PVST VLAN 1 exists - suppress CIST creation to avoid duplicate topology data
    $cist_id = $pvst_vlan1_exists; // Use PVST VLAN 1 as CIST equivalent
    print_debug("Suppressing CIST instance - using PVST VLAN 1 (ID: $cist_id) as CIST equivalent");
} else {
    // Normal CIST creation when no PVST VLAN 1 exists
    $cist_id = stp_instance_ensure($device['device_id'], 0, 'cist');
    print_debug("Created CIST instance (ID: $cist_id) - no PVST VLAN 1 found");
}

// Initialize instance_stats early to ensure CIST instance is always tracked
$instance_stats = [];
$instance_stats[$cist_id] = [
    'type' => 'cist',
    'instance_key' => 0,
    'name' => 'CIST',
    'port_count' => 0,
    'forwarding_count' => 0,
    'blocking_count' => 0
];

$ports_updated = 0;

$rstp_updates_batch = [];
$mstp_cist_batch    = [];
$msti_ports_batch   = [];
$instance_ports_batch = [];
$guard_updates_batch  = [];

$tbl = snmpwalk_cache_oid($device, 'dot1dStpPortState', [], 'BRIDGE-MIB');
$tbl = snmpwalk_cache_oid($device, 'dot1dStpPortPathCost', $tbl, 'BRIDGE-MIB');
$tbl = snmpwalk_cache_oid($device, 'dot1dStpPortPriority', $tbl, 'BRIDGE-MIB');
$tbl = snmpwalk_cache_oid($device, 'dot1dStpPortDesignatedBridge', $tbl, 'BRIDGE-MIB');
$tbl = snmpwalk_cache_oid($device, 'dot1dStpPortDesignatedPort', $tbl, 'BRIDGE-MIB');
$tbl = snmpwalk_cache_oid($device, 'dot1dStpPortDesignatedRoot', $tbl, 'BRIDGE-MIB');
$tbl = snmpwalk_cache_oid($device, 'dot1dStpPortForwardTransitions', $tbl, 'BRIDGE-MIB');
$tbl = snmpwalk_cache_oid($device, 'dot1dStpPortEnable', $tbl, 'BRIDGE-MIB');
$cist_ports_batch = [];
foreach ($tbl as $basePort => $row) {
  $port_id = $base_to_portid[(int)$basePort] ?? null;
  if (!$port_id) continue;

  $port_state = stp_normalize_state($row['dot1dStpPortState'] ?? null);
  $cist_ports_batch[] = [
    'device_id'           => $device['device_id'],
    'port_id'             => $port_id,
    'stp_instance_id'     => $cist_id,
    'base_port'           => (int)$basePort,
    'admin_enable'        => isset($row['dot1dStpPortEnable']) ? stp_truth($row['dot1dStpPortEnable']) : NULL,
    'state'               => $port_state,
    'path_cost'           => isset($row['dot1dStpPortPathCost']) ? (int)$row['dot1dStpPortPathCost'] : NULL,
    'priority'            => isset($row['dot1dStpPortPriority']) ? (int)$row['dot1dStpPortPriority'] : NULL,
    'designated_bridge'   => isset($row['dot1dStpPortDesignatedBridge']) ? strtoupper($row['dot1dStpPortDesignatedBridge']) : NULL,
    'designated_port'     => isset($row['dot1dStpPortDesignatedPort']) ? (int)$row['dot1dStpPortDesignatedPort'] : NULL,
    'designated_root'     => isset($row['dot1dStpPortDesignatedRoot']) ? strtoupper($row['dot1dStpPortDesignatedRoot']) : NULL,
    'forward_transitions' => isset($row['dot1dStpPortForwardTransitions']) ? (int)$row['dot1dStpPortForwardTransitions'] : NULL,
  ];
  $ports_updated++;
}

if (!empty($cist_ports_batch)) {
  dbReplaceMulti($cist_ports_batch, 'stp_ports');
  print_debug(sprintf('Bulk replaced %d CIST ports', count($cist_ports_batch)));
}
$cnt_cist = !empty($cist_ports_batch) ? count($cist_ports_batch) : 0;
if ($cnt_cist > 0) { print_cli(sprintf(' cist:%d', $cnt_cist)); }
$det_stp = $det_stp || !empty($cist_ports_batch);

if (snmp_test_oid($device, 'dot1dStpPortRole', 'RSTP-MIB')) {
  print_debug('Adding RSTP extensions');
  $roles = snmpwalk_cache_oid($device, 'dot1dStpPortRole', [], 'RSTP-MIB');
  $operE = snmpwalk_cache_oid($device, 'dot1dStpPortOperEdgePort', $roles, 'RSTP-MIB');
  $admE  = snmpwalk_cache_oid($device, 'dot1dStpPortAdminEdgePort', $operE, 'RSTP-MIB');
  $p2p   = snmpwalk_cache_oid($device, 'dot1dStpPortPointToPoint', $admE, 'RSTP-MIB');

  $rstp_updates_batch = [];
  foreach ($p2p as $basePort => $r) {
    $port_id = $base_to_portid[(int)$basePort] ?? null;
    if (!$port_id) continue;

    $rstp_updates_batch[] = [
      'device_id'      => $device['device_id'],
      'port_id'        => $port_id,
      'stp_instance_id'=> $cist_id,
      'base_port'      => (int)$basePort,
      'role'           => stp_normalize_role($r['dot1dStpPortRole'] ?? 0),
      'oper_edge'      => stp_truth($r['dot1dStpPortOperEdgePort'] ?? 2),
      'admin_edge'     => stp_truth($r['dot1dStpPortAdminEdgePort'] ?? 2),
      'point2point'    => stp_point2point_status($r['dot1dStpPortPointToPoint'] ?? 'unknown')
    ];
  }

  if (!empty($rstp_updates_batch)) {
    dbUpdateMulti($rstp_updates_batch, 'stp_ports', ['role', 'oper_edge', 'admin_edge', 'point2point']);
    print_debug(sprintf('Bulk updated RSTP extensions for %d ports', count($rstp_updates_batch)));
  }
  $det_rstp = $det_rstp || !empty($rstp_updates_batch);
  if (!empty($rstp_updates_batch)) { print_cli(sprintf(' rstp:%d', count($rstp_updates_batch))); }

}

// MSTP (CIST + MSTIs)
// Use a definitive scalar probe per guidelines, not MIB root
if (snmp_test_oid($device, 'ieee8021MstpConfigName.0', 'IEEE8021-MSTP-MIB')) {
  print_debug('Adding MSTP data');

  // Refresh region info
  $region = snmp_get_multi_oid($device, [
    'ieee8021MstpConfigName.0',
    'ieee8021MstpRevisionLevel.0'
  ], [], 'IEEE8021-MSTP-MIB');

  if (!empty($region)) {
    $region_data = [
      'device_id'       => $device['device_id'],
      'mst_region_name' => $region['0']['ieee8021MstpConfigName'] ?? NULL,
      'mst_revision'    => $region['0']['ieee8021MstpRevisionLevel'] ?? NULL
    ];
    // Update region fields only; do not REPLACE the whole row
    dbUpdate($region_data, 'stp_bridge', '`device_id` = ?', [ $device['device_id'] ]);
  }

  // CIST port table
  $cist = snmpwalk_cache_oid($device, 'ieee8021MstpCistPortTable', [], 'IEEE8021-MSTP-MIB');
  $mstp_cist_batch = [];
  foreach ($cist as $idx => $r) {
    // index is <component>.<basePort>
    $parts   = explode('.', $idx);
    $base    = (int)end($parts);
    $port_id = $base_to_portid[$base] ?? null;
    if (!$port_id) continue;

    $mstp_cist_batch[] = [
      'device_id'       => $device['device_id'],
      'port_id'         => $port_id,
      'stp_instance_id' => $cist_id,
      'base_port'       => $base,
      'state'           => stp_normalize_state($r['ieee8021MstpCistPortState'] ?? null),
      'role'            => stp_normalize_role($r['ieee8021MstpCistPortRole'] ?? 0),
      'path_cost'       => isset($r['ieee8021MstpCistPortPathCost']) ? (int)$r['ieee8021MstpCistPortPathCost'] : NULL,
      'oper_edge'       => stp_truth($r['ieee8021MstpCistPortOperEdgePort'] ?? 2),
      'point2point'     => stp_point2point_status($r['ieee8021MstpCistPortAdminP2P'] ?? 'unknown')
    ];
  }

  // Batch update MSTP CIST ports
  if (!empty($mstp_cist_batch)) {
    dbUpdateMulti($mstp_cist_batch, 'stp_ports', ['state', 'role', 'path_cost', 'oper_edge', 'point2point']);
    print_debug(sprintf('Bulk updated MSTP CIST data for %d ports', count($mstp_cist_batch)));
  }
  $c_mstp_cist = !empty($mstp_cist_batch) ? count($mstp_cist_batch) : 0;

  // Collect per-instance timer data for MSTP
  $msti_timers = snmpwalk_cache_oid($device, 'ieee8021MstpTable', [], 'IEEE8021-MSTP-MIB');
  foreach ($msti_timers as $msti_key => $timer_data) {
    // Parse MSTI index from key
    $msti_id = (int)$msti_key;
    if ($msti_id <= 0) continue;

    $instance_timers = [];
    if (isset($timer_data['ieee8021MstpHelloTime'])) {
      $instance_timers['hello_time_cs'] = (int)$timer_data['ieee8021MstpHelloTime'];
    }
    if (isset($timer_data['ieee8021MstpMaxAge'])) {
      $instance_timers['max_age_cs'] = (int)$timer_data['ieee8021MstpMaxAge'];
    }
    if (isset($timer_data['ieee8021MstpForwardDelay'])) {
      $instance_timers['forward_delay_cs'] = (int)$timer_data['ieee8021MstpForwardDelay'];
    }

    if (!empty($instance_timers)) {
      $iid = stp_instance_ensure($device['device_id'], $msti_id, 'msti', $instance_timers);
      print_debug("Updated MSTI $msti_id timers");
    }
  }

  // Collect MSTP instance names and enhanced data
  $msti_config = snmpwalk_cache_oid($device, 'ieee8021MstpConfigIdTable', [], 'IEEE8021-MSTP-MIB');
  foreach ($msti_config as $msti_index => $config) {
    $msti_id = (int)$msti_index;
    if ($msti_id <= 0) continue;

    $instance_data = [];
    if (isset($config['ieee8021MstpConfigName'])) {
      $instance_data['name'] = $config['ieee8021MstpConfigName'];
    }

    if (!empty($instance_data)) {
      $iid = stp_instance_ensure($device['device_id'], $msti_id, 'msti', $instance_data);
      print_debug("Enhanced MSTI $msti_id with name: " . ($instance_data['name'] ?? 'N/A'));
    }
  }

  // MSTI port tables - auto-discover MSTIs from port data (no discovery needed)
  $tbl = snmpwalk_cache_oid($device, 'ieee8021MstpPortTable', [], 'IEEE8021-MSTP-MIB');
  $msti_ports_batch = [];
  $discovered_mstis = [];

  if (!empty($tbl)) {
    // First pass: discover all MSTIs present in port data
    foreach ($tbl as $idx => $r) {
      $parts = explode('.', $idx);
      $mstId = (int)(count($parts) >= 2 ? $parts[count($parts)-2] : 0);
      if ($mstId > 0) {
        $discovered_mstis[$mstId] = true;
      }
    }

    // Create instances for discovered MSTIs
    foreach (array_keys($discovered_mstis) as $msti_id) {
      $iid = stp_instance_ensure($device['device_id'], $msti_id, 'msti');
      print_debug("Auto-created/ensured MSTI instance $msti_id (ID: $iid)");

      // Process port data for this MSTI
      foreach ($tbl as $idx => $r) {
        // index often is <component>.<mstId>.<basePort> or <mstId>.<basePort>
        $parts = explode('.', $idx);
        $base  = (int)end($parts);
        $mstId = (int)(count($parts) >= 2 ? $parts[count($parts)-2] : 0);

        if ($mstId !== (int)$msti_id) continue;

        $port_id = $base_to_portid[$base] ?? null;
        if (!$port_id) continue;

        $msti_ports_batch[] = [
          'device_id'       => $device['device_id'],
          'port_id'         => $port_id,
          'stp_instance_id' => $iid,
          'base_port'       => $base,
          'state'           => stp_normalize_state($r['ieee8021MstpPortState'] ?? null),
          'role'            => stp_normalize_role($r['ieee8021MstpPortRole'] ?? 0),
          'path_cost'       => isset($r['ieee8021MstpPortPathCost']) ? (int)$r['ieee8021MstpPortPathCost'] : NULL,
        ];
        $ports_updated++;
      }
    }
  }

  // Batch replace MSTI ports
  if (!empty($msti_ports_batch)) {
    dbReplaceMulti($msti_ports_batch, 'stp_ports');
    print_debug(sprintf('Bulk replaced %d MSTI ports', count($msti_ports_batch)));
  }
  $c_msti      = !empty($msti_ports_batch) ? count($msti_ports_batch) : 0;
  if ($c_mstp_cist + $c_msti > 0) { print_cli(sprintf(' mstp:%d/%d', $c_mstp_cist, $c_msti)); }

  // CIST Regional Root (MSTP domains)
  $mstp_rr = snmp_get($device, 'ieee8021MstpCistRegionalRootId.0', '-Oqv', 'IEEE8021-MSTP-MIB');
  if ($mstp_rr) {
    $rr_hex = snmp_hexstring($mstp_rr);
    if (!empty($rr_hex)) {
      dbUpdate(['regional_root' => $rr_hex], 'stp_instances', '`stp_instance_id` = ?', [ $cist_id ]);
      print_debug('Updated CIST regional root from IEEE8021-MSTP-MIB');
    }
  }

  // VLAN→Instance mapping for MSTP (fix for missing UI functionality)
  $vlan_map = snmpwalk_cache_oid($device, 'ieee8021MstpVlanV2Table', [], 'IEEE8021-MSTP-MIB');
  if (!empty($vlan_map)) {
    $vlan_mappings = 0;
    foreach ($vlan_map as $vid => $row) {
      $mstId = isset($row['ieee8021MstpVlanV2MstId']) ? (int)$row['ieee8021MstpVlanV2MstId'] : 0;

      if ($mstId <= 0) {
        // VLAN maps to CIST (MSTI 0)
        $iid = $cist_id;
      } else {
        // VLAN maps to specific MSTI - ensure instance exists
        $iid = stp_instance_ensure($device['device_id'], $mstId, 'msti');
      }

      // Insert/update VLAN mapping
      dbReplace([
        'device_id' => $device['device_id'],
        'vlan_vlan' => (int)$vid,
        'stp_instance_id' => $iid
      ], 'stp_vlan_map');
      $vlan_mappings++;
    }

    if ($vlan_mappings > 0) {
      print_debug(sprintf('Updated MSTP VLAN mappings for %d VLANs', $vlan_mappings));
    }
  }
}
$det_mstp = $det_mstp || !empty($mstp_cist_batch) || !empty($msti_ports_batch);


// SIMPLE EVENTING ON ROOT/PORT CHANGES
$prev = $before;
$now  = $after;

if ($prev && $now) {
  if ($prev['designated_root'] !== $now['designated_root']) {
    $msg = 'STP root bridge changed: ' . stp_bridge_id_str($prev['designated_root']) . ' -> ' . stp_bridge_id_str($now['designated_root']);
    log_event($msg, $device, 'stp', NULL, 'warning');
  }

  if ((int)$prev['root_port'] !== (int)$now['root_port']) {
    $msg = 'STP root port changed: ' . (int)$prev['root_port'] . ' -> ' . (int)$now['root_port'];
    log_event($msg, $device, 'stp', NULL, 'warning');
  }
}

// Log general changes for bridge-level fields
stp_log_if_changed($device, $before, $after, 'bridge');

// VENDOR-SPECIFIC MIB EXTENSIONS
// Initialize vendor data collection structure
$vendor_stp_data = [
  'port_features' => ['guard' => [], 'inconsistencies' => []],
  'instance_ports' => [],
  'instance_data' => [],
  'protocol_detection' => ['stp' => false, 'rstp' => false, 'mstp' => false, 'pvst' => false]
];

// Load vendor MIB extensions only for MIBs associated with this device/OS
// Uses standard Observium include-dir-mib pattern to avoid probing unrelated vendors
$include_dir = 'includes/polling/stp';
include($config['install_dir'] . '/includes/include-dir-mib.inc.php');

// VENDOR DATA PROCESSING
// Process collected vendor data using structured approach
if (!empty($vendor_stp_data)) {

  // Process port features (guard settings, inconsistencies)
  $guard_updates_batch = [];

  // Merge guard reported by ifIndex into basePort keyed list
  if (!empty($vendor_stp_data['port_features']['guard_ifindex'])) {
    // Build reverse map if available
    $if_to_base = [];
    foreach ($base_to_if as $base => $ifIdx) { $if_to_base[(int)$ifIdx] = (int)$base; }
    foreach ($vendor_stp_data['port_features']['guard_ifindex'] as $ifIndex => $features) {
      $basePort = $if_to_base[(int)$ifIndex] ?? null;
      if ($basePort === null) continue;
      if (!isset($vendor_stp_data['port_features']['guard'][$basePort])) {
        $vendor_stp_data['port_features']['guard'][$basePort] = [];
      }
      $vendor_stp_data['port_features']['guard'][$basePort] = array_unique(array_merge(
        (array)$vendor_stp_data['port_features']['guard'][$basePort], (array)$features
      ));
    }
  }

  foreach ($vendor_stp_data['port_features']['guard'] as $basePort => $features) {
    $port_id = $base_to_portid[$basePort] ?? null;
    if (!$port_id) continue;

    $guard_updates_batch[] = [
      'device_id'       => $device['device_id'],
      'port_id'         => $port_id,
      'stp_instance_id' => $cist_id,
      'base_port'       => $basePort,
      'guard'           => implode(',', $features)
    ];
  }

  if (!empty($guard_updates_batch)) {
    dbUpdateMulti($guard_updates_batch, 'stp_ports', ['guard']);
    print_debug(sprintf('Updated guard features for %d ports', count($guard_updates_batch)));
  }
  if (!empty($guard_updates_batch)) { print_cli(sprintf(' guard:%d', count($guard_updates_batch))); }

  // Process instance-specific port data
  $instance_ports_batch = [];
  $pvst_vlan_map_count = 0;
  $pvst_ignore = isset($config['pvst']['ignore_vlans']) && is_array($config['pvst']['ignore_vlans']) ? $config['pvst']['ignore_vlans'] : [1002,1003,1004,1005];
  foreach ($vendor_stp_data['instance_ports'] as $instance_key => $ports) {
    // Parse instance key: "type:key"
    [$instance_type, $instance_num] = explode(':', $instance_key, 2);
    // Skip PVST reserved VLANs
    if ($instance_type === 'pvst' && in_array((int)$instance_num, $pvst_ignore, TRUE)) {
      print_debug("Skipping PVST reserved VLAN $instance_num");
      continue;
    }

    // Auto-create instance if needed (no discovery dependency!)
    $iid = stp_instance_ensure($device['device_id'], (int)$instance_num, $instance_type);
    print_debug("Auto-created/ensured vendor instance: $instance_key (ID: $iid)");

    // Ensure VLAN → STP instance mapping for PVST instances when discovery isn't used
    if ($instance_type === 'pvst' && (int)$instance_num > 0 && !in_array((int)$instance_num, $pvst_ignore, TRUE)) {
      dbReplace([
        'device_id'      => $device['device_id'],
        'vlan_vlan'      => (int)$instance_num,
        'stp_instance_id'=> $iid
      ], 'stp_vlan_map');
      $pvst_vlan_map_count++;
    }

    // Add to instance_stats for RRD creation
    if (!isset($instance_stats[$iid])) {
      $name = ($instance_type === 'pvst') ? 'VLAN-' . $instance_num :
              (($instance_type === 'msti') ? 'MSTI-' . $instance_num : $instance_type . '-' . $instance_num);
      $instance_stats[$iid] = [
        'type' => $instance_type,
        'instance_key' => $instance_num,
        'name' => $name,
        'port_count' => 0,
        'forwarding_count' => 0,
        'blocking_count' => 0
      ];
    }

    foreach ($ports as $basePort => $port_data) {
      // Use port_id from vendor data if available, otherwise lookup via basePort
      $port_id = $port_data['port_id'] ?? ($base_to_portid[$basePort] ?? null);
      if (!$port_id) continue;

      $port_update = [
        'device_id'       => $device['device_id'],
        'port_id'         => $port_id,
        'stp_instance_id' => $iid,
        'base_port'       => $basePort,
      ];

      // Add available port data fields
      foreach (['state', 'role', 'path_cost', 'inconsistent'] as $field) {
        if (isset($port_data[$field])) {
          $port_update[$field] = $port_data[$field];
        }
      }

      $instance_ports_batch[] = $port_update;
      $ports_updated++;
    }
  }

  if (!empty($instance_ports_batch)) {
    dbReplaceMulti($instance_ports_batch, 'stp_ports');
    print_debug(sprintf('Updated vendor port data for %d instance ports', count($instance_ports_batch)));
  }
  if (!empty($instance_ports_batch)) { print_cli(sprintf(' vendor:%d', count($instance_ports_batch))); }
  if ($pvst_vlan_map_count > 0) {
    print_debug(sprintf('Mapped %d PVST VLANs to STP instances', $pvst_vlan_map_count));
  }

  // Process instance-level enhancements
  foreach ($vendor_stp_data['instance_data'] as $instance_key => $instance_data) {
    // Parse instance key: "type:key"
    [$instance_type, $instance_num] = explode(':', $instance_key, 2);

    // Auto-create instance if needed (no discovery dependency!)
    $iid = stp_instance_ensure($device['device_id'], (int)$instance_num, $instance_type);
    print_debug("Auto-created/ensured vendor instance for data: $instance_key (ID: $iid)");

    // Also ensure VLAN → STP instance mapping when we only have instance metadata (no ports yet)
    if ($instance_type === 'pvst' && (int)$instance_num > 0 && !in_array((int)$instance_num, $pvst_ignore, TRUE)) {
      dbReplace([
        'device_id'      => $device['device_id'],
        'vlan_vlan'      => (int)$instance_num,
        'stp_instance_id'=> $iid
      ], 'stp_vlan_map');
      $pvst_vlan_map_count++;
    }

    // Add to instance_stats for RRD creation
    if (!isset($instance_stats[$iid])) {
      $name = ($instance_type === 'pvst') ? 'VLAN-' . $instance_num :
              (($instance_type === 'msti') ? 'MSTI-' . $instance_num : $instance_type . '-' . $instance_num);
      $instance_stats[$iid] = [
        'type' => $instance_type,
        'instance_key' => $instance_num,
        'name' => $name,
        'port_count' => 0,
        'forwarding_count' => 0,
        'blocking_count' => 0
      ];
    }

    if (!empty($instance_data)) {
      dbUpdate($instance_data, 'stp_instances', '`stp_instance_id` = ?', [$iid]);
      print_debug(sprintf('Enhanced instance %s with %d vendor fields', $instance_key, count($instance_data)));
    }
  }

  // Merge vendor protocol detection with main detection flags
  if (!empty(array_filter($vendor_stp_data['protocol_detection']))) {
    foreach ($vendor_stp_data['protocol_detection'] as $protocol => $detected) {
      if ($detected) {
        ${"det_$protocol"} = true;
        print_debug("Vendor protocol detection: $protocol");
      }
    }
  }

  // Merge vendor bridge data when BRIDGE-MIB data is missing
  if (!empty($vendor_stp_data['bridge_data'])) {
    $vendor_bridge_updated = false;

    // Update database with vendor-collected bridge data
    foreach ($vendor_stp_data['bridge_data'] as $field => $value) {
      if ($value !== null && $value !== 0) {
        // Only update if current bridge data is null or 0 (missing BRIDGE-MIB support)
        if (empty($after[$field]) || $after[$field] == 0) {
          dbUpdate([$field => $value], 'stp_bridge', '`device_id` = ?', [$device['device_id']]);
          print_debug("Updated bridge $field from vendor data: $value");
          $vendor_bridge_updated = true;
        }
      }
    }

    // Re-fetch bridge data if we updated it
    if ($vendor_bridge_updated) {
      $after = dbFetchRow("SELECT * FROM `stp_bridge` WHERE `device_id` = ?", [ $device['device_id'] ]);
    }
  }
}

// Fallback PVST detection: if we have pvst instances, mark pvst detected
if (!$det_pvst) {
  $has_pvst_instances = (int)dbFetchCell(
    "SELECT COUNT(*) FROM stp_instances WHERE device_id=? AND type='pvst'",
    [ $device['device_id'] ]
  );
  if ($has_pvst_instances > 0) {
    $det_pvst = true;
    print_debug("Derived PVST detection from stp_instances fallback");
  }
}

// Determine and persist STP protocol variant based on collected data and vendor hints
// Priority: MSTP > PVST > RSTP > STP
$variant_new = 'unknown';
if ($det_mstp) {
    $variant_new = 'mstp';
} elseif ($det_pvst) {
    $variant_new = 'pvst';
} elseif ($det_rstp) {
    $variant_new = 'rstp';
} elseif ($det_stp) {
    $variant_new = 'stp';
}

// Persist variant on stp_bridge if changed
$curr_variant = dbFetchCell('SELECT `variant` FROM `stp_bridge` WHERE `device_id` = ?', [ $device['device_id'] ]);
if ($variant_new && $variant_new !== $curr_variant) {
    dbUpdate([ 'variant' => $variant_new ], 'stp_bridge', '`device_id` = ?', [ $device['device_id'] ]);
    print_debug(sprintf('Updated STP variant: %s -> %s', $curr_variant ?: 'null', $variant_new));
    // Optional event for visibility
    log_event('STP variant changed: ' . strtoupper($curr_variant ?: 'unknown') . ' -> ' . strtoupper($variant_new), $device, 'stp', NULL, 'info');
}

// Track instance and port statistics from actual polling data
$guard_ports = [];

// Build instance statistics from what we actually polled
if (!empty($cist_ports_batch)) {
    foreach ($cist_ports_batch as $port_data) {
        $instance_stats[$cist_id]['port_count']++;
        if ($port_data['state'] === 'forwarding') {
            $instance_stats[$cist_id]['forwarding_count']++;
        } elseif ($port_data['state'] === 'blocking') {
            $instance_stats[$cist_id]['blocking_count']++;
        }
    }
}

// Add MSTP CIST instance stats
if (!empty($mstp_cist_batch)) {
    foreach ($mstp_cist_batch as $port_data) {
        $instance_stats[$cist_id]['port_count']++;
        if ($port_data['state'] === 'forwarding') {
            $instance_stats[$cist_id]['forwarding_count']++;
        } elseif ($port_data['state'] === 'blocking') {
            $instance_stats[$cist_id]['blocking_count']++;
        }
    }
}

// Add MSTI instance stats from discovered instances
if (!empty($discovered_mstis)) {
    foreach ($discovered_mstis as $msti_id => $true) {
        $iid = stp_instance_ensure($device['device_id'], $msti_id, 'msti');
        if (!isset($instance_stats[$iid])) {
            $instance_stats[$iid] = [
                'type' => 'msti',
                'instance_key' => $msti_id,
                'name' => 'MSTI-' . $msti_id,
                'port_count' => 0,
                'forwarding_count' => 0,
                'blocking_count' => 0
            ];
        }
    }
}

// Add MSTI port stats
if (!empty($msti_ports_batch)) {
    foreach ($msti_ports_batch as $port_data) {
        $iid = $port_data['stp_instance_id'];
        if (isset($instance_stats[$iid])) {
            $instance_stats[$iid]['port_count']++;
            if ($port_data['state'] === 'forwarding') {
                $instance_stats[$iid]['forwarding_count']++;
            } elseif ($port_data['state'] === 'blocking') {
                $instance_stats[$iid]['blocking_count']++;
            }
        }
    }
}

// Add vendor instance port stats (including PVST)
if (!empty($instance_ports_batch)) {
    foreach ($instance_ports_batch as $port_data) {
        $iid = $port_data['stp_instance_id'];

        // Get instance info to determine type
        $instance_info = dbFetchRow('SELECT instance_key, type AS instance_type FROM stp_instances WHERE stp_instance_id = ?', [$iid]);
        if ($instance_info) {
            $instance_type = $instance_info['instance_type'];
            $instance_key = $instance_info['instance_key'];

            if (!isset($instance_stats[$iid])) {
                $name = ($instance_type === 'pvst') ? 'VLAN-' . $instance_key :
                        (($instance_type === 'msti') ? 'MSTI-' . $instance_key : 'Instance-' . $instance_key);

                $instance_stats[$iid] = [
                    'type' => $instance_type,
                    'instance_key' => $instance_key,
                    'name' => $name,
                    'port_count' => 0,
                    'forwarding_count' => 0,
                    'blocking_count' => 0
                ];
            }

            $instance_stats[$iid]['port_count']++;
            if ($port_data['state'] === 'forwarding') {
                $instance_stats[$iid]['forwarding_count']++;
            } elseif ($port_data['state'] === 'blocking') {
                $instance_stats[$iid]['blocking_count']++;
            }
        }
    }
}

// Count guard features from processing batches
if (!empty($guard_updates_batch)) {
    $guard_ports = array_merge($guard_ports, $guard_updates_batch);
}

// Build port ID mapping for RRD updates: (device_id, port_id, stp_instance_id) -> stp_port_id
// This is needed because RRD updates require the stp_port_id from the database
$port_id_map = [];
$all_port_combos = [];

// Collect unique combinations from all batch arrays
foreach ([$cist_ports_batch, $rstp_updates_batch, $mstp_cist_batch, $msti_ports_batch, $instance_ports_batch] as $batch) {
    if (!empty($batch)) {
        foreach ($batch as $port_data) {
            if (isset($port_data['device_id'], $port_data['port_id'], $port_data['stp_instance_id'])) {
                $key = $port_data['device_id'] . '_' . $port_data['port_id'] . '_' . $port_data['stp_instance_id'];
                $all_port_combos[$key] = [
                    'device_id' => $port_data['device_id'],
                    'port_id' => $port_data['port_id'],
                    'stp_instance_id' => $port_data['stp_instance_id']
                ];
            }
        }
    }
}

// Fetch stp_port_ids for all combinations in one query
if (!empty($all_port_combos)) {
    $placeholders = implode(',', array_fill(0, count($all_port_combos), '(?,?,?)'));
    $values = [];
    foreach ($all_port_combos as $combo) {
        $values[] = $combo['device_id'];
        $values[] = $combo['port_id'];
        $values[] = $combo['stp_instance_id'];
    }

    $port_id_rows = dbFetchRows("SELECT stp_port_id, device_id, port_id, stp_instance_id
                                FROM stp_ports
                                WHERE (device_id, port_id, stp_instance_id) IN ($placeholders)", $values);

    foreach ($port_id_rows as $row) {
        $key = $row['device_id'] . '_' . $row['port_id'] . '_' . $row['stp_instance_id'];
        $port_id_map[$key] = $row['stp_port_id'];
    }

    print_debug(sprintf('Built STP port ID mapping for %d port combinations', count($port_id_map)));
}

// STP BOUNCE TRACKING: Update transition counters using all port data collected this cycle
$ports_current = [];
foreach ([$cist_ports_batch, $rstp_updates_batch, $mstp_cist_batch, $msti_ports_batch, $instance_ports_batch] as $batch) {
    if (!empty($batch)) {
        foreach ($batch as $port_data) {
            if (isset($port_data['device_id'], $port_data['port_id'], $port_data['stp_instance_id'], $port_data['state'])) {
                $key = $port_data['device_id'] . '_' . $port_data['port_id'] . '_' . $port_data['stp_instance_id'];
                if (isset($port_id_map[$key])) {
                    $ports_current[] = [
                        'stp_port_id' => $port_id_map[$key],
                        'state' => stp_state_to_int($port_data['state'])
                    ];
                }
            }
        }
    }
}

if (!empty($ports_current)) {
    stp_bounce_update_all($device['device_id'], $ports_current, TRUE);
    print_debug(sprintf('Updated bounce tracking for %d STP ports', count($ports_current)));
    print_cli(' bounce:' . count($ports_current));
}

// Optional cleanup of orphaned cache entries
if (isset($GLOBALS['poller_iteration']) && $GLOBALS['poller_iteration'] % 12 === 0) {
    stp_bounce_prune_orphans($device['device_id']);
} else {
    // Fallback for systems without poller_iteration tracking
    static $cleanup_counter = 0;
    if (++$cleanup_counter % 12 === 0) {
        stp_bounce_prune_orphans($device['device_id']);
    }
}

// Calculate device-level aggregate statistics
$total_instances = count($instance_stats);
$total_ports = 0;
$total_forwarding = 0;
$total_blocking = 0;

foreach ($instance_stats as $stats) {
    $total_ports += $stats['port_count'];
    $total_forwarding += $stats['forwarding_count'];
    $total_blocking += $stats['blocking_count'];
}

// Create device-level overview RRD with aggregate data
// Create overview if we have either bridge data OR instance data
if (!empty($after) || !empty($instance_stats)) {
    // For devices without BRIDGE-MIB support, use reasonable defaults
    $top_changes = 0;
    $time_since_tc = 0;
    $root_cost = 0;

    // If we have bridge data, use it; otherwise use defaults
    $inconsistent_events = 0;
    if (!empty($after)) {
        $top_changes = $after['top_changes'] ?? 0;          // Standard dot1dStpTopChanges (authoritative)
        $time_since_tc = $after['time_since_tc_cs'] ?? 0;
        $root_cost = $after['root_cost'] ?? 0;
        $inconsistent_events = $after['inconsistent_events'] ?? 0;  // Cisco fault counters (if present)
    }

    rrdtool_update_ng($device, 'stp-overview', [
        'topologyChanges' => $top_changes,      // Keep dot1dStpTopChanges as authoritative
        'age' => $time_since_tc,  // Keep centiseconds for compatibility
        'rootPathCost' => $root_cost,
        'blockedPorts' => $total_blocking,
        'forwardingPorts' => $total_forwarding,
        'totalInstances' => $total_instances,
        'inconsistentEvts' => $inconsistent_events,  // Cisco inconsistencies (separate from TC)
    ]);
    print_cli(' overview');

    // Set graph flags for device overview - only enable topology change graphs if we have data
    if ($top_changes > 0 || $time_since_tc > 0) {
        $graphs['device_stp_topchanges'] = TRUE;
        $graphs['device_stp_tc_age'] = TRUE;
    }
    $graphs['device_stp_portcounts'] = TRUE;  // Always enable port count graphs
    if ($inconsistent_events > 0) {
        $graphs['device_stp_inconsistent'] = TRUE;
    }
}

// Update individual instance RRDs from our polling data
foreach ($instance_stats as $instance_id => $stats) {
    if ($stats['type'] === 'cist' && !empty($after)) {
        // Update CIST instance RRD with bridge-level metrics using stable identifiers
        rrdtool_update_ng($device, 'stp-instance', [
            'topologyChanges' => $after['top_changes'] ?? 0,
            'timeSinceTopChg' => $after['time_since_tc_cs'] ?? 0,
            'rootPathCost'    => $after['root_cost'] ?? 0,
            'blockedPorts'    => $stats['blocking_count'],
            'forwardingPorts' => $stats['forwarding_count'],
            'rootChanges'     => 0, // TODO: track root changes over time
        ], ['type' => $stats['type'], 'instance_key' => $stats['instance_key']]);
        print_cli(' cist');
    } elseif (in_array($stats['type'], ['pvst', 'msti', 'rstp'])) {
        // Update non-CIST instance RRDs with instance-level metrics using stable identifiers
        rrdtool_update_ng($device, 'stp-instance', [
            'topologyChanges' => 0, // Instance-level topology changes not tracked yet
            'timeSinceTopChg' => 0, // Instance-level time since TC not available
            'rootPathCost' => 0,    // Instance-level root cost not available
            'blockedPorts' => $stats['blocking_count'],
            'forwardingPorts' => $stats['forwarding_count'],
            'rootChanges' => 0, // Instance-level root changes not tracked
        ], ['type' => $stats['type'], 'instance_key' => $stats['instance_key']]);
        print_cli(' ' . $stats['type'] . '-' . $stats['instance_key']);
    }
}

// Debug: concise source counts to aid troubleshooting (only when -d)
$__dbg_counts = [
  'cist'      => isset($cist_ports_batch) ? count($cist_ports_batch) : 0,
  'rstp_ext'  => isset($rstp_updates_batch) ? count($rstp_updates_batch) : 0,
  'mstp_cist' => isset($mstp_cist_batch) ? count($mstp_cist_batch) : 0,
  'msti'      => isset($msti_ports_batch) ? count($msti_ports_batch) : 0,
  'vendor'    => isset($instance_ports_batch) ? count($instance_ports_batch) : 0,
  'guard'     => isset($guard_updates_batch) ? count($guard_updates_batch) : 0,
];
print_debug(sprintf(
  'STP poll sources: CIST=%d, RSTPext=%d, MSTP_CIST=%d, MSTI=%d, VendorPorts=%d, GuardUpdates=%d',
  $__dbg_counts['cist'], $__dbg_counts['rstp_ext'], $__dbg_counts['mstp_cist'], $__dbg_counts['msti'], $__dbg_counts['vendor'], $__dbg_counts['guard']
));
// Final concise headline with variant and timing
$__elapsed = microtime(TRUE) - $__t_stp_start;
print_cli(sprintf(' variant:%s device_id:%d time:%.2fs', strtoupper($variant_new ?: ($curr_variant ?: 'unknown')), (int)$device['device_id'], $__elapsed));

// Update port-level RRDs from port batches using stable basePort identifiers
$port_rrd_updates = 0;
foreach ([$cist_ports_batch, $rstp_updates_batch, $mstp_cist_batch, $msti_ports_batch, $instance_ports_batch] as $batch) {
    if (!empty($batch)) {
        foreach ($batch as $port_data) {
            if (isset($port_data['device_id'], $port_data['base_port'], $port_data['stp_instance_id'], $port_data['state'])) {
                // Get instance info to determine type and key
                $instance_info = dbFetchRow('SELECT instance_key, type FROM stp_instances WHERE stp_instance_id = ?', [$port_data['stp_instance_id']]);
                if (!$instance_info) continue;

                // Map state to numeric value for graphing
                switch($port_data['state']) {
                    case 'disabled': $state_numeric = 0; break;
                    case 'blocking':
                    case 'discarding': $state_numeric = 1; break;
                    case 'listening': $state_numeric = 2; break;
                    case 'learning': $state_numeric = 3; break;
                    case 'forwarding': $state_numeric = 4; break;
                    default: $state_numeric = 0; break;
                }

                // Use stable SNMP-based identifiers for RRD files
                rrdtool_update_ng($device, 'stp-port', [
                    'state' => $state_numeric,
                    'pathCost' => $port_data['path_cost'] ?? 0,
                    'transitions' => 0, // Would need to track over time
                    'bounceRate' => 0,  // Would need bounce tracking
                ], [
                    'basePort' => $port_data['base_port'],
                    'type' => $instance_info['type'],
                    'instance_key' => $instance_info['instance_key']
                ]);

                $port_rrd_updates++;
            }
        }
    }
}
if ($port_rrd_updates > 0) {
    print_cli(" ports:$port_rrd_updates");
}



echo PHP_EOL;

// Build and display STP summary table from polling data
$table_rows = [];

// Calculate totals from our collected polling data
$total_instances = count($instance_stats);
$total_ports = 0;
$total_forwarding = 0;
$total_blocking = 0;

foreach ($instance_stats as $stats) {
    $total_ports += $stats['port_count'];
    $total_forwarding += $stats['forwarding_count'];
    $total_blocking += $stats['blocking_count'];

    // Add instance row to table
    $table_row = [];
    $table_row[] = $stats['name'];
    $table_row[] = strtoupper($stats['type']);
    $table_row[] = $stats['port_count'];
    $table_row[] = $stats['forwarding_count'];
    $table_row[] = $stats['blocking_count'];

    // Format timers if available (from bridge data for CIST)
    $timers = [];
    if ($stats['type'] === 'cist' && !empty($after)) {
        if (!empty($after['hello_time_cs'])) {
            $timers[] = 'H:' . ($after['hello_time_cs'] / 100) . 's';
        }
        if (!empty($after['max_age_cs'])) {
            $timers[] = 'M:' . ($after['max_age_cs'] / 100) . 's';
        }
        if (!empty($after['fwd_delay_cs'])) {
            $timers[] = 'F:' . ($after['fwd_delay_cs'] / 100) . 's';
        }
    }
    $table_row[] = implode(' ', $timers) ?: 'Default';

    $table_rows[] = $table_row;
}

// Count guard features from processed data
$guard_enabled_ports = 0;
if (!empty($guard_updates_batch)) {
    $guard_enabled_ports = count($guard_updates_batch);
}

// Bounce statistics would need to be tracked over time
$bouncing_ports = 0; // Would need historic tracking
$total_bounces = 0;  // Would need historic tracking

if (!empty($table_rows)) {
    $headers = ['%WInstance%n', '%WType%n', '%WPorts%n', '%WForwarding%n', '%WBlocking%n', '%WTimers%n'];
    print_cli_table($table_rows, $headers);

    // Print summary statistics
    echo PHP_EOL;
    print_cli_data_field('Summary', 2);
    echo PHP_EOL;

    $summary_stats = [
        'Protocol Variant' => strtoupper($variant_new ?: 'Unknown'),
        'Total Instances' => $total_instances,
        'Total Ports' => $total_ports,
        'Forwarding Ports' => $total_forwarding,
        'Blocking Ports' => $total_blocking,
        'Guard Enabled' => $guard_enabled_ports,
        'Bouncing Ports' => $bouncing_ports,
        'Total Bounces' => $total_bounces
    ];

    foreach ($summary_stats as $label => $value) {
        printf("  %-18s %s\n", $label . ':', $value);
    }

    // Show bridge information from polled data
    if (!empty($after)) {
        echo PHP_EOL;
        print_cli_data_field('Bridge Info', 2);
        echo PHP_EOL;

        if (!empty($after['bridge_id'])) {
            printf("  %-18s %s\n", 'Bridge ID:', stp_bridge_id_str($after['bridge_id']));
        }
        if (!empty($after['designated_root'])) {
            printf("  %-18s %s\n", 'Root Bridge:', stp_bridge_id_str($after['designated_root']));
        }
        if (isset($after['root_cost'])) {
            printf("  %-18s %s\n", 'Root Cost:', $after['root_cost']);
        }
        if (isset($after['root_port'])) {
            printf("  %-18s %s\n", 'Root Port:', $after['root_port'] ?: 'None');
        }
        if (isset($after['top_changes'])) {
            printf("  %-18s %s\n", 'Topology Changes:', $after['top_changes']);
        }
    }
} else {
    echo " no instances";
}

// CLEANUP STALE ENTRIES
// Remove instances, ports, and VLAN mappings that weren't seen in this polling run

if (!empty($instance_stats)) {
    // Get list of instance IDs that were actively polled/updated this run
    $active_instance_ids = array_keys($instance_stats);
    $active_instances_sql = implode(',', array_map('intval', $active_instance_ids));

    // Remove stale instances not seen in this polling run
    $stale_instances = dbFetchColumn("SELECT stp_instance_id FROM stp_instances
                                     WHERE device_id = ? AND stp_instance_id NOT IN ($active_instances_sql)",
                                     [$device['device_id']]);

    if (!empty($stale_instances)) {
        $stale_count = count($stale_instances);
        $stale_instances_sql = implode(',', array_map('intval', $stale_instances));

        // Remove stale instance records
        dbDelete('stp_instances', "device_id = ? AND stp_instance_id IN ($stale_instances_sql)", [$device['device_id']]);

        // Remove stale ports for these instances
        dbDelete('stp_ports', "device_id = ? AND stp_instance_id IN ($stale_instances_sql)", [$device['device_id']]);

        // Remove stale VLAN mappings for these instances
        dbDelete('stp_vlan_map', "device_id = ? AND stp_instance_id IN ($stale_instances_sql)", [$device['device_id']]);

        print_debug(sprintf('Cleaned up %d stale STP instances and related data', $stale_count));
    }

    // Remove stale ports for active instances (ports that were removed from active instances)
    if (!empty($all_port_combos)) {
        $active_port_keys = [];
        foreach ($all_port_combos as $combo) {
            $active_port_keys[] = sprintf("(device_id=%d AND port_id=%d AND stp_instance_id=%d)",
                                        $combo['device_id'], $combo['port_id'], $combo['stp_instance_id']);
        }
        $active_ports_sql = implode(' OR ', $active_port_keys);

        $stale_ports = dbFetchRows("SELECT stp_port_id FROM stp_ports
                                   WHERE device_id = ? AND stp_instance_id IN ($active_instances_sql)
                                   AND NOT ($active_ports_sql)", [$device['device_id']]);

        if (!empty($stale_ports)) {
            $stale_port_ids = array_column($stale_ports, 'stp_port_id');
            $stale_ports_sql = implode(',', array_map('intval', $stale_port_ids));

            dbDelete('stp_ports', "stp_port_id IN ($stale_ports_sql)");
            print_debug(sprintf('Cleaned up %d stale STP ports', count($stale_port_ids)));
        }
    }
} else {
    // No active instances - remove all STP data for this device (except bridge record)
    $removed_instances = dbDelete('stp_instances', 'device_id = ?', [$device['device_id']]);
    $removed_ports = dbDelete('stp_ports', 'device_id = ?', [$device['device_id']]);
    $removed_vlans = dbDelete('stp_vlan_map', 'device_id = ?', [$device['device_id']]);

    if ($removed_instances || $removed_ports || $removed_vlans) {
        print_debug(sprintf('Cleaned up all STP data: %d instances, %d ports, %d VLAN mappings',
                          $removed_instances, $removed_ports, $removed_vlans));
    }
}

// FINAL CLEANUP: Remove devices with meaningless STP data
// Check if this device has bridge data but no real STP configuration
if (!empty($after)) {
    $has_meaningful_stp = (
        ($after['priority'] && $after['priority'] > 0) ||  // Has bridge priority
        ($after['root_cost'] && $after['root_cost'] > 0) ||  // Has root cost
        ($after['designated_root'] && $after['designated_root'] !== '') ||  // Has designated root
        ($after['top_changes'] && $after['top_changes'] > 0) ||  // Has topology changes
        ($total_instances > 1) ||  // Has more than just CIST
        ($total_forwarding > 0 || $total_blocking > 0)  // Has active ports
    );

    if (!$has_meaningful_stp) {
        print_debug("Device has empty STP configuration - removing meaningless entries");

        // Remove all STP-related entries for this device
        dbDelete('stp_ports', '`device_id` = ?', [$device['device_id']]);
        dbDelete('stp_instances', '`device_id` = ?', [$device['device_id']]);
        dbDelete('stp_bridge', '`device_id` = ?', [$device['device_id']]);

        print_debug(sprintf("Cleaned up meaningless STP entries for device %d", $device['device_id']));

        // Early return since we removed all STP data
        return;
    }
}
