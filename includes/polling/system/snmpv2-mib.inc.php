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
 */

if (!isset($config['os'][$device['os']]['snmp']['noindex'])) {
    $snmpdata = snmp_get_multi_oid($device, [ 'sysUpTime.0', 'sysLocation.0', 'sysContact.0', 'sysName.0' ], [], 'SNMPv2-MIB', NULL, OBS_SNMP_ALL_UTF8);
    if (is_array($snmpdata[0])) {
        $snmpdata = $snmpdata[0];
    }
} else {
    // DERP hardware return common sysDescr/sysObjectID/sysUpTime without indexes
    $snmpdata = snmp_get_multi_oid($device, [ 'sysUpTime', 'sysLocation', 'sysContact', 'sysName' ], [], 'SNMPv2-MIB', NULL, OBS_SNMP_ALL_UTF8 | OBS_SNMP_NOINDEX);
    print_debug_vars($snmpdata);
    if (is_array($snmpdata[''])) {
        $snmpdata = $snmpdata[''];
    }
}
$polled   = round(snmp_endtime());
if (is_array($snmpdata)) {
    $poll_device = array_merge($poll_device, $snmpdata);

    if (isset($snmpdata['sysUpTime'])) {
        // SNMPv2-MIB::sysUpTime.0 = Timeticks: (2542831) 7:03:48.31
        $poll_device['sysUpTime'] = timeticks_to_sec($snmpdata['sysUpTime']);
    }

    $poll_device['sysName_SNMPv2'] = $poll_device['sysName']; // Store original sysName for devices who store hardware in this Oid
}

if (!isset($config['os'][$device['os']]['snmp']['noindex'])) {
    $sysDescr = snmp_get_oid($device, 'sysDescr.0', 'SNMPv2-MIB', NULL, OBS_SNMP_ALL_UTF8);
} else {
    // DERP hardware return common sysDescr/sysObjectID/sysUpTime without indexes
    $sysDescr = snmp_get_oid($device, 'sysDescr', 'SNMPv2-MIB', NULL, OBS_SNMP_ALL_UTF8);
}
if (snmp_status() || snmp_error_code() === OBS_SNMP_ERROR_EMPTY_RESPONSE) {
    // Allow empty response for sysDescr (not timeouts)

    $poll_device['sysDescr'] = $sysDescr;
}

$poll_device['sysObjectID']  = snmp_cache_sysObjectID($device);
$poll_device['snmpEngineID'] = snmp_cache_snmpEngineID($device);

unset($snmpdata, $sysDescr);

//EOF
