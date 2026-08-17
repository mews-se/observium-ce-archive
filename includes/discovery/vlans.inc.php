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

// Init collecting vars
$discovery_vlans       = [];
$discovery_ports_vlans = [];
$vlans_db              = [];
$ports_vlans_db        = [];
$ports_vlans           = [];

foreach (dbFetchRows("SELECT * FROM `vlans` WHERE `device_id` = ?", [ $device['device_id'] ]) as $vlan_db) {
    if (isset($vlans_db[$vlan_db['vlan_domain']][$vlan_db['vlan_vlan']])) {
        // Clean duplicates
        print_debug("Duplicate VLAN entry in DB found:");
        print_debug_vars($vlan_db);
        dbDelete('vlans', '`vlan_id` = ?', [$vlan_db['vlan_id']]);
        continue;
    }
    $vlans_db[$vlan_db['vlan_domain']][$vlan_db['vlan_vlan']] = $vlan_db;
}
print_debug_vars($vlans_db);

foreach (dbFetchRows("SELECT * FROM `ports_vlans` WHERE `device_id` = ?", [ $device['device_id'] ]) as $vlan_db) {
    if (isset($ports_vlans_db[$vlan_db['port_id']][$vlan_db['vlan']])) {
        // Clean duplicates
        print_debug("Duplicate Port VLAN entry in DB found:");
        print_debug_vars($vlan_db);
        dbDelete('ports_vlans', '`port_vlan_id` = ?', [$vlan_db['port_vlan_id']]);
        continue;
    }
    $ports_vlans_db[$vlan_db['port_id']][$vlan_db['vlan']] = $vlan_db;
}
print_debug_vars($ports_vlans_db);

// Include all discovery modules
$include_dir = "includes/discovery/vlans";
include("includes/include-dir-mib.inc.php");

// Filter out reserved PVST VLANs if configured (e.g., Cisco legacy 1002-1005)
$pvst_ignore = isset($config['pvst']['ignore_vlans']) && is_array($config['pvst']['ignore_vlans']) ? $config['pvst']['ignore_vlans'] : [];
if (!empty($pvst_ignore)) {
    foreach ($discovery_vlans as $domain_index => $vlans) {
        foreach ($vlans as $vlan_id => $vlan) {
            if (in_array((int)$vlan_id, $pvst_ignore, TRUE)) {
                unset($discovery_vlans[$domain_index][$vlan_id]);
            }
        }
    }
    foreach ($discovery_ports_vlans as $ifIndex => $vlans) {
        foreach ($vlans as $vlan_id => $vlan) {
            if (in_array((int)$vlan_id, $pvst_ignore, TRUE)) {
                unset($discovery_ports_vlans[$ifIndex][$vlan_id]);
            }
        }
    }
}

print_debug_vars($discovery_vlans, 1);
print_debug_vars($discovery_ports_vlans, 1);

/* Process discovered Vlans */
$table_rows  = [];
$vlan_params = ['vlan_name', 'vlan_mtu', 'vlan_type', 'vlan_status', 'vlan_context', 'ifIndex'];
foreach ($discovery_vlans as $domain_index => $vlans) {
    foreach ($vlans as $vlan_id => $vlan) {
        echo(" $vlan_id");
        $vlan_update = [];

        // Currently, vlan_context param only actual for CISCO-VTP-MIB
        if (!isset($vlan['vlan_context'])) {
            $vlan['vlan_context'] = 0;
        }

        if (isset($vlans_db[$domain_index][$vlan_id])) {
            // Vlan already in db, compare
            foreach ($vlan_params as $param) {
                if ($vlans_db[$domain_index][$vlan_id][$param] != $vlan[$param]) {
                    if ($param === 'ifIndex' && (is_null($vlan[$param]) || $vlan[$param] === '')) {
                        // Empty string stored as 0, prevent
                        $vlan_update[$param] = ['NULL'];
                    } else {
                        $vlan_update[$param] = $vlan[$param];
                    }
                }
            }

            if (count($vlan_update)) {
                dbUpdate($vlan_update, 'vlans', 'vlan_id = ?', [$vlans_db[$domain_index][$vlan_id]['vlan_id']]);
                $module_stats[$vlan_id]['V'] = 'U';
                $GLOBALS['module_stats'][$module]['updated']++;
            } else {
                $module_stats[$vlan_id]['V'] = '.';
                $GLOBALS['module_stats'][$module]['unchanged']++;
            }

        } else {
            // New vlan discovered
            $vlan_update              = $vlan;
            $vlan_update['device_id'] = $device['device_id'];
            dbInsert($vlan_update, 'vlans');
            $module_stats[$vlan_id]['V'] = '+';
            $GLOBALS['module_stats'][$module]['added']++;
        }

        $table_rows[] = [$domain_index, $vlan_id, $vlan['vlan_name'], $vlan['vlan_type'], $vlan['vlan_status']];
    }
}
$table_headers = ['%WDomain%n', '%WVlan: ID%n', '%WName%n', '%WType%n', '%WStatus%n'];
print_cli_table($table_rows, $table_headers);
/* End process vlans */

// Clean removed vlans
foreach ($vlans_db as $domain_index => $vlans) {
    foreach ($vlans as $vlan_id => $vlan) {
        if (empty($discovery_vlans[$domain_index][$vlan_id])) {
            dbDelete('vlans', "`device_id` = ? AND vlan_domain = ? AND vlan_vlan = ?", [$device['device_id'], $domain_index, $vlan_id]);
            $module_stats[$vlan_id]['V'] = '-';
            $GLOBALS['module_stats'][$module]['deleted']++;

            $table_rows[] = [$domain_index, $vlan_id, $vlan['vlan_name'], $vlan['vlan_type'], $vlan['vlan_status'], ''];
        }
    }
}

$valid['vlans']                             = $discovery_vlans;
$GLOBALS['module_stats'][$module]['status'] = safe_count($valid[$module]);
//if (OBS_DEBUG && $GLOBALS['module_stats'][$module]['status']) { print_vars($valid[$module]); }

/* Process discovered ports vlans */
$table_rows  = [];
$vlan_params = ['vlan']; // STP data moved to separate stp_* tables
foreach ($discovery_ports_vlans as $ifIndex => $vlans) {
    foreach ($vlans as $vlan_id => $vlan) {
        $port = get_port_by_index_cache($device, $ifIndex);
        if (!is_array($port)) {
            continue;
        } // Port not found, skip

        $table_rows[] = [$ifIndex, $port['port_label_short'], $vlan['vlan']];

        $vlan_update = [];
        if (isset($ports_vlans_db[$port['port_id']][$vlan_id])) {
            // Port vlan already in db, compare
            foreach ($vlan_params as $param) {
                if ($ports_vlans_db[$port['port_id']][$vlan_id][$param] != $vlan[$param]) {
                    $vlan_update[$param] = $vlan[$param];
                }
            }

            $id = $ports_vlans_db[$port['port_id']][$vlan_id]['port_vlan_id'];
            if (count($vlan_update)) {
                dbUpdate($vlan_update, 'ports_vlans', '`port_vlan_id` = ?', [$id]);
                $module_stats[$vlan_id]['P'] = 'U';
                $GLOBALS['module_stats']['ports_vlans']['updated']++;
            } else {
                $module_stats[$vlan_id]['P'] = '.';
                $GLOBALS['module_stats']['ports_vlans']['unchanged']++;
            }
        } else {
            // New port vlan discovered
            $vlan_update              = $vlan;
            $vlan_update['device_id'] = $device['device_id'];
            $vlan_update['port_id']   = $port['port_id'];

            $id                          = dbInsert($vlan_update, 'ports_vlans');
            $module_stats[$vlan_id]['P'] = '+';
            $GLOBALS['module_stats']['ports_vlans']['added']++;
        }

        // Store processed IDs
        $ports_vlans[$port['port_id']][$vlan_id] = $id;

    }
}
$table_headers = ['%WifIndex%n', '%WifDescr%n', '%WVlan%n'];
print_cli_table($table_rows, $table_headers);
/* End process ports vlans */

// Clean removed per port vlans
foreach ($ports_vlans_db as $port_id => $vlans) {
    foreach ($vlans as $vlan_id => $vlan) {
        if (empty($ports_vlans[$port_id][$vlan_id])) {
            dbDelete('ports_vlans', "`port_vlan_id` = ?", [$ports_vlans_db[$port_id][$vlan_id]['port_vlan_id']]);
            $module_stats[$vlan_id]['P'] = '-';
            $GLOBALS['module_stats']['ports_vlans']['deleted']++;
        }
    }
}

$valid['ports_vlans']                             = $ports_vlans;
$GLOBALS['module_stats']['ports_vlans']['status'] = count($valid['ports_vlans']);

// Populate STP VLAN mappings when STP poller is enabled. This keeps per-VLAN STP
// visibility without adding poller SNMP load by reusing data gathered here.
if (is_module_enabled($device, 'stp', 'poller')) {
    include_once($config['install_dir'] . '/includes/entities/stp.inc.php');

    // Build a unique VLAN set from discovery results and existing port VLANs.
    $vlan_set = [];
    foreach ($discovery_vlans as $vlans) {
        foreach (array_keys($vlans) as $vlan_id) {
            $vlan_id = (int)$vlan_id;
            if ($vlan_id > 0) {
                $vlan_set[$vlan_id] = TRUE;
            }
        }
    }
    foreach ($discovery_ports_vlans as $vlans) {
        foreach ($vlans as $vlan) {
            $vlan_id = (int)$vlan['vlan'];
            if ($vlan_id > 0) {
                $vlan_set[$vlan_id] = TRUE;
            }
        }
    }

    // Include access VLANs learned via ifVlan for completeness (no extra SNMP).
    foreach (dbFetchColumn('SELECT DISTINCT `ifVlan` FROM `ports` WHERE `device_id` = ? AND `ifVlan` > 0', [$device['device_id']]) as $access_vlan) {
        $vlan_set[(int)$access_vlan] = TRUE;
    }

    $pvst_ignore = isset($config['pvst']['ignore_vlans']) && is_array($config['pvst']['ignore_vlans']) ? $config['pvst']['ignore_vlans'] : [1002, 1003, 1004, 1005];
    $device_id   = $device['device_id'];

    $vlan_map = [];
    $vlan_port_rows = [];
    $cist_id  = stp_instance_ensure($device_id, 0, 'cist');
    foreach (array_keys($vlan_set) as $vlan_id) {
        if (in_array($vlan_id, $pvst_ignore, TRUE)) {
            continue;
        }
        $vlan_map[$vlan_id] = $cist_id;
    }

    // Override with MSTP mappings when available (single-column walk).
    if (snmp_test_oid($device, 'ieee8021MstpConfigName.0', 'IEEE8021-MSTP-MIB')) {
        $mstp_map = snmpwalk_cache_oid($device, 'ieee8021MstpVlanV2MstId', [], 'IEEE8021-MSTP-MIB');
        foreach ($mstp_map as $index => $entry) {
            if (!isset($entry['ieee8021MstpVlanV2MstId'])) {
                continue;
            }
            $mst_id = (int)$entry['ieee8021MstpVlanV2MstId'];
            if ($mst_id <= 0) {
                continue;
            }
            $parts   = explode('.', $index);
            $vlan_id = (int)end($parts);
            if ($vlan_id <= 0 || in_array($vlan_id, $pvst_ignore, TRUE)) {
                continue;
            }
            $instance_id        = stp_instance_ensure($device_id, $mst_id, 'msti');
            $vlan_map[$vlan_id] = $instance_id;
            $vlan_set[$vlan_id] = TRUE;
        }
    }

    $pvst_instances = [];
    $pvst_vlan_membership = [];

    // PVST devices: map enabled VLANs to dedicated instances (single-column walk).
    if (snmp_test_oid($device, 'stpxPVSTVlanEnable', 'CISCO-STP-EXTENSIONS-MIB')) {
        $pvst_table = snmpwalk_cache_oid($device, 'stpxPVSTVlanEnable', [], 'CISCO-STP-EXTENSIONS-MIB');
        foreach ($pvst_table as $index => $entry) {
            $state_raw = isset($entry['stpxPVSTVlanEnable']) ? strtolower(trim((string)$entry['stpxPVSTVlanEnable'])) : '';
            if (!in_array($state_raw, ['enabled', 'true', '1'], TRUE)) {
                continue;
            }
            $parts   = explode('.', $index);
            $vlan_id = (int)end($parts);
            if ($vlan_id <= 0 || in_array($vlan_id, $pvst_ignore, TRUE)) {
                continue;
            }
            $instance_id        = stp_instance_ensure($device_id, $vlan_id, 'pvst');
            $vlan_map[$vlan_id] = $instance_id;
            $vlan_set[$vlan_id] = TRUE;
            $pvst_instances[$vlan_id] = $instance_id;
        }
    }

    // When PVST is active, collect per-VLAN port state data with minimal SNMP.
    if (!empty($pvst_instances)) {
        // Build basePort -> ifIndex map
        $base_map_raw = snmp_cache_table($device, 'dot1dBasePortIfIndex', [], 'BRIDGE-MIB');

        $base_to_if = [];
        foreach ($base_map_raw as $base => $row) {
            if (isset($row['dot1dBasePortIfIndex'])) {
                $base_to_if[(int)$base] = (int)$row['dot1dBasePortIfIndex'];
            } elseif (isset($row['dot1qBasePortIfIndex'])) {
                $base_to_if[(int)$base] = (int)$row['dot1qBasePortIfIndex'];
            }
        }

        $if_to_portid = [];
        if (!empty($base_to_if)) {
            $ifindexes = array_values($base_to_if);
            $placeholders = implode(',', array_fill(0, count($ifindexes), '?'));
            $params = array_merge([$device_id], $ifindexes);
            foreach (dbFetchRows("SELECT `port_id`,`ifIndex` FROM `ports` WHERE `device_id` = ? AND `ifIndex` IN ($placeholders)", $params) as $row) {
                $if_to_portid[(int)$row['ifIndex']] = (int)$row['port_id'];
            }
        }

        // Map VLANs to basePorts from discovery result
        foreach ($discovery_ports_vlans as $ifIndex => $vlans) {
            if (!isset($if_to_portid[$ifIndex])) {
                continue;
            }
            $port_id = $if_to_portid[$ifIndex];
            $base_port = array_search($ifIndex, $base_to_if, TRUE);
            if ($base_port === FALSE) {
                continue;
            }
            foreach ($vlans as $vlan) {
                $vlan_id = (int)$vlan['vlan'];
                if (!isset($pvst_instances[$vlan_id])) {
                    continue;
                }
                $instance_id = $pvst_instances[$vlan_id];
                $vlan_port_rows[] = [
                    'device_id'       => $device_id,
                    'port_id'         => $port_id,
                    'stp_instance_id' => $instance_id,
                    'base_port'       => (int)$base_port,
                    'state'           => 'unknown',
                    'path_cost'       => NULL,
                    'priority'        => NULL,
                    'designated_bridge' => NULL,
                    'designated_port'   => NULL,
                    'designated_root'   => NULL
                ];
            }
        }
    }

    $valid_vlans = array_keys($vlan_map);

    if (!empty($vlan_map)) {
        $replace_rows = [];
        foreach ($vlan_map as $vlan_id => $instance_id) {
            $replace_rows[] = [
                'device_id'       => $device_id,
                'vlan_vlan'       => $vlan_id,
                'stp_instance_id' => $instance_id
            ];
        }
        dbReplaceMulti($replace_rows, 'stp_vlan_map');
    }

    // Remove stale mappings no longer seen in discovery results.
    if (!empty($valid_vlans)) {
        $placeholders = implode(',', array_fill(0, count($valid_vlans), '?'));
        $params = array_merge([$device_id], $valid_vlans);
        dbDelete('stp_vlan_map', "`device_id` = ? AND `vlan_vlan` NOT IN ($placeholders)", $params);
    } else {
        dbDelete('stp_vlan_map', '`device_id` = ?', [$device_id]);
    }

    if (!empty($vlan_port_rows)) {
        dbReplaceMulti($vlan_port_rows, 'stp_ports');
    }
}
//if (OBS_DEBUG && $GLOBALS['module_stats']['ports_vlans']['status']) { print_vars($valid['ports_vlans']); }


// Print vlan specific module stats (P - ports, V - vlans, S - spannigtree)

if ($module_stats) {
    $msg = "Module [ $module ] stats:";
    foreach ($module_stats as $vlan_id => $stat) {
        $msg .= ' ' . $vlan_id . '[';
        foreach ($stat as $k => $v) {
            $msg .= $k . $v;
        }
        $msg .= ']';
    }
    echo($msg);
}

unset($vlans_db, $ports_vlans_db, $ports_vlans, $discovery_vlans, $discovery_ports_vlans);

echo(PHP_EOL);

// EOF
