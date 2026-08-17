<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     web
 * @copyright  (C) Adam Armstrong
 *
 */

// Get VLANs for this port (no longer using legacy STP fields)
$vlans = dbFetchRows('SELECT V.*, PV.vlan FROM `ports_vlans` AS PV, vlans AS V WHERE PV.`port_id` = ? and PV.`device_id` = ? AND V.`vlan_vlan` = PV.vlan AND V.device_id = PV.device_id', [$port['port_id'], $device['device_id']]);

// Get STP data for this port from STP module
$stp_data = [];
$stp_ports = dbFetchRows('SELECT sp.*, si.instance_key, si.type, vm.vlan_vlan
                          FROM `stp_ports` sp
                          JOIN `stp_instances` si ON sp.stp_instance_id = si.stp_instance_id
                          LEFT JOIN `stp_vlan_map` vm ON si.stp_instance_id = vm.stp_instance_id
                          WHERE sp.device_id = ? AND sp.port_id = ?',
                          [$device['device_id'], $port['port_id']]);

foreach ($stp_ports as $stp_port) {
    // For PVST, use VLAN from mapping; for others, use instance_key
    $vlan_id = ($stp_port['type'] === 'pvst' && $stp_port['vlan_vlan']) ?
               $stp_port['vlan_vlan'] :
               $stp_port['instance_key'];

    if ($vlan_id > 0) {
        $stp_data[$vlan_id] = [
            'state' => $stp_port['state'],
            'priority' => $stp_port['priority'],
            'cost' => $stp_port['path_cost']
        ];
    }
}

echo generate_box_open();

echo('<table class="table  table-striped table-hover table-condensed">');

echo("<thead><tr><th>VLAN</th><th>Description</th><th>Cost</th><th>Priority</th><th>State</th><th>Other Ports</th></tr></thead>");

$row = 0;

foreach ($vlans as $vlan) {
    $row++;
    $row_colour = is_intnum($row / 2) ? OBS_COLOUR_LIST_A : OBS_COLOUR_LIST_B;
    echo('<tr>');

    echo('<td style="width: 100px;" class="entity-title"> Vlan ' . $vlan['vlan'] . '</td>');
    echo('<td style="width: 200px;" class="small">' . $vlan['vlan_name'] . '</td>');

    // Get STP data from STP module instead of legacy ports_vlans fields
    $stp_info = isset($stp_data[$vlan['vlan']]) ? $stp_data[$vlan['vlan']] : null;

    if ($stp_info) {
        $state = $stp_info['state'];
        $priority = $stp_info['priority'];
        $cost = $stp_info['cost'];

        if ($state == "blocking") {
            $class = "red";
        } elseif ($state == "forwarding") {
            $class = "green";
        } else {
            $class = "none";
        }
    } else {
        // No STP data available
        $state = '-';
        $priority = '-';
        $cost = '-';
        $class = "none";
    }

    echo("<td>" . $cost . "</td><td>" . $priority . "</td><td class=" . $class . ">" . $state . "</td>");

    $vlan_ports = [];
    $otherports = dbFetchRows("SELECT * FROM `ports_vlans` AS V, `ports` as P WHERE V.`device_id` = ? AND V.`vlan` = ? AND P.port_id = V.port_id", [$device['device_id'], $vlan['vlan']]);
    foreach ($otherports as $otherport) {
        $vlan_ports[$otherport['ifIndex']] = $otherport;
    }
    $otherports = dbFetchRows("SELECT * FROM ports WHERE `device_id` = ? AND `ifVlan` = ?", [$device['device_id'], $vlan['vlan']]);
    foreach ($otherports as $otherport) {
        $vlan_ports[$otherport['ifIndex']] = array_merge($otherport, ['untagged' => '1']);
    }
    ksort($vlan_ports);

    echo("<td>");
    $vsep = '';
    foreach ($vlan_ports as $otherport) {
        echo($vsep . generate_port_link_short($otherport));
        if ($otherport['untagged']) {
            echo("(U)");
        }
        $vsep = ", ";
    }
    echo("</td>");
    echo("</tr>");
}

echo("</table>");

echo generate_box_close();

// EOF
