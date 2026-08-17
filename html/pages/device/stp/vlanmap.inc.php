<?php
/**
 * Device STP VLAN Mapping View
 * 
 * Shows VLAN to STP instance mappings for MSTP and PVST variants
 * with enhanced filtering, search, and modern UI components
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

$device_id = $vars['device'] ?? null;
if (!$device_id) {
  echo '<div class="alert alert-danger">No device specified.</div>';
  return;
}

// Get filter parameters
$instance_filter = $vars['instance_type'] ?? '';
$search_filter = $vars['search'] ?? '';

// Build WHERE conditions
$where_conditions = ['m.device_id = ?'];
$params = [$device_id];

// Instance type filter
if (!empty($instance_filter)) {
  $where_conditions[] = 'i.type = ?';
  $params[] = $instance_filter;
}

// Search filter (search in VLAN ID, instance name, or instance key)
if (!empty($search_filter)) {
  $search = '%' . $search_filter . '%';
  $where_conditions[] = '(m.vlan_vlan LIKE ? OR i.name LIKE ? OR i.instance_key LIKE ?)';
  $params[] = $search;
  $params[] = $search;
  $params[] = $search;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get VLAN mapping data with enhanced query
$rows = dbFetchRows("
  SELECT m.vlan_vlan, i.stp_instance_id, i.instance_key, i.type, i.name, i.updated
  FROM stp_vlan_map m
  JOIN stp_instances i ON i.stp_instance_id = m.stp_instance_id
  $where_clause
  ORDER BY i.type, i.instance_key, m.vlan_vlan
", $params);

// Calculate summary statistics before filters for status panel
$summary_stats = dbFetchRow("
  SELECT 
    COUNT(DISTINCT i.stp_instance_id) AS total_instances,
    COUNT(DISTINCT m.vlan_vlan) AS total_vlans,
    SUM(CASE WHEN i.type = 'cist' THEN 1 ELSE 0 END) AS cist_instances,
    SUM(CASE WHEN i.type = 'msti' THEN 1 ELSE 0 END) AS msti_instances, 
    SUM(CASE WHEN i.type = 'pvst' THEN 1 ELSE 0 END) AS pvst_instances,
    MAX(i.updated) AS last_updated
  FROM stp_vlan_map m
  JOIN stp_instances i ON i.stp_instance_id = m.stp_instance_id
  WHERE m.device_id = ?
", [$device_id]);

// Create status panel
$status_boxes = [
  [
    'title' => 'Total Instances',
    'value' => (int)($summary_stats['total_instances'] ?? 0),
    'subtitle' => 'STP Instances'
  ],
  [
    'title' => 'Mapped VLANs',
    'value' => (int)($summary_stats['total_vlans'] ?? 0),
    'subtitle' => 'VLAN Assignments'
  ],
  [
    'title' => 'CIST Instances',
    'value' => ['text' => (int)($summary_stats['cist_instances'] ?? 0), 'class' => 'label-primary'],
    'subtitle' => 'Common Instance'
  ],
  [
    'title' => 'MSTI Instances',
    'value' => ['text' => (int)($summary_stats['msti_instances'] ?? 0), 'class' => 'label-info'],
    'subtitle' => 'Multiple Instances'
  ],
  [
    'title' => 'PVST Instances',
    'value' => ['text' => (int)($summary_stats['pvst_instances'] ?? 0), 'class' => 'label-success'],
    'subtitle' => 'Per-VLAN Instance'
  ],
  [
    'title' => 'Last Updated',
    'value' => $summary_stats['last_updated'] ? format_uptime(time() - strtotime($summary_stats['last_updated'])) . ' ago' : 'Never',
    'subtitle' => 'Data Freshness'
  ]
];

echo generate_status_panel($status_boxes);

// Filter form
$search_form = [
  [
    'type' => 'select',
    'name' => 'Instance Type',
    'id' => 'instance_type',
    'width' => '150px',
    'value' => $instance_filter,
    'values' => [
      '' => 'All Types',
      'cist' => 'CIST',
      'msti' => 'MSTI', 
      'pvst' => 'PVST'
    ]
  ],
  [
    'type' => 'text',
    'name' => 'Search',
    'id' => 'search',
    'width' => '200px',
    'placeholder' => 'VLAN, instance, name...',
    'value' => $search_filter
  ]
];

print_search($search_form, 'VLAN Mapping Filters', 'search', generate_url($vars));

if (empty($rows)) {
  $content = '<div class="box-state-title">No VLAN Mappings Found</div>';
  $content .= '<p class="box-state-description">';
  if ($instance_filter || $search_filter) {
    $content .= 'No VLAN mappings match the current filter criteria.';
  } else {
    $content .= 'This device has no STP VLAN mappings configured. This is normal for devices running standard STP/RSTP or devices without VLAN-aware STP variants.';
  }
  $content .= '</p>';

  echo generate_box_open();
  echo generate_box_state('info', $content, [
    'icon' => $config['icon']['info'],
    'size' => 'medium'
  ]);
  echo generate_box_close();
  return;
}

// Group VLANs by instance for display
$by_instance = [];
foreach ($rows as $row) {
  $instance_key = $row['type'] . '#' . $row['instance_key'];
  $by_instance[$instance_key]['stp_instance_id'] = $row['stp_instance_id'];
  $by_instance[$instance_key]['type'] = $row['type'];
  $by_instance[$instance_key]['instance_key'] = $row['instance_key'];
  $by_instance[$instance_key]['name'] = $row['name'];
  $by_instance[$instance_key]['updated'] = $row['updated'];
  $by_instance[$instance_key]['vlans'][] = (int)$row['vlan_vlan'];
}

echo generate_box_open(['title' => 'STP VLAN Mappings (' . count($by_instance) . ' instances)']);

echo '<table class="table table-striped table-hover table-condensed">';
echo '<thead><tr>';
echo '<th class="state-marker"></th>';
echo '<th>Instance</th>';
echo '<th>Name</th>';
echo '<th>Type</th>';
echo '<th>VLAN Ranges</th>';
echo '<th>VLAN Count</th>';
echo '<th>Updated</th>';
echo '</tr></thead><tbody>';

foreach ($by_instance as $instance_data) {
  $vlans = $instance_data['vlans'];
  sort($vlans);
  $vlan_count = count($vlans);

  // Compress consecutive VLAN ranges for display
  $ranges = [];
  $start = $prev = null;
  foreach ($vlans as $vlan) {
    if ($start === null) {
      $start = $prev = $vlan;
      continue;
    }
    if ($vlan === $prev + 1) {
      $prev = $vlan;
      continue;
    }
    // End of range, add it
    $ranges[] = ($start === $prev) ? "$start" : "$start-$prev";
    $start = $prev = $vlan;
  }
  // Add final range
  if ($start !== null) {
    $ranges[] = ($start === $prev) ? "$start" : "$start-$prev";
  }

  // Determine row class based on instance type and VLAN count
  $row_class = '';
  if ($vlan_count > 100) {
    $row_class = 'info'; // Large VLAN assignments
  } else {
    $row_class = 'ok'; // Normal assignment
  }

  // Color code by instance type
  $type_colors = [
    'msti' => 'info',
    'pvst' => 'success', 
    'cist' => 'primary'
  ];
  $type_class = $type_colors[strtolower($instance_data['type'])] ?? 'default';

  echo '<tr class="'.$row_class.'">';

  // State marker
  echo '<td class="state-marker"></td>';

  // Instance with link to instances page
  echo '<td class="entity-title">';
  $instance_url = generate_url(['page' => 'device', 'device' => $device_id, 'tab' => 'stp', 'section' => 'ports', 'instance_id' => $instance_data['stp_instance_id']]);
  echo '<a class="entity" href="' . $instance_url . '">';
  echo strtoupper($instance_data['type']) . ' ' . $instance_data['instance_key'];
  echo '</a>';
  echo '</td>';

  // Name
  echo '<td>';
  if (!empty($instance_data['name'])) {
    echo '<span class="text-muted">' . htmlentities($instance_data['name']) . '</span>';
  } else {
    echo '—';
  }
  echo '</td>';

  // Type
  echo '<td>';
  echo '<span class="label label-' . $type_class . '">' . strtoupper($instance_data['type']) . '</span>';
  echo '</td>';

  // VLAN Ranges (enhanced display)
  echo '<td>';
  if (count($ranges) <= 12) {
    // Show all ranges
    $range_html = [];
    foreach ($ranges as $range) {
      // Highlight single VLANs vs ranges differently
      if (strpos($range, '-') !== false) {
        $range_html[] = '<span class="label label-default">' . htmlentities($range) . '</span>';
      } else {
        $range_html[] = '<span class="label label-info">' . htmlentities($range) . '</span>';
      }
    }
    echo implode(' ', $range_html);
  } else {
    // Too many ranges, show compact view
    echo htmlentities(implode(', ', array_slice($ranges, 0, 8)));
    echo ' <small class="text-muted">... +' . (count($ranges) - 8) . ' more ranges</small>';
  }
  echo '</td>';

  // VLAN Count
  echo '<td>';
  if ($vlan_count > 50) {
    echo '<strong class="text-info">' . $vlan_count . '</strong>';
  } else {
    echo '<strong>' . $vlan_count . '</strong>';
  }
  echo '</td>';

  // Updated
  echo '<td><small>' . htmlentities($instance_data['updated']) . '</small></td>';

  echo '</tr>';
}

echo '</tbody></table>';

// Pagination support (if needed in future)
$filtered_count = count($by_instance);
if ($filtered_count > 50) {
  // Could add pagination here in future if needed
}

echo generate_box_close();

// Quick statistics summary (if filters applied)
if ($instance_filter || $search_filter) {
  $total_vlans = array_sum(array_map(function($inst) { return count($inst['vlans']); }, $by_instance));
  $instance_types = array_count_values(array_column($by_instance, 'type'));

  echo generate_box_open(['title' => 'Filter Results Summary']);
  echo '<div class="row" style="margin: 10px 0;">';
  echo '<div class="col-md-4"><strong>' . count($by_instance) . '</strong><br><small class="text-muted">Instances Found</small></div>';
  echo '<div class="col-md-4"><strong>' . $total_vlans . '</strong><br><small class="text-muted">VLANs Mapped</small></div>';
  echo '<div class="col-md-4">';

  $type_colors = ['msti' => 'info', 'pvst' => 'success', 'cist' => 'primary'];
  $type_labels = [];
  foreach ($instance_types as $type => $count) {
    $type_class = $type_colors[strtolower($type)] ?? 'default';
    $type_labels[] = '<span class="label label-' . $type_class . '">' . strtoupper($type) . ' ' . $count . '</span>';
  }
  echo implode(' ', $type_labels);
  echo '<br><small class="text-muted">Types Found</small>';
  echo '</div>';
  echo '</div>';
  echo generate_box_close();
}

// EOF