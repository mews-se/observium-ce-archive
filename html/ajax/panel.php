<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage web
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2024 Observium Limited
 *
 */

include_once("../../includes/observium.inc.php");

include($config['html_dir'] . "/includes/authenticate.inc.php");

if (!$_SESSION['authenticated']) {
    // not authenticated
    die("Unauthenticated");
}

// Push $_GET into $vars to be compatible with web interface naming
$vars = get_vars('GET');

if (isset($vars['name']) && (!is_alpha($vars['name']) || !is_file($config['html_dir'] . "/includes/panels/" . $vars['name'] . ".inc.php"))) {
    die("Invalid panel name");
}

include($config['html_dir'] . "/includes/cache-data.inc.php");

$panel_name = $vars['name'] ?? 'default';

include($config['html_dir'] . "/includes/panels/" . $panel_name . ".inc.php");

// EOF
