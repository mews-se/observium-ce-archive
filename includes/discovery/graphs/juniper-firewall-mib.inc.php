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

echo("Juniper Firewall Counters");

// jnxFirewallCounterTable
// Index: jnxFWCounterFilterName.jnxFWCounterName.jnxFWCounterType

// JUNIPER-FIREWALL-MIB::jnxFWCounterDisplayType."MacOS_vlan_102"."30__Fileserver_1_150".counter = INTEGER: counter(2)
// JUNIPER-FIREWALL-MIB::jnxFWCounterDisplayType."__default_arp_policer__"."__default_arp_policer__".policer = INTEGER: policer(3)
$fws = snmpwalk_cache_threepart_oid($device, "jnxFWCounterDisplayType", [], "JUNIPER-FIREWALL-MIB");
if (!safe_empty($fws)) {
    $oid = 'jnxFWCounterDisplayType';
}

$array = [];
foreach ($fws as $filter => $counters) {
    // Check graphs firewall ignore filters
    if (entity_descr_check($filter, 'graphs.fw', FALSE)) {
        continue;
    }
    foreach ($counters as $counter => $types) {
        // Check graphs firewall ignore filters
        if (entity_descr_check($counter, 'graphs.fw', FALSE)) {
            continue;
        }
        foreach ($types as $type => $data) {
            $array[$filter][$counter][$type] = 1;
        }
    }
}

echo("\n");

if (!safe_empty($array)) {
    // FIXME. Adama, this array is very big for attrib table
    set_entity_attrib('device', $device['device_id'], 'juniper-firewall-mib', str_compress(safe_json_encode($array)));
}

unset($fws, $filter, $counters, $counter, $data);

// EOF

