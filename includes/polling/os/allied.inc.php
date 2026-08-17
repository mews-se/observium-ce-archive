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

// Allied Telesis have somewhat messy MIBs. It's often hard to work out what is where. :)
if (!$hardware) {
    // AtiSwitch-MIB::atiswitchProductType.0 = INTEGER: at8024GB(2)
    // AtiSwitch-MIB::atiswitchSw.0 = STRING: AT-S39
    // AtiSwitch-MIB::atiswitchSwVersion.0 = STRING: v3.3.0

    if ($hw = snmp_get_oid($device, 'atiswitchProductType.0', 'AtiSwitch-MIB')) {
        $version  = snmp_get_oid($device, 'atiswitchSwVersion.0', 'AtiSwitch-MIB');
        $features = snmp_get_oid($device, 'atiswitchSw.0', 'AtiSwitch-MIB');

        $hardware = str_replace('at', 'AT-', $hw);
        $version  = ltrim($version, 'v');
    } elseif ($hardware = snmp_get_oid($device, 'atiL2SwProduct.0', 'AtiL2-MIB')) {
        // AtiL2-MIB::atiL2SwProduct.0 = STRING: "AT-8326GB"
        // AtiL2-MIB::atiL2SwVersion.0 = STRING: "AT-S41 v1.1.6 "

        $version = snmp_get_oid($device, 'atiL2SwVersion.0', 'AtiL2-MIB');

        [ $features, $version ] = explode(' ', $version);
        $version  = trim($version, 'v ');
    }

    return;
}

if (!$version) {
    // Same as above
    $version = snmp_get_oid($device, 'atiswitchSwVersion.0', 'AtiSwitch-MIB');
    if (!$version) {
        $version = snmp_get_oid($device, 'atiL2SwVersion.0', 'AtiL2-MIB');
        [ $features, $version]  = explode(' ', $version);
    }
    $version  = trim($version, 'v ');
}

// EOF
