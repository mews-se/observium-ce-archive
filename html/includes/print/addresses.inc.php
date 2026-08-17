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

function get_address_af($vars) {

    if (!isset($vars['af'])) {
        // Compat with search and device page
        $vars['af'] = $vars['search'] ?? $vars['view'];
    }
    // Set AF before a build query
    switch (strtolower((string)$vars['af'])) {

        case '4':
        case 'v4':
        case 'ipv4':
            return 'ipv4';

        case '6':
        case 'v6':
        case 'ipv6':
            return 'ipv6';
    }

    if (isset($vars['address']) && str_contains($vars['address'], ':')) {
        return 'ipv6';
    }

    return isset($vars['ipv6_address']) ? 'ipv6' : 'ipv4';
}

function build_addresses_query($vars) {

    // Set AF before a build query
    $af = get_address_af($vars);

    $addr_table    = $af . '_addresses';
    $addr_table_id = $af . '_address_id';
    $net_table     = $af . '_networks';
    $net_table_id  = $af . '_network_id';

    $where      = [];
    $join_ports = FALSE;
    foreach ($vars as $var => $value) {
        if (safe_empty($value)) {
            continue;
        }

        switch ($var) {
            case 'device':
                // FIXME -- this seems usseful enough that it should already be a function.
                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                $value = array_reduce($value, static function ($carry, $entry) {
                    if (is_numeric($entry)) {
                        $carry[] = $entry;
                    } elseif ($device_id = get_device_id_by_hostname($entry)) {
                        $carry[] = $device_id;
                    }
                    return $carry;
                }, []);
            // no break here
            case 'device_id':

                $where[] = generate_query_values($value, 'device_id');
                break;

            case 'port_id':
                $where[] = generate_query_values($value, 'port_id');
                break;

            case 'port_name':
            case 'port':
            case 'interface':
                $where[]    = generate_query_values($value, 'port_label', 'LIKE%');
                $join_ports = TRUE;
                break;

            case 'type':
                $where[] = generate_query_values($value, $af . '_type');
                break;

            case 'vrf':
                // FIXME - this probably doesn't function as anyone would expect, since every vrf of the same name has different ids.
                $sql   = "SELECT DISTINCT `vrf_id` FROM `vrfs`";
                $sql  .= generate_where_clause(generate_query_values($value, 'vrf_name'));

                $value = dbFetchColumn($sql);
            // no break here

            case 'vrf_id':
                $where[] = generate_query_values($value, 'vrf_id');
                break;

            case 'network':
                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                if ($ids = get_entity_ids_ip_by_network($af, $value)) {
                    // Full network with prefix
                    $where[] = generate_query_values($ids, $addr_table_id);
                } else {
                    // Part of network string
                    $where[] = '0'; // Nothing!
                }
                break;

            case 'address':
            case 'ipv4_address':
            case 'ipv6_address':

                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                // Remove prefix part
                $value = array_reduce($value, static function ($carry, $entry) {
                    $carry[] = explode('/', $entry)[0];
                    return $carry;
                }, []);

                if ($ids = get_entity_ids_ip_by_network($af, $value)) {
                    $where[] = generate_query_values($ids, $addr_table_id);
                } else {
                    $where[] = '0'; // Nothing!
                }
                break;
        }
    }

    $query = 'SELECT `' . $addr_table . '`.*, `' . $net_table . '`.*';
    $query .= ' FROM `' . $addr_table . '`';
    if ($join_ports) {
        $query .= ' LEFT JOIN `ports` USING (`port_id`, `device_id`)';
    }
    $query .= ' LEFT JOIN `' . $net_table . '` USING (`' . $net_table_id . '`)';
    $query .= generate_where_clause($where, generate_query_permitted_ng([ 'device', 'port' ]));
    $query .= ' ORDER BY `' . $af . '_binary`';

    return $query;
}

function build_addresses_ns_query($vars) {
    // Set AF before a build query
    $af = get_address_af($vars);

    if ($af === 'ipv6') {
        $column_netscaler = 'vsvr_ipv6';
        $where_netscaler  = [ "`vsvr_ipv6` != '0:0:0:0:0:0:0:0'", "`vsvr_ipv6` != '' " ];
    } else {
        $column_netscaler = 'vsvr_ip';
        $where_netscaler  = [ "`vsvr_ip` != '0.0.0.0'", "`vsvr_ip` != '' " ];
    }

    foreach ($vars as $var => $value) {
        if (safe_empty($value)) {
            continue;
        }
        switch ($var) {
            case 'device':
                // FIXME -- this seems usseful enough that it should already be a function.
                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                $value = array_reduce($value, static function($carry, $entry) {
                    if (is_numeric($entry)) {
                        $carry[] = $entry;
                    } elseif ($device_id = get_device_id_by_hostname($entry)) {
                        $carry[] = $device_id;
                    }

                    return $carry;
                }, []);
                // no break here
            case 'device_id':
                $where_netscaler[] = generate_query_values($value, 'device_id');
                break;

            case 'network':
                $where_netscaler[] = '0'; // Currently, unsupported for Netscaller
                break;

            case 'address':
            case 'ipv4_address':
            case 'ipv6_address':
                if (!is_array($value)) {
                    $value = explode(',', $value);
                }
                // Remove prefix part
                $value = array_reduce($value, static function($carry, $entry) {
                    $carry[] = explode('/', $entry)[0];

                    return $carry;
                }, []);

                /// FIXME. Netscaller hack
                if ($ip_valid_ns = (count($value) && get_ip_version($value[0]))) {
                    // Netscaller for valid IP address
                    $where_netscaler[] = generate_query_values($value, $column_netscaler);
                } else {
                    $where_netscaler[] = generate_query_values($value, $column_netscaler, '%LIKE%');
                }
                break;
        }
    }

    // Build netscaler Vserver IPs
    $query_netscaler = 'SELECT * ';
    $query_netscaler .= ' FROM `netscaler_vservers`';
    $query_netscaler .= ' LEFT JOIN `devices` USING(`device_id`)';
    $query_netscaler .= generate_where_clause($where_netscaler, generate_query_permitted_ng('devices'));
    $query_netscaler .= generate_query_sort($column_netscaler);

    return $query_netscaler;
}

/**
 * Get IPv4/IPv6 addresses.
 *
 * @param array $vars
 *
 * @return array
 *
 */
function get_addresses($vars) {

    // Query normal ip tables
    $query   = build_addresses_query($vars);

    // Query addresses
    $entries = dbFetchRows($query);

    // Query address count
    //if ($pagination) { $count = dbFetchCell($query_count); }

    if (!dbExist('netscaler_vservers')) {
        return (array)$entries;
    }

    // Also search netscaler Vserver IPs
    $af = get_address_af($vars);

    // reduce queries to netscaller on common installations without it
    // Rewrite netscaler addresses
    $ns_array = [];
    foreach (dbFetchRows(build_addresses_ns_query($vars)) as $entry) {
        $ip_address = $af === 'ipv4' ? $entry['vsvr_ip'] : $entry['vsvr_ipv6'];
        $ip_network = $af === 'ipv4' ? $entry['vsvr_ip'] . '/32' : $entry['vsvr_ipv6'] . '/128';

        $ns_array[] = [
            'type'           => 'netscalervsvr',
            'device_id'      => $entry['device_id'],
            'hostname'       => $entry['hostname'],
            'vsvr_id'        => $entry['vsvr_id'],
            'vsvr_label'     => $entry['vsvr_label'],
            'ifAlias'        => 'Netscaler: ' . $entry['vsvr_type'] . '/' . $entry['vsvr_entitytype'],
            $af . '_address' => $ip_address,
            $af . '_network' => $ip_network
        ];
    }
    //print_message($query_netscaler_count);
    $ns_array = array_sort($ns_array, $af . '_address'); // Sort netscaller

    return array_merge((array)$entries, $ns_array);
}

function generate_addresses_table_header($vars) {

    $list = [];
    $list['device'] = empty($vars['device']) || $vars['page'] === 'search';

    $string = '<table class="' . OBS_CLASS_TABLE_STRIPED . '">' . PHP_EOL;
    if (!$short) { // FIXME

        // Currently unsortable
        $cols = [];
        if ($list['device']) {
            //$cols['device'] = [ 'Device' ];
            $cols[] = [ 'Device' ];
        }
        // $cols['port']    = [ 'Interface' ];
        // $cols['ip']      = [ 'Address' ];
        // $cols['ip_type'] = [ 'Type' ];
        $cols[]          = [ 'Interface' ];
        $cols[]          = [ 'Address' ];
        $cols[]          = [ 'Type' ];
        $cols[]          = [ '[VRF] Description' ];

        $string .= get_table_header($cols, $vars);
    }

    return $string . '  <tbody>' . PHP_EOL;
}

/**
 * Display IPv4/IPv6 addresses.
 *
 * Display pages with IP addresses from device Interfaces.
 *
 * @param array $vars
 *
 * @return void
 *
 */
function print_addresses($vars) {

    // With pagination? (display page numbers in header)
    $pagination = isset($vars['pagination']) && $vars['pagination'];
    pagination($vars, 0, TRUE); // Get default pagesize/pageno
    $pageno   = $vars['pageno'];
    $pagesize = $vars['pagesize'];
    $start    = $pagesize * $pageno - $pagesize;

    $ip_array = get_addresses($vars);

    if ($pagination) {
        $count    = count($ip_array);
        $ip_array = array_slice($ip_array, $start, $pagesize);
    }

    if ($vars['search'] == '6' || str_contains($vars['search'], 'v6') ||
        $vars['view'] == '6' || str_contains($vars['view'], 'v6')) {
        $address_type = 'ipv6';
    } else {
        $address_type = 'ipv4';
    }

    $list = [];
    $list['device'] = empty($vars['device']) || $vars['page'] === 'search';

    $string = generate_box_open($vars['header']);

    $string .= generate_addresses_table_header($vars);

    $vrf_cache = [];
    foreach ($ip_array as $entry) {
        $address_show = TRUE;
        // if ($ip_valid_ns && $mask) {
        //     // Netscaller. If the address is not in the specified network, don't show entry.
        //     //print_vars($entry[$address_type . '_address']);
        //     //print_vars($addr[0] . '/' . $mask);
        //     $address_show = ip_in_network($entry[$address_type . '_address'], $addr[0] . '/' . $mask);
        // }

        if ($address_show) {
            [ $prefix, $length ] = explode('/', $entry[$address_type . '_network']);

            if ($entry['type'] == 'netscalervsvr') {
                $entity_link = generate_entity_link($entry['type'], $entry);
            } elseif ($port = get_port_by_id_cache($entry['port_id'])) {
                if ($port['ifInErrors_delta'] > 0 || $port['ifOutErrors_delta'] > 0) {
                    $port_error = generate_port_link($port, '<span class="label label-important">Errors</span>', 'port_errors');
                }
                // for port_label_short - generate_port_link($link_if, NULL, NULL, TRUE, TRUE)
                $entity_link      = generate_port_link_short($port) . ' ' . $port_error;
                $entry['ifAlias'] = $port['ifAlias'];
            } elseif ($vlan = dbFetchRow('SELECT * FROM `vlans` WHERE `device_id` = ? AND `ifIndex` = ?', [$entry['device_id'], $entry['ifIndex']])) {
                // Vlan ifIndex (without associated port)
                $entity_link      = 'Vlan ' . $vlan['vlan_vlan'];
                $entry['ifAlias'] = $vlan['vlan_name'];
            } else {
                $entity_link = 'ifIndex ' . $entry['ifIndex'];
            }

            // Query VRFs
            if ($entry['vrf_id']) {
                if (isset($vrf_cache[$entry['vrf_id']])) {
                    $vrf_name = $vrf_cache[$entry['vrf_id']];
                } else {
                    $vrf_name                    = dbFetchCell("SELECT `vrf_name` FROM `vrfs` WHERE `vrf_id` = ?", [$entry['vrf_id']]);
                    $vrf_cache[$entry['vrf_id']] = $vrf_name;
                }
                $entry['ifAlias'] = '<span class="label label-default">' . $vrf_name . '</span> ' . $entry['ifAlias'];
            }

            $device_link = generate_device_link($entry);
            $string      .= '  <tr>' . PHP_EOL;
            if ($list['device']) {
                $string .= '    <td class="entity" style="white-space: nowrap">' . $device_link . '</td>' . PHP_EOL;
            }
            $string .= '    <td class="entity">' . $entity_link . '</td>' . PHP_EOL;
            if ($address_type === 'ipv6') {
                $entry[$address_type . '_address'] = ip_compress($entry[$address_type . '_address']);
            }
            $string     .= '    <td>' . generate_popup_link('ip', $entry[$address_type . '_address'] . '/' . $length) . '</td>' . PHP_EOL;
            $type       = strlen($entry[$address_type . '_type']) ? $entry[$address_type . '_type'] : get_ip_type($entry[$address_type . '_address'] . '/' . $length);
            $type_class = $GLOBALS['config']['ip_types'][$type]['label-class'];
            $string     .= '    <td><span class="label label-' . $type_class . '">' . $type . '</span></td>' . PHP_EOL;
            $string     .= '    <td>' . $entry['ifAlias'] . '</td>' . PHP_EOL;
            $string     .= '  </tr>' . PHP_EOL;

        }
    }

    $string .= '  </tbody>' . PHP_EOL;
    $string .= '</table>';
    $string .= generate_box_close();

    // Print pagination header
    if ($pagination) {
        $string = pagination($vars, $count) . $string . pagination($vars, $count);
    }

    // Print addresses
    echo $string;
}

function generate_ip_link($text = NULL, $vars = [], $class = NULL, $escape = TRUE) {

    $addresses = [];
    // Find networks
    if (preg_match_all(OBS_PATTERN_IP_NET_FULL, $text, $matches)) {
        $addresses += $matches['ip_network'];
    }
    // Find single addresses
    if (preg_match_all(OBS_PATTERN_IP_FULL, $text, $matches)) {
        $addresses += $matches['ip'];
    }
    //r($addresses);
    if (safe_empty($addresses)) {
        return NULL;
    }

    $return = $escape ? escape_html($text) : $text; // escape before replacement (ip nets not escaped anyway)

    foreach ($addresses as $address) {
        [ $ip, $net ] = explode('/', $address);

        $ip_version = get_ip_version($ip);
        if ($ip_version === 6) {
            // Compress IPv6
            $address_compressed = ip_compress($ip);
            if (!safe_empty($net)) {
                $address_compressed .= '/' . $net;
            }
        } else {
            // IPv4 always compressed :)
            $address_compressed = $address;
        }

        $ip_type = get_ip_type($ip);
        //print_warning("$address : $ip_type");
        // Do not linkify some types of ip addresses
        if (in_array($ip_type, [ 'loopback', 'unspecified', 'broadcast', 'private', 'cgnat', 'link-local', 'reserved' ])) {
            if (!safe_empty($class)) {
                $link   = '<span class="' . $class . '">' . $address_compressed . '</span>';
                $return = str_replace($address, $link, $return);
            } elseif ($ip_version === 6) {
                $return = str_replace($address, $address_compressed, $return);
            }
            continue;
        }

        $url    = !safe_empty($vars) ? generate_url($vars) : 'javascript:void(0)'; // If vars empty, a set link not clickable
        $link   = '<a href="' . $url . '" class="entity-popup' . ($class ? " $class" : '') . '" data-eid="' . $ip . '" data-etype="ip">' . $address_compressed . '</a>';
        $return = str_replace($address, $link, $return);
    }

    return $return;
}

// EOF
