<?php

/**
 * Observium
 *
 * @package        observium
 * @subpackage     ajax
 * @copyright      (C) Adam Armstrong
 *
 */

// FIXME - add some ability to do fancier formatting.


include_once("../../includes/observium.inc.php");
include($config['html_dir'] . "/includes/authenticate.inc.php");

if (!$_SESSION['authenticated']) {
    echo("unauthenticated");
    exit;
}

if ($_SESSION['userlevel'] >= 5) {

    $device_id = filter_input(INPUT_GET, 'device_id', FILTER_SANITIZE_NUMBER_INT);
    $entity_type = filter_input(INPUT_GET, 'entity_type', FILTER_SANITIZE_STRING);
    $options = [];

    if (!isset($config['entities'][$entity_type])) {
        echo("invalid entity_type");
        exit;
    }

    $entity_def = $config['entities'][$entity_type];
    $table = $entity_def['table'];
    $id_field = isset($entity_def['table_fields']['id']) ? $entity_def['table_fields']['id'] : null;
    $device_field = isset($entity_def['table_fields']['device_id']) ? $entity_def['table_fields']['device_id'] : null;
    $name_field = isset($entity_def['table_fields']['shortname']) ? $entity_def['table_fields']['shortname'] : (isset($entity_def['table_fields']['name']) ? $entity_def['table_fields']['name'] : null);
    $subtext_field = isset($entity_def['table_fields']['descr']) ? $entity_def['table_fields']['descr'] : null;
    $deleted_field = isset($entity_def['table_fields']['deleted']) ? $entity_def['table_fields']['deleted'] : null;
    $grouping_field = isset($entity_def['grouping_field']) ? $entity_def['grouping_field'] : null;

    if (!$table || !$id_field) {
        echo("missing table or ID field");
        exit;
    }

    // Query DB
    $where = [];
    $params = [];

    // Special case for 'device' type: use permission clause only
    if ($entity_type !== "device" && $device_field && is_numeric($device_id)) {
        if (device_permitted($device_id)) {
            $where[] = "`$device_field` = ?";
            $params[] = $device_id;
        } else {
            print_error_permission("Device not permitted");
            return;
        }
    }

    if ($deleted_field) {
        $where[] = "`$deleted_field` = 0";
    }

    $where_sql = generate_where_clause($where);
    $entities = dbFetchRows("SELECT * FROM `$table` $where_sql", $params);
    $humanize_func = "humanize_$entity_type";

    foreach ($entities as $entity) {
        if (!is_entity_permitted($entity, $entity_type)) {
            continue;
        }

        // Apply humanize function if defined

        if (function_exists($humanize_func)) {
                $humanize_func($entity);
        }
        
        $id = $entity[$id_field];
        $name = isset($entity[$name_field]) ? $entity[$name_field] : "[unnamed]";
        $subtext = $subtext_field && isset($entity[$subtext_field]) ? nicecase($entity[$subtext_field]) : '';
        $group = $grouping_field && isset($entity[$grouping_field]) ? nicecase($entity[$grouping_field]) : null;

        // Resolve icon via icon_field + icon_map
        $icon = null;
        if (isset($entity_def['icon_field'], $entity_def['icon_map'])) {
            $key = isset($entity[$entity_def['icon_field']]) ? $entity[$entity_def['icon_field']] : null;
            if ($key !== null && isset($entity_def['icon_map'][$key]['icon'])) {
                $icon = $entity_def['icon_map'][$key]['icon'];
            }
        }

        // Fallback to per-row or per-entity icon
        if ($icon === null) {
            $icon = isset($entity['icon']) ? $entity['icon'] : (isset($entity_def['icon']) ? $entity_def['icon'] : null);
        }

        $options[] = [
            'value'   => $id,
            'group'   => $group,
            'name'    => addslashes($name),
            'subtext' => addslashes($subtext),
            'icon'    => $icon,
        ];
    }

    echo safe_json_encode($options);
}