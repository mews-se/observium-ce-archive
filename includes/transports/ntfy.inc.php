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

// Single-file transport: ntfy
global $definitions;

$definitions['transports']['ntfy'] = [
    //'community'   => FALSE,
    'name'        => 'Ntfy',
    'identifiers' => [ 'topic', 'url' ],
    'parameters' => [
        'required' => [
            'topic' => [ 'description' => "Topic" ],
        ],
        'optional' => [
            'url'     => [
                'description' => 'ntfy URL',
                'tooltip'     => 'Optional parameter, set this only for use with self-hosted instance. When unset, the transport uses ntfy.sh hosted API.',
                'default'     => 'https://ntfy.sh'
            ],
            'token'   => [
                'description' => "Access Token",
                'tooltip'     => 'Optional parameter, for reserved and/or protected topics.'
            ],
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
                        'ALERT_SEVERITY == "Critical"' => '5',
                        'ALERT_SEVERITY == "Warning"' => '4',
                        'ALERT_SEVERITY == "Informational"' => '3',
                        'default' => '5'
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
        'url'               => '%url%',
        'message_template'  => 'ntfy_text', // Default template name, see: includes/templates/notification/ntfy_text.tpl
        'message_transform' => [ 'action' => 'preg_replace', 'from' => '/ {6,}/', 'to' => '' ], // clean multiline metrics
        'request_header'    => [
            'Authorization?token' => 'Bearer %token%', // additional request header with contact accesskey
            'Tags'                => '%ALERT_EMOJI_NAME%',
            'Icon'                => '%ICON_URL%',     // works only on Android devices
            'Priority'            => '%priority%'
        ],
        'request_params'    => [
            "topic"           => '%topic%',
            "title"           => '%TITLE%',
            //"click"           => '%ALERT_URL%',
            "message"         => '%message%',
            "actions"         => [
                [ 'action' => 'view', 'label' => 'Modify Alert', 'url' => '%ALERT_URL%' ],
                [ 'action' => 'view', 'label' => 'View Device',  'url' => '%DEVICE_URL%' ],
            ]
        ],
        'response_format'   => 'json',
        'response_test'     => [ 'field' => 'event', 'operator' => 'eq', 'value' => 'message' ],
        // Error response example:
        // {"code":40301,"http":403,"error":"forbidden","link":"https://ntfy.sh/docs/publish/#authentication"}
        'response_fields'   => [ 'status' => 'code', 'message' => 'error', 'info' => 'link' ], // API response fields with extra information
    ]
];

// Ntfy transport now uses enhanced preprocessing - no custom send function needed!

// EOF