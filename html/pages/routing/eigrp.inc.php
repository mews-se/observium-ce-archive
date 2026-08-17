<?php
/**
 * Observium - Global EIGRP
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

register_html_title('EIGRP');
$vars['protocol'] = 'eigrp';

include_once($config['html_dir'] . '/includes/navbars/eigrp.inc.php');

// WHERE (build separate filters for AS queries vs Peer queries to avoid ambiguous columns)
$params = [];

// AS-scoped WHERE
$where_ases = [];
$where_ases[] = trim(generate_query_permitted_ng(['device'], [ 'device_table' => 'eigrp_ases' ]));
if (!safe_empty($vars['device'])) {
  $ids = is_array($vars['device']) ? $vars['device'] : [ $vars['device'] ];
  $ids = array_map('intval', $ids);
  $where_ases[] = generate_query_values($ids, 'eigrp_ases.device_id');
}
if (!safe_empty($vars['vpnname'])) { $where_ases[] = '`eigrp_vpns`.`eigrp_vpn_name` LIKE ?'; $params[] = '%'.$vars['vpnname'].'%'; }
if (is_numeric($vars['asn']))     { $where_ases[] = '`eigrp_ases`.`eigrp_as` = ?'; $params[] = (int)$vars['asn']; }

$sql  = 'FROM `eigrp_ases` ';
$sql .= 'LEFT JOIN `eigrp_vpns` USING (`device_id`,`eigrp_vpn`) ';
$sql .= 'LEFT JOIN `devices` ON `devices`.`device_id` = `eigrp_ases`.`device_id` ';
if (!empty($where_ases)) { $sql .= ' WHERE ' . implode(' AND ', $where_ases); }

// KPI bar
$kpi_devices = dbFetchCell('SELECT COUNT(DISTINCT `eigrp_ases`.`device_id`) '.$sql, $params);
$kpi_ases    = dbFetchCell('SELECT COUNT(*) '.$sql, $params);
// Derived sums per instance for overview tiles
$kpi_routes_i = (int)dbFetchCell('SELECT COALESCE(SUM(`routes_int`),0) '.$sql, $params);
$kpi_routes_e = (int)dbFetchCell('SELECT COALESCE(SUM(`routes_ext`),0) '.$sql, $params);

// Peers KPI uses peers-scoped WHERE
$peer_params = [];
$where_peers = [];
$where_peers[] = trim(generate_query_permitted_ng(['device'], [ 'device_table' => 'eigrp_peers' ]));
if (!safe_empty($vars['device'])) {
  $ids = is_array($vars['device']) ? $vars['device'] : [ $vars['device'] ];
  $ids = array_map('intval', $ids);
  $where_peers[] = generate_query_values($ids, 'eigrp_peers.device_id');
}
if (is_numeric($vars['asn'])) { $where_peers[] = '`eigrp_peers`.`eigrp_as` = ?'; $peer_params[] = (int)$vars['asn']; }
if (!safe_empty($vars['vpnname'])) {
  $where_peers[] = '`eigrp_vpns`.`eigrp_vpn_name` LIKE ?';
  $peer_params[] = '%'.$vars['vpnname'].'%';
}
// vpnname filter applies via join to eigrp_vpns in the peer KPI query below

$peer_kpi_sql  = 'FROM `eigrp_peers` ';
$peer_kpi_sql .= 'LEFT JOIN `eigrp_vpns` USING (`device_id`,`eigrp_vpn`) ';
if (!empty($where_peers)) { $peer_kpi_sql .= ' WHERE ' . implode(' AND ', $where_peers); }
$kpi_peers     = dbFetchCell('SELECT COUNT(*) '.$peer_kpi_sql, $peer_params);
$kpi_peers_up  = dbFetchCell('SELECT COUNT(*) '.$peer_kpi_sql . ((!empty($where_peers)) ? ' AND ' : ' WHERE ') . "`state` = 'up'", $peer_params);

// Global summary status panel (modern style like VLAN pages)
// VPN count (distinct names) using AS-scoped SQL
$kpi_vpns = dbFetchCell('SELECT COUNT(DISTINCT `eigrp_vpns`.`eigrp_vpn_name`) '.$sql, $params);

// Interfaces participating (ports) - apply device filter
$ports_where = [];
$ports_params = [];
$ports_where[] = trim(generate_query_permitted_ng(['device'], [ 'device_table' => 'eigrp_ports' ]));
if (!safe_empty($vars['device'])) {
  $ids = is_array($vars['device']) ? $vars['device'] : [ $vars['device'] ];
  $ids = array_map('intval', $ids);
  $ports_where[] = generate_query_values($ids, 'eigrp_ports.device_id');
}
// Apply AS filter to ports KPI
if (is_numeric($vars['asn'])) {
  $ports_where[] = '`eigrp_ports`.`eigrp_as` = ?';
  $ports_params[] = (int)$vars['asn'];
}
// Apply VPN filter via join
$ports_sql = 'SELECT COUNT(*) FROM `eigrp_ports`';
if (!safe_empty($vars['vpnname'])) {
  $ports_sql .= ' LEFT JOIN `eigrp_vpns` USING (`device_id`,`eigrp_vpn`)';
  $ports_where[] = '`eigrp_vpns`.`eigrp_vpn_name` LIKE ?';
  $ports_params[] = '%'.$vars['vpnname'].'%';
}
$kpi_ports = dbFetchCell($ports_sql.(empty($ports_where)?'':' WHERE '.implode(' AND ', $ports_where)), $ports_params);

// High SRTT peers (warning threshold 200ms or user-specified)
$srtt_threshold = is_numeric($vars['srtt']) ? (int)$vars['srtt'] : 200;
$peer_warn_sql = $peer_kpi_sql . ((strpos($peer_kpi_sql, ' WHERE ') !== FALSE) ? ' AND ' : ' WHERE ') . '`peer_srtt` > ?';
$kpi_high_srtt = dbFetchCell('SELECT COUNT(*) '.$peer_warn_sql, array_merge($peer_params, [$srtt_threshold]));

// Flapping peers in last 24h (based on last_change set by poller)
$peer_flap_sql = $peer_kpi_sql . ((strpos($peer_kpi_sql, ' WHERE ') !== FALSE) ? ' AND ' : ' WHERE ') . '`last_change` IS NOT NULL AND `last_change` >= NOW() - INTERVAL 1 DAY';
$kpi_flaps = dbFetchCell('SELECT COUNT(*) '.$peer_flap_sql, $peer_params);
// SIA events last 24h (from event log, type eigrp-as and message contains 'SIA') - apply device filter
$sia_where = [];
$sia_params = [];
$sia_where[] = "`timestamp` >= NOW() - INTERVAL 1 DAY AND `type` = 'eigrp-as' AND `message` LIKE '%SIA%'";
if (!safe_empty($vars['device'])) {
  $ids = is_array($vars['device']) ? $vars['device'] : [ $vars['device'] ];
  $ids = array_map('intval', $ids);
  $sia_where[] = generate_query_values($ids, 'device_id');
}
$kpi_sia_24h = (int)dbFetchCell("SELECT COUNT(*) FROM `eventlog` WHERE ".implode(' AND ', $sia_where), $sia_params);

$status_boxes = [];
// 1. EIGRP Devices
$status_boxes[] = [ 'title' => 'EIGRP Devices', 'value' => [ 'text' => (string)$kpi_devices, 'class' => ($kpi_devices > 0 ? 'label-primary' : 'label-default') ], 'subtitle' => 'Running EIGRP' ];
// 2. AS Instances
$status_boxes[] = [ 'title' => 'AS Instances', 'value' => [ 'text' => (string)$kpi_ases, 'class' => ($kpi_ases > 0 ? 'label-primary' : 'label-default') ], 'subtitle' => 'Autonomous Systems' ];
// 3. VPN Networks
$status_boxes[] = [ 'title' => 'VPN Networks', 'value' => [ 'text' => (string)$kpi_vpns, 'class' => ($kpi_vpns > 0 ? 'label-info' : 'label-default') ], 'subtitle' => 'Virtual Networks' ];
// 4. EIGRP Ports
$status_boxes[] = [ 'title' => 'EIGRP Ports', 'value' => [ 'text' => (string)$kpi_ports, 'class' => ($kpi_ports > 0 ? 'label-info' : 'label-default') ], 'subtitle' => 'Participating Interfaces' ];
// 5. Peer health - up vs total with percentage
$peer_health_pct = ($kpi_peers > 0) ? round(($kpi_peers_up / $kpi_peers) * 100) : 0;
$peer_health_class = ($peer_health_pct >= 95) ? 'label-success' : (($peer_health_pct >= 80) ? 'label-warning' : 'label-danger');
$status_boxes[] = [ 'title' => 'Peer Health', 'value' => [ 'text' => $kpi_peers_up.' / '.$kpi_peers.' ('.$peer_health_pct.'%)', 'class' => $peer_health_class ], 'subtitle' => 'Active / Total Peers' ];
// 6. Recent Problems - downs in last hour
$peer_down_sql = $peer_kpi_sql . ((strpos($peer_kpi_sql, ' WHERE ') !== FALSE) ? ' AND ' : ' WHERE ') . "`state` = 'down' AND `last_seen` >= NOW() - INTERVAL 1 HOUR";
$kpi_down1h = (int)dbFetchCell('SELECT COUNT(*) '.$peer_down_sql, $peer_params);
$status_boxes[] = [ 'title' => 'Recent Downs', 'value' => [ 'text' => (string)$kpi_down1h, 'class' => ($kpi_down1h > 0 ? 'label-danger' : 'label-success') ], 'subtitle' => 'Last Hour' ];
// 7. Performance Issues - high latency peers
$status_boxes[] = [ 'title' => 'High Latency', 'value' => [ 'text' => (string)$kpi_high_srtt, 'class' => ($kpi_high_srtt > 0 ? 'label-warning' : 'label-success') ], 'subtitle' => 'SRTT > '.$srtt_threshold.'ms' ];
// 8. Stability Issues - flapping peers
$status_boxes[] = [ 'title' => 'Flapping', 'value' => [ 'text' => (string)$kpi_flaps, 'class' => ($kpi_flaps > 0 ? 'label-warning' : 'label-success') ], 'subtitle' => 'Last 24 Hours' ];

echo generate_status_panel($status_boxes);

// Search/filter form (applies to Instances and Peers)
$form = [ 'type' => 'rows', 'submit_by_key' => TRUE, 'url' => generate_url($vars) ];
$form['row'][0]['device']  = [ 'type' => 'multiselect', 'name' => 'Device', 'grid' => 2, 'width' => '100%', 'value' => $vars['device'], 'values' => generate_form_values('device') ];
$form['row'][0]['vpnname'] = [ 'type' => 'text', 'name' => 'VPN name',      'grid' => 2, 'placeholder' => TRUE, 'value' => $vars['vpnname'] ];
$form['row'][0]['asn']     = [ 'type' => 'text', 'name' => 'AS',            'grid' => 2, 'placeholder' => TRUE, 'value' => $vars['asn'] ];
$form['row'][0]['srtt']    = [ 'type' => 'text', 'name' => 'SRTT > (ms)',   'grid' => 1, 'width' => '100%', 'placeholder' => TRUE, 'value' => $vars['srtt'] ];

$form['row'][0]['state']   = [ 'type' => 'select', 'name' => 'State',       'grid' => 1, 'width' => '100%', 'value' => $vars['state'], 'values' => [ '' => 'Any', 'up' => 'Up', 'down' => 'Down' ] ];

$form['row'][0]['qonly']   = [ 'type' => 'toggle', 'label' => 'Queue>0',      'grid' => 1, 'onchange_submit' => TRUE, 'value' => (int)get_var_true($vars['qonly']) ];
$form['row'][0]['flap24']  = [ 'type' => 'toggle', 'label' => 'Flapping 24h', 'grid' => 2, 'onchange_submit' => TRUE, 'value' => (int)get_var_true($vars['flap24']) ];
$form['row'][0]['search']  = [ 'type' => 'submit',                                'grid' => 1, 'right' => TRUE ];
$form['row'][0]['view']    = [ 'type' => 'hidden', 'value' => $vars['view'] ?: 'overview' ];

#echo generate_box_open();
print_form($form);
#echo generate_box_close();

// View routing
$view = $vars['view'] ?: 'overview';

if ($view === 'overview') {
  // AS/VRF Health matrix (top 25 by severity)
  echo generate_box_open(['title' => 'AS/VRF Health']);
  echo '<table class="table table-striped table-condensed">';
  echo '<thead><tr><th class="state-marker"></th><th>Device</th><th>VPN</th><th>AS</th><th>Peers Up</th><th>Down (1h)</th><th>Flaps (24h)</th><th>SIA Routes</th><th>Routes Int/Ext</th><th>Worst SRTT</th></tr></thead><tbody>';
  $health_sql  = 'SELECT `eigrp_ases`.*, `eigrp_vpns`.`eigrp_vpn_name`, `devices`.`hostname`, ';
  // Derive worst_srtt and problem counts to avoid schema dependencies
  $health_sql .= 'COALESCE((SELECT MAX(`peer_srtt`) FROM `eigrp_peers` ep '
               . 'WHERE ep.`device_id` = `eigrp_ases`.`device_id` AND ep.`eigrp_vpn` = `eigrp_ases`.`eigrp_vpn` AND ep.`eigrp_as` = `eigrp_ases`.`eigrp_as`), 0) AS worst_srtt, ';
  $health_sql .= '(SELECT COUNT(*) FROM `eigrp_peers` ed '
               . 'WHERE ed.`device_id` = `eigrp_ases`.`device_id` AND ed.`eigrp_vpn` = `eigrp_ases`.`eigrp_vpn` AND ed.`eigrp_as` = `eigrp_ases`.`eigrp_as` '
               . 'AND ed.`state` = "down" AND ed.`last_seen` >= NOW() - INTERVAL 1 HOUR) AS down_recent_calc, ';
  $health_sql .= '(SELECT COUNT(*) FROM `eigrp_peers` ef '
               . 'WHERE ef.`device_id` = `eigrp_ases`.`device_id` AND ef.`eigrp_vpn` = `eigrp_ases`.`eigrp_vpn` AND ef.`eigrp_as` = `eigrp_ases`.`eigrp_as` '
               . 'AND ef.`last_change` IS NOT NULL AND ef.`last_change` >= NOW() - INTERVAL 1 DAY) AS flaps24_calc, ';
  $health_sql .= 'COALESCE(`eigrp_ases`.`sia_routes`, `eigrp_ases`.`cEigrpStuckInActiveCount`, 0) AS sia_routes_eff ';
  $health_sql .= $sql; // reuse AS-scoped FROM/JOIN/WHERE
  $health_sql .= ' ORDER BY down_recent_calc DESC, sia_routes_eff DESC, flaps24_calc DESC, worst_srtt DESC LIMIT 25';
  foreach (dbFetchRows($health_sql, $params) as $as) {
    $dev = device_by_id_cache($as['device_id']);
    $down_recent_chk = isset($as['peers_down_recent']) ? (int)$as['peers_down_recent'] : (int)$as['down_recent_calc'];
    $flaps24_chk     = isset($as['peers_flapping_24h']) ? (int)$as['peers_flapping_24h'] : (int)$as['flaps24_calc'];
    $sia_chk         = isset($as['sia_routes']) ? (int)$as['sia_routes'] : (isset($as['sia_routes_eff']) ? (int)$as['sia_routes_eff'] : 0);
    $row_state = 'success';
    if ($down_recent_chk > 0) { $row_state = 'error'; }
    else if ($flaps24_chk > 0 || $sia_chk > 0 || (int)$as['worst_srtt'] > 300) { $row_state = 'warning'; }
    echo '<tr class="'.$row_state.'">';
    echo '<td class="state-marker"></td>';
    echo '<td class="entity">'.generate_device_link($dev, NULL, ['tab'=>'routing','proto'=>'eigrp']).'</td>';
    echo '<td>'.escape_html($as['eigrp_vpn_name']).'</td>';
    echo '<td>'.(int)$as['eigrp_as'].'</td>';
    $peers_link = generate_url([ 'page' => 'device', 'device' => $as['device_id'], 'tab' => 'routing', 'proto' => 'eigrp', 'view' => 'peers', 'vpn' => $as['eigrp_vpn'], 'asn' => $as['eigrp_as'] ]);
    $down_link  = generate_url([ 'page' => 'device', 'device' => $as['device_id'], 'tab' => 'routing', 'proto' => 'eigrp', 'view' => 'peers', 'vpn' => $as['eigrp_vpn'], 'asn' => $as['eigrp_as'], 'state' => 'down' ]);
    echo '<td><a href="'.$peers_link.'">'.(int)$as['peers_up'].'</a></td>';
    $down_recent = isset($as['peers_down_recent']) ? (int)$as['peers_down_recent'] : (int)$as['down_recent_calc'];
    $flaps24     = isset($as['peers_flapping_24h']) ? (int)$as['peers_flapping_24h'] : (int)$as['flaps24_calc'];
    echo '<td><a href="'.$down_link.'">'.$down_recent.'</a></td>';
    echo '<td>'.$flaps24.'</td>';
    $sia_routes = isset($as['sia_routes']) ? (int)$as['sia_routes'] : (isset($as['sia_routes_eff']) ? (int)$as['sia_routes_eff'] : (int)$as['cEigrpStuckInActiveCount']);
    echo '<td>'.$sia_routes.'</td>';
    echo '<td>'.(int)$as['routes_int'].' / '.(int)$as['routes_ext'].'</td>';
    echo '<td>'.(int)$as['worst_srtt'].' ms</td>';
    echo '</tr>';
  }
  echo '</tbody></table>';
  echo generate_box_close();

  // (Top 3 panels queries moved below to use proper filtering)

  // Top peers by SRTT (now) - using filtered peer parameters
  echo generate_box_open(['title' => 'Top Peers by SRTT (now)']);
  $top_peer_sql  = 'SELECT `eigrp_peers`.*, `eigrp_vpns`.`eigrp_vpn_name`, `devices`.`hostname` FROM `eigrp_peers` ';
  $top_peer_sql .= 'LEFT JOIN `eigrp_vpns` USING (`device_id`,`eigrp_vpn`) LEFT JOIN `devices` USING (`device_id`) ';
  if (!empty($where_peers)) { $top_peer_sql .= ' WHERE '.implode(' AND ', $where_peers); }
  $top_peer_sql .= ' ORDER BY `peer_srtt` DESC LIMIT 10';
  echo '<table class="table table-condensed table-striped">';
  echo '<thead><tr><th class="state-marker"></th><th>Device</th><th>VPN</th><th>AS</th><th>Peer</th><th>Local If</th><th>SRTT</th><th>RTO</th><th>Q</th></tr></thead><tbody>';
  foreach (dbFetchRows($top_peer_sql, $peer_params) as $p) {
    $dev   = device_by_id_cache($p['device_id']);
    $lport = get_port_by_ifIndex($p['device_id'], $p['peer_ifindex']);

    // Determine row class based on SRTT performance
    $srtt = (int)$p['peer_srtt'];
    $row_class = '';
    if ($srtt > 800) {
      $row_class = 'error';
    } elseif ($srtt > 300) {
      $row_class = 'warning';
    } else {
      $row_class = 'ok';
    }

    echo '<tr class="'.$row_class.'">';
    echo '<td class="state-marker"></td>';
    echo '<td class="entity">'.generate_device_link($dev, NULL, ['tab'=>'routing','proto'=>'eigrp','view'=>'overview','vpn'=>$p['eigrp_vpn'],'asn'=>$p['eigrp_as']]).'</td>';
    echo '<td>'.escape_html($p['eigrp_vpn_name']).'</td>';
    echo '<td>'.(int)$p['eigrp_as'].'</td>';
    echo '<td>'.escape_html($p['peer_addr']).'</td>';
    echo '<td class="entity">'.(is_array($lport) ? generate_port_link($lport) : 'Unknown').'</td>';
    echo '<td>'.$srtt.' ms</td>';
    echo '<td>'.(int)$p['peer_rto'].' ms</td>';
    echo '<td>'.(int)$p['peer_qcount'].'</td>';
    echo '</tr>';
  }
  echo '</tbody></table>';
  echo generate_box_close();

  // Optional: three summary panels moved below for a cleaner overview
  // Recompute Top devices/instances and VPN distribution using filtered parameters
  $top_dev_sql  = 'SELECT `eigrp_peers`.`device_id`, COUNT(*) AS peers FROM `eigrp_peers`';
  $top_dev_sql .= ' LEFT JOIN `eigrp_vpns` USING (`device_id`,`eigrp_vpn`)';
  $top_dev_where = [];
  $top_dev_params = [];
  $top_dev_where[] = trim(generate_query_permitted_ng(['device'], [ 'device_table' => 'eigrp_peers' ]));
  if (!safe_empty($vars['device'])) {
    $ids = is_array($vars['device']) ? $vars['device'] : [ $vars['device'] ];
    $ids = array_map('intval', $ids);
    $top_dev_where[] = generate_query_values($ids, 'eigrp_peers.device_id');
  }
  if (is_numeric($vars['asn'])) { $top_dev_where[] = '`eigrp_peers`.`eigrp_as` = ?'; $top_dev_params[] = (int)$vars['asn']; }
  if (!safe_empty($vars['vpnname'])) { $top_dev_where[] = '`eigrp_vpns`.`eigrp_vpn_name` LIKE ?'; $top_dev_params[] = '%'.$vars['vpnname'].'%'; }
  $top_dev_sql .= ' WHERE ' . implode(' AND ', $top_dev_where);
  $top_dev_sql .= ' GROUP BY `device_id` ORDER BY peers DESC LIMIT 10';
  $top_devices = dbFetchRows($top_dev_sql, $top_dev_params);

  $top_as_sql  = 'SELECT `eigrp_ases`.*, `eigrp_vpns`.`eigrp_vpn_name` FROM `eigrp_ases` ';
  $top_as_sql .= 'LEFT JOIN `eigrp_vpns` USING (`device_id`,`eigrp_vpn`) ';
  $top_as_sql .= 'LEFT JOIN `devices` ON `devices`.`device_id` = `eigrp_ases`.`device_id` ';
  if (!empty($where_ases)) { $top_as_sql .= ' WHERE ' . implode(' AND ', $where_ases); }
  $top_as_sql .= ' ORDER BY `cEigrpNbrCount` DESC LIMIT 10';
  $top_instances = dbFetchRows($top_as_sql, $params);

  $dist_sql  = 'SELECT `eigrp_vpns`.`eigrp_vpn_name`, COUNT(*) AS instances FROM `eigrp_ases` ';
  $dist_sql .= 'LEFT JOIN `eigrp_vpns` USING (`device_id`,`eigrp_vpn`) ';
  $dist_sql .= 'LEFT JOIN `devices` ON `devices`.`device_id` = `eigrp_ases`.`device_id` ';
  if (!empty($where_ases)) { $dist_sql .= ' WHERE ' . implode(' AND ', $where_ases); }
  $dist_sql .= ' GROUP BY `eigrp_vpn_name` ORDER BY instances DESC LIMIT 10';
  $vpn_dist = dbFetchRows($dist_sql, $params);

  echo '<div class="row">';
  // Column 1: Top Devices by peers
  echo '<div class="col-md-4">';
  echo generate_box_open(['title' => 'Top Devices (by Peers)']);
  echo '<table class="table table-condensed table-striped">';
  echo '<thead><tr><th class="state-marker"></th><th>Device</th><th class="text-right">Peers</th></tr></thead><tbody>';
  foreach ($top_devices as $row) {
    $dev = device_by_id_cache($row['device_id']);
    echo '<tr class="ok">';
    echo '<td class="state-marker"></td>';
    echo '<td class="entity">'.generate_device_link($dev, NULL, ['tab'=>'routing','proto'=>'eigrp']).'</td>';
    echo '<td class="text-right">'.(int)$row['peers'].'</td>';
    echo '</tr>';
  }
  if (empty($top_devices)) { echo '<tr><td colspan="3"><em>No data</em></td></tr>'; }
  echo '</tbody></table>';
  echo generate_box_close();
  echo '</div>';

  // Column 2: Top Instances by neighbours
  echo '<div class="col-md-4">';
  echo generate_box_open(['title' => 'Top Instances (by Neighbours)']);
  echo '<table class="table table-condensed table-striped">';
  echo '<thead><tr><th class="state-marker"></th><th>Device</th><th>VPN</th><th>AS</th><th class="text-right">Nbrs</th></tr></thead><tbody>';
  foreach ($top_instances as $as) {
    $dev = device_by_id_cache($as['device_id']);
    echo '<tr class="ok">';
    echo '<td class="state-marker"></td>';
    echo '<td class="entity">'.generate_device_link($dev, NULL, ['tab'=>'routing','proto'=>'eigrp','view'=>'overview','vpn'=>$as['eigrp_vpn'],'asn'=>$as['eigrp_as']]).'</td>';
    echo '<td>'.escape_html($as['eigrp_vpn_name']).'</td>';
    echo '<td>AS'.(int)$as['eigrp_as'].'</td>';
    echo '<td class="text-right">'.(int)$as['cEigrpNbrCount'].'</td>';
    echo '</tr>';
  }
  if (empty($top_instances)) { echo '<tr><td colspan="5"><em>No data</em></td></tr>'; }
  echo '</tbody></table>';
  echo generate_box_close();
  echo '</div>';

  // Column 3: VPN distribution
  echo '<div class="col-md-4">';
  echo generate_box_open(['title' => 'VPN Distribution (Instances)']);
  echo '<table class="table table-condensed table-striped">';
  echo '<thead><tr><th class="state-marker"></th><th>VPN</th><th class="text-right">Instances</th></tr></thead><tbody>';
  foreach ($vpn_dist as $vpn) {
    echo '<tr class="ok">';
    echo '<td class="state-marker"></td>';
    echo '<td>'.escape_html($vpn['eigrp_vpn_name']).'</td>';
    echo '<td class="text-right">'.(int)$vpn['instances'].'</td>';
    echo '</tr>';
  }
  if (empty($vpn_dist)) { echo '<tr><td colspan="3"><em>No data</em></td></tr>'; }
  echo '</tbody></table>';
  echo generate_box_close();
  echo '</div>';
  echo '</div>';
}

if ($view === 'instances') {
  // Pagination: total instances with same filters
  $total_instances = (int)dbFetchCell('SELECT COUNT(*) '.$sql, $params);
  $limit = generate_query_limit($vars, $total_instances);
  $pagination_html = pagination($vars, $total_instances);

  $as_rows = dbFetchRows('SELECT `eigrp_ases`.*, `eigrp_vpns`.`eigrp_vpn_name` '.$sql.' ORDER BY `devices`.`hostname`,`eigrp_vpn_name`,`eigrp_as`'.$limit, $params);
  echo $pagination_html;
  echo generate_box_open(['title' => 'Instances']);
  echo '<table class="table table-striped table-condensed">';
  echo '<thead><tr><th class="state-marker"></th><th>Device</th><th>VPN</th><th>AS</th><th>Router ID</th><th>Ports</th><th>Peers Up</th><th>Down (1h)</th><th>Routes Int/Ext</th><th>Degraded/Poor</th><th>Flaps (24h)</th></tr></thead><tbody>';
  foreach ($as_rows as $as) {
    $dev = device_by_id_cache($as['device_id']);
    $port_count = dbFetchCell('SELECT COUNT(*) FROM `eigrp_ports` WHERE device_id = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ?', [ $as['device_id'], $as['eigrp_vpn'], $as['eigrp_as'] ]);

    // Determine row class based on instance health
    $degraded = (int)dbFetchCell('SELECT COUNT(*) FROM `eigrp_peers` WHERE `device_id` = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ? AND (`peer_srtt` > 300 OR `peer_rto` > 800)', [ $as['device_id'], $as['eigrp_vpn'], $as['eigrp_as'] ]);
    $flaps24  = (int)dbFetchCell('SELECT COUNT(*) FROM `eigrp_peers` WHERE `device_id` = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ? AND `last_change` IS NOT NULL AND `last_change` >= NOW() - INTERVAL 1 DAY', [ $as['device_id'], $as['eigrp_vpn'], $as['eigrp_as'] ]);
    $down_recent = (int)$as['peers_down_recent'];

    $row_class = '';
    if ($down_recent > 0) {
      $row_class = 'error';
    } elseif ($flaps24 > 0 || $degraded > 0) {
      $row_class = 'warning';
    } else {
      $row_class = 'ok';
    }

    echo '<tr class="'.$row_class.'">';
    echo '<td class="state-marker"></td>';
    echo '<td class="entity">'.generate_device_link($dev, NULL, ['tab'=>'routing','proto'=>'eigrp','view'=>'overview','vpn'=>$as['eigrp_vpn'],'asn'=>$as['eigrp_as']]).'</td>';
    echo '<td>'.escape_html($as['eigrp_vpn_name']).'</td>';
    echo '<td>'.(int)$as['eigrp_as'].'</td>';
    echo '<td>'.escape_html($as['cEigrpAsRouterId']).'</td>';
    echo '<td>'.(int)$port_count.'</td>';
    echo '<td>'.(int)$as['peers_up'].'</td>';
    echo '<td>'.(int)$as['peers_down_recent'].'</td>';
    echo '<td>'.(int)$as['routes_int'].' / '.(int)$as['routes_ext'].'</td>';
    $degraded = (int)dbFetchCell('SELECT COUNT(*) FROM `eigrp_peers` WHERE `device_id` = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ? AND (`peer_srtt` > 300 OR `peer_rto` > 800)', [ $as['device_id'], $as['eigrp_vpn'], $as['eigrp_as'] ]);
    $flaps24  = (int)dbFetchCell('SELECT COUNT(*) FROM `eigrp_peers` WHERE `device_id` = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ? AND `last_change` IS NOT NULL AND `last_change` >= NOW() - INTERVAL 1 DAY', [ $as['device_id'], $as['eigrp_vpn'], $as['eigrp_as'] ]);
    $deg_class = ($degraded > 0 ? 'label-warning' : 'label-success');
    $flp_class = ($flaps24 > 0 ? 'label-warning' : 'label-success');
    echo '<td><span class="label '.$deg_class.'">'.$degraded.'</span></td>';
    echo '<td><span class="label '.$flp_class.'">'.$flaps24.'</span></td>';
    echo '</tr>';
  }
  if (empty($as_rows)) { echo '<tr><td colspan="11"><em>No instances found</em></td></tr>'; }
  echo '</tbody></table>';
  echo generate_box_close();
  echo $pagination_html;
}

if ($view === 'problems') {
  // Problems view: peers with issues (Down within 1h, Flapping within 24h, High SRTT/RTO, Queue > 0)
  $threshold_srtt = is_numeric($vars['srtt']) ? (int)$vars['srtt'] : 300;
  $threshold_rto  = is_numeric($vars['rto'])  ? (int)$vars['rto']  : 800;
  $problems_sql  = 'SELECT `eigrp_peers`.*, `eigrp_vpns`.`eigrp_vpn_name`, `devices`.`hostname`, 
                           CASE 
                             WHEN `state` = "down" AND `last_seen` >= NOW() - INTERVAL 1 HOUR THEN "Down"
                             WHEN `last_change` IS NOT NULL AND `last_change` >= NOW() - INTERVAL 1 DAY THEN "Flapping"
                             WHEN `peer_srtt` > ? OR `peer_rto` > ? THEN "High Latency"
                             WHEN `peer_qcount` > 0 THEN "Queue"
                             ELSE "Unknown"
                           END AS issue_type
                    FROM `eigrp_peers`
                    LEFT JOIN `eigrp_vpns` USING (`device_id`,`eigrp_vpn`)
                    LEFT JOIN `devices` USING (`device_id`)';
  $where_issue = [];
  if (!empty($where_peers)) { $where_issue[] = implode(' AND ', $where_peers); }
  $where_issue[] = '(
                      (`state` = "down" AND `last_seen` >= NOW() - INTERVAL 1 HOUR)
                   OR (`last_change` IS NOT NULL AND `last_change` >= NOW() - INTERVAL 1 DAY)
                   OR (`peer_srtt` > ? OR `peer_rto` > ?)
                   OR (`peer_qcount` > 0)
                   )';
  $problems_params = array_merge($peer_params, [ $threshold_srtt, $threshold_rto, $threshold_srtt, $threshold_rto ]);
  $problems_sql .= ' WHERE ' . implode(' AND ', $where_issue) . ' ORDER BY `devices`.`hostname`,`eigrp_vpn_name`,`eigrp_as`,`issue_type`';

  echo generate_box_open(['title' => 'Problems']);
  echo '<table class="table table-striped table-condensed">';
  echo '<thead><tr><th class="state-marker"></th><th>Device</th><th>VPN</th><th>AS</th><th>Peer</th><th>Local If</th><th>Issue</th><th>Since</th></tr></thead><tbody>';
  foreach (dbFetchRows($problems_sql, $problems_params) as $row) {
    $dev   = device_by_id_cache($row['device_id']);
    $lport = get_port_by_ifIndex($row['device_id'], $row['peer_ifindex']);
    $since = '';
    if ($row['issue_type'] === 'Down')      { $since = format_timestamp($row['last_seen']); }
    elseif ($row['issue_type'] === 'Flapping') { $since = format_timestamp($row['last_change']); }
    else { $since = format_uptime($row['peer_uptime']); }

    // Determine row class based on issue severity
    $row_class = '';
    if ($row['issue_type'] === 'Down') {
      $row_class = 'error';
    } elseif ($row['issue_type'] === 'Flapping') {
      $row_class = 'warning';
    } else {
      $row_class = 'info';
    }

    echo '<tr class="'.$row_class.'">';
    echo '<td class="state-marker"></td>';
    echo '<td class="entity">'.generate_device_link($dev, NULL, ['tab'=>'routing','proto'=>'eigrp','view'=>'overview','vpn'=>$row['eigrp_vpn'],'asn'=>$row['eigrp_as']]).'</td>';
    echo '<td>'.escape_html($row['eigrp_vpn_name']).'</td>';
    echo '<td>'.(int)$row['eigrp_as'].'</td>';
    echo '<td>'.escape_html($row['peer_addr']).'</td>';
    echo '<td class="entity">'.(is_array($lport) ? generate_port_link($lport) : 'Unknown').'</td>';
    echo '<td><span class="label '.($row['issue_type']==='Down'?'label-danger':($row['issue_type']==='Flapping'?'label-warning':'label-info')).'">'.escape_html($row['issue_type']).'</span></td>';
    echo '<td>'.escape_html($since).'</td>';
    echo '</tr>';
  }
  echo '</tbody></table>';
  echo generate_box_close();
}

if ($view === 'peers') {
  // Build filters for peers query and total count
  $where_ex = $where_peers;
  $peer_params_ex = $peer_params;
  if ($vars['state'] === 'up' || $vars['state'] === 'down') {
    $where_ex[] = '`eigrp_peers`.`state` = ?';
    $peer_params_ex[] = $vars['state'];
  }
  if (get_var_true($vars['qonly'])) {
    $where_ex[] = '`eigrp_peers`.`peer_qcount` > 0';
  }
  if (is_numeric($vars['srtt'])) {
    $where_ex[] = '`eigrp_peers`.`peer_srtt` > ?';
    $peer_params_ex[] = (int)$vars['srtt'];
  }
  if (get_var_true($vars['flap24'])) {
    $where_ex[] = '`eigrp_peers`.`last_change` IS NOT NULL AND `eigrp_peers`.`last_change` >= NOW() - INTERVAL 1 DAY';
  }

  // Pagination for peers with filters
  $count_sql  = 'SELECT COUNT(*) FROM `eigrp_peers` LEFT JOIN `eigrp_vpns` USING (`device_id`,`eigrp_vpn`) ';
  if (!empty($where_ex)) { $count_sql .= ' WHERE ' . implode(' AND ', $where_ex); }
  $total_peers = (int)dbFetchCell($count_sql, $peer_params_ex);
  $peer_limit  = generate_query_limit($vars, $total_peers);
  $peer_pagination = pagination($vars, $total_peers);

  echo $peer_pagination;
  echo generate_box_open(['title' => 'Peers']);
  echo '<table class="table table-striped table-condensed">';
  echo '<thead><tr><th class="state-marker"></th><th>Device</th><th>VPN</th><th>AS</th><th>Peer</th><th>Local If</th><th>State</th><th>Q</th><th>Uptime</th><th>SRTT</th><th>RTO</th><th>Last Change</th><th>Down Since</th><th>Health</th><th>Version</th></tr></thead><tbody>';
  // Build peers query with peers-scoped WHERE
  $peer_sql  = 'SELECT `eigrp_peers`.*, `eigrp_vpns`.`eigrp_vpn_name`, `devices`.`hostname` FROM `eigrp_peers` ';
  $peer_sql .= 'LEFT JOIN `eigrp_vpns` USING (`device_id`,`eigrp_vpn`) LEFT JOIN `devices` USING (`device_id`) ';
  if (!empty($where_ex)) { $peer_sql .= 'WHERE '.implode(' AND ', $where_ex); }
  $peer_sql .= ' ORDER BY `devices`.`hostname`,`eigrp_vpn_name`,`eigrp_as`,`peer_addr`'.$peer_limit;
  foreach (dbFetchRows($peer_sql, $peer_params_ex) as $p) {
    $dev   = device_by_id_cache($p['device_id']);
    $lport = get_port_by_ifIndex($p['device_id'], $p['peer_ifindex']);

    // Determine row class based on peer state and health
    $srtt = (int)$p['peer_srtt'];
    $rto  = (int)$p['peer_rto'];
    $row_class = '';
    if ($p['state'] === 'down') {
      $row_class = 'error';
    } elseif ($srtt > 800 || $rto > 2000) {
      $row_class = 'error';
    } elseif ($srtt > 300 || $rto > 800) {
      $row_class = 'warning';
    } else {
      $row_class = 'ok';
    }

    echo '<tr class="'.$row_class.'">';
    echo '<td class="state-marker"></td>';
    // Deep-link to the device EIGRP view with the same VPN/AS context
    echo '<td class="entity">'.generate_device_link($dev, NULL, ['tab'=>'routing','proto'=>'eigrp','view'=>'overview','vpn'=>$p['eigrp_vpn'],'asn'=>$p['eigrp_as']]).'</td>';
    echo '<td>'.escape_html($p['eigrp_vpn_name']).'</td>';
    echo '<td>'.(int)$p['eigrp_as'].'</td>';
    echo '<td>'.escape_html($p['peer_addr']).'</td>';
    echo '<td class="entity">'.(is_array($lport) ? generate_port_link($lport) : 'Unknown').'</td>';
    $state_label = $p['state'] === 'down' ? '<span class="label label-danger">Down</span>' : '<span class="label label-success">Up</span>';
    echo '<td>'.$state_label.'</td>';
    echo '<td>'.(int)$p['peer_qcount'].'</td>';
    echo '<td>'.format_uptime($p['peer_uptime']).'</td>';
    $health_text = 'Good'; $health_class = 'label-success';
    if ($srtt > 300 || $rto > 800) { $health_text = 'Degraded'; $health_class = 'label-warning'; }
    if ($srtt > 800 || $rto > 2000) { $health_text = 'Poor'; $health_class = 'label-danger'; }
    echo '<td>'.$srtt.' ms</td>';
    echo '<td>'.$rto.' ms</td>';
    echo '<td>'.(!safe_empty($p['last_change']) ? format_timestamp($p['last_change']) : '').'</td>';
    echo '<td>'.(!safe_empty($p['down_since']) ? format_timestamp($p['down_since']) : '').'</td>';
    echo '<td><span class="label '.$health_class.'">'.$health_text.'</span></td>';
    echo '<td>'.escape_html($p['peer_version']).'</td>';
    echo '</tr>';
  }
  echo '</tbody></table>';
  echo generate_box_close();
  echo $peer_pagination;
}
