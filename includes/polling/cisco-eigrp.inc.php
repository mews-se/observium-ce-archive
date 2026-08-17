<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     poller
 * @copyright  (C) Adam Armstrong
 *
 */

// Only run this on Cisco kit.
// Seems this MIB supported only in IOS Catalyst 6k/7k. See ftp://ftp.cisco.com/pub/mibs/supportlists/
// IOS 3560:  ftp://ftp.cisco.com/pub/mibs/supportlists/cat3560/cat3560-supportlist.html
// IOS 6k/7k: ftp://ftp.cisco.com/pub/mibs/supportlists/cisco7606/cisco7606-supportlist.html
// IOS-XE:    ftp://ftp.cisco.com/pub/mibs/supportlists/cat4000/cat4000-supportlist.html
//            ftp://ftp.cisco.com/pub/mibs/supportlists/asr1000/asr1000-supportlist.html
// IOS-XR:    ftp://ftp.cisco.com/pub/mibs/supportlists/asr9000/asr9000-supportlist.html
// ASA:       ftp://ftp.cisco.com/pub/mibs/supportlists/asa/asa-supportlist.html

if (is_device_mib($device, 'CISCO-EIGRP-MIB')) {

    // Poll VPNs

    print_cli_data("EIGRP VPNs");

    // cEigrpVpnInfo.cEigrpVpnTable.cEigrpVpnEntry.cEigrpVpnName.65536 = default

    foreach (dbFetchRows('SELECT * FROM `eigrp_vpns` WHERE `device_id` = ?', [$device['device_id']]) as $entry) {
        $vpn_db[$entry['eigrp_vpn']] = $entry;
    }

    $table = [];
    $peers_up = [];

    $vpn_poll = snmpwalk_multipart_oid($device, 'cEigrpVpnEntry', [], 'CISCO-EIGRP-MIB');
    // Initialize dependent polls to safe defaults; fill only when VPNs are present
    $as_poll = [];
    $ports_poll = [];
    $peers_poll = [];

    foreach ($vpn_poll as $vpn_id => $vpn) {
        if (is_array($vpn_db[$vpn_id])) {
            if ($vpn_db[$vpn_id]['eigrp_vpn_name'] != $vpn['cEigrpVpnName']) {
                dbUpdate(['eigrp_vpn_name' => $vpn['cEigrpVpnName']], 'eigrp_vpns', '`eigrp_vpn` = ? AND `device_id` = ?', [$vpn_id, $device['device_id']]);
            }
            unset($vpn_db[$vpn_id]);
        } else {
            dbInsert(['eigrp_vpn' => $vpn_id, 'eigrp_vpn_name' => $vpn['cEigrpVpnName'], 'device_id' => $device['device_id']], 'eigrp_vpns');
        }
        $table[] = [$vpn_id, $vpn['cEigrpVpnName']];
    }

    foreach ($vpn_db as $entry) {
        dbDelete('eigrp_vpns', 'eigrp_vpn_id = ?', [$entry['eigrp_vpn_id']]);
    }

    print_cli_table($table, ['VPN ID', 'VPN Name']);
    unset($table);

    // End poll VPNs


    /////////////////////
    ///  Poll ASes    ///
    /////////////////////

    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpNbrCount.65536.2449 = 3
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpHellosSent.65536.2449 = 56609
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpHellosRcvd.65536.2449 = 56552
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpUpdatesSent.65536.2449 = 20
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpUpdatesRcvd.65536.2449 = 17
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpQueriesSent.65536.2449 = 0
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpQueriesRcvd.65536.2449 = 1
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpRepliesSent.65536.2449 = 1
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpRepliesRcvd.65536.2449 = 0
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpAcksSent.65536.2449 = 13
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpAcksRcvd.65536.2449 = 14
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpInputQHighMark.65536.2449 = 3
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpInputQDrops.65536.2449 = 0
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpSiaQueriesSent.65536.2449 = 0
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpSiaQueriesRcvd.65536.2449 = 0
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpAsRouterIdType.65536.2449 = ipv4
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpAsRouterId.65536.2449 = "x.x.x.x"
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpTopoRoutes.65536.2449 = 14
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpHeadSerial.65536.2449 = 1
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpNextSerial.65536.2449 = 27
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpXmitPendReplies.65536.2449 = 0
    // cEigrpAsInfo.cEigrpTraffStatsTable.cEigrpTraffStatsEntry.cEigrpXmitDummies.65536.2449 = 0

    print_cli_data("EIGRP ASes");

    foreach (dbFetchRows('SELECT * FROM `eigrp_ases` WHERE `device_id` = ?', [$device['device_id']]) as $entry) {
        $ases_db[$entry['eigrp_vpn'] . '-' . $entry['eigrp_as']] = $entry;
    }

    $table = [];

    // Only poll ASes if we found a VPN
    if (count($vpn_poll)) {
        $as_poll = snmpwalk_multipart_oid($device, 'cEigrpTraffStatsEntry', [], 'CISCO-EIGRP-MIB');
    }

    // Aggregate topology states per AS for quick counts (active/SIA) and route type counts (internal/external)
    $topo_counts = [];
    if (count($vpn_poll)) {
        $topo_poll = snmpwalk_multipart_oid($device, 'cEigrpTopoEntry', [], 'CISCO-EIGRP-MIB');
        foreach ($topo_poll as $vpn => $as_list) {
            foreach ($as_list as $as => $routes) {
                $active     = 0;
                $sia        = 0;
                $routes_int = 0;
                $routes_ext = 0;
                foreach ($routes as $row) {
                    if (isset($row['cEigrpActive']) && $row['cEigrpActive'] === 'true') { $active++; }
                    if (isset($row['cEigrpStuckInActive']) && $row['cEigrpStuckInActive'] === 'true') { $sia++; }
                    // Internal table contributes to internal routes count
                    $routes_int++;
                }
                $topo_counts[$vpn][$as] = [ 'active' => $active, 'sia' => $sia, 'routes_int' => $routes_int, 'routes_ext' => $routes_ext ];
            }
        }
        unset($topo_poll);

        // On many platforms, external routes are exposed under cEigrpExtTopoEntry; add those explicitly
        $topo_ext_poll = snmpwalk_multipart_oid($device, 'cEigrpExtTopoEntry', [], 'CISCO-EIGRP-MIB');
        foreach ((array)$topo_ext_poll as $vpn => $as_list) {
            foreach ((array)$as_list as $as => $routes) {
                foreach ((array)$routes as $row) {
                    if (!isset($topo_counts[$vpn][$as])) { $topo_counts[$vpn][$as] = [ 'active' => 0, 'sia' => 0, 'routes_int' => 0, 'routes_ext' => 0 ]; }
                    $topo_counts[$vpn][$as]['routes_ext']++;
                    if (isset($row['cEigrpActive']) && $row['cEigrpActive'] === 'true') { $topo_counts[$vpn][$as]['active']++; }
                    if (isset($row['cEigrpStuckInActive']) && $row['cEigrpStuckInActive'] === 'true') { $topo_counts[$vpn][$as]['sia']++; }
                }
            }
        }
        unset($topo_ext_poll);
    }

    // If device does not expose cEigrpVpnEntry (some platforms), gate with a synthetic default VPN (65536)
    $have_vpns = count($vpn_poll) > 0;
    if (!$have_vpns) {
        $vpn_poll = [ 65536 => [ 'cEigrpVpnName' => 'default' ] ];
    }

    foreach ($as_poll as $vpn => $as_list) {
        foreach ($as_list as $as => $entry) {

            // Fix IP addresses because Cisco sometimes suck
            $entry['cEigrpAsRouterId'] = hex2ip($entry['cEigrpAsRouterId']);

            $db_data = [];

            foreach (['cEigrpNbrCount', 'cEigrpAsRouterIdType', 'cEigrpAsRouterId', 'cEigrpTopoRoutes', 'cEigrpSiaQueriesSent', 'cEigrpSiaQueriesRcvd'] as $datum) {
                if (array_key_exists($datum, $entry)) { $db_data[$datum] = $entry[$datum]; }
            }

            // Derived counts from topology table
            if (isset($topo_counts[$vpn][$as])) {
                // Normalized snake_case
                $db_data['active_routes'] = (int)$topo_counts[$vpn][$as]['active'];
                $db_data['sia_routes']    = (int)$topo_counts[$vpn][$as]['sia'];
                // Legacy camel-case for backward compatibility
                $db_data['cEigrpActiveCount']        = (int)$topo_counts[$vpn][$as]['active'];
                $db_data['cEigrpStuckInActiveCount'] = (int)$topo_counts[$vpn][$as]['sia'];
            }

            // Track last poll timestamp
            $db_data['last_poll'] = ['NOW()'];

            if (is_array($ases_db[$vpn . '-' . $as])) {
                $as_db = $ases_db[$vpn . '-' . $as];

                // Derive SIA events if counters increased
                if (isset($as_db['cEigrpSiaQueriesSent']) && isset($db_data['cEigrpSiaQueriesSent'])) {
                    $delta_sent = (int)$db_data['cEigrpSiaQueriesSent'] - (int)$as_db['cEigrpSiaQueriesSent'];
                    $delta_rcvd = (int)$db_data['cEigrpSiaQueriesRcvd'] - (int)$as_db['cEigrpSiaQueriesRcvd'];
                    if ($delta_sent > 0 || $delta_rcvd > 0) {
                        log_event("EIGRP SIA queries observed: +{$delta_sent} sent, +{$delta_rcvd} rcvd (VPN $vpn AS $as)", $device, 'eigrp-as', $as_db['eigrp_as_id'], 'warning');
                    }
                }

                dbUpdate($db_data, 'eigrp_ases', '`eigrp_as_id` = ?', [$as_db['eigrp_as_id']]);

                // Remove port_db entry to keep track of what exists.
                unset ($ases_db[$vpn . '-' . $as]);

            } else {

                // Add extra data for insertion
                $db_data['eigrp_vpn'] = $vpn;
                $db_data['eigrp_as']  = $as;
                $db_data['device_id'] = $device['device_id'];

                dbInsert($db_data, 'eigrp_ases');
                echo('+');
            }

            // For validation, include derived int/ext counts when available
            $routes_i = isset($topo_counts[$vpn][$as]['routes_int']) ? (int)$topo_counts[$vpn][$as]['routes_int'] : 0;
            $routes_e = isset($topo_counts[$vpn][$as]['routes_ext']) ? (int)$topo_counts[$vpn][$as]['routes_ext'] : 0;
            $table[] = [$vpn, $as, $entry['cEigrpAsRouterId'], $entry['cEigrpNbrCount'], $entry['cEigrpTopoRoutes'], $routes_i, $routes_e];

            // Counters below are persisted to RRD only (not DB) for graphing
            $rrd_fields = ['cEigrpNbrCount', 'cEigrpHellosSent', 'cEigrpHellosRcvd', 'cEigrpUpdatesSent', 'cEigrpUpdatesRcvd', 'cEigrpQueriesSent', 'cEigrpQueriesRcvd', 'cEigrpRepliesSent', 'cEigrpRepliesRcvd',
                           'cEigrpAcksSent', 'cEigrpAcksRcvd', 'cEigrpInputQHighMark', 'cEigrpInputQDrops', 'cEigrpSiaQueriesSent', 'cEigrpSiaQueriesRcvd', 'cEigrpTopoRoutes', 'cEigrpHeadSerial', 'cEigrpNextSerial',
                           'cEigrpXmitPendReplies', 'cEigrpXmitDummies'];

            $rrd_data = [];

            foreach ($rrd_fields as $field) {
                $rrd_field            = str_replace('cEigrp', '', $field);
                $rrd_data[$rrd_field] = $entry[$field];
            }

            // Add aggregated topology counts and derived peers/routes to RRD data
            $rrd_data['ActiveCount']        = isset($topo_counts[$vpn][$as]) ? (int)$topo_counts[$vpn][$as]['active'] : 0;
            $rrd_data['StuckInActiveCount'] = isset($topo_counts[$vpn][$as]) ? (int)$topo_counts[$vpn][$as]['sia']    : 0;
            $rrd_data['RoutesInt']          = isset($topo_counts[$vpn][$as]) ? (int)$topo_counts[$vpn][$as]['routes_int'] : 0;
            $rrd_data['RoutesExt']          = isset($topo_counts[$vpn][$as]) ? (int)$topo_counts[$vpn][$as]['routes_ext'] : 0;
            $rrd_data['PeersUp']            = isset($peers_up[$vpn][$as])    ? (int)$peers_up[$vpn][$as] : 0;

            // Write per-ASN EIGRP statistics
            rrdtool_update_ng($device, 'cisco-eigrp-as', $rrd_data, "$vpn-$as");

        }
    }

    foreach ($ases_db as $entry) {
        dbDelete('eigrp_ases', 'eigrp_as_id = ?', [$entry['eigrp_as_id']]);
    }

    print_cli_table($table, ['VPN ID', 'ASN', 'RTR ID', 'Nbrs', 'RoutesTot', 'Int', 'Ext']);
    unset($table);

    // End poll ASes


    /////////////////////
    ///  Poll Ports   ///
    /////////////////////

    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpPeerCount.65536.2449.10 = 1
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpXmitReliableQ.65536.2449.10 = 0
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpXmitUnreliableQ.65536.2449.10 = 0
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpMeanSrtt.65536.2449.10 = 32
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpPacingReliable.65536.2449.10 = 11
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpPacingUnreliable.65536.2449.10 = 0
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpMFlowTimer.65536.2449.10 = 139
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpPendingRoutes.65536.2449.10 = 0
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpHelloInterval.65536.2449.10 = 5
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpXmitNextSerial.65536.2449.10 = 0
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpUMcasts.65536.2449.10 = 0
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpRMcasts.65536.2449.10 = 0
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpUUcasts.65536.2449.10 = 4
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpRUcasts.65536.2449.10 = 10
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpMcastExcepts.65536.2449.10 = 0
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpCRpkts.65536.2449.10 = 0
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpAcksSuppressed.65536.2449.10 = 0
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpRetransSent.65536.2449.10 = 5
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpOOSrvcd.65536.2449.10 = 1
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpAuthMode.65536.2449.10 = none
    // cEigrpInterfaceInfo.cEigrpInterfaceTable.cEigrpInterfaceEntry.cEigrpAuthKeyChain.65536.2449.10 =

    print_cli_data("EIGRP Ports");

    $ports_db = [];
    foreach (dbFetchRows('SELECT * FROM `eigrp_ports` WHERE `device_id` = ?', [$device['device_id']]) as $db_port) {
        $ports_db[$db_port['eigrp_vpn'] . '-' . $db_port['eigrp_as'] . '-' . $db_port['eigrp_ifIndex']] = $db_port;
    }

    $table = [];

    // Only poll ports if we found a VPN
    if (count($vpn_poll)) {
        $ports_poll = snmpwalk_multipart_oid($device, 'cEigrpInterfaceEntry', [], 'CISCO-EIGRP-MIB');
    }

    foreach ($ports_poll as $vpn => $as_list) {
        foreach ($as_list as $as => $if_list) {
            foreach ($if_list as $ifIndex => $eigrp_port) {

                $port = get_port_by_index_cache($device['device_id'], $ifIndex);

                if (is_array($ports_db[$vpn . '-' . $as . '-' . $ifIndex])) {
                    $eigrp_update = NULL;

                    $port_db = $ports_db[$vpn . '-' . $as . '-' . $ifIndex];

                    if ($port['port_id'] != $port_db['port_id']) {
                        $eigrp_update['port_id'] = $port['port_id'];
                    }
                    if ($eigrp_port['cEigrpAuthMode'] != $port_db['eigrp_authmode']) {
                        $eigrp_update['eigrp_authmode'] = $eigrp_port['cEigrpAuthMode'];
                    }
                    if ($eigrp_port['cEigrpMeanSrtt'] != $port_db['eigrp_MeanSrtt']) {
                        $eigrp_update['eigrp_MeanSrtt'] = $eigrp_port['cEigrpMeanSrtt'];
                    }
                    if (isset($eigrp_port['cEigrpHelloInterval']) && $eigrp_port['cEigrpHelloInterval'] != $port_db['eigrp_HelloInterval']) {
                        $eigrp_update['eigrp_HelloInterval'] = $eigrp_port['cEigrpHelloInterval'];
                    }
                    if (isset($eigrp_port['cEigrpPeerCount']) && $eigrp_port['cEigrpPeerCount'] != $port_db['eigrp_peer_count']) {
                        $eigrp_update['eigrp_peer_count'] = $eigrp_port['cEigrpPeerCount'];
                    }
                    if (isset($eigrp_port['cEigrpPacingReliable']) && $eigrp_port['cEigrpPacingReliable'] != $port_db['eigrp_PacingReliable']) {
                        $eigrp_update['eigrp_PacingReliable'] = $eigrp_port['cEigrpPacingReliable'];
                    }
                    if (isset($eigrp_port['cEigrpPacingUnreliable']) && $eigrp_port['cEigrpPacingUnreliable'] != $port_db['eigrp_PacingUnreliable']) {
                        $eigrp_update['eigrp_PacingUnreliable'] = $eigrp_port['cEigrpPacingUnreliable'];
                    }
                    if (isset($eigrp_port['cEigrpXmitReliableQ']) && $eigrp_port['cEigrpXmitReliableQ'] != $port_db['eigrp_XmitReliableQ']) {
                        $eigrp_update['eigrp_XmitReliableQ'] = $eigrp_port['cEigrpXmitReliableQ'];
                    }
                    if (isset($eigrp_port['cEigrpXmitUnreliableQ']) && $eigrp_port['cEigrpXmitUnreliableQ'] != $port_db['eigrp_XmitUnreliableQ']) {
                        $eigrp_update['eigrp_XmitUnreliableQ'] = $eigrp_port['cEigrpXmitUnreliableQ'];
                    }
                    if (isset($eigrp_port['cEigrpPendingRoutes']) && $eigrp_port['cEigrpPendingRoutes'] != $port_db['eigrp_PendingRoutes']) {
                        $eigrp_update['eigrp_PendingRoutes'] = $eigrp_port['cEigrpPendingRoutes'];
                    }

                    // Update last poll timestamp on interface row
                    $eigrp_update['last_poll'] = ['NOW()'];

                    if (is_array($eigrp_update)) {
                        dbUpdate($eigrp_update, 'eigrp_ports', '`eigrp_port_id` = ?', [$ports_db[$vpn . '-' . $as . '-' . $ifIndex]['eigrp_port_id']]);
                    }
                    unset ($eigrp_update);

                    // Remove port_db entry to keep track of what exists.
                    unset ($ports_db[$vpn . '-' . $as . '-' . $ifIndex]);

                } else {
                    dbInsert([
                      'eigrp_vpn'           => $vpn,
                      'eigrp_as'            => $as,
                      'eigrp_ifIndex'       => $ifIndex,
                      'port_id'             => $port['port_id'],
                      'device_id'           => $device['device_id'],
                      'eigrp_peer_count'    => $eigrp_port['cEigrpPeerCount'],
                      'eigrp_MeanSrtt'      => $eigrp_port['cEigrpMeanSrtt'],
                      'eigrp_authmode'      => $eigrp_port['cEigrpAuthMode'],
                      'eigrp_HelloInterval' => isset($eigrp_port['cEigrpHelloInterval']) ? $eigrp_port['cEigrpHelloInterval'] : NULL,
                      'eigrp_PacingReliable'    => isset($eigrp_port['cEigrpPacingReliable']) ? $eigrp_port['cEigrpPacingReliable'] : NULL,
                      'eigrp_PacingUnreliable'  => isset($eigrp_port['cEigrpPacingUnreliable']) ? $eigrp_port['cEigrpPacingUnreliable'] : NULL,
                      'eigrp_XmitReliableQ'     => isset($eigrp_port['cEigrpXmitReliableQ']) ? $eigrp_port['cEigrpXmitReliableQ'] : NULL,
                      'eigrp_XmitUnreliableQ'   => isset($eigrp_port['cEigrpXmitUnreliableQ']) ? $eigrp_port['cEigrpXmitUnreliableQ'] : NULL,
                      'eigrp_PendingRoutes'     => isset($eigrp_port['cEigrpPendingRoutes']) ? $eigrp_port['cEigrpPendingRoutes'] : NULL,
                      'last_poll'               => ['NOW()'],
                    ], 'eigrp_ports');
                    echo('+');
                }

                // Write per-interface EIGRP statistics
                rrdtool_update_ng($device, 'cisco-eigrp-port', [
                  'MeanSrtt'       => $eigrp_port['cEigrpMeanSrtt'],
                  'UMcasts'        => $eigrp_port['cEigrpUMcasts'],
                  'RMcasts'        => $eigrp_port['cEigrpRMcasts'],
                  'UUcasts'        => $eigrp_port['cEigrpUUcasts'],
                  'RUcasts'        => $eigrp_port['cEigrpRUcasts'],
                  'McastExcepts'   => $eigrp_port['cEigrpMcastExcepts'],
                  'CRpkts'         => $eigrp_port['cEigrpCRpkts'],
                  'AcksSuppressed' => $eigrp_port['cEigrpAcksSuppressed'],
                  'RetransSent'    => $eigrp_port['cEigrpRetransSent'],
                  'OOSrvcd'        => $eigrp_port['cEigrpOOSrvcd'],
                ],                "$vpn-$as-$ifIndex");

                $table[] = [$vpn, $as, $port['port_label_short']];

                unset ($eigrp_update);
            }
        }
    }

    // Delete entries that no longer exist on the device
    foreach ($ports_db as $entry) {
        dbDelete('eigrp_ports', 'eigrp_port_id = ?', [$entry['eigrp_port_id']]);
    }

    print_cli_table($table, ['VPN ID', 'ASN', 'Port']);
    unset($table);

    /// Finish Polling Ports


    /////////////////////
    ///  Poll Peers   ///
    /////////////////////

    // cEigrpPeerInfo.cEigrpPeerTable.cEigrpPeerEntry.cEigrpPeerAddrType.65536.2449.2 = ipv4
    // cEigrpPeerInfo.cEigrpPeerTable.cEigrpPeerEntry.cEigrpPeerAddr.65536.2449.2 = "x.x.x.x"
    // cEigrpPeerInfo.cEigrpPeerTable.cEigrpPeerEntry.cEigrpPeerIfIndex.65536.2449.2 = 11
    // cEigrpPeerInfo.cEigrpPeerTable.cEigrpPeerEntry.cEigrpHoldTime.65536.2449.2 = 10
    // cEigrpPeerInfo.cEigrpPeerTable.cEigrpPeerEntry.cEigrpUpTime.65536.2449.2 = 1d00h
    // cEigrpPeerInfo.cEigrpPeerTable.cEigrpPeerEntry.cEigrpSrtt.65536.2449.2 = 48
    // cEigrpPeerInfo.cEigrpPeerTable.cEigrpPeerEntry.cEigrpRto.65536.2449.2 = 288
    // cEigrpPeerInfo.cEigrpPeerTable.cEigrpPeerEntry.cEigrpPktsEnqueued.65536.2449.2 = 0
    // cEigrpPeerInfo.cEigrpPeerTable.cEigrpPeerEntry.cEigrpLastSeq.65536.2449.2 = 16
    // cEigrpPeerInfo.cEigrpPeerTable.cEigrpPeerEntry.cEigrpVersion.65536.2449.2 = 49.54/46.4847504648
    // cEigrpPeerInfo.cEigrpPeerTable.cEigrpPeerEntry.cEigrpRetrans.65536.2449.2 = 0
    // cEigrpPeerInfo.cEigrpPeerTable.cEigrpPeerEntry.cEigrpRetries.65536.2449.2 = 0

    print_cli_data("EIGRP Peers");

    $table = [];

    $peers_db = [];
    foreach (dbFetchRows('SELECT * FROM `eigrp_peers` WHERE `device_id` = ?', [$device['device_id']]) as $entry) {
        // Normalize address key to lowercase for consistent matching (IPv6) and include ifIndex to avoid collisions
        $peers_db[$entry['eigrp_vpn'] . '-' . $entry['eigrp_as'] . '-' . strtolower($entry['peer_addr']) . '-' . (int)$entry['peer_ifindex']] = $entry;
    }

    // Only poll peers if we found a VPN
    if (count($vpn_poll)) {
        $peers_poll = snmpwalk_multipart_oid($device, 'cEigrpPeerEntry', [], 'CISCO-EIGRP-MIB');
    }

    foreach ($peers_poll as $vpn => $as_list) {
        foreach ($as_list as $as => $peers) {
            foreach ($peers as $peer_index => $peer) {
                // rewrite uptime value to seconds from weirdoformat
                $peer['cEigrpUpTime']   = uptime_to_seconds($peer['cEigrpUpTime']);
                $peer['cEigrpPeerAddr'] = hex2ip($peer['cEigrpPeerAddr']);
                $peer_addr = strtolower($peer['cEigrpPeerAddr']);

                // Count peers up per AS/VRF
                if (!isset($peers_up[$vpn][$as])) { $peers_up[$vpn][$as] = 0; }
                $peers_up[$vpn][$as]++;

                $db_data = [];
                foreach (['cEigrpPeerAddrType' => 'peer_addrtype',
                          'cEigrpPeerAddr'     => 'peer_addr',
                          'cEigrpPeerIfIndex'  => 'peer_ifindex',
                          'cEigrpHoldTime'     => 'peer_holdtime',
                          'cEigrpUpTime'       => 'peer_uptime',
                          'cEigrpSrtt'         => 'peer_srtt',
                          'cEigrpRto'          => 'peer_rto',
                          'cEigrpPktsEnqueued' => 'peer_qcount',
                          'cEigrpVersion'      => 'peer_version',
                          'cEigrpLastSeq'      => 'last_seq',
                          'cEigrpRetrans'      => 'retrans',
                          'cEigrpRetries'      => 'retries'] as $datum => $field) {
                    $db_data[$field] = $peer[$datum];
                }
                // Normalize stored peer_addr to lowercase for consistency
                $db_data['peer_addr'] = $peer_addr;
                // Persist raw SNMP index as handle for safety
                $db_data['peer_handle'] = (string)$peer_index;

                $peer_key_db = $vpn . '-' . $as . '-' . $peer_addr . '-' . (int)$peer['cEigrpPeerIfIndex'];
                if (is_array($peers_db[$peer_key_db])) {
                    $peer_db = $peers_db[$peer_key_db];

                    // On presence, mark UP and bump last_seen
                    $db_data['state']     = 'up';
                    $db_data['last_seen'] = ['NOW()'];
                    // Debounce: if previously down and down_since was recent (<=30 min), treat as flap
                    if (isset($peer_db['state']) && $peer_db['state'] === 'down' && !safe_empty($peer_db['down_since'])) {
                        $down_since_ts = strtotime($peer_db['down_since']);
                        if ($down_since_ts && (time() - $down_since_ts) <= 1800) {
                            log_event("EIGRP adjacency flap (down->up within 30m): {$peer_addr} (VPN $vpn AS $as)", $device, 'eigrp-peer', $peer_db['eigrp_peer_id'], 'warning');
                            $db_data['last_change'] = ['NOW()'];
                        }
                        // Clear down_since on re-up
                        $db_data['down_since'] = NULL;
                    }
                    dbUpdate($db_data, 'eigrp_peers', '`eigrp_peer_id` = ?', [$peer_db['eigrp_peer_id']]);

                    // Remove port_db entry to keep track of what exists.
                    unset ($peers_db[$peer_key_db]);

                } else {

                    // Add extra data for insertion
                    $db_data['eigrp_vpn'] = $vpn;
                    $db_data['eigrp_as']  = $as;
                    $db_data['device_id'] = $device['device_id'];
                    // Mark UP and set first/last seen
                    $db_data['state']      = 'up';
                    $db_data['first_seen'] = ['NOW()'];
                    $db_data['last_seen']  = ['NOW()'];

                    dbInsert($db_data, 'eigrp_peers');
                    echo('+');
                }

                // Adjacency flap detection: significant uptime reset
                if (isset($peer_db) && is_array($peer_db)) {
                    if ((int)$peer_db['peer_uptime'] > 300 && (int)$db_data['peer_uptime'] < 120) {
                        $port = get_port_by_index_cache($device['device_id'], (int)$peer['cEigrpPeerIfIndex']);
                        $port_label = is_array($port) ? $port['port_label_short'] : (string)$peer['cEigrpPeerIfIndex'];
                        log_event("EIGRP adjacency flap: {$peer_addr} (VPN $vpn AS $as) on {$port_label}", $device, 'eigrp-peer', $peer_db['eigrp_peer_id'], 'warning');
                        dbUpdate(['last_change' => ['NOW()']], 'eigrp_peers', '`eigrp_peer_id` = ?', [$peer_db['eigrp_peer_id']]);
                    }
                    // Sustained queue: warn when qcount > 0 for 3 consecutive polls
                    $qcnt = (int)$db_data['peer_qcount'];
                    $next_bad_q = $qcnt > 0 ? min(9, (int)$peer_db['bad_q_consec'] + 1) : 0;
                    if ($next_bad_q === 3) {
                        log_event("EIGRP sustained peer queue: {$peer_addr} (VPN $vpn AS $as)", $device, 'eigrp-peer', $peer_db['eigrp_peer_id'], 'warning');
                    }
                    dbUpdate(['bad_q_consec' => $next_bad_q], 'eigrp_peers', '`eigrp_peer_id` = ?', [$peer_db['eigrp_peer_id']]);

                    // SRTT surge (simple EWMA baseline)
                    $srtt_baseline = (int)$peer_db['srtt_baseline'];
                    if ($srtt_baseline <= 0) { $srtt_baseline = 50; }
                    $srtt_val = (int)$db_data['peer_srtt'];
                    if ($srtt_val > max(50, 2 * $srtt_baseline)) {
                        log_event("EIGRP SRTT surge to {$srtt_val}ms (baseline ~{$srtt_baseline}ms) {$peer_addr}", $device, 'eigrp-peer', $peer_db['eigrp_peer_id'], 'warning');
                    }
                    $new_baseline = (int)round(($srtt_baseline * 9 + max(1, $srtt_val)) / 10);
                    dbUpdate(['srtt_baseline' => $new_baseline], 'eigrp_peers', '`eigrp_peer_id` = ?', [$peer_db['eigrp_peer_id']]);
                }

                // Build the array to send to RRD: clamp Srtt/Rto/Q
                $srtt = min(max((int)$peer['cEigrpSrtt'], 0), 10000);
                $rto  = min(max((int)$peer['cEigrpRto'],  0), 60000);
                $qcnt = min(max((int)$peer['cEigrpPktsEnqueued'], 0), 10000);
                $rrd_data = [ 'Srtt' => $srtt, 'Rto' => $rto, 'PktsEnqueued' => $qcnt ];

                // Write per-peer EIGRP statistics with safe RRD index
                $peer_key = safename($peer_addr . '-' . (int)$peer['cEigrpPeerIfIndex']);
                rrdtool_update_ng($device, 'cisco-eigrp-peer', $rrd_data, "$vpn-$as-$peer_key");

                $table[] = [$vpn, $as, $peer['cEigrpPeerAddr']];
            }
        }
    }

    foreach ($peers_db as $entry) {
        // Peer missing in poll → declare DOWN and keep row, but only log the first transition
        if ($entry['state'] !== 'down') {
            log_event("EIGRP adjacency down: {$entry['peer_addr']} (VPN {$entry['eigrp_vpn']} AS {$entry['eigrp_as']})", $device, 'eigrp-peer', $entry['eigrp_peer_id'], 'warning');
            $update = ['state' => 'down', 'last_seen' => ['NOW()'], 'down_since' => ['NOW()']];
        } else {
            // Keep last_seen fresh but avoid duplicate log spam
            $update = ['last_seen' => ['NOW()']];
        }
        dbUpdate($update, 'eigrp_peers', '`eigrp_peer_id` = ?', [$entry['eigrp_peer_id']]);
    }

    print_cli_table($table, ['VPN ID', 'ASN', 'Address']);
    unset($table);

    // Finalize per-instance derived metrics: peers_up, peers_down_recent, routes_int/ext, flaps_24h
    // Build union of AS keys we saw during this poll
    $as_keys = [];
    foreach ($topo_counts as $vpn => $as_list) {
        foreach ($as_list as $as => $data) { $as_keys[$vpn . ':' . $as] = ['vpn' => $vpn, 'as' => $as]; }
    }
    foreach ($peers_up as $vpn => $as_list) {
        foreach ($as_list as $as => $cnt) { $as_keys[$vpn . ':' . $as] = ['vpn' => $vpn, 'as' => $as]; }
    }

    foreach ($as_keys as $ctx) {
        $vpn = $ctx['vpn'];
        $as  = $ctx['as'];
        $db_up    = (int)($peers_up[$vpn][$as] ?? 0);
        // Recent downs in the last window (configurable) using last_seen timestamp
        $window = (int)$config['eigrp_recent_down_window']; // seconds (default 3600)
        if ($window <= 0) { $window = 3600; }
        $db_downr = (int)dbFetchCell('SELECT COUNT(*) FROM `eigrp_peers` WHERE `device_id` = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ? AND `state` = "down" AND `last_seen` >= FROM_UNIXTIME(UNIX_TIMESTAMP(NOW()) - ?)', [ $device['device_id'], $vpn, $as, $window ]);
        $routes_i = (int)($topo_counts[$vpn][$as]['routes_int'] ?? 0);
        $routes_e = (int)($topo_counts[$vpn][$as]['routes_ext'] ?? 0);
        $flaps24  = (int)dbFetchCell('SELECT COUNT(*) FROM `eigrp_peers` WHERE `device_id` = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ? AND `last_change` IS NOT NULL AND `last_change` >= NOW() - INTERVAL 1 DAY', [ $device['device_id'], $vpn, $as ]);

        dbUpdate([
          'peers_up'           => $db_up,
          'peers_down_recent'  => $db_downr,
          'peers_flapping_24h' => $flaps24,
          'routes_int'         => $routes_i,
          'routes_ext'         => $routes_e,
        ], 'eigrp_ases', '`device_id` = ? AND `eigrp_vpn` = ? AND `eigrp_as` = ?', [ $device['device_id'], $vpn, $as ]);
    }

} // End if CISCO-EIGRP-MIB

// EOF
