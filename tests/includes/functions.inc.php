<?php

/**
 * Initialize (start) snmpsimd daemon, for tests or other purposes.
 *   Stop daemon not required, because here registered shutdown_function for kill daemon at end of run script(s)
 *
 * @param string  $snmpsimd_data Data DIR, where *.snmprec placed
 * @param string  $snmpsimd_ip   Local IP which used for daemon (default 127.0.0.1)
 * @param integer $snmpsimd_port Local Port which used for daemon (default 16111)
 */
function snmpsimd_init($snmpsimd_data, $snmpsimd_ip = '127.0.0.1', $snmpsimd_port = 16111) {
    global $config;

    echo "SNMPsimd init...\n";
    $ip_found = TRUE;
    if (str_contains($snmpsimd_ip, ':')) {
        // IPv6
        $ifconfig_cmd = "ip addr | grep 'inet6 $snmpsimd_ip/' | awk '{print $2}'"; // new
        if (empty(external_exec($ifconfig_cmd))) {
            $ifconfig_cmd = "ifconfig | grep 'inet6 addr:$snmpsimd_ip' | cut -d: -f2 | awk '{print $1}'"; // old
            if (empty(external_exec($ifconfig_cmd))) {
                $ip_found = FALSE;
            }
        }
        $snmpsimd_end = 'udpv6';
    } else {
        $ifconfig_cmd = "ip addr | grep 'inet $snmpsimd_ip/' | awk '{print $2}'"; // new
        if (empty(external_exec($ifconfig_cmd))) {
            $ifconfig_cmd = "ifconfig | grep 'inet addr:$snmpsimd_ip' | cut -d: -f2 | awk '{print $1}'"; // old
            if (empty(external_exec($ifconfig_cmd))) {
                $ip_found = FALSE;
            }
        }
        $snmpsimd_end = 'udpv4';
    }

    if ($ip_found) {
        //$snmpsimd_port = 16111;

        // Detect snmpsimd command path
        $snmpsimd_path = external_exec('which snmpsim-command-responder');
        if (empty($snmpsimd_path)) {
            foreach (['/usr/local/bin/', '/usr/bin/', '/usr/sbin/'] as $path) {
                if (is_executable($path . 'snmpsim-command-responder')) {
                    $snmpsimd_path = $path . 'snmpsim-command-responder';
                    break;
                }
                if (is_executable($path . 'snmpsimd.py')) {
                    $snmpsimd_path = $path . 'snmpsimd.py';
                    break;
                }
                if (is_executable($path . 'snmpsimd')) {
                    $snmpsimd_path = $path . 'snmpsimd';
                    break;
                }
            }
        }
        //var_dump($snmpsimd_path);

        if (empty($snmpsimd_path)) {
            print_warning("snmpsimd not found, please install it first.");
        } else {
            //$snmpsimd_data = dirname(__FILE__) . '/data/os';

            $tmp_path = empty($config['temp_dir']) ? '/tmp' : $config['temp_dir']; // GLOBALS empty in php units

            $snmpsimd_pid = $tmp_path . '/observium_snmpsimd.pid';
            $snmpsimd_log = $tmp_path . '/observium_snmpsimd.log';

            if (is_file($snmpsimd_pid)) {
                // Kill stale snmpsimd process
                $pid  = file_get_contents($snmpsimd_pid);
                $info = get_pid_info($pid);
                //var_dump($info);
                if (str_contains($info['COMMAND'], 'snmpsimd')) {
                    external_exec("kill -9 $pid");
                }
                unlink($snmpsimd_pid);
            }

            $snmpsimd_cmd = "$snmpsimd_path --daemonize --data-dir=$snmpsimd_data --agent-$snmpsimd_end-endpoint=$snmpsimd_ip:$snmpsimd_port --pid-file=$snmpsimd_pid --logging-method=file:$snmpsimd_log";
            //var_dump($snmpsimd_cmd);

            external_exec($snmpsimd_cmd);
            $pid = file_get_contents($snmpsimd_pid);
            if ($pid) {
                define('OBS_SNMPSIMD', TRUE);
                register_shutdown_function(function ($snmpsimd_pid): void {
                    $pid = file_get_contents($snmpsimd_pid);
                    //echo "KILL'em all! PID: $pid\n";
                    external_exec("kill -9 $pid");
                    unlink($snmpsimd_pid);
                }, $snmpsimd_pid);
            }
        }
        //exit;
    } else {
        print_warning("Local IP $snmpsimd_ip unavailable. SNMP simulator not started.");
    }
    if (!defined('OBS_SNMPSIMD')) {
        define('OBS_SNMPSIMD', FALSE);
    }
}

// EOF
