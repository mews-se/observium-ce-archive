<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage discovery
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2025 Observium Limited
 *
 */

// NSCRTV-FTTX-GPON-MIB::onuName.16779265 = STRING: 287203
// NSCRTV-FTTX-GPON-MIB::onuSerialNum.16779265 = Hex-STRING: 48 57 54 43 A6 65 2B 0B
// NSCRTV-FTTX-GPON-MIB::onuType.16779265 = INTEGER: fixed(1)
// NSCRTV-FTTX-GPON-MIB::onuVendorID.16779265 = STRING: "HWTC"
// NSCRTV-FTTX-GPON-MIB::onuEquipmentID.16779265 = STRING: "FD511G-X"
// NSCRTV-FTTX-GPON-MIB::onuOperationStatus.16779265 = INTEGER: up(1)
// NSCRTV-FTTX-GPON-MIB::onuAdminStatus.16779265 = INTEGER: up(1)
// NSCRTV-FTTX-GPON-MIB::onuTestDistance.16779265 = INTEGER: 788 Meter
// NSCRTV-FTTX-GPON-MIB::resetONU.16779265 = INTEGER: 0
// NSCRTV-FTTX-GPON-MIB::onuDeactive.16779265 = INTEGER: active(1)
// NSCRTV-FTTX-GPON-MIB::onuTimeSinceLastRegister.16779265 = Counter32: 1585704 second
// NSCRTV-FTTX-GPON-MIB::onuSysUpTime.16779265 = Counter32: 1585704 second
// NSCRTV-FTTX-GPON-MIB::onuHardwareVersion.16779265 = STRING: F690.1B
// NSCRTV-FTTX-GPON-MIB::onuPerfStats15minuteEnable.16779265 = INTEGER: 0
// NSCRTV-FTTX-GPON-MIB::onuPerfStats24hourEnable.16779265 = INTEGER: 0
// NSCRTV-FTTX-GPON-MIB::onuMatchState.16779265 = INTEGER: mismatch(2)
// NSCRTV-FTTX-GPON-MIB::onuConfigState.16779265 = INTEGER: fail(2)
// NSCRTV-FTTX-GPON-MIB::onuLastDownTime.16779265 = STRING: 2024-12-23 10:34:15
// NSCRTV-FTTX-GPON-MIB::onuLastDownCause.16779265 = STRING: dying-gasp
// NSCRTV-FTTX-GPON-MIB::onuSoftware0Version.16779265 = STRING: V1.3.8
// NSCRTV-FTTX-GPON-MIB::onuSoftware0Valid.16779265 = INTEGER: valid(1)
// NSCRTV-FTTX-GPON-MIB::onuSoftware0Active.16779265 = INTEGER: active(1)
// NSCRTV-FTTX-GPON-MIB::onuSoftware0Commited.16779265 = INTEGER: committed(1)
// NSCRTV-FTTX-GPON-MIB::onuSoftware1Version.16779265 = STRING: V1.3.8
// NSCRTV-FTTX-GPON-MIB::onuSoftware1Valid.16779265 = INTEGER: valid(1)
// NSCRTV-FTTX-GPON-MIB::onuSoftware1Active.16779265 = INTEGER: inactive(0)
// NSCRTV-FTTX-GPON-MIB::onuSoftware1Commited.16779265 = INTEGER: uncommitted(0)

// NSCRTV-FTTX-GPON-MIB::onuReceivedOpticalPower.16779265.0.0 = INTEGER: -2181 centi-dBm
// NSCRTV-FTTX-GPON-MIB::onuTramsmittedOpticalPower.16779265.0.0 = INTEGER: 213 centi-dBm
// NSCRTV-FTTX-GPON-MIB::onuBiasCurrent.16779265.0.0 = INTEGER: 1695 centi-mA
// NSCRTV-FTTX-GPON-MIB::onuWorkingVoltage.16779265.0.0 = INTEGER: 328000 centi-mV
// NSCRTV-FTTX-GPON-MIB::onuWorkingTemperature.16779265.0.0 = INTEGER: 4386 Centi-degree centigrade

// NSCRTV-FTTX-GPON-MIB::onuReceivedOpticalPower.4718593.0.0 = INTEGER: -985 centi-dBm
// NSCRTV-FTTX-GPON-MIB::onuTramsmittedOpticalPower.4718593.0.0 = INTEGER: 202 centi-dBm
// NSCRTV-FTTX-GPON-MIB::onuBiasCurrent.4718593.0.0 = INTEGER: 1220 centi-mA
// NSCRTV-FTTX-GPON-MIB::onuWorkingVoltage.4718593.0.0 = INTEGER: 334 centi-mV
// NSCRTV-FTTX-GPON-MIB::onuWorkingTemperature.4718593.0.0 = INTEGER: 3471 Centi-degree centigrade

$oids = snmpwalk_cache_oid($device, 'gponOnuPonPortOpticalTransmissionPropertyTable', [], $mib);
print_debug_vars($oids);

if (!snmp_status()) {
    return;
}

$oids_onu = snmpwalk_cache_oid($device, 'onuName',                   [], $mib);
$oids_onu = snmpwalk_cache_oid($device, 'onuVendorID',        $oids_onu, $mib);
$oids_onu = snmpwalk_cache_oid($device, 'onuEquipmentID',     $oids_onu, $mib);
$oids_onu = snmpwalk_cache_oid($device, 'onuHardwareVersion', $oids_onu, $mib);
$oids_onu = snmpwalk_cache_oid($device, 'onuOperationStatus', $oids_onu, $mib);
$oids_onu = snmpwalk_cache_oid($device, 'onuAdminStatus',     $oids_onu, $mib);
$oids_onu = snmpwalk_cache_oid($device, 'onuTestDistance',    $oids_onu, $mib);
$oids_onu = snmpwalk_cache_oid($device, 'onuDeactive',        $oids_onu, $mib);
$oids_onu = snmpwalk_cache_oid($device, 'onuSysUpTime',       $oids_onu, $mib);
$oids_onu = snmpwalk_cache_oid($device, 'onuTimeSinceLastRegister', $oids_onu, $mib);
print_debug_vars($oids_onu);

foreach ($oids as $index => $entry) {
    [ $onu_index, $card_index, $port_index ] = explode('.', $index);
    $onu = $oids_onu[$onu_index];

    if ($onu['onuAdminStatus'] === 'down' || $onu['onuDeactive'] === 'deactivate') {
        // Skip ONU disabled or Down ports
        continue;
    }

    $onu_descr = "ONU {$onu['onuName']}, Card $card_index, Port $port_index";
    $onu_extra = "({$onu['onuVendorID']} {$onu['onuEquipmentID']})";

    $options  = [
        'measured_entity_label' => $onu_descr,
        'measured_class' => 'fiber'
    ];

    $descr    = $onu_descr . " RX Power " . $onu_extra;
    $oid_name = 'onuReceivedOpticalPower';
    $oid_num  = '.1.3.6.1.4.1.17409.2.8.4.4.1.4.' . $index;
    discover_sensor_ng($device, 'dbm', $mib, $oid_name, $oid_num, $index, $descr, 0.01, $entry[$oid_name], $options);

    $descr    = $onu_descr . " TX Power " . $onu_extra;
    $oid_name = 'onuTramsmittedOpticalPower';
    $oid_num  = '.1.3.6.1.4.1.17409.2.8.4.4.1.5.' . $index;
    discover_sensor_ng($device, 'dbm', $mib, $oid_name, $oid_num, $index, $descr, 0.01, $entry[$oid_name], $options);

    $descr    = $onu_descr . " Bias " . $onu_extra;
    $oid_name = 'onuBiasCurrent';
    $oid_num  = '.1.3.6.1.4.1.17409.2.8.4.4.1.6.' . $index;
    discover_sensor_ng($device, 'current', $mib, $oid_name, $oid_num, $index, $descr, 0.00001, $entry[$oid_name], $options);

    $descr    = $onu_descr . " Voltage " . $onu_extra;
    $oid_name = 'onuWorkingVoltage';
    $oid_num  = '.1.3.6.1.4.1.17409.2.8.4.4.1.7.' . $index;
    discover_sensor_ng($device, 'voltage', $mib, $oid_name, $oid_num, $index, $descr, 0.00001, $entry[$oid_name], $options);

    $descr    = $onu_descr . " Temperature " . $onu_extra;
    $oid_name = 'onuWorkingTemperature';
    $oid_num  = '.1.3.6.1.4.1.17409.2.8.4.4.1.8.' . $index;
    discover_sensor_ng($device, 'temperature', $mib, $oid_name, $oid_num, $index, $descr, 0.01, $entry[$oid_name], $options);
}

// Extra distance sensor
foreach ($oids_onu as $onu_index => $onu) {

    if ($onu['onuAdminStatus'] === 'down' || $onu['onuDeactive'] === 'deactivate') {
        // Skip ONU disabled or Down ports
        continue;
    }

    $descr = "ONU {$onu['onuName']} Test Distance ({$onu['onuVendorID']} {$onu['onuEquipmentID']})";
    $options  = [
        'measured_entity_label' => "ONU {$onu['onuName']}",
        'measured_class' => 'onu'
    ];

    $oid_name = 'onuTestDistance';
    $oid_num  = '.1.3.6.1.4.1.17409.2.8.4.1.1.9.' . $onu_index;
    if ($onu[$oid_name] > 0) {
        discover_sensor_ng($device, 'distance', $mib, $oid_name, $oid_num, $onu_index, $descr, 1, $onu[$oid_name], $options);
    }

    $descr = "ONU {$onu['onuName']} Uptime ({$onu['onuVendorID']} {$onu['onuEquipmentID']})";
    $oid_name = 'onuSysUpTime';
    $oid_num  = '.1.3.6.1.4.1.17409.2.8.4.1.1.9.' . $onu_index;
    if ($onu[$oid_name] > 0) {
        discover_sensor_ng($device, 'age', $mib, $oid_name, $oid_num, $onu_index, $descr, 1, $onu[$oid_name], $options);
    }
}

// EOF
