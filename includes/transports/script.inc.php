<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage alerting
 * @copyright  (C) Adam Armstrong
 *
 */

// Single-file transport: script
global $definitions;

$definitions['transports']['script'] = [
  'name'        => 'External program',
  'identifiers' => [ 'script' ],
  'parameters'  => [
    'required'  => [
      'script'    => [ 'description' => 'External program path' ],
    ],
  ],
  'send_function' => 'transport_send_script'
];

/**
 * Send notification via external script
 *
 * @param array $context Notification context
 * @return bool TRUE on success, FALSE on failure
 */
function transport_send_script($context) {
    $endpoint = $context['endpoint'];
    $message_tags = $context['message_tags'];

    $message_keys = array_keys($message_tags);

    // Export all tags for external program usage
    $unescape = [];
    foreach ($message_keys as $key) {
        putenv("OBSERVIUM_$key=" . $message_tags[$key]);
        $unescape['from'][] = '\$OBSERVIUM_' . $key;
        $unescape['to'][]   = '$OBSERVIUM_' . $key;
    }

    // Clean script from injections and
    // Revert back OBSERVIUM variables from escaping
    // I.e.: "\$OBSERVIUM_TITLE \$OBSERVIUM_TIMESTAMP \$OBSERVIUM_DURATION"
    $script_cmd = str_replace($unescape['from'], $unescape['to'], escapeshellcmd($endpoint['script']));

    // Execute given script
    external_exec($script_cmd, $exec_status);

    // If script's exit code is 0, success. Otherwise, we mark it as failed.
    $success = ($exec_status['exitcode'] === 0);

    // Clean out all set environment variables we set before execution
    foreach ($message_keys as $key) {
        putenv("OBSERVIUM_$key");
    }

    return $success;
}

// EOF