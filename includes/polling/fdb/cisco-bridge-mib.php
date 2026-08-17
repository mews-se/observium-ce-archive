<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage poller
 * @copyright  (C) Adam Armstrong
 *
 */

// NOTE. Do not include in mib way! Directly include.

if (!safe_empty($fdbs) || !is_device_mib($device, 'Q-BRIDGE-MIB')) {
    // Really there is BRIDGE-MIB, but as in old way we check for Q-BRIDGE-MIB
    return;
}

// Fetch list of active VLANs (with vlan context exist)
$cisco_vlans = [];
$sql = 'SELECT DISTINCT `vlan_vlan` FROM `vlans` WHERE `device_id` = ? AND `vlan_context` = ? AND (`vlan_status` = ? OR `vlan_status` = ?)';
foreach (dbFetchRows($sql, [ $device['device_id'], 1, 'active', 'operational']) as $entry) {
    $cisco_vlans[$entry['vlan_vlan']] = $entry['vlan_name'];
}

// I think this is global, not per-VLAN. (in normal world..)
// But NOPE, this is Cisco way (probably for pvst) @mike
// See: https://jira.observium.org/browse/OBS-2813
//
// From same device example default and vlan 103:
// snmpbulkwalk -v2c community -m BRIDGE-MIB -M /srv/observium/mibs/rfc:/srv/observium/mibs/net-snmp sw-1917 dot1dBasePortIfIndex
//BRIDGE-MIB::dot1dBasePortIfIndex.49 = INTEGER: 10101
//BRIDGE-MIB::dot1dBasePortIfIndex.50 = INTEGER: 10102
// snmpbulkwalk -v2c community@103 -m BRIDGE-MIB -M /srv/observium/mibs/rfc:/srv/observium/mibs/net-snmp sw-1917 dot1dBasePortIfIndex
//BRIDGE-MIB::dot1dBasePortIfIndex.1 = INTEGER: 10001
//BRIDGE-MIB::dot1dBasePortIfIndex.3 = INTEGER: 10003
//BRIDGE-MIB::dot1dBasePortIfIndex.4 = INTEGER: 10004
//...
// But I will try to pre-cache, this fetch port association for default (1) vlan only!
$dot1dBasePort_table = [];
$dot1dBasePortIfIndex[1] = snmp_cache_table($device, 'dot1dBasePortIfIndex', [], 'BRIDGE-MIB');
foreach ($dot1dBasePortIfIndex[1] as $base_port => $data) {
    $dot1dBasePort_table[$base_port] = $port_ifIndex_table[$data['dot1dBasePortIfIndex']];
}

/**
 * Cache dot1dBasePort to ifIndex associations for a specific VLAN context
 * This is needed because Cisco stores per-VLAN port mappings
 *
 * @param array $device_context Device array with VLAN context
 * @param int $vlan VLAN ID
 * @param array &$dot1dBasePort_table Reference to port mapping table
 * @param array &$dot1dBasePortIfIndex Reference to cached VLAN mappings
 * @param array $port_ifIndex_table Port index lookup table
 * @return bool TRUE if cache was updated, FALSE if already cached
 */
if (!function_exists('cisco_cache_port_mapping')) {
    function cisco_cache_port_mapping($device_context, $vlan, &$dot1dBasePort_table, &$dot1dBasePortIfIndex, $port_ifIndex_table) {

        if (isset($dot1dBasePortIfIndex[$vlan])) {
            // Already cached for this VLAN
            return FALSE;
        }

        print_debug("Cache dot1dBasePort -> IfIndex association table by vlan $vlan");

        // Walk port association for this vlan context
        $dot1dBasePortIfIndex[$vlan] = snmpwalk_cache_oid($device_context, 'dot1dBasePortIfIndex', [], 'BRIDGE-MIB');

        if (!is_null($dot1dBasePortIfIndex[$vlan])) {
            foreach ($dot1dBasePortIfIndex[$vlan] as $base_port => $data) {
                $dot1dBasePort_table[$base_port] = $port_ifIndex_table[$data['dot1dBasePortIfIndex']];
            }
            return TRUE;
        } else {
            // Prevent rewalk in cycle if empty output
            $dot1dBasePortIfIndex[$vlan] = FALSE;
            return FALSE;
        }
    }
}

// NXOS support both MIBS for FDB entries (with per vlan contexts),
// but prefer Q-BRIDGE-MIB for single snmpwalk instead per VLAN
if ($device['os'] === 'nxos') {
    // NOTE. NXOS store port associations in per vlan contexts
    foreach (snmpwalk_cache_twopart_oid($device, 'dot1qTpFdbEntry', [], 'Q-BRIDGE-MIB', NULL, OBS_SNMP_ALL_TABLE) as $vlan => $vlan_entry) {
        foreach ($vlan_entry as $mac => $entry) {
            $mac       = mac_zeropad($mac);
            $base_port = $entry['dot1qTpFdbPort'];

            // Cache port mapping for this VLAN if needed
            if (!isset($dot1dBasePort_table[$base_port]) && !isset($dot1dBasePortIfIndex[$vlan]) &&
                isset($cisco_vlans[$vlan])) { // Check if vlan context already exist

                $device_context = get_device_vlan_context($device, $vlan);
                cisco_cache_port_mapping($device_context, $vlan, $dot1dBasePort_table, $dot1dBasePortIfIndex, $port_ifIndex_table);
            }

            // Resolve port with fallback strategy
            if (isset($dot1dBasePort_table[$base_port])) {
                $fdb_port = $dot1dBasePort_table[$base_port];
            } elseif (isset($port_ifIndex_table[$base_port])) {
                $fdb_port = $port_ifIndex_table[$base_port];
            } else {
                // Fallback - create minimal port reference
                $fdb_port = [ 'ifIndex' => $base_port ];
            }

            $fdbs[$vlan][$mac] = [
                'port_id'    => $fdb_port['port_id'],
                'port_index' => $fdb_port['ifIndex'],
                'fdb_status' => $entry['dot1qTpFdbStatus']
            ];
        }
    }

    if (!safe_empty($fdbs)) {
        // NXOS fdb entries polled by simple Q-BRIDGE-MIB
        return;
    }
}

foreach ($cisco_vlans as $vlan => $vlan_name) {

    // Set per-VLAN context
    $device_context = get_device_vlan_context($device, $vlan);
    if (!$device_context) {
        continue;
    }
    //$device_context['snmp_retries'] = 1;         // Set retries to 1 for speedup walking

    //dot1dTpFdbAddress[0:7:e:6d:55:41] 0:7:e:6d:55:41
    //dot1dTpFdbPort[0:7:e:6d:55:41] 28
    //dot1dTpFdbStatus[0:7:e:6d:55:41] learned
    $dot1dTpFdbEntry_table = snmpwalk_cache_oid($device_context, 'dot1dTpFdbEntry', [], 'BRIDGE-MIB', NULL, OBS_SNMP_ALL_TABLE);

    if (!snmp_status()) {
        // Continue if no entries for vlan
        unset($device_context);
        continue;
    }

    foreach ($dot1dTpFdbEntry_table as $mac => $entry) {
        $mac      = mac_zeropad($mac);
        $base_port = $entry['dot1dTpFdbPort'];

        // Cache port mapping for this VLAN if needed
        if (!isset($dot1dBasePort_table[$base_port]) && !isset($dot1dBasePortIfIndex[$vlan])) {
            cisco_cache_port_mapping($device_context, $vlan, $dot1dBasePort_table, $dot1dBasePortIfIndex, $port_ifIndex_table);
        }

        // Resolve port with fallback strategy
        if (isset($dot1dBasePort_table[$base_port])) {
            $fdb_port = $dot1dBasePort_table[$base_port];
        } elseif (isset($port_ifIndex_table[$base_port])) {
            $fdb_port = $port_ifIndex_table[$base_port];
        } else {
            // Fallback - create minimal port reference
            $fdb_port = [ 'ifIndex' => $base_port ];
        }

        $fdbs[$vlan][$mac] = [
            'port_id'    => $fdb_port['port_id'],
            'port_index' => $fdb_port['ifIndex'],
            'fdb_status' => $entry['dot1dTpFdbStatus']
        ];
    }
}

unset($dot1dBasePortIfIndex, $dot1dTpFdbEntry_table, $dot1dBasePort_table);

// EOF
