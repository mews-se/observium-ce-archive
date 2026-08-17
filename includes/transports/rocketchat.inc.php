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

// Single-file transport: rocketchat
global $definitions;

/**
 * For full docs see https://docs.rocket.chat/api/rest-api/methods/chat/postmessage
 */
$definitions['transports']['rocketchat'] = [
    'name'        => 'Rocket.Chat',
    'identifiers' => [ 'url', 'channel' ],
    'parameters'  => [
        'global'    => [
            'url'     => [ 'description' => 'Base URL', 'tooltip' => 'e.g. http://localhost:3000/' ],
            'channel' => [ 'description' => 'Channel',  'default' => '#general' ],
        ],
        'required'  => [
            'token'   => [ 'description' => 'Personal Token' ],
            'user-id' => [ 'description' => 'User Id' ],
        ],
    ],

    'notification' => [
        'method'           => 'POST',
        'request_format'   => 'json',
        'url'              => '%url%/api/v1/chat.postMessage',
        'message_template' => 'rocketchat_text',
        'message_transform' => [ 'action' => 'preg_replace', 'from' => '/ {3,}/', 'to' => '' ],
        'request_header'   => [ 'X-Auth-Token' => '%token%', 'X-User-Id' => '%user-id%' ],
        'request_params'   => [
            'channel'        => '%channel%',
            'alias'          => '%TITLE%',
            'text'           => '%message%',
            'emoji'          => ':%ALERT_EMOJI_NAME%:',
        ],
        'response_format'  => 'json',
        'response_test'    => [ 'field' => 'success', 'operator' => 'eq', 'value' => TRUE ]
    ],
];

// EOF