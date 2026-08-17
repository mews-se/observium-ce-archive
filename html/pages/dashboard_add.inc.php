<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     webui
 * @author         Adam Armstrong <adama@observium.org>
 * @copyright  (C) Adam Armstrong
 *
 */

if ($_SESSION['authenticated']) {
    // Create a new personal dashboard, default to Private
    $owner_id = (int)$_SESSION['user_id'];
    // Slugify helper
    $make_slug = function($name) {
        $s = strtolower($name);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = trim($s, '-');
        if ($s === '') { $s = 'dashboard'; }
        // Ensure uniqueness by appending counter
        $base = $s; $i = 2;
        while (dbExist('dashboards', '`slug` = ?', [$s])) { $s = $base . '-' . $i; $i++; }
        return $s;
    };

    $name    = 'My Dashboard';
    $slug    = $make_slug($name);
    $dash_id = dbInsert(['dash_name' => $name, 'slug' => $slug, 'user_id' => $owner_id, 'is_public' => 0], 'dashboards');
    if ($dash_id) {
        $new_name = 'Dashboard ' . $dash_id;
        $new_slug = $make_slug($new_name);
        dbUpdate(['dash_name' => $new_name, 'slug' => $new_slug], 'dashboards', '`dash_id` = ?', [$dash_id]);
        redirect_to_url(generate_url(['page' => 'dashboard', 'dash' => $new_slug, 'edit' => 'yes', 'action' => NULL]));
    } else {
        print_error('Unable to create dashboard.');
    }
}
