<?php
// Sensible defaults
$mapdir             = 'configs';
$observium_base     = '../../';
$observium_url      = '/';
$ignore_observium   = FALSE;
$config['base_url'] = $observium_url;

include_once("../includes/observium.inc.php");

include($config['html_dir'] . "/includes/authenticate.inc.php");

// Don't run if weathermap isn't enabled
if (!$config['weathermap']['enable'] || $_SESSION['userlevel'] < 7) {
    die();
}

$config['base_url'] = $config['url_path'] ?? $observium_url;
$observium_found    = TRUE;

// ******************************************

// Improved escaping for JS - use json_encode where possible

$command = filter_input(INPUT_GET, 'command', FILTER_SANITIZE_STRING) ?? '';

if ($command === 'ajax_get_ports') {
    $host_id = filter_input(INPUT_GET, 'host_id', FILTER_VALIDATE_INT) ?? -1;
    $filterstring = trim(filter_input(INPUT_GET, 'filterstring', FILTER_SANITIZE_STRING) ?? '');
    $ignore_desc = filter_input(INPUT_GET, 'ignore_desc', FILTER_VALIDATE_BOOLEAN) ?? false;
    $ignore_zero = filter_input(INPUT_GET, 'ignore_zero', FILTER_VALIDATE_BOOLEAN) ?? false;

    $query = "SELECT d.device_id, d.hostname, p.port_id, p.port_label, 
              p.ifInOctets_rate, p.ifOutOctets_rate 
              FROM devices AS d 
              LEFT JOIN ports AS p ON d.device_id = p.device_id 
              WHERE p.disabled = 0";
    $params = [];

    if ($host_id > 0) {
        $query .= " AND d.device_id = ?";
        $params[] = $host_id;
    }

    if ($filterstring !== '') {
        $like = '%' . $filterstring . '%';
        $query .= " AND p.port_label LIKE ?";
        $params[] = $like;
    }

    if ($ignore_desc) {
        $query .= " AND p.port_label != ''";
    }

    if ($ignore_zero) {
        $query .= " AND (p.ifInOctets_rate > 0 OR p.ifOutOctets_rate > 0)";
    }

    $query .= " ORDER BY d.hostname, p.port_label";

    $ports = dbFetchRows($query, $params);

    header('Content-Type: application/json');
    echo json_encode($ports);
    exit;
}

if ($command === 'ajax_get_devices') {
    $filterstring = trim(filter_input(INPUT_GET, 'filterstring', FILTER_SANITIZE_STRING) ?? '');

    $query = "SELECT device_id AS id, hostname AS name FROM devices";
    $params = [];

    if ($filterstring !== '') {
        $like = '%' . $filterstring . '%';
        $query .= " WHERE hostname LIKE ?";
        $params[] = $like;
    }

    $query .= " ORDER BY hostname";

    $devices = dbFetchRows($query, $params);

    header('Content-Type: application/json');
    echo json_encode($devices);
    exit;
}

if ($command === 'link_step2') {
    // Assuming this is for device-level, but fixing variables
    $dataid = filter_input(INPUT_GET, 'dataid', FILTER_VALIDATE_INT) ?? 0;
    if ($dataid <= 0) {
        die('Invalid dataid');
    }
    ?>
    <html>
    <head>
        <script type="text/javascript">
            function update_source_step2(graphid) {
                const base_url = '<?php echo htmlspecialchars($config['base_url'] ?? ''); ?>';
                if (typeof window.opener === "object") {
                    const graph_url = base_url + 'graph.php?height=100&width=512&device=' + graphid + '&type=device_bits&legend=no';
                    const info_url = base_url + 'device/device=' + graphid + '/';
                    opener.document.forms["frmMain"].link_infourl.value = info_url;
                    opener.document.forms["frmMain"].link_hover.value = graph_url;
                }
                self.close();
            }
            window.onload = () => update_source_step2(<?php echo $dataid; ?>);
        </script>
    </head>
    <body>
    This window should disappear in a moment.
    </body>
    </html>
    <?php
    exit;
}

if ($command === 'link_step1') {
    $host_id = filter_input(INPUT_GET, 'host_id', FILTER_VALIDATE_INT) ?? -1;
    $overlib = filter_input(INPUT_GET, 'overlib', FILTER_VALIDATE_BOOLEAN) ?? true;
    $aggregate = filter_input(INPUT_GET, 'aggregate', FILTER_VALIDATE_BOOLEAN) ?? false;

    $hosts = dbFetchRows("SELECT device_id, hostname FROM devices ORDER BY hostname");
    ?>
    <html>
    <head>
        <title>Pick an Observium port</title>
        <style type="text/css">
            body { font-family: sans-serif; font-size: 10pt; margin: 10px; }
            form { margin-bottom: 10px; }
            .listcontainer { max-height: 400px; overflow-y: auto; border: 1px solid #ccc; border-radius: 4px; }
            ul { list-style: none; margin: 0; padding: 0; }
            ul li { padding: 8px; border-bottom: 1px solid #eee; cursor: pointer; display: flex; justify-content: space-between; }
            ul li:hover { background-color: #f0f0f0; }
            ul li.row0 { background: #f8f8f8; }
            ul li.row1 { background: #ffffff; }
            label { cursor: pointer; }
            input[type="text"] { width: 200px; padding: 4px; }
            select { padding: 4px; }
            .rates { color: gray; font-size: smaller; }
        </style>
        <script type="text/javascript">
            function escapeHtml(unsafe) {
                return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
            }

            function formatBytes(bits) {
                if (bits === 0) return '0 bps';
                const k = 1000;
                const sizes = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
                const i = Math.floor(Math.log(bits) / Math.log(k));
                return parseFloat((bits / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }

            let debounceTimer;
            function debounce(func, delay) {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(func, delay);
            }

            function updateList() {
                const hostId = document.getElementById('host_id').value;
                const filterString = document.getElementById('filterstring').value.toLowerCase(); // Case-insensitive
                const ignoreDesc = document.getElementById('ignore_desc').checked ? 1 : 0;
                const ignoreZero = document.getElementById('ignore_zero').checked ? 1 : 0;
                const ul = document.getElementById('dslist');
                ul.innerHTML = '<li>Loading...</li>';

                const params = new URLSearchParams({
                    command: 'ajax_get_ports',
                    host_id: hostId,
                    filterstring: filterString,
                    ignore_desc: ignoreDesc,
                    ignore_zero: ignoreZero
                });

                fetch('data-pick.php?' + params) // Assume filename is data-pick.php
                    .then(response => response.json())
                    .then(ports => {
                        ul.innerHTML = '';
                        if (ports.length === 0) {
                            ul.innerHTML = '<li>No results found.</li>';
                            return;
                        }
                        ports.forEach((port, i) => {
                            const li = document.createElement('li');
                            li.className = 'row' + (i % 2);
                            li.innerHTML = `
                                <a href="#" onclick="update_source_step1(${port.port_id}); return false;">
                                    <span style="color:darkred">${escapeHtml(port.hostname)}</span> <b>|</b> ${escapeHtml(port.port_label)}
                                </a>
                                <span class="rates">In: ${formatBytes(port.ifInOctets_rate * 8)} Out: ${formatBytes(port.ifOutOctets_rate * 8)}</span>
                            `;
                            ul.appendChild(li);
                        });
                    })
                    .catch(error => {
                        ul.innerHTML = '<li>Error loading ports.</li>';
                        console.error(error);
                    });
            }

            function update_source_step1(port_id) {
                const fullpath = 'obs_port:' + port_id;
                if (typeof window.opener === "object") {
                    const targetField = opener.document.forms["frmMain"].link_target;
                    if (document.getElementById('aggregate').checked) {
                        targetField.value += (targetField.value ? ' ' : '') + fullpath;
                    } else {
                        targetField.value = fullpath;
                    }
                }
                if (document.getElementById('overlib').checked) {
                    update_source_step2(port_id);
                } else {
                    self.close();
                }
            }

            function update_source_step2(port_id) {
                const base_url = '<?php echo htmlspecialchars($config['base_url'] ?? ''); ?>';
                if (typeof window.opener === "object") {
                    const graph_url = base_url + 'graph.php?height=100&width=512&id=' + port_id + '&type=port_bits&legend=no';
                    const info_url = base_url + 'graphs/type=port_bits/id=' + port_id + '/';
                    opener.document.forms["frmMain"].link_infourl.value = info_url;
                    opener.document.forms["frmMain"].link_hover.value = graph_url;
                }
                self.close();
            }

            window.addEventListener('load', updateList);
        </script>
    </head>
    <body>
    <h3>Pick an Observium port:</h3>
    <form name="mini" onsubmit="return false;">
        Host: <select id="host_id" onchange="updateList()">
            <option value="-1" <?php echo $host_id === -1 ? 'selected' : ''; ?>>Any</option>
            <option value="0" <?php echo $host_id === 0 ? 'selected' : ''; ?>>None</option>
            <?php foreach ($hosts as $host): ?>
                <option value="<?php echo $host['device_id']; ?>" <?php echo $host_id === (int)$host['device_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($host['hostname']); ?></option>
            <?php endforeach; ?>
        </select><br>
        Filter: <input id="filterstring" size="20" onkeyup="debounce(updateList, 300)" placeholder="Case-insensitive search"><br>
        <input id="overlib" type="checkbox" <?php echo $overlib ? 'checked' : ''; ?>> <label for="overlib">Also set OVERLIBGRAPH and INFOURL.</label><br>
        <input id="aggregate" type="checkbox" <?php echo $aggregate ? 'checked' : ''; ?>> <label for="aggregate">Append TARGET to existing one (Aggregate)</label><br>
        <input id="ignore_desc" type="checkbox" onchange="updateList()"> <label for="ignore_desc">Ignore ports with blank labels</label><br>
        <input id="ignore_zero" type="checkbox" onchange="updateList()"> <label for="ignore_zero">Ignore ports with zero traffic</label>
    </form>
    <div class="listcontainer">
        <ul id="dslist"></ul>
    </div>
    </body>
    </html>
    <?php
    exit;
}

if ($command === 'node_step1') {
    $host_id = filter_input(INPUT_GET, 'host_id', FILTER_VALIDATE_INT) ?? -1;
    $overlib = filter_input(INPUT_GET, 'overlib', FILTER_VALIDATE_BOOLEAN) ?? true;

    $hosts = dbFetchRows("SELECT device_id AS id, hostname AS name FROM devices ORDER BY hostname");
    ?>
    <html>
    <head>
        <title>Pick a device</title>
        <style type="text/css">
            body { font-family: sans-serif; font-size: 10pt; margin: 10px; }
            form { margin-bottom: 10px; }
            .listcontainer { max-height: 400px; overflow-y: auto; border: 1px solid #ccc; border-radius: 4px; }
            ul { list-style: none; margin: 0; padding: 0; }
            ul li { padding: 8px; border-bottom: 1px solid #eee; cursor: pointer; }
            ul li:hover { background-color: #f0f0f0; }
            ul li.row0 { background: #f8f8f8; }
            ul li.row1 { background: #ffffff; }
            label { cursor: pointer; }
            input[type="text"] { width: 200px; padding: 4px; }
            select { padding: 4px; }
        </style>
        <script type="text/javascript">
            function escapeHtml(unsafe) {
                return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
            }

            let debounceTimer;
            function debounce(func, delay) {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(func, delay);
            }

            function updateList() {
                const filterString = document.getElementById('filterstring').value.toLowerCase();
                const ul = document.getElementById('dslist');
                ul.innerHTML = '<li>Loading...</li>';

                const params = new URLSearchParams({
                    command: 'ajax_get_devices',
                    filterstring: filterString
                });

                fetch('data-pick.php?' + params)
                    .then(response => response.json())
                    .then(devices => {
                        ul.innerHTML = '';
                        if (devices.length === 0) {
                            ul.innerHTML = '<li>No results found.</li>';
                            return;
                        }
                        devices.forEach((device, i) => {
                            const li = document.createElement('li');
                            li.className = 'row' + (i % 2);
                            li.innerHTML = `
                                <a href="#" onclick="update_source_step1(${device.id}, '${escapeHtml(device.name)}'); return false;">
                                    ${escapeHtml(device.name)}
                                </a>
                            `;
                            ul.appendChild(li);
                        });
                    })
                    .catch(error => {
                        ul.innerHTML = '<li>Error loading devices.</li>';
                        console.error(error);
                    });
            }

            function update_source_step1(graphid, name) {
                const base_url = '<?php echo htmlspecialchars($config['base_url'] ?? ''); ?>';
                if (typeof window.opener === "object") {
                    const graph_url = base_url + 'graph.php?height=100&width=512&device=' + graphid + '&type=device_bits&legend=no';
                    const info_url = base_url + 'device/device=' + graphid + '/';
                    opener.document.forms["frmMain"].node_hover.value = graph_url;
                    opener.document.forms["frmMain"].node_new_name.value = name;
                    opener.document.forms["frmMain"].node_label.value = name;
                    if (document.getElementById('overlib').checked) {
                        opener.document.forms["frmMain"].node_infourl.value = info_url;
                    }
                }
                self.close();
            }

            window.addEventListener('load', updateList);
        </script>
    </head>
    <body>
    <h3>Pick a device:</h3>
    <form name="mini" onsubmit="return false;">
        Filter: <input id="filterstring" size="20" onkeyup="debounce(updateList, 300)" placeholder="Case-insensitive search"><br>
        <input id="overlib" type="checkbox" <?php echo $overlib ? 'checked' : ''; ?>> <label for="overlib">Set both OVERLIBGRAPH and INFOURL.</label><br>
    </form>
    <div class="listcontainer">
        <ul id="dslist"></ul>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// If no valid command, perhaps show error or default
die('Invalid command');