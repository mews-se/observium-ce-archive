<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage poller
 * @copyright  (C) Adam Armstrong
 *
 */

// FIXME. This mib still not in defaults, only module checks
// if (!is_device_mib($device, 'UCD-DISKIO-MIB')) {
//     print_debug("UCD-DISKIO-MIB disabled for device, module ucd-diskio is skipped.");
//     return;
// }

// FIXME - store state data in database

$diskio_db = dbFetchRows("SELECT * FROM `ucd_diskio` WHERE `device_id` = ?", [ $device['device_id'] ]);
if (safe_empty($diskio_db)) {
    unset($diskio_db);
    return;
}

$diskio_oids = snmpwalk_cache_oid($device, "diskIOEntry", [], "UCD-DISKIO-MIB");
print_debug_vars($diskio_oids);

//echo("Checking UCD DiskIO MIB: ");
$table_rows = [];
foreach ($diskio_db as $diskio) {

    $index = $diskio['diskio_index'];
    if (!isset($diskio_oids[$index])) {
        print_debug($diskio['diskio_descr'] . " not exist, skipped");
        continue;
    }
    $entry = [
        'read'     => $diskio_oids[$index]['diskIONReadX'],
        'written'  => $diskio_oids[$index]['diskIONWrittenX'],
        'reads'    => $diskio_oids[$index]['diskIOReads'],
        'writes'   => $diskio_oids[$index]['diskIOWrites']
    ];

    //echo($diskio['diskio_descr'] . " ");

    rrdtool_update_ng($device, 'ucd_diskio', $entry, $diskio['diskio_descr']);

    $table_row    = [];
    $table_row[]  = $diskio['diskio_descr'];
    $table_row[]  = $diskio['diskio_index'];
    $table_row[]  = $entry['read'];
    $table_row[]  = $entry['written'];
    $table_row[]  = $entry['reads'];
    $table_row[]  = $entry['writes'];
    $table_rows[] = $table_row;
    unset($table_row);

}

$headers = [ '%WLabel%n', '%WIndex%n', '%WRead%n', '%WWritten%n', '%WReads%n', '%WWrites%n' ];
print_cli_table($table_rows, $headers);

unset($diskio_data, $diskio_oids);

// EOF
