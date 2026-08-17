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

$vars['entity']      = $port['port_id'];
$vars['entity_type'] = "port";

print_events($vars);

// EOF
