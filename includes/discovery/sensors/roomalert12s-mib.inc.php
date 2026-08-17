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

$scale = 1; // Start at 1 for 2 digits setting.
//$scale = 0.01;

$oids = snmpwalk_cache_oid($device, "digital", [], "ROOMALERT12S-MIB");

$index = 0;
$i     = 1;

//for ($i = 1; $i <= 2; $i++) {
if (isset($oids[$index]["digital-sen$i-1"])) {
    $name = "External Digital Sensor";
    // Sensor is present.
    if (!isset($oids[$index]["digital-sen$i-3"])) {
        // Temp sensor
        $descr = "$name: Temperature";
        $oid   = ".1.3.6.1.4.1.20916.1.12.1.2.$i.1.$index";
        $value = $oids[$index]["digital-sen$i-1"];
        if ($value > 100) {
            $scale = 0.01;
        }

        discover_sensor_ng($device, 'temperature', $mib, "digital-sen$i-1", $oid, $index, $descr, $scale, $value);
    } elseif (isset($oids[$index]["digital-sen$i-5"])) {
        // FIXME. AVTECH, are you idiots? You can give any type of sensor here, but there is no way to know which one it is.

        // ROOMALERT12S-MIB::digital-sen1-1.0 = INTEGER: 3220 -- current temperature (C)
        // ROOMALERT12S-MIB::digital-sen1-2.0 = INTEGER: 8996 -- current temperature (F)
        // ROOMALERT12S-MIB::digital-sen1-3.0 = INTEGER: 2708 -- % relative humidity, voltage reading (V) or air speed (m/s)
        // ROOMALERT12S-MIB::digital-sen1-4.0 = INTEGER: 8996 -- heat index (F) or air speed (f/m)
        // ROOMALERT12S-MIB::digital-sen1-5.0 = INTEGER: 3220 -- heat index (C) or air flow (CMH)
        // ROOMALERT12S-MIB::digital-sen1-6.0 = INTEGER: 1762 -- dew point (C) or air flow (CFM)
        // ROOMALERT12S-MIB::digital-sen1-7.0 = INTEGER: 6371 -- dew point (F)

        // Temp/Humidity sensor
        $descr = "$name: Temperature";
        $oid   = ".1.3.6.1.4.1.20916.1.12.1.2.$i.1.$index";
        $value = $oids[$index]["digital-sen$i-1"];
        if ($value > 100) {
            $scale = 0.01;
        }

        discover_sensor_ng($device, 'temperature', $mib, "digital-sen$i-1", $oid, $index, $descr, $scale, $value);


        $descr = "$name: Humidity";
        $oid   = ".1.3.6.1.4.1.20916.1.12.1.2.$i.3.$index";
        $value = $oids[$index]["digital-sen$i-3"];

        discover_sensor_ng($device, 'humidity', $mib, "digital-sen$i-3", $oid, $index, $descr, $scale, $value);

        $descr = "$name: Heat index";
        $oid   = ".1.3.6.1.4.1.20916.1.12.1.2.$i.5.$index";
        $value = $oids[$index]["digital-sen$i-5"];

        discover_sensor_ng($device, 'temperature', $mib, "digital-sen$i-5", $oid, $index, $descr, $scale, $value);

        $descr = "$name: Dew Point";
        $oid   = ".1.3.6.1.4.1.20916.1.12.1.2.$i.6.$index";
        $value = $oids[$index]["digital-sen$i-6"];

        discover_sensor_ng($device, 'dewpoint', $mib, "digital-sen$i-6", $oid, $index, $descr, $scale, $value);
    } else {
        // Power sensor
        $descr = "Channel $i: Current";
        $oid   = ".1.3.6.1.4.1.20916.1.12.1.2.$i.1.$index";
        $value = $oids[$index]["digital-sen$i-1"];
        discover_sensor('current', $device, $oid, "digital-sen$i-1.$index", 'roomalert', $descr, $scale, $value);

        $descr = "Channel $i: Power";
        $oid   = ".1.3.6.1.4.1.20916.1.12.1.2.$i.2.$index";
        $value = $oids[$index]["digital-sen$i-2"];
        discover_sensor('power', $device, $oid, "digital-sen$i-2.$index", 'roomalert', $descr, $scale, $value);

        $descr = "Channel $i: Voltage";
        $oid   = ".1.3.6.1.4.1.20916.1.12.1.2.$i.3.$index";
        $value = $oids[$index]["digital-sen$i-3"];
        discover_sensor('voltage', $device, $oid, "digital-sen$i-3.$index", 'roomalert', $descr, $scale, $value);

        $descr = "Channel $i: Reference voltage";
        $oid   = ".1.3.6.1.4.1.20916.1.12.1.2.$i.4.$index";
        $value = $oids[$index]["digital-sen$i-4"];
        discover_sensor('voltage', $device, $oid, "digital-sen$i-4.$index", 'roomalert', $descr, $scale, $value);
    }
}
//}

// EOF
