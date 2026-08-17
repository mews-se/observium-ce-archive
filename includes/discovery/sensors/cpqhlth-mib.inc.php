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

// Power Supplies

// CPQHLTH-MIB::cpqHeFltTolPwrSupplyCondition.0 = INTEGER: degraded(3)
// CPQHLTH-MIB::cpqHeFltTolPwrSupplyStatus.0 = INTEGER: other(1)
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyChassis.0.1 = INTEGER: 0
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyChassis.0.2 = INTEGER: 0
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyBay.0.1 = INTEGER: 1
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyBay.0.2 = INTEGER: 2
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyPresent.0.1 = INTEGER: present(3)
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyPresent.0.2 = INTEGER: present(3)
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyCondition.0.1 = INTEGER: ok(2)
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyCondition.0.2 = INTEGER: failed(4)
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyStatus.0.1 = INTEGER: noError(1)
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyStatus.0.2 = INTEGER: orringdiodeFailed(12)
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyMainVoltage.0.1 = INTEGER: 228
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyMainVoltage.0.2 = INTEGER: 0
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyCapacityUsed.0.1 = INTEGER: 92
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyCapacityUsed.0.2 = INTEGER: 0
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyCapacityMaximum.0.1 = INTEGER: 460
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyCapacityMaximum.0.2 = INTEGER: 460
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyRedundant.0.1 = INTEGER: notRedundant(2)
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyRedundant.0.2 = INTEGER: notRedundant(2)
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyModel.0.1 = STRING: "656362-B21"
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyModel.0.2 = STRING: "656362-B21"
// CPQHLTH-MIB::cpqHeFltTolPowerSupplySerialNumber.0.1 = STRING: "5BXRD0DLL5C1NL"
// CPQHLTH-MIB::cpqHeFltTolPowerSupplySerialNumber.0.2 = STRING: "5BXRD0DLL5C3J3"
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyAutoRev.0.1 = Hex-STRING: 00 00 00 00
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyAutoRev.0.2 = Hex-STRING: 00 00 00 00
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyHotPlug.0.1 = INTEGER: hotPluggable(3)
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyHotPlug.0.2 = INTEGER: hotPluggable(3)
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyFirmwareRev.0.1 = STRING: "1.03"
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyFirmwareRev.0.2 = STRING: "1.03"
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyHwLocation.0.1 = ""
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyHwLocation.0.2 = ""
// CPQHLTH-MIB::cpqHeFltTolPowerSupplySparePartNum.0.1 = STRING: "660184-001"
// CPQHLTH-MIB::cpqHeFltTolPowerSupplySparePartNum.0.2 = STRING: "660184-001"
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyRedundantPartner.0.1 = INTEGER: 0
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyRedundantPartner.0.2 = INTEGER: 0
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyErrorCondition.0.1 = INTEGER: noError(1)
// CPQHLTH-MIB::cpqHeFltTolPowerSupplyErrorCondition.0.2 = INTEGER: powerinputloss(6)

foreach (snmpwalk_cache_oid($device, 'cpqHeFltTolPwrSupply', [], 'CPQHLTH-MIB') as $index => $entry) {
    if (in_array($entry['cpqHeFltTolPowerSupplyPresent'], [ 'absent', 'other' ])) {
        continue;
    }

    $descr = "PSU " . $entry['cpqHeFltTolPowerSupplyBay'];
    $options       = [
        'entPhysicalClass' => 'powersupply',
        'measured_class'   => 'powersupply',
        'measured_entity_label' => $descr,
    ];

    if ($entry['cpqHeFltTolPowerSupplyCapacityMaximum'] > 0) {
        $oid      = ".1.3.6.1.4.1.232.6.2.9.3.1.7.$index";
        $oid_name = 'cpqHeFltTolPowerSupplyCapacityUsed';
        $value    = $entry['cpqHeFltTolPowerSupplyCapacityUsed'];

        $limits  = [
            'limit_high' => $entry['cpqHeFltTolPowerSupplyCapacityMaximum'],
            'rename_rrd' => "cpqhlth-cpqHeFltTolPwrSupply.$index"
        ];
        discover_sensor_ng($device, 'power', $mib, $oid_name, $oid, $index, $descr, 1, $value, array_merge($options, $limits));

        $oid      = ".1.3.6.1.4.1.232.6.2.9.3.1.6.$index";
        $oid_name = 'cpqHeFltTolPowerSupplyMainVoltage';
        $value    = $entry['cpqHeFltTolPowerSupplyMainVoltage'];

        discover_sensor_ng($device, 'voltage', $mib, $oid_name, $oid, $index, $descr, 1, $value, $options);
    }

    $oid   = ".1.3.6.1.4.1.232.6.2.9.3.1.4.$index";
    $value = $entry['cpqHeFltTolPowerSupplyCondition'];

    discover_status_ng($device, $mib, 'cpqHeFltTolPowerSupplyCondition', $oid, $index, 'cpqhlth-state', $descr . ' Status', $value, $options);

    $oid   = ".1.3.6.1.4.1.232.6.2.9.3.1.18.$index";
    $value = $entry['cpqHeFltTolPowerSupplyErrorCondition'];

    discover_status_ng($device, $mib, 'cpqHeFltTolPowerSupplyErrorCondition', $oid, $index, 'cpqHeFltTolPowerSupplyErrorCondition', $descr . ' Condition', $value, $options);

}

// Temperatures
$descr_count = [ 'cpu' => 1, 'powerSupply' => 1, 'ioBoard' => 1 ];

foreach (snmpwalk_cache_oid($device, 'CpqHeTemperatureEntry', [], 'CPQHLTH-MIB') as $index => $entry) {
    if (in_array($entry['cpqHeTemperatureLocale'], [ 'other', 'unknown', 'system', 'memory', 'storage', 'removableMedia' ])) {
        // other(1), unknown(2), system(3), systemBoard(4),
        // ioBoard(5), cpu(6), memory(7), storage(8),
        // removableMedia(9), powerSupply(10), ambient(11),
        // chassis(12), bridgeCard(13)
        print_debug("DEBUG: Sensor skipped by descr cpqHeTemperatureLocale:\n");
        print_debug_vars($entry);
        continue;
    }
    if ($entry['cpqHeTemperatureThreshold'] <= 0) {
        print_debug("DEBUG: Sensor skipped by zero cpqHeTemperatureThreshold:\n");
        print_debug_vars($entry);
        continue;
    }

    // Limits
    $options  = [ 'limit_high' => $entry['cpqHeTemperatureThreshold'] ];

    // Descr
    switch ($entry['cpqHeTemperatureLocale']) {
        case 'ioBoard':
            $descr = 'IO Board' . ' ' . $descr_count[$entry['cpqHeTemperatureLocale']]++;
            break;
        case 'cpu':
            $descr = 'CPU' . ' ' . $descr_count[$entry['cpqHeTemperatureLocale']]++;
            break;
        case 'ambient':
            $descr = 'Ambient';
            break;
        case 'chassis':
            $descr = 'Chassis';
            break;
        case 'powerSupply':
            $descr = 'PSU' . ' ' . $descr_count[$entry['cpqHeTemperatureLocale']]++;
            // Append entity label
            $options['entPhysicalClass']      = 'powersupply';
            $options['measured_class']        = 'powersupply';
            $options['measured_entity_label'] = $descr;
            break;
        default:
            $descr = rewrite_entity_name($entry['cpqHeTemperatureLocale']);
    }

    $oid      = ".1.3.6.1.4.1.232.6.2.6.8.1.4.$index";
    $oid_name = 'cpqHeTemperatureCelsius';
    $value    = $entry['cpqHeTemperatureCelsius'];

    $options['rename_rrd'] = "cpqhlth-CpqHeTemperatureEntry.$index";
    discover_sensor_ng($device, 'temperature', $mib, $oid_name, $oid, $index, $descr, 1, $value, $options);
}

// Memory Modules

// CPQHLTH-MIB::cpqHeResMem2ModuleHwLocation.0 = STRING: "PROC  1 DIMM  1 "
// CPQHLTH-MIB::cpqHeResMem2ModuleStatus.0 = INTEGER: good(4)
// CPQHLTH-MIB::cpqHeResMem2ModuleStatus.1 = INTEGER: notPresent(2)
// .1.3.6.1.4.1.232.6.2.14.13.1.19.0 = INTEGER: good(4)
// CPQHLTH-MIB::cpqHeResMem2ModuleCondition.1 = INTEGER: ok(2)

$oids = snmpwalk_cache_oid($device, 'cpqHeResMem2ModuleStatus', [], 'CPQHLTH-MIB');
if (snmp_status()) {
    $oids = snmpwalk_cache_oid($device, 'cpqHeResMem2ModuleHwLocation', $oids, 'CPQHLTH-MIB');
    $oids = snmpwalk_cache_oid($device, 'cpqHeResMem2ModuleType', $oids, 'CPQHLTH-MIB');
    $oids = snmpwalk_cache_oid($device, 'cpqHeResMem2ModuleFrequency', $oids, 'CPQHLTH-MIB');
    $oids = snmpwalk_cache_oid($device, 'cpqHeResMem2ModulePartNo', $oids, 'CPQHLTH-MIB');
    $oids = snmpwalk_cache_oid($device, 'cpqHeResMem2ModuleSize', $oids, 'CPQHLTH-MIB');
    $oids = snmpwalk_cache_oid($device, 'cpqHeResMem2ModuleCondition', $oids, 'CPQHLTH-MIB');
}

foreach ($oids as $index => $entry) {
    if (isset($entry['cpqHeResMem2ModuleStatus']) && $entry['cpqHeResMem2ModuleStatus'] != 'notPresent') {
        if (empty($entry['cpqHeResMem2ModuleHwLocation'])) {
            $cpqHeResMem2ModuleType = [
                // other(1),
                // board(2),
                // cpqSingleWidthModule(3),
                // cpqDoubleWidthModule(4),
                'simm'        => 'SIMM',
                'pcmcia'      => 'PCMCIA',
                // compaq-specific(7),
                'dimm'        => 'DIMM',
                // smallOutlineDimm(9),
                'rimm'        => 'RIMM',
                'srimm'       => 'SRIMM',
                'fb-dimm'     => 'FB-DIMM',
                'dimmddr'     => 'DIMM DDR',
                'dimmddr2'    => 'DIMM DDR2',
                'dimmddr3'    => 'DIMM DDR3',
                'dimmfbd2'    => 'DIMM FBD2',
                'fb-dimmddr2' => 'FB-DIMM DDR2',
                'fb-dimmddr3' => 'FB-DIMM DDR3',
                'dimmddr4'    => 'DIMM DDR4',
                // hpe-specific(20)
            ];

            if (isset($cpqHeResMem2ModuleType[$entry['cpqHeResMem2ModuleType']])) {
                $descr = $cpqHeResMem2ModuleType[$entry['cpqHeResMem2ModuleType']];
            } else {
                $descr = 'DIMM';
            }
            $descr .= ' ' . $index;

        } else {
            $descr = $entry['cpqHeResMem2ModuleHwLocation'];
        }

        $addition = [];
        if (!empty($entry['cpqHeResMem2ModuleSize'])) {
            $addition[] = format_bi($entry['cpqHeResMem2ModuleSize'] * 1024) . 'b';
        }
        if ($entry['cpqHeResMem2ModuleFrequency'] > 0) {
            $addition[] = $entry['cpqHeResMem2ModuleFrequency'] . 'MHz';
        }
        if (!empty($entry['cpqHeResMem2ModulePartNo'])) {
            $addition[] = trim($entry['cpqHeResMem2ModulePartNo']);
        }

        if ($addition) {
            $descr .= ' (' . implode(', ', $addition) . ')';
        }

        $oid    = ".1.3.6.1.4.1.232.6.2.14.13.1.19." . $index;
        $status = $entry['cpqHeResMem2ModuleStatus'];

        discover_status_ng($device, $mib, 'cpqHeResMem2ModuleStatus', $oid, $index, 'cpqHeResMem2ModuleStatus', $descr . ' Status', $status, ['entPhysicalClass' => 'memory']);

        $oid    = ".1.3.6.1.4.1.232.6.2.14.13.1.20." . $index;
        $status = $entry['cpqHeResMem2ModuleCondition'];
        discover_status_ng($device, $mib, 'cpqHeResMem2ModuleCondition', $oid, $index, 'cpqHeResMem2ModuleCondition', $descr . ' Condition', $status, ['entPhysicalClass' => 'memory']);
    }
}

unset($oids);

// EOF
