<?php
/**
 * STP Problem Ports - Global View
 * 
 * Shows all problematic STP ports across the entire network
 * Filters for blocking/discarding/broken states, inconsistencies, and flapping ports
 */

// Filter processing
$where_conditions = ['(p.state IN (\'blocking\',\'discarding\',\'broken\') OR p.inconsistent = 1 OR p.transitions_5m = 1 OR p.transitions_60m >= 3)'];
$where_conditions[] = 'po.ifAdminStatus = \'up\''; // Hide admin-down ports
$params = [];

// Variant filter - $vars['variant'] is already an array if multiselect
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

// Only inconsistent filter
if (!empty($vars['only_inconsistent'])) {
  $where_conditions[] = "p.inconsistent = 1";
}

// Only trunks filter (basic implementation)
if (!empty($vars['only_trunks'])) {
  $where_conditions[] = "po.ifType IN ('ethernetCsmacd','ieee8023adLag')";
}

// Search filter
if (!empty($vars['search'])) {
  $search = '%' . $vars['search'] . '%';
  $where_conditions[] = "(d.hostname LIKE ? OR po.ifName LIKE ? OR po.ifDescr LIKE ?)";
  $params[] = $search;
  $params[] = $search;
  $params[] = $search;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Main query
$sql = "
  SELECT d.device_id, d.hostname,
         po.port_id, po.ifName, po.ifDescr, po.ifIndex,
         p.state, p.role, p.oper_edge, p.guard, p.inconsistent, p.stp_instance_id, p.updated,
         p.transitions_5m, p.transitions_60m, p.state_changed,
         sb.variant, COALESCE(sb.mst_region_name,'') AS region, COALESCE(sb.designated_root,'') AS root_hex, sb.domain_hash
  FROM stp_ports p
  JOIN ports po   ON po.port_id = p.port_id
  JOIN devices d  ON d.device_id = p.device_id
  JOIN stp_bridge sb ON sb.device_id = p.device_id
  $where_clause
  ORDER BY p.transitions_5m DESC, p.transitions_60m DESC, p.inconsistent DESC, 
           CASE p.state 
             WHEN 'broken' THEN 1 
             WHEN 'discarding' THEN 2 
             WHEN 'blocking' THEN 3 
             ELSE 4 
           END,
           d.hostname, po.ifIndex";

// Pagination - use Observium standard variable names
$page_size = (int)($vars['pagesize'] ?? 50);
$page = (int)($vars['pageno'] ?? 1);
$offset = ($page - 1) * $page_size;

$problem_ports = dbFetchRows($sql . " LIMIT $offset, $page_size", $params);
$total_count = dbFetchCell("SELECT COUNT(*) FROM ($sql) AS total_ports", $params);

// Filter form using Observium's search pattern
$search_form = [
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
    'placeholder' => 'Device:port search...',
    'value' => $vars['search'] ?? ''
  ]
];

// Add hidden fields for toggles if they're set
if (!empty($vars['only_trunks'])) {
  $search_form[] = ['type' => 'hidden', 'id' => 'only_trunks', 'value' => $vars['only_trunks']];
}
if (!empty($vars['only_inconsistent'])) {
  $search_form[] = ['type' => 'hidden', 'id' => 'only_inconsistent', 'value' => $vars['only_inconsistent']];
}

print_search($search_form, 'Problem Port Filters', 'search', generate_url($vars));

// Results table
if (empty($problem_ports)) {
  $content = '<div class="box-state-title">No Problem Ports Found</div>';
  $content .= '<p class="box-state-description">All STP ports are healthy - no blocking, discarding, broken, inconsistent, or flapping ports detected.</p>';

  echo generate_box_open();
  echo generate_box_state('success', $content, [
    'icon' => $config['icon']['ok']
  ]);
  echo generate_box_close();
} else {
  echo generate_box_open(['title' => "Problem Ports ($total_count total)"]);

  echo '<table class="table table-striped table-hover table-condensed">';
  echo '<thead><tr>';
  echo '<th class="state-marker"></th>';
  echo '<th>Device</th>';
  echo '<th>Port</th>';
  echo '<th>Domain</th>';
  echo '<th>Instance</th>';
  echo '<th>State</th>';
  echo '<th>Role</th>';
  echo '<th>Edge</th>';
  echo '<th>Guard</th>';
  echo '<th>Inconsistent</th>';
  echo '<th>Flaps</th>';
  echo '<th>Updated</th>';
  echo '</tr></thead><tbody>';

  foreach ($problem_ports as $port) {
    // Generate clean domain link using hash
    $dom_link = generate_url([
      'page' => 'stp',
      'view' => 'domain',
      'domain_hash' => $port['domain_hash']
    ]);

    // Get instance info
    $inst = dbFetchRow("SELECT type, instance_key FROM stp_instances WHERE stp_instance_id = ?", [$port['stp_instance_id']]);
    $inst_text = $inst ? strtoupper($inst['type']) . ' ' . $inst['instance_key'] : '—';

    // Determine row class based on port issues (prioritize flapping)
    $t5 = (int)($port['transitions_5m'] ?? 0);
    $t60 = (int)($port['transitions_60m'] ?? 0);

    $row_class = '';
    if ($t5 === 1) {
      $row_class = 'error'; // Currently flapping - highest priority
    } elseif ($port['inconsistent'] == 1) {
      $row_class = 'error';
    } elseif (in_array($port['state'], ['broken', 'discarding'])) {
      $row_class = 'error';
    } elseif ($t60 >= 6) {
      $row_class = 'error'; // Heavy flapping in last hour
    } elseif ($t60 >= 3) {
      $row_class = 'warning'; // Moderate flapping
    } elseif ($port['state'] === 'blocking') {
      $row_class = 'warning';
    } else {
      $row_class = 'info';
    }

    echo '<tr class="'.$row_class.'">';

    // State marker
    echo '<td class="state-marker"></td>';

    // Device
    $device_url = generate_device_url(['device_id' => $port['device_id'], 'hostname' => $port['hostname']], ['tab' => 'stp']);
    echo '<td class="entity-title"><a class="entity" href="' . $device_url . '">' . htmlentities($port['hostname']) . '</a></td>';

    // Port
    echo '<td>' . generate_port_link(['port_id' => $port['port_id']], $port['ifName'] ?: $port['ifDescr']) . '</td>';

    // Domain
    echo '<td><a class="entity" href="' . $dom_link . '">' . stp_format_domain_labels($port['variant'], $port['region'], $port['root_hex']) . '</a></td>';

    // Instance
    echo '<td>' . htmlentities($inst_text) . '</td>';

    // State using standardized label function with STP state group
    echo '<td>' . get_type_class_label($port['state'], 'stp_state') . '</td>';

    // Role
    echo '<td>' . htmlentities($port['role']) . '</td>';

    // Edge
    echo '<td>' . ($port['oper_edge'] ? '<span class="label label-success">edge</span>' : '') . '</td>';

    // Guard
    echo '<td>' . htmlentities($port['guard'] ?: '') . '</td>';

    // Inconsistent
    echo '<td>' . ($port['inconsistent'] ? '<span class="label label-danger">YES</span>' : '') . '</td>';

    // Flaps column - Dual badge system
    echo '<td>';
    $warn_60m = 3;
    $crit_60m = 6;

    $b5 = 'label ' . ($t5 ? 'label-danger' : 'label-default');

    if ($t60 >= $crit_60m)      { $b60 = 'label label-danger'; }
    else if ($t60 >= $warn_60m) { $b60 = 'label label-warning'; }
    else                        { $b60 = 'label label-success'; }

    // Tooltip
    $chg = (int)($port['state_changed'] ?? 0);
    $tt = [];
    $tt[] = $t5 ? 'Changed state in the last poll (5m).' : 'No change this poll.';
    $tt[] = "Transitions in last hour: {$t60}.";
    if ($chg > 0) {
      $tt[] = 'Last change ' . format_uptime(time() - $chg) . ' ago';
    }
    $tooltip = htmlspecialchars(implode(' ', $tt));

    echo '<span class="'.$b5.'" title="'.$tooltip.'">5m '.$t5.'</span> ';
    echo '<span class="'.$b60.'" title="'.$tooltip.'">60m '.$t60.'</span>';
    echo '</td>';

    // Updated
    echo '<td>' . htmlentities($port['updated']) . '</td>';

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