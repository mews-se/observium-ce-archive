<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage notifications
 * @copyright  (C) Adam Armstrong
 *
 */

/**
 * Queue notifications for sending to contacts associated with an alert.
 *
 * @param array $entry  Alert entry from alert_table
 * @param string $type  Type of notification (default: "alert")
 * @param int $log_id   Alert log ID (optional)
 *
 * @return array          List of processed notification ids.
 */
function alert_notifier($entry, $type = "alert", $log_id = NULL) {

    $device = device_by_id_cache($entry['device_id']);

    $message_tags = alert_generate_tags($entry, $type);

    //logfile('debug.log', var_export($message, TRUE));

    $alert_id = $entry['alert_test_id'];

    $notify_status = FALSE; // Set alert notify status to FALSE by default

    $notification_type = 'alert';
    $contacts          = get_alert_contacts($device, $alert_id, $notification_type);

    $notification_ids = []; // Init list of Notification IDs
    foreach ($contacts as $contact) {

        // Add notification to queue
        $notification              = [
          'device_id'             => $device['device_id'],
          'log_id'                => $log_id,
          'aca_type'              => $notification_type,
          //'severity'              => 6,
          'endpoints'             => safe_json_encode($contact),
          'message_graphs'        => $message_tags['ENTITY_GRAPHS_ARRAY'],
          'notification_added'    => time(),
          'notification_lifetime' => 300,                      // Lifetime in seconds
          'notification_entry'    => safe_json_encode($entry), // Store full alert entry for use later if required (not sure that this needed)
        ];
        $notification_message_tags = $message_tags;
        unset($notification_message_tags['ENTITY_GRAPHS_ARRAY']); // graphs array stored in separate blob column message_graphs, do not duplicate this data
        $notification['message_tags'] = safe_json_encode($notification_message_tags);

        /// DEVEL
        //file_put_contents('/tmp/alert_'.$alert_id.'_'.$message_tags['ALERT_STATE'].'_'.time().'.json', safe_json_encode($notification, JSON_PRETTY_PRINT));

        $notification_id = dbInsert($notification, 'notifications_queue');

        print_cli_data("Queueing Notification ", "[" . $notification_id . "]");

        $notification_ids[] = $notification_id;
    }

    return $notification_ids;
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function alert_generate_subject($device, $prefix, $message_tags)
{
    $subject = "$prefix: [" . device_name($device) . ']';

    if ($message_tags['ENTITY_TYPE']) {
        $subject .= ' [' . $message_tags['ENTITY_TYPE'] . ']';
    }
    if ($message_tags['ENTITY_NAME'] && $message_tags['ENTITY_NAME'] != $device['hostname']) {
        $subject .= ' [' . $message_tags['ENTITY_NAME'] . ']';
    }
    $subject .= ' ' . $message_tags['ALERT_MESSAGE'];

    return $subject;
}

function alert_generate_tags($entry, $type = "alert") {

    global $alert_rules;

    $alert_unixtime = time(); // Store time when alert processed

    $device = device_by_id_cache($entry['device_id']);
    $entity = get_entity_by_id_cache($entry['entity_type'], $entry['entity_id']);
    $alert  = $alert_rules[$entry['alert_test_id']];

    // Get device group memberships for alert routing
    $device_groups = get_entity_group_names('device', $device['device_id']);

    // Get entity group memberships for alert routing
    $entity_groups = get_entity_group_names($entry['entity_type'], $entry['entity_id']);

    /// DEVEL
    print_debug_vars($entry);
    print_debug_vars($alert);
    print_debug_vars($entity);

    /*
    [conditions] => Array
      (
        [0] => Array
          (
            [metric] => sensor_value
            [condition] => >
            [value] => 30
          )

        [1] => Array
          (
            [metric] => sensor_value
            [condition] => <
            [value] => 2
          )

      )
     */
    //$conditions = json_decode($alert['conditions'], TRUE);

    // [state] => {"metrics":{"sensor_value":45},"failed":[{"metric":"sensor_value","condition":">","value":"30"}]}
    $state = safe_json_decode($entry['state']);
    $entry['metrics'] = $state['metrics']; // passed to alert_generate_graphs()

    $condition_array = [];
    foreach ($state['failed'] as $failed) {
        $condition_array[] = $failed['metric'] . " " . $failed['condition'] . " " . format_value($failed['value']) . " (" . format_value($state['metrics'][$failed['metric']]) . ")";
    }

    $metric_array = [];
    foreach ($state['metrics'] as $metric => $value) {
        $metric_array[] = $metric . ' = ' . $value;
    }

    // FIXME. Need move this function outside of generate tags,
    // because this is transport specific function.
    // Now do not generate graphs if $config['email']['graphs'] set to false for ANY transport!
    $graphs = alert_generate_graphs($entry, $entity);

    // Severity
    if (empty($alert['severity'])) {
        $alert['severity'] = 'crit';
    }
    $severity_name = $GLOBALS['config']['alerts']['severity'][$alert['severity']]['name'] ?? 'Critical';

    // FIXME. This is how was previously, seems as need something change here?
    $alert_duration = $entry['last_ok'] > 0 ? format_uptime($alert_unixtime - $entry['last_ok']) . " (" . format_unixtime($entry['last_ok']) . ")" : "Unknown";

    $alert_status = alert_status_array($entry, $alert['severity']);

    $message_tags = [
      'ALERT_STATE'             => $alert_status['status_name'],
      'ALERT_STATE_NAME'        => $alert_status['status_name_custom'],
      'ALERT_EMOJI'             => get_icon_emoji($alert_status['status_emoji']), // https://unicodey.com/emoji-data/table.htm
      'ALERT_EMOJI_NAME'        => $alert_status['status_emoji'],
      'ALERT_STATUS'            => $alert_status['status'],                       // Tag for templates (0 - ALERT, 1 - RECOVERY, 2 - DELAYED, 3 - SUPPRESSED, 9 - SYSLOG)
      'ALERT_STATUS_CUSTOM'     => $alert_status['status_custom'],                // Tag for templates (as defined in $config['alerts']['status'] array)
      //'ALERT_SEVERITY'          => $alert['severity'],                          // Only: crit(2), warn(4), info(6)
      'ALERT_SEVERITY'          => $severity_name,                                // Critical, Warning, Informational
      'ALERT_COLOR'             => $alert_status['status_color'],
      'ALERT_URL'               => generate_url([ 'page'        => 'device',
                                                  'device'      => $device['device_id'],
                                                  'tab'         => 'alert',
                                                  'alert_entry' => $entry['alert_table_id'] ]),
      'ALERT_UNIXTIME'          => $alert_unixtime,                        // Standard unixtime
      'ALERT_TIMESTAMP'         => date('Y-m-d H:i:s P', $alert_unixtime), //           ie: 2000-12-21 16:01:07 +02:00
      'ALERT_TIMESTAMP_RFC2822' => date('r', $alert_unixtime),             // RFC 2822, ie: Thu, 21 Dec 2000 16:01:07 +0200
      'ALERT_TIMESTAMP_RFC3339' => date(DATE_RFC3339, $alert_unixtime),    // RFC 3339, ie: 2005-08-15T15:52:01+00:00
      'ALERT_ID'                => $entry['alert_table_id'],
      'ALERT_MESSAGE'           => $alert['alert_message'],
      'ALERT_NAME'              => $alert['alert_name'],

      'CONDITIONS'          => implode(PHP_EOL . '             ', $condition_array),
      'METRICS'             => implode(PHP_EOL . '             ', $metric_array),
      'DURATION'            => $alert_duration,

      // Entity TAGs
      'ENTITY_URL'          => generate_entity_url($entry['entity_type'], $entry['entity_id']),
      'ENTITY_LINK'         => generate_entity_link($entry['entity_type'], $entry['entity_id'], $entity['entity_name']),
      'ENTITY_NAME'         => $entity['entity_name'],
      'ENTITY_ID'           => $entity['entity_id'],
      'ENTITY_TYPE'         => $alert['entity_type'],
      'ENTITY_DESCRIPTION'  => $entity['entity_descr'],
      //'ENTITY_GRAPHS'       => $graphs_html,          // Predefined/embedded html images
      'ENTITY_GRAPHS_ARRAY' => safe_json_encode($graphs),  // Json encoded images array

      // Entity group memberships
      'ENTITY_GROUPS'       => $entity_groups,                          // Array: {group_id: "group_name",...} for JSON transports
      'ENTITY_GROUP_NAMES'  => implode(', ', $entity_groups),           // Comma-separated group names for text
      'ENTITY_GROUP_IDS'    => implode(', ', array_keys($entity_groups)), // Comma-separated group IDs

      // Device TAGs
      'DEVICE_HOSTNAME'     => $device['hostname'],
      'DEVICE_SYSNAME'      => $device['sysName'],
      //'DEVICE_SYSDESCR'     => $device['sysDescr'],
      'DEVICE_DESCRIPTION'  => $device['purpose'],
      'DEVICE_ID'           => $device['device_id'],
      'DEVICE_URL'          => generate_device_url($device),
      'DEVICE_LINK'         => generate_device_link($device),
      'DEVICE_HARDWARE'     => $device['hardware'],
      'DEVICE_OS'           => $device['os_text'] . ' ' . $device['version'] . ($device['features'] ? ' (' . $device['features'] . ')' : ''),
      'DEVICE_TYPE'         => $device['type'],
      'DEVICE_LOCATION'     => $device['location'],
      'DEVICE_UPTIME'       => device_uptime($device),
      'DEVICE_REBOOTED'     => format_unixtime($device['last_rebooted']),

      // Device group memberships
      'DEVICE_GROUPS'       => $device_groups,                          // Array: {group_id: "group_name",...} for JSON transports
      'DEVICE_GROUP_NAMES'  => implode(', ', $device_groups),           // Comma-separated group names for text
      'DEVICE_GROUP_IDS'    => implode(', ', array_keys($device_groups)), // Comma-separated group IDs
    ];

    // If this is a probe entity, include the probe's runtime message in tags
    // and append it to ALERT_MESSAGE for cross-transport visibility without
    // per-transport modifications.
    if ($entry['entity_type'] === 'probe') {
        // Prefer the current probe message from the entity record; fall back to
        // any value captured in alert state metrics if present.
        $probe_msg = '';
        if (!empty($entity['probe_msg'])) {
            $probe_msg = $entity['probe_msg'];
        } elseif (isset($state['metrics']) && is_array($state['metrics']) && isset($state['metrics']['message'])) {
            $probe_msg = $state['metrics']['message'];
        }

        if ($probe_msg !== '') {
            // Use the message_tags version for clarity
            $message_tags['PROBE_MESSAGE'] = $probe_msg;
            // Append to the configured alert message with a clear separator
            $message_tags['ALERT_MESSAGE'] = rtrim($message_tags['ALERT_MESSAGE'] . ' — ' . $probe_msg);
        }
    }

    $message_tags['TITLE'] = alert_generate_subject($device, $alert_status['status_name_custom'], $message_tags);

    return $message_tags;
}

/**
 * Returns array of alert status params:
 *   - status        (0 - ALERT, 1 - RECOVERY, 2 - DELAYED, 3 - SUPPRESSED, 9 - SYSLOG)
 *   - status_custom (as defined in $config['alerts']['status'] array)
 *   - status_name   (ALERT, RECOVERY, DELAYED, SUPPRESSED)
 *   - status_name_custom (as defined in $config['alerts']['status_name'] array)
 *   - status_emoji
 *   - status_color
 * @param array $entry
 * @param string $severity
 *
 * @return array
 */
function alert_status_array($entry, $severity = 'crit') {

    $cfg = $GLOBALS['config']['alerts'] ?? [];

    // print_debug("ALERT_STATUS DEBUG\n");
    // print_debug_vars($entry);

    $array = [
        'status'        => $entry['alert_status'],
        'status_custom' => $cfg['status'][$entry['alert_status']] ?? $entry['alert_status'] // Custom alert statuses
    ];

    if (isset($cfg['status_name'][$entry['alert_status']]) &&
        is_alpha($cfg['status_name'][$entry['alert_status']])) {
        // Ability for set custom alert status name (override default)
        $array['status_name_custom'] = strtoupper($cfg['status_name'][$entry['alert_status']]);
    }

    if ($entry['alert_status'] == '1') {
        // RECOVER
        $array['status_name'] = 'RECOVER';
        if (empty($array['status_name_custom'])) {
            $array['status_name_custom'] = $array['status_name'];
        }
        $array['status_emoji'] = 'white_check_mark';
        $array['status_color'] = '';

        //print_debug_vars($array);
        return $array;
    }

    $array['status_name'] = 'ALERT';

    if ($entry['has_alerted']) {
        // ALERT REMINDER by $config['alerts']['interval']
        if (empty($array['status_name_custom'])) {
            $array['status_name_custom'] = $array['status_name'];
        }
        $array['status_name'] .= ' REMINDER';
        $array['status_name_custom'] .= ' REMINDER';
        $array['status_emoji'] = 'repeat';
        $array['status_color'] = '';

        //print_debug_vars($array);
        return $array;
    }

    // ALERT (first time)
    if (empty($array['status_name_custom'])) {
        $array['status_name_custom'] = $array['status_name'];
    }
    $array['status_emoji'] = $cfg['severity'][$severity]['emoji'];
    $array['status_color'] = ltrim($cfg['severity'][$severity]['color'], '#');

    //print_debug_vars($array);
    return $array;
}

function alert_generate_graphs($entry, $entity) {

    // FIXME. Who this function depends on email config??
    if (isset($GLOBALS['config']['email']['graphs']) && !$GLOBALS['config']['email']['graphs']) {
        return [];
    }

    // entity definitions
    $def        = $GLOBALS['config']['entities'][$entry['entity_type']] ?? [];

    $graphs     = [];
    $graph_done = [];
    foreach ($entry['metrics'] as $metric => $value) {
        if (is_array($def['metric_graphs'][$metric]) &&
            !in_array($def['metric_graphs'][$metric]['type'], $graph_done)) {

            $graph_array = $def['metric_graphs'][$metric];
            foreach ($graph_array as $key => $val) {
                // Check to see if we need to do any substitution
                if (str_starts($val, '@')) {
                    $nval = substr($val, 1);
                    //echo(" replaced " . $val . " with " . $entity[$nval] . " from entity. " . PHP_EOL . "<br />");
                    $graph_array[$key] = $entity[$nval];
                }
            }

            $image_data_uri = generate_alert_graph($graph_array);
            $image_url      = generate_graph_url($graph_array);

            $graphs[] = [ 'label' => $graph_array['type'], 'type' => $graph_array['type'], 'url' => $image_url, 'data' => $image_data_uri ];

            $graph_done[] = $graph_array['type'];
        }

        unset($graph_array);
    }

    if (empty($graph_done) && is_array($def['graph'])) {
        // We can draw a graph for this type/metric pair!

        $graph_array = $def['graph'];
        foreach ($graph_array as $key => $val) {
            // Check to see if we need to do any substitution
            if (str_starts($val, '@')) {
                $nval = substr($val, 1);
                //echo(" replaced ".$val." with ". $entity[$nval] ." from entity. ".PHP_EOL."<br />");
                $graph_array[$key] = $entity[$nval];
            }
        }

        //print_vars($graph_array);

        $image_data_uri = generate_alert_graph($graph_array);
        $image_url      = generate_graph_url($graph_array);

        $graphs[] = [ 'label' => $graph_array['type'], 'type' => $graph_array['type'], 'url' => $image_url, 'data' => $image_data_uri ];

        unset($graph_array);
    }

    //print_vars($graphs);
    return $graphs;
}

/**
 * Get contacts associated with selected notification type and alert ID
 * Currently know notification types: alert, syslog
 *
 * @param array  $device            Common device array
 * @param int    $alert_id          Alert ID
 * @param string $notification_type Used type for notifications
 *
 * @return array Array with transport -> endpoints lists
 */
function get_alert_contacts($device, $alert_id, $notification_type) {

    if (!is_array($device)) {
        $device = device_by_id_cache($device);
    }

    $contacts = [];

    if ($device['ignore']) {
        print_error("Device '{$device['hostname']}' set ignored in Device -> Edit -> Settings.");
        return $contacts;
    }
    if ($GLOBALS['config']['alerts']['disable']['all']) {
        print_error("Alert notifications disabled by \$config['alerts']['disable']['all'].");
        return $contacts;
    }
    if (get_dev_attrib($device, 'disable_notify')) {
        print_error("Alert notifications disabled for device '{$device['hostname']}' in Device -> Edit -> Alerts.");
        return $contacts;
    }

    $cfg = $GLOBALS['config']['email'];

    // figure out which transport methods apply to an alert
    $sql = "SELECT * FROM `alert_contacts`";
    $sql .= " WHERE `contact_disabled` = 0 AND `contact_id` IN";
    $sql .= " (SELECT `contact_id` FROM `alert_contacts_assoc` WHERE `aca_type` = ? AND `alert_checker_id` = ?);";

    $syscontact_exist = $cfg['default_syscontact'];
    $syscontact_id    = 0;
    foreach (dbFetchRows($sql, [$notification_type, $alert_id]) as $contact) {
        if ($contact['contact_method'] === 'syscontact') {
            $syscontact_exist = !$contact['contact_disabled'];
            $syscontact_id    = $contact['contact_id'];
            continue;
        }
        $contacts[] = $contact;
    }

    // append syscontact as email transport
    if ($syscontact_exist) {
        // default device contact
        if (get_dev_attrib($device, 'override_sysContact_bool')) {
            $email = get_dev_attrib($device, 'override_sysContact_string');
        } elseif (parse_email($device['sysContact'])) {
            $email = $device['sysContact'];
        } else {
            $email = $cfg['default'];
        }

        foreach (parse_email($email) as $email => $descr) {
            $contacts[] = ['contact_endpoint' => '{"email":"' . $email . '"}', 'contact_id' => $syscontact_id, 'contact_descr' => $descr, 'contact_method' => 'email'];
            print_debug("Added contact by device sysContact ($email, $descr).");
        }

    }

    if (empty($contacts) && $cfg['default_only'] &&
        !safe_empty($cfg['default'])) {
        // if alert_contacts table is not in use, fall back to default
        // hardcoded defaults for when there is no contact configured.

        foreach (parse_email($cfg['default']) as $email => $descr) {
            $contacts[] = ['contact_endpoint' => '{"email":"' . $email . '"}', 'contact_id' => '0', 'contact_descr' => $descr, 'contact_method' => 'email'];
            print_debug("Added contact by default email config ($email, $descr).");
        }
    }

    return $contacts;
}

function process_notifications($vars = []) {
    global $definitions;

    $result = [];
    $where  = [];

    $sql = 'SELECT * FROM `notifications_queue` ';

    foreach ($vars as $var => $value) {
        switch ($var) {
            case 'device_id':
            case 'notification_id':
                if (safe_empty($value)) {
                    print_debug("DEBUG: process_notifications() - Passed empty variable $var, exit.");
                    return FALSE;
                }
                $where[] = generate_query_values($value, $var);
                break;
            case 'aca_type':
                $where[] = generate_query_values($value, $var);
                break;
        }
    }

    if (empty($where)) {
        print_debug("DEBUG: process_notifications() - Passed empty $vars, process all notifications.");
    }

    foreach (dbFetchRows($sql . generate_where_clause($where)) as $notification) {

        // Recheck if the current notification is locked
        $locked = dbFetchCell('SELECT `notification_locked` FROM `notifications_queue` WHERE `notification_id` = ?', [$notification['notification_id']]); //ALTER TABLE `notifications_queue` ADD `notification_locked` BOOLEAN NOT NULL DEFAULT FALSE AFTER `notification_entry`;
        //if ($locked || $locked === NULL || $locked === FALSE) // If notification not exist or column 'notification_locked' not exist this query return NULL or (possible?) FALSE
        if ($locked || $locked === FALSE) {
            // Notification already processed by other alerter or has already been sent
            print_debug('Notification ID (' . $notification['notification_id'] . ') locked or not exist anymore in table. Skipped.');
            print_debug_vars($notification, 1);
            continue;
        }
        print_debug_vars($notification);

        // Lock current notification
        dbUpdate([ 'notification_locked' => 1 ], 'notifications_queue', '`notification_id` = ?', [$notification['notification_id']]);

        $notification_count = 0;
        $endpoint           = safe_json_decode($notification['endpoints']);

        // If this notification is older than lifetime, unset the endpoints so that it is removed.
        if ((time() - $notification['notification_added']) > $notification['notification_lifetime']) {
            $endpoint = [];
            print_debug('Notification ID (' . $notification['notification_id'] . ') expired.');
            print_debug_vars($notification, 1);
        } else {
            $notification_age      = time() - $notification['notification_added'];
            $notification_timeleft = $notification['notification_lifetime'] - $notification_age;
        }

        $message_tags   = safe_json_decode($notification['message_tags']);
        $message_graphs = safe_json_decode($notification['message_graphs']);
        if (safe_count($message_graphs)) {
            $message_tags['ENTITY_GRAPHS_ARRAY'] = $message_graphs;
            $message_tags['ENTITY_GRAPH_URL']    = $message_graphs[0]['url'];
            $message_tags['ENTITY_GRAPH_BASE64'] = substr($message_graphs[0]['data'], 22); // cut: data:image/png;base64,
            //print_vars($message_tags['ENTITY_GRAPH_URL']);
            //print_vars($message_tags['ENTITY_GRAPH_BASE64']);
        }
        // Fix empty DURATION tag
        if (isset($message_tags['ALERT_UNIXTIME']) && empty($message_tags['DURATION'])) {
            $message_tags['DURATION'] = format_uptime(time() - $message_tags['ALERT_UNIXTIME']) . ' (' . $message_tags['ALERT_TIMESTAMP'] . ')';
        }
        // Append common message URLs
        $message_tags['BASE_URL'] = $GLOBALS['config']['web_url'];
        $message_tags['ICON_URL'] = $GLOBALS['config']['web_url'] . escape_html($GLOBALS['config']['favicon']);

        if (isset($GLOBALS['config']['alerts']['disable'][$endpoint['contact_method']]) &&
            $GLOBALS['config']['alerts']['disable'][$endpoint['contact_method']]) {
            $result[$endpoint['contact_method']] = 'disabled';
            unset($endpoint);
            continue;
        } // Skip if method disabled globally

        $transport = $endpoint['contact_method'];

        // Check if transport exists (check both new definitions and config)
        $is_transport_def  = isset($definitions['transports'][$transport]['notification']);
        $is_transport_func = isset($definitions['transports'][$transport]['send_function']);
        if ($is_transport_def || $is_transport_func) {

            print_cli_data_field("Notifying");
            echo("[" . $endpoint['contact_method'] . "] " . $endpoint['contact_descr'] . ": " . $endpoint['contact_endpoint']);

            // Split out endpoint data as stored JSON in the database into array for use in transport
            // The original string also remains available as the contact_endpoint key
            foreach (safe_json_decode($endpoint['contact_endpoint']) as $field => $value) {
                $endpoint[$field] = $value;
            }

            // Build standardised context for new notification system
            $context = build_alert_context($notification, $endpoint, $message_tags, $message_graphs);

            // Use new unified notification system
            $notify_status = notify_send($transport, $context);

            // Check success and handle results
            if ($notify_status['success']) {
                $result[$transport] = 'ok';
                unset($endpoint);
                $notification_count++;
                print_message(" [%gOK%n]", 'color');
            } else {
                $result[$transport] = 'false';
                print_message(" [%rFALSE%n]", 'color');
                if (isset($notify_status['error']) && $notify_status['error']) {
                    print_cli_data_field('', 4);
                    print_message("[%y" . $notify_status['error'] . "%n]", 'color');
                }
            }
        } else {
            $result[$transport] = 'missing';
            unset($endpoint); // Remove it because it's dumb and doesn't exist. Don't retry it if it doesn't exist.
            print_cli_data("Missing transport definition", $transport);
        }

        // Remove notification from queue,
        // currently in any case, lifetime, added time and result status is ignored!
        switch ($notification['aca_type']) {
            case 'alert':
                if ($notification_count) {
                    dbUpdate(['notified' => 1], 'alert_log', '`event_id` = ?', [$notification['log_id']]);
                }
                break;

            case 'syslog':
                if ($notification_count) {
                    dbUpdate(['notified' => 1], 'syslog_alerts', '`lal_id` = ?', [$notification['log_id']]);
                }
                break;

            case 'web':
                // Currently not used
                break;
        }

        if (empty($endpoint)) {
            dbDelete('notifications_queue', '`notification_id` = ?', [ $notification['notification_id'] ]);
        } else {
            // Set the endpoints to the remaining un-notified endpoints and unlock the queue entry.
            dbUpdate(['notification_locked' => 0, 'endpoints' => safe_json_encode($endpoint)], 'notifications_queue', '`notification_id` = ?', [$notification['notification_id']]);
        }
    }

    return $result;
}

// Use this function to write to the alert_log table
// Fix me - quite basic.
// DOCME needs phpdoc block
// TESTME needs unit testing
function log_alert($text, $device, $alert, $log_type)
{
    $insert = [
      'alert_test_id' => $alert['alert_test_id'],
      'device_id'     => $device['device_id'],
      'entity_type'   => $alert['entity_type'],
      'entity_id'     => $alert['entity_id'],
      'timestamp'     => ["NOW()"],
      //'status'        => $alert['alert_status'],
      'log_type'      => $log_type,
      'message'       => $text
    ];
    if ($alert['state'] && !str_ends($log_type, 'NOTIFY') && get_db_version() >= 479) {
        $insert['log_state'] = $alert['state'];
    }

    return dbInsert($insert, 'alert_log');
}

/**
 * Generate alert transport tags, used for transform any other parts of notification definition.
 *
 * @param string $transport    Alert transport key (see transports definitions)
 * @param array  $tags         (optional) Contact array and other tags
 * @param array  $params       (optional) Array of requested params with key => value entries (used with request method POST)
 * @param array  $message      (optional) Array with some variants of alert message (ie text, html) and title
 * @param array  $message_tags (optional) Array with all message tags
 *
 * @return array               HTTP Context which can used in get_http_request()
 * @global array $config
 */
function generate_transport_tags($transport, $tags = [], $params = [], $message = [], $message_tags = []) {
    global $config;

    if (!isset($message['message'])) {
        // Just use text version of message (also possible in future html, etc
        $message['message'] = $message['text'];
    }
    $tags = array_merge($tags, $params, $message, $message_tags);

    // If transport config options exist, merge it with tags array
    // for use in replace/etc, ie: smsbox
    if (isset($config[$transport])) {
        foreach ($config[$transport] as $param => $value) {
            if (!isset($tags[$param]) || $tags[$param] === '') {
                $tags[$param] = $value;
            }
        }
    }

    // Set defaults and transform params if required
    $def_params = [];

    // Get transport parameters from definitions or config
    global $definitions;
    $transport_params = $definitions['transports'][$transport]['parameters'] ?? [];

    // Merge required/global and optional parameters
    foreach (array_keys($transport_params) as $tmp) {
        $def_params[] = $transport_params[$tmp];
    }
    foreach (array_merge([], ...array_values($transport_params)) as $param => $entry) {
        // Set default if tag empty
        if (isset($entry['default']) && safe_empty($tags[$param])) {
            $tags[$param] = $entry['default'];
        }
        // Transform param if defined
        if (isset($entry['transform'], $tags[$param])) {
            $tags[$param] = string_transform($tags[$param], $entry['transform']);
        }
    }
    //print_vars($tags);

    return $tags;
}

function valid_json_notification($value) {
    //r($value);
    safe_json_decode($value);
    $valid = json_last_error() === JSON_ERROR_NONE;

    if (!$valid) {
        // Load test message_tags for correct JSON validate with real data
        // https://jira.observium.org/browse/OBS-4626
        //bdump($value);
        $notification = safe_json_decode(file_get_contents($GLOBALS['config']['install_dir'] . '/includes/templates/test/notification_ALERT.json'));
        $message_tags = safe_json_decode($notification['message_tags']);

        $notification = safe_json_decode(file_get_contents($GLOBALS['config']['install_dir'] . '/includes/templates/test/notification_SYSLOG.json'));
        $message_tags = array_merge(safe_json_decode($notification['message_tags']), $message_tags);

        // Decode again with real data
        //bdump(array_tag_replace($message_tags, $value));
        safe_json_decode(array_tag_replace($message_tags, $value));

        $valid = json_last_error() === JSON_ERROR_NONE;
    }

    return $valid;
}

/**
 * Central dispatcher for sending notifications.
 *
 * This function reads the transport definition and calls the appropriate
 * send function or falls back to the legacy generic handler.
 *
 * @param string $transport The transport type (e.g., 'email', 'slack')
 * @param array  $context   A standardised array with all notification data
 *
 * @return array Array with boolean status and error message when false.
 */
function notify_send($transport, $context) {
    global $config, $definitions;

    // Get transport definition from $definitions
    $definition = $definitions['transports'][$transport] ?? NULL;
    if (!$definition) {
        print_debug("Transport '$transport' not found in definitions or config");
        return ['success' => FALSE, 'error' => "Transport '$transport' not defined"];
    }

    // Initialize return status
    $notify_status = [ 'success' => FALSE, 'error' => '' ];

    // 1. Check for a dedicated 'send_function'
    if (isset($definition['send_function']) && function_exists($definition['send_function'])) {
        print_debug("Dispatching notification to dedicated send function: {$definition['send_function']}");
        $notify_status['success'] = $definition['send_function']($context);
        return $notify_status;
    }

    // 2. Enhanced definition-based generic HTTP handler with preprocessing
    if (isset($definition['notification'])) {
        $notification_def = $definition['notification'];
        // Check if transport uses enhanced preprocessing
        if (isset($definition['preprocessing'])) {
            print_debug("Dispatching notification to enhanced definition-based handler with preprocessing.");

            // Apply preprocessing rules
            $processed_context = apply_transport_preprocessing($transport, $definition, $context);

            // Use standard HTTP processing with preprocessed data
            $message_tags = $processed_context['message_tags'];

            // Check if this is a recovery notification and use alternate config if available
            $is_recovery = ($message_tags['ALERT_STATE'] === 'RECOVER' || $message_tags['ALERT_STATE'] === 'OK');
            if ($is_recovery && isset($definition['notification_recovery'])) {
                print_debug("Using notification_recovery config for RECOVER/OK state");
                $notification_def = $definition['notification_recovery'];
            }

            // Prepare data array
            $data = [];

            // Pass message tags to request as ARRAY if requested (e.g., opsgenie)
            if (isset($notification_def['message_tags']) && $notification_def['message_tags']) {
                $data = array_merge($data, $message_tags);
                // Remove graph-related fields that are too large for JSON requests
                unset($data['ENTITY_GRAPHS_ARRAY'], $data['ENTITY_GRAPH_BASE64'], $data['ENTITY_GRAPH_URL']);
            }

            // Generate transport tags, used for rewrites in definition
            $tags = generate_transport_tags($transport, $processed_context['endpoint'], $data, [], $message_tags);

            // Generate context/options with encoded data and transport specific api headers
            $options = generate_http_context($notification_def, $tags, $data);
            // Always get response also with bad status
            $options['ignore_errors'] = TRUE;

            // Send request
            $url = generate_http_url($notification_def, $tags, $data);
            $success = process_http_request($notification_def, $url, $options);
            if (!$success) {
                $notify_status['error'] = get_last_message();

                // Second request (fallback when defined)
                if (isset($notification_def['url_fallback'])) {
                    $url = generate_http_url($notification_def, $tags, $data, 'url_fallback');
                    if ($success = process_http_request($notification_def, $url, $options)) {
                        // reset error message
                        $notify_status['error'] = '';
                    } else {
                        // Append error from fallback
                        $notify_status['error'] .= '; ' . get_last_message();
                    }
                }
            }
            $notify_status['success'] = $success;
            return $notify_status;
        }

        print_debug("Dispatching notification to simple definition-based handler.");
        // This logic fully replicates the original process_notifications() logic

        $endpoint = $context['endpoint'];
        $message_tags = $context['message_tags'];

        // Clean data array for use with definition based processing
        $data = [];
        $message = [];

        // Pass message tags to request as ARRAY (example in opsgenie)
        if (isset($notification_def['message_tags']) && $notification_def['message_tags']) {
            $data = array_merge($data, $message_tags);
            // Remove graph-related fields that are too large for JSON requests
            unset($data['ENTITY_GRAPHS_ARRAY'], $data['ENTITY_GRAPH_BASE64'], $data['ENTITY_GRAPH_URL']);
        } elseif (isset($notification_def['message_json'])) {
            // Pass raw JSON as $data (example in webhook-json)
            $json_tags = generate_transport_tags($transport, $endpoint, [], [], $message_tags);
            // Remove graph-related fields that are too large for JSON requests
            unset($json_tags['ENTITY_GRAPHS_ARRAY'], $json_tags['ENTITY_GRAPH_BASE64'], $json_tags['ENTITY_GRAPH_URL']);

            // escape tags for json
            print_debug("Transport TAGs escaped for JSON.");

            // Escape all tags except 'json' and graphs keys (contact_endpoint is not used in json)
            array_json_escape($json_tags, [ 'json', 'contact_endpoint' ]);

            $json = array_tag_replace($json_tags, $notification_def['message_json']);

            $json_array = safe_json_decode($json);
            if (!safe_empty($json_array)) {
                $data = array_merge($data, $json_array);
            }
            unset($json_tags, $json, $json_array);
        } else {
            // Or set common title tag
            $message['title'] = $message_tags['TITLE'];
        }

        // Generate a notification message from tags using templates
        if (isset($endpoint['contact_message_custom']) && $endpoint['contact_message_custom'] &&
            !empty($endpoint['contact_message_template'])) {
            // Use user defined template
            print_debug("User-defined message template is used.");
            $message['text'] = simple_template($endpoint['contact_message_template'], $message_tags);
        } elseif (isset($notification_def['message_template'])) {
            print_debug("Definition message template file is used.");
            // template can have tags (ie telegram)
            if (strpos($notification_def['message_template'], '%') !== false) {
                $message_template = array_tag_replace_encode(generate_transport_tags($transport, $endpoint), $notification_def['message_template']);
                $message_template = strtolower($message_template);
            } else {
                $message_template = $notification_def['message_template'];
            }
            // Template in file, see: includes/templates/notification/
            $message['text'] = simple_template($message_template, $message_tags, ['is_file' => TRUE]);
        } elseif (isset($notification_def['message_text'])) {
            print_debug("Definition message template is used.");
            // Template in definition
            $message['text'] = simple_template($notification_def['message_text'], $message_tags);
        }

        // After all, message transform
        if (isset($notification_def['message_transform']) && isset($message['text'])) {
            $message['text'] = string_transform($message['text'], $notification_def['message_transform']);
        }

        // Generate transport tags, used for rewrites in definition
        $tags = generate_transport_tags($transport, $endpoint, $data, $message, $message_tags);

        // Generate context/options with encoded data and transport specific api headers
        $options = generate_http_context($transport, $tags, $data);
        // Always get response also with bad status
        $options['ignore_errors'] = TRUE;

        // Send request
        $url = generate_http_url($transport, $tags, $data);
        $success = process_http_request($transport, $url, $options);
        if (!$success) {
            $notify_status['error'] = get_last_message();

            // Second request (fallback when defined)
            if (isset($notification_def['url_fallback'])) {
                $url = generate_http_url($transport, $tags, $data, 'url_fallback');
                if ($success = process_http_request($transport, $url, $options)) {
                    // reset error message
                    $notify_status['error'] = '';
                } else {
                    // Append error from fallback
                    $notify_status['error'] .= '; ' . get_last_message();
                }
            }
        }
        $notify_status['success'] = $success;

        // Clean after transport data and request generation
        unset($message, $url, $data, $options, $tags);

        return $notify_status;
    }

    /* 4. Fallback to legacy file-based transport
    $method_include = $config['install_dir'] . '/includes/alerting/' . $transport . '.inc.php';
    if (is_file($method_include)) {
        print_debug("Dispatching notification to legacy file-based transport: $method_include");
        // The legacy files expect global variables, so we need to set them up.
        // This is not ideal, but necessary for backward compatibility.
        $GLOBALS['endpoint'] = $context['endpoint'];
        $GLOBALS['message_tags'] = $context['message_tags'];
        $GLOBALS['message'] = $context['message'];
        $GLOBALS['device'] = $context['device'];
        $GLOBALS['notify_status'] = ['success' => FALSE];

        include($method_include);

        // Check if the legacy file set a success status
        $notify_status['success'] = $GLOBALS['notify_status']['success'] ?? TRUE;
        $notify_status['error']   = $GLOBALS['notify_status']['error'] ?? '';
        return $notify_status;
    }
    */

    print_warning("Could not find a valid send method for transport '$transport'.");
    $notify_status['error'] = "No valid send method found for transport '$transport'";
    return $notify_status;
}

/**
 * Build standardised context from alert notification data
 *
 * This function converts alert notification queue data into the standardised
 * context format used by the new notification system.
 *
 * @param array $notification Notification from notifications_queue table
 * @param array $endpoint Contact endpoint data
 * @param array $message_tags Processed message tags
 * @param array $message_graphs Graph data
 * @return array Standardized context for notify_send()
 */
function build_alert_context($notification, $endpoint, $message_tags, $message_graphs) {

    // Build context that matches what transport functions expect
    return [
        'device'       => NULL, // Will be populated if needed
        'endpoint'     => $endpoint,
        'message_tags' => $message_tags,
        'message'      => [], // Legacy format compatibility
        'graphs'       => $message_graphs,
        'notification' => $notification, // Full notification data for advanced processing
    ];
}

/**
 * New notification rule entry point for standardised context
 *
 * This function is called by the refactored notify_rule() and serves as
 * the entry point for the new standardised notification system.
 *
 * @param array $rule The notification rule array
 * @param array $context Standardized notification context
 * @return array Array with boolean status and error message when false.
 */
function notify_rule_new($rule, $context) {
    global $definitions;

    $transport = $context['endpoint']['contact_method'];

    // Get transport name from new definitions or fallback to config
    $transport_name = $definitions['transports'][$transport]['name'] ?? $transport;

    print_cli_data("Notify via", "[" . $transport_name . "] " . $context['endpoint']['contact_descr']);

    // Split out endpoint data as stored JSON in the database into array for use in transport
    $context['endpoint'] = array_merge($context['endpoint'], (array)safe_json_decode($context['endpoint']['contact_endpoint']));

    // Use the new unified dispatcher
    $notify_status = notify_send($transport, $context);

    if ($notify_status['success']) {
        print_cli("OK\n", 'color');
        return ['success' => TRUE, $transport => 'ok'];
    }

    print_cli("FAIL\n", 'color');
    if (isset($notify_status['error']) && $notify_status['error']) {
        print_cli("Error: " . $notify_status['error'] . "\n", 'color');
    }
    return ['success' => FALSE, $transport => 'false'];
}

/**
 * Apply preprocessing rules to transport context
 * 
 * @param string $transport Transport name
 * @param array $definition Transport definition
 * @param array $context Original context
 * @return array Processed context
 */
function apply_transport_preprocessing($transport, $definition, $context) {
    $processed_context = $context; // Start with original context
    $message_tags = $processed_context['message_tags'];
    $endpoint = $processed_context['endpoint'];

    // Check for custom preprocessing function (e.g., transport_opsgenie_preprocess)
    $preprocess_function = "transport_{$transport}_preprocess";
    if (function_exists($preprocess_function)) {
        print_debug("Calling custom preprocessing function: {$preprocess_function}");
        $processed_context = $preprocess_function($processed_context, $definition);
        $message_tags = $processed_context['message_tags'];
        $endpoint = $processed_context['endpoint'];
    }

    if (!isset($definition['preprocessing'])) {
        return $processed_context;
    }

    $preprocessing = $definition['preprocessing'];

    // Process each preprocessing rule
    foreach ($preprocessing as $rule_type => $rule_config) {
        switch ($rule_type) {
            case 'field_transforms':
                // New unified transforms system using existing string_transform
                foreach ($rule_config as $field_name => $transform_config) {
                    // Get source value
                    $source_field = $transform_config['source'] ?? $field_name;

                    if (isset($message_tags[$source_field])) {
                        $source_value = $message_tags[$source_field];
                    } elseif (isset($endpoint[$source_field])) {
                        $source_value = $endpoint[$source_field];
                    } else {
                        $source_value = '';
                    }

                    // Pass message_tags context for conditional_map transforms
                    if ($transform_config['action'] === 'conditional_map') {
                        $transform_config['message_tags'] = $message_tags;
                    }

                    // Special case: split transform on empty string should return empty array, not ['']
                    if (($transform_config['action'] === 'split' || $transform_config['action'] === 'explode') &&
                        $transform_config['index'] === 'array' && $source_value === '') {
                        $transformed_value = [];
                    } else {
                        // Apply transform using existing string_transform function
                        $transformed_value = string_transform($source_value, $transform_config);
                    }
                    $message_tags[$field_name] = $transformed_value;
                }
                break;

            case 'custom_fields':
                foreach ($rule_config as $field_name => $field_config) {
                    $source_data = [];

                    // Get source data
                    switch ($field_config['source']) {
                        case 'message_tags':
                            $source_data = $message_tags;
                            break;
                        case 'endpoint':
                            $source_data = $endpoint;
                            break;
                    }

                    // Apply exclusions
                    if (isset($field_config['exclude'])) {
                        foreach ($field_config['exclude'] as $exclude_field) {
                            unset($source_data[$exclude_field]);
                        }
                    }

                    // Apply inclusions (if specified, only include these)
                    if (isset($field_config['include'])) {
                        $filtered_data = [];
                        foreach ($field_config['include'] as $include_field) {
                            if (isset($source_data[$include_field])) {
                                $filtered_data[$include_field] = $source_data[$include_field];
                            }
                        }
                        $source_data = $filtered_data;
                    }

                    // Apply transformation
                    $transform = $field_config['transform'] ?? 'array';
                    switch ($transform) {
                        case 'json':
                            $message_tags[$field_name] = safe_json_encode($source_data);
                            break;
                        case 'string':
                            $message_tags[$field_name] = implode(', ', $source_data);
                            break;
                        case 'array':
                        default:
                            $message_tags[$field_name] = $source_data;
                            break;
                    }
                }
                break;
        }
    }

    $processed_context['message_tags'] = $message_tags;
    return $processed_context;
}

// Legacy evaluate_mapping_rules and evaluate_condition functions removed - 
// All functionality moved to enhanced string_transform with conditional_map action

// EOF