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

// FIXME. This mib still not in defaults, only module checks
// if (!is_device_mib($device, 'UCD-DISKIO-MIB')) {
//     print_debug("UCD-DISKIO-MIB disabled for device, module ucd-diskio is skipped.");
//     return;
// }

$diskio_oids = snmpwalk_cache_oid($device, 'diskIOEntry', [], 'UCD-DISKIO-MIB');

// Build array of entries from the database
$diskio_db = [];
foreach (dbFetchRows('SELECT * FROM `ucd_diskio` WHERE `device_id` = ?', [ $device['device_id'] ]) as $entry) {
    $diskio_db[$entry['diskio_index']] = $entry;
}

$table_rows = [];
if (!safe_empty($diskio_oids)) {
    foreach ($diskio_oids as $index => $entry) {
        if (preg_match('/^(loop|ram)\d/', $entry['diskIODevice']) ||
            entity_descr_check($entry['diskIODevice'], 'storage', 32)) { // Limit descr to 32 chars. FIXME. why so small field?
            // Skip loop & ram pseudo disk devices
            // And Check storage ignore filters
            if (!isset($diskio_db[$index])) {
                $table_rows[] = [ $index, $entry['diskIODevice'], "%yignored%n" ];
            }
            continue;
        }

        if ($entry['diskIONRead'] > 0 || $entry['diskIONWritten'] > 0) {
            print_debug('$index ' . $entry['diskIODevice']);
            if (isset($diskio_db[$index]) && $diskio_db[$index]['diskio_descr'] == $entry['diskIODevice']) {
                // Entries match. Nothing to do here!
                //echo('.');
                $table_rows[] = [ $index, $entry['diskIODevice'], "%gok%n" ];
            } elseif (isset($diskio_db[$index])) {
                // Index exists, but block device has changed!
                //echo('U');
                $table_rows[] = [ $index, $entry['diskIODevice'], "%bupdated%n" ];
                dbUpdate([ 'diskio_descr' => $entry['diskIODevice'] ], 'ucd_diskio', '`diskio_id` = ?', [ $diskio_db[$index]['diskio_id'] ]);
            } else {
                // Index doesn't exist in the database. Add it.
                $table_rows[] = [ $index, $entry['diskIODevice'], "%gadded%n" ];
                $inserted = dbInsert(['device_id' => $device['device_id'], 'diskio_index' => $index, 'diskio_descr' => $entry['diskIODevice']], 'ucd_diskio');
                //echo('+');
            }
            // Remove from the DB array
            unset($diskio_db[$index]);
        } // end validity check


    } // end array foreach
} // End array if

// Remove diskio entries which weren't redetected here
$diskio_delete = [];
foreach ($diskio_db as $index => $entry) {
    $table_rows[] = [ $index, $entry['diskio_descr'], "%rdeleted%n" ];
    $diskio_delete[] = $entry['diskio_id'];
    //echo('-');
}
if ($diskio_delete) {
    dbDelete('ucd_diskio', generate_query_values($diskio_delete, 'diskio_id'));
}

$headers = [ '%WIndex%n', '%WLabel%n', '%Wstatus%n' ];
print_cli_table($table_rows, $headers);

unset($diskio_db, $diskio_oids, $diskio_delete, $table_rows);

// EOF
