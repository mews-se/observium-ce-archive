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

// Keep peer graph focused on 3 high-signal metrics used by UI
$array = [
  'Srtt'         => ['descr' => 'SRTT', 'colour' => 'FF0000'],
  'Rto'          => ['descr' => 'RTO',  'colour' => '00AAAA'],
  'PktsEnqueued' => ['descr' => 'Queue','colour' => 'FF00FF'],
];

$i = 0;
if (rrd_is_file($rrd_filename)) {
    foreach ($array as $ds => $entry) {
        $rrd_list[$i]['filename'] = $rrd_filename;
        $rrd_list[$i]['descr']    = $entry['descr'];
        $rrd_list[$i]['ds']       = $ds;
#    $rrd_list[$i]['colour'] = $entry['colour'];
        $i++;
    }
} else {
    echo("file missing: $file");
}

$colours   = "mixed";
$nototal   = 1;
$unit_text = "Metric";

$log_y = TRUE;

include($config['html_dir'] . "/includes/graphs/generic_multi_line.inc.php");

// EOF
