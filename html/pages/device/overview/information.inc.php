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

if (isset($overview_extended) && $overview_extended) {
    // From old information_extended.inc.php
    // FIXME. Not sure if it's required

    echo generate_box_open();

    echo('<table class="table table-condensed table-striped table-hover">');

    if ($config['web_show_overview_extra']) {
        echo '<tr>';
        echo '<td colspan=2 style="padding: 10px;">';

        /*
        if (is_file($config['html_dir'] . '/images/hardware/' . trim($device['sysObjectID'], ".") . '.png')) {
          echo '<img style="height: 100px; float: right;" src="'.$config['site_url'] . '/images/hardware/' . trim($device['sysObjectID'], ".") . '.png'.'"></img>';
        }
        */
        echo '<strong><i>' . escape_html($device['sysDescr']) . '</i></strong></td></tr>';
    }

} else {
    echo generate_box_open([ 'box-class' => 'hidden-xl' ]);

    echo('<table class="table table-condensed table-striped table-hover">');

    if ($config['web_show_overview_extra']) {
        echo('<tr><td colspan=2 style="padding: 10px;"><strong><i>' . escape_html($device['sysDescr']) . "</i></strong></td></tr>");
    }
}
unset($overview_extended);

// Groups
if (OBSERVIUM_EDITION !== 'community' && $config['web_show_overview_extra'] &&
    $_SESSION['userlevel'] >= 5 && $groups = get_entity_group_names('device', $device['device_id'])) {

    echo('<tr>
        <td class="entity">Groups</td>
        <td>');
    foreach ($groups as $group_id => $group) {
        $link = generate_link($group, [ 'page' => 'group', 'group_id' => $group_id ]); // always escaped (as default)
        echo '<span class="label">' . $link . '</span> ';
    }
    echo('</td>
      </tr>');
}

// External Poller
if (OBS_DISTRIBUTED && $config['web_show_overview_extra'] &&
    $_SESSION['userlevel'] >= 5 && $device['poller_id'] > 0) {
    $poller = get_poller($device['poller_id']);
    echo('<tr>
        <td class="entity">Poller</td>
        <td>' . generate_link($device['poller_id'] . ': ' . $poller['poller_name'], [ 'page' => 'devices', 'poller_id' => $device['poller_id'] ]) . '</td>
      </tr>');
}

if ($device['purpose']) {
    echo('<tr>
        <td class="entity">Description</td>
        <td>' . escape_html($device['purpose']) . '</td>
      </tr>');
}

if ($device['hardware']) {
    if ($device['vendor']) {
        echo('<tr>
          <td class="entity">Vendor/Hardware</td>
          <td>' . generate_link($device['vendor'],   [ 'page' => 'devices', 'vendor' => $device['vendor'] ]) . ' ' .
                  generate_link($device['hardware'], [ 'page' => 'devices', 'vendor' => $device['vendor'], 'hardware' => $device['hardware'] ]) . '</td>
        </tr>');
    } else {
        echo('<tr>
          <td class="entity">Hardware</td>
          <td>' . generate_link($device['hardware'], [ 'page' => 'devices', 'hardware' => $device['hardware'] ]) . '</td>
        </tr>');
    }
} elseif ($device['vendor']) {
    // Only Vendor exists
    echo('<tr>
        <td class="entity">Vendor</td>
        <td>' . generate_link($device['vendor'],   [ 'page' => 'devices', 'vendor' => $device['vendor'] ]) . '</td>
      </tr>');
}

if ($device['os'] !== 'generic') {
    echo('<tr>
        <td class="entity">Operating system</td>
        <td>' . generate_link($device['os_text'], [ 'page' => 'devices', 'os' => $device['os'] ]) . ' ' .
                generate_link($device['version'] . ($device['features'] ? ' (' . $device['features'] . ')' : ''), [ 'page' => 'devices', 'os' => $device['os'], 'version' => $device['version'] ]) . ' </td>
      </tr>');
}

if ($device['sysName']) {
    echo('<tr>
        <td class="entity">System name</td>');
    echo('
        <td>' . escape_html($device['sysName']) . '</td>
      </tr>');
}

if ($device['sysContact']) {
    echo('<tr>
        <td class="entity">Contact</td>');
    if (get_dev_attrib($device, 'override_sysContact_bool')) {
        echo('
        <td>' . escape_html(get_dev_attrib($device, 'override_sysContact_string')) . '</td>
      </tr>
      <tr>
        <td class="entity">SNMP Contact</td>');
    }
    echo('
        <td>' . escape_html($device['sysContact']) . '</td>
      </tr>');
}

if ($device['location']) {
    echo('<tr>
        <td class="entity">Location</td>
        <td>' . generate_link($device['location'], [ 'page' => 'devices', 'location' => '"' . $device['location'] . '"' ]) . '</td>
      </tr>');
    // if (get_dev_attrib($device, 'override_sysLocation_bool') && !empty($device['real_location'])) {
    //     echo('<tr>
    //     <td class="entity">SNMP Location</td>
    //     <td>' . escape_html($device['real_location']) . '</td>
    //   </tr>');
    // }
}

if ($device['asset_tag']) {
    echo('<tr>
        <td class="entity">Asset tag</td>
        <td>' . escape_html($device['asset_tag']) . '</td>
      </tr>');
}

if ($device['serial']) {
    echo('<tr>
        <td class="entity">Serial</td>
        <td>' . escape_html($device['serial']) . '</td>
      </tr>');
}

if ($config['web_show_overview_extra'] && $device['state']['la']['5min']) {
    if ($device['state']['la']['5min'] > 10) {
        $la_class = 'text-danger';
    } elseif ($device['state']['la']['5min'] > 4) {
        $la_class = 'text-warning';
    } else {
        $la_class = '';
    }
    echo('<tr>
        <td class="entity">Load average</td>
        <td class="' . $la_class . '">' . number_format((float)$device['state']['la']['1min'], 2) . ', ' .
         number_format((float)$device['state']['la']['5min'], 2) . ', ' .
         number_format((float)$device['state']['la']['15min'], 2) . '</td>
      </tr>');
}

if ($config['web_show_overview_extra'] && $device['ip'] && $_SESSION['userlevel'] >= 5) {
    echo('<tr>
        <td class="entity">Cached IP</td>');
    echo('
        <td>' . generate_link($device['ip'], [ 'page' => 'devices', 'ip' => $device['ip'] ]) . '</td>
      </tr>');
}

if ($device['uptime']) {
    echo('<tr>
        <td class="entity">Uptime</td>
        <td>' . device_uptime($device) . '</td>
      </tr>');
}
/*
if ($device['status_type'] && $device['status_type'] != 'ok')
{
  if ($device['status_type'] == 'ping')
  {
    $reason = 'not Pingable';
  }
  else if ($device['status_type'] == 'snmp')
  {
    $reason = 'not SNMPable';
  }
  else if ($device['status_type'] == 'dns')
  {
    $reason = 'DNS hostname unresolved';
  }

  echo('<tr>
        <td class="entity">Down reason</td>
        <td>' . $reason . '</td>
      </tr>');
}
*/

if ($device['last_rebooted']) {
    echo('<tr>
        <td class="entity">Last reboot</td>
        <td>' . format_unixtime($device['last_rebooted']) . '</td>
      </tr>');
}

echo("</table>");
echo generate_box_close();

// EOF
