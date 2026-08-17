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

if (!isset($valid['sensor']['temperature']['UBNT-EdgeMAX-MIB-ubntThermTemperature']) &&
    !isset($valid['sensor']['temperature']['ENTITY-SENSOR-MIB-entPhySensorValue'])) {
    $scale = 0.001;
    $oids  = snmpwalk_cache_oid($device, 'lmTempSensorsEntry', [], 'LM-SENSORS-MIB');

    $cpu_package = NULL;
    foreach ($oids as $index => $entry) {
        $options = [ 'rename_rrd' => "lmsensors-$index" ];

        /* Detect Core sensors with Package IDs
        lmTempSensorsDevice.1 = Package id 1
        lmTempSensorsDevice.2 = Core 0
        lmTempSensorsDevice.3 = Core 1
        lmTempSensorsDevice.4 = Core 2
        lmTempSensorsDevice.5 = Core 3
        lmTempSensorsDevice.6 = Core 4
        lmTempSensorsDevice.7 = Core 5
        lmTempSensorsDevice.8 = Core 6
        lmTempSensorsDevice.9 = Core 7

        lmTempSensorsDevice.11 = Package id 0
        lmTempSensorsDevice.12 = coretemp-isa-0000:Core 0
        lmTempSensorsDevice.13 = coretemp-isa-0000:Core 1
        lmTempSensorsDevice.14 = coretemp-isa-0000:Core 2
        lmTempSensorsDevice.15 = coretemp-isa-0000:Core 3
        lmTempSensorsDevice.16 = coretemp-isa-0000:Core 4
        lmTempSensorsDevice.17 = coretemp-isa-0000:Core 5
        lmTempSensorsDevice.18 = coretemp-isa-0000:Core 6
        lmTempSensorsDevice.19 = coretemp-isa-0000:Core 7
         */
        if (is_numeric($cpu_package) && preg_match('/^(?:core\S*\-\d+:)?(Core.+)/', $entry['lmTempSensorsDevice'], $matches)) {
            //         'measured_class' => 'fiber',
            //         'measured_entity_label' => $port_label,
            $options['measured_class']        = 'processor';
            $options['measured_entity_label'] = "CPU $cpu_package";

            $descr = "CPU $cpu_package: {$matches[1]}";
        } elseif (preg_match('/^Package id (\d+)$/', $entry['lmTempSensorsDevice'], $matches)) {
            // After this sensor going CPU Cores sensors
            $cpu_package = $matches[1];

            $descr = "CPU $cpu_package";
        } else {
            // All other - reset CPU package sensors
            $cpu_package = NULL;

            $descr = str_ireplace([ 'temperature-', 'temp-', 'temp_' ], '', $entry['lmTempSensorsDevice']);
        }

        $oid   = ".1.3.6.1.4.1.2021.13.16.2.1.3.$index";
        $value = $entry['lmTempSensorsValue'];
        /* VM:
        lmTempSensorsDevice.1 = Core 0
        lmTempSensorsDevice.2 = Core 1
        lmTempSensorsValue.1 = 100000
        lmTempSensorsValue.2 = 100000
         */

        if ($entry['lmTempSensorsValue'] > 0 &&
            $value != 100000 && // VM always report 100000
            $value * $scale <= 200) {
            discover_sensor_ng($device, 'temperature', $mib, 'lmTempSensorsValue', $oid, $index, $descr, $scale, $value, $options);
        }
    }
    unset($cpu_package);
}

if (!isset($valid['sensor']['fanspeed']['UBNT-EdgeMAX-MIB-ubntFanRpm']) &&
    !isset($valid['sensor']['fanspeed']['ENTITY-SENSOR-MIB-entPhySensorValue'])) {
    $scale = 1;
    $oids  = snmpwalk_cache_oid($device, 'lmFanSensorsEntry', [], 'LM-SENSORS-MIB');
    foreach ($oids as $index => $entry) {
        $oid   = ".1.3.6.1.4.1.2021.13.16.3.1.3.$index";
        $descr = str_ireplace('fan-', '', $entry['lmFanSensorsDevice']);
        $value = $entry['lmFanSensorsValue'];
        if ($entry['lmFanSensorsValue'] > 0) {
            discover_sensor_ng($device, 'fanspeed', $mib, 'lmFanSensorsValue', $oid, $index, $descr, $scale, $value, [ 'rename_rrd' => "lmsensors-$index" ]);
        }
    }
}

//if (!isset($valid['sensor']['voltage'])) {
    $scale = 0.001;
    $oids  = snmpwalk_cache_oid($device, 'lmVoltSensorsEntry', [], 'LM-SENSORS-MIB');
    foreach ($oids as $index => $entry) {
        $oid   = ".1.3.6.1.4.1.2021.13.16.4.1.3.$index";
        $descr = str_ireplace([ 'voltage, ', 'volt-' ], '', $entry['lmVoltSensorsDevice']);
        $value = $entry['lmVoltSensorsValue'];
        if (is_numeric($entry['lmVoltSensorsValue']) && ($entry['lmVoltSensorsValue'] < 4000000000)) {
            // LM-SENSORS-MIB::lmVoltSensorsDevice.1 = STRING: in1
            // LM-SENSORS-MIB::lmVoltSensorsDevice.2 = STRING: in2
            // LM-SENSORS-MIB::lmVoltSensorsValue.1 = Gauge32: 4294967234
            // LM-SENSORS-MIB::lmVoltSensorsValue.2 = Gauge32: 273
            discover_sensor_ng($device, 'voltage', $mib, 'lmVoltSensorsValue', $oid, $index, $descr, $scale, $value, [ 'rename_rrd' => "lmsensors-$index" ]);
        }
    }
//}

//$oids = snmpwalk_cache_oid($device, 'lmMiscSensorsEntry', array(), 'LM-SENSORS-MIB');

unset($oids);

// EOF
