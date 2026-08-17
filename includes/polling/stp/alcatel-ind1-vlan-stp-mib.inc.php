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
 * Alcatel-Lucent AOS 5/6 STP Vendor Data Collector (IND1)
 *
 * Collects ALCATEL-IND1-VLAN-STP-MIB data and feeds it to main STP poller.
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

print_debug('Collecting Alcatel-Lucent AOS 5/6 STP extensions data (IND1)');

// Vendor MIB availability check: probe a single column OID that proves table presence
if (!snmp_test_oid($device, 'vStpIns1x1VlanNumber', 'ALCATEL-IND1-VLAN-STP-MIB') &&
    !snmp_test_oid($device, 'vlanNumber', 'ALCATEL-IND1-VLAN-STP-MIB')) {
  print_debug('ALCATEL-IND1-VLAN-STP-MIB not available (no vStpIns1x1VlanNumber/vlanNumber)');
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

// IND1: collect instance-level data
$aos_instances = snmpwalk_cache_oid($device, 'vStpInsTable', [], 'ALCATEL-IND1-VLAN-STP-MIB');
if (empty($aos_instances)) {
  print_debug('AOS IND1 STP: No vStpInsTable data available');
  return;
}

$enhanced_instances = 0;
$instance_index_to_type = [];
$instance_index_to_key  = [];

foreach ($aos_instances as $idx => $r) {
  $parts = explode('.', $idx);
  if (count($parts) !== 2) {
    print_debug("Skipping malformed AOS IND1 STP instance index: $idx");
    continue;
  }
  $bridge_mode  = (int)$parts[0]; // 1=flat, 2=onePerVlan; MSTP identified via MstiNumber
  $instance_num = (int)$parts[1];

  // Protocol spec flags
  $spec = isset($r['vStpInsProtocolSpecification']) ? (int)$r['vStpInsProtocolSpecification'] : 0;
  if ($spec === 3) { $vendor_stp_data['protocol_detection']['stp']  = true; }
  if ($spec === 4) { $vendor_stp_data['protocol_detection']['rstp'] = true; }
  if ($spec === 5) { $vendor_stp_data['protocol_detection']['mstp'] = true; }

  $msti_num = isset($r['vStpInsMstiNumber']) ? (int)$r['vStpInsMstiNumber'] : -1;
  if ($msti_num >= 0) { $vendor_stp_data['protocol_detection']['mstp'] = true; }

  $instance_type = 'cist';
  $instance_key  = 0;
  if ($msti_num > 0) {
    $instance_type = 'msti';
    $instance_key  = $msti_num;
  } elseif ($bridge_mode === 2) {
    $instance_type = 'pvst';
    $instance_key  = (isset($r['vStpIns1x1VlanNumber']) && (int)$r['vStpIns1x1VlanNumber'] > 0)
                     ? (int)$r['vStpIns1x1VlanNumber']
                     : $instance_num;
    $vendor_stp_data['protocol_detection']['pvst'] = true;
  } else {
    $instance_type = 'cist';
    $instance_key  = 0; // normalize CIST
  }

  $instance_index_to_type[$instance_num] = $instance_type;
  $instance_index_to_key[$instance_num]  = $instance_key;

  $instance_full_key = $instance_type . ':' . $instance_key;

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

  if ($spec) {
    if ($spec === 3) { $instance_data['protocol_spec'] = 'ieee8021d'; }
    elseif ($spec === 4) { $instance_data['protocol_spec'] = 'ieee8021w'; }
    elseif ($spec === 5) { $instance_data['protocol_spec'] = 'ieee8021s'; }
    else { $instance_data['protocol_spec'] = 'unknown'; }
  }

  if (!empty($instance_data)) {
    $vendor_stp_data['instance_data'][$instance_full_key] = $instance_data;
    $enhanced_instances++;
  }
}

// IND1: collect per-port operational data
$aos_ports = snmpwalk_cache_oid($device, 'vStpPortTable', [], 'ALCATEL-IND1-VLAN-STP-MIB');
if (!empty($aos_ports)) {
  foreach ($aos_ports as $idx => $pr) {
    $parts = explode('.', $idx);
    if (count($parts) < 2) { continue; }
    $inst_num  = (int)$parts[0];
    $base_port = (int)end($parts);

    $inst_type = $instance_index_to_type[$inst_num] ?? 'cist';
    $inst_key  = $instance_index_to_key[$inst_num]  ?? $inst_num;
    $inst_full = $inst_type . ':' . $inst_key;

    if (!isset($vendor_stp_data['instance_ports'][$inst_full])) {
      $vendor_stp_data['instance_ports'][$inst_full] = [];
    }

    $port_data = [];
    if (isset($pr['vStpPortState'])) { $port_data['state'] = stp_normalize_state($pr['vStpPortState']); }
    if (isset($pr['vStpPortPathCost'])) { $port_data['path_cost'] = (int)$pr['vStpPortPathCost']; }
    if (isset($pr['vStpPortPriority'])) { $port_data['priority'] = (int)$pr['vStpPortPriority']; }
    if (isset($pr['vStpPortDesignatedRoot']) && $pr['vStpPortDesignatedRoot'] !== '') {
      $port_data['designated_root'] = strtoupper($pr['vStpPortDesignatedRoot']);
    }
    if (isset($pr['vStpPortDesignatedBridge']) && $pr['vStpPortDesignatedBridge'] !== '') {
      $port_data['designated_bridge'] = strtoupper($pr['vStpPortDesignatedBridge']);
    }
    if (isset($pr['vStpPortDesignatedPtNumber'])) { $port_data['designated_port'] = (int)$pr['vStpPortDesignatedPtNumber']; }
    if (isset($pr['vStpPortForwardTransitions'])) { $port_data['forward_transitions'] = (int)$pr['vStpPortForwardTransitions']; }

    if (!empty($port_data)) {
      $vendor_stp_data['instance_ports'][$inst_full][$base_port] = $port_data;
    }
  }
  print_debug(sprintf('AOS IND1 STP: Collected per-port data for %d entries', count($aos_ports)));
} else {
  print_debug('AOS IND1 STP: No vStpPortTable data available');
}

if ($enhanced_instances > 0) {
  print_debug("Alcatel-Lucent AOS IND1 STP: Collected data for $enhanced_instances instances");
} else {
  print_debug('Alcatel-Lucent AOS IND1 STP: No instances found (vendor MIB returned no useful data)');
}

// No DB operations here; main poller ingests $vendor_stp_data
