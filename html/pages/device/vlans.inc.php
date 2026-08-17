<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage web
 * @copyright  (C) Adam Armstrong
 *
 */

$link_array = [
    'page'   => 'device',
    'device' => $device['device_id'],
    'tab'    => 'vlans'
];

if (isset($vars['graph'])) {
    $graph_type = "port_" . $vars['graph'];
} else {
    $graph_type = "port_bits";
}

// Check if viewing a specific VLAN
if (!empty($vars['vlan_id']) && is_numeric($vars['vlan_id'])) {
    $vars['view'] = 'vlan_detail';
    $vlan_id = (int)$vars['vlan_id'];
} elseif (!$vars['view']) {
    $vars['view'] = "overview";
}

$navbar['brand'] = 'VLANs';
$navbar['class'] = 'navbar-narrow';

if ($vars['view'] === 'vlan_detail') {
    $navbar['brand'] = 'VLAN ' . $vlan_id;
    $navbar['options']['back']['text'] = '← Back to VLANs';
    $navbar['options']['back']['url'] = generate_url($link_array, ['vlan_id' => NULL]);
    $navbar['options']['back']['class'] = 'text-primary';
} else {
    $navbar['options']['overview']['text'] = 'Overview';
    $navbar['options']['matrix']['text'] = 'Port Matrix';
    $navbar['options']['changes']['text'] = 'Recent Changes';
    $navbar['options']['analysis']['text'] = 'Analysis';
}

foreach ($navbar['options'] as $option => $data) {
    if ($option == $vars['view']) {
        $navbar['options'][$option]['class'] = 'active';
    }
    $navbar['options'][$option]['url'] = generate_url($link_array, ['view' => $option, 'graph' => NULL]);
}

// Add graph options
$navbar['options_right']['graphs']['text'] = 'Graphs';
foreach ($config['graph_types']['port'] as $type => $data) {
    if ($type === 'fdb_count' && !is_module_enabled($device, 'ports_fdbcount', 'poller')) {
        continue;
    }

    $navbar['options_right']['graphs']['suboptions'][$type]['text'] = $data['name'];
    $navbar['options_right']['graphs']['suboptions'][$type]['url'] = generate_url($link_array, ['view' => 'graphs', 'graph' => $type]);
    if ($vars['graph'] == $type && $vars['view'] == "graphs") {
        $navbar['options_right']['graphs']['suboptions'][$type]['class'] = "active";
        $navbar['options_right']['graphs']['class'] = "active";
    }
}

// Quick filters
if (isset($vars['graph']) && in_array($vars['graph'], ['fdb_count'])) {
    $vars['filters'] = $vars['filters'] ?? ['deleted' => TRUE, 'virtual' => TRUE];
    $vars['filters'] = navbar_ports_filter($navbar, $vars, ['virtual']);
}

print_navbar($navbar);

$vlans = dbFetchRows("SELECT * FROM `vlans` WHERE `device_id` = ? ORDER BY `vlan_vlan`", [$device['device_id']]);
$vlan_count = count($vlans);

// Batch statistics queries for performance
$stats_queries = [
    // Trunk ports: ports with tagged VLANs in ports_vlans (may also have native VLAN in ifVlan)
    'trunk_ports' => "SELECT COUNT(DISTINCT pv.port_id)
                      FROM `ports_vlans` AS pv
                      JOIN `ports` AS p USING(`device_id`, `port_id`)
                      WHERE pv.`device_id` = ? AND p.`deleted` = 0",

    // Access ports: ports with ifVlan set but NO tagged VLANs
    'access_ports' => "SELECT COUNT(*) FROM `ports` AS p
                       WHERE p.`device_id` = ? AND p.`deleted` = 0
                       AND p.`ifVlan` IS NOT NULL AND p.`ifVlan` != ''
                       AND NOT EXISTS (SELECT 1 FROM `ports_vlans` pv WHERE pv.port_id = p.port_id)",

    // Total VLAN assignments (tagged): useful for measuring trunk complexity
    'tagged_assignments' => "SELECT COUNT(*) FROM `ports_vlans` AS pv
                             JOIN `ports` AS p USING(`device_id`, `port_id`)
                             WHERE pv.`device_id` = ? AND p.`deleted` = 0",

    // Total unique MAC addresses learned
    'total_macs' => "SELECT COUNT(DISTINCT `mac_address`) FROM `vlans_fdb`
                     WHERE `device_id` = ? AND `deleted` = 0",

    // Total ports on device
    'total_ports' => "SELECT COUNT(*) FROM `ports` WHERE `device_id` = ? AND `deleted` = 0"
];

$stats = [];
foreach ($stats_queries as $stat_name => $query) {
    $stats[$stat_name] = dbFetchCell($query, [$device['device_id']]);
}

// Calculate derived metrics
$stats['ports_with_vlans'] = $stats['trunk_ports'] + $stats['access_ports'];
$stats['unassigned_ports'] = $stats['total_ports'] - $stats['ports_with_vlans'];

// Port coverage: percentage of ports assigned to at least one VLAN (0-100%)
$stats['port_coverage'] = 0;
if ($stats['total_ports'] > 0) {
    $stats['port_coverage'] = round(($stats['ports_with_vlans'] / $stats['total_ports']) * 100);
}

// Average VLANs per trunk port (complexity metric)
$stats['avg_vlans_per_trunk'] = 0;
if ($stats['trunk_ports'] > 0) {
    $stats['avg_vlans_per_trunk'] = round($stats['tagged_assignments'] / $stats['trunk_ports'], 1);
}

// Count unused VLANs (VLANs with no port assignments on this device)
$unused_vlans = 0;
foreach ($vlans as $vlan) {
    $tagged_count = dbFetchCell("SELECT COUNT(*) FROM `ports_vlans` pv 
                                LEFT JOIN `ports` p USING(device_id, port_id) 
                                WHERE pv.device_id = ? AND pv.vlan = ? AND p.deleted = 0", 
                                [$device['device_id'], $vlan['vlan_vlan']]);
    $untagged_count = dbFetchCell("SELECT COUNT(*) FROM `ports` 
                                  WHERE device_id = ? AND ifVlan = ? AND deleted = 0", 
                                  [$device['device_id'], $vlan['vlan_vlan']]);

    if ($tagged_count == 0 && $untagged_count == 0) {
        $unused_vlans++;
    }
}

switch ($vars['view']) {

    case 'vlan_detail':
        // Per-VLAN detailed port view
        $vlan_info = dbFetchRow("SELECT * FROM `vlans` WHERE `device_id` = ? AND `vlan_vlan` = ? LIMIT 1",
                               [$device['device_id'], $vlan_id]);

        if (!$vlan_info) {
            print_warning('VLAN ' . $vlan_id . ' not found on this device.');
            break;
        }

        // Get all ports in this VLAN with STP state
        $vlan_ports = dbFetchRows("SELECT p.*, pv.vlan, sp.state as stp_state, sp.role as stp_role,
                                         si.type as stp_type, si.name as stp_instance_name,
                                         sp.path_cost as stp_cost, sp.priority as stp_priority
                                  FROM `ports_vlans` pv
                                  LEFT JOIN `ports` p USING(device_id, port_id)
                                  LEFT JOIN `stp_vlan_map` sv ON pv.device_id = sv.device_id AND pv.vlan = sv.vlan_vlan
                                  LEFT JOIN `stp_ports` sp ON pv.port_id = sp.port_id AND sv.stp_instance_id = sp.stp_instance_id
                                  LEFT JOIN `stp_instances` si ON sp.stp_instance_id = si.stp_instance_id
                                  WHERE pv.device_id = ? AND pv.vlan = ? AND p.deleted = 0
                                  ORDER BY p.ifIndex", [$device['device_id'], $vlan_id]);

        // Get untagged ports (from ports.ifVlan)
        $untagged_ports = dbFetchRows("SELECT p.*, 'untagged' as vlan_type, sp.state as stp_state, sp.role as stp_role,
                                             si.type as stp_type, si.name as stp_instance_name,
                                             sp.path_cost as stp_cost, sp.priority as stp_priority
                                      FROM `ports` p
                                      LEFT JOIN `stp_vlan_map` sv ON p.device_id = sv.device_id AND p.ifVlan = sv.vlan_vlan
                                      LEFT JOIN `stp_ports` sp ON p.port_id = sp.port_id AND sv.stp_instance_id = sp.stp_instance_id
                                      LEFT JOIN `stp_instances` si ON sp.stp_instance_id = si.stp_instance_id
                                      WHERE p.device_id = ? AND p.ifVlan = ? AND p.deleted = 0
                                      ORDER BY p.ifIndex", [$device['device_id'], $vlan_id]);

        // Merge port arrays and remove duplicates
        $all_ports = [];
        $port_ids_seen = [];

        foreach ($vlan_ports as $port) {
            $port['vlan_type'] = 'tagged';
            if (!in_array($port['port_id'], $port_ids_seen)) {
                $all_ports[] = $port;
                $port_ids_seen[] = $port['port_id'];
            }
        }

        foreach ($untagged_ports as $port) {
            $port['vlan_type'] = 'untagged';
            if (!in_array($port['port_id'], $port_ids_seen)) {
                $all_ports[] = $port;
                $port_ids_seen[] = $port['port_id'];
            }
        }

        // Calculate device coverage percentage for this VLAN
        $vlan_usage_percent = 0;
        if ($stats['total_ports'] > 0) {
            $vlan_usage_percent = round((count($all_ports) / $stats['total_ports']) * 100);
        }

        // VLAN Information status boxes
        $status_boxes = [
            [
                'title' => 'VLAN ID',
                'value' => ['text' => $vlan_info['vlan_vlan'], 'class' => 'label-primary'],
                'subtitle' => 'Identifier'
            ],
            [
                'title' => 'VLAN Name',
                'value' => $vlan_info['vlan_name'] ?: 'VLAN ' . $vlan_id,
                'subtitle' => 'Description'
            ],
            [
                'title' => 'Total Ports',
                'value' => ['text' => count($all_ports), 'class' => 'label-primary'],
                'subtitle' => 'Port Assignments'
            ],
            [
                'title' => 'Device Coverage',
                'value' => ['text' => $vlan_usage_percent . '%', 'class' => $vlan_usage_percent >= 50 ? 'label-info' : ($vlan_usage_percent >= 20 ? 'label-default' : 'label-suppressed')],
                'subtitle' => 'Of ' . $stats['total_ports'] . ' Total Ports'
            ],
            [
                'title' => 'Tagged Ports',
                'value' => ['text' => count($vlan_ports), 'class' => 'label-info'],
                'subtitle' => 'Trunk Ports'
            ],
            [
                'title' => 'Untagged Ports',
                'value' => ['text' => count($untagged_ports), 'class' => 'label-info'],
                'subtitle' => 'Access Ports'
            ]
        ];

        if (!empty($vlan_info['vlan_mtu'])) {
            $status_boxes[] = [
                'title' => 'MTU',
                'value' => $vlan_info['vlan_mtu'],
                'subtitle' => 'Maximum Transmission Unit'
            ];
        }

        echo generate_status_panel($status_boxes);

        // Port table
        if (empty($all_ports)) {
            print_warning('No ports assigned to VLAN ' . $vlan_id . ' on this device.');
        } else {
            echo generate_box_open(['title' => 'Port Assignments and STP States']);

            echo '<table class="table table-striped table-hover table-condensed">';
            echo '<thead>';
            echo '<tr>';
            echo '<th class="state-marker"></th>';
            echo '<th>Port</th>';
            echo '<th>Interface</th>';
            echo '<th>Description</th>';
            echo '<th>Type</th>';
            echo '<th>Speed</th>';
            echo '<th>STP State</th>';
            echo '<th>STP Role</th>';
            echo '<th>STP Cost</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($all_ports as $port) {
                humanize_port($port);

                // State marker color based on operational status
                $port_state_class = ($port['ifOperStatus'] === 'up') ? 'success' : 'danger';

                // STP state indicator
                $stp_indicator = '';
                if (!empty($port['stp_state'])) {
                    switch ($port['stp_state']) {
                        case 'forwarding': $stp_class = 'label-success'; break;
                        case 'blocking':
                        case 'discarding': $stp_class = 'label-danger'; break;
                        case 'learning':
                        case 'listening': $stp_class = 'label-warning'; break;
                        default: $stp_class = 'label-default';
                    }
                    $stp_indicator = '<span class="label ' . $stp_class . '" title="STP: ' . ucfirst($port['stp_state']) . '">' .
                                   strtoupper(substr($port['stp_state'], 0, 1)) . '</span>';
                } else {
                    $stp_indicator = '<span class="label label-default" title="No STP data">-</span>';
                }

                // VLAN type indicator
                $vlan_badge = ($port['vlan_type'] === 'untagged') ?
                    '<span class="label label-info">Untagged</span>' :
                    '<span class="label label-primary">Tagged</span>';

                echo '<tr>';
                echo '<td class="state-marker"><span class="label label-' . $port_state_class . '"></span></td>';
                echo '<td class="entity">' . generate_port_link($port) . ' ' . $vlan_badge . '</td>';
                echo '<td><span class="text-nowrap">' . $port['ifName'] . '</span></td>';
                echo '<td>' . escape_html($port['ifAlias']) . '</td>';
                echo '<td>' . $port['human_type'] . '</td>';
                echo '<td>' . ($port['ifSpeed'] ? format_si($port['ifSpeed'], 2, 3) . 'bps' : '<span class="text-muted">-</span>') . '</td>';
                echo '<td>' . $stp_indicator . '</td>';
                echo '<td>' . (ucfirst($port['stp_role']) ?: '<span class="text-muted">-</span>') . '</td>';
                echo '<td>' . ($port['stp_cost'] ?: '<span class="text-muted">-</span>') . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo generate_box_close();
        }

        break;

    case 'overview':
    default:
        $status_boxes = [
            [
                'title' => 'Total VLANs',
                'value' => ['text' => $vlan_count, 'class' => 'label-suppressed'],
                'subtitle' => 'Configured VLANs'
            ],
            [
                'title' => 'Port Coverage',
                'value' => ['text' => $stats['port_coverage'] . '%', 'class' => $stats['port_coverage'] >= 80 ? 'label-success' : ($stats['port_coverage'] >= 50 ? 'label-info' : 'label-warning')],
                'subtitle' => 'Ports with VLANs'
            ],
            [
                'title' => 'Trunk Ports',
                'value' => ['text' => $stats['trunk_ports'], 'class' => $stats['trunk_ports'] > 0 ? 'label-primary' : 'label-default'],
                'subtitle' => 'Tagged VLAN Ports'
            ],
            [
                'title' => 'Access Ports',
                'value' => ['text' => $stats['access_ports'], 'class' => $stats['access_ports'] > 0 ? 'label-info' : 'label-default'],
                'subtitle' => 'Untagged Only'
            ],
            [
                'title' => 'Unassigned Ports',
                'value' => ['text' => $stats['unassigned_ports'], 'class' => $stats['unassigned_ports'] > 0 ? 'label-warning' : 'label-success'],
                'subtitle' => 'No VLAN Assignment'
            ],
            [
                'title' => 'Tagged Assignments',
                'value' => ['text' => $stats['tagged_assignments'], 'class' => 'label-suppressed'],
                'subtitle' => 'Total VLAN Tags'
            ],
            [
                'title' => 'Avg VLANs/Trunk',
                'value' => ['text' => $stats['avg_vlans_per_trunk'], 'class' => $stats['avg_vlans_per_trunk'] > 20 ? 'label-warning' : 'label-info'],
                'subtitle' => 'Trunk Complexity'
            ],
            [
                'title' => 'MAC Addresses',
                'value' => ['text' => format_number($stats['total_macs']), 'class' => 'label-suppressed'],
                'subtitle' => 'Learned MACs'
            ]
        ];

        echo generate_status_panel($status_boxes);

        // Check if we have PVST instances but no port data (common issue)
        $pvst_instances = dbFetchCell("SELECT COUNT(*) FROM stp_instances WHERE device_id = ? AND type = 'pvst'", [$device['device_id']]);
        $pvst_ports = dbFetchCell("SELECT COUNT(DISTINCT sp.stp_port_id) FROM stp_instances si
                                   JOIN stp_ports sp ON si.stp_instance_id = sp.stp_instance_id
                                   WHERE si.device_id = ? AND si.type = 'pvst'", [$device['device_id']]);

        if ($pvst_instances > 0 && $pvst_ports == 0) {
            print_warning("STP data collection issue: This device has PVST configured but per-VLAN STP port data is not available via SNMP. STP state indicators may not be shown for individual VLANs.");
        }

        // Comprehensive VLAN table with full details
        echo generate_box_open(/*['title' => 'VLAN Configuration & Port Assignments']*/);

        if ($vlan_count === 0) {
            echo generate_box_state('info', 'No VLANs configured on this device', [
                'size' => 'large',
                'icon' => 'fa fa-info-circle'
            ]);
        } else {
            echo '<table class="table table-striped table-hover table-condensed">';

            $cols = [
                ['', 'class="state-marker"'],
                'VLAN ID',
                'Name', 
                'Status',
                ['Tagged', 'style="width: 60px; text-align: center;"'],
                ['Untagged', 'style="width: 60px; text-align: center;"'],
                ['MACs', 'style="width: 50px; text-align: center;"'],
                ['Usage', 'style="width: 100px; text-align: center;"'],
                ['Ports', 'style="width: 30%;"']
            ];

            echo get_table_header($cols, $vars);
            echo '<tbody>';

            foreach ($vlans as $vlan) {
                if (!is_numeric($vlan['vlan_vlan'])) {
                    continue;
                }

                // Get tagged ports for this VLAN with STP state
                $tagged_ports = dbFetchRows("SELECT p.*, sp.state as stp_state, sp.role as stp_role, si.type as stp_type
                                           FROM `ports_vlans` pv
                                           LEFT JOIN `ports` p USING(device_id, port_id)
                                           LEFT JOIN `stp_vlan_map` sv ON pv.device_id = sv.device_id AND pv.vlan = sv.vlan_vlan
                                           LEFT JOIN `stp_ports` sp ON pv.port_id = sp.port_id AND sv.stp_instance_id = sp.stp_instance_id
                                           LEFT JOIN `stp_instances` si ON sp.stp_instance_id = si.stp_instance_id
                                           WHERE pv.device_id = ? AND pv.vlan = ? AND p.deleted = 0
                                           ORDER BY p.ifIndex",
                                           [$device['device_id'], $vlan['vlan_vlan']]);

                // Get untagged ports for this VLAN with STP state
                $untagged_ports = dbFetchRows("SELECT p.*, sp.state as stp_state, sp.role as stp_role, si.type as stp_type
                                             FROM `ports` p
                                             LEFT JOIN `stp_vlan_map` sv ON p.device_id = sv.device_id AND p.ifVlan = sv.vlan_vlan
                                             LEFT JOIN `stp_ports` sp ON p.port_id = sp.port_id AND sv.stp_instance_id = sp.stp_instance_id
                                             LEFT JOIN `stp_instances` si ON sp.stp_instance_id = si.stp_instance_id
                                             WHERE p.device_id = ? AND p.ifVlan = ? AND p.deleted = 0
                                             ORDER BY p.ifIndex",
                                             [$device['device_id'], $vlan['vlan_vlan']]);

                // Get MAC count for this VLAN
                $mac_count = dbFetchCell("SELECT COUNT(DISTINCT mac_address) FROM `vlans_fdb` 
                                        WHERE device_id = ? AND vlan_id = ? AND deleted = 0", 
                                        [$device['device_id'], $vlan['vlan_vlan']]);

                // Get global device count for this VLAN
                $global_count = dbFetchCell("SELECT COUNT(DISTINCT device_id) FROM `vlans` 
                                           WHERE vlan_vlan = ?", 
                                           [$vlan['vlan_vlan']]);

                $tagged_count = count($tagged_ports);
                $untagged_count = count($untagged_ports);

                // Calculate unique ports (avoid double-counting hybrid ports that are both tagged and native)
                $port_ids_in_vlan = [];
                foreach ($tagged_ports as $p) {
                    $port_ids_in_vlan[$p['port_id']] = true;
                }
                foreach ($untagged_ports as $p) {
                    $port_ids_in_vlan[$p['port_id']] = true;
                }
                $total_ports = count($port_ids_in_vlan);
                $mac_count = $mac_count ?: 0;
                $global_count = $global_count ?: 1;

                // Determine row state based on VLAN health
                $row_class = '';
                if ($total_ports == 0) {
                    $row_class = 'warning';
                } elseif ($total_ports > 10) {
                    $row_class = 'info';
                } else {
                    $row_class = 'ok';
                }

                echo '<tr class="' . $row_class . '">';
                echo '<td class="state-marker"></td>';
                echo '<td class="entity-title">';
                echo '<span class="entity">' . generate_link('VLAN ' . $vlan['vlan_vlan'], array_merge($link_array, ['vlan_id' => $vlan['vlan_vlan']])) . '</span>';
                if ($global_count > 1) {
                    echo ' <small><span class="label label-info" title="Present on ' . $global_count . ' devices">Global</span></small>';
                }
                echo '</td>';
                echo '<td>' . escape_html($vlan['vlan_name']) . '</td>';
                echo '<td>';
                if ($total_ports == 0) {
                    echo '<span class="label label-default">Unused</span>';
                } elseif ($total_ports > 10) {
                    echo '<span class="label label-danger">High Usage</span>';
                } else {
                    echo '<span class="label label-success">Active</span>';
                }
                echo '</td>';
                echo '<td class="text-center">';
                if ($tagged_count > 0) {
                    echo '<strong>' . $tagged_count . '</strong>';
                } else {
                    echo '<span class="text-muted">-</span>';
                }
                echo '</td>';
                echo '<td class="text-center">';
                if ($untagged_count > 0) {
                    echo '<strong>' . $untagged_count . '</strong>';
                } else {
                    echo '<span class="text-muted">-</span>';
                }
                echo '</td>';
                echo '<td class="text-center">';
                if ($mac_count > 0) {
                    echo '<strong>' . $mac_count . '</strong>';
                } else {
                    echo '<span class="text-muted">-</span>';
                }
                echo '</td>';
                echo '<td class="text-center">';

                // Usage visualization with percentage
                if ($stats['total_ports'] > 0) {
                    $percent = round(($total_ports / $stats['total_ports']) * 100);
                    echo '<div class="progress" style="margin: 0; height: 18px; width: 80px;">';
                    echo '<div class="progress-bar" style="width: ' . min($percent, 100) . '%;">';
                    echo '<small>' . $percent . '%</small>';
                    echo '</div>';
                    echo '</div>';
                } else {
                    echo '<span class="text-muted">-</span>';
                }
                echo '</td>';
                echo '<td class="small">';

                $port_links = [];

                // Add tagged ports with entity class and STP state
                foreach ($tagged_ports as $port) {
                    $stp_indicator = '';
                    if (!empty($port['stp_state'])) {
                        $stp_class = '';
                        switch ($port['stp_state']) {
                            case 'forwarding': $stp_class = 'label-success'; break;
                            case 'blocking':
                            case 'discarding': $stp_class = 'label-danger'; break;
                            case 'learning':
                            case 'listening': $stp_class = 'label-warning'; break;
                            default: $stp_class = 'label-default';
                        }
                        $stp_indicator = ' <span class="label ' . $stp_class . '" title="STP: ' . ucfirst($port['stp_state']) . '">' .
                                       strtoupper(substr($port['stp_state'], 0, 1)) . '</span>';
                    }
                    $port_links[] = '<span class="entity">' . generate_port_link($port, $port['port_label_short']) . $stp_indicator . '</span>';
                }

                // Add untagged ports with (U) indicator, entity class and STP state
                foreach ($untagged_ports as $port) {
                    $stp_indicator = '';
                    if (!empty($port['stp_state'])) {
                        $stp_class = '';
                        switch ($port['stp_state']) {
                            case 'forwarding': $stp_class = 'label-success'; break;
                            case 'blocking':
                            case 'discarding': $stp_class = 'label-danger'; break;
                            case 'learning':
                            case 'listening': $stp_class = 'label-warning'; break;
                            default: $stp_class = 'label-default';
                        }
                        $stp_indicator = ' <span class="label ' . $stp_class . '" title="STP: ' . ucfirst($port['stp_state']) . '">' .
                                       strtoupper(substr($port['stp_state'], 0, 1)) . '</span>';
                    }
                    $port_links[] = '<span class="entity">' . generate_port_link($port, $port['port_label_short'] . ' <small>(U)</small>') . $stp_indicator . '</span>';
                }

                if (count($port_links) > 0) {
                    if (count($port_links) <= 6) {
                        echo implode(', ', $port_links);
                    } else {
                        echo implode(', ', array_slice($port_links, 0, 6));
                        echo ' <small><span class="label label-default">+' . (count($port_links) - 6) . ' more</span></small>';
                    }
                } else {
                    echo '<span class="text-muted">No ports assigned</span>';
                }

                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo generate_box_close();
        break;

    case 'matrix':

        if ($vlan_count === 0 || $stats['total_ports'] === 0) {
            echo generate_box_state('info', 'No VLANs or ports available for matrix display', [
                'size' => 'medium',
                'icon' => 'fa fa-table'
            ]);
        } else {
            // Get VLAN-capable ports only (exclude loopback, tunnel, virtual interfaces)
            $excluded_types = ['softwareLoopback', 'tunnel', 'mplsTunnel', 'virtualTg', 
                              'l2vlan', 'ciscoISLvlan', 'l3ipvlan', 'l3ipxvlan', 'propVirtual', 
                              'bridge', 'other'];

            $ports = dbFetchRows("SELECT * FROM `ports` 
                                WHERE `device_id` = ? AND `deleted` = 0 
                                AND (`ifType` IS NULL OR `ifType` NOT IN ('" . implode("','", $excluded_types) . "'))
                                ORDER BY `ifIndex` /*LIMIT 100*/", [$device['device_id']]);

            // Limit VLANs for display but allow more than before
            //$display_vlans = array_slice($vlans, 0, 50);
            $display_vlans = $vlans;

            // Batch load all port-VLAN relationships with STP state
            $port_vlans = [];
            $port_stp_states = [];
            if (!empty($ports)) {
                $port_ids = array_column($ports, 'port_id');
                $placeholders = str_repeat('?,', count($port_ids) - 1) . '?';

                // Get tagged VLAN mappings with STP state
                $vlan_mappings = dbFetchRows(
                    "SELECT pv.port_id, pv.vlan, sp.state as stp_state, sp.role as stp_role
                     FROM `ports_vlans` pv
                     LEFT JOIN `stp_vlan_map` sv ON pv.device_id = sv.device_id AND pv.vlan = sv.vlan_vlan
                     LEFT JOIN `stp_ports` sp ON pv.port_id = sp.port_id AND sv.stp_instance_id = sp.stp_instance_id
                     WHERE pv.device_id = ? AND pv.port_id IN ($placeholders)",
                    array_merge([$device['device_id']], $port_ids)
                );

                foreach ($vlan_mappings as $mapping) {
                    $port_vlans[$mapping['port_id']][$mapping['vlan']] = true;
                    $port_stp_states[$mapping['port_id']][$mapping['vlan']] = $mapping['stp_state'];
                }

                // Get untagged VLAN STP states
                $untagged_stp = dbFetchRows(
                    "SELECT p.port_id, p.ifVlan as vlan, sp.state as stp_state
                     FROM `ports` p
                     LEFT JOIN `stp_vlan_map` sv ON p.device_id = sv.device_id AND p.ifVlan = sv.vlan_vlan
                     LEFT JOIN `stp_ports` sp ON p.port_id = sp.port_id AND sv.stp_instance_id = sp.stp_instance_id
                     WHERE p.device_id = ? AND p.port_id IN ($placeholders) AND p.ifVlan IS NOT NULL",
                    array_merge([$device['device_id']], $port_ids)
                );

                foreach ($untagged_stp as $mapping) {
                    if ($mapping['vlan']) {
                        $port_stp_states[$mapping['port_id']][$mapping['vlan']] = $mapping['stp_state'];
                    }
                }
            }

            /*
            echo '<div class="alert alert-info">';
            echo 'Showing first ' . count($ports) . ' VLAN-capable ports (excluding loopback, tunnel, virtual) and ' . count($display_vlans) . ' VLANs';
            if ($vlan_count > 50 || $stats['total_ports'] > 100) {
                echo '<br><strong>Performance Note:</strong> Matrix is limited for large networks. Use Detail view for complete port/VLAN information.';
            }
            echo '</div>'; */

            echo generate_box_open();
            echo '<div class="table-responsive" style="max-height: 600px; overflow: auto;">';
            echo '<table class="table table-condensed table-striped table-matrix-sticky">';
            echo '<thead><tr>';
            //echo '<th class="state-marker matrix-sticky-corner"></th>';
            echo '<th class="matrix-sticky-corner"><i class="sprite-port"></i> Port</th>';

            foreach ($display_vlans as $vlan) {
                echo '<th class="text-center matrix-sticky-header" style="writing-mode: vertical-rl; text-orientation: mixed; min-width: 30px;">';
                echo generate_link('VLAN ' . $vlan['vlan_vlan'], array_merge($link_array, ['vlan_id' => $vlan['vlan_vlan']]), ['class' => 'text-white']);
                echo '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($ports as $port) {
                // Generate port row class based on status (extracted from humanize_port logic)

                //humanize_port($port);

                echo '<tr class="' . $port['row_class'] . '">';
                //echo '<td class="state-marker matrix-sticky-column"></td>';
                echo '<td class="entity matrix-sticky-column">' . generate_port_link($port, short_ifname($port['label'])) . '</td>';

                foreach ($display_vlans as $vlan) {
                    echo '<td class="text-center">';

                    $is_tagged = isset($port_vlans[$port['port_id']][$vlan['vlan_vlan']]);
                    $stp_state = $port_stp_states[$port['port_id']][$vlan['vlan_vlan']] ?? null;

                    if ($is_tagged) {
                        // Tagged port - show T with STP state color
                        $label_class = 'label-success'; // Default for tagged
                        $title = 'Tagged';
                        if ($stp_state) {
                            switch ($stp_state) {
                                case 'forwarding': $label_class = 'label-success'; break;
                                case 'blocking':
                                case 'discarding': $label_class = 'label-danger'; break;
                                case 'learning':
                                case 'listening': $label_class = 'label-warning'; break;
                                default: $label_class = 'label-default';
                            }
                            $title = 'Tagged - STP: ' . ucfirst($stp_state);
                        }
                        echo '<span class="label ' . $label_class . '" title="' . $title . '">T</span>';
                    } elseif ($port['ifVlan'] == $vlan['vlan_vlan']) {
                        // Untagged port - show U with STP state color
                        $label_class = 'label-info'; // Default for untagged
                        $title = 'Untagged';
                        if ($stp_state) {
                            switch ($stp_state) {
                                case 'forwarding': $label_class = 'label-success'; break;
                                case 'blocking':
                                case 'discarding': $label_class = 'label-danger'; break;
                                case 'learning':
                                case 'listening': $label_class = 'label-warning'; break;
                                default: $label_class = 'label-default';
                            }
                            $title = 'Untagged - STP: ' . ucfirst($stp_state);
                        }
                        echo '<span class="label ' . $label_class . '" title="' . $title . '">U</span>';
                    } else {
                        echo '-';
                    }

                    echo '</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';

            // Add STP state legend
            echo '<div class="row" style="margin: 10px 0;">';
            echo '<div class="col-md-12">';
            echo '<small><strong>Matrix Legend:</strong> ';
            echo '<span class="label label-success">T/U</span> Forwarding &nbsp;';
            echo '<span class="label label-danger">T/U</span> Blocking/Discarding &nbsp;';
            echo '<span class="label label-warning">T/U</span> Learning/Listening &nbsp;';
            echo '<span class="label label-info">U</span> Untagged (no STP data) &nbsp;';
            echo '<span class="label label-default">T/U</span> Unknown STP state';
            echo '</small>';
            echo '</div>';
            echo '</div>';

            echo generate_box_close();
        }

        break;

    case 'changes':
        // Recent VLAN changes

            echo generate_box_open();

            $content = '<div class="box-state-title">VLAN History - Work in Progress</div>';
            $content .= '<p class="box-state-description">VLAN change history is currently being developed and depends upon future eventlog changes.</p>';

            echo generate_box_state('info', $content, [
                'icon' => $config['icon']['info'],
                'size' => 'large'
            ]);

            echo generate_box_close();

        /*
        echo generate_box_open(['title' => 'Recent VLAN Changes']);

        $sql = "SELECT * FROM `eventlog` 
                WHERE `device_id` = ? AND (`type` = 'vlan' OR `message` LIKE '%VLAN%')
                AND `timestamp` > DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY `timestamp` DESC LIMIT 100";

        $events = dbFetchRows($sql, [$device['device_id']]);

        if (count($events) > 0) {
            echo '<table class="table table-condensed table-striped">';
            echo '<thead><tr>';
            echo '<th width="150"><i class="sprite-clock"></i> Timestamp</th>';
            echo '<th>Event Type</th>';
            echo '<th>Message</th>';
            echo '<th>User</th>';
            echo '</tr></thead><tbody>';

            foreach ($events as $event) {
                echo '<tr>';
                echo '<td class="state-marker"></td>';
                echo '<td>' . format_timestamp($event['timestamp']) . '</td>';
                echo '<td><span class="label label-info">' . $event['type'] . '</span></td>';
                echo '<td>' . escape_html($event['message']) . '</td>';
                echo '<td>' . escape_html($event['username'] ?: 'System') . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo generate_box_state('default', 'No VLAN changes recorded in the last 30 days', [
                'size' => 'medium',
                'icon' => 'fa fa-history'
            ]);
        }

        echo generate_box_close();

        // Port VLAN assignment changes
        echo generate_box_open(['title' => 'Port VLAN Assignment History']);

        $sql = "SELECT * FROM `eventlog` 
                WHERE `device_id` = ? AND `type` = 'interface' 
                AND (`message` LIKE '%VLAN%' OR `message` LIKE '%tagged%' OR `message` LIKE '%untagged%')
                AND `timestamp` > DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY `timestamp` DESC LIMIT 50";

        $port_events = dbFetchRows($sql, [$device['device_id']]);

        if (count($port_events) > 0) {
            echo '<table class="table table-condensed table-striped">';
            echo '<thead><tr>';
            echo '<th width="150"><i class="sprite-clock"></i> Timestamp</th>';
            echo '<th><i class="sprite-port"></i> Port</th>';
            echo '<th>Change</th>';
            echo '</tr></thead><tbody>';

            foreach ($port_events as $event) {
                $port = null;
                // Try to extract port information from the message
                if (preg_match('/port (\d+)|ifIndex (\d+)/i', $event['message'], $matches)) {
                    $ifIndex = $matches[1] ?: $matches[2];
                    $port = dbFetchRow("SELECT * FROM `ports` WHERE `device_id` = ? AND `ifIndex` = ?", 
                                      [$device['device_id'], $ifIndex]);
                }

                echo '<tr>';
                echo '<td class="state-marker"></td>';
                echo '<td>' . format_timestamp($event['timestamp']) . '</td>';
                echo '<td>';
                if ($port) {
                    echo generate_port_link($port, short_ifname($port['label']));
                } else {
                    echo 'Unknown';
                }
                echo '</td>';
                echo '<td>' . escape_html($event['message']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo generate_box_state('default', 'No port VLAN assignment changes in the last 7 days', [
                'size' => 'medium', 
                'icon' => 'fa fa-exchange'
            ]);
        }

        echo generate_box_close();
        */
        break;

    case 'analysis':
        // VLAN analysis and recommendations
        if ($vlan_count === 0) {
            //echo generate_box_open(['title' => 'VLAN Analysis & Recommendations']);
            echo generate_box_state('info', 'No VLANs to analyze on this device', [
                'size' => 'large',
                'icon' => 'fa fa-search'
            ]);
            //echo generate_box_close();
            break;
        }

        // Enhanced analysis with batch queries
        $analysis_sql = "
            SELECT 
                v.vlan_vlan, v.vlan_name,
                COALESCE(port_stats.total_ports, 0) as total_ports,
                COALESCE(global_stats.global_count, 1) as global_count
            FROM vlans v
            LEFT JOIN (
                SELECT vlan, COUNT(DISTINCT port_id) as total_ports
                FROM (
                    SELECT vlan, port_id FROM ports_vlans WHERE device_id = ?
                    UNION
                    SELECT ifVlan as vlan, port_id FROM ports 
                    WHERE device_id = ? AND ifVlan IS NOT NULL AND deleted = 0
                ) combined
                GROUP BY vlan
            ) port_stats ON v.vlan_vlan = port_stats.vlan
            LEFT JOIN (
                SELECT vlan_vlan, COUNT(DISTINCT device_id) as global_count
                FROM vlans
                GROUP BY vlan_vlan
            ) global_stats ON v.vlan_vlan = global_stats.vlan_vlan
            WHERE v.device_id = ?";

        $analysis_data = dbFetchRows($analysis_sql, [
            $device['device_id'], 
            $device['device_id'], 
            $device['device_id']
        ]);

        // Categorize VLANs and collect metrics
        $unused_vlans = [];
        $single_port_vlans = [];
        $high_span_vlans = [];
        $orphaned_vlans = [];

        foreach ($analysis_data as $vlan_data) {
            if ($vlan_data['total_ports'] == 0) {
                $unused_vlans[] = $vlan_data;
            } elseif ($vlan_data['total_ports'] == 1) {
                $single_port_vlans[] = $vlan_data;
            } elseif ($vlan_data['total_ports'] > ($stats['total_ports'] * 0.7)) {
                $high_span_vlans[] = $vlan_data;
            }

            // Check for orphaned VLANs (exist only on this device)
            if ($vlan_data['global_count'] == 1 && $vlan_data['total_ports'] > 0) {
                $orphaned_vlans[] = $vlan_data;
            }
        }

        // Enhanced trunk analysis
        $trunk_ports = dbFetchRows("SELECT DISTINCT p.*, 
                                           COUNT(DISTINCT pv.vlan) as vlan_count,
                                           GROUP_CONCAT(DISTINCT pv.vlan ORDER BY pv.vlan) as vlans
                                   FROM `ports` AS p
                                   JOIN `ports_vlans` AS pv ON (p.device_id = pv.device_id AND p.port_id = pv.port_id)
                                   WHERE p.`device_id` = ? AND p.`deleted` = 0
                                   GROUP BY p.port_id
                                   HAVING COUNT(DISTINCT pv.vlan) > 5
                                   ORDER BY vlan_count DESC, p.ifIndex", [$device['device_id']]);

        $trunk_native_issues = [];
        $trunk_excessive_vlans = [];
        foreach ($trunk_ports as $trunk) {
            // Check for non-standard native VLANs
            if ($trunk['ifVlan'] != 1 && !empty($trunk['ifVlan'])) {
                $trunk_native_issues[] = $trunk;
            }
            // Check for excessive VLAN assignments (potential security risk)
            if ($trunk['vlan_count'] > 50) {
                $trunk_excessive_vlans[] = $trunk;
            }
        }

        // Calculate health scores
        $config_health_score = 100;
        $issues_count = 0;

        if (count($unused_vlans) > 0) {
            $config_health_score -= min(20, count($unused_vlans) * 2);
            $issues_count++;
        }
        if (count($single_port_vlans) > 0) {
            $config_health_score -= min(15, count($single_port_vlans) * 3);
            $issues_count++;
        }
        if (count($trunk_native_issues) > 0) {
            $config_health_score -= min(25, count($trunk_native_issues) * 5);
            $issues_count++;
        }
        if (count($trunk_excessive_vlans) > 0) {
            $config_health_score -= min(20, count($trunk_excessive_vlans) * 10);
            $issues_count++;
        }

        $config_health_score = max(0, $config_health_score);

        // Status panel with key metrics
        $status_boxes = [
            [
                'title' => 'Configuration Health',
                'value' => ['text' => $config_health_score . '%', 'class' => $config_health_score >= 80 ? 'label-success' : ($config_health_score >= 60 ? 'label-warning' : 'label-danger')],
                'subtitle' => 'Overall VLAN Health'
            ],
            [
                'title' => 'Issues Found',
                'value' => ['text' => $issues_count, 'class' => $issues_count == 0 ? 'label-success' : ($issues_count <= 2 ? 'label-warning' : 'label-danger')],
                'subtitle' => 'Configuration Issues'
            ],
            [
                'title' => 'Unused VLANs',
                'value' => ['text' => count($unused_vlans), 'class' => count($unused_vlans) == 0 ? 'label-success' : 'label-warning'],
                'subtitle' => 'No Port Assignments'
            ],
            [
                'title' => 'Trunk Ports',
                'value' => count($trunk_ports),
                'subtitle' => 'Multi-VLAN Ports'
            ],
            [
                'title' => 'Orphaned VLANs',
                'value' => ['text' => count($orphaned_vlans), 'class' => count($orphaned_vlans) == 0 ? 'label-success' : 'label-info'],
                'subtitle' => 'Device-Only VLANs'
            ],
            [
                'title' => 'VLAN Efficiency',
                'value' => ['text' => round((($vlan_count - count($unused_vlans)) / max($vlan_count, 1)) * 100) . '%', 'class' => 'label-info'],
                'subtitle' => 'Active VLANs Ratio'
            ]
        ];

        echo generate_status_panel($status_boxes);

        // Issues Analysis Box
        if ($issues_count > 0) {
            echo generate_box_open(['title' => 'Configuration Issues & Recommendations', 'icon' => 'fa fa-exclamation-triangle']);

            // Unused VLANs table
            if (count($unused_vlans) > 0) {
                echo '<h4 class="text-warning"><i class="fa fa-exclamation-triangle"></i> Unused VLANs (' . count($unused_vlans) . ')</h4>';
                echo '<table class="table table-striped table-hover table-condensed">';
                echo '<thead><tr>';
                echo '<th class="state-marker"></th>';
                echo '<th>VLAN ID</th>';
                echo '<th>Name</th>';
                echo '<th>Global Usage</th>';
                echo '<th>Recommendation</th>';
                echo '</tr></thead><tbody>';

                foreach (array_slice($unused_vlans, 0, 10) as $vlan_data) {
                    echo '<tr class="warning">';
                    echo '<td class="state-marker"></td>';
                    echo '<td class="entity">' . generate_link('VLAN ' . $vlan_data['vlan_vlan'], ['page' => 'vlan', 'vlan_id' => $vlan_data['vlan_vlan']]) . '</td>';
                    echo '<td>' . escape_html($vlan_data['vlan_name']) . '</td>';
                    echo '<td>';
                    if ($vlan_data['global_count'] > 1) {
                        echo '<span class="label label-info">' . ($vlan_data['global_count'] - 1) . ' other devices</span>';
                    } else {
                        echo '<span class="label label-warning">Only this device</span>';
                    }
                    echo '</td>';
                    echo '<td><small>';
                    if ($vlan_data['global_count'] > 1) {
                        echo 'Verify if needed locally, may be used elsewhere';
                    } else {
                        echo 'Consider removing if not needed';
                    }
                    echo '</small></td>';
                    echo '</tr>';
                }

                if (count($unused_vlans) > 10) {
                    echo '<tr><td colspan="5" class="text-center"><em>... and ' . (count($unused_vlans) - 10) . ' more unused VLANs</em></td></tr>';
                }
                echo '</tbody></table>';
            }

            // Single-port VLANs table
            if (count($single_port_vlans) > 0) {
                echo '<h4 class="text-info"><i class="fa fa-info-circle"></i> Single-Port VLANs (' . count($single_port_vlans) . ')</h4>';
                echo '<table class="table table-striped table-hover table-condensed">';
                echo '<thead><tr>';
                echo '<th class="state-marker"></th>';
                echo '<th>VLAN ID</th>';
                echo '<th>Name</th>';
                echo '<th>Global Usage</th>';
                echo '<th>Assessment</th>';
                echo '</tr></thead><tbody>';

                foreach (array_slice($single_port_vlans, 0, 8) as $vlan_data) {
                    echo '<tr class="info">';
                    echo '<td class="state-marker"></td>';
                    echo '<td class="entity">' . generate_link('VLAN ' . $vlan_data['vlan_vlan'], ['page' => 'vlan', 'vlan_id' => $vlan_data['vlan_vlan']]) . '</td>';
                    echo '<td>' . escape_html($vlan_data['vlan_name']) . '</td>';
                    echo '<td>';
                    if ($vlan_data['global_count'] > 1) {
                        echo '<span class="label label-success">Multi-device</span>';
                    } else {
                        echo '<span class="label label-warning">Local only</span>';
                    }
                    echo '</td>';
                    echo '<td><small>';
                    if ($vlan_data['global_count'] > 1) {
                        echo 'Normal - part of larger network design';
                    } else {
                        echo 'Review - possible misconfiguration';
                    }
                    echo '</small></td>';
                    echo '</tr>';
                }

                if (count($single_port_vlans) > 8) {
                    echo '<tr><td colspan="5" class="text-center"><em>... and ' . (count($single_port_vlans) - 8) . ' more single-port VLANs</em></td></tr>';
                }
                echo '</tbody></table>';
            }

            // Trunk issues table
            if (count($trunk_native_issues) > 0) {
                echo '<h4 class="text-danger"><i class="fa fa-exclamation-circle"></i> Trunk Native VLAN Issues (' . count($trunk_native_issues) . ')</h4>';
                echo '<table class="table table-striped table-hover table-condensed">';
                echo '<thead><tr>';
                echo '<th class="state-marker"></th>';
                echo '<th>Port</th>';
                echo '<th>Description</th>';
                echo '<th>Native VLAN</th>';
                echo '<th>VLANs Count</th>';
                echo '<th>Risk Level</th>';
                echo '</tr></thead><tbody>';

                foreach ($trunk_native_issues as $trunk) {
                    // Determine risk level
                    $risk_class = 'warning';
                    $risk_text = 'Medium';
                    if ($trunk['ifVlan'] == 0 || empty($trunk['ifVlan'])) {
                        $risk_class = 'danger';
                        $risk_text = 'High';
                    } elseif ($trunk['vlan_count'] > 20) {
                        $risk_class = 'danger';
                        $risk_text = 'High';
                    }

                    echo '<tr class="' . ($risk_class == 'danger' ? 'error' : 'warning') . '">';
                    echo '<td class="state-marker"></td>';
                    echo '<td class="entity">' . generate_port_link($trunk, short_ifname($trunk['label'])) . '</td>';
                    echo '<td>' . escape_html($trunk['ifAlias']) . '</td>';
                    echo '<td>';
                    if (empty($trunk['ifVlan'])) {
                        echo '<span class="label label-danger">None</span>';
                    } else {
                        echo '<span class="label label-warning">VLAN ' . $trunk['ifVlan'] . '</span>';
                    }
                    echo '</td>';
                    echo '<td>' . $trunk['vlan_count'] . '</td>';
                    echo '<td><span class="label label-' . $risk_class . '">' . $risk_text . '</span></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }

            echo generate_box_close();
        }

        // Trunk Analysis Box
        if (count($trunk_ports) > 0) {
            echo generate_box_open(['title' => 'Trunk Port Analysis', 'icon' => 'fa fa-sitemap']);

            echo '<table class="table table-striped table-hover table-condensed">';
            echo '<thead><tr>';
            echo '<th class="state-marker"></th>';
            echo '<th>Port</th>';
            echo '<th>Description</th>';
            echo '<th>Native VLAN</th>';
            echo '<th>VLAN Count</th>';
            echo '<th>Status</th>';
            echo '<th>Security Assessment</th>';
            echo '</tr></thead><tbody>';

            foreach (array_slice($trunk_ports, 0, 15) as $trunk) {
                // Determine health status
                $row_class = 'ok';
                $status_text = 'Normal';
                $status_class = 'success';
                $security_text = 'Good';
                $security_class = 'success';

                if ($trunk['ifVlan'] != 1 && !empty($trunk['ifVlan'])) {
                    $row_class = 'warning';
                    $status_text = 'Non-standard Native';
                    $status_class = 'warning';
                }

                if ($trunk['vlan_count'] > 50) {
                    $row_class = 'error';
                    $security_text = 'High Risk';
                    $security_class = 'danger';
                } elseif ($trunk['vlan_count'] > 20) {
                    if ($row_class == 'ok') $row_class = 'warning';
                    $security_text = 'Review Needed';
                    $security_class = 'warning';
                }

                if ($trunk['ifOperStatus'] != 'up') {
                    $row_class = 'disabled';
                    $status_text = 'Port Down';
                    $status_class = 'default';
                }

                echo '<tr class="' . $row_class . '">';
                echo '<td class="state-marker"></td>';
                echo '<td class="entity">' . generate_port_link($trunk, short_ifname($trunk['label'])) . '</td>';
                echo '<td class="small">' . escape_html($trunk['ifAlias']) . '</td>';
                echo '<td>';
                if (empty($trunk['ifVlan'])) {
                    echo '<span class="label label-danger">None</span>';
                } elseif ($trunk['ifVlan'] == 1) {
                    echo '<span class="label label-success">1 (Default)</span>';
                } else {
                    echo '<span class="label label-warning">VLAN ' . $trunk['ifVlan'] . '</span>';
                }
                echo '</td>';
                echo '<td><strong>' . $trunk['vlan_count'] . '</strong></td>';
                echo '<td><span class="label label-' . $status_class . '">' . $status_text . '</span></td>';
                echo '<td><span class="label label-' . $security_class . '">' . $security_text . '</span></td>';
                echo '</tr>';
            }

            if (count($trunk_ports) > 15) {
                echo '<tr><td colspan="7" class="text-center"><em>... and ' . (count($trunk_ports) - 15) . ' more trunk ports</em></td></tr>';
            }
            echo '</tbody></table>';

            echo generate_box_close();
        }

        // Status panel with key metrics
        $status_boxes = [
            [
                'title' => 'VLANs',
                'value' => ['text' => $vlan_count, 'class' => 'label-suppressed'],
                'subtitle' => 'Total VLANs'
            ],
            [
                'title' => 'Active VLANs',
                'value' => ['text' => ($vlan_count - count($unused_vlans)), 'class' => 'label-success'],
                'subtitle' => 'VLANs In Use'
            ],
            [
                'title' => 'Ports/VLAN',
                'value' => ['text' => (round(($stats['total_tagged_ports'] + $stats['total_untagged_ports']) / max($vlan_count, 1), 1)), 'class' => 'label-warning'],
                'subtitle' => 'Average Ports per VLAN'
            ],
        ];

        echo generate_status_panel($status_boxes);


        // Statistics and Recommendations Box
        echo generate_box_open(['title' => 'Advanced Statistics & Insights', 'icon' => 'fa fa-chart-bar']);

        echo '<div class="row">';
        echo '<div class="col-md-6">';

        // Key metrics table
        echo '<h4>Configuration Metrics</h4>';
        echo '<table class="table table-condensed">';
        echo '<tr><td class="state-marker"></td><td>Total VLANs</td><td><strong>' . $vlan_count . '</strong></td></tr>';
        echo '<tr><td class="state-marker"></td><td>Active VLANs</td><td><strong>' . ($vlan_count - count($unused_vlans)) . '</strong></td></tr>';
        echo '<tr><td class="state-marker"></td><td>Average Ports per VLAN</td><td><strong>' . round(($stats['total_tagged_ports'] + $stats['total_untagged_ports']) / max($vlan_count, 1), 1) . '</strong></td></tr>';
        echo '<tr><td class="state-marker"></td><td>Trunk Ports</td><td><strong>' . count($trunk_ports) . '</strong></td></tr>';
        echo '<tr><td class="state-marker"></td><td>Orphaned VLANs</td><td><strong>' . count($orphaned_vlans) . '</strong></td></tr>';
        echo '<tr><td class="state-marker"></td><td>VLAN Efficiency</td><td><strong>' . round((($vlan_count - count($unused_vlans)) / max($vlan_count, 1)) * 100) . '%</strong></td></tr>';
        echo '</table>';

        echo '</div>';
        echo '<div class="col-md-6">';

        // High-span VLANs analysis
        if (count($high_span_vlans) > 0) {
            echo '<h4>High-Span VLANs</h4>';
            echo '<p>VLANs configured on more than 70% of ports:</p>';
            echo '<table class="table table-condensed">';
            foreach ($high_span_vlans as $vlan_data) {
                $percentage = round(($vlan_data['total_ports'] / max($stats['total_ports'], 1)) * 100);
                echo '<tr>';
                echo '<td class="state-marker"></td>';
                echo '<td>' . generate_link('VLAN ' . $vlan_data['vlan_vlan'], ['page' => 'vlan', 'vlan_id' => $vlan_data['vlan_vlan']]) . '</td>';
                echo '<td>' . $vlan_data['total_ports'] . ' ports</td>';
                echo '<td><span class="label label-info">' . $percentage . '%</span></td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<h4>VLAN Distribution</h4>';
            echo '<p class="text-success">Good VLAN distribution - no single VLAN dominates the port usage.</p>';
        }

        // Best practices recommendations
        echo '<h4>Best Practices</h4>';
        echo '<ul class="small">';
        echo '<li><strong>Native VLAN:</strong> Use VLAN 1 or a dedicated management VLAN as native on trunks</li>';
        echo '<li><strong>Security:</strong> Limit trunk VLANs to only what is needed per port</li>';
        echo '<li><strong>Naming:</strong> Use consistent VLAN naming across devices</li>';
        echo '<li><strong>Cleanup:</strong> Remove unused VLANs to simplify configuration</li>';
        echo '<li><strong>Documentation:</strong> Maintain VLAN assignment documentation</li>';
        echo '</ul>';

        echo '</div>';
        echo '</div>';

        echo generate_box_close();
        break;

    case 'graphs':
        // Graph view - show selected graph type for all VLANs
        echo generate_box_open(['title' => 'VLAN Port Graphs - ' . $config['graph_types']['port'][$vars['graph']]['name']]);

        if ($vlan_count === 0) {
            echo generate_box_state('info', 'No VLANs available for graph display', [
                'size' => 'large',
                'icon' => 'fa fa-line-chart'
            ]);
        } else {
            foreach ($vlans as $vlan) {
                if (!is_numeric($vlan['vlan_vlan'])) {
                    continue;
                }

                echo '<h4>VLAN ' . $vlan['vlan_vlan'] . ' - ' . escape_html($vlan['vlan_name']) . '</h4>';
                echo '<div class="row">';

                // Batch load ports for this VLAN
                $vlan_ports = [];

                // Tagged ports
                $tagged_ports = dbFetchRows(
                    "SELECT * FROM `ports_vlans` LEFT JOIN `ports` USING(`device_id`, `port_id`)
                     WHERE `device_id` = ? AND `vlan` = ? AND `deleted` = 0
                     ORDER BY `ifIndex`",
                    [$device['device_id'], $vlan['vlan_vlan']]
                );

                foreach ($tagged_ports as $port) {
                    $vlan_ports[$port['ifIndex']] = $port;
                }

                // Untagged ports
                $untagged_ports = dbFetchRows(
                    "SELECT * FROM `ports`
                     WHERE `device_id` = ? AND `ifVlan` = ? AND `deleted` = 0
                     ORDER BY `ifIndex`",
                    [$device['device_id'], $vlan['vlan_vlan']]
                );

                foreach ($untagged_ports as $port) {
                    $vlan_ports[$port['ifIndex']] = array_merge($port, ['untagged' => '1']);
                }

                ksort($vlan_ports);

                if (empty($vlan_ports)) {
                    echo '<div class="col-md-12">';
                    echo generate_box_state('default', 'No ports assigned to this VLAN', [
                        'size' => 'small',
                        'icon' => 'fa fa-info-circle'
                    ]);
                    echo '</div>';
                } else {
                    foreach ($vlan_ports as $port) {
                        echo '<div class="col-md-3">';
                        echo '<p><strong>' . generate_port_link($port, short_ifname($port['label']));
                        echo ($port['untagged'] ? ' (U)' : ' (T)') . '</strong></p>';
                        print_port_minigraph($port, $graph_type, 'day');
                        echo '</div>';
                    }
                }

                echo '</div>';
                echo '<hr>';
            }
        }

        echo generate_box_close();
        break;
}

register_html_title("VLANs");

// EOF
