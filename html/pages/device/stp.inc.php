<?php
/**
 * Device → STP tab
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }
// STP entity functions now loaded via includes/entities.inc.php

$device_id = $vars['device'];
$bridge    = dbFetchRow("SELECT * FROM `stp_bridge` WHERE `device_id`=?", [ $device_id ]);

if (!$bridge) {
  print_warning('No STP data recorded for this device.');
  return;
}

// Get root device info for global summary
$root_dev_id = NULL;
$root_device = NULL;
if (!safe_empty($bridge['designated_root'])) {
  $root_dev_id = dbFetchCell('SELECT `device_id` FROM `stp_bridge` WHERE `bridge_id` = ? LIMIT 1', [ $bridge['designated_root'] ]);
  if (!$root_dev_id) {
    $root_dev_id = dbFetchCell('SELECT `device_id` FROM `stp_bridge` WHERE `designated_root` = ? AND IFNULL(`root_port`,0) = 0 LIMIT 1', [ $bridge['designated_root'] ]);
  }
  if ($root_dev_id) {
    $root_device = device_by_id_cache($root_dev_id);
    humanize_device($root_device); // Ensure shorthost is populated for both uses
  }
}

// Get health analysis for global summary
$health = stp_device_health($device_id);

// Get CIST instance ID for graphs
$cist_instance = dbFetchRow("SELECT stp_instance_id FROM `stp_instances` WHERE `device_id`=? AND `type`='cist'", [$device_id]);
$cist_instance_id = $cist_instance['stp_instance_id'] ?? null;

// Global STP Network Summary - using status panel function
$status_boxes = [];

// Root Bridge Information
if ($root_device && $root_dev_id != $device_id) {
  $status_boxes[] = [
    'title' => 'Root Bridge',
    'value' => ['html' => '<strong>' . generate_device_link_short($root_device, ['tab' => 'stp']) . '</strong>'],
    'subtitle' => 'Network Root'
  ];
} else {
  $status_boxes[] = [
    'title' => 'Root Status', 
    'value' => ['text' => 'This Device', 'class' => 'label-primary'],
    'subtitle' => 'Network Root'
  ];
}

// STP Variant Information
$variant_subtitles = [
  'stp' => 'IEEE 802.1D',
  'rstp' => 'IEEE 802.1w', 
  'mstp' => 'IEEE 802.1s',
  'pvst' => 'Cisco PVST+',
  'rpvst' => 'Cisco Rapid PVST+',
];
$status_boxes[] = [
  'title' => 'Protocol Variant',
  'value' => ['text' => strtoupper($bridge['variant']), 'class' => 'label-info'],
  'subtitle' => $variant_subtitles[strtolower($bridge['variant'])] ?? 'Standard STP'
];

// Network Role
if ((int)$bridge['root_cost'] === 0) {
  $status_boxes[] = [
    'title' => 'Network Role',
    'value' => ['text' => 'Root Bridge', 'class' => 'label-success'],
    'subtitle' => 'Cost: 0'
  ];
} else {
  $status_boxes[] = [
    'title' => 'Network Role',
    'value' => ['text' => 'Non-Root', 'class' => 'label-default'],
    'subtitle' => 'Cost: ' . (int)$bridge['root_cost']
  ];
}

// Problem Ports Statistics
$problem_ports = (int)dbFetchCell("SELECT COUNT(DISTINCT p.port_id) FROM `stp_ports` p JOIN `ports` po ON po.port_id = p.port_id WHERE p.device_id = ? AND (p.state IN ('blocking','discarding','broken') OR p.inconsistent = 1 OR p.transitions_5m = 1 OR p.transitions_60m >= 3) AND po.ifAdminStatus = 'up'", [$device_id]);
$total_ports = (int)dbFetchCell("SELECT COUNT(DISTINCT p.port_id) FROM `stp_ports` p JOIN `ports` po ON po.port_id = p.port_id WHERE p.device_id = ? AND po.ifAdminStatus = 'up'", [$device_id]);

if ($problem_ports > 0) {
  $problem_label = ['text' => $problem_ports . ' Issues', 'class' => 'label-danger'];
  $problem_subtitle = $total_ports . ' total ports';
} else {
  $problem_label = ['text' => 'All Healthy', 'class' => 'label-success'];
  $problem_subtitle = $total_ports . ' total ports';
}

$status_boxes[] = [
  'title' => 'Problem Ports',
  'value' => $problem_label,
  'subtitle' => $problem_subtitle
];

// Topology Changes
if ($bridge['time_since_tc_cs'] !== null) {
  $tc_time_label = stp_format_tc_time($bridge['time_since_tc_cs'], 'label');
  $tc_value = $tc_time_label;
  $tc_subtitle = 'Last Change';
} else {
  $tc_value = ['text' => 'Unknown', 'class' => 'label-default'];
  $tc_subtitle = 'No Data';
}

$status_boxes[] = [
  'title' => 'Topology Changes',
  'value' => $tc_value,
  'subtitle' => $tc_subtitle
];

// Health Status  
if ($health['score'] >= 90) {
  $health_label = ['text' => 'Healthy', 'class' => 'label-success'];
} elseif ($health['score'] >= 70) {
  $health_label = ['text' => 'Minor Issues', 'class' => 'label-warning'];
} else {
  $health_label = ['text' => 'Needs Attention', 'class' => 'label-danger'];
}
$status_boxes[] = [
  'title' => 'Health Status',
  'value' => $health_label,
  'subtitle' => 'Score: ' . $health['score'] . '/100'
];

echo generate_status_panel($status_boxes);

// Subtabs
$section = $vars['section'] ?: 'overview';
$navbar = [
  'brand' => 'Spanning Tree',
  'class' => 'navbar-narrow',
  'options' => [
    'overview'  => [ 'text' => 'Overview' ],
    'instances' => [ 'text' => 'Instances' ],
    'ports'     => [ 'text' => 'Ports' ],
    'vlanmap'   => [ 'text' => 'VLAN Mapping' ],
  ]
];

// Populate subtab URLs (Observium pattern: no JS tabs)
$base = [ 'page' => 'device', 'device' => $device_id, 'tab' => 'stp' ];
$navbar['options']['overview']['url']  = generate_url($base, ['section' => 'overview']);
$navbar['options']['instances']['url'] = generate_url($base, ['section' => 'instances']);
$navbar['options']['ports']['url']     = generate_url($base, ['section' => 'ports']);
$navbar['options']['vlanmap']['url']   = generate_url($base, ['section' => 'vlanmap']);
$navbar['options'][$section]['class'] = 'active';
print_navbar($navbar);

if ($section === 'overview') {
  // Totals
  $total_instances = dbFetchCell("SELECT COUNT(*) FROM `stp_instances` WHERE `device_id` = ?", [$device_id]);
  $total_ports = dbFetchCell("SELECT COUNT(DISTINCT p.port_id) FROM `stp_ports` p JOIN `ports` po ON po.port_id = p.port_id WHERE p.device_id = ? AND po.ifOperStatus = 'up'", [$device_id]);
  $forwarding_ports = dbFetchCell("SELECT COUNT(DISTINCT p.port_id) FROM `stp_ports` p JOIN `ports` po ON po.port_id = p.port_id WHERE p.device_id = ? AND p.state = 'forwarding' AND po.ifOperStatus = 'up'", [$device_id]);
  $blocking_ports = dbFetchCell("SELECT COUNT(DISTINCT p.port_id) FROM `stp_ports` p JOIN `ports` po ON po.port_id = p.port_id WHERE p.device_id = ? AND p.state IN ('blocking', 'discarding') AND po.ifOperStatus = 'up'", [$device_id]);
  $edge_ports = dbFetchCell("SELECT COUNT(DISTINCT p.port_id) FROM `stp_ports` p JOIN `ports` po ON po.port_id = p.port_id WHERE p.device_id = ? AND p.oper_edge = 1 AND po.ifOperStatus = 'up'", [$device_id]);

  // Root link
  $root_bridge_str = stp_bridge_id_str($bridge['designated_root']);
  if ($root_device && $root_dev_id == $device_id) {
    // Local device is root
    $root_bridge_link = '<strong>' . generate_device_link_short($root_device, ['tab' => 'stp']) . '</strong> <span class="label label-primary">self</span>';
  } elseif ($root_device) {
    // Remote root device
    $root_bridge_link = '<strong>' . generate_device_link_short($root_device, ['tab' => 'stp']) . '</strong>';
  } else {
    // Unknown root device
    $root_bridge_link = '<strong>' . escape_html($root_bridge_str) . '</strong>';
  }

  // Root port link
  $root_port_html = (int)$bridge['root_port'];
  if ((int)$bridge['root_port'] > 0) {
    $cist_id = stp_instance_ensure($device_id, 0, 'cist');
    $prow = dbFetchRow('SELECT po.* FROM `stp_ports` AS p JOIN `ports` AS po ON po.`port_id`=p.`port_id` WHERE p.`device_id`=? AND p.`stp_instance_id`=? AND p.`base_port`=? LIMIT 1', [ $device_id, $cist_id, (int)$bridge['root_port'] ]);
    if ($prow) { $root_port_html = generate_port_link($prow); }
  }

  echo '<div class="row">';
  echo '<div class="col-md-8">';

  // Bridge info
  echo generate_box_open(['title' => 'STP Bridge Information']);
  echo '<div class="row" style="margin: 0;">';
  echo '<div class="col-md-6" style="padding-left: 0;">';
  echo '<dl class="dl-horizontal dl-condensed">';
  echo '<dt>Protocol:</dt><dd><span class="label label-primary">'.htmlentities(strtoupper($bridge['variant'])).'</span></dd>';
  echo '<dt>Priority:</dt><dd>'.(int)$bridge['priority'].'</dd>';
  echo '<dt>Root Bridge:</dt><dd>'.$root_bridge_link.'</dd>';
  echo '<dt>Root Cost:</dt><dd>'.(int)$bridge['root_cost'].'</dd>';
  echo '<dt>Root Port:</dt><dd>'.$root_port_html.'</dd>';
  if (!empty($bridge['domain_hash'])) {
    $domain_url = generate_url(['page' => 'stp', 'view' => 'domain', 'domain_hash' => $bridge['domain_hash']]);
    echo '<dt>STP Domain:</dt><dd>';
    echo '<a class="entity" href="'.$domain_url.'">';
    echo stp_format_domain_labels($bridge['variant'], $bridge['mst_region_name'], $bridge['designated_root']);
    echo '</a>';
    echo '</dd>';
  }
  if ($bridge['mst_region_name'] !== NULL) {
    echo '<dt>MST Region:</dt><dd>'.htmlentities($bridge['mst_region_name']).' <small class="text-muted">(rev '.(int)$bridge['mst_revision'].')</small></dd>';
  }
  echo '</dl>';
  echo '</div>';
  echo '<div class="col-md-6" style="padding-right: 0;">';
  echo '<dl class="dl-horizontal dl-condensed">';
  echo '<dt>Hello Time:</dt><dd>'.($bridge['hello_time_cs'] ? round($bridge['hello_time_cs']/100, 2).'s' : 'N/A').'</dd>';
  echo '<dt>Max Age:</dt><dd>'.($bridge['max_age_cs'] ? round($bridge['max_age_cs']/100, 2).'s' : 'N/A').'</dd>';
  echo '<dt>Forward Delay:</dt><dd>'.($bridge['fwd_delay_cs'] ? round($bridge['fwd_delay_cs']/100, 2).'s' : 'N/A').'</dd>';
  echo '<dt>Topology Changes:</dt><dd><strong>'.(int)$bridge['top_changes'].'</strong></dd>';
  echo '<dt>Time Since TC:</dt><dd>'.stp_format_tc_time($bridge['time_since_tc_cs'], 'span').'</dd>';
  echo '<dt>Last Updated:</dt><dd><small>'.format_timestamp($bridge['updated']).'</small></dd>';
  echo '</dl>';
  echo '</div>';
  echo '</div>';
  echo generate_box_close();

  // Port status row
  $blocked_ratio = $total_ports > 0 ? round(($blocking_ports / $total_ports) * 100, 1) : 0;
  $port_status = [];
  $port_status[] = [ 'title' => 'Instances',   'value' => ['text' => (string)$total_instances, 'class' => 'label-default'] ];
  $port_status[] = [ 'title' => 'Total Ports', 'value' => ['text' => (string)$total_ports, 'class' => 'label-default'] ];
  $port_status[] = [ 'title' => 'Forwarding',  'value' => ['text' => (string)$forwarding_ports, 'class' => 'label-success'] ];
  $port_status[] = [ 'title' => 'Blocking',    'value' => ['text' => (string)$blocking_ports, 'class' => 'label-danger'] ];
  $port_status[] = [ 'title' => 'Edge Ports',  'value' => ['text' => (string)$edge_ports, 'class' => 'label-info'] ];
  $port_status[] = [ 'title' => 'Blocked',     'value' => ['text' => number_format($blocked_ratio, 1) . '%', 'class' => 'label-default'] ];
  echo generate_status_panel($port_status);

  // Graphs table
  echo generate_box_open();
  echo '<table class="table table-striped table-condensed" style="margin-bottom: 0;">';
  echo '<tr><td style="padding-left: 0; padding-right: 0;"><h4 style="margin-top: 5px;">Topology Changes</h4>';
  $graph_array = [ 'type' => 'device_stp_topchanges', 'device' => $device_id, 'legend' => 'no', 'cols' => 8 ];
  print_graph_row($graph_array);
  echo '</td></tr>';
  echo '<tr><td style="padding-left: 0; padding-right: 0;"><h4 style="margin-top: 5px;">Time Since Topology Change</h4>';
  $graph_array = [ 'type' => 'device_stp_tc_age', 'device' => $device_id, 'legend' => 'no', 'cols' => 8 ];
  print_graph_row($graph_array);
  echo '</td></tr>';
  echo '<tr><td style="padding-left: 0; padding-right: 0;"><h4 style="margin-top: 5px;">Port State Distribution</h4>';
  $graph_array = [ 'type' => 'device_stp_portcounts', 'device' => $device_id, 'legend' => 'no', 'cols' => 8 ];
  print_graph_row($graph_array);
  echo '</td></tr>';
  echo '<tr><td style="padding-left: 0; padding-right: 0;"><h4 style="margin-top: 5px;">Root Path Cost</h4>';
  $graph_array = [ 'type' => 'device_stp_rootcost', 'device' => $device_id, 'legend' => 'no', 'cols' => 8 ];
  print_graph_row($graph_array);
  echo '</td></tr>';

  // Show Cisco inconsistent events graph if device has this data (Cisco-specific)
  if (!empty($bridge['inconsistent_events']) && $bridge['inconsistent_events'] > 0) {
    echo '<tr><td style="padding-left: 0; padding-right: 0;"><h4 style="margin-top: 5px;">Inconsistent Events <small class="text-muted">(Cisco)</small></h4>';
    $graph_array = [ 'type' => 'device_stp_inconsistent', 'device' => $device_id, 'legend' => 'no', 'cols' => 8 ];
    print_graph_row($graph_array);
    echo '</td></tr>';
  }

  echo '</table>';
  echo generate_box_close();

  echo '</div>'; // end left col-md-8

  echo '<div class="col-md-4">';
  // Health Analysis
  echo generate_box_open(['title' => 'Health Analysis']);
  echo '<div class="text-center" style="padding: 15px 10px 10px 10px;">';
  echo '<div style="font-size: 36px; font-weight: bold; color: ';
  if ($health['score'] >= 90) echo '#5cb85c';
  elseif ($health['score'] >= 70) echo '#f0ad4e';
  elseif ($health['score'] >= 50) echo '#d9534f';
  else echo '#d9534f';
  echo '">'.$health['score'].'</div>';
  echo '<div class="text-muted">Health Score</div>';
  echo '</div>';
  if (!empty($health['metrics'])) {
    echo '<div style="padding: 0 10px;">';
    echo '<dl class="dl-horizontal dl-condensed" style="margin-bottom: 10px;">';
    if (isset($health['metrics']['tc_rate'])) {
      echo '<dt>TC Rate:</dt><dd>'.sprintf('%.1f/hr', $health['metrics']['tc_rate']).'</dd>';
    }
    if (isset($health['metrics']['port_issues'])) {
      echo '<dt>Port Issues:</dt><dd>'.$health['metrics']['port_issues'].'</dd>';
    }
    if (isset($health['metrics']['convergence_issues'])) {
      echo '<dt>Convergence:</dt><dd>'.$health['metrics']['convergence_issues'].' issues</dd>';
    }
    echo '</dl>';
    echo '</div>';
  }
  if (!empty($health['issues']) || !empty($health['warnings'])) {
    echo '<div style="padding: 0 10px 10px 10px;">';
    if (!empty($health['issues'])) {
      echo '<div class="text-danger" style="margin-bottom: 5px;"><strong>'.count($health['issues']).' Issues:</strong></div>';
      echo '<ul style="margin: 0; padding-left: 15px; font-size: 11px;" class="text-danger">';
      foreach (array_slice($health['issues'], 0, 3) as $issue) { echo '<li>'.htmlentities($issue).'</li>'; }
      if (count($health['issues']) > 3) { echo '<li><em>+'.(count($health['issues'])-3).' more...</em></li>'; }
      echo '</ul>';
    }
    if (!empty($health['warnings'])) {
      echo '<div class="text-warning" style="margin: 5px 0;"><strong>'.count($health['warnings']).' Warnings:</strong></div>';
      echo '<ul style="margin: 0; padding-left: 15px; font-size: 11px;" class="text-warning">';
      foreach (array_slice($health['warnings'], 0, 2) as $warning) { echo '<li>'.htmlentities($warning).'</li>'; }
      if (count($health['warnings']) > 2) { echo '<li><em>+'.(count($health['warnings'])-2).' more...</em></li>'; }
      echo '</ul>';
    }
    echo '</div>';
  } else {
    echo '<div class="text-center text-success" style="padding: 10px;"><i class="icon-ok"></i> All systems healthy</div>';
  }
  echo generate_box_close();

  // Instances summary
  $counts = dbFetchRows('SELECT `type`, COUNT(*) AS c FROM `stp_instances` WHERE `device_id`=? GROUP BY `type`', [ $device_id ]);
  $summary = [];
  $type_colors = ['cist' => 'primary', 'msti' => 'info', 'pvst' => 'success', 'rpvst' => 'warning'];
  foreach ($counts as $c) {
    $color = $type_colors[strtolower($c['type'])] ?? 'default';
    $summary[] = '<span class="label label-'.$color.'">'.strtoupper($c['type']).' '.(int)$c['c'].'</span>';
  }
  $sample = dbFetchRows('SELECT `stp_instance_id`,`type`,`instance_key` FROM `stp_instances` WHERE `device_id`=? ORDER BY `type`,`instance_key` LIMIT 8', [ $device_id ]);
  echo generate_box_open([ 'title' => 'STP Instances' ]);
  echo '<div style="padding: 10px;">';
  if ($summary) { echo '<div style="margin-bottom: 10px;"><strong>Types:</strong><br>'.implode(' ', $summary).'</div>'; }
  if ($sample) {
    echo '<div style="margin-bottom: 10px;"><strong>Quick Access:</strong><br>';
    foreach ($sample as $s) {
      $url = generate_url([ 'page' => 'device', 'device' => $device_id, 'tab' => 'stp', 'section' => 'ports', 'instance_id' => (int)$s['stp_instance_id'] ]);
      $label_class = $type_colors[strtolower($s['type'])] ?? 'default';
      echo '<a class="label label-'.$label_class.'" style="margin: 1px 2px; display: inline-block;" href="'.$url.'" title="View ports for this instance">'.escape_html(strtoupper($s['type']).' '.$s['instance_key']).'</a>';
    }
    if (count($sample) >= 8) { echo '<br><small class="text-muted">Showing first 8 instances...</small>'; }
    echo '</div>';
  }
  $all_url = generate_url([ 'page' => 'device', 'device' => $device_id, 'tab' => 'stp', 'section' => 'instances' ]);
  echo '<a class="btn btn-xs btn-primary" href="'.$all_url.'">View All Instances</a>';
  echo '</div>';
  echo generate_box_close();

  echo '</div>'; // end right col
  echo '</div>'; // end outer row
}

// OTHER SECTIONS
if ($section === 'instances') {
  include($config['html_dir'] . '/pages/device/stp/instances.inc.php');
}

if ($section === 'ports') {
  include($config['html_dir'] . '/pages/device/stp/ports.inc.php');
}

if ($section === 'vlanmap') {
  include($config['html_dir'] . '/pages/device/stp/vlanmap.inc.php');
}
