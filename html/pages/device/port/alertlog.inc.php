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

// Pagination
$vars['pagination'] = TRUE;

$vars['entity_type'] = 'port';
$vars['entity_id']   = $vars['port'];

// Print Alert Log
print_alert_log($vars);

// EOF
