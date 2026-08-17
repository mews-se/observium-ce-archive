<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage tests
 *
 */

// Unified bootstrap file for all PHPUnit tests

// Currently supported with PHPUnit minimum 9.5
// Examples how to start all test:
// 1. with specific php version and phpunit version
// /opt/homebrew/opt/php@7.3/bin/php /usr/local/bin/phpunit-9.phar --configuration phpunit9.xml
// /opt/homebrew/opt/php@8.1/bin/php /usr/local/bin/phpunit-10.phar
// 2. specific test
// /opt/homebrew/opt/php@7.3/bin/php /usr/local/bin/phpunit-9.phar --configuration phpunit9.xml tests/IncludesCommonTest.php
// /opt/homebrew/opt/php@8.1/bin/php /usr/local/bin/phpunit-10.phar tests/IncludesCommonTest.php

// Clear config array, we're starting with a clean state
global $config;
$config = [];

// Set base directory
$base_dir = realpath(__DIR__ . '/..');
$config['install_dir'] = $base_dir;

// Optional: Enable debug mode
$verbosityLevel = 0;
foreach ((array)$_SERVER['argv'] as $arg) {
    if (preg_match('/^-v{1,3}$/', $arg)) {
        $verbosityLevel = strlen($arg) - 1; // -v => 1, -vv => 2, -vvv => 3
    } elseif ($arg === '--verbose') {
        $verbosityLevel = max($verbosityLevel, 1);
    }
}
if ($verbosityLevel > 0) {
    define('OBS_DEBUG', $verbosityLevel);
}

/* Detect requested Test Class
$argv = $_SERVER['argv'] ?? [];
$test_class = '';
foreach ($argv as $arg) {
    if (preg_match('/tests\/(.+)\.php$/', $arg, $m)) {
        $test_class = $m[1];
        break;
    }
}
*/

// Load core includes in correct order
include(__DIR__ . '/../includes/defaults.inc.php');
//include(__DIR__ . '/../config.php'); // Do not include user editable config here
include(__DIR__ . '/../includes/polyfill.inc.php');
include(__DIR__ . '/../includes/autoloader.inc.php');
include(__DIR__ . '/../includes/debugging.inc.php');
require_once(__DIR__ . '/../includes/constants.inc.php');
include(__DIR__ . '/../includes/common.inc.php');
include(__DIR__ . '/../includes/definitions.inc.php');

// Load test-specific definitions (fake definitions for testing)
// use loading over setUp()
//include(__DIR__ . '/data/test_definitions.inc.php');

// Load test config if available (e.g., for API keys)
if (is_file(__DIR__ . '/data/config.php')) {
    include(__DIR__ . '/data/config.php');
}

// Include DB functions (without connection)
//if (str_contains($test_class, 'DbTest')) {
    // In constants.inc.php OBS_DB_SKIP set to TRUE for skipping real DB access
    // Currently we not have tests with create/update in db
    include_once($config['install_dir'] . "/includes/db.inc.php");

    /* Connect to database
    if (!db_skip()) {
        db_config();
        $GLOBALS[OBS_DB_LINK] = dbOpen($config['db_host'], $config['db_user'], $config['db_pass'], $config['db_name']);
    }
    */
//}

// Load core functions
include(__DIR__ . '/../includes/functions.inc.php');
//if (str_starts_with($test_class, 'Html')) {
    include_once(__DIR__ . '/../html/includes/functions.inc.php');
//}

mem_cache_set('db_version', 999); // Set fake db schema version

// JSON precision settings for consistent float handling (moved to phpunit.xml)
//ini_set('precision', 14);
//ini_set('serialize_precision', 17);

// EOF
