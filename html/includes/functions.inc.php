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

// Notifications and alerts in bottom navbar

$notifications = [];
$alerts        = [];

// FIXME. Will replaced soon
// Load caching only for WUI (not used by default)
if (!is_cli()) {
    include_once($config['html_dir'] . '/includes/cache.inc.php');
}

include_once($config['html_dir'] . '/includes/sessions.inc.php');
include_once($config['html_dir'] . '/includes/integrations.inc.php');
include_once($config['html_dir'] . '/includes/graphs/functions.inc.php');
include_once($config['html_dir'] . '/includes/colours.inc.php');

$print_functions = [
  'addresses', 'events', 'mac_addresses', 'rows', 'status', 'arptable', 'fdbtable', 'navbar',
  'search', 'syslogs', 'inventory', 'alert', 'authlog', 'dot1xtable', 'alert_log', 'logalert',
  'common', 'routing', 'neighbours', 'vlan', 'trap_log'
];
if (OBSERVIUM_EDITION !== 'community') {
    $print_functions[] = 'billing';
    $print_functions[] = 'group';
}

foreach ($print_functions as $item) {
    $print_path = $config['html_dir'] . '/includes/print/' . $item . '.inc.php';
    if (is_file($print_path)) {
        include_once($print_path);
    }
}

// Load generic entity include
include($config['html_dir'] . '/includes/entities/generic.inc.php');

// Load all per-entity includes
foreach ($config['entities'] as $entity_type => $item) {
    $path = $config['html_dir'] . '/includes/entities/' . $entity_type . '.inc.php';
    if (is_file($path)) {
        include_once($path);
    }
}

/**
 * Callback function for replacing strings in the HTML buffer at the end of the script execution.
 *
 * @param string $buffer HTML buffer obtained from ob_start()
 *
 * @return string Modified buffer
 */
function html_callback($buffer) {

    // Do not disclose version to unauthorized requests
    $version_param = $_SESSION['authenticated'] ? '?v=' . OBSERVIUM_VERSION : '';

    // Define template strings for registered CSS/JS links and other elements
    $templates = [
        'css'    => '    <link href="%%STRING%%' . $version_param . '" rel="stylesheet" type="text/css" />' . PHP_EOL,
        'style'  => '    <style type="text/css">' . PHP_EOL . '%%STRING%%' . PHP_EOL . '  </style>' . PHP_EOL,
        'js'     => '    <script type="text/javascript" src="%%STRING%%' . $version_param . '"></script>' . PHP_EOL,
        'script' => '  <script type="text/javascript">' . PHP_EOL .
                    '  <!-- Begin' . PHP_EOL . '%%STRING%%' . PHP_EOL .
                    '  // End -->' . PHP_EOL . '  </script>' . PHP_EOL,
        // key-value
        'meta-equiv' => '    <meta http-equiv="%%STRING_name%%" content="%%STRING_content%%" />' . PHP_EOL,
        'meta'       => '    <meta name="%%STRING_name%%" content="%%STRING_content%%" />' . PHP_EOL,
    ];

    // Process and replace resources in the buffer
    $cache_resources = (array) mem_cache_get('html_resources');
    foreach ($templates as $type => $template) {
        $uppercase_type = strtoupper($type);
        if (!isset($cache_resources[$type])) {
            // Clean template string
            $buffer = str_replace('<!-- ##' . $uppercase_type . '_CACHE## -->', '', $buffer);
            continue;
        }

        $resource_string = '<!-- ' . $uppercase_type . ' BEGIN -->' . PHP_EOL;
        if ($type === 'meta-equiv' || $type === 'meta') {
            foreach ($cache_resources[$type] as $name => $content) {

                //bdump($content);
                $resource_string .= str_replace([ '%%STRING_name%%', '%%STRING_content%%' ], [ $name, $content ], $template);
            }
        } else {
            foreach (array_unique($cache_resources[$type]) as $content) {
                $resource_string .= str_replace('%%STRING%%', $content, $template);
            }
        }
        $resource_string .= '    <!-- ' . $uppercase_type . ' END -->' . PHP_EOL;
        $buffer = str_replace('<!-- ##' . $uppercase_type . '_CACHE## -->' . PHP_EOL, $resource_string, $buffer);
    }


    // Replace placeholders in the buffer with actual values (case-sensitive)
    return strtr($buffer, [
        '##TITLE##'      => html_callback_build_title(),
        '##PAGE_PANEL##' => mem_cache_get('html_page_panel'),
        '##UI_ALERTS##'  => implode(PHP_EOL, (array) mem_cache_get('html_alerts')),
    ]);
}

/**
 * Set the title of the page based on various criteria.
 */
function html_callback_build_title() {

    global $config, $vars;

    $cache_title =& mem_cache_key('html_title');

    if (empty($cache_title)) {
        // Title not set by any page, fall back to nicecase'd page name:
        if ($vars['page'] && $_SESSION['authenticated']) {
            $cache_title = (array) nicecase($vars['page']);
        } else {
            // Main page or no page specified, leave the title empty
            $cache_title = [];
        }
    }

    // If a suffix is set, append it to the title
    if ($config['page_title_suffix']) {
        $cache_title[] = $config['page_title_suffix'];
    }

    // If a prefix is set, prepend it to the title
    if ($config['page_title_prefix']) {
        array_unshift($cache_title, $config['page_title_prefix']);
    }

    // Build the title with separators
    return escape_html(implode($config['page_title_separator'], $cache_title));
}

/**
 * Register an HTML title section
 *
 * Registers title section for use in the html <title> tag.
 * Calls can be stacked, and will be concatenated later by the HTML callback function.
 *
 * @param string $title Section title content
 */
function register_html_title($title) {
    $cache_title =& mem_cache_key('html_title');
    $cache_title[] = $title;
}

/**
 * Register an HTML resource
 *
 * Registers resource for use later (will be re-inserted via output buffer handler)
 * CSS and JS files default to the css/ and js/ directories respectively.
 * Scripts are inserted literally as passed in $name.
 *
 * @param string $type    Type of resource (css/js/script)
 * @param string $content Filename or script content or array (for meta)
 */
// TESTME needs unit testing
function register_html_resource($type, $content)
{
    // If no path specified, default to subdirectory of resource type (for CSS and JS only)
    $type = strtolower($type);
    if (in_array($type, [ 'css', 'js' ]) && !str_contains($content, '/')) {
        $content = $type . '/' . $content;
    }

    $cache_resources =& mem_cache_key('html_resources');
    // Insert into cache variable, used in html callback function
    $cache_resources[$type][] = $content;
}

function register_html_meta($name, $content, $tag = 'name') {
    if (safe_empty($content) || !is_alpha($name)) {
        return;
    }

    $cache_resources =& mem_cache_key('html_resources');
    if ($tag !== 'name') {
        // http-equiv is multiplied
        $cache_resources['meta-equiv'][$name] = escape_html($content);
    } else {
        $cache_resources['meta'][$name] = escape_html($content);
    }
}

/**
 * Register an HTML alert block displayed in top of page.
 *
 * @param string $text     Alert message
 * @param string $title    Alert title if passed
 * @param string $severity Severity in list: info, danger, warning, success, recovery, suppressed, delay, disabled
 */
function register_html_alert($text, $title = NULL, $severity = 'info') {
    if (!$GLOBALS['config']['web_show_notifications']) {
        // suppress web ui alerts
        return;
    }

    // FIXME handle severity parameter with colour or icon?
    $ui_alert = '<div width="100%" class="alert alert-' . $severity . '">';
    if (!safe_empty($title)) {
        $ui_alert .= '<h4>' . $title . '</h4>';
    }
    $ui_alert .= $text . '</div>';

    $ui_alerts =& mem_cache_key('html_alerts');
    $ui_alerts[] = $ui_alert;
}

/**
 * Register an HTML panel section
 *
 * Registers left panel section.
 * Calls can be stacked, and will be concatenated later by the HTML callback function.
 *
 * @param string $html Section panel content
 */
// TESTME needs unit testing
function register_html_panel($html = '') {
    if (!mem_cache_exists('html_page_panel') && (empty($html) || $html === 'default')) {
        // register default (ajax) panel

        // Load a default panel after whole page (only when visible)
        // $config['html_dir'] . "/includes/panels/default.inc.php"
        register_html_resource('script', "$(document).ready(function (e) { $('#myAffix:visible[data-panel=default]').load('/ajax/panel.php').attr('data-panel', 'loaded'); });");
        // load when become visible
        register_html_resource('script', "$(window).resize(function (e)  { $('#myAffix:visible[data-panel=default]').load('/ajax/panel.php').attr('data-panel', 'loaded'); });");

        return;
    }

    // Just rename data attrib for panel placeholder from default
    register_html_resource('script', "$(document).ready(function (e) { $('#myAffix[data-panel=default]').attr('data-panel', 'register'); });");

    // Cache requested html panel for current page
    mem_cache_set('html_page_panel', $html);
}

/**
 * @param string|array $patterns
 * @return bool
 */
function http_match_referer($patterns) {
    if ($_SERVER['HTTP_SEC_FETCH_SITE'] !== 'same-origin') {
        return FALSE;
    }

    foreach ((array)$patterns as $pattern) {
        if (preg_match($pattern, $_SERVER['HTTP_REFERER'])) {
            return TRUE;
        }
    }

    return FALSE;
}

/**
 * Parse $_GET, $_POST and REQUEST_URI into $vars array
 *
 * @param array|string $vars_order Request variables order (POST, URI, GET)
 * @param boolean      $auth       this var or ($_SESSION['authenticated']) used for allow to use var_decode()
 *
 * @return array array of vars
 */
function get_vars($vars_order = [], $auth = FALSE) {
    if (is_string($vars_order)) {
        $vars_order = explode(' ', $vars_order);
    } elseif (empty($vars_order) || !is_array($vars_order)) {
        $vars_order = [ 'POST', 'URI', 'GET' ]; // Default order
    }

    // Content-Type=>application/x-www-form-urlencoded
    $content_type = $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'];

    // Allow using var_decode(), this prevents to use potentially unsafe serialize functions
    $auth = $auth || $_SESSION['authenticated'];

    $vars = [];
    foreach ($vars_order as $order) {
        $order = strtoupper($order);
        switch ($order) {
            case 'JSON':
                //r(getallheaders());
                //exit;

                // https://stackoverflow.com/questions/8893574/php-php-input-vs-post
                if (!in_array($content_type, ['application/x-www-form-urlencoded', 'multipart/form-data-encoded'])) {
                    //$json = @json_decode(trim(file_get_contents("php://input")), TRUE, 512, OBS_JSON_DECODE);
                    $json = safe_json_decode(trim(file_get_contents("php://input")));

                    if (is_array_assoc($json)) {
                        //$vars = array_merge_indexed($vars, $json);
                        $vars = $json; // Currently just override $vars, see ajax actions
                        //$vars_got['JSON'] = 1;
                    }
                }
                break;

            case 'POST':
                // Parse POST variables into $vars
                foreach ($_POST as $name => $value) {
                    // Var names sanitize
                    if (!preg_match(OBS_PATTERN_VAR_NAME, $name)) {
                        continue;
                    }

                    if (!isset($vars[$name])) {
                        $vars[$name] = $auth ? var_decode($value) : $value;
                        if (is_string($vars[$name]) && preg_match(OBS_PATTERN_XSS, $vars[$name])) {
                            // Prevent any <script> html tag inside vars, exclude any possible XSS with scripts
                            unset($vars[$name]);
                        }
                        //$vars_got['POST'] = 1;
                    }
                }
                break;

            case 'URI':
            case 'URL':
                // Parse URI into $vars
                $segments   = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
                $compressed = $auth && in_array('compressed=1', $segments, TRUE); // vars can be compressed by str_compress()

                //r($segments);
                //r($_SERVER['REQUEST_URI']);
                $last_var = '';
                foreach ($segments as $pos => $segment) {
                    $name_value = str_contains($segment, '=');
                    if ($pos === 0 && !$name_value) {
                        if (!preg_match(OBS_PATTERN_XSS, $segment)) {
                            // Prevent any <script> html tag inside vars, exclude any possible XSS with scripts
                            $segment = urldecode($segment);
                            $vars['page'] = $segment;
                        }
                        $last_var = 'page';
                        //$vars_got['URI'] = 1;
                    } elseif ($last_var === 'port' && !$name_value) {
                        // Append segment to previous var
                        // Correctly parse /port=GigabitEthernet0/1/0.2571
                        if (!preg_match(OBS_PATTERN_XSS, $segment)) {
                            // Prevent any <script> html tag inside vars, exclude any possible XSS with scripts
                            $segment = urldecode($segment);
                            $vars[$last_var] .= '/' . $segment;
                        }
                    } else {
                        [ $name, $value ] = explode('=', $segment, 2);
                        $last_var = $name;

                        // Var names sanitize
                        if (!preg_match(OBS_PATTERN_VAR_NAME, $name)) {
                            continue;
                        }

                        if (!isset($vars[$name])) {
                            if (!$name_value || $value === '') {
                                $vars[$name] = 'yes';
                            } else {
                                if ($compressed && $value_uncompress = str_decompress($value)) {
                                    $value = $value_uncompress;
                                    unset($value_uncompress);
                                } else {
                                    // rawurldecode() instead of urldecode() to translate %n and not mangle +
                                    // %7F (DEL, delete) - control char, not defined in HTML 4 standard
                                    // %05 (ENQ, enquiry) - control char, not defined in HTML 4 standard
                                    $value = str_replace(['%7F', '%05'], ['/', '%'], rawurldecode($value));
                                }
                                if (preg_match(OBS_PATTERN_XSS, $value)) {
                                    // Prevent any <script> html tag inside vars, exclude any possible XSS with scripts
                                    continue;
                                }

                                // Better to understand quoted vars
                                $vars[$name] = get_var_csv($value, $auth);
                                if (is_string($vars[$name])) {
                                    if (preg_match(OBS_PATTERN_XSS, $vars[$name])) {
                                        // Prevent any <script> html tag inside vars, exclude any possible XSS with scripts
                                        unset($vars[$name]);
                                    } elseif (!isset($vars['quoted']) &&
                                        is_string_quoted($value) && str_contains($vars[$name], ',')) {
                                        // Append quoted var for correctly generate_url()
                                        $vars['quoted'] = 1;
                                    }
                                }
                            }
                            //$vars_got['URI'] = 1;
                        }
                    }
                }
                break;

            case 'GET':
                // Parse GET variable into $vars
                $compressed = $auth && get_var_true($_GET['compressed']); // vars can be compressed by str_compress()
                foreach ($_GET as $name => $value) {
                    // Var names sanitize
                    if (!preg_match(OBS_PATTERN_VAR_NAME, $name)) {
                        continue;
                    }

                    if (!isset($vars[$name])) {
                        if ($compressed && $value_uncompress = str_decompress($value)) {
                            $value = $value_uncompress;
                            unset($value_uncompress);
                        } else {
                            // rawurldecode() instead of urldecode() to translate %n and not mangle +
                            // %7F (DEL, delete) - control char, not defined in HTML 4 standard
                            // %05 (ENQ, enquiry) - control char, not defined in HTML 4 standard
                            $value = str_replace(['%7F', '%05'], ['/', '%'], rawurldecode($value));
                        }
                        if (preg_match(OBS_PATTERN_XSS, $value)) {
                            // Prevent any <script> html tag inside vars, exclude any possible XSS with scripts
                            continue;
                        }

                        // Better to understand quoted vars
                        $vars[$name] = get_var_csv($value, $auth);
                        if (is_string($vars[$name])) {
                            if (preg_match(OBS_PATTERN_XSS, $vars[$name])) {
                                // Prevent any <script> html tag inside vars, exclude any possible XSS with scripts
                                unset($vars[$name]);
                            } elseif (!isset($vars['quoted']) &&
                                      is_string_quoted($value) && str_contains($vars[$name], ',')) {
                                // Append quoted var for correctly generate_url()
                                $vars['quoted'] = 1;
                            }
                        }
                        //$vars_got['GET'] = 1;
                    }
                }
                break;
        }
    }
    //print_success("Got [".implode(', ', array_keys($vars_got))."] vars ($content_type).");

    // Always convert location to array
    if (isset($vars['location'])) {
        if ($vars['location'] === '') {
            // Unset location if is empty string
            unset($vars['location']);
        } elseif (is_array($vars['location'])) {
            // Additionally, decode locations if array entries encoded
            foreach ($vars['location'] as $k => $location) {
                $vars['location'][$k] = $auth ? var_decode($location) : $location;
            }
        } else {
            // All other location strings covert to array
            $vars['location'] = [$vars['location']];
        }
    }

    return ($vars);
}

function form_action(&$vars) {
    global $config;

    if (!isset($vars['action']) || is_ajax() || !is_alpha($vars['action'])) {
        //bdump($vars);
        return FALSE;
    }
    if ($vars['page'] === 'wmap') {
        // Weathermap
        return FALSE;
    }
    if ($_SESSION['userlevel'] < 7) {
        print_error_permission('Action not allowed.');
        return FALSE;
    }
    if (!request_token_valid($vars)) {
        //r($vars);
        return FALSE;
    }

    if ($config['devel']) {
        bdump($vars);
    }

    $limitwrite  = $_SESSION['userlevel'] >= 8;
    $securewrite = $_SESSION['userlevel'] >= 9;
    $readwrite   = $_SESSION['userlevel'] >= 10;

    $vars['action'] = strtolower($vars['action']); // force action to lowercase

    if (is_file($config['html_dir'] . "/includes/actions/" . $vars['action'] . ".inc.php")) {
        return include($config['html_dir'] . "/includes/actions/" . $vars['action'] . ".inc.php");
    }

    $target = explode('_', $vars['action'], 2)[0];
    if ($target !== $vars['action'] &&
        is_file($config['html_dir'] . "/includes/actions/" . $target . ".inc.php")) {
        return include($config['html_dir'] . "/includes/actions/" . $target . ".inc.php");
    }

    // Unknown action
    return FALSE;
}

/**
 * Validate requests by compare session and request tokens.
 * This prevents a CSRF attacks
 *
 * @param string|array $token Passed from request token or array with 'requesttoken' param inside.
 * @param string       $json  Store status of token validation as json for ajax requests
 *
 * @return boolean TRUE if session requesttoken same as passed from request
 */
function request_token_valid($token = NULL, &$json = '') {
    if (is_array($token)) {
        // If $vars array passed, fetch our default 'requesttoken' param
        $token = $token['requesttoken'];
    }

    // Set output silent if json status requested
    $silent = func_num_args() >= 2;

    //print_vars($_SESSION['requesttoken']);
    //print_vars($token);

    // See: https://stackoverflow.com/questions/6287903/how-to-properly-add-csrf-token-using-php
    // Session token generated after valid user auth in html/includes/authenticate.inc.php
    if (empty($_SESSION['requesttoken'])) {
        // User didn't authenticate
        //print_warning("Request passed by unauthorized user.");
        if ($silent) {
            $json = safe_json_encode(['status' => 'failed', 'message' => 'Request passed by unauthorized user.', 'class' => 'danger']);
        }

        return FALSE;
    }

    if (empty($token)) {
        // Token not passed, WARNING seems as CSRF attack
        if (!$silent) {
            print_error("WARNING. Possible CSRF attack with EMPTY request token.");
        } else {
            $json = safe_json_encode(['status' => 'failed', 'message' => 'WARNING. Possible CSRF attack with EMPTY request token.', 'class' => 'danger']);
        }

        return FALSE;
    }

    if (hash_equals($_SESSION['requesttoken'], $token)) {
        // Correct session and request tokens, all good
        if ($silent) {
            $json = safe_json_encode(['status' => 'ok']);
        }
        return TRUE;
    }

    // Passed incorrect request token,
    // WARNING seems as CSRF attack
    if (!$silent) {
        print_error("WARNING. Possible CSRF attack with INCORRECT request token.");
    } else {
        $json = safe_json_encode([ 'status' => 'failed', 'message' => 'WARNING. Possible CSRF attack with INCORRECT request token.', 'class' => 'danger',
                                  /* 'session_t' => $_SESSION['requesttoken'], 'request_t' => $token */]);
    }
    ///FIXME. need an user actions log
    return FALSE;
}

// TESTME needs unit testing
/**
 * Generates base64 data uri with alert graph
 *
 * @return string
 */
function generate_alert_graph($graph_array) {
    global $config, $vars;

    $vars                   = $graph_array;
    $auth                   = is_cli() ? TRUE : $GLOBALS['auth']; // Always set $auth to true for cli
    $vars['image_data_uri'] = TRUE;
    $vars['height']         = '150';
    $vars['width']          = '400';
    $vars['legend']         = 'no';
    $vars['from']           = get_time('twoday');
    $vars['to']             = get_time();

    include($config['html_dir'] . '/includes/graphs/graph.inc.php');

    return $image_data_uri;
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function datetime_preset($preset)
{
    $begin_fmt = 'Y-m-d 00:00:00';
    $end_fmt   = 'Y-m-d 23:59:59';

    switch ($preset) {
        case 'sixhours':
            $from = date('Y-m-d H:i:00', strtotime('-6 hours'));
            $to   = date('Y-m-d H:i:59');
            break;
        case 'today':
            $from = date($begin_fmt);
            $to   = date($end_fmt);
            break;
        case 'yesterday':
            $from = date($begin_fmt, strtotime('-1 day'));
            $to   = date($end_fmt, strtotime('-1 day'));
            break;
        case 'tweek':
            $from = (date('l') === 'Monday') ? date($begin_fmt) : date($begin_fmt, strtotime('last Monday'));
            $to   = (date('l') === 'Sunday') ? date($end_fmt) : date($end_fmt, strtotime('next Sunday'));
            break;
        case 'lweek':
            $from = date($begin_fmt, strtotime('-6 days'));
            $to   = date($end_fmt);
            break;
        case 'tmonth':
            $from = date('Y-m-01 00:00:00');
            $to   = date('Y-m-t 23:59:59');
            break;
        case 'lmonth':
            $timestamp = strtotime('previous month');
            $from      = date('Y-m-01 00:00:00', $timestamp);
            $to        = date('Y-m-t 23:59:59', $timestamp);
            break;
        case 'tquarter':
        case 'lquarter':
            $quarter = ceil(date('m') / 3); // Current quarter
            if ($preset === 'lquarter') {
                $quarter -= 1; // Previous quarter
            }
            $year = date('Y');
            if ($quarter < 1) {
                $year    -= 1;
                $quarter = 4;
            }
            $tmonth = $quarter * 3;
            $fmonth = $tmonth - 2;

            $from = $year . '-' . zeropad($fmonth) . '-01 00:00:00';
            $to   = date('Y-m-t 23:59:59', strtotime($year . '-' . $tmonth . '-01'));
            break;
        case 'tyear':
            $from = date('Y-01-01 00:00:00');
            $to   = date('Y-12-31 23:59:59');
            break;
        case 'lyear':
            $from = date($begin_fmt, strtotime('previous year + 1 day'));
            $to   = date($end_fmt);
            break;
    }

    return ['from' => $from, 'to' => $to];
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function bug()
{
    echo('<div class="alert alert-error">
  <button type="button" class="close" data-dismiss="alert">&times;</button>
  <strong>Bug!</strong> Please report this to the Observium development team.
</div>');
}

/**
 * This function determines detailed info of web browser by User-Agent agent string.
 * If User-Agent not passed, used current from $_SERVER['HTTP_USER_AGENT']
 *
 * @param string $user_agent Custom User-Agent string, by default, the value of HTTP User-Agent header is used
 *
 * @return array Return detected browser info: user_agent, type, icon, platform, browser, version,
 *                                             browser_full - full browser name (ie: Chrome 43.0)
 *                                             svg          - supported or not svg images (TRUE|FALSE),
 *                                             screen_ratio - for HiDPI screens it more that 1,
 *                                             screen_resolution - full resolution of client screen (if exist),
 *                                             screen_size  - initial size of browser window (if exist)
 */
// TESTME! needs unit testing
function detect_browser($user_agent = NULL)
{
    $ua_custom = !is_null($user_agent); // Used custom user agent?

    if (!$ua_custom && isset($GLOBALS['cache']['detect_browser'])) {
        // Return cached info
        return $GLOBALS['cache']['detect_browser'];
    }

    if (!$ua_custom) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
    }

    // Detect Browser name, version and platform
    $ua_info = [];
    if (!empty($user_agent)) {

        $parser = new \donatj\UserAgent\UserAgentParser();
        $ua     = $parser->parse($user_agent);
        //r($ua);
        $ua_info['browser']      = $ua->browser();
        $ua_info['version']      = $ua->browserVersion();
        $ua_info['platform']     = str_replace('Macintosh', 'MacOS', $ua->platform());
        $ua_info['browser_full'] = $ua_info['browser'] . ' ' . preg_replace('/^([^\.]+(?:\.[^\.]+)?).*$/', '\1', $ua_info['version']);
        //r($ua_info);
    }

    // Detect Browser type & icon
    $type = get_browser_type($user_agent);
    $icon = get_browser_icon($type, $ua_info['platform']);

    $detect_browser = [
      'user_agent'   => $user_agent,
      'type'         => $type,
      'icon'         => $icon,
      'browser_full' => $ua_info['browser_full'],
      'browser'      => $ua_info['browser'],
      'version'      => $ua_info['version'],
      'platform'     => $ua_info['platform']
    ];

    // For custom UA, do not cache and return only base User-Agent info
    if ($ua_custom) {
        return $detect_browser;
    }

    // Load screen and DPI detector. This set cookies with:
    get_browser_screen($detect_browser);

    $GLOBALS['cache']['detect_browser'] = $detect_browser; // Store to cache

    if (isset($GLOBALS['config']['devel']) && $GLOBALS['config']['devel']) {
        bdump($GLOBALS['cache']['detect_browser']);
    }

    return $GLOBALS['cache']['detect_browser'];
}

/**
 * This function determines type of web browser for current User-Agent (mobile/tablet/tv/generic).
 * For more detailed browser info and custom User-Agent use detect_browser()
 *
 * @return string Return type of browser (generic/mobile/tablet)
 */
function get_browser_type($user_agent = NULL) {

    $detect = new Mobile_Detect;

    if (!is_null($user_agent)) {
        // Set custom User-Agent
        $detect->setUserAgent($user_agent);
    }
    //bdump($detect);

    // Detect Browser type
    if ($detect->isMobile()) {
        if ($detect->isTablet()) {
            // Any tablet device.
            return 'tablet';
        }

        // Any phone device (exclude tablets).
        return 'mobile';
    }

    if ($detect->isTV()) {
        return 'tv';
    }

    return 'generic';
}

function get_browser_icon($type, $platform) {
    if ($type === 'mobile') {
        return 'icon-mobile-phone';
    }
    if ($type === 'tablet') {
        return 'icon-tablet';
    }
    if ($type === 'tv') {
        return 'icon-film';
    }

    switch (strtolower($platform)) {
        case 'macintosh':
        case 'macos':
            return 'icon-apple';

        case 'windows':
            return 'icon-windows';

        case 'linux':
            return 'icon-linux';
    }

    return 'icon-laptop';
}

function get_browser_screen(&$detect_browser) {

    if (is_graph() || is_api()) {
        return;
    }

    // Load screen and DPI detector. This set cookies with:
    //  $_COOKIE['observium_screen_ratio'] - if ratio >= 2, then HiDPI screen is used
    //  $_COOKIE['observium_screen_resolution'] - screen resolution 'width x height', ie: 1920x1080
    //  $_COOKIE['observium_screen_size'] - current window size (less than resolution) 'width x height', ie: 1097x456
    register_html_resource('js', 'observium-screen.js');

    // Additional browser info (screen_ratio, screen_size)
    $detect_browser['screen_ratio'] = $_COOKIE['observium_screen_ratio'] ?? 2;
    if (isset($_COOKIE['observium_screen_resolution'])) {
        $detect_browser['screen_resolution'] = $_COOKIE['observium_screen_resolution'];
        $detect_browser['screen_size']       = $_COOKIE['observium_screen_size'];
    }

    return $detect_browser['screen_ratio'];
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function data_uri($file, $mime)
{
    $contents = file_get_contents($file);
    $base64   = base64_encode($contents);

    return ('data:' . $mime . ';base64,' . $base64);
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function toner_map($descr, $colour)
{
    return str_istarts($descr, $GLOBALS['config']['toner'][$colour]);
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function toner_to_colour($descr, $percent)
{
    if (str_starts($descr, 'C') || toner_map($descr, "cyan")) {
        $colour['left']  = "B6F6F6";
        $colour['right'] = "33B4B1";
    } elseif (str_starts($descr, 'M') || toner_map($descr, "magenta")) {
        $colour['left']  = "FBA8E6";
        $colour['right'] = "D028A6";
    } elseif (str_starts($descr, 'Y') || toner_map($descr, "yellow")) {
        $colour['left']  = "FFF764";
        $colour['right'] = "DDD000";
    } elseif (str_starts($descr, 'K') || toner_map($descr, "black")) {
        $colour['left']  = "888787";
        $colour['right'] = "555555";
    } elseif (str_starts($descr, 'R') || toner_map($descr, "red")) {
        $colour['left']  = "FB6A4A";
        $colour['right'] = "CB181D";
    }

    if (!isset($colour['left'])) {
        $colour          = get_percentage_colours(100 - $percent);
        $colour['found'] = FALSE;
    } else {
        $colour['found'] = TRUE;
    }

    return $colour;
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_link($text, $vars, $new_vars = [], $escape = TRUE)
{
    if ($escape) {
        $text = escape_html($text);
    }
    return '<a href="' . generate_url($vars, $new_vars) . '">' . $text . '</a>';
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function pagination(&$vars, $total, $options = []) {

    // Compatibility with pre-options
    if (!is_array($options)) {
        $options = [ 'return_vars' => (bool)$options ];
    }

    $pagesizes = [ 10, 20, 50, 100, 500, 1000, 10000, 50000 ]; // Permitted pagesizes
    if (is_numeric($vars['pagesize'])) {
        $per_page = (int)$vars['pagesize'];
    } elseif (isset($_SESSION['pagesize'])) {
        $per_page = $_SESSION['pagesize'];
    } else {
        $per_page = $GLOBALS['config']['web_pagesize'];
    }

    // Widget/ajax do not set session var for pagesize
    if (!$vars['short'] && !is_ajax()) {
        // Permit fixed pagesizes only (except $vars['short'] == TRUE)
        foreach ($pagesizes as $pagesize) {
            if ($per_page <= $pagesize) {
                $per_page = $pagesize;
                break;
            }
        }
        if (isset($vars['pagesize']) && $vars['pagesize'] != $_SESSION['pagesize']) {
            if ($vars['pagesize'] != $GLOBALS['config']['web_pagesize']) {
                session_set_var('pagesize', $per_page); // Store pagesize in session only if changed default
            } elseif (isset($_SESSION['pagesize'])) {
                session_unset_var('pagesize');          // Reset pagesize from session
            }
        }
    }
    $vars['pagesize'] = $per_page;       // Return back current pagesize

    $page     = (int)$vars['pageno'];
    $lastpage = ceil($total / $per_page);
    if ($page < 1) {
        $page = 1;
    } elseif (!$options['return_vars'] && $lastpage < $page) {
        $page = (int)$lastpage;
    }
    $vars['pageno'] = $page; // Return back current pageno

    if ($options['return_vars']) {
        return '';
    } // Silent exit (needed for detect default pagesize/pageno)

    $start = ($page - 1) * $per_page;
    $prev  = $page - 1;
    $next  = $page + 1;
    $lpm1  = $lastpage - 1;

    $adjacents  = 3;
    $pagination = '';

    // Show pagination if total > 99, total > page size, or web_always_paginate is set.
    if ($total > 99 || $total > $per_page ||
        (isset($GLOBALS['config']['web_always_paginate']) && $GLOBALS['config']['web_always_paginate'] === 1)) {

        $total_text = ($total > 9999) ? format_si($total) : $total;

        $pagination .= '<div class="row">' . PHP_EOL .
                       '  <div class="col-lg-1 col-md-2 col-sm-2" style="display: inline-block;">' . PHP_EOL .
                       //'    <span class="btn disabled" style="line-height: 20px;">'.$total.'&nbsp;Items</span>' . PHP_EOL .
                       '    <div class="box box-solid" style="padding: 4px 12px;">' . $total_text . '&nbsp;Items</div>' . PHP_EOL .
                       '  </div>' . PHP_EOL .
                       '  <div class="col-lg-10 col-md-8 col-sm-8">' . PHP_EOL .
                       '    <div class="pagination pagination-centered"><ul>' . PHP_EOL;

        if ($prev) {
            //$pagination .= '      <li><a href="'.generate_url($vars, array('pageno' => 1)).'">First</a></li>' . PHP_EOL;
            $pagination .= '      <li><a href="' . generate_url($vars, ['pageno' => $prev]) . '">Prev</a></li>' . PHP_EOL;
        }

        if ($lastpage < 7 + ($adjacents * 2)) {
            for ($counter = 1; $counter <= $lastpage; $counter++) {
                if ($counter == $page) {
                    $pagination .= "<li class='active'><a>$counter</a></li>";
                } else {
                    $pagination .= "<li><a href='" . generate_url($vars, ['pageno' => $counter]) . "'>$counter</a></li>";
                }
            }
        } elseif ($lastpage > 5 + ($adjacents * 2)) {
            if ($page < 1 + ($adjacents * 2)) {
                for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++) {
                    if ($counter == $page) {
                        $pagination .= "<li class='active'><a>$counter</a></li>";
                    } else {
                        $class = '';
                        //if ($counter > 9)
                        //{
                        //  $class = ' class="hidden-md hidden-sm hidden-xs"';
                        //}
                        //else if ($counter > 6)
                        //{
                        //  $class = ' class="hidden-sm hidden-xs"';
                        //}
                        $pagination .= "<li$class><a href='" . generate_url($vars, ['pageno' => $counter]) . "'>$counter</a></li>";
                    }
                }

                $pagination .= "<li><a href='" . generate_url($vars, ['pageno' => $lpm1]) . "'>$lpm1</a></li>";
                $pagination .= "<li><a href='" . generate_url($vars, ['pageno' => $lastpage]) . "'>$lastpage</a></li>";
            } elseif ($lastpage - ($adjacents * 2) > $page && $page > ($adjacents * 2)) {
                $pagination .= "<li><a href='" . generate_url($vars, ['pageno' => '1']) . "'>1</a></li>";
                $pagination .= "<li><a href='" . generate_url($vars, ['pageno' => '2']) . "'>2</a></li>";

                for ($counter = $page - $adjacents; $counter <= $page + $adjacents; $counter++) {
                    if ($counter == $page) {
                        $pagination .= "<li class='active'><a>$counter</a></li>";
                    } else {
                        $pagination .= "<li><a href='" . generate_url($vars, ['pageno' => $counter]) . "'>$counter</a></li>";
                    }
                }

                $pagination .= "<li><a href='" . generate_url($vars, ['pageno' => $lpm1]) . "'>$lpm1</a></li>";
                $pagination .= "<li><a href='" . generate_url($vars, ['pageno' => $lastpage]) . "'>$lastpage</a></li>";
            } else {
                $pagination .= "<li><a href='" . generate_url($vars, ['pageno' => '1']) . "'>1</a></li>";
                $pagination .= "<li><a href='" . generate_url($vars, ['pageno' => '2']) . "'>2</a></li>";
                for ($counter = $lastpage - (2 + ($adjacents * 2)); $counter <= $lastpage; $counter++) {
                    if ($counter == $page) {
                        $pagination .= "<li class='active'><a>$counter</a></li>";
                    } else {
                        $class = '';
                        //if ($lastpage - $counter > 9)
                        //{
                        //  $class = ' class="hidden-md hidden-sm hidden-xs"';
                        //}
                        //else if ($lastpage - $counter > 6)
                        //{
                        //  $class = ' class="hidden-sm hidden-xs"';
                        //}
                        $pagination .= "<li$class><a href='" . generate_url($vars, ['pageno' => $counter]) . "'>$counter</a></li>";
                    }
                }
            }
        }

        if ($page < $counter - 1) {
            $pagination .= "<li><a href='" . generate_url($vars, ['pageno' => $next]) . "'>Next</a></li>";
            # No need for "Last" as we don't have "First", 1, 2 and the 2 last pages are always in the list.
            #$pagination.= "<li><a href='".generate_url($vars, array('pageno' => $lastpage))."'>Last</a></li>";
        } elseif ($lastpage > 1) {
            $pagination .= "<li class='active'><a>Next</a></li>";
            #$pagination.= "<li class='active'><a>Last</a></li>";
        }

        $pagination .= "</ul></div></div>";

        //$values = array('' => array('name'))
        foreach ($pagesizes as $pagesize) {
            $value          = generate_url($vars, ['pagesize' => $pagesize, 'pageno' => floor(float_div($start, $pagesize))]);
            $name           = $pagesize == $GLOBALS['config']['web_pagesize'] ? "[ $pagesize ]" : $pagesize;
            $values[$value] = ['name' => $name, 'class' => 'text-center'];
        }
        $element = [
          'type'       => 'select',
          'class'      => 'pagination',
          'id'         => 'pagesize',
          'name'       => '# ' . $per_page,
          'width'      => '90px',
          'onchange'   => "window.open(this.options[this.selectedIndex].value,'_top')",
          'value'      => $per_page,
          'data-style' => 'box',
          'values'     => $values
        ];

        $pagination .= '
       <div class="col-lg-1 col-md-2 col-sm-2">
       <form class="pull-right pagination" action="#">';

        $pagination .= generate_form_element($element);

        $pagination .= '</form></div></div>';
    }

    return $pagination;
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_url($vars, $new_vars = []) {
    $vars = !safe_empty($vars) ? array_merge($vars, (array)$new_vars) : (array)$new_vars;

    $url = !safe_empty($vars['page']) ? urlencode($vars['page']) : '';
    unset($vars['page']);

    if (!str_ends_with($url, '/')) {
        $url .= '/';
    }

    $quoted = isset($vars['quoted']) && get_var_true($vars['quoted']);

    foreach ($vars as $var => $value) {
        if ($var === "username" || $var === "password" || $var === "quoted") {
            // Ignore these vars. They shouldn't end up in URLs.
            continue;
        }

        if (is_array($value)) {
            // Keep a numeric array list as a comma list
            $value_encode = is_array_numeric($value) ? implode(',', $value) : var_encode($value);
        } elseif (!is_numeric($var) && !safe_empty($value) && !str_contains($var, "opt")) {
            if ($quoted && str_contains($value, ',')) {
                //bdump($value);
                // values with comma converted to array,
                // ie: peer_as=AS1736%3A%20MU-AS%2C%20US
                // reference page: /routing/protocol=bgp/peer_as=AS1736%3A%20MU-AS%2C%20US/view=graphs/graph=prefixes_ipv4unicast/
                $value = '"' . $value . '"';
                //bdump($value);
            }
            // rawurlencode() according change in r12351
            $value_encode = rawurlencode(str_replace([ '%', '/' ], [ '%05', '%7F' ], $value)); // %7F converted back to / in get_vars()
        } else {
            if ($GLOBALS['config']['debug']) {
                bdump("generate_url() skipped: $var = $value");
            }
            continue;
        }
        $url .= urlencode($var) . '=' . $value_encode . '/';
    }

    // If we're being generated outside the web interface, prefix the generated URL to make it work properly.
    if (is_cli()) {
        if ($GLOBALS['config']['web_url'] === 'http://localhost:80/') { // default
            // override default web_url by http://localhost/
            $url = 'http://' . get_localhost() . '/' . $url;
        } else {
            $url = $GLOBALS['config']['web_url'] . $url;
        }
    }

    return $url;
}

function generate_html_attribs($attribs) {
    if (!is_array($attribs)) {
        return '';
    }

    // Make some common operations with attribs, i.e. confirmation
    process_html_attribs($attribs);

    // Filter attributes (data-*, aria-*, role, style, class)
    //$attrib_pattern = '/^(data\-[_\w\-]+|aria\-[_\w\-]+|role|class|style|onclick)$/';
    // More free form HTML attributes, ie: https://www.w3schools.com/tags/ref_attributes.asp
    $attrib_pattern = '/^((data|aria|http|accept)\-[a-z_\-]+|[a-z]+)$/';

    $elements = [];
    foreach ($attribs as $attr => $value) {
        if (is_array($value)) {
            $value = implode(' ', $value);
        } elseif (safe_empty($value)) {
            continue;
        }

        if (preg_match($attrib_pattern, $attr)) {
            $elements[] = escape_html($attr) . '="' . escape_html($value) . '"';
        }
    }

    if (!empty($elements)) {
        return implode(' ', $elements);
    }
    return '';
}

function process_html_attribs(&$attribs) {
    if (isset($attribs['data-toggle'])) {
        // Enable item specific JS/CSS/Script
        switch ($attribs['data-toggle']) {
            case 'confirm':
            case 'confirmation':
                if ($attribs['data-toggle'] === 'confirmation') {
                    $attribs['data-toggle'] = 'confirm';
                }

                // Bootstrap-Confirmation
                register_html_resource('js', 'bootstrap-confirmation.min.js');

                //register_html_resource('script', '$("[data-toggle=\'' . $attribs['data-toggle'] .
                //                                 '\']").confirmation({rootSelector: \'[data-toggle=' . $attribs['data-toggle'] . ']\',});');
                //$script_options = [ 'rootSelector: \'[data-toggle=' . $attribs['data-toggle'] . ']\'' ];

                if (!isset($attribs['data-btn-ok-label'])) {
                    // default "Yes"
                    //$attribs['data-btn-ok-label'] = 'Yes';
                }
                if (!isset($attribs['data-btn-ok-class'])) {
                    // default "btn btn-xs btn-primary"
                }
                if (!isset($attribs['data-btn-ok-icon'])) {
                    // default "glyphicon glyphicon-ok"
                    //$attribs['data-btn-ok-icon'] = 'Yes';
                }
                if (!isset($attribs['data-btn-cancel-label'])) {
                    // default "No"
                    //$attribs['data-btn-cancel-label'] = 'Cheese';
                }
                if (!isset($attribs['data-btn-cancel-class'])) {
                    // default "btn btn-xs btn-default"
                    //$attribs['data-btn-cancel-class'] = 'btn-small btn-warning';
                }
                if (!isset($attribs['data-btn-cancel-icon'])) {
                    // default "glyphicon glyphicon-remove"
                    //$attribs['data-btn-cancel-icon'] = 'icon-sort';
                }

                // migrate from popConfirm
                if (!isset($attribs['data-title'])) {
                    $attribs['data-title'] = 'Confirmation';
                }
                if (!isset($attribs['data-html'])) {
                    $attribs['data-html'] = 'true';
                }
                if (!isset($attribs['data-singleton'])) {
                    $attribs['data-singleton'] = 'true';
                }
                if (!isset($attribs['data-popout'])) {
                    $attribs['data-popout'] = 'true';
                }
                if (isset($attribs['data-confirm-placement'])) {
                    $attribs['data-placement'] = $attribs['data-confirm-placement'];
                    unset($attribs['data-confirm-placement']);
                }
                if (isset($attribs['data-confirm-content'])) {
                    $attribs['data-content'] = $attribs['data-confirm-content'];
                    unset($attribs['data-confirm-content']);
                }
                //register_html_resource('script', '$("[data-toggle=\'' . $attribs['data-toggle'] . '\']").confirmation({' . implode(', ', $script_options) . '});');
                break;
        }
    }
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_feed_url($vars)
{
    if (!class_exists('SimpleXMLElement')) {
        return FALSE;
    } // Break if class SimpleXMLElement is not available.

    if (!is_numeric($_SESSION['user_id']) || !is_numeric($_SESSION['userlevel'])) {
        return FALSE;
    }

    if ($key = get_user_pref($_SESSION['user_id'], 'atom_key')) {
        $param   = [];
        $param[] = rtrim($GLOBALS['config']['base_url'], '/') . '/feed.php?id=' . $_SESSION['user_id'];
        $param[] = 'hash=' . encrypt($_SESSION['user_id'] . '|' . $_SESSION['userlevel'] . '|' . $_SESSION['auth_mechanism'], $key);

        $feed_type = 'atom';
        foreach ($vars as $var => $value) {
            if ($value != '') {
                switch ($var) {
                    case 'v':
                        if ($value == 'rss') {
                            $param[]   = "$var=rss";
                            $feed_type = 'rss';
                        }
                        break;
                    case 'feed':
                        $title   = "Observium :: " . ucfirst($value) . " Feed";
                        $param[] = 'size=' . $GLOBALS['config']['frontpage']['eventlog']['items'];
                    // no break here
                    case 'size':
                        $param[] = "size=" . (int)$value;
                        break;
                }
            }
        }

        $baseurl = implode('&amp;', $param);

        return '<link href="' . $baseurl . '" rel="alternate" title="' . escape_html($title) . '" type="application/' . $feed_type . '+xml" />';
    }

    return FALSE;
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_location_url($location, $vars = [])
{
    if ($location === '') {
        $location = OBS_VAR_UNSET;
    }
    $value = var_encode($location);
    return generate_url(['page' => 'devices', 'location' => $value], $vars);
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_overlib_content($graph_array, $text = NULL, $escape = TRUE) {

    $graph_array['height'] = "100";
    $graph_array['width']  = "220";

    if ($escape) {
        $text = escape_html($text);
    }

    $content = '<div style="width: 590px;"><span style="font-weight: bold; font-size: 16px;">' . $text . '</span><br />';
    /*
  $box_args = array('body-style' => 'width: 590px;');
  if (strlen($text))
  {
    $box_args['title'] = $text;
  }
  $content = generate_box_open($box_args);
  */
    foreach ([ 'day', 'week', 'month', 'year' ] as $period) {
        $graph_array['from'] = get_time($period);
        $content             .= generate_graph_tag($graph_array);
    }
    $content .= "</div>";
    //$content .= generate_box_close();

    return $content;
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function get_percentage_colours($percentage)
{

    if ($percentage > '90') {
        $background['left']  = 'cb181d';
        $background['right'] = 'fb6a4a';
        $background['class'] = 'error';
    } elseif ($percentage > '80') {
        $background['left']  = 'cc4c02';
        $background['right'] = 'fe9929';
        $background['class'] = 'warning';
    } elseif ($percentage > '60') {
        $background['left']  = '6a51a3';
        $background['right'] = '9e9ac8';
        $background['class'] = 'information';
    } elseif ($percentage > '30') {
        $background['left']  = '045a8d';
        $background['right'] = '74a9cf';
        $background['class'] = 'information';
    } else {
        $background['left']  = '4d9221';
        $background['right'] = '7fbc41';
        $background['class'] = 'information';
    }

    return ($background);
}

/**
 * Generate common popup links which uses ajax/entitypopup.php
 *
 * @param string  $type   Popup type, see possible types in html/ajax/entitypopup.php
 * @param string  $text   Text used as link name and ajax data
 * @param array   $vars   Array for generate url
 * @param string  $class  Additional css classes for link
 * @param boolean $escape Escape or not text in url
 *
 * @return string Returns string with link, when hover on this link show popup message based on type
 */
function generate_popup_link($type, $text = NULL, $vars = [], $class = NULL, $escape = TRUE) {
    if (!is_string($type) || !is_string($text)) {
        return '';
    }

    if ($type === 'ip' && $return = generate_ip_link($text, $vars, $class, $escape)) {
        return $return;
    }

    if ($type === 'autodiscovery') {
        $data   = $text;
        $text   = get_icon('info');
        $escape = FALSE;
    }

    $url  = safe_count($vars) ? generate_url($vars) : 'javascript:void(0)'; // If vars empty, a set link not clickable
    $data = $data ?? $text;
    if ($escape) {
        $text = escape_html($text);
    }

    return '<a href="' . $url . '" class="entity-popup' . ($class ? " $class" : '') . '" data-eid="' . $data . '" data-etype="' . $type . '">' . $text . '</a>';
}

function generate_tooltip_time($timestamp, $text = '') {

    if (is_valid_unixtime($timestamp)) {
        // Unixtime
        $timediff = get_time() - $timestamp;
        $timetext = format_uptime($timediff, "short-3");
        if (!safe_empty($text)) {
            $timetext .= " $text";
        }

        return generate_tooltip_link('', $timetext, format_unixtime($timestamp), NULL);
    }

    // Timestamp
    $timediff = get_time() - strtotime($timestamp);
    $timetext = format_uptime($timediff, "short-3");
    if (!safe_empty($text)) {
        $timetext .= " $text";
    }

    return generate_tooltip_link('', $timetext, format_timestamp($timestamp), NULL);
}

/**
 * Generate mouseover links with static tooltip from URL, link text, contents and a class.
 *
 * Tooltips with static position and linked to current object.
 * Note, mostly same as overlib_link(), except tooltip position.
 * Always display tooltip if content not empty
 *
 * @param string  $url      URL string
 * @param string  $text     Text displayed as link
 * @param string  $contents Text content displayed in mouseover tooltip (only for non-mobile devices)
 * @param string  $class    Css class name used for link
 * @param array   $attribs  Url/link extended attributes (ie data-*, class, style)
 * @param boolean $escape   Escape or not link text
 *
 * @return string
 */
// TESTME needs unit testing
function generate_tooltip_link($url, $text, $contents = '', $class = NULL, $attribs = [], $escape = FALSE)
{
    global $config, $link_iter;

    $link_iter++;

    $href = !safe_empty($url) ? 'href="' . $url . '"' : '';
    if ($escape) {
        $text = escape_html($text);
    }

    $attribs['class'] = array_merge((array)$class, (array)$attribs['class']);

    // Allow the Grinch to disable popups and destroy Christmas.
    $allow_mobile = !in_array(detect_browser()['type'], [ 'mobile', 'tablet' ]) || $config['web_mouseover_mobile'];
    if ($config['web_mouseover'] && $allow_mobile && !safe_empty($contents)) {
        $attribs['style']        = 'cursor: pointer;';
        $attribs['data-rel']     = 'tooltip';
        $attribs['data-tooltip'] = $contents;
        //$output  = '<a '.$href.' class="'.$class.'" style="cursor: pointer;" data-rel="tooltip" data-tooltip="'.escape_html($contents).'">'.$text.'</a>';
    }

    return '<a ' . $href . ' ' . generate_html_attribs($attribs) . '>' . $text . '</a>';
}

/**
 * Generate mouseover links from URL, link text, contents and a class.
 *
 * Tooltips followed by mouse cursor.
 * Note, by default text NOT escaped for compatability with many old magic code usage.
 *
 * @param string  $url      URL string
 * @param string  $text     Text displayed as link
 * @param string  $contents Text content displayed in mouseover tooltip (only for non-mobile devices)
 * @param string  $class    Css class name used for link
 * @param array   $attribs  Url/link extended attributes (ie data-*, class, style)
 * @param boolean $escape   Escape or not link text
 */
// TESTME needs unit testing
function generate_mouseover_link($url, $text, $contents, $class = NULL, $attribs = [], $escape = FALSE)
{
    global $config, $link_iter;

    $link_iter++;

    $href = (strlen($url) ? 'href="' . $url . '"' : '');
    if ($escape) {
        $text = escape_html($text);
    }

    if ($class) {
        $attribs['class'] = array_merge((array)$class, (array)$attribs['class']);
    }

    // Allow the Grinch to disable popups and destroy Christmas.
    $allow_mobile = (in_array(detect_browser()['type'], [ 'mobile', 'tablet' ]) ? $config['web_mouseover_mobile'] : TRUE);
    if ($config['web_mouseover'] && strlen($contents) && $allow_mobile) {
        $attribs['style']        = 'cursor: pointer;';
        $attribs['class']        = array_merge(['tooltip-from-data'], (array)$attribs['class']);
        $attribs['data-tooltip'] = $contents;
        //$output  = '<a '.$href.' class="tooltip-from-data '.$class.'" style="cursor: pointer;" data-tooltip="'.escape_html($contents).'">'.$text.'</a>';
    }

    return '<a ' . $href . ' ' . generate_html_attribs($attribs) . '>' . $text . '</a>';
}

function overlib_link($url, $text, $contents, $class = NULL, $attribs = [], $escape = FALSE)
{
    return generate_mouseover_link($url, $text, $contents, $class, $attribs, $escape);
}

/**
 * DERPECATED
 * Generate menu links with item counts from URL, link text, contents and a class.
 *
 * Tooltips with static position and linked to current object.
 * Note, mostly same as overlib_link(), except tooltip position.
 * Always display tooltip if content not empty
 *
 * @param string  $url    URL string
 * @param string  $text   Text displayed as link
 * @param string  $count  Counts displayed at right
 * @param string  $class  Css class name used for count (default is 'label')
 * @param boolean $escape Escape or not link text
 */
function generate_menu_link($url, $text, $count = NULL, $class = 'label', $escape = FALSE, $alert_count = NULL)
{
    return generate_menu_link_ng([ 'url'         => $url,
                                   'count'       => $count,
                                   'alert_count' => $alert_count,
                                   'count_class' => $class ], $text, $escape);
}


/**
 * Generate menu links with item counts from URL, link text, contents and a class.
 *
 * Replaces previous function with multiple arguments. Should be used for all navbar menus
 *
 * @param array $array Array of options
 * @param string $text
 * @param bool $escape
 */
// TESTME needs unit testing
function generate_menu_link_ng($array, $text = NULL, $escape = TRUE) {

    $array = array_merge([ 'role' => 'menuitem', 'count' => NULL, 'event' => '' ], (array)$array);

    $attribs = [];
    if (isset($array['role'])) {
        $attribs['role'] = $array['role'];
    }
    if (isset($array['tooltip'])) {
        $attribs['alt'] = $array['tooltip'];
    }
    if (isset($array['alt'])) {
        $attribs['data-rel']     = 'tooltip';
        $attribs['data-tooltip'] = $array['alt'];
    }
    if (isset($array['id'])) {
        $attribs['id'] = $array['id'];
    }

    if (isset($array['attribs']) && is_array($array['attribs'])) {
        $attribs = array_merge($attribs, $array['attribs']);
        unset($array['link_opts']); // do not use unescaped link_opts, convert to attribs!
    }
    $link_opts = generate_html_attribs($attribs);
    if (isset($array['link_opts'])) {
        $link_opts .= ' ' . $array['link_opts'];
    }

    if (empty($array['url']) || $array['url'] === '#' || isset($array['action'])) {
        $array['url'] = 'javascript:void(0)';
    }

    $output = '<a href="' . $array['url'] . '" ' . $link_opts . '>';

    $output .= '<span>';
    if (isset($array['icon'])) {
        $output .= get_icon($array['icon']) . '&nbsp;';
    }
    if (isset($entry['image'])) {
        // Detect an allowed screen ratio for current browser, cached!
        $ua_info = detect_browser();
        if (isset($entry['image_2x']) && $ua_info['screen_ratio'] > 1) {
            // Add hidpi image set
            $srcset = ' srcset="' . $entry['image_2x'] . ' 2x"';
        } else {
            $srcset = '';
        }
        $output .= '<img src="' . $entry['image'] . '"' . $srcset . ' alt="" />' . '&nbsp;';
    }

    if (safe_empty($text)) {
        if (isset($array['title'])) {
            $text = $array['title'];
        } elseif (isset($array['text'])) {
            $text = $array['text'];
        }
    }
    if ($array['escape'] ?? $escape) {
        $text = escape_html($text);
    }

    $output .= $text . '</span>';

    // Counter label(s) in navbar menu
    $extra = '';
    if (isset($array['count_array']) && safe_count($array['count_array'])) {
        // Multiple counts as a group
        $count_items = [];
        // Ok/Up
        if ($array['count_array']['ok']) {
            $count_items[] = [ 'event' => 'success', 'text' => $array['count_array']['ok'] ];
        } elseif ($array['count_array']['up']) {
            $count_items[] = [ 'event' => 'success', 'text' => $array['count_array']['up'] ];
        }
        // Warning
        if ($array['count_array']['warning']) {
            $count_items[] = [ 'event' => 'warning', 'text' => $array['count_array']['warning'] ];
        }
        // Alert/Down
        if ($array['count_array']['alert']) {
            $count_items[] = [ 'event' => 'danger', 'text' => $array['count_array']['alert'] ];
        } elseif ($array['count_array']['down']) {
            $count_items[] = [ 'event' => 'danger', 'text' => $array['count_array']['down'] ];
        }
        // Ignored
        if ($array['count_array']['ignored']) {
            $count_items[] = [ 'event' => 'default', 'text' => $array['count_array']['ignored'] ];
        }
        // Disabled
        if ($array['count_array']['disabled']) {
            $count_items[] = [ 'event' => 'inverse', 'text' => $array['count_array']['disabled'] ];
        }
        // Fallback to just count
        if (empty($count_items) && !safe_empty($array['count_array']['count'])) {
            $count_items[] = [ 'event' => 'default', 'text' => $array['count_array']['count'] ];
        }

        //r(get_label_group($count_items));
        $extra .= get_label_group($count_items);
    } else {
        // single counts
        if (isset($array['alert_count']) && is_numeric($array['alert_count'])) {
            //$extra .= ' <span class="label label-danger">' . $array['alert_count'] . '</span> ';
            $extra .= ' ' . get_label_span($array['alert_count'], 'danger') . ' ';
        }

        if (isset($array['count']) && is_numeric($array['count'])) {
            //$extra .= ' <span class="' . $array['count_class'] . '">' . $array['count'] . '</span>';
            $extra .= ' ' . get_label_span($array['count'], $array['events']);
        }
    }
    if (isset($array['extra'])) {
        // used in refresh menu
        $extra .= ($array['escape'] ?? $escape) ? escape_html($array['extra']) : $array['extra'];
    }

    $output .= $extra . '</a>';

    return $output;
}


// Generate a typical 4-graph popup using $graph_array
// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_graph_popup($graph_array)
{
    global $config;

    // Todo - this should have entity headers where appropriate, too.

    // Take $graph_array and print day,week,month,year graps in overlib, hovered over graph

    $original_from = $graph_array['from'];

    $graph = generate_graph_tag($graph_array);

    /*
  $box_args = array('body-style' => 'width: 850px;');
  if (strlen($graph_array['popup_title']))
  {
    $box_args['title'] = $graph_array['popup_title'];
  }
  $content = generate_box_open($box_args);
  */
    unset($graph_array['style']);
    $content               = '<div class=entity-title><h4>' . $graph_array['popup_title'] . '</h4></div>';
    $content               .= '<div style="width: 850px">';
    $graph_array['legend'] = "yes";
    $graph_array['height'] = "100";
    $graph_array['width']  = "340";
    $graph_array['from']   = get_time('day');
    $content               .= generate_graph_tag($graph_array);
    $graph_array['from']   = get_time('week');
    $content               .= generate_graph_tag($graph_array);
    $graph_array['from']   = get_time('month');
    $content               .= generate_graph_tag($graph_array);
    $graph_array['from']   = get_time('year');
    $content               .= generate_graph_tag($graph_array);
    $content               .= "</div>";
    //$content .= generate_box_close();

    $graph_array['from'] = $original_from;

    $graph_array['link'] = generate_url($graph_array, ['page' => 'graphs', 'height' => NULL, 'width' => NULL, 'bg' => NULL]);

    return overlib_link($graph_array['link'], $graph, $content, NULL);
}

// output the popup generated in generate_graph_popup();
// TESTME needs unit testing
// DOCME needs phpdoc block
function print_graph_popup($graph_array)
{
    echo(generate_graph_popup($graph_array));
}

/**
 * Delete orphaned entity permission entries for non-existent entities
 *
 * @param string $mode        'role' or 'user'
 * @param string $entity_type Entity type (device, port, sensor, bill, group)
 * @param int    $entity_id   Entity ID
 * @param array  $params      Array containing role_id or user_id
 *
 * @return int Number of deleted entries
 */
function entity_permission_cleanup($mode, $entity_type, $entity_id, $params) {
    $table = $mode === 'role' ? 'roles_entity_permissions' : 'entity_permissions';
    $where = '`entity_type` = ? AND `entity_id` = ?';

    if ($mode === 'role') {
        $where .= ' AND `role_id` = ?';
        $result = dbDelete($table, $where, [$entity_type, $entity_id, $params['role_id']]);
    } else {
        $where .= ' AND `user_id` = ?';
        $result = dbDelete($table, $where, [$entity_type, $entity_id, $params['user_id']]);
    }

    return $result;
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function permissions_cache($user_id) {

    $cache_key  = 'permissions_' . $GLOBALS['config']['auth_mechanism'] . $user_id;
    $cache_item = get_cache_item($cache_key);
    if (ishit_cache_item($cache_item)) {
        // permissions stored in Fast Cache
        $permissions = get_cache_data($cache_item);
        mem_cache_set('user_permissions', $permissions);
        return $permissions;
    }

    if (!mem_cache_exists('user_permissions')) {
        // Init cache entry and build array
        $permissions =& mem_cache_key('user_permissions');

        // Get permissions from user-specific and role tables.
        $permission_where         = '`user_id` = ? AND `auth_mechanism` = ?';
        $permission_params        = [ $user_id, $GLOBALS['config']['auth_mechanism'] ];
        $entity_permissions       = dbFetchRows("SELECT * FROM `entity_permissions` WHERE " . $permission_where, $permission_params);
        $roles_entity_permissions = dbFetchRows("SELECT * FROM `roles_entity_permissions` LEFT JOIN `roles_users` USING (`role_id`) WHERE " . $permission_where, $permission_params);
        foreach (array_merge((array)$entity_permissions, (array)$roles_entity_permissions) as $entity) {
            // Set access to ro if it's not in the defined list.
            $access = (in_array($entity['access'], [ 'ro', 'rw' ]) ? $entity['access'] : 'ro');

            if ($entity['entity_type'] === 'group') {
                // this is a group, so expand its members into an array
                $group = get_group_by_id($entity['entity_id']);
                foreach (get_group_entities($entity['entity_id']) as $group_entity_id) {
                    $permissions[$group['entity_type']][(int)$group_entity_id] = $access;
                }
            } elseif ($entity['entity_type'] === 'poller') {
                // this is a poller, so expand to all devices on this poller
                $poller_devices = dbFetchColumn('SELECT `device_id` FROM `devices` WHERE `poller_id` = ?',
                                                 [$entity['entity_id']]);
                foreach ($poller_devices as $device_id) {
                    $permissions['device'][(int)$device_id] = $access;
                }
            }
            $permissions[$entity['entity_type']][(int)$entity['entity_id']] = $access;
        }

        // Cache platform permissions
        foreach (dbFetchRows("SELECT * FROM `roles_permissions` LEFT JOIN `roles_users` USING (`role_id`) WHERE " . $permission_where, $permission_params) as $perm) {
            $permissions['permission'][$perm['permission']] = TRUE;
        }

        // Alerts
        // FIXME - this seems like it would be slow on very large installs
        $alert = [];
        foreach (dbFetchRows('SELECT `alert_table_id`, `device_id`, `entity_id`, `entity_type` FROM `alert_table`') as $alert_table_entry) {
            //r($alert_table_entry);
            if (is_entity_permitted($alert_table_entry['entity_id'], $alert_table_entry['entity_type'], $alert_table_entry['device_id'], $permissions)) {
                $alert[$alert_table_entry['alert_table_id']] = TRUE;
            }
        }
        if (!safe_empty($alert)) {
            $permissions['alert'] = $alert;
        }
    } else {
        $permissions = mem_cache_get('user_permissions');
    }

    set_cache_item($cache_item, $permissions);

    // Clear expired cache
    del_cache_expired();

    return $permissions;
}

/**
 * Return WEB client remote IP address.
 * In most cases (also by default) this is just $_SERVER['REMOTE_ADDR'],
 * but if config options ($config['web_remote_addr_header']) set, this can use specified HTTP headers
 *
 * @param bool $use_http_header Use or not HTTP header specified in $config['web_remote_addr_header']
 *
 * @return string IP address of remote client
 */
function get_remote_addr($use_http_header = FALSE) {

    if ($use_http_header) {
        // Note, these headers is very dangerous for use as auth!
        $addr_headers = [
            'HTTP_CF_CONNECTING_IP',   // CF-Connecting-IP (CloudFlare network)
            'HTTP_CF_CONNECTING_IPV6', // CF-Connecting-IPv6 (CloudFlare network)
            'HTTP_X_REAL_IP',          // X-Real-IP
            'HTTP_X_FORWARDED_FOR',    // X-Forwarded-For
            'HTTP_CLIENT_IP'           // Client-IP
        ];

        if (!in_array($GLOBALS['config']['web_remote_addr_header'], [ 'default', 'detect', 'auto' ])) {
            $remote_addr_header = 'HTTP_' . str_replace('-', '_', strtoupper($GLOBALS['config']['web_remote_addr_header']));
            if (in_array($remote_addr_header, $addr_headers)) {
                // Use only exact single header
                $addr_headers = [ $remote_addr_header ];
                if ($remote_addr_header === 'HTTP_CF_CONNECTING_IP') {
                    // Append IPv6 header for CloudFlare
                    $addr_headers[] = 'HTTP_CF_CONNECTING_IPV6';
                }
            } else {
                // Unknown config value passed, do not check any header
                $addr_headers = [];
            }
        }

        foreach ($addr_headers as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }
            if ($GLOBALS['config']['devel']) {
                //bdump($_SERVER);
                bdump($_SERVER[$header], $header);
            }
            if (preg_match(OBS_PATTERN_IP_FULL, $_SERVER[$header], $matches)) {
                // HTTP header found and it contains valid IP address
                return $matches[1];
            }
        }
    }

    // By default, just use server remote address
    return $_SERVER['REMOTE_ADDR'];
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function print_graph_tag($args)
{
    echo(generate_graph_tag($args));
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_graph_tag($args, $return_array = FALSE)
{

    if (empty($args)) {
        // Quickly return if passed an empty array
        return '';
    }

    $i = [];
    $style = 'max-width: 100%; width: auto; vertical-align: top;';
    if (isset($args['style'])) {
        if (is_array($args['style'])) {
            $style .= implode("; ", $args['style']) . ';';
        } else {
            $style .= $args['style'] . ';';
        }
        unset($args['style']);
    }

    $i['img_id'] = $args['img_id'] ?? random_string(8);

    // Detect an allowed screen ratio for the current browser
    $ua_info = detect_browser();
    $zoom    = $ua_info['screen_ratio'];

    if ($zoom > 1) {
        // Add img srcset for HiDPI screens
        $args_x         = $args;
        $args_x['zoom'] = $zoom;
        $srcset         = ' srcset="' . generate_graph_url($args_x) . ' ' . $args_x['zoom'] . 'x"';
        $i['srcset']    = $srcset;
    } else {
        $srcset = '';
    }

    $attribs = generate_html_attribs(array_filter_key($args, [ 'class', 'loading', 'fetchpriority' ]));

    $img_url = generate_graph_url($args);

    $i['img_url'] = $img_url;
    $i['img_tag'] = '<img id="' . $i['img_id'] . '" ' . $attribs . ' src="' . $img_url . '"' . $srcset . ' style="' . $style . '" alt="" />';
    //$i['img_tag'] = '<img id="' . $i['img_id'] . '" src="' . $img_url . '"' . $srcset . $attribs.' style="' . $style . '" alt="" loading="lazy" />';

    if ($return_array) {
        return $i;
    }

    return $i['img_tag'];
}

function generate_graph_url($args, $escape = TRUE)
{

    // Encode arrays in fields before we build the URL
    foreach ($args as $key => $arg) {
        if (is_array($arg)) {
            // Encode arrays
            $args[$key] = var_encode($arg);
        }
    }

    $url = 'graph.php?' . http_build_query($args);

    if (is_cli()) {
        if ($GLOBALS['config']['web_url'] == 'http://localhost:80/') {
            // override default web_url by http://localhost/
            $url = 'http://' . get_localhost() . '/' . $url;
        } else {
            $url = $GLOBALS['config']['web_url'] . $url;
        }
    }

    return $url;

}

// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_graph_js_state($args)
{
    // we are going to assume we know roughly what the graph url looks like here.
    // TODO: Add sensible defaults
    $from   = (is_numeric($args['from']) ? $args['from'] : 0);
    $to     = (is_numeric($args['to']) ? $args['to'] : 0);
    $width  = (is_numeric($args['width']) ? $args['width'] : 0);
    $height = (is_numeric($args['height']) ? $args['height'] : 0);
    $legend = str_replace("'", "", $args['legend']);

    $state = <<<STATE
<script type="text/javascript">
document.graphFrom = $from;
document.graphTo = $to;
document.graphWidth = $width;
document.graphHeight = $height;
document.graphLegend = '$legend';
</script>
STATE;

    return $state;
}

/**
 * Generate Percentage Bar
 *
 * This function generates an Observium percentage bar from a supplied array of arguments.
 * It is possible to draw a bar that does not work at all,
 * So care should be taken to make sure values are valid.
 *
 * @param array $args
 *
 * @return string
 */

// TESTME needs unit testing
function percentage_bar($args)
{
    if (strlen($args['bg'])) {
        $style .= 'background-color:' . $args['bg'] . ';';
    }
    if (strlen($args['border'])) {
        $style .= 'border-color:' . $args['border'] . ';';
    }
    if (strlen($args['width'])) {
        $style .= 'width:' . $args['width'] . ';';
    }
    if (strlen($args['text_c'])) {
        $style_b .= 'color:' . $args['text_c'] . ';';
    }

    $total  = '0';
    $output = '<div class="percbar" style="' . $style . '">';
    foreach ($args['bars'] as $bar) {
        $output .= '<div class="bar" style="width:' . $bar['percent'] . '%; background-color:' . $bar['colour'] . ';"></div>';
        $total  += $bar['percent'];
    }
    $left = '100' - $total;
    if ($left > 0) {
        $output .= '<div class="bar" style="width:' . $left . '%;"></div>';
    }

    if ($left >= 0) {
        $output .= '<div class="bar-text" style="margin-left: -100px; margin-top: 0px; float: right; text-align: right; ' . $style_b . '">' . $args['text'] . '</div>';
    }

    foreach ($args['bars'] as $bar) {
        $output .= '<div class="bar-text" style="width:' . $bar['percent'] . '%; max-width:' . $bar['percent'] . '%; padding-left: 4px;">' . $bar['text'] . '</div>';
    }
#  if ($left > '0') { $output .= '<div class="bar-text" style="margin-left: -100px; margin-top: -16px; float: right; text-align: right; '.$style_b.'">'.$args['text'].'</div>'; }

    $output .= '</div>';

    return $output;
}

// Legacy function
// DO NOT USE THIS. Please replace instances of it with percentage_bar from above.
// TESTME needs unit testing
// DOCME needs phpdoc block
function print_percentage_bar($width, $height, $percent, $left_text, $left_colour, $left_background, $right_text, $right_colour, $right_background)
{

    if ($percent > "100") {
        $size_percent = "100";
    } else {
        $size_percent = $percent;
    }

    $percentage_bar['border']  = "#" . $left_background;
    $percentage_bar['bg']      = "#" . $right_background;
    $percentage_bar['width']   = $width;
    $percentage_bar['text']    = $right_text;
    $percentage_bar['bars'][0] = ['percent' => $size_percent, 'colour' => '#' . $left_background, 'text' => $left_text];

    $output = percentage_bar($percentage_bar);

    return $output;
}

/**
 * Return cached locations list
 *
 * If filter used, return locations available only for specified params.
 * Without filter return all available locations (cached)
 *
 * @param array $filter
 *
 * @return array
 */
// TESTME needs unit testing
function get_locations($filter = [])
{
    $where_array = [];
    foreach ($filter as $var => $value) {
        switch ($var) {
            case 'location_lat':
            case 'location_lon':
            case 'location_country':
            case 'location_state':
            case 'location_county':
            case 'location_city':
                // Check geo params only when GEO enabled globally
                if ($GLOBALS['config']['geocoding']['enable']) {
                    $where_array[$var] = generate_query_values($value, $var);
                }
                break;
            case 'location':
                $where_array[$var] = generate_query_values($value, $var);
                break;
        }
    }

    if (!safe_empty($where_array)) {
        // Return only founded locations
        $locations = dbFetchColumn("SELECT DISTINCT `location` FROM `devices_locations`" . generate_where_clause($GLOBALS['cache']['where']['devices_permitted'], $where_array));
    } else {
        $locations = empty($GLOBALS['cache']['device_locations']) ? [] : array_keys($GLOBALS['cache']['device_locations']);
    }

    sort($locations);

    return $locations;
}

// Note, by default text NOT escaped.
// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_ap_link($args, $text = NULL, $type = NULL, $escape = FALSE)
{

    humanize_port($args);

    if (!$text) {
        $text = escape_html($args['port_label']);
    }
    if ($type) {
        $args['graph_type'] = $type;
    }
    if (!isset($args['graph_type'])) {
        $args['graph_type'] = 'port_bits';
    }

    if (!isset($args['hostname']) && $args['device_id']) {
        $args = array_merge($args, device_by_id_cache($args['device_id']));
    }

    $content = "<div class=entity-title>" . $args['text'] . " - " . escape_html($args['port_label']) . "</div>";
    if ($args['ifAlias']) {
        $content .= escape_html($args['ifAlias']) . "<br />";
    }
    $content               .= "<div style=\'width: 850px\'>";
    $graph_array['type']   = $args['graph_type'];
    $graph_array['legend'] = "yes";
    $graph_array['height'] = "100";
    $graph_array['width']  = "340";
    $graph_array['to']     = get_time();
    $graph_array['from']   = get_time('day');
    $graph_array['id']     = $args['accesspoint_id'];
    $content               .= generate_graph_tag($graph_array);
    $graph_array['from']   = get_time('week');
    $content               .= generate_graph_tag($graph_array);
    $graph_array['from']   = get_time('month');
    $content               .= generate_graph_tag($graph_array);
    $graph_array['from']   = get_time('year');
    $content               .= generate_graph_tag($graph_array);
    $content               .= "</div>";

    $url = generate_ap_url($args);
    if (port_permitted($args['interface_id'], $args['device_id'])) {
        return overlib_link($url, $text, $content, $class, $escape);
    }

    return $text;
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_ap_url($ap, $vars = [])
{
    return generate_url(['page' => 'device', 'device' => $ap['device_id'], 'tab' => 'accesspoint', 'ap' => $ap['accesspoint_id']], $vars);
}

/**
 * Generate SQL WHERE string with check permissions and ignores for device_id, port_id and other
 *
 * Note, this function uses comparison operator IN. Max number of values in the IN list
 * is limited by the 'max_allowed_packet' option (default: 1048576)
 *
 * Usage examples:
 *  generate_query_permitted()
 *   ' AND `device_id` IN (1,4,8,33) AND `device_id` NOT IN (66) AND (`device_id` != '' AND `device_id` IS NOT NULL) '
 *  generate_query_permitted(array('device'), array('device_table' => 'D'))
 *   ' AND `D`.`device_id` IN (1,4,8,33) AND `D`.`device_id` NOT IN (66) AND (`D`.`device_id` != '' AND `D`.`device_id` IS NOT NULL) '
 *  generate_query_permitted(array('device', 'port'), array('port_table' => 'I')) ==
 *   ' AND `device_id` IN (1,4,8,33) AND `device_id` NOT IN (66) AND (`device_id` != '' AND `device_id` IS NOT NULL)
 *     AND `I`.`port_id` IN (1,4,8,33) AND `I`.`port_id` NOT IN (66) AND (`I`.`port_id` != '' AND `I`.`port_id` IS NOT NULL) '
 *  generate_query_permitted(array('device', 'port'), array('port_table' => 'I', 'hide_ignored' => TRUE))
 *    This additionaly exclude all ignored devices and ports
 *
 * @param array|string $type_array Array with permission types, currently allowed 'devices', 'ports'
 * @param array        $options    Options for each permission type: device_table, port_table, hide_ignored, hide_disabled
 *
 * @return string
 * @uses html/includes/cache-data.inc.php
 * @global integer     $_SESSION   ['userlevel']
 * @global boolean     $GLOBALS    ['config']['web_show_disabled']
 * @global array       $GLOBALS    ['permissions']
 * @global array       $GLOBALS    ['cache']['devices']
 * @global array       $GLOBALS    ['cache']['ports']
 * @global string      $GLOBALS    ['vars']['page']
 */
// TESTME needs unit testing
function generate_query_permitted_ng($type_array = [ 'device' ], $options = []) {
    if (!is_array($type_array)) {
        $type_array = [ $type_array ];
    }
    $user_limited = $_SESSION['userlevel'] < 5;
    $page         = $GLOBALS['vars']['page'];

    // If device IDs stored in SESSION use it (used in ajax)
    //if (!isset($GLOBALS['cache']['devices']) && isset($_SESSION['cache']['devices']))
    //{
    //  $GLOBALS['cache']['devices'] = $_SESSION['cache']['devices'];
    //}

    if (!mem_cache_exists('user_permissions')) {
        if (is_graph() || is_api()) {
            // Do not broke graph/api output
            print_debug("Function " . __FUNCTION__ . "() on page '$page' called before include cache-data.inc.php or something wrong with caching permissions.");
        } else {
            // Note, this function must be used after load permissions list!
            print_error("Function " . __FUNCTION__ . "() on page '$page' called before include cache-data.inc.php or something wrong with caching permissions.");
        }
        //permissions_cache($_SESSION['user_id']);
    }
    $permissions = $user_limited ? (array) mem_cache_get('user_permissions') : [];

    // Use option hide_disabled if passed or use config
    $options['hide_disabled'] = $options['hide_disabled'] ?? !$GLOBALS['config']['web_show_disabled'];

    //$query_permitted = '';
    $query_part = [];

    foreach ($type_array as $type) {
        switch ($type) {
            // Devices permission query
            case 'device':
            case 'devices':
                $column          = '`device_id`';
                $query_permitted = [];
                if (isset($options['device_table'])) {
                    $column = '`' . $options['device_table'] . '`.' . $column;
                }

                // Show only permitted devices
                if ($user_limited) {
                    if (!safe_empty($permissions['device'])) {
                        $query_permitted[] = generate_query_values(array_keys($permissions['device']), $column);
                    } else {
                        // Exclude all entries, because there are no permitted devices
                        $query_permitted[] = ' 0';
                    }
                }

                // Also don't show ignored and disabled devices (except on 'device' and 'devices' pages)
                $devices_excluded = [];
                if (!str_starts_with($page, 'device')) {
                    if ($options['hide_ignored'] && !safe_empty($GLOBALS['cache']['devices']['ignored'])) {
                        $devices_excluded = array_merge($devices_excluded, $GLOBALS['cache']['devices']['ignored']);
                    }
                    if ($options['hide_disabled'] && !safe_empty($GLOBALS['cache']['devices']['disabled'])) {
                        $devices_excluded = array_merge($devices_excluded, $GLOBALS['cache']['devices']['disabled']);
                    }
                }
                if (!safe_empty($devices_excluded)) {
                    //sort($devices_excluded, SORT_NUMERIC);
                    //r($devices_excluded);
                    // Set query with excluded devices
                    $query_permitted[] = generate_query_values($devices_excluded, $column, '!=');
                }

                // At the end excluded entries with empty/null device_id (wrong entries)
                //$query_permitted[] = " ($column != '' AND $column IS NOT NULL)";
                $query_permitted[] = " $column IS NOT NULL"; // Note: SELECT '' = 0; is TRUE
                $query_part[]      = implode(" AND ", $query_permitted);
                unset($query_permitted);
                break;

            // Ports permission query
            case 'port':
            case 'ports':
                $table = isset($options['port_table']) ? '`' . $options['port_table'] . '`.' : '';
                if (isset($options['entity'])) {
                    $query_permitted[] = generate_query_values('port', $table.'entity_type');
                    $column = $table.'`entity_id`';
                } else {
                    $column = $table.'`port_id`';
                }

                // If port IDs stored in SESSION use it (used in ajax)
                //if (!isset($GLOBALS['cache']['ports']) && isset($_SESSION['cache']['ports']))
                //{
                //  $GLOBALS['cache']['ports'] = $_SESSION['cache']['ports'];
                //}

                // Show only permitted ports
                if ($user_limited) {
                    if (!safe_empty($permissions['port'])) {
                        $query_permitted[] = generate_query_values(array_keys($permissions['port']), $column);
                        // $query_permitted[] = " $column IN (" .
                        //                      implode(',', array_keys($permissions['port'])) .
                        //                      ')';
                    } else {
                        // Exclude all entries, because there is no permitted ports
                        $query_permitted[] = '0';
                    }
                }

                $ports_excluded = [];
                // Don't show ports with disabled polling.
                if (!safe_empty($GLOBALS['cache']['ports']['poll_disabled'])) {
                    $ports_excluded = array_merge($ports_excluded, $GLOBALS['cache']['ports']['poll_disabled']);
                    //foreach ($GLOBALS['cache']['ports']['poll_disabled'] as $entry)
                    //{
                    //  $ports_excluded[] = $entry;
                    //}
                    //$ports_excluded = array_unique($ports_excluded);
                }
                // Don't show deleted ports (except on 'deleted-ports' page)
                if ($page !== 'deleted-ports' && !safe_empty($GLOBALS['cache']['ports']['deleted'])) {
                    $ports_excluded = array_merge($ports_excluded, $GLOBALS['cache']['ports']['deleted']);
                    //foreach ($GLOBALS['cache']['ports']['deleted'] as $entry)
                    //{
                    //  $ports_excluded[] = $entry;
                    //}
                    //$ports_excluded = array_unique($ports_excluded);
                }
                if ($page !== 'device' && !in_array('device', $type_array)) {
                    // Don't show ports for disabled devices (except on 'device' page or if 'device' permissions already queried)
                    if ($options['hide_disabled'] && !$user_limited && !safe_empty($GLOBALS['cache']['ports']['device_disabled'])) {
                        $ports_excluded = array_merge($ports_excluded, $GLOBALS['cache']['ports']['device_disabled']);
                        //foreach ($GLOBALS['cache']['ports']['device_disabled'] as $entry)
                        //{
                        //  $ports_excluded[] = $entry;
                        //}
                        //$ports_excluded = array_unique($ports_excluded);
                    }
                    // Don't show ports for ignored devices (except on 'device' page)
                    if ($options['hide_ignored'] && !safe_empty($GLOBALS['cache']['ports']['device_ignored'])) {
                        $ports_excluded = array_merge($ports_excluded, $GLOBALS['cache']['ports']['device_ignored']);
                        //foreach ($GLOBALS['cache']['ports']['device_ignored'] as $entry)
                        //{
                        //  $ports_excluded[] = $entry;
                        //}
                        //$ports_excluded = array_unique($ports_excluded);
                    }
                }
                // Don't show ignored ports (only on some pages!)
                if (($page === 'overview' || $options['hide_ignored']) && !safe_empty($GLOBALS['cache']['ports']['ignored'])) {
                    $ports_excluded = array_merge($ports_excluded, $GLOBALS['cache']['ports']['ignored']);
                    //foreach ($GLOBALS['cache']['ports']['ignored'] as $entry)
                    //{
                    //  $ports_excluded[] = $entry;
                    //}
                    //$ports_excluded = array_unique($ports_excluded);
                }
                unset($entry);
                if (!safe_empty($ports_excluded)) {
                    // Set query with excluded ports
                    $query_permitted[] = generate_query_values($ports_excluded, $column, '!=');

                }

                // At the end excluded entries with empty/null port_id (wrong entries)
                if (!isset($options['port_null']) || !$options['port_null']) {
                    //$query_permitted[] = "($column != '' AND $column IS NOT NULL)";
                    $query_permitted[] = "$column IS NOT NULL";
                } elseif (!$user_limited && safe_count($query_permitted)) {
                    // FIXME. derp code, need rewrite
                    //$query_permitted[] = safe_count($query_permitted) ? "OR $column IS NULL" : "$column IS NULL";
                    $query_permitted[] = "OR $column IS NULL";
                }
                $query_permitted = implode(" AND ", (array)$query_permitted);

                if (!safe_empty($query_permitted)) {
                    $query_part[] = str_replace(" AND OR ", ' OR ', $query_permitted);
                }
                unset($query_permitted);

                break;

            case 'sensor':
            case 'sensors':
                // For sensors
                // FIXME -- this is easily generifyable, just use translate_table_array()

                $table = isset($options['sensor_table']) ? '`' . $options['sensor_table'] . '`.' : '';
                if (isset($options['entity'])) {
                    $query_permitted[] = generate_query_values('sensor', $table.'entity_type');
                    $column = $table.'`entity_id`';
                } else {
                    $column = $table.'`sensor_id`';
                }

                // If IDs stored in SESSION use it (used in ajax)
                //if (!isset($GLOBALS['cache']['sensors']) && isset($_SESSION['cache']['sensors']))
                //{
                //  $GLOBALS['cache']['sensors'] = $_SESSION['cache']['sensors'];
                //}

                // Show only permitted entities
                if ($user_limited) {
                    if (!safe_empty($permissions['sensor'])) {
                        $query_permitted[] = generate_query_values(array_keys($permissions['sensor']), $column);
                    } else {
                        // Exclude all entries, because there are no permitted entities
                        $query_permitted[] = '0';
                    }
                    $query_permitted = implode(" AND ", (array)$query_permitted);

                    if (!safe_empty($query_permitted)) {
                        $query_part[] = str_replace(" AND OR ", ' OR ', $query_permitted);
                    }
                    unset($query_permitted);
                }

                break;

            case 'alert':
            case 'alerts':
                // For generic alert

                $column = '`alert_table_id`';

                // Show only permitted entities
                if ($user_limited) {
                    if (!safe_empty($permissions['alert'])) {
                        $query_permitted = generate_query_values(array_keys($permissions['alert']), $column);
                    } else {
                        // Exclude all entries, because there are no permitted entities
                        $query_permitted = '0';
                    }
                    $query_part[] = $query_permitted;
                    unset($query_permitted);
                }

                break;

            case 'bill':
            case 'bills':
                // For bills
                break;
        }
    }
    if (!safe_empty($query_part)) {
        //r($query_part);
        if ($user_limited) {
            // Limited user must use OR for include multiple entities
            $query_permitted = "((" . implode(") OR (", $query_part) . "))";
        } else {
            // Unlimited used must use AND for exclude multiple hidden entities
            $query_permitted = "((" . implode(") AND (", $query_part) . "))";
        }

        // Append leading AND if option requested
        if ($options['leading_and']) {
            $query_permitted = ' AND ' . $query_permitted;
        }
    }
    //r($query_permitted);

    return !safe_empty($query_permitted) ? $query_permitted . ' ' : '';
}

/**
 * Compat function for old usages, call to generate_query_permitted_ng()
 *
 * @param $type_array
 * @param $options
 *
 * @return string
 */
function generate_query_permitted($type_array = ['device'], $options = [])
{
    $options['leading_and'] = TRUE;
    return generate_query_permitted_ng($type_array, $options);
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function dashboard_exists($dash_id)
{
    return dbExist('dashboards', '`dash_id` = ?', [$dash_id]);
    //return count(dbFetchRow("SELECT * FROM `dashboards` WHERE `dash_id` = ?", array($dash_id)));
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function get_user_prefs($user_id)
{
    $prefs = [];
    foreach (dbFetchRows("SELECT * FROM `users_prefs` WHERE `user_id` = ?", [$user_id]) as $entry) {
        $prefs[$entry['pref']] = $entry;
    }

    return $prefs;
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function get_user_pref($user_id, $pref)
{
    if ($entry = dbFetchRow("SELECT `value` FROM `users_prefs` WHERE `user_id` = ? AND `pref` = ?", [$user_id, $pref])) {
        return $entry['value'];
    }

    return NULL;
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function set_user_pref($user_id, $pref, $value)
{
    //if (dbFetchCell("SELECT COUNT(*) FROM `users_prefs` WHERE `user_id` = ? AND `pref` = ?", array($user_id, $pref)))
    if (dbExist('users_prefs', '`user_id` = ? AND `pref` = ?', [$user_id, $pref])) {
        $id = dbUpdate(['value' => $value], 'users_prefs', '`user_id` = ? AND `pref` = ?', [$user_id, $pref]);
    } else {
        $id = dbInsert(['user_id' => $user_id, 'pref' => $pref, 'value' => $value], 'users_prefs');
    }

    return $id;
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function del_user_pref($user_id, $pref)
{
    return dbDelete('users_prefs', "`user_id` = ? AND `pref` = ?", [$user_id, $pref]);
}

/**
 * Load user-specific configuration settings and merge them with global configuration.
 *
 * This function fetches user-specific preferences from the database and merges
 * them with the global configuration. Only settings allowed by the configuration
 * definitions are considered. The resulting configuration is stored in the
 * $load_config parameter.
 *
 * @param array $load_config A reference to the array where the merged configuration should be stored.
 * @param int   $user_id     The ID of the user whose preferences should be loaded.
 *
 * @return void
 */
function load_user_config(&$load_config, $user_id)
{
    global $config;

    if (!$prefs = dbFetchRows("SELECT * FROM `users_prefs` WHERE `user_id` = ? AND `pref` NOT IN (?, ?)", [$user_id, 'atom_key', 'api_key'])) {
        // No user prefs set
        return FALSE;
    }

    // Always use global config here!
    include($config['install_dir'] . '/includes/config-variables.inc.php');

    foreach ($prefs as $item) {
        if (!isset($config_variable[$item['pref']]['useredit']) ||
            !$config_variable[$item['pref']]['useredit']) {
            // Load only permitted settings
            print_debug("User [$user_id] setting '{$item['pref']}' not permitted by definitions.");
            continue;
        }

        // Convert boo|bee|baa config value into $config['boo']['bee']['baa']
        $tree = explode('|', $item['pref']);

        set_nested_value($load_config, $tree, safe_unserialize($item['value']));
    }
}

/**
 * Set a value in a nested array, given a list of keys.
 *
 * This function sets a value in a nested array, creating intermediate arrays as
 * necessary. The keys are specified as an array, where each element represents a
 * level in the nested array. The value is set at the position specified by the
 * last key.
 *
 * @param array $array A reference to the array in which the value should be set.
 * @param array $keys  An array of keys specifying the position in the nested array.
 * @param mixed $value The value to set in the nested array.
 *
 * @return void
 */
function set_nested_value(&$array, $keys, $value)
{
    $last_key = array_pop($keys);
    foreach ($keys as $key) {
        if (!isset($array[$key]) || !is_array($array[$key])) {
            $array[$key] = [];
        }
        $array = &$array[$key];
    }
    $array[$last_key] = $value;
}

function process_sql_vars($vars)
{
    global $config;

    // Always use global config here!
    include($config['install_dir'] . '/includes/config-variables.inc.php');

    $deletes     = [];
    $sets        = [];
    $errors      = [];
    $set_attribs = []; // set obs_attribs

    // Submit button pressed
    foreach ($vars as $varname => $value) {
        if (str_starts_with($varname, 'varset_')) {
            $varname  = substr($varname, 7);
            $sqlname  = str_replace('__', '|', $varname);
            $sqlset   = get_var_true($value); // value sets in sql
            $content  = $vars[$varname];
            $confname = '$config[\'' . implode("']['", explode('|', $sqlname)) . '\']';
            $section  = $config_variable[$sqlname]['section'];

            if ($vars[$varname . '_custom']) {
                $ok = FALSE;

                if (isset($config_variable[$sqlname]['edition']) && $config_variable[$sqlname]['edition'] !== OBSERVIUM_EDITION) {
                    // Skip variables not allowed for current Observium edition
                    continue;
                }
                if (isset($config_sections[$section]['edition']) && $config_sections[$section]['edition'] !== OBSERVIUM_EDITION) {
                    // Skip sections not allowed for current Observium edition
                    continue;
                }

                $vartype = $config_variable[$sqlname]['type'];
                $params  = [];

                // If a callback function is defined, use this to fill params.
                if ($config_variable[$sqlname]['params_call'] && function_exists($config_variable[$sqlname]['params_call'])) {
                    $params = call_user_func($config_variable[$sqlname]['params_call']);
                    // Else if the params are defined directly, use these.
                } elseif (is_array($config_variable[$sqlname]['params'])) {
                    // Else if the params are defined directly, use these.
                    if (is_array_flat($config_variable[$sqlname]['params'])) {
                        // simple list convert to common params arrays
                        foreach ($config_variable[$sqlname]['params'] as $param) {
                            $params[$param] = [ 'name' => $param ];
                        }
                    } else {
                        $params = $config_variable[$sqlname]['params'];
                    }
                } elseif (str_contains($vartype, '|')) {
                    // CLEANME. Deperecated, use params definition
                    // Split enum|foo|bar into enum  foo|bar
                    $vartype = explode('|', $vartype, 2)[0];
                }

                switch ($vartype) {
                    case 'int':
                    case 'integer':
                    case 'float':
                        if (is_numeric($content)) {
                            $ok = TRUE;
                        } else {
                            $errors[] = $config_variable[$sqlname]['name'] . " ($confname) should be of <strong>numeric</strong> type. Setting '" . escape_html($content) . "' ignored.";
                        }
                        break;
                    case 'bool':
                    case 'boolean':
                        switch ($content) {
                            case 'on':
                            case '1':
                                $content = 1;
                                $ok      = TRUE;
                                break;
                            case 'off': // Won't actually happen. When "unchecked" the field is simply not transmitted...
                            case '0':
                            case '':    // ... which we catch here.
                                $content = 0;
                                $ok      = TRUE;
                                break;
                            default:
                                $ok       = FALSE;
                                $errors[] = $config_variable[$sqlname]['name'] . " ($confname) should be of type <strong>bool</strong>. Setting '" . escape_html($content) . "' ignored.";
                        }
                        break;
                    case 'enum':
                        if (!array_key_exists($content, $params)) {
                            $ok       = FALSE;
                            $errors[] = $config_variable[$sqlname]['name'] . " ($confname) should be one of <strong>" . implode(', ', $params) . "</strong>. Setting '" . escape_html($content) . "' ignored.";
                        } else {
                            $ok = TRUE;
                        }
                        break;
                    case 'enum-array':
                        //r($content);
                        //r($params);
                        foreach ($content as $value) {
                            // Check all values
                            if (!array_key_exists($value, $params)) {
                                $ok       = FALSE;
                                $errors[] = $config_variable[$sqlname]['name'] . " ($confname) all values should be one of this list <strong>" . implode(', ', $params) . "</strong>. Settings '" . implode(', ', $content) . "' ignored.";
                                break;
                            }
                            $ok = TRUE;
                        }
                        break;

                    case 'enum-list':
                        //r($content);
                        //r($params);
                        if (isset($content['value'])) {
                            $content = array_filter(array_unique($content['value']), static function ($value) { return !safe_empty($value); });
                            $ok = !safe_empty($content);
                            //r($content);
                        }
                        break;
                    case 'enum-key-value':
                        //r($content);
                        //r($params);
                        if (isset($content['key'], $content['value'])) {
                            $tmp     = $content;
                            $content = [];
                            foreach ($tmp['key'] as $i => $key) {
                                if (safe_empty($key) && safe_empty($tmp['value'][$i])) {
                                    // skip an empty key-value pair
                                    continue;
                                }
                                $content[$key] = $tmp['value'][$i];
                            }
                            $ok = !safe_empty($content);
                            //r($content);
                        }
                        break;
                    case 'enum-freeinput':
                        //r($content);
                        //r($params);
                        // FIXME, need validate values
                        if (is_null($content)) {
                            // Empty array allowed, for override defaults
                            $content = [];
                            $ok      = TRUE;
                        }
                        foreach ($content as $value) {
                            $ok = TRUE;
                        }
                        break;
                    case 'password':
                    case 'string':
                        $ok = TRUE;
                        break;
                    default:
                        $ok       = FALSE;
                        $errors[] = $config_variable[$sqlname]['name'] . " ($confname) is of unknown type (" . $config_variable[$sqlname]['type'] . ")";
                        break;
                }

                if ($ok) {
                    $sets[$sqlname] = $content;

                    // Set an obs_attrib, example for syslog trigger
                    //r($config_variable[$sqlname]);
                    if (isset($config_variable[$sqlname]['set_attrib']) && !safe_empty($config_variable[$sqlname]['set_attrib'])) {
                        $set_attribs[$config_variable[$sqlname]['set_attrib']] = get_time();
                    }
                }
            } elseif ($sqlset) {
                $deletes[] = $sqlname;

                // Set an obs_attrib, example for syslog trigger
                //r($config_variable[$sqlname]);
                if (isset($config_variable[$sqlname]['set_attrib']) && !safe_empty($config_variable[$sqlname]['set_attrib'])) {
                    $set_attribs[$config_variable[$sqlname]['set_attrib']] = get_time();
                }
            }
        }
    }

    return [ 'sets' => $sets, 'set_attribs' => $set_attribs, 'deletes' => $deletes, 'errors' => $errors ];
}

/**
 * Convert amqp|conn|host into returning value of $arrayvar['amqp']['conn']['host']
 *
 * @param string  $sqlname   Variable name
 * @param array   $arrayvar  Array where to see param
 * @param Boolean $try_isset If True, return isset($sqlname) check, else return variable content
 *
 * @return mixed
 */
function sql_to_array($sqlname, $arrayvar, $try_isset = TRUE)
{

    [$key, $pop_sqlname] = explode('|', $sqlname, 2);

    if (!is_array($arrayvar)) {
        return FALSE;
    }

    $isset = array_key_exists($key, $arrayvar);

    if (safe_empty($pop_sqlname)) {
        // Reached the variable, return its content, or FALSE if it's not set
        if ($try_isset) {
            return $isset;
        }
        return $isset ? $arrayvar[$key] : NULL;
    }

    if ($isset) {
        // Recurse to lower level
        return sql_to_array($pop_sqlname, $arrayvar[$key], $try_isset);
    }
    return FALSE;
}

/**
 * Darkens or lightens a colour
 * Found via http://codepad.org/MTGLWVd0
 *
 * First argument is the colour in hex, second argument is how dark it should be 1=same, 2=50%
 *
 * @param string $rgb
 * @param int    $darker
 *
 * @return string
 */
function darken_color($rgb, $darker = 2)
{
    if (strpos($rgb, '#') !== FALSE) {
        $hash = '#';
        $rgb  = str_replace('#', '', $rgb);
    } else {
        $hash = '';
    }
    $len = strlen($rgb);
    if ($len == 6) {
    } // Passed RGB
    elseif ($len == 8) {
        // Passed RGBA, remove alpha channel
        $rgb = substr($rgb, 0, 6);
    } else {
        $rgb = FALSE;
    }

    if ($rgb === FALSE) {
        return $hash . '000000';
    }

    $darker = ($darker > 1) ? $darker : 1;

    [$R16, $G16, $B16] = str_split($rgb, 2);

    $R = sprintf("%02X", floor(hexdec($R16) / $darker));
    $G = sprintf("%02X", floor(hexdec($G16) / $darker));
    $B = sprintf("%02X", floor(hexdec($B16) / $darker));

    return $hash . $R . $G . $B;
}

function json_output($status, $message)
{
    header("Content-type: application/json; charset=utf-8");
    echo safe_json_encode(["status" => $status, "message" => $message]);

    exit();
}

/**
 * Redirect to specified URL
 *
 * @param string $url Redirecting URL
 */
function redirect_to_url($url)
{
    if (safe_empty($url) || $url === '#') {
        return;
    } // Empty url, do not redirect

    $parse = parse_url($url);
    //r($url);
    if (!isset($parse['scheme']) && !str_starts($url, '/')) {
        // When this is not full url or not started with /
        $url = '/' . $url;
    }

    if (headers_sent()) {
        // HTML headers already sent, use JS than
        register_html_resource('script', "location.href='$url'");
    } else {
        // Just use headers
        header('Location: ' . $url);
    }
}

function generate_colour_gradient($start_colour, $end_colour, $steps)
{

    if ($steps < 4) {
        $steps = 4;
    }

    $FromRGB['r'] = hexdec(substr($start_colour, 0, 2));
    $FromRGB['g'] = hexdec(substr($start_colour, 2, 2));
    $FromRGB['b'] = hexdec(substr($start_colour, 4, 2));

    $ToRGB['r'] = hexdec(substr($end_colour, 0, 2));
    $ToRGB['g'] = hexdec(substr($end_colour, 2, 2));
    $ToRGB['b'] = hexdec(substr($end_colour, 4, 2));

    $StepRGB['r'] = ($FromRGB['r'] - $ToRGB['r']) / ($steps - 1);
    $StepRGB['g'] = ($FromRGB['g'] - $ToRGB['g']) / ($steps - 1);
    $StepRGB['b'] = ($FromRGB['b'] - $ToRGB['b']) / ($steps - 1);

    $GradientColors = [];
    $GradientColors[] = $start_colour; // Hack because array starts at 0, but we count from 1.

    for ($i = 0; $i < $steps; $i++) {
        $RGB['r'] = floor($FromRGB['r'] - ($StepRGB['r'] * $i));
        $RGB['g'] = floor($FromRGB['g'] - ($StepRGB['g'] * $i));
        $RGB['b'] = floor($FromRGB['b'] - ($StepRGB['b'] * $i));

        $HexRGB['r'] = sprintf('%02x', ($RGB['r']));
        $HexRGB['g'] = sprintf('%02x', ($RGB['g']));
        $HexRGB['b'] = sprintf('%02x', ($RGB['b']));

        $GradientColors[] = implode(NULL, $HexRGB);
    }
    $GradientColors = array_filter($GradientColors, "c_len");

    unset($GradientColors[0]); // Remove the placeholder array position 0 because it's not used.
    //r($GradientColors);

    return $GradientColors;
}

function c_len($val)
{
    return strlen($val) === 6;
}

function adjust_colour_brightness($hex, $steps)
{
    // Steps should be between -255 and 255. Negative = darker, positive = lighter
    $steps = max(-255, min(255, $steps));

    // Normalize into a six character long hex string
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) === 3) {
        $hex = str_repeat($hex[0], 2) . str_repeat($hex[1], 2) . str_repeat($hex[2], 2);
    }

    // Split into three parts: R, G and B
    $color_parts = str_split($hex, 2);

    $return = '';
    foreach ($color_parts as $color) {
        $color  = hexdec($color);                                 // Convert to decimal
        $color  = max(0, min(255, $color + $steps));              // Adjust color
        $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT); // Make two char hex code
    }

    return $return;
}

/**
 * Highlight (or replace with entity links) part of the string.
 *
 * @param string $text    Text where need to highlight a search string
 * @param array  $search  Search array. Can be string, simple array or array with 'search', 'replace' pairs
 * @param string $replace Default is just
 * @param bool   $escape  Escape not entity part of text
 *
 * @return string
 */
function html_highlight_entities($text, $search = [], $replace = '', $escape = TRUE) {

    if (empty($search)) {
        return $escape ? escape_html($text) : $text;
    }

    if (empty($replace)) {
        // Default is highlight as danger class
        $replace = '<em class="text-danger">$2</em>';
    }

    $tags = [];
    foreach ((array)$search as $index => $entry) {

        if (!isset($entry['pattern'])) {
            if (!isset($entry['search'])) {
                continue;
            }
            // generate a pattern from a search array
            $entry['search'] = array_map(function ($p) {
                // allow limited regex patterns in search strings (currently only for interfaces links)
                return str_replace('\\\\d\+', '\d+', preg_quote($p, '%'));
            }, $entry['search']);
            $entry['pattern'] = '(' . implode('|', $entry['search']) . ')';
        }
        $pattern = OBS_PATTERN_START_S . $entry['pattern'] . OBS_PATTERN_END_S;
        $entry_replace = $entry['replace'] ?? $replace;

        if (preg_match($pattern, $text, $matches)) {
            $entity = $escape ? escape_html($matches[2]) : $matches[2];
            $tags['ENTITY_REPLACE_'.$index] = str_replace('$2', $entity, $entry_replace);

            $match1 = $matches[1] ?? '';
            $match3 = $matches[3] ?? '';

            $text = str_replace($match1.$matches[2].$match3, $match1.'%ENTITY_REPLACE_'.$index.'%'.$match3, $text); // use tagged replace
        }
    }

    if ($escape) {
        $text = escape_html($text);
    }

    if (empty($tags)) {
        // replace strings not found, tags not required
        return $text;
    }

    return array_tag_replace($tags, $text);
}

/**
 * Highlight part of string.
 *
 * @param string        $text    Text where need highlight search string
 * @param string|array  $search  Search string or array with string.
 * @param string        $replace Default is danger text class
 * @param bool          $escape  Escape not search part of text
 *
 * @return string
 */
function html_highlight($text, $search = [], $replace = '', $escape = TRUE) {

    if (empty($search)) {
        return $text;
    }

    if (is_array($search)) {
        $search_escaped = array_map(function ($entry) {
            return preg_quote($entry, '%');
        }, $search);
        $pattern = implode('|', $search_escaped);
    } else {
        $pattern = preg_quote($search, '%');
    }

    if (empty($replace)) {
        // Default is highlight as danger class
        $replace = '<em class="text-danger">$0</em>';
    }

    $tags = [];
    if (preg_match_all('%' . $pattern . '%i', $text, $matches)) {
        //r($matches);
        foreach (array_unique($matches[0]) as $index => $match) {
            $string  = $escape ? escape_html($match) : $match;
            $text    = str_replace($match, '%HIGHLIGHT_REPLACE_'.$index.'%', $text); // use tagged replace

            $tags['HIGHLIGHT_REPLACE_'.$index] = str_replace('$0', $string, $replace);
        }
    }

    if ($escape) {
        $text = escape_html($text);
    }

    if (empty($tags)) {
        // replace strings not found, tags not required
        return $text;
    }

    return array_tag_replace($tags, $text);
}

/**
 * Silly class to assign and remember a unique class for a type.
 *
 * @param string $type
 * @param string $group
 *
 * @return string
 */
function get_type_class($type, $group = "unknown") {
    global $cache;

    // Short-circuit if hardcoded classes exist for this
    if (isset($GLOBALS['config']['type_class'][$group][$type]['class'])) {
        return $GLOBALS['config']['type_class'][$group][$type]['class'];
    }

    if (isset($cache['type_class'][$group][$type])) {
        return $cache['type_class'][$group][$type]['class'];
    }

    // all known label classes
    // $classes = [ 'default', 'primary', 'success', 'info', 'warning', 'important',
    //              'error', 'danger', 'suppressed', 'delayed', 'inverse', 'rainbow' ];

    // available label classes for cycling
    $classes = [ 'primary', 'success', 'info', 'warning', 'important', 'suppressed', 'default' ];

    $next = $cache['type_class'][$group]['_NEXT_'] ?? 0;

    $cache['type_class'][$group][$type]['class'] = $classes[$next];

    if (isset($classes[$next + 1])) {
        $next++;
    } else {
        $next = 0;
    }
    $cache['type_class'][$group]['_NEXT_'] = $next;

    return $cache['type_class'][$group][$type]['class'];
}

/**
 * Silly class to return a label using persistent class for a certain string/type within a given group
 *
 * @param string $type
 * @param string $group
 *
 * @return string
 */
function get_type_class_label($type, $group = "unknown") {
    return '<span class="label label-' . get_type_class($type, $group) . '">' . $type . '</span>';
}

/**
 * Get a value from the array by traversing the keys.
 *
 * @param array $array The array to get the value from.
 * @param array $keys  An array of keys to traverse.
 *
 * @return mixed|null The value at the specified keys or null if not found.
 */
function get_value_by_keys(&$array, $keys)
{
    $key = array_shift($keys);
    if (empty($keys)) {
        return $array[$key] ?? NULL;
    }
    return isset($array[$key]) ? get_value_by_keys($array[$key], $keys) : NULL;
}

/**
 * Set a value in the array by traversing the keys.
 *
 * @param array $array The array to set the value in.
 * @param array $keys  An array of keys to traverse.
 * @param mixed $value The value to set.
 */
function set_value_by_keys(&$array, $keys, $value)
{
    $key = array_shift($keys);
    if (empty($keys)) {
        $array[$key] = $value;
    } else {
        if (!isset($array[$key])) {
            $array[$key] = [];
        }
        set_value_by_keys($array[$key], $keys, $value);
    }
}

/**
 * Unset a value in the array by traversing the keys.
 *
 * @param array $array The array to unset the value in.
 * @param array $keys  An array of keys to traverse.
 */
function unset_value_by_keys(&$array, $keys)
{
    $key = array_shift($keys);
    if (empty($keys)) {
        unset($array[$key]);
    } else {
        if (isset($array[$key])) {
            unset_value_by_keys($array[$key], $keys);
        }
    }
}

/**
 * Generate a responsive status panel with overview metrics
 *
 * Creates a horizontal grid of status boxes, each containing:
 * - Title (bold header)
 * - Value (main content - string, number, label array, or HTML)
 * - Subtitle (small description)
 *
 * Responsive grid adjusts column count based on number of boxes:
 * - 1-4 boxes: 3 columns each (col-md-3)
 * - 5-6 boxes: 2 columns each (col-md-2)
 * - 7+ boxes: 1 column each (col-md-1)
 *
 * Value formats:
 * - String/number: HTML-escaped text
 * - ['text' => 'Label', 'class' => 'label-success']: Bootstrap label
 * - ['html' => '<strong>HTML</strong>']: Raw HTML (for device links)
 *
 * @param array $boxes Array of status boxes
 * @param array $options Optional container settings (title, class, style)
 * @return string HTML for complete status panel
 *
 * Example:
 * generate_status_panel([
 *   ['title' => 'Root Bridge', 'value' => 'sw-core-1', 'subtitle' => 'Network Root'],
 *   ['title' => 'Protocol', 'value' => ['text' => 'RSTP', 'class' => 'label-info'], 'subtitle' => 'IEEE 802.1w'],
 *   ['title' => 'Status', 'value' => ['html' => generate_device_link($device)], 'subtitle' => 'Device Link'],
 *   ['title' => 'Health', 'value' => 'Good', 'subtitle' => 'Score: 95/100']
 * ]);
 */
function generate_status_panel($boxes, $options = []) {
    if (empty($boxes) || !is_array($boxes)) {
        return '';
    }

    $box_count = count($boxes);

    $panel_title = $options['title'] ?? '';
    $panel_style = $options['style'] ?? '';
    $panel_class = $options['class'] ?? 'well well-sm';

    $html = '<div class="row"><div class="col-md-12">';
    if ($panel_title) {
        $html .= generate_box_open(['title' => $panel_title]);
    } else {
        $html .= '<div class="' . $panel_class . '"' . ($panel_style ? ' style="' . $panel_style . '"' : '') . '>';
    }

    // Special 8-box layout: 2 rows of 4 boxes each (7-8 boxes)
    if ($box_count >= 7 && $box_count <= 8) {
        $html .= '<div class="row">';
        // First row of 4 boxes
        $html .= '<div class="col-md-6"><div class="row text-center">';
        for ($i = 0; $i < 4 && $i < $box_count; $i++) {
            $box = $boxes[$i];
            if (is_callable($box['value'])) {
                $box['value'] = $box['value']();
            }
            $html .= '<div class="col-md-3">';
            if (!empty($box['title'])) {
                $html .= '<strong>' . htmlentities($box['title']) . '</strong><br>';
            }
            if (array_key_exists('value', $box)) {
                $html .= generate_status_value($box['value']);
            }
            if (!empty($box['subtitle'])) {
                $html .= '<br><small class="text-muted">' . htmlentities($box['subtitle']) . '</small>';
            }
            $html .= '</div>';
        }
        $html .= '</div></div>';

        // Second row of remaining boxes
        $html .= '<div class="col-md-6"><div class="row text-center">';
        for ($i = 4; $i < $box_count; $i++) {
            $box = $boxes[$i];
            if (is_callable($box['value'])) {
                $box['value'] = $box['value']();
            }
            $html .= '<div class="col-md-3">';
            if (!empty($box['title'])) {
                $html .= '<strong>' . htmlentities($box['title']) . '</strong><br>';
            }
            if (array_key_exists('value', $box)) {
                $html .= generate_status_value($box['value']);
            }
            if (!empty($box['subtitle'])) {
                $html .= '<br><small class="text-muted">' . htmlentities($box['subtitle']) . '</small>';
            }
            $html .= '</div>';
        }
        $html .= '</div></div>';
        $html .= '</div>';
    } else {
        // Standard single-row layout for 1-6 boxes
        if ($box_count <= 3) {
            $col_class = 'col-md-4';
        } elseif ($box_count <= 4) {
            $col_class = 'col-md-3';
        } elseif ($box_count <= 6) {
            $col_class = 'col-md-2';
        } else {
            $col_class = 'col-md-' . max(1, floor(12 / $box_count));
        }

        $html .= '<div class="row text-center">';
        foreach ($boxes as $box) {
            // Support callable values for dynamic content
            if (is_callable($box['value'])) {
                $box['value'] = $box['value']();
            }

            $html .= '<div class="' . $col_class . '">';
            if (!empty($box['title'])) {
                $html .= '<strong>' . htmlentities($box['title']) . '</strong><br>';
            }
            if (array_key_exists('value', $box)) {
                $html .= generate_status_value($box['value']);
            }
            if (!empty($box['subtitle'])) {
                $html .= '<br><small class="text-muted">' . htmlentities($box['subtitle']) . '</small>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
    }

    $html .= $panel_title ? generate_box_close() : '</div>';
    $html .= '</div></div>';
    return $html;
}

/**
 * Format status value: string, single label, list of labels, or raw HTML
 *
 * Supported formats:
 * - string/number: HTML-escaped text
 * - ['text' => 'Label Text', 'class' => 'label-info']: Bootstrap label
 * - ['html' => '<a href="...">Link</a>']: Raw HTML (use with caution)
 * - [label1, label2, ...]: Array of labels
 */
function generate_status_value($value) {
    if (is_string($value) || is_numeric($value)) {
        return htmlentities((string)$value);
    }
    if (is_array($value)) {
        if (isset($value['html'])) {
            // Allow raw HTML content (use with caution - ensure content is safe)
            return (string)$value['html'];
        }
        if (isset($value['text']) && isset($value['class'])) {
            return '<span class="label ' . htmlentities($value['class']) . '">' . htmlentities($value['text']) . '</span>';
        }
        $labels = [];
        foreach ($value as $label) {
            if (is_array($label) && isset($label['text']) && isset($label['class'])) {
                $labels[] = '<span class="label ' . htmlentities($label['class']) . '">' . htmlentities($label['text']) . '</span>';
            } else {
                $labels[] = htmlentities((string)$label);
            }
        }
        return implode(' ', $labels);
    }
    return htmlentities((string)$value);
}

/**
 * Generate form values for poller entity type
 *
 * @param mixed $form_filter Filter array or FALSE
 * @param string $column Column name to use (default: poller_id)
 * @param array $options Additional options (filter_mode, show_icon, etc)
 *
 * @return array Array of form values
 */
function generate_poller_form_values($form_filter = FALSE, $column = 'poller_id', $options = [])
{
    if (!is_array($form_filter)) {
        $options['filter_mode'] = FALSE;
    }

    $form_items = [];
    $query = 'SELECT `poller_id`, `poller_name` FROM `pollers` ORDER BY `poller_name`';

    foreach (dbFetchRows($query) as $entry) {
        // Filter items based on filter_mode
        if ($options['filter_mode'] === 'include') {
            if (!in_array($entry['poller_id'], $form_filter)) {
                continue;
            }
        } elseif ($options['filter_mode'] === 'exclude') {
            if (in_array($entry['poller_id'], $form_filter)) {
                continue;
            }
        }

        $name = $entry['poller_name'] . ' (ID: ' . $entry['poller_id'] . ')';
        $form_items[$entry['poller_id']] = $name;
    }

    return $form_items;
}

// EOF
