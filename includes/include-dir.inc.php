<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage common
 * @copyright  (C) Adam Armstrong
 *
 */

/**
 * @var array   $config
 * @var string  $include_dir
 * @var string  $include_dir_regexp
 * @var integer $include_dir_depth
 * @var boolean $include_dir_sort
 */

// This is an include so that we don't lose variable scope.

$include_dir_regexp = $include_dir_regexp ?? "/\.inc\.php$/";
$include_dir_depth  = is_numeric($include_dir_depth ?? NULL) ? (int)$include_dir_depth : 0; // Do not include files from (one level) subdir by default
$include_dir_sort   = $include_dir_sort ?? FALSE;

$include_paths = [];
foreach (get_recursive_directory_iterator($config['install_dir'] . '/' . $include_dir, $include_dir_depth, $include_dir_regexp, TRUE) as $file => $info) {
    $include_paths[] = $file;
}

// This loop used only when sorting includes
if ($include_dir_sort) {
    asort($include_paths);
}

foreach ($include_paths as $file) {
    // do not use print_debug, which not included for definitions!
    //if (OBS_DEBUG > 1) { echo('Including' . ($include_dir_sort ? ' sorted: ' : ': ') . $file . PHP_EOL); }

    include($file);
}

unset($include_dir_regexp, $include_dir_depth, $include_dir_sort, $include_dir, $include_paths);

// EOF
