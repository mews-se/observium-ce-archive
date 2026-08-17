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

// Single-file transport: smstools
global $definitions;

$definitions['transports']['smstools'] = [
  'name' => 'SMSTools',
  'identifiers' => [ 'recipient' ],
  'parameters' => [
    'required' => [
      'recipient' => [ 'description' => "Recipient's phone number", 'tooltip' => 'Start with country code, no leading +' ],
    ],
    'optional' => [
      'path' => [ 'description' => 'Outgoing spool directory', 'tooltip' => 'Defaults to /var/spool/sms/outgoing' ],
    ],
  ],
  'send_function' => 'transport_send_smstools'
];

/**
 * Send notification via SMSTools
 * Create SMS file in outgoing directory of smstools (http://smstools3.kekekasvi.com/ - apt-get install smstools)
 * Observium needs to be able to write in the specified folder (make user a member of 'dialout' or 'smsd' on Debian)
 * You will need to make sure smstools can also manipulate the files created by Observium (you may need to chgrp the outgoing directory to 'dialout')
 *
 * @param array $context Notification context
 * @return bool TRUE on success, FALSE on failure
 */
function transport_send_smstools($context) {
    $endpoint = $context['endpoint'];
    $message_tags = $context['message_tags'];

    $message = $message_tags['ALERT_STATE_NAME'] . " " . $message_tags['DEVICE_HOSTNAME'] . ": " . $message_tags['ENTITY_NAME'] . "\n" . $message_tags['ALERT_MESSAGE'];

    // Fall back to default path if not specified
    if (empty($endpoint['path'])) {
        $endpoint['path'] = '/var/spool/sms/outgoing';
    }

    // Create unique filename (mode is 0600)
    $tmpfname = tempnam($endpoint['path'], 'Observium-');

    if ($fd = fopen($tmpfname, "w")) {
        fwrite($fd, 'To: ' . $endpoint['recipient'] . "\n\n");
        fwrite($fd, $message);
        fclose($fd);

        // Make sure group can also read this by setting mode to 0660
        chmod($tmpfname, 0660);

        return TRUE;
    } else {
        return FALSE;
    }
}

// EOF