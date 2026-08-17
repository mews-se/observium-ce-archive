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

// Single-file transport: webhook-old
global $definitions;

$definitions['transports']['webhook-old'] = [
    'name'        => 'Webhook (Old)',
    'identifiers' => [ 'url' ],
    'parameters' => [
        'required' => [
            'url' => [ 'description' => 'URL', 'tooltip' => 'e.g. https://webhook/api' ],
        ],
        'global' => [
            'token' => [ 'description' => 'Authentication token' ],
            'originator' => [ 'description' => 'Sender of message' ],
        ],
    ],

    'notification' => [
        'method'           => 'POST',
        'request_format'   => 'json',
        'url'              => '%url%',
        'message_text'     => "{{TITLE}}\n{{METRICS}}",
        'message_transform' => [ 'action' => 'replace', 'from' => '             ', 'to' => '' ],
        'request_params'   => [
            'originator'  => '%originator%',
            'body'        => '%message%'
        ],
        'request_header'   => [ 'Authorization' => 'AccessKey %token%' ],
        'response_format'  => 'json',
        'response_test'    => [ 'field' => 'message', 'operator' => 'eq', 'value' => 'SUCCESS' ]
    ],
];

// EOF