<?php
/**
 * Observium - Device EIGRP
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

if (empty($vars['view'])) { $vars['view'] = 'overview'; }

include_once($config['html_dir'] . '/includes/navbars/eigrp.inc.php');

// Resolve chosen VPN/AS
if (!isset($vars['vpn'])) {
  $first = dbFetchRow("SELECT `eigrp_vpn` FROM `eigrp_vpns` WHERE `device_id` = ? ORDER BY `eigrp_vpn` LIMIT 1", [ $device['device_id'] ]);
  if ($first) { $vars['vpn'] = $first['eigrp_vpn']; }
}
if (isset($vars['vpn']) && !isset($vars['asn'])) {
  $first = dbFetchRow("SELECT `eigrp_as` FROM `eigrp_ases` WHERE `device_id` = ? AND `eigrp_vpn` = ? ORDER BY `eigrp_as` LIMIT 1",
                      [ $device['device_id'], $vars['vpn'] ]);
  if ($first) { $vars['asn'] = $first['eigrp_as']; }
}

$view = $vars['view'];

if ($view === 'overview') {
  $as = dbFetchRow("SELECT * FROM `eigrp_ases` LEFT JOIN `eigrp_vpns` USING (`device_id`,`eigrp_vpn`)
                    WHERE `device_id` = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ?",
                    [ $device['device_id'], $vars['vpn'], $vars['asn'] ]);
  if (!$as) {
    print_warning('No EIGRP data found for the selected VPN/AS.');
    return;
  }

  // Build status panel with styled values and deep links
  $status_boxes = [];

  // Router ID, AS, and VPN on status bar
  $status_boxes[] = [ 'title' => 'Router ID', 'value' => escape_html($as['cEigrpAsRouterId'] ?: 'Unknown'), 'subtitle' => 'EIGRP Instance' ];
  $status_boxes[] = [ 'title' => 'AS', 'value' => [ 'text' => 'AS' . (int)$vars['asn'], 'class' => 'label-info' ], 'subtitle' => 'Autonomous System' ];
  $status_boxes[] = [ 'title' => 'VPN', 'value' => escape_html(isset($as['eigrp_vpn_name']) ? $as['eigrp_vpn_name'] : $vars['vpn']), 'subtitle' => 'VRF/VPN Name' ];

  // Routes as label-group with BGP-style colors
  if (isset($as['routes_int']) || isset($as['routes_ext'])) {
    $routes_html = '<span class="label label-info">'.(int)$as['routes_int'].'</span> <span class="label label-primary">'.(int)$as['routes_ext'].'</span>';
    $status_boxes[] = [ 'title' => 'Routes', 'value' => [ 'html' => $routes_html ], 'subtitle' => 'Internal / External' ];
  } else {
    $status_boxes[] = [ 'title' => 'Routes', 'value' => [ 'text' => (string)(int)$as['cEigrpTopoRoutes'], 'class' => 'label-default' ], 'subtitle' => 'Topology' ];
  }

  // Links to peers/ports tabs
  $peers_link = generate_url([ 'page' => 'device', 'device' => $device['device_id'], 'tab' => 'routing', 'proto' => 'eigrp', 'view' => 'peers', 'vpn' => $vars['vpn'], 'asn' => $vars['asn'] ]);
  $ports_link = generate_url([ 'page' => 'device', 'device' => $device['device_id'], 'tab' => 'routing', 'proto' => 'eigrp', 'view' => 'ports', 'vpn' => $vars['vpn'], 'asn' => $vars['asn'] ]);

  // Peers/Neighbours count
  if (isset($as['peers_up'])) {
    $status_boxes[] = [ 'title' => 'Peers Up', 'value' => [ 'html' => '<a href="'.$peers_link.'" class="label label-success">'.(int)$as['peers_up'].'</a>' ], 'subtitle' => 'Active Adjacencies' ];
  } else {
    $status_boxes[] = [ 'title' => 'Neighbours', 'value' => [ 'html' => '<a href="'.$peers_link.'" class="label label-primary">'.(int)$as['cEigrpNbrCount'].'</a>' ], 'subtitle' => 'Total Adjacencies' ];
  }

  // Health metrics - combine Down (1h) and Flaps (24h) into one metric
  $degraded = (int)dbFetchCell('SELECT COUNT(*) FROM `eigrp_peers` WHERE `device_id` = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ? AND (`peer_srtt` > 300 OR `peer_rto` > 800)', [ $device['device_id'], $vars['vpn'], $vars['asn'] ]);
  $flaps24  = (int)dbFetchCell('SELECT COUNT(*) FROM `eigrp_peers` WHERE `device_id` = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ? AND `last_change` IS NOT NULL AND `last_change` >= NOW() - INTERVAL 1 DAY', [ $device['device_id'], $vars['vpn'], $vars['asn'] ]);
  $down_recent = (int)$as['peers_down_recent'];

  // Combined health issues metric
  $health_issues = $down_recent + $flaps24 + $degraded;
  $health_class = $health_issues > 0 ? ($down_recent > 0 ? 'label-danger' : 'label-warning') : 'label-success';
  $status_boxes[] = [ 'title' => 'Health Issues', 'value' => [ 'text' => (string)$health_issues, 'class' => $health_class ], 'subtitle' => 'Down/Flap/Degraded' ];
  echo generate_status_panel($status_boxes);

  // AS Overview graph (optional)
  if (get_var_true($vars['graphs']) && is_array($as) && isset($as['eigrp_as_id'])) {
    echo generate_box_open(['title' => 'AS Overview']);
    $graph_array = [ 'to' => get_time(), 'id' => $as['eigrp_as_id'], 'type' => 'eigrpas_graph' ];
    print_graph_row($graph_array);
    echo generate_box_close();
  }

  // Peer summaries for quick triage
  $problem_peers = dbFetchRows("SELECT * FROM `eigrp_peers` WHERE `device_id` = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ? AND (`state` != 'up' OR `peer_qcount` > 0 OR `peer_srtt` > 300 OR `peer_rto` > 800) ORDER BY (`state` = 'down') DESC, `peer_srtt` DESC LIMIT 8",
                               [ $device['device_id'], $vars['vpn'], $vars['asn'] ]);
  echo generate_box_open(['title' => 'Peer Issues']);
  echo '<table class="table table-striped table-condensed">';
  echo '<thead><tr><th class="state-marker"></th><th>Peer</th><th>Local Interface</th><th>Status</th><th>Issues</th><th>Version</th></tr></thead><tbody>';
  if (!$problem_peers) {
    echo '<tr><td class="state-marker"></td><td colspan="5"><span class="text-muted">No peer issues detected.</span></td></tr>';
  }
  foreach ($problem_peers as $peer) {
    $lport = get_port_by_ifIndex($device['device_id'], $peer['peer_ifindex']);
    $row_class = ($peer['state'] !== 'up') ? 'error' : (((int)$peer['peer_srtt'] > 300 || (int)$peer['peer_rto'] > 800) ? 'warning' : 'ok');
    $issues = [];
    if ($peer['state'] !== 'up') { $issues[] = 'Down'; }
    if ((int)$peer['peer_qcount'] > 0) { $issues[] = 'Queue ' . (int)$peer['peer_qcount']; }
    if ((int)$peer['peer_srtt'] > 300) { $issues[] = 'SRTT ' . (int)$peer['peer_srtt'] . 'ms'; }
    if ((int)$peer['peer_rto'] > 800) { $issues[] = 'RTO ' . (int)$peer['peer_rto'] . 'ms'; }
    if ((int)$peer['bad_q_consec'] >= 3) { $issues[] = 'Queue streak ' . (int)$peer['bad_q_consec']; }
    $issue_text = $issues ? implode(', ', $issues) : 'Degraded metrics';

    echo '<tr class="'.$row_class.'">';
    echo '<td class="state-marker"></td>';
    echo '<td>'.escape_html($peer['peer_addr']).'</td>';
    echo '<td class="entity">'.(is_array($lport) ? generate_port_link($lport) : 'Unknown').'</td>';
    echo '<td>'.escape_html(nicecase($peer['state'])).'</td>';
    echo '<td>'.escape_html($issue_text).'</td>';
    echo '<td>'.escape_html($peer['peer_version']).'</td>';
    echo '</tr>';
  }
  echo '</tbody></table>';
  echo generate_box_close();

  $latency_peers = dbFetchRows("SELECT * FROM `eigrp_peers` WHERE `device_id` = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ? ORDER BY `peer_srtt` DESC LIMIT 5",
                               [ $device['device_id'], $vars['vpn'], $vars['asn'] ]);
  echo generate_box_open(['title' => 'Top SRTT Peers']);
  echo '<table class="table table-striped table-condensed">';
  echo '<thead><tr><th class="state-marker"></th><th>Peer</th><th>SRTT</th><th>RTO</th><th>Queue</th><th>Uptime</th></tr></thead><tbody>';
  if (!$latency_peers) {
    echo '<tr><td class="state-marker"></td><td colspan="5"><span class="text-muted">No peers discovered.</span></td></tr>';
  }
  foreach ($latency_peers as $peer) {
    $row_class = ($peer['state'] !== 'up') ? 'error' : (((int)$peer['peer_srtt'] > 800) ? 'error' : ((int)$peer['peer_srtt'] > 300 ? 'warning' : 'ok'));
    echo '<tr class="'.$row_class.'">';
    echo '<td class="state-marker"></td>';
    echo '<td>'.escape_html($peer['peer_addr']).'</td>';
    echo '<td>'.(int)$peer['peer_srtt'].' ms</td>';
    echo '<td>'.(int)$peer['peer_rto'].' ms</td>';
    echo '<td>'.(int)$peer['peer_qcount'].'</td>';
    echo '<td>'.format_uptime($peer['peer_uptime']).'</td>';
    echo '</tr>';
  }
  echo '</tbody></table>';
  echo generate_box_close();

  $interface_alerts = dbFetchRows("SELECT ep.*, p.ifAlias FROM `eigrp_ports` AS ep LEFT JOIN `ports` AS p ON p.port_id = ep.port_id WHERE ep.`device_id` = ? AND ep.`eigrp_vpn` = ? AND ep.`eigrp_as` = ? AND (ep.`eigrp_PendingRoutes` > 0 OR ep.`eigrp_XmitReliableQ` > 0 OR ep.`eigrp_XmitUnreliableQ` > 0) ORDER BY (ep.`eigrp_PendingRoutes` + ep.`eigrp_XmitReliableQ` + ep.`eigrp_XmitUnreliableQ`) DESC LIMIT 5",
                                 [ $device['device_id'], $vars['vpn'], $vars['asn'] ]);
  echo generate_box_open(['title' => 'Interfaces with Pending Routes/Queues']);
  echo '<table class="table table-striped table-condensed">';
  echo '<thead><tr><th class="state-marker"></th><th>Port</th><th>Description</th><th>Pending Routes</th><th>Reliable Q</th><th>Unreliable Q</th></tr></thead><tbody>';
  if (!$interface_alerts) {
    echo '<tr><td class="state-marker"></td><td colspan="5"><span class="text-muted">No interface queue issues detected.</span></td></tr>';
  }
  foreach ($interface_alerts as $intf) {
    $port = get_port_by_id_cache($intf['port_id']);
    echo '<tr class="warning">';
    echo '<td class="state-marker"></td>';
    echo '<td class="entity">'.generate_port_link($port).'</td>';
    echo '<td>'.escape_html($intf['ifAlias']).'</td>';
    echo '<td>'.(int)$intf['eigrp_PendingRoutes'].'</td>';
    echo '<td>'.(int)$intf['eigrp_XmitReliableQ'].'</td>';
    echo '<td>'.(int)$intf['eigrp_XmitUnreliableQ'].'</td>';
    echo '</tr>';
  }
  echo '</tbody></table>';
  echo generate_box_close();

  $events = dbFetchRows("SELECT `timestamp`,`message` FROM `eventlog` WHERE `device_id` = ? AND `type` LIKE 'eigrp%' ORDER BY `timestamp` DESC LIMIT 5", [ $device['device_id'] ]);
  echo generate_box_open(['title' => 'Recent EIGRP Events']);
  echo '<table class="table table-condensed">';
  echo '<thead><tr><th>Time</th><th>Message</th></tr></thead><tbody>';
  if (!$events) {
    echo '<tr><td colspan="2"><span class="text-muted">No recent events.</span></td></tr>';
  }
  foreach ($events as $event) {
    echo '<tr><td>'.format_timestamp($event['timestamp']).'</td><td>'.escape_html($event['message']).'</td></tr>';
  }
  echo '</tbody></table>';
  echo generate_box_close();

} elseif ($view === 'peers') {
  include($config['html_dir'] . '/pages/device/routing/eigrp/peers.inc.php');
} elseif ($view === 'ports') {
  include($config['html_dir'] . '/pages/device/routing/eigrp/ports.inc.php');
} else {
  $vars['view'] = 'overview';
  redirect_to_url(generate_url($vars));
}
