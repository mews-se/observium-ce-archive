<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage discovery
 * @copyright  (C) Adam Armstrong
 *
 */

// Cisco PVST+ SNMP context discovery
// Tests VLAN contexts for per-VLAN STP data collection

print_debug("Testing Cisco PVST+ SNMP contexts");

// Check if device has PVST configured
$spanning_tree_type = snmp_get_oid($device, 'stpxSpanningTreeType.0', 'CISCO-STP-EXTENSIONS-MIB');
if ($spanning_tree_type !== 'pvstPlus') {
    print_debug("Device not running PVST+, skipping context discovery");
    return;
}

// Get VLANs that have port assignments (only test contexts for active VLANs)
$active_vlans = dbFetchColumn("SELECT DISTINCT vlan FROM ports_vlans WHERE device_id = ?
                               UNION
                               SELECT DISTINCT ifVlan FROM ports WHERE device_id = ? AND ifVlan > 0",
                              [$device['device_id'], $device['device_id']]);

if (empty($active_vlans)) {
    print_debug("No VLANs with port assignments found");
    return;
}

$pvst_context_data = [];

foreach ($active_vlans as $vlan_id) {
    $device_context = get_device_vlan_context($device, $vlan_id);
    if (!$device_context) {
        continue;
    }
    $device_context['snmp_timeout'] = 2;
    $device_context['snmp_retries'] = 1;

    $bridge_ports = snmpwalk_cache_oid($device_context, 'dot1dBasePortIfIndex', [], 'BRIDGE-MIB');
    if (empty($bridge_ports) || !snmp_status()) {
        continue;
    }

    $stp_rows = snmpwalk_cache_oid($device_context, 'dot1dStpPortState', [], 'BRIDGE-MIB');
    $stp_rows = snmpwalk_cache_oid($device_context, 'dot1dStpPortPathCost', $stp_rows, 'BRIDGE-MIB');
    $stp_rows = snmpwalk_cache_oid($device_context, 'dot1dStpPortPriority', $stp_rows, 'BRIDGE-MIB');
    $stp_rows = snmpwalk_cache_oid($device_context, 'dot1dStpPortDesignatedBridge', $stp_rows, 'BRIDGE-MIB');
    $stp_rows = snmpwalk_cache_oid($device_context, 'dot1dStpPortDesignatedPort', $stp_rows, 'BRIDGE-MIB');
    $stp_rows = snmpwalk_cache_oid($device_context, 'dot1dStpPortDesignatedRoot', $stp_rows, 'BRIDGE-MIB');
    $stp_rows = snmpwalk_cache_oid($device_context, 'dot1dStpPortForwardTransitions', $stp_rows, 'BRIDGE-MIB');
    $stp_rows = snmpwalk_cache_oid($device_context, 'dot1dStpPortEnable', $stp_rows, 'BRIDGE-MIB');

    if (empty($stp_rows) || !snmp_status()) {
        continue;
    }

    foreach ($stp_rows as $base_port => $stp_data) {
        $if_index = isset($bridge_ports[$base_port]['dot1dBasePortIfIndex'])
            ? (int)$bridge_ports[$base_port]['dot1dBasePortIfIndex']
            : null;
        if (!$if_index) {
            continue;
        }
        $port = get_port_by_index_cache($device, $if_index);
        if (!$port || !isset($port['port_id'])) {
            continue;
        }

        $pvst_context_data[$vlan_id][$base_port] = [
            'port_id'             => (int)$port['port_id'],
            'ifIndex'             => $if_index,
            'state'               => stp_normalize_state($stp_data['dot1dStpPortState'] ?? null),
            'path_cost'           => isset($stp_data['dot1dStpPortPathCost']) ? (int)$stp_data['dot1dStpPortPathCost'] : null,
            'priority'            => isset($stp_data['dot1dStpPortPriority']) ? (int)$stp_data['dot1dStpPortPriority'] : null,
            'designated_bridge'   => isset($stp_data['dot1dStpPortDesignatedBridge']) ? strtoupper($stp_data['dot1dStpPortDesignatedBridge']) : null,
            'designated_port'     => isset($stp_data['dot1dStpPortDesignatedPort']) ? (int)$stp_data['dot1dStpPortDesignatedPort'] : null,
            'designated_root'     => isset($stp_data['dot1dStpPortDesignatedRoot']) ? strtoupper($stp_data['dot1dStpPortDesignatedRoot']) : null,
            'forward_transitions' => isset($stp_data['dot1dStpPortForwardTransitions']) ? (int)$stp_data['dot1dStpPortForwardTransitions'] : null,
            'admin_enable'        => isset($stp_data['dot1dStpPortEnable']) ? stp_truth($stp_data['dot1dStpPortEnable']) : null
        ];
    }
}

if (!empty($pvst_context_data)) {
    set_entity_attrib('device', $device, 'stp_pvst_snapshot', safe_json_encode($pvst_context_data));
    $context_count = count($pvst_context_data);
    $port_total = array_sum(array_map('count', $pvst_context_data));
    print_cli(sprintf(' contexts:%d ports:%d', $context_count, $port_total));

    if (OBS_DEBUG) {
        $headers = ['%WVLAN%n', '%WPorts%n'];
        $rows = [];
        foreach ($pvst_context_data as $vlan_id => $ports) {
            $rows[] = [$vlan_id, count($ports)];
        }
        print_cli_table($rows, $headers);
    }
} else {
    del_entity_attrib('device', $device, 'stp_pvst_snapshot');
    print_cli(' contexts:0');
}

// EOF
