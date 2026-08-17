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

/*
SITE-MONITORING-MIB::es1DcSys1DataName.1 = STRING: Battery / Global / State
SITE-MONITORING-MIB::es1DcSys1DataName.11 = STRING: Converters / Voltage
SITE-MONITORING-MIB::es1DcSys1DataName.16 = STRING: Battery / Regulation / Operating Mode
SITE-MONITORING-MIB::es1DcSys1DataName.17 = STRING: Battery / Regulation / Previous DC Operating Mode
SITE-MONITORING-MIB::es1DcSys1DataName.18 = STRING: Battery / Regulation / Target Voltage
SITE-MONITORING-MIB::es1DcSys1DataName.19 = STRING: Battery / Regulation / Max Charge Current
SITE-MONITORING-MIB::es1DcSys1DataName.21 = STRING: Converters / Power
SITE-MONITORING-MIB::es1DcSys1DataName.22 = STRING: Converters / Current
SITE-MONITORING-MIB::es1DcSys1DataName.23 = STRING: Converters / Installed Power
SITE-MONITORING-MIB::es1DcSys1DataName.32 = STRING: Converters / Count / Members
SITE-MONITORING-MIB::es1DcSys1DataName.33 = STRING: Converters / Count / OK
SITE-MONITORING-MIB::es1DcSys1DataName.51 = STRING: Load / Global / Power
SITE-MONITORING-MIB::es1DcSys1DataName.52 = STRING: Load / Global / Current
SITE-MONITORING-MIB::es1DcSys1DataName.60 = STRING: Battery / Measurements / Voltage
SITE-MONITORING-MIB::es1DcSys1DataName.61 = STRING: Battery / Measurements / Input Current
SITE-MONITORING-MIB::es1DcSys1DataName.62 = STRING: Battery / Measurements / Input Power
SITE-MONITORING-MIB::es1DcSys1DataName.71 = STRING: Battery / Measurements / Temperature
SITE-MONITORING-MIB::es1DcSys1DataName.81 = STRING: Battery / Estimates / Resistance
SITE-MONITORING-MIB::es1DcSys1DataName.90 = STRING: Battery / Capacity / Nominal
SITE-MONITORING-MIB::es1DcSys1DataName.91 = STRING: Battery / Autonomy / State Of Charge
SITE-MONITORING-MIB::es1DcSys1DataName.92 = STRING: Battery / Autonomy / Calculated Autonomy
SITE-MONITORING-MIB::es1DcSys1DataName.93 = STRING: Battery / Capacity / Remaining
SITE-MONITORING-MIB::es1DcSys1DataName.94 = STRING: Battery / Autonomy / Battery Current Integration
SITE-MONITORING-MIB::es1DcSys1DataName.95 = STRING: Battery / Capacity / State Of Health
SITE-MONITORING-MIB::es1DcSys1DataName.202 = STRING: Battery / Boost / Last Start Trigger
SITE-MONITORING-MIB::es1DcSys1DataName.203 = STRING: Battery / Boost / Last Stop Trigger
SITE-MONITORING-MIB::es1DcSys1DataName.205 = STRING: Battery / Boost / Duration
SITE-MONITORING-MIB::es1DcSys1DataName.206 = STRING: Battery / Boost / Start Time
SITE-MONITORING-MIB::es1DcSys1DataName.251 = STRING: Battery / Test / Result
SITE-MONITORING-MIB::es1DcSys1DataName.252 = STRING: Battery / Test / Discharged Capacity Ratio
SITE-MONITORING-MIB::es1DcSys1DataName.253 = STRING: Battery / Test / Discharged Capacity
SITE-MONITORING-MIB::es1DcSys1DataName.254 = STRING: Battery / Test / Final Voltage
SITE-MONITORING-MIB::es1DcSys1DataName.255 = STRING: Battery / Test / Duration
SITE-MONITORING-MIB::es1DcSys1DataName.256 = STRING: Battery / Test / Start Time
SITE-MONITORING-MIB::es1DcSys1DataName.261 = STRING: Battery / Test / Previous Result
SITE-MONITORING-MIB::es1DcSys1DataName.262 = STRING: Battery / Test / Previous Discharged Capacity Ratio
SITE-MONITORING-MIB::es1DcSys1DataName.263 = STRING: Battery / Test / Previous Discharged Capacity
SITE-MONITORING-MIB::es1DcSys1DataName.264 = STRING: Battery / Test / Previous Final Voltage
SITE-MONITORING-MIB::es1DcSys1DataName.265 = STRING: Battery / Test / Previous Duration
SITE-MONITORING-MIB::es1DcSys1DataName.266 = STRING: Battery / Test / Previous Start Time
SITE-MONITORING-MIB::es1DcSys1DataName.271 = STRING: Battery / Test / Next Scheduled Battery Test
SITE-MONITORING-MIB::es1DcSys1DataName.301 = STRING: Battery Disconnect / Global / State
SITE-MONITORING-MIB::es1DcSys1DataName.302 = STRING: Battery Disconnect / Global / Reason
SITE-MONITORING-MIB::es1DcSys1DataName.303 = STRING: Battery Disconnect / Global / Feedback State
SITE-MONITORING-MIB::es1DcSys1DataValue.1 = STRING: Charge
SITE-MONITORING-MIB::es1DcSys1DataValue.11 = STRING: 54.32
SITE-MONITORING-MIB::es1DcSys1DataValue.16 = STRING: Float
SITE-MONITORING-MIB::es1DcSys1DataValue.17 = STRING: Float
SITE-MONITORING-MIB::es1DcSys1DataValue.18 = STRING: 54.60
SITE-MONITORING-MIB::es1DcSys1DataValue.19 = STRING: 25
SITE-MONITORING-MIB::es1DcSys1DataValue.21 = STRING: 0.0
SITE-MONITORING-MIB::es1DcSys1DataValue.22 = STRING: 0.0
SITE-MONITORING-MIB::es1DcSys1DataValue.23 = STRING: 1000.0
SITE-MONITORING-MIB::es1DcSys1DataValue.32 = STRING: 1
SITE-MONITORING-MIB::es1DcSys1DataValue.33 = STRING: 1
SITE-MONITORING-MIB::es1DcSys1DataValue.51 = STRING: 0.0
SITE-MONITORING-MIB::es1DcSys1DataValue.52 = STRING: 0.0
SITE-MONITORING-MIB::es1DcSys1DataValue.60 = STRING: 49.95
SITE-MONITORING-MIB::es1DcSys1DataValue.61 = STRING: 0.0
SITE-MONITORING-MIB::es1DcSys1DataValue.62 = STRING: 0
SITE-MONITORING-MIB::es1DcSys1DataValue.71 = STRING: 32.3
SITE-MONITORING-MIB::es1DcSys1DataValue.81 = STRING: 6.1
SITE-MONITORING-MIB::es1DcSys1DataValue.90 = STRING: 150.00
SITE-MONITORING-MIB::es1DcSys1DataValue.91 = STRING: 96.49
SITE-MONITORING-MIB::es1DcSys1DataValue.92 = STRING: 534.11
SITE-MONITORING-MIB::es1DcSys1DataValue.93 = STRING: 144.74
SITE-MONITORING-MIB::es1DcSys1DataValue.94 = STRING: 0.000
SITE-MONITORING-MIB::es1DcSys1DataValue.95 = STRING: 99.58
SITE-MONITORING-MIB::es1DcSys1DataValue.202 = STRING: None
SITE-MONITORING-MIB::es1DcSys1DataValue.203 = STRING: None
SITE-MONITORING-MIB::es1DcSys1DataValue.205 = STRING: 0.00
SITE-MONITORING-MIB::es1DcSys1DataValue.206 = STRING:
SITE-MONITORING-MIB::es1DcSys1DataValue.251 = STRING: Never Tested
SITE-MONITORING-MIB::es1DcSys1DataValue.252 = STRING: 0.00
SITE-MONITORING-MIB::es1DcSys1DataValue.253 = STRING: 0.00
SITE-MONITORING-MIB::es1DcSys1DataValue.254 = STRING: 0.0
SITE-MONITORING-MIB::es1DcSys1DataValue.255 = STRING: 0.00
SITE-MONITORING-MIB::es1DcSys1DataValue.256 = STRING:
SITE-MONITORING-MIB::es1DcSys1DataValue.261 = STRING: Never Tested
SITE-MONITORING-MIB::es1DcSys1DataValue.262 = STRING: 0.00
SITE-MONITORING-MIB::es1DcSys1DataValue.263 = STRING: 0.00
SITE-MONITORING-MIB::es1DcSys1DataValue.264 = STRING: 0.0
SITE-MONITORING-MIB::es1DcSys1DataValue.265 = STRING: 0.00
SITE-MONITORING-MIB::es1DcSys1DataValue.266 = STRING:
SITE-MONITORING-MIB::es1DcSys1DataValue.271 = STRING:
SITE-MONITORING-MIB::es1DcSys1DataValue.301 = STRING: Not Present
SITE-MONITORING-MIB::es1DcSys1DataValue.302 = STRING: NA
SITE-MONITORING-MIB::es1DcSys1DataValue.303 = STRING: Not Present
 */

$oids = snmpwalk_cache_oid($device, 'es1DcSys1ConfigName', [], $mib);
$oids = snmpwalk_cache_oid($device, 'es1DcSys1ConfigValue', $oids, $mib);
$data_config = [];
foreach ($oids as $index => $entry) {
    [ $name1, $name2, $name3 ] = explode(' / ', $entry['es1DcSys1ConfigName'], 3);
    if (safe_empty($name3)) {
        // es1DcSys1DataName.21 = Converters / Power
        $name3 = $name2;
        $name2 = 'Global';
    }
    $data_config[$name1][$name2][$name3] = $entry['es1DcSys1ConfigValue'];
}
print_debug_vars($data_config);

$oids = snmpwalk_cache_oid($device, 'es1DcSys1DataName', [], $mib);
$oids = snmpwalk_cache_oid($device, 'es1DcSys1DataValue', $oids, $mib);
$data = [];
foreach ($oids as $index => $entry) {
    [ $name1, $name2, $name3 ] = explode(' / ', $entry['es1DcSys1DataName'], 3);
    if (safe_empty($name3)) {
        // es1DcSys1DataName.21 = Converters / Power
        $name3 = $name2;
        $name2 = 'Global';
    }
    $data[$name1][$name2][$name3] = [ $index, $entry['es1DcSys1DataValue'], "$name1 / $name3" ];
}
print_debug_vars($data);

foreach ($data as $name1 => $values1) {
    foreach ($values1 as $name2 => $values2) {
        if (in_array($name2, [ 'Test', 'Boost' ], TRUE)) {
            continue;
        }
        foreach ($values2 as $name3 => $entry) {

            $index    = $entry[0];
            $value    = $entry[1];
            $descr    = $entry[2];

            $oid_name = 'es1DcSys1DataValue';
            $oid_num  = '.1.3.6.1.4.1.12551.20.1.20.1.20.1.13.2.1.3.' . $index;

            if (in_array($name3, [ 'State', 'Operating Mode' ], TRUE)) {
                discover_status_ng($device, $mib, $oid_name, $oid_num, $index, 'DcSysData', $descr, $value, [ 'entPhysicalClass' => $name1 ]);
                continue;
            }
            if (str_starts_with($name3, 'State Of ')) {
                discover_sensor_ng($device, 'capacity', $mib, $oid_name, $oid_num, $index, $descr, 1, $value);
                continue;
            }
            if ($descr === 'Battery / Calculated Autonomy') {
                discover_sensor_ng($device, 'runtime', $mib, $oid_name, $oid_num, $index, $descr, 1, $value);
                continue;
            }

            if (str_starts_with($name3, 'Installed') || $value == 0) {
                continue;
            }
            if (in_array($name2, [ 'Global', 'Measurements' ], TRUE)) {
                if (str_ends_with($name3, 'Voltage')) {
                    $class = 'voltage';
                } elseif (str_ends_with($name3, 'Current')) {
                    $class = 'current';
                } elseif (str_ends_with($name3, 'Power')) {
                    $class = 'power';
                } elseif (str_ends_with($name3, 'Temperature')) {
                    // FIXME.
                    // SITE-MONITORING-MIB::siteV1CfgWebServerLocalizationTempUnit.0 = STRING: Celsius
                    $class = 'temperature';
                } elseif (str_ends_with($name3, 'Humidity')) {
                    $class = 'humidity';
                } else {
                    continue;
                }
            } else {
                continue;
            }

            $options = [];
            // Limits
            if (isset($data_config[$name1][ucfirst($class)])) {
                $limits = $data_config[$name1][ucfirst($class)];

                if (isset($limits['Alarm Low Threshold'])) {
                    $options['limit_low']       = $limits['Alarm Low Threshold'];
                    if (isset($limits['Alarm Low Hysteresis'])) {
                        $options['limit_low_warn'] = $options['limit_low'] + $limits['Alarm Low Hysteresis'];
                    }
                }
                if (isset($limits['Alarm Low Clear Threshold'])) {
                    $options['limit_low_warn']  = $limits['Alarm Low Clear Threshold'];
                }
                if (isset($limits['Alarm High Threshold'])) {
                    $options['limit_high']      = $limits['Alarm High Threshold'];
                    if (isset($limits['Alarm High Hysteresis'])) {
                        $options['limit_high_warn'] = $options['limit_high'] - $limits['Alarm High Hysteresis'];
                    }
                }

                if (isset($limits['Alarm High Clear Threshold'])) {
                    $options['limit_high_warn'] = $limits['Alarm High Clear Threshold'];
                }
            }

            discover_sensor_ng($device, $class, $mib, $oid_name, $oid_num, $index, $descr, 1, $value, $options);
        }
    }
}
unset($oids, $data, $data_config);

/**
SITE-MONITORING-MIB::es1ConvSys1DataName.31 = STRING: Converters / Bypass / State
SITE-MONITORING-MIB::es1ConvSys1DataName.102 = STRING: AC Outputs / Global / Current
SITE-MONITORING-MIB::es1ConvSys1DataName.103 = STRING: AC Outputs / Global / Active Power
SITE-MONITORING-MIB::es1ConvSys1DataName.104 = STRING: AC Outputs / Global / Apparent Power
SITE-MONITORING-MIB::es1ConvSys1DataName.106 = STRING: AC Outputs / Global / Status
SITE-MONITORING-MIB::es1ConvSys1DataName.107 = STRING: AC Outputs / Global / Members
SITE-MONITORING-MIB::es1ConvSys1DataName.111 = STRING: AC Outputs / Global / Installed Active Power
SITE-MONITORING-MIB::es1ConvSys1DataName.112 = STRING: AC Outputs / Global / Installed Apparent Power
SITE-MONITORING-MIB::es1ConvSys1DataName.113 = STRING: AC Outputs / Global / Available Active Power
SITE-MONITORING-MIB::es1ConvSys1DataName.114 = STRING: AC Outputs / Global / Available Apparent Power
SITE-MONITORING-MIB::es1ConvSys1DataName.115 = STRING: AC Outputs / Global / Power Factor
SITE-MONITORING-MIB::es1ConvSys1DataName.117 = STRING: AC Outputs / Global / Saturation Level
SITE-MONITORING-MIB::es1ConvSys1DataName.121 = STRING: AC Outputs / Phase 1 / Voltage
SITE-MONITORING-MIB::es1ConvSys1DataName.122 = STRING: AC Outputs / Phase 1 / Current
SITE-MONITORING-MIB::es1ConvSys1DataName.123 = STRING: AC Outputs / Phase 1 / Active Power
SITE-MONITORING-MIB::es1ConvSys1DataName.124 = STRING: AC Outputs / Phase 1 / Apparent Power
SITE-MONITORING-MIB::es1ConvSys1DataName.125 = STRING: AC Outputs / Phase 1 / Frequency
SITE-MONITORING-MIB::es1ConvSys1DataName.126 = STRING: AC Outputs / Phase 1 / Status
SITE-MONITORING-MIB::es1ConvSys1DataName.127 = STRING: AC Outputs / Phase 1 / Members
SITE-MONITORING-MIB::es1ConvSys1DataName.131 = STRING: AC Outputs / Phase 1 / Installed Active Power
SITE-MONITORING-MIB::es1ConvSys1DataName.132 = STRING: AC Outputs / Phase 1 / Installed Apparent Power
SITE-MONITORING-MIB::es1ConvSys1DataName.133 = STRING: AC Outputs / Phase 1 / Available Active Power
SITE-MONITORING-MIB::es1ConvSys1DataName.134 = STRING: AC Outputs / Phase 1 / Available Apparent Power
SITE-MONITORING-MIB::es1ConvSys1DataName.135 = STRING: AC Outputs / Phase 1 / Power Factor
SITE-MONITORING-MIB::es1ConvSys1DataName.137 = STRING: AC Outputs / Phase 1 / Saturation Level
SITE-MONITORING-MIB::es1ConvSys1DataName.302 = STRING: AC Inputs / Global / Current
SITE-MONITORING-MIB::es1ConvSys1DataName.303 = STRING: AC Inputs / Global / Active Power
SITE-MONITORING-MIB::es1ConvSys1DataName.304 = STRING: AC Inputs / Global / Apparent Power
SITE-MONITORING-MIB::es1ConvSys1DataName.310 = STRING: AC Inputs / Global / Status
SITE-MONITORING-MIB::es1ConvSys1DataName.311 = STRING: AC Inputs / Global / Installed Active Power
SITE-MONITORING-MIB::es1ConvSys1DataName.312 = STRING: AC Inputs / Global / Installed Apparent Power
SITE-MONITORING-MIB::es1ConvSys1DataName.313 = STRING: AC Inputs / Global / Available Active Power
SITE-MONITORING-MIB::es1ConvSys1DataName.314 = STRING: AC Inputs / Global / Available Apparent Power
SITE-MONITORING-MIB::es1ConvSys1DataName.317 = STRING: AC Inputs / Global / Saturation Level
SITE-MONITORING-MIB::es1ConvSys1DataName.321 = STRING: AC Inputs / Phase 1 / Voltage
SITE-MONITORING-MIB::es1ConvSys1DataName.322 = STRING: AC Inputs / Phase 1 / Current
SITE-MONITORING-MIB::es1ConvSys1DataName.323 = STRING: AC Inputs / Phase 1 / Active Power
SITE-MONITORING-MIB::es1ConvSys1DataName.324 = STRING: AC Inputs / Phase 1 / Apparent Power
SITE-MONITORING-MIB::es1ConvSys1DataName.325 = STRING: AC Inputs / Phase 1 / Frequency
SITE-MONITORING-MIB::es1ConvSys1DataName.330 = STRING: AC Inputs / Phase 1 / Status
SITE-MONITORING-MIB::es1ConvSys1DataName.331 = STRING: AC Inputs / Phase 1 / Installed Active Power
SITE-MONITORING-MIB::es1ConvSys1DataName.332 = STRING: AC Inputs / Phase 1 / Installed Apparent Power
SITE-MONITORING-MIB::es1ConvSys1DataName.333 = STRING: AC Inputs / Phase 1 / Available Active Power
SITE-MONITORING-MIB::es1ConvSys1DataName.334 = STRING: AC Inputs / Phase 1 / Available Apparent Power
SITE-MONITORING-MIB::es1ConvSys1DataName.337 = STRING: AC Inputs / Phase 1 / Saturation Level
SITE-MONITORING-MIB::es1ConvSys1DataName.501 = STRING: DC / Bus 1 / Voltage
SITE-MONITORING-MIB::es1ConvSys1DataName.502 = STRING: DC / Bus 1 / Current
SITE-MONITORING-MIB::es1ConvSys1DataName.503 = STRING: DC / Bus 1 / Power
SITE-MONITORING-MIB::es1ConvSys1DataName.504 = STRING: DC / Bus 1 / Voltage SetPoint
SITE-MONITORING-MIB::es1ConvSys1DataName.505 = STRING: DC / Bus 1 / Power Setpoint
SITE-MONITORING-MIB::es1ConvSys1DataName.506 = STRING: DC / Bus 1 / Status
SITE-MONITORING-MIB::es1ConvSys1DataName.507 = STRING: DC / Bus 1 / Members
SITE-MONITORING-MIB::es1ConvSys1DataName.511 = STRING: DC / Bus 1 / Installed Power
SITE-MONITORING-MIB::es1ConvSys1DataName.512 = STRING: DC / Bus 1 / Available Power
SITE-MONITORING-MIB::es1ConvSys1DataName.516 = STRING: DC / Bus 1 / Status Charger
SITE-MONITORING-MIB::es1ConvSys1DataName.517 = STRING: DC / Bus 1 / Saturation Level
SITE-MONITORING-MIB::es1ConvSys1DataName.611 = STRING: Gateway / GW1 / Bus Status
SITE-MONITORING-MIB::es1ConvSys1DataName.612 = STRING: Gateway / GW1 / Configuration Status
SITE-MONITORING-MIB::es1ConvSys1DataName.613 = STRING: Gateway / GW1 / Last Configuration Sending State
SITE-MONITORING-MIB::es1ConvSys1DataName.614 = STRING: Gateway / GW1 / Last Configuration Sending Message
SITE-MONITORING-MIB::es1ConvSys1DataName.615 = STRING: Gateway / GW1 / Last Configuration Sending Details
SITE-MONITORING-MIB::es1ConvSys1DataName.706 = STRING: Synchronizer / Global / Status
SITE-MONITORING-MIB::es1ConvSys1DataName.707 = STRING: Synchronizer / Global / Members
SITE-MONITORING-MIB::es1ConvSys1DataName.708 = STRING: Synchronizer / Global / OK
SITE-MONITORING-MIB::es1ConvSys1DataName.726 = STRING: Synchronizer / Phase 1 / Status
SITE-MONITORING-MIB::es1ConvSys1DataName.727 = STRING: Synchronizer / Phase 1 / Members
SITE-MONITORING-MIB::es1ConvSys1DataName.728 = STRING: Synchronizer / Phase 1 / OK
SITE-MONITORING-MIB::es1ConvSys1DataValue.31 = STRING: normal
SITE-MONITORING-MIB::es1ConvSys1DataValue.102 = STRING: 3.79
SITE-MONITORING-MIB::es1ConvSys1DataValue.103 = STRING: 760
SITE-MONITORING-MIB::es1ConvSys1DataValue.104 = STRING: 840
SITE-MONITORING-MIB::es1ConvSys1DataValue.106 = STRING: OK
SITE-MONITORING-MIB::es1ConvSys1DataValue.107 = STRING: 1
SITE-MONITORING-MIB::es1ConvSys1DataValue.111 = STRING: 1000
SITE-MONITORING-MIB::es1ConvSys1DataValue.112 = STRING: 1250
SITE-MONITORING-MIB::es1ConvSys1DataValue.113 = STRING: 1000
SITE-MONITORING-MIB::es1ConvSys1DataValue.114 = STRING: 1250
SITE-MONITORING-MIB::es1ConvSys1DataValue.115 = STRING: 0.91
SITE-MONITORING-MIB::es1ConvSys1DataValue.117 = STRING: 76.00
SITE-MONITORING-MIB::es1ConvSys1DataValue.121 = STRING: 230.20
SITE-MONITORING-MIB::es1ConvSys1DataValue.122 = STRING: 3.79
SITE-MONITORING-MIB::es1ConvSys1DataValue.123 = STRING: 760
SITE-MONITORING-MIB::es1ConvSys1DataValue.124 = STRING: 840
SITE-MONITORING-MIB::es1ConvSys1DataValue.125 = STRING: 50.00
SITE-MONITORING-MIB::es1ConvSys1DataValue.126 = STRING: OK
SITE-MONITORING-MIB::es1ConvSys1DataValue.127 = STRING: 1
SITE-MONITORING-MIB::es1ConvSys1DataValue.131 = STRING: 1000
SITE-MONITORING-MIB::es1ConvSys1DataValue.132 = STRING: 1250
SITE-MONITORING-MIB::es1ConvSys1DataValue.133 = STRING: 1000
SITE-MONITORING-MIB::es1ConvSys1DataValue.134 = STRING: 1250
SITE-MONITORING-MIB::es1ConvSys1DataValue.135 = STRING: 0.91
SITE-MONITORING-MIB::es1ConvSys1DataValue.137 = STRING: 76.00
SITE-MONITORING-MIB::es1ConvSys1DataValue.302 = STRING: 3.50
SITE-MONITORING-MIB::es1ConvSys1DataValue.303 = STRING: 845.00
SITE-MONITORING-MIB::es1ConvSys1DataValue.304 = STRING: 833.68
SITE-MONITORING-MIB::es1ConvSys1DataValue.310 = STRING: OK
SITE-MONITORING-MIB::es1ConvSys1DataValue.311 = STRING: 1000
SITE-MONITORING-MIB::es1ConvSys1DataValue.312 = STRING: 1250
SITE-MONITORING-MIB::es1ConvSys1DataValue.313 = STRING: 1000
SITE-MONITORING-MIB::es1ConvSys1DataValue.314 = STRING: 1250
SITE-MONITORING-MIB::es1ConvSys1DataValue.317 = STRING: 84.50
SITE-MONITORING-MIB::es1ConvSys1DataValue.321 = STRING: 245.10
SITE-MONITORING-MIB::es1ConvSys1DataValue.322 = STRING: 3.50
SITE-MONITORING-MIB::es1ConvSys1DataValue.323 = STRING: 845.00
SITE-MONITORING-MIB::es1ConvSys1DataValue.324 = STRING: 833.68
SITE-MONITORING-MIB::es1ConvSys1DataValue.325 = STRING: 50.00
SITE-MONITORING-MIB::es1ConvSys1DataValue.330 = STRING: OK
SITE-MONITORING-MIB::es1ConvSys1DataValue.331 = STRING: 1000
SITE-MONITORING-MIB::es1ConvSys1DataValue.332 = STRING: 1250
SITE-MONITORING-MIB::es1ConvSys1DataValue.333 = STRING: 1000
SITE-MONITORING-MIB::es1ConvSys1DataValue.334 = STRING: 1250
SITE-MONITORING-MIB::es1ConvSys1DataValue.337 = STRING: 84.50
SITE-MONITORING-MIB::es1ConvSys1DataValue.501 = STRING: 54.13
SITE-MONITORING-MIB::es1ConvSys1DataValue.502 = STRING: 0.00
SITE-MONITORING-MIB::es1ConvSys1DataValue.503 = STRING: 0.00
SITE-MONITORING-MIB::es1ConvSys1DataValue.504 = STRING: 54.43
SITE-MONITORING-MIB::es1ConvSys1DataValue.505 = STRING: 1100
SITE-MONITORING-MIB::es1ConvSys1DataValue.506 = STRING: OK
SITE-MONITORING-MIB::es1ConvSys1DataValue.507 = STRING: 1
SITE-MONITORING-MIB::es1ConvSys1DataValue.511 = STRING: 1000.00
SITE-MONITORING-MIB::es1ConvSys1DataValue.512 = STRING: 1000.00
SITE-MONITORING-MIB::es1ConvSys1DataValue.516 = STRING: Voltage Mode (15)
SITE-MONITORING-MIB::es1ConvSys1DataValue.517 = STRING: 0.00
SITE-MONITORING-MIB::es1ConvSys1DataValue.611 = STRING: connected
SITE-MONITORING-MIB::es1ConvSys1DataValue.612 = STRING: ok_new
SITE-MONITORING-MIB::es1ConvSys1DataValue.613 = STRING:
SITE-MONITORING-MIB::es1ConvSys1DataValue.614 = STRING:
SITE-MONITORING-MIB::es1ConvSys1DataValue.615 = STRING:
SITE-MONITORING-MIB::es1ConvSys1DataValue.706 = STRING: Not Present
SITE-MONITORING-MIB::es1ConvSys1DataValue.707 = STRING: 0
SITE-MONITORING-MIB::es1ConvSys1DataValue.708 = STRING: 0
SITE-MONITORING-MIB::es1ConvSys1DataValue.726 = STRING: Not Present
SITE-MONITORING-MIB::es1ConvSys1DataValue.727 = STRING: 0
SITE-MONITORING-MIB::es1ConvSys1DataValue.728 = STRING: 0
 */
$oids = snmpwalk_cache_oid($device, 'es1ConvSys1ConfigName', [], $mib);
$oids = snmpwalk_cache_oid($device, 'es1ConvSys1ConfigValue', $oids, $mib);
$data_config = [];
foreach ($oids as $index => $entry) {
    [ $name1, $name2, $name3 ] = explode(' / ', $entry['es1ConvSys1ConfigName'], 3);
    if (safe_empty($name3)) {
        // es1DcSys1DataName.21 = Converters / Power
        $name3 = $name2;
        $name2 = 'Global';
    }
    $data_config[$name1][$name2][$name3] = $entry['es1ConvSys1ConfigValue'];
}
print_debug_vars($data_config);

$oids = snmpwalk_cache_oid($device, 'es1ConvSys1DataName', [], $mib);
$oids = snmpwalk_cache_oid($device, 'es1ConvSys1DataValue', $oids, $mib);
$data = [];
foreach ($oids as $index => $entry) {
    [ $name1, $name2, $name3 ] = explode(' / ', $entry['es1ConvSys1DataName'], 3);
    if (safe_empty($name3)) {
        // es1DcSys1DataName.21 = Converters / Power
        $name3 = $name2;
        $name2 = 'Global';
    }
    $data[$name1][$name2][$name3] = [ $index, $entry['es1ConvSys1DataValue'], "$name1 / $name3" ];
}
print_debug_vars($data);

foreach ($data as $name1 => $values1) {
    foreach ($values1 as $name2 => $values2) {
        // if (in_array($name2, [ 'Test', 'Boost' ], TRUE)) {
        //     continue;
        // }
        foreach ($values2 as $name3 => $entry) {

            $index    = $entry[0];
            $value    = $entry[1];
            $descr    = $entry[2];
            if ($name2 !== 'Global') {
                $descr .= ' (' . $name2 . ')';
            }

            $oid_name = 'es1ConvSys1DataValue';
            $oid_num  = '.1.3.6.1.4.1.12551.20.1.20.1.24.1.13.2.1.3.' . $index;

            if (in_array($name3, [ 'State', 'Status' ], TRUE)) {
                discover_status_ng($device, $mib, $oid_name, $oid_num, $index, 'ConvSysData', $descr, $value, [ 'entPhysicalClass' => $name1 ]);
                continue;
            }
            // if (str_starts_with($name3, 'State Of ')) {
            //     discover_sensor_ng($device, 'capacity', $mib, $oid_name, $oid_num, $index, $descr, 1, $value);
            //     continue;
            // }
            // if ($descr === 'Battery / Calculated Autonomy') {
            //     discover_sensor_ng($device, 'runtime', $mib, $oid_name, $oid_num, $index, $descr, 1, $value);
            //     continue;
            // }

            if (str_starts($name3, [ 'Installed', 'Available' ]) || $value == 0) {
                continue;
            }
            if ($name3 === 'Voltage') {
                $class = 'voltage';
            } elseif ($name3 === 'Current') {
                $class = 'current';
            } elseif ($name3 === 'Power' || $name3 === 'Active Power') {
                $class = 'power';
            } elseif ($name3 === 'Apparent Power') {
                $class = 'apower';
            } elseif ($name3 === 'Power Factor') {
                $class = 'powerfactor';
            } elseif ($name3 === 'Frequency') {
                $class = 'frequency';
            } else {
                continue;
            }

            $options = [];
            if (str_starts_with($name2, 'Phase')) {
                $options  = [
                    'measured_entity_label' => "$name1 / $name2",
                    'measured_class' => 'phase'
                ];
            }
            // Limits
            if (isset($data_config[$name1][ucfirst($class)])) {
                $limits = $data_config[$name1][ucfirst($class)];

                if (isset($limits['Low Stop'])) {
                    $options['limit_low']       = $limits['Low Stop'];
                }
                if (isset($limits['Low Start'])) {
                    $options['limit_low_warn']  = $limits['Low Start'];
                }
                if (isset($limits['High Stop'])) {
                    $options['limit_high']      = $limits['High Stop'];
                }
                if (isset($limits['High Start'])) {
                    $options['limit_high_warn'] = $limits['High Start'];
                }
            }

            discover_sensor_ng($device, $class, $mib, $oid_name, $oid_num, $index, $descr, 1, $value, $options);
        }
    }
}

unset($oids, $data, $data_config);

// EOF
