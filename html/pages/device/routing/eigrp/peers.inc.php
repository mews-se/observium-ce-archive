<?php
/**
 * Observium - Device EIGRP Peers
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

// Filter box (state, queue, SRTT, flapping)
$form = [ 'type' => 'rows', 'space' => '5px', 'url' => generate_url($vars) ];
$form['row'][0]['state']  = [ 'type' => 'select', 'name' => 'State', 'width' => '100%', 'value' => $vars['state'], 'values' => [ '' => 'Any', 'up' => 'Up', 'down' => 'Down' ] ];
$form['row'][0]['qonly']  = [ 'type' => 'switch', 'name' => 'Q > 0', 'onchange_submit' => TRUE, 'value' => (int)get_var_true($vars['qonly']) ];
$form['row'][0]['srtt']   = [ 'type' => 'text', 'name' => 'SRTT > (ms)', 'width' => '100%', 'placeholder' => TRUE, 'value' => $vars['srtt'] ];
$form['row'][0]['flap24'] = [ 'type' => 'switch', 'name' => 'Flapping (24h)', 'onchange_submit' => TRUE, 'value' => (int)get_var_true($vars['flap24']) ];
echo generate_box_open(['title' => 'Filter']);
print_form($form);
echo generate_box_close();

echo generate_box_open(['title' => 'Peers']);
echo '<table class="table table-striped table-condensed">';
echo '<thead><tr><th class="state-marker"></th><th>Local Port</th><th>Alias</th><th>Peer Address</th><th>Peer Device</th><th>Peer Port</th><th>Peer Alias</th><th>State</th><th>Q</th><th>Uptime</th><th>SRTT</th><th>RTO</th><th>Last Change</th><th>Down Since</th><th>Version</th><th>Details</th></tr></thead><tbody>';

$where = [ 'device_id = ?', 'eigrp_vpn = ?', 'eigrp_as = ?' ];
$params = [ $device['device_id'], $vars['vpn'], $vars['asn'] ];
if ($vars['state'] === 'up' || $vars['state'] === 'down') {
  $where[] = '`state` = ?';
  $params[] = $vars['state'];
}
if (get_var_true($vars['qonly'])) {
  $where[] = '`peer_qcount` > 0';
}
if (is_numeric($vars['srtt'])) {
  $where[] = '`peer_srtt` > ?';
  $params[] = (int)$vars['srtt'];
}
if (get_var_true($vars['flap24'])) {
  $where[] = '`last_change` IS NOT NULL AND `last_change` >= NOW() - INTERVAL 1 DAY';
}

$sql = 'SELECT * FROM `eigrp_peers` WHERE ' . implode(' AND ', $where) . ' ORDER BY `peer_addr`';
$rows = dbFetchRows($sql, $params);

$peer_ids = [];
$peer_addresses = [];
foreach ($rows as $peer_meta) {
  $peer_ids[] = (int)$peer_meta['eigrp_peer_id'];
  if (!safe_empty($peer_meta['peer_addr'])) {
    $peer_addresses[strtolower($peer_meta['peer_addr'])] = TRUE;
  }
}

$peer_events = [];
if ($peer_ids) {
  $placeholders = implode(',', array_fill(0, count($peer_ids), '?'));
  $event_rows = dbFetchRows('SELECT `reference`,`timestamp`,`message` FROM `eventlog` WHERE `device_id` = ? AND `type` = "eigrp-peer" AND `reference` IN (' . $placeholders . ') ORDER BY `timestamp` DESC', array_merge([ $device['device_id'] ], $peer_ids));
  foreach ($event_rows as $event_row) {
    $ref = (int)$event_row['reference'];
    if (!isset($peer_events[$ref])) {
      $peer_events[$ref] = $event_row;
    }
  }
}

$bfd_map = [];
if ($peer_addresses) {
  $addr_placeholders = implode(',', array_fill(0, count($peer_addresses), '?'));
  $bfd_rows = dbFetchRows('SELECT * FROM `bfd_sessions` WHERE `device_id` = ? AND LOWER(`bfd_remote_address`) IN (' . $addr_placeholders . ')', array_merge([ $device['device_id'] ], array_keys($peer_addresses)));
  foreach ($bfd_rows as $bfd_row) {
    $addr = strtolower((string)$bfd_row['bfd_remote_address']);
    if ($addr !== '') { $bfd_map[$addr] = $bfd_row; }
  }
}

foreach ($rows as $peer) {
  $local_port = get_port_by_ifIndex($device['device_id'], $peer['peer_ifindex']);

  // Try to map peer address back to a known port/device (IPv4 only here)
  $peer_dev  = NULL;
  $peer_port = NULL;
  if (strpos($peer['peer_addr'], ':') === FALSE) {
    $ip = dbFetchRow("SELECT * FROM `ipv4_addresses` WHERE `ipv4_address` = ?", [ $peer['peer_addr'] ]);
    if ($ip) {
      $peer_port = get_port_by_id_cache($ip['port_id']);
      if (is_array($peer_port)) { $peer_dev = device_by_id_cache($peer_port['device_id']); }
    }
  }

  $row_class = ($peer['state'] === 'down') ? 'error' : 'success';
  echo '<tr class="'.$row_class.'">';
  echo '<td class="state-marker"></td>';
  $detail_id = 'peer-detail-' . (int)$peer['eigrp_peer_id'];
  echo '<td>'.(is_array($local_port) ? generate_port_link($local_port) : 'Unknown').'</td>';
  echo '<td>'.(is_array($local_port) ? escape_html($local_port['ifAlias']) : '').'</td>';
  echo '<td>'.escape_html($peer['peer_addr']).'</td>';
  echo '<td>'.(is_array($peer_dev) ? generate_device_link($peer_dev) : '').'</td>';
  echo '<td>'.(is_array($peer_port) ? generate_port_link($peer_port) : '').'</td>';
  echo '<td>'.(is_array($peer_port) ? escape_html($peer_port['ifAlias']) : '').'</td>';
  $state_label = $peer['state'] === 'down' ? '<span class="label label-danger">Down</span>' : '<span class="label label-success">Up</span>';
  echo '<td>'.$state_label.'</td>';
  echo '<td>'.(int)$peer['peer_qcount'].'</td>';
  echo '<td>'.format_uptime($peer['peer_uptime']).'</td>';
  echo '<td>'.(int)$peer['peer_srtt'].' ms</td>';
  echo '<td>'.(int)$peer['peer_rto'].' ms</td>';
  echo '<td>'.(!safe_empty($peer['last_change']) ? format_timestamp($peer['last_change']) : '').'</td>';
  echo '<td>'.(!safe_empty($peer['down_since']) ? format_timestamp($peer['down_since']) : '').'</td>';
  echo '<td>'.escape_html($peer['peer_version']).'</td>';
  echo '<td><a href="#" class="btn btn-xs btn-default" onclick="togglePeerDetail(\''.$detail_id.'\'); return false;">Details</a></td>';
  echo '</tr>';

  $event = $peer_events[$peer['eigrp_peer_id']] ?? NULL;
  $bfd   = NULL;
  if (!safe_empty($peer['peer_addr'])) {
    $addr_key = strtolower($peer['peer_addr']);
    if (isset($bfd_map[$addr_key])) { $bfd = $bfd_map[$addr_key]; }
  }

  echo '<tr id="'.$detail_id.'" class="peer-detail-row" style="display:none;"><td colspan="16">';
  echo '<div class="row">';
  echo '<div class="col-md-4"><dl class="dl-horizontal dl-condensed">';
  echo '<dt>Address Type</dt><dd>'.escape_html(nicecase($peer['peer_addrtype'])).'</dd>';
  echo '<dt>Hold Time</dt><dd>'.(int)$peer['peer_holdtime'].' s</dd>';
  echo '<dt>First Seen</dt><dd>'.(!safe_empty($peer['first_seen']) ? format_timestamp($peer['first_seen']) : '—').'</dd>';
  echo '<dt>Last Seen</dt><dd>'.(!safe_empty($peer['last_seen']) ? format_timestamp($peer['last_seen']) : '—').'</dd>';
  echo '<dt>Last Change</dt><dd>'.(!safe_empty($peer['last_change']) ? format_timestamp($peer['last_change']) : '—').'</dd>';
  echo '<dt>Down Since</dt><dd>'.(!safe_empty($peer['down_since']) ? format_timestamp($peer['down_since']) : '—').'</dd>';
  echo '</dl></div>';

  echo '<div class="col-md-4"><dl class="dl-horizontal dl-condensed">';
  echo '<dt>Queue Depth</dt><dd>'.(int)$peer['peer_qcount'].'</dd>';
  echo '<dt>Queue Streak</dt><dd>'.(int)$peer['bad_q_consec'].' polls</dd>';
  echo '<dt>SRTT Baseline</dt><dd>'.(int)$peer['srtt_baseline'].' ms</dd>';
  echo '<dt>Last Seq</dt><dd>'.escape_html($peer['last_seq']).'</dd>';
  echo '<dt>Retransmissions</dt><dd>'.escape_html($peer['retrans']).'</dd>';
  echo '<dt>Retries</dt><dd>'.escape_html($peer['retries']).'</dd>';
  if ($event) {
    echo '<dt>Last Event</dt><dd>'.format_timestamp($event['timestamp']).'<br>'.escape_html($event['message']).'</dd>';
  }
  echo '</dl></div>';

  echo '<div class="col-md-4"><dl class="dl-horizontal dl-condensed">';
  if (is_array($local_port)) {
    echo '<dt>Interface Status</dt><dd>'.escape_html($local_port['ifAdminStatus']).' / '.escape_html($local_port['ifOperStatus']).'</dd>';
    if (!safe_empty($local_port['ifAlias'])) {
      echo '<dt>Description</dt><dd>'.escape_html($local_port['ifAlias']).'</dd>';
    }
  }
  if ($bfd) {
    echo '<dt>BFD State</dt><dd>'.escape_html($bfd['bfd_oper_status']).' (admin '.escape_html($bfd['bfd_admin_status']).')</dd>';
    echo '<dt>BFD Remote Heard</dt><dd>'.(get_var_true($bfd['bfd_remote_heard']) ? 'Yes' : 'No').'</dd>';
    if (!safe_empty($bfd['bfd_last_down'])) {
      echo '<dt>BFD Last Down</dt><dd>'.format_timestamp($bfd['bfd_last_down']).'</dd>';
    }
    if (!safe_empty($bfd['bfd_last_up'])) {
      echo '<dt>BFD Last Up</dt><dd>'.format_timestamp($bfd['bfd_last_up']).'</dd>';
    }
  } else {
    echo '<dt>BFD</dt><dd>No matching session</dd>';
  }
  echo '</dl></div>';
  echo '</div>';
  echo '</td></tr>';
}

echo '</tbody></table>';
echo generate_box_close();

echo '<script type="text/javascript">function togglePeerDetail(id){var row=document.getElementById(id);if(!row){return;}if(row.style.display==="none"||row.style.display===""){row.style.display="table-row";}else{row.style.display="none";}}</script>';
