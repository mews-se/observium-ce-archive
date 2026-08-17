<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     poller
 * @copyright  (C) Adam Armstrong
 *
 */

$table_rows = [];

$sql = "SELECT *";
$sql .= " FROM  `oids_entries`";
$sql .= " LEFT JOIN `oids` USING(`oid_id`)";
$sql .= " WHERE `device_id` = ?";

//print_vars($sql);

$entries_db = [];
foreach (dbFetchRows($sql, [ $device['device_id'] ]) as $entry) {
    if (isset($entries_db[$entry['oid_id']])) {
        // Duplicate entry (only one entry per device)
        print_debug("Duplicate Custom OID entry in DB found: " . $entry['oid_descr']);
        print_debug_vars($entry);
        dbDelete('oids_entries', '`oid_entry_id` = ?', [ $entry['oid_entry_id'] ]);
        continue;
    }
    $entries_db[$entry['oid_id']] = $entry;
}

// FIXME - removal and blacklisting

foreach (dbFetchRows("SELECT * FROM `oids` WHERE `oid_autodiscover` = ?", [ 1 ]) as $oid) {
    $value = snmp_fix_numeric(snmp_get_oid($device, $oid['oid'], NULL, NULL, OBS_SNMP_ALL_NUMERIC | OBS_SNMP_TIMETICKS));
    // Don't discover stuff which is returning min/max 32-bit values
    $invalid_value = $value == '4294967295' || $value == '2147483647' || $value == '-2147483647';

    if (is_numeric($value) && !$invalid_value) {

        if (!isset($entries_db[$oid['oid_id']])) {
            // Auto-add this OID.
            if ($oid_entry_id = dbInsert(['oid_id' => $oid['oid_id'], 'device_id' => $device['device_id']], 'oids_entries')) {
                $GLOBALS['module_stats'][$module]['added']++;
                print_debug("SUCCESS: Added OID entry (id: $oid_entry_id)");
            } else {
                print_warning("ERROR: Unable to add OID entry for " . $oid['oid_name']);
            }
        } else {
            $GLOBALS['module_stats'][$module]['unchanged']++;
        }
    } else {
        if (isset($entries_db[$oid['oid_id']])) {
            // Mark this OID as deleted from the host.
            dbUpdate([ 'deleted' => '1' ], 'oids_entries', '`oid_entry_id` = ?', [ $oid['oid_entry_id'] ]);
            $GLOBALS['module_stats'][$module]['deleted']++;
        }

    }
}

unset($entries_db);

// EOF
