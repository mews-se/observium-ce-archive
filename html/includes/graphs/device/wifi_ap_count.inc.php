<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     graphs
 * @copyright  (C) Adam Armstrong
 *
 */

$rrd_filename = get_rrd_path($device, "wifi_ap_count.rrd");

if (rrd_is_file($rrd_filename, TRUE)) {
    $ds              = "value";
    $colour_line     = "8C0000";
    $colour_area     = "EBCD8B";
    $colour_area_max = "cc9999";
    $unit_text       = "APs";
    $line_text       = 'Access Points';
    $scale_min       = 0;

    include($config['html_dir'] . "/includes/graphs/generic_simplex.inc.php");
} else {
    $rrd_filename = get_rrd_path($device, "aruba-controller.rrd");

    $ds              = "NUMAPS";
    $colour_line     = "8C0000";
    $colour_area     = "EBCD8B";
    $colour_area_max = "cc9999";
    $unit_text       = "APs";
    $line_text       = 'Access Points';
    $scale_min       = 0;

    include($config['html_dir'] . "/includes/graphs/generic_simplex.inc.php");
}

// EOF
