<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage functions
 * @copyright  (C) Adam Armstrong
 *
 */

// Common Functions
/// FIXME. There should be functions that use only standard php (and self) functions.

/**
 * Returns an array of predefined timestamp formats along with their names and example values.
 * The array also includes a 'Current' timestamp format based on the global `$config['timestamp_format']`.
 *
 * @return array An associative array containing timestamp formats as keys and their properties (name and subtext) as values.
 * @global array $config The global configuration array containing the 'timestamp_format' key.
 *
 */
function get_params_timestamp()
{
    global $config;

    $params = [
      'Y-m-d H:i:s'     => ['name' => 'Default'],
      'Y-m-d H:i:s T'   => ['name' => 'Default with TZ'],
      'd/m/Y h:i:s A'   => ['name' => 'GB'],
      'd/m/Y h:i:s A T' => ['name' => 'GB with TZ'],
      'n/j/Y g:i:s A'   => ['name' => 'US'],
      'j F Y, g:i:s A'  => ['name' => 'US Full'],
      'n/j/Y g:i:s A T' => ['name' => 'US with TZ'],
      'd.m.Y H:i:s'     => ['name' => 'EU'],
      'd.m.Y H:i:s T'   => ['name' => 'EU with TZ'],
    ];
    foreach ($params as $key => $param) {
        $params[$key]['subtext'] = date($key);
    }

    if (!isset($params[$config['timestamp_format']])) {
        $params[$config['timestamp_format']] = ['name' => 'Current (' . date($config['timestamp_format']) . ')'];
    }

    return $params;

}

/**
 * Generate RFC 4122 version 4 UUID.
 *
 * @param bool $dashes Whether to include dashes in the output.
 *
 * @return string UUID string (32 chars without dashes, 36 with dashes).
 */
function generate_uuid4($dashes = TRUE): string {
    // Generate 16 cryptographically secure random bytes
    $data = random_bytes(16);

    // Set version to 0100 (UUID v4) in bits 12-15 of time_hi_and_version
    $data[6] = chr(ord($data[6]) & 0x0F | 0x40);

    // Set variant to 10xx in bits 6-7 of clock_seq_hi_and_reserved
    $data[8] = chr(ord($data[8]) & 0x3F | 0x80);

    $hex = bin2hex($data);

    if ($dashes) {
        // Format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
        return substr($hex, 0, 8) . '-' .
               substr($hex, 8, 4) . '-' .
               substr($hex, 12, 4) . '-' .
               substr($hex, 16, 4) . '-' .
               substr($hex, 20, 12);
    }

    return $hex;
}

/**
 * Generate and store Unique ID for current system. Store in DB at first run.
 *  IDs is RFC 4122 version 4 (without dashes, varchar(32)), i.e. c39b2386c4e8487fad4a87cd367b279d
 *
 * @return string Unique system ID
 */
function get_unique_id(): string {
    if (!defined('OBS_UNIQUE_ID')) {
        $unique_id = get_obs_attrib('unique_id');

        if (safe_empty($unique_id)) {
            // Generate a version 4 (random) UUID object
            $unique_id = generate_uuid4(FALSE); // i.e. c39b2386c4e8487fad4a87cd367b279d
            if (!db_skip()) {
                dbInsert([ 'attrib_type' => 'unique_id', 'attrib_value' => $unique_id ], 'observium_attribs');
            }
        }
        define('OBS_UNIQUE_ID', $unique_id);
    }

    return OBS_UNIQUE_ID;
}

/**
 * Generate and store Unique Request ID for current script/page.
 * ID unique between 2 different requests or page loads
 *  IDs is RFC 4122 version 4, i.e. 25769c6c-d34d-4bfe-ba98-e0ee856f3e7a
 *
 * @return string Unique Request ID
 */
function get_request_id(): string {
    if (!defined('OBS_REQUEST_ID')) {
        // Generate a version 4 (random) UUID object
        // i.e. 25769c6c-d34d-4bfe-ba98-e0ee856f3e7a
        define('OBS_REQUEST_ID', generate_uuid4());
    }

    return OBS_REQUEST_ID;
}

/**
 * Set new DB Schema version
 *
 * @param integer $db_rev        New DB schema revision
 * @param boolean $schema_insert Update (by default) or insert by first install db schema
 *
 * @return boolean Status of DB schema update
 */
function set_db_version($db_rev, $schema_insert = FALSE) {
    if ($db_rev >= 211) { // Do not remove this check, since before this revision observium_attribs table not exist!
        $status = set_obs_attrib('dbSchema', $db_rev);
    } else {
        if ($schema_insert) {
            $status = dbInsert(['version' => $db_rev], 'dbSchema');
            if ($status !== FALSE) {
                $status = TRUE;
            } // Note dbInsert return IDs if exist or 0 for not indexed tables
        } else {
            $status = dbUpdate(['version' => $db_rev], 'dbSchema');
        }
    }

    if ($status) {
        mem_cache_set('db_version', $db_rev); // Cache new db version
    }

    return $status;
}

/**
 * Get current DB Schema version
 *
 * @return string DB schema version
 */
// TESTME needs unit testing
function get_db_version($refresh = FALSE) {
    if ($refresh || !mem_cache_exists('db_version')) {
        if ($refresh) {
            reset_attribs_cache();
        }
        $db_rev = @get_obs_attrib('dbSchema');
        if (!$db_rev) {
            $db_rev = 0;
        }
        $db_rev = (int)$db_rev;
        if ($db_rev > 0) {
            mem_cache_set('db_version', $db_rev); // Cache new db version
        }
        return $db_rev;
    }

    return mem_cache_get('db_version');
}

/**
 * Get unique local id.
 * Need to identify poller system.
 *
 * @return string
 */
function get_local_id() {
    // http://0pointer.de/blog/projects/ids.html
    switch (PHP_OS) {
        case 'Linux':
            // Note. system-uuid is good, but available only for root
            if (is_file('/etc/machine-id')) {
                // 1d56dd4b3c334a20bff1fc4878b9e1ee
                return trim(file_get_contents('/etc/machine-id'));
            }
            if (is_file('/var/lib/dbus/machine-id')) {
                return trim(file_get_contents('/var/lib/dbus/machine-id'));
            }
            print_debug("DEBUG: Machine-ID not found on Linux host.");
            break;
        case 'FreeBSD':
            // kern.hostuuid: fe38be37-5d64-11eb-b896-6470021048e6
            if ($id = explode(': ', external_exec('sysctl kern.hostuuid'))[1]) {
                return str_replace('-', '', trim($id));
            }
            break;
        case 'Darwin':
            // $ system_profiler SPHardwareDataType
            // Hardware:
            //
            //     Hardware Overview:
            //
            //       Model Name: iMac
            //       Model Identifier: iMac21,1
            //       Chip: Apple M1
            //       Total Number of Cores: 8 (4 performance and 4 efficiency)
            //       Memory: 16 GB
            //       System Firmware Version: 7429.41.5
            //       OS Loader Version: 6723.140.2
            //       Serial Number (system): XXXX402RXXXX
            //       Hardware UUID: 360CXXXX-XXXX-XXXX-8C34-D2EA2266XXXX
            //       Provisioning UDID: 0000XXXX-0009193C36FB001E
            //       Activation Lock Status: Disabled

            foreach (explode("\n", external_exec('system_profiler SPHardwareDataType')) as $line) {
                if (str_contains($line, 'UUID:') &&
                    $id = explode(': ', $line)[1]) {
                        return str_replace('-', '', strtolower(trim($id)));
                }
            }
            break;
    }

    // Derp way, need to store lock file, available only for current host (not in db!)..
    $id_file = $GLOBALS['config']['log_dir'] . '/.machine-id';
    if (is_file($id_file)) {
        return file_get_contents($id_file);
    }

    $unique_id = generate_uuid4(FALSE);   // i.e. c39b2386c4e8487fad4a87cd367b279d
    if (file_put_contents($id_file, $unique_id)) {
        // return generated id, only when lock file is writable, for prevent logs spamming
        return $unique_id;
    }

    return '';
}

/**
 * Get cached local hostname
 *
 * @return string FQDN local hostname
 */
function get_localhost() {

    if (!mem_cache_exists('localhost')) {
        $localhost = php_uname('n');
        if (!str_contains($localhost, '.')) {
            // try use hostname -f for get FQDN hostname
            $localhost_t = external_exec('/bin/hostname -f');
            if (str_contains($localhost_t, '.')) {
                $localhost = $localhost_t;
            }
        }
        mem_cache_set('localhost', $localhost);
        return $localhost;
    }

    return mem_cache_get('localhost');
}

/**
 * Get owner of current process
 *
 * @return string Username
 */
function get_localuser()
{
    if ($_SERVER['USER']) {
        return $_SERVER['USER'];
    }
    if (function_exists('posix_geteuid')) {
        return posix_getpwuid(posix_geteuid())['name'];
    }

    return external_exec('whoami');
}

/**
 * Calculates the total size of a directory.
 *
 * @param string $dir The path to the directory.
 *
 * @return int|null The total size of the directory in bytes or null if the directory does not exist or is not readable.
 */
function get_dir_size($dir)
{
    // Check if the directory exists and is readable
    if (!is_dir($dir) || !is_readable($dir)) {
        return NULL;
    }

    $size = 0;

    foreach (get_recursive_directory_iterator($dir) as $file) {
        // Check if the file is not a link to avoid potential infinite loop
        if (!$file -> isLink()) {
            $size += $file -> getSize();
        }
    }

    return $size;
}

/**
 * Recursively delete dir.
 *
 * @param string $dir
 *
 * @return bool
 */
function delete_dir($dir)
{
    if (!file_exists($dir)) {
        print_debug("Dir '$dir' not exist.");
        return TRUE;
    }

    $dirs  = [];
    $files = [];
    // Delete files inside dir
    foreach (get_recursive_directory_iterator($dir) as $path => $file) {
        $files[] = $path;
        if ($dir !== $file -> getPath()) {
            $dirs[] = $file -> getPath();
        }

        if (!unlink($path)) {
            // File not deleted
            return FALSE;
        }
        /*
    print_vars($file->getFilename());
    echo PHP_EOL;
    print_vars($file->getExtension());
    echo PHP_EOL;
    print_vars($file->getPath());
    echo PHP_EOL;
    */
    }
    if (count($files)) {
        print_debug("Deleted files:");
        print_debug_vars($files);
    }

    // Now delete sub-dirs
    foreach ($dirs as $d) {
        if (!rmdir($d)) {
            // Sub dir not deleted
            return FALSE;
        }
    }
    $dirs[] = $dir;
    print_debug("Deleted dirs:");
    print_debug_vars($dirs);

    return rmdir($dir);
}

function percent($value, $max, $precision = 0)
{

    $percent = float_div($value, $max) * 100;

    if (is_numeric($precision)) {
        return round($percent, $precision);
    }
    return $percent;
}

/**
 * Percent Class
 *
 * Given a percentage value return a class name (for CSS).
 *
 * @param int|string $percent
 *
 * @return string
 */
function percent_class($percent)
{
    if ($percent < "25") {
        $class = "info";
    } elseif ($percent < "50") {
        $class = "";
    } elseif ($percent < "75") {
        $class = "success";
    } elseif ($percent < "90") {
        $class = "warning";
    } else {
        $class = "danger";
    }

    return $class;
}

/**
 * Percent Colour
 *
 * This function returns a colour based on a 0-100 value
 * It scales from green to red from 0-100 as default.
 *
 * @param integer $value
 * @param integer $brightness
 * @param integer $max
 * @param integer $min
 * @param string  $thirdColourHex
 *
 * @return string
 */
function percent_colour($value, $brightness = 128, $max = 100, $min = 0, $thirdColourHex = '00')
{
    if ($value > $max) {
        $value = $max;
    }
    if ($value < $min) {
        $value = $min;
    }

    // Calculate first and second colour (Inverse relationship)
    $div    = float_div($value, $max);
    $first  = (1 - $div) * $brightness;
    $second = $div * $brightness;

    // Find the influence of the middle Colour (yellow if 1st and 2nd are red and green)
    $diff      = abs($first - $second);
    $influence = ($brightness - $diff) / 2;
    $first     = (int)($first + $influence);
    $second    = (int)($second + $influence);

    // Convert to HEX, format and return
    $firstHex  = str_pad(dechex($first), 2, 0, STR_PAD_LEFT);
    $secondHex = str_pad(dechex($second), 2, 0, STR_PAD_LEFT);

    return '#' . $secondHex . $firstHex . $thirdColourHex;

    // alternatives:
    // return $thirdColourHex . $firstHex . $secondHex;
    // return $firstHex . $thirdColourHex . $secondHex;
}

/**
 * Convert sequence of numbers in an array to range of numbers.
 * Example:
 *  array(1,2,3,4,5,6,7,8,9,10)    -> '1-10'
 *  array(1,2,3,5,7,9,10,11,12,14) -> '1-3,5,7,9-12,14'
 *
 * @param array  $arr       Array with sequence of numbers
 * @param string $separator Use this separator for list
 * @param bool   $sort      Sort input array or not
 *
 * @return string
 */
function range_to_list($arr, $separator = ',', $sort = TRUE)
{
    if (!is_array($arr)) {
        return '';
    }

    if ($sort) {
        sort($arr, SORT_NUMERIC);
    }

    $ranges = [];
    $count  = count($arr);
    for ($i = 0; $i < $count; $i++) {
        $rstart = $arr[$i];
        $rend   = $rstart;
        while (isset($arr[$i + 1]) && ((int)$arr[$i + 1] - (int)$arr[$i]) === 1) {
            $rend = $arr[$i + 1];
            $i++;
        }
        if (is_numeric($rstart) && is_numeric($rend)) {
            $ranges[] = ($rstart == $rend) ? $rstart : $rstart . '-' . $rend;
        } else {
            return ''; // Not numeric value(s)
        }
    }

    return implode($separator, $ranges);
}

// '1-3,5,7,9-12,14' -> array(1,2,3,5,7,9,10,11,12,14)
function list_to_range($str, $separator = ',', $sort = TRUE)
{
    if (!is_string($str)) {
        return $str;
    }

    // Clean spaces while separator not with spaces
    if (!str_contains($separator, ' ')) {
        $str = str_replace(' ', '', $str);
    }

    $arr = [];
    foreach (explode($separator, trim($str)) as $list) {
        $negative = FALSE;
        if ($list[0] === '-') {
            $negative = TRUE;
            $list     = substr($list, 1);
        }
        if (str_contains($list, '-')) {
            [$min, $max] = explode('-', $list, 2);
            if (!is_numeric($min) || !is_numeric($max)) {
                continue;
            }
            if ($negative) {
                $min = '-' . $min;
            }
            if ($min > $max) {
                // ie 10-3
                [$min, $max] = [$max, $min];
            } elseif ($min == $max) {
                // ie 1-1
                $arr[] = (int)$min;
                continue;
            }
            for ($i = $min; $i <= $max; $i++) {
                $arr[] = (int)$i;
            }
        } elseif (is_numeric($list)) {
            $arr[] = $negative ? (int)('-' . $list) : (int)$list;
        }
    }

    if ($sort) {
        sort($arr, SORT_NUMERIC);
    }

    return $arr;
}

/**
 * Write a line to the specified logfile (or default log if not specified).
 * We open & close for every line, somewhat lower performance but this means multiple concurrent processes could write to the file.
 * Now marking process and pid, if things are running simultaneously you can still see what's coming from where.
 *
 * @param string $filename
 * @param string $string
 *
 * @return false|void
 */
function logfile($filename, $string = NULL) {
    global $config;

    if (defined('__PHPUNIT_PHAR__')) {
        print_debug("Skip logging to '$filename' when run phpunit tests.");
        return FALSE;
    }

    // Use default logfile if none specified
    if (safe_empty($string)) {
        $string   = $filename;
        $filename = $config['log_file'];
    }

    // Place logfile in log directory if no path specified
    if (basename($filename) === $filename) {
        $filename = $config['log_dir'] . '/' . $filename;
    }
    // Create logfile if not exist
    if (is_file($filename)) {
        if (!is_writable($filename)) {
            print_debug("Log file '$filename' is not writeable, check file permissions.");
            return FALSE;
        }
        $fd = fopen($filename, 'ab');
    } else {
        $fd = fopen($filename, 'wb');
        // Check writable file (only after creation for speedup)
        if (!is_writable($filename)) {
            print_debug("Log file '$filename' is not writeable or not created.");
            if ($fd !== FALSE) { fclose($fd); }
            return FALSE;
        }
    }


    $string = '[' . date('Y/m/d H:i:s O') . '] ' . OBS_SCRIPT_NAME . '(' . getmypid() . '): ' . trim($string) . PHP_EOL;
    fwrite($fd, $string);
    fclose($fd);
}

/**
 * Get used system versions
 *
 * @param string|null $program
 *
 * @return  array|string
 */
function get_versions($program = NULL) {
    $return_version = !empty($program); // return only version string for program

    if (mem_cache_exists('versions')) {
        // Already cached
        $versions = mem_cache_get('versions');
        if ($return_version) {
            $key = strtolower($program) . '_version';
            if (isset($versions[$key])) {
                return $versions[$key];
            }
            // else directly request version
        } else {
            return $versions;
        }
    }

    $versions = []; // Init
    if ($return_version) {
        // Return only one not cached version
        $programs = (array)$program;
    } else {
        // return array with all versions
        $programs = ['os', 'php', 'python', 'mysql', 'snmp', 'rrdtool', 'fping', 'http', 'curl'];
    }

    foreach ($programs as $entry) {
        switch ($entry) {
            case 'os':
                // Local system OS version
                if (is_executable($GLOBALS['config']['install_dir'] . '/scripts/distro')) {
                    $os = explode('|', external_exec($GLOBALS['config']['install_dir'] . '/scripts/distro'), 7);
                    //print_vars($os);

                    $versions['os_system']         = $os[0];
                    $versions['os_version']        = $os[1];
                    $versions['os_arch']           = $os[2];
                    $versions['os_distro']         = $os[3];
                    $versions['os_distro_version'] = $os[4];
                    $versions['os_virt']           = !empty($os[5]) ? $os[5] : $os[6]; // virt or cont
                    if ($versions['os_virt']) {
                        if (isset($GLOBALS['config']['virt-what'][$versions['os_virt']])) {
                            $versions['os_virt_name'] = $GLOBALS['config']['virt-what'][$versions['os_virt']];
                        } else {
                            $versions['os_virt_name'] = nicecase($versions['os_virt']);
                        }
                    }
                    $versions['os_text']           = $os[0] . ' ' . $os[1] . ' [' . $os[2] . '] (' . $os[3] . ' ' . $os[4] . ')';
                }
                if ($return_version) {
                    return (string)$versions['os_version'];
                }
                break;

            case 'php':
                // PHP
                $versions['php_version'] = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
                if ($return_version) {
                    return $versions['php_version'];
                }
                $versions['php_text'] = PHP_VERSION;
                $versions['php_old']  = version_compare($versions['php_version'], OBS_MIN_PHP_VERSION, '<');

                // PHP OPcache
                $versions['php_opcache'] = FALSE;
                if (extension_loaded('Zend OPcache')) {
                    $opcache = ini_get('opcache.enable');
                    if ($opcache && is_cli()) {
                        $opcache = ini_get('opcache.enable_cli'); // extra ini option for cli
                    }
                    $versions['php_opcache'] = $opcache;
                }

                // FastCGI
                $versions['php_fastcgi'] = str_contains(PHP_SAPI, 'fcgi'); // fpm-fcgi, cgi-fcgi

                // PHP memory_limit
                $php_memory_limit             = unit_string_to_numeric(ini_get('memory_limit'));
                $versions['php_memory_limit'] = $php_memory_limit;
                if ($php_memory_limit < 0) {
                    $versions['php_memory_limit_text'] = 'Unlimited';
                } else {
                    $versions['php_memory_limit_text'] = format_bytes($php_memory_limit);
                }

                // PHP math (Brick\Math)
                if (extension_loaded('gmp')) {
                    $versions['php_math_text'] = 'GMP';
                } elseif (extension_loaded('bcmath')) {
                    $versions['php_math_text'] = 'BCMATH';
                } else {
                    $versions['php_math_text'] = 'NATIVE';
                }

                break;

            case 'python':
                /** Python
                 * I.e.:
                 * python_version = 3.10.12
                 * python_text    = 3.10.12
                 */
                $python_version = str_replace('Python ', '', external_exec('/usr/bin/env python3 --version 2>&1'));
                $python3_default = FALSE;
                if (str_contains($python_version, 'No such file or directory')) {
                    // /usr/bin/env: 'python3': No such file or directory
                    $python2_version = str_replace('Python ', '', external_exec('/usr/bin/env python --version 2>&1'));
                    if (str_contains($python2_version, 'No such file or directory')) {
                        // /usr/bin/env: 'python': No such file or directory
                        $python_version = 'Not found';
                    } elseif (str_starts_with($python2_version, '2.')) {
                        // Any python 2.x is old and unsupported now
                        $python_version = $python2_version;
                        $versions['python_old'] = TRUE;
                    } else {
                        $python3_default = TRUE;
                    }
                }
                $versions['python_version'] = $python_version;
                if ($return_version) {
                    return $versions['python_version'];
                }
                if (!isset($versions['python_old'])) {
                    $versions['python_old'] = version_compare($versions['python_version'], OBS_MIN_PYTHON3_VERSION, '<');
                }
                $versions['python_text'] = $python_version;
                if ($python3_default) {
                    $versions['python_text'] .= ' (python3 is not default python command)';
                }
                break;

            case 'mysql':
            case 'mariadb':
                if (db_skip()) {
                    break;
                }
                /** MySQL
                 * I.e.:
                 * mysql_client  = 5.0.12-dev
                 * mysql_full    = 10.3.23-MariaDB-log
                 * mysql_name    = MariaDB
                 * mysql_version = 10.3.23
                 * mysql_text    = 10.3.23-MariaDB-log (extension: mysqli 5.0.12-dev)
                 */

                $mysql_version            = dbFetchCell("SELECT version();");
                $versions['mysql_full']   = $mysql_version;
                $versions['mysql_version'] = explode('-', $mysql_version)[0];

                // Define DB NAME for later use
                $versions['mysql_name'] = str_contains(strtolower($mysql_version), 'maria') ? 'MariaDB' : 'MySQL';
                if (!defined('OBS_DB_NAME')) {
                    define('OBS_DB_NAME', $versions['mysql_name']);
                }

                if ($return_version) {
                    return $versions['mysql_version'];
                }

                if ($versions['mysql_name'] === 'MariaDB') {
                    $versions['mysql_old'] = version_compare($versions['mysql_version'], OBS_MIN_MARIADB_VERSION, '<');
                } else {
                    $versions['mysql_old'] = version_compare($versions['mysql_version'], OBS_MIN_MYSQL_VERSION, '<');
                }
                $mysql_client = dbClientInfo();
                if (preg_match('/(\d+\.[\w\.\-]+)/', $mysql_client, $matches)) {
                    $mysql_client = $matches[1];
                }
                $versions['mysql_client'] = (OBS_DB_MYSQLND ? 'MySQLnd' : 'MySQLi') . ' ' . $mysql_client;
                $mysql_version          .= ' (client: ' . $versions['mysql_client'] . ')';
                $versions['mysql_text'] = $mysql_version;

                break;

            case 'snmp':
                /** SNMP
                 * I.e.:
                 * snmp_version = 5.7.2
                 * snmp_text    = NET-SNMP 5.7.2
                 */
                $snmp_cmd                 = is_executable($GLOBALS['config']['snmpget']) ? $GLOBALS['config']['snmpget'] : '/usr/bin/env snmpget';
                $snmp_version             = str_replace(' version:', '', external_exec($snmp_cmd . " --version 2>&1"));
                $versions['snmp_version'] = str_replace('NET-SNMP ', '', $snmp_version);
                if ($return_version) {
                    return $versions['snmp_version'];
                }
                if (empty($versions['snmp_version'])) {
                    $versions['snmp_version'] = 'not found';
                }
                $versions['snmp_text'] = $snmp_version;
                break;

            case 'rrdtool':
                /** RRDtool
                 * I.e.:
                 * rrdtool_version   = 1.5.5
                 * rrdcached_version = 1.5.5
                 * rrdtool_text      = 1.5.5 (rrdcached 1.5.5: unix:/var/run/rrdcached.sock)
                 */
                $rrdtool_cmd = is_executable($GLOBALS['config']['rrdtool']) ? $GLOBALS['config']['rrdtool'] : '/usr/bin/env rrdtool';
                [, $rrdtool_version] = explode(' ', external_exec($rrdtool_cmd . ' --version | head -n1'));
                $versions['rrdtool_version'] = $rrdtool_version;
                if ($return_version) {
                    return $versions['rrdtool_version'];
                }
                $versions['rrdtool_old'] = version_compare($versions['rrdtool_version'], OBS_MIN_RRD_VERSION, '<');

                if (!safe_empty($GLOBALS['config']['rrdcached'])) {
                    if (OBS_RRD_REMOTE) {
                        // Remote rrdcached daemon (unknown version)
                        $rrdtool_version .= ' (rrdcached remote: ' . $GLOBALS['config']['rrdcached'] . ')';
                        // Remote RRDcached require version 1.5.5
                        $versions['rrdtool_old'] = version_compare($versions['rrdtool_version'], '1.5.5', '<');
                    } else {
                        $rrdcached_exec = str_replace('rrdtool', 'rrdcached', $GLOBALS['config']['rrdtool']);
                        if (!is_executable($rrdcached_exec)) {
                            $rrdcached_exec = '/usr/bin/env rrdcached -h';
                        }
                        [, $versions['rrdcached_version']] = explode(' ', external_exec($rrdcached_exec . ' -h | head -n1'));
                        $rrdtool_version .= ' (rrdcached ' . $versions['rrdcached_version'] . ': ' . $GLOBALS['config']['rrdcached'] . ')';
                    }
                }

                if (empty($rrdtool_version)) {
                    $rrdtool_version             = 'not found';
                    $versions['rrdtool_version'] = $rrdtool_version;
                    $versions['rrdtool_old']     = TRUE;
                }
                $versions['rrdtool_text'] = $rrdtool_version;
                break;

            case 'fping':
                /** Fping
                 * I.e.:
                 * fping_version = 3.13
                 * fping_text    = 3.13 (IPv4 and IPv6)
                 */
                $fping_version = 'not found';
                $fping_exec    = is_executable($GLOBALS['config']['fping']) ? $GLOBALS['config']['fping'] : '/usr/bin/env fping';
                $fping         = external_exec($fping_exec . " -v 2>&1");
                if (preg_match('/Version\s+(\d\S+)/', $fping, $matches)) {
                    $fping_version = $matches[1];
                    $fping_text    = $fping_version;

                    if (version_compare($fping_version, '4.0', '>=')) {
                        $fping_text .= ' (IPv4 and IPv6)';
                    } elseif (is_executable($GLOBALS['config']['fping6'])) {
                        $fping_text .= ' (IPv4 and IPv6)';
                    } else {
                        $fping_text .= ' (IPv4 only)';
                    }
                }
                $versions['fping_version'] = $fping_version;
                $versions['fping_text']    = $fping_text;
                if ($return_version) {
                    return $versions['fping_version'];
                }
                break;

            case 'http':
                // Apache (or any http used?)
                if (is_cli()) {
                    foreach (['apache2', 'httpd'] as $http_cmd) {
                        if (is_executable('/usr/sbin/' . $http_cmd)) {
                            $http_cmd = '/usr/sbin/' . $http_cmd;
                        } else {
                            $http_cmd = '/usr/bin/env ' . $http_cmd;
                        }
                        $http_version = external_exec($http_cmd . ' -v | awk \'/Server version:/ {print $3}\'');

                        if ($http_version) {
                            break;
                        }
                    }
                    if (empty($http_version)) {
                        $http_version = 'not found';
                    }
                    $versions['http_full'] = $http_version;
                } else {
                    $versions['http_full'] = $_SERVER['SERVER_SOFTWARE'];
                }
                $versions['http_version'] = str_replace('Apache/', '', $versions['http_full']);
                $versions['http_text']    = $versions['http_version'];
                if (str_contains($versions['http_version'], ' (')) {
                    // Remove extra info from httpd version string: 2.4.52 (Ubuntu)
                    $versions['http_version'] = explode(' (', $versions['http_version'])[0];
                }
                if ($return_version) {
                    return $versions['http_version'];
                }
                break;

            case 'curl':
                //case 'fetch':
                // cURL or fetch library
                if (function_exists('curl_version')) {
                    $curl_version = curl_version();
                    //print_vars($curl_version);

                    $versions['curl_version'] = $curl_version['version'];
                    // Do not use curl when https not supported
                    $curl_https            = in_array('https', $curl_version['protocols']);
                    $versions['curl_old']  = version_compare($versions['curl_version'], '7.16.2', '<') || !$curl_https;
                    $versions['curl_text'] = 'cURL ' . $curl_version['version'];
                    $curl_extra            = [];
                    if (!$curl_https) {
                        $curl_extra[] = 'NO https';
                    }
                    if ($curl_version['ssl_version']) {
                        // OpenSSL/1.1.1f
                        $curl_extra[] = $curl_version['ssl_version'];
                    }
                    if ($curl_version['libz_version']) {
                        // 1.2.11
                        $curl_extra[] = 'LibZ ' . $curl_version['libz_version'];
                    }
                    if ($curl_version['libidn']) {
                        // 2.3.0
                        $curl_extra[] = 'LibIDN ' . $curl_version['libidn'];
                    }
                    // if ($curl_version['brotli_version']) {
                    //   // 1.0.9
                    //   $curl_extra[] = 'Brotli ' . $curl_version['brotli_version'];
                    // }
                    // if ($curl_version['libssh_version']) {
                    //   // libssh/0.9.3/openssl/zlib
                    //   $curl_extra[] = $curl_version['libssh_version'];
                    // }
                    if ($curl_extra) {
                        $versions['curl_text'] .= ' (' . implode(', ', $curl_extra) . ')';
                    }
                } else {
                    $versions['curl_version'] = '-1';
                    $versions['curl_old']     = TRUE;
                    $versions['curl_text']    = 'PHP fetch';
                }
                if ($return_version) {
                    return $versions['curl_version'];
                }
                break;

            case 'svn':
                $svn_version = '';
                $svn_text    = 'not found';
                $svn_exec    = is_executable($GLOBALS['config']['svn']) ? $GLOBALS['config']['svn'] : '/usr/bin/env svn';
                $svn         = external_exec($svn_exec . " -q --version");
                if (preg_match('/^(\d\S+)/', $svn, $matches)) {
                    $svn_version = $matches[1];
                    $svn_text    = $svn_version;

                    if (version_compare($svn_version, '1.7', '<')) {
                        $svn_text .= ' (old)';
                        $versions['svn_old'] = TRUE;
                    }
                }
                $versions['svn_version'] = $svn_version;
                $versions['svn_text']    = $svn_text;
                if ($return_version) {
                    return $versions['svn_version'];
                }
                break;
        }
    }

    // Cache for current execution
    mem_cache_set('versions', $versions);
    //print_vars($versions);

    return $versions;
}

/**
 * Print version information about used Observium and additional software.
 *
 * @return NULL
 */
function print_versions() {

    $observium_date  = format_unixtime(strtotime(OBSERVIUM_DATE), 'jS F Y');

    $versions        = get_versions();
    $os_version      = $versions['os_text'];
    $php_version     = $versions['php_text'];
    $python_version  = $versions['python_text'];
    $mysql_version   = $versions['mysql_text'];
    $mysql_name      = $versions['mysql_name'];
    $snmp_version    = $versions['snmp_text'];
    $rrdtool_version = $versions['rrdtool_text'];
    $fping_version   = $versions['fping_text'];
    $http_version    = $versions['http_text'];
    $curl_version    = $versions['curl_text'];

    // PHP info
    $php_opcache           = $versions['php_opcache'];
    $php_memory_limit      = $versions['php_memory_limit'];
    $php_memory_limit_text = $versions['php_memory_limit_text'];
    $php_math              = $versions['php_math_text'];

    if (is_cli()) {
        $timezone = get_timezone();
        //print_vars($timezone);

        $mysql_mode    = dbFetchCell("SELECT @@SESSION.sql_mode;");
        $mysql_binlog  = dbShowVariables("LIKE 'log_bin'");
        $mysql_charset = dbShowVariables("LIKE 'character_set_connection'");

        if ($versions['php_old']) {
            $php_version = '%r' . $php_version;
        }
        if ($versions['python_old']) {
            $python_version = '%r' . $python_version;
        }
        if ($versions['mysql_old']) {
            $mysql_version = '%r' . $mysql_version;
        }
        if ($versions['rrdtool_old']) {
            $rrdtool_version = '%r' . $rrdtool_version;
        }
        if ($versions['curl_old']) {
            $curl_version = '%r' . $curl_version;
        }

        echo PHP_EOL;
        print_cli_heading("Observium");
        print_cli_data("Version", OBSERVIUM_VERSION);
        if (OBSERVIUM_EDITION !== 'community') {
            print_cli_data("Train", OBSERVIUM_TRAIN);
        }
        print_cli_data("Released", $observium_date);
        if (OBSERVIUM_EDITION !== 'community' && OBSERVIUM_USER) {
            print_cli_data("Subscription User", OBSERVIUM_USER);
            // FIXME. Need way for get subscription level and date end of subscription.
        }

        echo PHP_EOL;
        print_cli_heading("Software versions");
        print_cli_data("OS", $os_version);
        if ($versions['os_virt']) {
            print_cli_data("VM", $versions['os_virt_name']);
        }
        print_cli_data("Apache", $http_version);
        print_cli_data("PHP", $php_version);
        print_cli_data("Python", $python_version);
        print_cli_data($mysql_name, $mysql_version);
        print_cli_data("SNMP", $snmp_version);
        print_cli_data("RRDtool", $rrdtool_version);
        print_cli_data("Fping", $fping_version);
        print_cli_data("Fetch", $curl_version);

        // Additionally, in CLI always display Memory Limit, MySQL Mode and Charset info

        echo PHP_EOL;
        print_cli_heading("PHP info", 3);
        print_cli_data("OPcache", (!$php_opcache ? '%rDISABLED' : 'ENABLED'), 3);
        print_cli_data("Memory Limit", ($php_memory_limit >= 0 && $php_memory_limit < 268435456 ? '%r' : '') . $php_memory_limit_text, 3);
        print_cli_data("Math", ($php_math === 'NATIVE' ? '%r' : '') . $php_math, 3);

        echo PHP_EOL;
        print_cli_heading("DB info", 3);
        print_cli_data("DB schema", get_db_version(), 3);
        print_cli_data("$mysql_name binlog", ($mysql_binlog['log_bin'] === 'ON' ? '%r' : '') . $mysql_binlog['log_bin'], 3);
        print_cli_data("$mysql_name mode", $mysql_mode, 3);

        echo PHP_EOL;
        print_cli_heading("Charset info", 3);
        print_cli_data("PHP", ini_get("default_charset"), 3);
        print_cli_data($mysql_name, $mysql_charset['character_set_connection'], 3);

        echo PHP_EOL;
        print_cli_heading("Timezones info", 3);
        print_cli_data("Date", date("l, d-M-y H:i:s T"), 3);
        print_cli_data("PHP", $timezone['php'], 3);
        print_cli_data($mysql_name, ($timezone['diff'] !== 0 ? '%r' : '') . $timezone['mysql'], 3);

        if (OBS_DISTRIBUTED) {
            $poller_id = $GLOBALS['config']['poller_id'];
            if ($poller_id !== 0 && empty($GLOBALS['config']['poller_name'])) {
                // poller name not set by config
                $poller = get_poller($poller_id);
                $poller_name = $poller['poller_name'];
            } else {
                $poller_name = $poller_id !== 0 ? $GLOBALS['config']['poller_name'] : 'Main';
            }
            echo PHP_EOL;
            print_cli_heading("Poller info", 3);
            print_cli_data("ID", $poller_id, 3);
            print_cli_data("Name", $poller_name, 3);

            // Show distributed pollers versions
            if ($poller_id === 0) {
                echo PHP_EOL;
                print_cli_heading("Remote Pollers Versions");
                foreach (dbFetchRows('SELECT `poller_id`, `poller_name`, `poller_version` FROM `pollers` WHERE `poller_version` IS NOT NULL') as $poller) {
                    print_cli_data("ID ".$poller['poller_id']." ({$poller['poller_name']})", $poller['poller_version']);
                }
            }
        }
        echo PHP_EOL;

    } else {

        $php_options = $versions['php_fastcgi'] ? 'FastCGI; ' : '';
        $php_options .= 'OPcache: ';
        if ($php_opcache) {
            $php_options .= '<span class="text-success">Yes</span>';
        } else {
            $php_options .= '<span class="text-danger">No</span>';
        }
        $php_options .= '; FastCache: ';
        // and fastcache (for wui only)
        if (function_exists('get_cache_stats')) {
            $phpfastcache = get_cache_stats();
            if ($phpfastcache['enabled']) {
                $php_options .= '<span class="text-success">Yes</span>, ' . $phpfastcache['driver'] . ', ' . format_bytes($phpfastcache['size']);
            } else {
                $php_options .= '<span class="text-danger">No</span>';
            }
        } else {
            $php_options .= '<span class="text-danger">No</span>';
        }
        $php_options = " ($php_options)";

        if ($php_memory_limit >= 0 && $php_memory_limit < 268435456) {
            $php_memory_limit_text = '<span class="text-danger">' . $php_memory_limit_text . '</span>';
        } else {
            $php_memory_limit_text = '<span class="text-success">' . $php_memory_limit_text . '</span>';
        }
        $php_options .= " (Memory: $php_memory_limit_text)";

        // Check minimum versions
        if ($versions['php_old']) {
            $php_class   = 'error';
            $php_version = generate_tooltip_link(NULL, $php_version, 'Minimum supported: ' . OBS_MIN_PHP_VERSION);
        } else {
            $php_class   = '';
            $php_version = escape_html($php_version);
        }
        if ($versions['python_old']) {
            $python_class = 'warning';
            if (str_starts_with($python_version, '2.')) {
                $python_version = generate_tooltip_link(NULL, $python_version, 'Recommended version is greater than or equal to: ' . OBS_MIN_PYTHON2_VERSION . ' or ' . OBS_MIN_PYTHON3_VERSION);
            } else {
                $python_version = generate_tooltip_link(NULL, $python_version, 'Recommended version is greater than or equal to: ' . OBS_MIN_PYTHON3_VERSION);
            }
        } else {
            $python_class   = '';
            $python_version = escape_html($python_version);
        }
        if ($versions['mysql_old']) {
            $mysql_class = 'warning';
            if ($mysql_name === 'MariaDB') {
                $mysql_version = generate_tooltip_link(NULL, $mysql_version, 'Recommended version is greater than or equal to: ' . OBS_MIN_MARIADB_VERSION);
            } else {
                $mysql_version = generate_tooltip_link(NULL, $mysql_version, 'Recommended version is greater than or equal to: ' . OBS_MIN_MYSQL_VERSION);
            }
        } else {
            $mysql_class   = '';
            $mysql_version = escape_html($mysql_version);
        }
        if ($versions['rrdtool_old']) {
            $rrdtool_class   = 'error';
            $rrdtool_version = generate_tooltip_link(NULL, $rrdtool_version, 'Minimum supported: ' . OBS_MIN_RRD_VERSION);
        } else {
            $rrdtool_class   = '';
            $rrdtool_version = escape_html($rrdtool_version);
        }
        if ($versions['curl_old']) {
            $curl_class = 'error';
            if ($versions['curl_version'] == '-1') {
                $curl_tooltip = 'cURL module not installed';
            } else {
                $curl_tooltip = 'cURL module too old. Minimum supported: 7.16.2';
            }
            $curl_version = generate_tooltip_link(NULL, $curl_version, $curl_tooltip);
        } else {
            $curl_class   = '';
            $curl_version = escape_html($curl_version);
        }

        $vm_line = '';
        if ($versions['os_virt']) {
            $vm_line .= '<tr><td><b>VM</b></td><td>' . escape_html($versions['os_virt_name']) . '</td></tr>';
        }
        echo generate_box_open(['title' => 'Version Information']);
        echo '
        <table class="table table-striped table-condensed-more">
          <tbody>
            <tr><td><b>' . escape_html(OBSERVIUM_PRODUCT) . '</b></td><td>' . escape_html(OBSERVIUM_VERSION_LONG) . ' (' . escape_html($observium_date) . ')</td></tr>
            <tr><td><b>OS</b></td><td>' . escape_html($os_version) . '</td></tr>
            ' . $vm_line . '
            <tr><td><b>Apache</b></td><td>' . escape_html($http_version) . '</td></tr>
            <tr class="' . $php_class . '"><td><b>PHP</b></td><td>' . $php_version . $php_options . '</td></tr>
            <tr class="' . $python_class . '"><td><b>Python</b></td><td>' . $python_version . '</td></tr>
            <tr class="' . $mysql_class . '"><td><b>' . $mysql_name . '</b></td><td>' . $mysql_version . '</td></tr>
            <tr><td><b>SNMP</b></td><td>' . escape_html($snmp_version) . '</td></tr>
            <tr class="' . $rrdtool_class . '"><td><b>RRDtool</b></td><td>' . $rrdtool_version . '</td></tr>
            <tr><td><b>Fping</b></td><td>' . escape_html($fping_version) . '</td></tr>
            <tr class="' . $curl_class . '"><td><b>Fetch</b></td><td>' . $curl_version . '</td></tr>
          </tbody>
        </table>' . PHP_EOL;
        echo generate_box_close();
    }
    //r(mem_cache_stat());
}

/**
 * Convert SNMP timeticks string into seconds
 *
 * SNMP timeticks can be in two different normal formats:
 *  - "(2105)"       == 21.05 sec
 *  - "0:0:00:21.05" == 21.05 sec
 * Sometime devices return wrong type or numeric instead timetick:
 *  - "Wrong Type (should be Timeticks): 1632295600" == 16322956 sec
 *  - "1546241903" == 15462419.03 sec
 * Parse the timeticks string and convert it to seconds.
 *
 * @param string $timetick
 * @param bool   $float - Return a float with microseconds
 *
 * @return int|float
 */
function timeticks_to_sec($timetick, $float = FALSE)
{
    if (str_contains($timetick, 'Wrong Type')) {
        // Wrong Type (should be Timeticks): 1632295600
        [, $timetick] = explode(': ', $timetick, 2);
    }

    $timetick = trim($timetick, " \t\n\r\0\x0B\"()"); // Clean string
    if (is_numeric($timetick)) {
        // When "Wrong Type" or timetick as an integer, than time with count of ten millisecond ticks
        $time = $timetick / 100;
        return ($float ? (float)$time : (int)$time);
    }
    if (!preg_match('/^[\d\.: ]+$/', $timetick)) {
        return FALSE;
    }

    $timetick_array = explode(':', $timetick);
    if (count($timetick_array) == 1 && is_numeric($timetick)) {
        $secs      = $timetick;
        $microsecs = 0;
    } else {
        //list($days, $hours, $mins, $secs) = $timetick_array;
        $secs  = array_pop($timetick_array);
        $mins  = array_pop($timetick_array);
        $hours = array_pop($timetick_array);
        $days  = array_pop($timetick_array);
        [$secs, $microsecs] = explode('.', $secs);

        $hours += $days * 24;
        $mins  += $hours * 60;
        $secs  += $mins * 60;

        // Sometime used non standard years counter
        if (count($timetick_array)) {
            $years = array_pop($timetick_array);
            $secs  += $years * 31536000; // 365 * 24 * 60 * 60;
        }
        //print_vars($timetick_array);
    }
    $time = ($float ? (float)$secs + $microsecs / 100 : (int)$secs);
    print_debug("Timeticks converted $timetick -> $time");

    return $time;
}

/**
 * Convert SNMP DateAndTime string into unixtime
 *
 * field octets contents range
 * ----- ------ -------- -----
 * 1 1-2 year 0..65536
 * 2 3 month 1..12
 * 3 4 day 1..31
 * 4 5 hour 0..23
 * 5 6 minutes 0..59
 * 6 7 seconds 0..60
 * (use 60 for leap-second)
 * 7 8 deci-seconds 0..9
 * 8 9 direction from UTC '+' / '-'
 * 9 10 hours from UTC 0..11
 * 10 11 minutes from UTC 0..59
 *
 * For example, Tuesday May 26, 1992 at 1:30:15 PM EDT would be displayed as:
 * 1992-5-26,13:30:15.0,-4:0
 *
 * Note that if only local time is known, then timezone information (fields 8-10) is not present.
 *
 * @param string  $datetime DateAndTime string
 * @param boolean $use_gmt  Return unixtime converted to GMT or Local (by default)
 *
 * @return integer Unixtime
 */
function datetime_to_unixtime($datetime, $use_gmt = FALSE)
{
    $timezone = get_timezone();

    $datetime = trim($datetime);
    if (preg_match('/(?<year>\d+)-(?<mon>\d+)-(?<day>\d+)(?:,(?<hour>\d+):(?<min>\d+):(?<sec>\d+)(?<millisec>\.\d+)?(?:,(?<tzs>[+\-]?)(?<tzh>\d+):(?<tzm>\d+))?)/', $datetime, $matches)) {
        if (isset($matches['tzh'])) {
            // Use TZ offset from datetime string
            $offset = $matches['tzs'] . ($matches['tzh'] * 3600 + $matches['tzm'] * 60); // Offset from GMT in seconds
        } else {
            // Or use system offset
            $offset = $timezone['php_offset'];
        }
        $time_tmp = mktime($matches['hour'], $matches['min'], $matches['sec'], $matches['mon'], $matches['day'], $matches['year']); // Generate unixtime

        $time_gmt   = $time_tmp + ($offset * -1);            // Unixtime from string in GMT
        $time_local = $time_gmt + $timezone['php_offset'];   // Unixtime from string in local timezone
    } else {
        $time_local = time();                                // Current unixtime with local timezone
        $time_gmt   = $time_local - $timezone['php_offset']; // Current unixtime in GMT
    }

    if (OBS_DEBUG > 1) {
        $debug_msg = 'UNIXTIME from DATETIME "' . ($time_tmp ? $datetime : 'time()') . '": ';
        $debug_msg .= 'LOCAL (' . format_unixtime($time_local) . '), GMT (' . format_unixtime($time_gmt) . '), TZ (' . $timezone['php'] . ')';
        print_message($debug_msg);
    }

    if ($use_gmt) {
        return ($time_gmt);
    }

    return ($time_local);
}

/**
 * Format seconds to requested time format.
 *
 * Default format is "long".
 *
 * Supported formats:
 *   long    => '1 year, 1 day, 1h 1m 1s'
 *   longest => '1 year, 1 day, 1 hour 1 minute 1 second'
 *   short-3 => '1y 1d 1h'
 *   short-2 => '1y 1d'
 *   shorter => *same as short-2 above
 *   (else)  => '1y 1d 1h 1m 1s'
 *
 * @param mixed  $uptime Time is seconds
 * @param string $format Optional format
 *
 * @return string
 */
function format_uptime($uptime, $format = "long") {

    $uptime = clean_number($uptime);
    if (!is_numeric($uptime)) {
        print_debug("Passed incorrect value to " . __FUNCTION__ . "()");
        print_debug_vars($uptime);
        //return FALSE;
        return '0s'; // incorrect, but for keep compatibility
    }

    $uptime = (int)round($uptime);
    if ($uptime <= 0) {
        return '0s';
    }

    $up['y'] = floor($uptime / 31536000);
    $up['d'] = floor($uptime % 31536000 / 86400);
    $up['h'] = floor($uptime % 86400 / 3600);
    $up['m'] = floor($uptime % 3600 / 60);
    $up['s'] = floor($uptime % 60);

    $result = '';

    if (str_starts_with($format, 'long')) {
        if ($up['y'] > 0) {
            $result .= $up['y'] . ' year' . ($up['y'] != 1 ? 's' : '');
            if ($up['d'] > 0 || $up['h'] > 0 || $up['m'] > 0 || $up['s'] > 0) {
                $result .= ', ';
            }
        }

        if ($up['d'] > 0) {
            $result .= $up['d'] . ' day' . ($up['d'] != 1 ? 's' : '');
            if ($up['h'] > 0 || $up['m'] > 0 || $up['s'] > 0) {
                $result .= ', ';
            }
        }

        if ($format === 'longest') {
            if ($up['h'] > 0) {
                $result .= $up['h'] . ' hour' . ($up['h'] != 1 ? 's ' : ' ');
            }
            if ($up['m'] > 0) {
                $result .= $up['m'] . ' minute' . ($up['m'] != 1 ? 's ' : ' ');
            }
            if ($up['s'] > 0) {
                $result .= $up['s'] . ' second' . ($up['s'] != 1 ? 's ' : ' ');
            }
        } else {
            if ($up['h'] > 0) {
                $result .= $up['h'] . 'h ';
            }
            if ($up['m'] > 0) {
                $result .= $up['m'] . 'm ';
            }
            if ($up['s'] > 0) {
                $result .= $up['s'] . 's ';
            }
        }
    } else {
        $count = $format === 'shorter' ? 2 : 6;
        if (str_starts_with($format, 'short-')) {
            // short-2 => 2, short-3 => 3 and up to 6
            $tmp = explode('-', $format, 2)[1];
            if (is_numeric($tmp) && $tmp >= 1 && $tmp <= 6) {
                $count = (int)$tmp;
            }
        }

        foreach ($up as $period => $value) {
            if ($value == 0) {
                continue;
            }

            $result .= $value . $period . ' ';
            $count--;
            if ($count == 0) {
                break;
            }
        }
    }

    return trim($result);
}

/**
 * Get current timezones for mysql and php.
 * Use this function when need display timedate from mysql
 * for fix diffs between this timezones
 *
 * Example:
 * Array
 * (
 *  [mysql] => +03:00
 *  [php] => +03:00
 *  [php_abbr] => MSK
 *  [php_offset] => +10800
 *  [mysql_offset] => +10800
 *  [diff] => 0
 * )
 *
 * @param bool $refresh Refresh timezones
 *
 * @return array Timezones info
 */
function get_timezone($refresh = FALSE) {

    if ($refresh || !mem_cache_exists('timezone')) {
        $timezone = [];
        // OS timezone
        if ($refresh) {
            // Call to external exec only when refresh (basically it's not required)
            $timezone['system'] = preg_replace('/(\d\d$)/', ':$1', external_exec('date "+%z"')); // return '+0300' and convert to '+03:00'
        }

        // PHP timezone
        [ $timezone['php'], $timezone['php_abbr'], $timezone['php_name'], $timezone['php_daylight'] ] = explode('|', date('P|T|e|I'));
        //$timezone['php']         = date('P');                                       // return '+03:00'
        //$timezone['php_abbr']    = date('T');                                       // return 'MSK'
        //$timezone['php_name']    = date('e');                                       // return 'Europe/Moscow'
        //$timezone['php_daylight'] = date('I');                                      // return '0'
        // Offset from GMT in seconds
        $sign = $timezone['php'][0];
        [ $hours, $minutes ] = explode(':', $timezone['php']);
        $timezone['php_offset'] = $sign . (abs($hours) * 3600 + $minutes * 60);       // return +10800

        // If mysql skipped, just return system/php timezones without caching
        if (db_skip()) {
            return $timezone;
        }

        // DB timezone
        $timezone['mysql'] = dbFetchCell('SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP);'); // return '03:00:00'
        if ($timezone['mysql'][0] !== '-') {
            $timezone['mysql'] = '+' . $timezone['mysql'];
        }
        $timezone['mysql'] = preg_replace('/:00$/', '', $timezone['mysql']);        // convert to '+03:00'
        // Offset from GMT in seconds
        $sign = $timezone['mysql'][0];
        [ $hours, $minutes ] = explode(':', $timezone['mysql']);
        $timezone['mysql_offset'] = $sign . (abs($hours) * 3600 + $minutes * 60);   // return +10800

        // Get the difference in sec between mysql and php timezones
        $timezone['diff']  = (int)$timezone['mysql_offset'] - (int)$timezone['php_offset'];

        mem_cache_set('timezone', $timezone);
        return $timezone;
    }

    return mem_cache_get('timezone');
}

/**
 * Generate an associative array of all available timezones, sorted by UTC offset.
 *
 * @param bool $refresh Force cache refresh.
 * @return array Associative array: timezone identifier => [ 'descr' => ..., 'offset' => ... ]
 */
function generate_timezone_list($refresh = FALSE) {

    if ($refresh || !mem_cache_exists('timezone_list')) {
        $timezone_offsets = [];
        $now = new DateTime('now');

        foreach (timezone_identifiers_list() as $timezone) {
            $tz = new DateTimeZone($timezone);
            // Use current time in timezone for the correct offset (accounts for DST)
            $timezone_offsets[$timezone] = $tz->getOffset($now);
        }

        // Sort by offset, then by name for stability
        asort($timezone_offsets, SORT_NUMERIC);

        $timezone_list = [];
        foreach ($timezone_offsets as $timezone => $offset) {
            $sign = ($offset < 0) ? '-' : '+';
            $abs_offset = abs($offset);
            // Offset is in seconds, convert to H:i
            $hours = floor($abs_offset / 3600);
            $minutes = floor(($abs_offset % 3600) / 60);
            $offset_formatted = sprintf('%02d:%02d', $hours, $minutes);

            $pretty_offset = "UTC{$sign}{$offset_formatted}";
            $descr = "($pretty_offset) $timezone";
            $timezone_list[$timezone] = [ 'descr' => $descr, 'offset' => $offset ];
        }

        mem_cache_set('timezone_list', $timezone_list);
        return $timezone_list;
    }

    return mem_cache_get('timezone_list');
}

/**
 * Convert common MAC strings to fixed 12 char string
 *
 * @param string $mac MAC string (ie: 66:c:9b:1b:62:7e, 00 02 99 09 E9 84)
 *
 * @return string      Cleaned MAC string (ie: 00029909e984)
 */
function mac_zeropad($mac) {
    $mac = strtolower(trim($mac));
    if (str_contains($mac, ':')) {
        // STRING: 66:c:9b:1b:62:7e
        $mac_parts = explode(':', $mac);
        if (count($mac_parts) === 6) {
            $mac = '';
            foreach ($mac_parts as $part) {
                $mac .= zeropad($part);
            }
        } else {
            // stringified
            // 30:30:2d:30:36:2d:33:39:2d:30:41:2d:35:46:2d:36:38 -> STRING: "00-06-39-0A-5F-68"
            $hex_mac = str_replace(':', ' ', $mac);
            $str_mac = snmp_hexstring($hex_mac);
            if ($str_mac && $str_mac !== $hex_mac) {
                return mac_zeropad($str_mac);
            }
        }
    } else {
        if (str_contains($mac, ' ') && strlen($mac) >= 35) { // 12 * 3 - 1
            // stringified
            // 30 30 2d 30 36 2d 33 39 2d 30 41 2d 35 46 2d 36 38 -> STRING: "00-06-39-0A-5F-68"
            $str_mac = snmp_hexstring($mac);
            if ($str_mac && $str_mac !== $mac) {
                return mac_zeropad($str_mac);
            }
        }

        // Hex-STRING: 00 02 99 09 E9 84
        // Cisco MAC:  1234.5678.9abc
        // Other Vendors: 00-0B-DC-00-68-AF
        // Some other: 0x123456789ABC
        $mac = str_replace([ ' ', '.', '-', '0x' ], '', $mac);
    }

    if (strlen($mac) === 12 && ctype_xdigit($mac)) {
        return $mac;
    }

    // Strip out non-hex digits (Not sure when this required, copied for compat with old format_mac())
    $mac = preg_replace('/[[:^xdigit:]]/', '', $mac);
    return (strlen($mac) === 12) ? $mac : NULL;
}

/**
 * Formats a MAC address string with the specified delimiter.
 *
 * @param string $mac        MAC address string in any known format.
 * @param string $split_char Allowed delimiters for specific formats: ':', '', ' ', '0x'.
 *
 * @return string The formatted MAC address string.
 */
function format_mac($mac, $split_char = ':') {
    // Clean MAC string
    $mac = mac_zeropad($mac);

    if (empty($mac)) {
        return '';
    }

    // Add colons
    $mac = preg_replace('/([[:xdigit:]]{2})(?!$)/', '$1:', $mac);

    // Convert fake MACs to IP
    //if (preg_match('/ff:fe:([[:xdigit:]]+):([[:xdigit:]]+):([[:xdigit:]]+):([[:xdigit:]]{1,2})/', $mac, $matches))
    if (preg_match('/ff:fe:([[:xdigit:]]{2}):([[:xdigit:]]{2}):([[:xdigit:]]{2}):([[:xdigit:]]{2})/', $mac, $matches)) {
        if ($matches[1] == '00' && $matches[2] == '00') {
            $mac = hexdec($matches[3]) . '.' . hexdec($matches[4]) . '.X.X'; // Cisco, why you convert 192.88.99.1 to 0:0:c0:58 (should be c0:58:63:1)
        } else {
            $mac = hexdec($matches[1]) . '.' . hexdec($matches[2]) . '.' . hexdec($matches[3]) . '.' . hexdec($matches[4]);
        }
    }

    if ($split_char === '') {
        $mac = str_replace(':', $split_char, $mac);
    } elseif ($split_char === ' ') {
        $mac = strtoupper(str_replace(':', $split_char, $mac));
    } elseif ($split_char === '.') {
        // Cisco like format
        $parts = explode(':', $mac, 6);
        $mac   = $parts[0] . $parts[1] . '.' . $parts[2] . $parts[3] . '.' . $parts[4] . $parts[5];
    } elseif ($split_char === '0x') {
        $mac = '0x' . strtoupper(str_replace(':', '', $mac));
    }

    return $mac;
}

/**
 * Get MAC address vendor from OUI database
 *
 * @param string $mac MAC address in any format
 * @return string Vendor name or 'Unknown' if not found
 */
function get_mac_vendor($mac) {
    global $config;

    // Clean MAC and get first 6 hex digits (OUI)
    $mac_clean = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac)); // Can use mac_zeropad() here, but it's not required
    if (strlen($mac_clean) < 6) {
        return 'Unknown';
    }

    $oui = substr($mac_clean, 0, 6);

    // Load OUI data only when we have a MAC to lookup and haven't loaded it yet
    if (!isset($config['oui']) || !is_array($config['oui'])) {
        include_once($config['install_dir'] . '/includes/definitions/oui.inc.php');
    }

    return $config['oui'][$oui] ?? 'Unknown';
}

/**
 * Get MAC address vendors for multiple MACs efficiently
 *
 * @param array $macs Array of MAC addresses
 * @return array Associative array of MAC => Vendor
 */
function get_mac_vendors_bulk($macs) {
    global $config;

    if (empty($macs)) {
        return [];
    }

    // Load OUI data once
    if (!isset($config['oui']) || !is_array($config['oui'])) {
        include_once($config['install_dir'] . '/includes/definitions/oui.inc.php');
    }

    $vendors = [];
    foreach ($macs as $mac) {
        $mac_clean = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac)); // Can use mac_zeropad() here, but it's not required
        if (strlen($mac_clean) >= 6) {
            $oui = substr($mac_clean, 0, 6);
            $vendors[$mac] = $config['oui'][$oui] ?? 'Unknown';
        } else {
            $vendors[$mac] = 'Unknown';
        }
    }

    return $vendors;
}

/**
 * Checks if the required exec functions are available.
 *
 * @return bool TRUE if proc_open and proc_get_status are available and not disabled, FALSE otherwise.
 */
function is_exec_available()
{
    // Check if ini_get is not disabled
    if (!function_exists('ini_get')) {
        print_warning('WARNING: Function ini_get() is disabled via the `disable_functions` option in php.ini configuration file. Please clean this function from this list.');

        return TRUE; // NOTE, this is not a critical function for functionally
    }

    $required_functions = ['proc_open', 'proc_get_status'];
    $disabled_functions = explode(',', ini_get('disable_functions'));
    foreach ($required_functions as $func) {
        if (in_array($func, $disabled_functions)) {
            print_error('ERROR: Function ' . $func . '() is disabled via the `disable_functions` option in php.ini configuration file. Please clean this function from this list.');
            return FALSE;
        }
    }

    return TRUE;
}

function process_os_group_def(&$config) {
    foreach ($config['os'] as $this_os => $os_data) {
        // Merge group properties into OS definitions, including extra groups
        if (isset($os_data['group'])) {
            $os_groups = (array)$os_data['group'];

            if (isset($os_data['groups'])) {
                $os_groups = array_merge($os_groups, (array)$os_data['groups']);
            }
            foreach ($os_groups as $this_os_group) {
                if (!isset($config['os_group'][$this_os_group])) {
                    continue;
                }

                foreach ($config['os_group'][$this_os_group] as $property => $value) {

                    // Skip properties handled separately
                    // use get_device_mibs(), get_device_mibs_blacklist(), check_main_module() and check_submodule()
                    if (in_array($property, [ 'mibs', 'mib_blacklist', 'modules' ], TRUE)) {
                        continue;
                    }

                    if (!isset($config['os'][$this_os][$property])) {
                        $config['os'][$this_os][$property] = $value;
                    } elseif (is_array($value) &&
                              in_array($property, [ 'sysDescr_regex', 'port_label', 'syslog_msg', 'syslog_program', 'comments' ], TRUE)) {
                        // Merge arrays instead of overriding
                        $config['os'][$this_os][$property] = array_merge((array)$config['os'][$this_os][$property], $value);
                    }
                }
            }
        }

        if (isset($os_data['snmpable'])) {
            // Add all 'snmpable' to generic
            $config['os']['generic']['snmpable'] = array_merge($config['os']['generic']['snmpable'], (array)$os_data['snmpable']);
        }
    }
    $config['os']['generic']['snmpable'] = array_unique($config['os']['generic']['snmpable']);
    if (empty($config['os']['generic']['snmpable'])) {
        unset($config['os']['generic']['snmpable']);
    }
}

/**
 * Opens pipes for a command, sets non-blocking mode for stdout and stderr, and returns the process resource.
 *
 * @param string      $command The command to be executed.
 * @param array       $pipes   An array that will be filled with pipe resources.
 * @param string|null $cwd     Optional. The working directory for the command. Defaults to NULL, which uses the current working directory.
 * @param array       $env     Optional. An array of environment variables for the command. Defaults to an empty array.
 *
 * @return resource|false The process resource on success, or false on failure.
 */
function pipe_open($command, &$pipes, $cwd = NULL, $env = [])
{
    $descriptorspec = [
      0 => ['pipe', 'r'],  // stdin
      1 => ['pipe', 'w'],  // stdout
      2 => ['pipe', 'w']   // stderr
    ];

    $process = proc_open($command, $descriptorspec, $pipes, $cwd, $env);

    if (is_resource($process)) {
        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
    }

    return $process;
}

/**
 * Reads the output of a command executed through pipes.
 *
 * @param string $command  The command to be executed.
 * @param array  $pipes    An array of pipe resources.
 * @param bool   $fullread Optional. Determines if the entire output should be read. Default is true.
 *
 * @return string The output of the command with the last end-of-line character removed.
 */
function pipe_read($command, &$pipes, $fullread = TRUE)
{
    // $pipes like this:
    // 0 => writeable handle connected to child stdin
    // 1 => readable handle connected to child stdout
    // Any error output will be appended to /tmp/error-output.txt

    fwrite($pipes[0], $command);
    fclose($pipes[0]);

    if ($fullread) {
        // Read while not end of file
        $stdout = '';
        while (!feof($pipes[1])) {
            $stdout .= fgets($pipes[1]);
        }
    } else {
        // Output not matter, for rrdtool
        $iter   = 0;
        $line   = '';
        $stdout = '';
        while (strlen($line) < 1 && $iter < 1000) {
            // wait for 10 milliseconds to loosen loop (max 1s)
            usleep(1000);
            $line   = fgets($pipes[1], 1024);
            $stdout .= $line;
            $iter++;
        }
    }

    return preg_replace('/(?:\n|\r\n|\r)$/D', '', $stdout); // remove last (only) eol
}

/**
 * Closes the pipes and the process.
 *
 * @param resource $process The process resource.
 * @param array    $pipes   An array of pipe resources.
 *
 * @return int The termination status of the process that was run.
 */
function pipe_close($process, &$pipes)
{
    // Close each pipe resource if it's valid
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    // Close pipes before proc_close() to avoid deadlock

    return proc_close($process);
}

/**
 * Execute an external command and return its stdout, with execution details stored in a reference parameter.
 *
 * @param string  $command     The command to execute.
 * @param array  &$exec_status Reference parameter to store execution details (stdout, stderr, exitcode, runtime).
 * @param int     $timeout     The timeout for the command in seconds. Set to null for no timeout.
 * @param bool    $debug       If true, the debugging function will be called to print execution details.
 *
 * @return string The stdout from the executed command.
 */
function external_exec($command, &$exec_status = [], $timeout = NULL, $debug = FALSE)
{
    $command = trim($command);
    $debug   = $debug || (defined('OBS_DEBUG') && OBS_DEBUG);

    if ($debug > 0) {
        external_exec_debug_cmd($command);
    }

    if ($command === '') {
        $exec_status = [
          'stdout'   => '',
          'stderr'   => 'Empty command passed',
          'exitcode' => -1,
          'runtime'  => 0
        ];
        return '';
    }

    $descriptorspec = [
      1 => ['pipe', 'w'], // stdout
      2 => ['pipe', 'w']  // stderr
    ];

    $process = proc_open('exec ' . $command, $descriptorspec, $pipes);

    if (!is_resource($process)) {
        $exec_status = [
          'stdout'   => FALSE,
          'stderr'   => '',
          'exitcode' => -1,
          'runtime'  => 0
        ];
        return FALSE;
    }

    stream_set_blocking($pipes[1], 0);
    stream_set_blocking($pipes[2], 0);

    $stdout      = $stderr = '';
    $exec_status = [];

    $start   = microtime(TRUE);
    $runtime = 0;

    while (TRUE) {
        $read = [];
        if (!feof($pipes[1])) {
            $read[] = $pipes[1];
        }
        if (!feof($pipes[2])) {
            $read[] = $pipes[2];
        }
        if (empty($read)) {
            break;
        }
        $write  = NULL;
        $except = NULL;

        if (stream_select($read, $write, $except, $timeout) === FALSE) {
            break; // Handle stream_select() failure
        }

        foreach ($read as $pipe) {
            if ($pipe === $pipes[1]) {
                $stdout .= fread($pipe, 8192);
            } elseif ($pipe === $pipes[2]) {
                $stderr .= fread($pipe, 8192);
            }
        }

        $runtime = elapsed_time($start);
        $status  = proc_get_status($process);

        // Break from this loop if the process exited before timeout
        if (!$status['running']) {
            // Check for the rare situation of a wrong process status due to a proc_get_status() bug
            // See: https://bugs.php.net/bug.php?id=69014
            if (feof($pipes[1]) === FALSE) {
                // Store the status in $status_fix to use later if needed
                if (!isset($status_fix)) {
                    $status_fix = $status;
                }
                if ($debug > 1) {
                    print_error("Possible proc_get_status() bug encountered. Process is still running, but the status indicates otherwise.");
                }
            } else {
                // Process exited normally, so we can break the loop
                break;
            }
        }

        if ($timeout !== NULL) {
            $timeout -= $runtime;

            if ($timeout < 0) {
                proc_terminate($process, 9);
                $status['running']  = FALSE;
                $status['exitcode'] = -1;
                break;
            }
        }
    }
    $exec_status['endtime'] = microtime(TRUE);

    // FIXME -- Check if necessary in PHP7+

    if ($status['running']) {
        // Fix sometimes wrong status by adding a delay to wait for the process to finish
        $delay      = 0;
        $delay_step = 5000;   // Step increment of 5 milliseconds
        $delay_max  = 150000; // Maximum delay of 150 milliseconds

        // Keep waiting in increments of delay_step while the process is running
        // and the total delay is less than the maximum allowed delay (delay_max)
        while ($status['running'] && $delay < $delay_max) {
            usleep($delay_step); //
            $status = proc_get_status($process);
            $delay  += $delay_step;
        }

        $exec_status['exitdelay'] = (int)($delay / 1000);
    } elseif (isset($status_fix)) {
        // If $status_fix is set, use it to correct the wrong process status
        $status = $status_fix;
    }

    $stdout = preg_replace('/(?:\n|\r\n|\r)$/D', '', $stdout); // remove last (only) eol
    $stderr = rtrim($stderr);

    if ($status['running']) {
        proc_terminate($process, 9);
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $exec_status['stdout']   = $stdout;
    $exec_status['stderr']   = $stderr;
    $exec_status['exitcode'] = $status['exitcode'] ?? -1;
    $exec_status['runtime']  = $runtime;

    if ($debug) {
        external_exec_debug($exec_status);
    }

    return $stdout;
}

/**
 * Print sanitized debugging information for a command.
 *
 * This function hides sensitive data (such as SNMP authentication parameters
 * and usernames/passwords) from the debug output to ensure security.
 *
 * @param string $command The command to execute and debug.
 */
function external_exec_debug_cmd($command) {

    $debug_command = ($command === '' && isset($GLOBALS['snmp_command'])) ? $GLOBALS['snmp_command'] : $command;
    if (OBS_DEBUG < 2 && $GLOBALS['config']['snmp']['hide_auth'] &&
        preg_match("/snmp(bulk)?(get|getnext|walk)(\s+-(t|r|Cr)['\d\s]+){0,3}(\s+-Cc)?\s+-v[123]c?\s+/", $debug_command)) {
        // Hide SNMP authentication parameters from debug output
        $pattern       = "/\s+(?:(\-[uxXaA])\s*(?:'.*?')|(\-c)\s*(?:'.*?(@\S+)?'))/"; // do not hide contexts, only community and v3 auth
        $debug_command = preg_replace($pattern, ' \1\2 ***\3', $debug_command);
    } elseif (OBS_DEBUG < 2 && preg_match("!\ --(user(?:name)?|password)=!", $debug_command)) {
        // Hide any username/password in debug output, e.g. in WMIC
        $pattern       = "/ --(user(?:name)?|password)=(\S+|\'[^\']*\')/";
        $debug_command = preg_replace($pattern, ' --\1=***', $debug_command);
    }
    print_console(PHP_EOL . 'CMD[%y' . $debug_command . '%n]' . PHP_EOL);
}

/**
 * Print detailed debugging information for an executed command.
 *
 * This function displays the exit code, runtime, exit delay (if applicable),
 * and the contents of stdout and stderr from the executed command.
 *
 * @param array $exec_status An array containing execution details:
 *                           - 'stdout': The standard output of the command.
 *                           - 'stderr': The standard error output of the command.
 *                           - 'exitcode': The exit code returned by the command.
 *                           - 'runtime': The time taken to execute the command.
 *                           - 'exitdelay': The delay before the command exited (optional).
 */
function external_exec_debug($exec_status) {

    print_console("CMD EXITCODE[" . ($exec_status['exitcode'] !== 0 ? '%r' : '%g') . $exec_status['exitcode'] . "%n]");
    print_console("CMD RUNTIME[" . ($exec_status['runtime'] > 7 ? '%r' : '%g') . round($exec_status['runtime'], 4) . "s%n]");

    if ($exec_status['exitdelay'] > 0) {
        print_console("CMD EXITDELAY[%r" . $exec_status['exitdelay'] . "ms%n]");
    }

    print_console("STDOUT[" . PHP_EOL . $exec_status['stdout'] . PHP_EOL . "]");

    if ($exec_status['exitcode'] && $exec_status['stderr']) {
        print_console("STDERR[" . PHP_EOL . $exec_status['stderr'] . PHP_EOL . "]");
    }
}

/**
 * Get information about process by it identifier (pid)
 *
 * @param int     $pid   The process identifier.
 * @param boolean $stats If true, additionally show cpu/memory stats
 *
 * @return array|false  Array with information about process, If process not found, return FALSE
 */
function get_pid_info($pid, $stats = FALSE)
{
    $pid = (int)$pid;
    if ($pid < 1) {
        print_debug("Incorrect PID passed");
        //trigger_error("PID ".$pid." doesn't exists", E_USER_WARNING);
        return FALSE;
    }

    if (!$stats && PHP_OS_FAMILY === 'Linux') {
        // Do not use call to ps on Linux and extended stat not required
        // FIXME. Need something same on BSD and other Unix platforms

        if ($pid_stat = lstat("/proc/$pid")) {
            $pid_info = ['PID' => (string)$pid];
            $ps_stat  = explode(" ", file_get_contents("/proc/$pid/stat"));
            $pid_info['PPID']         = $ps_stat[3];
            $pid_info['UID']          = $pid_stat['uid'] . '';
            $pid_info['GID']          = $pid_stat['gid'] . '';
            $pid_info['STAT']         = $ps_stat[2];
            $pid_info['COMMAND']      = trim(str_replace("\0", " ", file_get_contents("/proc/$pid/cmdline")));
            $pid_info['STARTED']      = date("r", $pid_stat['mtime']);
            $pid_info['STARTED_UNIX'] = $pid_stat['mtime'];

            return $pid_info;
        }

        print_debug("DEBUG get_pid_info(): /proc dir seems not exist on system.");
        return FALSE;
    }

    // Use ps call, have troubles on high load systems!

    if ($stats) {
        // Add CPU/Mem stats
        $options = 'pid,ppid,uid,gid,pcpu,pmem,vsz,rss,tty,stat,time,lstart,args';
    } else {
        $options = 'pid,ppid,uid,gid,tty,stat,time,lstart,args';
    }

    //$timezone = get_timezone(); // Get system timezone info, for correct started time conversion

    $ps = external_exec('/bin/ps -ww -o ' . $options . ' -p ' . $pid, $exec_status, 1); // Set timeout 1sec for exec
    $ps = explode("\n", rtrim($ps));

    if ($exec_status['exitcode'] === 127) {
        print_debug("/bin/ps command not found, not possible to get process info.");
        return NULL;
    }
    if ($exec_status['exitcode'] > 0 || count($ps) < 2) {
        print_debug("PID " . $pid . " doesn't exists");
        //trigger_error("PID ".$pid." doesn't exists", E_USER_WARNING);
        return FALSE;
    }
    if ($exec_status['exitcode'] === -1) {
        // Timeout, but probably still correct
        print_debug("DEBUG get_pid_info(): cmd (" . '/bin/ps -ww -o ' . $options . ' -p ' . $pid . ") exit with timeout");
    }
    // "  PID  PPID   UID   GID %CPU %MEM    VSZ   RSS TT       STAT     TIME                  STARTED COMMAND"
    // "14675 10250  1000  1000  0.0  0.2 194640 11240 pts/4    S+   00:00:00 Mon Mar 21 14:48:08 2016 php ./test_pid.php"
    //
    // "  PID  PPID   UID   GID TT       STAT     TIME                  STARTED COMMAND"
    // "14675 10250  1000  1000 pts/4    S+   00:00:00 Mon Mar 21 14:48:08 2016 php ./test_pid.php"
    //print_vars($ps);

    // Parse output
    $keys    = preg_split("/\s+/", $ps[0], -1, PREG_SPLIT_NO_EMPTY);
    $entries = preg_split("/\s+/", $ps[1], count($keys) - 1, PREG_SPLIT_NO_EMPTY);
    $started = preg_split("/\s+/", array_pop($entries), 6, PREG_SPLIT_NO_EMPTY);
    $command = array_pop($started);

    //$started[]    = str_replace(':', '', $timezone['system']); // Add system TZ to started time
    $started[]   = external_exec('date "+%z"'); // Add system TZ to started time
    $started_rfc = array_shift($started) . ','; // Sun
    // Reimplode and convert to RFC2822 started date 'Sun, 20 Mar 2016 18:01:53 +0300'
    $started_rfc .= ' ' . $started[1];          // 20
    $started_rfc .= ' ' . $started[0];          // Mar
    $started_rfc .= ' ' . $started[3];          // 2016
    $started_rfc .= ' ' . $started[2];          // 18:01:53
    $started_rfc .= ' ' . $started[4];          // +0300
    //$started_rfc .= implode(' ', $started);
    $entries[] = $started_rfc;

    $entries[] = $command; // Re-add command
    //print_vars($entries);
    //print_vars($started);

    $pid_info = [];
    foreach ($keys as $i => $key) {
        $pid_info[$key] = $entries[$i];
    }
    $pid_info['STARTED_UNIX'] = strtotime($pid_info['STARTED']);
    //print_vars($pid_info);

    return $pid_info;
}

/**
 * Add information about process into DB
 *
 * @param array|int $device Device array
 * @param int       $pid    PID for process. If empty used current PHP process ID
 *
 * @return int        DB id for inserted row
 */
function add_process_info($device, $pid = NULL)
{
    global $argv, $config;

    $process_name = OBS_SCRIPT_NAME;
    $process      = OBS_PROCESS_NAME;

    // Ability for skip any process checking
    // WARNING. USE AT OWN RISK
    if (isset($config['check_process'][$process]) && !$config['check_process'][$process]) {
        if (OBS_DEBUG) {
            print_error("WARNING. Process '$process_name' adding disabled.");
        }
        return NULL;
    }

    // Check if device_id passed instead array
    if (is_numeric($device)) {
        $device = ['device_id' => $device];
    }
    if (!is_numeric($pid)) {
        $pid = getmypid();
    }
    $pid_info = get_pid_info($pid);

    if (is_array($pid_info)) {
        if ($process_name === 'poller.php' || $process_name === 'alerter.php') {
            // Try detect parent poller wrapper
            $parent_info = $pid_info;
            do {
                $found       = FALSE;
                $parent_info = get_pid_info($parent_info['PPID']);
                if (str_contains($parent_info['COMMAND'], $process_name)) {
                    $found = TRUE;
                } elseif (str_contains($parent_info['COMMAND'], 'poller-wrapper.py')) {
                    $pid_info['PPID'] = $parent_info['PID'];
                }
            } while ($found);
        }
        $update_array = [
          'process_pid'     => $pid,
          'process_name'    => $process_name,
          'process_ppid'    => $pid_info['PPID'],
          'process_uid'     => $pid_info['UID'],
          'process_command' => $pid_info['COMMAND'],
          'process_start'   => $pid_info['STARTED_UNIX'],
          'device_id'       => $device['device_id']
        ];
        if ($config['poller_id'] > 0 && is_cli()) {
            $update_array['poller_id'] = $config['poller_id'];
        }
        return dbInsert($update_array, 'observium_processes');
    }
    print_debug("Process info not added for PID: $pid");

    return NULL;
}

/**
 * Delete information about process from DB
 *
 * @param array $device Device array
 * @param int   $pid    PID for process. If empty used current PHP process ID
 *
 * @return int                 DB id for inserted row
 */
function del_process_info($device, $pid = NULL)
{
    global $argv, $config;

    $process_name = basename($argv[0]);

    // Ability for skip any process checking
    // WARNING. USE AT OWN RISK
    $process = str_replace('.php', '', $process_name);
    if (isset($config['check_process'][$process]) && !$config['check_process'][$process]) {
        if (OBS_DEBUG) {
            print_error("WARNING. Process '$process_name' delete disabled.");
        }
        return NULL;
    }

    // Check if device_id passed instead array
    if (is_numeric($device)) {
        $device = ['device_id' => $device];
    }
    if (!is_numeric($pid)) {
        $pid = getmypid();
    }

    if ($pid) {
        $params = [$pid, $process_name, $device['device_id'], $config['poller_id']];

        return dbDelete('observium_processes', '`process_pid` = ? AND `process_name` = ? AND `device_id` = ? AND `poller_id` = ?', $params);
    }

    return NULL;
}

function check_process_run($device, $pid = NULL)
{
    global $config, $argv;

    $check = FALSE;

    $process_name = basename($argv[0]);

    // Ability for skip any process checking
    // WARNING. USE AT OWN RISK
    $process = str_replace('.php', '', $process_name);
    if (isset($config['check_process'][$process]) && !$config['check_process'][$process]) {
        if (OBS_DEBUG) {
            print_error("WARNING. Process '$process_name' checking disabled.");
        }
        return $check;
    }

    // Check if device_id passed instead array
    if (is_numeric($device)) {
        $device = ['device_id' => $device];
    }

    $query  = 'SELECT * FROM `observium_processes` WHERE `process_name` = ? AND `device_id` = ? AND `poller_id` = ?';
    $params = [$process_name, $device['device_id'], $config['poller_id']];
    if (is_numeric($pid)) {
        $query    .= ' AND `process_pid` = ?';
        $params[] = (int)$pid;
    }

    foreach (dbFetchRows($query, $params) as $process) {
        // We found processes in DB, check if it exist on system
        $pid_info = get_pid_info($process['process_pid']);
        if (is_array($pid_info) && strpos($pid_info['COMMAND'], $process_name) !== FALSE) {
            // Process still running
            $check = array_merge($pid_info, $process);
        } else {
            // Remove stalled DB entries
            dbDelete('observium_processes', '`process_id` = ?', [$process['process_id']]);
        }
    }

    return $check;
}

/**
 * Determine array is associative
 *
 * @param array $array
 *
 * @return boolean
 */
function is_array_assoc($array) {
    return is_array($array) && !array_is_list($array);
}

/**
 * Determine array is flat sequential list
 * Valid: [ 0, 3, 'apple' ]
 * Not Valid: [ 0, 3, [] ]
 *
 * @param array $array
 *
 * @return boolean
 */
function is_array_flat($array) {
    if (!is_array($array)) {
        return FALSE;
    }
    return array_is_list($array) && !array_filter($array, 'is_array');
}

function is_array_numeric($array) {
    if (!is_array($array) || empty($array)) {
        return FALSE;
    }
    foreach ($array as $value) {
        // Check if the value is not numeric
        if (!is_numeric($value)) {
            // Return false immediately if a non-numeric value is found
            return FALSE;
        }
    }

    // If the loop completes, all values are numeric
    return TRUE;
}

/**
 * Detect if a needle exists in an array. Support mixed needle value.
 *
 * @param mixed $value Needle
 * @param array $array Where to find
 *
 * @return bool
 */
function array_value_exist($value, $array) {
    if (!is_array($array) || empty($array)) {
        return FALSE;
    }

    // Non array needle
    if (!is_array($value)) {
        return in_array($value, $array);
    }

    // Get the intersection of both arrays.
    $intersect = array_intersect($value, $array);

    // Check if the intersection is not empty.
    return !empty($intersect);
}

/**
 * Checks if the given key or index exists in the array.
 * Case-insensitive implementation
 *
 * @param string|int $key   Value to check.
 * @param array      $array An array with keys to check.
 *
 * @return bool
 */
function array_key_iexists($key, $array) {
    if (!is_array($array)) {
        return FALSE;
    }
    return in_array(strtolower($key), array_map('strtolower', array_keys($array)), TRUE);
}

/**
 * Case-insensitive in_array()
 *
 * @param string $needle
 * @param array $array
 *
 * @return bool
 */
function in_iarray($needle, $array) {
    if (!is_array($array)) {
        return FALSE;
    }
    // Convert both the needle and haystack elements to lowercase
    return in_array(strtolower($needle), array_map('strtolower', $array), TRUE);
}

/**
 * Get all values from specific key in a multidimensional array
 *
 * @param $key string
 * @param $arr array
 *
 * @return null|string|array
 */
function array_value_recursive($key, array $arr)
{
    $val = [];
    array_walk_recursive($arr, static function ($v, $k) use ($key, &$val): void {
        if ($k == $key) {
            array_push($val, $v);
        }
    });

    return count($val) > 1 ? $val : array_pop($val);
}

/**
 * @param        $array
 * @param        $string
 * @param string $delimiter
 *
 * @return mixed|null
 */
function array_get_nested($array, $string, $delimiter = '->')
{
    foreach (explode($delimiter, $string) as $key) {
        if (!array_key_exists($key, (array)$array)) {
            return NULL;
        }
        $array = $array[$key];
    }

    return $array;
}

/**
 * Insert a value or key/value pair after a specific key in an array.  If key doesn't exist, value is appended
 * to the end of the array.
 *
 * @param array        $array
 * @param string       $key
 * @param array|string $new
 *
 * @return array
 */
function array_push_after(array $array, $key, $new)
{
    $keys  = array_keys($array);
    $index = array_search($key, $keys, TRUE);
    $count = count($array);
    if ($index === FALSE) {
        return array_merge_recursive($array, (array)$new);
    }
    $pos = $index + 1;

    return array_merge_recursive(array_slice($array, 0, $pos, TRUE), (array)$new, array_slice($array, $pos, $count - 1, TRUE));
}

function array_filter_key($array, $keys = [], $condition = TRUE) {

    if ($condition === 'starts' || $condition === 'starts_with') {
        return array_filter($array, static function($key) use ($keys) { return str_starts_with($key, (string)$keys); }, ARRAY_FILTER_USE_KEY);
    }
    if ($condition === 'contains') {
        return array_filter($array, static function($key) use ($keys) { return str_contains($key, (string)$keys); }, ARRAY_FILTER_USE_KEY);
    }

    // keys is array
    if (!is_array($array) || ($condition === TRUE && empty($keys)) || !array_is_list($keys)) {
        return [];
    }

    if ($condition === FALSE || $condition === '!=' || $condition === '!==') {
        return array_filter($array, static function($key) use ($keys) { return !in_array($key, $keys, TRUE); }, ARRAY_FILTER_USE_KEY);
    }
    return array_filter($array, static function($key) use ($keys) { return in_array($key, $keys, TRUE); }, ARRAY_FILTER_USE_KEY);
}

/**
 * Converts an associative array into a key:value string.
 *
 * @param array $array The associative array to process.
 * @param string $separator Separator between key:value pairs.
 * @param string $key_separator Separator between key and value.
 *
 * @return string The resulting string in key:value format.
 */
function implode_key_value($separator, $array, $key_separator = ":") {
    return implode($separator, array_map(
        function ($key, $value) use ($key_separator) {
            return "$key$key_separator$value";
        },
        array_keys($array),
        $array
    ));
}

/**
 * Fast string compare function, checks if string contain $needle
 * Note: function renamed from str_contains() for not to intersect with php8 function.
 *
 * @param string $string             The string to search in
 * @param mixed  $needle             If needle is not a string, it is converted to an string
 * @param mixed  $encoding           For use "slow" multibyte compare, pass required encoding here (ie: UTF-8)
 * @param bool   $case_insensitivity If case_insensitivity is TRUE, comparison is case insensitive
 *
 * @return bool                       Returns TRUE if $string starts with $needle or FALSE otherwise
 */
function str_contains_array($string, $needle, $encoding = FALSE, $case_insensitivity = FALSE)
{
    if (is_array($string)) {
        // This function required string to search
        return FALSE;
    }

    // If needle is array, use recursive compare
    if (is_array($needle)) {
        foreach ($needle as $findme) {
            if (str_contains_array($string, (string)$findme, $encoding, $case_insensitivity)) {
                $GLOBALS['str_last_needle'] = (string)$findme;
                return TRUE;
            }
        }
        $GLOBALS['str_last_needle'] = (string)$findme;
        return FALSE;
    }

    $needle                     = (string)$needle;
    $string                     = (string)$string;
    $GLOBALS['str_last_needle'] = $needle;
    $compare                    = $string === $needle;
    if ($needle === '') {
        return $compare;
    }
    if ($case_insensitivity) {
        // Case-INsensitive

        // NOTE, multibyte compare required mb_* functions and slower than general functions
        if ($encoding && check_extension_exists('mbstring') &&
            mb_strlen($string, $encoding) !== strlen($string)) {
            //$encoding = 'UTF-8';
            //return mb_strripos($string, $needle, -mb_strlen($string, $encoding), $encoding) !== FALSE;
            return $compare || mb_stripos($string, $needle) !== FALSE;
        }

        return $compare || stripos($string, $needle) !== FALSE;
    }

    // Case-sensitive
    return $compare || str_contains((string)$string, $needle);
}

function str_icontains_array($string, $needle, $encoding = FALSE)
{
    return str_contains_array($string, $needle, $encoding, TRUE);
}

/**
 * Fast string compare function, checks if string begin with $needle
 *
 * @param string  $string             The string to search in
 * @param mixed   $needle             If needle is not a string, it is converted to an string
 * @param mixed   $encoding           For use "slow" multibyte compare, pass required encoding here (ie: UTF-8)
 * @param boolean $case_insensitivity If case_insensitivity is TRUE, comparison is case insensitive
 *
 * @return boolean                    Returns TRUE if $string starts with $needle or FALSE otherwise
 */
function str_starts($string, $needle, $encoding = FALSE, $case_insensitivity = FALSE)
{
    if (is_array($string)) {
        // This function required string to search
        return FALSE;
    }

    // If needle is array, use recursive compare
    if (is_array($needle)) {
        foreach ($needle as $findme) {
            if (str_starts($string, (string)$findme, $encoding, $case_insensitivity)) {
                $GLOBALS['str_last_needle'] = (string)$findme;
                return TRUE;
            }
        }
        $GLOBALS['str_last_needle'] = (string)$findme;
        return FALSE;
    }

    $needle                     = (string)$needle;
    $string                     = (string)$string;
    $GLOBALS['str_last_needle'] = $needle;
    if ($needle === '') {
        return $string === $needle;
    }
    if ($case_insensitivity) {
        // Case-INsensitive

        // NOTE, multibyte compare required mb_* functions and slower than general functions
        if ($encoding &&
            check_extension_exists('mbstring') && mb_strlen($string, $encoding) !== strlen($string)) {
            //$encoding = 'UTF-8';
            return mb_strripos($string, $needle, -mb_strlen($string, $encoding), $encoding) !== FALSE;
        }

        return $needle !== ''
          ? strncasecmp($string, $needle, strlen($needle)) === 0
          : $string === '';
    }

    // Case-sensitive
    return str_starts_with((string)$string, $needle);
}

function str_istarts($string, $needle, $encoding = FALSE)
{
    return str_starts($string, $needle, $encoding, TRUE);
}

/**
 * Fast string compare function, checks if string end with $needle
 *
 * @param string  $string             The string to search in
 * @param mixed   $needle             If needle is not a string, it is converted to an string
 * @param mixed   $encoding           For use "slow" multibyte compare, pass required encoding here (ie: UTF-8)
 * @param boolean $case_insensitivity If case_insensitivity is TRUE, comparison is case insensitive
 *
 * @return boolean                    Returns TRUE if $string ends with $needle or FALSE otherwise
 */
function str_ends($string, $needle, $encoding = FALSE, $case_insensitivity = FALSE)
{
    if (is_array($string)) {
        // This function required string to search
        return FALSE;
    }

    // If needle is array, use recursive compare
    if (is_array($needle)) {
        foreach ($needle as $findme) {
            if (str_ends($string, (string)$findme, $encoding, $case_insensitivity)) {
                $GLOBALS['str_last_needle'] = (string)$findme;
                return TRUE;
            }
        }
        $GLOBALS['str_last_needle'] = (string)$findme;
        return FALSE;
    }

    $needle                     = (string)$needle;
    $string                     = (string)$string;
    $GLOBALS['str_last_needle'] = $needle;
    $compare                    = $needle !== '';
    if ($needle === '') {
        return $string === $needle;
    }

    // NOTE, multibyte compare required mb_* functions and slower than general functions
    if ($encoding && $compare &&
        check_extension_exists('mbstring') && mb_strlen($string, $encoding) !== strlen($string)) {
        //$encoding = 'UTF-8';
        $diff = mb_strlen($string, $encoding) - mb_strlen($needle, $encoding);
        if ($case_insensitivity) {
            return $diff >= 0 && mb_stripos($string, $needle, $diff, $encoding) !== FALSE;
        }
        return $diff >= 0 && mb_strpos($string, $needle, $diff, $encoding) !== FALSE;
    }

    // Case sensitive compare
    if (!$case_insensitivity) {
        return str_ends_with((string)$string, $needle);
    }

    $nlen = strlen($needle);

    return $compare
      ? substr_compare($string, $needle, -$nlen, $nlen, $case_insensitivity) === 0
      : $string === '';
}

function str_iends($string, $needle, $encoding = FALSE)
{
    return str_ends($string, $needle, $encoding, TRUE);
}

/**
 * Compress long strings to hexified compressed string. Can be uncompressed by str_decompress().
 *
 * @param string $string
 *
 * @return string
 */
function str_compress($string)
{
    if (!is_string($string)) {
        return $string;
    }

    if ($compressed = gzdeflate($string, 9)) {
        $compressed = gzdeflate($compressed, 9);

        if (OBS_DEBUG > 1) {
            $compressed = safe_base64_encode($compressed);
            print_cli("DEBUG: String '$string' [" . strlen($string) . "] compressed to '" . $compressed . "' [" . strlen($compressed) . "].");
            return $compressed;
        }

        return safe_base64_encode($compressed);
    }

    return $string;
}

/**
 * Decompress strings compressed by str_compress().
 *
 * @param string $compressed
 *
 * @return string
 */
function str_decompress($compressed)
{
    if (!is_string($compressed) || !ctype_print($compressed) || !$bin = safe_base64_decode($compressed)) {
        return FALSE;
    }

    $string = gzinflate(gzinflate($bin));
    if (!is_string($string)) {
        // Not an compressed string?

        return FALSE;
    }

    if (OBS_DEBUG > 1) {
        print_cli("DEBUG: String '$compressed' [" . strlen($compressed) . "] decompressed to '" . $string . "' [" . strlen($string) . "].");
    }
    return $string;
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function is_cli()
{
    if (defined('__PHPUNIT_PHAR__') && isset($GLOBALS['cache']['is_cli'])) {
        // Allow override is_cli() in PHPUNIT
        return $GLOBALS['cache']['is_cli'];
    }
    if (!defined('OBS_CLI')) {
        define('OBS_CLI', PHP_SAPI === 'cli' && empty($_SERVER['REMOTE_ADDR']));
        if (defined('OBS_DEBUG') && OBS_DEBUG > 1) {
            print_cli("DEBUG: is_cli() == " . (OBS_CLI ? 'TRUE' : 'FALSE') . ", PHP_SAPI: '" . PHP_SAPI . "', REMOTE_ADDR: '" . $_SERVER['REMOTE_ADDR'] . "'");
        }
    }

    return OBS_CLI;
}

function cli_is_piped()
{
    if (!defined('OBS_CLI_PIPED')) {
        define('OBS_CLI_PIPED', check_extension_exists('posix') && !posix_isatty(STDOUT));
    }

    return OBS_CLI_PIPED;
}

// Detect if script runned from crontab
// DOCME needs phpdoc block
// TESTME needs unit testing
function is_cron()
{
    if (!defined('OBS_CRON')) {
        $cron = is_cli() && !isset($_SERVER['TERM']);
        // For more accurate check if STDOUT exist (but this requires posix extension)
        if ($cron) {
            $cron = $cron && cli_is_piped();
        }
        define('OBS_CRON', $cron);
    }

    return OBS_CRON;
}

/**
 * Detect if current URI is link to graph
 *
 * @return boolean TRUE if current script is graph
 */
// TESTME needs unit testing
function is_graph()
{
    if (!defined('OBS_GRAPH')) {
        // defined in html/graph.php
        define('OBS_GRAPH', FALSE);
    }

    return OBS_GRAPH;
}

/**
 * Detect if current URI is API
 *
 * @return boolean TRUE if current script is API
 */
// TESTME needs unit testing
function is_api()
{
    if (!defined('OBS_API')) {
        // defined in html/graph.php
        define('OBS_API', FALSE);
    }

    return OBS_API;
}

function is_ajax()
{
    if (!defined('OBS_AJAX')) {
        define('OBS_AJAX', (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
                           str_starts_with($_SERVER['REQUEST_URI'], '/ajax/'));
    }

    return OBS_AJAX;
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function is_ssl()
{
    if (isset($_SERVER['HTTPS'])) {
        if ($_SERVER['HTTPS'] === '1' || strtolower($_SERVER['HTTPS']) === 'on') {
            return TRUE;
        }
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return TRUE;
    } elseif (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) === 'on') {
        return TRUE;
    } elseif (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == '443')) {
        return TRUE;
    }
    //elseif (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') { return TRUE; }

    return FALSE;
}

function is_iframe() {
    //bdump($_SERVER['HTTP_SEC_FETCH_DEST']);
    // Note, Safari detect as iframe: <object data=""
    return isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe';
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function print_prompt($text, $default_yes = FALSE)
{
    if (is_cli()) {
        if (cli_is_piped()) {
            // If now not have interactive TTY skip any prompts, return default
            return TRUE && $default_yes;
        }

        $question = ($default_yes ? 'Y/n' : 'y/N');
        echo trim($text), " [$question]: ";
        $handle = fopen('php://stdin', 'r');
        $line   = strtolower(trim(fgets($handle, 3)));
        fclose($handle);
        if ($default_yes) {
            $return = ($line === 'no' || $line === 'n');
        } else {
            $return = ($line === 'yes' || $line === 'y');
        }
    } else {
        // Here placeholder for web prompt
        $return = TRUE && $default_yes;
    }

    return $return;
}

/**
 * This function echoes text with style 'debug', see print_message().
 * Here check constant OBS_DEBUG if OBS_DEBUG not set output - empty.
 *
 * @param string  $text
 * @param boolean $strip Stripe special characters (for web) or html tags (for cli)
 */
function print_debug($text, $strip = FALSE) {
    if (defined('OBS_DEBUG') && OBS_DEBUG > 0) {
        print_message($text, 'debug', $strip);
    }
}

/**
 * This function echoes text with style 'error', see print_message().
 *
 * @param string  $text
 * @param boolean $strip Stripe special characters (for web) or html tags (for cli)
 */
function print_error($text, $strip = TRUE) {
    print_message($text, 'error', $strip);
}

/**
 * This function echoes text with style 'warning', see print_message().
 *
 * @param string  $text
 * @param boolean $strip Stripe special characters (for web) or html tags (for cli)
 */
function print_warning($text, $strip = TRUE) {
    print_message($text, 'warning', $strip);
}

/**
 * This function echoes text with style 'success', see print_message().
 *
 * @param string  $text
 * @param boolean $strip Stripe special characters (for web) or html tags (for cli)
 */
function print_success($text, $strip = TRUE) {
    print_message($text, 'success', $strip);
}

function print_console($text, $strip = FALSE) {
    print_message($text, 'console', $strip);
}

/**
 * This function echoes text with specific styles (different for cli and web output).
 *
 * @param string  $text
 * @param string  $type  Supported types: default, success, warning, error, debug
 * @param boolean $strip Stripe special characters (for web) or html tags (for cli)
 */
function print_message($text, $type = '', $strip = TRUE) {

    // Do nothing if an input text not any string (like NULL, array or other). (Empty string '' still printed).
    if (!is_string($text) && !is_numeric($text)) {
        return NULL;
    }

    $type = strtolower(trim($type));
    switch ($type) {
        case 'ok':
        case 'success':
            $cli_class = '%g';                  // green
            $cli_color = FALSE;                 // by default cli coloring disabled
            $class     = 'alert alert-success'; // green
            break;

        case 'warning':
            $cli_class = '%b';                  // blue
            $cli_color = FALSE;                 // by default cli coloring disabled
            $class     = 'alert alert-warning'; // yellow
            break;

        case 'error':
        case 'danger':
        case 'alert':
        case 'debug':
            $cli_class = '%r';                 // red
            $cli_color = FALSE;                // by default cli coloring disabled
            $class     = 'alert alert-danger'; // red
            break;

        case 'suppressed':
            $cli_class = '%m';                     // magenta
            $cli_color = FALSE;                    // by default cli coloring disabled
            $class     = 'alert alert-suppressed'; // magenta
            break;

        case 'color':
            $cli_class = '';                 // none
            $cli_color = TRUE;               // allow using coloring
            $class     = 'alert alert-info'; // blue
            break;

        case 'console':
            // This is a special type used nl2br conversion for display console messages on WUI with correct line breaks
            $cli_class = '';                       // none
            $cli_color = TRUE;                     // allow using coloring
            $class     = 'alert alert-suppressed'; // purple
            break;

        case 'info':
        default:
            $cli_class = '%W';               // bold
            $cli_color = FALSE;              // by default cli coloring disabled
            $class     = 'alert alert-info'; // blue
            break;
    }

    // Strip tags from a text, separate for cli/web
    if ($strip) {
        $text = message_strip_tags($text, $cli_color);
    }

    // Store non debug message to global var
    if ($type !== 'debug') {
        $GLOBALS['last_message'] = $text;
    }

    if (is_cli()) {
        if ($type === 'debug' && !$cli_color) {
            // For debug just echo message.
            echo $text . PHP_EOL;
        } else {
            print_cli($cli_class . $text . '%n' . PHP_EOL, $cli_color);
        }

        return;
    }

    if (safe_empty($text) ||
        ($type !== 'debug' && (is_graph() || is_api()))) {
        // Do not web output if the string is empty or graph or api
        return;
    }

    if (str_starts_with($type, 'box-')) {
        // Boxed Web UI output
        print_box($text, str_replace('box-', '', $type));
        return;
    }

    // General Web UI output
    $msg = PHP_EOL . '    <div class="' . $class . '">';
    if (!str_contains_array($type, [ 'warning', 'error', 'danger' ])) {
        // Dismiss button
        $msg .= '<button type="button" class="close" data-dismiss="alert">&times;</button>';
    }
    if ($type === 'console') {
        $text = nl2br(trim($text)); // Convert newline to <br /> for console messages with line breaks
    }

    $msg .= '
      <div>' . $text . '</div>
    </div>' . PHP_EOL;

    echo $msg;
}

/**
 * @param string $text
 * @param bool $color
 *
 * @return string
 */
function message_strip_tags($text, $color = TRUE) {

    if (is_cli()) {
        // Strip in CLI output
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');              // Convert special HTML entities back to characters
        $text = str_ireplace([ '<br />', '<br>', '<br/>' ], PHP_EOL, $text); // Convert html <br> to into newline

        // FIXME. Convert some color label classes to cli tags?
        return strip_tags($text);
    }

    // Strip in WEB output
    if ($text === strip_tags($text)) {
        // Convert special characters to HTML entities only if text not have html tags
        $text = escape_html($text);
    }

    if ($color) {
        // Replace some Pear::Console_Color2 color codes with html styles
        $to = [
            '%',                                  // '%%'
            '</span>',                            // '%n'
            '<span class="label label-warning">', // '%y'
            '<span class="label label-success">', // '%g'
            '<span class="label label-danger">',  // '%r'
            '<span class="label label-primary">', // '%b'
            '<span class="label label-info">',    // '%c'
            '<span class="label label-default">', // '%W'
            '<span class="label label-default" style="color:black;">', // '%k'
            '<span style="font-weight: bold;">',  // '%_'
            '<span style="text-decoration: underline;">', // '%U'
        ];
    } else {
        $to = [ '%', '' ];
    }

    // Cli colored tags (Pear::Console_Color2)
    $from = [ '%%', '%n', '%y', '%g', '%r', '%b', '%c', '%W', '%k', '%_', '%U' ];

    return str_replace($from, $to, $text);
}

function get_last_message()
{
    if (!isset($GLOBALS['last_message'])) {
        return NULL;
    }
    $text = str_replace(['%%', '%n', '%y', '%g', '%r', '%b', '%c', '%W', '%k', '%_', '%U'], '', $GLOBALS['last_message']);

    // Reset message for prevent errors in loops
    //unset($GLOBALS['last_message']);

    if (preg_match('/^[A-Z\_ ]+\[(.+)\]$/s', $text, $matches)) {
        // CLI messages like:
        // RESPONSE ERROR[You must use an API key to authenticate each request]
        return $matches[1];
    }
    return strip_tags($text);
}

function print_cli($text, $colour = TRUE) {

    $msg = new Console_Color2();

    // Always append reset colour at text end
    if ($colour && str_contains($text, '%')) {
        $text .= '%n';
    }

    echo $msg->convert($text, $colour);
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function print_obsolete_config($filter = '')
{
    global $config;

    $list = [];
    foreach ($config['obsolete_config'] as $entry) {
        if ($filter && strpos($entry['old'], $filter) === FALSE) {
            continue;
        }
        $old = explode('->', $entry['old']);
        // Founded obsolete config in overall $config
        $found = !is_null(array_get_nested($config, $entry['old']));
        if ($found && is_null(array_get_nested(get_defined_settings(), $entry['old']))) {
            // Check if this is in config.php or in DB config
            $found = FALSE;
            // FIXME need migrate old DB config to new
        }

        if ($found) {
            $new    = explode('->', $entry['new']);
            $info   = strlen($entry['info']) ? ' (' . $entry['info'] . ')' : '';
            $list[] = "  %r\$config['" . implode("']['", $old) . "']%n -> %g\$config['" . implode("']['", $new) . "']%n" . $info;
        }
    }

    if ($list) {
        $msg = "%WWARNING%n Obsolete configuration(s) found in config.php file, please rename respectively:\n" . implode(PHP_EOL, $list);
        print_message($msg, 'color');
        return TRUE;
    } else {
        return FALSE;
    }
}

// Check if php extension exist, than warn or fail
// DOCME needs phpdoc block
// TESTME needs unit testing
function check_extension_exists($extension, $text = FALSE, $fatal = FALSE) {
    $extension = strtolower($extension);

    if (isset($GLOBALS['cache']['extension'][$extension])) {
        // Cached
        $exist = $GLOBALS['cache']['extension'][$extension];
    } else {

        $extension_functions = [
            'ldap'     => 'ldap_connect',
            'mysql'    => 'mysql_connect',
            'mysqli'   => 'mysqli_connect',
            'mbstring' => 'mb_detect_encoding',
            'posix'    => 'posix_isatty',
            'session'  => 'session_name',
            'curl'     => 'curl_init',
            'svn'      => 'svn_log'
        ];

        if (isset($extension_functions[$extension])) {
            $exist = @function_exists($extension_functions[$extension]);
        } else {
            $exist = @extension_loaded($extension);
        }

        // Cache
        $GLOBALS['cache']['extension'][$extension] = $exist;
    }

    if (!$exist) {
        // Print error (only if $text not equals to FALSE)
        if ($text === '' || $text === TRUE) {
            // Generic message
            print_error("The extension '$extension' is missing. Please check your PHP configuration.");
        } elseif ($text !== FALSE) {
            // Custom message
            print_error("The extension '$extension' is missing. $text");
        } else {
            // Debug message
            print_debug("The extension '$extension' is missing. Please check your PHP configuration.");
        }

        // Exit if $fatal set to TRUE
        if ($fatal) {
            exit(2);
        }
    }

    return $exist;
}

/**
 * Sign function
 *
 * This function extracts the sign of the number.
 * Returns -1 (negative), 0 (zero), 1 (positive)
 *
 * @param integer|float $int
 *
 * @return integer
 */
function sgn($int)
{
    if ($int < 0) {
        return -1;
    }
    return $int > 0 ? 1 : 0;
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function truncate($substring, $max = 50, $rep = '...') {

    if (empty($rep)) {
        return truncate_len($substring, $max);
    }

    // Use mb_substr if available for proper multibyte support
    if (function_exists('mb_substr')) {
        return mb_truncate($substring, $max, $rep);
    }

    // Handle empty or non-string values
    if (safe_empty($substring) || is_array($substring)) {
        return $rep;
    }
    $string = (string)$substring;

    // If input length is less than or equal to max, return as-is
    if (strlen($string) <= $max) {
        return $string;
    }

    // Use HTML entity for ellipsis in non-CLI environments when default rep is used
    if ($rep === '...' && !is_cli()) {
        //$rep = '&hellip;';
        $rep = '&mldr;';
        $rep_len = 1;
    } else {
        // Replacement length (HTML entity counts as one char visually but more in bytes)
        $rep_len = (str_starts_with($rep, '&') && str_ends_with($rep, ';')) ? 1 : strlen($rep);
    }

    $leave = $max - $rep_len;
    return substr_replace($string, $rep, $leave);
}

/**
 * @param $substring
 * @param $max
 *
 * @return false|string
 */
function truncate_len($substring, $max = 255) {
    // Handle empty or non-string values
    if (safe_empty($substring) || is_array($substring)) {
        return '';
    }
    $string = (string)$substring;

    if ($max && strlen($substring) > $max) {
        return function_exists('mb_substr') ? mb_substr($substring, 0, $max) : substr($substring, 0, $max);
    }

    return $string;
}

/**
 * Truncate a multibyte string to a specified width, appending a replacement if truncated.
 *
 * @param string $substring The input string to be truncated.
 * @param int    $max       The maximum allowed length of the truncated string. Default is 50.
 * @param string $rep       The replacement string to be used when truncating (default '...' or '&mldr;' for HTML)
 *
 * @return string The truncated string.
 */
function mb_truncate($substring, $max = 50, $rep = '...') {
    // Handle non-string input and empty
    if (safe_empty($substring) || is_array($substring)) {
        return $rep;
    }
    $substring = (string)$substring;

    // If string is already within the limit, return as-is
    if (mb_strlen($substring, 'UTF-8') <= $max) {
        return $substring;
    }

    // Replace $rep with &mldr; in web context if default value is used
    if ($rep === '...' && !is_cli()) {
        $rep = '&mldr;';
        $rep_len = 1;
    } else {
        // Replacement length (HTML entity counts as one char visually but more in bytes)
        $rep_len = (str_starts_with($rep, '&') && str_ends_with($rep, ';')) ? 1 : mb_strlen($rep, 'UTF-8');
    }

    // Truncate the string using mb_strimwidth() and add the replacement string if needed
    $leave = $max - $rep_len;

    return mb_substr($substring, 0, $leave, 'UTF-8') . $rep;
    //return mb_strimwidth($substring, 0, $max, $rep, 'UTF-8');
}

function str_pad_left($string, $len, $pad_string = ' ') {
    if (function_exists('mb_str_pad')) {
        return mb_str_pad($string, $len, $pad_string, STR_PAD_LEFT, 'UTF-8');
    }
    return str_pad($string, $len, $pad_string, STR_PAD_LEFT);
}

/**
 * Escape HTML characters in a string using htmlspecialchars() while allowing specific tags and entities.
 *
 * @param string|null $string The input string to be escaped.
 *
 * @return string|null The escaped string, or null if the input is null.
 */
function escape_html($string) {
    if (empty($string)) {
        return $string;
    }

    $escaped = htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (empty($escaped)) {
        // When string has invalid code unit sequences, try decoding or use ENT_SUBSTITUTE
        $encodings = 'ISO-8859-1,ISO-8859-5,CP1251,GB18030';
        if (function_exists('mb_detect_encoding') && $enc = mb_detect_encoding($string, $encodings)) {
            //var_dump($enc);
            $string = mb_convert_encoding($string, 'UTF-8', $enc);
            //var_dump($string);
        }
        $escaped = htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
    $string = $escaped;

    // Un-escape allowed tags using a callback
    if (str_contains($string, '&lt;')) {
        $allowed_tags = $GLOBALS['config']['escape_html']['tags'];
        $string = preg_replace_callback('/&lt;(\/?(.+?)(?: *\/)?)&gt;/', static function ($matches) use ($allowed_tags) {
            $tag = $matches[2];

            if (in_array($tag, $allowed_tags, TRUE)) {
                return '<' . $matches[1] . '>';
            }

            return $matches[0];
        }, $string);
    }

    // Un-escape allowed entities using a callback
    if (str_contains($string, '&amp;')) {
        $allowed_entities = $GLOBALS['config']['escape_html']['entities'];
        $string = preg_replace_callback('/&amp;([^;]+);/', static function ($matches) use ($allowed_entities) {
            $entity = $matches[1];

            if (in_array($entity, $allowed_entities, TRUE)) {
                return '&' . $entity . ';';
            }

            return $matches[0];
        }, $string);
    }

    return $string;
}


/**
 * Generate a random string with a given length and an optional character set.
 *
 * @param int         $max        The length of the generated random string. Default is 16.
 * @param string|null $characters An optional string of characters to be used for generating the random string. If not provided, a default set of alphanumeric
 *                                characters will be used.
 *
 * @return string The generated random string.
 */
function random_string($max = 16, $characters = NULL)
{
    if (!$characters || !is_string($characters)) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }

    $randstring = '';
    $length     = strlen($characters) - 1;

    for ($i = 0; $i < $max; $i++) {
        $randstring .= $characters[random_int(0, $length)];
    }

    return $randstring;
}

/**
 * Generate a cryptographically secure random string with a given length and an optional encoding.
 *
 * @param int    $length   The length of the generated random string. Default is 16.
 * @param string $encoding The encoding to use for the generated string. Supports 'hex' and 'base64'. Default is 'hex'.
 *
 * @return string|null The generated random string, or null if an invalid encoding is provided.
 */
function generate_secure_random_string($length = 16, $encoding = 'hex')
{
    $randomBytes = random_bytes($length);

    switch ($encoding) {
        case 'hex':
            return bin2hex($randomBytes);
        case 'base64':
            return rtrim(base64_encode($randomBytes), '=');
        default:
            return NULL;
    }
}

/**
 * Sanitize a filename by replacing any non-alphanumeric or non-standard characters with underscores.
 *
 * @param string $filename The input filename to be sanitized.
 *
 * @return string The sanitized filename with non-alphanumeric or non-standard characters replaced by underscores.
 */
function safename($filename)
{
    return preg_replace('/[^a-zA-Z0-9._\-]/', '_', $filename);
}

/**
 * Pad a number with zeros on the left side up to the specified length.
 *
 * @param int|string $num    The input number to be zero-padded.
 * @param int        $length The desired length of the zero-padded number. Default is 2.
 *
 * @return string The zero-padded number as a string.
 */
function zeropad($num, $length = 2)
{
    return str_pad($num, $length, '0', STR_PAD_LEFT);
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function format_bps($value, $round = 2, $sf = 3)
{
    return format_si($value, $round, $sf) . "bps";
}

/**
 * Format a storage value in bytes using the binary prefix system (KB, MB, GB, etc.), and round the value
 * to a specified number of decimal places.
 *
 * @param float $value The input value in bytes to be formatted.
 * @param int   $round The number of decimal places to round the value to. Default is 2.
 * @param int   $sf    The number of significant figures to use in the formatted value. Default is 3.
 *
 * @return string The formatted storage value with the appropriate binary prefix.
 */
function format_bytes($value, $round = 2, $sf = 3)
{
    return format_bi($value, $round, $sf) . 'B';
}

/**
 * Format a numeric value using the SI prefix system, and round the value to a specified number of decimal places.
 *
 * @param mixed $value The input value to be formatted.
 * @param int   $round The number of decimal places to round the value to. Default is 2.
 * @param int   $sf    The number of significant figures to use in the formatted value. Default is 3.
 *
 * @return string The formatted value with the appropriate SI prefix.
 */
function format_si($value, $round = 2, $sf = 3) {
    $value = clean_number($value);
    if (!is_numeric($value)) {
        print_debug("Passed incorrect value to " . __FUNCTION__ . "()");
        print_debug_vars($value);
        //return FALSE;
        return '0'; // incorrect, but for keep compatibility
    }

    if ($value < 0) {
        $neg   = TRUE;
        $value *= -1;
    } else {
        $neg = FALSE;
    }

    // https://physics.nist.gov/cuu/Units/prefixes.html
    if ($value >= 0.1) {
        $sizes = [ '', 'k', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y' ];
        $ext   = $sizes[0];
        for ($i = 1; (($i < count($sizes)) && ($value >= 1000)); $i++) {
            $value /= 1000;
            $ext   = $sizes[$i];
        }
    } else {
        $sizes = [ '', 'm', 'µ', 'n', 'p', 'f', 'a', 'z', 'y' ];
        $ext   = $sizes[0];
        for ($i = 1; (($i < count($sizes)) && ($value != 0) && ($value <= 0.1)); $i++) {
            $value *= 1000;
            $ext   = $sizes[$i];
        }
    }

    if ($neg) {
        $value *= -1;
    }
    //print_warning("$value " . round($value, $round));

    return format_number_short(round($value, $round), $sf) . $ext;
}

/**
 * Format a value using binary prefixes (k, M, G, T, P, E) and round the value
 * to a specified number of decimal places.
 *
 * @param mixed $value The input value to be formatted.
 * @param int   $round The number of decimal places to round the value to. Default is 2.
 * @param int   $sf    The number of significant figures to use in the formatted value. Default is 3.
 *
 * @return string The formatted value with the appropriate binary prefix.
 */
function format_bi($value, $round = 2, $sf = 3) {
    $value = clean_number($value);
    if (!is_numeric($value)) {
        print_debug("Passed incorrect value to " . __FUNCTION__ . "()");
        print_debug_vars($value);
        //return FALSE;
        return '0'; // incorrect, but for keep compatibility
    }

    if ($value < 0) {
        $neg   = TRUE;
        $value *= -1;
    } else {
        $neg = FALSE;
    }
    $sizes = [ '', 'k', 'M', 'G', 'T', 'P', 'E' ];
    $ext   = $sizes[0];
    for ($i = 1; (($i < count($sizes)) && ($value >= 1024)); $i++) {
        $value /= 1024;
        $ext   = $sizes[$i];
    }

    if ($neg) {
        $value *= -1;
    }

    return format_number_short(round($value, $round), $sf) . $ext;
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function format_number($value, $base = '1000', $round = 2, $sf = 3) {
    if ($base == '1000') {
        return format_si($value, $round, $sf);
    }
    return format_bi($value, $round, $sf);
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function format_value($value, $format = '', $round = 2, $sf = 3) {

    if ($value === TRUE) {
        return 'TRUE';
    }
    if ($value === FALSE) {
        return 'FALSE';
    }
    if (is_null($value)) {
        return 'NULL';
    }
    // Only return "" for truly non-numeric, empty values
    if (safe_empty(trim($value))) {
        return '""';
    }

    switch (strtolower((string)$format)) {
        case 'si':
        case '1000':
            return format_si($value, $round, $sf);

        case 'bi':
        case '1024':
            return format_bi($value, $round, $sf);

        case 'shorttime':
            return format_uptime($value, 'short');

        case 'uptime':
        case 'time':
            return format_uptime($value);

        case 'time60':
            if ($value >= 1440) {
                // Use format uptime for time greater than 1 day
                return format_uptime($value * 60);
            }
            break;
    }

    $num = clean_number($value);
    if (is_numeric($num)) {
        $orig  = $num;
        $value = sprintf("%01.{$round}f", $num);
        if (abs($orig) > 0 && preg_match('/^\-?0\.0+$/', $value)) {
            // prevent show small values as zero
            // ie 0.000627 as 0.00
            //r($orig);
            //r($value);
            //r(format_number_short($orig, $sf));
            return format_number_short($orig, $sf);
        }

        return preg_replace(['/\.0+$/', '/(\.\d)0+$/'], '\1', $value);
    }

    return $value;
}

/**
 * Format a number so that it contains at most $sf significant figures, including at most one decimal point.
 * This version does not use scientific notation.
 *
 * Examples:
 * -  723.42 (sf=3) -> 723
 * -   72.34 (sf=3) -> 72.3
 * -    2.23 (sf=3) -> 2.23
 * - 0.00001 (sf=3) -> 0.000
 *
 * @param float|int|string $num The input number to format.
 * @param int              $sf     The number of significant figures to keep.
 *
 * @return string The formatted number as a string.
 */
function format_number_short($num, $sf) {
    // remove non-numeric chars from end of string
    $number = clean_number($num);
    if (!is_numeric($number)) {
        // passed not numeric, return original
        return $num;
    }
    if (is_intnum($number) || $number === 0.0) {
        return (int)$number ? (string)$number : '0';
    }

    // Next part only for float numbers
    $exponent = floor(log10(abs($number)));
    if (!is_finite($exponent)) {
        // prevent infinite exponent with zeroes
        return '0';
    }

    if ($exponent >= -$sf && $exponent < $sf) {
        $mantissa = $number / (10 ** $exponent);
        $formatted_number = number_format($mantissa * (10 ** $exponent), $sf - $exponent - 1, '.', '');
    } elseif ($exponent < 0) {
        $formatted_number = number_format($number, $sf - $exponent - 1, '.', '');
        // numbers less 0.01
    } else {
        $formatted_number = number_format($number, $sf - 1, '.', '');
    }

    return trim_number($formatted_number);
}

/**
 * Clean commas and non-numeric chars
 *
 * @param mixed $num Numeric value
 * @return float|int|string
 */
function clean_number($num) {
    if (!is_string($num) || is_numeric($num)) {
        return $num;
    }

    // remove non-numeric chars from end of string
    $num = preg_replace('/\D+$/', '', $num);

    // number_format() by default return numbers with comma
    // number_format('7619627.0010', 4) -> 7,619,627.0010
    if (str_contains($num, ',')) {
        //var_dump($a);
        return str_replace(',', '', $num);
    }
    return $num;
}

/**
 * Trim unimportant zeroes from string float number,
 * examples:
 *  1.0 -> 1
 *  1.020 -> 1.02
 *
 * @param float|int|string $number
 *
 * @return float|int|string
 */
function trim_number($number) {
    if (!is_string($number)) {
        return $number;
    }
    $number = clean_number($number);
    return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
}

/**
 * Is Valid Hostname
 *
 * See: http://stackoverflow.com/a/4694816
 *      http://stackoverflow.com/a/2183140
 *
 * The Internet standards (Request for Comments) for protocols mandate that
 * component hostname labels may contain only the ASCII letters 'a' through 'z'
 * (in a case-insensitive manner), the digits '0' through '9', and the hyphen
 * ('-'). The original specification of hostnames in RFC 952, mandated that
 * labels could not start with a digit or with a hyphen, and must not end with
 * a hyphen. However, a subsequent specification (RFC 1123) permitted hostname
 * labels to start with digits. No other symbols, punctuation characters, or
 * white space are permitted. While a hostname may not contain other characters,
 * such as the underscore character (_), other DNS names may contain the underscore
 *
 * @param string $hostname
 * @param bool   $fqdn
 *
 * @return bool
 */
function is_valid_hostname($hostname, $fqdn = FALSE) {
    // Pre-check if hostname is FQDN
    if ($fqdn && !preg_match('/\.(xn\-\-[a-z0-9]{2,}|[a-z]{2,})$/i', $hostname)) {
        return FALSE;
    }

    return (preg_match("/^(_?[a-z\d](-*[_a-z\d])*)(\.(_?[a-z\d](-*[_a-z\d])*))*$/i", $hostname) // valid chars check
            && preg_match("/^.{1,253}$/", $hostname)                                      // overall length check
            && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $hostname));                 // length of each label
}

/**
 * Correct alternative for is_int(), is_integer() and ctype_digit() for validated integer numbers.
 * Work with string and numbers.
 *
 * @param string|mixed $value
 *
 * @return bool
 */
function is_intnum($value) {
    if (!is_numeric($value) || is_float($value)) {
        return FALSE;
    }
    $value = (string)$value;
    if ($value[0] === '-') {
        $value = substr($value, 1);
    } // negative number
    return ctype_digit($value);
}

function is_string_quoted($str, $quote = '"') {
    if (!is_string($str)) {
        return FALSE;
    }

    // If no quote type specified, check for any valid quote
    // if ($quote === null) {
    //     return is_string_quoted($str, '"') || is_string_quoted($str, "'");
    // }

    // Validate quote parameter
    if (!in_array($quote, [ '"', '\"', "'", "\'" ], TRUE)) {
        return FALSE;
    }
    return str_starts_with($str, $quote) && str_ends_with($str, $quote);
}

/**
 * Validate returned values for common parameters like hardware/version/serial/location.
 *
 * @param string $string
 * @param string $type
 *
 * @return bool
 */
function is_valid_param($string, $type = '') {

    // Empty or not string is invalid
    if (!(is_string($string) || is_numeric($string)) || safe_empty($string)) {
        print_debug("Detected empty value for param '$type'.");
        return FALSE;
    }

    // --, **, .., **--.--**
    $poor_default_pattern = '/^[\*\.\-]+$/';
    // single words with possible brackets: <none>, (none), Unknown
    $poor_words_pattern = 'unknown|uninitialized|private|public|default|test|none|unset|not set|not available|default string|none|empty|snmpv2|n/?a|1234567890|0123456789|\?';
    // start/end for a poor words pattern (brackets)
    $poor_pattern_start = '!^[<\\\(]*\s*(';  // <, \, (
    $poor_pattern_end   = ')\s*[>\\\)]*$!i'; // >, \, )

    switch (strtolower($type)) {
        case 'asset_tag':
        case 'hardware':
        case 'vendor':
        case 'serial':
        case 'version':
        case 'revision':
            // extra words for sysLocation
            $poor_snmp_pattern = $poor_pattern_start . $poor_words_pattern . '|sim|No Asset Tag|Tag 12345' . $poor_pattern_end;
            $poor_snmp_contains = [ ' not set', ' not available', 'denied', 'No Such' ];
            $valid = ctype_print($string) &&
                     !(str_istarts($string, [ 'Not Avail', 'Not Specified', 'To be filled by O.E.M.' ]) ||
                       str_icontains_array($string, $poor_snmp_contains) ||
                       preg_match($poor_default_pattern, $string) ||
                       preg_match($poor_snmp_pattern, $string));
            break;

        case 'mac':
            // Note 00:00:00:00:00:00 still valid mac address
            $valid = !safe_empty(mac_zeropad($string));
            break;

        case 'ip':
            $valid = get_ip_version($string);
            break;

        case 'hostname':
        case 'sysname':
            $valid = is_valid_hostname($string);
            break;

        case 'location':
        case 'syslocation':
            // derp cmd for grep locations:
            // egrep -r --include="*.snmprec" '^1.3.6.1.2.1.1.6.0' . | awk -F '|' '{print $3}' | sort | uniq -c | sort -n
            // extra words for sysLocation
            $poor_snmp_pattern = $poor_pattern_start . $poor_words_pattern .
                                 '|office|address|here|location|snmplocation|syslocation|(No|System) Location' .
                                 $poor_pattern_end;
            $poor_snmp_contains = [ ' not set', 'Sitting on the Dock of the Bay', 'Right here, right now', 'edit /etc/snmp/snmpd.conf' ];
            $valid = strlen($string) > 4 && !(str_contains_array($string, $poor_snmp_contains) || preg_match($poor_snmp_pattern, $string));
            break;

        case 'contact':
        case 'syscontact':
            $valid = !(preg_match($poor_default_pattern, $string) ||
                       preg_match($poor_pattern_start . $poor_words_pattern . $poor_pattern_end, $string));
            break;

        case 'sysobjectid':
            $valid = preg_match('/^\.?\d+(\.\d+)*$/', $string);
            break;

        case 'type':
            $valid = array_key_exists($string, (array)$GLOBALS['config']['devicetypes']);
            break;

        case 'port':
        case 'snmp_port':
            // port 0 also valid, but we exclude because it reserved
            $valid = is_intnum($string) && $string > 0 && $string <= 65353;
            break;

        case 'filename':
            $len = strlen($string);
            if ($len > 255 || !$len) {
                $valid = FALSE;
            } else {
                $valid = strpbrk($string, '|\'\\?*&<";:>+[]=/') === FALSE;
            }
            break;

        case 'path':
            $valid = preg_match(OBS_PATTERN_PATH_UNIX, $string);
            if (!$valid) {
                $valid = preg_match(OBS_PATTERN_PATH_WIN, $string);
            }
            break;

        case 'posix_username':
            // strict posix (https://unix.stackexchange.com/questions/157426/what-is-the-regex-to-validate-linux-users):
            // ^[a-z_]([a-z0-9_-]{0,31}|[a-z0-9_-]{0,30}\$)$
            $valid = preg_match('/^[a-z_]([a-z0-9_-]{0,31}|[a-z0-9_-]{0,30}\$)$/', $string);
            break;

        case 'username':
            // posix 32 chars
            // windows/ldap 20 chars
            // pre-windows2000 256 chars
            //$valid = strlen($string) <= 256 && preg_match('/^\w[\w\-\\\\]+\$?$/u', $string); // allow utf8 usernames
            $valid = preg_match('/^[\w!][\w@!#^~+\$\-\.\ \\\\]{0,254}[\w\$]$/u', $string); // allow utf8 usernames
            break;

        case 'password':
            $valid = preg_match('/^[[:print:]]+$/u', $string); // allow any printable utf8
            break;

        case 'timestamp':
            $valid = is_valid_timestamp($string);
            break;

        case 'unixtime':
            $valid = is_valid_unixtime($string);
            break;

        case 'lat':
        case 'latitude':
        case 'location_lat':
            $valid = is_numeric($string) && $string >= -90 && $string <= 90;
            break;

        case 'lon':
        case 'longitude':
        case 'location_lon':
            $valid = is_numeric($string) && $string >= -180 && $string <= 180;
            break;

        case 'snmp_version':
            $valid = preg_match('/^v(1|2c|3)$/', $string);
            break;

        case 'snmp_transport':
            $valid = preg_match('/^(udp|tcp)6?$/', $string);
            break;

        case 'snmp_community':
            // allow all common latin and special chars
            $valid = preg_match('/^[\w\ %!@#\$%\^&\*\(\)_\-\+~`\[\]\{\}\|\\\\<>,\.\/\?;:]{1,32}$/', $string);
            break;

        case 'snmp_timeout':
            $valid = is_intnum($string) && $string > 0 && $string <= 120;
            break;

        case 'snmp_retries':
            $valid = is_intnum($string) && $string > 0 && $string <= 10;
            break;

        case 'snmp_maxrep':
            $valid = is_intnum($string) && $string >= 0 && $string <= 5000;
            break;

        case 'snmp_authlevel':
            $valid = in_array($string, [ 'noAuthNoPriv', 'authNoPriv', 'authPriv' ], TRUE);
            break;

        case 'snmp_authalgo':
            // MD5|SHA|SHA-224|SHA-256|SHA-384|SHA-512
            $valid = preg_match('/^(md5|sha(\-?(224|256|384|512))?)$/i', $string);
            break;

        case 'snmp_authname':
            // The SNMP authname can contain up to 32 characters in length and include any combination of alphanumeric characters (uppercase letters, lowercase letters, and numbers).Spaces not allowed.
            $valid = preg_match('/^[a-z0-9]{1,64}$/i', $string);
            break;

        case 'snmp_cryptoalgo':
            // DES|AES|AES-192|AES-192-C|AES-256|AES-256-C
            $valid = preg_match('/^(des|aes(\-?(192|256)(\-?c)?)?)$/i', $string);
            break;

        case 'snmp_authpass':
        case 'snmp_cryptopass':
            $valid = preg_match('/^\w{8,64}$/', $string); // any string without spaces and special chars
            break;

        default:
            // --, **, .., **--.--**
            $valid = !preg_match($poor_default_pattern, $string);
    }

    if (!$valid) {
        print_debug("Detected invalid value '$string' for param '$type'.");
    }

    return (bool)$valid;
}

/**
 * BOOLEAN safe function to check if hostname resolves as IPv4 or IPv6 address
 *
 * @param string $hostname
 * @param string|array $options all - request any record ipv4/ipv6, ipv4 or a - only ipv4, ipv6 or aaaa - only ipv6
 *
 * @return bool
 */
function is_domain_resolves($hostname, $options = 'all') {
    return (is_valid_hostname($hostname) && gethostbyname6($hostname, $options));
}

/**
 * Get host record from /etc/hosts
 *
 * @param string $host Hostname for resolve
 * @param string|array $options all - request any record ipv4/ipv6, ipv4 or a - only ipv4, ipv6 or aaaa - only ipv6
 *
 * @return string|false
 */
function ip_from_hosts($host, $options = 'all') {
    $host = strtolower($host);

    $try_a    = array_value_exist($options, [ 'all', 'ipv4', 'a' ]);
    $try_aaaa = array_value_exist($options, [ 'all', 'ipv6', 'aaaa' ]);

    if (OBS_DEBUG > 1) {
        if ($try_a && $try_aaaa) {
            $debug_msg = 'IPv4/IPv6';
        } elseif ($try_a) {
            $debug_msg = 'IPv4 only';
        } else {
            $debug_msg = 'IPv6 only';
        }
        print_cli("Try resolve '$host' in /etc/hosts as $debug_msg..\n");
    }

    try {
        foreach (new SplFileObject('/etc/hosts') as $line) {
            // skip empty and comments
            if (str_contains($line, '#')) {
                // remove inline comments
                $line = explode('#', $line, 2)[0];
            }
            $line = trim($line);
            if (safe_empty($line)) {
                continue;
            }

            $hosts = preg_split('/\s/', strtolower($line), -1, PREG_SPLIT_NO_EMPTY);

            //print_debug_vars($hosts);
            $ip = array_shift($hosts);
            //$hosts = array_map('strtolower', $d);
            if (in_array($host, $hosts, TRUE)) {
                if (($try_a && str_contains($ip, '.')) ||
                    ($try_aaaa && str_contains($ip, ':'))) {

                    print_debug("Host '$host' found in /etc/hosts: $ip");
                    return $ip;
                }
            }
        }
    } catch (Exception $e) {
        print_warning("Could not open the file /etc/hosts! This file should be world readable, also check that SELinux is not in enforcing mode.");
    }

    return FALSE;
}

/**
 * Same as gethostbyname(), but work with both IPv4 and IPv6.
 * Get the IPv4 or IPv6 address corresponding to a given Internet hostname.
 * By default, return IPv4 address (A record) if exist,
 * else IPv6 address (AAAA record) if exist.
 * For get only IPv6 record use gethostbyname6($hostname, 'ipv6').
 *
 * @param string       $host
 * @param string|array $options all - request any record ipv4/ipv6, ipv4 or a - only ipv4, ipv6 or aaaa - only ipv6
 *
 * @return false|mixed
 */
function gethostbyname6($host, $options = 'all') {
    // get AAAA record for $host
    // if option ipv4 is set and AAAA fails, it tries for A
    // the first match found is returned
    // otherwise returns FALSE

    if ($dns = gethostbynamel6($host, $options, TRUE)) {
        return array_shift($dns);
    }

    return FALSE;
}

/**
 * Same as gethostbynamel(), but work with both IPv4 and IPv6.
 * By default, returns both IPv4/IPv6 addresses (A and AAAA records),
 * for get only IPv6 addresses use gethostbynamel6($hostname, 'ipv6').
 *
 * @param string       $host
 * @param string|array $options all - request any record ipv4/ipv6, ipv4 or a - only ipv4, ipv6 or aaaa - only ipv6
 * @param bool         $first   // Return first found record, for gethostbyname6()
 *
 * @return array|false
 */
function gethostbynamel6($host, $options = 'all', $first = FALSE) {

    $try_a      = array_value_exist($options, [ 'all', 'ipv4', 'a' ]);
    $try_aaaa   = array_value_exist($options, [ 'all', 'ipv6', 'aaaa' ]);

    // get AAAA records for $host,
    // if $try_a is true, if AAAA fails, it tries for A
    // results are returned in an array of ips found matching type
    // otherwise returns FALSE

    $ip6 = [];
    $ip4 = [];

    if ($try_a) {
        // First try /etc/hosts (v4)
        $etc4 = ip_from_hosts($host, 'ipv4');
        if ($etc4) {
            $ip4[] = $etc4;

            if ($first) {
                return $ip4;
            }
        }

        // Second try /etc/hosts (v6)
        $etc6 = $try_aaaa ? ip_from_hosts($host, 'ipv6') : FALSE;
        if ($etc6) {
            $ip6[] = $etc6;

            if ($first) {
                return $ip6;
            }
        }

        // Separate A and AAAA queries, see: https://www.mail-archive.com/observium@observium.org/msg09239.html
        $dns = dns_get_record($host, DNS_A);
        print_debug_vars($dns);
        if (!is_array($dns)) {
            $dns = [];
        }

        // Request AAAA record (when requested only first record and A record exists, skip)
        if ($try_aaaa && !($first && count($dns))) {
            $dns6 = dns_get_record($host, DNS_AAAA);
            print_debug_vars($dns6);
            if (is_array($dns6)) {
                $dns = array_merge($dns, $dns6);
            }
        }
    } elseif ($try_aaaa) {
        // First try /etc/hosts (v6)
        $etc6 = ip_from_hosts($host, 'ipv6');
        if ($etc6) {
            $ip6[] = $etc6;

            if ($first) {
                return $ip6;
            }
        }
        $dns = dns_get_record($host, DNS_AAAA);
        print_debug_vars($dns);
    } else {
        // Not A or AAAA record requested
        return FALSE;
    }

    foreach ($dns as $record) {
        switch ($record['type']) {
            case 'A':
                $ip4[] = $record['ip'];
                break;
            case 'AAAA':
                $ip6[] = $record['ipv6'];
                break;
        }
    }

    if ($try_a && count($ip4)) {
        // Merge ipv4 & ipv6
        $ip6 = array_merge($ip4, $ip6);
    }

    if (count($ip6)) {
        return $ip6;
    }

    return FALSE;
}

/**
 * Get the Internet hostname corresponding to a given IP address.
 * Support IPv4 and IPv6.
 *
 * @param string $ip IPv4/IPv6 address
 *
 * @return string|false PTR name or FALSE
 */
function gethostbyaddr6($ip) {

    $resolver = new Net_DNS2_Resolver();
    try {
        if ($response = $resolver->query($ip, 'PTR')) {
            return $response->answer[0]->ptrdname;
        }
    } catch (Net_DNS2_Exception $e) {
        print_debug("gethostbyaddr6($ip) failed: " . $e->getMessage() . PHP_EOL);
    }

    return FALSE;
}

function elapsed_time($microtime_start, $precision = NULL) {
    $time_elapsed = microtime(TRUE) - $microtime_start;

    return is_numeric($precision) ? round($time_elapsed, $precision) : $time_elapsed;
}

/**
 * Return named unix times from now.
 * Note, 'now' static unixtime over process run.
 * For undate run get_time('new')
 *
 * @param string $period Named time from now, ie: fiveminute, twelvehour, year
 * @param bool   $future If TRUE, return unix time in the future.
 * @param bool   $gmt    Return time in GMT UTC+0 time zone. This not stored in config
 *
 * @return int
 */
function get_time($period = 'now', $future = FALSE, $gmt = FALSE) {
    global $config;

    /*
    $config['time']['fiveminute'] = $config['time']['now'] - 300;      //time() - (5 * 60);
    $config['time']['fourhour']   = $config['time']['now'] - 14400;    //time() - (4 * 60 * 60);
    $config['time']['sixhour']    = $config['time']['now'] - 21600;    //time() - (6 * 60 * 60);
    $config['time']['twelvehour'] = $config['time']['now'] - 43200;    //time() - (12 * 60 * 60);
    $config['time']['day']        = $config['time']['now'] - 86400;    //time() - (24 * 60 * 60);
    $config['time']['twoday']     = $config['time']['now'] - 172800;   //time() - (2 * 24 * 60 * 60);
    $config['time']['week']       = $config['time']['now'] - 604800;   //time() - (7 * 24 * 60 * 60);
    $config['time']['twoweek']    = $config['time']['now'] - 1209600;  //time() - (2 * 7 * 24 * 60 * 60);
    $config['time']['month']      = $config['time']['now'] - 2678400;  //time() - (31 * 24 * 60 * 60);
    $config['time']['twomonth']   = $config['time']['now'] - 5356800;  //time() - (2 * 31 * 24 * 60 * 60);
    $config['time']['threemonth'] = $config['time']['now'] - 8035200;  //time() - (3 * 31 * 24 * 60 * 60);
    $config['time']['sixmonth']   = $config['time']['now'] - 16070400; //time() - (6 * 31 * 24 * 60 * 60);
    $config['time']['year']       = $config['time']['now'] - 31536000; //time() - (365 * 24 * 60 * 60);
    $config['time']['twoyear']    = $config['time']['now'] - 63072000; //time() - (2 * 365 * 24 * 60 * 60);
    $config['time']['threeyear']  = $config['time']['now'] - 94608000; //time() - (3 * 365 * 24 * 60 * 60);
    */

    $period = empty($period) ? 'now' : strtolower(trim($period));

    // Set times needed by loads of scripts
    if ($gmt) {
        // UTC+0
        $time = gmdate('U');
    } else {
        // System timezone (default)
        if ($period === 'new') {
            $time = time();
        } else {
            $time = $config['time']['now'] ?? time();
        }
        $config['time']['now'] = $time;
    }

    if ($period === 'now' || $period === 'new') {
        return (int)$time;
    }

    $multipliers = [
        'one'   => 1, 'two'   => 2, 'three' => 3, 'four' => 4,  'five'   => 5,  'six'    => 6,
        'seven' => 7, 'eight' => 8, 'nine'  => 9, 'ten'  => 10, 'eleven' => 11, 'twelve' => 12
    ];
    $times = [
        'year'   => 31536000,
        'month'  => 2678400,
        'week'   => 604800,
        'day'    => 86400,
        'hour'   => 3600,
        'minute' => 60
    ];
    $time_pattern = '/^(?<multiplier>' . implode('|', array_keys($multipliers)) . '|\d+)?(?<time>' . implode('|', array_keys($times)) . ')s?$/';
    //r($time_pattern);
    if (preg_match($time_pattern, $period, $matches)) {
        $multiplier = $multipliers[$matches['multiplier']] ?? (is_numeric($matches['multiplier']) ? $matches['multiplier'] : 1);

        $diff = $multiplier * $times[$matches['time']];

        if ($future) {
            $time += $diff;
        } else {
            $time -= $diff;
        }
    }

    return (int)$time;
}

/**
 * Return named unix times from now.
 * Note, 'now' static unixtime over process run.
 *
 * Always return UTC+0 times
 *
 * @param string $period Named time from now, ie: fiveminute, twelvehour, year
 * @param bool   $future If TRUE, return unix time in the future.
 *
 * @return int
 */
function get_time_gmt($period = 'now', $future = FALSE) {
    return get_time($period, $future, TRUE);
}

/**
 * Validate if the given string is a valid timestamp in 'Y-m-d H:i:s' format.
 *
 * @param string $timestamp
 * @return bool
 */
function is_valid_timestamp($timestamp) {
    $format = 'Y-m-d H:i:s';
    $d = DateTime::createFromFormat($format, $timestamp);
    return $d && $d->format($format) === $timestamp;
}

/**
 * Validate if the given string is a valid Unix timestamp.
 *
 * @param string|int $unixtime
 * @return bool
 */
function is_valid_unixtime($unixtime) {
    if (!is_numeric($unixtime)) {
        return FALSE;
    }

    // Minimum & Maximum possible unixtime, only for validate passed unixtime
    //const OBS_MIN_UNIXTIME = 504921600;       // 01/01/1986 @ 12:00am (UTC), no network devices produced before this date :)
    //const OBS_MAX_UNIXTIME = 7258118400;      // 01/01/2200 @ 12:00am (UTC), a message to descendants if you are still using Observium in the 23rd century :)

    return ($unixtime >= 504921600 && $unixtime <= 7258118400);
}

/**
 * Format date string.
 * If passed 'now' return formatted current datetime.
 *
 * This function converts date/time string to format from
 * config option $config['timestamp_format'].
 * If date/time is not detected in string, function return original string.
 * Example conversions to format 'd-m-Y H:i':
 * '2012-04-18 14:25:01' -> '18-04-2012 14:25'
 * 'Star wars' -> 'Star wars'
 *
 * @param string $str string need formatted as timestamp
 * @param string $format custom format for timestamp, default is $config['timestamp_format']
 *
 * @return string
 */
// TESTME needs unit testing
function format_timestamp($str = 'now', $format = NULL) {

    if ($str === 'now') {
        // Use for get formatted current time
        $timestamp = get_time($str);
    } elseif (is_valid_unixtime($str)) {
        // seems as unixtime
        return format_unixtime($str, $format);
    } elseif (($timestamp = strtotime($str)) === FALSE) {
        return $str;
    }

    // FIXME. timezone?!

    // if format not forced, use the config option
    $format = empty($format) ? $GLOBALS['config']['timestamp_format'] : (string)$format;

    return date($format, $timestamp);
}

/**
 * Format unixtime.
 *
 * This function convert unixtime string to format from
 * config option $config['timestamp_format'].
 * Can take an optional format parameter, which is passed to date();
 *
 * @param string $time   Unixtime in seconds since the Unix Epoch (also allowed microseconds)
 * @param string $format Common date format
 *
 * @return string
 */
// TESTME needs unit testing
function format_unixtime($time, $format = NULL) {

    [ $sec, $usec ] = explode('.', (string)$time);
    if (!safe_empty($usec)) {
        $date = date_create_from_format('U.u', number_format($time, 6, '.', ''));
    } else {
        $date = date_create_from_format('U', $sec);
    }

    // If something wrong with create data object, just return empty string (and yes, we never use zero unixtime)
    if (!$date || $time == 0) {
        return '';
    }

    // Set correct timezone
    $tz = get_timezone();
    //r($tz);
    try {
        $date_timezone = new DateTimeZone(str_replace(':', '', $tz['php']));
        //$date_timezone = new DateTimeZone($tz['php_name']);
        $date->setTimeZone($date_timezone);
    } catch (Throwable $throwable) {
        print_debug($throwable -> getMessage());
    }
    //r($date);

    // if format not forced, use the config option
    $format = empty($format) ? $GLOBALS['config']['timestamp_format'] : (string)$format;

    return date_format($date, (string)$format);
}

/**
 * Reformat US-based dates to display based on $config['date_format']
 *
 * Supported input formats:
 *   DD/MM/YYYY
 *   DD/MM/YY
 *
 * Handling of YY -> YYYY years is passed on to PHP's strtotime, which
 * is currently cut off at 1970/2069.
 *
 * @param string $date Erroneous date format
 *
 * @return string $date
 */
function reformat_us_date($date) {

    $date = trim($date);
    if (preg_match('!^\d{1,2}/\d{1,2}/(\d{2}|\d{4})$!', $date)) {
        // Only date
        $format = $GLOBALS['config']['date_format'];
    } elseif (preg_match('!^\d{1,2}/\d{1,2}/(\d{2}|\d{4})\s+\d{1,2}:\d{1,2}(:\d{1,2})?$!', $date)) {
        // Date + time
        $format = $GLOBALS['config']['timestamp_format'];
    } else {
        return $date;
    }

    return date($format, strtotime($date));
}

/**
 * This function convert human written Uptime to seconds.
 * Opposite function for format_uptime().
 *
 * Also, applicable for some uptime formats in MIB, like EigrpUpTimeString:
 *  'hh:mm:ss', reflecting hours, minutes, and seconds
 *  If the uptime is greater than 24 hours, is less precise and
 *  the minutes and seconds are not reflected. Instead, only the days
 *  and hours are shown and the string will be formatted like this: 'xxxdxxh'
 *
 * @param string $uptime Uptime in human readable string or timetick
 *
 * @return int Uptime in seconds
 */
function uptime_to_seconds($uptime) {
    if (str_contains($uptime, 'Wrong Type')) {
        // Wrong Type (should be Timeticks): 1632295600
        return timeticks_to_sec($uptime);
    }

    if (str_contains($uptime, ':') && !preg_match('/[a-zA-Z]/', $uptime)) {
        // timeticks, but exclude strings: 315 days18:50:04
        return timeticks_to_sec($uptime);
    }

    $uptime = preg_replace('/^[a-z]+ */i', '', $uptime); // Clean "up" string

    return age_to_seconds($uptime);
}

/**
 * Convert age string to seconds.
 *
 * This function converts age string to seconds.
 * If age is numeric, then it is in seconds.
 * The supplied age accepts values such as 31d, 240h, 1.5d etc.
 * Accepted age scales are:
 * y (years), M (months), w (weeks), d (days), h (hours), m (minutes), s (seconds), ms (milliseconds).
 * NOTE, for month use CAPITAL 'M'
 * With wrong and negative returns 0
 *
 * '3y 4M 6w 5d 3h 1m 3s' -> 109191663
 * '184 days 22 hrs 02 min 38 sec' ->
 * '3y4M6w5d3h1m3s'       -> 109191663
 * '1.5w'                 -> 907200
 * -886732     -> 0
 * 'Star wars' -> 0
 *
 * @param string|int $age
 * @param bool $float When TRUE, return value as float
 *
 * @return int|float
 */
// TESTME needs unit testing
function age_to_seconds($age, $float = FALSE) {
    $age = trim($age);

    // Empty string check
    if (empty($age)) {
        return 0;
    }

    // Handle pure numeric input
    if (is_numeric($age)) {
        if ($age > 0) {
            return $float ? (float)$age : (int)$age;
        }
        return 0;
    }

    $seconds = 0;

    // Check if string contains hh:mm:ss or hh:mm:ss.ms format
    $hms_pattern = '/(\d{1,2}):(\d{1,2}):(\d{1,2}(?:\.\d+)*)/';
    if (preg_match($hms_pattern, $age, $matches)) {
        $seconds += (int)$matches[1] * 3600 + (int)$matches[2] * 60 + (float)$matches[3];
        // Remove the matched time from the string to avoid double-counting
        $age = preg_replace($hms_pattern, '', $age, 1);
    }

    // Parse milliseconds first (at end of string only) to avoid conflict with minutes
    $ms_pattern = '/(\d+(?:\.\d+)*)\s*(?:[mM][iI][lL][lL][iI][sS][eE][cC][oO][nN][dD]\(?[sS]?\)?|[mM][sS](?:[eE][cC])?)$/';
    if (preg_match($ms_pattern, $age, $matches)) {
        $seconds += (float)$matches[1] * 0.001;
        // Remove matched milliseconds from string
        $age = preg_replace($ms_pattern, '', $age, 1);
    }

    // Time unit conversion rates
    $units = [
        'years'   => [ 'scale' => 31536000, 'pattern' => '(?:[yY][eE][aA][rR]\(?[sS]?\)?|[yY])' ],
        'months'  => [ 'scale' => 2628000,  'pattern' => '(?:[mM][oO][nN][tT][hH]\(?[sS]?\)?|M)' ],
        'weeks'   => [ 'scale' => 604800,   'pattern' => '(?:[wW][eE][eE][kK]\(?[sS]?\)?|[wW])' ],
        'days'    => [ 'scale' => 86400,    'pattern' => '(?:[dD][aA][yY]\(?[sS]?\)?|[dD])' ],
        'hours'   => [ 'scale' => 3600,     'pattern' => '(?:[hH](?:[oO][uU])?[rR]\(?[sS]?\)?|[hH])' ],
        'minutes' => [ 'scale' => 60,       'pattern' => '(?:[mM][iI][nN][uU][tT][eE]\(?[sS]?\)?|[mM][iI][nN]|m)' ],
        'seconds' => [ 'scale' => 1,        'pattern' => '(?:[sS][eE][cC][oO][nN][dD]\(?[sS]?\)?|[sS][eE][cC]|[sS])' ],
    ];

    // Extract all time components
    foreach ($units as $def) {
        // Pattern to match: number + optional space + unit + optional separator (comma, space, colon)
        $pattern = '/(\d+(?:\.\d+)*)\s*' . $def['pattern'] . '[,\s:]*/';
        if (preg_match($pattern, $age, $matches)) {
            $seconds += (float)$matches[1] * $def['scale'];
        }
    }

    // Validate that we actually parsed something
    if ($seconds <= 0) {
        return 0;
    }

    return $float ? (float)$seconds : (int)$seconds;
}

/**
 * Convert age string to unixtime.
 *
 * This function convert age string to unixtime.
 *
 * Description and notes same as for age_to_seconds()
 *
 * Additional check if $age more than minimal age in seconds
 *
 * '3y 4M 6w 5d 3h 1m 3s' -> time() - 109191663
 * '3y4M6w5d3h1m3s'       -> time() - 109191663
 * '1.5w'                 -> time() - 907200
 * -886732     -> 0
 * 'Star wars' -> 0
 *
 * @param string|int|float $age
 * @param string|int $min_age
 *
 * @return int
 */
// TESTME needs unit testing
function age_to_unixtime($age, $min_age = 1) {
    $age = age_to_seconds($age);
    if ($age >= $min_age) {
        return time() - $age;
    }
    return 0;
}

/**
 * Convert an variable to base64 encoded string
 *
 * This function converts any array or other variable to encoded string
 * which can be used in urls.
 * Can use serialize and json(default) methods.
 *
 * NOTE. In PHP < 5.4 json converts UTF-8 characters to Unicode escape sequences
 * also json rounds float numbers (98172397.1234567890 ==> 98172397.123457)
 *
 * @param mixed  $var
 * @param string $method
 *
 * @return string
 */
function var_encode($var, $method = 'json')
{
    switch ($method) {
        case 'serialize':
            $string = base64_encode(serialize($var));
            break;
        default:
            //$tmp = json_encode($var, OBS_JSON_ENCODE);
            //echo PHP_EOL . 'precision = ' . ini_get('precision') . "\n";
            //echo 'serialize_precision = ' . ini_get('serialize_precision');
            //echo("\n---\n"); var_dump($var); echo("\n---\n"); var_dump($tmp);
            $string = base64_encode(json_encode($var, OBS_JSON_ENCODE));
            break;
    }
    return $string;
}

/**
 * Decode an previously encoded string by var_encode() to original variable
 *
 * This function converts base64 encoded string to original variable.
 * Can use serialize and json(default) methods.
 * If json/serialize not detected returns original var
 *
 * NOTE. In PHP < 5.4 json converts UTF-8 characters to Unicode escape sequences,
 * also json rounds float numbers (98172397.1234567890 ==> 98172397.123457)
 *
 * @param string $string
 * @param string $method
 *
 * @return mixed
 */
function var_decode($string, $method = 'json') {

    if (!is_string($string)) {
        // Decode only string vars
        return $string;
    }

    if ((strlen($string) % 4) > 0) {
        // BASE64 length must be multiple by 4
        return $string;
    }

    $value = base64_decode($string, TRUE);
    if ($value === FALSE) {
        // This is not base64 string, return original var
        return $string;
    }

    switch ($method) {
        case 'serialize':
        case 'unserialize':
            if ($value === 'b:0;') {
                return FALSE;
            }
            $decoded = safe_unserialize($value);
            if ($decoded !== FALSE) {
                // Serialized encoded string detected
                return $decoded;
            }
            break;
        default:
            if ($string === 'bnVsbA==') {
                return NULL;
            }
            if (OBS_JSON_DECODE > 0) {
                $decoded = @json_decode($value, TRUE, 512, OBS_JSON_DECODE);
            } else {
                // Prevent to broke on old php (5.3), where supported only 3 params
                $decoded = @json_decode($value, TRUE, 512);
            }
            switch (json_last_error()) {
                case JSON_ERROR_STATE_MISMATCH:
                case JSON_ERROR_SYNTAX:
                    // Critical json errors, return original string
                    break;
                case JSON_ERROR_NONE:
                default:
                    if ($decoded !== NULL) {
                        // JSON encoded string detected
                        return $decoded;
                    }
            }
            break;
    }

    // In all other cases return original var
    return $string;
}

/**
 * @param mixed $var
 * @param mixed $true
 *
 * @return bool
 */
function get_var_true($var, $true = NULL) {
    if (is_string($var)) {
        $var = strtolower($var);
    }
    return $var === '1' || $var === 1 ||
           $var === 'on' || $var === 'yes' || $var === 'true' ||
           $var === TRUE ||
           // allow extra param for true, ie confirm
           (!empty($true) && $var === $true);
}

/**
 * @param mixed $var
 * @param mixed $false
 *
 * @return bool
 */
function get_var_false($var, $false = NULL) {
    if (is_string($var)) {
        $var = strtolower($var);
    }
    return $var === '0' || $var === 0 ||
           $var === 'off' || $var === 'no' || $var === 'false' ||
           $var === FALSE || (is_null($false) && $var === NULL) || // FIXME. I not sure about null, because it's here as alternative for isset
           // allow extra param for false, ie confirm
           (!empty($false) && $var === $false);
}

/**
 * Convert CSV like string to array or keep as is if not csv.
 *
 * @param mixed $str     String probably CSV list or encoded.
 * @param bool  $encoded If TRUE and string is encoded (by var_encode()) decode it
 *
 * @return array|mixed
 */
function get_var_csv($str, $encoded = FALSE) {

    if (!is_string($str)) {
        // If variable already array, keep as is
        return $str;
    }

    // Better to understand quoted vars
    $values = str_getcsv($str);

    if (count($values) === 1) {
        // not comma list, but can be quoted value
        $values = $values[0];

        // Try to decode var if original value not csv
        if ($encoded && $str === $values) {
            return var_decode($str);
        }
    }

    return var_comma_safe($values);
}

function var_comma_safe($value) {

    if (is_array($value)) {
        foreach ($value as &$entry) {
            $entry = var_comma_safe($entry);
        }
        return $value;
    }

    if (str_contains($value, '%1F')) {
        $value = str_replace('%1F', ',', $value); // %1F (US, unit separator) - not defined in HTML 4 standard
    }
    return $value;
}

/**
 * Parse CSV files with or without header, and return a multidimensional array
 *
 * @param string $content
 * @param bool $has_header
 * @param string $separator
 *
 * @return array
 */
function parse_csv($content, $has_header = TRUE, $separator = ",") {
    $lines  = explode("\n", $content);
    $lines  = array_filter(array_map('trim', $lines), 'strlen'); // clean empty lines

    # If the CSV file has a header, load up the titles into $headers
    if ($has_header) {
        $header  = array_shift($lines);
        $headers = array_map('trim', str_getcsv($header, $separator));
        //print_vars($headers);
    }

    # Process every line
    $result = [];
    foreach ($lines as $line) {
        $csv = array_map('trim', str_getcsv($line, $separator));
        //print_vars($csv);
        if ($has_header) {
            $result[] = array_combine($headers, $csv);
        } else {
            $result[] = $csv;
        }
    }

    return $result;
}

/**
 * Parse number with units to numeric.
 *
 * This function converts numbers with units (e.g. 100MB) to their value
 * in bytes (e.g. 104857600).
 *
 * @param string $str
 * @param int $unit_base Use custom rigid unit base (1000 or 1024)
 *
 * @return float
 */
function unit_string_to_numeric($str, $unit_base = NULL) {
    $value = is_string($str) ? trim($str) : $str;

    // If it's already a number, return original value
    if (is_numeric($value)) {
        return (float)$value;
    }
    // Any not numeric values return as is (array, booleans)
    if (!is_string($value)) {
        return $str;
    }

    //preg_match('/(\d+\.?\d*)\ ?(\w+)/', $str, $matches);
    $pattern = '/^(?<number>\d+\.?\d*)\ ?(?<prefix>[kmgtpezy]i?)?(?<unit>[a-z]*)$/i';
    preg_match($pattern, $value, $matches);

    // Error, return original string
    if (!is_numeric($matches['number'])) {
        return $str;
    }

    // Unit base 1000 or 1024
    $prefix_len = strlen($matches['prefix']);
    $any_unit   = FALSE;
    if (in_array($unit_base, [ 1000, 1024 ])) {
        // Use rigid unit base, this interprets any units with hard multiplier base
        $base = (int)$unit_base;
        // Convert any unit, ie 17.3kVA,
        $any_unit = $base === 1000 && $prefix_len === 1;
    } elseif ($prefix_len === 2) {
        // IEC prefixes Ki, Gi, Ti, etc
        $base = 1024;
    } else {
        switch ($matches['unit']) {
            case '':
            case 'B':
            case 'Byte':
            case 'byte':
                $base = 1024;
                break;
            case 'b':
            case 'Bps':
            case 'bit':
            case 'bps':
                $base = 1000;
                break;
            default:
                $base = 1024;
        }
    }

    // https://en.wikipedia.org/wiki/Binary_prefix
    $prefixes = [
        //'b' => 0,
        'k' => 1, 'ki' => 1,
        'm' => 2, 'mi' => 2,
        'g' => 3, 'gi' => 3,
        't' => 4, 'ti' => 4,
        'p' => 5, 'pi' => 5,
        'e' => 6, 'ei' => 6,
        'z' => 7, 'zi' => 7,
        'y' => 8, 'yi' => 8
    ];

    $power = 0;
    if ($prefix_len) {
        $prefix = strtolower($matches['prefix']);
        if (isset($prefixes[$prefix])) {
            $power = $prefixes[$prefix];
        } else {
            // incorrect prefixes, return original value
            return $str;
        }
    }
    switch ($matches['unit']) {
        case '':
        case 'B':
        case 'Byte':
        case 'Bytes':
        case 'byte':
        case 'bytes':
            $base = $base ?? 1024;
            break;

        case 'b':
        case 'Bps':
        case 'bit':
        case 'bits':
        case 'bps':
            $base = $base ?? 1000;
            break;

        default:
            if (!$any_unit) {
                // unknown unit, return original value
                return $str;
            }
    }

    return (float)($matches[1] * fpow($base, $power));
}

/**
 * Generate Unique ID from string, based on crc32b hash. This ID unique for specific string and not changed over next call.
 *
 * @param string $string String
 *
 * @return int Unique ID
 */
function string_to_id($string)
{
    return hexdec(hash("crc32b", $string));
}

/**
 * Replace all newlines in string to space char (except string begin and end)
 *
 * @param string $string Input string
 *
 * @return string Output string without NL characters
 */
function nl2space($string)
{
    if (!is_string($string) || $string == '') {
        return $string;
    }

    $string = trim($string, "\n\r");
    return preg_replace('/ ?(\r\n|\r|\n) ?/', ' ', $string);
}

/**
 * This noob function replaces windows/mac newline character to unix newline
 *
 * @param string $string Input string
 *
 * @return string Clean output string
 */
function nl2nl($string)
{
    if (!is_string($string) || $string == '') {
        return $string;
    }

    return preg_replace('/\r\n|\r/', PHP_EOL, $string);
}

/**
 * Microtime
 *
 * This function returns the current Unix timestamp seconds, accurate to the
 * nearest microsecond.
 *
 * @return float
 */
function utime()
{
    return microtime(TRUE);
}


/**
 * Bitwise checking if flags set
 *
 * Examples:
 *  if (is_flag_set(FLAG_A, some_var)) // eg: some_var = 0b01100000000010
 *  if (is_flag_set(FLAG_A | FLAG_F | FLAG_L, some_var)) // to check if at least one flag is set
 *  if (is_flag_set(FLAG_A | FLAG_J | FLAG_M | FLAG_D, some_var, TRUE)) // to check if all flags are set
 *
 * @param int  $flag  Checked flags
 * @param int  $param Parameter for checking
 * @param bool $all   Check all flags
 *
 * @return bool
 */
function is_flag_set($flag, $param, $all = FALSE)
{
    $set = $flag & $param;

    if ($set and !$all) {
        return TRUE;
    } // at least one of the flags passed is set
    elseif ($all and ($set == $flag)) {
        return TRUE;
    } // to check that all flags are set

    return FALSE;
}

/**
 * Return a file extension when it exists on a system, limited extensions (default is svg and png).
 *
 * @param string $file
 * @param array|string $ext_list
 * @return false|string
 */
function is_file_ext($file, $ext_list = [ 'svg', 'png' ]) {

    $key = 'is_file_ext|' . md5($file);
    if (mem_cache_exists($key)) {
        // reduce is_file() calls
        $ext = mem_cache_get($key);
        if (in_array($ext, (array)$ext_list, TRUE)) {
            return $ext;
        }
    }

    foreach ((array)$ext_list as $ext) {
        if (is_file($file . ".$ext")) {
            //if ($ext === 'svg') { r($file . ".$ext"); }
            mem_cache_set($key, $ext);
            return $ext;
        }
    }
    return FALSE;
}

/**
 * Checks if a string is composed solely of lower case letters.
 * Primarily used to sanitise strings used for file inclusion
 *
 * @param $string
 *
 * @return false|int
 */
function is_alpha($string)
{
    return preg_match(OBS_PATTERN_ALPHA, $string);
}

/**
 * Convert "smart quotes" to real quotes and emdash into a hyphen.
 *
 * @url https://stackoverflow.com/questions/1262038/how-to-replace-microsoft-encoded-quotes-in-php
 * @param string $string
 *
 * @return string
 */
function smart_quotes($string) {
    if (!is_string($string)) {
        return $string;
    }

    $quotes = [
      "\xC2\xAB"     => '"', // « (U+00AB) in UTF-8
      "\xC2\xBB"     => '"', // » (U+00BB) in UTF-8
      "\xE2\x80\x98" => "'", // ‘ (U+2018) in UTF-8
      "\xE2\x80\x99" => "'", // ’ (U+2019) in UTF-8
      "\xE2\x80\x9A" => "'", // ‚ (U+201A) in UTF-8
      "\xE2\x80\x9B" => "'", // ‛ (U+201B) in UTF-8
      "\xE2\x80\x9C" => '"', // “ (U+201C) in UTF-8
      "\xE2\x80\x9D" => '"', // ” (U+201D) in UTF-8
      "\xE2\x80\x9E" => '"', // „ (U+201E) in UTF-8
      "\xE2\x80\x9F" => '"', // ‟ (U+201F) in UTF-8
      "\xE2\x80\xB9" => "'", // ‹ (U+2039) in UTF-8
      "\xE2\x80\xBA" => "'", // › (U+203A) in UTF-8
      "\xE2\x80\xB2" => "'", // ′ (U+2032) in UTF-8
      "\xE2\x80\xB3" => '"', // ″ (U+2033) in UTF-8
      // dashes
      "\xE2\x80\x94" => '-', // — (U+2014) in UTF-8
      "\xEF\xB9\x98" => '-', // ﹘ (U+FE58) in UTF-8
    ];

    return strtr($string, $quotes);
}

/**
 * Escape string for JSON output, with smart quotes normalisation
 * https://stackoverflow.com/questions/7462394/php-json-string-escape-double-quotes-for-js-output
 *
 * @param string $str String to escape
 * @return string Escaped string without surrounding quotes
 */
function json_escape($str) {
    if (!is_string($str)) {
        return $str;
    }

    // Normalise smart quotes from Word/etc before JSON encoding
    $str = smart_quotes($str);

    // Encode to JSON
    $encoded = safe_json_encode($str);

    // Check if encoding was successful
    if (!is_string($encoded) || $encoded === '') {
        // Fallback: return normalised string
        return $str;
    }

    // Remove surrounding quotes that json_encode adds to strings
    if (str_starts_with($encoded, '"') && str_ends_with($encoded, '"')) {
        return substr($encoded, 1, -1);
    }

    return $encoded;
}

/**
 * Apply json_escape to array values, excluding specified keys
 *
 * @param array $array Array to process (modified by reference)
 * @param array $skip_keys Keys to skip from escaping (optional)
 * @return array Reference to the modified array
 */
function array_json_escape(&$array, $skip_keys = []) {
    if (!is_array($array)) {
        return $array;
    }

    $escaped = FALSE;
    foreach ($array as $key => &$value) {
        if (!in_array($key, $skip_keys, TRUE) && is_string($value)) {
            $value = json_escape($value);
            $escaped = TRUE;
        } elseif (OBS_DEBUG > 1) {
            print_debug("DEBUG: array_json_escape() skipped key '$key'");
        }
    }
    unset($value); // Break the reference after foreach

    if ($escaped && OBS_DEBUG > 1) {
        print_debug("DEBUG: array_json_escape() escaped array:");
        print_debug_vars($array);
    }
    return $array;
}

function safe_json_encode($var, $options = 0) {
    $options |= OBS_JSON_ENCODE; // JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION

    $str = @json_encode($var, $options);

    if (OBS_DEBUG && json_last_error() !== JSON_ERROR_NONE) {
        print_message('JSON ENCODE[%r' . json_last_error_msg() . '%n]');
        echo "JSON VAR[\n" . print_vars($var) . "\n]\n";
    }

    return $str;
}

function safe_json_decode($str, $options = 0) {
    if (!is_string($str)) {
        // When not string passed return original variable,
        // This is different from json_decode() do, but better for us
        if (OBS_DEBUG) {
            print_message('JSON DECODE[%rNot string passed%n]');
            echo 'JSON RAW['.PHP_EOL;
            print_vars($str);
            echo PHP_EOL.']'.PHP_EOL;
        }
        return $str;
    }
    if ($str === '') {
        if (OBS_DEBUG) {
            print_message('JSON DECODE[%yEmpty string%n]');
        }
        return $str;
    }

    $options |= OBS_JSON_DECODE;

    $json = @json_decode(smart_quotes($str), TRUE, 512, $options);

    $json_error = json_last_error();
    if ($json_error !== JSON_ERROR_NONE) {
        $msg = json_last_error_msg();

        if ($json_error === JSON_ERROR_CTRL_CHAR) {
            // Try to fix "Control character error, possibly incorrectly encoded"
            $str_fix = preg_replace('/[[:cntrl:]]/', '', smart_quotes($str));
            print_debug_vars($str_fix);
        } elseif (function_exists('mb_ord')) {
            // Try to fix utf errors
            $str_fix = fix_json_unicode(smart_quotes($str));
            print_debug_vars($str_fix);
        } else {
            // https://jira.observium.org/browse/OBS-4881
            // Prevent php fatal errors in poller without mbstring
            print_debug("WARNING! PHP extension mbstring not exist. Please read Observium requirements.");
            $str_fix = FALSE;
        }
        if ($str_fix) {
            $json_fix = @json_decode($str_fix, TRUE, 512, $options);
            if (json_last_error() === JSON_ERROR_NONE) {
                //print_vars(smart_quotes(fix_json_unicode($str)));
                //print_vars($json_fix);
                return $json_fix;
            }
        }
        if (OBS_DEBUG) {
            print_message('JSON DECODE[%r' . $msg . '%n]');
            echo "JSON[" . PHP_EOL . $str . PHP_EOL . "]" . PHP_EOL;
        }
    }

    return $json;
}

/*
 * PHP json_decode not correctly convert UTF8 encoded chars, but correct decode escaped unicode :/
 * "ËЙЦУКЕНГШЩЗХЪФЫВАПРОЛДЖЭЯЧСМИТЬБЮ" ->
 * "\u00cb\u0419\u0426\u0423\u041a\u0415\u041d\u0413\u0428\u0429\u0417\u0425\u042a\u0424\u042b\u0412\u0410\u041f\u0420\u041e\u041b\u0414\u0416\u042d\u042f\u0427\u0421\u041c\u0418\u0422\u042c\u0411\u042e"
 */
function fix_json_unicode($string) {
    if (!function_exists('mb_ord')) {
        // Safe return original string, for prevention php fatal errors
        print_debug("WARNING! PHP extension mbstring required for fix_json_unicode().");
        return $string;
    }

    return preg_replace_callback('/([\x{0080}-\x{FFFF}])/u', static function ($match) {
        return '\\u' . str_pad(dechex(mb_ord($match[1], 'UTF-8')), 4, '0', STR_PAD_LEFT);
    }, $string);
}

function safe_unserialize($str)
{
    if (is_array($str)) {
        return NULL;
    }

    return @unserialize($str, ['allowed_classes' => FALSE]);
}

function safe_count($array, $mode = COUNT_NORMAL): int {
    if (is_countable($array)) {
        return count($array, $mode);
    }

    return 0;
}

/**
 * Report if var empty (only empty array [], string '' and NULL)
 * Note FALSE, 0, 0.0 and '0' return TRUE (not empty)
 *
 * @param $var
 *
 * @return bool
 */
function safe_empty($var): bool {
    return $var !== 0 && $var !== 0.0 && $var !== '0' && $var !== FALSE && empty($var);
}

/**
 * Safe explode: returns an empty array when input is not a string.
 *
 * @param string     $delimiter  Delimiter boundary string.
 * @param mixed      $string     Input value; if not string, empty array returned.
 * @param int        $limit      Maximum number of elements to return (default: PHP_INT_MAX).
 *
 * @return string[]              Array of exploded string elements, or empty array on invalid input.
 */
function safe_explode(string $delimiter, $string, int $limit = PHP_INT_MAX): array {
    // if (is_array($string)) {
    //     // explode() cannot work on arrays; FIXME. return as is or recursive?
    //     return $string;
    // }
    if (!is_string($string) || $string === '') {
        return [];
    }

    return explode($delimiter, $string, $limit);
}

/**
 * Creates a RecursiveIteratorIterator object for a given directory with a specified maximum depth.
 *  key - full file path
 *  entry->getFilename() - filename only
 *
 * @param string      $dir         The path to the directory.
 * @param int         $max_depth   The maximum allowed subdirectory depth. Defaults to -1, which allows for any depth.
 * @param string|null $pattern     Optional regex pattern to filter files (e.g., '/\.inc\.php$/'). Defaults to NULL (no filtering).
 * @param bool        $skip_hidden Whether to skip hidden files and directories (starting with .). Defaults to FALSE.
 *
 * @return RecursiveIteratorIterator|CallbackFilterIterator Returns an iterator object for the specified directory.
 */
function get_recursive_directory_iterator($dir, $max_depth = -1, $pattern = NULL, $skip_hidden = FALSE) {
    $directory = new RecursiveDirectoryIterator($dir, FilesystemIterator::KEY_AS_PATHNAME |
                                                      FilesystemIterator::CURRENT_AS_FILEINFO |
                                                      FilesystemIterator::SKIP_DOTS);

    // Filter hidden files/directories before recursion
    if ($skip_hidden) {
        $directory = new RecursiveCallbackFilterIterator($directory, function($current, $key, $iterator) {
            // Skip hidden files and directories (starting with .)
            return $current->getFilename()[0] !== '.';
        });
    }

    $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::LEAVES_ONLY,
                                                          RecursiveIteratorIterator::CATCH_GET_CHILD);
    $iterator->setMaxDepth($max_depth);

    // Apply regex filter to filename if provided
    if ($pattern !== NULL) {
        $iterator = new CallbackFilterIterator($iterator, function($current) use ($pattern) {
            return preg_match($pattern, $current->getFilename());
        });
    }

    return $iterator;
}

/**
 * Checks if the filesystem of the given path is full, based on a specified threshold.
 *
 * @param string $path The path for which to check the filesystem.
 *
 * @return bool Returns true if the filesystem is considered full, false otherwise.
 *
 * The function calculates the percentage of free disk space and compares it to a threshold.
 * If the percentage of free space is lower than the threshold, the function returns true,
 * indicating that the filesystem is considered full. Otherwise, it returns false.
 */
function is_filesystem_full($path): bool {
    if ($disk_total_space = disk_total_space($path)) {
        $disk_free_space      = disk_free_space($path);
        $disk_full_percentage = float_div($disk_free_space, $disk_total_space) * 100;

        return $disk_full_percentage < 1;
    }

    return FALSE;
}

// EOF
