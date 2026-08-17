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

// Register Chart.js for discovery performance charts
register_html_resource('js', 'chart.umd.min.js');

?>

    <div class="row">
    <div class="col-md-12">

<?php

// Get discovery performance data for charts
$discovery_totals = dbFetchRows("
    SELECT `discovery_time`, SUM(`time_taken`) AS `total_time`
    FROM `discovery_perf_history`
    WHERE `device_id` = ? AND `discovery_time` >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY `discovery_time`
    ORDER BY `discovery_time` ASC
", [ $device['device_id'] ]);

if (!safe_empty($discovery_totals)) {
    echo '<div class="row">';
    
    // Discovery Totals Over Time Chart
    echo '<div class="col-md-8">';
    echo generate_box_open(['title' => 'Discovery Performance Over Time (Last 30 Days)']);
    echo '<div style="height: 250px; margin: 20px;">';
    echo '<canvas id="discoveryTotalsChart"></canvas>';
    echo '</div>';
    echo generate_box_close();
    echo '</div>';
    
    // Current Module Breakdown Chart
    echo '<div class="col-md-4">';
    echo generate_box_open(['title' => 'Latest Discovery Breakdown']);
    echo '<div style="height: 250px; margin: 20px;">';
    echo '<canvas id="discoveryModulesChart"></canvas>';
    echo '</div>';
    echo generate_box_close();
    echo '</div>';
    
    echo '</div>'; // End row
    
    // Prepare data for charts
    $totals_labels = array_map(function($row) { 
        return date('M j', strtotime($row['discovery_time'])); 
    }, $discovery_totals);
    $totals_data = array_map('floatval', array_column($discovery_totals, 'total_time'));
    
    // JavaScript for charts
    echo '<script>';
    echo 'document.addEventListener("DOMContentLoaded", function() {';
    echo 'if (typeof Chart !== "undefined") {';
    
    // Totals chart
    echo 'new Chart(document.getElementById("discoveryTotalsChart"), {';
    echo 'type: "line",';
    echo 'data: {';
    echo 'labels: ' . safe_json_encode($totals_labels) . ',';
    echo 'datasets: [{';
    echo 'label: "Total Discovery Time (s)",';
    echo 'data: ' . safe_json_encode($totals_data) . ',';
    echo 'borderColor: "#5bc0de",';
    echo 'backgroundColor: "rgba(91, 192, 222, 0.1)",';
    echo 'borderWidth: 2,';
    echo 'fill: true';
    echo '}]';
    echo '},';
    echo 'options: {';
    echo 'responsive: true,';
    echo 'maintainAspectRatio: false,';
    echo 'plugins: { legend: { display: false } },';
    echo 'scales: {';
    echo 'y: { beginAtZero: true, title: { display: true, text: "Time (s)" } },';
    echo 'x: { title: { display: true, text: "Date" } }';
    echo '}';
    echo '}';
    echo '});';
    
    // Modules chart
    $current_modules = dbFetchRows("
    SELECT `module`, `time_taken`
    FROM `discovery_perf_history`
    WHERE `device_id` = ? AND `discovery_time` = (
        SELECT MAX(`discovery_time`) 
        FROM `discovery_perf_history`
        WHERE `device_id` = ?
    )
    ORDER BY `time_taken` DESC", [ $device['device_id'], $device['device_id'] ]);
    if (!safe_empty($current_modules)) {
        $modules_labels = array_column($current_modules, 'module');
        $modules_data = array_map('floatval', array_column($current_modules, 'time_taken'));
        
        echo 'new Chart(document.getElementById("discoveryModulesChart"), {';
        echo 'type: "doughnut",';
        echo 'data: {';
        echo 'labels: ' . safe_json_encode($modules_labels) . ',';
        echo 'datasets: [{';
        echo 'data: ' . safe_json_encode($modules_data) . ',';
        echo 'backgroundColor: ["#5bc0de","#5cb85c","#f0ad4e","#d9534f","#428bca","#7b4397","#dc2430","#ff6b35","#f7931e","#8e44ad","#17a2b8","#28a745","#ffc107","#dc3545","#6f42c1"]';
        echo '}]';
        echo '},';
        echo 'options: {';
        echo 'responsive: true,';
        echo 'maintainAspectRatio: false,';
        echo 'plugins: {';
        echo 'legend: { position: "bottom", labels: { boxWidth: 12, font: { size: 10 } } }';
        echo '}';
        echo '}';
        echo '});';
    }
    
    echo '}';
    echo '});';
    echo '</script>';
    
} else {
    // Fallback if no discovery data
    echo generate_box_open([ 'title' => 'Discovery Performance' ]);
    if (OBS_DISTRIBUTED && $device['poller_id'] &&
        get_poller($device['poller_id'])['observium_revision'] < 14275) {
        // Too old poller, discovery perf was added in r14275
        print_box("No discovery performance data available for this device yet. Your remote poller (ID {$device['poller_id']}) is too old and does not collect these statistics.", 'error', 'no-shadow');
    } else {
        print_box("No discovery performance data available for this device yet. Data will appear after discovery runs.", 'warning', 'no-shadow');
    }
    echo generate_box_close();
}

$sql = "SELECT `process_command`, `process_name`, `process_start`, `poller_id` FROM `observium_processes` WHERE `device_id` = ? ORDER BY `process_ppid`, `process_start`";
if ($processes = dbFetchRows($sql, [$device['device_id']])) {
    echo generate_box_open(['title' => 'Running Processes']);
    $cols = [
        //'Process ID', 'PID', 'PPID', 'UID',
        'Command', 'Name', 'Started', 'Poller ID'
        //'Device'
    ];
    echo build_table($processes, ['columns' => $cols, 'process_start' => 'prettytime']);
    echo generate_box_close();
}

$navbar = ['brand' => "Performance", 'class' => "navbar-narrow"];

$navbar['options']['overview']['text'] = 'Overview';
$navbar['options']['poller']['text']   = 'Poller Modules';
$navbar['options']['discovery']['text'] = 'Discovery Modules';
$navbar['options']['memory']['text']   = 'Poller Memory';
$navbar['options']['snmp']['text']     = 'Poller SNMP';
$navbar['options']['db']['text']       = 'Poller DB';

foreach ($navbar['options'] as $option => $array) {
    if (!isset($vars['view'])) {
        $vars['view'] = "overview";
    }
    if ($vars['view'] == $option) {
        $navbar['options'][$option]['class'] .= " active";
    }
    $navbar['options'][$option]['url'] = generate_url($vars, ['view' => $option]);
}

print_navbar($navbar);
unset($navbar);

if (is_array($device['state']['poller_mod_perf'])) {
    arsort($device['state']['poller_mod_perf']);
}

if ($vars['view'] === 'db') {
    echo generate_box_open();
    echo '<table class="' . OBS_CLASS_TABLE_STRIPED_TWO . ' table-hover"><tbody>' . PHP_EOL;

    foreach (['device_pollerdb_count' => 'MySQL Operations',
              'device_pollerdb_times' => 'MySQL Times'] as $graphtype => $name) {

        echo '<tr><td><h3>' . $name . '</h3></td></tr>';
        echo '<tr><td>';

        $graph = ['type'   => $graphtype,
                  'device' => $device['device_id']];

        print_graph_row($graph);

        echo '</td></tr>';

    }

    echo '</tbody></table>';
    echo generate_box_close();
} elseif ($vars['view'] === 'snmp') {
    echo generate_box_open();
    echo '<table class="' . OBS_CLASS_TABLE_STRIPED_TWO . ' table-hover"><tbody>' . PHP_EOL;

    foreach (['device_pollersnmp_count'        => 'SNMP Requests',
              'device_pollersnmp_times'        => 'SNMP Times',
              'device_pollersnmp_errors_count' => 'SNMP Errors',
              'device_pollersnmp_errors_times' => 'SNMP Errors Times'] as $graphtype => $name) {

        echo '<tr><td><h3>' . $name . '</h3></td></tr>';
        echo '<tr><td>';

        $graph = ['type'   => $graphtype,
                  'device' => $device['device_id']];

        print_graph_row($graph);

        echo '</td></tr>';

    }

    echo '</tbody></table>';
    echo generate_box_close();
} elseif ($vars['view'] === 'memory') {
    echo generate_box_open();
    echo '<table class="' . OBS_CLASS_TABLE_STRIPED_TWO . ' table-hover"><tbody>' . PHP_EOL;

    echo '<tr><td><h3>Memory usage</h3></td></tr>';
    echo '<tr><td>';

    $graph = ['type'   => 'device_pollermemory_perf',
              'device' => $device['device_id']];

    print_graph_row($graph);

    echo '</td></tr>';

    echo '</tbody></table>';
    echo generate_box_close();
} elseif ($vars['view'] === 'poller') {

    echo generate_box_open();
    echo '<table class="' . OBS_CLASS_TABLE_STRIPED_TWO . ' table-hover"><tbody>' . PHP_EOL;

    foreach ($device['state']['poller_mod_perf'] as $module => $time) {

        echo '<tr><td><h3>' . $module . '</h3></td><td style="width: 40px">' . $time . 's</td></tr>';
        echo '<tr><td colspan=2>';

        $graph = ['type'   => 'device_pollermodule_perf',
                  'device' => $device['device_id'],
                  'module' => $module];

        print_graph_row($graph);

        echo '</td></tr>';

    }

    echo '</tbody></table>';
    echo generate_box_close();

} elseif ($vars['view'] === 'discovery') {

    // Get recent discovery performance data for this device
    $discovery_data = dbFetchRows("
        SELECT module, time_taken, discovery_time 
        FROM discovery_perf_history 
        WHERE device_id = ? AND discovery_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY discovery_time DESC
        LIMIT 1000
    ", [$device['device_id']]);
    
    if (!safe_empty($discovery_data)) {
        // Group by module for analysis
        $modules_data = [];
        foreach ($discovery_data as $row) {
            $modules_data[$row['module']][] = $row;
        }
        
        // Create status panel with summary stats
        $total_modules = count($modules_data);
        $total_avg_time = round(array_sum(array_column($discovery_data, 'time_taken')) / count($discovery_data), 3);
        $last_discovery = max(array_column($discovery_data, 'discovery_time'));
        $last_discovery_ago = format_uptime(get_time('now') - strtotime($last_discovery), 'shorter') . ' ago';
        
        $status_boxes = [];
        $status_boxes[] = [
            'title' => 'Active Modules',
            'value' => ['text' => $total_modules, 'class' => 'label-info'],
            'subtitle' => 'Discovered modules'
        ];
        $status_boxes[] = [
            'title' => 'Average Time',
            'value' => ['text' => $total_avg_time . 's', 'class' => 'label-primary'],
            'subtitle' => 'Per module'
        ];
        $status_boxes[] = [
            'title' => 'Last Discovery',
            'value' => ['text' => $last_discovery_ago, 'class' => 'label-success'],
            'subtitle' => format_timestamp($last_discovery)
        ];
        $status_boxes[] = [
            'title' => 'Data Points',
            'value' => ['text' => count($discovery_data), 'class' => 'label-default'],
            'subtitle' => 'Last 30 days'
        ];
        
        echo generate_status_panel($status_boxes);
        
        echo generate_box_open(['title' => 'Discovery Module Performance (Last 30 Days)']);
        echo '<table class="' . OBS_CLASS_TABLE_STRIPED_TWO . ' table-hover"><tbody>' . PHP_EOL;
        
        foreach ($modules_data as $module => $data) {
            $recent_time = $data[0]['time_taken']; // Most recent run
            $avg_time = round(array_sum(array_column($data, 'time_taken')) / count($data), 3);
            $min_time = round(min(array_column($data, 'time_taken')), 3);  
            $max_time = round(max(array_column($data, 'time_taken')), 3);
            $runs = count($data);
            
            echo '<tr><td><h3>' . escape_html($module) . '</h3></td>';
            echo '<td><small>Recent: ' . $recent_time . 's | Avg: ' . $avg_time . 's | Range: ' . $min_time . 's-' . $max_time . 's | Runs: ' . $runs . '</small></td>';
            echo '<td style="width: 40px">' . $recent_time . 's</td></tr>';
            echo '<tr><td colspan=3>';
            
            // Create Chart.js line chart for historical performance data
            $chart_id = 'chart_' . preg_replace('/[^a-zA-Z0-9]/', '_', $module);
            $times_data = array_column($data, 'time_taken');
            $times_labels = array_map(function($row) { 
                return date('M j', strtotime($row['discovery_time'])); 
            }, array_reverse($data));
            
            echo '<div style="height: 120px; position: relative; margin: 10px 0;">';
            echo '<canvas id="' . $chart_id . '"></canvas>';
            echo '</div>';
            echo '<script>';
            echo 'if (typeof Chart !== "undefined") {';
            echo '  const ctx_' . $chart_id . ' = document.getElementById("' . $chart_id . '").getContext("2d");';
            echo '  new Chart(ctx_' . $chart_id . ', {';
            echo '    type: "line",';
            echo '    data: {';
            echo '      labels: ' . json_encode($times_labels) . ',';
            echo '      datasets: [{';
            echo '        label: "Discovery Time (s)",';
            echo '        data: ' . json_encode(array_reverse($times_data)) . ',';
            echo '        borderColor: "#5bc0de",';
            echo '        backgroundColor: "rgba(91, 192, 222, 0.1)",';
            echo '        borderWidth: 2,';
            echo '        fill: true,';
            echo '        tension: 0.1,';
            echo '        pointRadius: 3,';
            echo '        pointHoverRadius: 5';
            echo '      }]';
            echo '    },';
            echo '    options: {';
            echo '      responsive: true,';
            echo '      maintainAspectRatio: false,';
            echo '      plugins: {';
            echo '        legend: { display: false },';
            //echo '        title: {';
            //echo '          display: true,';
            //echo '          text: ' . json_encode($module . ' Performance Trend') . ',';
            //echo '          font: { size: 12 }';
            //echo '        }';
            echo '      },';
            echo '      scales: {';
            echo '        y: {';
            echo '          beginAtZero: true,';
            echo '          title: { display: true, text: "Time (s)" },';
            echo '          grid: { display: true }';
            echo '        },';
            echo '        x: {';
            //echo '          title: { display: true, text: "Discovery Date" },';
            echo '          ticks: { maxRotation: 45 }';
            echo '        }';
            echo '      },';
            echo '      interaction: { intersect: false, mode: "index" }';
            echo '    }';
            echo '  });';
            echo '}';
            echo '</script>';
            
            echo '</td></tr>';
        }
        
        echo '</tbody></table>';
        echo generate_box_close();
        
    } else {
        echo generate_box_open(['title' => 'Discovery Module Performance']);
        echo '<div class="alert alert-info">No historical discovery performance data available yet. ';
        echo 'Data will appear after discovery runs on this device.</div>';
        echo generate_box_close();
    }

} else {

    ?>

    </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Poller Module Times</h3>
                </div>
                <div class="box-body no-padding">
                    <table class="table table-hover table-striped table-condensed">
                        <thead>
                        <tr>
                            <th>Module</th>
                            <th colspan="2">Duration</th>

                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        //r($device['state']['poller_mod_perf']);
                        foreach ($device['state']['poller_mod_perf'] as $module => $time) {
                            if ($time > 0.001) {
                                $perc = format_number_short(float_div($time, $device['last_polled_timetaken']) * 100, 2);

                                echo('    <tr>
      <td><strong>' . $module . '</strong></td>
      <td style="width: 80px;">' . format_value($time) . 's</td>
      <td style="width: 70px;"><span style="color:' . percent_colour($perc) . '">' . $perc . '%</span></td>
    </tr>');

                                // Separate sub-module perf (ie ports)
                                foreach ($device['state']['poller_' . $module . '_perf'] as $submodule => $subtime) {
                                    echo('    <tr>
        <td>&nbsp;<i class="icon-share-alt icon-flip-vertical"></i><strong style="padding-left:1em"><i>' . $submodule . '</i></strong></td>
        <td style="width: 80px;"><i>' . format_value($subtime) . 's</i></td>
        <td style="width: 70px;"></td>
      </tr>');
                                }
                            }
                        }

                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-2">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Poller Times</h3>
                </div>
                <div class="box-body no-padding">
                    <table class="table table-hover table-striped table-condensed ">
                        <thead>
                        <tr>
                            <th>Time</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        $times = is_array($device['state']['poller_history']) ? array_slice($device['state']['poller_history'], 0, 30, TRUE) : [];
                        foreach ($times as $start => $duration) {
                            echo('    <tr>
      <td>' . generate_tooltip_time($start, 'ago') . '</td>
      <td>' . format_uptime($duration) . '</td>
    </tr>');
                        }

                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">


            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Discovery Module Times</h3>
                </div>
                <div class="box-body no-padding">
                    <table class="table table-hover table-striped table-condensed">
                        <thead>
                        <tr>
                            <th>Module</th>
                            <th>Avg</th>
                            <th>Last</th>
                            <th>Time</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        // Get recent discovery module data from database
                        $recent_discovery = dbFetchRows("
                            SELECT module, 
                                   AVG(time_taken) as avg_time,
                                   MAX(discovery_time) as last_run,
                                   COUNT(*) as run_count,
                                   (SELECT time_taken FROM discovery_perf_history dph2 
                                    WHERE dph2.device_id = dph1.device_id AND dph2.module = dph1.module 
                                    ORDER BY discovery_time DESC LIMIT 1) as last_time
                            FROM discovery_perf_history dph1
                            WHERE device_id = ? AND discovery_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                            GROUP BY module
                            ORDER BY avg_time DESC
                        ", [$device['device_id']]);

                        if (!safe_empty($recent_discovery)) {
                            $total_avg_time = array_sum(array_column($recent_discovery, 'avg_time'));
                            $total_last_time = array_sum(array_column($recent_discovery, 'last_time'));
                            
                            foreach ($recent_discovery as $module_data) {
                                if ($module_data['avg_time'] > 0.001) {
                                    $avg_perc = $total_avg_time > 0 ? format_number_short(($module_data['avg_time'] / $total_avg_time) * 100, 1) : 0;
                                    $last_perc = $total_last_time > 0 ? format_number_short(($module_data['last_time'] / $total_last_time) * 100, 1) : 0;
                                    $last_run = format_uptime(get_time('now') - strtotime($module_data['last_run']), 'shorter') . ' ago';

                                    echo('    <tr>
          <td><strong>' . escape_html($module_data['module']) . '</strong> <small>(' . $module_data['run_count'] . ' runs)</small></td>
          <td style="width: 80px;">' . format_value($module_data['avg_time']) . 's <small>(' . $avg_perc . '%)</small><br></td>
          <td style="width: 80px;">' . format_value($module_data['last_time']) . 's <small>(' . $last_perc . '%)</small></td>
          <td style="width: 80px;"><small>' . $last_run . '</small></td>
        </tr>');
                                }
                            }
                        } else {
                            echo '<tr><td colspan="3" class="text-center"><small>No recent discovery data available</small></td></tr>';
                        }

                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-2">

            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Discovery Times</h3>
                </div>
                <div class="box-body no-padding">
                    <table class="table table-hover table-striped  table-condensed ">
                        <thead>
                        <tr>
                            <th>Time</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php

                        // Get recent discovery run totals from database (last 30 runs)
                        $discovery_runs = dbFetchRows("
                            SELECT discovery_time, SUM(time_taken) as total_time
                            FROM discovery_perf_history 
                            WHERE device_id = ?
                            GROUP BY discovery_time 
                            ORDER BY discovery_time DESC 
                            LIMIT 30
                        ", [$device['device_id']]);
                        
                        if (!safe_empty($discovery_runs)) {
                            foreach ($discovery_runs as $run) {
                                $start = strtotime($run['discovery_time']);
                                $duration = $run['total_time'];
                                echo('    <tr>
      <td>' . generate_tooltip_time($start, 'ago') . '</td>
      <td>' . format_uptime($duration) . '</td>
    </tr>');
                            }
                        } else {
                            echo '<tr><td colspan="2" class="text-center"><small>No discovery history available</small></td></tr>';
                        }

                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php

}

// EOF
