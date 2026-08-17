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

// Single-file transport: webhook-json
global $definitions;

$definitions['transports']['webhook-json'] = [
    'name'        => 'Webhook JSON',
    'identifiers' => [ 'url' ],
    'parameters'  => [
        'required' => [
            'url' => [ 'description' => 'URL', 'tooltip' => 'e.g. https://webhook/api' ],
        ],
        'global' => [
            'json' => [ 'description' => 'JSON passed to Webhook', 'type' => 'textarea',
                        'tooltip' => 'Valid JSON passed to Webhook', 'format' => 'json',
                        'default' => '{"ALERT_STATE":"%ALERT_STATE%","ALERT_STATE_NAME":"%ALERT_STATE_NAME%",
                                       "ALERT_EMOJI":"%ALERT_EMOJI%","ALERT_EMOJI_NAME":"%ALERT_EMOJI_NAME%",
                                       "ALERT_STATUS":"%ALERT_STATUS%","ALERT_STATUS_CUSTOM":"%ALERT_STATUS_CUSTOM%",
                                       "ALERT_SEVERITY":"%ALERT_SEVERITY%","ALERT_COLOR":"#%ALERT_COLOR%",
                                       "ALERT_URL":"%ALERT_URL%","ALERT_UNIXTIME":"%ALERT_UNIXTIME%","ALERT_TIMESTAMP":"%ALERT_TIMESTAMP%",
                                       "ALERT_TIMESTAMP_RFC2822":"%ALERT_TIMESTAMP_RFC2822%","ALERT_TIMESTAMP_RFC3339":"%ALERT_TIMESTAMP_RFC3339%",
                                       "ALERT_ID":"%ALERT_ID%","ALERT_MESSAGE":"%ALERT_MESSAGE%","CONDITIONS":"%CONDITIONS%",
                                       "METRICS":"%METRICS%","DURATION":"%DURATION%","ENTITY_URL":"%ENTITY_URL%","ENTITY_LINK":"%ENTITY_LINK%",
                                       "ENTITY_NAME":"%ENTITY_NAME%","ENTITY_ID":"%ENTITY_ID%","ENTITY_TYPE":"%ENTITY_TYPE%",
                                       "ENTITY_DESCRIPTION":"%ENTITY_DESCRIPTION%","DEVICE_HOSTNAME":"%DEVICE_HOSTNAME%",
                                       "DEVICE_SYSNAME":"%DEVICE_SYSNAME%","DEVICE_DESCRIPTION":"%DEVICE_DESCRIPTION%","DEVICE_ID":"%DEVICE_ID%",
                                       "DEVICE_URL":"%DEVICE_URL%","DEVICE_LINK":"%DEVICE_LINK%","DEVICE_HARDWARE":"%DEVICE_HARDWARE%",
                                       "DEVICE_OS":"%DEVICE_OS%","DEVICE_TYPE":"%DEVICE_TYPE%","DEVICE_LOCATION":"%DEVICE_LOCATION%",
                                       "DEVICE_UPTIME":"%DEVICE_UPTIME%","DEVICE_REBOOTED":"%DEVICE_REBOOTED%","TITLE":"%TITLE%"}' ],
        ],
        'optional'  => [
            'url_fallback' => [ 'description' => 'Fallback URL', 'tooltip' => 'Optional fallback url if main unavailable by timeout' ],
            'token'        => [ 'description' => 'Authentication token' ],
        ],
    ],

    'notification' => [
        'method'           => 'POST',
        'request_format'   => 'json',
        'url'              => '%url%',
        'request_header'   => [ 'Authorization?token' => '%token%' ],
        'message_json'     => '%json%', // Escaped message tags by array_json_escape()
        'response_format'  => 'json',
    ],
];

// EOF