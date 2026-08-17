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

// Virtual ports counters
echo("vifTable ");
$vifTable = snmpwalk_cache_oid($device, 'vifTable', [], 'BISON-ROUTER-MIB');
print_debug_vars($vifTable);

if (!snmp_status()) {
    return;
}

foreach ($vifTable as $entry) {
    $portIndex   = $entry['vifPort']; // parent port index
    $ifIndex     = $entry['vifCvid']; // virtual port index

    if (isset($port_stats[$portIndex]) && !isset($port_stats[$ifIndex])) {
        // Add hidden/ignored Vlan port
        $port_stats[$ifIndex] = [
            'ifIndex'       => $ifIndex,
            'ifDescr'       => $entry['vifName'],
            'ifType'        => 'l2vlan',
            'ifAlias'       => '',
            //'ifPhysAddress' => $port_stats[$portIndex]['ifPhysAddress'],
            'ifOperStatus'  => $port_stats[$portIndex]['ifOperStatus'],  // Oper status from parent port
            'ifAdminStatus' => $port_stats[$portIndex]['ifAdminStatus'], // Admin status from parent port
            'ignore'        => '1',       // Set this ports ignored ?
            'disabled'      => '0',

            // Counters
            // 'ifInOctets'       => $entry['vifRxOctets'],
            // 'ifHCInOctets'     => $entry['vifRxOctets'],
            // 'ifOutOctets'      => $entry['vifTxOctets'],
            // 'ifHCOutOctets'    => $entry['vifTxOctets'],
            //
            // 'ifInUcastPkts'    => $entry['vifRxPkts'],
            // 'ifHCInUcastPkts'  => $entry['vifRxPkts'],
            // 'ifOutUcastPkts'   => $entry['vifTxPkts'],
            // 'ifHCOutUcastPkts' => $entry['vifTxPkts'],
        ];
    }
}

// EOF
