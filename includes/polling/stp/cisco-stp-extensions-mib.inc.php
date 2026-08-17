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
 * Cisco STP Extensions Vendor Data Collector
 *
 * Collects CISCO-STP-EXTENSIONS-MIB data and feeds it to main STP poller.
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

print_debug('Collecting Cisco STP extensions data');

// Vendor MIB availability check: probe specific column OIDs
$cisco_has_faststart = snmp_test_oid($device, 'stpxFastStartBpduGuardEnable', 'CISCO-STP-EXTENSIONS-MIB');
$cisco_has_pvst      = snmp_test_oid($device, 'stpxPVSTVlanEnable', 'CISCO-STP-EXTENSIONS-MIB');
if (!$cisco_has_faststart && !$cisco_has_pvst) {
  print_debug('CISCO-STP-EXTENSIONS-MIB not available (no FastStart/PVST columns)');
  return;
}

// Initialize vendor data structure for this MIB
if (!isset($vendor_stp_data)) {
  $vendor_stp_data = [
    'port_features' => ['guard' => [], 'inconsistencies' => []],
    'instance_ports' => [],
    'instance_data' => [],
    'protocol_detection' => ['stp' => false, 'rstp' => false, 'mstp' => false, 'pvst' => false],
    'bridge_data' => []
  ];
}

// CISCO INCONSISTENCY COUNTERS  
// These are NOT topology changes - they're STP inconsistency/fault counters
$cisco_inconsistency_counters = snmp_get_multi_oid($device, [
  'stpxInconsistentState.0',           // General STP inconsistencies
  'stpxRootInconsistentState.0',       // Root inconsistencies  
  'stpxLoopInconsistentState.0'        // Loop inconsistencies
], [], 'CISCO-STP-EXTENSIONS-MIB');

// Process inconsistency data as separate fault metrics (NOT topology changes)
if (!empty($cisco_inconsistency_counters['0'])) {
  $inconsistency_data = $cisco_inconsistency_counters['0'];

  // Calculate total inconsistency events - these are fault counters, not TC events
  $total_inconsistencies = 0;
  $total_inconsistencies += isset($inconsistency_data['stpxInconsistentState']) ? (int)$inconsistency_data['stpxInconsistentState'] : 0;
  $total_inconsistencies += isset($inconsistency_data['stpxRootInconsistentState']) ? (int)$inconsistency_data['stpxRootInconsistentState'] : 0;
  $total_inconsistencies += isset($inconsistency_data['stpxLoopInconsistentState']) ? (int)$inconsistency_data['stpxLoopInconsistentState'] : 0;

  if ($total_inconsistencies > 0) {
    // Store as separate inconsistency counter, NOT as topology changes
    $vendor_stp_data['bridge_data']['inconsistent_events'] = $total_inconsistencies;
    print_debug(sprintf('Collected Cisco STP inconsistency counters: %d events (NOT topology changes)', $total_inconsistencies));
  }
}

// CISCO GUARD FEATURES
// Collect BPDU Guard and FastStart features for basic STP devices
$cisco_guards = snmpwalk_cache_oid($device, 'stpxFastStartPortTable', [], 'CISCO-STP-EXTENSIONS-MIB');
$cisco_bpdu_guard = snmpwalk_cache_oid($device, 'stpxFastStartBpduGuardTable', $cisco_guards, 'CISCO-STP-EXTENSIONS-MIB');

foreach ($cisco_bpdu_guard as $basePort => $r) {
  $guard_features = [];

  if (isset($r['stpxFastStartBpduGuardEnable']) && stp_truth($r['stpxFastStartBpduGuardEnable'])) {
    $guard_features[] = 'bpdu';
  }
  // Could add other Cisco guard types here (root guard, loop guard, etc.)

  if (!empty($guard_features)) {
    $vendor_stp_data['port_features']['guard'][(int)$basePort] = $guard_features;
  }
}

// CISCO BASIC STP GUARD FEATURES (for non-PVST devices)
// This section handles devices running basic STP that still have Cisco guard features

{
  print_debug('Collecting additional Cisco guard features');

  // This is additional guard data beyond what was collected above
  // Only collect if we haven't already found comprehensive guard data
  $existing_guard_ports = count($vendor_stp_data['port_features']['guard']);
  if ($existing_guard_ports < 5) { // Arbitrary threshold - if we have few guards, try basic collection
    $basic_guards = snmpwalk_cache_oid($device, 'stpxFastStartPortTable', [], 'CISCO-STP-EXTENSIONS-MIB');
    $basic_bpdu_guard = snmpwalk_cache_oid($device, 'stpxFastStartBpduGuardTable', $basic_guards, 'CISCO-STP-EXTENSIONS-MIB');

    foreach ($basic_bpdu_guard as $basePort => $r) {
      $existing_guards = $vendor_stp_data['port_features']['guard'][(int)$basePort] ?? [];

      if (isset($r['stpxFastStartBpduGuardEnable']) && stp_truth($r['stpxFastStartBpduGuardEnable'])) {
        $existing_guards[] = 'bpdu';
      }

      if (!empty($existing_guards)) {
        $vendor_stp_data['port_features']['guard'][(int)$basePort] = array_unique($existing_guards);
      }
    }

    print_debug(sprintf('Added basic Cisco guard features for %d additional ports',
      count($vendor_stp_data['port_features']['guard']) - $existing_guard_ports));
  }
}

// CISCO BRIDGE ASSURANCE AND ADVANCED GUARDS
$bridge_assurance = snmpwalk_cache_oid($device, 'stpxSMSTPortConfigTable', [], 'CISCO-STP-EXTENSIONS-MIB');
foreach ($bridge_assurance as $basePort => $r) {
  $port_guards = $vendor_stp_data['port_features']['guard'][(int)$basePort] ?? [];

  // Bridge Assurance detection
  if (isset($r['stpxSMSTPortAdminBridgeAssurance']) && stp_truth($r['stpxSMSTPortAdminBridgeAssurance'])) {
    $port_guards[] = 'bridge-assurance';
  }

  // Root Guard detection
  if (isset($r['stpxRootGuardConfigEnabled']) && stp_truth($r['stpxRootGuardConfigEnabled'])) {
    $port_guards[] = 'root';
  }

  // Loop Guard detection
  if (isset($r['stpxLoopGuardConfigEnabled']) && stp_truth($r['stpxLoopGuardConfigEnabled'])) {
    $port_guards[] = 'loop';
  }

  if (!empty($port_guards)) {
    $vendor_stp_data['port_features']['guard'][(int)$basePort] = array_unique($port_guards);
  }
}

print_debug(sprintf('Collected Cisco guard features for %d ports', count($vendor_stp_data['port_features']['guard'])));

// Populate PVST state from discovery snapshot (context walks done in discovery)
$pvst_snapshot_json = get_entity_attrib('device', $device, 'stp_pvst_snapshot');
if (!safe_empty($pvst_snapshot_json)) {
  $pvst_snapshot = safe_json_decode($pvst_snapshot_json, TRUE);

  if (is_array($pvst_snapshot)) {
    $snapshot_ports = 0;

    foreach ($pvst_snapshot as $vlan_id => $ports_state) {
        $device_context = get_device_vlan_context($device, $vlan_id);
        if (!$device_context) {
            continue;
        }
        $device_context['snmp_timeout'] = 2;
        $device_context['snmp_retries'] = 1;

      $instance_key = 'pvst:' . (int)$vlan_id;

      if (!isset($vendor_stp_data['instance_ports'][$instance_key])) {
        $vendor_stp_data['instance_ports'][$instance_key] = [];
      }

      foreach ((array)$ports_state as $base_port => $row) {
        $base_port = (int)$base_port;
        if ($base_port <= 0) {
          continue;
        }

        $port_data = [];

        if (isset($row['state'])) {
          $port_data['state'] = stp_normalize_state($row['state']);
        }
        if (isset($row['path_cost'])) {
          $port_data['path_cost'] = (int)$row['path_cost'];
        }
        if (isset($row['priority'])) {
          $port_data['priority'] = (int)$row['priority'];
        }
        if (isset($row['designated_bridge'])) {
          $port_data['designated_bridge'] = strtoupper($row['designated_bridge']);
        }
        if (isset($row['designated_port'])) {
          $port_data['designated_port'] = (int)$row['designated_port'];
        }
        if (isset($row['designated_root'])) {
          $port_data['designated_root'] = strtoupper($row['designated_root']);
        }
        if (isset($row['forward_transitions'])) {
          $port_data['forward_transitions'] = (int)$row['forward_transitions'];
        }
        if (isset($row['admin_enable'])) {
          $port_data['admin_enable'] = (int)$row['admin_enable'];
        }
        if (isset($row['inconsistent'])) {
          $port_data['inconsistent'] = (int)$row['inconsistent'];
          if ($port_data['inconsistent']) {
            $vendor_stp_data['port_features']['inconsistencies'][(int)$base_port] = TRUE;
          }
        }

        // Prefer explicit port_id from snapshot; fall back to base_port mapping
        if (isset($row['port_id'])) {
          $port_data['port_id'] = (int)$row['port_id'];
        } elseif (isset($base_to_portid[$base_port])) {
          $port_data['port_id'] = (int)$base_to_portid[$base_port];
        }

        if (!empty($port_data)) {
          $vendor_stp_data['instance_ports'][$instance_key][$base_port] = $port_data;
          $snapshot_ports++;
        }
      }
      // Refresh forwarding state via lightweight context GETs

      $base_ports = array_keys((array)$ports_state);
      if (!empty($base_ports)) {
        $oids = [];
        foreach ($base_ports as $base_port_idx) {
          $base_port_idx = (int)$base_port_idx;
          if ($base_port_idx > 0) {
            $oids[] = 'dot1dStpPortState.' . $base_port_idx;
          }
        }

        if (!empty($oids)) {
          $states = snmp_get_multi_oid($device_context, $oids, [], 'BRIDGE-MIB');
          if (!empty($states) && snmp_status()) {
            foreach ($states as $oid => $value) {
              if (preg_match('/dot1dStpPortState\.(\d+)/', $oid, $m)) {
                $base_idx = (int)$m[1];
                if (isset($vendor_stp_data['instance_ports'][$instance_key][$base_idx])) {
                  $vendor_stp_data['instance_ports'][$instance_key][$base_idx]['state'] = stp_normalize_state($value);
                }
              }
            }
          }
        }
      }
    }

    print_debug(sprintf('Loaded PVST snapshot for %d VLANs (%d ports)', count($pvst_snapshot), $snapshot_ports));
  }
}

// CISCO PVST VLAN INSTANCES
// If we didn't get port data but have VLAN enable data, create instances from VLANs
if (empty($vendor_stp_data['instance_ports'])) {
  $cisco_data = snmpwalk_cache_oid($device, 'stpxPVSTVlanEnable', [], 'CISCO-STP-EXTENSIONS-MIB');

  foreach ($cisco_data as $vlan => $data) {
    if (isset($data['stpxPVSTVlanEnable']) && $data['stpxPVSTVlanEnable'] === 'enabled') {
      $instance_key = "pvst:$vlan";

      // Create empty instance (no port data available)
      if (!isset($vendor_stp_data['instance_ports'][$instance_key])) {
        $vendor_stp_data['instance_ports'][$instance_key] = [];
      }

      // Add basic instance data
      if (!isset($vendor_stp_data['instance_data'][$instance_key])) {
        $vendor_stp_data['instance_data'][$instance_key] = [
          'name' => "VLAN-$vlan"
        ];
      }
    }
  }

  if (!empty($vendor_stp_data['instance_ports'])) {
    print_debug(sprintf('Created %d PVST instances from VLAN enable data', count($vendor_stp_data['instance_ports'])));
  }
}

// Signal PVST detection if we found PVST data
if (!empty($vendor_stp_data['instance_ports'])) {
  $vendor_stp_data['protocol_detection']['pvst'] = true;
}

print_debug('Cisco STP data collection complete');
