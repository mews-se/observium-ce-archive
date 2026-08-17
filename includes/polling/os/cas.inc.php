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

// SNMPv2-MIB::sysDescr.0 Blue Coat S400-A3, Version: 1.2.4.4, Release Id: 157593
// BLUECOAT-CAS-MIB::casInstalledFirmwareVersion.0 = STRING: 1.2.4.4(157593)
// BLUECOAT-CAS-MIB::casAvStatusIndex.0 = Counter32: 0
// BLUECOAT-CAS-MIB::casAvVendorName.0 = STRING: Kaspersky Labs
// BLUECOAT-CAS-MIB::casAvEngineVersion.0 = STRING: 8.2.5.17
// BLUECOAT-CAS-MIB::casAvPatternVersion.0 = STRING: 160119 210400.6788010

if ($av = snmp_get_oid($device, 'casAvVendorName.0', 'BLUECOAT-CAS-MIB')) {
    $eng      = snmp_get_oid($device, 'casAvEngineVersion.0', 'BLUECOAT-CAS-MIB');
    $pat      = snmp_get_oid($device, 'casAvPatternVersion.0', 'BLUECOAT-CAS-MIB');
    $features = "$av-$eng ($pat)";
}

if (preg_match('/Blue Coat (?<hw>\S+), Version: (?<version>[\d\.\-]+)/', $poll_device['sysDescr'], $matches)) {
    $hardware = $matches['hw'];
    $version  = $matches['version'];
} else {
    $hardware = trim(explode(',', $poll_device['sysDescr'])[0]);
    $version  = snmp_get_oid($device, 'casInstalledFirmwareVersion.0', 'BLUECOAT-CAS-MIB');
}

// EOF
