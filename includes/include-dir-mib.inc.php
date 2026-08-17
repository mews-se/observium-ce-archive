<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage common
 * @copyright  (C) Adam Armstrong
 *
 */

// This is an include so that we don't lose variable scope.

$include_lib   = $include_lib   ?? FALSE;
$include_order = $include_order ?? NULL; // Order for include MIBs definitions, default: 'model,os,group,default'
$include_stats = $include_stats ?? [];   // Initialise stats array if not exists

$mibs = get_device_mibs_permitted($device, $include_order);

// Bodge for <os|os_group>[|/*].inc.php loading.
if (isset($include_dir_os) && $include_dir_os) {
    $mibs[] = $device['os'];
    if (isset($device['os_group']) && !empty($device['os_group'])) {
        $mibs[] = $device['os_group'];
    }

    unset($include_dir_os);
}

foreach ($mibs as $mib) {
    $inc_dir   = $config['install_dir'] . '/' . $include_dir . '/' . strtolower($mib);
    $inc_file  = $inc_dir . '.inc.php';

    // MIB timing start
    $inc_start  = microtime(TRUE);
    $inc_status = FALSE; // TRUE, when mib file(s) exist and included
    if (is_file($inc_file)) {
        print_cli_data_field("$mib ");

        include($inc_file);
        $inc_status = TRUE;
        echo(PHP_EOL);
    } elseif (is_dir($inc_dir)) {
        if (OBS_DEBUG) {
            echo("[[$mib]]");
        }

        foreach (glob($inc_dir . '/*.inc.php') as $dir_file) {
            if (is_file($dir_file)) {
                print_cli_data_field("$mib ");
                include($dir_file);
                $inc_status = TRUE;
                echo(PHP_EOL);
            }
        }
    }

    if ($inc_status === FALSE) {
        continue;
    }

    if ($include_lib && is_file($inc_dir . '.lib.php')) {
        // separated functions include, for exclude fatal redeclare errors
        include_once($inc_dir . '.lib.php');
    }

    // MIB timing only for valid includes
    $include_stats[$mib] = ($include_stats[$mib] ?? 0) + elapsed_time($inc_start);
}

unset($include_dir, $include_lib, $include_order, $inc_file, $inc_dir, $dir_file, $mib);

// EOF
