#!/usr/bin/env php
<?php

/**
 * Observium Probe Help Extraction Tool
 *
 * This tool extracts and displays help information from probe plugins.
 * It can test individual probes or refresh help for all probes.
 *
 * Usage:
 *   php probe_help_tool.php -p <probe_type> [options]
 *   php probe_help_tool.php --refresh-all [options]
 *
 * Options:
 *   -p, --probe      Probe type to extract help from
 *   -f, --format     Output format: text, json, html (default: text)
 *   -r, --refresh    Force refresh cache for individual probe
 *   --refresh-all    Refresh help for all enabled probes
 *   --list-probes    List all available probe types
 *   --clear-cache    Clear all probe help cache
 *   --cache-stats    Show cache statistics
 *   -v, --verbose    Verbose output
 *   -h, --help       Show this help message
 *
 * Examples:
 *   php probe_help_tool.php -p check_http
 *   php probe_help_tool.php -p check_ping -f json
 *   php probe_help_tool.php --refresh-all -v
 *   php probe_help_tool.php --list-probes
 *
 * @package    observium
 * @subpackage tools
 */

$scriptname = basename(__FILE__);
$scriptdir = dirname(__FILE__);

// Include Observium framework
chdir(dirname($argv[0]));
require_once('includes/observium.inc.php');

/**
 * Display help message
 */
function show_help() {
    global $scriptname;
    
    echo "Observium Probe Help Extraction Tool\n\n";
    echo "Usage:\n";
    echo "  php $scriptname -p <probe_type> [options]\n";
    echo "  php $scriptname --refresh-all [options]\n\n";
    echo "Options:\n";
    echo "  -p, --probe      Probe type to extract help from\n";
    echo "  -f, --format     Output format: text, json, html (default: text)\n";
    echo "  -r, --refresh    Force refresh cache for individual probe\n";
    echo "  --refresh-all    Refresh help for all enabled probes\n";
    echo "  --list-probes    List all available probe types\n";
    echo "  --clear-cache    Clear all probe help cache\n";
    echo "  --cache-stats    Show cache statistics\n";
    echo "  -v, --verbose    Verbose output\n";
    echo "  -h, --help       Show this help message\n\n";
    echo "Examples:\n";
    echo "  php $scriptname -p check_http\n";
    echo "  php $scriptname -p check_ping -f json\n";
    echo "  php $scriptname --refresh-all -v\n";
    echo "  php $scriptname --list-probes\n\n";
}

/**
 * List all available probe types
 */
function list_probes() {
    global $config;
    
    echo "Available Probe Types:\n";
    echo str_repeat("-", 50) . "\n";
    
    foreach ($config['probes'] as $probe_type => $probe_config) {
        $status = isset($probe_config['enable']) && $probe_config['enable'] ? '✓' : '✗';
        $installed = get_probe_path($probe_type) ? '✓' : '✗';
        $description = $probe_config['descr'] ?? 'No description';
        
        printf("%-20s [%s%s] %s\n", 
            $probe_type, 
            $status, 
            $installed, 
            $description
        );
    }
    
    echo "\nLegend: [Enabled][Installed]\n";
}

/**
 * Display probe help in specified format
 */
function display_probe_help($probe_type, $format, $refresh) {
    $help_info = get_probe_help_auto($probe_type, $refresh);
    
    if (!$help_info || empty($help_info['help'])) {
        echo "Error: No help available for probe type: $probe_type\n";
        return false;
    }
    
    switch ($format) {
        case 'json':
            echo json_encode($help_info, JSON_PRETTY_PRINT) . "\n";
            break;
            
        case 'html':
            echo get_probe_help_formatted($probe_type) . "\n";
            break;
            
        case 'text':
        default:
            echo "Probe Type: $probe_type\n";
            echo str_repeat("=", 50) . "\n\n";
            
            if (!empty($help_info['description'])) {
                echo "Description:\n";
                echo wordwrap($help_info['description'], 70) . "\n\n";
            }
            
            if (!empty($help_info['usage'])) {
                echo "Usage:\n";
                echo $help_info['usage'] . "\n\n";
            }
            
            if (!empty($help_info['version'])) {
                echo "Version:\n";
                echo $help_info['version'] . "\n\n";
            }
            
            if (!empty($help_info['options'])) {
                echo "Options:\n";
                echo $help_info['options'] . "\n\n";
            }
            
            if (!empty($help_info['examples'])) {
                echo "Examples:\n";
                echo $help_info['examples'] . "\n\n";
            }
            
            echo "Raw Help Output:\n";
            echo str_repeat("-", 30) . "\n";
            echo $help_info['help'] . "\n";
            break;
    }
    
    return true;
}

/**
 * Refresh help for all probes
 */
function refresh_all_probes($verbose) {
    echo "Extracting help for all enabled probes...\n\n";
    
    $stats = refresh_all_probe_help();
    
    if ($verbose) {
        global $config;
        
        foreach ($config['probes'] as $probe_type => $probe_config) {
            if (isset($probe_config['enable']) && !$probe_config['enable']) {
                continue;
            }
            
            $help_info = get_probe_help_auto($probe_type);
            $status = $help_info && !empty($help_info['help']) ? 'SUCCESS' : 'FAILED';
            $path = get_probe_path($probe_type) ?: 'NOT FOUND';
            
            printf("%-25s [%-7s] %s\n", $probe_type, $status, $path);
        }
        echo "\n";
    }
    
    echo "Summary:\n";
    printf("  Total probes: %d\n", $stats['total']);
    printf("  Successful:   %d\n", $stats['success']);
    printf("  Failed:       %d\n", $stats['failed']);
    printf("  Success rate: %.1f%%\n", 
        $stats['total'] > 0 ? ($stats['success'] / $stats['total']) * 100 : 0
    );
}

// Parse command line arguments
$options = getopt("p:f:rvh", [
    "probe:", "format:", "refresh", "refresh-all", "list-probes", "clear-cache", "cache-stats", "verbose", "help"
]);

// Show help if requested or no arguments provided
if (isset($options['h']) || isset($options['help']) || empty($options)) {
    show_help();
    exit(0);
}

// Extract options
$probe_type = $options['p'] ?? $options['probe'] ?? null;
$format = $options['f'] ?? $options['format'] ?? 'text';
$refresh = isset($options['r']) || isset($options['refresh']);
$refresh_all = isset($options['refresh-all']);
$list_probes_flag = isset($options['list-probes']);
$clear_cache = isset($options['clear-cache']);
$cache_stats = isset($options['cache-stats']);
$verbose = isset($options['v']) || isset($options['verbose']);

// Validate format
if (!in_array($format, ['text', 'json', 'html'])) {
    echo "Error: Invalid format. Use 'text', 'json', or 'html'\n";
    exit(1);
}

// Handle different operations
if ($list_probes_flag) {
    list_probes();
    exit(0);
}

if ($clear_cache) {
    echo "Clearing probe help cache...\n";
    // Clear both raw help and formatted help caches
    $deleted = 0;
    $cache_keys = dbFetchColumn("SELECT `cache_key` FROM `cache` WHERE `cache_key` LIKE 'probe_help%'");
    foreach ($cache_keys as $key) {
        if (db_cache_delete($key)) {
            $deleted++;
        }
    }
    echo "Cleared $deleted cache entries.\n";
    exit(0);
}

if ($cache_stats) {
    echo "Cache Statistics:\n";
    echo str_repeat("-", 30) . "\n";
    
    $stats = db_cache_stats();
    echo "Total entries: " . $stats['total'] . "\n";
    echo "Active entries: " . $stats['active'] . "\n";
    echo "Expired entries: " . $stats['expired'] . "\n";
    echo "Permanent entries: " . $stats['permanent'] . "\n";
    
    // Probe-specific stats
    $probe_cache_count = dbFetchCell("SELECT COUNT(*) FROM `cache` WHERE `cache_key` LIKE 'probe_help%'");
    echo "Probe help entries: " . $probe_cache_count . "\n";
    
    exit(0);
}

if ($refresh_all) {
    refresh_all_probes($verbose);
    exit(0);
}

if (!$probe_type) {
    echo "Error: Probe type is required (-p or --probe)\n";
    echo "Use --list-probes to see available probe types\n";
    exit(1);
}

// Check if probe type exists
if (!isset($config['probes'][$probe_type])) {
    echo "Error: Unknown probe type: $probe_type\n";
    echo "Use --list-probes to see available probe types\n";
    exit(1);
}

// Check if probe is installed
if (!get_probe_path($probe_type)) {
    echo "Warning: Probe plugin not found for: $probe_type\n";
    echo "The probe may not be installed on this system.\n\n";
}

// Display probe help
if (!display_probe_help($probe_type, $format, $refresh)) {
    exit(1);
}

echo "\nHelp extraction completed successfully.\n";

?>