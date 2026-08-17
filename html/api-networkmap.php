<?php
/**
 * Observium Network Map API
 *
 * Returns JSON data for Cytoscape.js network topology visualization
 *
 * @package    observium
 * @subpackage web
 * @copyright  (C) Adam Armstrong
 *
 */

include_once("../includes/observium.inc.php");
include($config['html_dir'] . "/includes/authenticate.inc.php");

header('Content-Type: application/json');

if (!$_SESSION['authenticated']) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Do various queries which we use in multiple places
include($config['html_dir'] . "/includes/cache-data.inc.php");

$vars = get_vars('GET');

$device_id = isset($vars['device']) ? (int)$vars['device'] : null;

$elements = [];
$processed_edges = [];

// Build WHERE clause
$where = '';
$params = [];
if ($device_id) {
    $where = ' WHERE `neighbours`.`device_id` = ?';
    $params[] = $device_id;
}

// Get all active neighbour relationships
$sql = 'SELECT
    `neighbours`.*,
    `devices`.`hostname` as `device_hostname`,
    `devices`.`sysName` as `device_sysname`,
    `ports`.`port_label`,
    `ports`.`port_label_short`,
    `ports`.`ifDescr`,
    `ports`.`ifSpeed`,
    `ports`.`ifOperStatus`
FROM `neighbours`
LEFT JOIN `devices` USING(`device_id`)
LEFT JOIN `ports` USING(`device_id`, `port_id`)' .
$where . ' AND `neighbours`.`active` = 1';

$links = dbFetchRows($sql, $params);

$nodes_added = [];

// Process each link
foreach ($links as $link) {
    if (!device_permitted($link['device_id']) || !port_permitted($link['port_id'])) {
        continue;
    }

    $local_device_id = $link['device_id'];
    $local_device = device_by_id_cache($local_device_id);
    $local_device_name = short_hostname($local_device['hostname']);

    $local_port_id = $link['port_id'];
    $local_port_label = $link['port_label_short'] ?: ($link['port_label'] ?: short_ifname($link['ifDescr']));

    // Add local device node
    if (!isset($nodes_added['device_' . $local_device_id])) {
        $elements[] = [
            'data' => [
                'id' => 'device_' . $local_device_id,
                'label' => $local_device_name,
                'type' => 'device',
                'device_id' => $local_device_id,
                'url' => generate_url(['page' => 'device', 'device' => $local_device_id, 'tab' => 'ports', 'view' => 'map'])
            ],
            'classes' => 'device'
        ];
        $nodes_added['device_' . $local_device_id] = true;
    }

    // Add local port node
    if (!isset($nodes_added['port_' . $local_port_id])) {
        $port_status = $link['ifOperStatus'];
        $port_class = ($port_status == 'up') ? 'port port-up' : 'port port-down';

        $elements[] = [
            'data' => [
                'id' => 'port_' . $local_port_id,
                'label' => $local_port_label,
                'type' => 'port',
                'device_id' => $local_device_id,
                'port_id' => $local_port_id,
                'parent' => 'device_' . $local_device_id,
                'status' => $port_status,
                'url' => generate_url(['page' => 'device', 'device' => $local_device_id, 'tab' => 'port', 'port' => $local_port_id])
            ],
            'classes' => $port_class
        ];
        $nodes_added['port_' . $local_port_id] = true;
    }

    // Handle remote device/port
    $remote_device_id = null;
    $remote_port_id = $link['remote_port_id'];

    if ($remote_port_id && $remote_device_id = get_device_id_by_port_id($remote_port_id)) {
        // Remote is a known device in our system
        $remote_device = device_by_id_cache($remote_device_id);
        $remote_device_name = short_hostname($remote_device['hostname']);
        $remote_port = get_port_by_id_cache($remote_port_id);
        $remote_port_label = $remote_port['port_label_short'] ?: ($remote_port['port_label'] ?: short_ifname($remote_port['ifDescr']));

        if (!device_permitted($remote_device_id) || !port_permitted($remote_port_id)) {
            continue;
        }

        // Add remote device node
        if (!isset($nodes_added['device_' . $remote_device_id])) {
            $elements[] = [
                'data' => [
                    'id' => 'device_' . $remote_device_id,
                    'label' => $remote_device_name,
                    'type' => 'device',
                    'device_id' => $remote_device_id,
                    'url' => generate_url(['page' => 'device', 'device' => $remote_device_id, 'tab' => 'ports', 'view' => 'map'])
                ],
                'classes' => 'device'
            ];
            $nodes_added['device_' . $remote_device_id] = true;
        }

        // Add remote port node
        if (!isset($nodes_added['port_' . $remote_port_id])) {
            $remote_port_status = $remote_port['ifOperStatus'];
            $remote_port_class = ($remote_port_status == 'up') ? 'port port-up' : 'port port-down';

            $elements[] = [
                'data' => [
                    'id' => 'port_' . $remote_port_id,
                    'label' => $remote_port_label,
                    'type' => 'port',
                    'device_id' => $remote_device_id,
                    'port_id' => $remote_port_id,
                    'parent' => 'device_' . $remote_device_id,
                    'status' => $remote_port_status,
                    'url' => generate_url(['page' => 'device', 'device' => $remote_device_id, 'tab' => 'port', 'port' => $remote_port_id])
                ],
                'classes' => $remote_port_class
            ];
            $nodes_added['port_' . $remote_port_id] = true;
        }

        $remote_port_node_id = 'port_' . $remote_port_id;
    } else {
        // Remote is an unknown/external device
        $remote_device_name = $link['remote_hostname'];
        $remote_port_label = $link['remote_port'];
        $external_device_id = 'ext_device_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $remote_device_name);
        $external_port_id = 'ext_port_' . md5($remote_device_name . $remote_port_label);

        // Add external device node
        if (!isset($nodes_added[$external_device_id])) {
            $elements[] = [
                'data' => [
                    'id' => $external_device_id,
                    'label' => $remote_device_name,
                    'type' => 'external_device'
                ],
                'classes' => 'external-device'
            ];
            $nodes_added[$external_device_id] = true;
        }

        // Add external port node
        if (!isset($nodes_added[$external_port_id])) {
            $elements[] = [
                'data' => [
                    'id' => $external_port_id,
                    'label' => $remote_port_label,
                    'type' => 'external_port',
                    'parent' => $external_device_id
                ],
                'classes' => 'external-port'
            ];
            $nodes_added[$external_port_id] = true;
        }

        $remote_port_node_id = $external_port_id;
    }

    // Determine link color and width based on speed
    $ifSpeed = $link['ifSpeed'];
    if ($ifSpeed >= 10000000000) {
        $linkClass = 'link-10g'; // Red for 10G+
    } elseif ($ifSpeed >= 1000000000) {
        $linkClass = 'link-1g'; // Blue for 1G
    } elseif ($ifSpeed >= 100000000) {
        $linkClass = 'link-100m'; // Green for 100M
    } else {
        $linkClass = 'link-slow'; // Gray for slower
    }

    // Add edge: port -> remote port (the actual link)
    // Avoid duplicate edges
    $edge_id1 = 'port_' . $local_port_id . '_' . $remote_port_node_id;
    $edge_id2 = $remote_port_node_id . '_port_' . $local_port_id;

    if (!isset($processed_edges[$edge_id1]) && !isset($processed_edges[$edge_id2])) {
        $speed_label = '';
        if ($ifSpeed >= 1000000000) {
            $speed_label = ($ifSpeed / 1000000000) . 'G';
        } elseif ($ifSpeed >= 1000000) {
            $speed_label = ($ifSpeed / 1000000) . 'M';
        }

        $elements[] = [
            'data' => [
                'id' => $edge_id1,
                'source' => 'port_' . $local_port_id,
                'target' => $remote_port_node_id,
                'label' => nicecase($link['protocol']) . ($speed_label ? ' ' . $speed_label : ''),
                'protocol' => $link['protocol'],
                'speed' => $ifSpeed
            ],
            'classes' => $linkClass
        ];
        $processed_edges[$edge_id1] = true;
        $processed_edges[$edge_id2] = true;
    }
}

// Return the data in Cytoscape.js format
echo json_encode([
    'elements' => $elements,
    'device_id' => $device_id
], JSON_PRETTY_PRINT);

// EOF
