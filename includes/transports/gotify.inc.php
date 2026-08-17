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

// Single-file transport: gotify
global $definitions;

$definitions['transports']['gotify'] = [
    //'community'   => FALSE,
    'name'        => 'Gotify',
    'identifiers' => [ 'url', 'token' ],
    'parameters' => [
        'required' => [
            'url'   => [ 'description' => 'Gotify URL' ],
            'token' => [ 'description' => "App Token" ],
        ],
    ],

    // Unified preprocessing using field_transforms with conditional_map
    'preprocessing' => [
        'field_transforms' => [
            'priority' => [
                'source' => 'ALERT_STATUS',
                'action' => 'conditional_map',
                'conditions' => [
                    'ALERT_STATUS == 0 || ALERT_STATUS == 9' => [
                        'ALERT_SEVERITY == "Critical"' => '10',
                        'ALERT_SEVERITY == "Warning"' => '5',
                        'ALERT_SEVERITY == "Informational"' => '3',
                        'default' => '9'
                    ],
                    'ALERT_STATUS == 1' => '3', // RECOVER
                    'ALERT_STATUS == 2' => '2', // DELAYED
                    'ALERT_STATUS == 3' => '2', // SUPPRESSED
                    'default' => '1'
                ]
            ]
        ]
    ],

    'notification' => [
        'method'            => 'POST',
        'request_format'    => 'json',
        'url'               => '%url%/message',
        //'message_text'      => "{{METRICS}}",
        'message_template'  => 'ntfy_text', // Default template name, see: includes/templates/notification/ntfy_text.tpl
        'message_transform' => [ 'action' => 'preg_replace', 'from' => '/ {6,}/', 'to' => '' ], // clean multiline metrics
        'request_header'    => [
            'X-Gotify-Key'  => '%token%', // additional request header with application token
            //'Tags'          => '%ALERT_EMOJI_NAME%',
            //'Priority'      => 'high'
        ],
        'request_params'    => [
            "priority"        => '%priority%',
            "title"           => '%TITLE%',
            "message"         => '%message%',
            'extras'          => [
                //"client::display"      => [ 'contentType' => 'text/markdown' ],
                "client::notification" => [ 'click' => [ 'url' => '%ALERT_URL%' ], ], // not sure that this works
            ]
        ],
        'response_format'   => 'json',
        // { "id": 16, "appid": 1, "message": "example", "title": "Alert me", "priority": 10, "date": "2025-03-11T21:52:54.639998373Z" }
        'response_test'     => [ 'field' => 'id', 'operator' => 'regex', 'value' => '^\d+' ],
        // Error response example:
        // { "error": "Bad Request", "errorCode": 400, "errorDescription": "json: cannot unmarshal string into Go struct field MessageExternal.priority of type int" }
        'response_fields'   => [ 'status' => 'errorCode', 'message' => 'error', 'info' => 'errorDescription' ], // API response fields with extra information
    ]
];

// Gotify transport now uses enhanced preprocessing - no custom send function needed!

// EOF