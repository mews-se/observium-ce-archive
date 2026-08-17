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

// Single-file transport: pushover
global $definitions;

$definitions['transports']['pushover'] = [
    'community' => TRUE,
    'name' => 'Pushover',
    'identifiers' => [ 'token', 'user' ],
    'parameters'  => [
        'required' => [
            'token' => [ 'description' => 'Application API Token', 'tooltip' => 'Your Pushover app token from https://pushover.net/apps' ],
            'user'  => [ 'description' => 'User/Group Key', 'tooltip' => 'The user or group key to receive the notification' ],
        ],
        'optional' => [
            'priority' => [ 'description' => 'Priority', 'tooltip' => 'e.g. 0 (normal), 1 (high), 2 (emergency). Defaults to 0.', 'default' => '0' ],
            'sound'    => [
                'description' => 'Sound',
                'tooltip' => 'Notification sound name, e.g. pushover, siren. Defaults to pushover.',
                'params'      => [
                    'pushover'      => [ 'name' => 'Pushover', 'subtext' => 'Default' ],
 	  	            'bike'          => 'Bike',
 	  	            'bugle'         => 'Bugle',
 	  	            'cashregister'  => 'Cash Register',
 	  	            'classical'     => 'Classical',
 	  	            'cosmic'        => 'Cosmic',
 	  	            'falling'       => 'Falling',
 	  	            'gamelan'       => 'Gamelan',
 	  	            'incoming'      => 'Incoming',
 	  	            'intermission'  => 'Intermission',
 	  	            'magic'         => 'Magic',
 	  	            'mechanical'    => 'Mechanical',
 	  	            'pianobar'      => 'Piano Bar',
 	  	            'siren'         => 'Siren',
 	  	            'spacealarm'    => 'Space Alarm',
 	  	            'tugboat'       => 'Tug Boat',
 	  	            'alien'         => 'Alien Alarm (long)',
	  	            'climb'         => 'Climb (long)',
 	  	            'persistent'    => 'Persistent (long)',
 	  	            'echo'          => 'Pushover Echo (long)',
 	  	            'updown'        => 'Up Down (long)',
 	  	            'vibrate'       => [ 'name' => 'Vibrate Only', 'subtext' => 'No sound' ],
 	  	            'none'          => [ 'name' => 'None', 'subtext' => 'Silent' ],
 	  	        ],
                'type'    => 'enum',
                'default' => 'pushover'
            ],
            'attach_graph' => [ 'description' => 'Attach entity Graph', 'type' => 'bool' ],
        ],
    ],

    // Use unified field_transforms system for priority mapping
    'preprocessing' => [
        'field_transforms' => [
            'priority' => [
                'source' => 'ALERT_STATE',
                'action' => 'map',
                'map' => [
                    'RECOVER' => '0',
                    'SYSLOG' => '0', 
                    'default' => '1' // Higher for alerts
                ]
            ]
        ]
    ],

    // https://pushover.net/api
    'notification' => [
        'method' => 'POST',
        'request_format' => 'json',
        'url' => 'https://api.pushover.net/1/messages.json',
        'message_transform' => [ 'action' => 'replace', 'from' => '             ', 'to' => '' ], // Clean metrics spacing
        'request_params' => [
            'token'     => '%token%',
            'user'      => '%user%',
            'title'     => '%TITLE%',
            'message'   => "%ALERT_MESSAGE%\nDevice: %DEVICE_HOSTNAME%\nEntity: %ENTITY_NAME%\nDuration: %DURATION%\nMetrics: %METRICS%",
            'url'       => '%ALERT_URL%',
            'url_title' => 'View Alert',
            '?priority' => '%priority%', // Optional field
            '?sound'    => '%sound%',    // Optional field
            'timestamp' => '%ALERT_UNIXTIME%',

            // Append this params if attach_graph is true
            'attachment_type?:attach_graph'   => 'image/png',
            'attachment_base64?:attach_graph' => '%ENTITY_GRAPH_BASE64%'
        ],
        'response_format' => 'json',
        'response_test' => [ 'field' => 'status', 'operator' => 'eq', 'value' => '1' ],
    ],
];

// Pushover transport uses field_transforms with pure definitions - no custom send function needed!

// EOF