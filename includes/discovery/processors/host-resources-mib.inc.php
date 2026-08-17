<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     discovery
 * @copyright  (C) Adam Armstrong
 *
 */

if (!safe_empty($valid['processor']['cpm'])) {
    // Skip HOST-RESOURCES-MIB when:
    // CISCO-PROCESS-MIB already discovered, see: https://jira.observium.org/browse/OBS-4394
    print_debug("Skipped HOST-RESOURCES-MIB for better processor(s) by CISCO-PROCESS-MIB.");
    return;
}
if (is_device_mib($device, 'CHECKPOINT-MIB') && is_numeric(snmp_getnext_oid($device, 'CHECKPOINT-MIB::multiProcUsage'))) {
    // CHECKPOINT-MIB already discovered, see: https://jira.observium.org/browse/OBS-5106
    print_debug("Skipped HOST-RESOURCES-MIB for better processor(s) by CHECKPOINT-MIB.");
    return;
}

$hr_array = snmpwalk_cache_oid($device, 'hrProcessorLoad', [], 'HOST-RESOURCES-MIB:HOST-RESOURCES-TYPES');
$hr_count = safe_count($hr_array);

if (!$hr_count) {
    return;
}

$hr_array = snmpwalk_cache_oid($device, 'hrDevice', $hr_array, 'HOST-RESOURCES-MIB:HOST-RESOURCES-TYPES');
$hr_first = array_key_first($hr_array);
$hr_cpus  = 0;
$hr_total = 0;

foreach ($hr_array as $index => $entry) {
    if (!is_numeric($entry['hrProcessorLoad'])) {
        continue;
    }
    if ($device['os'] === 'arista_eos' && $index == 1) {
        continue;
    }

    if (!isset($entry['hrDeviceType'])) {
        $entry['hrDeviceType']  = 'hrDeviceProcessor';
        $entry['hrDeviceIndex'] = $index;
    } elseif ($entry['hrDeviceType'] === 'hrDeviceOther' &&
              preg_match('/^cpu\d+:/', $entry['hrDeviceDescr'])) {
        // Workaround bsnmpd reporting CPUs as hrDeviceOther (FY FreeBSD.)
        $entry['hrDeviceType'] = 'hrDeviceProcessor';
    }

    if ($entry['hrDeviceType'] === 'hrDeviceProcessor') {
        $hrDeviceIndex = $entry['hrDeviceIndex'];

        $usage_oid = ".1.3.6.1.2.1.25.3.3.1.2.$index";
        $usage     = $entry['hrProcessorLoad'];

        // Workaround to set fake description for Mikrotik and other who don't populate hrDeviceDescr
        if (empty($entry['hrDeviceDescr'])) {
            $descr = 'Processor';
            if ($hr_count > 1) {

                // Append processor index
                $hr_index = $index;
                if ($hr_first > 0) {
                    // Juniper devices have the first index as 0
                    $hr_index -= 1;
                }
                $descr .= ' ' . $hr_index;
            }
        } elseif (str_contains($entry['hrDeviceDescr'], ':')) {
            // What is this for? I have forgotten. What has : in its hrDeviceDescr?
            // Set description to that found in hrDeviceDescr, first part only if containing a :
            // GenuineIntel: QEMU Virtual CPU version 4.2.0
            $descr = explode(':', $entry['hrDeviceDescr'])[1];
        } else {
            $descr = $entry['hrDeviceDescr'];
        }

        $descr = rewrite_entity_name($descr);

        if ($descr !== 'An electronic chip that makes the computer work.') {
            discover_processor($valid['processor'], $device, $usage_oid, $index, 'hr', $descr, 1, $usage, NULL, $hrDeviceIndex);
            $hr_cpus++;
            $hr_total += $usage;
        }
        unset($old_rrd, $new_rrd, $descr, $usage_oid, $usage, $hrDeviceIndex);
    }
    unset($entry);
}

if ($hr_cpus) {
    discover_processor($valid['processor'], $device, 1, 1, 'hr-average', 'Average', 1, $hr_total / $hr_cpus);

    // Remove UCD processor poller, this is because UCD-SNMP-MIB run earlier
    $ucd_where = '`device_id` = ? AND `processor_type` IN (?, ?)';
    $ucd_params = [ $device['device_id'], 'ucd-old', 'ucd-raw' ];
    if (dbExist('processors', $ucd_where, $ucd_params)) {
        print_debug("Removed UCD processor, prefer HOST-RESOURCES average");
        $GLOBALS['module_stats']['processors']['deleted']++;
        dbDelete('processors', $ucd_where, $ucd_where);
    }
}

unset($hr_array, $hr_count, $hr_index, $hr_first, $oid, $ucd_where, $ucd_params);

// EOF
