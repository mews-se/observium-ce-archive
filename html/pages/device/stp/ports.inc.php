<?php
/**
 * Device STP Ports View
 *
 * Shows all STP ports for a specific device with filtering and state information
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

$device_id = $vars['device'] ?? null;
if (!$device_id) {
  echo '<div class="alert alert-danger">No device specified.</div>';
  return;
}

// Get filter parameters
$instance_filter = $vars['instance_id'] ?? '';
$state_filter = $vars['state'] ?? '';
$role_filter = $vars['role'] ?? '';
$flap_filter = $vars['flap'] ?? '';
$search_filter = $vars['search'] ?? '';

// Build WHERE conditions
$where_conditions = ['p.device_id = ?'];
$params = [$device_id];

// Instance filter
if (!empty($instance_filter) && is_numeric($instance_filter)) {
  $where_conditions[] = 'p.stp_instance_id = ?';
  $params[] = (int)$instance_filter;
}

// State filter
if (!empty($state_filter)) {
  $where_conditions[] = 'p.state = ?';
  $params[] = $state_filter;
}

// Role filter
if (!empty($role_filter)) {
  $where_conditions[] = 'p.role = ?';
  $params[] = $role_filter;
}

// Flap filter - Apply filter to query building for the table
if (!empty($flap_filter)) {
  switch ($flap_filter) {
    case '5m':
      $where_conditions[] = 'p.transitions_5m = 1';
      break;
    case '60m':
      $where_conditions[] = 'p.transitions_60m > 0';
      break;
    case '60m3':
      $where_conditions[] = 'p.transitions_60m >= 3';
      break;
  }
}

// Search filter
if (!empty($search_filter)) {
  $search = '%' . $search_filter . '%';
  $where_conditions[] = '(po.ifName LIKE ? OR po.ifDescr LIKE ?)';
  $params[] = $search;
  $params[] = $search;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Main query
$sql = "
  SELECT p.stp_port_id, p.port_id, p.stp_instance_id, p.base_port, p.state, p.role,
         p.admin_edge, p.oper_edge, p.point2point, p.path_cost, p.priority,
         p.designated_bridge, p.designated_port, p.forward_transitions,
         p.designated_root, p.inconsistent, p.guard, p.bounce_count,
         p.last_bounce, p.transitions_5m, p.transitions_60m, p.state_changed, p.updated,
         po.ifName, po.ifDescr, po.ifIndex, po.ifOperStatus, po.ifAdminStatus,
         i.type AS instance_type, i.instance_key
  FROM stp_ports p
  JOIN ports po ON po.port_id = p.port_id
  JOIN stp_instances i ON i.stp_instance_id = p.stp_instance_id
  $where_clause
  ORDER BY p.transitions_60m DESC, p.transitions_5m DESC, i.type, i.instance_key, po.ifIndex
";

// Pagination
$page_size = (int)($vars['pagesize'] ?? 50);
$page = (int)($vars['pageno'] ?? 1);
$offset = ($page - 1) * $page_size;

$ports = dbFetchRows($sql . " LIMIT $offset, $page_size", $params);
$total_count = dbFetchCell("SELECT COUNT(*) FROM ($sql) AS total_ports", $params);

// Get available instances for filter
$instances = dbFetchRows("
  SELECT stp_instance_id, type, instance_key
  FROM stp_instances
  WHERE device_id = ?
  ORDER BY type, instance_key
", [$device_id]);

// Filter form
$search_form = [
  [
    'type' => 'select',
    'name' => 'Instance',
    'id' => 'instance_id',
    'width' => '150px',
    'value' => $instance_filter,
    'values' => ['' => 'All Instances'] + array_column(array_map(function($i) {
      return [$i['stp_instance_id'], strtoupper($i['type']) . ' ' . $i['instance_key']];
    }, $instances), 1, 0)
  ],
  [
    'type' => 'select',
    'name' => 'State',
    'id' => 'state',
    'width' => '120px',
    'value' => $state_filter,
    'values' => [
      '' => 'All States',
      'forwarding' => 'Forwarding',
      'blocking' => 'Blocking',
      'discarding' => 'Discarding',
      'learning' => 'Learning',
      'listening' => 'Listening',
      'disabled' => 'Disabled',
      'broken' => 'Broken'
    ]
  ],
  [
    'type' => 'select',
    'name' => 'Role',
    'id' => 'role',
    'width' => '120px',
    'value' => $role_filter,
    'values' => [
      '' => 'All Roles',
      'root' => 'Root',
      'designated' => 'Designated',
      'alternate' => 'Alternate',
      'backup' => 'Backup',
      'disabled' => 'Disabled'
    ]
  ],
  [
    'type' => 'text',
    'name' => 'Search',
    'id' => 'search',
    'width' => '200px',
    'placeholder' => 'Port name search...',
    'value' => $search_filter
  ]
];

print_search($search_form, 'Port Filters', 'search', generate_url($vars));

// Navbar quick filters - Let operators jump straight to "only noisy ports"
$nav = array('options' => array());
$nav['class'] = 'navbar-narrow';

$active = $flap_filter;

$nav['options']['all']  = array('text' => 'All',  'url' => generate_url($vars, array('flap' => NULL)), 'active' => $active === '');
$nav['options']['5m']   = array('text' => 'Flapped (5m)',  'url' => generate_url($vars, array('flap' => '5m')),  'active' => $active === '5m');
$nav['options']['60m3'] = array('text' => '>=3 in 60m',    'url' => generate_url($vars, array('flap' => '60m3')), 'active' => $active === '60m3');

print_navbar($nav);

// Results table
echo generate_box_open(['title' => "STP Ports ($total_count total)"]);

if (empty($ports)) {
  // Check if this is due to PVST instances with no port data
  $pvst_instances = dbFetchCell("SELECT COUNT(*) FROM stp_instances WHERE device_id = ? AND type = 'pvst'", [$device_id]);
  $pvst_ports = dbFetchCell("SELECT COUNT(DISTINCT sp.stp_port_id) FROM stp_instances si
                             JOIN stp_ports sp ON si.stp_instance_id = sp.stp_instance_id
                             WHERE si.device_id = ? AND si.type = 'pvst'", [$device_id]);

  // Check if this is a PVST instance with no port data
  $is_pvst_instance = false;
  if (!empty($instance_filter) && is_numeric($instance_filter)) {
    $instance_type = dbFetchCell("SELECT type FROM stp_instances WHERE stp_instance_id = ? AND device_id = ?", [$instance_filter, $device_id]);
    $is_pvst_instance = ($instance_type === 'pvst');
  }

  if (($pvst_instances > 0 && $pvst_ports == 0 && empty($instance_filter)) || $is_pvst_instance) {
    // PVST configured but no port data available (either globally or for specific instance)
    $content = '<div class="box-state-title">STP Data Collection Issue</div>';
    $content .= '<p class="box-state-description">This device has PVST configured but per-VLAN STP port data is not available via SNMP. This is a known limitation with some Cisco devices where PVST instances are created but port state data cannot be collected.</p>';
    $content .= '<p class="box-state-description">Try viewing the CIST instance for basic STP information, or check the device configuration to ensure SNMP contexts are properly configured.</p>';
  } else {
    // Standard no results message
    $content = '<div class="box-state-title">No STP Ports Found</div>';
    $content .= '<p class="box-state-description">No STP ports match the current filter criteria.</p>';
  }

  echo generate_box_state('info', $content, [
    'icon' => $config['icon']['info'],
    'size' => 'medium'
  ]);
} else {
  echo '<table class="table table-striped table-hover table-condensed">';
  echo '<thead><tr>';
  echo '<th class="state-marker"></th>';
  echo '<th>Port</th>';
  echo '<th>Instance</th>';
  echo '<th>State</th>';
  echo '<th>Role</th>';
  echo '<th>Cost</th>';
  echo '<th>Priority</th>';
  echo '<th>Edge</th>';
  echo '<th>P2P</th>';
  echo '<th>Guard</th>';
  echo '<th>Inconsistent</th>';
  echo '<th>Flaps</th>';
  echo '<th>Last Change</th>';
  echo '<th>Updated</th>';
  echo '</tr></thead><tbody>';

  $warn_60m = 3;
  $crit_60m = 6;

  foreach ($ports as $port) {
    // Port name
    $port_name = $port['ifName'] ?: $port['ifDescr'];

    // Instance display
    $instance_display = strtoupper($port['instance_type']) . ' ' . $port['instance_key'];

    // Bounce tracking variables
    $t5  = (int)($port['transitions_5m'] ?? 0);     // 0/1
    $t60 = (int)($port['transitions_60m'] ?? 0);    // 0..n
    $chg = (int)($port['state_changed'] ?? 0);      // unix ts or 0
    $now = time();

    // Row emphasis if flapped this poll
    $row_class = ($t5 === 1) ? 'error' : ''; // Red highlighting for current flapping

    // State formatting using standardized labels

    // Role with color coding
    $role_class = '';
    switch ($port['role']) {
      case 'root': $role_class = 'text-primary'; break;
      case 'designated': $role_class = 'text-success'; break;
      case 'alternate':
      case 'backup': $role_class = 'text-warning'; break;
      case 'disabled': $role_class = 'text-muted'; break;
    }

    // Determine row class based on port health
    $row_class = '';
    if ($port['inconsistent'] == 1) {
      $row_class = 'error';
    } elseif (in_array($port['state'], ['broken', 'discarding'])) {
      $row_class = 'error';
    } elseif (in_array($port['state'], ['blocking']) && $port['ifOperStatus'] === 'up') {
      $row_class = 'warning';
    } elseif ($port['ifOperStatus'] !== 'up') {
      $row_class = 'disabled';
    } elseif ($port['state'] === 'forwarding') {
      $row_class = 'ok';
    } else {
      $row_class = 'info'; // learning, listening states
    }

    echo '<tr class="'.$row_class.'">';

    // State marker
    echo '<td class="state-marker"></td>';

    // Port
    echo '<td class="entity-title">';
    echo generate_port_link($port);
    if ($port['ifOperStatus'] !== 'up') {
      echo ' <small class="text-muted">(' . $port['ifOperStatus'] . ')</small>';
    }
    echo '</td>';

    // Instance
    echo '<td>';
    $type_colors = ['cist' => 'primary', 'msti' => 'info', 'pvst' => 'success'];
    $type_class = $type_colors[strtolower($port['instance_type'])] ?? 'default';
    echo '<span class="label label-' . $type_class . '">' . htmlentities($instance_display) . '</span>';
    echo '</td>';

    // State
    echo '<td>' . get_type_class_label($port['state'], 'stp_state') . '</td>';

    // Role
    echo '<td>';
    if (empty($port['role']) || strtolower($port['role']) === 'unknown') {
      echo '—';
    } else {
      echo '<span class="' . $role_class . '">' . htmlentities($port['role']) . '</span>';
    }
    echo '</td>';

    // Cost
    echo '<td>' . ($port['path_cost'] ? (int)$port['path_cost'] : '—') . '</td>';

    // Priority
    echo '<td>' . ($port['priority'] ? (int)$port['priority'] : '—') . '</td>';

    // Edge
    echo '<td>';
    if ($port['admin_edge']) {
      if ($port['oper_edge']) {
        echo '<span class="label label-success">Edge</span>';
      } else {
        echo '<span class="label label-warning">Admin Edge</span>';
      }
    } elseif ($port['oper_edge']) {
      echo '<span class="label label-info">Auto Edge</span>';
    } else {
      echo '—';
    }
    echo '</td>';

    // Point-to-Point
    echo '<td>';
    if ($port['point2point'] === 'true') {
      echo '<span class="text-success">Yes</span>';
    } elseif ($port['point2point'] === 'false') {
      echo '<span class="text-muted">No</span>';
    } else {
      echo '—';
    }
    echo '</td>';

    // Guard
    echo '<td>' . htmlentities($port['guard'] ?: '—') . '</td>';

    // Inconsistent
    echo '<td>';
    if ($port['inconsistent']) {
      echo '<span class="label label-danger">YES</span>';
    } else {
      echo '—';
    }
    echo '</td>';

    // Flaps column - Dual badge system
    echo '<td>';

    // Badge classes
    $b5 = 'label ' . ($t5 ? 'label-danger' : 'label-default');

    if ($t60 >= $crit_60m)      { $b60 = 'label label-danger'; }
    else if ($t60 >= $warn_60m) { $b60 = 'label label-warning'; }
    else                        { $b60 = 'label label-success'; }

    // Tooltip text
    $tt = [];
    $tt[] = $t5 ? 'Changed state in the last poll (5m).' : 'No change this poll.';
    $tt[] = "Transitions in last hour: {$t60}.";
    if ($chg > 0) {
      $tt[] = 'Last change ' . format_uptime($now - $chg) . ' ago';
    }
    $tooltip = htmlspecialchars(implode(' ', $tt));

    $flap_cell = '<span class="'.$b5.'" title="'.$tooltip.'">5m '.$t5.'</span> ';
    $flap_cell .= '<span class="'.$b60.'" title="'.$tooltip.'">60m '.$t60.'</span>';

    echo $flap_cell;
    echo '</td>';

    // Last change cell (humanized)
    echo '<td class="text-nowrap">';
    echo $chg ? format_uptime($now - $chg) . ' ago' : '—';
    echo '</td>';

    // Updated
    echo '<td><small>' . htmlentities($port['updated']) . '</small></td>';

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

// Quick statistics
if (!empty($ports)) {
  $stats = [
    'forwarding' => 0,
    'blocking' => 0,
    'edge' => 0,
    'inconsistent' => 0
  ];

  foreach ($ports as $port) {
    if ($port['state'] === 'forwarding') $stats['forwarding']++;
    if (in_array($port['state'], ['blocking', 'discarding'])) $stats['blocking']++;
    if ($port['oper_edge']) $stats['edge']++;
    if ($port['inconsistent']) $stats['inconsistent']++;
  }

  echo generate_box_open(['title' => 'Port Summary']);
  echo '<div class="row text-center" style="margin: 10px 0;">';
  echo '<div class="col-md-3"><strong class="text-success">' . $stats['forwarding'] . '</strong><br><small class="text-muted">Forwarding</small></div>';
  echo '<div class="col-md-3"><strong class="text-warning">' . $stats['blocking'] . '</strong><br><small class="text-muted">Blocking</small></div>';
  echo '<div class="col-md-3"><strong class="text-info">' . $stats['edge'] . '</strong><br><small class="text-muted">Edge Ports</small></div>';
  echo '<div class="col-md-3"><strong class="text-danger">' . $stats['inconsistent'] . '</strong><br><small class="text-muted">Inconsistent</small></div>';
  echo '</div>';
  echo generate_box_close();
}

// EOF
