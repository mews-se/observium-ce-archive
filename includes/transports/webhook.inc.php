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

// Single-file transport: webhook
global $definitions;

$definitions['transports']['webhook'] = [
    'name'        => 'Webhook',
    'identifiers' => [ 'url' ],
    'parameters'  => [
        'required' => [
            'url' => [ 'description' => 'URL', 'tooltip' => 'e.g. https://webhook/api' ],
        ],
        'global' => [
        ],
    ],

    'notification' => [
        'method'           => 'POST',
        'request_format'   => 'json',
        'url'              => '%url%',
        'message_tags'     => TRUE,
        'response_format'  => 'json',
        'response_test'    => [ 'field' => 'status', 'operator' => 'eq', 'value' => 'successful' ]
    ],
];

// EOF