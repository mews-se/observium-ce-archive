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

register_html_resource("js", "popper.core.js");
register_html_resource("js", "tippy.js");
register_html_resource("js", "cytoscape.min.js");
//register_html_resource("js", "cola.min.js");
//register_html_resource("js", "cytoscape-cola.js");
register_html_resource("js", "shim.min.js");
register_html_resource("js", "layout-base.js");
register_html_resource("js", "cose-base.js");
//register_html_resource("js", "cytoscape-cose-bilkent.js");
register_html_resource("js", "cytoscape-fcose.js");
register_html_resource("js", "cytoscape-layout-utilities.js");
//register_html_resource("js", "cytoscape-dagre.js");
register_html_resource("js", "cytoscape-popper.js");
//register_html_resource("js", "cytoscape-qtip.js");
//register_html_resource("css", "tippy-translucent.css");

register_html_title("Traffic Map");

$navbar['class'] = 'navbar-narrow';
$navbar['brand'] = 'Traffic Map';

$options = ['port_labels' => 'Port Labels'];

foreach ($options as $option => $label) {
    if (isset($vars[$option]) && $vars[$option]) {
        $navbar['options'][$option]['class'] = 'active';
        $navbar['options'][$option]['url']   = generate_url($vars, [$option => NULL]);
    } else {
        $navbar['options'][$option]['url'] = generate_url($vars, [$option => 'YES']);
    }
    $navbar['options'][$option]['text'] = $label;
    $navbar['options'][$option]['icon'] = $config['icon']['cef'];
}

// 'Devices' navbar menu
$navbar['options']['devices']['text']  = 'Devices';
$navbar['options']['devices']['class'] = 'dropdown-scrollable';
$navbar['options']['devices']['icon']  = $config['icon']['device'];
foreach (generate_form_values('device') as $device_id => $device) {
    $navbar['options']['devices']['suboptions'][$device_id]['text'] = $device['name'];
    $navbar['options']['devices']['suboptions'][$device_id]['url']  = generate_url($vars, ['group' => NULL, 'device_id' => $device_id]);
    if ($vars['device_id'] == $device_id) {
        $navbar['options']['devices']['text']                            .= ' (' . $device['name'] . ')';
        $navbar['options']['devices']['suboptions'][$device_id]['class'] = 'active';
    }
}

// 'VLANS' navbar menu
$navbar['options']['vlans']['text']  = 'VLANs';
$navbar['options']['vlans']['class'] = 'dropdown-scrollable';
$navbar['options']['vlans']['icon']  = $config['icon']['vlan'];
$vlans = dbFetchRows("SELECT `vlan_vlan`, `vlan_name` FROM `vlans` GROUP BY `vlan_vlan`, `vlan_name` ORDER BY `vlan_name`");
foreach ($vlans as $vlan) {
    $vlan_id = $vlan['vlan_vlan'];
    $navbar['options']['vlans']['suboptions'][$vlan_id]['text'] = $vlan['vlan_name'] . ' ('. $vlan_id . ')';
    $navbar['options']['vlans']['suboptions'][$vlan_id]['url']  = generate_url($vars, ['vlan_id' => $vlan_id, 'group_id' => NULL, 'device_id' => NULL]);
    if ($vars['vlan_id'] == $vlan_id) {
        $navbar['options']['vlans']['text']                            .= ' (' . $vlan['vlan_name'] . ')';
        $navbar['options']['vlans']['suboptions'][$vlan_id]['class'] = 'active';
    }
}

// 'Groups' navbar menu

// 'Groups' navbar menu
$navbar['options']['groups']['text']  = 'Groups';
$navbar['options']['groups']['class'] = 'dropdown-scrollable';
$navbar['options']['groups']['icon']  = $config['icon']['group'];
$groups                               = get_groups_by_type();

$groups['device'] = isset($groups['device']) && is_array($groups['device']) ? $groups['device'] : [];
$groups['port']   = isset($groups['port']) && is_array($groups['port']) ? $groups['port'] : [];

foreach (array_merge($groups['device'], $groups['port']) as $group) {
    $group_id                                                     = $group['group_id'];
    $navbar['options']['groups']['suboptions'][$group_id]['text'] = $group['group_name'];
    $navbar['options']['groups']['suboptions'][$group_id]['icon'] = $group['entity_type'] === "device" ? $config['icon']['device'] : $config['icon']['port'];
    $navbar['options']['groups']['suboptions'][$group_id]['url']  = generate_url($vars, ['group_id' => $group_id, 'device_id' => NULL]);
    if ($vars['group_id'] == $group_id) {
        $navbar['options']['groups']['text']  .= ' (' . $group['group_name'] . ')';
        $navbar['options']['groups']['class'] = 'active';
    }
}

print_navbar($navbar);
unset($navbar);

// FIXME. Move to html/includes/print/neighbours.inc.php
function get_neighbour_map($vars) {
    global $cache;

    $where_array   = [ '`active` = 1' ];
    $params = [];
    //$where_array[] = generate_query_permitted_ng('device', [ 'device_table' => 'n' ]);

    if (isset($vars['group_id'])) {

        $group = get_group_by_id($vars['group_id']);

        //r($group);

        $title = $group['group_name'];

        if ($group['entity_type'] == "port") {
            $port_id_list = get_group_entities($vars['group_id'], 'port');
            if (count($port_id_list) > 0) {
                $where_array[] = '(' . generate_query_values($port_id_list, 'n.port_id') . ' OR ' . generate_query_values($port_id_list, 'remote_port_id') . ')';
            } else {
                print_error("Group contains no entities.");
            }
        } elseif ($group['entity_type'] == "device") {
            $device_id_list = get_group_entities($vars['group_id'], 'device');
            if (count($device_id_list) > 0) {
                $where_array[] = '(' . generate_query_values($device_id_list, 'n.device_id') . ' OR ' . generate_query_values($device_id_list, 'remote_device_id') . ')';
            } else {
                print_error("Group contains no entities.");
            }
        }
    } elseif (isset($vars['device_id']) && $vars['device_id']) {
        //$where_array[] = "(`device_id` = '".dbEscape($vars['device_id'])."' OR `remote_device_id` = '".dbEscape($vars['device_id'])."')";
        $where_array[] = '(' . generate_query_values($vars['device_id'], 'n.device_id') . ' OR ' . generate_query_values($vars['device_id'], 'remote_device_id') . ')';
    }

    $sql = "SELECT n.port_id, n.remote_port_id, n.neighbour_id,
       p1.device_id as local_device_id, p1.port_id as local_port_id,
       p2.device_id as remote_device_id, p2.port_id as remote_port_id,
       d1.hostname as local_hostname, d2.hostname as remote_hostname,
       p1.port_label_short as local_port, p2.port_label_short as remote_port,
       p1.ifOperStatus, p1.ifSpeed, p1.ifInOctets_rate, p1.ifOutOctets_rate,
       ph1.port_label_short as local_parent_port, ph2.port_label_short as remote_parent_port
FROM neighbours AS n
LEFT JOIN ports AS p1 ON (n.port_id = p1.port_id)
LEFT JOIN ports AS p2 ON (n.remote_port_id = p2.port_id)
LEFT JOIN devices AS d1 ON (p1.device_id = d1.device_id)
LEFT JOIN devices AS d2 ON (p2.device_id = d2.device_id)
LEFT JOIN ports_stack AS ps1 ON (p1.port_id = ps1.port_id_low AND ps1.ifStackStatus = 'active')
LEFT JOIN ports AS ph1 ON (ps1.port_id_high = ph1.port_id)
LEFT JOIN ports_stack AS ps2 ON (p2.port_id = ps2.port_id_low AND ps2.ifStackStatus = 'active')
LEFT JOIN ports AS ph2 ON (ps2.port_id_high = ph2.port_id)";

    if (isset($vars['vlan_id'])) {
        $sql .= " LEFT JOIN `ports_vlans` AS pv1 ON (p1.device_id = pv1.device_id AND p1.port_id = pv1.port_id AND pv1.vlan = ?)
                  LEFT JOIN `ports_vlans` AS pv2 ON (p2.device_id = pv2.device_id AND p2.port_id = pv2.port_id AND pv2.vlan = ?)";
        $where_array[] = '(pv1.vlan IS NOT NULL OR pv2.vlan IS NOT NULL)';
        $params[] = $vars['vlan_id'];
        $params[] = $vars['vlan_id'];
    }

    $where_array[] = '(p2.device_id IS NULL OR p1.device_id < p2.device_id)';
    $sql .= generate_where_clause($where_array, generate_query_permitted_ng('device', [ 'device_table' => 'n' ]));
    $sql .= " GROUP BY n.port_id, n.remote_port_id ORDER BY d1.hostname, p1.ifIndex";

    # DEBUG?
    #error_log("SQL Query: " . $sql);
    #error_log("SQL Params: " . print_r($params, true));

    $links = dbFetchRows($sql, $params);

    $nodes = [];
    $edges = [];
    $link_exists = [];

    foreach ($links as $neighbour) {
        if (is_numeric($neighbour['remote_device_id']) && $neighbour['remote_device_id']) {
            if (isset($link_exists[$neighbour['remote_port_id'] . '-' . $neighbour['port_id']]) ||
                isset($link_exists[$neighbour['port_id'] . '-' . $neighbour['remote_port_id']])) {
                continue;
            }

            $local_device = device_by_id_cache($neighbour['local_device_id']);
            $remote_device = device_by_id_cache($neighbour['remote_device_id']);

            if (!isset($nodes['d' . $local_device['device_id']])) {
                $nodes['d' . $local_device['device_id']] = [
                    'id'       => 'd' . $local_device['device_id'],
                    'label'    => device_name($local_device, TRUE),
                    'popupurl' => 'ajax/entity_popup.php?entity_type=device&entity_id=' . $local_device['device_id'],
                ];
            }

            if (!isset($nodes['d' . $remote_device['device_id']])) {
                $nodes['d' . $remote_device['device_id']] = [
                    'id'       => 'd' . $remote_device['device_id'],
                    'label'    => device_name($remote_device, TRUE),
                    'popupurl' => 'ajax/entity_popup.php?entity_type=device&entity_id=' . $remote_device['device_id'],
                ];
            }

            $edges[] = [
                'source'     => 'd' . $neighbour['local_device_id'],
                'target'     => 'd' . $neighbour['remote_device_id'],
            ];

            $link_exists[$neighbour['port_id'] . '-' . $neighbour['remote_port_id']] = TRUE;
        }
    }

    return [ 'nodes' => array_values($nodes), 'edges' => $edges, 'external_nodes' => [] ];
}


$map = get_neighbour_map($vars);

?>

<style>
    .tippy-tooltip.translucent-theme {
        background-color: tomato;
        color: yellow;
    }
</style>


<script>
    document.addEventListener('DOMContentLoaded', function () {

        var settings = {
            name: 'fcose',
            animate: 'end',
            animationEasing: 'ease-in-out',
            animationDuration: 250,
            //randomize: true,
            quality: "proof",
            fit: true,
            padding: 30,
            nodeDimensionsIncludeLabels: true,
            uniformNodeDimensions: false,
            packComponents: true,
            step: "all",
            samplingType: true,
            sampleSize: 25,
            nodeSeparation: 100,
            piTol: 0.0000001,
            nodeRepulsion: node => 4500,
            idealEdgeLength: edge => 45,
            edgeElasticity: edge => 0.45,
            nestingFactor: 0.1,
            numIter: 4500,
            tile: true,
            tilingPaddingVertical: 10,
            tilingPaddingHorizontal: 10,
            gravity: 0.25,
            gravityRangeCompound: 1.5,
            gravityCompound: 1.0,
            gravityRange: 1.8,
            initialEnergyOnIncremental: 0.3,
        };

        var cy = window.cy = cytoscape({
            container: document.getElementById('cy'),

            layout: settings,

            wheelSensitivity: 0.25,
            pixelRatio: 'auto',

            style: [
                {
                    selector: 'node',
                    style: {
                        'label': 'data(id)',
                    }
                },
                {
                    selector: 'node:parent',
                    style: {
                        'padding': 10,
                        'shape': 'roundrectangle',
                        'border-width': 0,
                        'background-color': '#c5c5c5',
                    },
                },
                {
                    selector: 'node:parent',
                    css: {
                        'background-opacity': 0.333
                    }
                },
                {
                    selector: 'edge',
                    'style': {
                        'target-arrow-shape': 'square',
                        'source-arrow-shape': 'square',
                        'curve-style': 'bezier',
                        'edge-text-rotation': "autorotate",
                        'source-text-rotation': "autorotate",
                        'target-text-rotation': "autorotate",
                        'width': 4,
                    }
                },
                {
                    selector: '[colourin]',
                    style: {
                        'source-arrow-color': 'data(colourout)',
                        'target-arrow-color': 'data(colourin)',
                    }
                },
                {
                    selector: '[name]',
                    style: {
                        'label': 'data(name)'
                    }
                },
                {
                    selector: '[label]',
                    style: {
                        'label': 'data(label)',
                        'text-background-color': '#c5c5c5',
                        'edge-text-rotation': "autorotate"
                    }
                },
                {
                    selector: '[label]',
                    css: {
                        'text-background-opacity': '0.333',
                        'text-background-padding': '2px',
                        'text-background-shape': 'roundrectangle',
                    }
                },
                {
                    selector: "node:childless",
                    style: {
                        'background-fit': 'cover',
                        'background-image': '/img/router.png',
                        //'background-image-opacity': 0.25,
                        //'background-color': '#FF0000',
                        //'border-width': 2,
                        //'border-color': '#c5c5c5',
                        //'border-opacity': 0.5,
                    }
                },
                {
                    selector: "[labelin]",
                    style: {
                        'target-label': 'data(labelin)',
                        'target-text-offset': '30px',
                        'text-halign': 'right',
                        'text-valign': 'center',
                        //'text-margin-x': '-10px',
                        //'text-margin-y': '-10px',
                        //'text-background-color': '#c5c5c5',
                        //'text-background-opacity': '0.5',
                        //'text-background-padding': '2px',
                        //'text-background-shape': 'roundrectangle',
                    }
                },
                {
                    selector: "[labelout]",
                    style: {
                        'source-label': 'data(labelout)',
                        'source-text-offset': '30px',
                        'text-halign': 'right',
                        'text-valign': 'center',
                        //'text-margin-x': '-10px',
                        //'text-margin-y': '-10px',
                        //'text-background-color': '#c5c5c5',
                        //'text-background-opacity': '0.5',
                        //'text-background-padding': '2px',
                        //'text-background-shape': 'roundrectangle',
                    }
                },
                {
                    selector: 'edge',
                    style: {
                        //'line-fill': 'linear-gradient',
                        //'line-gradient-stop-colors': 'data(gradient)',
                        //'line-gradient-stop-positions': '0% 40% 50% 100%',
                        //'text-background-opacity': '0.5',
                        //'text-background-color': '#555555',
                        'curve-style': 'bezier',
                    }
                }
            ],


            <?php

            echo 'elements: {' . PHP_EOL;
            echo "  'nodes': [ " . PHP_EOL;
            foreach ($map['nodes'] as $node) {
                echo "{ data: " . safe_json_encode($node) . " }," . PHP_EOL;
            }
            echo "]," . PHP_EOL;

            echo "  'edges': [ " . PHP_EOL;
            foreach ($map['edges'] as $edge) {
                echo "{ data: " . safe_json_encode($edge) . " }," . PHP_EOL;
            }
            echo '],' . PHP_EOL;
            echo '}' . PHP_EOL;

            ?>

        });

        document.getElementById("rearrangeButton").addEventListener("click", function () {
            var layout = cy.layout(settings);
            layout.run();
        });

        function makePopper(ele) {
            let ref = ele.popperRef();

            // FIXME - need some delay, otherwise it floods requests when moving mouse over large network
            if (ele.data('tooltip')) {
                ele.tippy = tippy(document.createElement('div'), {
                    // popperInstance will be available onCreate
                    //theme: 'translucent',
                    allowHTML: true,
                    followCursor: 'true',
                    hideOnClick: false,
                    maxWidth: 'none',
                    //trigger: 'click',
                    content: ele.data('tooltip')
                });
            } else if (ele.data('popupurl')) {
                ele.tippy = tippy(document.createElement('div'), {
                    // popperInstance will be available onCreate
                    //theme: 'translucent',
                    allowHTML: true,
                    followCursor: 'true',
                    hideOnClick: false,
                    maxWidth: 'none',
                    //trigger: 'click',
                    onShow(instance) {
                        fetch(ele.data('popupurl'), {
                            method: 'post'
                        })
                            .then((response) => response.text())
                            .then((text) => {
                                instance.setContent(text);
                            })
                            .catch((error) => {
                                // Fallback if the network request failed
                                instance.setContent(`Request failed. ${error}`);
                            });
                    },
                });
                ele.tippy.setContent('Node ' + ele.id());
            }
        }

        cy.ready(function () {
            cy.elements().forEach(function (ele) {
                makePopper(ele);
            });
        });

        cy.elements().unbind('mouseover');
        cy.elements().bind('mouseover', (event) => event.target.tippy.show());

        cy.elements().unbind('mouseout');
        cy.elements().bind('mouseout', (event) => event.target.tippy.hide());

        cy.elements().unbind('drag');
        cy.elements().bind('drag', (event) => event.target.tippy.popperInstance.update());

    });
</script>

<button class="btn" id="rearrangeButton" type="button">Re-arrange</button>

<div id="cy" style="width: 100%; height: 1000px;"></div>

