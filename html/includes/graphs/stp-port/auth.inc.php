<?php
/**
 * STP Port Graphs Auth and Tag Prep
 *
 * Expected request var:
 *  - id (int) stp_port_id (database ID)
 *
 * Looks up the stp_ports row and its instance to populate $graph_tags:
 *   basePort, type, instance_key
 * and sets $device for auth context using the row device_id.
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

// Authorise by device context
if (is_intnum($vars['device'])) {
    $device = device_by_id_cache($vars['device']);
} elseif (!safe_empty($vars['device'])) {
    $device = device_by_name($vars['device']);
}

// Resolve by stp_port_id if supplied, fallback to device context only when id is not set
$auth = FALSE;
if (isset($vars['id']) && is_numeric($vars['id'])) {
    $sp = dbFetchRow('SELECT * FROM `stp_ports` WHERE `stp_port_id` = ? LIMIT 1', [ (int)$vars['id'] ]);
    if ($sp && isset($sp['device_id'])) {
        $device = device_by_id_cache($sp['device_id']);
        if ($device && device_permitted($device['device_id'])) {
            $auth = TRUE;

            // Load instance for type and key
            $si = dbFetchRow('SELECT `type`,`instance_key` FROM `stp_instances` WHERE `stp_instance_id` = ? LIMIT 1', [ (int)$sp['stp_instance_id'] ]);

            // Prepare extra template tags for STP port RRD filenames
            $graph_tags = [];
            $graph_tags['basePort'] = (int)$sp['base_port'];
            if ($si) {
                $graph_tags['type'] = (string)$si['type'];
                $graph_tags['instance_key'] = (int)$si['instance_key'];
            }

            if (!isset($graph_title)) {
                $graph_title = device_name($device, TRUE) . ' :: STP Port ' . (int)$sp['base_port'];
            }
        }
    }
}

// EOF
