<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage ajax
 * @copyright  (C) Adam Armstrong
 *
 */

// FIXME, create api-internal for such

include_once("../../includes/observium.inc.php");

include($config['html_dir'] . "/includes/authenticate.inc.php");

if (!$_SESSION['authenticated']) {
    echo('<li class="nav-header">Session expired, please log in again!</li>');
    exit;
}

$vars         = get_vars('GET');
$array_filter = in_array($vars['field'], ['syslog_program'], TRUE); // modules with cached field
if (!safe_empty($vars['field']) && $vars['cache'] !== 'no' && ($array_filter || safe_empty($vars['query']))) {
    $cache_key = 'options_' . $vars['field'];
    foreach ($vars as $param => $value) {
        if (in_array($param, ['field', 'query', 'cache'], TRUE)) {
            continue;
        }
        $cache_key .= "_$param=$value";
    }
} else {
    $cache_key = '';
}

$query = '';
if ($cache_key && $options = get_cache_session($cache_key)) {
    // Return cached data (if not set in vars cache = 'no')
    //header("Content-type: application/json; charset=utf-8");
    //echo safe_json_encode(array('options' => $_SESSION['cache'][$cache_key]));
    //$options = $_SESSION['cache'][$cache_key];
} else {
    $where  = [];
    $params = [];
    //print_vars($vars);
    switch ($vars['field']) {
        case 'ipv4_network':
        case 'ipv6_network':
            $ip_version        = explode('_', $vars['field'])[0];
            $query  = 'SELECT `' . $ip_version . '_network` FROM `' . $ip_version . '_networks` ';
            if (!safe_empty($vars['query'])) {
                //$query .= ' AND `' . $ip_version . '_network` LIKE ?';
                //$params[] = '%' . $vars['query'] . '%';
                $where[] = generate_query_values($vars['query'], $vars['field'], '%LIKE%');
            }
            $network_permitted = dbFetchColumn('SELECT DISTINCT(`' . $ip_version . '_network_id`) FROM `' . $ip_version . '_addresses` WHERE ' . generate_query_permitted_ng('ports'));
            $query .= generate_where_clause($where, generate_query_values($network_permitted, $ip_version . '_network_id'));
            $query .= ' ORDER BY `' . $ip_version . '_network`;';
            //print_vars($query);
            break;

        case 'ifspeed':
            $query           = 'SELECT `ifSpeed`, COUNT(`ifSpeed`) as `count` FROM `ports` ' .
                               generate_where_clause('`ifSpeed` > 0', generate_query_permitted_ng('ports')) .
                               ' GROUP BY ifSpeed ORDER BY `count` DESC';
            $call_function   = 'formatRates';
            $call_params     = [4, 4];
            break;

        case 'syslog_program':
            //$query_permitted   = generate_query_permitted_ng();
            $query = 'SELECT DISTINCT `program` FROM `syslog`';
            if (is_intnum($vars['device_id'])) {
                $query .= ' WHERE ' . generate_query_values($vars['device_id'], 'device_id');
            }
            $array_filter = TRUE; // Search query string in array instead sql query (when this faster)
            break;

        case 'bgp_peer_as':
            // Combine AS number and AS text into string: ASXXXX: My AS text
            $query    = 'SELECT DISTINCT CONCAT(?, CONCAT_WS(?, `bgpPeerRemoteAs`, `astext`)) AS `' . $vars['field'] . '` FROM `bgpPeers` ';
            $params[] = 'AS';
            $params[] = ': ';
            if (!safe_empty($vars['query'])) {
                $where[]  = '(' . generate_query_values($vars['query'], 'bgpPeerRemoteAs', '%LIKE%') . ' OR ' . generate_query_values($vars['query'], 'astext', '%LIKE%') . ')';
            }
            $query   .= generate_where_clause($where, generate_query_permitted_ng('devices'));
            break;

        case 'bgp_local_ip':
            $query  = 'SELECT DISTINCT `bgpPeerLocalAddr` FROM `bgpPeers`';
            if (!safe_empty($vars['query'])) {
                $where[] = generate_query_values($vars['query'], 'bgpPeerLocalAddr', '%LIKE%');
            }
            $query .= generate_where_clause($where, generate_query_permitted_ng('devices'));
            break;

        case 'bgp_peer_ip':
            $query  = 'SELECT DISTINCT `bgpPeerRemoteAddr` FROM `bgpPeers`';
            if (!safe_empty($vars['query'])) {
                $where[] = generate_query_values($vars['query'], 'bgpPeerRemoteAddr', '%LIKE%');
            }

            $query .= generate_where_clause($where, generate_query_permitted_ng('devices'));
            break;

        default:
            json_output('error', 'Search type unknown');
    }

    if (!safe_empty($query)) {
        $options = dbFetchColumn($query, $params);
        if (safe_count($options)) {
            if (isset($call_function)) {
                $call_options = [];
                foreach ($options as $option) {
                    $call_options[] = call_user_func_array($call_function, array_merge([$option], $call_params));
                }
                $options = $call_options;
            }

            // Cache request in session var (need convert to common caching lib)
            if ($cache_key) {
                set_cache_session($cache_key, $options);
                //@session_start();
                //$_SESSION['cache'][$cache_key] = $options; // Cache query data in session for speedup
                //session_write_close();
            }
        } else {
            json_output('error', 'Data fields are empty');
        }
    }
}

if (safe_count($options)) {
    // Filter/search query string in array, instead sql query, when this is faster (ie syslog program)
    if ($array_filter) {
        $new_options = [];
        foreach ($options as $option) {
            if (str_contains_array($option, $vars['query'])) {
                $new_options[] = $option;
            }
        }
        $options = $new_options;
    }

    header("Content-type: application/json; charset=utf-8");
    echo safe_json_encode(['options' => $options]);
} else {
    json_output('error', 'Data fields are empty');
}

// EOF
