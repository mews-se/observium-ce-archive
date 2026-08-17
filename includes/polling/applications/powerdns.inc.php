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

if (empty($agent_data['app']['powerdns'])) {
    return;
}

$app_id = discover_app($device, 'powerdns');

print_debug_vars($agent_data['app']['powerdns']);
$powerdns = [];
foreach (safe_explode(",", $agent_data['app']['powerdns']) as $line) {
    [ $key, $value ] = safe_explode("=", $line, 2);
    $powerdns[$key] = $value;
}

if (empty($powerdns)) {
    print_debug("ERROR: Incorrect PowerDNS agent data passed.");
    return;
}

$data = [
    'corruptPackets'   => $powerdns['corrupt-packets'],
    'def_cacheInserts' => $powerdns['deferred-cache-inserts'],
    'def_cacheLookup'  => $powerdns['deferred-cache-lookup'],
    'latency'          => $powerdns['latency'],
    'pc_hit'           => $powerdns['packetcache-hit'],
    'pc_miss'          => $powerdns['packetcache-miss'],
    'pc_size'          => $powerdns['packetcache-size'],
    'qsize'            => $powerdns['qsize-q'],
    'qc_hit'           => $powerdns['query-cache-hit'],
    'qc_miss'          => $powerdns['query-cache-miss'],
    'rec_answers'      => $powerdns['recursing-answers'],
    'rec_questions'    => $powerdns['recursing-questions'],
    'servfailPackets'  => $powerdns['servfail-packets'],
    'q_tcpAnswers'     => $powerdns['tcp-answers'],
    'q_tcpQueries'     => $powerdns['tcp-queries'],
    'q_timedout'       => $powerdns['timedout-packets'],
    'q_udpAnswers'     => $powerdns['udp-answers'],
    'q_udpQueries'     => $powerdns['udp-queries'],
    'q_udp4Answers'    => $powerdns['udp4-answers'],
    'q_udp4Queries'    => $powerdns['udp4-queries'],
    'q_udp6Answers'    => $powerdns['udp6-answers'],
    'q_udp6Queries'    => $powerdns['udp6-queries']
];

update_application($app_id, $data);
rrdtool_update_ng($device, 'powerdns', $data, $app_id);

// Clean
unset($app_id, $powerdns, $data);

// EOF
