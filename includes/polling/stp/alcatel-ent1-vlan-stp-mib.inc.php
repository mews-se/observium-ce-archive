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
 * Alcatel-Lucent AOS 7/8+ STP Vendor Data Collector (ENT1)
 *
 * Collects ALCATEL-ENT1-VLAN-STP-MIB data and feeds it to main STP poller.
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

print_debug('Collecting Alcatel-Lucent AOS 7/8+ STP extensions data (ENT1)');

// Vendor MIB availability check: probe a single column OID that proves table presence
if (!snmp_test_oid($device, 'vlanNumber', 'ALCATEL-ENT1-VLAN-STP-MIB') &&
    !snmp_test_oid($device, 'vStpIns1x1VlanNumber', 'ALCATEL-ENT1-VLAN-STP-MIB')) {
  print_debug('ALCATEL-ENT1-VLAN-STP-MIB not available (no vlanNumber/vStpIns1x1VlanNumber)');
  return;
}

// Initialize vendor data structure if needed
if (!isset($vendor_stp_data)) {
  $vendor_stp_data = [
    'port_features' => ['guard' => [], 'inconsistencies' => []],
    'instance_ports' => [],
    'instance_data' => [],
    'protocol_detection' => ['stp' => false, 'rstp' => false, 'mstp' => false, 'pvst' => false]
  ];
}

// ENT1: collect instance-level data only (operational per-port data is not exposed in this MIB)
$aos_instances = snmpwalk_cache_oid($device, 'vStpInsTable', [], 'ALCATEL-ENT1-VLAN-STP-MIB');
if (empty($aos_instances)) {
  print_debug('AOS ENT1 STP: No vStpInsTable data available');
  return;
}

$enhanced_instances = 0;
$instance_index_to_type = [];
$instance_index_to_key  = [];

foreach ($aos_instances as $idx => $r) {
  // Index format: bridgeMode.instanceNumber
  $parts = explode('.', $idx);
  if (count($parts) !== 2) {
    print_debug("Skipping malformed AOS ENT1 STP instance index: $idx");
    continue;
  }

  $bridge_mode  = (int)$parts[0]; // 1=flat, 2=onePerVlan, MSTP handled via vStpInsMstiNumber
  $instance_num = (int)$parts[1];

  // Determine protocol spec and MSTI number when available
  $spec = isset($r['vStpInsProtocolSpecification']) ? (int)$r['vStpInsProtocolSpecification'] : 0;
  if ($spec === 3) { $vendor_stp_data['protocol_detection']['stp']  = true; }
  if ($spec === 4) { $vendor_stp_data['protocol_detection']['rstp'] = true; }
  if ($spec === 5) { $vendor_stp_data['protocol_detection']['mstp'] = true; }

  $msti_num = isset($r['vStpInsMstiNumber']) ? (int)$r['vStpInsMstiNumber'] : -1;
  if ($msti_num >= 0) { $vendor_stp_data['protocol_detection']['mstp'] = true; }

  // Determine instance type and key with correct MSTP/PVST mapping
  $instance_type = 'cist';
  $instance_key  = 0;
  if ($msti_num > 0) {
    // MSTP instance
    $instance_type = 'msti';
    $instance_key  = $msti_num;
  } elseif ($bridge_mode === 2) {
    // PVST (one per VLAN)
    $instance_type = 'pvst';
    $instance_key  = (isset($r['vStpIns1x1VlanNumber']) && (int)$r['vStpIns1x1VlanNumber'] > 0)
                     ? (int)$r['vStpIns1x1VlanNumber']
                     : $instance_num;
    $vendor_stp_data['protocol_detection']['pvst'] = true;
  } else {
    // CIST
    $instance_type = 'cist';
    $instance_key  = 0; // normalize to single CIST
  }

  $instance_full_key = $instance_type . ':' . $instance_key;
  // Track mapping for port-table correlation later
  $instance_index_to_type[$instance_num] = $instance_type;
  $instance_index_to_key[$instance_num]  = $instance_key;

  // Instance-level enhanced data
  $instance_data = [];

  if (isset($r['vStpInsDesignatedRoot']) && $r['vStpInsDesignatedRoot'] !== '') {
    $instance_data['designated_root'] = strtoupper($r['vStpInsDesignatedRoot']);
  }
  if (isset($r['vStpInsRootCost'])) {
    $instance_data['root_cost'] = (int)$r['vStpInsRootCost'];
  }
  if (isset($r['vStpInsRootPortNumber']) && (int)$r['vStpInsRootPortNumber'] > 0) {
    $instance_data['root_port'] = (int)$r['vStpInsRootPortNumber'];
  }
  // Next-best (alternate) root metrics (vendor value → generic fields)
  if (isset($r['vStpInsNextBestRootCost'])) {
    $instance_data['alt_root_cost'] = (int)$r['vStpInsNextBestRootCost'];
  }
  if (isset($r['vStpInsNextBestRootPortNumber'])) {
    $instance_data['alt_root_port'] = (int)$r['vStpInsNextBestRootPortNumber'];
  }
  if (isset($r['vStpInsTopChanges'])) {
    $instance_data['top_changes'] = (int)$r['vStpInsTopChanges'];
  }
  if (isset($r['vStpInsTimeSinceTopologyChange'])) {
    $instance_data['time_since_tc_cs'] = (int)$r['vStpInsTimeSinceTopologyChange'];
  }
  if (isset($r['vStpInsMaxAge'])) {
    $instance_data['max_age_cs'] = (int)$r['vStpInsMaxAge'];
  }
  if (isset($r['vStpInsHelloTime'])) {
    $instance_data['hello_time_cs'] = (int)$r['vStpInsHelloTime'];
  }
  if (isset($r['vStpInsForwardDelay'])) {
    $instance_data['fwd_delay_cs'] = (int)$r['vStpInsForwardDelay'];
  }
  if (isset($r['vStpInsHoldTime'])) {
    $instance_data['hold_time_cs'] = (int)$r['vStpInsHoldTime'];
  }

  // Protocol specification (store for reference)
  if ($spec) {
    if ($spec === 3) { $instance_data['protocol_spec'] = 'ieee8021d'; }
    elseif ($spec === 4) { $instance_data['protocol_spec'] = 'ieee8021w'; }
    elseif ($spec === 5) { $instance_data['protocol_spec'] = 'ieee8021s'; }
    else { $instance_data['protocol_spec'] = 'unknown'; }
  }
  // Vendor regional root for CIST, if exposed
  if (isset($r['vStpInsCistRegionalRootId']) && $instance_type === 'cist') {
    $instance_data['regional_root'] = snmp_hexstring($r['vStpInsCistRegionalRootId']);
  }

  if (!empty($instance_data)) {
    $vendor_stp_data['instance_data'][$instance_full_key] = $instance_data;
    $enhanced_instances++;
  }
}

if ($enhanced_instances > 0) {
  print_debug("Alcatel-Lucent AOS ENT1 STP: Collected data for $enhanced_instances instances");
} else {
  print_debug('Alcatel-Lucent AOS ENT1 STP: No instances found (vendor MIB returned no useful data)');
}

// Try to augment PVST per-instance port membership/state using VLAN-MGR vpaTable (if available)
// This helps ENT1 devices that do not expose operational per-port STP tables
// Dependencies: base_to_if map is built by main poller; use it to resolve basePort from ifIndex
if (!empty($vendor_stp_data) && function_exists('snmpwalk_cache_oid')) {
  // Build reverse map if available
  $if_to_base = [];
  if (!empty($base_to_if) && is_array($base_to_if)) {
    foreach ($base_to_if as $base => $ifIdx) { $if_to_base[(int)$ifIdx] = (int)$base; }
  }

  $vpa = snmpwalk_cache_oid($device, 'vpaTable', [], 'ALCATEL-ENT1-VLAN-MGR-MIB');
  if (!empty($vpa)) {
    $augmented = 0;
    foreach ($vpa as $idx => $vr) {
      // Index is vpaVlanNumber.ifIndex; prefer explicit fields when present
      $vlan = isset($vr['vpaVlanNumber']) ? (int)$vr['vpaVlanNumber'] : (int)explode('.', $idx)[0];
      $ifIndex = isset($vr['vpaIfIndex']) ? (int)$vr['vpaIfIndex'] : (int)explode('.', $idx)[1];
      if ($vlan <= 0 || $ifIndex <= 0) continue;

      // Map ifIndex to basePort for consistent handling in main poller
      $basePort = $if_to_base[$ifIndex] ?? null;
      if ($basePort === null) continue; // cannot map, skip

      // State mapping: forwarding(0), blocking(1), inactive(2), invalid(3), dhlBlocking(4)
      $state = 'unknown';
      if (isset($vr['vpaState'])) {
        $vs = (int)$vr['vpaState'];
        if ($vs === 0) $state = 'forwarding';
        elseif ($vs === 1 || $vs === 4) $state = 'blocking';
        elseif ($vs === 2) $state = 'disabled';
      }

      $inst_key = 'pvst:' . $vlan;
      if (!isset($vendor_stp_data['instance_ports'][$inst_key])) {
        $vendor_stp_data['instance_ports'][$inst_key] = [];
      }
      // Only set if not already present from other sources
      if (!isset($vendor_stp_data['instance_ports'][$inst_key][$basePort])) {
        $vendor_stp_data['instance_ports'][$inst_key][$basePort] = [ 'state' => $state ];
        $augmented++;
      }
    }
    if ($augmented > 0) {
      $vendor_stp_data['protocol_detection']['pvst'] = true;
      print_debug(sprintf('AOS ENT1 STP: Augmented PVST ports from VLAN-MGR for %d entries', $augmented));
    }
  }
}

// Per-instance per-port operational data (preferred when available)
$ins_ports = snmpwalk_cache_oid($device, 'vStpInsPortTable', [], 'ALCATEL-ENT1-VLAN-STP-MIB');
if (!empty($ins_ports)) {
  $count = 0;
  foreach ($ins_ports as $idx => $pr) {
    // index: bridgeMode.instanceNumber.basePort
    $parts = explode('.', $idx);
    if (count($parts) < 3) continue;
    $bridge_mode   = (int)$parts[0];
    $instance_num  = (int)$parts[1];
    $base_port     = (int)end($parts);
    if ($base_port <= 0) continue;

    $inst_type = $instance_index_to_type[$instance_num] ?? (($bridge_mode === 2) ? 'pvst' : 'cist');
    $inst_key  = $instance_index_to_key[$instance_num]  ?? (($bridge_mode === 2) ? $instance_num : 0);
    $inst_full = $inst_type . ':' . $inst_key;

    if (!isset($vendor_stp_data['instance_ports'][$inst_full])) {
      $vendor_stp_data['instance_ports'][$inst_full] = [];
    }

    $port_data = [];
    if (isset($pr['vStpInsPortState'])) {
      $port_data['state'] = stp_normalize_state($pr['vStpInsPortState']);
    }
    if (isset($pr['vStpInsPortPathCost'])) {
      $port_data['path_cost'] = (int)$pr['vStpInsPortPathCost'];
    }
    if (isset($pr['vStpInsPortPriority'])) {
      $port_data['priority'] = (int)$pr['vStpInsPortPriority'];
    }
    if (isset($pr['vStpInsPortDesignatedRoot']) && $pr['vStpInsPortDesignatedRoot'] !== '') {
      $port_data['designated_root'] = strtoupper($pr['vStpInsPortDesignatedRoot']);
    }
    if (isset($pr['vStpInsPortDesignatedBridge']) && $pr['vStpInsPortDesignatedBridge'] !== '') {
      $port_data['designated_bridge'] = strtoupper($pr['vStpInsPortDesignatedBridge']);
    }
    if (isset($pr['vStpInsPortDesignatedPtNumber'])) {
      $port_data['designated_port'] = (int)$pr['vStpInsPortDesignatedPtNumber'];
    }
    if (isset($pr['vStpInsPortForwardTransitions'])) {
      $port_data['forward_transitions'] = (int)$pr['vStpInsPortForwardTransitions'];
    }
    if (isset($pr['vStpInsPortRole'])) {
      $port_data['role'] = stp_normalize_role($pr['vStpInsPortRole']);
    }
    if (isset($pr['vStpInsPortAdminEdge'])) {
      $port_data['admin_edge'] = stp_truth($pr['vStpInsPortAdminEdge']);
    }
    if (isset($pr['vStpInsPortAutoEdge'])) {
      $port_data['oper_edge'] = stp_truth($pr['vStpInsPortAutoEdge']);
    }
    if (isset($pr['vStpInsPortOperConnectionType'])) {
      // map to point2point: true/false/unknown
      $val = strtolower((string)$pr['vStpInsPortOperConnectionType']);
      if (is_numeric($val)) {
        $i = (int)$val; $val = ($i === 2) ? 'true' : (($i === 1) ? 'false' : 'unknown');
      }
      $port_data['point2point'] = in_array($val, ['true','false'], true) ? $val : 'unknown';
    }

    if (!empty($port_data)) {
      $vendor_stp_data['instance_ports'][$inst_full][$base_port] = $port_data;
      $count++;
    }
  }
  print_debug(sprintf('AOS ENT1 STP: Collected per-instance port data for %d entries', $count));
}

// Try to augment PVST per-instance port membership/state using VLAN-MGR vpaTable (if available)
// This helps ENT1 devices that do not expose operational per-port STP tables
// Dependencies: base_to_if map is built by main poller; use it to resolve basePort from ifIndex
if (!empty($vendor_stp_data) && function_exists('snmpwalk_cache_oid')) {
  // Build reverse map if available
  $if_to_base = [];
  if (!empty($base_to_if) && is_array($base_to_if)) {
    foreach ($base_to_if as $base => $ifIdx) { $if_to_base[(int)$ifIdx] = (int)$base; }
  }

  $vpa = snmpwalk_cache_oid($device, 'vpaTable', [], 'ALCATEL-ENT1-VLAN-MGR-MIB');
  if (!empty($vpa)) {
    $augmented = 0;
    foreach ($vpa as $idx => $vr) {
      // Index is vpaVlanNumber.ifIndex; prefer explicit fields when present
      $vlan = isset($vr['vpaVlanNumber']) ? (int)$vr['vpaVlanNumber'] : (int)explode('.', $idx)[0];
      $ifIndex = isset($vr['vpaIfIndex']) ? (int)$vr['vpaIfIndex'] : (int)explode('.', $idx)[1];
      if ($vlan <= 0 || $ifIndex <= 0) continue;

      // Map ifIndex to basePort for consistent handling in main poller
      $basePort = $if_to_base[$ifIndex] ?? null;
      if ($basePort === null) continue; // cannot map, skip

      // State mapping: forwarding(0), blocking(1), inactive(2), invalid(3), dhlBlocking(4)
      $state = 'unknown';
      if (isset($vr['vpaState'])) {
        $vs = (int)$vr['vpaState'];
        if ($vs === 0) $state = 'forwarding';
        elseif ($vs === 1 || $vs === 4) $state = 'blocking';
        elseif ($vs === 2) $state = 'disabled';
      }

      $inst_key = 'pvst:' . $vlan;
      if (!isset($vendor_stp_data['instance_ports'][$inst_key])) {
        $vendor_stp_data['instance_ports'][$inst_key] = [];
      }
      // Only set if not already present from other sources
      if (!isset($vendor_stp_data['instance_ports'][$inst_key][$basePort])) {
        $vendor_stp_data['instance_ports'][$inst_key][$basePort] = [ 'state' => $state ];
        $augmented++;
      }
    }
    if ($augmented > 0) {
      $vendor_stp_data['protocol_detection']['pvst'] = true;
      print_debug(sprintf('AOS ENT1 STP: Augmented PVST ports from VLAN-MGR for %d entries', $augmented));
    }
  }
}

// Loop Guard: map to guard set using port config table
$portcfg = snmpwalk_cache_oid($device, 'vStpPortConfigTable', [], 'ALCATEL-ENT1-VLAN-STP-MIB');
if (!empty($portcfg)) {
  foreach ($portcfg as $idx => $pr) {
    $ifIndex = isset($pr['vStpPortConfigIfIndex']) ? (int)$pr['vStpPortConfigIfIndex'] : (int)$idx;
    if ($ifIndex <= 0) continue;
    if (isset($pr['vStpPortConfigLoopGuard']) && stp_truth($pr['vStpPortConfigLoopGuard'])) {
      // Stage as ifIndex; main poller will convert to basePort
      $vendor_stp_data['port_features']['guard_ifindex'][$ifIndex][] = 'loop';
    }
  }
}