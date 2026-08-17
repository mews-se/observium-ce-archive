<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     alerting
 * @copyright  (C) Adam Armstrong
 *
 */

// Single-file transport: devnull
global $definitions;

$definitions['transports']['devnull'] = [
  'name' => '/dev/null',
  'send_function' => 'transport_send_devnull'
];

/**
 * Send notification via devnull transport (does nothing)
 * This lies and says it did something. It did not. Used to prevent default.
 *
 * @param array $context Notification context
 * @return bool Always returns TRUE
 */
function transport_send_devnull($context) {
    return TRUE;
}

// EOF