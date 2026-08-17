<?php
/**
 * Device STP Instances View
 * 
 * Shows all STP instances for a specific device with their configuration
 * and port state summaries
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

$device_id = $vars['device'] ?? null;
if (!$device_id) {
  echo '<div class="alert alert-danger">No device specified.</div>';
  return;
}

// Get all instances for this device with port state aggregates
$instances = dbFetchRows("
  SELECT i.stp_instance_id, i.type, i.instance_key, i.name, i.designated_root, 
         i.root_cost, i.root_port, i.bridge_priority, i.top_changes, 
         i.hello_time_cs, i.max_age_cs, i.forward_delay_cs, i.updated,
         SUM(CASE WHEN p.state='forwarding' THEN 1 ELSE 0 END) AS fwd_ports,
         SUM(CASE WHEN p.state IN ('blocking','discarding','broken') THEN 1 ELSE 0 END) AS bad_ports,
         SUM(CASE WHEN p.inconsistent=1 THEN 1 ELSE 0 END) AS incons_ports,
         SUM(CASE WHEN p.transitions_5m=1 THEN 1 ELSE 0 END) AS flaps_5m,
         SUM(COALESCE(p.transitions_60m,0)) AS flaps_60m,
         COUNT(p.port_id) AS total_ports
  FROM stp_instances i
  LEFT JOIN stp_ports p ON p.stp_instance_id = i.stp_instance_id
  WHERE i.device_id = ?
  GROUP BY i.stp_instance_id
  ORDER BY i.type, i.instance_key
", [$device_id]);

if (empty($instances)) {
  $content = '<div class="box-state-title">No STP Instances Found</div>';
  $content .= '<p class="box-state-description">This device has no STP instances configured.</p>';

  echo generate_box_open();
  echo generate_box_state('info', $content, [
    'icon' => $config['icon']['info'],
    'size' => 'medium'
  ]);
  echo generate_box_close();
  return;
}

// Summary statistics
$total_instances = count($instances);
$cist_count = count(array_filter($instances, function($i) { return $i['type'] === 'cist'; }));
$msti_count = count(array_filter($instances, function($i) { return $i['type'] === 'msti'; }));
$pvst_count = count(array_filter($instances, function($i) { return $i['type'] === 'pvst'; }));

echo generate_box_open(['title' => "STP Instances ($total_instances total)"]);

echo '<div class="row" style="margin-bottom: 15px;">';
echo '<div class="col-md-12">';
echo '<div class="text-center">';
echo '<div class="col-md-3"><strong>' . $total_instances . '</strong><br><small class="text-muted">Total Instances</small></div>';
echo '<div class="col-md-3"><strong class="text-primary">' . $cist_count . '</strong><br><small class="text-muted">CIST</small></div>';
echo '<div class="col-md-3"><strong class="text-info">' . $msti_count . '</strong><br><small class="text-muted">MSTI</small></div>';
echo '<div class="col-md-3"><strong class="text-success">' . $pvst_count . '</strong><br><small class="text-muted">PVST</small></div>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '<table class="table table-striped table-hover table-condensed">';
echo '<thead><tr>';
echo '<th class="state-marker"></th>';
echo '<th>Instance</th>';
echo '<th>Root Bridge</th>';
echo '<th>Root Cost</th>';
echo '<th>Root Port</th>';
echo '<th>Bridge Priority</th>';
echo '<th>Total Ports</th>';
echo '<th>Forwarding</th>';
echo '<th>Bad States</th>';
echo '<th>Inconsistent</th>';
echo '<th>Flaps</th>';
echo '<th>Health</th>';
echo '<th>Updated</th>';
echo '</tr></thead><tbody>';

foreach ($instances as $instance) {
  // Format instance label (just type + key)
  $instance_label = strtoupper($instance['type']);
  if ($instance['instance_key'] !== null && $instance['instance_key'] !== '') {
    $instance_label .= ' ' . $instance['instance_key'];
  }

  // Format descriptive name separately
  $instance_name = '';
  if (!empty($instance['name'])) {
    $instance_name = ' <small class="text-muted">(' . htmlentities($instance['name']) . ')</small>';
  }

  // Calculate health based on port states
  $total_ports = (int)$instance['total_ports'];
  $bad_ports = (int)$instance['bad_ports'];
  $incons_ports = (int)$instance['incons_ports'];

  $health_class = 'label-success';
  $health_text = 'Good';
  $row_class = '';

  if ($total_ports > 0) {
    $bad_ratio = ($bad_ports + $incons_ports) / $total_ports;
    if ($bad_ratio > 0.3) {
      $health_class = 'label-danger';
      $health_text = 'Poor';
      $row_class = 'error';
    } elseif ($bad_ratio > 0.1) {
      $health_class = 'label-warning';
      $health_text = 'Fair';
      $row_class = 'warning';
    } else {
      $row_class = 'ok';
    }
  } elseif ($total_ports === 0) {
    $health_class = 'label-default';
    $health_text = 'No Ports';
    $row_class = 'disabled';
  } else {
    $row_class = 'ok';
  }

  // Format root bridge
  $root_bridge_str = '—';
  if (!empty($instance['designated_root'])) {
    $root_bridge_str = stp_bridge_id_str($instance['designated_root']);
  }

  // Format root port
  $root_port_html = '';
  if ($instance['root_port'] > 0) {
    $port = dbFetchRow("SELECT port_id, ifName, ifDescr FROM ports WHERE device_id = ? AND ifIndex = ?", 
                      [$device_id, $instance['root_port']]);
    if ($port) {
      $root_port_html = generate_port_link(['port_id' => $port['port_id']], $port['ifName'] ?: $port['ifDescr']);
    } else {
      $root_port_html = $instance['root_port'];
    }
  } else {
    $root_port_html = '—';
  }

  echo '<tr class="'.$row_class.'">';

  // State marker
  echo '<td class="state-marker"></td>';

  // Instance
  echo '<td>';
  $instance_url = generate_url(['page' => 'device', 'device' => $device_id, 'tab' => 'stp', 'section' => 'ports', 'instance_id' => $instance['stp_instance_id']]);
  echo '<a class="entity" href="' . $instance_url . '">';
  $type_colors = ['cist' => 'primary', 'msti' => 'info', 'pvst' => 'success'];
  $type_class = $type_colors[strtolower($instance['type'])] ?? 'default';
  echo '<span class="label label-' . $type_class . '">' . $instance_label . '</span>';
  echo '</a>';
  echo $instance_name;
  echo '</td>';

  // Root Bridge
  echo '<td>' . htmlentities($root_bridge_str) . '</td>';

  // Root Cost
  echo '<td>' . (int)$instance['root_cost'] . '</td>';

  // Root Port
  echo '<td>' . $root_port_html . '</td>';

  // Total Ports
  echo '<td>' . $total_ports . '</td>';

  // Forwarding
  echo '<td>';
  if ($instance['fwd_ports'] > 0) {
    echo '<span class="text-success">' . (int)$instance['fwd_ports'] . '</span>';
  } else {
    echo (int)$instance['fwd_ports'];
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
  $inst_5m  = (int)$instance['flaps_5m'];
  $inst_60m = (int)$instance['flaps_60m'];

  // Badge classes using same logic as ports page
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
echo generate_box_close();

// EOF