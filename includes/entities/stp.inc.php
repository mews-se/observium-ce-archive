<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     entities
 * @copyright  (C) Adam Armstrong
 *
 * Common STP entity helpers used by discovery, poller and UI.
 */

if (!defined('OBSERVIUM')) { die('No direct access allowed'); }

/**
 * Determines the running STP variant using the fastest possible SNMP checks.
 *
 * @param array $device The device array.
 * @return string The detected variant ('mstp', 'rstp', 'stp', 'unknown').
 */
function stp_determine_variant(&$device) {
    // 1. The fastest check: Ask the device what protocol it's running directly.
    $protocol_spec = snmp_get($device, 'dot1dStpProtocolSpecification.0', '-Oqv', 'BRIDGE-MIB');
    if ($protocol_spec && is_string($protocol_spec)) {
        $spec_lower = strtolower($protocol_spec);
        if (str_contains($spec_lower, 'mstp') || str_contains($spec_lower, 'ieee8021s')) {
            return 'mstp';
        }
        if (str_contains($spec_lower, 'rstp') || str_contains($spec_lower, 'ieee8021w')) {
            return 'rstp';
        }
    }

    // 2. Check for MSTP by getting the next OID of a column in its config table.
    $mstp_check = snmp_get_next($device, 'ieee8021MstpConfigurationName', 'IEEE8021-MSTP-MIB');
    if ($mstp_check && $mstp_check['oid_name'] === 'ieee8021MstpConfigurationName') {
        return 'mstp';
    }

    // Note: PVST check is handled by the vendor module override mechanism.

    // 3. Check for RSTP by getting the next OID of the PortRole column.
    $rstp_check = snmp_get_next($device, 'dot1dStpPortRole', 'RSTP-MIB');
    if ($rstp_check && $rstp_check['oid_name'] === 'dot1dStpPortRole') {
        return 'rstp';
    }

    // 4. Fallback check for basic STP by getting the next OID of a column.
    $stp_check = snmp_get_next($device, 'dot1dStpPortState', 'BRIDGE-MIB');
    if ($stp_check && $stp_check['oid_name'] === 'dot1dStpPortState') {
        return 'stp';
    }

    return 'unknown';
}


/** -------------------- ENUM NORMALIZERS -------------------- **/

function stp_normalize_state($raw) {
  // BRIDGE-MIB dot1dStpPortState: disabled(1), blocking(2), listening(3), learning(4), forwarding(5), broken(6)
  // IEEE8021-MSTP state sometimes uses discarding; normalize to same vocabulary.
  $map = [
    1 => 'disabled',
    2 => 'blocking',
    3 => 'listening',
    4 => 'learning',
    5 => 'forwarding',
    6 => 'broken',
    'discarding' => 'discarding', // textual form from some MIBs
  ];
  if (is_numeric($raw)) { $raw = (int)$raw; }
  return $map[$raw] ?? (is_string($raw) ? strtolower($raw) : 'unknown');
}

function stp_normalize_role($raw) {
  // RSTP-MIB dot1dStpPortRole: disabled(1), root(2), designated(3), alternate(4), backup(5), master(6)
  $map = [
    0 => 'unknown',
    1 => 'disabled',
    2 => 'root',
    3 => 'designated',
    4 => 'alternate',
    5 => 'backup',
    6 => 'master',
  ];
  if (is_numeric($raw)) $raw = (int)$raw;
  return $map[$raw] ?? (is_string($raw) ? strtolower($raw) : 'unknown');
}

function stp_truth($v) {
  // SNMP TruthValue: true(1), false(2)
  if (is_numeric($v)) return ((int)$v === 1) ? 1 : 0;
  $v = strtolower(trim((string)$v));
  return ($v === '1' || $v === 'true' || $v === 'enabled') ? 1 : 0;
}

/**
 * Normalize dot1dStpPortPointToPoint/AdminP2P values to 'true'|'false'|'unknown'.
 */
function stp_point2point_status($raw)
{
  if (is_numeric($raw)) {
    $i = (int)$raw;
    if ($i === 1) return 'true';   // forceTrue(1)
    if ($i === 2) return 'false';  // forceFalse(2)
    if ($i === 3) return 'unknown';// auto(3)
  }
  $s = strtolower(trim((string)$raw));
  if ($s === 'true' || $s === '1')  return 'true';
  if ($s === 'false' || $s === '2') return 'false';
  return 'unknown';
}


/** -------------------- BRIDGE ID UTILS -------------------- **/

function stp_bridge_id_str($octets) {
  // Expects binary(8) or colon/space separated hex; returns "prio.extsysid:mac" (e.g. 32768.0001:2233-4455-6677)
  if ($octets === '' || $octets === null) return '';
  $bytes = $octets;

  if (!ctype_print($bytes) && strlen($bytes) === 8) {
    // likely raw binary from DB
  } else {
    // hex string -> binary
    $clean = preg_replace('/[^0-9A-Fa-f]/', '', $bytes);
    if (strlen($clean) === 16) $bytes = pack('H*', $clean);
  }

  if (strlen($bytes) !== 8) return '';
  $prio_ext = unpack('n', substr($bytes, 0, 2))[1]; // 2 bytes
  $mac      = bin2hex(substr($bytes, 2, 6));
  // format mac as xxxx-xxxx-xxxx
  $mac_fmt  = implode('-', str_split($mac, 4));
  return sprintf('%u.%s', $prio_ext, $mac_fmt);
}


/** -------------------- DB HELPERS -------------------- **/

function stp_instance_ensure($device_id, $instance_key, $type, array $fields = []) {
  $id = dbFetchCell("SELECT `stp_instance_id` FROM `stp_instances` WHERE `device_id`=? AND `instance_key`=? AND `type`=?",
                    [ $device_id, $instance_key, $type ]);
  $data = array_merge([
    'device_id'    => $device_id,
    'instance_key' => (int)$instance_key,
    'type'         => $type
  ], $fields);

  if ($id) {
    if (!empty($fields)) {
      dbUpdate($fields, 'stp_instances', '`stp_instance_id` = ?', [ $id ]);
    } else {
      dbQuery('UPDATE `stp_instances` SET `updated` = CURRENT_TIMESTAMP WHERE `stp_instance_id` = ?', [ $id ]);
    }
    return (int)$id;
  }
  return (int)dbInsert($data, 'stp_instances');
}


/** -------------------- BOUNCE TRACKING -------------------- **/

function stp_bounce_hour_floor($now)
{
  return $now - ($now % 3600);
}

/**
 * Convert state string to integer for efficient storage/comparison
 */
function stp_state_to_int($state_str) 
{
  $state_map = [
    'disabled'    => 0,
    'blocking'    => 1,
    'discarding'  => 1, // Same as blocking
    'listening'   => 2,
    'learning'    => 3,
    'forwarding'  => 4,
    'broken'      => 5,
    'unknown'     => 6
  ];

  return $state_map[$state_str] ?? 6; // Default to unknown
}

/**
 * Update bounce counters for one port.
 * @param int $stp_port_id
 * @param int $current_state  Canonical state (tiny int from stp_normalize_state)
 * @param int $now            time()
 * @return array [$t5, $t60, $last_change]
 */
function stp_bounce_update_port($stp_port_id, $current_state, $now)
{
  $row = dbFetchRow("SELECT * FROM `stp_port_state_cache` WHERE `stp_port_id` = ?", array($stp_port_id));
  $w60_start = stp_bounce_hour_floor($now);

  if (!$row)
  {
    // First sighting: seed without counting a change this interval.
    dbInsert(array(
      'stp_port_id'     => $stp_port_id,
      'last_state'      => (int)$current_state,
      'last_change'     => $now,
      'w60_start'       => $w60_start,
      'w60_transitions' => 0
    ), 'stp_port_state_cache');

    return array(0, 0, $now);
  }

  $prev_state  = (int)$row['last_state'];
  $changed     = ($prev_state !== (int)$current_state) ? 1 : 0;
  $last_change = $changed ? $now : (int)$row['last_change'];

  // 60m window rollover on hour boundary
  if ((int)$row['w60_start'] === $w60_start) {
    $w60_transitions = (int)$row['w60_transitions'] + $changed;
  } else {
    $w60_transitions = $changed;
  }

  dbUpdate(array(
    'last_state'      => (int)$current_state,
    'last_change'     => $last_change,
    'w60_start'       => $w60_start,
    'w60_transitions' => $w60_transitions
  ), 'stp_port_state_cache', "stp_port_id = ?", array($stp_port_id));

  // 5m == current poll interval
  return array($changed, $w60_transitions, $last_change);
}

/**
 * Batch update for all current ports on a device.
 * $ports_current: list of arrays with keys: stp_port_id, state (canonical int)
 */
function stp_bounce_update_all($device_id, $ports_current, $persist_to_stp_ports = TRUE)
{
  $now = time();

  foreach ($ports_current as $p)
  {
    $spid  = (int)$p['stp_port_id'];
    $state = (int)$p['state'];

    list($t5, $t60, $last_change) = stp_bounce_update_port($spid, $state, $now);

    if ($persist_to_stp_ports)
    {
      dbUpdate(array(
        'transitions_5m'  => $t5,
        'transitions_60m' => $t60,
        'state_changed'   => $last_change
      ), 'stp_ports', "stp_port_id = ?", array($spid));
    }
  }
}

/**
 * Optional hygiene: remove cache rows for ports that no longer exist.
 * Call occasionally after you finish writing stp_ports.
 */
function stp_bounce_prune_orphans($device_id)
{
  // Fast anti-join limited to device scope
  $orphans = dbFetchRows(
    "SELECT spsc.stp_port_id
       FROM `stp_port_state_cache` AS spsc
  LEFT JOIN `stp_ports` AS sp ON sp.stp_port_id = spsc.stp_port_id
      WHERE sp.stp_port_id IS NULL
        AND EXISTS (SELECT 1 FROM `stp_ports` WHERE `device_id` = ? LIMIT 1)", array($device_id));

  if (!empty($orphans))
  {
    $ids = array_column($orphans, 'stp_port_id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    dbDelete('stp_port_state_cache', "stp_port_id IN ($in)", $ids);
  }

  // Age-based cleanup: remove entries older than 7 days (batch processing for large datasets)
  $cutoff = time() - (7 * 86400); // 7 days ago
  $aged_count = 0;
  $batch_size = 10000; // Process in batches to avoid long-running queries

  // Count how many entries need cleanup first
  $total_aged = dbFetchCell('SELECT COUNT(*) FROM stp_port_state_cache WHERE last_change < ?', [$cutoff]);

  if ($total_aged > 0) {
    if ($total_aged > $batch_size) {
      // For large datasets, delete in batches to avoid timeouts
      $batches_deleted = 0;
      while (($batch_count = dbDelete('stp_port_state_cache', 'last_change < ? LIMIT ' . $batch_size, [$cutoff])) > 0) {
        $aged_count += $batch_count;
        $batches_deleted++;
        if ($batches_deleted >= 5) { // Limit to 5 batches per run to avoid long polling times
          break;
        }
      }
    } else {
      // Small dataset, delete all at once
      $aged_count = dbDelete('stp_port_state_cache', 'last_change < ?', [$cutoff]);
    }

    if ($aged_count > 0) {
      $remaining = $total_aged - $aged_count;
      $msg = sprintf('Cleaned up %d aged STP cache entries (>7 days old)', $aged_count);
      if ($remaining > 0) {
        $msg .= sprintf(', %d remaining for next cycle', $remaining);
      }
      print_debug($msg);
    }
  }
}


/** -------------------- STP HEALTH & PROBLEM DETECTION -------------------- **/

/**
 * Detect STP health issues for a device
 * Returns array of health indicators and problems
 */
function stp_device_health($device_id) {
  $health = [
    'score' => 100,
    'issues' => [],
    'warnings' => [],
    'metrics' => []
  ];
  
  // Get basic bridge data
  $bridge = dbFetchRow("SELECT * FROM `stp_bridge` WHERE `device_id` = ?", [$device_id]);
  if (!$bridge) {
    $health['score'] = 0;
    $health['issues'][] = 'No STP bridge data found';
    return $health;
  }
  
  // Check bridge-level health
  if ($bridge['variant'] === 'unknown') {
    $health['score'] -= 20;
    $health['warnings'][] = 'STP variant not properly detected';
  }
  
  // Analyze topology change frequency (high TC rate indicates instability)
  $tc_rate = stp_calculate_tc_rate($device_id);
  $health['metrics']['tc_rate'] = $tc_rate;
  // Note: TC rate calculation disabled until proper historical tracking is implemented
  // if ($tc_rate > 10) { // More than 10 TCs per hour
  //   $health['score'] -= 15;
  //   $health['warnings'][] = sprintf('High topology change rate: %.1f/hour', $tc_rate);
  // }
  
  // Check for port-level problems
  $port_issues = stp_analyze_port_health($device_id);
  $health['metrics']['port_issues'] = count($port_issues);
  $health['score'] -= min(30, count($port_issues) * 5); // Max 30 point deduction
  $health['issues'] = array_merge($health['issues'], $port_issues);
  
  // Check for multiple STP instances complexity
  $instance_count = dbFetchCell("SELECT COUNT(*) FROM `stp_instances` WHERE `device_id` = ?", [$device_id]);
  $health['metrics']['stp_instances'] = $instance_count;
  if ($instance_count > 10) {
    $health['score'] -= 5;
    $health['warnings'][] = "High STP complexity: $instance_count instances";
  }
  
  // Check for PVST inconsistencies
  $pvst_inconsistent = dbFetchCell("SELECT COUNT(*) FROM `stp_ports` WHERE `device_id` = ? AND `inconsistent` = 1", [$device_id]);
  if ($pvst_inconsistent > 0) {
    $health['score'] -= min(25, $pvst_inconsistent * 10);
    $health['issues'][] = "PVST inconsistencies detected on $pvst_inconsistent ports";
  }
  
  // Check for missing edge port optimization
  $missed_edge_optimization = dbFetchCell("
    SELECT COUNT(*) FROM `stp_ports` p 
    JOIN `ports` po ON po.port_id = p.port_id 
    WHERE p.device_id = ? AND p.admin_edge = 1 AND p.oper_edge != 1 
    AND po.ifOperStatus = 'up' AND p.state = 'forwarding'", [$device_id]);
  if ($missed_edge_optimization > 0) {
    $health['score'] -= min(10, $missed_edge_optimization * 2);
    $health['warnings'][] = "Edge optimization not working on $missed_edge_optimization ports";
  }
  
  // Check for convergence problems
  $convergence_issues = stp_detect_convergence_problems($device_id);
  $health['metrics']['convergence_issues'] = count($convergence_issues);
  $health['score'] -= min(20, count($convergence_issues) * 10);
  $health['issues'] = array_merge($health['issues'], $convergence_issues);
  
  // Check for root bridge consistency
  $root_issues = stp_check_root_consistency($device_id);
  if ($root_issues) {
    $health['score'] -= 10;
    $health['warnings'][] = $root_issues;
  }
  
  // Check for suboptimal root bridge selection (very low priority suggests intentional root)
  if ($bridge['priority'] && $bridge['priority'] > 32768 && (int)$bridge['root_cost'] === 0) {
    $health['warnings'][] = 'Device is root bridge with default priority (' . $bridge['priority'] . ') - consider lowering priority';
  }
  
  // Check for potential loops (multiple forwarding ports to same neighbor)
  $potential_loops = dbFetchCell("
    SELECT COUNT(*) FROM (
      SELECT designated_bridge, COUNT(*) as cnt 
      FROM stp_ports p 
      JOIN ports po ON po.port_id = p.port_id 
      WHERE p.device_id = ? AND p.state = 'forwarding' 
      AND p.designated_bridge IS NOT NULL 
      AND po.ifOperStatus = 'up'
      GROUP BY designated_bridge 
      HAVING cnt > 1
    ) AS loops", [$device_id]);
  if ($potential_loops > 0) {
    $health['score'] -= 15;
    $health['issues'][] = "Potential loops: multiple forwarding ports to same neighbor";
  }
  
  // ==== BEGIN: STP flap aggregation (device-level) ====
  global $config;
  $T = isset($config['stp']['health']) ? $config['stp']['health'] : array('warn_60m'=>3,'crit_60m'=>6,'warn_5m_pct'=>5.0,'crit_5m_pct'=>10.0);
  
  // Device scope
  $scopeWhere = "WHERE `device_id` = ?";
  $scopeArgs  = array($device_id);

  // One pass of aggregates (fast, index-friendly)
  $total   = (int) dbFetchCell("SELECT COUNT(*) FROM `stp_ports` $scopeWhere", $scopeArgs);
  $flap5   = (int) dbFetchCell("SELECT COUNT(*) FROM `stp_ports` $scopeWhere AND `transitions_5m` = 1", $scopeArgs);
  $worst60 = (int) dbFetchCell("SELECT COALESCE(MAX(`transitions_60m`),0) FROM `stp_ports` $scopeWhere", $scopeArgs);
  $sum60   = (int) dbFetchCell("SELECT COALESCE(SUM(`transitions_60m`),0) FROM `stp_ports` $scopeWhere", $scopeArgs);
  $pct5    = $total ? ($flap5 * 100.0 / $total) : 0.0;

  // Map aggregates → flap status
  $flap_status = ($pct5 >= (float)$T['crit_5m_pct'] || $worst60 >= (int)$T['crit_60m']) ? 'critical'
              : (($pct5 >= (float)$T['warn_5m_pct'] || $worst60 >= (int)$T['warn_60m']) ? 'warning' : 'ok');

  // Apply flap scoring based on severity
  if ($flap_status === 'critical') {
    $health['score'] -= 25; // Major impact for device-level critical flapping
  } elseif ($flap_status === 'warning') {
    $health['score'] -= 10; // Moderate impact for device-level warning
  }

  // Build reason text
  $flap_reasons = [];
  if ($total === 0) {
    $flap_reasons[] = 'No STP ports found';
  } else {
    if ($flap5 > 0) { $flap_reasons[] = sprintf('%d/%d (%.1f%%) flapped in last 5m', $flap5, $total, $pct5); }
    if ($worst60>0) { $flap_reasons[] = sprintf('worst port %d transitions (60m), total %d', $worst60, $sum60); }
    if ($flap5===0 && $sum60===0) { $flap_reasons[] = 'No recent flaps detected'; }
  }

  // Add to appropriate health category based on severity
  if ($flap_status === 'critical' && !empty($flap_reasons)) {
    $health['issues'] = array_merge($health['issues'], $flap_reasons);
  } elseif ($flap_status === 'warning' && !empty($flap_reasons)) {
    $health['warnings'] = array_merge($health['warnings'], $flap_reasons);
  }

  // Expose metrics for UI usage
  $health['metrics']['total_ports'] = $total;
  $health['metrics']['flapping_5m'] = $flap5;
  $health['metrics']['worst_60m'] = $worst60;
  $health['metrics']['total_60m'] = $sum60;
  $health['metrics']['flap_5m_pct'] = $pct5;
  // ==== END: STP flap aggregation (device-level) ====
  
  $health['score'] = max(0, $health['score']); // Floor at 0
  return $health;
}

/**
 * Get STP instance health (similar to device health but scoped to instance)
 * @param int $device_id
 * @param int $stp_instance_id 
 * @return array Health metrics for the specific instance
 */
function stp_instance_health($device_id, $stp_instance_id) {
  $health = [
    'score' => 100,
    'issues' => [],
    'warnings' => [],
    'metrics' => []
  ];
  
  // ==== BEGIN: STP flap aggregation (instance-level) ====
  global $config;
  $T = isset($config['stp']['health']) ? $config['stp']['health'] : array('warn_60m'=>3,'crit_60m'=>6,'warn_5m_pct'=>5.0,'crit_5m_pct'=>10.0);
  
  // Instance scope
  $scopeWhere = "WHERE `stp_instance_id` = ?";
  $scopeArgs  = array((int)$stp_instance_id);

  // One pass of aggregates (fast, index-friendly)
  $total   = (int) dbFetchCell("SELECT COUNT(*) FROM `stp_ports` $scopeWhere", $scopeArgs);
  $flap5   = (int) dbFetchCell("SELECT COUNT(*) FROM `stp_ports` $scopeWhere AND `transitions_5m` = 1", $scopeArgs);
  $worst60 = (int) dbFetchCell("SELECT COALESCE(MAX(`transitions_60m`),0) FROM `stp_ports` $scopeWhere", $scopeArgs);
  $sum60   = (int) dbFetchCell("SELECT COALESCE(SUM(`transitions_60m`),0) FROM `stp_ports` $scopeWhere", $scopeArgs);
  $pct5    = $total ? ($flap5 * 100.0 / $total) : 0.0;

  // Map aggregates → flap status
  $flap_status = ($pct5 >= (float)$T['crit_5m_pct'] || $worst60 >= (int)$T['crit_60m']) ? 'critical'
              : (($pct5 >= (float)$T['warn_5m_pct'] || $worst60 >= (int)$T['warn_60m']) ? 'warning' : 'ok');

  // Apply flap scoring based on severity
  if ($flap_status === 'critical') {
    $health['score'] -= 40; // Higher impact for instance-level issues
  } elseif ($flap_status === 'warning') {
    $health['score'] -= 15;
  }

  // Build reason text
  $flap_reasons = [];
  if ($total === 0) {
    $flap_reasons[] = 'No ports in this STP instance';
  } else {
    if ($flap5 > 0) { $flap_reasons[] = sprintf('%d/%d (%.1f%%) flapped in last 5m', $flap5, $total, $pct5); }
    if ($worst60>0) { $flap_reasons[] = sprintf('worst port %d transitions (60m), total %d', $worst60, $sum60); }
    if ($flap5===0 && $sum60===0) { $flap_reasons[] = 'Instance stable - no recent flaps'; }
  }

  // Add to appropriate health category
  if ($flap_status === 'critical' && !empty($flap_reasons)) {
    $health['issues'] = array_merge($health['issues'], $flap_reasons);
  } elseif ($flap_status === 'warning' && !empty($flap_reasons)) {
    $health['warnings'] = array_merge($health['warnings'], $flap_reasons);
  }

  // Expose metrics
  $health['metrics']['total_ports'] = $total;
  $health['metrics']['flapping_5m'] = $flap5;
  $health['metrics']['worst_60m'] = $worst60;
  $health['metrics']['total_60m'] = $sum60;
  $health['metrics']['flap_5m_pct'] = $pct5;
  // ==== END: STP flap aggregation (instance-level) ====
  
  $health['score'] = max(0, $health['score']);
  return $health;
}

/**
 * Calculate topology change rate per hour based on recent changes
 */
function stp_calculate_tc_rate($device_id) {
  $bridge = dbFetchRow("SELECT `top_changes`, `updated` FROM `stp_bridge` WHERE `device_id` = ?", [$device_id]);
  if (!$bridge || !$bridge['top_changes']) return 0;
  
  // For now, return 0 as we need historical data to calculate real rate
  // top_changes is cumulative, we'd need to track deltas over time
  // TODO: Implement proper rate calculation with historical tracking
  return 0;
}

/**
 * Analyze port-level health issues
 */
function stp_analyze_port_health($device_id) {
  $issues = [];
  
  // Get all ports with their STP state (now includes bounce tracking columns)
  $ports = dbFetchRows("
    SELECT p.*, po.ifName, po.ifDescr, i.type, i.instance_key
    FROM stp_ports p 
    JOIN ports po ON po.port_id = p.port_id
    JOIN stp_instances i ON i.stp_instance_id = p.stp_instance_id
    WHERE p.device_id = ? AND po.ifOperStatus = 'up'
    ORDER BY po.ifIndex", [$device_id]);
  
  global $config;
  $T = isset($config['stp']['health']) ? $config['stp']['health'] : array('warn_60m'=>3,'crit_60m'=>6);
  
  foreach ($ports as $port) {
    $port_name = $port['ifName'] ?: $port['ifDescr'];
    
    // Check for flapping/bouncing ports (NEW)
    $t5  = (int)($port['transitions_5m'] ?? 0);
    $t60 = (int)($port['transitions_60m'] ?? 0);
    
    if ($t5 === 1) {
      $issues[] = "Port {$port_name}: Currently flapping (changed state in last poll)";
    } elseif ($t60 >= (int)$T['crit_60m']) {
      $issues[] = "Port {$port_name}: Critically unstable ({$t60} transitions in last hour)";
    } elseif ($t60 >= (int)$T['warn_60m']) {
      $issues[] = "Port {$port_name}: Unstable ({$t60} transitions in last hour)";
    }
    
    // Check for inconsistent ports
    if ($port['inconsistent']) {
      $issues[] = "Port {$port_name}: PVST inconsistency detected";
    }
    
    // Check for ports stuck in transitional states
    if (in_array($port['state'], ['listening', 'learning']) && $port['role'] !== 'disabled') {
      $issues[] = "Port {$port_name}: Stuck in {$port['state']} state (convergence issue?)";
    }
    
    // Check for unexpected blocking on access ports
    if ($port['state'] === 'blocking' && $port['oper_edge'] == 1) {
      $issues[] = "Port {$port_name}: Edge port unexpectedly blocked";
    }
    
    // Check for missing oper_edge on edge ports (RSTP optimization not working)
    if ($port['admin_edge'] == 1 && $port['oper_edge'] != 1 && $port['state'] === 'forwarding') {
      $issues[] = "Port {$port_name}: Admin edge not operationally edge (check for BPDUs)";
    }
    
    // Check for unusual path costs that might indicate misconfig
    if ($port['path_cost'] && ($port['path_cost'] > 200000 || $port['path_cost'] == 1)) {
      $issues[] = "Port {$port_name}: Unusual path cost {$port['path_cost']} (check configuration)";
    }
  }
  
  return $issues;
}

/**
 * Detect convergence and timing problems
 */
function stp_detect_convergence_problems($device_id) {
  $issues = [];
  
  $bridge = dbFetchRow("SELECT * FROM `stp_bridge` WHERE `device_id` = ?", [$device_id]);
  if (!$bridge) return $issues;
  
  // Check for suboptimal timer values
  if ($bridge['hello_time_cs'] && $bridge['hello_time_cs'] > 300) { // > 3 seconds
    $issues[] = 'Hello time too high (' . ($bridge['hello_time_cs']/100) . 's) - slow convergence';
  }
  
  if ($bridge['fwd_delay_cs'] && $bridge['fwd_delay_cs'] > 2000) { // > 20 seconds  
    $issues[] = 'Forward delay too high (' . ($bridge['fwd_delay_cs']/100) . 's) - slow convergence';
  }
  
  if ($bridge['max_age_cs'] && $bridge['max_age_cs'] > 2500) { // > 25 seconds
    $issues[] = 'Max age too high (' . ($bridge['max_age_cs']/100) . 's) - slow failure detection';
  }
  
  // Check for very recent topology changes (might indicate instability)
  if ($bridge['time_since_tc_cs'] && $bridge['time_since_tc_cs'] < 300) { // < 3 seconds
    $issues[] = 'Very recent topology change (' . ($bridge['time_since_tc_cs']/100) . 's ago)';
  }
  
  return $issues;
}

/**
 * Check root bridge consistency across instances
 */
function stp_check_root_consistency($device_id) {
  $instances = dbFetchRows("SELECT * FROM `stp_instances` WHERE `device_id` = ?", [$device_id]);
  if (count($instances) <= 1) return null;
  
  $roots = [];
  foreach ($instances as $inst) {
    if ($inst['designated_root']) {
      $roots[] = $inst['designated_root'];
    }
  }
  
  $unique_roots = array_unique($roots);
  if (count($unique_roots) > 3) {
    return 'Multiple root bridges detected (' . count($unique_roots) . ') - possible network partitioning';
  }
  
  return null;
}

/**
 * Get STP health status class for UI styling
 */
function stp_health_class($score) {
  if ($score >= 90) return 'label-success';
  if ($score >= 70) return 'label-warning'; 
  if ($score >= 50) return 'label-danger';
  return 'label-important';
}

/**
 * Get port health indicators for individual ports
 */
function stp_get_port_health($port_data) {
  $indicators = [];
  
  // Check basic state/role consistency
  if ($port_data['state'] === 'forwarding' && $port_data['role'] === 'alternate') {
    $indicators[] = ['type' => 'error', 'msg' => 'Forwarding alternate port - topology issue'];
  }
  
  if ($port_data['state'] === 'blocking' && $port_data['role'] === 'designated') {
    $indicators[] = ['type' => 'error', 'msg' => 'Blocking designated port - upstream issue'];
  }
  
  // Edge port optimization
  if ($port_data['admin_edge'] && !$port_data['oper_edge'] && $port_data['state'] === 'forwarding') {
    $indicators[] = ['type' => 'warning', 'msg' => 'Edge not operational - receiving BPDUs'];
  }
  
  // Inconsistency
  if ($port_data['inconsistent']) {
    $indicators[] = ['type' => 'error', 'msg' => 'PVST inconsistency'];
  }
  
  // Transitional states
  if (in_array($port_data['state'], ['listening', 'learning'])) {
    $indicators[] = ['type' => 'info', 'msg' => 'Converging (' . $port_data['state'] . ')'];
  }
  
  // ==== BEGIN: STP flap escalation (per-port) ====
  global $config;
  $T   = isset($config['stp']['health']) ? $config['stp']['health'] : array('warn_60m'=>3,'crit_60m'=>6);
  $t5  = (int)($port_data['transitions_5m']  ?? 0);
  $t60 = (int)($port_data['transitions_60m'] ?? 0);
  $chg = (int)($port_data['state_changed']   ?? 0);

  $flap_status = ($t5 === 1 || $t60 >= (int)$T['crit_60m']) ? 'critical'
              : (($t60 >= (int)$T['warn_60m']) ? 'warning' : 'ok');

  // Build flap reason messages
  $flap_reason_bits = array();
  if ($t5 === 1)  { $flap_reason_bits[] = 'Flapped in last poll (5m)'; }
  if ($t60 > 0)   { $flap_reason_bits[] = $t60 . ' transitions in last hour'; }
  if ($chg > 0)   { $flap_reason_bits[] = 'Last change ' . format_uptime(time() - $chg) . ' ago'; }
  
  if (!empty($flap_reason_bits)) {
    $flap_msg = implode('; ', $flap_reason_bits);
    
    // Map flap status to indicator type
    $flap_type = ($flap_status === 'critical') ? 'error' : (($flap_status === 'warning') ? 'warning' : 'info');
    
    $indicators[] = ['type' => $flap_type, 'msg' => $flap_msg];
  }
  // ==== END: STP flap escalation (per-port) ====
  
  return $indicators;
}

/** -------------------- UI HELPERS -------------------- **/

/**
 * Format STP topology change time display
 * 
 * @param int|null $time_since_tc_cs Time since topology change in centiseconds
 * @param string $format Format type: 'text' for plain text, 'label' for Bootstrap labels, 'span' for colored spans
 * @return string|array Formatted time display
 */
function stp_format_tc_time($time_since_tc_cs, $format = 'text') {
  if (is_null($time_since_tc_cs)) {
    switch ($format) {
      case 'label': return ['text' => 'N/A', 'class' => 'label-default'];
      case 'span': return '<span class="text-muted">N/A</span>';
      default: return 'N/A';
    }
  }
  
  $tc_seconds = (int)($time_since_tc_cs / 100);
  
  if ($tc_seconds === 0) {
    switch ($format) {
      case 'label': return ['text' => 'Stable', 'class' => 'label-success'];
      case 'span': return '<span class="text-muted">No recent TC</span>';
      default: return 'Stable';
    }
  }
  
  // Use format_uptime for human-readable time formatting
  $text = format_uptime($tc_seconds, 'short-2');
  
  if ($tc_seconds < 300) { // < 5 minutes - recent change
    switch ($format) {
      case 'label': return ['text' => $text, 'class' => 'label-danger'];
      case 'span': return '<span class="text-danger">' . $text . '</span>';
      default: return $text;
    }
  } elseif ($tc_seconds < 3600) { // < 1 hour - moderately recent
    switch ($format) {
      case 'label': return ['text' => $text, 'class' => 'label-warning'];
      case 'span': return '<span class="text-warning">' . $text . '</span>';
      default: return $text;
    }
  } else { // > 1 hour - stable
    switch ($format) {
      case 'label': return ['text' => $text, 'class' => 'label-success'];
      case 'span': return '<span class="text-success">' . $text . '</span>';
      default: return $text;
    }
  }
}


/** -------------------- CHANGE/LOG HELPERS -------------------- **/

function stp_log_if_changed($device, $row_before, $row_after, $scope = 'bridge', $entity_type = NULL, $entity_id = NULL) {
  if (!$row_before || !$row_after) return;

  // Skip highly dynamic/derived fields to avoid per-poll noise
  $skip_keys = [ 'domain_hash', 'updated', 'last_change', 'time_since_tc_cs' ];

  foreach ($row_after as $k => $v) {
    if (!array_key_exists($k, $row_before)) { continue; }
    if (in_array($k, $skip_keys, TRUE)) { continue; }
    if ($row_before[$k] === $v) { continue; }

    $prev = is_scalar($row_before[$k]) ? $row_before[$k] : json_encode($row_before[$k]);
    $curr = is_scalar($v) ? $v : json_encode($v);
    $msg = sprintf('STP %s %s changed: %s -> %s', strtoupper($scope), $k, $prev, $curr);

    // Tag events as STP, attach to device
    log_event($msg, $device, 'stp', NULL, 'info');
  }
}

/**
 * Format STP domain as label-group badges
 * 
 * @param string $variant Protocol variant (stp, rstp, mstp, pvst, rpvst)
 * @param string $region MST region name (only for MSTP)
 * @param string $root_hex Root bridge ID
 * @return string HTML label-group
 */
function stp_format_domain_labels($variant, $region = null, $root_hex = null) {
  $html = '<span class="label-group">';
  
  // Protocol variant - use consistent instance type colors
  $variant_colors = ['cist' => 'primary', 'msti' => 'info', 'pvst' => 'success', 'stp' => 'default', 'rstp' => 'default', 'mstp' => 'info', 'rpvst' => 'success'];
  $variant_class = $variant_colors[strtolower($variant)] ?? 'default';
  $html .= '<span class="label label-'.$variant_class.'">'.htmlentities(strtoupper($variant)).'</span>';
  
  // MST Region (only for MSTP)
  if (!empty($region)) {
    $html .= '<span class="label label-default">'.htmlentities($region).'</span>';
  }
  
  // Root Bridge (full display)
  if (!empty($root_hex)) {
    $root_display = stp_bridge_id_str($root_hex);
    $html .= '<span class="label label-primary">'.htmlentities($root_display).'</span>';
  }
  
  $html .= '</span>';
  return $html;
}
