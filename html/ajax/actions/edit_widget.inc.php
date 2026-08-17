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

if ($readonly) {
    return;
} // Currently edit allowed only for 7+

$widget = dbFetchRow("SELECT * FROM `dash_widgets` WHERE `widget_id` = ?", [$vars['widget_id']]);

$widget['widget_config'] = safe_json_decode($widget['widget_config']);

switch ($widget['widget_type']) {

    case "graph":

        if (safe_count($widget['widget_config'])) {

//      echo '
//      <form onsubmit="return false">
//        Title  <input name="widget-config-input" data-field="title" value="'.$widget['widget_config']['title'].'" data-id="'.$widget['widget_id'].'"></input>
//      </form>
//      ';

            //r($widget['widget_config']);

            //r(isset($widget['widget_config']['legend']) && $widget['widget_config']['legend'] === 'no');

            $modal_args = [
              'id'    => 'modal-edit_widget_' . $widget['widget_id'],
              'title' => 'Configure Widget',
              //'hide'  => TRUE,
              //'fade'  => TRUE,
              //'role'  => 'dialog',
              //'class' => 'modal-md',
            ];

            $form                       = [
              'form_only'  => TRUE, // Do not add modal open/close divs (it's generated outside)
              'type'       => 'horizontal',
              'id'         => 'edit_widget_' . $widget['widget_id'],
              'userlevel'  => 7,          // Minimum user level for display form
              'modal_args' => $modal_args, // !!! This generate modal specific form
              //'help'     => 'This will completely delete the rule and all associations and history.',
              'class'      => '', // Clean default box class!
              //'url'       => generate_url([ 'page' => 'syslog_rules' ]),
              'onsubmit'   => "return false",
            ];
            $form['fieldset']['body']   = ['class' => 'modal-body'];   // Required this class for modal body!
            $form['fieldset']['footer'] = ['class' => 'modal-footer']; // Required this class for modal footer!

            $form['row'][1]['widget-config-title']  = [
              'type'        => 'text',
              'fieldset'    => 'body',
              'name'        => 'Title',
              'placeholder' => 'Graph Title',
              'class'       => 'input-xlarge',
              'attribs'     => [
                'data-id'    => $widget['widget_id'],
                'data-field' => 'title',
                'data-type'  => 'text'
              ],
              'value'       => $widget['widget_config']['title']
            ];
            $form['row'][2]['widget-config-legend'] = [
              'type'     => 'checkbox',
              'fieldset' => 'body',
              'name'     => 'Show Legend',
              //'placeholder' => 'Yes, please delete this rule.',
              //'onchange'    => "javascript: toggleAttrib('disabled', 'delete_button_".$la['la_id']."'); showDiv(!this.checked, 'warning_".$la['la_id']."_div');",
              'attribs'  => [
                'data-id'    => $widget['widget_id'],
                'data-field' => 'legend',
                'data-type'  => 'checkbox'
              ],
              'value'    => safe_empty($widget['widget_config']['legend']) ? 'yes' : $widget['widget_config']['legend'] //'legend'
            ];


            $form['row'][8]['close'] = [
              'type'      => 'submit',
              'fieldset'  => 'footer',
              'div_class' => '', // Clean default form-action class!
              'name'      => 'Close',
              'icon'      => '',
              'attribs'   => [
                'data-dismiss' => 'modal',
                'aria-hidden'  => 'true'
              ]
            ];

            echo generate_form_modal($form);
            unset($form);

            /*
            echo '
      <form onsubmit="return false" class="form form-horizontal" style="margin-bottom: 0px;">
        <fieldset>
        <div id="purpose_div" class="control-group" style="margin-bottom: 10px;"> <!-- START row-1 -->
          <label class="control-label" for="purpose">Title</label>
          <div id="purpose_div" class="controls">
            <input type="text" placeholder="Graph Title" name="widget-config-title" class="input" data-field="title" style="width: 100%;" value="'.$widget['widget_config']['title'].'" data-id="'.$widget['widget_id'].'">
          </div>
        </div>

        <div id="ignore_div" class="control-group" style="margin-bottom: 10px;"> <!-- START row-6 -->
          <label class="control-label" for="ignore">Show Legend</label>
          <div id="ignore_div" class="controls">
            <input type="checkbox" name="widget-config-legend" data-field="legend" data-type="checkbox" value="legend" '.(isset($widget['widget_config']['legend']) && $widget['widget_config']['legend'] === 'no' ? '' : 'checked').' data-id="'.$widget['widget_id'].'">
          </div>
        </div>
      </fieldset>  <!-- END fieldset-body -->

      <div class="modal-footer">
         <fieldset>
            <button id="close" name="close" type="submit" class="btn btn-default text-nowrap" value="" data-dismiss="modal" aria-hidden="true">Close</button>
            <!-- <button id="action" name="action" type="submit" class="btn btn-primary text-nowrap" value="add_contact"><i style="margin-right: 0px;" class="icon-ok icon-white"></i>&nbsp;&nbsp;Add Contact</button> -->
         </fieldset>
      </div>

      </form>';
            */


        } else {

            print_message('To add a graph to this widget, navigate to the required graph and use the "Add To Dashboard" function on the graph page.');

            echo '<h3>Step 1. Locate Graph and click for Graph Browser.</h3>';
            echo '<img class="img img-thumbnail" src="images/doc/add_graph_1">';

            echo '<h3>Step 2. Select Add to Dashboard in Graph Browser.</h3>';
            echo '<img class="img" src="images/doc/add_graph_2">';
        }
        break;

    case "alert_table":
        // Load table filter definitions
        include_once($config['html_dir'] . '/includes/definitions/table_filters.inc.php');

        $modal_args = [
            'id'    => 'modal-edit_widget_' . $widget['widget_id'],
            'title' => 'Configure Alert Table Widget',
        ];

        $form = [
            'form_only'  => TRUE,
            'type'       => 'horizontal',
            'id'         => 'edit_widget_' . $widget['widget_id'],
            'userlevel'  => 7,
            'modal_args' => $modal_args,
            'class'      => '',
            'onsubmit'   => "return false",
        ];
        $form['fieldset']['body']   = ['class' => 'modal-body'];
        $form['fieldset']['footer'] = ['class' => 'modal-footer'];

        // Generate form fields from table filter definitions
        $row_num = 1;
        foreach ($config['table_filters']['alert'] as $filter_id => $filter_config) {
            $form_field = [
                'type'        => $filter_config['type'],
                'fieldset'    => 'body',
                'name'        => $filter_config['name'],
                'attribs'     => [
                    'data-id'    => $widget['widget_id'],
                    'data-field' => $filter_id,
                    'data-type'  => $filter_config['type']
                ]
            ];

            // Add type-specific properties
            if (isset($filter_config['values'])) {
                $form_field['values'] = $filter_config['values'];
            }
            if (isset($filter_config['placeholder'])) {
                $form_field['placeholder'] = $filter_config['placeholder'];
            }
            if (isset($filter_config['width'])) {
                $form_field['width'] = $filter_config['width'];
            }

            // Add selectpicker class for select/multiselect fields
            if ($filter_config['type'] === 'select' || $filter_config['type'] === 'multiselect') {
                $form_field['class'] = 'selectpicker';

                // For multiselect, add multiple attribute
                if ($filter_config['type'] === 'multiselect') {
                    $form_field['attribs']['multiple'] = 'multiple';
                }
            }

            // Set current value from widget config
            $current_value = $widget['widget_config'][$filter_id] ?? $filter_config['default'] ?? '';

            // Handle multiselect values properly
            if ($filter_config['type'] === 'multiselect') {
                // Ensure value is an array for multiselects
                if (!is_array($current_value)) {
                    $current_value = !empty($current_value) ? [$current_value] : [];
                }
            }

            $form_field['value'] = $current_value;

            $form['row'][$row_num]['widget-config-' . $filter_id] = $form_field;
            $row_num++;
        }

        $form['row'][98]['save'] = [
            'type'      => 'submit',
            'fieldset'  => 'footer',
            'div_class' => '',
            'name'      => 'Save',
            'icon'      => 'icon-ok',
            'class'     => 'btn-primary',
            'attribs'   => [
                'onclick' => 'saveWidgetConfig(' . $widget['widget_id'] . ')'
            ]
        ];

        $form['row'][99]['close'] = [
            'type'      => 'submit',
            'fieldset'  => 'footer',
            'div_class' => '',
            'name'      => 'Close',
            'icon'      => '',
            'attribs'   => [
                'data-dismiss' => 'modal',
                'aria-hidden'  => 'true'
            ]
        ];

        echo generate_form_modal($form);
        break;

    case "status_table":
        // Get current values or defaults from global config
        $current_config = $widget['widget_config'] ?: [];

        echo '<form onsubmit="return false" class="form form-horizontal" style="margin-bottom: 0px;">';
        echo '<fieldset>';

        // Status categories section
        echo '<h4><i class="' . $config['icon']['status'] . '"></i> Status Categories</h4>';

        // Generate checkboxes for each category
        $categories = [
            'devices' => ['label' => 'Down Devices', 'default' => $config['frontpage']['device_status']['devices']],
            'ports' => ['label' => 'Down Ports', 'default' => $config['frontpage']['device_status']['ports']],
            'neighbours' => ['label' => 'Down Neighbours (CDP/LLDP)', 'default' => $config['frontpage']['device_status']['neighbours']],
            'errors' => ['label' => 'Port Errors', 'default' => $config['frontpage']['device_status']['errors']],
            'bgp' => ['label' => 'BGP Sessions Down', 'default' => $config['frontpage']['device_status']['bgp']],
            'uptime' => ['label' => 'Recent Reboots', 'default' => $config['frontpage']['device_status']['uptime']],
            'services' => ['label' => 'Down Services', 'default' => $config['frontpage']['device_status']['services']]
        ];

        foreach ($categories as $field => $info) {
            $checked = isset($current_config[$field]) ? get_var_true($current_config[$field]) : $info['default'];
            echo '<div class="control-group" style="margin-bottom: 10px;">';
            echo '<label class="control-label">' . escape_html($info['label']) . '</label>';
            echo '<div class="controls">';
            echo '<input type="checkbox" data-field="' . $field . '" data-type="checkbox" data-id="' . $widget['widget_id'] . '"' . ($checked ? ' checked' : '') . '>';
            echo '</div>';
            echo '</div>';
        }

        // Settings section
        echo '<h4><i class="' . $config['icon']['settings'] . '"></i> Display Settings</h4>';

        // Max interval
        $max_interval = isset($current_config['max_interval']) ? $current_config['max_interval'] : $config['frontpage']['device_status']['max']['interval'];
        echo '<div class="control-group" style="margin-bottom: 10px;">';
        echo '<label class="control-label">Max Interval (hours)</label>';
        echo '<div class="controls">';
        echo '<input type="text" class="input-small" placeholder="24" data-field="max_interval" data-type="text" data-id="' . $widget['widget_id'] . '" value="' . escape_html($max_interval) . '">';
        echo '</div>';
        echo '</div>';

        // Max count
        $max_count = isset($current_config['max_count']) ? $current_config['max_count'] : $config['frontpage']['device_status']['max']['count'];
        echo '<div class="control-group" style="margin-bottom: 10px;">';
        echo '<label class="control-label">Max Count</label>';
        echo '<div class="controls">';
        echo '<input type="text" class="input-small" placeholder="200" data-field="max_count" data-type="text" data-id="' . $widget['widget_id'] . '" value="' . escape_html($max_count) . '">';
        echo '</div>';
        echo '</div>';

        echo '</fieldset>';

        // Footer with buttons
        echo '<div class="modal-footer">';
        echo '<fieldset>';
        echo '<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>';
        echo '<button type="button" class="btn btn-primary" onclick="saveWidgetConfig(' . $widget['widget_id'] . ')"><i class="icon-ok"></i> Save</button>';
        echo '</fieldset>';
        echo '</div>';

        echo '</form>';
        break;

    case "clock":
        $modal_args = [
            'id'    => 'modal-edit_widget_' . $widget['widget_id'],
            'title' => 'Configure Clock Widget'
        ];

        $form = [
            'form_only'  => TRUE,
            'type'       => 'horizontal',
            'id'         => 'edit_widget_' . $widget['widget_id'],
            'userlevel'  => 7,
            'modal_args' => $modal_args,
            'class'      => '',
            'onsubmit'   => "return false",
        ];
        $form['fieldset']['body']   = ['class' => 'modal-body'];
        $form['fieldset']['footer'] = ['class' => 'modal-footer'];

        $row = 1;

        $form['row'][$row]['clock-style'] = [
            'type'      => 'select',
            'fieldset'  => 'body',
            'name'      => 'Clock Style',
            'values'    => [
                'digital' => 'Digital',
                'analog'  => 'Analog'
            ],
            'value'     => $widget['widget_config']['style'] ?? 'digital',
            'attribs'   => [
                'data-field' => 'style',
                'data-type'  => 'text'
            ]
        ];
        $row++;

        $form['row'][$row]['clock-format'] = [
            'type'      => 'select',
            'fieldset'  => 'body',
            'name'      => 'Time Format',
            'values'    => [
                '24' => '24 Hour',
                '12' => '12 Hour (AM/PM)'
            ],
            'value'     => $widget['widget_config']['format'] ?? '24',
            'attribs'   => [
                'data-field' => 'format',
                'data-type'  => 'text'
            ]
        ];
        $row++;

        $timezones = [
            'local'    => 'Local Browser Time',
            'UTC'      => 'UTC',
            'UTC-12'   => 'UTC-12',
            'UTC-11'   => 'UTC-11',
            'UTC-10'   => 'UTC-10',
            'UTC-9'    => 'UTC-9',
            'UTC-8'    => 'UTC-8',
            'UTC-7'    => 'UTC-7',
            'UTC-6'    => 'UTC-6',
            'UTC-5'    => 'UTC-5',
            'UTC-4'    => 'UTC-4',
            'UTC-3'    => 'UTC-3',
            'UTC-2'    => 'UTC-2',
            'UTC-1'    => 'UTC-1',
            'UTC+1'    => 'UTC+1',
            'UTC+2'    => 'UTC+2',
            'UTC+3'    => 'UTC+3',
            'UTC+4'    => 'UTC+4',
            'UTC+5'    => 'UTC+5',
            'UTC+6'    => 'UTC+6',
            'UTC+7'    => 'UTC+7',
            'UTC+8'    => 'UTC+8',
            'UTC+9'    => 'UTC+9',
            'UTC+10'   => 'UTC+10',
            'UTC+11'   => 'UTC+11',
            'UTC+12'   => 'UTC+12'
        ];

        $form['row'][$row]['clock-timezone'] = [
            'type'      => 'select',
            'fieldset'  => 'body',
            'name'      => 'Timezone',
            'values'    => $timezones,
            'value'     => $widget['widget_config']['timezone'] ?? 'UTC',
            'attribs'   => [
                'data-field' => 'timezone',
                'data-type'  => 'text'
            ]
        ];
        $row++;

        $form['row'][$row]['clock-show-date'] = [
            'type'     => 'checkbox',
            'fieldset' => 'body',
            'name'     => 'Show Date (digital only)',
            'value'    => isset($widget['widget_config']['show_date']) ?
                         ($widget['widget_config']['show_date'] === 'yes' || $widget['widget_config']['show_date'] === TRUE ? 'yes' : 'no') : 'yes',
            'attribs'  => [
                'data-field' => 'show_date',
                'data-type'  => 'checkbox'
            ]
        ];
        $row++;

        $form['row'][$row]['clock-show-seconds'] = [
            'type'     => 'checkbox',
            'fieldset' => 'body',
            'name'     => 'Show Seconds',
            'value'    => isset($widget['widget_config']['show_seconds']) ?
                         ($widget['widget_config']['show_seconds'] === 'yes' || $widget['widget_config']['show_seconds'] === TRUE ? 'yes' : 'no') : 'yes',
            'attribs'  => [
                'data-field' => 'show_seconds',
                'data-type'  => 'checkbox'
            ]
        ];
        $row++;

        $form['row'][$row]['clock-bg-color'] = [
            'type'        => 'text',
            'fieldset'    => 'body',
            'name'        => 'Background Color',
            'placeholder' => '#ffffff or transparent',
            'class'       => 'input-small',
            'value'       => $widget['widget_config']['bg_color'] ?? '',
            'attribs'     => [
                'data-field' => 'bg_color',
                'data-type'  => 'text'
            ]
        ];
        $row++;

        $form['row'][$row]['clock-text-color'] = [
            'type'        => 'text',
            'fieldset'    => 'body',
            'name'        => 'Text Color',
            'placeholder' => '#333333',
            'class'       => 'input-small',
            'value'       => $widget['widget_config']['text_color'] ?? '',
            'attribs'     => [
                'data-field' => 'text_color',
                'data-type'  => 'text'
            ]
        ];
        $row++;

        $form['row'][$row]['save'] = [
            'type'      => 'submit',
            'fieldset'  => 'footer',
            'div_class' => '',
            'class'     => 'btn-primary',
            'name'      => 'Save',
            'icon'      => 'icon-ok',
            'attribs'   => [
                'onclick' => 'saveWidgetConfig(' . $widget['widget_id'] . ')'
            ]
        ];
        $row++;

        $form['row'][$row]['close'] = [
            'type'      => 'submit',
            'fieldset'  => 'footer',
            'div_class' => '',
            'name'      => 'Close',
            'icon'      => '',
            'attribs'   => [
                'data-dismiss' => 'modal',
                'aria-hidden'  => 'true'
            ]
        ];

        echo generate_form_modal($form);
        break;

    default:
        r($widget['widget_config']);
}

// EOF
