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

/* Common cache functions here */

/**
 * Add clear cache attrib, this will request for clearing cache in next request.
 *
 * @param string $target Clear cache target: wui or cli (default if wui)
 *
 * @throws Exception
 */
function set_cache_clear($target = 'wui') {
    if (OBS_DEBUG || (defined('OBS_CACHE_DEBUG') && OBS_CACHE_DEBUG)) {
        print_error('<span class="text-warning">CACHE CLEAR SET.</span> Cache clear set.');
    }
    if (!$GLOBALS['config']['cache']['enable']) {
        // Cache not enabled
        return;
    }

    switch (strtolower($target)) {
        case 'cli':
            // Add clear CLI cache attrib. Currently not used
            set_obs_attrib('cache_cli_clear', get_request_id());
            break;
        default:
            // Add clear WUI cache attrib
            set_obs_attrib('cache_wui_clear', get_request_id());
    }
}

/* Memory cache functions for current process run.
   Not same as Fast Cache for Web UI. */

/**
 * Always-return-by-reference memory cache.
 * Caller chooses:
 *   $ref  =& mem_cache();   // live edits
 *   $copy  = mem_cache();   // snapshot (copy-on-write)
 */
function &mem_cache() {
    static $cache = [];
    return $cache;
}

/** Get/create a single entry by reference for in-place edits */
function &mem_cache_key(string $key) {
    $c =& mem_cache();
    if (!array_key_exists($key, $c)) {
        $c[$key] = []; // ensure it exists so we can return a ref
    }
    return $c[$key];
}

/** Get a value (with default) */
function mem_cache_get(string $key, $default = NULL) {
    $c =& mem_cache(); // value is fine for read
    return array_key_exists($key, $c) ? $c[$key] : $default;
}

/** Exists check (fast path) */
function mem_cache_exists(string $key) {
    $c =& mem_cache();
    return array_key_exists($key, $c);
}

/** Set a value (live edit via reference) */
function mem_cache_set(string $key, $value) {
    $c =& mem_cache();
    $c[$key] = $value;
}

/** Reset: either one key or the whole cache */
function mem_cache_reset(?string $key = NULL) {
    $c =& mem_cache();
    if ($key === null) {
        $c = [];              // wipe all
    } else {
        unset($c[$key]);      // remove one
    }
}

/** Stats: serialized cache size + count */
function mem_cache_stat() {
    $c =& mem_cache();

    $cachesize = strlen(serialize($c)); // size of serialized cache
    return [
        'count' => count($c),
        'items' => array_keys($c),
        'usage' => $cachesize,
        'human' => format_bytes($cachesize),
    ];
}

/** Stats: real PHP memory usage + count */
function mem_cache_memstat() {
    // Warning, this reset cache on each call
    // Memory size returns incorrect value! No better ways for calculate variable size

    $c =& mem_cache();

    $items = array_keys($c);
    $cachesize  = memory_get_usage();
    mem_cache_reset();
    $cachesize -= memory_get_usage();

    if ($cachesize < 0) {
        $cachesize = 0;
    } // Silly PHP!

    return [
        'count' => count($items),
        'items' => $items,
        'usage' => $cachesize,
        'human' => format_bytes($cachesize),
    ];
}

// EOF
