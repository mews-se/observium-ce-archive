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

register_html_title("Map");

// Register JavaScript resources
register_html_resource('js', 'cytoscape.min.js');
register_html_resource('js', 'dagre.min.js');
register_html_resource('js', 'cytoscape-dagre.min.js');

?>

<style>
#cy {
    width: 100%;
    height: 1200px;
    border: 1px solid #ddd;
    background-color: #fafafa;
}
#cy-controls {
    margin-bottom: 10px;
    padding: 10px;
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 4px;
}
#cy-controls button {
    margin-right: 5px;
    padding: 5px 12px;
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 3px;
    cursor: pointer;
}
#cy-controls button:hover {
    background: #e6e6e6;
}
#cy-controls input[type="range"] {
    vertical-align: middle;
    width: 150px;
}
#cy-controls label {
    margin-left: 10px;
    margin-right: 5px;
}
#cy-legend {
    margin-top: 10px;
    padding: 10px;
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 12px;
}
#cy-legend .legend-item {
    display: inline-block;
    margin-right: 20px;
}
#cy-legend .legend-color {
    display: inline-block;
    width: 20px;
    height: 3px;
    margin-right: 5px;
    vertical-align: middle;
}
</style>

<div id="cy-controls">
    <button id="fit-btn">Fit to Screen</button>
    <button id="center-btn">Center</button>
    <button id="zoom-reset-btn">Reset Zoom (1:1)</button>
    <label for="zoom-slider">Zoom:</label>
    <input type="range" id="zoom-slider" min="0.1" max="3" step="0.1" value="1">
    <span id="zoom-level">100%</span>
    <span style="margin-left: 20px; border-left: 1px solid #ccc; padding-left: 10px;">
        <button id="export-png-btn">Export PNG</button>
        <button id="export-svg-btn">Export SVG</button>
    </span>
</div>

<div id="cy"></div>

<div id="cy-legend">
    <strong>Link Speeds:</strong>
    <span class="legend-item"><span class="legend-color" style="background: #dc143c; height: 4px;"></span> 10G+</span>
    <span class="legend-item"><span class="legend-color" style="background: #4169e1; height: 3px;"></span> 1G</span>
    <span class="legend-item"><span class="legend-color" style="background: #32cd32; height: 2px;"></span> 100M</span>
    <span class="legend-item"><span class="legend-color" style="background: #999; height: 1px;"></span> &lt;100M</span>
    <strong style="margin-left: 20px;">Nodes:</strong>
    <span class="legend-item"><span class="legend-color" style="background: #add8e6; height: 10px; width: 40px;"></span> Device</span>
    <span class="legend-item"><span class="legend-color" style="background: #90ee90; height: 8px; width: 30px; border-radius: 50%;"></span> Port (Up)</span>
    <span class="legend-item"><span class="legend-color" style="background: #ffb6c1; height: 8px; width: 30px; border-radius: 50%;"></span> Port (Down)</span>
</div>

<?php

// Register inline script for map initialization
$map_script = <<<'JAVASCRIPT'
(function() {
    // Register dagre layout
    if (typeof cytoscape !== 'undefined' && typeof dagre !== 'undefined') {
        cytoscape.use(cytoscapeDagre);
    }

    // Fetch network map data
    fetch('/api-networkmap.php?device=__DEVICE_ID__')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('cy').innerHTML = '<div style="padding: 20px; text-align: center;">Error: ' + data.error + '</div>';
                return;
            }

            // Initialize Cytoscape
            var cy = cytoscape({
                container: document.getElementById('cy'),
                elements: data.elements,
                style: [
                    // Device nodes (compound parent nodes)
                    {
                        selector: 'node.device',
                        style: {
                            'label': 'data(label)',
                            'shape': 'round-rectangle',
                            'background-color': '#add8e6',
                            'border-width': 2,
                            'border-color': '#4682b4',
                            'text-valign': 'top',
                            'text-halign': 'center',
                            'text-margin-y': -5,
                            'font-size': 16,
                            'font-weight': 'bold',
                            'color': '#000',
                            'padding': '15px'
                        }
                    },
                    // Port nodes (up)
                    {
                        selector: 'node.port-up',
                        style: {
                            'label': 'data(label)',
                            'shape': 'ellipse',
                            'width': 60,
                            'height': 30,
                            'background-color': '#90ee90',
                            'border-width': 1,
                            'border-color': '#228b22',
                            'text-valign': 'center',
                            'text-halign': 'center',
                            'font-size': 11,
                            'color': '#000'
                        }
                    },
                    // Port nodes (down)
                    {
                        selector: 'node.port-down',
                        style: {
                            'label': 'data(label)',
                            'shape': 'ellipse',
                            'width': 60,
                            'height': 30,
                            'background-color': '#ffb6c1',
                            'border-width': 1,
                            'border-color': '#dc143c',
                            'text-valign': 'center',
                            'text-halign': 'center',
                            'font-size': 11,
                            'color': '#000'
                        }
                    },
                    // External device nodes
                    {
                        selector: 'node.external-device',
                        style: {
                            'label': 'data(label)',
                            'shape': 'round-rectangle',
                            'background-color': '#d3d3d3',
                            'border-width': 2,
                            'border-color': '#808080',
                            'border-style': 'dashed',
                            'text-valign': 'top',
                            'text-halign': 'center',
                            'text-margin-y': -5,
                            'font-size': 14,
                            'color': '#333',
                            'padding': '15px'
                        }
                    },
                    // External port nodes
                    {
                        selector: 'node.external-port',
                        style: {
                            'label': 'data(label)',
                            'shape': 'ellipse',
                            'width': 60,
                            'height': 30,
                            'background-color': '#e8e8e8',
                            'border-width': 1,
                            'border-color': '#808080',
                            'text-valign': 'center',
                            'text-halign': 'center',
                            'font-size': 11,
                            'color': '#333'
                        }
                    },
                    // 10G+ links
                    {
                        selector: 'edge.link-10g',
                        style: {
                            'width': 4,
                            'line-color': '#dc143c',
                            'target-arrow-color': '#dc143c',
                            'target-arrow-shape': 'triangle',
                            'curve-style': 'bezier',
                            'label': 'data(label)',
                            'font-size': 9,
                            'text-rotation': 'autorotate',
                            'text-margin-y': -10
                        }
                    },
                    // 1G links
                    {
                        selector: 'edge.link-1g',
                        style: {
                            'width': 3,
                            'line-color': '#4169e1',
                            'target-arrow-color': '#4169e1',
                            'target-arrow-shape': 'triangle',
                            'curve-style': 'bezier',
                            'label': 'data(label)',
                            'font-size': 9,
                            'text-rotation': 'autorotate',
                            'text-margin-y': -10
                        }
                    },
                    // 100M links
                    {
                        selector: 'edge.link-100m',
                        style: {
                            'width': 2,
                            'line-color': '#32cd32',
                            'target-arrow-color': '#32cd32',
                            'target-arrow-shape': 'triangle',
                            'curve-style': 'bezier',
                            'label': 'data(label)',
                            'font-size': 9,
                            'text-rotation': 'autorotate',
                            'text-margin-y': -10
                        }
                    },
                    // Slow links
                    {
                        selector: 'edge.link-slow',
                        style: {
                            'width': 1,
                            'line-color': '#999999',
                            'target-arrow-color': '#999999',
                            'target-arrow-shape': 'triangle',
                            'curve-style': 'bezier',
                            'label': 'data(label)',
                            'font-size': 9,
                            'text-rotation': 'autorotate',
                            'text-margin-y': -10
                        }
                    },
                    // Hover effects
                    {
                        selector: 'node:active',
                        style: {
                            'overlay-color': '#1e90ff',
                            'overlay-padding': 5,
                            'overlay-opacity': 0.3
                        }
                    }
                ],
                layout: {
                    name: 'dagre',
                    rankDir: 'LR',
                    nodeSep: 30,
                    rankSep: 150,
                    padding: 10
                }
            });

            // Node click handler - navigate to device/port page
            cy.on('tap', 'node', function(evt) {
                var node = evt.target;
                var url = node.data('url');
                if (url) {
                    window.parent.location.href = url;
                }
            });

            // Highlight connected nodes on hover
            cy.on('mouseover', 'node', function(evt) {
                var node = evt.target;
                node.style('background-color', '#ffeb3b');
                node.connectedEdges().style('line-color', '#ff9800');
            });

            cy.on('mouseout', 'node', function(evt) {
                var node = evt.target;
                node.removeStyle('background-color');
                node.connectedEdges().removeStyle('line-color');
            });

            // Update zoom level display
            function updateZoomDisplay() {
                var zoom = cy.zoom();
                document.getElementById('zoom-level').textContent = Math.round(zoom * 100) + '%';
                document.getElementById('zoom-slider').value = zoom;
            }

            cy.on('zoom', updateZoomDisplay);

            // Control buttons
            document.getElementById('fit-btn').addEventListener('click', function() {
                cy.fit(null, 50);
                updateZoomDisplay();
            });

            document.getElementById('center-btn').addEventListener('click', function() {
                cy.center();
            });

            document.getElementById('zoom-reset-btn').addEventListener('click', function() {
                cy.zoom(1);
                cy.center();
                updateZoomDisplay();
            });

            document.getElementById('zoom-slider').addEventListener('input', function(e) {
                cy.zoom(parseFloat(e.target.value));
                cy.center();
                updateZoomDisplay();
            });

            // Export functionality
            document.getElementById('export-png-btn').addEventListener('click', function() {
                var png = cy.png({
                    output: 'blob',
                    bg: '#fafafa',
                    full: true,
                    scale: 2 // 2x for better quality
                });

                // Create download link
                var url = URL.createObjectURL(png);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'network-map-device-__DEVICE_ID__.png';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });

            document.getElementById('export-svg-btn').addEventListener('click', function() {
                var svg = cy.svg({
                    full: true,
                    scale: 1
                });

                // Create download link
                var blob = new Blob([svg], {type: 'image/svg+xml'});
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'network-map-device-__DEVICE_ID__.svg';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });

            // Initial setup: center at 1:1 zoom instead of auto-fit
            // This prevents squishing on devices with many ports
            cy.zoom(1);
            cy.center();
            updateZoomDisplay();
        })
        .catch(error => {
            document.getElementById('cy').innerHTML = '<div style="padding: 20px; text-align: center; color: red;">Error loading network map: ' + error + '</div>';
            console.error('Error:', error);
        });
})();
JAVASCRIPT;

// Replace device ID placeholder in the script
$map_script = str_replace('__DEVICE_ID__', $device['device_id'], $map_script);

register_html_resource('script', $map_script);

// EOF
