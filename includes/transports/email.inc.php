<?php
// Single-file transport: email
// Contains both definition and function in one file

// Transport definition
global $definitions;
$definitions['transports']['email'] = [
    'name'          => 'E-mail',
    'send_function' => 'transport_send_email',
    'identifiers'   => [ 'email' ],
    'parameters'    => [
        'required' => [
            'email' => [ 'description' => 'Address' ],
        ],
    ]
];

/**
 * Send notification to Email
 *
 * @param array $context
 * @return bool
 */
function transport_send_email($context)
{
    global $config;

    // Find local hostname
    $localhost = get_localhost();

    $cfg         = $config['email'];
    $endpoint    = $context['endpoint'];
    $message_tags = $context['message_tags'];

    $emails      = [];
    $mail_params = [];

    $emails[$endpoint['email']] = $endpoint['contact_descr'];

    // Mail backend params
    $backend = strtolower(trim($cfg['backend']));
    switch ($backend) {
        case 'sendmail':
            $sendmail_exec = explode(' ', $cfg['sendmail_path'])[0];
            if (empty($cfg['sendmail_path']) || !is_executable($sendmail_exec)) {
                return FALSE;
            }
            $mail_params['sendmail_path'] = $cfg['sendmail_path'];
            break;

        case 'smtp':
            $mail_params['host'] = $cfg['smtp_host'];
            $mail_params['port'] = $cfg['smtp_port'];
            if (strtolower($cfg['smtp_secure']) === 'ssl') {
                if (!str_starts_with($cfg['smtp_host'], 'ssl://')) {
                    $mail_params['host'] = 'ssl://' . $cfg['smtp_host'];
                }
                if ($cfg['smtp_port'] == 25) {
                    $mail_params['port'] = 465; // Default port for SSL
                }
            } elseif ($cfg['smtp_secure'] === 'no' || $cfg['smtp_secure'] === 'none' || $cfg['smtp_secure'] === FALSE) {
                $mail_params['starttls'] = FALSE;
            } elseif ($cfg['smtp_secure']) {
                $mail_params['starttls'] = TRUE;
            }

            $mail_params['socket_options']['ssl']['verify_peer_name']  = (bool)$cfg['smtp_secure_verify'];
            $mail_params['socket_options']['ssl']['allow_self_signed'] = (bool)$cfg['smtp_secure_self'];
            $mail_params['timeout']   = $cfg['smtp_timeout'];
            $mail_params['auth']      = $cfg['smtp_auth'];
            $mail_params['username']  = $cfg['smtp_username'];
            $mail_params['password']  = $cfg['smtp_password'];
            $mail_params['localhost'] = $localhost;
            if (OBS_DEBUG) {
                $mail_params['debug'] = TRUE;
            }
            break;

        case 'smtpmx':
        case 'mx':
            $mail_params['mailname'] = $localhost;
            $mail_params['timeout'] = $cfg['smtp_timeout'];
            if (OBS_DEBUG) {
                $mail_params['debug'] = TRUE;
            }
            $backend = 'smtpmx';
            break;

        case 'mail':
        default:
            $sendmail_path = ini_get('sendmail_path');
            $sendmail_exec = explode(' ', $sendmail_path)[0];
            if (empty($sendmail_path) || !is_executable($sendmail_exec)) {
                if (!empty($cfg['sendmail_path']) && is_executable(explode(' ', $cfg['sendmail_path'])[0])) {
                    ini_set('sendmail_path', $cfg['sendmail_path']);
                } else {
                    return FALSE;
                }
            }
            $backend = 'mail';
    }

    $time_rfc = date('r', time());

    $headers = [];
    if (empty($cfg['from'])) {
        $headers['From']        = 'Observium <observium@' . $localhost . '>';
        $headers['Return-Path'] = 'observium@' . $localhost;
    } else {
        foreach(parse_email($cfg['from']) as $from => $from_name) {
            $headers['From']        = (empty($from_name) ? $from : '"' . $from_name . '" <' . $from . '>');
            $headers['Return-Path'] = $from;
            break;
        }
    }

    $rcpts      = [];
    $rcpts_full = [];
    foreach ($emails as $to => $to_name) {
        $rcpts_full[] = (empty($to_name) ? $to : '"' . trim($to_name) . '" <' . $to . '>');
        $rcpts[]      = $to;
    }

    $rcpts_full = implode(', ', $rcpts_full);
    $rcpts      = implode(', ', $rcpts);

    $headers['To']      = $rcpts_full;
    $headers['Subject'] = $message_tags['TITLE'];
    $headers['Message-ID'] = '<' . md5(uniqid(time(), TRUE)) . '@' . $localhost . '>';
    $headers['Date']       = $time_rfc;
    $headers['X-Priority'] = 3;
    $headers['X-Mailer']   = OBSERVIUM_PRODUCT . ' ' . OBSERVIUM_VERSION;
    $headers['Precedence']               = 'bulk';
    $headers['Auto-submitted']           = 'auto-generated';
    $headers['X-Auto-Response-Suppress'] = 'All';

    $time_sent = $time_rfc;

    $mime = new Mail_mime(['head_charset' => 'utf-8',
                       'text_charset' => 'utf-8',
                       'html_charset' => 'utf-8',
                       'eol'          => PHP_EOL]);

    $message_tags['FOOTER'] = "\n\nE-mail sent to: $rcpts\n" .
                          "E-mail sent at: $time_sent\n\n" .
                          "-- \n" . OBSERVIUM_PRODUCT_LONG . ' ' . OBSERVIUM_VERSION . "\n" . OBSERVIUM_URL . "\n";

    $message['text'] = simple_template('email_text', $message_tags, [ 'is_file' => TRUE ]);

    $message_tags_html               = $message_tags;
    $message_tags_html['CONDITIONS'] = nl2br(escape_html($message_tags['CONDITIONS']));
    $message_tags_html['METRICS']    = nl2br(escape_html($message_tags['METRICS']));

    $graphs = $context['graphs'];

    if (is_array($graphs) && count($graphs)) {
        $message_tags_html['ENTITY_GRAPHS'] = '';
        foreach ($graphs as $graph) {
            $cid = random_string(16);
            [ $gmime, $base64 ] = explode(';', $graph['data'], 2);
            $gmime  = substr($gmime, 5);
            $base64 = substr($base64, 7);
            $mime->addHTMLImage(base64_decode($base64), $gmime, $cid . '.png', FALSE, $cid);

            $message_tags_html['ENTITY_GRAPHS'] .= '<h4>' . $graph['type'] . '</h4>';
            $message_tags_html['ENTITY_GRAPHS'] .= '<a href="' . $graph['url'] . '"><img src="cid:' . $cid . '"></a><br />';
        }
    }

    $message_tags_html['FOOTER'] = "\n<br /><p style=\"font-size: 11px;\">E-mail sent to: $rcpts<br />\n" .
                               "E-mail sent at: $time_sent</p>\n" .
                               '<div style="font-size: 11px; color: #999;">-- <br /><a href="' .
                               OBSERVIUM_URL . '">' . OBSERVIUM_PRODUCT_LONG . ' ' . OBSERVIUM_VERSION . "</a></div>\n";

    $message['html'] = simple_template('email_html', $message_tags_html, [ 'is_file' => TRUE ]);
    unset($message_tags_html);

    foreach ($message as $part => $part_body) {
        switch ($part) {
            case 'text':
            case 'txt':
            case 'plain':
                $mime->setTXTBody($part_body);
                break;
            case 'html':
                $mime->setHTMLBody($part_body);
                break;
        }
    }
    $body = $mime->get();

    foreach ($headers as $name => $value) {
        $headers[$name] = $mime->encodeHeader($name, $value, 'utf-8', 'quoted-printable');
    }
    $headers = $mime->headers($headers);

    $mail = Mail::factory($backend, $mail_params);
    $status = $mail->send($rcpts, $headers, $body);

    if (PEAR::isError($status)) {
        return FALSE;
    } else {
        return TRUE;
    }
}