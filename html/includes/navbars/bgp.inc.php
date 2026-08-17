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

if (!isset($vars['view'])) {
    $vars['view'] = 'details';
}

$navbar['class'] = "navbar-narrow";
$navbar['brand'] = "BGP";

if ($vars['page'] == 'device') {
    $link_array = [
        'page'    => 'device',
        'device'  => $device['device_id'],
        'tab'     => 'routing',
        'proto'   => 'bgp'
    ];
} else {
    // Global Routing page
    $link_array = [ 'page' => 'routing', 'protocol' => 'bgp' ];
}

// BGP type options
$types = [
    'all'      => 'All',
    'internal' => 'iBGP',
    'external' => 'eBGP'
];
foreach ($types as $option => $text) {
    $is_active = ($vars['type'] ?? '') === $option || (empty($vars['type']) && $option === 'all');
    $bgp_options = [ 'type' => $option ];
    if (isset($vars['private'])) {
        $bgp_options['private'] = $vars['private'];
    }
    if ($vars['adminstatus']) {
        $bgp_options['adminstatus'] = $vars['adminstatus'];
    }
    if ($vars['state']) {
        $bgp_options['state'] = $vars['state'];
    }

    $navbar['options'][$option] = [
        'text'  => $text,
        'class' => $is_active ? 'active' : '',
        'url'   => generate_url($link_array, $bgp_options),
    ];
}

$navbar['options']['split1']['divider'] = TRUE;

// Private/Public
$is_active   = get_var_true($vars['private']);
$bgp_options = [ 'private' => $is_active ? NULL : 'yes' ];
if (isset($vars['type'])) {
    $bgp_options['type'] = $vars['type'];
}
if ($vars['adminstatus']) {
    $bgp_options['adminstatus'] = $vars['adminstatus'];
}
if ($vars['state']) {
    $bgp_options['state'] = $vars['state'];
}

$navbar['options']['private'] = [
    'text'  => 'Private',
    'class' => $is_active ? 'active' : '',
    'url'   => generate_url($link_array, $bgp_options),
];

$is_active   = isset($vars['private']) && get_var_false($vars['private']);
$bgp_options['private'] = $is_active ? NULL : 'no';
$navbar['options']['public'] = [
    'text'  => 'Public',
    'class' => $is_active ? 'active' : '',
    'url'   => generate_url($link_array, $bgp_options),
];

// VRFs on device page
if (($vars['page'] == 'device') &&
    dbExist('bgpPeers', '`device_id` = ? AND `virtual_name` NOT IS NULL', [ $device['device_id'] ])) {

    $navbar['options']['splitvrf']['divider'] = TRUE;

    $is_active   = get_var_true($vars['vrfs']);
    $bgp_options = [ 'vrfs' => $is_active ? NULL : 'yes' ];
    if (isset($vars['type'])) {
        $bgp_options['type'] = $vars['type'];
    }
    if (isset($vars['private'])) {
        $bgp_options['private'] = $vars['private'];
    }
    if ($vars['adminstatus']) {
        $bgp_options['adminstatus'] = $vars['adminstatus'];
    }
    if ($vars['state']) {
        $bgp_options['state'] = $vars['state'];
    }
    $navbar['options'][$option]['text'] = 'VRFs';
    $navbar['options'][$option]['url'] = generate_url($link_array, $bgp_options);
}

// Statuses
$navbar['options']['split2']['divider'] = TRUE;

$navbar_status = [
    'text'       => "Status",
    'class'      => '',
    'suboptions' => [],
];

// Adminstatus (enable/shutdown) options
$adminstatus = [
    'start' => 'Enabled',
    'stop'  => 'Shutdown',
];
foreach ($adminstatus as $option => $text) {
    $is_active   = ($vars['adminstatus'] ?? '') === $option;
    $bgp_options = [ 'adminstatus' => $is_active ? NULL : $option ];
    if (isset($vars['type'])) {
        $bgp_options['type'] = $vars['type'];
    }
    if (isset($vars['private'])) {
        $bgp_options['private'] = $vars['private'];
    }
    if ($vars['state']) {
        $bgp_options['state'] = $vars['state'];
    }
    $navbar_status['suboptions'][$option] = [
        'text'  => $text,
        'class' => $is_active ? 'active' : '',
        'url'   => generate_url($link_array, $bgp_options)
    ];
    if ($is_active) {
        $navbar_status['class'] = 'active';
    }
}

$navbar_status['suboptions']['split']['divider'] = TRUE;

// Status (Up/Down) options
$status = [
    'up'   => 'Up',
    'down' => 'Down',
];
foreach ($status as $option => $text) {
    $is_active   = ($vars['state'] ?? '') === $option;
    $bgp_options = [ 'state' => $is_active ? NULL : $option ];
    if (isset($vars['type'])) {
        $bgp_options['type'] = $vars['type'];
    }
    if (isset($vars['private'])) {
        $bgp_options['private'] = $vars['private'];
    }
    if ($vars['adminstatus']) {
        $bgp_options['adminstatus'] = $vars['adminstatus'];
    }
    $navbar_status['suboptions'][$option] = [
        'text'  => $text,
        'class' => $is_active ? 'active' : '',
        'url'   => generate_url($link_array, $bgp_options)
    ];
    if ($is_active) {
        $navbar_status['class'] = 'active';
    }
}

$navbar['options']['status'] = $navbar_status;

// Right navbar options
$navbar['options_right']['details'] = [
    'text'  => 'No Graphs',
    'class' => ($vars['view'] ?? '') === 'details' ? 'active' : '',
    'url'   => generate_url($vars, [ 'view' => 'details', 'graph' => 'NULL' ])
];

$navbar['options_right']['updates'] = [
    'text'  => 'Updates',
    'class' => ($vars['graph'] ?? '') === 'updates' ? 'active' : '',
    'url'   => generate_url($vars, [ 'view' => 'graphs', 'graph' => 'updates' ])
];

/*
$bgp_graphs = array();
foreach ($cache['graphs'] as $entry)
{
  if (preg_match('/^bgp_(?<subtype>prefixes)_(?<afi>ipv[46])(?<safi>[a-z]+)/', $entry, $matches))
  {
    if (!isset($bgp_graphs[$matches['safi']]))
    {
      $bgp_graphs[$matches['safi']] = array('text' => nicecase($matches['safi']));
    }
    $bgp_graphs[$matches['safi']]['types'][$matches['subtype'].'_'.$matches['afi'].$matches['safi']] = nicecase($matches['afi']) . ' ' . nicecase($matches['safi']) . ' ' . nicecase($matches['subtype']);
  }
}
*/

$bgp_graphs = [
    'unicast'   => [ 'text' => 'Unicast' ],
    'multicast' => [ 'text' => 'Multicast' ],
    'mac'       => [ 'text' => 'MAC Accounting' ]
];
$bgp_graphs['unicast']['types']   = [
    'prefixes_ipv4unicast' => 'IPv4 Unicast Prefixes',
    'prefixes_ipv6unicast' => 'IPv6 Unicast Prefixes',
    'prefixes_ipv4vpn'     => 'VPNv4 Prefixes',
    //'prefixes_ipv6vpn'     => 'VPNv6 Prefixes',
];
$bgp_graphs['multicast']['types'] = [
    'prefixes_ipv4multicast' => 'IPv4 Multicast Prefixes',
    'prefixes_ipv6multicast' => 'IPv6 Multicast Prefixes'
];
$bgp_graphs['mac']['types'] = [
    'macaccounting_bits' => 'MAC Bits',
    'macaccounting_pkts' => 'MAC Pkts'
];

foreach ($bgp_graphs as $bgp_graph => $bgp_options) {
    $navbar_graph = [
        'text'       => $bgp_options['text'],
        'class'      => '',
        'suboptions' => [],
    ];

    foreach ($bgp_options['types'] as $option => $type_text) {
        $is_active = ($vars['graph'] ?? '') === $option;
        if ($is_active) {
            $navbar_graph['class'] = 'active';
        }
        $navbar_graph['suboptions'][$option] = [
            'text'  => $type_text,
            'class' => $is_active ? 'active' : '',
            'url'   => generate_url($vars, [ 'view' => 'graphs', 'graph' => $option ]),
        ];
    }
    $navbar['options_right'][$bgp_graph] = $navbar_graph;
}
/*
foreach ($bgp_graphs as $bgp_graph => $bgp_options) {
    $navbar['options_right'][$bgp_graph]['text'] = $bgp_options['text'];
    foreach ($bgp_options['types'] as $option => $text) {
        if ($vars['graph'] == $option) {
            $navbar['options_right'][$bgp_graph]['class']                        .= ' active';
            $navbar['options_right'][$bgp_graph]['suboptions'][$option]['class'] = 'active';
        }
        $navbar['options_right'][$bgp_graph]['suboptions'][$option]['text'] = $text;
        $navbar['options_right'][$bgp_graph]['suboptions'][$option]['url']  = generate_url($vars, ['view' => 'graphs', 'graph' => $option]);
    }
}
*/

print_navbar($navbar);
unset($navbar);

// EOF
