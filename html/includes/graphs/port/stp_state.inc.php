<?php
if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

// This graph now requires basePort, type, and instance_key (new stable identifiers)
if (!isset($vars['basePort']) || !isset($vars['type']) || !isset($vars['instance_key'])) {
    graph_error('Missing required parameters: basePort, type, instance_key');
    return;
}

$rrd_filename = get_rrd_path($device, 
    'stp-port-' . $vars['basePort'] . '-' . $vars['type'] . '-' . $vars['instance_key'] . '.rrd');

if (!is_file($rrd_filename)) {
    graph_error('STP Port RRD file not found.');
    return;
}

$rrd_options .= ' -t "STP Port State" --rigid --vertical-label "State"';
$rrd_options .= ' --alt-autoscale-max --alt-autoscale-min --y-grid 1:1 --lower-limit 0 --upper-limit 7';

$defs = "DEF:state=$rrd_filename:state:AVERAGE ";

// Get state definitions from MIB
$states = $config['mibs']['BRIDGE-MIB']['states']['dot1dStpPortState'];

// Create custom ticks for the Y-axis
foreach ($states as $state_id => $state)
{
    if (!is_numeric($state_id)) { continue; } // Skip non-numeric keys like aliases
    $defs .= sprintf('TICK:state#%s:%s:%s ', $state['graph_colour'], ($state_id - 0.5), str_pad(strtoupper($state['name']), 10));
}

$defs .= "LINE1:state#333333 ";

$rrd_options .= $defs;