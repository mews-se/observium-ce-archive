<?php
/**
 * Global STP (Spanning Tree Protocol) Views
 * 
 * Provides network-wide STP monitoring organized by domains
 * Views: domains (default), domain detail, problem-ports, instances, events
 */

$pagetitle[] = "Spanning Tree";

// Get current view
$view = $vars['view'] ?: 'domains';

// Domain hash generator - matches database computed field
function stp_domain_hash($variant, $region, $root_hex) {
  return substr(md5($variant . ($region ?? '') . ($root_hex ?? '')), 0, 12);
}

// Generate human-readable domain ID for display
function stp_domain_display_name($variant, $region, $root_hex) {
  $name = strtoupper($variant);
  if (!empty($region)) {
    $name .= " Region: " . $region;
  }
  if (!empty($root_hex)) {
    $name .= " Root: " . stp_bridge_id_str($root_hex);
  }
  return $name;
}

// Bridge ID display helper  
function stp_bridgeid_to_str($hex) {
  if (empty($hex)) return '';
  return stp_bridge_id_str($hex);
}

// Safe URL encoding for bridge IDs
function stp_encode_bridge_id($hex) {
  if (empty($hex)) return '';
  return base64_encode($hex);
}

// Safe URL decoding for bridge IDs  
function stp_decode_bridge_id($encoded) {
  if (empty($encoded)) return '';
  $decoded = base64_decode($encoded, true);
  return $decoded !== false ? $decoded : '';
}

// Navigation bar with proper URLs
$navbar = [
  'brand' => 'Spanning Tree',
  'options' => [
    'domains'       => ['text' => 'Domains', 'url' => generate_url(['page' => 'stp', 'view' => 'domains'])],
    'problem-ports' => ['text' => 'Problem Ports', 'url' => generate_url(['page' => 'stp', 'view' => 'problem-ports'])], 
    'instances'     => ['text' => 'Instances', 'url' => generate_url(['page' => 'stp', 'view' => 'instances'])],
    'events'        => ['text' => 'Events', 'url' => generate_url(['page' => 'stp', 'view' => 'events'])]
  ],
  'class' => 'navbar-narrow'
];
$navbar['options'][$view]['class'] = 'active';
print_navbar($navbar);

// Route to appropriate view
switch ($view) {
  case 'domain':
    include($config['html_dir'].'/pages/stp/domain.inc.php');
    break;

  case 'problem-ports':
    include($config['html_dir'].'/pages/stp/problem-ports.inc.php');
    break;

  case 'instances':
    include($config['html_dir'].'/pages/stp/instances.inc.php');
    break;

  case 'events':
    include($config['html_dir'].'/pages/stp/events.inc.php');
    break;

  case 'domains':
  default:
    include($config['html_dir'].'/pages/stp/domains.inc.php');
    break;
}

// EOF
