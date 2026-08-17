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

/**
 * Autoloader for Classes used in Observium
 *
 * @param string $class_name Name of class
 *
 * @return boolean Status of loaded class
 */
function observium_autoload($class_name) {
    //var_dump($class_name);
    if (isset($GLOBALS['config']['install_dir'])) {
        $base_dir = $GLOBALS['config']['install_dir'] . '/libs/';
    } else {
        // not know why in phpunit $GLOBALS and $config reset on this stage
        $base_dir = dirname(__DIR__) . '/libs/';
    }

    $class_array = explode('\\', $class_name);
    $class_file  = str_replace('_', '/', implode('/', $class_array)) . '.php';
    //print_vars($class_array);
    switch ($class_array[0]) {
        case 'cli':
            include_once($base_dir . 'cli/cli.php'); // Cli classes required base functions
            $class_file = str_replace('/Table/', '/table/', $class_file);
            //var_dump($class_file);
            break;

        case 'Phpfastcache':
            // Phpfastcache 8+
            $class_file     = str_replace('_', '/', implode('/', $class_array)) . '.php';
            break;

        case 'flight':
        case 'Flight':
            $class_file = array_pop($class_array) . '.php';
            if (PHP_VERSION_ID < 70400) {
                // Old compat version 1.x
                // CLEANME. Remove this when a minimum php version is set to 7.4
                $class_file = 'flight1/' . $class_file;
            } else {
                // PHP 7.4+ (for 8.1 required)
                $class_file = 'flight/' . $class_file;
            }
            break;

        case 'Doctrine':
            if (PHP_VERSION_ID >= 80100 && $class_array[1] === 'SqlFormatter') {
                // PHP 8.1+ (since 1.4+ required)
                $class_file = str_replace('/SqlFormatter/', '/SqlFormatter15/', $class_file);
                //$class_file     = str_replace('_', '/', implode('/', $class_array)) . '.php';
            }
            break;

        case 'Brick':
            if (PHP_VERSION_ID >= 80000 && $class_array[1] === 'Math') {
                // PHP 8.0+ (since 0.11 required)
                $class_file = str_replace('/Math/', '/Math11/', $class_file);
                //$class_file     = str_replace('_', '/', implode('/', $class_array)) . '.php';
            }
            break;

        case 'Defuse':
        case 'donatj':
            $class_file = str_replace($class_array[0] . '/', '', $class_file);

            // Initial base class file
            $class_file_base = $base_dir . end($class_array) . '.php';
            if (is_file($class_file_base)) {
                $base_status = include_once($class_file_base);
                if (defined('OBS_DEBUG') && OBS_DEBUG > 1 && function_exists('print_message')) {
                    // autoload included before common
                    print_message("%WLoad base file for class '$class_name' from '$class_file_base': " . ($base_status ? '%gOK' : '%rFAIL'), 'console');
                }
            }
            break;

        case 'PhpUnitsOfMeasure':
            include_once($base_dir . 'PhpUnitsOfMeasure/UnitOfMeasureInterface.php');
            break;

        case 'Tracy':
            if (PHP_VERSION_ID >= 80200) {
                // Tracy 2.11+ requires PHP 8.2+
                $tracy_loader = $base_dir . 'Nette211/tracy.php';
            } else {
                // Tracy 2.9.8 for PHP < 8.2
                $tracy_loader = $base_dir . 'Nette/tracy.php';
            }
            $status = require_once($tracy_loader);
            if (defined('OBS_DEBUG') && OBS_DEBUG > 1 && function_exists('print_message')) {
                print_message("%WLoad class '$class_name' loader from '{$base_dir}Nette/tracy.php': " . ($status ? '%gOK' : '%rFAIL'), 'console');
            }
            return $status;
            //array_unshift($class_array, 'Nette');
            //$class_file     = str_replace('_', '/', implode('/', $class_array)) . '.php';
            break;

        default:
            if (strpos($class_name, 'Parsedown') === 0) {
                $class_file = 'parsedown/' . $class_file;
            } elseif (is_file($base_dir . 'pear/' . $class_file)) {
                // By default, try Pear file
                $class_file = 'pear/' . $class_file;
            } elseif (is_dir($base_dir . 'pear/' . $class_name)) {
                // And Pear dir
                $class_file = 'pear/' . $class_name . '/' . $class_file;
            }
        //elseif (!is_cli() && is_file($GLOBALS['config']['html_dir'] . '/includes/' . $class_file))
        //{
        //  // For WUI check class files in html_dir
        //  $base_dir   = $GLOBALS['config']['html_dir'] . '/includes/';
        //}
    }
    $full_path = $base_dir . $class_file;

    if ($status = is_file($full_path)) {
        $status = include_once($full_path);
    }
    if (defined('OBS_DEBUG') && OBS_DEBUG > 1 &&
        function_exists('print_message')) {
        print_message("%WLoad class '$class_name' from '$full_path': " . ($status ? '%gOK' : '%rFAIL'), 'console');
    }
    return $status;
}

// Register autoload function
spl_autoload_register('observium_autoload');