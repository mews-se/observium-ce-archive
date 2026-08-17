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

// DERP devices, derp MIB.

$scale = 1; // Start at 1 for 2 digits setting.
//$scale = 0.01;

// ROOMALERT12E-MIB::digital-sen1-1.0 = INTEGER: 2106
// ROOMALERT12E-MIB::digital-sen1-2.0 = INTEGER: 6990
// ROOMALERT12E-MIB::digital-sen1-8.0 = STRING: "Sensor 1"

$oids = snmpwalk_cache_oid($device, "digital-sen1",    [], $mib);
$oids = snmpwalk_cache_oid($device, "digital-sen2", $oids, $mib);
$oids = snmpwalk_cache_oid($device, "digital-sen3", $oids, $mib);

$index = 0;

for ($i = 1; $i <= 2; $i++) {
    if (isset($oids[$index]["digital-sen$i-1"])) {
        $name = $oids[$index]["digital-sen$i-8"] ?: "Channel $i";
        $oid_num_base = '.1.3.6.1.4.1.20916.1.10.1.' . ($i + 1);
        // Sensor is present.
        if (!isset($oids[$index]["digital-sen$i-3"])) {
            // Temp sensor
            $descr   = "$name: Temperature";
            $oid     = "$oid_num_base.1.$index";
            $value   = $oids[$index]["digital-sen$i-1"];
            if ($value > 100) {
                $scale = 0.01;
            }

            discover_sensor_ng($device, 'temperature', $mib, "digital-sen$i-1", $oid, $index, $descr, $scale, $value);
        } elseif (isset($oids[$index]["digital-sen$i-5"])) {
            // Temp/Humidity sensor
            $descr   = "$name: Temperature";
            $oid     = "$oid_num_base.1.$index";
            $value   = $oids[$index]["digital-sen$i-1"];
            if ($value > 100) {
                $scale = 0.01;
            }

            discover_sensor_ng($device, 'temperature', $mib, "digital-sen$i-1", $oid, $index, $descr, $scale, $value);

            $descr   = "$name: Humidity";
            $oid     = "$oid_num_base.3.$index";
            $value   = $oids[$index]["digital-sen$i-3"];

            discover_sensor_ng($device, 'humidity', $mib, "digital-sen$i-3", $oid, $index, $descr, $scale, $value);

            $descr   = "$name: Heat index";
            $oid     = "$oid_num_base.5.$index";
            $value   = $oids[$index]["digital-sen$i-5"];

            discover_sensor_ng($device, 'temperature', $mib, "digital-sen$i-5", $oid, $index, $descr, $scale, $value);

            $descr = "$name: Dew Point";
            $oid   = "$oid_num_base.6.$index";
            $value = $oids[$index]["digital-sen$i-6"];

            discover_sensor_ng($device, 'dewpoint', $mib, "digital-sen$i-6", $oid, $index, $descr, $scale, $value);
        } else {
            // Power sensor
            $descr = "Channel $i: Current";
            $oid   = "$oid_num_base.1.$index";
            $value = $oids[$index]["digital-sen$i-1"];
            if ($value > 100) {
                $scale = 0.01;
            }

            discover_sensor_ng($device, 'current', $mib, "digital-sen$i-1", $oid, $index, $descr, $scale, $value);

            $descr = "Channel $i: Power";
            $oid   = "$oid_num_base.2.$index";
            $value = $oids[$index]["digital-sen$i-2"];

            discover_sensor_ng($device, 'power', $mib, "digital-sen$i-2", $oid, $index, $descr, $scale, $value);

            $descr = "Channel $i: Voltage";
            $oid   = "$oid_num_base.3.$index";
            $value = $oids[$index]["digital-sen$i-3"];

            discover_sensor_ng($device, 'voltage', $mib, "digital-sen$i-3", $oid, $index, $descr, $scale, $value);

            $descr = "Channel $i: Reference voltage";
            $oid   = "$oid_num_base.4.$index";
            $value = $oids[$index]["digital-sen$i-4"];

            discover_sensor_ng($device, 'voltage', $mib, "digital-sen$i-4", $oid, $index, $descr, $scale, $value);
        }
    }
}

// EOF
