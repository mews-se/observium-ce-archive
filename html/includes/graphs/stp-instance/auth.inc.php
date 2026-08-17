<?php
/**
 * STP Instance Graphs Auth and Tag Prep
 *
 * Expected request var:
 *  - id (int) stp_instance_id (database ID)
 *
 * Looks up the stp_instances row to populate $graph_tags:
 *   type, instance_key
 * and sets $device for auth context using the row device_id.
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

$auth = FALSE;
if (isset($vars['id']) && is_numeric($vars['id'])) {
    $si = dbFetchRow('SELECT * FROM `stp_instances` WHERE `stp_instance_id` = ? LIMIT 1', [ (int)$vars['id'] ]);
    if ($si && isset($si['device_id'])) {
        $device = device_by_id_cache($si['device_id']);
        if ($device && device_permitted($device['device_id'])) {
            $auth = TRUE;

            $graph_tags = [];
            $graph_tags['type'] = (string)$si['type'];
            $graph_tags['instance_key'] = (int)$si['instance_key'];

            if (!isset($graph_title)) {
                $graph_title = device_name($device, TRUE) . ' :: STP Instance ' . $graph_tags['type'] . '-' . $graph_tags['instance_key'];
            }
        }
    }
}

// EOF

