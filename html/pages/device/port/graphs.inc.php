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

echo generate_box_open();

?>

    <table class="table table-striped  table-condensed">

        <?php

        $rrdfile = get_port_rrdfilename($port, NULL, TRUE);

        if (rrd_is_file($rrdfile)) {
            // Ensure generic graph row renderer knows the port context
            $graph_array['id'] = $port['port_id'];
            echo('<tr><td>');
            echo('<h3>Traffic</h3>');
            $graph_array['type'] = "port_bits";
            print_graph_row($graph_array);
            echo('</td></tr>');

            if (rrd_is_file(get_port_rrdfilename($port, "ipv6-octets", TRUE), TRUE)) {
                echo('<tr><td>');
                echo("<h3>IPv6 Traffic</h3>");
                $graph_array['type'] = "port_ipv6_bits";

                print_graph_row($graph_array);
                echo('</td></tr>');
            }

            echo('<tr><td>');
            echo("<h3>Unicast Packets</h3>");
            $graph_array['type'] = "port_upkts";

            print_graph_row($graph_array);
            echo('</td></tr>');

            echo('<tr><td>');
            echo("<h3>Non Unicast Packets</h3>");
            $graph_array['type'] = "port_nupkts";

            print_graph_row($graph_array);
            echo('</td></tr>');

            echo('<tr><td>');
            echo("<h3>Average Packet Size</h3>");
            $graph_array['type'] = "port_pktsize";

            print_graph_row($graph_array);
            echo('</td></tr>');

            echo('<tr><td>');
            echo("<h3>Percent Utilisation</h3>");
            $graph_array['type'] = "port_percent";

            print_graph_row($graph_array);
            echo('</td></tr>');

            echo('<tr><td>');
            echo("<h3>Errors</h3>");
            $graph_array['type'] = "port_errors";

            print_graph_row($graph_array);
            echo('</td></tr>');

            echo('<tr><td>');
            echo("<h3>Discards</h3>");
            $graph_array['type'] = "port_discards";

            print_graph_row($graph_array);
            echo('</td></tr>');

            if (rrd_is_file(get_port_rrdfilename($port, "dot3", TRUE), TRUE)) {
                echo('<tr><td>');
                echo("<h3>Ethernet Errors</h3>");
                $graph_array['type'] = "port_etherlike";

                print_graph_row($graph_array);
                echo('</td></tr>');

            }

            if (rrd_is_file(get_port_rrdfilename($port, "fdbcount", TRUE), TRUE)) {
                echo('<tr><td>');
                echo("<h3>FDB Count</h3>");
                $graph_array['type'] = "port_fdb_count";

                print_graph_row($graph_array);
                echo('</td></tr>');
            }
        } else {
            print_error("The port's RRD database file '" . $rrdfile . "' doesn't exist.");
        }

        ?>

    </table>
<?php

echo generate_box_close();

// EOF
