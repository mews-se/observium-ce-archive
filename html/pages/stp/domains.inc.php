<?php
/**
 * STP Domains View - Global STP domain inventory
 * 
 * Shows all STP domains (groups of devices sharing a root bridge)
 * with health indicators and quick access to problem areas
 */

// Filter processing
$where_conditions = [];
$params = [];

// Variant filter - $vars['variant'] is already an array if multiselect
if (!empty($vars['variant'])) {
  $variants = is_array($vars['variant']) ? $vars['variant'] : [$vars['variant']];
  $variant_placeholders = str_repeat('?,', count($variants) - 1) . '?';
  $where_conditions[] = "sb.variant IN ($variant_placeholders)";
  $params = array_merge($params, $variants);
}

// Location filter (reuse Observium's location handling)
if (!empty($vars['location'])) {
  $where_conditions[] = "d.location LIKE ?";
  $params[] = '%' . $vars['location'] . '%';
}

// "Hot only" filter - recent topology changes
if (!empty($vars['hot_only'])) {
  $hot_threshold = 300; // 5 minutes in centiseconds  
  $where_conditions[] = "sb.time_since_tc_cs < ?";
  $params[] = $hot_threshold;
}

// Minimum members filter
$min_members = (int)($vars['min_members'] ?? 1);

// Text search on region name or root bridge ID
if (!empty($vars['search'])) {
  $search = '%' . $vars['search'] . '%';
  $where_conditions[] = "(sb.mst_region_name LIKE ? OR sb.designated_root LIKE ?)";
  $params[] = $search;
  $params[] = $search;
}

// Build WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
  $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Main query - get domains with aggregated data including flaps
$sql = "
SELECT
  sb.domain_hash,
  sb.variant,
  COALESCE(sb.mst_region_name, '') AS region,
  COALESCE(sb.designated_root, '') AS root_hex,
  COUNT(*) AS members,
  MIN(sb.time_since_tc_cs) AS min_tca,
  MAX(sb.updated) AS updated,
  COALESCE((
    SELECT SUM(x.bad_ports) FROM (
      SELECT COUNT(*) AS bad_ports
      FROM stp_ports sp
      JOIN stp_bridge sb2 ON sb2.device_id = sp.device_id
      WHERE sp.state IN ('blocking','discarding','broken')
        AND sb2.domain_hash = sb.domain_hash
      GROUP BY sp.device_id
    ) x
  ),0) AS bad_ports,
  COALESCE((
    SELECT SUM(y.incons) FROM (
      SELECT COUNT(*) AS incons
      FROM stp_ports sp
      JOIN stp_bridge sb3 ON sb3.device_id = sp.device_id
      WHERE sp.inconsistent = 1
        AND sb3.domain_hash = sb.domain_hash
      GROUP BY sp.device_id
    ) y
  ),0) AS inconsistent,
  COALESCE((
    SELECT SUM(z.flaps_5m) FROM (
      SELECT SUM(CASE WHEN sp.transitions_5m=1 THEN 1 ELSE 0 END) AS flaps_5m
      FROM stp_ports sp
      JOIN stp_bridge sb4 ON sb4.device_id = sp.device_id
      WHERE sb4.domain_hash = sb.domain_hash
      GROUP BY sp.device_id
    ) z
  ),0) AS flaps_5m,
  COALESCE((
    SELECT SUM(w.flaps_60m) FROM (
      SELECT SUM(COALESCE(sp.transitions_60m,0)) AS flaps_60m
      FROM stp_ports sp
      JOIN stp_bridge sb5 ON sb5.device_id = sp.device_id
      WHERE sb5.domain_hash = sb.domain_hash
      GROUP BY sp.device_id
    ) w
  ),0) AS flaps_60m
FROM stp_bridge sb
JOIN devices d ON d.device_id = sb.device_id
$where_clause
GROUP BY sb.domain_hash
HAVING members >= ?
ORDER BY (min_tca IS NULL) ASC, min_tca ASC, members DESC";

$params[] = $min_members;

// Pagination - use Observium standard variable names
$page_size = (int)($vars['pagesize'] ?? 50);
$page = (int)($vars['pageno'] ?? 1);
$offset = ($page - 1) * $page_size;

$domains = dbFetchRows($sql . " LIMIT $offset, $page_size", $params);
$total_count = dbFetchCell("SELECT COUNT(*) FROM ($sql) AS total_domains", $params);

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
    'name' => 'Min Members',
    'id' => 'min_members',
    'width' => '100px',
    'value' => $vars['min_members'] ?? 1
  ],
  [
    'type' => 'text',
    'name' => 'Search',
    'id' => 'search',
    'width' => '200px',
    'placeholder' => 'Region or root bridge...',
    'value' => $vars['search'] ?? ''
  ]
];

if (!empty($vars['hot_only'])) {
  $search_form[] = [
    'type' => 'hidden',
    'id' => 'hot_only',
    'value' => $vars['hot_only']
  ];
}

print_search($search_form, 'STP Domain Filters', 'search', generate_url($vars));

// Results table
echo generate_box_open(['title' => "STP Domains ($total_count total)"]);

if (empty($domains)) {
  $content = '<div class="box-state-title">No STP Domains Found</div>';
  $content .= '<p class="box-state-description">No spanning tree domains match the current filter criteria.</p>';

  echo generate_box_state('info', $content, [
    'icon' => $config['icon']['info'],
    'size' => 'medium'
  ]);
} else {

  echo '<table class="table table-striped table-hover table-condensed">';
  echo '<thead><tr>';
  echo '<th class="state-marker"></th>';
  echo '<th>Domain (ID)</th>';
  echo '<th>Root Bridge</th>';
  echo '<th>Members</th>';
  echo '<th>Hot</th>';
  echo '<th>Bad Ports</th>';
  echo '<th>Inconsistent</th>';
  echo '<th>Flaps</th>';
  echo '<th>Updated</th>';
  echo '</tr></thead><tbody>';

  foreach ($domains as $row) {
    // We now use the domain_hash directly from the database, no need to generate it
    $dom_hash = $row['domain_hash'];
    $hot_cs = (int)$row['min_tca'];

    // Format hot indicator using helper function
    $hot_label = stp_format_tc_time($row['min_tca'], 'label');
    $hot_txt = $hot_label['text'];
    $hot_class = $hot_label['class'];

    // Determine row class based on domain health (include flapping)
    $row_class = '';
    $bad_ports = (int)$row['bad_ports'];
    $inconsistent = (int)$row['inconsistent'];
    $flaps_5m = (int)$row['flaps_5m'];
    $flaps_60m = (int)$row['flaps_60m'];

    if ($flaps_5m > 0) {
      $row_class = 'error'; // Currently flapping - highest priority
    } elseif ($inconsistent > 0) {
      $row_class = 'error';
    } elseif ($flaps_60m >= 10) { // High flapping threshold for domain level
      $row_class = 'error';
    } elseif ($bad_ports > 0) {
      $row_class = 'warning';
    } elseif ($flaps_60m >= 5) { // Moderate flapping
      $row_class = 'warning';
    } elseif ($hot_class === 'label-danger') { // Very recent topology change
      $row_class = 'warning';
    } else {
      $row_class = 'ok';
    }

    echo '<tr class="'.$row_class.'">';

    // State marker
    echo '<td class="state-marker"></td>';

    // Domain column with link to detail view - use domain hash for clean URLs
    $domain_url = generate_url([
      'page' => 'stp', 
      'view' => 'domain', 
      'domain_hash' => $row['domain_hash']
    ]);

    // Generate label-group display
    echo '<td>';
    echo '<a class="entity" href="' . $domain_url . '">' . stp_format_domain_labels($row['variant'], $row['region'], $row['root_hex']) . '</a>';
    echo '<br><small class="text-muted">Hash: ' . $row['domain_hash'] . '</small>';
    echo '</td>';

    // Root Bridge
    echo '<td>' . htmlentities(stp_bridgeid_to_str($row['root_hex'])) . '</td>';

    // Members
    echo '<td>' . (int)$row['members'] . '</td>';

    // Hot indicator
    echo '<td><span class="label ' . $hot_class . '">' . $hot_txt . '</span></td>';

    // Bad Ports
    $bad_ports = (int)$row['bad_ports'];
    echo '<td>';
    if ($bad_ports > 0) {
      echo '<span class="text-danger">' . $bad_ports . '</span>';
    } else {
      echo $bad_ports;
    }
    echo '</td>';

    // Inconsistent
    $inconsistent = (int)$row['inconsistent'];
    echo '<td>';
    if ($inconsistent > 0) {
      echo '<span class="text-danger">' . $inconsistent . '</span>';
    } else {
      echo $inconsistent;
    }
    echo '</td>';

    // Flaps column - Domain-level dual badge system
    echo '<td>';

    // Use higher thresholds for domain-level view since multiple devices
    $warn_60m = 5;   // Domain has moderate flapping
    $crit_60m = 10;  // Domain has high flapping

    $b5 = 'label ' . ($flaps_5m > 0 ? 'label-danger' : 'label-default');

    if ($flaps_60m >= $crit_60m)      { $b60 = 'label label-danger'; }
    else if ($flaps_60m >= $warn_60m) { $b60 = 'label label-warning'; }
    else                              { $b60 = 'label label-success'; }

    // Tooltip
    $tt = [];
    $tt[] = $flaps_5m > 0 ? "Ports changed state in the last poll across this domain." : "No changes this poll.";
    $tt[] = "Total domain transitions in last hour: {$flaps_60m}.";
    $tt[] = "Domain has {$row['members']} member devices.";
    $tooltip = htmlspecialchars(implode(' ', $tt));

    echo '<span class="'.$b5.'" title="'.$tooltip.'">5m '.$flaps_5m.'</span> ';
    echo '<span class="'.$b60.'" title="'.$tooltip.'">60m '.$flaps_60m.'</span>';
    echo '</td>';

    // Updated
    echo '<td>' . htmlentities($row['updated']) . '</td>';

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