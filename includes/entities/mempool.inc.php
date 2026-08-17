<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage entities
 * @copyright  (C) Adam Armstrong
 *
 */

function mempool_find_definition_by_oid($mib, $oid_name) {
    global $config;

    if (!isset($config['mibs'][$mib]['mempool'])) {
        return null;
    }

    // First try traditional associative lookup
    if (isset($config['mibs'][$mib]['mempool'][$oid_name])) {
        return $config['mibs'][$mib]['mempool'][$oid_name];
    }

    // Then search through [] syntax entries for matching OID
    $primary_oids = ['oid_total', 'oid_used', 'oid_free', 'oid_perc'];
    foreach ($config['mibs'][$mib]['mempool'] as $key => $def) {
        if (is_numeric($key) && is_array($def)) {
            // Check if any primary OID matches
            foreach ($primary_oids as $oid_key) {
                if (isset($def[$oid_key]) && $def[$oid_key] === $oid_name) {
                    return $def;
                }
            }
        }
    }

    return null;
}

function get_mempool_rrd($device, $mempool, $full = TRUE) {

    $index = $mempool['mempool_index'];

    $rrd_file = strtolower($mempool['mempool_mib']) . "-" . $mempool['mempool_object'] . "-" . $index;
    /* keep as actual renamed rrd files
    if (!empty($processor['mempool_object'])) {
        // mempool_object not empty only for definition based mempools
        $rrd_file = strtolower($mempool['mempool_mib']) . "-" . $mempool['mempool_object'] . "-" . $index;
    } else {
        // rrd filenames for file-based mempools
        $rrd_file = strtolower($mempool['mempool_mib']) . "-" . $index;
    }
    */

    if ($full) {
        // Prepend mempool
        return 'mempool-' . $rrd_file . '.rrd';
    }
    return $rrd_file;
}

// EOF