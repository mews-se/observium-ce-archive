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

$sessions = [];
foreach (dbFetchRows('SELECT `bgpPeer_id`,`local_as`,`bgpPeerState`,`bgpPeerAdminStatus`,`bgpPeerRemoteAs` FROM `bgpPeers` WHERE `device_id` = ?;', [$device['device_id']]) as $bgp) {
    $sessions['count']++;
    if ($bgp['bgpPeerAdminStatus'] === 'start' || $bgp['bgpPeerAdminStatus'] === 'running') {
        $sessions['enabled']++;
        if ($bgp['bgpPeerState'] !== 'established') {
            $sessions['alerts']++;
        } else {
            $sessions['connected']++;
        }
    } else {
        $sessions['shutdown']++;
    }
    if ($bgp['bgpPeerRemoteAs'] == $bgp['local_as']) {
        $sessions['internal']++;
    } else {
        $sessions['external']++;
    }
}

echo generate_state_header([
    'title' => 'BGP AS' . $device['human_local_as'],
    'row_class' => 'up',
    'badges' => [
        ['label' => 'Total Sessions', 'value' => $sessions['count'] + 0, 'class' => 'default'],
        ['label' => 'Errored Sessions', 'value' => $sessions['alerts'] + 0, 'class' => 'danger'],
        ['label' => 'iBGP', 'value' => $sessions['internal'] + 0, 'class' => 'info'],
        ['label' => 'eBGP', 'value' => $sessions['external'] + 0, 'class' => 'primary']
    ]
]);

include($config['html_dir'] . "/includes/navbars/bgp.inc.php");

// Pagination
$vars['pagination'] = TRUE;

//r($cache['bgp']);
print_bgp_peer_table($vars);

register_html_title("BGP Peers");

// EOF
