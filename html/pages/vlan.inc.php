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

// Global read-only because no permissions checking right now
if ($_SESSION['userlevel'] < 5) {
    print_error_permission();
    return;
}

if (safe_empty($vars['vlan_id']) || !is_numeric($vars['vlan_id'])) {

    $navbar = ['brand' => 'VLANs', 'class' => 'navbar-narrow'];
    $navbar['options']['list']['text'] = 'List View';
    $navbar['options']['matrix']['text'] = 'Device Matrix';
    $navbar['options']['consistency']['text'] = 'Consistency Check';
    $navbar['options']['topology']['text'] = 'VLAN Topology';
    $navbar['options']['analytics']['text'] = 'Analytics';

    if (!isset($vars['view'])) {
        $vars['view'] = 'list';
    }

    foreach ($navbar['options'] as $option => $array) {
        if ($vars['view'] == $option) {
            $navbar['options'][$option]['class'] .= " active";
        }
        $navbar['options'][$option]['url'] = generate_url($vars, ['view' => $option]);
    }

    print_navbar($navbar);

    $search_form = [
      [
        'type' => 'text',
        'name' => 'Search',
        'id' => 'vlan_search',
        'width' => '200px',
        'placeholder' => 'VLAN ID or Name...',
        'value' => $vars['vlan_search'] ?? ''
      ],
      [
        'type' => 'select',
        'name' => 'VLAN Range',
        'id' => 'vlan_range',
        'width' => '150px',
        'value' => $vars['vlan_range'] ?? '',
        'values' => [
          '' => 'All VLANs',
          '1-1005' => 'Standard (1-1005)',
          '1006-4094' => 'Extended (1006-4094)',
          'unused' => 'Unused VLANs',
          'inconsistent' => 'Inconsistent Names'
        ]
      ],
      [
        'type' => 'select',
        'name' => 'Min Devices',
        'id' => 'min_devices',
        'width' => '120px',
        'value' => $vars['min_devices'] ?? '',
        'values' => [
          '' => 'Any Device Count',
          '1' => 'Single Device',
          '2' => '2+ Devices',
          '5' => '5+ Devices',
          '10' => '10+ Devices'
        ]
      ],
      [
        'type' => 'select',
        'name' => 'MAC Status',
        'id' => 'has_macs',
        'width' => '120px',
        'value' => $vars['has_macs'] ?? '',
        'values' => [
          '' => 'Any MAC Status',
          'yes' => 'Has MACs',
          'no' => 'No MACs',
          'many' => '100+ MACs'
        ]
      ]
    ];

    print_search($search_form, 'VLAN Filters', 'search', generate_url($vars));

    $vlans = get_vlans($vars);

    // Apply additional filters
    if (!safe_empty($vars['vlan_search'])) {
        foreach ($vlans as $vlan_id => $vlan) {
            $match = FALSE;
            if (strpos((string)$vlan_id, $vars['vlan_search']) !== FALSE) {
                $match = TRUE;
            } else {
                foreach ($vlan['names'] as $name => $count) {
                    if (stripos($name, $vars['vlan_search']) !== FALSE) {
                        $match = TRUE;
                        break;
                    }
                }
            }
            if (!$match) {
                unset($vlans[$vlan_id]);
            }
        }
    }

    if (!safe_empty($vars['vlan_range'])) {
        switch ($vars['vlan_range']) {
            case '1-1005':
                $vlans = array_filter($vlans, function($k) { return $k >= 1 && $k <= 1005; }, ARRAY_FILTER_USE_KEY);
                break;
            case '1006-4094':
                $vlans = array_filter($vlans, function($k) { return $k >= 1006 && $k <= 4094; }, ARRAY_FILTER_USE_KEY);
                break;
            case 'unused':
                $vlans = array_filter($vlans, function($v) { 
                    return $v['counts']['ports_tagged'] == 0 && $v['counts']['ports_untagged'] == 0; 
                });
                break;
            case 'inconsistent':
                $vlans = array_filter($vlans, function($v) { 
                    return count($v['names']) > 1; 
                });
                break;
        }
    }

    if (!safe_empty($vars['min_devices']) && is_numeric($vars['min_devices'])) {
        $vlans = array_filter($vlans, function($v) use ($vars) { 
            return $v['counts']['devices'] >= $vars['min_devices']; 
        });
    }

    if (!safe_empty($vars['has_macs'])) {
        switch ($vars['has_macs']) {
            case 'yes':
                $vlans = array_filter($vlans, function($v) { return $v['counts']['macs'] > 0; });
                break;
            case 'no':
                $vlans = array_filter($vlans, function($v) { return $v['counts']['macs'] == 0; });
                break;
            case 'many':
                $vlans = array_filter($vlans, function($v) { return $v['counts']['macs'] >= 100; });
                break;
        }
    }

    // Statistics Summary - calculate comprehensive metrics
    $total_vlans = count($vlans);
    $total_macs = 0;
    $multi_device_vlans = 0;
    $inconsistent_vlans = 0;
    $unused_vlans = 0;
    $high_traffic_vlans = 0;

    foreach ($vlans as $vlan) {
        $macs = (int)($vlan['counts']['macs'] ?? 0);
        $total_macs += $macs;

        // Multi-device VLANs
        if (($vlan['counts']['devices'] ?? 0) > 1) {
            $multi_device_vlans++;
        }

        // Inconsistent naming
        if (!empty($vlan['names']) && count($vlan['names']) > 1) {
            $inconsistent_vlans++;
        }

        // Unused VLANs (no ports)
        $tagged = (int)($vlan['counts']['ports_tagged'] ?? 0);
        $untagged = (int)($vlan['counts']['ports_untagged'] ?? 0);
        if ($tagged == 0 && $untagged == 0) {
            $unused_vlans++;
        }

        // High-traffic VLANs (100+ MACs)
        if ($macs >= 100) {
            $high_traffic_vlans++;
        }
    }

    $status_boxes = [
      [
        'title' => 'Total VLANs',
        'value' => ['text' => $total_vlans, 'class' => 'label-suppressed'],
        'subtitle' => 'All VLAN IDs'
      ],
      [
        'title' => 'Multi-Device',
        'value' => ['text' => $multi_device_vlans, 'class' => $multi_device_vlans > 0 ? 'label-primary' : 'label-default'],
        'subtitle' => 'Span Multiple Devices'
      ],
      [
        'title' => 'Inconsistent Names',
        'value' => ['text' => $inconsistent_vlans, 'class' => $inconsistent_vlans > 0 ? 'label-warning' : 'label-success'],
        'subtitle' => 'Different Names'
      ],
      [
        'title' => 'Unused VLANs',
        'value' => ['text' => $unused_vlans, 'class' => $unused_vlans > 0 ? 'label-warning' : 'label-success'],
        'subtitle' => 'No Ports Assigned'
      ],
      [
        'title' => 'High-Traffic VLANs',
        'value' => ['text' => $high_traffic_vlans, 'class' => $high_traffic_vlans > 0 ? 'label-info' : 'label-default'],
        'subtitle' => '100+ MACs Each'
      ],
      [
        'title' => 'Total MACs',
        'value' => ['text' => format_number($total_macs), 'class' => 'label-suppressed'],
        'subtitle' => 'All MAC Addresses'
      ]
    ];

    echo generate_status_panel($status_boxes);

    // Display based on view
    switch ($vars['view']) {

        case 'matrix':
            // Device/VLAN matrix view
            echo generate_box_open();

            $devices = [];
            foreach ($vlans as $vlan_id => $vlan) {
                foreach ($vlan['devices'] as $device_id => $count) {
                    $devices[$device_id] = device_by_id_cache($device_id);
                }
            }

            echo '<div class="table-responsive">';
            echo '<table class="table table-striped table-hover table-condensed table-matrix-sticky">';
            echo '<thead><tr>';
            echo '<th class="state-marker"></th>';
            echo '<th>Device</th>';

            // Limited to first 20 VLANs for readability
            $display_vlans = array_slice($vlans, 0, 20, TRUE);
            foreach ($display_vlans as $vlan_id => $vlan) {
                echo '<th class="text-center" style="writing-mode: vertical-rl; text-orientation: mixed;">';
                echo generate_link('VLAN ' . $vlan_id, ['page' => 'vlan', 'vlan_id' => $vlan_id]);
                echo '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($devices as $device_id => $device) {

                humanize_device($device);

                echo '<tr>';
                echo '<td class="state-marker"></td>';
                echo '<td class="entity">' . generate_device_link_short($device, NULL, ['tab' => 'vlans']) . '</td>';

                foreach ($display_vlans as $vlan_id => $vlan) {
                    echo '<td class="text-center">';
                    if (isset($vlan['devices'][$device_id])) {
                        // Get port counts for this VLAN on this device
                        $tagged = dbFetchCell("SELECT COUNT(*) FROM `ports_vlans` AS pv 
                                             LEFT JOIN `ports` AS p USING(`device_id`, `port_id`) 
                                             WHERE p.`device_id` = ? AND pv.`vlan` = ?", [$device_id, $vlan_id]);
                        $untagged = dbFetchCell("SELECT COUNT(*) FROM `ports` 
                                               WHERE `device_id` = ? AND `ifVlan` = ?", [$device_id, $vlan_id]);

                        $label_class = 'label-primary';
                        if ($tagged > 0 && $untagged > 0) {
                            $label_class = 'label-warning';
                            $text = 'T:' . $tagged . '/U:' . $untagged;
                        } elseif ($tagged > 0) {
                            $text = 'T:' . $tagged;
                        } else {
                            $label_class = 'label-info';
                            $text = 'U:' . $untagged;
                        }
                        echo '<span class="label ' . $label_class . '">' . $text . '</span>';
                    } else {
                        echo '-';
                    }
                    echo '</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
            echo generate_box_close();
            break;

        case 'consistency':
            // VLAN consistency check
            echo generate_box_open();
            echo '<table class="table table-striped table-hover">';
            echo '<thead><tr>';
            echo '<th class="state-marker"></th>';
            echo '<th>VLAN ID</th>';
            echo '<th>Name Variations</th>';
            echo '<th>Devices</th>';
            echo '<th>Recommended Action</th>';
            echo '</tr></thead><tbody>';

            foreach ($vlans as $vlan_id => $vlan) {
                if (!empty($vlan['names']) && count($vlan['names']) > 1) {
                    echo '<tr class="warning">';
                    echo '<td class="state-marker"></td>';
                    echo '<td><strong>' . $vlan_id . '</strong></td>';
                    echo '<td>';
                    foreach ($vlan['names'] as $name => $count) {
                        echo '<span class="label label-danger">' . escape_html($name) . ' (' . $count . ')</span> ';
                    }
                    echo '</td>';
                    echo '<td>';
                    $device_links = [];
                    foreach ($vlan['devices'] as $device_id => $count) {
                        $device = device_by_id_cache($device_id);
                        $device_links[] = '<span class=entity>' .generate_device_link_short($device, NULL, ['tab' => 'vlans']) .'</span>';
                    }
                    echo implode(', ', $device_links);
                    echo '</td>';
                    echo '<td>';
                    // Most common name should be the standard
                    arsort($vlan['names']);
                    $recommended = array_key_first($vlan['names']);
                    echo 'Standardize to: <strong>' . escape_html($recommended) . '</strong>';
                    echo '</td>';
                    echo '</tr>';
                }
            }

            echo '</tbody></table>';
            echo generate_box_close();
            break;

        case 'topology':
            // VLAN spanning tree topology view
            echo generate_box_open();

            // Group VLANs by spanning pattern
            $spanning_patterns = [];
            foreach ($vlans as $vlan_id => $vlan) {
                if ($vlan['counts']['devices'] > 1) {
                    $device_list = array_keys($vlan['devices']);
                    sort($device_list);
                    $pattern = implode(',', $device_list);
                    $spanning_patterns[$pattern][] = $vlan_id;
                }
            }

            echo '<div class="alert alert-info">';
            echo '<strong>VLAN Spanning Patterns:</strong> VLANs grouped by which devices they span across';
            echo '</div>';

            foreach ($spanning_patterns as $pattern => $vlan_list) {
                $device_ids = explode(',', $pattern);
                echo '<div class="well well-sm">';
                echo '<h4>Spanning ' . count($device_ids) . ' devices:</h4>';
                echo '<p><strong>Devices:</strong> ';
                foreach ($device_ids as $device_id) {
                    $device = device_by_id_cache($device_id);
                    echo '<span class="entity">' . generate_device_link_short($device, NULL, ['tab' => 'vlans']) . '</span> ';
                }
                echo '</p>';
                echo '<p><strong>VLANs:</strong> ';
                foreach ($vlan_list as $vlan_id) {
                    echo '<span class="entity">' . generate_link('VLAN ' . $vlan_id, ['page' => 'vlan', 'vlan_id' => $vlan_id]) . '</span> ';
                }
                echo '</p>';
                echo '</div>';
            }

            echo generate_box_close();
            break;

        case 'analytics':
            // VLAN analytics and trends
            echo generate_box_open();

            // VLAN utilization distribution
            $utilization_buckets = [
                'unused' => 0,
                '1-10_ports' => 0,
                '11-50_ports' => 0,
                '51-100_ports' => 0,
                '100+_ports' => 0
            ];

            foreach ($vlans as $vlan_id => $vlan) {
                $total_ports = $vlan['counts']['ports_tagged'] + $vlan['counts']['ports_untagged'];
                if ($total_ports == 0) {
                    $utilization_buckets['unused']++;
                } elseif ($total_ports <= 10) {
                    $utilization_buckets['1-10_ports']++;
                } elseif ($total_ports <= 50) {
                    $utilization_buckets['11-50_ports']++;
                } elseif ($total_ports <= 100) {
                    $utilization_buckets['51-100_ports']++;
                } else {
                    $utilization_buckets['100+_ports']++;
                }
            }

            ?>
            <div class="row">
                <div class="col-md-6">
                    <h4>VLAN Port Utilization</h4>
                    <table class="table table-condensed">
                        <tr>
                            <td>Unused VLANs</td>
                            <td><span class="label label-default"><?php echo $utilization_buckets['unused']; ?></span></td>
                        </tr>
                        <tr>
                            <td>1-10 Ports</td>
                            <td><span class="label label-info"><?php echo $utilization_buckets['1-10_ports']; ?></span></td>
                        </tr>
                        <tr>
                            <td>11-50 Ports</td>
                            <td><span class="label label-primary"><?php echo $utilization_buckets['11-50_ports']; ?></span></td>
                        </tr>
                        <tr>
                            <td>51-100 Ports</td>
                            <td><span class="label label-warning"><?php echo $utilization_buckets['51-100_ports']; ?></span></td>
                        </tr>
                        <tr>
                            <td>100+ Ports</td>
                            <td><span class="label label-danger"><?php echo $utilization_buckets['100+_ports']; ?></span></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h4>Top VLANs by MAC Count</h4>
                    <table class="table table-condensed">
                        <?php
                        // Sort VLANs by MAC count
                        uasort($vlans, function($a, $b) {
                            return ($b['counts']['macs'] ?? 0) - ($a['counts']['macs'] ?? 0);
                        });
                        $top_vlans = array_slice($vlans, 0, 5, TRUE);

                        foreach ($top_vlans as $vlan_id => $vlan) {
                            if (($vlan['counts']['macs'] ?? 0) > 0) {
                                echo '<tr>';
                                echo '<td>' . generate_link('VLAN ' . $vlan_id, ['page' => 'vlan', 'vlan_id' => $vlan_id]) . '</td>';
                                echo '<td><span class="label label-warning">' . $vlan['counts']['macs'] . ' MACs</span></td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </table>
                </div>
            </div>
            <?php

            echo generate_box_close();
            break;

        default: // List view
            echo generate_box_open();
            echo '<table class="table table-striped table-hover">';

            $cols = [
                'VLAN ID',
                'VLAN Name(s)',
                'Devices',
                ['Tagged Ports', 'class="text-center"'],
                ['Untagged Ports', 'class="text-center"'],
                ['Unique MACs', 'class="text-center"'],
                ['Status', 'class="text-center"']
            ];
            echo get_table_header($cols, $vars);

            foreach ($vlans as $vlan_id => $vlan) {
                if ($vlan_id === '') {
                    continue;
                }

                // Determine primary name
                if (is_array($vlan['names'])) {
                    arsort($vlan['names']);
                    $primary_name = array_key_first($vlan['names']);
                    $name_inconsistent = count($vlan['names']) > 1;
                } else {
                    $primary_name = "VLAN $vlan_id";
                    $name_inconsistent = FALSE;
                }

                echo '<tr>';
                echo '<td><strong>' . generate_link($vlan_id, ['page' => 'vlan', 'vlan_id' => $vlan_id]) . '</strong></td>';
                echo '<td>';
                echo escape_html($primary_name);
                if ($name_inconsistent) {
                    echo ' <span class="label label-warning" title="' . count($vlan['names']) . ' name variations">!</span>';
                }
                echo '</td>';
                echo '<td><span class="label label-primary">' . $vlan['counts']['devices'] . '</span></td>';
                echo '<td class="text-center"><span class="label label-primary">' . ($vlan['counts']['ports_tagged'] ?? 0) . '</span></td>';
                echo '<td class="text-center"><span class="label label-info">' . ($vlan['counts']['ports_untagged'] ?? 0) . '</span></td>';
                echo '<td class="text-center"><span class="label label-suppressed">' . ($vlan['counts']['macs'] ?? 0) . '</span></td>';
                echo '<td class="text-center">';

                // Status indicators
                if (($vlan['counts']['ports_tagged'] ?? 0) + ($vlan['counts']['ports_untagged'] ?? 0) == 0) {
                    echo '<span class="label label-default">Unused</span>';
                } elseif ($vlan['counts']['devices'] > 5) {
                    echo '<span class="label label-danger">Wide Span</span>';
                } elseif (($vlan['counts']['macs'] ?? 0) > 500) {
                    echo '<span class="label label-warning">High MAC</span>';
                } else {
                    echo '<span class="label label-success">Active</span>';
                }

                echo '</td>';
                echo '</tr>';
            }

            echo '</table>';
            echo generate_box_close();
            break;
    }

} else {
    // Per-VLAN detailed page
    $vlan_id = $vars['vlan_id'];

    // Fetch comprehensive VLAN information
    $vlan_names = dbFetchRows("SELECT DISTINCT `vlan_name`, `device_id` FROM `vlans` WHERE `vlan_vlan` = ?", [$vlan_id]);
    $count_untagged = dbFetchCell("SELECT COUNT(*) FROM `ports` WHERE `ifVlan` = ? AND `deleted` = 0", [$vlan_id]);
    $count_device   = dbFetchCell("SELECT COUNT(DISTINCT(`device_id`)) FROM `vlans` WHERE `vlan_vlan` = ?", [$vlan_id]);
    $count_tagged   = dbFetchCell("SELECT COUNT(*) FROM `ports_vlans` AS pv LEFT JOIN `ports` AS p USING(`device_id`, `port_id`) WHERE `vlan` = ? AND p.`deleted` = 0", [$vlan_id]);
    $count_mac      = dbFetchCell("SELECT COUNT(DISTINCT(`mac_address`)) FROM `vlans_fdb` WHERE `vlan_id` = ? AND `deleted` = 0", [$vlan_id]);

    // Check for name consistency
    $unique_names = array_unique(array_column($vlan_names, 'vlan_name'));
    $name_consistency = count($unique_names) == 1 ? 'Consistent' : 'Inconsistent (' . count($unique_names) . ' variations)';

    // VLAN Header with status panel
    $first_seen = dbFetchCell("SELECT MIN(`vlan_added`) FROM `vlans` WHERE `vlan_vlan` = ?", [$vlan_id]);

    // Calculate port breakdown
    $total_ports = $count_tagged + $count_untagged;
    $port_breakdown = [];
    if ($total_ports > 0) {
        $tagged_pct = round(($count_tagged / $total_ports) * 100);
        $untagged_pct = 100 - $tagged_pct;
        $port_breakdown = [
            'html' => '<span class="label-group">' .
                     '<span class="label label-primary">' . $tagged_pct . '%</span>' .
                     '<span class="label label-info">' . $untagged_pct . '%</span>' .
                     '</span>'
        ];
    } else {
        $port_breakdown = ['text' => 'No Ports', 'class' => 'label-default'];
    }

    $status_boxes = [
        [
            'title' => 'VLAN ID',
            'value' => ['text' => $vlan_id, 'class' => 'label-primary'],
            'subtitle' => 'Identifier'
        ],
        [
            'title' => 'Devices',
            'value' => ['text' => $count_device, 'class' => 'label-primary'],
            'subtitle' => 'Running This VLAN'
        ],
        [
            'title' => 'Total Ports',
            'value' => ['text' => $total_ports, 'class' => 'label-primary'],
            'subtitle' => 'All Assignments'
        ],
        [
            'title' => 'Port Breakdown',
            'value' => $port_breakdown,
            'subtitle' => 'Tagged / Untagged'
        ],
        [
            'title' => 'Tagged Ports',
            'value' => ['text' => $count_tagged, 'class' => $count_tagged > 0 ? 'label-primary' : 'label-default'],
            'subtitle' => 'Trunk Ports'
        ],
        [
            'title' => 'Untagged Ports',
            'value' => ['text' => $count_untagged, 'class' => $count_untagged > 0 ? 'label-info' : 'label-default'],
            'subtitle' => 'Access Ports'
        ],
        [
            'title' => 'Name Consistency',
            'value' => ['text' => count($unique_names) == 1 ? 'OK' : count($unique_names) . ' Names', 'class' => count($unique_names) == 1 ? 'label-success' : 'label-warning'],
            'subtitle' => count($unique_names) == 1 ? $unique_names[0] : 'Multiple Names'
        ],
        [
            'title' => 'MAC Addresses',
            'value' => ['text' => format_number($count_mac), 'class' => 'label-suppressed'],
            'subtitle' => 'Learned MACs'
        ]
    ];

    echo generate_status_panel($status_boxes);

    // Show first seen info
    if ($first_seen) {
        echo '<div class="alert alert-info">';
        echo '<strong>VLAN ' . $vlan_id . '</strong> first seen: ' . format_uptime(time() - strtotime($first_seen)) . ' ago';
        if (count($unique_names) > 1) {
            echo '<br><strong>Name variations:</strong> ' . escape_html(implode(', ', $unique_names));
        }
        echo '</div>';
    }

    // Enhanced navigation tabs
    $navbar = ['brand' => "VLAN " . $vlan_id, 'class' => "navbar-narrow"];

    $navbar['options']['devices']['text'] = 'Devices';
    $navbar['options']['ports']['text'] = 'All Ports';
    $navbar['options']['tagged']['text'] = 'Tagged Ports';
    $navbar['options']['untagged']['text'] = 'Untagged Ports';
    $navbar['options']['macs']['text'] = 'MAC Addresses';
    $navbar['options']['topology']['text'] = 'Topology';
    $navbar['options']['history']['text'] = 'History';

    if (!isset($vars['view'])) {
        $vars['view'] = "devices";
    }

    foreach ($navbar['options'] as $option => $array) {
        if ($vars['view'] == $option) {
            $navbar['options'][$option]['class'] .= " active";
        }
        $navbar['options'][$option]['url'] = generate_url($vars, ['view' => $option]);
    }

    $navbar['options']['vlans']['text']  = "All VLANs";
    $navbar['options']['vlans']['icon']  = "icon-angle-left";
    $navbar['options']['vlans']['url']   = generate_url(['page' => 'vlan']);
    $navbar['options']['vlans']['right'] = TRUE;

    print_navbar($navbar);

    switch ($vars['view']) {

        case 'ports':
        case 'tagged':
        case 'untagged':
            // Comprehensive port view
            echo generate_box_open();
            echo '<table class="table table-striped table-hover">';
            echo '<thead><tr>';
            echo '<th class="state-marker"></th>';
            echo '<th>Device</th>';
            echo '<th>Port</th>';
            echo '<th>Description</th>';
            echo '<th>Type</th>';
            echo '<th>Speed</th>';
            echo '<th>Status</th>';
            echo '<th>Utilization</th>';
            echo '</tr></thead><tbody>';

            if ($vars['view'] == 'ports' || $vars['view'] == 'tagged') {
                $sql = "SELECT p.*, pv.*, d.* FROM `ports_vlans` AS pv 
                        LEFT JOIN `ports` AS p USING(`device_id`, `port_id`) 
                        LEFT JOIN `devices` AS d USING(`device_id`)
                        WHERE pv.`vlan` = ? AND p.`deleted` = 0 
                        ORDER BY d.`hostname`, p.`ifIndex`";
                foreach (dbFetchRows($sql, [$vlan_id]) as $port) {
                    humanize_port($port);
                    echo '<tr class="'.$port['row_class'].'">';
                    echo '<td class="state-marker">';
                    echo '<td class="entity">' . generate_device_link_short($port) . '</td>';
                    echo '<td class="entity">' . generate_port_link($port) . '</td>';
                    echo '<td>' . escape_html($port['ifAlias']) . '</td>';
                    echo '<td><span class="label label-default">Tagged</span></td>';
                    echo '<td>' . format_bps($port['ifSpeed']) . '</td>';
                    echo '<td>';
                    if ($port['ifOperStatus'] == 'up') {
                        echo '<span class="label label-success">Up</span>';
                    } elseif ($port['ifAdminStatus'] == 'down') {
                        echo '<span class="label label-default">Admin Down</span>';
                    } else {
                        echo '<span class="label label-danger">Down</span>';
                    }
                    echo '</td>';
                    echo '<td style="white-space: nowrap;">';
                    if ($port['ifSpeed'] > 0) {
                        $in_perc = round(($port['ifInOctets_rate'] * 8) / $port['ifSpeed'] * 100);
                        $out_perc = round(($port['ifOutOctets_rate'] * 8) / $port['ifSpeed'] * 100);
                        echo '<i class="icon-circle-arrow-down text-success"></i>' . sprintf('%2d', $in_perc) . '% ';
                        echo '<i class="icon-circle-arrow-up text-primary"></i>' . sprintf('%2d', $out_perc) . '%';
                    } else {
                        echo '<span class="text-muted">-</span>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
            }

            if ($vars['view'] == 'ports' || $vars['view'] == 'untagged') {
                $sql = "SELECT p.*, d.* FROM `ports` AS p 
                        LEFT JOIN `devices` AS d USING(`device_id`)
                        WHERE p.`ifVlan` = ? AND p.`deleted` = 0 
                        ORDER BY d.`hostname`, p.`ifIndex`";
                foreach (dbFetchRows($sql, [$vlan_id]) as $port) {
                    humanize_port($port);
                    echo '<tr class="'.$port['row_class'].'">';
                    echo '<td class="state-marker"></td>';
                    echo '<td class="entity">' . generate_device_link_short($port) . '</td>';
                    echo '<td class="entity">' . generate_port_link($port, $port['port_label_short']) . '</td>';
                    echo '<td>' . escape_html($port['ifAlias']) . '</td>';
                    echo '<td><span class="label label-info">Untagged</span></td>';
                    echo '<td>' . format_bps($port['ifSpeed']) . '</td>';
                    echo '<td>';
                    if ($port['ifOperStatus'] == 'up') {
                        echo '<span class="label label-success">Up</span>';
                    } elseif ($port['ifAdminStatus'] == 'down') {
                        echo '<span class="label label-default">Admin Down</span>';
                    } else {
                        echo '<span class="label label-danger">Down</span>';
                    }
                    echo '</td>';
                    echo '<td style="white-space: nowrap;">';
                    if ($port['ifSpeed'] > 0) {
                        $in_perc = round(($port['ifInOctets_rate'] * 8) / $port['ifSpeed'] * 100);
                        $out_perc = round(($port['ifOutOctets_rate'] * 8) / $port['ifSpeed'] * 100);
                        echo '<i class="icon-circle-arrow-down text-success"></i>' . sprintf('%2d', $in_perc) . '% ';
                        echo '<i class="icon-circle-arrow-up text-primary"></i>' . sprintf('%2d', $out_perc) . '%';
                    } else {
                        echo '<span class="text-muted">-</span>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
            }

            echo '</tbody></table>';
            echo generate_box_close();
            break;

        case 'macs':

            // MAC statistics
            $mac_stats = dbFetchRow("SELECT 
                COUNT(DISTINCT `mac_address`) as unique_macs,
                COUNT(DISTINCT `device_id`) as devices_with_macs,
                COUNT(*) as total_entries,
                MIN(`fdb_last_change`) as first_seen,
                MAX(`fdb_last_change`) as last_updated
                FROM `vlans_fdb` 
                WHERE `vlan_id` = ? AND `deleted` = 0", [$vlan_id]);

            if ($mac_stats['unique_macs'] > 0) {
                $status_boxes = [
                    [
                        'title' => 'Unique MACs',
                        'value' => format_number($mac_stats['unique_macs']),
                        'subtitle' => 'Learned Addresses'
                    ],
                    [
                        'title' => 'Active Devices', 
                        'value' => ['text' => $mac_stats['devices_with_macs'], 'class' => $mac_stats['devices_with_macs'] > 0 ? 'label-success' : 'label-default'],
                        'subtitle' => 'With MAC Entries'
                    ],
                    [
                        'title' => 'Total Entries',
                        'value' => ['text' => format_number($mac_stats['total_entries']), 'class' => 'label-info'],
                        'subtitle' => 'FDB Records'
                    ],
                    [
                        'title' => 'Last Activity',
                        'value' => $mac_stats['last_updated'] ? format_uptime(time() - $mac_stats['last_updated']) : 'Unknown',
                        'subtitle' => 'Most Recent Update'
                    ]
                ];

                echo generate_status_panel($status_boxes);
            }

            echo generate_box_open();

            echo '<table class="table table-striped table-hover table-condensed">';
            echo '<thead><tr>';
            echo '<th width="100">MAC Address</th>';
            echo '<th width="150">Vendor</th>';
            echo '<th width="40">Infra</th>';
            echo '<th width="150">Device</th>';
            echo '<th width="150">Port</th>';
            echo '<th width="100">Visibility</th>';
            echo '<th>VLAN Context</th>';
            echo '<th width="90">Last Seen</th>';
            echo '</tr></thead><tbody>';

            $sql = "SELECT `mac_address`,
                    COUNT(DISTINCT(`device_id`)) AS `device_count`, 
                    COUNT(*) AS `entry_count`,
                    MAX(`fdb_last_change`) AS `last_seen`
                    FROM `vlans_fdb` 
                    WHERE `vlan_id` = ? AND `deleted` = 0
                    GROUP BY `mac_address` 
                    ORDER BY `entry_count` DESC, `mac_address`
                    LIMIT 1000";

            $macs = dbFetchRows($sql, [$vlan_id]);

            // Get vendor information for all MACs at once
            $mac_list = array_column($macs, 'mac_address');
            $vendors = get_mac_vendors_bulk($mac_list);

            // Get all port associations for these MACs in a single query
            $port_associations = [];
            $port_mac_matches = [];
            if (!empty($mac_list)) {
                // Get port associations (which port the MAC was learned on)
                $placeholders_fdb = str_repeat('?,', count($mac_list) - 1) . '?';
                $fdb_ports_sql = "SELECT f.mac_address, f.fdb_last_change, p.*, d.hostname, d.device_id as dev_id
                                  FROM vlans_fdb f
                                  LEFT JOIN ports p ON (f.device_id = p.device_id AND f.port_id = p.port_id)
                                  LEFT JOIN devices d ON (p.device_id = d.device_id)
                                  WHERE f.vlan_id = ? AND f.deleted = 0 
                                  AND f.mac_address IN ($placeholders_fdb)
                                  ORDER BY f.mac_address, f.fdb_last_change DESC";

                $fdb_results = dbFetchRows($fdb_ports_sql, array_merge([$vlan_id], $mac_list));

                // Group by MAC address, collect all ports (for MACs that appear on multiple ports)
                foreach ($fdb_results as $result) {
                    if (!isset($port_associations[$result['mac_address']])) {
                        $port_associations[$result['mac_address']] = [];
                    }
                    // Only add if we have valid port info and haven't seen this port yet
                    if ($result['port_id'] && $result['hostname']) {
                        $port_key = $result['device_id'] . '_' . $result['port_id'];
                        if (!isset($port_associations[$result['mac_address']][$port_key])) {
                            $port_associations[$result['mac_address']][$port_key] = $result;
                        }
                    }
                }

                // Check which MAC addresses belong to ports (network infrastructure)
                $placeholders = str_repeat('?,', count($mac_list) - 1) . '?';
                $port_mac_sql = "SELECT p.ifPhysAddress as mac_address, p.*, d.hostname
                                 FROM ports p
                                 LEFT JOIN devices d ON (p.device_id = d.device_id)
                                 WHERE p.deleted = 0 AND p.ifPhysAddress IN ($placeholders)";

                $port_mac_results = dbFetchRows($port_mac_sql, $mac_list);

                foreach ($port_mac_results as $port_result) {
                    $port_mac_matches[$port_result['mac_address']] = $port_result;
                }

                // Get VLAN context for all MACs - show how many other VLANs they appear in
                $vlan_context = [];
                $placeholders_vlan = str_repeat('?,', count($mac_list) - 1) . '?';
                $vlan_context_sql = "SELECT mac_address, COUNT(DISTINCT vlan_id) as vlan_count,
                                     GROUP_CONCAT(DISTINCT vlan_id ORDER BY vlan_id SEPARATOR ',') as vlan_list
                                     FROM vlans_fdb 
                                     WHERE deleted = 0 AND mac_address IN ($placeholders_vlan)
                                     GROUP BY mac_address";

                $vlan_results = dbFetchRows($vlan_context_sql, $mac_list);

                foreach ($vlan_results as $vlan_result) {
                    $vlan_context[$vlan_result['mac_address']] = $vlan_result;
                }
            }

            foreach ($macs as $mac) {
                $vendor = $vendors[$mac['mac_address']] ?? 'Unknown';
                $is_infrastructure = isset($port_mac_matches[$mac['mac_address']]);

                echo '<tr>';

                // MAC Address column
                echo '<td><code>' . format_mac($mac['mac_address']) . '</code></td>';

                // Vendor column - truncate unless row will be 2 lines anyway
                if ($is_infrastructure) {
                    // Infrastructure rows are already 2 lines, so show full vendor
                    echo '<td><small>' . $vendor . '</small></td>';
                } else {
                    // Endpoint rows should be 1 line, so truncate vendor
                    $truncated_vendor = strlen($vendor) > 20 ? substr($vendor, 0, 17) . '...' : $vendor;
                    echo '<td><small>' . $truncated_vendor . '</small></td>';
                }

                // Infra column
                if ($is_infrastructure) {
                    echo '<td><span class="label label-success">Infra</span></td>';
                } else {
                    echo '<td></td>';
                }

                // Device column - show the device this MAC belongs to (for infrastructure)
                if ($is_infrastructure) {
                    $infra_device = $port_mac_matches[$mac['mac_address']];
                    echo '<td><span class="entity">' . generate_device_link($infra_device, short_hostname($infra_device['hostname'])) . '</span>';
                    echo '<br><small class="text-muted">' . escape_html(substr($infra_device['location'] ?? '', 0, 30)) . '</small></td>';
                } else {
                    echo '<td></td>';
                }

                // Port column - show the port this MAC belongs to (for infrastructure)
                if ($is_infrastructure) {
                    $infra_device = $port_mac_matches[$mac['mac_address']];
                    echo '<td><span class="entity">' . generate_port_link($infra_device, $infra_device['port_label_short']) . '</span>';
                    echo '<br><small class="text-muted">' . escape_html(substr($infra_device['ifAlias'] ?? '', 0, 20)) . '</small></td>';
                } else {
                    echo '<td></td>';
                }

                // Visibility column - shows how widely this MAC is seen in the L2 domain
                echo '<td><div class="label-group">';
                if ($mac['device_count'] == 1) {
                    echo '<span class="label label-success">Local</span>';
                    echo '<span class="label label-default">1 switch</span>';
                } elseif ($mac['device_count'] <= 3) {
                    echo '<span class="label label-info">Limited</span>';
                    echo '<span class="label label-default">' . $mac['device_count'] . ' switches</span>';
                } else {
                    echo '<span class="label label-warning">Wide</span>';
                    echo '<span class="label label-default">' . $mac['device_count'] . ' switches</span>';
                }
                echo '</div></td>';

                // VLAN Context column - shows how many VLANs this MAC appears in
                if (isset($vlan_context[$mac['mac_address']])) {
                    $context = $vlan_context[$mac['mac_address']];
                    $vlan_count = $context['vlan_count'];
                    $vlan_list = $context['vlan_list'];
                    $vlan_array = explode(',', $vlan_list);

                    echo '<td>';

                    // Always show the count label first
                    if ($vlan_count == 1) {
                        echo '<span class="label label-success">1 VLAN</span> ';
                    } elseif ($vlan_count <= 30) {
                        echo '<span class="label label-info">' . $vlan_count . ' VLANs</span> ';
                    } else {
                        echo '<span class="label label-warning">' . $vlan_count . ' VLANs</span> ';
                    }

                    // Show VLAN links
                    if ($vlan_count <= 30) {
                        $vlan_links = [];
                        foreach ($vlan_array as $vlan_id) {
                            $vlan_links[] = '<span class="entity">' . generate_link($vlan_id, ['page' => 'vlan', 'vlan_id' => $vlan_id]) . '</span>';
                        }
                        echo '<small>' . implode(', ', $vlan_links) . '</small>';
                    } else {
                        $first_few = array_slice($vlan_array, 0, 30);
                        $vlan_links = [];
                        foreach ($first_few as $vlan_id) {
                            $vlan_links[] = '<span class="entity">' . generate_link($vlan_id, ['page' => 'vlan', 'vlan_id' => $vlan_id]) . '</span>';
                        }
                        echo '<small>' . implode(', ', $vlan_links) . '...</small>';
                    }

                    echo '</td>';
                } else {
                    echo '<td><span class="text-muted">Unknown</span></td>';
                }

                // Last Seen column
                echo '<td>' . ($mac['last_seen'] ? format_uptime(time() - $mac['last_seen'], 'short-2') . ' ago' : 'Unknown') . '</td>';

                echo '</tr>';
            }

            echo '</tbody></table>';
            echo generate_box_close();
            break;

        case 'topology':
            include($config['html_dir'] . '/pages/vlan/topology.inc.php');
            break;

        case 'history':
            // VLAN change history - Work in Progress
            echo generate_box_open();

            $content = '<div class="box-state-title">VLAN History - Work in Progress</div>';
            $content .= '<p class="box-state-description">VLAN change history and MAC address trends are currently being developed.</p>';

            echo generate_box_state('info', $content, [
                'icon' => $config['icon']['info'],
                'size' => 'large'
            ]);

            echo generate_box_close();
            break;

        case 'devices':
        default:
            // Get basic device list first, then batch statistics  
            $sql = "SELECT DISTINCT v.device_id, v.vlan_vlan, v.vlan_name, 
                           d.hostname, d.status, d.ignore, d.disabled
                    FROM vlans v 
                    LEFT JOIN devices d USING (device_id) 
                    WHERE v.vlan_vlan = ? 
                    ORDER BY d.hostname";
            $devices = dbFetchRows($sql, [$vlan_id]);


            if (!empty($devices)) {
                $device_ids = array_column($devices, 'device_id');
                $device_id_placeholders = str_repeat('?,', count($device_ids) - 1) . '?';

                // Batch load statistics
                $tagged_counts = [];
                $tagged_results = dbFetchRows("SELECT pv.device_id, COUNT(*) as tagged_count
                                              FROM ports_vlans pv
                                              LEFT JOIN ports p USING(device_id, port_id)
                                              WHERE pv.vlan = ? AND pv.device_id IN ($device_id_placeholders) AND p.deleted = 0
                                              GROUP BY pv.device_id", 
                                              array_merge([$vlan_id], $device_ids));
                foreach ($tagged_results as $row) {
                    $tagged_counts[$row['device_id']] = $row['tagged_count'];
                }

                $untagged_counts = [];
                $untagged_results = dbFetchRows("SELECT device_id, COUNT(*) as untagged_count
                                                FROM ports
                                                WHERE ifVlan = ? AND device_id IN ($device_id_placeholders) AND deleted = 0
                                                GROUP BY device_id", 
                                                array_merge([$vlan_id], $device_ids));
                foreach ($untagged_results as $row) {
                    $untagged_counts[$row['device_id']] = $row['untagged_count'];
                }

                $mac_counts = [];
                $mac_results = dbFetchRows("SELECT device_id, COUNT(DISTINCT mac_address) as mac_count
                                           FROM vlans_fdb
                                           WHERE vlan_id = ? AND device_id IN ($device_id_placeholders) AND deleted = 0
                                           GROUP BY device_id", 
                                           array_merge([$vlan_id], $device_ids));
                foreach ($mac_results as $row) {
                    $mac_counts[$row['device_id']] = $row['mac_count'];
                }

                // Add statistics to devices array
                foreach ($devices as &$device) {
                    $device['tagged_count'] = $tagged_counts[$device['device_id']] ?? 0;
                    $device['untagged_count'] = $untagged_counts[$device['device_id']] ?? 0;
                    $device['mac_count'] = $mac_counts[$device['device_id']] ?? 0;
                }
                unset($device); // Important: unset reference to prevent contamination
            }
            $device_count = count($devices);

            echo generate_box_open(['title' => "Devices Running VLAN $vlan_id ($device_count total)"]);

            if (empty($devices)) {
                $content = '<div class="box-state-title">No Devices Found</div>';
                $content .= '<p class="box-state-description">This VLAN is not configured on any monitored devices.</p>';

                echo generate_box_state('info', $content, [
                    'icon' => $config['icon']['info'],
                    'size' => 'medium'
                ]);
            } else {
                // Get total port counts for each device for percentage calculation
                $device_port_totals = [];
                $device_ids = array_column($devices, 'device_id');
                $device_id_placeholders = str_repeat('?,', count($device_ids) - 1) . '?';
                $port_total_results = dbFetchRows("SELECT device_id, COUNT(*) as total_ports
                                                   FROM ports
                                                   WHERE device_id IN ($device_id_placeholders) AND deleted = 0
                                                   GROUP BY device_id",
                                                   $device_ids);
                foreach ($port_total_results as $row) {
                    $device_port_totals[$row['device_id']] = $row['total_ports'];
                }

                echo '<table class="table table-striped table-hover table-condensed">';
                echo '<thead><tr>';
                echo '<th class="state-marker"></th>';
                echo '<th>Device</th>';
                echo '<th>VLAN Name</th>';
                echo '<th class="text-center">Tagged</th>';
                echo '<th class="text-center">Untagged</th>';
                echo '<th class="text-center">Total</th>';
                echo '<th class="text-center">Port %</th>';
                echo '<th class="text-center">MACs</th>';
                echo '<th class="text-center">Activity</th>';
                echo '</tr></thead><tbody>';

                foreach ($devices as $device) {
                    $tagged_count = (int)$device['tagged_count'];
                    $untagged_count = (int)$device['untagged_count'];
                    $mac_count = (int)$device['mac_count'];
                    $total_ports = $tagged_count + $untagged_count;
                    $device_total_ports = $device_port_totals[$device['device_id']] ?? 1;
                    $port_percentage = $device_total_ports > 0 ? round(($total_ports / $device_total_ports) * 100) : 0;

                    // Determine row class based on VLAN activity
                    $row_class = '';
                    if ($total_ports == 0) {
                        $row_class = 'warning'; // VLAN defined but no ports
                    } elseif ($mac_count > 0) {
                        $row_class = ''; // Active VLAN - no special highlighting
                    } else {
                        $row_class = 'info'; // Configured but no MACs
                    }

                    echo '<tr class="' . $row_class . '">';
                    echo '<td class="state-marker"></td>';

                    echo '<td class="entity">';
                    echo generate_device_link($device, short_hostname($device['hostname']), ['tab' => 'vlans']);
                    echo '</td>';

                    echo '<td>' . escape_html($device['vlan_name']) . '</td>';

                    echo '<td class="text-center">';
                    echo '<span class="label ' . ($tagged_count > 0 ? 'label-primary' : 'label-default') . '">' . $tagged_count . '</span>';
                    echo '</td>';

                    echo '<td class="text-center">';
                    echo '<span class="label ' . ($untagged_count > 0 ? 'label-info' : 'label-default') . '">' . $untagged_count . '</span>';
                    echo '</td>';

                    echo '<td class="text-center">';
                    echo '<span class="label ' . ($total_ports > 0 ? 'label-primary' : 'label-default') . '">' . $total_ports . '</span>';
                    echo '</td>';

                    echo '<td class="text-center">';
                    if ($port_percentage > 0) {
                        $pct_class = $port_percentage >= 50 ? 'label-info' : 'label-default';
                        echo '<span class="label ' . $pct_class . '">' . $port_percentage . '%</span>';
                    } else {
                        echo '<span class="label label-default">0%</span>';
                    }
                    echo '</td>';

                    echo '<td class="text-center">';
                    echo '<span class="label ' . ($mac_count > 0 ? 'label-suppressed' : 'label-default') . '">' . format_number($mac_count) . '</span>';
                    echo '</td>';

                    // Activity indicator based on MAC presence
                    echo '<td class="text-center">';
                    if ($total_ports == 0) {
                        echo '<span class="label label-warning">No Ports</span>';
                    } elseif ($mac_count > 0) {
                        echo '<span class="label label-success">Active</span>';
                    } else {
                        echo '<span class="label label-default">Idle</span>';
                    }
                    echo '</td>';

                    echo '</tr>';
                }

                echo '</tbody></table>';
            }

            echo generate_box_close();
            break;
    }
}

// EOF
