<?php
// Observium DB Port Datasource.

// TARGET formats:
// - obs_port:device_id|hostname:ifIndex|ifAlias (preferred for stability)
// - obs_port:port_id (numeric port ID; device derived automatically)
// Device_id survives hostname changes; ifIndex survives port add/removals.
// ifAlias for descriptive lookups (may not be unique; returns first match).
// Added notes: Errors Rate, PPS In/Out Rate, Discards Rate.

class WeatherMapDataSource_obsdb extends WeatherMapDataSource
{

    function Init(&$map)
    {
        if (!function_exists("dbFetchRow")) {
            return FALSE;
        }
        return (TRUE);
    }

    function Recognise($targetstring)
    {
        return preg_match("/^obs_port:/", $targetstring);
    }

    function ReadData($targetstring, &$map, &$item)
    {
        $data[IN]  = NULL;
        $data[OUT] = NULL;
        $data_time = 0;

        $parts = explode(':', substr($targetstring, 9)); // Skip 'obs_port:'
        $part_count = count($parts);

        if ($part_count < 1 || $part_count > 2) {
            wm_warn("ObsPortDB: Invalid target format (expected 1-2 parts after 'obs_port:')");
            return [NULL, NULL, 0];
        }

        if ($part_count === 1) {
            // Single part: Assume port_id
            $port_id = $parts[0];
            if (!is_numeric($port_id)) {
                wm_warn("ObsPortDB: Single-part target must be numeric port_id");
                return [NULL, NULL, 0];
            }
            $device_id = get_device_id_by_port_id($port_id);
            $device = device_by_id_cache($device_id);
            $port = get_port_by_id_cache($port_id);
            if (is_array($port) && $port['device_id'] != $device['device_id']) {
                wm_warn("ObsPortDB: Port device_id doesn't match derived device_id");
                $port = null;
            }
        } else {
            // Two parts: device_identifier:port_identifier
            $device_identifier = $parts[0];
            $port_identifier = $parts[1];

            if (is_numeric($device_identifier)) {
                $device = device_by_id_cache($device_identifier);
            } else {
                $device = device_by_name($device_identifier);
            }

            if (!is_array($device)) {
                wm_warn("ObsPortDB: Device not found for '$device_identifier'");
                return [NULL, NULL, 0];
            }

            if (is_numeric($port_identifier)) {
                $port = get_port_by_ifIndex($device['device_id'], $port_identifier);
            } else {
                $port = get_port_by_ifAlias($device['device_id'], $port_identifier);
            }
        }

        if (is_array($device) && is_array($port)) {
            if (isset($port['ifInOctets_rate'])) {
                $data[IN]  = $port['ifInOctets_rate'] * 8;
                $data[OUT] = $port['ifOutOctets_rate'] * 8;
                $data_time = $port['poll_time'];

                $item->add_note("Errors Rate", $port['ifErrors_rate']);
                $item->add_note("PPS In Rate", $port['ifInNUcastPkts_rate'] + $port['ifInUcastPkts_rate']);
                $item->add_note("PPS Out Rate", $port['ifOutNUcastPkts_rate'] + $port['ifOutUcastPkts_rate']);
                $item->add_note("Discards Rate", $port['ifInDiscards_rate'] + $port['ifOutDiscards_rate']); // Example addition
            } else {
                wm_warn("ObsPortDB: No rates available for port");
            }
        } else {
            wm_warn("ObsPortDB: Port or device not found");
        }

        wm_debug("ObsPortDB ReadData: Returning (" . ($data[IN] === NULL ? 'NULL' : $data[IN]) . "," . ($data[OUT] === NULL ? 'NULL' : $data[OUT]) . ",$data_time)\n");

        return ([$data[IN], $data[OUT], $data_time]);
    }
}