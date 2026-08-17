<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage functions
 * @copyright  (C) Adam Armstrong
 *
 */

function encrypt_sodium($string, $key, $nonce = NULL) {
    // https://dev.to/paragonie/libsodium-quick-reference#crypto-secretbox

    $key_len = check_extension_exists('mbstring') ? mb_strlen($key, '8bit') : strlen($key);
    //logfile('debug_wui.log', 'Encrypt requested. Key: '.$key.', size: '.$key_len);
    if (!$key_len || $key_len > SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        //echo $key;
        print_debug("Encryption key must be at least 1 and not more than " . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . ".");
        return FALSE;
    }
    if ($key_len < SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        $key = sodium_pad($key, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        //logfile('debug_wui.log', 'Padded Key: '.$key.', size: '.strlen($key));
    }

    if (!$nonce) {
        // Sodium encrypt require $nonce
        // if not set use server unique id
        $nonce = get_unique_id();
    }

    // Fix nonce length
    $nonce_len = check_extension_exists('mbstring') ? mb_strlen($nonce, '8bit') : strlen($nonce);
    if ($nonce_len > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        $nonce = substr($nonce, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    } elseif ($nonce_len < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        $nonce = sodium_pad($nonce, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    }

    try {
        $encrypted = safe_base64_encode(sodium_crypto_secretbox($string, $nonce, $key));
    } catch (SodiumException $exception) {
        //var_dump($exception);
        print_debug($exception->getMessage());
        $encrypted = FALSE;
    }
    //var_dump($encrypted);
    return $encrypted;
}

function decrypt_sodium($encrypted, $key, $nonce = NULL) {
    // https://dev.to/paragonie/libsodium-quick-reference#crypto-secretbox

    $key_len = check_extension_exists('mbstring') ? mb_strlen($key, '8bit') : strlen($key);
    //logfile('debug_wui.log', 'Decrypt requested. Key: '.$key.', size: '.$key_len);
    if ($key_len > SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        return FALSE;
    }
    if ($key_len < SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        $key = sodium_pad($key, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        //logfile('debug_wui.log', 'Padded Key: '.$key.', size: '.strlen($key));
    }

    if (!$nonce) {
        // Sodium encrypt require $nonce
        // if not set use server unique id
        $nonce = get_unique_id();
    }
    // Fix nonce length
    $nonce_len = check_extension_exists('mbstring') ? mb_strlen($nonce, '8bit') : strlen($nonce);
    if ($nonce_len > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        $nonce = substr($nonce, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    } elseif ($nonce_len < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        $nonce = sodium_pad($nonce, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    }

    try {
        $string = sodium_crypto_secretbox_open(safe_base64_decode($encrypted), $nonce, $key);
    } catch (SodiumException $exception) {
        //var_dump($key);
        print_debug($exception->getMessage());
        $string = FALSE;
    }
    return $string;
}

function is_base64($string) {
    // Check if there are valid base64 characters
    if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $string)) {
        return FALSE;
    }

    // Decode the string in strict mode and check the results
    $decoded = base64_decode($string, TRUE);
    if ($decoded === FALSE) {
        return FALSE;
    }

    // Encode the string again
    if (base64_encode($decoded) != $string) {
        return FALSE;
    }

    return TRUE;
}

/**
 * Safe variant of base64_encode()
 *
 * @param $string
 *
 * @return string
 */
function safe_base64_encode($string) {
    $data = base64_encode($string);
    return str_replace(['+', '/', '='], ['-', '_', ''], $data);
}

/**
 * Safe variant of base64_decode()
 *
 * @param $string
 *
 * @return string
 */
function safe_base64_decode($string) {
    $data = str_replace(['-', '_'], ['+', '/'], $string);
    $mod4 = strlen($data) % 4;
    if ($mod4) {
        $data .= substr('====', $mod4);
    }
    return base64_decode($data);
}

// DOCME needs phpdoc block
function encrypt($string, $key, $nonce = NULL) {
    //logfile('debug_wui.log', 'Encrypt requested');

    if (!OBS_ENCRYPT) {
        print_debug("Encrypt unsupported. Please enable sodium extension for php!");
        return FALSE;
    }

    return encrypt_sodium($string, $key, $nonce);
}

// DOCME needs phpdoc block
function decrypt($encrypted, $key, $nonce = NULL) {
    //logfile('debug_wui.log', 'Decrypt requested');

    if (!OBS_ENCRYPT) {
        print_debug("Encrypt unsupported. Please enable sodium extension for php!");
        return FALSE;
    }

    return decrypt_sodium($encrypted, $key, $nonce);
}

function get_encrypt_key($random = FALSE) {

    if (!OBS_ENCRYPT) { // only available for php 7.2+ & sodium
        print_debug("Sodium encryption unavailable or incorrect encryption secret.");
        return NULL;
    }

    $charlist = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ~!@#$^&*()_+-=[]{}\|/?,.<>;:';
    if ($random) {
        return generate_random_string(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $charlist);
    }

    $secret = get_defined_settings('encrypt_secret');
    if (check_extension_exists('mbstring')) {
        $key_len = is_string($secret) ? mb_strlen($secret, '8bit') : 0;
    } else {
        $key_len = is_string($secret) ? strlen($secret) : 0;
    }
    if ($key_len < 12 || $key_len > 32) {
        print_debug("Incorrect encryption secret, must be at least 12 and not more than 32.");
        if (OBS_DEBUG) {
            // php -r 'echo "\$config[\"encrypt_secret\"] = \"" . bin2hex(random_bytes(16)) . "\";\n";' >> config.php
            print_cli("Add to config.php:\n");
            print_cli("\$config['encrypt_secret'] = '" . generate_random_string(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $charlist) . "';\n");
        }

        return NULL;
    }

    return $secret;
}

// EOF