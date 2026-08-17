<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage ajax
 * @copyright  (C) Adam Armstrong
 *
 */

include_once("../../includes/observium.inc.php");

include($config['html_dir'] . "/includes/authenticate.inc.php");

if (!$_SESSION['authenticated']) {
    print_json_status('failed', 'Unauthorized.');
    exit();
}

$vars = get_vars([ 'JSON', 'POST' ]); // Got a JSON payload. Replace $var.

$readonly   = $_SESSION['userlevel'] < 7;
$limitwrite = $_SESSION['userlevel'] >= 9;
$readwrite  = $_SESSION['userlevel'] >= 10;

// Helpers for dashboard permissions
function can_edit_dash($dash_id) {
    $dash = dbFetchRow("SELECT user_id FROM `dashboards` WHERE `dash_id` = ?", [$dash_id]);
    if (!$dash) { return FALSE; }
    if ($_SESSION['userlevel'] >= 7) { return TRUE; }
    if (!is_null($dash['user_id']) && (int)$dash['user_id'] === (int)$_SESSION['user_id']) { return TRUE; }
    return FALSE;
}
function can_view_dash($dash_id) {
    $dash = dbFetchRow("SELECT user_id, is_public FROM `dashboards` WHERE `dash_id` = ?", [$dash_id]);
    if (!$dash) { return FALSE; }
    if ((int)$dash['is_public'] === 1) { return TRUE; }
    if (!is_null($dash['user_id']) && (int)$dash['user_id'] === (int)$_SESSION['user_id']) { return TRUE; }
    if ($_SESSION['userlevel'] >= 10) { return TRUE; }
    return FALSE;
}

switch ($vars['action']) {
    // Helper: require a valid CSRF token for mutating actions
    // Keep this local to avoid global includes churn
    case null: break; // placeholder to keep syntax highlighters happy
}

// Local helper to require CSRF for state-changing actions
if (!function_exists('require_csrf_or_fail')) {
    function require_csrf_or_fail($vars) {
        $json = '';
        if (!request_token_valid($vars, $json)) {
            $json = safe_json_decode($json);
            $json['reload'] = TRUE;
            print_json_status('failed', 'CSRF Token missing. Reload page.', $json);
            exit();
        }
    }
}

switch ($vars['action']) {
    case "theme":
        $pref = 'web_theme_default';
        if ($vars['value'] === 'reset') {
            session_unset_var("theme");
            if ($config['web_theme_default'] === 'system') {
                // Override default
                session_unset_var("theme_default");
            }

            if (del_user_pref($_SESSION['user_id'], $pref)) {
                print_json_status('ok', 'Theme reset.');
            }
        } elseif (isset($config['themes'][$vars['value']]) || $vars['value'] === 'system') {
            if (set_user_pref($_SESSION['user_id'], $pref, serialize($vars['value']))) {
                print_json_status('ok', 'Theme set.');
            }
        } else {
            print_json_status('failed', 'Invalid theme.');
        }
        break;

    case "big_graphs":
        $pref = 'graphs|size';
        if (set_user_pref($_SESSION['user_id'], $pref, serialize('big'))) {
            print_json_status('ok', 'Big graphs set.');
            session_unset_var("big_graphs"); // clear old
        }
        break;

    case "normal_graphs":
        $pref = 'graphs|size';
        if (set_user_pref($_SESSION['user_id'], $pref, serialize('normal'))) {
            print_json_status('ok', 'Normal graphs set.');
            session_unset_var("big_graphs"); // clear old
        }
        break;

    case "touch_on":
        session_set_var("touch", TRUE);
        print_json_status('ok', 'Touch mode enabled.');
        break;

    case "touch_off":
        session_unset_var("touch");
        print_json_status('ok', 'Touch mode disabled.');
        break;

    case "save_grid": // Save current layout of dashboard grid
        require_csrf_or_fail($vars);
        // Check edit permission per affected widget's dashboard
        foreach ((array)$vars['grid'] as $w) {
            $dash_id = dbFetchCell("SELECT `dash_id` FROM `dash_widgets` WHERE `widget_id` = ?", [$w['id']]);
            if (!$dash_id || !can_edit_dash($dash_id)) {
                print_json_status('failed', 'Action not allowed.');
                exit();
            }
        }
        foreach ((array)$vars['grid'] as $w) {
            dbUpdate(['x' => $w['x'], 'y' => $w['y'], 'width' => $w['width'], 'height' => $w['height']], 'dash_widgets', '`widget_id` = ?', [$w['id']]);
        }
        break;

    case "add_widget": // Add widget of 'widget_type' to dashboard 'dash_id'

        require_csrf_or_fail($vars);

        if (isset($vars['dash_id']) && isset($vars['widget_type'])) {
            if (!can_edit_dash($vars['dash_id'])) { print_json_status('failed', 'Action not allowed.'); exit(); }

            $widget_type = (string)$vars['widget_type'];
            // Enforce safe widget type
            if (!preg_match('/^[a-z0-9_-]+$/i', $widget_type)) {
                print_json_status('failed', 'Invalid widget type.');
                exit();
            }
            if (isset($config['widgets']) && is_array($config['widgets'])) {
                if (!array_key_exists($widget_type, $config['widgets']) || !empty($config['widgets'][$widget_type]['deprecated']) || $widget_type === 'graph') {
                    print_json_status('failed', 'Widget not allowed.');
                    exit();
                }
            }

            $widget_id = dbInsert([
                'dash_id'       => $vars['dash_id'],
                'widget_config' => json_encode([]),
                'widget_type'   => $widget_type
            ], 'dash_widgets');
        }

        if ($widget_id) {
            print_json_status('ok', '', ['id' => $widget_id]);
        } else {
            //print_r($vars); // For debugging
        }
        break;

    case "delete_ap":

        require_csrf_or_fail($vars);

        // Currently edit allowed only for Admins
        if ($readonly) {
            print_json_status('failed', 'Action not allowed.');
            exit();
        }

        if (is_numeric($vars['id'])) {
            $rows_deleted = dbDelete('wifi_aps', '`wifi_ap_id` = ?', [$vars['id']]);
        }

        if ($rows_deleted) {
            print_json_status('ok', 'AP Deleted', ['id' => $vars['id']]);
        }

        break;

    case "del_widget":

        require_csrf_or_fail($vars);
        if (is_numeric($vars['widget_id'])) {
            $dash_id = dbFetchCell("SELECT `dash_id` FROM `dash_widgets` WHERE `widget_id` = ?", [$vars['widget_id']]);
            if (!$dash_id || !can_edit_dash($dash_id)) { print_json_status('failed', 'Action not allowed.'); exit(); }
            $rows_deleted = dbDelete('dash_widgets', '`widget_id` = ?', [$vars['widget_id']]);
        }

        if ($rows_deleted) {
            print_json_status('ok', 'Widget Deleted.', ['id' => $vars['widget_id']]);
        }
        break;

    case "dash_rename":

        require_csrf_or_fail($vars);
        if (is_numeric($vars['dash_id'])) {
            if (!can_edit_dash($vars['dash_id'])) { print_json_status('failed', 'Action not allowed.'); exit(); }
            $rows_updated = dbUpdate(['dash_name' => $vars['dash_name']], 'dashboards', '`dash_id` = ?', [$vars['dash_id']]);
        } else {
            print_json_status('failed', 'Invalid Dashboard ID.');
        }

        if ($rows_updated) {
            print_json_status('ok', 'Dashboard Name Updated.', ['id' => $vars['dash_id']]);
        } else {
            print_json_status('failed', 'Update Failed.');
        }

        break;

    case "dash_update_descr":
        require_csrf_or_fail($vars);
        if (!is_numeric($vars['dash_id'])) { print_json_status('failed', 'Invalid Dashboard ID.'); break; }
        if (!can_edit_dash($vars['dash_id'])) { print_json_status('failed', 'Action not allowed.'); break; }
        $descr = (string)$vars['descr'];
        $rows_updated = dbUpdate(['descr' => $descr], 'dashboards', '`dash_id` = ?', [$vars['dash_id']]);
        if ($rows_updated) { print_json_status('ok', 'Description updated.', ['id' => $vars['dash_id']]); }
        else { print_json_status('ok', 'No change.'); }
        break;

    case "dash_delete":

        require_csrf_or_fail($vars);
        if (is_numeric($vars['dash_id'])) {
            if (!can_edit_dash($vars['dash_id'])) { print_json_status('failed', 'Action not allowed.'); exit(); }
            $rows_deleted = dbDelete('dash_widgets', '`dash_id` = ?', [$vars['dash_id']]);
            $rows_deleted += dbDelete('dashboards', '`dash_id` = ?', [$vars['dash_id']]);
        } else {
            print_json_status('failed', 'Invalid Dashboard ID.');
        }

        if ($rows_deleted) {
            print_json_status('ok', 'Dashboard Deleted.', ['id' => $vars['dash_id']]);
        } else {
            print_json_status('failed', 'Deletion Failed.');
        }

        break;

    case "dash_visibility":
        require_csrf_or_fail($vars);
        if (!is_numeric($vars['dash_id'])) { print_json_status('failed', 'Invalid Dashboard ID.'); break; }
        if (!can_edit_dash($vars['dash_id'])) { print_json_status('failed', 'Action not allowed.'); break; }
        $is_public = get_var_true($vars['is_public']) ? 1 : 0;
        $rows_updated = dbUpdate(['is_public' => $is_public], 'dashboards', '`dash_id` = ?', [$vars['dash_id']]);
        if ($rows_updated) { print_json_status('ok', 'Visibility updated.', ['id' => $vars['dash_id'], 'is_public' => $is_public]); }
        else { print_json_status('ok', 'No change.'); }
        break;

    case "dash_set_default":
        require_csrf_or_fail($vars);
        if (!is_numeric($vars['dash_id'])) { print_json_status('failed', 'Invalid Dashboard ID.'); break; }
        if (!can_view_dash($vars['dash_id'])) { print_json_status('failed', 'You do not have access to this dashboard.'); break; }
        if (set_user_pref($_SESSION['user_id'], 'dashboard_default', (string)$vars['dash_id'])) {
            print_json_status('ok', 'Default dashboard set.', ['id' => $vars['dash_id']]);
        } else {
            print_json_status('failed', 'Unable to set default.');
        }
        break;

    case "dash_export":
        require_csrf_or_fail($vars);
        if (!is_numeric($vars['dash_id'])) { print_json_status('failed', 'Invalid Dashboard ID.'); break; }
        if (!can_view_dash($vars['dash_id'])) { print_json_status('failed', 'You do not have access to this dashboard.'); break; }
        $dash = dbFetchRow("SELECT dash_id, dash_name, slug, descr FROM `dashboards` WHERE `dash_id` = ?", [(int)$vars['dash_id']]);
        if (!$dash) { print_json_status('failed', 'Dashboard not found.'); break; }
        $widgets = dbFetchRows("SELECT widget_type, widget_config, x, y, width, height FROM `dash_widgets` WHERE `dash_id` = ? ORDER BY `y`,`x`", [(int)$vars['dash_id']]);
        // payload structure
        $payload = [
            'version' => 1,
            'name'    => $dash['dash_name'],
            'slug'    => $dash['slug'],
            'descr'   => $dash['descr'],
            'widgets' => []
        ];
        foreach ($widgets as $w) {
            $payload['widgets'][] = [
                'type'   => $w['widget_type'],
                'config' => safe_json_decode($w['widget_config']),
                'x'      => is_numeric($w['x']) ? (int)$w['x'] : NULL,
                'y'      => is_numeric($w['y']) ? (int)$w['y'] : NULL,
                'width'  => (int)$w['width'],
                'height' => (int)$w['height']
            ];
        }
        print_json_status('ok', 'Export ready.', ['payload' => json_encode($payload)]);
        break;

    case "dash_import":
        require_csrf_or_fail($vars);
        // Import payload into a new private dashboard for current user
        $owner = (int)$_SESSION['user_id'];
        $payload = safe_json_decode($vars['payload']);
        if (!is_array($payload) || !isset($payload['widgets']) || !is_array($payload['widgets'])) {
            print_json_status('failed', 'Invalid import payload.');
            break;
        }
        $name = isset($payload['name']) && $payload['name'] ? $payload['name'] : 'Imported Dashboard';
        $new_id = dbInsert(['dash_name' => $name, 'user_id' => $owner, 'is_public' => 0], 'dashboards');
        if (!$new_id) { print_json_status('failed', 'Unable to create dashboard.'); break; }
        foreach ($payload['widgets'] as $w) {
            // Minimal validation; leave install-specific IDs as-is
            $type = isset($w['type']) ? (string)$w['type'] : 'unknown';
            if (!preg_match('/^[a-z0-9_-]+$/i', $type)) { $type = 'unknown'; }
            if (isset($config['widgets']) && is_array($config['widgets'])) {
                if (!array_key_exists($type, $config['widgets']) || !empty($config['widgets'][$type]['deprecated']) || $type === 'graph') {
                    $type = 'unknown';
                }
            }
            $cfg  = isset($w['config']) ? json_encode($w['config']) : json_encode([]);
            $x    = isset($w['x']) && is_numeric($w['x']) ? (int)$w['x'] : NULL;
            $y    = isset($w['y']) && is_numeric($w['y']) ? (int)$w['y'] : NULL;
            $width  = isset($w['width']) ? (int)$w['width'] : 4;
            $height = isset($w['height']) ? (int)$w['height'] : 3;
            dbInsert(['dash_id' => $new_id, 'widget_type' => $type, 'widget_config' => $cfg, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height], 'dash_widgets');
        }
        // generate slug
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
        $slug = trim($slug, '-'); if ($slug === '') { $slug = 'dashboard'; }
        $base = $slug; $i = 2;
        while (dbExist('dashboards', '`slug` = ?', [$slug])) { $slug = $base . '-' . $i; $i++; }
        dbUpdate(['slug' => $slug], 'dashboards', '`dash_id` = ?', [$new_id]);
        print_json_status('ok', 'Dashboard imported.', ['id' => $new_id, 'slug' => $slug]);
        break;

    case "dash_clone":
        require_csrf_or_fail($vars);
        // Duplicate a dashboard into a new personal dashboard for the current user
        if (!is_numeric($vars['dash_id'])) { print_json_status('failed', 'Invalid Dashboard ID.'); break; }
        if (!can_view_dash($vars['dash_id'])) { print_json_status('failed', 'You do not have access to this dashboard.'); break; }
        $src = dbFetchRow("SELECT * FROM `dashboards` WHERE `dash_id` = ?", [$vars['dash_id']]);
        if (!$src) { print_json_status('failed', 'Source dashboard not found.'); break; }
        $new_name = trim((string)$vars['name']);
        if ($new_name === '') { $new_name = 'Copy of ' . $src['dash_name']; }
        $owner = (int)$_SESSION['user_id'];
        $new_id = dbInsert(['dash_name' => $new_name, 'user_id' => $owner, 'is_public' => 0], 'dashboards');
        if (!$new_id) { print_json_status('failed', 'Unable to create dashboard.'); break; }
        foreach (dbFetchRows("SELECT * FROM `dash_widgets` WHERE `dash_id` = ?", [$src['dash_id']]) as $w) {
            dbInsert([
                'dash_id'       => $new_id,
                'widget_type'   => $w['widget_type'],
                'widget_config' => $w['widget_config'],
                'x'             => $w['x'],
                'y'             => $w['y'],
                'width'         => $w['width'],
                'height'        => $w['height']
            ], 'dash_widgets');
        }
        // generate slug
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $new_name));
        $slug = trim($slug, '-'); if ($slug === '') { $slug = 'dashboard'; }
        $base = $slug; $i = 2;
        while (dbExist('dashboards', '`slug` = ?', [$slug])) { $slug = $base . '-' . $i; $i++; }
        dbUpdate(['slug' => $slug], 'dashboards', '`dash_id` = ?', [$new_id]);
        print_json_status('ok', 'Dashboard cloned.', ['id' => $new_id, 'slug' => $slug]);
        break;

    case "dash_reorder":
        require_csrf_or_fail($vars);
        // Reorder dashboards with explicit scope; expects array order: [dash_id1, dash_id2, ...]
        $order = isset($vars['order']) && is_array($vars['order']) ? $vars['order'] : [];
        $scope = isset($vars['scope']) ? (string)$vars['scope'] : 'my';
        if ($scope === 'public' && $_SESSION['userlevel'] < 10) {
            print_json_status('failed', 'Only admins may reorder public dashboards.');
            break;
        }
        $pos = 1;
        foreach ($order as $did) {
            $did = (int)$did;
            $row = dbFetchRow("SELECT user_id, is_public FROM `dashboards` WHERE `dash_id` = ?", [$did]);
            if (!$row) { continue; }
            if ($scope === 'public') {
                if ((int)$row['is_public'] !== 1) { continue; }
                // Admin-only path, update order
                dbUpdate(['dash_order' => $pos], 'dashboards', '`dash_id` = ?', [$did]);
                $pos++;
            } else { // 'my'
                if ((int)$row['user_id'] !== (int)$_SESSION['user_id']) { continue; }
                dbUpdate(['dash_order' => $pos], 'dashboards', '`dash_id` = ?', [$did]);
                $pos++;
            }
        }
        print_json_status('ok', 'Order updated.');
        break;

    case "update_widget_config":

        require_csrf_or_fail($vars);
        //print_r($vars);

        // Currently edit allowed only for Admins
        if ($readonly) {
            print_json_status('failed', 'Action not allowed.');
            exit();
        }

        $widget                  = dbFetchRow("SELECT * FROM `dash_widgets` WHERE `widget_id` = ?", [$vars['widget_id']]);
        $widget['widget_config'] = safe_json_decode($widget['widget_config']);

        // Ensure caller can edit the dashboard containing this widget
        if (!$widget || !can_edit_dash($widget['dash_id'])) {
            print_json_status('failed', 'Action not allowed.');
            exit();
        }

        // Verify config value applies to this widget here

        $default_on = ['legend', 'devices', 'ports', 'neighbours', 'errors', 'bgp', 'uptime'];

        // Handle bulk configuration update (new format)
        if (isset($vars['config']) && is_array($vars['config'])) {
            // Update multiple configuration fields at once
            foreach ($vars['config'] as $field => $value) {
                if (empty($value) ||
                    (in_array($field, $default_on) && get_var_true($value)) ||
                    (!in_array($field, $default_on) && get_var_false($value))) {
                    // Just unset the value if it's empty or it's a default value.
                    unset($widget['widget_config'][$field]);
                } else {
                    $widget['widget_config'][$field] = $value;
                }
            }

            dbUpdate(['widget_config' => json_encode($widget['widget_config'])], 'dash_widgets',
                     '`widget_id` = ?', [$widget['widget_id']]
            );

            print_json_status('ok', 'Widget Updated.', ['id' => $widget['widget_id']]);
        }
        // Handle single field update (legacy format)
        elseif (isset($vars['config_field']) && isset($vars['config_value'])) {
            if (empty($vars['config_value']) ||
                (in_array($vars['config_field'], $default_on) && get_var_true($vars['config_value'])) ||
                (!in_array($vars['config_field'], $default_on) && get_var_false($vars['config_value']))) {
                // Just unset the value if it's empty or it's a default value.
                unset($widget['widget_config'][$vars['config_field']]);
            } else {
                $widget['widget_config'][$vars['config_field']] = $vars['config_value'];
            }

            dbUpdate(['widget_config' => json_encode($widget['widget_config'])], 'dash_widgets',
                     '`widget_id` = ?', [$widget['widget_id']]
            );

            //echo dbError();

            print_json_status('ok', 'Widget Updated.', ['id' => $widget['widget_id']]);
        } else {
            print_json_status('failed', 'Update Failed.');
        }

        break;

    case "poller_delete":
        require_csrf_or_fail($vars);

        if (!$readwrite) {
            print_json_status('failed', 'Insufficient permissions. Admin access required.');
            break;
        }

        if (!is_numeric($vars['poller_id']) || $vars['poller_id'] == 0) {
            print_json_status('failed', 'Invalid poller ID. Cannot delete default poller.');
            break;
        }

        $poller_id = (int)$vars['poller_id'];
        $target_poller_id = isset($vars['target_poller_id']) ? (int)$vars['target_poller_id'] : 0;

        if ($poller_id == $target_poller_id) {
            print_json_status('failed', 'Source and target poller cannot be the same.');
            break;
        }

        $poller = dbFetchRow("SELECT * FROM `pollers` WHERE `poller_id` = ?", [$poller_id]);
        if (!$poller) {
            print_json_status('failed', 'Poller not found.');
            break;
        }

        if ($target_poller_id != 0) {
            $target_poller = dbFetchRow("SELECT * FROM `pollers` WHERE `poller_id` = ?", [$target_poller_id]);
            if (!$target_poller) {
                print_json_status('failed', 'Target poller not found.');
                break;
            }
        }

        $devices = dbFetchRows("SELECT * FROM `devices` WHERE `poller_id` = ?", [$poller_id]);
        $device_count = count($devices);

        if ($device_count > 0) {
            $updated = dbUpdate(['poller_id' => $target_poller_id], 'devices', '`poller_id` = ?', [$poller_id]);
            if ($updated === FALSE) {
                print_json_status('failed', 'Failed to reassign devices.');
                break;
            }
        }

        $deleted = dbDelete('pollers', '`poller_id` = ?', [$poller_id]);
        if (!$deleted) {
            print_json_status('failed', 'Failed to delete poller from database.');
            break;
        }

        $target_name = $target_poller_id == 0 ? 'Default' : $target_poller['poller_name'];
        $message = "Poller '{$poller['poller_name']}' deleted successfully.";
        if ($device_count > 0) {
            $message .= " {$device_count} device(s) reassigned to '{$target_name}'.";
        }

        print_json_status('ok', $message, [
            'poller_id' => $poller_id,
            'devices_moved' => $device_count,
            'target_poller_id' => $target_poller_id
        ]);

        break;

    default:

        // Validate CSRF Token
        //r($vars);
        $json = '';
        if (!request_token_valid($vars, $json)) {
            $json           = safe_json_decode($json);
            $json['reload'] = TRUE;
            print_json_status('failed', 'CSRF Token missing. Reload page.', $json);
            exit();
        }
        unset($json);

        $action_path = __DIR__ . '/actions/' . $vars['action'] . '.inc.php';
        if (is_alpha($vars['action']) && is_file($action_path)) {
            include $action_path;
        } else {
            print_json_status('failed', 'Unknown action requested.');
        }
}

// EOF
