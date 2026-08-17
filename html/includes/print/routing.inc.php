<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     web
 * @copyright  (C) Adam Armstrong
 *
 */

/**
 * Display bgp peers.
 *
 * Display pages with BGP Peers.
 * Examples:
 * print_bgp() - display all bgp peers from all devices
 * print_bgp(array('pagesize' => 99)) - display 99 bgp peers from all device
 * print_bgp(array('pagesize' => 10, 'pageno' => 3, 'pagination' => TRUE)) - display 10 bgp peers from page 3 with
 * pagination header print_bgp(array('pagesize' => 10, 'device' = 4)) - display 10 bgp peers for device_id 4
 *
 * @param array $vars
 *
 * @return void
 *
 */
function print_bgp_peer_table($vars) {

    $entries = get_bgp_array($vars);
    //r($entries);

    if (!$entries['count']) {
        // There have been no entries returned. Print the warning.
        print_warning('<h4>No BGP peers found!</h4>');
        return;
    }

    // Entries have been returned. Print the table.
    $list = ['device' => FALSE];
    if ($vars['page'] !== 'device') {
        $list['device'] = TRUE;
    }

    switch ($vars['graph']) {
        case 'prefixes_ipv4unicast':
        case 'prefixes_ipv4multicast':
        case 'prefixes_ipv4vpn':
        case 'prefixes_ipv6unicast':
        case 'prefixes_ipv6multicast':
        case 'macaccounting_bits':
        case 'macaccounting_pkts':
        case 'updates':
            $table_class   = 'table-striped-two';
            $list['graph'] = TRUE;
            break;
        default:
            $table_class   = 'table-striped';
            $list['graph'] = FALSE;
    }

    $string = generate_box_open();

    $string .= '<table class="table  ' . $table_class . ' table-hover table-condensed ">' . PHP_EOL;

    $cols = [
      'state-marker' => '',
      [NULL, 'style' => 'width: 1px;'],
      'device'   => ['device' => 'Local address', 'style' => 'width: 180px;'],
      'local_as' => ['local_as' => 'Local AS / VRF', 'style' => 'width: 110px;'],
      [NULL, 'style' => 'width: 20px;'],
      'peer_ip'  => ['peer_ip' => 'Peer address', 'style' => 'width: 180px;'],
      ['type' => 'Type', 'peer_as' => 'Remote AS'],
      ['Family', 'style' => 'width: 50px;'],
      'state'    => ['state' => 'State'],
      'uptime'   => ['uptime' => 'Uptime / Updates', 'style' => 'width: 160px;'],
    ];
    //if (!$list['device']) { unset($cols['device']); }
    $string .= generate_table_header($cols, $vars);

    $string .= '  <tbody>' . PHP_EOL;

    foreach ($entries['entries'] as $peer) {
        $local_dev  = device_by_id_cache($peer['device_id']);
        //$local_as   = ($list['device'] ? ' (AS' . $peer['human_local_as'] . ')' : ''); // Disabled - redundant with Local AS column
        $local_as   = '';
        $local_icon = get_icon($GLOBALS['config']['entities']['device']['icon']);
        $local_name = $local_icon . generate_device_link_short($local_dev, ['tab' => 'routing', 'proto' => 'bgp'], 18);
        $local_ip   = generate_device_link($local_dev, $peer['human_localip'] . $local_as, ['tab' => 'routing', 'proto' => 'bgp']);
        $peer_as    = 'AS' . $peer['human_remote_as'];
        if ($peer['peer_device_id']) {
            $peer_dev  = device_by_id_cache($peer['peer_device_id']);
            $peer_icon = get_icon($GLOBALS['config']['entities']['device']['icon']);
            $peer_name = $peer_icon . generate_device_link_short($peer_dev, ['tab' => 'routing', 'proto' => 'bgp'], 18);
        } else {
            // Wrap reverse DNS hostname with CSS truncation
            $peer_name = '<small style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">' . escape_html($peer['reverse_dns']) . '</small>';
        }

        //$peer_ip        = generate_entity_link("bgp_peer", $peer, $peer['human_remoteip']);
        $peer_ip        = generate_link($peer['human_remoteip'], ['page' => 'routing' , 'proto' => 'bgp', 'peer_ip' => $peer['human_remoteip'], 'view' => 'graphs', 'graph' => 'updates']);

        $peer_afis      = &$entries['afisafi'][$peer['device_id']][$peer['bgpPeer_id']];
        $peer_afis_html = [];

        // Generate AFI/SAFI labels
        foreach ($peer_afis as $peer_afi) {
            // $peer_afi_html = '<span class="label-group">';
            if (isset($GLOBALS['config']['routing_afis_name'][$peer_afi['afi']])) {
                $afi_num   = $GLOBALS['config']['routing_afis_name'][$peer_afi['afi']];
                $afi_class = $GLOBALS['config']['routing_afis'][$afi_num]['class'];
            } else {
                $afi_class = 'default';
            }

            if (isset($GLOBALS['config']['routing_safis_name'][$peer_afi['safi']])) {
                // Named SAFI
                $safi_num   = $GLOBALS['config']['routing_safis_name'][$peer_afi['safi']];
                $safi_class = $GLOBALS['config']['routing_safis'][$safi_num]['class'];

            } elseif (isset($GLOBALS['config']['routing_safis'][$peer_afi['safi']])) {
                // Numeric SAFI
                $safi_num         = $peer_afi['safi'];
                $peer_afi['safi'] = $GLOBALS['config']['routing_safis'][$safi_num]['name'];
                $safi_class       = $GLOBALS['config']['routing_safis'][$safi_num]['class'];
            } else {
                $safi_class = 'default';
            }

            $peer_afi_items = [
                [ 'event' => $afi_class,  'text' => $peer_afi['afi'] ],
                [ 'event' => $safi_class, 'text' => $peer_afi['safi'] ],
            ];
            $peer_afi_html  = get_label_group($peer_afi_items);
            //r($peer_afi_html);
            $peer_afis_html[] = $peer_afi_html;
        }

        $string .= '  <tr class="' . $peer['html_row_class'] . '">' . PHP_EOL;
        $string .= '     <td class="state-marker"></td>' . PHP_EOL;
        $string .= '     <td></td>' . PHP_EOL;
        $string .= '     <td style="white-space: nowrap; max-width: 180px; overflow: hidden; text-overflow: ellipsis;" class="entity">' . $local_ip . '<br />' . $local_name . '</td>' . PHP_EOL;
        $string .= '     <td><strong><span class="label label-' . $peer['peer_local_class'] . '">AS' . $peer['human_local_as'] . '</span></strong>';
        if (!safe_empty($peer['virtual_name'])) {
            $vitual_type = isset($GLOBALS['config']['os'][$local_dev['os']]['snmp']['virtual_type']) ? nicecase($GLOBALS['config']['os'][$local_dev['os']]['snmp']['virtual_type']) : 'VRF';
            $string      .= '<br /><span class="label label-primary">' . $vitual_type . ': ' . $peer['virtual_name'] . '</span>';
        }
        $string .= '</td>' . PHP_EOL;
        $string .= '     <td><span class="text-success"><i class="glyphicon glyphicon-arrow-right"></i></span></td>' . PHP_EOL;
        $string .= '     <td style="white-space: nowrap; max-width: 180px; overflow: hidden; text-overflow: ellipsis;" class="entity">' . $peer_ip . '<br />' . $peer_name . '</td>' . PHP_EOL;
        $string .= '     <td style="max-width: 200px;"><span class="label label-' . $peer['peer_type_class'] . '">' . $peer['peer_type'] . '</span> ';
        $string .= '<a href="'.generate_url(['page' => 'routing' , 'proto' => 'bgp', 'peer_as' => $peer['human_remote_as'], 'view' => 'graphs', 'graph' => 'updates']).'">
                           <span class="label label-' . $peer['peer_type_class'] . '">' . $peer_as . '</span>
                           </a>
                           <br /><small style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">' . escape_html($peer['astext']) . '</small></td>' . PHP_EOL;
        $string .= '     <td>' . implode('<br />', $peer_afis_html) . '</td>' . PHP_EOL;
        $string .= '     <td><strong><span class=" label label-' . $peer['admin_class'] . '">' . $peer['bgpPeerAdminStatus'] . '</span><br /><span class="label label-' . $peer['state_class'] . '">' . $peer['bgpPeerState'] . '</span></strong></td>' . PHP_EOL;
        $string .= '     <td style="white-space: nowrap">' . format_uptime($peer['bgpPeerFsmEstablishedTime']) . '<br />
                Updates: <i class="icon-circle-arrow-down text-success"></i> ' . format_si($peer['bgpPeerInUpdates']) . ' <i class="icon-circle-arrow-up text-primary"></i> ' . format_si($peer['bgpPeerOutUpdates']) . '</td>' . PHP_EOL;
        $string .= '  </tr>' . PHP_EOL;

        // Graphs
        $peer_graph = FALSE;
        switch ($vars['graph']) {
            case 'prefixes_ipv4unicast':
            case 'prefixes_ipv4multicast':
            case 'prefixes_ipv4vpn':
            case 'prefixes_ipv6unicast':
            case 'prefixes_ipv6multicast':
                $afisafi = preg_replace('/prefixes_(ipv[46])(\w+)/', '$1.$2', $vars['graph']); // prefixes_ipv6unicast ->> ipv6.unicast
                if (isset($peer_afis[$afisafi]) && $peer['bgpPeer_id']) {
                    $graph_array['type'] = 'bgp_' . $vars['graph'];
                    $graph_array['id']   = $peer['bgpPeer_id'];
                    $peer_graph          = TRUE;
                }
                break;

            case 'updates':
                if ($peer['bgpPeer_id']) {
                    $graph_array['type'] = 'bgp_updates';
                    $graph_array['id']   = $peer['bgpPeer_id'];
                    $peer_graph          = TRUE;
                }
                break;

            case 'macaccounting_bits':
            case 'macaccounting_pkts':
                // FIXME. I really still not know it works or not? -- mike
                // This part copy-pasted from old code as is
                $acc      = dbFetchRow("SELECT * FROM `mac_accounting` AS M
                        LEFT JOIN `ip_mac` AS I ON M.mac = I.mac_address
                        LEFT JOIN `ports` AS P ON P.port_id = M.port_id
                        LEFT JOIN `devices` AS D ON D.device_id = P.device_id
                        WHERE I.ip_address = ?", [$peer['bgpPeerRemoteAddr']]);
                $database = get_rrd_path($device, "cip-" . $acc['ifIndex'] . "-" . $acc['mac'] . ".rrd");
                if (is_array($acc) && is_file($database)) {
                    $peer_graph          = TRUE;
                    $graph_array['id']   = $acc['ma_id'];
                    $graph_array['type'] = $vars['graph'];
                }
                break;
        }

        if ($peer_graph) {
            $graph_array['to'] = get_time();
            $string            .= '  <tr class="' . $peer['html_row_class'] . '">' . PHP_EOL;
            $string            .= '    <td class="state-marker"></td><td colspan="10" style="white-space: nowrap">' . PHP_EOL;

            $string .= generate_graph_row($graph_array);

            $string .= '    </td>' . PHP_EOL . '  </tr>' . PHP_EOL;
        } elseif ($list['graph']) {
            // Empty row for correct view class table-striped-two
            $string .= '  <tr class="' . $peer['html_row_class'] . '"><td class="state-marker"></td><td colspan="10"></td></tr>' . PHP_EOL;
        }
    }

    $string .= '  </tbody>' . PHP_EOL;
    $string .= '</table>';

    $string .= generate_box_close();

    // Print pagination header
    if ($entries['pagination_html']) {
        $string = $entries['pagination_html'] . $string . $entries['pagination_html'];
    }

    // Print
    echo $string;

}

// Populate bgp page specific bgp stuff. FIXME - replace
function bgp_cache_populate() {
    global $cache;

    foreach (dbFetchRows('SELECT `device_id`,`bgpPeer_id`,`local_as`,`bgpPeerState`,`bgpPeerAdminStatus`,`bgpPeerRemoteAs` FROM `bgpPeers`' .
                         generate_where_clause(generate_query_permitted_ng(['device']))) as $bgp) {

        // Skip disabled devices
        if (!$GLOBALS['config']['web_show_disabled'] && in_array($bgp['device_id'], $cache['devices']['disabled'])) {
            continue;
        }

        // if (!device_permitted($bgp)) {
        //     continue;
        // }

        $cache['bgp']['permitted'][] = $bgp['bgpPeer_id']; // Collect permitted peers
        if ($bgp['bgpPeerAdminStatus'] === 'start' || $bgp['bgpPeerAdminStatus'] === 'running') {
            $cache['bgp']['start'][] = $bgp['bgpPeer_id']; // Collect START peers (bgpPeerAdminStatus = (start || running))
            if ($bgp['bgpPeerState'] === 'established') {
                $cache['bgp']['up'][] = $bgp['bgpPeer_id']; // Collect UP peers (bgpPeerAdminStatus = (start || running), bgpPeerState = established)
            }
        } else {
            $cache['routing']['bgp']['down']++;
        }
        if ($bgp['local_as'] == $bgp['bgpPeerRemoteAs']) {
            $cache['bgp']['internal'][] = $bgp['bgpPeer_id']; // Collect iBGP peers
        } else {
            $cache['bgp']['external'][] = $bgp['bgpPeer_id']; // Collect eBGP peers
        }

        if (is_bgp_as_private($bgp['bgpPeerRemoteAs'])) {
            $cache['bgp']['private'][] = $bgp['bgpPeer_id']; // Collect Private AS peers
        } else {
            //$cache['bgp']['public'][] = $bgp['bgpPeer_id']; // Collect Public AS peers
        }
    }
}


/**
 * Params:
 *
 * pagination, pageno, pagesize
 * device, type, adminstatus, state
 */
function get_bgp_array($vars) {

    $array = [];

    // With pagination? (display page numbers in header)
    //$array['pagination'] = (isset($vars['pagination']) && $vars['pagination']);
    $array['pagination'] = TRUE;
    pagination($vars, 0, TRUE); // Get default pagesize/pageno
    $array['pageno']   = $vars['pageno'];
    $array['pagesize'] = $vars['pagesize'];
    $start             = $array['pagesize'] * $array['pageno'] - $array['pagesize'];
    $pagesize          = $array['pagesize'];

    // populate bgp cache for use here
    bgp_cache_populate();
    $cache_bgp = &$GLOBALS['cache']['bgp'];
    //r($cache_bgp);

    // Begin query generate
    $single_device = FALSE;
    $where         = [];
    foreach ($vars as $var => $value) {
        if (!safe_empty($value)) {
            switch ($var) {
                case "group":
                case "group_id":
                    $values  = get_group_entities($value);
                    $where[] = generate_query_values($values, 'bgpPeer_id');
                    break;

                case 'device':
                case 'device_id':
                    $where[]       = generate_query_values($value, 'device_id');
                    $single_device = $vars['page'] === 'device' && device_permitted($value);
                    break;

                case 'peer':
                case 'peer_id':
                    $where[] = generate_query_values($value, 'peer_device_id');
                    break;

                case 'local_ip':
                    $where[] = generate_query_values(ip_uncompress($value), 'bgpPeerLocalAddr');
                    break;

                case 'peer_ip':
                    $where[] = generate_query_values(ip_uncompress($value), 'bgpPeerRemoteAddr');
                    break;

                case 'local_as':
                    $where[] = generate_query_values(bgp_asdot_to_asplain($value), 'local_as');
                    break;

                case 'peer_as':
                    if (is_string($value) && preg_match_all('/AS(?<as>[\d\.]+):/', $value, $matches)) {
                        //r($matches);
                        $value = $matches['as'];
                    }
                    $where[] = generate_query_values(bgp_asdot_to_asplain($value), 'bgpPeerRemoteAs');
                    break;

                case 'type':
                    if ($value === 'external' || $value === 'ebgp') {
                        $where[] = generate_query_values($cache_bgp['external'], 'bgpPeer_id');
                    } elseif ($value === 'internal' || $value === 'ibgp') {
                        $where[] = generate_query_values($cache_bgp['internal'], 'bgpPeer_id');
                    }
                    break;

                case 'public':
                    $value = !get_var_true($value); // invert to private
                    // do not break here
                case 'private':
                    if (get_var_true($value)) {
                        $where[] = generate_query_values($cache_bgp['private'], 'bgpPeer_id');
                    } else {
                        $where[] = generate_query_values($cache_bgp['private'], 'bgpPeer_id', '!='); // NOT IN
                    }
                    break;

                case 'adminstatus':
                    if (get_var_true($value, 'start')) {
                        $where[] = generate_query_values($cache_bgp['start'], 'bgpPeer_id');
                    } elseif ($value === 'stop') {
                        $where[] = generate_query_values($cache_bgp['start'], 'bgpPeer_id', '!='); // NOT IN
                    }
                    break;

                case 'status':
                case 'state':
                    if (get_var_true($value, 'up')) {
                        $where[] = generate_query_values($cache_bgp['up'], 'bgpPeer_id');
                    } elseif ($value === 'down') {
                        $where[] = generate_query_values($cache_bgp['up'], 'bgpPeer_id', '!='); // NOT IN
                    }
                    break;

                case 'vrf':
                    // List of VRFs
                    $where[] = generate_query_values($value, 'virtual_name');
                    break;

                case 'vrfs':
                    // Contains VRFs
                    if (get_var_true($value)) {
                        $where[] = '`virtual_name` NOT IS NULL';
                    } else {
                        $where[] = '`virtual_name` IS NULL';
                    }
                    break;
            }
        }
    }

    // Show peers only for permitted devices
    /*
    if ($single_device) {
      // skip extra permissions check
    } elseif ($_SESSION['userlevel'] < 5) {
      $where[] = generate_query_values_ng($cache_bgp['permitted'], 'bgpPeer_id');
    } elseif (!$GLOBALS['config']['web_show_disabled'] && $GLOBALS['cache']['devices']['stat']['disabled']) {
      // Exclude disabled devices for Global Read+
      $where[] = generate_query_values_ng($GLOBALS['cache']['devices']['disabled'], 'device_id', '!=');
    } */

    $where = generate_where_clause($where, generate_query_permitted_ng('device'));

    // Use only bgpPeer_id and device_id in query!
    $query_count    = 'SELECT COUNT(*) FROM `bgpPeers` ' . $where;
    $array['count'] = dbFetchCell($query_count);
    //$array['count'] = dbFetchCell($query_count, $param, TRUE);

    // Pagination
    $array['pagination_html'] = pagination($vars, $array['count']);

    $query = 'SELECT `hostname`, `bgpLocalAs`, `bgpPeers`.* FROM `bgpPeers`';
    $query .= ' JOIN `devices` USING (`device_id`) ';
    $query .= $where;

    $sort_dir = $vars['sort_order'] === 'desc' ? ' DESC' : '';

    switch ($vars['sort']) {
        case "device":
            $sort = " ORDER BY `hostname`" . $sort_dir;
            break;

        case "local_as":
            $sort = " ORDER BY `local_as`$sort_dir, `virtual_name`$sort_dir";
            break;

        case "peer_ip":
            $sort = " ORDER BY `bgpPeerRemoteAddr`" . $sort_dir;
            break;

        case "peer_as":
            $sort = " ORDER BY `bgpPeerRemoteAs`" . $sort_dir;
            break;

        case 'state':
            $sort = " ORDER BY `bgpPeerAdminStatus`" . $sort_dir . ", `bgpPeerState`" . $sort_dir;
            break;

        case 'uptime':
            $sort = " ORDER BY `bgpPeerFsmEstablishedTime`" . $sort_dir;
            break;

        default:
            $sort = " ORDER BY `hostname`" . $sort_dir . ", `bgpPeerRemoteAs`" . $sort_dir . ", `bgpPeerRemoteAddr`" . $sort_dir;
    }

    $query .= $sort;
    $query .= " LIMIT $start,$pagesize";

    $peer_devices = [];
    // Query BGP
    foreach (dbFetchRows($query) as $entry) {
        humanize_bgp($entry);

        // Collect peer devices for AFI/SAFI
        $peer_devices[$entry['device_id']] = 1;

        $array['entries'][] = $entry;
    }

    // Query AFI/SAFI
    if (!safe_empty($peer_devices)) {
        $query_afi = 'SELECT `device_id`, `bgpPeer_id`, `afi`, `safi` FROM `bgpPeers_cbgp` ' .
                     generate_where_clause(generate_query_values(array_keys($peer_devices), 'device_id'));

        foreach (dbFetchRows($query_afi) as $entry) {
            $array['afisafi'][$entry['device_id']][$entry['bgpPeer_id']][$entry['afi'] . '.' . $entry['safi']] = [
                'afi'  => $entry['afi'],
                'safi' => $entry['safi']
            ];
        }
    }

    return $array;
}

/**
 * Display bgp peer afi/safi table.
 *
 * Display BGP Peer AFI/SAFI entries with detailed prefix information.
 *
 * @param array $vars
 *
 * @return void
 */
function print_bgp_peer_af_table($vars) {

    $entries = get_bgp_peer_af_array($vars);

    if (!$entries['count']) {
        print_warning('<h4>No BGP Peer AFI/SAFI entries found!</h4>');
        return;
    }

    // Determine if device column is needed
    $list = ['device' => FALSE];
    if ($vars['page'] !== 'device') {
        $list['device'] = TRUE;
    }

    $string = generate_box_open();
    $string .= '<table class="table table-striped table-hover table-condensed">' . PHP_EOL;

    $cols = [
        [NULL, 'class="state-marker"'],
        [NULL, 'style="width: 1px;"'],
        'device'     => ['Device', 'style="width: 120px;"'],
        'peer'       => ['BGP Peer', 'style="width: 150px;"'],
        'afi'        => ['AFI', 'style="width: 70px;"'],
        'safi'       => ['SAFI', 'style="width: 90px;"'],
        'accepted'   => ['Accepted', 'style="width: 80px;"'],
        'denied'     => ['Denied', 'style="width: 70px;"'],
        'advertised' => ['Advertised', 'style="width: 80px;"'],
        'suppressed' => ['Suppressed', 'style="width: 80px;"'],
        'withdrawn'  => ['Withdrawn', 'style="width: 80px;"'],
        'limits'     => ['Limits', 'style="width: 120px;"'],
    ];

    if (!$list['device']) { 
        unset($cols['device']); 
    }

    $string .= get_table_header($cols, $vars);
    $string .= '  <tbody>' . PHP_EOL;

    foreach ($entries['entries'] as $entry) {
        $device = device_by_id_cache($entry['device_id']);
        $peer = isset($entries['bgp_peers'][$entry['bgpPeer_id']]) ? $entries['bgp_peers'][$entry['bgpPeer_id']] : NULL;

        // Build AFI/SAFI labels
        $afi_class = 'default';
        $safi_class = 'default';

        if (isset($GLOBALS['config']['routing_afis_name'][$entry['afi']])) {
            $afi_num = $GLOBALS['config']['routing_afis_name'][$entry['afi']];
            $afi_class = $GLOBALS['config']['routing_afis'][$afi_num]['class'];
        }

        if (isset($GLOBALS['config']['routing_safis_name'][$entry['safi']])) {
            $safi_num = $GLOBALS['config']['routing_safis_name'][$entry['safi']];
            $safi_class = $GLOBALS['config']['routing_safis'][$safi_num]['class'];
        } elseif (isset($GLOBALS['config']['routing_safis'][$entry['safi']])) {
            $safi_num = $entry['safi'];
            $safi_class = $GLOBALS['config']['routing_safis'][$safi_num]['class'];
        }

        // Format prefix counts
        $accepted = is_numeric($entry['AcceptedPrefixes']) ? format_si($entry['AcceptedPrefixes']) : '-';
        $denied = is_numeric($entry['DeniedPrefixes']) ? format_si($entry['DeniedPrefixes']) : '-';
        $advertised = is_numeric($entry['AdvertisedPrefixes']) ? format_si($entry['AdvertisedPrefixes']) : '-';
        $suppressed = is_numeric($entry['SuppressedPrefixes']) ? format_si($entry['SuppressedPrefixes']) : '-';
        $withdrawn = is_numeric($entry['WithdrawnPrefixes']) ? format_si($entry['WithdrawnPrefixes']) : '-';

        // Format limits
        $limits_html = '';
        if (is_numeric($entry['PrefixAdminLimit']) && $entry['PrefixAdminLimit'] > 0) {
            $limit_class = 'info';
            if (is_numeric($entry['AcceptedPrefixes'])) {
                $usage_pct = ($entry['AcceptedPrefixes'] / $entry['PrefixAdminLimit']) * 100;
                if ($usage_pct >= 90) {
                    $limit_class = 'danger';
                } elseif ($usage_pct >= 75) {
                    $limit_class = 'warning';
                }
            }
            $limits_html = '<span class="label label-' . $limit_class . '">Limit: ' . format_si($entry['PrefixAdminLimit']) . '</span>';

            if (is_numeric($entry['PrefixThreshold']) && $entry['PrefixThreshold'] > 0) {
                $limits_html .= '<br /><span class="label label-default">Thresh: ' . $entry['PrefixThreshold'] . '%</span>';
            }
        }

        $string .= '  <tr class="' . $device['html_row_class'] . '">' . PHP_EOL;
        $string .= '     <td class="state-marker"></td>' . PHP_EOL;
        $string .= '     <td></td>' . PHP_EOL;

        if ($list['device']) {
            $string .= '     <td>' . generate_device_link_short($device, ['tab' => 'routing', 'proto' => 'bgp'], 15) . '</td>' . PHP_EOL;
        }

        $peer_ip = $peer ? $peer['bgpPeerRemoteAddr'] : 'Unknown';
        $peer_as = $peer ? 'AS' . $peer['bgpPeerRemoteAs'] : '';
        $peer_link = $peer ? generate_link($peer_ip, ['page' => 'routing', 'proto' => 'bgp', 'peer_ip' => $peer_ip]) : $peer_ip;

        $string .= '     <td>' . $peer_link;
        if ($peer_as) {
            $string .= '<br /><small>' . $peer_as . '</small>';
        }
        $string .= '</td>' . PHP_EOL;

        $string .= '     <td><span class="label label-' . $afi_class . '">' . strtoupper($entry['afi']) . '</span></td>' . PHP_EOL;
        $string .= '     <td><span class="label label-' . $safi_class . '">' . ucfirst($entry['safi']) . '</span></td>' . PHP_EOL;
        $string .= '     <td><strong>' . $accepted . '</strong></td>' . PHP_EOL;
        $string .= '     <td>' . $denied . '</td>' . PHP_EOL;
        $string .= '     <td>' . $advertised . '</td>' . PHP_EOL;
        $string .= '     <td>' . $suppressed . '</td>' . PHP_EOL;
        $string .= '     <td>' . $withdrawn . '</td>' . PHP_EOL;
        $string .= '     <td>' . $limits_html . '</td>' . PHP_EOL;
        $string .= '  </tr>' . PHP_EOL;
    }

    $string .= '  </tbody>' . PHP_EOL;
    $string .= '</table>';
    $string .= generate_box_close();

    // Print pagination header
    if ($entries['pagination_html']) {
        $string = $entries['pagination_html'] . $string . $entries['pagination_html'];
    }

    echo $string;
}

/**
 * Get BGP Peer AFI/SAFI array for table display
 *
 * @param array $vars
 * @return array
 */
function get_bgp_peer_af_array($vars) {
    $array = [];

    // Pagination setup
    $array['pagination'] = TRUE;
    pagination($vars, 0, TRUE);
    $array['pageno'] = $vars['pageno'];
    $array['pagesize'] = $vars['pagesize'];
    $start = $array['pagesize'] * $array['pageno'] - $array['pagesize'];
    $pagesize = $array['pagesize'];

    // Build WHERE clause
    $where = [];
    foreach ($vars as $var => $value) {
        if (!safe_empty($value)) {
            switch ($var) {
                case "group":
                case "group_id":
                    $values = get_group_entities($value);
                    $where[] = generate_query_values($values, 'cbgp_id');
                    break;

                case 'device':
                case 'device_id':
                    $where[] = generate_query_values($value, 'device_id');
                    break;

                case 'peer_id':
                case 'bgp_peer_id':
                    $where[] = generate_query_values($value, 'bgpPeer_id');
                    break;

                case 'afi':
                    $where[] = generate_query_values($value, 'afi');
                    break;

                case 'safi':
                    $where[] = generate_query_values($value, 'safi');
                    break;
            }
        }
    }

    // Add device permissions - need to join with devices table for permissions
    $device_where = generate_query_permitted_ng(['device']);
    if ($device_where) {
        $where[] = str_replace('`device_id`', '`bgpPeers_cbgp`.`device_id`', $device_where);
    }

    $where = generate_where_clause($where);

    // Count query with device join for permissions
    $query_count = 'SELECT COUNT(*) FROM `bgpPeers_cbgp` LEFT JOIN `devices` USING (`device_id`) ' . $where;
    $array['count'] = dbFetchCell($query_count);

    // Pagination
    $array['pagination_html'] = pagination($vars, $array['count']);

    // Main query - need same device join as count query
    $query = 'SELECT `bgpPeers_cbgp`.*, `bgpPeers`.bgpPeerRemoteAddr, `bgpPeers`.bgpPeerRemoteAs, `bgpPeers`.bgpPeerLocalAddr, `bgpPeers`.local_as, `bgpPeers`.virtual_name, `devices`.hostname 
              FROM `bgpPeers_cbgp` 
              LEFT JOIN `devices` USING (`device_id`)
              LEFT JOIN `bgpPeers` USING (`bgpPeer_id`) ';
    $query .= $where;

    // Sorting
    $sort_dir = $vars['sort_order'] === 'desc' ? ' DESC' : '';
    switch ($vars['sort']) {
        case "device":
            $sort = " ORDER BY `devices`.hostname" . $sort_dir;
            break;
        case "peer":
            $sort = " ORDER BY `bgpPeers`.bgpPeerRemoteAddr" . $sort_dir;
            break;
        case "afi":
            $sort = " ORDER BY `bgpPeers_cbgp`.afi" . $sort_dir;
            break;
        case "safi":
            $sort = " ORDER BY `bgpPeers_cbgp`.safi" . $sort_dir;
            break;
        case "accepted":
            $sort = " ORDER BY `bgpPeers_cbgp`.AcceptedPrefixes" . $sort_dir;
            break;
        default:
            $sort = " ORDER BY `devices`.hostname" . $sort_dir . ", `bgpPeers`.bgpPeerRemoteAddr" . $sort_dir . ", `bgpPeers_cbgp`.afi" . $sort_dir . ", `bgpPeers_cbgp`.safi" . $sort_dir;
    }

    $query .= $sort;
    $query .= " LIMIT $start,$pagesize";

    // Execute query
    foreach (dbFetchRows($query) as $entry) {
        $array['entries'][] = $entry;

        // Cache BGP peer data for display
        if ($entry['bgpPeer_id']) {
            $array['bgp_peers'][$entry['bgpPeer_id']] = [
                'bgpPeerRemoteAddr' => $entry['bgpPeerRemoteAddr'],
                'bgpPeerRemoteAs' => $entry['bgpPeerRemoteAs'],
                'bgpPeerLocalAddr' => $entry['bgpPeerLocalAddr'],
                'local_as' => $entry['local_as'],
                'virtual_name' => $entry['virtual_name']
            ];
        }
    }

    return $array;
}

// EOF
