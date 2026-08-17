<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage web
 * @copyright  (C) Adam Armstrong
 *
 */

register_html_resource('css', 'gridstack.min.css');
register_html_resource('js', 'lodash.min.js');
register_html_resource('js', 'jquery-ui.min.js');
register_html_resource('js', 'gridstack.all.js');
register_html_resource('css', 'dashboard.css');
register_html_resource('js', 'dashboard.js');

// Register JavaScript needed for alert widget ignore functionality
register_html_resource('js', 'bootstrap-confirmation.js');

// Load map stuff so that it's available to widgets.
// included in base css to control styles
//register_html_resource('css', 'leaflet.css');
register_html_resource('js', 'leaflet.js');
/* Too old, we unsupport MSIE (pre Edge) js fetch fix
$ua = detect_browser();
if ($ua['browser'] === 'MSIE' ||
    ($ua['browser'] === 'Firefox' && version_compare($ua['version'], '61', '<'))) // Also for FF ESR60 and older
{
    register_html_resource('js', 'js/compat/bluebird.min.js');
    register_html_resource('js', 'js/compat/fetch.js');
}
*/
register_html_resource('js', 'leaflet-realtime.js');

// Allows us to detect when things are resized.
register_html_resource('js', 'ResizeSensor.js');

include_dir($config['html_dir'] . '/includes/widgets/');

if ($_SESSION['userlevel'] >= 7 && $vars['reset_dashboard'] == "yes") {
    dbDelete('dashboards', '1');
    dbDelete('dash_widgets', '1');
}

$grid_cell_height = 20;
$grid_h_margin    = 100;
$grid_v_margin    = 15;

// Build dashboard picker lists (My/Public)
$dash_values = [];
$current_user = (int)$_SESSION['user_id'];
foreach (dbFetchRows("SELECT dash_id, dash_name, descr FROM `dashboards` WHERE `user_id` = ? ORDER BY `dash_order`, `dash_id`", [$current_user]) as $row) {
    $dash_values[$row['dash_id']] = [ 'name' => $row['dash_name'], 'subtext' => (string)$row['descr'], 'group' => 'My Dashboards' ];
}
foreach (dbFetchRows("SELECT dash_id, dash_name, descr FROM `dashboards` WHERE `is_public` = 1 ORDER BY `dash_order`, `dash_id`", []) as $row) {
    // Skip if already in 'My Dashboards' (owner may have public too)
    if (!isset($dash_values[$row['dash_id']])) {
        $dash_values[$row['dash_id']] = [ 'name' => $row['dash_name'], 'subtext' => (string)$row['descr'], 'group' => 'Public Dashboards' ];
    }
}

// Resolve dashboard to view: accept slug or id; prefer user default, else first accessible
if (isset($vars['dash']) && !is_numeric($vars['dash'])) {
    $row = dbFetchRow("SELECT * FROM `dashboards` WHERE `slug` = ?", [$vars['dash']]);
    if ($row) { $vars['dash'] = (string)$row['dash_id']; }
}
if (!isset($vars['dash'])) {
    $user_id = (int)$_SESSION['user_id'];
    $pref    = get_user_pref($user_id, 'dashboard_default');
    if (is_numeric($pref)) {
        $vars['dash'] = (string)$pref;
    }
}
if (!isset($vars['dash'])) {
    $vars['dash'] = dbFetchCell("SELECT `dash_id` FROM `dashboards` WHERE `is_public` = 1 OR `user_id` = ? ORDER BY `dash_id` ASC LIMIT 1", [(int)$_SESSION['user_id']]);
}
if (!isset($vars['dash'])) { $vars['dash'] = '1'; }


if (isset($vars['edit']) && $_SESSION['userlevel'] > 7) { $is_editing = TRUE; }

$dashboard = dbFetchRow("SELECT * FROM `dashboards` WHERE `dash_id` = ?", [$vars['dash']]);
// Permission: view allowed if public, owner, or high-level admin
if ($dashboard && !($dashboard['is_public'] == 1 || (int)$dashboard['user_id'] === (int)$_SESSION['user_id'] || $_SESSION['userlevel'] >= 10)) {
    // Fallback to first accessible
    $fallback = dbFetchRow("SELECT * FROM `dashboards` WHERE `is_public` = 1 OR `user_id` = ? ORDER BY `dash_id` ASC LIMIT 1", [(int)$_SESSION['user_id']]);
    $dashboard = $fallback;
    if ($dashboard) { $vars['dash'] = (string)$dashboard['dash_id']; }
}

if (is_array($dashboard)) {

    // Edit mode controls at top (no picker; dashboards are in navbar)
    $owner_can_edit = ($dashboard['user_id'] !== NULL && (int)$dashboard['user_id'] === (int)$_SESSION['user_id']);
    $in_edit_mode = (isset($vars['edit']) && ($_SESSION['userlevel'] > 7 || $owner_can_edit));
    // No page-level navbar; use edit well below for actions
    if ($in_edit_mode) {
      // Simple collapsible reorder interface
      echo '<div id="dash-reorder" style="display:none; margin-top:10px;">';
      echo '<div class="well well-sm">';
      echo '<div class="row">';
      echo '<div class="col-md-12">';
      echo '<h5 style="margin-top:0;"><i class="sprite-sort"></i> Drag to Reorder <small class="text-muted pull-right"><a href="#" onclick="dashReorderToggle(); return false;">Close</a></small></h5>';
      echo '</div>';
      echo '</div>';

      // Build lists
      $my_rows = dbFetchRows("SELECT dash_id, dash_name FROM `dashboards` WHERE `user_id` = ? ORDER BY `dash_order`, `dash_id`", [(int)$_SESSION['user_id']]);
      $pub_rows = [];
      if ($_SESSION['userlevel'] >= 10) {
        $pub_rows = dbFetchRows("SELECT dash_id, dash_name, user_id FROM `dashboards` WHERE `is_public` = 1 ORDER BY `dash_order`, `dash_id`", []);
      }

      echo '<div class="row">';

      // My Dashboards list - horizontal
      if (!empty($my_rows)) {
        echo '<div class="col-md-6">';
        echo '<strong>My Dashboards:</strong>';
        echo '<div id="dash-reorder-my-list" style="margin-top:5px;">';
        foreach ($my_rows as $row) {
          $current = ((int)$row['dash_id'] === (int)$vars['dash']) ? ' alert-info' : ' alert-default';
          echo '<div class="alert' . $current . '" data-dash="' . (int)$row['dash_id'] . '" style="padding:5px 8px; margin:2px; cursor:move; display:inline-block;">';
          echo '<i class="sprite-move drag-handle" style="margin-right:5px;"></i>' . escape_html($row['dash_name']);
          echo '</div>';
        }
        echo '</div>';
        echo '</div>';
      }

      // Public Dashboards list - horizontal (admin only)
      if (!empty($pub_rows) && $_SESSION['userlevel'] >= 10) {
        echo '<div class="col-md-6">';
        echo '<strong>Public Dashboards:</strong>';
        echo '<div id="dash-reorder-public-list" style="margin-top:5px;">';
        foreach ($pub_rows as $row) {
          if ((int)$row['user_id'] === (int)$_SESSION['user_id']) { continue; }
          $current = ((int)$row['dash_id'] === (int)$vars['dash']) ? ' alert-info' : ' alert-default';
          echo '<div class="alert' . $current . '" data-dash="' . (int)$row['dash_id'] . '" style="padding:5px 8px; margin:2px; cursor:move; display:inline-block;">';
          echo '<i class="sprite-move drag-handle" style="margin-right:5px;"></i>' . escape_html($row['dash_name']);
          echo '</div>';
        }
        echo '</div>';
        echo '</div>';
      }

      echo '</div>'; // row
      echo '</div>'; // well
      echo '</div>'; // #dash-reorder
    }

    // Owner or admin can edit
    if ($in_edit_mode) {
        // Build Add Widget options from widget registry ($config['widgets']) with subtext (descriptions)
        $types_values = [];
        if (isset($config['widgets']) && is_array($config['widgets'])) {
            $labels = [];
            foreach ($config['widgets'] as $key => $def) {
                if (!empty($def['deprecated'])) { continue; }
                if ($key === 'graph') { continue; } // added via graph page flow
                $label = isset($def['name']) ? $def['name'] : nicecase($key);
                $descr = isset($def['descr']) ? $def['descr'] : '';
                $types_values[$key] = [ 'name' => $label, 'subtext' => $descr ];
                $labels[$key] = $label; // natural sort
            }
            if (!empty($labels)) {
                natcasesort($labels);
                $sorted = [];
                foreach ($labels as $k => $l) { $sorted[$k] = $types_values[$k]; }
                $types_values = $sorted;
            }
        } else {
            // Fallback list
            $types_values = [
              'map'            => [ 'name' => 'Map',            'subtext' => '' ],
              'alert_table'    => [ 'name' => 'Alert Table',    'subtext' => '' ],
              'alert_boxes'    => [ 'name' => 'Alert Boxes',    'subtext' => '' ],
              'alertlog'       => [ 'name' => 'Alert Log',      'subtext' => '' ],
              'port_percent'   => [ 'name' => 'Traffic Composition', 'subtext' => '' ],
              'status_summary' => [ 'name' => 'Status Summary', 'subtext' => '' ],
              'syslog'         => [ 'name' => 'Syslog',         'subtext' => '' ],
              'syslog_alerts'  => [ 'name' => 'Syslog Alerts',  'subtext' => '' ],
              'eventlog'       => [ 'name' => 'Eventlog',       'subtext' => '' ]
            ];
        }

        // Compact editing bar using same pattern as syslog page
        $form = [
          'type'          => 'rows',
          'space'         => '5px',
          'submit_by_key' => TRUE,
          'id'            => 'dashboard_editor'
        ];

        $form['row'][0]['dash_id'] = [ 'type' => 'hidden', 'value' => $dashboard['dash_id'], 'id' => 'dash_id' ];

        // Name field
        $form['row'][0]['dash_name'] = [
          'type'        => 'text',
          'name'        => 'Name',
          'placeholder' => 'Dashboard Name',
          'value'       => $dashboard['dash_name'],
          'width'       => '100%',
          'div_class'   => 'col-lg-2 col-md-3 col-sm-6',
          'id'          => 'dash_name'
        ];

        // Description field
        $form['row'][0]['dash_descr'] = [
          'type'        => 'text',
          'name'        => 'Description',
          'placeholder' => 'Description',
          'value'       => $dashboard['descr'],
          'width'       => '100%',
          'div_class'   => 'col-lg-2 col-md-3 col-sm-6',
          'id'          => 'dash_descr'
        ];

        // Add widget select
        $form['row'][0]['widget_type'] = [
          'type'      => 'select',
          'name'      => 'Widget',
          'width'     => '100%',
          'div_class' => 'col-lg-2 col-md-2 col-sm-4',
          'subtext'   => TRUE,
          'values'    => $types_values
        ];

        // Add button
        $form['row'][0]['add'] = [
          'type'      => 'submit',
          'class'     => 'btn-success',
          'value'     => 'Add',
          'icon'      => $config['icon']['plus'],
          'div_class' => 'col-lg-1 col-md-1 col-sm-2'
        ];

        // Public/Private switch
        $form['row'][0]['dash_public'] = [
          'type'      => 'switch-ng',
          'name'      => 'Visibility',
          'div_class' => 'col-lg-1 col-md-1 col-sm-3',
          'on-text'   => 'Public',
          'off-text'  => 'Private',
          'value'     => ($dashboard['is_public'] ? 'on' : ''),
          'onchange'  => 'dashSetPublic();',
        ];

        // Actions dropdown
        $form['row'][0]['actions_dropdown'] = [
          'type'      => 'raw',
          'div_class' => 'col-lg-2 col-md-2 col-sm-4',
          'value'     => '<div class="btn-group">
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                              <i class="sprite-tools"></i> Actions <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu">
                              <li><a href="#" onclick="dashSetDefault(); return false;"><i class="sprite-star"></i> Set as Default</a></li>
                              <li><a href="#" onclick="dashReorderToggle(); return false;"><i class="sprite-sort"></i> Reorder Dashboards</a></li>
                              <li class="divider"></li>
                              <li><a href="#" onclick="dashClone(); return false;"><i class="sprite-overview"></i> Clone Dashboard</a></li>
                              <li><a href="#" onclick="dashExport(); return false;"><i class="sprite-download"></i> Export Dashboard</a></li>
                              <li><a href="#" onclick="dashImport(); return false;"><i class="sprite-upload"></i> Import Dashboard</a></li>
                              <li class="divider"></li>
                              <li><a href="#" onclick="dashDelete(); return false;" class="text-danger"><i class="sprite-cancel"></i> Delete Dashboard</a></li>
                            </ul>
                          </div>'
        ];


        print_form($form);
    }

    // Error/alert container
    echo '<div id="dashboard-alerts"></div>';
    echo '<div class="row">';
    echo '<div class="grid-stack" id="grid">';
    echo '</div>';
    echo '</div>';
    // Expose CSRF request token for AJAX calls
    if (isset($_SESSION['requesttoken'])) {
        echo generate_form_element(['type' => 'hidden', 'id' => 'requesttoken', 'value' => $_SESSION['requesttoken']]);
    }

    ?>

    <!--- <textarea id="saved-data" cols="100" rows="20" readonly="readonly"></textarea> -->

    <script type="text/javascript">
      // Build initial grid from DB
      var initial_grid = [
        <?php
          $data = [];
          $widgets = dbFetchRows("SELECT * FROM `dash_widgets` WHERE `dash_id` = ? ORDER BY `y`,`x`", [$dashboard['dash_id']]);
          foreach ($widgets as $widget) {
              $node = [
                'width'  => (int)$widget['width'],
                'height' => (int)$widget['height'],
                'id'     => (string)$widget['widget_id'],
                'type'   => (string)$widget['widget_type']
              ];
              if (is_numeric($widget['x'])) {
                  $node['x'] = (int)$widget['x'];
              }
              if (is_numeric($widget['y'])) {
                  $node['y'] = (int)$widget['y'];
              }
              $data[] = json_encode($node);
          }
          echo implode(",", $data);
        ?>
      ];

      // Build widget defaults map from registry (if available)
      var widgetDefaults = {
        <?php
          $defs = [];
          if (isset($config['widgets']) && is_array($config['widgets'])) {
            // Use same sorted order as the select
            $types_sorted = [];
            foreach ($config['widgets'] as $k => $def) {
              if (!empty($def['deprecated']) || $k === 'graph') { continue; }
              $types_sorted[$k] = isset($def['name']) ? $def['name'] : nicecase($k);
            }
            asort($types_sorted, SORT_NATURAL | SORT_FLAG_CASE);
            foreach (array_keys($types_sorted) as $key) {
              $def = $config['widgets'][$key];
              $w = isset($def['defaults']['width']) ? (int)$def['defaults']['width'] : 4;
              $h = isset($def['defaults']['height']) ? (int)$def['defaults']['height'] : 3;
              $min_w = array_key_exists('min_width', $def['defaults']) ? (int)$def['defaults']['min_width'] : NULL;
              $max_w = array_key_exists('max_width', $def['defaults']) ? (int)$def['defaults']['max_width'] : NULL;
              $min_h = array_key_exists('min_height', $def['defaults']) ? (int)$def['defaults']['min_height'] : NULL;
              $max_h = array_key_exists('max_height', $def['defaults']) ? (int)$def['defaults']['max_height'] : NULL;

              $parts = ['w: ' . $w, 'h: ' . $h];
              if ($min_w !== NULL) { $parts[] = 'minW: ' . $min_w; }
              if ($max_w !== NULL) { $parts[] = 'maxW: ' . $max_w; }
              if ($min_h !== NULL) { $parts[] = 'minH: ' . $min_h; }
              if ($max_h !== NULL) { $parts[] = 'maxH: ' . $max_h; }

              $defs[] = '"' . $key . '": { ' . implode(', ', $parts) . ' }';
            }
          }
          echo implode(",\n        ", $defs);
        ?>
      };

      // Initialize dashboard
      ObserviumDashboardInit({
        cellHeight: <?php echo $grid_cell_height; ?>,
        hMargin: <?php echo $grid_h_margin; ?>,
        vMargin: <?php echo $grid_v_margin; ?>,
        isEditing: <?php echo ((isset($vars['edit']) && ($_SESSION['userlevel'] > 7 || ($dashboard['user_id'] !== NULL && (int)$dashboard['user_id'] === (int)$_SESSION['user_id'])) ) ? 'true' : 'false'); ?>,
        dashId: <?php echo (int)$dashboard['dash_id']; ?>,
        slug: <?php echo json_encode((string)$dashboard['slug']); ?>,
        initialGrid: initial_grid,
        requesttoken: (document.getElementById('requesttoken') ? document.getElementById('requesttoken').value : ''),
        widgetDefaults: widgetDefaults,
        redirectUrl: '<?php echo generate_url(['page' => 'dashboard']); ?>'
      });
    </script>

    <?php
// print_vars($widgets);
    ?>

    <!-- styles moved to html/css/dashboard.css -->

    <!-- Modal -->
    <div class="modal fade" id="config-modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title">Configure Widget</h4>
                </div>
                <div id="config-modal-body" class="modal-body">
                    <div class="te"></div>
                </div>
                <!--
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Save changes</button>
                </div>
                -->
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
    <!-- /.modal -->

    <?php

    if ($_SESSION['userlevel'] > 7) {
        if (isset($vars['edit'])) {
            $url  = generate_url($vars, ['edit' => NULL]);
            $text = "Disable Editing Mode";
        } else {
            $url  = generate_url($vars, ['edit' => 'yes']);
            $text = "Enable Editing Mode";
        }

        $footer_entry     = '<li><a href="' . $url . '" title="' . escape_html($text) . '"><i class="sprite-sliders"></i></a></li>';
        $footer_entries[] = $footer_entry;
    }


} else {
    print_error('Dashboard does not exist!');
}

// EOF
