<?php
/**
 * Observium - Clock Dashboard Widget
 *
 * Auto-updating clock with configurable format, timezone, and display style
 */

if (!function_exists('print_clock_widget')) {
    function print_clock_widget($mod, $width, $height)
    {
        $widget_id = $mod['widget_id'];
        $config = $mod['vars'] ?: [];

        // Default configuration
        $defaults = [
            'style' => 'digital',
            'format' => '24',
            'show_date' => TRUE,
            'show_seconds' => TRUE,
            'timezone' => 'UTC',
            'bg_color' => '',
            'text_color' => ''
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($config[$key])) {
                $config[$key] = $value;
            }
        }

        // Convert checkbox values
        $config['show_date'] = get_var_true($config['show_date']);
        $config['show_seconds'] = get_var_true($config['show_seconds']);

        $style_class = $config['style'] === 'analog' ? 'clock-analog' : 'clock-digital';

        // Build inline styles only if custom colors are set
        $box_style = 'height: 100%; display: flex; align-items: center; justify-content: center;';
        if (!safe_empty($config['bg_color'])) {
            $box_style .= ' background-color: ' . escape_html($config['bg_color']) . ';';
        }

        $clock_style = 'text-align: center; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center;';
        if (!safe_empty($config['text_color'])) {
            $clock_style .= ' color: ' . escape_html($config['text_color']) . ';';
        }

        echo '<div class="box box-solid do-not-update" style="height: 100%; overflow: hidden;">';
        echo '<div class="box-content" style="' . $box_style . '">';

        if ($config['style'] === 'analog') {
            echo '<div id="clock-' . $widget_id . '" class="' . $style_class . '" style="position: relative; width: 100%; height: 100%; min-height: 100px;"></div>';
        } else {
            echo '<div id="clock-' . $widget_id . '" class="' . $style_class . '" style="' . $clock_style . '">';
            echo '<div class="clock-time" style="font-weight: bold; font-family: monospace; line-height: 1.2;"></div>';
            if ($config['show_date']) {
                echo '<div class="clock-date" style="margin-top: 5px; opacity: 0.8;"></div>';
            }
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';

        // JavaScript for clock functionality
        echo '<script type="text/javascript">';
        echo '(function() {';
        echo 'var clockElement = document.getElementById("clock-' . $widget_id . '");';
        echo 'var config = ' . json_encode($config) . ';';

        if ($config['style'] === 'analog') {
            // Analog clock
            echo '
            var canvas = document.createElement("canvas");
            clockElement.appendChild(canvas);
            var ctx = canvas.getContext("2d");

            // Get theme colors from computed styles
            function getThemeColors() {
                var colors = {};
                if (config.bg_color) {
                    colors.bg = config.bg_color;
                } else {
                    var boxContent = clockElement.closest(".box-content");
                    colors.bg = window.getComputedStyle(boxContent).backgroundColor;
                }
                if (config.text_color) {
                    colors.text = config.text_color;
                } else {
                    var boxContent = clockElement.closest(".box-content");
                    colors.text = window.getComputedStyle(boxContent).color;
                }
                return colors;
            }

            function resizeCanvas() {
                var container = clockElement;
                var size = Math.min(container.offsetWidth, container.offsetHeight);
                canvas.width = size;
                canvas.height = size;
                canvas.style.position = "absolute";
                canvas.style.left = (container.offsetWidth - size) / 2 + "px";
                canvas.style.top = (container.offsetHeight - size) / 2 + "px";
            }

            function drawAnalogClock() {
                resizeCanvas();
                var colors = getThemeColors();
                var now = new Date();
                if (config.timezone !== "local") {
                    var utc = now.getTime() + (now.getTimezoneOffset() * 60000);
                    var offset = 0;
                    if (config.timezone !== "UTC") {
                        var parts = config.timezone.match(/UTC([+-])(\d+)/);
                        if (parts) {
                            offset = parseInt(parts[2]) * 3600000 * (parts[1] === "+" ? 1 : -1);
                        }
                    }
                    now = new Date(utc + offset);
                }

                var radius = canvas.width / 2;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.translate(radius, radius);
                radius = radius * 0.9;

                // Face
                ctx.beginPath();
                ctx.arc(0, 0, radius, 0, 2 * Math.PI);
                ctx.fillStyle = colors.bg;
                ctx.fill();
                ctx.strokeStyle = colors.text;
                ctx.lineWidth = radius * 0.05;
                ctx.stroke();

                // Hour numbers
                ctx.font = radius * 0.15 + "px arial";
                ctx.fillStyle = colors.text;
                ctx.textBaseline = "middle";
                ctx.textAlign = "center";
                for(var num = 1; num <= 12; num++){
                    var ang = num * Math.PI / 6;
                    ctx.rotate(ang);
                    ctx.translate(0, -radius * 0.85);
                    ctx.rotate(-ang);
                    ctx.fillText(num.toString(), 0, 0);
                    ctx.rotate(ang);
                    ctx.translate(0, radius * 0.85);
                    ctx.rotate(-ang);
                }

                // Hour hand
                var hour = now.getHours();
                var minute = now.getMinutes();
                hour = hour % 12;
                hour = (hour * Math.PI / 6) + (minute * Math.PI / (6 * 60));
                drawHand(ctx, hour, radius * 0.5, radius * 0.07, colors.text);

                // Minute hand
                minute = (minute * Math.PI / 30) + (now.getSeconds() * Math.PI / (30 * 60));
                drawHand(ctx, minute, radius * 0.8, radius * 0.05, colors.text);

                // Second hand
                if (config.show_seconds) {
                    var second = (now.getSeconds() * Math.PI / 30);
                    drawHand(ctx, second, radius * 0.9, radius * 0.02, "#ff0000");
                }

                // Center dot
                ctx.beginPath();
                ctx.arc(0, 0, radius * 0.1, 0, 2 * Math.PI);
                ctx.fillStyle = colors.text;
                ctx.fill();

                ctx.translate(-radius - (canvas.width / 2 - radius), -radius - (canvas.height / 2 - radius));
            }

            function drawHand(ctx, pos, length, width, color) {
                ctx.beginPath();
                ctx.lineWidth = width;
                ctx.lineCap = "round";
                ctx.strokeStyle = color;
                ctx.moveTo(0, 0);
                ctx.rotate(pos);
                ctx.lineTo(0, -length);
                ctx.stroke();
                ctx.rotate(-pos);
            }

            setInterval(drawAnalogClock, 1000);
            drawAnalogClock();
            window.addEventListener("resize", function() { setTimeout(drawAnalogClock, 100); });
            ';
        } else {
            // Digital clock
            echo '
            var timeDiv = clockElement.querySelector(".clock-time");
            var dateDiv = clockElement.querySelector(".clock-date");

            var lastHeight = 0;

            function updateDigitalClock() {
                var now = new Date();
                if (config.timezone !== "local") {
                    var utc = now.getTime() + (now.getTimezoneOffset() * 60000);
                    var offset = 0;
                    if (config.timezone !== "UTC") {
                        var parts = config.timezone.match(/UTC([+-])(\d+)/);
                        if (parts) {
                            offset = parseInt(parts[2]) * 3600000 * (parts[1] === "+" ? 1 : -1);
                        }
                    }
                    now = new Date(utc + offset);
                }

                var hours = now.getHours();
                var minutes = now.getMinutes();
                var seconds = now.getSeconds();

                if (config.format === "12") {
                    var ampm = hours >= 12 ? "PM" : "AM";
                    hours = hours % 12;
                    hours = hours ? hours : 12;
                    var timeStr = pad(hours) + ":" + pad(minutes);
                    if (config.show_seconds) {
                        timeStr += ":" + pad(seconds);
                    }
                    timeStr += " " + ampm;
                } else {
                    var timeStr = pad(hours) + ":" + pad(minutes);
                    if (config.show_seconds) {
                        timeStr += ":" + pad(seconds);
                    }
                }

                timeDiv.textContent = timeStr;

                if (config.show_date && dateDiv) {
                    var days = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
                    var months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                    var dateStr = days[now.getDay()] + ", " + months[now.getMonth()] + " " + now.getDate() + ", " + now.getFullYear();
                    dateDiv.textContent = dateStr;
                }

                resizeDigitalClock();
            }

            function resizeDigitalClock() {
                var containerHeight = clockElement.offsetHeight;
                if (containerHeight !== lastHeight) {
                    lastHeight = containerHeight;
                    var fontSize = Math.max(12, Math.min(containerHeight * 0.3, 120));
                    timeDiv.style.fontSize = fontSize + "px";
                    if (dateDiv) {
                        dateDiv.style.fontSize = (fontSize * 0.3) + "px";
                    }
                }
            }

            function pad(num) {
                return (num < 10 ? "0" : "") + num;
            }

            setInterval(updateDigitalClock, 1000);
            updateDigitalClock();

            // Use ResizeObserver for immediate resize response if available
            if (typeof ResizeObserver !== "undefined") {
                var resizeObserver = new ResizeObserver(function() {
                    resizeDigitalClock();
                });
                resizeObserver.observe(clockElement);
            }

            window.addEventListener("resize", function() { setTimeout(resizeDigitalClock, 100); });
            ';
        }

        echo '})();';
        echo '</script>';
    }
}

// EOF
