<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage cache
 * @copyright  (C) Adam Armstrong
 *
 */

/**
 * Simple Database Cache Functions
 *
 * These functions provide a simple key-value cache using the database
 * as the storage backend. They are separate from the phpFastCache system above.
 * Function names are prefixed with 'db_cache_' to avoid conflicts.
 */

function db_cache_exists($key) {
    return dbExist('cache', '`cache_key` = ?', [ $key ]);
}

/**
 * Get a value from the database cache
 *
 * @param string $key Cache key
 * @return mixed|null Cached value or null if not found/expired
 */
function db_cache_get($key) {
    // Clean up expired entries occasionally (1% chance)
    if (random_int(1, 100) === 1) {
        db_cache_cleanup();
    }

    $sql = "SELECT `cache_value`, `cache_expires`, UNIX_TIMESTAMP(`cache_expires`) AS `unixtime_expires` FROM `cache` WHERE `cache_key` = ?";
    $result = dbFetchRow($sql, [$key]);

    if (!$result) {
        return NULL;
    }

    // Check if expired
    if ($result['cache_expires'] && $result['unixtime_expires'] < time()) {
        db_cache_delete($key);
        return NULL;
    }

    // Try to unserialize, fallback to raw value
    if (is_string($result['cache_value']) &&
        preg_match('/^([abdisrEOCR]:|N;)/', $result['cache_value'])) { // simple check for serialize
        if ($result['cache_value'] === 'b:0;') {
            return FALSE;
        }
        $decoded = safe_unserialize($result['cache_value']);
        if ($decoded !== FALSE) {
            return $decoded;
        }
    }

    return $result['cache_value'];
}

/**
 * Set a value in the database cache
 *
 * @param string $key Cache key
 * @param mixed $value Value to cache
 * @param int $ttl Time to live in seconds (0 = no expiration)
 * @return bool Success
 */
function db_cache_set($key, $value, $ttl = 0) {
    if ($ttl > 0) {
        //$expires = date('Y-m-d H:i:s', time() + $ttl); // incorrect for mysql with different timezone
        $expires = '(NOW() + INTERVAL ' . (int)$ttl . ' SECOND)';
    } else {
        $expires = 'NULL';
    }

    // Serialize complex values
    $cache_value = is_string($value) ? $value : serialize($value);

    $data = [
        'cache_key'     => $key,
        'cache_value'   => $cache_value,
        'cache_expires' => [ $expires ], // pass direct mysql function
    ];
    return dbUpdateMulti($data, 'cache');
    /* Incorrect syntax for new MySQL 8+
    $sql = "INSERT INTO `cache` (`cache_key`, `cache_value`, `cache_expires`) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            `cache_value` = VALUES(`cache_value`), 
            `cache_expires` = VALUES(`cache_expires`),
            `updated_at` = CURRENT_TIMESTAMP";

    return dbQuery($sql, [ $key, $cache_value, $expires ]);
    */
}

/**
 * Delete a value from the database cache
 *
 * @param string $key Cache key
 * @return bool Success
 */
function db_cache_delete($key) {
    return dbDelete('cache', '`cache_key` = ?', [$key]);
}

/**
 * Clear all database cache entries
 *
 * @return bool Success
 */
function db_cache_clear() {
    return dbQuery("TRUNCATE TABLE `cache`");
}

/**
 * Clean up expired database cache entries
 *
 * @return int Number of deleted entries
 */
function db_cache_cleanup() {
    $sql = "DELETE FROM `cache` WHERE `cache_expires` IS NOT NULL AND `cache_expires` < NOW()";
    $result = dbQuery($sql);
    return $result ? dbAffectedRows() : 0;
}

/**
 * Get database cache statistics
 *
 * @return array Cache statistics
 */
function db_cache_stats() {
    $total = dbFetchCell("SELECT COUNT(*) FROM `cache`");
    $expired = dbFetchCell("SELECT COUNT(*) FROM `cache` WHERE `cache_expires` IS NOT NULL AND `cache_expires` < NOW()");
    $permanent = dbFetchCell("SELECT COUNT(*) FROM `cache` WHERE `cache_expires` IS NULL");

    return [
        'total' => (int)$total,
        'expired' => (int)$expired,
        'permanent' => (int)$permanent,
        'active' => (int)($total - $expired)
    ];
}

/**
 * Get or set a cached value (database cache convenience function)
 *
 * @param string $key Cache key
 * @param callable $callback Function to generate value if not cached
 * @param int $ttl Time to live in seconds (default: 1 hour)
 * @return mixed Cached or generated value
 */
function db_cache_remember($key, $callback, $ttl = 3600) {
    $value = db_cache_get($key);

    if ($value === NULL) {
        $value = $callback();
        db_cache_set($key, $value, $ttl);
    }

    return $value;
}

// EOF
