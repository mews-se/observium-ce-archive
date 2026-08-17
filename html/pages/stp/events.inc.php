<?php
/**
 * STP Events View
 * 
 * Shows STP-related events from the global event log
 * Filters for STP-specific events across the network
 */

// Filter processing for event log
$where_conditions = ["message LIKE 'STP %' OR message LIKE '%spanning tree%' OR message LIKE '%topology change%'"];
$params = [];

// Time range filter
$time_from = $vars['timestamp_from'] ?? '';
$time_to = $vars['timestamp_to'] ?? '';

if (!empty($time_from)) {
  $where_conditions[] = "timestamp >= ?";
  $params[] = $time_from;
}

if (!empty($time_to)) {
  $where_conditions[] = "timestamp <= ?";
  $params[] = $time_to;
}

// Severity filter - $vars['severity'] is already an array if multiselect
if (!empty($vars['severity'])) {
  $severities = is_array($vars['severity']) ? $vars['severity'] : [$vars['severity']];
  $severity_placeholders = str_repeat('?,', count($severities) - 1) . '?';
  $where_conditions[] = "severity IN ($severity_placeholders)";
  $params = array_merge($params, $severities);
}

// Device filter
if (!empty($vars['device_id'])) {
  $where_conditions[] = "device_id = ?";
  $params[] = (int)$vars['device_id'];
}

// Search filter
if (!empty($vars['search'])) {
  $search = '%' . $vars['search'] . '%';
  $where_conditions[] = "message LIKE ?";
  $params[] = $search;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Main query
$sql = "
  SELECT el.event_id, el.device_id, el.timestamp, el.message, el.severity,
         d.hostname
  FROM eventlog el
  JOIN devices d ON d.device_id = el.device_id
  $where_clause
  ORDER BY el.timestamp DESC";

// Pagination - use Observium standard variable names  
$page_size = (int)($vars['pagesize'] ?? 100);
$page = (int)($vars['pageno'] ?? 1);
$offset = ($page - 1) * $page_size;

$events = dbFetchRows($sql . " LIMIT $offset, $page_size", $params);
$total_count = dbFetchCell("SELECT COUNT(*) FROM eventlog el JOIN devices d ON d.device_id = el.device_id $where_clause", $params);

// Time range defaults (last 7 days if not specified)
if (empty($time_from)) {
  $time_from = date('Y-m-d H:i:s', strtotime('-7 days'));
}
if (empty($time_to)) {
  $time_to = date('Y-m-d H:i:s');
}

// Filter form using Observium's search pattern
$search_form = [
  [
    'type' => 'datetime',
    'id' => 'timestamp',
    'presets' => true,
    'from' => $vars['timestamp_from'] ?? '',
    'to' => $vars['timestamp_to'] ?? ''
  ],
  [
    'type' => 'multiselect',
    'name' => 'Severity',
    'id' => 'severity',
    'width' => '150px',
    'value' => $vars['severity'] ?? '',
    'values' => [
      '0' => 'Emergency',
      '1' => 'Alert', 
      '2' => 'Critical',
      '3' => 'Error',
      '4' => 'Warning',
      '5' => 'Notice',
      '6' => 'Info',
      '7' => 'Debug'
    ]
  ],
  [
    'type' => 'text',
    'name' => 'Search',
    'id' => 'search',
    'width' => '200px',
    'placeholder' => 'Message search...',
    'value' => $vars['search'] ?? ''
  ]
];

print_search($search_form, 'STP Event Filters', 'search', generate_url($vars));

// Results table
echo generate_box_open(['title' => "STP Events ($total_count total)"]);

if (empty($events)) {
  $content = '<div class="box-state-title">No STP Events Found</div>';
  $content .= '<p class="box-state-description">No spanning tree events match the current time range and filter criteria.</p>';

  echo generate_box_state('info', $content, [
    'icon' => $config['icon']['info'],
    'size' => 'medium'
  ]);
} else {

  echo '<table class="table table-striped table-hover table-condensed">';
  echo '<thead><tr>';
  echo '<th style="width: 150px;">Time</th>';
  echo '<th style="width: 200px;">Device</th>';
  echo '<th>Message</th>';
  echo '<th style="width: 80px;">Severity</th>';
  echo '</tr></thead><tbody>';

  foreach ($events as $event) {
    // Severity color mapping
    $severity_classes = [
      0 => 'label-danger',   // Emergency
      1 => 'label-danger',   // Alert
      2 => 'label-danger',   // Critical
      3 => 'label-danger',   // Error
      4 => 'label-warning',  // Warning
      5 => 'label-info',     // Notice
      6 => 'label-info',     // Info
      7 => 'label-default'   // Debug
    ];

    $severity_names = [
      0 => 'Emergency',
      1 => 'Alert',
      2 => 'Critical', 
      3 => 'Error',
      4 => 'Warning',
      5 => 'Notice',
      6 => 'Info',
      7 => 'Debug'
    ];

    $severity = (int)$event['severity'];
    $severity_class = $severity_classes[$severity] ?? 'label-default';
    $severity_name = $severity_names[$severity] ?? 'Unknown';

    // Try to extract domain information from STP messages if possible
    $domain_info = '';
    if (preg_match('/STP.*bridge.*([0-9a-fA-F]{4}\.[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4})/', $event['message'], $matches)) {
      // Try to find domain for this bridge ID
      $bridge_hex = $matches[1];
      $domain_lookup = dbFetchRow("
        SELECT variant, COALESCE(mst_region_name,'') AS region, designated_root 
        FROM stp_bridge 
        WHERE device_id = ? AND designated_root LIKE ?
      ", [$event['device_id'], '%' . str_replace(['.', '-'], '', $bridge_hex) . '%']);

      if ($domain_lookup) {
        $dom_hash = stp_domain_hash($domain_lookup['variant'], $domain_lookup['region'], $domain_lookup['designated_root']);
        $dom_display = stp_domain_display_name($domain_lookup['variant'], $domain_lookup['region'], $domain_lookup['designated_root']);
        $dom_link = generate_url([
          'page' => 'stp',
          'view' => 'domain', 
          'domain_hash' => $dom_hash
        ]);
        $domain_info = ' <small>[<a href="' . $dom_link . '">' . htmlentities($dom_display) . '</a>]</small>';
      }
    }

    echo '<tr>';

    // Time
    echo '<td>' . format_timestamp($event['timestamp']) . '</td>';

    // Device  
    echo '<td class="entity-title">' . generate_device_link(['device_id' => $event['device_id'], 'hostname' => $event['hostname']]) . '</td>';

    // Message with potential domain link
    echo '<td>' . htmlentities($event['message']) . $domain_info . '</td>';

    // Severity
    echo '<td><span class="label ' . $severity_class . '">' . $severity_name . '</span></td>';

    echo '</tr>';
  }

  echo '</tbody></table>';
}

echo generate_box_close();

// Pagination
if ($total_count > $page_size) {
  $vars['pageno'] = $page;
  $vars['pagesize'] = $page_size;
  echo pagination($vars, $total_count);
}

// Recent events summary
$recent_summary = dbFetchRows("
  SELECT 
    d.device_id,
    d.hostname,
    COUNT(*) AS event_count,
    MAX(el.timestamp) AS last_event,
    MIN(el.severity) AS min_severity
  FROM eventlog el
  JOIN devices d ON d.device_id = el.device_id
  WHERE el.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND (el.message LIKE 'STP %' OR el.message LIKE '%spanning tree%' OR el.message LIKE '%topology change%')
  GROUP BY d.device_id, d.hostname
  ORDER BY event_count DESC, last_event DESC
  LIMIT 10
");

if (!empty($recent_summary)) {
  echo generate_box_open(['title' => 'Top Event Sources (Last 24 Hours)']);

  echo '<table class="table table-striped table-condensed">';
  echo '<thead><tr>';
  echo '<th>Device</th>';
  echo '<th>Event Count</th>';
  echo '<th>Last Event</th>';
  echo '<th>Highest Severity</th>';
  echo '</tr></thead><tbody>';

  foreach ($recent_summary as $summary) {
    $severity_class = $summary['min_severity'] <= 3 ? 'text-danger' : ($summary['min_severity'] == 4 ? 'text-warning' : 'text-info');
    $severity_names = [0 => 'Emergency', 1 => 'Alert', 2 => 'Critical', 3 => 'Error', 4 => 'Warning', 5 => 'Notice', 6 => 'Info', 7 => 'Debug'];
    $severity_name = $severity_names[$summary['min_severity']] ?? 'Unknown';

    echo '<tr>';
    echo '<td class="entity-title">' . generate_device_link(['device_id' => $summary['device_id'], 'hostname' => $summary['hostname']]) . '</td>';
    echo '<td>' . (int)$summary['event_count'] . '</td>';
    echo '<td>' . format_timestamp($summary['last_event']) . '</td>';
    echo '<td><span class="' . $severity_class . '">' . $severity_name . '</span></td>';
    echo '</tr>';
  }

  echo '</tbody></table>';
  echo generate_box_close();
}

// EOF