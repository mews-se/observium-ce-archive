<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     web
 * @copyright  (C) Adam Armstrong
 *
 */

//r($attribs);
if (get_var_true($vars['editing'])) {
    $updated = 0;
    if ($readonly) {
        print_error_permission('You have insufficient permissions to edit settings.');
    } else {
        if (!safe_empty($vars['ipmi_hostname'])) {
            set_dev_attrib($device, 'ipmi_hostname', $vars['ipmi_hostname']);
            $updated++;
        } elseif (!safe_empty($attribs['ipmi_hostname'])) {
            del_dev_attrib($device, 'ipmi_hostname');
            $updated++;
        }
        if (!safe_empty($vars['ipmi_username'])) {
            set_dev_attrib($device, 'ipmi_username', $vars['ipmi_username']);
            $updated++;
        } elseif (!safe_empty($attribs['ipmi_username'])) {
            del_dev_attrib($device, 'ipmi_username');
            $updated++;
        }
        if (!safe_empty($vars['ipmi_password'])) {
            set_dev_attrib($device, 'ipmi_password', $vars['ipmi_password']);
            $updated++;
        } elseif (!safe_empty($attribs['ipmi_password'])) {
            del_dev_attrib($device, 'ipmi_password');
            $updated++;
        }
        // IPMI v2.0 Key
        if (!safe_empty($vars['ipmi_key'])) {
            set_dev_attrib($device, 'ipmi_key', $vars['ipmi_key']);
            $updated++;
        } elseif (!safe_empty($attribs['ipmi_key'])) {
            del_dev_attrib($device, 'ipmi_key');
            $updated++;
        }
        if (is_valid_param($vars['ipmi_port'], 'port')) {
            set_dev_attrib($device, 'ipmi_port', $vars['ipmi_port']);
            $updated++;
        } elseif (!safe_empty($attribs['ipmi_port'])) {
            del_dev_attrib($device, 'ipmi_port');
            $updated++;
        }

        // We check interface & userlevel input from the dropdown against the allowed values in the definition array.
        if (!safe_empty($vars['ipmi_interface']) && in_array($vars['ipmi_interface'], array_keys($config['ipmi']['interfaces']))) {
            set_dev_attrib($device, 'ipmi_interface', $vars['ipmi_interface']);
            $updated++;
        } elseif (!safe_empty($attribs['ipmi_interface'])) {
            del_dev_attrib($device, 'ipmi_interface');
            print_error('Invalid interface specified (' . $vars['ipmi_interface'] . ').');
            $updated++;
        }

        if (!safe_empty($vars['ipmi_userlevel']) && in_array($vars['ipmi_userlevel'], array_keys($config['ipmi']['userlevels']))) {
            set_dev_attrib($device, 'ipmi_userlevel', $vars['ipmi_userlevel']);
            $updated++;
        } elseif (!safe_empty($attribs['ipmi_userlevel'])) {
            del_dev_attrib($device, 'ipmi_userlevel');
            print_error('Invalid user level specified (' . $vars['ipmi_userlevel'] . ').');
            $updated++;
        }

        $update_message = "Device IPMI data updated.";
    }

    if ($updated && $update_message) {
        print_message($update_message);
    } elseif ($update_message) {
        print_error($update_message);
    }
}

if (!file_exists($config['ipmitool'])) {
    print_warning("The ipmitool binary was not found at the configured path (" . $config['ipmitool'] . "). IPMI polling will not work.");
}


$form = [
    'type'     => 'horizontal',
    'id'       => 'edit',
    //'space'     => '20px',
    'title'    => 'IPMI Settings',
    //'icon'      => 'oicon-gear',
    //'class'     => 'box box-solid',
    'fieldset' => ['edit' => ''],
];

$form['row'][0]['editing'] = [
    'type'  => 'hidden',
    'value' => 'yes'
];
$form['row'][1]['ipmi_hostname'] = [
    'type'     => 'text',
    'name'     => 'IPMI Hostname',
    'width'    => '250px',
    'readonly' => $readonly,
    'value'    => get_dev_attrib($device, 'ipmi_hostname')
];
$form['row'][2]['ipmi_port'] = [
    'type'     => 'text',
    'name'     => 'IPMI Port',
    'width'    => '250px',
    'readonly' => $readonly,
    'value'    => get_dev_attrib($device, 'ipmi_port')
];
$form['row'][3]['ipmi_username'] = [
    'type'     => 'text',
    'name'     => 'IPMI Username',
    'width'    => '250px',
    'readonly' => $readonly,
    'value'    => get_dev_attrib($device, 'ipmi_username')
];
$form['row'][4]['ipmi_password'] = [
    'type'          => 'password',
    'name'          => 'IPMI Password',
    'width'         => '250px',
    'readonly'      => $readonly,
    'show_password' => !$readonly,
    'value'         => get_dev_attrib($device, 'ipmi_password')
];
$form['row'][5]['ipmi_key'] = [
    'type'          => 'password',
    'name'          => 'IPMI v2.0 Key',
    'width'         => '250px',
    'readonly'      => $readonly,
    'show_password' => !$readonly,
    'value'         => get_dev_attrib($device, 'ipmi_key')
];
$form['row'][6]['ipmi_userlevel'] = [
    'type'     => 'select',
    'name'     => 'IPMI Userlevel',
    'width'    => '250px',
    'readonly' => $readonly,
    'values'   => $config['ipmi']['userlevels'],
    'value'    => get_dev_attrib($device, 'ipmi_userlevel')
];
$form['row'][7]['ipmi_interface'] = [
    'type'     => 'select',
    'name'     => 'IPMI Interface',
    'width'    => '250px',
    'readonly' => $readonly,
    'values'   => $config['ipmi']['interfaces'],
    'value'    => get_dev_attrib($device, 'ipmi_interface')
];

$form['row'][10]['submit'] = [
    'type'     => 'submit',
    'name'     => 'Save Changes',
    'icon'     => 'icon-ok icon-white',
    'class'    => 'btn-primary',
    'readonly' => $readonly,
    'value'    => 'save'
];

print_form($form);
unset($form);

// EOF
