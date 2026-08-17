<?php
/**
 * Observium - EIGRP Navbar
 *
 * @package    observium
 * @subpackage webui
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

$navbar = [];
$navbar['brand'] = 'EIGRP';
$navbar['class'] = 'navbar-narrow';

if ($vars['page'] === 'device') {
  $link_array = [
    'page'   => 'device',
    'device' => $device['device_id'],
    'tab'    => 'routing',
    'proto'  => 'eigrp'
  ];

  foreach (['overview' => 'Overview', 'ports' => 'Ports', 'peers' => 'Peers'] as $view => $text) {
    $is_active = ($vars['view'] ?? '') === $view || (empty($vars['view']) && $view === 'overview');
    $navbar['options'][$view] = [
      'text'  => $text,
      'class' => $is_active ? 'active' : '',
      'url'   => generate_url($link_array, ['view' => $view])
    ];
  }

  // VPN selector
  $vpn_rows = dbFetchRows("SELECT * FROM `eigrp_vpns` WHERE `device_id` = ? ORDER BY `eigrp_vpn`", [$device['device_id']]);
  if (!empty($vpn_rows)) {
    if (!isset($vars['vpn'])) { $vars['vpn'] = $vpn_rows[0]['eigrp_vpn']; }
    $vpnopt = [ 'text' => 'VPN', 'class' => '', 'suboptions' => [] ];
    foreach ($vpn_rows as $row) {
      $active = (string)$vars['vpn'] === (string)$row['eigrp_vpn'];
      $vpnopt['suboptions'][$row['eigrp_vpn']] = [
        'text'  => $row['eigrp_vpn_name'],
        'class' => $active ? 'active' : '',
        'url'   => generate_url($link_array, ['view' => $vars['view'] ?: 'overview', 'vpn' => $row['eigrp_vpn'], 'asn' => NULL])
      ];
      if ($active) { $vpnopt['class'] = 'active'; $vpnopt['text'] .= ' ('. $row['eigrp_vpn_name'] .')'; }
    }
    $navbar['options']['vpn'] = $vpnopt;
  }

  // AS selector (constrained by VPN)
  if (isset($vars['vpn'])) {
    $as_rows = dbFetchRows("SELECT `eigrp_as` FROM `eigrp_ases` WHERE `device_id` = ? AND `eigrp_vpn` = ? ORDER BY `eigrp_as`",
                           [ $device['device_id'], $vars['vpn'] ]);
    if (!empty($as_rows)) {
      if (!isset($vars['asn'])) { $vars['asn'] = $as_rows[0]['eigrp_as']; }
      $asopt = [ 'text' => 'AS', 'class' => '', 'suboptions' => [] ];
      foreach ($as_rows as $row) {
        $as   = $row['eigrp_as'];
        $act  = (string)$vars['asn'] === (string)$as;
        $asopt['suboptions'][$as] = [
          'text'  => 'AS'.$as,
          'class' => $act ? 'active' : '',
          'url'   => generate_url($link_array, ['view' => $vars['view'] ?: 'overview', 'vpn' => $vars['vpn'], 'asn' => $as])
        ];
        if ($act) { $asopt['class'] = 'active'; $asopt['text'] .= ' (AS'.$as.')'; }
      }
      $navbar['options']['asn'] = $asopt;
    }
  }

  // Right-side: graphs toggle
  $navbar['options_right']['graphs'] = [
    'text'  => 'Graphs',
    'icon'  => $config['icon']['graphs'],
    'url'   => generate_url($vars, ['graphs' => ($vars['graphs'] == 'yes' ? NULL : 'yes')]),
    'class' => ($vars['graphs'] == 'yes' ? 'active' : NULL)
  ];

} else {
  // Global routing navbar
  $link_array = [ 'page' => 'routing', 'protocol' => 'eigrp', 'view' => $vars['view'] ?: 'overview' ];
  foreach (['overview' => 'Overview', 'instances' => 'Instances', 'peers' => 'Peers', 'problems' => 'Problems'] as $view => $text) {
    $is_active = ($vars['view'] ?? 'overview') === $view;
    $navbar['options'][$view] = [
      'text'  => $text,
      'class' => $is_active ? 'active' : '',
      'url'   => generate_url($link_array, ['view' => $view])
    ];
  }
}

print_navbar($navbar);
unset($navbar);
