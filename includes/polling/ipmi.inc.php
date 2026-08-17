<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     poller
 * @copyright  (C) Adam Armstrong
 *
 */

global $ipmi_sensors;

/// FIXME. From this uses only check_valid_sensors(), maybe need move to global functions or copy to polling. --mike
include_once("includes/discovery/functions.inc.php");

$ipmi = [];
if ($ipmi['host'] = get_dev_attrib($device, 'ipmi_hostname')) {
    $ipmi['interface'] = get_dev_attrib($device, 'ipmi_interface');
    $ipmi['user']      = get_dev_attrib($device, 'ipmi_username');
    if ($ipmi['interface'] === 'lanplus') {
        $ipmi['key'] = get_dev_attrib($device, 'ipmi_key');
    }
    if (safe_empty($ipmi['key'])) {
        $ipmi['password'] = get_dev_attrib($device, 'ipmi_password');
    }
    $ipmi['port']      = get_dev_attrib($device, 'ipmi_port');
    $ipmi['userlevel'] = get_dev_attrib($device, 'ipmi_userlevel');

    if (!is_valid_param($ipmi['port'], 'port')) {
        $ipmi['port'] = 623;
    }
    if (safe_empty($ipmi['userlevel'])) {
        $ipmi['userlevel'] = 'USER';
    }

    if (!array_key_exists($ipmi['interface'], (array)$config['ipmi']['interfaces'])) {
        // Also triggers on empty value
        $ipmi['interface'] = 'lan';
    }

    $own_hostname = $config['own_hostname'] ?: get_localhost();
    $remote       = '';
    if ($own_hostname !== $device['hostname'] &&
        !in_array($ipmi['host'], ['localhost', '127.0.0.1', '::1'], TRUE)) {

        $remote = " -I " . escapeshellarg($ipmi['interface']) .
                  " -H " . escapeshellarg($ipmi['host']) .
                  " -p " . $ipmi['port'] .
                  " -L " . escapeshellarg($ipmi['userlevel']) .
                  " -U " . escapeshellarg($ipmi['user']);
        if (safe_empty($ipmi['key'])) {
            $remote .= " -P " . escapeshellarg($ipmi['password']);
        } else {
            // LAN v2.0 Key
            $remote .= " -k " . escapeshellarg($ipmi['key']);
        }
    }

    // FIXME. What is this? not exist in any device field..
    if (is_numeric($device['ipmi_ciper']) && $device['ipmi_ciper'] == '17') {
        $remote .= " -C " . $device['ipmi_cipher'];
    }

    $results = external_exec($config['ipmitool'] . $remote . " sensor 2>/dev/null");
    /*
    if (strlen($results))
    {
      $sdr = external_exec($config['ipmitool'] . $remote . " sdr 2>/dev/null");
    } else {
      $sdr = '';
    }
    */

    $ipmi_sensors = parse_ipmitool_sensor($device, $results);
}

print_debug_vars($ipmi_sensors, 1);

foreach ($config['ipmi_unit'] as $type) {
    check_valid_sensors($device, $type, $ipmi_sensors, 'ipmi');
}

check_valid_status($device, $ipmi_sensors, 'ipmi');

// EOF
