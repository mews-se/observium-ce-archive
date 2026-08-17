<?php
/**
 * STP Domain Detail View
 * 
 * Shows detailed information about a specific STP domain:
 * - Domain header with aggregates
 * - Members table (devices in this domain)
 * - Problem ports within this domain
 * - Instance summary
 */

// Get domain hash parameter
$domain_hash = $vars['domain_hash'] ?? '';

// Validate domain hash parameter
if (empty($domain_hash) || !preg_match('/^[a-f0-9]{12}$/', $domain_hash)) {
  echo '<div class="alert alert-danger">Invalid domain hash parameter.</div>';
  return;
}

// Validate domain exists and get aggregates
$domain_check = dbFetchRow("
  SELECT 
    sb.variant,
    COALESCE(sb.mst_region_name, '') AS region,
    COALESCE(sb.designated_root, '') AS root_hex,
    COUNT(*) AS members,
    MIN(sb.time_since_tc_cs) AS min_tca,
    MAX(sb.updated) AS updated
  FROM stp_bridge sb
  JOIN devices d ON d.device_id = sb.device_id
  WHERE sb.domain_hash = ?
  GROUP BY sb.domain_hash
", [$domain_hash]);

// Extract domain components for display
$variant = $domain_check['variant'] ?? '';
$region = $domain_check['region'] ?? '';
$root_hex = $domain_check['root_hex'] ?? '';


if (!$domain_check) {
  echo '<div class="alert alert-danger">Domain not found.</div>';
  return;
}

// Get bad ports, inconsistent counts, and flap data for this domain
$bad_ports = dbFetchCell("
  SELECT COUNT(*)
  FROM stp_ports sp
  JOIN stp_bridge sb ON sb.device_id = sp.device_id
  WHERE sp.state IN ('blocking','discarding','broken')
    AND sb.domain_hash = ?
", [$domain_hash]);

$inconsistent = dbFetchCell("
  SELECT COUNT(*)
  FROM stp_ports sp
  JOIN stp_bridge sb ON sb.device_id = sp.device_id
  WHERE sp.inconsistent = 1
    AND sb.domain_hash = ?
", [$domain_hash]);

$flaps_5m = dbFetchCell("
  SELECT SUM(CASE WHEN sp.transitions_5m=1 THEN 1 ELSE 0 END)
  FROM stp_ports sp
  JOIN stp_bridge sb ON sb.device_id = sp.device_id
  WHERE sb.domain_hash = ?
", [$domain_hash]);

$flaps_60m = dbFetchCell("
  SELECT SUM(COALESCE(sp.transitions_60m,0))
  FROM stp_ports sp
  JOIN stp_bridge sb ON sb.device_id = sp.device_id
  WHERE sb.domain_hash = ?
", [$domain_hash]);

$total_ports = dbFetchCell("
  SELECT COUNT(*)
  FROM stp_ports sp
  JOIN stp_bridge sb ON sb.device_id = sp.device_id
  WHERE sb.domain_hash = ?
", [$domain_hash]);

// Domain header with status panel - 6-box layout example
$domain_status_boxes = [
  [
    'title' => 'Domain Identity',
    'value' => ['html' => stp_format_domain_labels($variant, $region, $root_hex)],
    'subtitle' => 'Hash: ' . $domain_hash
  ],
  [
    'title' => 'Protocol',
    'value' => ['text' => strtoupper($variant), 'class' => 'label-info'],
    'subtitle' => !empty($region) ? htmlentities($region) : 'No Region'
  ],
  [
    'title' => 'Root Bridge',
    'value' => stp_bridgeid_to_str($root_hex),
    'subtitle' => 'Network Root'
  ],
  [
    'title' => 'Members',
    'value' => (int)$domain_check['members'],
    'subtitle' => 'Devices in Domain'
  ],
  [
    'title' => 'Topology Changes',
    'value' => stp_format_tc_time($domain_check['min_tca'], 'label'),
    'subtitle' => 'Last Change'
  ],
  [
    'title' => 'Port Stability',
    'value' => function() use ($flaps_5m, $flaps_60m, $total_ports) {
      $flaps_5m = (int)$flaps_5m;
      $flaps_60m = (int)$flaps_60m;
      $total_ports = (int)$total_ports;

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
    'subtitle' => function() use ($flaps_5m, $total_ports) {
      $flaps_5m = (int)$flaps_5m;
      $total_ports = (int)$total_ports;

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
foreach ($domain_status_boxes as &$box) {
  if (is_callable($box['value'])) {
    $box['value'] = $box['value']();
  }
  if (is_callable($box['subtitle'])) {
    $box['subtitle'] = $box['subtitle']();
  }
}

echo generate_status_panel($domain_status_boxes);

// Members table
echo generate_box_open(['title' => 'Domain Members']);

// Use the exact same logic as device pages to find root device
$designated_root = dbFetchCell("SELECT DISTINCT designated_root FROM stp_bridge WHERE domain_hash = ? LIMIT 1", [$domain_hash]);
$root_device_id = NULL;

if (!safe_empty($designated_root)) {
  $root_device_id = dbFetchCell('SELECT `device_id` FROM `stp_bridge` WHERE `bridge_id` = ? LIMIT 1', [$designated_root]);
  if (!$root_device_id) {
    $root_device_id = dbFetchCell('SELECT `device_id` FROM `stp_bridge` WHERE `designated_root` = ? AND IFNULL(`root_port`,0) = 0 LIMIT 1', [$designated_root]);
  }
}

$members = dbFetchRows("
  SELECT d.device_id, d.hostname,
         CASE WHEN sb.device_id = ? THEN 1 ELSE 0 END AS is_root,
         sb.root_port, sb.root_cost, sb.time_since_tc_cs, sb.updated,
         COALESCE((
           SELECT SUM(CASE WHEN sp.transitions_5m=1 THEN 1 ELSE 0 END)
           FROM stp_ports sp
           WHERE sp.device_id = sb.device_id
         ),0) AS device_flaps_5m,
         COALESCE((
           SELECT SUM(COALESCE(sp.transitions_60m,0))
           FROM stp_ports sp
           WHERE sp.device_id = sb.device_id
         ),0) AS device_flaps_60m
  FROM stp_bridge sb
  JOIN devices d ON d.device_id = sb.device_id
  WHERE sb.domain_hash = ?
  ORDER BY (sb.device_id = ?) DESC, sb.time_since_tc_cs ASC
", [$root_device_id, $domain_hash, $root_device_id]);

if (empty($members)) {
  echo generate_box_state('info', '<strong>No Members Found</strong><br>This domain has no member devices.', [
    'icon' => $config['icon']['info'],
    'size' => 'medium'
  ]);
} else {
  echo '<table class="table table-striped table-hover table-condensed">';
  echo '<thead><tr>';
  echo '<th class="state-marker"></th>';
  echo '<th>Device</th>';
  echo '<th>Root?</th>';
  echo '<th>Root Port</th>';
  echo '<th>Root Cost</th>';
  echo '<th>Since TC</th>';
  echo '<th>Flaps</th>';
  echo '<th>Updated</th>';
  echo '</tr></thead><tbody>';

  foreach ($members as $member) {
    // Determine row class based on device role and status (include flapping)
    $dev_flaps_5m = (int)($member['device_flaps_5m'] ?? 0);
    $dev_flaps_60m = (int)($member['device_flaps_60m'] ?? 0);

    $row_class = '';
    if ($dev_flaps_5m > 0) {
      $row_class = 'error'; // Currently flapping - highest priority
    } elseif ($dev_flaps_60m >= 5) {
      $row_class = 'warning'; // Device has moderate flapping
    } elseif ($member['is_root'] == 1) {
      $row_class = 'info'; // Root bridge - informational status
    } else {
      $row_class = ''; // Non-root - no special status
    }

    echo '<tr class="'.$row_class.'">';

    // State marker
    echo '<td class="state-marker"></td>';

    // Device
    $device_url = generate_device_url(['device_id' => $member['device_id'], 'hostname' => $member['hostname']], ['tab' => 'stp']);
    echo '<td class="entity-title"><a class="entity" href="' . $device_url . '">' . htmlentities($member['hostname']) . '</a></td>';

    // Root indicator
    echo '<td>';
    if ($member['is_root']) {
      echo '<span class="label label-success">YES</span>';
    }
    echo '</td>';

    // Root Port
    echo '<td>';
    if ($member['root_port'] > 0) {
      // Try to find the port link
      $port = dbFetchRow("SELECT port_id, ifName, ifDescr FROM ports WHERE device_id = ? AND ifIndex = ?", 
                        [$member['device_id'], $member['root_port']]);
      if ($port) {
        echo generate_port_link(['port_id' => $port['port_id']], $port['ifName'] ?: $port['ifDescr']);
      } else {
        echo $member['root_port'];
      }
    } else {
      echo '0';
    }
    echo '</td>';

    // Root Cost
    echo '<td>' . (int)$member['root_cost'] . '</td>';

    // Time since TC
    echo '<td>' . stp_format_tc_time($member['time_since_tc_cs'], 'span') . '</td>';

    // Flaps column - Device-level dual badge system
    echo '<td>';

    // Use moderate thresholds for device-level view
    $warn_60m = 3;
    $crit_60m = 6;

    $b5 = 'label ' . ($dev_flaps_5m > 0 ? 'label-danger' : 'label-default');

    if ($dev_flaps_60m >= $crit_60m)      { $b60 = 'label label-danger'; }
    else if ($dev_flaps_60m >= $warn_60m) { $b60 = 'label label-warning'; }
    else                                  { $b60 = 'label label-success'; }

    // Tooltip
    $tt = [];
    $tt[] = $dev_flaps_5m > 0 ? "Device ports changed state in the last poll (5m)." : "No changes this poll.";
    $tt[] = "Device total transitions in last hour: {$dev_flaps_60m}.";
    $tooltip = htmlspecialchars(implode(' ', $tt));

    echo '<span class="'.$b5.'" title="'.$tooltip.'">5m '.$dev_flaps_5m.'</span> ';
    echo '<span class="'.$b60.'" title="'.$tooltip.'">60m '.$dev_flaps_60m.'</span>';
    echo '</td>';

    // Updated
    echo '<td>' . htmlentities($member['updated']) . '</td>';

    echo '</tr>';
  }

  echo '</tbody></table>';
}

echo generate_box_close();

// Problem ports in this domain
echo generate_box_open(['title' => 'Problem Ports in Domain']);

$problem_ports = dbFetchRows("
  SELECT d.hostname, p.port_id, po.ifName, po.ifDescr, p.stp_instance_id,
         p.state, p.role, p.oper_edge, p.guard, p.inconsistent, p.updated,
         p.transitions_5m, p.transitions_60m, p.state_changed
  FROM stp_ports p
  JOIN ports po ON po.port_id = p.port_id
  JOIN stp_bridge sb ON sb.device_id = p.device_id
  JOIN devices d ON d.device_id = p.device_id
  WHERE sb.domain_hash = ?
    AND (p.state IN ('blocking','discarding','broken') OR p.inconsistent = 1 OR p.transitions_5m = 1 OR p.transitions_60m >= 3)
    AND po.ifAdminStatus = 'up'
  ORDER BY p.transitions_5m DESC, p.transitions_60m DESC, p.inconsistent DESC, p.state DESC, d.hostname, po.ifIndex
  LIMIT 100
", [$domain_hash]);

if (empty($problem_ports)) {
  echo generate_box_state('success', '<strong>No Problem Ports</strong><br>All ports in this domain are healthy - no blocking, discarding, broken, inconsistent, or flapping ports.', [
    'icon' => $config['icon']['ok'],
    'size' => 'medium'
  ]);
} else {
  echo '<table class="table table-striped table-hover table-condensed">';
  echo '<thead><tr>';
  echo '<th class="state-marker"></th>';
  echo '<th>Device</th>';
  echo '<th>Port</th>';
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
    $device_url = generate_device_url(['hostname' => $port['hostname']], ['tab' => 'stp']);
    echo '<td class="entity-title"><a class="entity" href="' . $device_url . '">' . htmlentities($port['hostname']) . '</a></td>';

    // Port
    echo '<td class="entity-title">' . generate_port_link(['port_id' => $port['port_id']], $port['ifName'] ?: $port['ifDescr']) . '</td>';

    // Instance
    echo '<td>' . htmlentities($inst_text) . '</td>';

    // State
    echo '<td>' . get_type_class_label($port['state'], 'stp_state') . '</td>';

    // Role
    echo '<td>';
    if (empty($port['role']) || strtolower($port['role']) === 'unknown') {
      echo '—';
    } else {
      echo htmlentities($port['role']);
    }
    echo '</td>';

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
}

echo generate_box_close();

// EOF
