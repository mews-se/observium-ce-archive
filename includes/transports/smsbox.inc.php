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

// Single-file transport: smsbox
global $definitions;

/**
 * https://kannel.org/download/1.5.0/userguide-1.5.0/userguide.html#AEN4623
 * NOTE, additional contact params fetched from $config['smsbox']
 */
$definitions['transports']['smsbox'] = [
    'community'   => TRUE,
    'name'        => 'Kannel SMSBox',
    'identifiers' => [ 'phone' ],
    'parameters' => [
        'required' => [
            'phone' => [ 'description' => "Recipient's phone number" ],
        ],
    ],

    'notification' => [
        'method'           => 'GET',
        'request_format'   => 'urlencode',
        'url'              => '%scheme%://%host%:%port%/cgi-bin/sendsms',
        'request_params'   => [
            'user'     => '%user%',
            'password' => '%password%',
            'from'     => '%from%',
            'to'       => '%phone%',
            'text'     => '%message%'
        ],
        'message_text'     => "{{ALERT_STATE_NAME}} {{DEVICE_HOSTNAME}}: {{ENTITY_NAME}}\n{{ALERT_MESSAGE}}",
        'response_format'  => 'raw',
        'response_test'    => [ 'field' => 'response', 'operator' => 'regex', 'value' => 'Accepted|Queued' ],
    ],
];

// EOF