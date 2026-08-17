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

/**
 * Calculate which port a MAC is most likely directly connected to
 * Uses multiple heuristics to score each port:
 *   - Port type (physical vs virtual/LAG)
 *   - Trunk status (ifTrunk field)
 *   - STP participation (any STP entry = likely trunk)
 *   - Port naming patterns (uplink/trunk/po patterns)
 *   - MAC count per port (1 MAC = access, 100+ = trunk)
 *   - VLAN count per port (1 VLAN = access, 10+ = trunk)
 *   - CDP/LLDP neighbor presence (neighbor = uplink)
 *
 * @param array $fdb_entries Array of FDB entries for same MAC address
 * @return array FDB entries with added 'fdb_priority' field, sorted by priority (highest first)
 */
function fdb_guess_source_port($fdb_entries) {
    if (safe_count($fdb_entries) <= 1) {
        return $fdb_entries;  // Only one port, no calculation needed
    }

    // Extract unique port IDs
    $port_ids = array_unique(array_filter(array_column($fdb_entries, 'port_id')));
    if (empty($port_ids)) {
        return $fdb_entries;
    }

    // Batch fetch port data (1 query for all ports)
    $ports_query = 'SELECT * FROM `ports` WHERE `port_id` IN (' . implode(',', $port_ids) . ')';
    $ports = dbFetchRows($ports_query);
    $port_data = [];
    foreach ($ports as $port) {
        $port_data[$port['port_id']] = $port;
    }

    // Batch check for STP participation (1 query for all ports)
    // Ports participating in STP are likely trunks/uplinks, not access ports
    $stp_ports = [];
    $stp_query = 'SELECT DISTINCT `port_id` FROM `stp_ports` WHERE `port_id` IN (' . implode(',', $port_ids) . ')';
    $stp_rows = dbFetchColumn($stp_query);
    foreach ($stp_rows as $port_id) {
        $stp_ports[$port_id] = true;
    }

    // Batch fetch per-port statistics (1 query)
    // MAC count and VLAN count are strong indicators of trunk vs access ports
    $port_stats = [];
    $stats_query = 'SELECT `port_id`,
                           COUNT(DISTINCT `mac_address`) as mac_count,
                           COUNT(DISTINCT `vlan_id`) as vlan_count
                    FROM `vlans_fdb`
                    WHERE `port_id` IN (' . implode(',', $port_ids) . ') AND `deleted` = 0
                    GROUP BY `port_id`';
    $stats_rows = dbFetchRows($stats_query);
    foreach ($stats_rows as $stat) {
        $port_stats[$stat['port_id']] = [
            'mac_count' => (int)$stat['mac_count'],
            'vlan_count' => (int)$stat['vlan_count']
        ];
    }

    // Batch check for CDP/LLDP neighbors (1 query for all ports)
    // Ports with neighbors are typically uplinks/interconnects
    $neighbor_ports = [];
    $neighbor_query = 'SELECT DISTINCT `port_id` FROM `neighbours`
                       WHERE `port_id` IN (' . implode(',', $port_ids) . ') AND `active` = 1';
    $neighbor_rows = dbFetchColumn($neighbor_query);
    foreach ($neighbor_rows as $port_id) {
        $neighbor_ports[$port_id] = true;
    }

    // Port type priority scoring
    $type_priorities = [
        'gigabitEthernet'   => 40,
        'ethernetCsmacd'    => 40,
        'ieee80211'         => 30,
        'ieee8023adLag'     => -30,  // LAG = uplink
        'l2vlan'            => -40,  // VLAN interface
        'tunnel'            => -50,  // Tunnel/overlay
        'other'             => -20,
        'propVirtual'       => -50,
        'softwareLoopback'  => -60,
    ];

    // Calculate priority for each entry
    foreach ($fdb_entries as &$entry) {
        $port_id = $entry['port_id'];
        $port = $port_data[$port_id] ?? [];

        $priority = 50;  // Base priority

        // Port type priority
        $priority += $type_priorities[$port['ifType']] ?? 0;

        // Trunk detection - strong signal for uplink
        if (!safe_empty($port['ifTrunk'])) {
            $priority -= 40;
        }

        // STP participation - ports in STP are likely trunks/uplinks, not access ports
        if (isset($stp_ports[$port_id])) {
            $priority -= 30;
        }

        // Port name patterns
        if (preg_match('/uplink|trunk|^po\d+|stack|isl|vpc/i', $port['port_label'])) {
            $priority -= 30;
        }

        // MAC count heuristics - very strong signal
        if (isset($port_stats[$port_id])) {
            $mac_count = $port_stats[$port_id]['mac_count'];

            if ($mac_count == 1) {
                $priority += 60;  // Single MAC = very likely access port
            } elseif ($mac_count <= 5) {
                $priority += 40;  // Few MACs = likely access port
            } elseif ($mac_count <= 20) {
                $priority += 10;  // Some MACs = possibly access port
            } elseif ($mac_count > 1000) {
                $priority -= 50;  // Many MACs = definitely trunk
            } elseif ($mac_count > 100) {
                $priority -= 30;  // Lots of MACs = likely trunk
            }
        }

        // VLAN count heuristics - strong trunk indicator
        if (isset($port_stats[$port_id])) {
            $vlan_count = $port_stats[$port_id]['vlan_count'];

            if ($vlan_count == 1) {
                $priority += 30;  // Single VLAN = likely access port
            } elseif ($vlan_count > 50) {
                $priority -= 60;  // Many VLANs = definitely trunk
            } elseif ($vlan_count > 10) {
                $priority -= 40;  // Several VLANs = likely trunk
            } elseif ($vlan_count > 3) {
                $priority -= 20;  // Few VLANs = possibly trunk
            }
        }

        // CDP/LLDP neighbor presence - strong uplink indicator
        if (isset($neighbor_ports[$port_id])) {
            $priority -= 50;  // Has neighbor = likely uplink/interconnect
        }

        // Store priority
        $entry['fdb_priority'] = $priority;
        $entry['fdb_is_source'] = false;
    }

    // Sort by priority (highest first)
    usort($fdb_entries, function($a, $b) {
        return $b['fdb_priority'] - $a['fdb_priority'];
    });

    // Mark the source port (highest priority)
    if (!safe_empty($fdb_entries)) {
        $fdb_entries[0]['fdb_is_source'] = true;
    }

    return $fdb_entries;
}

/**
 * Display FDB table.
 *
 * @param array $vars
 *
 * @return none
 *
 */
function print_fdbtable($vars)
{
    global $config;

    //r($vars);
    $entries = get_fdbtable_array($vars);

    if (!$entries['count']) {
        // There have been no entries returned. Print the warning.
        print_warning('<h4>No FDB entries found!</h4>');
        return;
    }

    // Source port calculation - only if user enabled and within limits
    $calculate_best = FALSE;
    $max_results = $config['fdb']['guess_source_max_results'];

    if ($vars['guess_source']) {
        // Check if this is a specific MAC search (not wildcard)
        $is_specific_mac = (
            !empty($vars['address']) &&
            !str_contains_array($vars['address'], ['*', '?']) &&
            strlen(str_replace([':', ' ', '-'], '', $vars['address'])) >= 12
        );

        if (!$is_specific_mac) {
            print_warning("Source port calculation requires a specific MAC address search (no wildcards).");
        } elseif ($entries['count'] > $max_results) {
            print_warning("Too many results (" . $entries['count'] . ") to calculate source port. Maximum is " . $max_results . ". Configure \$config['fdb']['guess_source_max_results'] to increase.");
        } else {
            $calculate_best = TRUE;

            // Group entries by MAC address
            $grouped = [];
            foreach ($entries['entries'] as $entry) {
                $grouped[$entry['mac_address']][] = $entry;
            }

            // Calculate source port for each MAC
            $entries['entries'] = [];
            foreach ($grouped as $mac_entries) {
                if (count($mac_entries) > 1) {
                    $mac_entries = fdb_guess_source_port($mac_entries);
                }
                $entries['entries'] = array_merge($entries['entries'], $mac_entries);
            }
        }
    }

    $list = ['device' => FALSE, 'port' => FALSE];


    if (!isset($vars['device']) || is_array($vars['device']) || empty($vars['device']) || $vars['page'] === 'search') {
        $list['device'] = TRUE;
    }
    if (!isset($vars['port']) || is_array($vars['port']) || empty($vars['port']) || $vars['page'] === 'search') {
        $list['port'] = TRUE;
    }

    //r($list);

    $string = generate_box_open();

    $string .= '<table class="table  table-striped table-hover table-condensed">' . PHP_EOL;

    $cols = [
      'device'    => 'Device',
      'mac'       => ['MAC Address', 'style="width: 160px;"'],
      'status'    => ['Status', 'style="width: 100px;"'],
      'port'      => 'Port',
      'trunk'     => 'Trunk/Type',
      'vlan_id'   => 'VLAN ID',
      'vlan_name' => 'VLAN NAME',
      'changed'   => ['Changed', 'style="width: 100px;"']
    ];

    // Add source score column when guess source is enabled
    if ($calculate_best) {
        $cols['source'] = ['Source', 'style="width: 80px;"'];
    }

    if (!$list['device']) {
        unset($cols['device']);
    }
    if (!$list['port']) {
        unset($cols['port']);
    }

    if (!$short) {
        $string .= get_table_header($cols, $vars); // Currently, sorting is not available
    }

    //print_vars($entries['entries']);
    foreach ($entries['entries'] as $entry) {
        if ($entry['deleted']) {
            $port = [];

            $string .= '  <tr class="ignore">' . PHP_EOL;
        } else {
            $port = get_port_by_id_cache($entry['port_id']);

            // Highlight source port
            if (isset($entry['fdb_is_source']) && $entry['fdb_is_source']) {
                $string .= '  <tr class="success">' . PHP_EOL;  // Green highlight
            } elseif ($calculate_best && isset($entry['fdb_priority']) && $entry['fdb_priority'] < 30) {
                $string .= '  <tr class="ignore">' . PHP_EOL;  // Gray out unlikely ports
            } else {
                $string .= '  <tr>' . PHP_EOL;
            }
        }
        if ($list['device']) {
            $dev    = device_by_id_cache($entry['device_id']);
            $string .= '    <td class="entity" style="white-space: nowrap;">' . generate_device_link($dev) . '</td>' . PHP_EOL;
        }
        $string .= '    <td>' . generate_popup_link('mac', format_mac($entry['mac_address'])) . '</td>' . PHP_EOL;
        $string .= '    <td>' . $entry['fdb_status'] . '</td>' . PHP_EOL;
        if ($list['port']) {
            $string .= '    <td class="entity">' . generate_port_link_short($port) . ' ' . $port_error . '</td>' . PHP_EOL;
        }
        $string .= '    <td><span class="label">' . ($port['ifType'] === 'l2vlan' && empty($port['ifTrunk']) ? $port['human_type'] : $port['ifTrunk']) . '</span></td>' . PHP_EOL;
        $string .= '    <td>' . ($entry['vlan_vlan'] ? 'Vlan' . $entry['vlan_vlan'] : '') . '</td>' . PHP_EOL;
        $string .= '    <td>' . $entry['vlan_name'] . '</td>' . PHP_EOL;
        $string .= '    <td>' . generate_tooltip_link(NULL, format_uptime((get_time() - $entry['fdb_last_change']), 'short-2') . ' ago', format_unixtime($entry['fdb_last_change'])) . '</td>' . PHP_EOL;

        // Show source score when guess source is enabled
        if ($calculate_best) {
            $priority = $entry['fdb_priority'] ?? 50;
            $priority_class = '';
            $priority_icon = '';

            if ($entry['fdb_is_source'] ?? false) {
                $priority_class = 'label-success';
                $priority_icon = '<i class="icon-ok-sign"></i> ';
            } elseif ($priority < 30) {
                $priority_class = 'label-default';
                $priority_icon = '<i class="icon-remove-sign"></i> ';
            } else {
                $priority_class = 'label-info';
            }

            $string .= '    <td><span class="label ' . $priority_class . '">' . $priority_icon . $priority . '</span></td>' . PHP_EOL;
        }

        $string .= '  </tr>' . PHP_EOL;
    }

    $string .= '  </tbody>' . PHP_EOL;
    $string .= '</table>';

    $string .= generate_box_close();

    // Print pagination header
    if ($entries['pagination_html']) {
        $string = $entries['pagination_html'] . $string . $entries['pagination_html'];
    }

    // Print FDB table
    echo $string;
}

/**
 * Fetch FDB table array
 *
 * @param array $vars
 *
 * @return array
 *
 */
function get_fdbtable_array($vars) {

    $array = [];

    // With pagination? (display page numbers in header)
    $array['pagination'] = isset($vars['pagination']) && $vars['pagination'];
    pagination($vars, 0, TRUE); // Get default pagesize/pageno
    $array['pageno']   = $vars['pageno'];
    $array['pagesize'] = $vars['pagesize'];
    $start             = $array['pagesize'] * $array['pageno'] - $array['pagesize'];
    $pagesize          = $array['pagesize'];

    $params      = [];
    $where_array = [];
    $join_ports  = FALSE;
    if (!isset($vars['deleted'])) {
        // Do not show deleted entries by default
        $vars['deleted'] = 0;
    }

    foreach ($vars as $var => $value) {

        // Skip empty variables (and array with empty first entry) when building query
        if (safe_empty($value) || (safe_count($value) === 1 && safe_empty($value[0]))) {
            continue;
        }

        switch ($var) {
            case 'device':
            case 'device_id':
                $where_array[] = generate_query_values($value, 'F.device_id');
                break;
            case 'port':
            case 'port_id':
                $where_array[] = generate_query_values($value, 'F.port_id');
                break;
            case 'interface':
            case 'port_name':
                $where_array[] = generate_query_values($value, 'I.port_label', 'LIKE%');
                $join_ports = TRUE;
                break;
            case 'trunk':
                if (get_var_true($value)) {
                    $where_array[] = "(`I`.`ifTrunk` IS NOT NULL AND `I`.`ifTrunk` != '')";
                    $join_ports = TRUE;
                } elseif (get_var_false($value, 'none')) {
                    $where_array[] = "(`I`.`ifTrunk` IS NULL OR `I`.`ifTrunk` = '')";
                    $join_ports = TRUE;
                }
                break;
            case 'vlan_id':
                $where_array[] = generate_query_values($value, 'F.vlan_id');
                break;
            case 'vlan_name':
                $where_array[] = generate_query_values($value, 'V.vlan_name');
                break;
            case 'address':
                if (str_contains_array($value, [ '*', '?' ])) {
                    $like = 'LIKE';
                } else {
                    $like = '%LIKE%';
                }
                $where_array[] = generate_query_values(str_replace([':', ' ', '-', '.', '0x'], '', $value), 'F.mac_address', $like);
                break;
            case 'deleted':
                $where_array[] = 'F.`deleted` = ?';
                $params[] = $value;
        }
    }

    $sort = '';
    if (isset($vars['sort'])) {
        $sort_order = get_sort_order($vars);
        switch ($vars['sort']) {
            case "vlan_id":
                //$sort = " ORDER BY `V`.`vlan_vlan`";
                $sort = generate_query_sort('V.vlan_vlan', $sort_order);
                break;

            case "vlan_name":
                //$sort = " ORDER BY `V`.`vlan_name`";
                $sort = generate_query_sort('V.vlan_name', $sort_order);
                break;

            case "port":
                //$sort       = " ORDER BY `I`.`port_label`";
                $sort = generate_query_sort('I.port_label', $sort_order);
                $join_ports = TRUE;
                break;

            case "changed":
                //$sort = " ORDER BY `F`.`fdb_last_change`";
                $sort = generate_query_sort('F.fdb_last_change', $sort_order);
                break;

            case "mac":
            default:
                //$sort = " ORDER BY `mac_address`";
                $sort = generate_query_sort('mac_address', $sort_order);
        }
    } else {
        $sort = '';
    }

    // Show FDB tables only for permitted ports
    $query_permitted = generate_query_permitted_ng([ 'device', 'port' ], [ 'device_table' => 'F', 'port_table' => 'F', 'port_null' => TRUE ]);

    $query = 'FROM `vlans_fdb` AS F ';
    $query .= 'LEFT JOIN `vlans` as V ON F.`vlan_id` = V.`vlan_vlan` AND F.`device_id` = V.`device_id` ';
    if ($join_ports) {
        $query .= 'LEFT JOIN `ports` AS I ON I.`port_id` = F.`port_id` ';
    }
    $query       .= generate_where_clause($where_array, $query_permitted);
    $query_count = 'SELECT COUNT(*) ' . $query;
    $query       = 'SELECT F.*, V.`vlan_vlan`, V.`vlan_name` ' . $query;
    $query       .= $sort;
    $query       .= " LIMIT $start,$pagesize";

    //r($query);
    //r($params);

    // Query addresses
    //$array['entries'] = dbFetchRows($query, $params, TRUE);
    $array['entries'] = dbFetchRows($query, $params);

    if ($array['pagination']) {
        // Query address count
        $array['count']           = dbFetchCell($query_count, $params);
        $array['pagination_html'] = pagination($vars, $array['count']);
    } else {
        $array['count'] = safe_count($array['entries']);
    }

    return $array;
}

// EOF
