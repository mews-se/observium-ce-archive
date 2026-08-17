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

// VLAN Topology Visualization
// Shows network topology for a specific VLAN using Cytoscape.js

// Get all neighbor connections carrying this VLAN with link aggregation info
$sql = "SELECT n.port_id, n.remote_port_id, n.neighbour_id,
        p1.device_id as local_device_id, p1.port_id as local_port_id,
        p2.device_id as remote_device_id, p2.port_id as remote_port_id,
        d1.hostname as local_hostname, d2.hostname as remote_hostname,
        d1.type as local_device_type, d2.type as remote_device_type,
        p1.port_label_short as local_port, p2.port_label_short as remote_port,
        p1.ifOperStatus, p1.ifSpeed, p1.ifInOctets_rate, p1.ifOutOctets_rate,
        p1.ifInErrors_rate as local_ifInErrors_rate, p1.ifOutErrors_rate as local_ifOutErrors_rate,
        p1.ifInDiscards_rate as local_ifInDiscards_rate, p1.ifOutDiscards_rate as local_ifOutDiscards_rate,
        p1.ifVlan as local_ifvlan, p1.ifLastChange as local_ifLastChange,
        p2.ifOperStatus as remote_ifOperStatus, p2.ifInOctets_rate as remote_ifInOctets_rate, p2.ifOutOctets_rate as remote_ifOutOctets_rate,
        p2.ifInErrors_rate as remote_ifInErrors_rate, p2.ifOutErrors_rate as remote_ifOutErrors_rate,
        p2.ifInDiscards_rate as remote_ifInDiscards_rate, p2.ifOutDiscards_rate as remote_ifOutDiscards_rate,
        p2.ifVlan as remote_ifvlan, p2.ifLastChange as remote_ifLastChange,
        pv1.vlan as pv1_vlan, pv2.vlan as pv2_vlan,
        ph1.port_label_short as local_parent_port, ph2.port_label_short as remote_parent_port
        FROM `neighbours` AS n
        LEFT JOIN `ports` AS p1 ON (n.port_id = p1.port_id)
        LEFT JOIN `ports` AS p2 ON (n.remote_port_id = p2.port_id)
        LEFT JOIN `devices` AS d1 ON (p1.device_id = d1.device_id)
        LEFT JOIN `devices` AS d2 ON (p2.device_id = d2.device_id)
        LEFT JOIN `ports_vlans` AS pv1 ON (p1.device_id = pv1.device_id AND p1.port_id = pv1.port_id AND pv1.vlan = ?)
        LEFT JOIN `ports_vlans` AS pv2 ON (p2.device_id = pv2.device_id AND p2.port_id = pv2.port_id AND pv2.vlan = ?)
        LEFT JOIN `ports_stack` AS ps1 ON (p1.port_id = ps1.port_id_low AND ps1.ifStackStatus = 'active')
        LEFT JOIN `ports` AS ph1 ON (ps1.port_id_high = ph1.port_id)
        LEFT JOIN `ports_stack` AS ps2 ON (p2.port_id = ps2.port_id_low AND ps2.ifStackStatus = 'active')
        LEFT JOIN `ports` AS ph2 ON (ps2.port_id_high = ph2.port_id)
        WHERE (pv1.vlan IS NOT NULL OR pv2.vlan IS NOT NULL) AND n.active = 1
        AND (p2.device_id IS NULL OR p1.device_id < p2.device_id)
        GROUP BY n.port_id, n.remote_port_id
        ORDER BY d1.hostname, p1.ifIndex";

$links = dbFetchRows($sql, [$vlan_id, $vlan_id]);

if (count($links) > 0) {
    // Row 1: Diagram
    echo '<div class="row">';
    echo '<div class="col-md-12">';
    echo generate_box_open(['header-border' => TRUE]);
    echo '<div class="box-header with-border">';
    echo '<h3 class="box-title"><i class="fa fa-sitemap"></i> Network Topology for VLAN ' . $vlan_id . '</h3>';
    echo '<div class="box-tools pull-right">';
    echo '<div class="topology-legend">';
    echo '<span class="legend-item"><span class="legend-shape legend-diamond" style="background-color: var(--body-bg); border: 2px solid var(--text-color);"></span> Router</span>';
    echo '<span class="legend-item"><span class="legend-shape legend-rectangle" style="background-color: var(--body-bg); border: 2px solid var(--text-color);"></span> Switch</span>';
    echo '<span class="legend-item"><span class="legend-shape legend-round-rectangle" style="background-color: var(--body-bg); border: 2px solid var(--text-color);"></span> Network</span>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Build device and connection data for Cytoscape.js
    $cy_elements = [];
    $processed_devices = [];
    $device_pairs = []; // Group links by device pairs
    
    // First pass: collect all nodes and group links by device pairs
    foreach ($links as $link) {
        // Add local device node with VLAN-specific data
        if (!isset($processed_devices[$link['local_device_id']])) {
            // Count ports on this VLAN for this device
            $vlan_port_count = 0;
            $vlan_trunk_count = 0;
            $vlan_access_count = 0;
            foreach ($links as $l) {
                if ($l['local_device_id'] == $link['local_device_id'] || $l['remote_device_id'] == $link['local_device_id']) {
                    $vlan_port_count++;
                    // We could add more detailed port type analysis here if needed
                }
            }
            
            $cy_elements[] = [
                'group' => 'nodes',
                'data' => [
                    'id' => 'dev' . $link['local_device_id'],
                    'label' => short_hostname($link['local_hostname']),
                    'full_hostname' => $link['local_hostname'],
                    'device_id' => $link['local_device_id'],
                    'device_type' => $link['local_device_type'],
                    'vlan_ports' => $vlan_port_count,
                    'vlan_id' => $vlan_id
                ]
            ];
            $processed_devices[$link['local_device_id']] = true;
        }
        
        // Add remote device if it exists in our database
        if ($link['remote_device_id'] && !isset($processed_devices[$link['remote_device_id']])) {
            // Count ports on this VLAN for this device
            $vlan_port_count = 0;
            foreach ($links as $l) {
                if ($l['local_device_id'] == $link['remote_device_id'] || $l['remote_device_id'] == $link['remote_device_id']) {
                    $vlan_port_count++;
                }
            }
            
            $cy_elements[] = [
                'group' => 'nodes',
                'data' => [
                    'id' => 'dev' . $link['remote_device_id'],
                    'label' => short_hostname($link['remote_hostname']),
                    'full_hostname' => $link['remote_hostname'],
                    'device_id' => $link['remote_device_id'],
                    'device_type' => $link['remote_device_type'],
                    'vlan_ports' => $vlan_port_count,
                    'vlan_id' => $vlan_id
                ]
            ];
            $processed_devices[$link['remote_device_id']] = true;
        }
        
        // Group links by device pair, considering LAG aggregation
        if ($link['remote_device_id']) {
            // If both ports have the same parent port (LAG member), use LAG as identifier
            if (!empty($link['local_parent_port']) && !empty($link['remote_parent_port']) && 
                $link['local_parent_port'] == $link['remote_parent_port']) {
                // This is a LAG member - group by LAG interface name
                $pair_key = min($link['local_device_id'], $link['remote_device_id']) . '-' . max($link['local_device_id'], $link['remote_device_id']) . '-LAG-' . $link['local_parent_port'];
            } else {
                // Regular link grouping
                $pair_key = min($link['local_device_id'], $link['remote_device_id']) . '-' . max($link['local_device_id'], $link['remote_device_id']);
            }
            
            if (!isset($device_pairs[$pair_key])) {
                $is_lag = !empty($link['local_parent_port']) && !empty($link['remote_parent_port']) && 
                         $link['local_parent_port'] == $link['remote_parent_port'];
                
                $device_pairs[$pair_key] = [
                    'device1_id' => min($link['local_device_id'], $link['remote_device_id']),
                    'device2_id' => max($link['local_device_id'], $link['remote_device_id']),
                    'device1_name' => $link['local_device_id'] < $link['remote_device_id'] ? $link['local_hostname'] : $link['remote_hostname'],
                    'device2_name' => $link['local_device_id'] < $link['remote_device_id'] ? $link['remote_hostname'] : $link['local_hostname'],
                    'is_lag' => $is_lag,
                    'lag_name' => $is_lag ? $link['local_parent_port'] : null,
                    'links' => []
                ];
            }
            
            // Create normalized link data
            if ($link['local_device_id'] < $link['remote_device_id']) {
                $link_data = [
                    'port1' => $link['local_port'],
                    'port2' => $link['remote_port'],
                    'speed' => $link['ifSpeed'],
                    'status' => $link['ifOperStatus'],
                    'in_rate' => $link['ifInOctets_rate'] ?? 0,
                    'out_rate' => $link['ifOutOctets_rate'] ?? 0,
                    'in_err_rate' => (int)($link['local_ifInErrors_rate'] ?? 0) + (int)($link['remote_ifInErrors_rate'] ?? 0),
                    'out_err_rate' => (int)($link['local_ifOutErrors_rate'] ?? 0) + (int)($link['remote_ifOutErrors_rate'] ?? 0),
                    'in_disc_rate' => (int)($link['local_ifInDiscards_rate'] ?? 0) + (int)($link['remote_ifInDiscards_rate'] ?? 0),
                    'out_disc_rate' => (int)($link['local_ifOutDiscards_rate'] ?? 0) + (int)($link['remote_ifOutDiscards_rate'] ?? 0),
                    'tag_local' => ($link['local_ifvlan'] == $vlan_id ? 'U' : ($link['pv1_vlan'] ? 'T' : '-')),
                    'tag_remote' => ($link['remote_ifvlan'] == $vlan_id ? 'U' : ($link['pv2_vlan'] ? 'T' : '-')),
                    'last_change_local' => $link['local_ifLastChange'] ?? null,
                    'last_change_remote' => $link['remote_ifLastChange'] ?? null
                ];
            } else {
                $link_data = [
                    'port1' => $link['remote_port'],
                    'port2' => $link['local_port'],
                    'speed' => $link['ifSpeed'],
                    'status' => $link['ifOperStatus'],
                    'in_rate' => $link['ifInOctets_rate'] ?? 0,
                    'out_rate' => $link['ifOutOctets_rate'] ?? 0,
                    'in_err_rate' => (int)($link['local_ifInErrors_rate'] ?? 0) + (int)($link['remote_ifInErrors_rate'] ?? 0),
                    'out_err_rate' => (int)($link['local_ifOutErrors_rate'] ?? 0) + (int)($link['remote_ifOutErrors_rate'] ?? 0),
                    'in_disc_rate' => (int)($link['local_ifInDiscards_rate'] ?? 0) + (int)($link['remote_ifInDiscards_rate'] ?? 0),
                    'out_disc_rate' => (int)($link['local_ifOutDiscards_rate'] ?? 0) + (int)($link['remote_ifOutDiscards_rate'] ?? 0),
                    'tag_local' => ($link['remote_ifvlan'] == $vlan_id ? 'U' : ($link['pv2_vlan'] ? 'T' : '-')),
                    'tag_remote' => ($link['local_ifvlan'] == $vlan_id ? 'U' : ($link['pv1_vlan'] ? 'T' : '-')),
                    'last_change_local' => $link['remote_ifLastChange'] ?? null,
                    'last_change_remote' => $link['local_ifLastChange'] ?? null
                ];
            }
            
            $device_pairs[$pair_key]['links'][] = $link_data;
        }
    }
    
    // Second pass: create edges with detailed tooltips
    foreach ($device_pairs as $pair_key => $pair_data) {
        $links_count = count($pair_data['links']);
        $total_speed = 0; $up_links = 0; $down_links = 0; $max_speed = 0; $total_util = 0; $total_in_bps = 0; $total_out_bps = 0;
        $total_in_err = 0; $total_out_err = 0; $total_in_disc = 0; $total_out_disc = 0;
        $recent_change_age = null; $local_tag_summary = null; $remote_tag_summary = null;
        
        // Unified tooltip format for all link types
        $tooltip = '<div class="tooltip-header">';
        $tooltip .= '<strong>' . short_hostname($pair_data['device1_name']) . ' ↔ ' . short_hostname($pair_data['device2_name']) . '</strong>';
        if ($pair_data['is_lag']) {
            $tooltip .= ' <span class="label label-info">LAG</span>';
        }
        $tooltip .= ' <span class="text-muted">(' . $links_count . ' link' . ($links_count > 1 ? 's' : '') . ')</span>';
        $tooltip .= '</div>';
        $tooltip .= '<div class="tooltip-body">';
        
        if ($pair_data['is_lag']) {
            $tooltip .= '<div style="margin-bottom: 8px;">';
            $tooltip .= '<strong>🔗 ' . $pair_data['lag_name'] . '</strong> <span class="text-muted">(Link Aggregation)</span>';
            $tooltip .= '</div>';
        }
        
        // Port table for all scenarios
        $tooltip .= '<table class="table table-condensed" style="margin-bottom: 8px;">';
        $tooltip .= '<thead><tr><th>Local Port</th><th>Status</th><th>Remote Port</th><th>Speed</th><th>In</th><th>Out</th><th>Utl%</th><th>Tag</th></tr></thead><tbody>';
        
        // Calculate totals for all links first
        $speed_groups = [];
        foreach ($pair_data['links'] as $link) {
            $total_speed += $link['speed']; 
            $max_speed = max($max_speed, $link['speed']);
            if ($link['status'] == 'up') $up_links++; else $down_links++;
            
            $speed_key = $link['speed'];
            if (!isset($speed_groups[$speed_key])) $speed_groups[$speed_key] = 0;
            $speed_groups[$speed_key]++;
            
            $in_bps = (int) round(($link['in_rate'] ?? 0) * 8);
            $out_bps = (int) round(($link['out_rate'] ?? 0) * 8);
            $total_in_bps += $in_bps;
            $total_out_bps += $out_bps;
            $total_in_err += (int)($link['in_err_rate'] ?? 0);
            $total_out_err += (int)($link['out_err_rate'] ?? 0);
            $total_in_disc += (int)($link['in_disc_rate'] ?? 0);
            $total_out_disc += (int)($link['out_disc_rate'] ?? 0);
            
            if ($link['speed'] > 0 && ($in_bps > 0 || $out_bps > 0)) {
                $util = round(($in_bps + $out_bps) / $link['speed'] * 100, 1);
                $total_util += $util;
            }

            // Track most recent change across members
            foreach (['last_change_local','last_change_remote'] as $lc_key) {
                if (!empty($link[$lc_key]) && $link[$lc_key] !== '0000-00-00 00:00:00') {
                    $age = get_time() - strtotime($link[$lc_key]);
                    if ($recent_change_age === null || $age < $recent_change_age) { $recent_change_age = $age; }
                }
            }
            // Summarize VLAN tagging across ends
            if ($link['tag_local'] === 'U') { $local_tag_summary = 'U'; }
            elseif ($link['tag_local'] === 'T' && $local_tag_summary !== 'U') { $local_tag_summary = 'T'; }
            if ($link['tag_remote'] === 'U') { $remote_tag_summary = 'U'; }
            elseif ($link['tag_remote'] === 'T' && $remote_tag_summary !== 'U') { $remote_tag_summary = 'T'; }
        }
        
        // Add port details table (for 1-12 links) or summary (for 13+ links)
        if ($links_count <= 12) {
            // Show individual port details in table format
            foreach ($pair_data['links'] as $link) {
                $status_class = ($link['status'] == 'up') ? 'label-success' : 'label-danger';
                $status_icon = '●';
                $speed_label = format_bps($link['speed']);
                $in_bps = (int) round(($link['in_rate'] ?? 0) * 8);
                $out_bps = (int) round(($link['out_rate'] ?? 0) * 8);
                $in_label = format_bps($in_bps);
                $out_label = format_bps($out_bps);
                $util = ($link['speed'] > 0) ? round(($in_bps + $out_bps) / $link['speed'] * 100, 1) : 0;
                $tag_display = (($link['tag_local'] ?? '-') . '/' . ($link['tag_remote'] ?? '-'));
                $err_total = (int)($link['in_err_rate'] ?? 0) + (int)($link['out_err_rate'] ?? 0);
                $disc_total = (int)($link['in_disc_rate'] ?? 0) + (int)($link['out_disc_rate'] ?? 0);
                
                $tooltip .= '<tr>';
                $tooltip .= '<td><span class="entity-name">' . $link['port1'] . '</span></td>';
                $tooltip .= '<td><span class="label ' . $status_class . '">' . $status_icon . '</span></td>';
                $tooltip .= '<td><span class="entity-name">' . $link['port2'] . '</span></td>';
                $tooltip .= '<td>' . $speed_label . '</td>';
                $tooltip .= '<td><span class="text-info">' . $in_label . '</span></td>';
                $tooltip .= '<td><span class="text-success">' . $out_label . '</span></td>';
                $tooltip .= '<td>' . ($util > 0 ? $util . '%' : '-') . '</td>';
                $tooltip .= '<td>' . $tag_display . '</td>';
                $tooltip .= '</tr>';
                if ($err_total > 0 || $disc_total > 0) {
                    $tooltip .= '<tr><td></td><td colspan="5" class="text-muted" style="font-size: 90%;">';
                    if ($err_total > 0) {
                        $tooltip .= '<strong>Errors:</strong> ' . format_number((int)($link['in_err_rate'] ?? 0)) . '/s in, ' . format_number((int)($link['out_err_rate'] ?? 0)) . '/s out';
                    }
                    if ($disc_total > 0) {
                        if ($err_total > 0) { $tooltip .= ' • '; }
                        $tooltip .= '<strong>Discards:</strong> ' . format_number((int)($link['in_disc_rate'] ?? 0)) . '/s in, ' . format_number((int)($link['out_disc_rate'] ?? 0)) . '/s out';
                    }
                    $tooltip .= '</td><td colspan="2"></td></tr>';
                }
            }
        } else {
            // For many links, show configuration summary instead of individual ports
            $tooltip .= '<tr><td colspan="8" style="text-align: center; padding: 12px;">';
            
            // Speed configuration
            $speed_parts = [];
            foreach ($speed_groups as $speed => $count) { 
                $speed_parts[] = ($count > 1) ? '<span class="text-info">' . $count . 'x</span>' . format_bps($speed) : format_bps($speed); 
            }
            $tooltip .= '<strong>Configuration:</strong> ' . implode(' + ', $speed_parts) . '<br>';
            
            // Port status summary  
            if ($up_links > 0 && $down_links > 0) {
                $tooltip .= '<span class="text-success">' . $up_links . ' Up</span> / <span class="text-danger">' . $down_links . ' Down</span>';
            } else if ($up_links > 0) {
                $tooltip .= '<span class="text-success">All ' . $up_links . ' Up</span>';
            } else {
                $tooltip .= '<span class="text-danger">All ' . $down_links . ' Down</span>';
            }
            // Total traffic summary
            $tooltip .= '<br><strong>Traffic:</strong> <span class="text-info">In ' . format_bps($total_in_bps) . '</span> / <span class="text-success">Out ' . format_bps($total_out_bps) . '</span>';
            // Tag summary if known
            if ($local_tag_summary || $remote_tag_summary) {
                $tooltip .= '<br><strong>VLAN ' . $vlan_id . ':</strong> ' . (($local_tag_summary ?? '-') . '/' . ($remote_tag_summary ?? '-'));
            }
            
            $tooltip .= '</td></tr>';
        }
        
        $tooltip .= '</tbody></table>';
        
        // Summary statistics table for all scenarios
        $tooltip .= '<table class="table table-condensed" style="margin-top: 8px; border-top: 1px solid var(--box-border-color);">';
        $tooltip .= '<tr><td><strong>Total Speed:</strong></td><td>' . format_bps($total_speed) . '</td></tr>';
        $tooltip .= '<tr><td><strong>Active Links:</strong></td><td><span class="text-success">' . $up_links . '</span>/<span class="text-muted">' . $links_count . '</span></td></tr>';
        $tooltip .= '<tr><td><strong>Total In:</strong></td><td><span class="text-info">' . format_bps($total_in_bps) . '</span></td></tr>';
        $tooltip .= '<tr><td><strong>Total Out:</strong></td><td><span class="text-success">' . format_bps($total_out_bps) . '</span></td></tr>';
        if (($total_in_err + $total_out_err) > 0) {
            $tooltip .= '<tr><td><strong>Errors:</strong></td><td>' . format_number($total_in_err) . '/s in, ' . format_number($total_out_err) . '/s out</td></tr>';
        }
        if (($total_in_disc + $total_out_disc) > 0) {
            $tooltip .= '<tr><td><strong>Discards:</strong></td><td>' . format_number($total_in_disc) . '/s in, ' . format_number($total_out_disc) . '/s out</td></tr>';
        }
        if ($recent_change_age !== null) {
            $tooltip .= '<tr><td><strong>Last Flap:</strong></td><td>' . format_uptime($recent_change_age) . ' ago</td></tr>';
        }
        if ($local_tag_summary || $remote_tag_summary) {
            $tooltip .= '<tr><td><strong>VLAN ' . $vlan_id . ':</strong></td><td>' . (($local_tag_summary ?? '-') . '/' . ($remote_tag_summary ?? '-')) . ' <span class="text-muted">(Local/Remote: U=untagged, T=tagged)</span></td></tr>';
        }
        if ($total_util > 0) {
            $avg_util = round($total_util / $links_count, 1);
            $util_class = percent_class($avg_util);
            $tooltip .= '<tr><td><strong>Avg Utilization:</strong></td><td><span class="label label-' . $util_class . '">' . $avg_util . '%</span></td></tr>';
        }
        if ($total_speed > 0 && ($total_in_bps + $total_out_bps) > 0) {
            $bundle_util = round((($total_in_bps + $total_out_bps) / $total_speed) * 100, 1);
            $bu_class = percent_class($bundle_util);
            $tooltip .= '<tr><td><strong>Total Utilization:</strong></td><td><span class="label label-' . $bu_class . '">' . $bundle_util . '%</span></td></tr>';
        }
        $tooltip .= '</table>';
        $tooltip .= '</div>';
        
        $primary_speed_label = ($links_count > 1) ? $links_count . "x" . format_bps($max_speed) . " (" . format_bps($total_speed) . ")" : format_bps($max_speed);
        if ($up_links == $links_count) $status_color = 'success'; elseif ($up_links == 0) $status_color = 'danger'; else $status_color = 'warning';
        $edge_width = ($total_speed >= 10000000000) ? 6 : (($total_speed >= 1000000000) ? 4 : 2);
        if ($links_count > 1) $edge_width += 1;
        
        $cy_elements[] = [
            'group' => 'edges',
            'data' => ['id' => $pair_key, 'source' => 'dev' . $pair_data['device1_id'], 'target' => 'dev' . $pair_data['device2_id'], 'label' => $primary_speed_label, 'tooltip' => $tooltip, 'status' => $status_color, 'width' => $edge_width, 'dashes' => ($up_links > 0 && $down_links > 0)]
        ];
    }
    
    $device_count = count($processed_devices);
    
    if ($device_count > 20) {
        echo '<div class="callout callout-warning"><h5><i class="fa fa-exclamation-triangle"></i> Large Network</h5><p>This VLAN spans <strong>' . $device_count . ' devices</strong>. Network diagram is disabled for networks with more than 20 devices to maintain performance.</p></div>';
    } else {
        echo '<style>';
        echo '.cy-tooltip { background: var(--table-bg-accent); border: 1px solid var(--box-border-color); border-radius: 4px; padding: 0; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.3); max-width: 560px; font-size: 12px; }';
        echo '.cy-tooltip .tooltip-header { padding: 8px 12px; border-bottom: 1px solid var(--box-border-color); margin-bottom: 0; }';
        echo '.cy-tooltip .tooltip-body { padding: 8px 12px; overflow-x: auto; }';
        echo '.cy-tooltip .port-row { margin: 3px 0; }';
        echo '.cy-tooltip table.table-condensed { margin: 0; white-space: nowrap; }';
        echo '.cy-tooltip table.table-condensed td { padding: 2px 8px; border: none; }';
        echo '.topology-legend { display: flex; gap: 15px; font-size: 11px; }';
        echo '.legend-item { display: flex; align-items: center; gap: 5px; }';
        echo '.legend-shape { width: 12px; height: 12px; border: 1px solid var(--primary); display: inline-block; }';
        echo '.legend-diamond { transform: rotate(45deg); }';
        echo '.legend-rectangle { border-radius: 0; }';
        echo '.legend-round-rectangle { border-radius: 3px; }';
        echo '</style>';
        echo '<div class="network-diagram-container" style="position: relative; background: var(--table-bg-accent); padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        echo '<div id="cy-vlan-topology-' . $vlan_id . '" style="width: 100%; height: 500px; border: 1px solid var(--box-border-color); background: var(--table-bg-accent);"></div>';
        echo '</div>';

        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/cytoscape/3.23.0/cytoscape.min.js"></script>';
        echo '<script src="https://unpkg.com/@popperjs/core@2"></script>';
        echo '<script src="https://unpkg.com/cytoscape-popper@2.0.0/cytoscape-popper.js"></script>';
        
        echo '<script type="text/javascript">';
        echo 'document.addEventListener("DOMContentLoaded", function() {';
        echo '  const rootStyle = getComputedStyle(document.documentElement);';
        echo '  const textColor = rootStyle.getPropertyValue("--text-color").trim();';
        echo '  const primaryColor = rootStyle.getPropertyValue("--primary").trim();';
        echo '  const infoColor = rootStyle.getPropertyValue("--info").trim();';
        echo '  const successColor = rootStyle.getPropertyValue("--success").trim();';
        echo '  const dangerColor = rootStyle.getPropertyValue("--danger").trim();';
        echo '  const warningColor = rootStyle.getPropertyValue("--warning").trim();';
        echo '  const bodyBg = rootStyle.getPropertyValue("--body-bg").trim() || "white";';
        echo '  var elements = ' . json_encode($cy_elements) . ';';
        
        echo '  var cy = cytoscape({ container: document.getElementById("cy-vlan-topology-' . $vlan_id . '"), elements: elements, style: [ { selector: "node", style: { "background-color": bodyBg, "border-color": textColor, "border-width": 2, "label": "data(label)", "color": textColor, "font-size": "12px", "text-valign": "center", "text-halign": "center", "shape": "round-rectangle", "width": "label", "height": "label", "padding": "12px" } }, { selector: "node[device_type=\'router\']", style: { "shape": "diamond", "padding": "15px" } }, { selector: "node[device_type=\'switch\']", style: { "shape": "rectangle", "padding": "10px" } }, { selector: "node[device_type=\'network\']", style: { "shape": "round-rectangle", "padding": "12px" } }, { selector: "edge", style: { "width": "data(width)", "curve-style": "bezier", "target-arrow-shape": "none", "label": "data(label)", "color": textColor, "font-size": "10px", "text-background-color": bodyBg, "text-background-opacity": 1, "text-background-padding": "2px", "line-color": successColor } }, { selector: "edge[dashes]", style: { "line-style": "dashed" } }, { selector: "edge[status=\'success\']", style: { "line-color": successColor } }, { selector: "edge[status=\'warning\']", style: { "line-color": warningColor } }, { selector: "edge[status=\'danger\']", style: { "line-color": dangerColor } }, { selector: "node:selected", style: { "background-color": primaryColor, "border-color": primaryColor, "border-width": 3, "color": "white" } } ], layout: { name: "cose", idealEdgeLength: 120, nodeOverlap: 10, refresh: 20, fit: true, padding: 20, randomize: false, componentSpacing: 80, nodeRepulsion: 300000, edgeElasticity: 200, nestingFactor: 1.2, gravity: 40, numIter: 1500, initialTemp: 300, coolingFactor: 0.92, minTemp: 1.0, avoidOverlap: true, unconstrIter: 30, userConstIter: 10, allConstIter: 10 } });';
        
        echo '  '; 
        echo '  cy.ready(function() { ';
        echo '    var components = cy.elements().components(); ';
        echo '    if (components.length > 1) { ';
        echo '      var mainComponent = components.reduce((a, b) => a.length > b.length ? a : b); ';
        echo '      var sideComponents = components.filter(c => c !== mainComponent); ';
        echo '      var containerWidth = cy.width(); ';
        echo '      var containerHeight = cy.height(); ';
        echo '      setTimeout(function() { ';
        echo '        var padding = 40; ';
        echo '        mainComponent.layout({ ';
        echo '          name: "cose", ';
        echo '          fit: false, ';
        echo '          boundingBox: { x1: padding, y1: padding, x2: containerWidth * 0.75 - padding, y2: containerHeight - padding }, ';
        echo '          idealEdgeLength: 120, ';
        echo '          nodeRepulsion: 300000, ';
        echo '          gravity: 60, ';
        echo '          numIter: 800 ';
        echo '        }).run(); ';
        echo '        var sideX = containerWidth * 0.78; ';
        echo '        var sideY = padding; ';
        echo '        sideComponents.forEach((component, index) => { ';
        echo '          var bbox = { x1: sideX, y1: sideY, x2: containerWidth - padding, y2: sideY + 120 }; ';
        echo '          component.layout({ ';
        echo '            name: "grid", ';
        echo '            fit: false, ';
        echo '            boundingBox: bbox, ';
        echo '            rows: Math.ceil(Math.sqrt(component.length)), ';
        echo '            cols: Math.ceil(Math.sqrt(component.length)) ';
        echo '          }).run(); ';
        echo '          sideY += 140; ';
        echo '        }); ';
        echo '        setTimeout(function() { cy.center(); cy.fit(cy.elements(), 30); }, 300); ';
        echo '      }, 100); ';
        echo '    } ';
        echo '  }); ';
        echo '  ';
        echo '  var popper; var tooltipEl = null; ';
        echo '  cy.on("mouseover", "node, edge", function(e) { ';
        echo '    if (popper) { popper.destroy(); popper = null; } ';
        echo '    if (tooltipEl) { tooltipEl.remove(); tooltipEl = null; } ';
        echo '    var ele = e.target; '; 
        echo '    popper = ele.popper({ ';
        echo '      content: function() { '; 
        echo '        var div = document.createElement("div"); '; 
        echo '        div.classList.add("cy-tooltip"); '; 
        echo '        var content = ele.isNode() ? ';
        echo '          "<div class=\\"tooltip-header\\"><strong>🖥️ " + ele.data("full_hostname") + "</strong></div>" + ';
        echo '          "<div class=\\"tooltip-body\\"><table class=\\"table table-condensed\\"><tr><td><strong>VLAN " + ele.data("vlan_id") + " Ports:</strong></td><td>" + ele.data("vlan_ports") + "</td></tr>" + ';
        echo '          "<tr><td><strong>Role:</strong></td><td><span class=\\"text-info\\">Network Device</span></td></tr></table>" + ';
        echo '          "<small class=\\"text-muted\\">👆 Click to view device details</small></div>" ';
        echo '          : ele.data("tooltip"); '; 
        echo '        div.innerHTML = content.replace(/\n/g, "<br>"); ';
        echo '        document.body.appendChild(div); ';
        echo '        tooltipEl = div; ';
        echo '        return div; ';
        echo '      }, ';
        echo '      popper: { ';
        echo '        placement: "auto", ';
        echo '        modifiers: [ ';
        echo '          { name: "preventOverflow", options: { boundary: "viewport", padding: 8 } }, ';
        echo '          { name: "flip", options: { fallbackPlacements: ["top", "bottom", "right", "left"] } }, ';
        echo '          { name: "offset", options: { offset: [0, 8] } } ';
        echo '        ] ';
        echo '      } ';
        echo '    }); ';
        echo '  }); '; 

        echo '  cy.on("mouseout", "node, edge", function(e) { ';
        echo '    if (popper) { popper.destroy(); popper = null; } ';
        echo '    if (tooltipEl) { tooltipEl.remove(); tooltipEl = null; } ';
        echo '  }); ';

        echo '  cy.on("drag", "node", function(e) { ';
        echo '    if (popper) { popper.destroy(); popper = null; } ';
        echo '    if (tooltipEl) { tooltipEl.remove(); tooltipEl = null; } ';
        echo '  }); ';

        echo '  cy.on("tap", "node", function(evt){ ';
        echo '    if (popper) { popper.destroy(); popper = null; } ';
        echo '    if (tooltipEl) { tooltipEl.remove(); tooltipEl = null; } ';
        echo '    window.location.href = "/devices/device=" + evt.target.data("device_id") + "/tab=vlans/"; ';
        echo '  });';
        
        echo '});';
        echo '</script>';
        
        // Add legend
        echo '<div style="margin-top: 15px;">';
        echo '<div class="row text-center">';
        echo '<div class="col-md-2">';
        echo '<span class="text-success">● All Links Up</span>';
        echo '</div>';
        echo '<div class="col-md-2">';
        echo '<span class="text-warning">● Mixed Status</span>';  
        echo '</div>';
        echo '<div class="col-md-2">';
        echo '<span class="text-danger">● All Links Down</span>';
        echo '</div>';
        echo '<div class="col-md-3">';
        echo '<span class="text-muted">↔ Line thickness = Bandwidth</span>';
        echo '</div>';
        echo '<div class="col-md-3">';
        echo '<span class="text-muted">👆 Hover for details • Click to navigate</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="row text-center" style="margin-top: 8px;">';
        echo '<div class="col-md-6">';
        echo '<small class="text-muted">Link labels show: <code>2x10G (20G)</code> = Count × Speed (Total)</small>';
        echo '</div>';
        echo '<div class="col-md-6">';
        echo '<small class="text-muted">Dashed lines indicate mixed port status</small>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo generate_box_close();
    echo '</div></div>';

    // Row 2: Table of links
    echo '<div class="row">';
    echo '<div class="col-md-12">';
    echo generate_box_open(['header-border' => TRUE, 'title' => 'Link Details']);
    echo '<div class="table-responsive">';
    echo '<table class="table table-hover table-striped table-condensed">';
    echo '<thead><tr><th><i class="icon-server"></i> A-end Device</th><th><i class="icon-link"></i> A-end Port</th><th style="text-align: center;"><i class="icon-resize-small"></i> Link</th><th><i class="icon-server"></i> B-end Device</th><th><i class="icon-link"></i> B-end Port</th><th><i class="icon-dashboard"></i> Speed</th><th><i class="icon-signal"></i> Utilization</th></tr></thead>';
    echo '<tbody>';
    foreach ($links as $link) {
        // Determine link status: both ends must be up for link to be up
        // If remote port doesn't exist in DB, we can only show local status
        $local_up = ($link['ifOperStatus'] == 'up');
        $remote_exists = !empty($link['remote_device_id']);
        $remote_up = $remote_exists && ($link['remote_ifOperStatus'] == 'up');

        echo '<tr>';
        echo '<td><span class="entity-name">' . generate_device_link($link, short_hostname($link['local_hostname'])) . '</span></td>';
        echo '<td><span class="entity-name">' . generate_port_link($link, $link['local_port']) . '</span></td>';
        echo '<td style="text-align: center; vertical-align: middle;">';
        if (!$remote_exists) {
            // B-end not in DB, show A-end status only
            if ($local_up) {
                echo '<span class="label label-info" title="A-end Up (B-end unknown)"><i class="icon-random"></i></span>';
            } else {
                echo '<span class="label label-default" title="A-end Down (B-end unknown)"><i class="icon-random"></i></span>';
            }
        } elseif ($local_up && $remote_up) {
            echo '<span class="label label-success" title="Link Up (Both ends up)"><i class="icon-resize-small"></i></span>';
        } elseif ($local_up || $remote_up) {
            echo '<span class="label label-warning" title="Link Partial (One end down)"><i class="icon-warning-sign"></i></span>';
        } else {
            echo '<span class="label label-danger" title="Link Down (Both ends down)"><i class="icon-remove"></i></span>';
        }
        echo '</td>';
        echo '<td>';
        if ($link['remote_device_id']) { echo '<span class="entity-name">' . generate_device_link(['device_id' => $link['remote_device_id'], 'hostname' => $link['remote_hostname']], short_hostname($link['remote_hostname'])) . '</span>'; } else { echo '<span class="text-muted">' . escape_html($link['remote_hostname']) . '</span>'; }
        echo '</td>';
        echo '<td>';
        if ($link['remote_device_id']) { echo '<span class="entity-name">' . generate_port_link(['device_id' => $link['remote_device_id'], 'port_id' => $link['remote_port_id'], 'port_label_short' => $link['remote_port']], $link['remote_port']) . '</span>'; } else { echo '<span class="text-muted">' . escape_html($link['remote_port']) . '</span>'; }
        echo '</td>';
        echo '<td>';
        if ($link['ifSpeed'] > 0) { echo '<strong>' . format_bps($link['ifSpeed']) . '</strong>'; } else { echo '<span class="text-muted">Unknown</span>'; }
        echo '</td>';
        echo '<td>';
        if (isset($link['ifSpeed']) && $link['ifSpeed'] > 0) {
            $in_rate = $link['ifInOctets_rate'] ?? 0; $out_rate = $link['ifOutOctets_rate'] ?? 0;
            $util = round(($in_rate + $out_rate) * 8 / $link['ifSpeed'] * 100);
            echo '<span class="label label-' . percent_class($util) . '"><i class="fa fa-bar-chart"></i> ' . $util . '%</span>';
        } else { echo '<span class="text-muted">-</span>'; }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    echo generate_box_close();
    echo '</div></div>';

    // Row 3: STP and VLAN Info
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    $stp_sql = "SELECT * FROM `stp` WHERE `vlan` = ? ORDER BY `priority`";
    $stp_info = dbFetchRows($stp_sql, [$vlan_id]);
    if (count($stp_info) > 0) {
        echo generate_box_open(['header-border' => TRUE, 'title' => '<i class="fa fa-tree"></i> Spanning Tree']);
        echo '<div class="table-responsive"><table class="table table-condensed">';
        foreach ($stp_info as $stp) {
            $device = device_by_id_cache($stp['device_id']);
            echo '<tr><td colspan="2"><strong>' . generate_device_link($device) . '</strong>';
            if ($stp['root_bridge'] == 1) echo ' <span class="label label-success pull-right">Root Bridge</span>';
            echo '</td></tr>';
            echo '<tr><td><small class="text-muted">Priority:</small></td><td><code>' . $stp['priority'] . '</code></td></tr>';
            echo '<tr><td><small class="text-muted">Bridge Address:</small></td><td><code>' . format_mac($stp['bridge_address']) . '</code></td></tr>';
            if ($stp['root_bridge'] != 1) {
                echo '<tr><td><small class="text-muted">Root Bridge:</small></td><td><code>' . format_mac($stp['designated_root']) . '</code></td></tr>';
                echo '<tr><td><small class="text-muted">Path Cost:</small></td><td>' . $stp['root_path_cost'] . '</td></tr>';
            }
            echo '<tr><td><small class="text-muted">Topology Changes:</small></td><td>' . $stp['topology_change_count'] . '</td></tr>';
            if (count($stp_info) > 1) echo '<tr><td colspan="2"><hr style="margin: 10px 0;"></td></tr>';
        }
        echo '</table></div>';
        echo generate_box_close();
    }
    echo '</div>';
    echo '<div class="col-md-6">';
    echo generate_box_open(['header-border' => TRUE, 'title' => '<i class="fa fa-info-circle"></i> VLAN Info']);
    echo '<table class="table table-condensed">';
    echo '<tr><td><strong>VLAN ID:</strong></td><td>' . $vlan_id . '</td></tr>';
    if (isset($vlan['vlan_name']) && $vlan['vlan_name']) echo '<tr><td><strong>Name:</strong></td><td>' . escape_html($vlan['vlan_name']) . '</td></tr>';
    if (isset($vlan['vlan_type']) && $vlan['vlan_type']) echo '<tr><td><strong>Type:</strong></td><td>' . $vlan['vlan_type'] . '</td></tr>';
    $port_count = dbFetchCell("SELECT COUNT(DISTINCT port_id) FROM `ports_vlans` WHERE `vlan` = ?", [$vlan_id]);
    if ($port_count > 0) echo '<tr><td><strong>Total Ports:</strong></td><td>' . $port_count . '</td></tr>';
    echo '</table>';
    echo generate_box_close();
    echo '</div></div>';

} else {
    echo '<div class="callout callout-info">';
    echo '<h4><i class="fa fa-info-circle"></i> No Topology Data</h4>';
    echo '<p>No trunk links carrying VLAN ' . $vlan_id . ' were found via CDP/LLDP discovery.</p>';
    echo '<p><small>This could mean the VLAN is not trunked between devices, or neighbor discovery is not enabled.</small></p>';
    echo '</div>';
}

?>
