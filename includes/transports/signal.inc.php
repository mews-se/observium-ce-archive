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

// Single-file transport: signal
global $definitions;

/**
 * Signal Messenger transport
 * Requires signal-cli or signal-cli-rest-api running
 * For docs see: https://github.com/AsamK/signal-cli
 * REST API: https://github.com/bbernhard/signal-cli-rest-api
 */
$definitions['transports']['signal'] = [
    'community'   => TRUE,
    'name'        => 'Signal Messenger',
    'identifiers' => [ 'recipient' ],
    'parameters' => [
        'required' => [
            'recipient' => [ 'description' => 'Recipient Phone Number or Group ID', 'tooltip' => 'Phone number with country code (e.g. +1234567890) or Signal group ID' ],
        ],
        'optional' => [
            'api_mode' => [
                'description' => 'API Mode',
                'type' => 'enum',
                'params' => [ 'rest' => 'REST API', 'cli' => 'Command Line' ],
                'default' => 'rest',
                'tooltip' => 'Use signal-cli-rest-api (REST) or signal-cli directly (CLI)'
            ],
        ],
        'global' => [
            'rest_url' => [ 'description' => 'REST API URL', 'default' => 'http://localhost:8080', 'tooltip' => 'signal-cli-rest-api endpoint URL' ],
            'sender_number' => [ 'description' => 'Sender Phone Number', 'tooltip' => 'Registered Signal number (with country code, e.g. +1234567890)' ],
            'cli_path' => [ 'description' => 'CLI Path', 'default' => '/usr/local/bin/signal-cli', 'tooltip' => 'Path to signal-cli binary' ],
        ],
    ],
    'send_function' => 'transport_send_signal'
];

/**
 * Send notification via Signal Messenger
 *
 * @param array $context Notification context
 * @return bool TRUE on success, FALSE on failure
 */
function transport_send_signal($context) {
    $endpoint = $context['endpoint'];
    $message_tags = $context['message_tags'];

    $api_mode = $endpoint['api_mode'] ?? 'rest';
    $recipient = $endpoint['recipient'];
    $sender_number = $endpoint['sender_number'] ?? '';

    // Build message content
    $message = build_signal_message($message_tags);

    if ($api_mode === 'rest') {
        return send_signal_rest($endpoint, $sender_number, $recipient, $message);
    } else {
        return send_signal_cli($endpoint, $sender_number, $recipient, $message);
    }
}

/**
 * Build Signal message content
 */
function build_signal_message($message_tags) {
    $emoji = get_alert_emoji($message_tags['ALERT_STATE']);

    $message = "{$emoji} *{$message_tags['TITLE']}*\n\n";
    $message .= "📱 Device: {$message_tags['DEVICE_HOSTNAME']}\n";
    $message .= "🔧 Entity: {$message_tags['ENTITY_NAME']}\n";
    $message .= "📊 State: {$message_tags['ALERT_STATE_NAME']}\n";
    $message .= "💬 Message: {$message_tags['ALERT_MESSAGE']}\n\n";

    if (!empty($message_tags['CONDITIONS'])) {
        $message .= "⚠️ Conditions:\n{$message_tags['CONDITIONS']}\n\n";
    }

    if (!empty($message_tags['METRICS'])) {
        $message .= "📈 Metrics:\n{$message_tags['METRICS']}\n\n";
    }

    $message .= "⏱️ Duration: {$message_tags['DURATION']}\n";
    $message .= "🕒 Time: {$message_tags['ALERT_TIMESTAMP']}\n\n";
    $message .= "🔗 Alert: {$message_tags['ALERT_URL']}\n";
    $message .= "🖥️ Device: {$message_tags['DEVICE_URL']}";

    // Clean up excessive spacing
    $message = str_replace('             ', ' ', $message);
    $message = preg_replace('/\n{3,}/', "\n\n", $message);

    return $message;
}

/**
 * Get appropriate emoji for alert state
 */
function get_alert_emoji($alert_state) {
    switch ($alert_state) {
        case 'ALERT':
            return '🚨';
        case 'RECOVER':
            return '✅';
        case 'SYSLOG':
            return '📋';
        case 'OK':
            return '✅';
        default:
            return '⚠️';
    }
}

/**
 * Send Signal message via REST API
 */
function send_signal_rest($endpoint, $sender_number, $recipient, $message) {
    $rest_url = $endpoint['rest_url'] ?? 'http://localhost:8080';
    $api_url = rtrim($rest_url, '/') . '/v2/send';

    if (empty($sender_number)) {
        print_debug("Signal REST: sender_number is required");
        return FALSE;
    }

    // Determine if recipient is a phone number or group ID
    $is_group = !preg_match('/^\+\d+$/', $recipient);

    $payload = [
        'message' => $message,
        'number' => $sender_number,
    ];

    if ($is_group) {
        $payload['group_id'] = $recipient;
    } else {
        $payload['recipients'] = [$recipient];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Often localhost

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        print_debug("Signal REST: cURL error: {$curl_error}");
        return FALSE;
    }

    if ($http_code !== 201 && $http_code !== 200) {
        print_debug("Signal REST: HTTP error {$http_code}: {$response}");
        return FALSE;
    }

    $result = json_decode($response, TRUE);
    if ($result && isset($result['timestamp'])) {
        print_debug("Signal REST: Message sent successfully, timestamp: {$result['timestamp']}");
        return TRUE;
    }

    print_debug("Signal REST: Unexpected response: {$response}");
    return FALSE;
}

/**
 * Send Signal message via CLI
 */
function send_signal_cli($endpoint, $sender_number, $recipient, $message) {
    $cli_path = $endpoint['cli_path'] ?? '/usr/local/bin/signal-cli';

    if (!is_executable($cli_path)) {
        print_debug("Signal CLI: signal-cli not found or not executable at {$cli_path}");
        return FALSE;
    }

    if (empty($sender_number)) {
        print_debug("Signal CLI: sender_number is required");
        return FALSE;
    }

    // Determine if recipient is a phone number or group ID
    $is_group = !preg_match('/^\+\d+$/', $recipient);

    // Build command
    $cmd = escapeshellcmd($cli_path);
    $cmd .= ' -a ' . escapeshellarg($sender_number);
    $cmd .= ' send';

    if ($is_group) {
        $cmd .= ' -g ' . escapeshellarg($recipient);
    } else {
        $cmd .= ' ' . escapeshellarg($recipient);
    }

    $cmd .= ' -m ' . escapeshellarg($message);

    print_debug("Signal CLI: Executing command: {$cmd}");

    // Execute command
    $output = [];
    $return_var = 0;
    exec($cmd . ' 2>&1', $output, $return_var);

    $output_str = implode("\n", $output);

    if ($return_var === 0) {
        print_debug("Signal CLI: Message sent successfully");
        return TRUE;
    } else {
        print_debug("Signal CLI: Command failed with exit code {$return_var}: {$output_str}");
        return FALSE;
    }
}

// Signal transport provides:
// - Support for both REST API and CLI modes
// - Phone numbers and group messaging
// - Rich emoji-based message formatting
// - Proper error handling for both modes
// - Security-focused messaging platform
//
// Setup Requirements:
// 1. REST API Mode (Recommended):
//    - Install signal-cli-rest-api: https://github.com/bbernhard/signal-cli-rest-api
//    - Register a Signal number
//    - Set rest_url and sender_number
//
// 2. CLI Mode:
//    - Install signal-cli: https://github.com/AsamK/signal-cli
//    - Register a Signal number: signal-cli -a +yourNumber register
//    - Verify: signal-cli -a +yourNumber verify CODE
//    - Set cli_path and sender_number
//
// Configuration Examples:
// - Phone recipient: +1234567890
// - Group recipient: Use group ID from Signal
// - Multiple recipients: Create multiple transport endpoints

// EOF