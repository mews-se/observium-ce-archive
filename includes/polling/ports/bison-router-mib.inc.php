<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage poller
 * @copyright  (C) Adam Armstrong
 *
 */

// Virtual ports counters
echo("vifTable ");
$vifTable = snmpwalk_cache_oid($device, 'vifTable', [], 'BISON-ROUTER-MIB');
print_debug_vars($vifTable);

// BISON-ROUTER-MIB::vifIndex.2 = INTEGER: 2
// BISON-ROUTER-MIB::vifIndex.3 = INTEGER: 3
// BISON-ROUTER-MIB::vifIndex.4 = INTEGER: 4
// BISON-ROUTER-MIB::vifIndex.5 = INTEGER: 5
// BISON-ROUTER-MIB::vifName.2 = STRING: vl4055
// BISON-ROUTER-MIB::vifName.3 = STRING: vl4054
// BISON-ROUTER-MIB::vifName.4 = STRING: vl2701
// BISON-ROUTER-MIB::vifName.5 = STRING: vl2523
// BISON-ROUTER-MIB::vifPort.2 = INTEGER: 2
// BISON-ROUTER-MIB::vifPort.3 = INTEGER: 2
// BISON-ROUTER-MIB::vifPort.4 = INTEGER: 2
// BISON-ROUTER-MIB::vifPort.5 = INTEGER: 2
// BISON-ROUTER-MIB::vifSvid.2 = INTEGER: 0
// BISON-ROUTER-MIB::vifSvid.3 = INTEGER: 0
// BISON-ROUTER-MIB::vifSvid.4 = INTEGER: 0
// BISON-ROUTER-MIB::vifSvid.5 = INTEGER: 0
// BISON-ROUTER-MIB::vifCvid.2 = INTEGER: 4055
// BISON-ROUTER-MIB::vifCvid.3 = INTEGER: 4054
// BISON-ROUTER-MIB::vifCvid.4 = INTEGER: 2701
// BISON-ROUTER-MIB::vifCvid.5 = INTEGER: 2523
// BISON-ROUTER-MIB::vifRxPkts.2 = Counter64: 1861
// BISON-ROUTER-MIB::vifRxPkts.3 = Counter64: 538547659
// BISON-ROUTER-MIB::vifRxPkts.4 = Counter64: 102280157
// BISON-ROUTER-MIB::vifRxPkts.5 = Counter64: 103714991
// BISON-ROUTER-MIB::vifTxPkts.2 = Counter64: 0
// BISON-ROUTER-MIB::vifTxPkts.3 = Counter64: 214985845
// BISON-ROUTER-MIB::vifTxPkts.4 = Counter64: 285038807
// BISON-ROUTER-MIB::vifTxPkts.5 = Counter64: 250449672
// BISON-ROUTER-MIB::vifRxOctets.2 = Counter64: 193544
// BISON-ROUTER-MIB::vifRxOctets.3 = Counter64: 698141921427
// BISON-ROUTER-MIB::vifRxOctets.4 = Counter64: 47489537318
// BISON-ROUTER-MIB::vifRxOctets.5 = Counter64: 50463194600
// BISON-ROUTER-MIB::vifTxOctets.2 = Counter64: 0
// BISON-ROUTER-MIB::vifTxOctets.3 = Counter64: 99772810113
// BISON-ROUTER-MIB::vifTxOctets.4 = Counter64: 380461278833
// BISON-ROUTER-MIB::vifTxOctets.5 = Counter64: 317404718753

if (!snmp_status()) {
    return;
}

foreach ($vifTable as $vifIndex => $entry) {
    $portIndex   = $entry['vifPort']; // parent port index
    $ifIndex     = $entry['vifCvid']; // virtual port index

    if (isset($port_stats[$portIndex]) && !isset($port_stats[$ifIndex])) {
        // Add hidden/ignored Vlan port
        $port_stats[$ifIndex] = [
            'ifIndex'       => $ifIndex,
            'ifDescr'       => $entry['vifName'],
            'ifType'        => 'l2vlan', // FIXME. I do not know which type can used here
            'ifAlias'       => '',
            'ifSpeed'       => $port_stats[$portIndex]['ifSpeed'],
            'ifHighSpeed'   => $port_stats[$portIndex]['ifHighSpeed'],
            'ifPhysAddress' => $port_stats[$portIndex]['ifPhysAddress'],
            'ifOperStatus'  => $port_stats[$portIndex]['ifOperStatus'],  // Oper status from parent port
            'ifAdminStatus' => $port_stats[$portIndex]['ifAdminStatus'], // Admin status from parent port
            'ignore'        => '1',       // Set this ports ignored ?
            'disabled'      => '0',

            // Counters
            'ifInOctets'       => $entry['vifRxOctets'],
            'ifHCInOctets'     => $entry['vifRxOctets'],
            'ifOutOctets'      => $entry['vifTxOctets'],
            'ifHCOutOctets'    => $entry['vifTxOctets'],

            'ifInUcastPkts'    => $entry['vifRxPkts'],
            'ifHCInUcastPkts'  => $entry['vifRxPkts'],
            'ifOutUcastPkts'   => $entry['vifTxPkts'],
            'ifHCOutUcastPkts' => $entry['vifTxPkts'],
        ];

        // set to zero all other stat oids..
        foreach ($stat_oids_ifEntry as $oid) {
            if (!isset($port_stats[$ifIndex][$oid])) {
                $port_stats[$ifIndex][$oid] = 0;
            }
        }
        // foreach ($stat_oids_ifXEntry as $oid) {
        //     if (!isset($port_stats[$ifIndex][$oid])) {
        //         $port_stats[$ifIndex][$oid] = 0;
        //     }
        // }
    }
}

// Untagged/primary port vlans
$port_module = 'vlan';
if (!$ports_modules[$port_module]) {
    // Module disabled
    return;
}

// Base vlan IDs
// $ports_vlans_oids = snmpwalk_cache_oid($device, 'vifCvid', [], 'BISON-ROUTER-MIB');

echo("vifCvid vifPort ");

// $ports_vlans_oids = snmpwalk_cache_oid($device, 'vifPort', $ports_vlans_oids, 'BISON-ROUTER-MIB');
// print_debug_vars($ports_vlans_oids);

$vlan_rows = [];
foreach ($vifTable as $entry) {
    $ifIndex     = $entry['vifPort'];
    $vlan_num    = $entry['vifCvid'];
    $trunk       = 'access';
    $vlan_rows[] = [ $ifIndex, $vlan_num, $trunk ];

    // Set Vlan and Trunk
    $port_stats[$ifIndex]['ifVlan']  = $vlan_num;
    $port_stats[$ifIndex]['ifTrunk'] = $trunk;

}

$headers = ['%WifIndex%n', '%WVlan%n', '%WTrunk%n'];
print_cli_table($vlan_rows, $headers);

// EOF
