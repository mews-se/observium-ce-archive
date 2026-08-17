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

// Single-file transport: rocketchat-hook
global $definitions;

$definitions['transports']['rocketchat-hook'] = [
    'name'        => 'Rocket.Chat (Webhook)',
    'identifiers' => [ 'url' ],
    'parameters'  => [
        'global'    => [
            'url'     => [ 'description' => 'Webhook URL', 'tooltip' => 'e.g. https://my.domain/hooks/RuiCvnyfzGLA/FkHMWcXE8oYKw3zAEafpNefc4Qyfheufhie8D67AiHmgFFosSgo' ],
            'channel' => [ 'description' => 'Channel',  'default' => '#general' ],
        ],
    ],

    'notification' => [
        'method'           => 'POST',
        'request_format'   => 'json',
        'url'              => '%url%',
        'message_template' => 'rocketchat_text',
        'message_transform' => [ 'action' => 'preg_replace', 'from' => '/ {3,}/', 'to' => '' ],
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