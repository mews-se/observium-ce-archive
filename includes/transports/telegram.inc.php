<?php
// Single-file transport: telegram
// Contains both definition and function in one file

// Transport definition
global $definitions;
$definitions['transports']['telegram'] = [
    'name'        => 'Telegram Bot',
    'identifiers' => [ 'recipient' ],
    'parameters'  => [
        'required'  => [
            'recipient' => [ 'description' => 'Chat Identifier' ]
        ],
        'global'    => [
            'bot_hash'  => [ 'description' => 'Bot Token' ],
        ],
        'optional'  => [
            'thread' => [
                'description' => 'Thread (Supergroups)',
                'tooltip'     => 'Unique identifier of a message thread to which the message belongs',
            ],
            'disable_notification' => [
                'type'        => 'bool',
                'description' => 'Send notification silently',
                'tooltip'     => 'iOS users will not receive a notification, Android users will receive a notification with no sound. Values: true or false.'
            ],
            'parse_mode'           => [
                'type'        => 'enum',
                'description' => 'Notification Format',
                'params'      => [ 'HTML' => [ 'name' => 'HTML', 'subtext' => '(Default)' ],
                                   'TEXT' => [ 'name' => 'Simple', 'subtext' => '(A different template is used)' ] ],
                'default'     => 'HTML'
            ]
        ],
    ],

    'notification' => [
        'method'           => 'POST',
        'request_format'   => 'json',
        'url'              => 'https://api.telegram.org/bot%bot_hash%/sendMessage',
        'message_template' => 'telegram_%parse_mode%',
        'message_transform' => [ 'action' => 'preg_replace', 'from' => '/ {3,}/', 'to' => '' ],
        'request_params'   => [
            'chat_id'                  => '%recipient%',
            'message_thread_id'        => '%thread%',
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => 'true',
            'text'                     => '%message%'
        ],
        'response_format'  => 'json',
        'response_test'    => [ 'field' => 'ok', 'operator' => 'eq', 'value' => TRUE ],
        'response_fields'  => [ 'status' => 'error_code', 'message' => 'description', 'info' => 'parameters' ],
    ],
];

// EOF