<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package        observium
 * @subpackage     graphs
 * @copyright  (C) Adam Armstrong
 *
 */

$graph_def = $config['graph_types'][$type][$subtype];

$graph_return['legend_lines'] = 0;

/*
 * Tag handling for definition-based graphs
 *
 * The renderer builds a $tags context used for template substitutions
 * in fields like 'file', 'descr', and 'name'. Tags are populated in
 * this order without overwriting existing entries:
 *   1) Entity/device tags set by the entity auth include (existing behaviour)
 *   2) Optional $graph_tags provided by auth.inc.php (module-specific extras)
 *   3) All $vars from the request (URL/query) as last-resort tags
 *
 * This allows modules to pass placeholders (e.g. %basePort%, %type%, %instance_key%)
 * without coupling the generic renderer to module specifics. Filenames are
 * still sanitized by get_rrd_path()/rrdtool escaping.
 */

if (!isset($tags) || !is_array($tags)) { $tags = []; }

// Merge extra tags from auth includes if provided
if (isset($graph_tags) && is_array($graph_tags)) {
    foreach ($graph_tags as $k => $v) {
        if (!array_key_exists($k, $tags)) { $tags[$k] = $v; }
    }
}

// Merge request variables as tags (non-overwriting)
if (isset($vars) && is_array($vars)) {
    foreach ($vars as $k => $v) {
        if (!array_key_exists($k, $tags)) { $tags[$k] = $v; }
    }
}

if (isset($graph_def['descr'])) {
    $graph_return['descr'] = $graph_def['descr'];
}

// Set some defaults and convert $graph_def values to global values for use by common.inc.php.
// common.inc.php needs converted to use $graph_def so we can remove this.

if (isset($graph_def['name'])) {
    $graph_title = $graph_def['name'];
}
if (isset($graph_def['step'])) {
    $step = $graph_def['step'];
}
if (isset($graph_def['unit_text'])) {
    $unit_text = $graph_def['unit_text'];
}
if (isset($graph_def['scale_min'])) {
    $scale_min = $graph_def['scale_min'];
}
if (isset($graph_def['scale_max'])) {
    $scale_max = $graph_def['scale_max'];
}
if (isset($graph_def['legend'])) {
    $legend = $graph_def['legend'];
}
if (isset($graph_def['log_y']) && $graph_def['log_y'] == TRUE) {
    $log_y = TRUE;
} else {
    unset($log_y);
} // Strange, if $log_y set to FALSE anyway legend logarithmic
if (isset($graph_def['no_mag']) && $graph_def['no_mag'] == TRUE) {
    $mag_unit = "' '";
} else {
    $mag_unit = '%S';
}
if (isset($graph_def['num_fmt'])) {
    $num_fmt = $graph_def['num_fmt'];
} else {
    $num_fmt = '6.1';
}
if (isset($graph_def['nototal'])) {
    $nototal = $graph_def['nototal'];
} else {
    $nototal = TRUE;
}
if (!isset($graph_def['colours'])) {
    $graph_def['colours'] = "mixed";
}

// Handle color scheme function definitions for flexible color generation
if (isset($graph_def['colour_function'])) {
    $function_name = $graph_def['colour_function'];

    // Get function arguments (default to series count if not specified)
    if (isset($graph_def['colour_function_args'])) {
        $function_args = $graph_def['colour_function_args'];
        // Replace placeholders with actual values
        if (is_array($function_args)) {
            foreach ($function_args as &$arg) {
                if ($arg === '%series_count%') {
                    $arg = count($graph_def['ds']);
                } elseif ($arg === '%entity_count%' && isset($index)) {
                    // For multi-entity graphs, could be useful
                    $arg = is_array($index) ? count($index) : 1;
                }
            }
        } elseif ($function_args === '%series_count%') {
            $function_args = count($graph_def['ds']);
        } elseif ($function_args === '%entity_count%' && isset($index)) {
            $function_args = is_array($index) ? count($index) : 1;
        }
    } else {
        // Default to series count
        $function_args = count($graph_def['ds']);
    }

    // Generate color scheme using specified function
    if (function_exists($function_name)) {
        if (is_array($function_args)) {
            $scheme_colours = call_user_func_array('generate_palette', array_merge([$function_args[0]], [$function_name]));
        } else {
            $scheme_colours = generate_palette($function_args, $function_name);
        }
    }
} elseif (isset($graph_def['colour_scheme']) && function_exists($graph_def['colour_scheme'])) {
    // Alternative syntax: direct function name
    $scheme_colours = generate_palette(count($graph_def['ds']), $graph_def['colour_scheme']);
}
if (isset($graph_def['colour_offset'])) {
    $c_i = $graph_def['colour_offset'];
} else {
    $c_i = 0;
}

if (isset($graph_def['entity_type'])) {
    // Entity based RRD filename
    // See: counter definition example

    // Index can be TRUE/FALSE (for TRUE used global $index or $vars with key 'id') or name of used key from $vars
    if (is_bool($graph_def['index']) && $graph_def['index']) {
        // Don't overwrite an index set by the auth.inc.php
        if (!isset($index) && isset($vars['id'])) {
            $index         = $vars['id']; // Default index variable
            $tags['index'] = $index;
        }
    } elseif (isset($vars[$graph_def['index']])) {
        $index         = $vars[$graph_def['index']];
        $tags['index'] = $index;
    } else {
        $index = FALSE;
    }

    if (strlen($index)) {
        // Rewrite RRD filename
        $graph_def['file'] = get_entity_rrd_by_id($graph_def['entity_type'], $index);

        // Append entity array as tags
        $entity = get_entity_by_id_cache($graph_def['entity_type'], $index);
        $device = device_by_id_cache($entity['device_id']);
        $tags   = array_merge($tags, $device, $entity);

        // Some params required tag replaces
        if (isset($graph_def['name'])) {
            $graph_title = array_tag_replace($tags, $graph_def['name']);
        }
        if (isset($graph_def['unit_text'])) {
            $unit_text = array_tag_replace($tags, $graph_def['unit_text']);
        }
        if (isset($graph_def['descr'])) {
            $descr = array_tag_replace($tags, $graph_def['descr']);
        }
    }
} elseif (isset($graph_def['file']) && str_contains($graph_def['file'], '%')) {
    // Indexed graphs

    // Index can be TRUE/FALSE (for TRUE used global $index or $vars with key 'id') or name of used key from $vars
    if (is_bool($graph_def['index']) && $graph_def['index']) {
        // Don't overwrite an index set by the auth.inc.php
        if (isset($index)) {
            // SLA echo for example
            $tags['index'] = $index;
        } elseif (isset($vars['id'])) {
            $index         = $vars['id']; // Default index variable
            $tags['index'] = $index;
        }
    } elseif (isset($graph_def['index'], $vars[$graph_def['index']])) {
        $index         = $vars[$graph_def['index']];
        $tags['index'] = $index;
    } else {
        $index = FALSE;
    }

    if (!safe_empty($tags)) {
        // Rewrite RRD filename
        //$graph_def['file'] = str_replace('-index.rrd', '-'.$index.'.rrd', $graph_def['file']);
        $graph_def['file'] = array_tag_replace($tags, $graph_def['file']);
    }
}

include($config['html_dir'] . '/includes/graphs/common.inc.php');
include_once($config['html_dir'] . '/includes/graphs/legend.inc.php');

foreach ($graph_def['ds'] as $ds_name => $ds) {
    if (!isset($ds['file'])) {
        $ds['file'] = $graph_def['file'];
    } elseif (str_contains($ds['file'], '%')) {
        // Indexed graphs also replace %index% for specific DS
        $ds['file'] = array_tag_replace($tags, $ds['file']);
    }
    // label with tag replaces
    if (isset($ds['label'])) {
        $label_descr = array_tag_replace($tags, $ds['label']);
    } else {
        $label_descr = $descr;
    }

    if (!isset($ds['draw'])) {
        $ds['draw'] = "LINE1.5";
    }
    if ($graph_def['rra_min'] === FALSE || $ds['rra_min'] === FALSE) {
        $ds['rra_min'] = FALSE;
    } else {
        $ds['rra_min'] = TRUE;
    }
    if ($graph_def['rra_max'] === FALSE || $ds['rra_max'] === FALSE) {
        $ds['rra_max'] = FALSE;
    } else {
        $ds['rra_max'] = TRUE;
    }

    $ds_unit = strlen($ds['unit']) ? $ds['unit'] : ''; // Unit text per DS
    if (isset($ds['num_fmt'])) {
        $num_fmt = $ds['num_fmt'];                     // Numeric format per DS
    }

    $ds_data = $ds_name;

    $ds['file']        = get_rrd_path($device, $ds['file']);
    $ds['file_escape'] = rrdtool_escape($ds['file']);

    if (isset($ds['graph'])) {
        if (get_var_false($ds['graph'])) {
            // Some time required skip graphs, only CDEF
            if (!empty($ds['cdef'])) {
                //$ds_name = $ds_name."_c";
                //$ds_data = $ds_name;
                $cmd_cdef .= " CDEF:" . $ds_name . "=" . $ds['cdef'];
                //$cmd_cdef .= " CDEF:".$ds_name."_min=".$ds['cdef'];
                //$cmd_cdef .= " CDEF:".$ds_name."_max=".$ds['cdef'];
            }
            continue;
        }
        if ($ds['graph'] === 'availability' || $ds['graph'] === 'percents10') {
            // Graph for only one DS with 0/1 values as percents
            // See: device availability or alert status graph definitions

            $cmd_def = " DEF:" . $ds_name . "=" . $ds['file_escape'] . ":" . $ds_name . ":AVERAGE";

            $cmd_cdef  = " CDEF:percent=" . $ds_name . ",UN,UNKN," . $ds_name . ",IF,100,* ";
            $cmd_cdef .= " CDEF:unknown=" . $ds_name . ",UN,100,UNKN,IF";

            $cmd_cdef .= " CDEF:percent10=10,percent,LE,0,100,IF ";
            $cmd_cdef .= " CDEF:percent20=10,percent,GT,0,100,IF ";
            $cmd_cdef .= " CDEF:percent30=20,percent,GT,0,100,IF ";
            $cmd_cdef .= " CDEF:percent40=30,percent,GT,0,100,IF ";
            $cmd_cdef .= " CDEF:percent50=40,percent,GT,0,100,IF ";
            $cmd_cdef .= " CDEF:percent60=50,percent,GT,0,100,IF ";
            $cmd_cdef .= " CDEF:percent70=60,percent,GT,0,100,IF ";
            $cmd_cdef .= " CDEF:percent80=70,percent,GT,0,100,IF ";
            $cmd_cdef .= " CDEF:percent90=80,percent,GT,0,100,IF ";
            $cmd_cdef .= " CDEF:percent100=90,percent,GT,0,100,IF ";

            $colours    = $config['graph_colours']['percents10'];
            $cmd_graph  = " AREA:percent10#" . rrdtool_escape($colours[0]) . ":' 0-10%'";
            $cmd_graph .= " AREA:percent20#" . rrdtool_escape($colours[1]) . ":'11-20%'";
            $cmd_graph .= " AREA:percent30#" . rrdtool_escape($colours[2]) . ":'21-30%'";
            $cmd_graph .= " AREA:percent40#" . rrdtool_escape($colours[3]) . ":'31-40%'";
            $cmd_graph .= " AREA:percent50#" . rrdtool_escape($colours[4]) . ":'41-50%'";
            $cmd_graph .= " AREA:percent60#" . rrdtool_escape($colours[5]) . ":'51-60%'";
            $cmd_graph .= " AREA:percent70#" . rrdtool_escape($colours[6]) . ":'61-70%'";
            $cmd_graph .= " AREA:percent80#" . rrdtool_escape($colours[7]) . ":'71-80%'";
            $cmd_graph .= " AREA:percent90#" . rrdtool_escape($colours[8]) . ":'81-90%'";
            $cmd_graph .= " AREA:percent100#". rrdtool_escape($colours[9]) . ":'91-100%'";
            $cmd_graph .= " AREA:unknown#e5e5e5:'Unknown \\n'";

            //$cmd_graph .= " GPRINT:" . $ds_name . ":LAST:'Current \: %1.0lf'\\l";
            if (safe_empty($label_descr)) {
                $label_descr = 'Percent Availability :';
            }
            $cmd_graph .= " GPRINT:percent:AVERAGE:'" . rrdtool_escape($label_descr) . "%" . $num_fmt . "lf %%'\\r";
            break;
        }
    }

    $cmd_def .= " DEF:" . $ds_name . "=" . $ds['file_escape'] . ":" . $ds_name . ":AVERAGE";
    if ($ds['rra_min']) {
        $cmd_def .= " DEF:" . $ds_name . "_min=" . $ds['file_escape'] . ":" . $ds_name . ":MIN";
    } else {
        $cmd_def .= " CDEF:" . $ds_name . "_min=" . $ds_name;
    }
    if ($ds['rra_max']) {
        $cmd_def .= " DEF:" . $ds_name . "_max=" . $ds['file_escape'] . ":" . $ds_name . ":MAX";
    } else {
        $cmd_def .= " CDEF:" . $ds_name . "_max=" . $ds_name;
    }

    //$graph_return['rrds'][$ds['file']][] = $ds_name;

    if (!empty($ds['cdef'])) {
        $ds_name .= "_c";
        $ds_data  = $ds_name;
        $cmd_cdef .= " CDEF:" . $ds_name . "=" . $ds['cdef'];
        $cmd_cdef .= " CDEF:" . $ds_name . "_min=" . $ds['cdef'];
        $cmd_cdef .= " CDEF:" . $ds_name . "_max=" . $ds['cdef'];
    }

    if (!empty($ds['invert'])) {
        $cmd_cdef .= " CDEF:" . $ds_name . "_i=" . $ds_name . ",-1,*";
        $cmd_cdef .= " CDEF:" . $ds_name . "_min_i=" . $ds_name . "_min,-1,*";
        $cmd_cdef .= " CDEF:" . $ds_name . "_max_i=" . $ds_name . "_max,-1,*";
        $ds_data  = $ds_name;
        $ds_name .= "_i";
    }

    if (empty($ds['colour'])) {
        if (isset($scheme_colours)) {
            // Use pre-generated scheme colors with overflow handling
            if (isset($scheme_colours[$c_i])) {
                $colour = ltrim($scheme_colours[$c_i], '#');
            } else {
                // Handle case where more colors needed than provided in scheme
                $colour = ltrim($scheme_colours[$c_i % count($scheme_colours)], '#');
            }
        } elseif (!$config['graph_colours'][$graph_def['colours']][$c_i]) {
            // Enhanced color system: generate dynamic gradients for large datasets
            $total_series = count($graph_def['ds']);
            $available_colors = count($config['graph_colours'][$graph_def['colours']]);

            if ($total_series > $available_colors) {
                // Generate extended gradient palette for large datasets
                if (!isset($extended_gradient)) {
                    if ($total_series > 50) {
                        // For very large datasets, create alternating gradients
                        $extended_gradient = array_merge(
                            array_values(generate_colour_gradient(reset($config['graph_colours'][$graph_def['colours']]), end($config['graph_colours'][$graph_def['colours']]), 25)),
                            array_values(generate_colour_gradient(end($config['graph_colours'][$graph_def['colours']]), reset($config['graph_colours'][$graph_def['colours']]), 25))
                        );
                    } else {
                        // For medium datasets, single gradient
                        $extended_gradient = generate_colour_gradient(reset($config['graph_colours'][$graph_def['colours']]), end($config['graph_colours'][$graph_def['colours']]), $total_series);
                    }
                }
                $colour = $extended_gradient[$c_i % count($extended_gradient)];
            } else {
                $c_i = 0;
                $colour = $config['graph_colours'][$graph_def['colours']][$c_i];
            }
        } else {
            $colour = $config['graph_colours'][$graph_def['colours']][$c_i];
        }
        $c_i++;
    } else {
        $colour = $ds['colour'];
    }

    if ($ds['draw'] == "AREASTACK") {
        $ds['draw']  = "AREA";
        $ds['stack'] = ":STACK";
    } elseif (preg_match("/^LINESTACK([0-9\.]*)/", $ds['ds_draw'], $m)) { /// FIXME $ds['draw']
        if ($i == 0) {                                                    /// FIXME what is $i ?
            $ds['draw'] = "LINE$m[1]";
        } else {
            $ds['draw'] = "STACK";
        }
    }

    //bdump(rrdtool_escape($label_descr, $descr_len));
    $label_descr = rrdtool_escape($label_descr, $descr_len);

    $cmd_graph .= ' ' . $ds['draw'] . ':' . $ds_name . '#' . rrdtool_escape($colour) . ':"' . $label_descr . '"' . $ds['stack'];

    if (is_array($data_show)) {
        if (in_array("lst", $data_show)) {
            $cmd_graph .= " GPRINT:" . $ds_data . ":LAST:%" . $num_fmt . "lf" . $mag_unit . $ds_unit;
        }
        if (in_array("avg", $data_show)) {
            $cmd_graph .= " GPRINT:" . $ds_data . ":AVERAGE:%" . $num_fmt . "lf" . $mag_unit . $ds_unit;
        }
        if (in_array("min", $data_show)) {
            $cmd_graph .= " GPRINT:" . $ds_data . "_min:MIN:%" . $num_fmt . "lf" . $mag_unit . $ds_unit;
        }
        if (in_array("max", $data_show)) {
            $cmd_graph .= " GPRINT:" . $ds_data . "_max:MAX:%" . $num_fmt . "lf" . $mag_unit . $ds_unit;
        }
    }
    $cmd_graph .= " COMMENT:'\\l'";
    $graph_return['legend_lines']++;

    if ($ds['line']) {
        if (is_numeric($ds['line'])) {
            $line = 'LINE' . $ds['line'];
        } else {
            $line = 'LINE1';
        }

        $cmd_graph .= " $line:$ds_name#" . rrdtool_escape(darken_color($colour));
    }
}

$rrd_options .= $cmd_def . $cmd_cdef . $cmd_graph;

// EOF
