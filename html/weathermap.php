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

/* THIS IS ONE BIG VULNERABILITY! */

ini_set('allow_url_fopen', 0);

include_once("../includes/observium.inc.php");

if (!$config['web_iframe'] && is_iframe()) {
    print_error_permission("Not allowed to run in a iframe!");
    die();
}

include($config['html_dir'] . "/includes/authenticate.inc.php");

if (($_SERVER['REMOTE_ADDR'] != $_SERVER['SERVER_ADDR']) && !$_SESSION['authenticated']) {
    // not authenticated
    die("Unauthenticated");
}

$vars = get_vars('GET');

// Read-only actions allowed for level 5+ users
$readonly_actions = ['draw', 'font_samples', 'show_config', 'fetch_config'];
$action = $vars['action'] ?? '';

if ($_SESSION['userlevel'] < 5) {
    echo("Unauthorised Access Prohibited.");
    exit;
}

// Editing actions require level 7+
if (!in_array($action, $readonly_actions) && $_SESSION['userlevel'] <= 7) {
    echo("Unauthorised Access Prohibited. Editing requires administrator privileges.");
    exit;
}

include($config['install_dir'] . "/includes/weathermap/editor.php");

// EOF
