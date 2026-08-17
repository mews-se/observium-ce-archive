<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage web
 * @copyright  (C) Adam Armstrong
 *
 */

// Global read permissions required.
if ($_SESSION['userlevel'] < 5) {
    print_error_permission();
    return;
}

register_html_title("Poller/Discovery Timing");

// Register Chart.js for the discovery performance chart
register_html_resource('js', 'chart.umd.min.js');

// Generate cache of Pollers
$pollers = [ 0 => [ 'poller_name' => "Default", 'poller_id' => 0 ]];

$navbar = ['brand' => "Poller", 'class' => "navbar-narrow"];

if ($_SESSION['userlevel'] >= 7) {
    $navbar['options']['wrapper']['text'] = 'Wrapper';
}
$navbar['options']['devices']['text'] = 'Per-Device';
$navbar['options']['modules']['text'] = 'Poller Modules';
$navbar['options']['discovery']['text'] = 'Discovery Modules';

if (OBS_DISTRIBUTED) {

    foreach (dbFetchRows("SELECT *, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(`timestamp`) AS `lasttime` FROM `pollers`") as $entry) {
        $pollers[$entry['poller_id']] = $entry;
    }

    $navbar['options']['pollers']['text'] = 'Partitions';

    $navbar['options_right']['poller_id']['text'] = 'Poller Partition (All)';

    if (!safe_empty($vars['poller_id'])) {
        $navbar['options_right']['poller_id']['suboptions']['all']['text'] = 'All Partitions';
        $navbar['options_right']['poller_id']['suboptions']['all']['url']  = generate_url($vars, ['poller_id' => NULL]);
    }

    foreach ($pollers as $poller) {
        $navbar['options_right']['poller_id']['suboptions'][$poller['poller_id']]['text'] = escape_html($poller['poller_name']);
        $navbar['options_right']['poller_id']['suboptions'][$poller['poller_id']]['url']  = generate_url($vars, ['poller_id' => $poller['poller_id']]);
        if (!safe_empty($vars['poller_id']) && $vars['poller_id'] == $poller['poller_id']) {
            $navbar['options_right']['poller_id']['suboptions'][$poller['poller_id']]['class'] = "active";
            $navbar['options_right']['poller_id']['text']                                      = 'Poller Partition (' . $poller['poller_name'] . ')';
        }
    }
}

foreach ($navbar['options'] as $option => $array) {
    if (!isset($vars['view'])) {
        $vars['view'] = $option;
    }
    if ($vars['view'] == $option) {
        $navbar['options'][$option]['class'] .= " active";
    }
    $navbar['options'][$option]['url'] = generate_url($vars, ['view' => $option]);
}

print_navbar($navbar);
unset($navbar);

// Generate statistics

$totals['poller']    = 0;
$totals['discovery'] = 0;
$totals['count']    = 0;

$proc['avg2']['poller']    = 0;
$proc['avg2']['discovery'] = 0;
$proc['max']['poller']     = 0;
$proc['max']['discovery']  = 0;

$mod_total = 0;
$mods      = [];

// Make poller table
$devices = [];
foreach (dbFetchRows("SELECT * FROM `devices`") as $device) {

    $device_id = $device['device_id'];
    humanize_device($device);

    if ($device['disabled'] == 1 && !$config['web_show_disabled']) {
        continue;
    }
    if (!safe_empty($vars['poller_id']) && $device['poller_id'] != $vars['poller_id']) {
        // Restricting devices list to matching poller domain.
        //unset($devices[$device['device_id']]);
        continue;
    }

    // Convert empty times to numeric
    $device['last_polled_timetaken']     = (float)$device['last_polled_timetaken'];
    $device['last_discovered_timetaken'] = (float)$device['last_discovered_timetaken'];

    $devices[$device['device_id']] = $device;

    // Find max poller/discovery times
    if ($device['status']) {
        if ($device['last_polled_timetaken'] > $proc['max']['poller']) {
            $proc['max']['poller'] = $device['last_polled_timetaken'];
        }
        if ($device['last_discovered_timetaken'] > $proc['max']['discovery']) {
            $proc['max']['discovery'] = $device['last_discovered_timetaken'];
        }
    }
    $proc['avg2']['poller']    += $device['last_polled_timetaken'] ** 2;
    $proc['avg2']['discovery'] += $device['last_discovered_timetaken'] ** 2;

    $totals['count']++;
    $totals['poller']    += $device['last_polled_timetaken'];
    $totals['discovery'] += $device['last_discovered_timetaken'];

    $devices[$device_id] = array_merge($devices[$device_id], [ 'html_row_class'            => $device['html_row_class'],
                                                              'device_hostname'           => $device['hostname'],
                                                              'device_link'               => generate_device_link($device),
                                                              'device_status'             => $device['status'],
                                                              'device_disabled'           => $device['disabled'],
                                                              'last_polled_timetaken'     => $device['last_polled_timetaken'],
                                                              //'last_polled'               => $device['last_polled'],
                                                              'last_discovered_timetaken' => $device['last_discovered_timetaken'],
                                                              //'last_discovered'           => $device['last_discovered']
                                                             ]
    );

    // Collect poller module performance data
    foreach ($device['state']['poller_mod_perf'] as $mod => $time) {
        $mods[$mod]['time'] += $time;
        $mods[$mod]['count']++;
        $mod_total += $time;
    }
}

$proc['avg']['poller']    = round(float_div($totals['poller'], $totals['count']), 2);
$proc['avg']['discovery'] = round(float_div($totals['discovery'], $totals['count']), 2);

// End generate statistics

if ($vars['view'] === "modules") {

    if ($_SESSION['userlevel'] >= 7) {
        echo generate_box_open(['header-border' => TRUE, 'title' => 'Poller Modules']);

        $graph_array = [
          'type'   => 'global_pollermods',
          'from'   => get_time('week'),
          'to'     => get_time(),
          'legend' => 'no'
        ];

        if (!safe_empty($vars['poller_id']) && is_numeric($vars['poller_id'])) {
            $graph_array['poller_id'] = $vars['poller_id'];
        }

        print_graph_row($graph_array);

        echo generate_box_close();
    }


    echo generate_box_open();
    if ($_SESSION['userlevel'] >= 7) {
        echo('<table class="' . OBS_CLASS_TABLE_STRIPED_TWO . '">' . PHP_EOL);
    } else {
        echo('<table class="' . OBS_CLASS_TABLE_STRIPED . '">' . PHP_EOL);
    }

    $mods = array_sort_by($mods, 'time', SORT_DESC, SORT_NUMERIC);

    foreach ($mods as $mod => $data) {

        $perc = round(float_div($data['time'], $mod_total) * 100);
        $bg   = get_percentage_colours($perc);

        echo '<tr>';
        echo '  <td><h3>' . $mod . '</h3></td>';
        echo '  <td width="200">' . print_percentage_bar('100%', '20', $perc, $perc . '%', "ffffff", $bg['left'], '', "ffffff", $bg['right']) . '</td>';
        echo '  <td width="60">' . $data['count'] . '</td>';
        echo '  <td width="60">' . round($data['time'], 3) . 's</td>';
        echo '</tr>';
        if ($_SESSION['userlevel'] >= 7) {
            echo '<tr>';
            echo '  <td colspan=6>';

            $graph_array = [
              'type'   => 'global_pollermod',
              'module' => $mod,
              'legend' => 'no'
            ];

            if (!safe_empty($vars['poller_id']) && is_numeric($vars['poller_id'])) {
                $graph_array['poller_id'] = $vars['poller_id'];
            }

            print_graph_row($graph_array);

            echo '  </td>';
            echo '</tr>';
        }

    }

    ?>
    </tbody>
    </table>

    <?php

    echo generate_box_close();

} elseif ($vars['view'] === "discovery") {

    // Get discovery performance data from database (last 7 days for more stable averages)
    $db_discovery_data = dbFetchRows("
        SELECT module,
               COUNT(*) as device_count,
               SUM(time_taken) as total_time,
               AVG(time_taken) as avg_time,
               MIN(time_taken) as min_time,
               MAX(time_taken) as max_time
        FROM discovery_perf_history 
        WHERE discovery_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        " . (!safe_empty($vars['poller_id']) ? "AND device_id IN (SELECT device_id FROM devices WHERE poller_id = " . (int)$vars['poller_id'] . ")" : "") . "
        GROUP BY module
        ORDER BY total_time DESC
    ");

    if (!safe_empty($db_discovery_data)) {
        $db_discovery_total = array_sum(array_column($db_discovery_data, 'total_time'));

        // Add performance chart overview using Chart.js
        echo generate_box_open(['header-border' => TRUE, 'title' => 'Discovery Performance Overview (Last 7 Days)']);

        echo '<div style="height: 400px; margin: 20px;">';
        echo '<canvas id="discoveryChart"></canvas>';
        echo '</div>';

        echo '<script>';
        echo 'if (typeof Chart !== "undefined") {';
        echo '  const ctx = document.getElementById("discoveryChart").getContext("2d");';

        // Prepare data for Chart.js
        $chart_labels = array_map(function($m) { return $m['module']; }, $db_discovery_data);
        $chart_data = array_column($db_discovery_data, 'total_time');
        $chart_colors = [
            '#5bc0de', '#5cb85c', '#f0ad4e', '#d9534f', '#428bca', 
            '#7b4397', '#dc2430', '#ff6b35', '#f7931e', '#8e44ad',
            '#17a2b8', '#28a745', '#ffc107', '#dc3545', '#6f42c1'
        ];

        echo '  const discoveryChart = new Chart(ctx, {';
        echo '    type: "bar",';
        echo '    data: {';
        echo '      labels: ' . json_encode($chart_labels) . ',';
        echo '      datasets: [{';
        echo '        label: "Total Time (seconds)",';
        echo '        data: ' . json_encode($chart_data) . ',';
        echo '        backgroundColor: [' . implode(',', array_map(function($color) { return '"' . $color . '"'; }, array_slice($chart_colors, 0, count($chart_data)))) . '].slice(0, ' . count($chart_data) . '),';
        echo '        borderColor: [' . implode(',', array_map(function($color) { return '"' . $color . '"'; }, array_slice($chart_colors, 0, count($chart_data)))) . '].slice(0, ' . count($chart_data) . '),';
        echo '        borderWidth: 1';
        echo '      }]';
        echo '    },';
        echo '    options: {';
        echo '      responsive: true,';
        echo '      maintainAspectRatio: false,';
        echo '      plugins: {';
        echo '        legend: {';
        echo '          display: false';
        echo '        },';
        echo '        title: {';
        echo '          display: true,';
        echo '          text: "Discovery Module Performance (Total Time)"';
        echo '        }';
        echo '      },';
        echo '      scales: {';
        echo '        y: {';
        echo '          beginAtZero: true,';
        echo '          title: {';
        echo '            display: true,';
        echo '            text: "Time (seconds)"';
        echo '          }';
        echo '        },';
        echo '        x: {';
        echo '          title: {';
        echo '            display: true,';
        echo '            text: "Discovery Modules"';
        echo '          },';
        echo '          ticks: {';
        echo '            maxRotation: 45,';
        echo '            minRotation: 45';
        echo '          }';
        echo '        }';
        echo '      },';
        echo '      interaction: {';
        echo '        intersect: false,';
        echo '        mode: "index"';
        echo '      }';
        echo '    }';
        echo '  });';
        echo '} else {';
        echo '  document.getElementById("discoveryChart").parentElement.innerHTML = "<div class=\\"alert alert-warning\\">Chart.js library not loaded. Chart display requires Chart.js.</div>";';
        echo '}';
        echo '</script>';
        echo generate_box_close();
    }

    echo generate_box_open(['header-border' => TRUE, 'title' => 'Discovery Module Performance (Database Data - Last 7 Days)']);
    echo('<table class="' . OBS_CLASS_TABLE_STRIPED . '">' . PHP_EOL);

    echo get_table_header([
        'Module',
        [ 'Performance', 'style="width: 200px;"' ],
        [ 'Devices', 'style="width: 60px;"' ],
        [ 'Total Time', 'style="width: 80px;"' ],
        [ 'Avg Time', 'style="width: 80px;"' ],
        [ 'Min/Max', 'style="width: 100px;"' ],
        [ 'Percentage', 'style="width: 80px;"' ]
    ]);

    if (!safe_empty($db_discovery_data)) {
        foreach ($db_discovery_data as $data) {
            $perc = round(float_div($data['total_time'], $db_discovery_total) * 100, 1);
            $bg = get_percentage_colours($perc);

            echo '<tr>';
            echo '  <td><strong>' . escape_html($data['module']) . '</strong></td>';
            echo '  <td width="200">' . print_percentage_bar('100%', '20', $perc, $perc . '%', "ffffff", $bg['left'], '', "ffffff", $bg['right']) . '</td>';
            echo '  <td width="60" class="text-center">' . $data['device_count'] . '</td>';
            echo '  <td width="80" class="text-right">' . round($data['total_time'], 3) . 's</td>';
            echo '  <td width="80" class="text-right">' . round($data['avg_time'], 3) . 's</td>';
            echo '  <td width="100" class="text-right"><small>' . round($data['min_time'], 2) . 's/' . round($data['max_time'], 2) . 's</small></td>';
            echo '  <td width="80" class="text-right">' . $perc . '%</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="7" class="text-center"><div class="alert alert-info">No discovery performance data available yet. Data will appear after discovery runs.</div></td></tr>';
    }

    echo '</table>';
    echo generate_box_close();

    // Add summary statistics
    echo generate_box_open(['header-border' => TRUE, 'title' => 'Discovery Summary']);
    echo '<table class="' . OBS_CLASS_TABLE . '">';

    if (!safe_empty($db_discovery_data)) {
        // Use database data for summary
        $total_db_runs = array_sum(array_column($db_discovery_data, 'device_count'));
        $avg_per_run = $total_db_runs > 0 ? round($db_discovery_total / $total_db_runs, 3) : 0;
        $slowest_mod = $db_discovery_data[0]['module'];

        echo '<tr><th style="width: 200px;">Total Discovery Time</th><td>' . round($db_discovery_total, 3) . 's</td></tr>';
        echo '<tr><th>Average per Discovery Run</th><td>' . $avg_per_run . 's</td></tr>';
        echo '<tr><th>Active Modules</th><td>' . count($db_discovery_data) . '</td></tr>';
        echo '<tr><th>Total Discovery Runs</th><td>' . $total_db_runs . '</td></tr>';
        echo '<tr><th>Slowest Module</th><td>' . escape_html($slowest_mod) . ' (' . round($db_discovery_data[0]['avg_time'], 3) . 's avg)</td></tr>';
        echo '<tr><th>Data Source</th><td><small>Database (Last 7 Days)</small></td></tr>';

    } else {
        echo '<tr><td colspan="2" class="text-center"><div class="alert alert-info">No discovery performance data available yet.</div></td></tr>';
    }
    echo '</table>';
    echo generate_box_close();

    // Add historical trends if we have data
    if (dbExist('discovery_perf_history')) {
        echo generate_box_open(['header-border' => TRUE, 'title' => 'Discovery Performance Trends (Last 30 Days)']);

        // Get module trends over last 30 days  
        $trend_data = dbFetchRows("
            SELECT module,
                   DATE(discovery_time) as discovery_date,
                   AVG(time_taken) as avg_time,
                   MIN(time_taken) as min_time,
                   MAX(time_taken) as max_time,
                   COUNT(*) as device_count
            FROM discovery_perf_history 
            WHERE discovery_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY module, DATE(discovery_time)
            ORDER BY module, discovery_date DESC
            LIMIT 500
        ");

        if (!safe_empty($trend_data)) {
            // Group by module for display
            $trends_by_module = [];
            foreach ($trend_data as $row) {
                $trends_by_module[$row['module']][] = $row;
            }

            echo '<table class="' . OBS_CLASS_TABLE_STRIPED . '">';
            echo get_table_header([
                'Module',
                [ 'Recent Average', 'style="width: 100px;"' ],
                [ 'Trend (7 days)', 'style="width: 120px;"' ],
                [ 'Min/Max', 'style="width: 120px;"' ],
                [ 'Last Run', 'style="width: 100px;"' ]
            ]);

            foreach ($trends_by_module as $module => $data) {
                // Calculate 7-day trend
                $recent_avg = 0;
                $week_ago_avg = 0;
                $recent_count = 0;
                $week_count = 0;
                $min_time = PHP_INT_MAX;
                $max_time = 0;
                $last_run = '';

                foreach ($data as $i => $row) {
                    if ($i === 0) {
                        $last_run = $row['discovery_date'];
                    }

                    $min_time = min($min_time, $row['min_time']);
                    $max_time = max($max_time, $row['max_time']);

                    if ($i < 7) { // Last 7 days
                        $recent_avg += $row['avg_time'];
                        $recent_count++;
                    }
                    if ($i >= 7 && $i < 14) { // 7-14 days ago
                        $week_ago_avg += $row['avg_time'];
                        $week_count++;
                    }
                }

                $recent_avg = $recent_count ? round($recent_avg / $recent_count, 3) : 0;
                $week_ago_avg = $week_count ? round($week_ago_avg / $week_count, 3) : $recent_avg;

                // Calculate trend
                $trend_pct = 0;
                $trend_class = '';
                if ($week_ago_avg > 0 && $recent_avg > 0) {
                    $trend_pct = round((($recent_avg - $week_ago_avg) / $week_ago_avg) * 100, 1);
                    $trend_class = $trend_pct > 10 ? 'text-danger' : ($trend_pct < -10 ? 'text-success' : 'text-muted');
                }

                echo '<tr>';
                echo '  <td><strong>' . escape_html($module) . '</strong></td>';
                echo '  <td class="text-right">' . $recent_avg . 's</td>';
                echo '  <td class="text-right ' . $trend_class . '">';
                if ($trend_pct != 0) {
                    echo ($trend_pct > 0 ? '+' : '') . $trend_pct . '%';
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '  <td class="text-right"><small>' . round($min_time, 2) . 's / ' . round($max_time, 2) . 's</small></td>';
                echo '  <td class="text-right"><small>' . $last_run . '</small></td>';
                echo '</tr>';
            }

            echo '</table>';
        } else {
            echo '<p>No historical discovery performance data available yet. Data will appear after a few discovery runs.</p>';
        }

        echo generate_box_close();
    }

} elseif ($vars['view'] === "wrapper" && $_SESSION['userlevel'] >= 7) {

    $rrd_file = $config['rrd_dir'] . '/poller-wrapper.rrd';
    if (rrd_is_file($rrd_file, TRUE)) {
        echo generate_box_open(['header-border' => TRUE, 'title' => 'Poller Wrapper History']);

        $graph_array = [
          'type'   => 'poller_wrapper_threads',
          //'operation' => 'poll',
          //'width'  => 1158,
          'height' => 100,
          'from'   => get_time('week'),
          'to'     => get_time()
        ];

        // We've selected a paritioned poller. Switch the graph.
        if (isset($vars['poller_id']) && $vars['poller_id'] != 0) {
            $graph_array['type'] = 'poller_partitioned_wrapper_threads';
            $graph_array['id']   = $vars['poller_id'];
        }

        //echo(generate_graph_tag($graph_array));
        print_graph_row($graph_array);

        //$graph_array = array('type'   => 'poller_wrapper_count',
        //                     //'operation' => 'poll',
        //                     'width'  => 1158,
        //                     'height' => 100,
        //                     'from'   => get_time('week'),
        //                     'to'     => get_time('now'),
        //                     );
        //echo(generate_graph_tag($graph_array));
        //echo "<h3>Poller wrapper Total time</h3>";
        $graph_array = [
          'type'   => 'poller_wrapper_times',
          //'operation' => 'poll',
          //'width'  => 1158,
          'height' => 100,
          'from'   => get_time('week'),
          'to'     => get_time()
        ];

        // We've selected a paritioned poller. Switch the graph.
        if (isset($vars['poller_id']) && $vars['poller_id'] != 0) {
            $graph_array['type'] = 'poller_partitioned_wrapper_times';
            $graph_array['id']   = $vars['poller_id'];
        }

        //echo(generate_graph_tag($graph_array));
        print_graph_row($graph_array);

        //echo generate_box_close([ 'footer_content' => '<b>Please note:</b> Total poller wrapper time is the execution time for the poller wrapper process.' ]);
        echo generate_box_close();

    }

} elseif ($vars['view'] === "devices") {

    if ($_SESSION['userlevel'] >= 7) {
        echo generate_box_open(['header-border' => TRUE, 'title' => 'All Devices Poller Performance']);

        $graph_array = [
          'type'   => 'global_poller',
          'from'   => get_time('week'),
          'to'     => get_time(),
          'legend' => 'no'
        ];

        if (!safe_empty($vars['poller_id']) && is_numeric($vars['poller_id'])) {
            $graph_array['poller_id'] = $vars['poller_id'];
        }

        print_graph_row($graph_array);

        echo generate_box_close();
    }

    echo generate_box_open(['header-border' => TRUE, 'title' => 'Poller/Discovery Timing']);
    echo('<table class="' . OBS_CLASS_TABLE_STRIPED_MORE . '">' . PHP_EOL);

// FIXME -- table header generator / sorting

    ?>

    <thead>
    <tr>
        <th class="state-marker"></th>
        <th>Device</th>
        <th colspan="3">Last Polled</th>
        <th colspan="3">Last Discovered</th>
        <?php if (safe_empty($vars['poller_id'])) {
            echo '<th>Poller</th>';
        } ?>
    </tr>
    </thead>
    <tbody>
    <?php


    // Sort poller table
    // sort order: $polled > $discovered > $hostname
    $devices = array_sort_by($devices, 'device_status', SORT_DESC, SORT_NUMERIC,
                             'last_polled_timetaken', SORT_DESC, SORT_NUMERIC,
                             'last_discovered_timetaken', SORT_DESC, SORT_NUMERIC,
                             'device_hostname', SORT_ASC, SORT_STRING);

    // Print poller table
    foreach ($devices as $row) {
        $proc['time']['poller']  = round(float_div($row['last_polled_timetaken'], $proc['max']['poller']) * 100);
        $proc['color']['poller'] = "success";
        if ($row['last_polled_timetaken'] > ($proc['max']['poller'] * 0.75)) {
            $proc['color']['poller'] = "danger";
        } elseif ($row['last_polled_timetaken'] > ($proc['max']['poller'] * 0.5)) {
            $proc['color']['poller'] = "warning";
        } elseif ($row['last_polled_timetaken'] >= ($proc['max']['poller'] * 0.25)) {
            $proc['color']['poller'] = "info";
        }
        $proc['time']['discovery']  = round(float_div($row['last_discovered_timetaken'], $proc['max']['discovery']) * 100);
        $proc['color']['discovery'] = "success";
        if ($row['last_discovered_timetaken'] > ($proc['max']['discovery'] * 0.75)) {
            $proc['color']['discovery'] = "danger";
        } elseif ($row['last_discovered_timetaken'] > ($proc['max']['discovery'] * 0.5)) {
            $proc['color']['discovery'] = "warning";
        } elseif ($row['last_discovered_timetaken'] >= ($proc['max']['discovery'] * 0.25)) {
            $proc['color']['discovery'] = "info";
        }

        $poll_bg     = get_percentage_colours($proc['time']['poller']);
        $discover_bg = get_percentage_colours($proc['time']['discovery']);

        // Poller times
        echo('    <tr class="' . $row['html_row_class'] . '">
      <td class="state-marker"></td>
      <td class="entity">' . $row['device_link'] . '</td>
      <td style="width: 12%;">' .
             print_percentage_bar('100%', '20', $proc['time']['poller'], $proc['time']['poller'] . '%', "ffffff", $poll_bg['left'], '', "ffffff", $poll_bg['right'])
             . '</td>
      <td style="width: 7%">
        ' . $row['last_polled_timetaken'] . 's
      </td>
      <!-- <td>' . format_timestamp($row['last_polled']) . ' </td> -->
      <td>' . format_uptime(get_time('now') - strtotime($row['last_polled']), 'shorter') . ' ago</td>');

        // Discovery times
        echo('
      <td style="width: 12%;">' .
             print_percentage_bar('100%', '20', $proc['time']['discovery'], $proc['time']['discovery'] . '%', "ffffff", $discover_bg['left'], '', "ffffff", $discover_bg['right'])
             . '</td>
      <td style="width: 7%">
        ' . $row['last_discovered_timetaken'] . 's
      </td>
      <!-- <td>' . format_timestamp($row['last_discovered']) . '</td> -->
      <td>' . format_uptime(get_time('now') - strtotime($row['last_discovered']), 'shorter') . ' ago</td> ');

        if (safe_empty($vars['poller_id'])) {
            echo '
   <td>' . get_type_class_label($pollers[$row['poller_id']]['poller_name'], 'poller') . '</td>';
        }

        echo '
    </tr>
';
    }

    // Calculate root mean square
    $proc['avg2']['poller']    = sqrt(float_div($proc['avg2']['poller'], $totals['count']));
    $proc['avg2']['poller']    = round($proc['avg2']['poller'], 2);
    $proc['avg2']['discovery'] = sqrt(float_div($proc['avg2']['discovery'], $totals['count']));
    $proc['avg2']['discovery'] = round($proc['avg2']['discovery'], 2);

    echo('    <tr>
      <th></th>
      <th style="text-align: right;">Total time for all devices (average per device):</th>
      <th></th>
      <th colspan="3">' . $totals['poller'] . 's (' . $proc['avg2']['poller'] . 's)</th>
      <th></th>
      <th colspan="3">' . $totals['discovery'] . 's (' . $proc['avg2']['discovery'] . 's)</th>
    </tr>
');

    unset($row);

    ?>
    </tbody>
    </table>

    <?php

    echo generate_box_close();

} elseif ($vars['view'] === 'pollers') {

    //$pollers = dbFetchRows("SELECT * FROM `pollers`");
    unset($pollers[0]); // remove default poller here

    echo generate_box_open();

    echo '<table class="' . OBS_CLASS_TABLE_STRIPED . '">' . PHP_EOL;

    $table_cols = [
        [ 'ID', 'style="width: 20px;"' ],
        [ 'Poller Name', 'style="width: 8%;"' ],
        [ 'Host ID / Uname', 'style="width: 30%;"' ],
        [ 'Version', 'style="width: 80px;"' ],
        'Assigned Devices',
        [ 'Polled (ago)', 'style="width: 80px;"' ],
    ];
    if ($_SESSION['userlevel'] >= 10) {
        $table_cols[] = [ 'Actions', 'style="width: 100px;"' ];
    }
    echo get_table_header($table_cols);
    //echo '<tr><td>Poller Name</td><td>Assigned Devices</td></tr>';

    foreach ($pollers as $poller) {
        $device_list = [];
        echo '<tr>';
        echo '<td class="entity-name">' . $poller['poller_id'] . '</td>';
        echo '<td class="entity-name">' . $poller['poller_name'] . '</td>';
        if ($poller['device_id']) {
            echo '<td><small>' . $poller['host_id'] . ' (' . generate_device_link($poller['device_id']) . ')<br /><i>' .
                generate_device_link($poller['device_id'], $poller['host_uname']) . '</i></small></td>';
        } else {
            echo '<td><small>' . $poller['host_id'] . '<br /><i>' . $poller['host_uname'] . '</i></small></td>';
        }
        $poller_version = explode(' ', $poller['poller_version'])[0];
        $poller_revision = explode('.', $poller_version)[2];
        if ((get_obs_attrib('latest_rev') - $poller_revision) >= $config['version_check_revs']) {
            echo '<td><span class="label label-error">' . $poller_version . '</span></td>';
        } elseif (abs(OBSERVIUM_REV - $poller_revision) >= $config['version_check_revs']) {
            echo '<td><span class="label label-warning">' . $poller_version . '</span></td>';
        } else {
            echo '<td><span class="label">' . $poller_version . '</span></td>';
        }

        echo '<td>';
        foreach (dbFetchRows("SELECT * FROM `devices` WHERE `poller_id` = ?", [$poller['poller_id']]) as $device) {
            $device_list[] = generate_device_link($device);
        }

        $pollers[$poller['poller_id']]['devices'] = count($device_list);
        echo implode(', ', $device_list);
        echo '</td>';

        if ($poller['lasttime'] < 3600) {
            // Use dynamic counter
            $poller_refresh = 'poller_'.$poller['poller_id'].'_refresh';
            register_html_resource('script', 'time_refresh("'.$poller_refresh.'", '.$poller['lasttime'].', 1)');
            echo '<td><span id="'.$poller_refresh.'"></span></td>';
        } else {
            echo '<td>' . format_uptime($poller['lasttime']) . '</td>';
        }

        if ($_SESSION['userlevel'] >= 10) {
            echo '<td>';
            echo '<button type="button" class="btn btn-danger btn-sm" onclick="showDeletePollerModal(' . $poller['poller_id'] . ', \'' . addslashes($poller['poller_name']) . '\', ' . $pollers[$poller['poller_id']]['devices'] . ')">';
            echo '<i class="' . $config['icon']['delete'] . '"></i> Delete</button>';
            echo '</td>';
        }

        echo '</tr>';
    }

    echo '</table>';

    echo generate_box_close();

    // Add delete poller modal for admins
    if ($_SESSION['userlevel'] >= 10) {
        ?>
        <!-- Delete Poller Modal -->
        <div class="modal fade" id="deletePollerModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">Delete Poller</h4>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete poller <strong id="delete-poller-name"></strong>?</p>
                        <div id="device-reassignment-section">
                            <p>This poller has <strong><span id="delete-device-count"></span> device(s)</strong> assigned to it.</p>
                            <div class="form-group">
                                <label for="target-poller-select">Reassign devices to:</label>
                                <select class="form-control" id="target-poller-select">
                                    <option value="0">Default Poller</option>
                                    <?php
                                    foreach ($pollers as $p) {
                                        echo '<option value="' . $p['poller_id'] . '">' . escape_html($p['poller_name']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This action cannot be undone.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirm-delete-poller">Delete Poller</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var deletePollerData = {};

        function showDeletePollerModal(pollerId, pollerName, deviceCount) {
            deletePollerData = {
                pollerId: pollerId,
                pollerName: pollerName,
                deviceCount: deviceCount
            };

            $('#delete-poller-name').text(pollerName);
            $('#delete-device-count').text(deviceCount);

            // Hide device reassignment section if no devices
            if (deviceCount == 0) {
                $('#device-reassignment-section').hide();
            } else {
                $('#device-reassignment-section').show();
            }

            // Hide the current poller from target selection
            $('#target-poller-select option[value="' + pollerId + '"]').hide();
            $('#target-poller-select').val('0');

            $('#deletePollerModal').modal('show');
        }

        $('#confirm-delete-poller').on('click', function() {
            var targetPollerId = $('#target-poller-select').val();
            var button = $(this);

            button.prop('disabled', true).html('<i class="icon-spinner icon-spin"></i> Deleting...');

            $.ajax({
                url: 'ajax/actions.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'poller_delete',
                    poller_id: deletePollerData.pollerId,
                    target_poller_id: targetPollerId,
                    requesttoken: '<?php echo $_SESSION['requesttoken']; ?>'
                },
                success: function(response) {
                    if (response.status === 'ok') {
                        $('#deletePollerModal').modal('hide');
                        // Show success message
                        $('body').append('<div class="alert alert-success alert-dismissible" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">' +
                            '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                            response.message + '</div>');
                        // Reload page after 2 seconds
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        alert('Error: ' + response.message);
                        button.prop('disabled', false).html('<i class="<?php echo $config['icon']['delete']; ?>"></i> Delete Poller');
                    }
                },
                error: function() {
                    alert('An error occurred while deleting the poller.');
                    button.prop('disabled', false).html('<i class="<?php echo $config['icon']['delete']; ?>"></i> Delete Poller');
                }
            });
        });

        // Show all options again when modal is closed
        $('#deletePollerModal').on('hidden.bs.modal', function () {
            $('#target-poller-select option').show();
            $('#confirm-delete-poller').prop('disabled', false).html('Delete Poller');
        });
        </script>
        <?php
    }

    foreach ($pollers as $poller) {
        if (!$poller['devices']) {
            // poller not have associated device, skip graphs
            continue;
        }
        echo generate_box_open(['header-border' => TRUE, 'title' => 'Poller Wrapper (' . $poller['poller_name'] . ') History']);

        //r($poller);
        $graph_array = [
          'type'   => 'poller_partitioned_wrapper_threads',
          //'operation' => 'poll',
          'id'     => $poller['poller_id'],
          // 'width'  => 1158,
          'height' => 100,
          'from'   => get_time('week'),
          'to'     => get_time(),
        ];
        //echo(generate_graph_tag($graph_array));
        print_graph_row($graph_array);

        $graph_array = [
          'type'   => 'poller_partitioned_wrapper_times',
          //'operation' => 'poll',
          'id'     => $poller['poller_id'],
          // 'width'  => 1158,
          'height' => 100,
          'from'   => get_time('week'),
          'to'     => get_time(),
        ];
        //echo(generate_graph_tag($graph_array));
        print_graph_row($graph_array);

        echo generate_box_close();

        if ($actions = dbFetchRows('SELECT * FROM `observium_actions` WHERE `poller_id` = ?', [$poller['poller_id']])) {
            echo generate_box_open(['header-border' => TRUE, 'title' => 'Poller Actions (' . $poller['poller_name'] . ')']);
            $options = [
              'columns' => [
                'ID', 'Poller', 'Action', 'Identifier', 'Variables', 'Added'
              ],
              'vars'    => 'json',
              'added'   => 'unixtime'
            ];
            echo build_table($actions, $options);
            echo generate_box_close();
        }

    }
}

unset($devices, $proc, $pollers);

// EOF
