<?php
/**
 * STP Instances Global View
 * 
 * Shows all STP instances (CIST/MSTI/PVST) across devices
 * with port state summaries and health indicators
 */

// Filter processing
$where_conditions = [];
$params = [];

// Type filter (CIST/MSTI/PVST) - $vars['type'] is already an array if multiselect
if (!empty($vars['type'])) {
  $types = is_array($vars['type']) ? $vars['type'] : [$vars['type']];
  $type_placeholders = str_repeat('?,', count($types) - 1) . '?';
  $where_conditions[] = "i.type IN ($type_placeholders)";
  $params = array_merge($params, $types);
}

// Instance key filter (for MSTI/PVST instance numbers)
if (!empty($vars['instance_key'])) {
  $where_conditions[] = "i.instance_key = ?";
  $params[] = (int)$vars['instance_key'];
}

// Variant filter (via bridge join) - $vars['variant'] is already an array if multiselect
if (!empty($vars['variant'])) {
  $variants = is_array($vars['variant']) ? $vars['variant'] : [$vars['variant']];
  $variant_placeholders = str_repeat('?,', count($variants) - 1) . '?';
  $where_conditions[] = "sb.variant IN ($variant_placeholders)";
  $params = array_merge($params, $variants);
}

// Location filter
if (!empty($vars['location'])) {
  $where_conditions[] = "d.location LIKE ?";
  $params[] = '%' . $vars['location'] . '%';
}

// Search filter
if (!empty($vars['search'])) {
  $search = '%' . $vars['search'] . '%';
  $where_conditions[] = "(d.hostname LIKE ? OR i.instance_key LIKE ?)";
  $params[] = $search;
  $params[] = $search;
}

// Build WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
  $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Main query - get instances with port state aggregates and flap data
$sql = "
  SELECT d.hostname, i.device_id, i.stp_instance_id, i.type, i.instance_key, i.updated,
         sb.variant, COALESCE(sb.mst_region_name,'') AS region, COALESCE(sb.designated_root,'') AS root_hex, sb.domain_hash,
         SUM(CASE WHEN p.state='forwarding' THEN 1 ELSE 0 END) AS fwd,
         SUM(CASE WHEN p.state IN ('blocking','discarding','broken') THEN 1 ELSE 0 END) AS bad,
         SUM(CASE WHEN p.inconsistent=1 THEN 1 ELSE 0 END) AS incons,
         SUM(CASE WHEN p.transitions_5m=1 THEN 1 ELSE 0 END) AS flaps_5m,
         SUM(COALESCE(p.transitions_60m,0)) AS flaps_60m,
         COUNT(p.port_id) AS total_ports
  FROM stp_instances i
  JOIN devices d ON d.device_id = i.device_id
  JOIN stp_bridge sb ON sb.device_id = i.device_id
  LEFT JOIN stp_ports p ON p.stp_instance_id = i.stp_instance_id
  $where_clause
  GROUP BY i.stp_instance_id
  ORDER BY i.type, i.instance_key, d.hostname";

// Pagination - use Observium standard variable names
$page_size = (int)($vars['pagesize'] ?? 100);
$page = (int)($vars['pageno'] ?? 1);
$offset = ($page - 1) * $page_size;

$instances = dbFetchRows($sql . " LIMIT $offset, $page_size", $params);
$total_count = dbFetchCell("SELECT COUNT(*) FROM ($sql) AS total_instances", $params);

// Filter form using Observium's search pattern
$search_form = [
  [
    'type' => 'multiselect',
    'name' => 'Type',
    'id' => 'type',
    'width' => '120px',
    'value' => $vars['type'] ?? '',
    'values' => ['cist' => 'CIST', 'msti' => 'MSTI', 'pvst' => 'PVST']
  ],
  [
    'type' => 'multiselect',
    'name' => 'Variant',
    'id' => 'variant',
    'width' => '150px',
    'value' => $vars['variant'] ?? '',
    'values' => ['stp' => 'STP', 'rstp' => 'RSTP', 'mstp' => 'MSTP', 'pvst' => 'PVST', 'rpvst' => 'RPVST']
  ],
  [
    'type' => 'text',
    'name' => 'Instance Key',
    'id' => 'instance_key',
    'width' => '100px',
    'placeholder' => 'MSTI/VLAN...',
    'value' => $vars['instance_key'] ?? ''
  ],
  [
    'type' => 'text',
    'name' => 'Location',
    'id' => 'location',
    'width' => '150px',
    'placeholder' => 'Location filter...',
    'value' => $vars['location'] ?? ''
  ],
  [
    'type' => 'text',
    'name' => 'Search',
    'id' => 'search',
    'width' => '200px',
    'placeholder' => 'Device or instance...',
    'value' => $vars['search'] ?? ''
  ]
];

print_search($search_form, 'Instance Filters', 'search', generate_url($vars));

// Summary statistics - get before pagination for accurate totals
$summary_stats = dbFetchRow("
  SELECT 
    COUNT(DISTINCT i.stp_instance_id) AS total_instances,
    COUNT(DISTINCT i.device_id) AS total_devices,
    SUM(CASE WHEN i.type = 'cist' THEN 1 ELSE 0 END) AS cist_instances,
    SUM(CASE WHEN i.type = 'msti' THEN 1 ELSE 0 END) AS msti_instances,
    SUM(CASE WHEN i.type = 'pvst' THEN 1 ELSE 0 END) AS pvst_instances,
    SUM(CASE WHEN p.state='forwarding' THEN 1 ELSE 0 END) AS total_forwarding,
    SUM(CASE WHEN p.state IN ('blocking','discarding','broken') THEN 1 ELSE 0 END) AS total_bad_ports,
    SUM(CASE WHEN p.inconsistent=1 THEN 1 ELSE 0 END) AS total_inconsistent,
    SUM(CASE WHEN p.transitions_5m=1 THEN 1 ELSE 0 END) AS total_flaps_5m,
    SUM(COALESCE(p.transitions_60m,0)) AS total_flaps_60m,
    COUNT(p.port_id) AS total_ports
  FROM stp_instances i
  JOIN devices d ON d.device_id = i.device_id
  JOIN stp_bridge sb ON sb.device_id = i.device_id
  LEFT JOIN stp_ports p ON p.stp_instance_id = i.stp_instance_id
  $where_clause
", $params);

// Create status panel with 6 boxes
$status_boxes = [
  [
    'title' => 'Total Instances',
    'value' => (int)($summary_stats['total_instances'] ?? 0),
    'subtitle' => 'All Instance Types'
  ],
  [
    'title' => 'Devices',
    'value' => (int)($summary_stats['total_devices'] ?? 0),
    'subtitle' => 'Running STP'
  ],
  [
    'title' => 'CIST Instances',
    'value' => ['text' => (int)($summary_stats['cist_instances'] ?? 0), 'class' => 'label-primary'],
    'subtitle' => 'Common Spanning Tree'
  ],
  [
    'title' => 'MSTI Instances', 
    'value' => ['text' => (int)($summary_stats['msti_instances'] ?? 0), 'class' => 'label-info'],
    'subtitle' => 'Multiple Spanning Tree'
  ],
  [
    'title' => 'PVST Instances',
    'value' => ['text' => (int)($summary_stats['pvst_instances'] ?? 0), 'class' => 'label-success'],
    'subtitle' => 'Per-VLAN Spanning Tree'
  ],
  [
    'title' => 'Port Stability',
    'value' => function() use ($summary_stats) {
      $flaps_5m = (int)($summary_stats['total_flaps_5m'] ?? 0);
      $flaps_60m = (int)($summary_stats['total_flaps_60m'] ?? 0);
      $total_ports = (int)($summary_stats['total_ports'] ?? 0);

      // Calculate flap percentage for display
      if ($total_ports > 0) {
        $flap_pct_5m = ($flaps_5m / $total_ports) * 100;

        if ($flap_pct_5m >= 5.0) {
          return ['text' => $flaps_5m, 'class' => 'label-danger'];
        } elseif ($flap_pct_5m >= 2.0) {
          return ['text' => $flaps_5m, 'class' => 'label-warning'];
        } else {
          return ['text' => $flaps_5m, 'class' => 'label-success'];
        }
      } else {
        return ['text' => '0', 'class' => 'label-default'];
      }
    },
    'subtitle' => function() use ($summary_stats) {
      $flaps_5m = (int)($summary_stats['total_flaps_5m'] ?? 0);
      $total_ports = (int)($summary_stats['total_ports'] ?? 0);

      if ($total_ports > 0) {
        $flap_pct_5m = ($flaps_5m / $total_ports) * 100;
        return sprintf('%.1f%% Flapped (5m)', $flap_pct_5m);
      } else {
        return 'No Ports';
      }
    }
  ]
];

// Execute function values
foreach ($status_boxes as &$box) {
  if (is_callable($box['value'])) {
    $box['value'] = $box['value']();
  }
  if (is_callable($box['subtitle'])) {
    $box['subtitle'] = $box['subtitle']();
  }
}

echo generate_status_panel($status_boxes);

// Results table
echo generate_box_open(['title' => "STP Instances ($total_count total)"]);

if (empty($instances)) {
  $content = '<div class="box-state-title">No STP Instances Found</div>';
  $content .= '<p class="box-state-description">No spanning tree instances match the current filter criteria.</p>';

  echo generate_box_state('info', $content, [
    'icon' => $config['icon']['info'],
    'size' => 'medium'
  ]);
} else {

  echo '<table class="table table-striped table-hover table-condensed">';
  echo '<thead><tr>';
  echo '<th class="state-marker"></th>';
  echo '<th>Device</th>';
  echo '<th>Instance</th>';
  echo '<th>Domain</th>';
  echo '<th>Total Ports</th>';
  echo '<th>Forwarding</th>';
  echo '<th>Bad States</th>';
  echo '<th>Inconsistent</th>';
  echo '<th>Flaps</th>';
  echo '<th>Health</th>';
  echo '<th>Updated</th>';
  echo '</tr></thead><tbody>';

  foreach ($instances as $instance) {
    // Use domain hash directly from database query
    $dom_hash = $instance['domain_hash'];
    $dom_link = generate_url([
      'page' => 'stp',
      'view' => 'domain',
      'domain_hash' => $dom_hash
    ]);

    // Format instance display
    $instance_display = strtoupper($instance['type']);
    if ($instance['instance_key'] !== null && $instance['instance_key'] !== '') {
      $instance_display .= ' ' . $instance['instance_key'];
    }

    // Calculate health score based on port states
    $total_ports = (int)$instance['total_ports'];
    $bad_ports = (int)$instance['bad'];
    $incons_ports = (int)$instance['incons'];

    $health_class = 'label-success';
    $health_text = 'Good';

    if ($total_ports > 0) {
      $bad_ratio = ($bad_ports + $incons_ports) / $total_ports;
      if ($bad_ratio > 0.3) {
        $health_class = 'label-danger';
        $health_text = 'Poor';
      } elseif ($bad_ratio > 0.1) {
        $health_class = 'label-warning';
        $health_text = 'Fair';
      }
    } elseif ($total_ports === 0) {
      $health_class = 'label-default';
      $health_text = 'No Ports';
    }

    // Determine row class based on instance health
    $row_class = '';
    if ($incons_ports > 0) {
      $row_class = 'error';
    } elseif ($bad_ports > 0) {
      $row_class = 'warning';
    } elseif ($total_ports > 0) {
      $bad_ratio = ($bad_ports + $incons_ports) / $total_ports;
      if ($bad_ratio > 0.3) {
        $row_class = 'error';
      } elseif ($bad_ratio > 0.1) {
        $row_class = 'warning';  
      } else {
        $row_class = 'ok';
      }
    } else {
      $row_class = 'disabled'; // No ports
    }

    echo '<tr class="'.$row_class.'">';

    // State marker
    echo '<td class="state-marker"></td>';

    // Device
    $device_url = generate_device_url(['device_id' => $instance['device_id'], 'hostname' => $instance['hostname']], ['tab' => 'stp']);
    echo '<td class="entity-title"><a class="entity" href="' . $device_url . '">' . htmlentities($instance['hostname']) . '</a></td>';

    // Instance
    echo '<td>';
    echo '<span class="label label-info">' . htmlentities($instance_display) . '</span>';
    echo '</td>';

    // Domain
    echo '<td><a class="entity" href="' . $dom_link . '">' . stp_format_domain_labels($instance['variant'], $instance['region'], $instance['root_hex']) . '</a></td>';

    // Total Ports
    echo '<td>' . $total_ports . '</td>';

    // Forwarding
    echo '<td>';
    if ($instance['fwd'] > 0) {
      echo '<span class="text-success">' . (int)$instance['fwd'] . '</span>';
    } else {
      echo (int)$instance['fwd'];
    }
    echo '</td>';

    // Bad States
    echo '<td>';
    if ($bad_ports > 0) {
      echo '<span class="text-danger">' . $bad_ports . '</span>';
    } else {
      echo $bad_ports;
    }
    echo '</td>';

    // Inconsistent
    echo '<td>';
    if ($incons_ports > 0) {
      echo '<span class="text-danger">' . $incons_ports . '</span>';
    } else {
      echo $incons_ports;
    }
    echo '</td>';

    // Flaps column - Instance-level dual badge system
    echo '<td>';
    $inst_5m  = (int)($instance['flaps_5m'] ?? 0);
    $inst_60m = (int)($instance['flaps_60m'] ?? 0);

    // Badge classes using same thresholds as device page
    $warn_60m = 3;
    $crit_60m = 6;

    $b5 = 'label ' . ($inst_5m > 0 ? 'label-danger' : 'label-default');

    if ($inst_60m >= $crit_60m)      { $b60 = 'label label-danger'; }
    else if ($inst_60m >= $warn_60m) { $b60 = 'label label-warning'; }
    else                             { $b60 = 'label label-success'; }

    // Tooltip
    $tt = [];
    $tt[] = $inst_5m > 0 ? 'Ports changed state in the last poll (5m).' : 'No changes this poll.';
    $tt[] = "Total port transitions in last hour: {$inst_60m}.";
    $tooltip = htmlspecialchars(implode(' ', $tt));

    echo '<span class="'.$b5.'" title="'.$tooltip.'">5m '.$inst_5m.'</span> ';
    echo '<span class="'.$b60.'" title="'.$tooltip.'">60m '.$inst_60m.'</span>';
    echo '</td>';

    // Health
    echo '<td><span class="label ' . $health_class . '">' . $health_text . '</span></td>';

    // Updated
    echo '<td>' . htmlentities($instance['updated']) . '</td>';

    echo '</tr>';
  }

  echo '</tbody></table>';

  // Pagination
  if ($total_count > $page_size) {
    $vars['pageno'] = $page;
    $vars['pagesize'] = $page_size;
    echo pagination($vars, $total_count);
  }
}

echo generate_box_close();

// EOF
