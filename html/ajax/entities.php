<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     ajax
 * @author         Adam Armstrong <adama@observium.org>
 * @copyright  (C) Adam Armstrong
 *
 */

include_once("../../includes/observium.inc.php");

include($config['html_dir'] . "/includes/authenticate.inc.php");

if (!$_SESSION['authenticated']) {
    echo("unauthenticated");
    exit;
}
if ($_SESSION['userlevel'] < '5') {
    echo("not permitted");
    exit;
}

$result = [];

switch ($_GET['entity_type']) {

    case "port":
        /* DEPRECATED, REMOVEME
        $where_array = build_ports_where_array($GLOBALS['vars']);

        $where = ' WHERE 1 ';
        $where .= implode('', $where_array);

        $query = 'SELECT *, `ports`.`port_id` AS `port_id` FROM `ports`';
        $query .= $where;

        $ports_db = dbFetchRows($query, $param);
        port_permitted_array($ports_db);

        foreach ($ports_db as $port) {
            humanize_port($port);
            $device   = device_by_id_cache($port['device_id']);
            $result[] = [ (int)$port['port_id'], $device['hostname'], $port['port_label'], $port['ifAlias'], $port['ifOperStatus'] === 'up' ? 'up' : 'down' ];
        }
        */

        // First query port_id for reduction memory usage
        $sql = "SELECT `port_id` FROM `ports`";
        $sql .= " INNER JOIN `devices` USING (`device_id`)";
        $sql .= generate_where_clause(build_ports_where_array_ng($GLOBALS['vars']), $GLOBALS['cache']['where']['ports_permitted']);

        $ports_ids = dbFetchColumn($sql);

        // Query for requested data
        $query = 'SELECT `port_id`, `device_id`, `port_label`, `ifAlias`, `ifOperStatus` FROM `ports`';
        //$query .= " INNER JOIN `devices` USING (`device_id`)";
        $query .= ' WHERE ' . generate_query_values($ports_ids, 'port_id');

        foreach (dbFetchRows($query) as $port) {
            //humanize_port($port);
            //$device   = device_by_id_cache($port['device_id']);

            $result[] = [ (int)$port['port_id'], get_device_hostname_by_id($port['device_id']), $port['port_label'], $port['ifAlias'], $port['ifOperStatus'] === 'up' ? 'up' : 'down' ];
        }
        break;

}

header('Content-Type: application/json');
print safe_json_encode($result, JSON_NUMERIC_CHECK);


// EOF
