<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage graphs
 * @copyright  (C) Adam Armstrong
 *
 */

if (!is_intnum($vars['id'])) {
    return;
}

$mempool = dbFetchRow("SELECT * FROM `mempools` WHERE `mempool_id` = ?", [$vars['id']]);

if (is_numeric($mempool['device_id']) && ($auth || device_permitted($mempool['device_id']))) {
    $device = device_by_id_cache($mempool['device_id']);

    $rrd_filename = get_rrd_path($device, get_mempool_rrd($device, $mempool));

    $auth  = TRUE;

    $graph_title   = device_name($device, TRUE);
    $graph_title   .= " :: Memory Pool :: " . rewrite_entity_name($mempool['mempool_descr'], 'mempool');
}

// EOF
