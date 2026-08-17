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

// Very basic parser to parse classic Observium-type schemes.
// Parser should populate $port_ifAlias array with type, descr, circuit, speed and notes

function custom_port_parser($port) {

    if (safe_empty($port['ifAlias'])) {
        return [];
    }

    print_debug("Port ifAlias parsing: '{$port['ifAlias']}'\n");

    //$types = ['core', 'peering', 'transit', 'cust', 'server', 'l2tp', 'service'];
    $types = array_keys($GLOBALS['config']['ports']['descr_groups']); // base (still configurable) interface groups
    foreach ($GLOBALS['config']['int_groups'] as $custom_type) {
        $types[] = strtolower(trim($custom_type));
    }

    if (isset($GLOBALS['config']['ports']['descr_regexp'])) {
        $port_ifAlias = [];
        $params       = [ 'type', 'descr', 'circuit', 'speed', 'notes' ];
        foreach ((array)$GLOBALS['config']['ports']['descr_regexp'] as $pattern) {
            if (preg_match($pattern, $port['ifAlias'], $matches)) {
                foreach ($params as $param) {
                    if (!safe_empty($matches[$param])) {
                        $port_ifAlias[$param] = trim($matches[$param]);
                    }
                }
                break;
            }
        }
        if (isset($port_ifAlias['type'], $port_ifAlias['descr']) && in_array(strtolower($port_ifAlias['type']), $types, TRUE)) {
            print_debug("Found by config pattern..\n");
            print_debug_vars($port_ifAlias);

            return $port_ifAlias;
        }
    }

    // Pull out Type and Description or abort
    if (!preg_match('/^([^:]+)[:_]([^\[\]\(\)\{\}]+)/', $port['ifAlias'], $matches)) {
        return [];
    }

    $port_ifAlias = [];
    // Munge and Validate type
    $type = trim($matches[1], " \t\n\r\0\x0B\\/\"'");
    if (!in_array(strtolower($type), $types, TRUE)) {
        print_debug("Found, but type '$type' not configured in \$config['int_groups']..\n");
        return [];
    }
    $port_ifAlias['type'] = $type;

    // Munge and Validate description
    $descr = trim($matches[2]);
    if (safe_empty($descr)) {
        print_debug("Found, but descr empty..\n");
        return [];
    }
    $port_ifAlias['descr'] = $descr;

    if (preg_match('/\{(.*?)\}/', $port['ifAlias'], $matches)) {
        $port_ifAlias['circuit'] = $matches[1];
    }
    if (preg_match('/\[(.*?)\]/', $port['ifAlias'], $matches)) {
        $port_ifAlias['speed'] = $matches[1];
    }
    if (preg_match('/\((.*?)\)/', $port['ifAlias'], $matches)) {
        $port_ifAlias['notes'] = $matches[1];
    }

    print_debug("Found by function pattern..\n");
    print_debug_vars($port_ifAlias);

    return $port_ifAlias;
}

// EOF
