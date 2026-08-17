#!/usr/bin/env php
<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage cli
 * @copyright  (C) Adam Armstrong
 *
 */

chdir(dirname($argv[0]));

// Get options before definitions!
$options = getopt("h:f:o:m:t:r:icdqV", [], $opt_index);

include("../includes/observium.inc.php");

$cli = TRUE;

// get_versions();

if (isset($options['V'])) {
    // Print a current version and exit
    print_message(OBSERVIUM_PRODUCT . " " . OBSERVIUM_VERSION);
    if (is_array($options['V'])) {
        print_versions();
    }
    exit;
}

$snmpdump_start = utime();

if (isset($options['h'])) {
    if (is_intnum($options['h'])) {
        $device = device_by_id_cache($options['h']);
        //print_vars($device);
    } elseif (is_valid_hostname($options['h']) || get_ip_version($options['h'])) {
        $device = device_by_name($options['h']);

        if (!$device && $add = array_slice($argv, $opt_index)) {
            if (get_ip_version($options['h']) || is_domain_resolves($options['h'])) {
                array_unshift($add, $options['h']);

                if ($snmp = get_device_snmp_argv($add, $snmp_options)) {
                    //print_vars($snmp);
                    if ($snmp['snmp_version'] === 'v3') {
                        $snmp_options = array_merge(array_shift($snmp['snmp_v3_auth']), (array)$snmp_options);
                        $device = build_initial_device_array($options['h'], NULL, $snmp['snmp_version'], $snmp['snmp_port'], $snmp['snmp_transport'], (array)$snmp_options);
                    } else {
                        $device = build_initial_device_array($options['h'], array_shift($snmp['snmp_community']), $snmp['snmp_version'], $snmp['snmp_port'], $snmp['snmp_transport'], (array)$snmp_options);
                    }
                    //print_vars($device);
                }
                //print_vars($opt_index);
                // Use same way for parse snmp auth as in add_device
                // SNMPv1/2c:                    add_device.php <hostname> [community] [v1|v2c] [port] [udp|udp6|tcp|tcp6] [context]
                // SNMPv3   :         Defaults : add_device.php <hostname> any v3 [user] [port] [udp|udp6|tcp|tcp6] [context]
                //            No Auth, No Priv : add_device.php <hostname> nanp v3 [user] [port] [udp|udp6|tcp|tcp6] [context]
                //               Auth, No Priv : add_device.php <hostname> anp v3 <user> <password> [md5|sha|sha-224|sha-256|sha-384|sha-512] [port] [udp|udp6|tcp|tcp6] [context]
                //               Auth,    Priv : add_device.php <hostname> ap v3 <user> <password> <enckey> [md5|sha|sha-224|sha-256|sha-384|sha-512] [des|aes|aes-192|aes-192-c|aes-256|aes-256-c] [port] [udp|udp6|tcp|tcp6] [context]
            }
        }
    }

    if (!$device) {
        print_error("Unknown device '{$options['h']}'.");
        exit;
    }
} else {
    // Unknown device (need pass own snmp auth)
    $device = [];
}

if (!$device) {
    print_message("%n
USAGE:
$scriptname [-cqdV] -h device [-f filename]

EXAMPLE:
  ./scripts/snmpdump.php -c -h <device>       Show snmpwalk commands for make snmpdump specific device (exist in db)
  ./scripts/snmpdump.php -h <device>          Make snmpdump for specific device (exist in db) to file <hostname>.snmpwalk
 
  ./scripts/snmpdump.php -h <hostname> <community>  Make snmpdump for specific hostname with v2 community to file <hostname>.snmpwalk
                                                    Can use snmp v1/2c/3 auth params same as for ./add_device.php

OPTIONS:
  -h <device id> | <device hostname>          Device hostname or id (required).
  -f <filename>                               Filename for store snmpdump (default: <hostname>.snmpwalk).
                                              For write to stdout use -f stdout
  -t <timeout>                                SNMP timeout in seconds (default as configured for device or 1 sec)
  -r <retries>                                SNMP retry count (default is 5)
  -m <max-repetition>                         Set snmpbulkwalk Max Repetition value. Set it to 0 for disable SNMP bulk walk.
  -i                                          Set no increase flag for snmpwalk (-Cc).
  -o <oid>                                    Start Numeric Oid (default: .)
  -c                                          Show snmpwalk commands for self run.
  -q                                          Quiet output.
  -V                                          Show observium version and exit.
  -VV                                         Show observium and used programs versions and exit.

DEBUGGING OPTIONS:
  -d                                          Enable debugging output.
  -dd                                         More verbose debugging output.

%rInvalid arguments!%n", 'color', FALSE);
    exit;
}

$oid = $options['o'] ?? NULL;

if (isset($options['t']) && is_numeric($options['t'])) {
    // snmp timeout in seconds
    $device['snmp_timeout'] = (int)$options['t'];
}
if (isset($options['r']) && is_numeric($options['r'])) {
    // snmp retries count
    $device['snmp_retries'] = (int)$options['r'];
}
if (isset($options['m']) && is_numeric($options['m'])) {
    // snmpbulkwalk max-repetition
    $device['snmp_maxrep'] = (int)$options['m'];
}
if (isset($options['i'])) {
    // snmp no increase
    $device['snmp_noincrease'] = TRUE;
}

if (cli_is_piped()) {
    $options['f'] = 'stdout';
} elseif (isset($options['c'])) {
    // show snmpwalk commands
    snmp_dump($device, 'cmd', $oid);
    exit;
} elseif (!isset($options['f'])) {
    $options['f'] = $device['hostname'] . '.snmpwalk';
    if (!is_writable(dirname($options['f']))) {
        $options['f'] = $config['temp_dir'] . '/' . $options['f'];
    }
}

if (strtolower($options['f']) !== 'stdout') {
    if ($filename = snmp_dump($device, $options['f'], $oid)) {
        print_success("SNMP dump completed to file:\n $filename");
    }
} else {
    // Just snmpwalk to stdout
    snmp_dump($device, NULL, $oid);
}

// EOF