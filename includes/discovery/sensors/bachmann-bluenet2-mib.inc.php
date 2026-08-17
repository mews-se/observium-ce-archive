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

// BACHMANN-BLUENET2-MIB::blueNet2DeviceGuid.1 = Hex-STRING: 00 00 FF FF FF FF 00 00
// BACHMANN-BLUENET2-MIB::blueNet2DeviceName.1 = STRING: Master
// BACHMANN-BLUENET2-MIB::blueNet2DeviceFriendlyName.1 = STRING: Master
// BACHMANN-BLUENET2-MIB::blueNet2DeviceDescription.1 = STRING:
// BACHMANN-BLUENET2-MIB::blueNet2DeviceType.1 = OID: BACHMANN-BLUENET2-PRODUCTS-MIB::blueNet2ProductChassisMonitored-3p-32A-24-6-0
// BACHMANN-BLUENET2-MIB::blueNet2DeviceStatus.1 = INTEGER: childAlarm(25)
$oids_device = snmpwalk_cache_oid($device, 'blueNet2DeviceName',             [], 'BACHMANN-BLUENET2-MIB');
$oids_device = snmpwalk_cache_oid($device, 'blueNet2DeviceGuid',   $oids_device, 'BACHMANN-BLUENET2-MIB');
$oids_device = snmpwalk_cache_oid($device, 'blueNet2DeviceType',   $oids_device, 'BACHMANN-BLUENET2-MIB');
$oids_device = snmpwalk_cache_oid($device, 'blueNet2DeviceStatus', $oids_device, 'BACHMANN-BLUENET2-MIB');

// BACHMANN-BLUENET2-MIB::blueNet2DeviceNumberOfSensors.1 = Gauge32: 0
// BACHMANN-BLUENET2-MIB::blueNet2DeviceNumberOfCircuits.1 = Gauge32: 1
// BACHMANN-BLUENET2-MIB::blueNet2DeviceNumberOfPhases.1 = Gauge32: 3
// BACHMANN-BLUENET2-MIB::blueNet2DeviceNumberOfFuses.1 = Gauge32: 6
// BACHMANN-BLUENET2-MIB::blueNet2DeviceNumberOfSockets.1 = Gauge32: 0
// BACHMANN-BLUENET2-MIB::blueNet2DeviceNumberOfRCMs.1 = Gauge32: 0
// BACHMANN-BLUENET2-MIB::blueNet2DeviceNumberOfVars.1 = Gauge32: 57
$oids_device = snmpwalk_cache_oid($device, 'blueNet2DeviceNumberOfSensors',  $oids_device, 'BACHMANN-BLUENET2-MIB');
$oids_device = snmpwalk_cache_oid($device, 'blueNet2DeviceNumberOfCircuits', $oids_device, 'BACHMANN-BLUENET2-MIB');
$oids_device = snmpwalk_cache_oid($device, 'blueNet2DeviceNumberOfPhases',   $oids_device, 'BACHMANN-BLUENET2-MIB');
$oids_device = snmpwalk_cache_oid($device, 'blueNet2DeviceNumberOfFuses',    $oids_device, 'BACHMANN-BLUENET2-MIB');
$oids_device = snmpwalk_cache_oid($device, 'blueNet2DeviceNumberOfSockets',  $oids_device, 'BACHMANN-BLUENET2-MIB');
$oids_device = snmpwalk_cache_oid($device, 'blueNet2DeviceNumberOfRCMs',     $oids_device, 'BACHMANN-BLUENET2-MIB');
$oids_device = snmpwalk_cache_oid($device, 'blueNet2DeviceNumberOfVars',     $oids_device, 'BACHMANN-BLUENET2-MIB');

print_debug_vars($oids_device);

$bluenet_guid = [];
$bluenet = [ 'devices' => safe_count($oids_device) ];
foreach ($oids_device as $index => $entry) {
    if (isset($bluenet['sensors'])) {
        $bluenet['sensors'] += $entry['blueNet2DeviceNumberOfSensors'];
    } else {
        $bluenet['sensors'] = (int)$entry['blueNet2DeviceNumberOfSensors'];
    }
    if (isset($bluenet['circuits'])) {
        $bluenet['circuits'] += $entry['blueNet2DeviceNumberOfCircuits'];
    } else {
        $bluenet['circuits'] = (int)$entry['blueNet2DeviceNumberOfCircuits'];
    }
    if (isset($bluenet['phases'])) {
        $bluenet['phases'] += $entry['blueNet2DeviceNumberOfPhases'];
    } else {
        $bluenet['phases'] = (int)$entry['blueNet2DeviceNumberOfPhases'];
    }
    if (isset($bluenet['fuses'])) {
        $bluenet['fuses'] += $entry['blueNet2DeviceNumberOfFuses'];
    } else {
        $bluenet['fuses'] = (int)$entry['blueNet2DeviceNumberOfFuses'];
    }
    if (isset($bluenet['sockets'])) {
        $bluenet['sockets'] += $entry['blueNet2DeviceNumberOfSockets'];
    } else {
        $bluenet['sockets'] = (int)$entry['blueNet2DeviceNumberOfSockets'];
    }
    if (isset($bluenet['rcms'])) {
        $bluenet['rcms'] += $entry['blueNet2DeviceNumberOfRCMs'];
    } else {
        $bluenet['rcms'] = (int)$entry['blueNet2DeviceNumberOfRCMs'];
    }
    if (isset($bluenet['vars'])) {
        $bluenet['vars'] += $entry['blueNet2DeviceNumberOfVars'];
    } else {
        $bluenet['vars'] = (int)$entry['blueNet2DeviceNumberOfVars'];
    }

    $descr     = 'Device: ' . $entry['blueNet2DeviceName'];
    $oid_name  = 'blueNet2DeviceStatus';
    $oid_num   = '.1.3.6.1.4.1.31770.2.2.4.2.1.7.' . $index;
    $value     = $entry[$oid_name];

    $options = [ 'measured_class' => 'device', 'measured_entity_label' => 'Device '. $index ];
    discover_status_ng($device, $mib, $oid_name, $oid_num, $index, 'BlueNet2EntityStates', $descr, $value, $options);

    // Set descr for guid (for derp vars sensors)
    $guid = explode(' ', trim($entry['blueNet2DeviceGuid']));
    array_pop($guid);
    $quid = implode('', $guid);
    $bluenet_guid[$quid] = [ 'descr' => $descr ];
}
print_debug_vars($bluenet);

// Sensors?

// Circuits
if ($bluenet['circuits']) {
    // BACHMANN-BLUENET2-MIB::blueNet2CircuitGuid.1.1 = Hex-STRING: 00 00 00 FF FF FF 00 00
    // BACHMANN-BLUENET2-MIB::blueNet2CircuitName.1.1 = STRING: Inlet 1
    // BACHMANN-BLUENET2-MIB::blueNet2CircuitFriendlyName.1.1 = STRING: Inlet 1
    // BACHMANN-BLUENET2-MIB::blueNet2CircuitDescription.1.1 = STRING:
    // BACHMANN-BLUENET2-MIB::blueNet2CircuitType.1.1 = OID: BACHMANN-BLUENET2-PRODUCTS-MIB::blueNet2ProductCircuit-4PWYE
    // BACHMANN-BLUENET2-MIB::blueNet2CircuitStatus.1.1 = INTEGER: childAlarm(25)
    // BACHMANN-BLUENET2-MIB::blueNet2CircuitNumberOfPhases.1.1 = Gauge32: 3
    $oids_circuit = snmpwalk_cache_oid($device, 'blueNet2CircuitTable', [], 'BACHMANN-BLUENET2-MIB');
    print_debug_vars($oids_circuit);

    foreach ($oids_circuit as $index => $entry) {
        [ $bdevice, $circuit ] = explode('.', $index);

        $descr    = 'Circuit: ' . $entry['blueNet2CircuitName'];
        if ($bluenet['devices'] > 1) {
            $descr .= ', Device: ' . $oids_device[$bdevice]['blueNet2DeviceName'];
        }
        $oid_name = 'blueNet2CircuitStatus';
        $oid_num  = '.1.3.6.1.4.1.31770.2.2.6.2.1.8.' . $index;
        $value    = $entry[$oid_name];

        $options = [ 'measured_class' => 'circuit', 'measured_entity_label' => 'Circuit ' . $index ];
        discover_status_ng($device, $mib, $oid_name, $oid_num, $index, 'BlueNet2EntityStates', $descr, $value, $options);

        // Set descr for guid (for derp vars sensors)
        $guid = explode(' ', trim($entry['blueNet2CircuitGuid']));
        array_pop($guid);
        $quid = implode('', $guid);
        $bluenet_guid[$quid] = [ 'descr' => $descr, 'measured_class' => 'circuit', 'measured_entity_label' => $descr  ];
    }
}

// Phases
if ($bluenet['phases']) {
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseGuid.1.1.1 = Hex-STRING: 00 00 00 00 FF FF 00 00
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseGuid.1.1.2 = Hex-STRING: 00 00 00 01 FF FF 00 00
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseGuid.1.1.3 = Hex-STRING: 00 00 00 02 FF FF 00 00
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseName.1.1.1 = STRING: Phase 1
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseName.1.1.2 = STRING: Phase 2
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseName.1.1.3 = STRING: Phase 3
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseFriendlyName.1.1.1 = STRING: Phase 1
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseFriendlyName.1.1.2 = STRING: Phase 2
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseFriendlyName.1.1.3 = STRING: Phase 3
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseDescription.1.1.1 = STRING:
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseDescription.1.1.2 = STRING:
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseDescription.1.1.3 = STRING:
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseStatus.1.1.1 = INTEGER: on(19)
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseStatus.1.1.2 = INTEGER: on(19)
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseStatus.1.1.3 = INTEGER: childAlarm(25)
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseNumberOfFuses.1.1.1 = Gauge32: 2
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseNumberOfFuses.1.1.2 = Gauge32: 2
    // BACHMANN-BLUENET2-MIB::blueNet2PhaseNumberOfFuses.1.1.3 = Gauge32: 2
    $oids_phase = snmpwalk_cache_oid($device, 'blueNet2PhaseTable', [], 'BACHMANN-BLUENET2-MIB');
    print_debug_vars($oids_phase);

    foreach ($oids_phase as $index => $entry) {
        [ $bdevice, $circuit, $phase ] = explode('.', $index);

        $descr    = $entry['blueNet2PhaseName'];
        if ($bluenet['circuits'] > 1) {
            $descr .= ', Circuit: ' . $oids_circuit["$bdevice.$circuit"]['blueNet2CircuitName'];
        }
        if ($bluenet['devices'] > 1) {
            $descr .= ', Device: ' . $oids_device[$bdevice]['blueNet2DeviceName'];
        }
        $oid_name = 'blueNet2PhaseStatus';
        $oid_num  = '.1.3.6.1.4.1.31770.2.2.6.3.1.8.' . $index;
        $value    = $entry[$oid_name];

        $options = [ 'measured_class' => 'phase', 'measured_entity_label' => 'Phase ' . $index ];
        discover_status_ng($device, $mib, $oid_name, $oid_num, $index, 'BlueNet2EntityStates', $descr, $value, $options);

        // Set descr for guid (for derp vars sensors)
        $guid = explode(' ', trim($entry['blueNet2PhaseGuid']));
        array_pop($guid);
        $quid = implode('', $guid);
        $bluenet_guid[$quid] = [ 'descr' => $descr, 'measured_class' => 'phase', 'measured_entity_label' => $descr  ];
    }
}

// Fuses
if ($bluenet['fuses']) {
    // BACHMANN-BLUENET2-MIB::blueNet2FuseGuid.1.1.1.1 = Hex-STRING: 00 00 00 00 00 FF 00 00
    // BACHMANN-BLUENET2-MIB::blueNet2FuseGuid.1.1.1.2 = Hex-STRING: 00 00 00 00 01 FF 00 00
    // BACHMANN-BLUENET2-MIB::blueNet2FuseGuid.1.1.2.1 = Hex-STRING: 00 00 00 01 00 FF 00 00
    // BACHMANN-BLUENET2-MIB::blueNet2FuseGuid.1.1.2.2 = Hex-STRING: 00 00 00 01 01 FF 00 00
    // BACHMANN-BLUENET2-MIB::blueNet2FuseGuid.1.1.3.1 = Hex-STRING: 00 00 00 02 00 FF 00 00
    // BACHMANN-BLUENET2-MIB::blueNet2FuseGuid.1.1.3.2 = Hex-STRING: 00 00 00 02 01 FF 00 00
    // BACHMANN-BLUENET2-MIB::blueNet2FuseName.1.1.1.1 = STRING: Fuse 1
    // BACHMANN-BLUENET2-MIB::blueNet2FuseName.1.1.1.2 = STRING: Fuse 2
    // BACHMANN-BLUENET2-MIB::blueNet2FuseName.1.1.2.1 = STRING: Fuse 1
    // BACHMANN-BLUENET2-MIB::blueNet2FuseName.1.1.2.2 = STRING: Fuse 2
    // BACHMANN-BLUENET2-MIB::blueNet2FuseName.1.1.3.1 = STRING: Fuse 1
    // BACHMANN-BLUENET2-MIB::blueNet2FuseName.1.1.3.2 = STRING: Fuse 2
    // BACHMANN-BLUENET2-MIB::blueNet2FuseFriendlyName.1.1.1.1 = STRING: Fuse 1
    // BACHMANN-BLUENET2-MIB::blueNet2FuseFriendlyName.1.1.1.2 = STRING: Fuse 2
    // BACHMANN-BLUENET2-MIB::blueNet2FuseFriendlyName.1.1.2.1 = STRING: Fuse 1
    // BACHMANN-BLUENET2-MIB::blueNet2FuseFriendlyName.1.1.2.2 = STRING: Fuse 2
    // BACHMANN-BLUENET2-MIB::blueNet2FuseFriendlyName.1.1.3.1 = STRING: Fuse 1
    // BACHMANN-BLUENET2-MIB::blueNet2FuseFriendlyName.1.1.3.2 = STRING: Fuse 2
    // BACHMANN-BLUENET2-MIB::blueNet2FuseDescription.1.1.1.1 = STRING:
    // BACHMANN-BLUENET2-MIB::blueNet2FuseDescription.1.1.1.2 = STRING:
    // BACHMANN-BLUENET2-MIB::blueNet2FuseDescription.1.1.2.1 = STRING:
    // BACHMANN-BLUENET2-MIB::blueNet2FuseDescription.1.1.2.2 = STRING:
    // BACHMANN-BLUENET2-MIB::blueNet2FuseDescription.1.1.3.1 = STRING:
    // BACHMANN-BLUENET2-MIB::blueNet2FuseDescription.1.1.3.2 = STRING:
    // BACHMANN-BLUENET2-MIB::blueNet2FuseType.1.1.1.1 = OID: BACHMANN-BLUENET2-PRODUCTS-MIB::blueNet2ProductFuse-16C
    // BACHMANN-BLUENET2-MIB::blueNet2FuseType.1.1.1.2 = OID: BACHMANN-BLUENET2-PRODUCTS-MIB::blueNet2ProductFuse-16C
    // BACHMANN-BLUENET2-MIB::blueNet2FuseType.1.1.2.1 = OID: BACHMANN-BLUENET2-PRODUCTS-MIB::blueNet2ProductFuse-16C
    // BACHMANN-BLUENET2-MIB::blueNet2FuseType.1.1.2.2 = OID: BACHMANN-BLUENET2-PRODUCTS-MIB::blueNet2ProductFuse-16C
    // BACHMANN-BLUENET2-MIB::blueNet2FuseType.1.1.3.1 = OID: BACHMANN-BLUENET2-PRODUCTS-MIB::blueNet2ProductFuse-16C
    // BACHMANN-BLUENET2-MIB::blueNet2FuseType.1.1.3.2 = OID: BACHMANN-BLUENET2-PRODUCTS-MIB::blueNet2ProductFuse-16C
    // BACHMANN-BLUENET2-MIB::blueNet2FuseStatus.1.1.1.1 = INTEGER: on(19)
    // BACHMANN-BLUENET2-MIB::blueNet2FuseStatus.1.1.1.2 = INTEGER: on(19)
    // BACHMANN-BLUENET2-MIB::blueNet2FuseStatus.1.1.2.1 = INTEGER: on(19)
    // BACHMANN-BLUENET2-MIB::blueNet2FuseStatus.1.1.2.2 = INTEGER: on(19)
    // BACHMANN-BLUENET2-MIB::blueNet2FuseStatus.1.1.3.1 = INTEGER: lost(7)
    // BACHMANN-BLUENET2-MIB::blueNet2FuseStatus.1.1.3.2 = INTEGER: lost(7)
    // BACHMANN-BLUENET2-MIB::blueNet2FuseNumberOfSockets.1.1.1.1 = Gauge32: 0
    // BACHMANN-BLUENET2-MIB::blueNet2FuseNumberOfSockets.1.1.1.2 = Gauge32: 0
    // BACHMANN-BLUENET2-MIB::blueNet2FuseNumberOfSockets.1.1.2.1 = Gauge32: 0
    // BACHMANN-BLUENET2-MIB::blueNet2FuseNumberOfSockets.1.1.2.2 = Gauge32: 0
    // BACHMANN-BLUENET2-MIB::blueNet2FuseNumberOfSockets.1.1.3.1 = Gauge32: 0
    // BACHMANN-BLUENET2-MIB::blueNet2FuseNumberOfSockets.1.1.3.2 = Gauge32: 0
    $oids_fuses = snmpwalk_cache_oid($device, 'blueNet2FuseTable', [], 'BACHMANN-BLUENET2-MIB');
    print_debug_vars($oids_fuses);

    foreach ($oids_fuses as $index => $entry) {
        [ $bdevice, $circuit, $phase, $fuse ] = explode('.', $index);

        $descr    = $entry['blueNet2FuseName'] . ', ' . $oids_phase["$bdevice.$circuit.$phase"]['blueNet2PhaseName'];
        if ($bluenet['circuits'] > 1) {
            $descr .= ', Circuit: ' . $oids_circuit["$bdevice.$circuit"]['blueNet2CircuitName'];
        }
        if ($bluenet['devices'] > 1) {
            $descr .= ', Device: ' . $oids_device[$bdevice]['blueNet2DeviceName'];
        }
        $oid_name = 'blueNet2FuseStatus';
        $oid_num  = '.1.3.6.1.4.1.31770.2.2.6.4.1.10.' . $index;
        $value    = $entry[$oid_name];

        $options = [ 'measured_class' => 'fuse', 'measured_entity_label' => 'Fuse ' . $index ];
        discover_status_ng($device, $mib, $oid_name, $oid_num, $index, 'BlueNet2EntityStates', $descr, $value, $options);

        // Set descr for guid (for derp vars sensors)
        $guid = explode(' ', trim($entry['blueNet2FuseGuid']));
        array_pop($guid);
        $quid = implode('', $guid);
        $bluenet_guid[$quid] = [ 'descr' => $descr, 'measured_class' => 'fuse', 'measured_entity_label' => $descr  ];
    }
}
print_debug_vars($bluenet_guid);

// Vars
if ($bluenet['vars']) {
    // Too big table, do not keep in memory

    //snmpwalk_cache_oid($device, 'blueNet2VariableTable', [], 'BACHMANN-BLUENET2-MIB', NULL, OBS_SNMP_ALL_NUMERIC_INDEX);

    // BACHMANN-BLUENET2-MIB::blueNet2VariableGuid.0.0.0.0.255.255.0.1 = Hex-STRING: 00 00 00 00 FF FF 00 01
    // BACHMANN-BLUENET2-MIB::blueNet2VariableName.0.0.0.0.255.255.0.1 = STRING: Voltage
    // BACHMANN-BLUENET2-MIB::blueNet2VariableFriendlyName.0.0.0.0.255.255.0.1 = STRING: Voltage
    // BACHMANN-BLUENET2-MIB::blueNet2VariableDescription.0.0.0.0.255.255.0.1 = STRING:
    // BACHMANN-BLUENET2-MIB::blueNet2VariableType.0.0.0.0.255.255.0.1 = INTEGER: voltage(1)
    // BACHMANN-BLUENET2-MIB::blueNet2VariableStatus.0.0.0.0.255.255.0.1 = INTEGER: ok(2)
    // BACHMANN-BLUENET2-MIB::blueNet2VariableAlarm.0.0.0.0.255.255.0.1 = STRING:
    // BACHMANN-BLUENET2-MIB::blueNet2VariableScaling.0.0.0.0.255.255.0.1 = INTEGER: -2
    // BACHMANN-BLUENET2-MIB::blueNet2VariableUnit.0.0.0.0.255.255.0.1 = INTEGER: volt(38)
    // BACHMANN-BLUENET2-MIB::blueNet2VariableSetPoint.0.0.0.0.255.255.0.1 = INTEGER: available(2)
    // BACHMANN-BLUENET2-MIB::blueNet2VariableMode.0.0.0.0.255.255.0.1 = INTEGER: noReset(2)
    // BACHMANN-BLUENET2-MIB::blueNet2VariableEntPhysicalIndex.0.0.0.0.255.255.0.1 = Gauge32: 100019

    //snmpwalk_cache_oid($device, 'blueNet2VariableSetPointTable', [], 'BACHMANN-BLUENET2-MIB', NULL, OBS_SNMP_ALL_NUMERIC_INDEX);

    // BACHMANN-BLUENET2-MIB::blueNet2VariableSetPointType.0.0.0.0.255.255.0.1 = BITS: F0 highAlarm(0) lowAlarm(1) highWarning(2) lowWarning(3)
    // BACHMANN-BLUENET2-MIB::blueNet2VariableSetPointMinValue.0.0.0.0.255.255.0.1 = INTEGER: 0
    // BACHMANN-BLUENET2-MIB::blueNet2VariableSetPointMaxValue.0.0.0.0.255.255.0.1 = INTEGER: 26000
    // BACHMANN-BLUENET2-MIB::blueNet2VariableSetPointLowerAlarm.0.0.0.0.255.255.0.1 = INTEGER: 0
    // BACHMANN-BLUENET2-MIB::blueNet2VariableSetPointLowerWarning.0.0.0.0.255.255.0.1 = INTEGER: 0
    // BACHMANN-BLUENET2-MIB::blueNet2VariableSetPointUpperWarning.0.0.0.0.255.255.0.1 = INTEGER: 26000
    // BACHMANN-BLUENET2-MIB::blueNet2VariableSetPointUpperAlarm.0.0.0.0.255.255.0.1 = INTEGER: 26000
    // BACHMANN-BLUENET2-MIB::blueNet2VariableSetPointHysteresis.0.0.0.0.255.255.0.1 = Gauge32: 50

    //snmpwalk_cache_oid($device, 'blueNet2VariableDataTable', [], 'BACHMANN-BLUENET2-MIB', NULL, OBS_SNMP_ALL_NUMERIC_INDEX);

    // BACHMANN-BLUENET2-MIB::blueNet2VariableDataGuid.0.0.0.0.255.255.0.1 = Hex-STRING: 00 00 00 00 FF FF 00 01
    // BACHMANN-BLUENET2-MIB::blueNet2VariableDataType.0.0.0.0.255.255.0.1 = INTEGER: voltage(1)
    // BACHMANN-BLUENET2-MIB::blueNet2VariableDataStatus.0.0.0.0.255.255.0.1 = INTEGER: ok(2)
    // BACHMANN-BLUENET2-MIB::blueNet2VariableDataValue.0.0.0.0.255.255.0.1 = INTEGER: 23044
    // BACHMANN-BLUENET2-MIB::blueNet2VariableDataDateTime.0.0.0.0.255.255.0.1 = STRING: 2013-5-16,8:53:14.0,+2:0

    foreach (snmpwalk_cache_oid($device, 'blueNet2VariableDataTable', [], 'BACHMANN-BLUENET2-MIB', NULL, OBS_SNMP_ALL_NUMERIC_INDEX) as $index => $entry) {

        if (in_array($entry['blueNet2VariableDataStatus'], [ 'undefined', 'deactivate', 'disabled' ])) {
            print_debug('Skipped disabled entry index: ' . $index);
            print_debug_vars($entry);
            continue;
        }
        // voltage (1), peakVoltage (2),
        // current (4), peakCurrent (5), differentialCurrentAc (7), differentialCurrentDc (8), neutralCurrent (9),
        // phaseAngle      (16),
        // powerFactor     (17),
        // apparentPower   (18),
        // activePower (19), peakActivePower (20), peakActivePowerUser (21),
        // reactivePower   (22),
        // frequency       (23),
        // peakNeutralCurrent (24),
        // apparentEnergyAccumulated (32), apparentEnergyDelta (33),
        // reactiveEnergyAccumulated (34), reactiveEnergyDelta (35),
        // activeEnergyAccumulated (36), activeEnergyDelta (37), activeEnergyAccumulatedUser (38),
        // activeEnergyRuntime (39), customEnergyRuntimeUser(40),
        //                                 fuseState       (48),
        //                                 orientation     (49),
        //                                 usb             (50),
        //                                 socketState     (51),
        //                                 pduState        (52),
        //                                 sensorState     (53),
        //                                 circuitState    (54),
        //                                 phaseState      (55),
        //                                 rcdState        (56),
        //                                 socketGroupState (57),
        //                                 globalState     (58),
        //                                 sensorType      (64),
        //                                 circuitType     (65),
        //                                 fuseType        (66),
        //                                 socketType      (67),
        //                                 socketColor     (68),
        //                                 phaseType       (69),
        //                                 pduType         (70),
        //                                 rcmType         (71),
        //                                 deltaVoltage12  (80),
        //                                 deltaVoltage23  (81),
        //                                 deltaVoltage31  (82),
        //                                 rcmACPeak       (83),
        //                                 rcmDCPeak       (84),
        // temperature     (256),
        // humidity        (257),
        // ioInputChannel1 (258), ioInputChannel2 (259), ioInputChannel3 (260), ioInputChannel4 (261),
        // ioOutputChannel1 (262), ioOutputChannel2 (263), ioOutputChannel3 (264), ioOutputChannel4 (265),
        // dewPoint        (266),
        // pressure        (267), diffPressure    (268),
        //                                 co2Equivalent   (269),
        //                                 tvoc            (270),
        //                                 unspecified     (65535)
        $sensor_type = 'sensor'; // counter/status
        switch ($entry['blueNet2VariableDataType']) {
            case '1':
            case 'voltage':
                $class = 'voltage';
                break;

            case '4':
            case 'current':
            case '9':
            case 'neutralCurrent':
                $class = 'current';
                break;

            case '17':
            case 'powerFactor':
                $class = 'powerfactor';
                break;

            case '18':
            case 'apparentPower':
                $class = 'apower';
                break;

            case '19':
            case 'activePower':
                $class = 'power';
                break;

            case '22':
            case 'reactivePower':
                $class = 'rpower';
                break;

            case '23':
            case 'frequency':
                $class = 'frequency';
                break;

            case '32':
            case 'apparentEnergyAccumulated':
                $sensor_type = 'counter';
                $class = 'aenergy';
                break;

            case '34':
            case 'reactiveEnergyAccumulated':
                $sensor_type = 'counter';
                $class = 'renergy';
                break;

            case '36':
            case 'activeEnergyAccumulated':
            // case '38':
            // case 'activeEnergyAccumulatedUser':
                $sensor_type = 'counter';
                $class = 'energy';
                break;

            case '256':
            case 'temperature':
                $class = 'temperature';
                break;

            case '257':
            case 'humidity':
                $class = 'humidity';
                break;

            case '266':
            case 'dewPoint':
                $class = 'dewpoint';
                break;

            case '267':
            case 'pressure':
                $class = 'pressure';
                break;

            default:
                print_debug('Skipped unsupported variable type: ' . $entry['blueNet2VariableDataType']);
                print_debug_vars($entry);
                continue 2;
        }
        $oids = [
            'blueNet2VariableName.' . $index, 'blueNet2VariableFriendlyName.' . $index,
            'blueNet2VariableScaling.' . $index, 'blueNet2VariableUnit.' . $index,
            'blueNet2VariableSetPoint.' . $index, 'blueNet2VariableEntPhysicalIndex.' . $index,
        ];
        $data = snmp_get_multi_oid($device, $oids, [], 'BACHMANN-BLUENET2-MIB', NULL, OBS_SNMP_ALL_NUMERIC_INDEX);
        if ($limit = ($data[$index]['blueNet2VariableSetPoint'] === 'available')) {
            $oids = [
                'blueNet2VariableSetPointLowerAlarm.' . $index, 'blueNet2VariableSetPointLowerWarning.' . $index,
                'blueNet2VariableSetPointUpperWarning.' . $index, 'blueNet2VariableSetPointUpperAlarm.' . $index,
            ];
            $data = snmp_get_multi_oid($device, $oids, $data, 'BACHMANN-BLUENET2-MIB', NULL, OBS_SNMP_ALL_NUMERIC_INDEX);
        }
        $entry = array_merge($entry, $data[$index]);
        print_debug_vars($entry);

        $options = [ 'entPhysicalIndex' => $entry['blueNet2VariableEntPhysicalIndex'] ];

        if ($entry['blueNet2VariableFriendlyName'] && $entry['blueNet2VariableFriendlyName'] !== $entry['blueNet2VariableName']) {
            $descr = $entry['blueNet2VariableFriendlyName'];
        } else {
            $descr = $entry['blueNet2VariableName'];
        }
        // Get descr for guid
        $guid = explode(' ', trim($entry['blueNet2VariableDataGuid']));
        array_pop($guid);
        $quid = implode('', $guid);
        if (isset($bluenet_guid[$quid])) {
            $descr .= ' (' . $bluenet_guid[$quid]['descr'] . ')';
            if (isset($bluenet_guid[$quid]['measured_class'])) {
                // Append measured label
                $options = array_merge($options, $bluenet_guid[$quid]);
            }
        }

        $scale    = si_to_scale($entry['blueNet2VariableScaling']);
        if (str_starts_with($entry['blueNet2VariableUnit'], 'kilo')) {
            // kiloWattHour, kiloVaHour, kiloVarHour
            $scale *= 1000;
        } elseif (str_starts_with($entry['blueNet2VariableUnit'], 'milli')) {
            // milliAmpere
            $scale /= 1000;
        }

        $oid_name = 'blueNet2VariableDataValue';
        $oid_num  = '.1.3.6.1.4.1.31770.2.2.8.4.1.5.' . $index;
        $value    = $entry[$oid_name];

        if ($class === 'powerfactor') {
            // do not set limit and reset negative value
            if ($value < 0) {
                $scale *= -1;
            }
        } elseif ($limit) {
            $options['limit_high']      = $entry['blueNet2VariableSetPointUpperAlarm']   * $scale;
            $options['limit_high_warn'] = $entry['blueNet2VariableSetPointUpperWarning'] * $scale;
            $options['limit_low']       = $entry['blueNet2VariableSetPointLowerAlarm']   * $scale;
            $options['limit_low_warn']  = $entry['blueNet2VariableSetPointLowerWarning'] * $scale;
        }
        if ($sensor_type === 'counter') {
            discover_counter($device, $class, $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, $options);
        } else {
            discover_sensor_ng($device, $class, $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, $options);
        }
    }
    //print_debug_vars($oids_fuses);
}

// EOF
