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

$jnxDomCurrentTable_oids = [
    'jnxDomCurrentModuleTemperature',
    'jnxDomCurrentModuleTemperatureHighAlarmThreshold',
    'jnxDomCurrentModuleTemperatureLowAlarmThreshold',
    'jnxDomCurrentModuleTemperatureHighWarningThreshold',
    'jnxDomCurrentModuleTemperatureLowWarningThreshold',

    'jnxDomCurrentModuleVoltage',
    'jnxDomCurrentModuleVoltageHighAlarmThreshold',
    'jnxDomCurrentModuleVoltageLowAlarmThreshold',
    'jnxDomCurrentModuleVoltageHighWarningThreshold',
    'jnxDomCurrentModuleVoltageLowWarningThreshold',

    'jnxDomCurrentTxLaserBiasCurrent',
    'jnxDomCurrentTxLaserBiasCurrentHighAlarmThreshold',
    'jnxDomCurrentTxLaserBiasCurrentLowAlarmThreshold',
    'jnxDomCurrentTxLaserBiasCurrentHighWarningThreshold',
    'jnxDomCurrentTxLaserBiasCurrentLowWarningThreshold',

    'jnxDomCurrentRxLaserPower',
    'jnxDomCurrentRxLaserPowerHighAlarmThreshold',
    'jnxDomCurrentRxLaserPowerLowAlarmThreshold',
    'jnxDomCurrentRxLaserPowerHighWarningThreshold',
    'jnxDomCurrentRxLaserPowerLowWarningThreshold',

    'jnxDomCurrentTxLaserOutputPower',
    'jnxDomCurrentTxLaserOutputPowerHighAlarmThreshold',
    'jnxDomCurrentTxLaserOutputPowerLowAlarmThreshold',
    'jnxDomCurrentTxLaserOutputPowerHighWarningThreshold',
    'jnxDomCurrentTxLaserOutputPowerLowWarningThreshold',

    'jnxDomCurrentModuleLaneCount'
];

$jnxDomCurrentLaneTable_oids = [
    'jnxDomCurrentLaneLaserTemperature',
    'jnxDomCurrentLaneTxLaserBiasCurrent',
    'jnxDomCurrentLaneRxLaserPower',
    'jnxDomCurrentLaneTxLaserOutputPower',
];

$oids = [];
foreach ($jnxDomCurrentTable_oids as $oid) {
    $oids = snmpwalk_cache_oid($device, $oid, $oids, 'JUNIPER-DOM-MIB');
}

$lane_oids = [];
foreach ($jnxDomCurrentLaneTable_oids as $oid) {
    $lane_oids = snmpwalk_cache_twopart_oid($device, $oid, $lane_oids, 'JUNIPER-DOM-MIB');
}

foreach ($oids as $index => $entry) {

    $entry['index'] = $index;
    $match          = [ 'measured_match' => [ 'entity_type' => 'port', 'field' => 'ifIndex', 'match' => '%index%' ] ];
    $options        = entity_measured_match_definition($device, $match, $entry);

    $jnxDomCurrentModuleLaneCount = safe_count($lane_oids[$index]);
    if (isset($lane_oids[$index]) && $jnxDomCurrentModuleLaneCount >= 8 &&
        $lane_oids[$index][1]['jnxDomCurrentLaneTxLaserBiasCurrent'] == 0 &&
        $lane_oids[$index][1]['jnxDomCurrentLaneRxLaserPower'] == 0 &&
        $lane_oids[$index][1]['jnxDomCurrentLaneTxLaserOutputPower'] == 0) {

        print_debug("DOM sensors for ZR+ module with 8 lanes");
        // This is a hard case for ZR+ modules, reports as multilane but really has 1 lane
        // Use only the first lane but with fix for temperature sensors.
        // https://jira.observium.org/browse/OBS-5016
        /**
         * jnxDomCurrentModuleLaneCount.547 = 8
         * jnxDomCurrentLaneLaserTemperature.547.0 = 49
         * jnxDomCurrentLaneLaserTemperature.547.1 = 49
         * jnxDomCurrentLaneLaserTemperature.547.2 = 49
         * jnxDomCurrentLaneLaserTemperature.547.3 = 49
         * jnxDomCurrentLaneLaserTemperature.547.4 = 49
         * jnxDomCurrentLaneLaserTemperature.547.5 = 49
         * jnxDomCurrentLaneLaserTemperature.547.6 = 49
         * jnxDomCurrentLaneLaserTemperature.547.7 = 49
         * jnxDomCurrentLaneTxLaserBiasCurrent.547.0 = 200000
         * jnxDomCurrentLaneTxLaserBiasCurrent.547.1 = 0
         * jnxDomCurrentLaneTxLaserBiasCurrent.547.2 = 0
         * jnxDomCurrentLaneTxLaserBiasCurrent.547.3 = 0
         * jnxDomCurrentLaneTxLaserBiasCurrent.547.4 = 0
         * jnxDomCurrentLaneTxLaserBiasCurrent.547.5 = 0
         * jnxDomCurrentLaneTxLaserBiasCurrent.547.6 = 0
         * jnxDomCurrentLaneTxLaserBiasCurrent.547.7 = 0
         * jnxDomCurrentLaneRxLaserPower.547.0 = -238
         * jnxDomCurrentLaneRxLaserPower.547.1 = 0
         * jnxDomCurrentLaneRxLaserPower.547.2 = 0
         * jnxDomCurrentLaneRxLaserPower.547.3 = 0
         * jnxDomCurrentLaneRxLaserPower.547.4 = 0
         * jnxDomCurrentLaneRxLaserPower.547.5 = 0
         * jnxDomCurrentLaneRxLaserPower.547.6 = 0
         * jnxDomCurrentLaneRxLaserPower.547.7 = 0
         * jnxDomCurrentLaneTxLaserOutputPower.547.0 = -298
         * jnxDomCurrentLaneTxLaserOutputPower.547.1 = 0
         * jnxDomCurrentLaneTxLaserOutputPower.547.2 = 0
         * jnxDomCurrentLaneTxLaserOutputPower.547.3 = 0
         * jnxDomCurrentLaneTxLaserOutputPower.547.4 = 0
         * jnxDomCurrentLaneTxLaserOutputPower.547.5 = 0
         * jnxDomCurrentLaneTxLaserOutputPower.547.6 = 0
         * jnxDomCurrentLaneTxLaserOutputPower.547.7 = 0
         */
        /**
         * jnxDomCurrentModuleTemperature.547 = 61
         * jnxDomCurrentModuleTemperatureHighAlarmThreshold.547 = 75
         * jnxDomCurrentModuleTemperatureLowAlarmThreshold.547 = -5
         * jnxDomCurrentModuleTemperatureHighWarningThreshold.547 = 70
         * jnxDomCurrentModuleTemperatureLowWarningThreshold.547 = 0
         * jnxDomCurrentModuleVoltage.547 = 3156
         * jnxDomCurrentModuleVoltageHighAlarmThreshold.547 = 3630
         * jnxDomCurrentModuleVoltageLowAlarmThreshold.547 = 2970
         * jnxDomCurrentModuleVoltageHighWarningThreshold.547 = 3464
         * jnxDomCurrentModuleVoltageLowWarningThreshold.547 = 3134
         * jnxDomCurrentTxLaserBiasCurrent.547 = 0
         * jnxDomCurrentTxLaserBiasCurrentHighAlarmThreshold.547 = 210000
         * jnxDomCurrentTxLaserBiasCurrentLowAlarmThreshold.547 = 190000
         * jnxDomCurrentTxLaserBiasCurrentHighWarningThreshold.547 = 205000
         * jnxDomCurrentTxLaserBiasCurrentLowWarningThreshold.547 = 195000
         * jnxDomCurrentRxLaserPower.547 = -238
         * jnxDomCurrentRxLaserPowerHighAlarmThreshold.547 = 400
         * jnxDomCurrentRxLaserPowerLowAlarmThreshold.547 = -1801
         * jnxDomCurrentRxLaserPowerHighWarningThreshold.547 = 200
         * jnxDomCurrentRxLaserPowerLowWarningThreshold.547 = -1600
         * jnxDomCurrentTxLaserOutputPower.547 = -298
         * jnxDomCurrentTxLaserOutputPowerHighAlarmThreshold.547 = 400
         * jnxDomCurrentTxLaserOutputPowerLowAlarmThreshold.547 = -1899
         * jnxDomCurrentTxLaserOutputPowerHighWarningThreshold.547 = 300
         * jnxDomCurrentTxLaserOutputPowerLowWarningThreshold.547 = -1801
         */

        // get first lane entry
        $i          = 0;
        $lane       = $i + 1;
        $lane_index = "$index.$i";
        $lane_entry = $lane_oids[$index][$i];

        // Temperature
        $descr    = $options['port_label'] . ' Temperature';
        $scale    = 1;
        $limits = [
            'limit_high'      => $entry['jnxDomCurrentModuleTemperatureHighAlarmThreshold'],
            'limit_low'       => $entry['jnxDomCurrentModuleTemperatureLowAlarmThreshold'],
            'limit_high_warn' => $entry['jnxDomCurrentModuleTemperatureHighWarningThreshold'],
            'limit_low_warn'  => $entry['jnxDomCurrentModuleTemperatureLowWarningThreshold']
        ];

        if ($entry['jnxDomCurrentModuleTemperature'] > 0) {
            $oid_name = 'jnxDomCurrentModuleTemperature';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.1.1.1.8.{$index}";
            $value    = $entry[$oid_name];

            discover_sensor_ng($device, 'temperature', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, array_merge($options, $limits));
        } else {
            $oid_name = 'jnxDomCurrentLaneLaserTemperature';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.2.1.1.9.{$lane_index}";
            $value    = $lane_entry[$oid_name];

            discover_sensor_ng($device, 'temperature', $mib, $oid_name, $oid_num, $lane_index, $descr, $scale, $value, array_merge($options, $limits));
        }

        // Voltage
        if (isset($entry['jnxDomCurrentModuleVoltage'])) {
            $descr    = $options['port_label'] . " Voltage";
            $oid_name = 'jnxDomCurrentModuleVoltage';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.1.1.1.25.{$index}";
            $scale    = 0.001;
            $value    = $entry[$oid_name];

            $limits = [
                'limit_high'      => $entry['jnxDomCurrentModuleVoltageHighAlarmThreshold'] * $scale,
                'limit_low'       => $entry['jnxDomCurrentModuleVoltageLowAlarmThreshold'] * $scale,
                'limit_high_warn' => $entry['jnxDomCurrentModuleVoltageHighWarningThreshold'] * $scale,
                'limit_low_warn'  => $entry['jnxDomCurrentModuleVoltageLowWarningThreshold'] * $scale
            ];

            discover_sensor_ng($device, 'voltage', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, array_merge($options, $limits));
        }

        // Bias
        $descr    = $options['port_label'] . " TX Bias";
        $scale    = 0.000001;
        $limits = [
            'limit_high'      => $entry['jnxDomCurrentTxLaserBiasCurrentHighAlarmThreshold'] * $scale,
            'limit_low'       => $entry['jnxDomCurrentTxLaserBiasCurrentLowAlarmThreshold'] * $scale,
            'limit_high_warn' => $entry['jnxDomCurrentTxLaserBiasCurrentHighWarningThreshold'] * $scale,
            'limit_low_warn'  => $entry['jnxDomCurrentTxLaserBiasCurrentLowWarningThreshold'] * $scale
        ];

        if ($entry['jnxDomCurrentTxLaserBiasCurrent'] != 0) {
            $oid_name = 'jnxDomCurrentTxLaserBiasCurrent';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.1.1.1.6.{$index}";
            $value    = $entry[$oid_name];

            discover_sensor_ng($device, 'current', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, array_merge($options, $limits));
        } else {
            $oid_name = 'jnxDomCurrentLaneTxLaserBiasCurrent';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.2.1.1.7.{$lane_index}";
            $value    = $lane_entry[$oid_name];

            discover_sensor_ng($device, 'current', $mib, $oid_name, $oid_num, $lane_index, $descr, $scale, $value, array_merge($options, $limits));
        }

        // RX Power
        $descr    = $options['port_label'] . " RX Power";
        $scale    = 0.01;

        $limits = [
            'limit_high'      => $entry['jnxDomCurrentRxLaserPowerHighAlarmThreshold'] * $scale,
            'limit_low'       => $entry['jnxDomCurrentRxLaserPowerLowAlarmThreshold'] * $scale,
            'limit_high_warn' => $entry['jnxDomCurrentRxLaserPowerHighWarningThreshold'] * $scale,
            'limit_low_warn'  => $entry['jnxDomCurrentRxLaserPowerLowWarningThreshold'] * $scale
        ];

        if ($entry['jnxDomCurrentRxLaserPower'] != 0) {
            $oid_name = 'jnxDomCurrentRxLaserPower';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.1.1.1.5.{$index}";
            $value    = $entry[$oid_name];

            discover_sensor_ng($device, 'dbm', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, array_merge($options, $limits));
        } else {
            $oid_name = 'jnxDomCurrentLaneRxLaserPower';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.2.1.1.6.{$lane_index}";
            $value    = $lane_entry[$oid_name];

            discover_sensor_ng($device, 'dbm', $mib, $oid_name, $oid_num, $lane_index, $descr, $scale, $value, array_merge($options, $limits));
        }

        // TX Power
        $descr    = $options['port_label'] . " TX Power";
        $scale    = 0.01;

        $limits = [
            'limit_high'      => $entry['jnxDomCurrentTxLaserOutputPowerHighAlarmThreshold'] * $scale,
            'limit_low'       => $entry['jnxDomCurrentTxLaserOutputPowerLowAlarmThreshold'] * $scale,
            'limit_high_warn' => $entry['jnxDomCurrentTxLaserOutputPowerHighWarningThreshold'] * $scale,
            'limit_low_warn'  => $entry['jnxDomCurrentTxLaserOutputPowerLowWarningThreshold'] * $scale
        ];

        if ($entry['jnxDomCurrentTxLaserOutputPower'] != 0) {
            $oid_name = 'jnxDomCurrentTxLaserOutputPower';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.1.1.1.7.{$index}";
            $value    = $entry[$oid_name];

            discover_sensor_ng($device, 'dbm', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, array_merge($options, $limits));
        } else {
            $oid_name = 'jnxDomCurrentLaneTxLaserOutputPower';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.2.1.1.8.{$lane_index}";
            $value    = $lane_entry[$oid_name];

            discover_sensor_ng($device, 'dbm', $mib, $oid_name, $oid_num, $lane_index, $descr, $scale, $value, array_merge($options, $limits));
        }

    } elseif (isset($lane_oids[$index]) && $jnxDomCurrentModuleLaneCount > 1) {
        print_debug("DOM sensors for multi-lane module with $jnxDomCurrentModuleLaneCount lanes");
        // Multi-lane sensors.
        // Note, jnxDomCurrentModuleLaneCount can be 0 and 4 incorrectly
        foreach ($lane_oids[$index] as $i => $lane_entry) {
            $lane       = $i + 1;
            $lane_index = "$index.$i";

            $descr    = $options['port_label'] . " Lane $lane Temperature";
            $oid_name = 'jnxDomCurrentLaneLaserTemperature';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.2.1.1.9.{$lane_index}";
            $scale    = 1;
            $value    = $lane_entry[$oid_name];

            $limits = [
                'limit_high'      => $entry['jnxDomCurrentModuleTemperatureHighAlarmThreshold'],
                'limit_low'       => $entry['jnxDomCurrentModuleTemperatureLowAlarmThreshold'],
                'limit_high_warn' => $entry['jnxDomCurrentModuleTemperatureHighWarningThreshold'],
                'limit_low_warn'  => $entry['jnxDomCurrentModuleTemperatureLowWarningThreshold']
            ];

            $invalid = $value == 0 &&
                       $entry['jnxDomCurrentModuleTemperatureHighAlarmThreshold'] == 0 &&
                       $entry['jnxDomCurrentModuleTemperatureLowAlarmThreshold'] == 0 &&
                       $entry['jnxDomCurrentModuleTemperatureHighWarningThreshold'] == 0 &&
                       $entry['jnxDomCurrentModuleTemperatureLowWarningThreshold'] == 0;
            if (!$invalid) {
                $limits['rename_rrd'] = "juniper-dom-$lane_index";
                discover_sensor_ng($device, 'temperature', $mib, $oid_name, $oid_num, $lane_index, $descr, $scale, $value, array_merge($options, $limits));
            }

            // jnxDomCurrentTxLaserBiasCurrent
            $descr    = $options['port_label'] . " Lane $lane TX Bias";
            $oid_name = 'jnxDomCurrentLaneTxLaserBiasCurrent';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.2.1.1.7.{$lane_index}";
            $scale    = 0.000001;
            $value    = $lane_entry[$oid_name];

            $limits = [
                'limit_high'      => $entry['jnxDomCurrentTxLaserBiasCurrentHighAlarmThreshold'] * $scale,
                'limit_low'       => $entry['jnxDomCurrentTxLaserBiasCurrentLowAlarmThreshold'] * $scale,
                'limit_high_warn' => $entry['jnxDomCurrentTxLaserBiasCurrentHighWarningThreshold'] * $scale,
                'limit_low_warn'  => $entry['jnxDomCurrentTxLaserBiasCurrentLowWarningThreshold'] * $scale
            ];

            $limits['rename_rrd'] = "juniper-dom-$lane_index";
            discover_sensor_ng($device, 'current', $mib, $oid_name, $oid_num, $lane_index, $descr, $scale, $value, array_merge($options, $limits));

            # jnxDomCurrentRxLaserPower[508] -507 0.01 dbm
            $descr    = $options['port_label'] . " Lane $lane RX Power";
            $oid_name = 'jnxDomCurrentLaneRxLaserPower';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.2.1.1.6.{$lane_index}";
            $scale    = 0.01;
            $value    = $lane_entry[$oid_name];

            $limits = [
                'limit_high'      => $entry['jnxDomCurrentRxLaserPowerHighAlarmThreshold'] * $scale,
                'limit_low'       => $entry['jnxDomCurrentRxLaserPowerLowAlarmThreshold'] * $scale,
                'limit_high_warn' => $entry['jnxDomCurrentRxLaserPowerHighWarningThreshold'] * $scale,
                'limit_low_warn'  => $entry['jnxDomCurrentRxLaserPowerLowWarningThreshold'] * $scale
            ];

            $limits['rename_rrd'] = "juniper-dom-rx-$lane_index";
            discover_sensor_ng($device, 'dbm', $mib, $oid_name, $oid_num, $lane_index, $descr, $scale, $value, array_merge($options, $limits));

            # jnxDomCurrentTxLaserOutputPower[508] -507 0.01 dbm
            $descr    = $options['port_label'] . " Lane $lane TX Power";
            $oid_name = 'jnxDomCurrentLaneTxLaserOutputPower';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.2.1.1.8.{$lane_index}";
            $scale    = 0.01;
            $value    = $lane_entry[$oid_name];

            $limits = [
                'limit_high'      => $entry['jnxDomCurrentTxLaserOutputPowerHighAlarmThreshold'] * $scale,
                'limit_low'       => $entry['jnxDomCurrentTxLaserOutputPowerLowAlarmThreshold'] * $scale,
                'limit_high_warn' => $entry['jnxDomCurrentTxLaserOutputPowerHighWarningThreshold'] * $scale,
                'limit_low_warn'  => $entry['jnxDomCurrentTxLaserOutputPowerLowWarningThreshold'] * $scale
            ];

            $limits['rename_rrd'] = "juniper-dom-tx-$lane_index";
            discover_sensor_ng($device, 'dbm', $mib, $oid_name, $oid_num, $lane_index, $descr, $scale, $value, array_merge($options, $limits));
        }
    } else {
        print_debug("DOM sensors for single lane module");

        if ($entry['jnxDomCurrentTxLaserBiasCurrent'] == 0 &&
            $entry['jnxDomCurrentTxLaserOutputPower'] == 0 && $entry['jnxDomCurrentRxLaserPower'] == 0) {
            // Skip empty dom sensors
            continue;
        }

        # jnxDomCurrentModuleTemperature[508] 35
        # jnxDomCurrentModuleTemperatureHighAlarmThreshold[508] 100
        # jnxDomCurrentModuleTemperatureLowAlarmThreshold[508] -25
        # jnxDomCurrentModuleTemperatureHighWarningThreshold[508] 95
        # jnxDomCurrentModuleTemperatureLowWarningThreshold[508] -20
        $descr    = $options['port_label'] . ' Temperature';
        $oid_name = 'jnxDomCurrentModuleTemperature';
        $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.1.1.1.8.{$index}";
        $scale    = 1;
        $value    = $entry[$oid_name];

        $limits = [
            'limit_high'      => $entry['jnxDomCurrentModuleTemperatureHighAlarmThreshold'],
            'limit_low'       => $entry['jnxDomCurrentModuleTemperatureLowAlarmThreshold'],
            'limit_high_warn' => $entry['jnxDomCurrentModuleTemperatureHighWarningThreshold'],
            'limit_low_warn'  => $entry['jnxDomCurrentModuleTemperatureLowWarningThreshold']
        ];


        $limits['rename_rrd'] = "juniper-dom-$index";
        discover_sensor_ng($device, 'temperature', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, array_merge($options, $limits));

        // jnxDomCurrentModuleVoltage
        if (isset($entry['jnxDomCurrentModuleVoltage'])) {
            $descr    = $options['port_label'] . " Voltage";
            $oid_name = 'jnxDomCurrentModuleVoltage';
            $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.1.1.1.25.{$index}";
            $scale    = 0.001;
            $value    = $entry[$oid_name];

            $limits = [
                'limit_high'      => $entry['jnxDomCurrentModuleVoltageHighAlarmThreshold'] * $scale,
                'limit_low'       => $entry['jnxDomCurrentModuleVoltageLowAlarmThreshold'] * $scale,
                'limit_high_warn' => $entry['jnxDomCurrentModuleVoltageHighWarningThreshold'] * $scale,
                'limit_low_warn'  => $entry['jnxDomCurrentModuleVoltageLowWarningThreshold'] * $scale
            ];

            discover_sensor_ng($device, 'voltage', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, array_merge($options, $limits));
        }

        // jnxDomCurrentTxLaserBiasCurrent
        $descr    = $options['port_label'] . " TX Bias";
        $oid_name = 'jnxDomCurrentTxLaserBiasCurrent';
        $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.1.1.1.6.{$index}";
        $scale    = 0.000001;
        $value    = $entry[$oid_name];

        $limits = [
            'limit_high'      => $entry['jnxDomCurrentTxLaserBiasCurrentHighAlarmThreshold'] * $scale,
            'limit_low'       => $entry['jnxDomCurrentTxLaserBiasCurrentLowAlarmThreshold'] * $scale,
            'limit_high_warn' => $entry['jnxDomCurrentTxLaserBiasCurrentHighWarningThreshold'] * $scale,
            'limit_low_warn'  => $entry['jnxDomCurrentTxLaserBiasCurrentLowWarningThreshold'] * $scale
        ];

        $limits['rename_rrd'] = "juniper-dom-$index";
        discover_sensor_ng($device, 'current', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, array_merge($options, $limits));

        # jnxDomCurrentRxLaserPower[508] -507 0.01 dbm
        $descr    = $options['port_label'] . " RX Power";
        $oid_name = 'jnxDomCurrentRxLaserPower';
        $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.1.1.1.5.{$index}";
        $scale    = 0.01;
        $value    = $entry[$oid_name];

        $limits = [
            'limit_high'      => $entry['jnxDomCurrentRxLaserPowerHighAlarmThreshold'] * $scale,
            'limit_low'       => $entry['jnxDomCurrentRxLaserPowerLowAlarmThreshold'] * $scale,
            'limit_high_warn' => $entry['jnxDomCurrentRxLaserPowerHighWarningThreshold'] * $scale,
            'limit_low_warn'  => $entry['jnxDomCurrentRxLaserPowerLowWarningThreshold'] * $scale
        ];

        $limits['rename_rrd'] = "juniper-dom-rx-$index";
        discover_sensor_ng($device, 'dbm', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, array_merge($options, $limits));

        # jnxDomCurrentTxLaserOutputPower[508] -507 0.01 dbm
        $descr    = $options['port_label'] . " TX Power";
        $oid_name = 'jnxDomCurrentTxLaserOutputPower';
        $oid_num  = ".1.3.6.1.4.1.2636.3.60.1.1.1.1.7.{$index}";
        $type     = 'juniper-dom-tx'; // $mib . '-' . $oid_name;
        $scale    = 0.01;
        $value    = $entry[$oid_name];

        $limits = [
            'limit_high'      => $entry['jnxDomCurrentTxLaserOutputPowerHighAlarmThreshold'] * $scale,
            'limit_low'       => $entry['jnxDomCurrentTxLaserOutputPowerLowAlarmThreshold'] * $scale,
            'limit_high_warn' => $entry['jnxDomCurrentTxLaserOutputPowerHighWarningThreshold'] * $scale,
            'limit_low_warn'  => $entry['jnxDomCurrentTxLaserOutputPowerLowWarningThreshold'] * $scale
        ];

        $limits['rename_rrd'] = "juniper-dom-tx-$index";
        discover_sensor_ng($device, 'dbm', $mib, $oid_name, $oid_num, $index, $descr, $scale, $value, array_merge($options, $limits));

    }

}

// EOF
