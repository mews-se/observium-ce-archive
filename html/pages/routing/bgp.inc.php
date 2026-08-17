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

//if ($_SESSION['permissions'] < '5')
//if ($_SESSION['userlevel'] < '5') {
//  print_error_permission();
//  return;
//}

$form_items = [];
$form_limit = 250; // Limit count for multiselect (use input instead)

$form_items['devices'] = generate_form_values('device', dbFetchColumn('SELECT DISTINCT `device_id` FROM `bgpPeers`'));
//r($form_items['devices']);

$param  = 'peer_as';
$column = 'bgpPeerRemoteAs';
// fast query 0.0015, 0.0020, 0.0017
$query = 'SELECT COUNT(DISTINCT `' . $column . '`) FROM `bgpPeers`' . generate_where_clause($GLOBALS['cache']['where']['devices_permitted']);
$count = dbFetchCell($query);
if ($count < $form_limit) {
    $form_items[$param] = []; // Set
    // slow query: 0.0093, 0.0125, 0.0063
    $query = 'SELECT DISTINCT `' . $column . '`, `astext` FROM `bgpPeers`' . generate_where_clause($cache['where']['devices_permitted']) . ' ORDER BY `' . $column . '`';
    foreach (dbFetchRows($query) as $entry) {
        if (safe_empty($entry[$column])) {
            continue;
        }

        $form_items[$param][$entry[$column]]['name']    = 'AS' . $entry[$column];
        $form_items[$param][$entry[$column]]['subtext'] = $entry['astext'];
    }
}

$form_params = [
    'local_ip' => 'bgpPeerLocalAddr',
    'peer_ip'  => 'bgpPeerRemoteAddr',
    //'peer_as'  => 'bgpPeerRemoteAs',
];

foreach ($form_params as $param => $column) {
    $query = 'SELECT COUNT(DISTINCT `' . $column . '`) FROM `bgpPeers`' . generate_where_clause($GLOBALS['cache']['where']['devices_permitted']);
    $count = dbFetchCell($query);
    if ($count < $form_limit) {
        $query = 'SELECT DISTINCT `' . $column . '` FROM `bgpPeers`' . generate_where_clause($GLOBALS['cache']['where']['devices_permitted']) . ' ORDER BY `' . $column . '`';
        foreach (dbFetchColumn($query) as $entry) {
            if (safe_empty($entry)) {
                continue;
            }

            if (str_contains($entry, ':')) {
                $form_items[$param][$entry]['group'] = 'IPv6';
                $form_items[$param][$entry]['name']  = ip_compress($entry);
            } else {
                $form_items[$param][$entry]['group'] = 'IPv4';
                $form_items[$param][$entry]['name']  = escape_html($entry);
            }
        }
    }
}

$form = [
    'type'          => 'rows',
    'space'         => '5px',
    'submit_by_key' => TRUE,
    'url'           => generate_url($vars)
];
$form['row'][0]['device'] = [
    'type'   => 'multiselect',
    'name'   => 'Local Device',
    'width'  => '100%',
    'value'  => $vars['device'],
    'values' => $form_items['devices']
];
$param                    = 'local_ip';
$param_name               = 'Local address';
foreach ([ 'local_ip' => 'Local address',
           'peer_ip'  => 'Peer address',
           'peer_as'  => 'Remote AS' ] as $param => $param_name) {

    if (isset($form_items[$param])) {
        // If not so many item values, use multiselect
        $form['row'][0][$param] = [
            'type'   => 'multiselect',
            'name'   => $param_name,
            'width'  => '100%',
            'value'  => $vars[$param],
            'values' => $form_items[$param]
        ];
    } else {
        // Instead, use input with autocomplete
        $form['row'][0][$param] = [
            'type'        => 'text',
            'name'        => $param_name,
            'width'       => '100%',
            'placeholder' => TRUE,
            'ajax'        => TRUE,
            'ajax_vars'   => [ 'field' => 'bgp_' . $param ],
            'value'       => $vars[$param]
        ];
    }
}

$form['row'][0]['type'] = [
    'type'   => 'select',
    'name'   => 'Type',
    'width'  => '100%',
    'value'  => $vars['type'],
    'values' => [ '' => 'All', 'internal' => 'iBGP', 'external' => 'eBGP' ]
];

// search button
$form['row'][0]['search'] = [
    'type'  => 'submit',
    //'name'        => 'Search',
    //'icon'        => 'icon-search',
    'right' => TRUE
];

$panel_form = [
    'type'          => 'rows',
    'title'         => 'Search BGP',
    'space'         => '10px',
    'submit_by_key' => TRUE,
    'url'           => generate_url($vars)
];

$panel_form['row'][0]['device'] = $form['row'][0]['device'];
//$panel_form['row'][0]['device']['grid'] = 6;
$panel_form['row'][0]['local_ip'] = $form['row'][0]['local_ip'];

$panel_form['row'][1]['peer_as'] = $form['row'][0]['peer_as'];
$panel_form['row'][1]['peer_ip'] = $form['row'][0]['peer_ip'];

$panel_form['row'][2]['type']   = $form['row'][0]['type'];
$panel_form['row'][2]['search'] = $form['row'][0]['search'];

// Register custom panel
register_html_panel(generate_form($panel_form));

echo '<div class="hidden-xl">';
print_form($form);
echo '</div>';

unset($form, $panel_form, $form_items, $navbar);

include($config['html_dir'] . "/includes/navbars/bgp.inc.php");

//r($cache['bgp']);
print_bgp_peer_table($vars);

register_html_title("BGP Peers");

// EOF
