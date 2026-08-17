<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage discovery
 * @copyright  (C) Adam Armstrong
 *
 */

// STP discovery simply invokes per-MIB handlers; all work done in vendor modules
$include_dir = "includes/discovery/stp";
include("includes/include-dir-mib.inc.php");
