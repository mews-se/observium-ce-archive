<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage entities
 * @copyright  (C) Adam Armstrong
 *
 */

if (!rrd_is_file(get_port_rrdfilename($port, "adsl", TRUE), TRUE)) {
    return;
}

echo generate_box_open();
echo('<table class="table table-condensed table-striped table-hover">');

$graph_array['to'] = get_time();
$graph_array['id'] = $port['port_id'];

echo('<tr><td>');
echo("<h3>ADSL Line Speed</h4>");
$graph_array['type'] = "port_adsl_speed";
print_graph_row($graph_array);
echo('</td></tr>');

echo('<tr><td>');
echo("<h3>ADSL Line Attenuation</h4>");
$graph_array['type'] = "port_adsl_attenuation";
print_graph_row($graph_array);
echo('</td></tr>');

echo('<tr><td>');
echo("<h3>ADSL Line SNR Margin</h4>");
$graph_array['type'] = "port_adsl_snr";
print_graph_row($graph_array);
echo('</td></tr>');

echo('<tr><td>');
echo('<h3>ADSL Output Powers</h4>');
$graph_array['type'] = "port_adsl_power";
print_graph_row($graph_array);
echo('</td></tr>');

echo('</table>');
echo generate_box_close();

// EOF
