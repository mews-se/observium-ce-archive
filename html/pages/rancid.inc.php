<?php

if ($_SESSION['userlevel'] < 7) {
    print_error_permission();
    return;
}

?>

<div class="row">
    <div class="col-md-12">

        <?php

        $pattern = trim($vars['pattern'] ?? '');
        $search_depth = $vars['search_depth'] ?? 'current';
        $context_lines = 2;

        $form = [
                'type'          => 'rows', 'space' => '5px', 'submit_by_key' => TRUE,
                'url'           => generate_url(['page' => 'rancid']),
        ];

        $form['row'][0]['pattern'] = [
                'type' => 'text', 'name' => 'Pattern', 'placeholder' => 'Regex pattern (PCRE)',
                'width' => '100%', 'div_class' => 'col-lg-3 col-md-4 col-sm-6', 'value' => $pattern
        ];

        $form['row'][0]['device_id'] = [
                'type'     => 'multiselect',
                'name'     => 'Filter by Device(s)',
                'id'       => 'device_id', // The actual element ID/name
                'width'    => '100%',
                'div_class'=> 'col-lg-5 col-md-7 col-sm-10',
                //'attribs'  => ['data-load' => 'devices', 'data-placeholder' => 'All Devices'],
                'groups'   => ['up' => 'Up', 'down' => 'Down', 'disabled' => 'Disabled'],
                'values' => generate_form_values('device'),
                'value'    => $vars['device_id']
        ];

        $form['row'][0]['search_depth'] = [
                'type' => 'select', 'name' => 'Search Depth', 'width' => '100%', 'div_class' => 'col-lg-2 col-md-3 col-sm-4',
                'value' => $search_depth,
                'values' => ['current' => 'Current Only', 'all' => 'All History', '7' => 'Last 7 Days', '30' => 'Last 30 Days', '90' => 'Last 90 Days', '365' => 'Last Year']
        ];
        $form['row'][0]['search'] = [
                'type' => 'submit', 'icon' => 'icon-search',
                'div_class' => 'col-lg-1 col-md-2 col-sm-2', 'right'     => TRUE
        ];


        print_form($form); unset($form);

        ?>
    </div>
</div>

<?php

if ($pattern !== '') {
    if (false === @preg_match("/{$pattern}/", '')) {
        print_error("Invalid Regex Pattern", "The pattern you entered is not a valid PCRE regular expression.");
        return;
    }

    echo '<h3>Searching configs for pattern: <code>' . htmlspecialchars($pattern) . '</code></h3>';

    $valid_config_dirs = [];
    $config_dirs = is_array($config['rancid_configs']) ? $config['rancid_configs'] : [$config['rancid_configs']];
    foreach ($config_dirs as $dir) { if (is_dir(rtrim($dir, '/'))) { $valid_config_dirs[] = rtrim($dir, '/'); } }
    if (empty($valid_config_dirs)) { print_error("No valid RANCID config directories found."); return; }

    // hostnames from ids
    $filter_hostnames = [];
    if (!empty($vars['device_id'])) {
        $where = generate_where_clause(generate_query_values($vars['device_id'], 'device_id'));
        $allowed_devices = dbFetchRows("SELECT `hostname` FROM `devices` $where");
        $filter_hostnames = array_column($allowed_devices, 'hostname');
    }

    $all_results = [];
    foreach ($valid_config_dirs as $config_dir) {
        set_time_limit(300);
        $files = new \GlobIterator($config_dir . '/*');
        foreach ($files as $file) {
            if ($file->isDir()) { continue; }
            $hostname = $file->getBasename();

            // CHANGED: Apply the new hostname filter
            if (!empty($filter_hostnames) && !in_array($hostname, $filter_hostnames)) {
                continue;
            }

            if (!isset($all_results[$hostname])) { $all_results[$hostname] = ['device' => get_rancid_device_link($hostname), 'sources' => []]; }
            $source_matches = [];
            $live_matches = search_config_file($file->getPathname(), $pattern, $context_lines);
            if (!empty($live_matches)) { $source_matches['live'] = ['label' => 'Current version', 'data' => $live_matches]; }
            if ($search_depth !== 'current') {
                if (is_dir($config_dir . '/.git')) {
                    $git_matches = search_rancid_git_history($config_dir, $hostname, $pattern, $context_lines, $search_depth);
                    if (!empty($git_matches)) { $source_matches['git'] = ['label' => 'Git History', 'data' => $git_matches]; }
                } elseif (is_dir($config_dir . '/.svn')) {
                    $svn_matches = search_rancid_svn_history($config_dir, $hostname, $pattern, $context_lines, $search_depth);
                    if (!empty($svn_matches)) { $source_matches['svn'] = ['label' => 'SVN History', 'data' => $svn_matches]; }
                }
            }
            if (!empty($source_matches)) { $all_results[$hostname]['sources'][$config_dir] = $source_matches; }
        }
    }

    echo '<div class="config-search-container" style="margin-top: 20px;">';
    foreach ($all_results as $hostname => $result) {
        if (empty($result['sources'])) { continue; }
        render_collapsible_results($hostname, $result, $pattern);
    }
    echo '</div>';
}

function get_rancid_device_link($hostname) {
    $device = dbFetchRow("SELECT * FROM `devices` WHERE `hostname` = ?", [$hostname]);
    return $device ? generate_device_link($device, escape_html($hostname), ['tab' => 'showconfig']) : escape_html($hostname);
}

function search_config_file($file_or_content, $pattern, $context) {
    $is_filename = (strpos($file_or_content, "\n") === false && is_file($file_or_content));
    $lines = $is_filename ? file($file_or_content, FILE_IGNORE_NEW_LINES) : explode("\n", $file_or_content);
    if (empty($lines)) { return []; }
    $match_indices = [];
    foreach ($lines as $i => $line) { if (preg_match("/{$pattern}/i", $line)) { $match_indices[] = $i; } }
    if (empty($match_indices)) { return []; }
    $lines_to_show = [];
    foreach ($match_indices as $i) { for ($j = max(0, $i - $context); $j <= min(count($lines) - 1, $i + $context); $j++) { $lines_to_show[$j] = true; } }
    $results = []; $prev_index = -2;
    foreach ($lines_to_show as $i => $val) {
        if ($i > $prev_index + 1 && !empty($results)) { $results[] = ['separator' => true]; }
        $results[] = ['line_ref' => $i + 1, 'text' => $lines[$i], 'highlight' => in_array($i, $match_indices)];
        $prev_index = $i;
    }
    return $results;
}

function search_rancid_git_history($dir, $filename, $pattern, $context, $history_limit = 'all') {
    $escaped_dir = escapeshellarg($dir);
    $rev_list_param = '`git rev-list --all`';
    if (is_numeric($history_limit)) {
        $rev_list_param = '`git rev-list --all --since="' . (int)$history_limit . ' days ago"`';
    }
    $rev_list_check = shell_exec("cd $escaped_dir && " . str_replace('`', '', $rev_list_param) . " --max-count=1 2>&1");
    if (empty(trim($rev_list_check)) || strpos($rev_list_check, 'fatal:') === 0) { return []; }
    $cmd_grep = sprintf("cd %s && git --no-pager grep -n -i -C %d -E %s %s -- %s 2>&1", $escaped_dir, $context, escapeshellarg($pattern), $rev_list_param, escapeshellarg($filename));
    $output = shell_exec($cmd_grep);
    if (!$output || strpos($output, 'fatal:') === 0) { return []; }
    $results = []; $commit_dates = [];
    $escaped_re_filename = preg_quote($filename, '/');
    $re_match   = '/^([a-f0-9]+):' . $escaped_re_filename . ':(\d+):(.*)$/';
    $re_context = '/^([a-f0-9]+):' . $escaped_re_filename . '-(\d+)-(.*)$/';
    foreach (explode("\n", $output) as $line) {
        if (preg_match($re_match, $line, $m)) {
            $commit_dates[$m[1]] = true;
            $results[] = ['commit' => $m[1], 'line_ref' => $m[2], 'text' => $m[3], 'highlight' => true];
        } else if (preg_match($re_context, $line, $m)) {
            $results[] = ['commit' => $m[1], 'line_ref' => $m[2], 'text' => $m[3], 'highlight' => false];
        } else if (trim($line) === '--') {
            $results[] = ['separator' => true];
        }
    }
    if (!empty($commit_dates)) {
        $hashes = implode(' ', array_keys($commit_dates));
        $date_cmd = sprintf("cd %s && git --no-pager show -s --format=\"%%H %%ci\" %s", $escaped_dir, $hashes);
        foreach (explode("\n", trim(shell_exec($date_cmd))) as $date_line) {
            if (preg_match('/^([a-f0-9]+) (.*)$/', $date_line, $m)) { $commit_dates[$m[1]] = $m[2]; }
        }
        foreach ($results as &$res) { if (isset($res['commit'])) { $res['date'] = $commit_dates[$res['commit']]; } }
    }
    return $results;
}

function search_rancid_svn_history($dir, $filename, $pattern, $context, $history_limit = 'all') {
    $escaped_dir = escapeshellarg($dir);
    $escaped_filename = escapeshellarg($filename);
    $revision_param = '';
    if (is_numeric($history_limit)) {
        try {
            $start_date = new DateTime('-' . (int)$history_limit . ' days', new DateTimeZone('UTC'));
            $revision_param = '-r "HEAD:{' . $start_date->format('Y-m-d') . '}"';
        } catch (Exception $e) { /* ignore invalid date */ }
    }
    $log_output = shell_exec("cd $escaped_dir && svn log -q --limit=1 $revision_param $escaped_filename 2>&1");
    if (!$log_output || strpos($log_output, 'svn: E') === 0 || strpos($log_output, 'is not a working copy') !== false) { return []; }
    $all_log_output = shell_exec("cd $escaped_dir && svn log -q $revision_param $escaped_filename 2>&1");
    preg_match_all('/^r(\d+)\s\|/m', $all_log_output, $rev_matches);
    if (empty($rev_matches[1])) { return []; }
    $results = []; $rev_dates = [];
    foreach ($rev_matches[1] as $rev) {
        $content = shell_exec("cd $escaped_dir && svn cat -r $rev $escaped_filename 2>&1");
        if (strpos($content, 'svn: E') === 0) { continue; }
        $sub_results = search_config_file($content, $pattern, $context);
        if (!empty($sub_results)) {
            $rev_dates[$rev] = true;
            foreach($sub_results as $res) { $res['rev'] = $rev; $results[] = $res; }
        }
    }
    if (!empty($rev_dates)) {
        $revs = '-r ' . implode(' -r ', array_keys($rev_dates));
        $date_cmd = "cd $escaped_dir && svn log $revs $escaped_filename --limit " . count($rev_dates) . " 2>&1";
        preg_match_all('/^r(\d+) \| [^|]+ \| ([^|]+) \(/m', shell_exec($date_cmd), $matches, PREG_SET_ORDER);
        foreach ($matches as $m) { $rev_dates[$m[1]] = trim($m[2]); }
        foreach ($results as &$res) { if (isset($res['rev'])) { $res['date'] = $rev_dates[$res['rev']]; } }
    }
    return $results;
}

function render_collapsible_results($hostname, $result, $pattern) {
    $toggle_id = 'toggle-' . preg_replace('/[^a-zA-Z0-9]/', '', $hostname) . rand(1000, 9999);
    $summary_parts = [];
    $total_matches = 0;
    foreach($result['sources'] as $source_dir => $matches) {
        $count = 0;
        foreach($matches as $res) { foreach($res['data'] as $d) { if($d['highlight'] ?? false) $count++; } }
        if ($count > 0) { $total_matches += $count; $summary_parts[] = "<strong>$count</strong> in <code>" . basename($source_dir) . "</code>"; }
    }
    if ($total_matches === 0) return;
    $summary = $total_matches . " total matches (" . implode(', ', $summary_parts) . ")";
    echo '<div class="config-search-item"><input type="checkbox" class="config-search-toggle" id="' . $toggle_id . '">';
    echo '<label for="' . $toggle_id . '" class="config-search-header"><span class="config-search-devicename">' . $result['device'] . '</span>';
    echo '<span class="config-search-summary">' . $summary . '</span></label><div class="config-search-content">';
    echo '<table class="table table-hover table-condensed-more table-striped table-bordered"><tbody>';
    foreach ($result['sources'] as $source_dir => $matches) {
        echo '<tr><td colspan="2" style="font-weight: bold; border-top: 2px solid #ddd; background-color: #fafafa;">Source: <code>' . escape_html($source_dir) . '</code></td></tr>';
        foreach($matches as $res) {
            echo '<tr><td colspan="2" class="bg-gray-light"><strong>' . $res['label'] . '</strong></td></tr>';
            foreach($res['data'] as $match) {
                if (isset($match['separator'])) { echo '<tr><td colspan="2" style="border-top: 2px solid #eee; padding: 0;"></td></tr>'; continue; }
                $line_html = htmlspecialchars($match['text']);
                $row_style = $match['highlight'] ? 'background-color:#fff9ea;' : '';
                if ($match['highlight']) { $line_html = preg_replace("/(" . $pattern . ")/i", '<mark>$1</mark>', $line_html); }
                echo '<tr style="' . $row_style . '"><td style="white-space: nowrap; width: 150px; vertical-align: top;">';
                if (isset($match['commit'])) {
                    echo 'git: ' . substr($match['commit'], 0, 7) . ':' . $match['line_ref'];
                    echo '<br><small class="text-muted">' . escape_html($match['date']) . '</small>';
                } else if (isset($match['rev'])) {
                    echo 'svn: r' . $match['rev'] . ':' . $match['line_ref'];
                    echo '<br><small class="text-muted">' . escape_html($match['date']) . '</small>';
                } else { echo $match['line_ref']; }
                echo '</td><td><code>' . $line_html . '</code></td></tr>';
            }
        }
    }
    echo '</tbody></table></div></div>';
}
?>
<style type="text/css">
    .config-search-item { margin-bottom: 5px; }
    .config-search-toggle { display: none; }
    .config-search-header { display: block; padding: 10px 15px; background-color: #f5f5f5; border: 1px solid #ddd; border-radius: 3px; cursor: pointer; font-size: 16px; font-weight: bold; color: #333; transition: background-color 0.2s ease; }
    .config-search-header:hover { background-color: #e9e9e9; }
    .config-search-header::before { content: '▶'; display: inline-block; margin-right: 10px; font-size: 10px; transition: transform 0.2s ease-in-out; }
    .config-search-summary { float: right; font-size: 12px; font-weight: normal; color: #777; line-height: 20px; }
    .config-search-content { display: none; border: 1px solid #ddd; border-top: none; padding: 0; border-radius: 0 0 3px 3px; overflow: hidden; }
    .config-search-toggle:checked ~ .config-search-content { display: block; }
    .config-search-toggle:checked ~ .config-search-header::before { transform: rotate(90deg); }
    .config-search-toggle:checked ~ .config-search-header { border-radius: 3px 3px 0 0; }
</style>