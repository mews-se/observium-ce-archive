<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage discovery
 * @copyright  (C) Adam Armstrong
 *
 */

// Active Cfg

// ACD-TWAMP-GEN-MIB::acdTwampGenCfgName.1 = STRING: to_G406-9910_gt007
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgState.1 = INTEGER: enable(0)
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgInterval.1 = Gauge32: 1000
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgReferencePeriod.1 = Gauge32: 15
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgPktSize.1 = Gauge32: 41
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgDestIPv4Addr.1 = IpAddress: 10.8.17.5
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgSourcePortNumber.1 = Gauge32: 10000
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgDestPortNumber.1 = Gauge32: 862
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgDscp.1 = Gauge32: 0
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgEcn.1 = Gauge32: 0
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgVlan1Priority.1 = Gauge32: 0

// ACD-TWAMP-GEN-MIB::acdTwampGenCfgTwoWayMaxDelay.1 = Gauge32: 10000
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgTwoWayMaxDelayThrs.1 = Gauge32: 10
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgTwoWayAvgDelayThrs.1 = Gauge32: 10000

// ACD-TWAMP-GEN-MIB::acdTwampGenCfgTwoWayMaxDv.1 = Gauge32: 2000
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgTwoWayMaxDvThrs.1 = Gauge32: 2
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgTwoWayAvgDvThrs.1 = Gauge32: 2000

// ACD-TWAMP-GEN-MIB::acdTwampGenCfgOneWayNearEndMaxDelay.1 = Gauge32: 10000
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgOneWayNearEndMaxDelayThrs.1 = Gauge32: 10
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgOneWayNearEndAvgDelayThrs.1 = Gauge32: 10000
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgOneWayNearEndMaxDv.1 = Gauge32: 2000
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgOneWayNearEndMaxDvThrs.1 = Gauge32: 2
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgOneWayNearEndAvgDvThrs.1 = Gauge32: 2000

// ACD-TWAMP-GEN-MIB::acdTwampGenCfgOneWayFarEndMaxDelay.1 = Gauge32: 10000
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgOneWayFarEndMaxDelayThrs.1 = Gauge32: 10
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgOneWayFarEndAvgDelayThrs.1 = Gauge32: 10000
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgOneWayFarEndMaxDv.1 = Gauge32: 2000
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgOneWayFarEndMaxDvThrs.1 = Gauge32: 2
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgOneWayFarEndAvgDvThrs.1 = Gauge32: 2000

// ACD-TWAMP-GEN-MIB::acdTwampGenCfgPacketLossContinuityCheck.1 = Gauge32: 10
// ACD-TWAMP-GEN-MIB::acdTwampGenCfgPacketLossRate.1 = Gauge32: 1000

// ACD-TWAMP-GEN-MIB::acdTwampGenResultInstName.1 = STRING: to_G406-9910_gt007
// ACD-TWAMP-GEN-MIB::acdTwampGenResultPeriodNb.1 = Gauge32: 871
// ACD-TWAMP-GEN-MIB::acdTwampGenResultPeriodTime.1 = STRING: 1970-1-12,3:15:0.0,+0:0
// ACD-TWAMP-GEN-MIB::acdTwampGenResultCurrTxPacketCount.1 = Counter64: 715
// ACD-TWAMP-GEN-MIB::acdTwampGenResultCurrRxPacketCount.1 = Counter64: 715

// Detect Active sessions
foreach (snmpwalk_cache_oid($device, "acdTwampGenCfgTable", [], 'ACD-TWAMP-GEN-MIB') as $session_id => $session) {
    if ($session['acdTwampGenCfgState'] === 'disable' || $session['acdTwampGenCfgState'] === '1') {
        // Skip inactive
        continue;
    }

    $data = [
        'device_id'  => $device['device_id'],
        'sla_mib'    => $mib,
        'sla_index'  => $session_id,
        //'sla_owner'  => $session['twampSessionSrcAddr'], // owner used in rrd index (index-owner)
        'sla_tag'    => $session['acdTwampGenCfgName'],
        'sla_target' => $session['acdTwampGenCfgDestIPv4Addr'],
        'sla_graph'  => 'jitter',
        'rtt_type'   => 'twamp',
        'sla_status' => $session['acdTwampGenCfgState'] === 'enable' ? 'active' : 'inactive',
        'deleted'    => 0,
    ];

    // Limits (in ms)
    $data['sla_limit_high']      = $session['acdTwampGenCfgTwoWayMaxDelay'];
    $data['sla_limit_high_warn'] = (int)($data['sla_limit_high'] / 5);

    $sla_table[$mib][$session_id] = $data; // Pull to array for the main processing
}

if (OBS_DEBUG) {
    print_vars(snmpwalk_cache_oid($device, "acdTwampGenResultTable", [], 'ACD-TWAMP-GEN-MIB'));
}

// EOF
