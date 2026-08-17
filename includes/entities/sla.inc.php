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

/**
 * Get named SLA index.
 *
 * @param array $sla
 * @return string
 */
function get_sla_index($sla) {
    $index = $sla['sla_index'];

    // Use 'owner.index' as index for all except Cisco, HPE and TWAMP
    switch (strtoupper($sla['sla_mib'])) {
        case 'DISMAN-PING-MIB':
        case 'JUNIPER-PING-MIB':
        case 'HH3C-NQA-MIB':
        case 'HUAWEI-DISMAN-PING-MIB':
        case 'ZHONE-DISMAN-PING-MIB':
        case 'H3C-NQA-MIB':
            // DISMAN-PING based mibs have two part (named) indexes
            $index = $sla['sla_owner'] . '.' . $index;
            break;
    }

    return $index;
}

function get_sla_rrd_index($sla) {
    $rrd_index = strtolower($sla['sla_mib']) . '-' . $sla['sla_index'];
    if ($sla['sla_owner']) {
        // Add owner name to rrd file if not empty
        $rrd_index .= '-' . $sla['sla_owner'];
    }

    return $rrd_index;
}

// EOF
