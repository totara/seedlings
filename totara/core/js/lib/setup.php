<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */
require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content.class.php');

/**
 * Constants for defining JS to load
 */
define('TOTARA_JS_DIALOG',         1);
define('TOTARA_JS_TREEVIEW',       2);
define('TOTARA_JS_DATEPICKER',     3);
define('TOTARA_JS_PLACEHOLDER',    4);
define('TOTARA_JS_ICON_PREVIEW',   5);
define('TOTARA_JS_UI',             6);
define('TOTARA_JS_DATATABLES',     7);
/**
 * Load appropriate JS and CSS files for lightbox
 *
 * @param $options array Array of option constants
 */
function local_js($options = array()) {
    global $CFG, $PAGE;

    // Include required javascript libraries
    // jQuery component and UI bundle found here: http://jqueryui.com/download
    // Core, Widget, Position, Dialog, Tabs, Datepicker, Effects Core, Effects "Fade"

    // Serve up latest jQuery if using something better than IE8.
    if (!core_useragent::is_ie() || core_useragent::check_ie_version(9)) {
        $PAGE->requires->js('/totara/core/js/lib/jquery-2.1.0.min.js');
    } else {
        $PAGE->requires->js('/totara/core/js/lib/jquery-1.9.1.min.js');
    }

    // If UI
    if (in_array(TOTARA_JS_UI, $options)) {

        $PAGE->requires->js('/totara/core/js/lib/jquery-ui-1.10.4.custom.min.js');

    }

    // If dialog
    if (in_array(TOTARA_JS_DIALOG, $options)) {

        $PAGE->requires->js('/totara/core/js/lib/jquery-ui-1.10.4.custom.min.js');

        // Load required strings into the JS global namespace in the form
        // M.str.COMPONENT.IDENTIFIER, eg; M.str.totara_core['save']. Can also
        // be accessed with M.util.get_string(IDENTIFIER, COMPONENT), use third
        // arg for a single {$a} replacement. See /lib/outputrequirementslib.php
        // for detail and limitations.
        $PAGE->requires->strings_for_js(array('save', 'delete'), 'totara_core');
        $PAGE->requires->strings_for_js(array('ok', 'cancel'), 'moodle');

        // Include the totara_dialog JS module. Args supplied to the module's
        // init method must be a php array (or null if none), the first index
        // being a JSON formatted string of args, which are parsed into a config
        // object stored in the module, eg; array('args'=>'{"id":' .$id. '}')
        // which is then available via M.totara_dialog.config.id once the module
        // has loaded. Further args can be supplied to the init method but are
        // not JSON parsed, but are still available via the usual 'arguments'
        // object of the init method.
        $jsmodule = array(
                'name' => 'totara_dialog',
                'fullpath' => '/totara/core/js/lib/totara_dialog.js',
                'requires' => array('json'));
        $PAGE->requires->js_init_call('M.totara_dialog.init', null, false, $jsmodule);
    }

    // If treeview enabled
    if (in_array(TOTARA_JS_TREEVIEW, $options)) {

        $PAGE->requires->js('/totara/core/js/lib/jquery.treeview.min.js');

    }

    // If datepicker enabled
    if (in_array(TOTARA_JS_DATEPICKER, $options)) {

        $PAGE->requires->js('/totara/core/js/lib/jquery-ui-1.10.4.custom.min.js');

        $PAGE->requires->strings_for_js(array('datepickerlongyeardisplayformat', 'datepickerlongyearplaceholder', 'datepickerlongyearregexjs'), 'totara_core');
        $PAGE->requires->string_for_js('thisdirection', 'langconfig');

        $lang = current_language();

        // include datepicker localization file if present for current language
        $file = "/totara/core/js/lib/i18n/jquery.ui.datepicker-{$lang}.js";
        if (is_readable($CFG->dirroot . $file)) {
            $PAGE->requires->js($file);
        }


    }

    // if placeholder enabled
    if (in_array(TOTARA_JS_PLACEHOLDER, $options)) {
        $PAGE->requires->js('/totara/core/js/lib/jquery.placeholder.min.js');
        $PAGE->requires->js('/totara/core/js/lib/load.placeholder.js');

    }

    // If Icon preview is enabled
    if (in_array(TOTARA_JS_ICON_PREVIEW, $options)) {

        $PAGE->requires->js('/totara/core/js/icon.preview.js');

    }

    if (in_array(TOTARA_JS_DATATABLES, $options)) {
        $PAGE->requires->js('/totara/core/js/lib/jquery.dataTables.min.js');
    }
}

/**
 * Adds JS datepicker setup call to page
 *
 * @param string $selector A JQuery Selector string referencing the element to add
 *                         the picker to
 * @param string $dateformat (optional) provide if format should not be standard dd/mm/yy
 */
function build_datepicker_js($selector, $dateformat=null) {
    global $PAGE;

    $PAGE->requires->strings_for_js(array('datepickerlongyeardisplayformat', 'datepickerlongyearplaceholder', 'datepickerlongyearregexjs'), 'totara_core');

    if (empty($dateformat)) {
        $dateformat = get_string('datepickerlongyeardisplayformat', 'totara_core');
    }
    $button_img = array('t/calendar', 'totara_core');
    $args = array($selector, $dateformat, $button_img);
    $PAGE->requires->js_init_call('M.totara_core.build_datepicker', $args);
}

/**
 * Return markup for a branch of a hierarchy based treeview
 *
 * @param   $elements       array       Single level array of elements
 * @param   $error_string   string      String to display if no elements supplied
 * @param   $hierarchy      object      The hierarchy object (optional)
 * @param   $disabledlist   array       Array of IDs of elements that should be disabled
 * @uses    $CFG
 * @return  $html
 */
function build_treeview($elements, $error_string, $hierarchy = null, $disabledlist = array()) {

    global $CFG, $OUTPUT;
    // maximum number of items to load (at any one level)
    // before giving up and suggesting search instead.
    $maxitems = TOTARA_DIALOG_MAXITEMS;

    $html = '';

    $buttons = array('addbutton' => 'add',
                     'deletebutton' => 'delete');

    if (is_array($elements) && !empty($elements)) {

        if(count($elements) > $maxitems) {
            $html .= '<li class="last"><span class="empty dialog-nobind">';
            $html .= get_string('error:morethanxitemsatthislevel', 'totara_core', $maxitems);
            $html .= ' <a href="#search-tab" onclick="$(\'#tabs\').tabs(\'select\', 1);return false;">';
            $html .= get_string('trysearchinginstead', 'totara_core');
            $html .= '</a>';
            $html .= '</span></li>'.PHP_EOL;
            return $html;
        }

        // Get parents array
        if ($hierarchy) {
            $parents = $hierarchy->get_all_parents();
        } else {
            $parents = array();
        }

        $total = count($elements);
        $count = 0;

        // Loop through elements
        foreach ($elements as $element) {
            ++$count;

            // Initialise class vars
            $li_class = '';
            $div_class = '';
            $span_class = '';

            // If last element
            if ($count == $total) {
                $li_class .= ' last';
            }

            // If element has children
            if (array_key_exists($element->id, $parents)) {
                $li_class .= ' expandable closed';
                $div_class .= ' hitarea closed-hitarea expandable-hitarea';
                $span_class .= ' folder';

                if ($count == $total) {
                    $li_class .= ' lastExpandable';
                    $div_class .= ' lastExpandable-hitarea';
                }
            }

            $addbutton_html = '<img src="'.$OUTPUT->pix_url('t/'.$buttons['addbutton']).'" class="addbutton" />';

            // Make disabled elements non-draggable and greyed out
            if (array_key_exists($element->id, $disabledlist)){
                $span_class .= ' unclickable';
                $addbutton_html = '';
            }

            $html .= '<li class="'.trim($li_class).'" id="item_list_'.$element->id.'">';
            $html .= '<div class="'.trim($div_class).'"></div>';
            $html .= '<span id="item_'.$element->id.'" class="'.trim($span_class).'">';
            // format_string() really slow here...
            $html .= '<table><tr>';
            $html .= '<td class="list-item-name">'.format_string($element->fullname).'</td>';
            $html .= '<td class="list-item-action">'.$addbutton_html.'</td>';
            $html .= '</tr></table>';
            $html .= '</span>';

            if ($div_class !== '') {
                $html .= '<ul style="display: none;"></ul>';
            }
            $html .= '</li>'.PHP_EOL;
        }
    }
    else {
        $html .= '<li class="last"><span class="empty">';
        $html .= $error_string;
        $html .= '</span></li>'.PHP_EOL;
    }

    // Add hidden button images that can later be used/cloned by js TODO: add tooltip get_string
    foreach ($buttons as $classname => $pic) {
        $html .= '<img id="'.$classname.'_ex" src="'.$OUTPUT->pix_url('t/'.$pic).'"
            class="'.$classname.'" style="display: none;" />';
    }

    return $html;
}

/**
 * Return markup for category treeview skeleton
 *
 * @param   $list           array       Array of full cat path names
 * @param   $parents        array       Array of category parents
 * @param   $load_string    string      String to display as a placeholder for unloaded branches
 * @uses    $CFG
 * @return  $html
 */
function build_category_treeview($list, $parents, $load_string) {

    global $CFG, $OUTPUT;

    $buttons = array('addbutton' => 'add',
                     'deletebutton' => 'delete');

    $html = '';

    if (is_array($list) && !empty($list)) {

        $len = count($list);
        $i = 0;
        $parent = array();

        // Add empty category to end of array to trigger
        // closing nested lists
        $list[] = null;

        foreach ($list as $id => $category) {
            ++$i;

            // If an actual category
            if ($category !== null) {
                if (!isset($parents[$id])) {
                    $this_parent = array();
                } else {
                    $this_parents = array_reverse($parents[$id]);
                    $this_parent = $parents[$id];
                }
            // If placeholder category at end
            } else {
                $this_parent = array();
            }

            if ($this_parent == $parent) {
                if ($i > 1) {
                    $html .= '<li class="last loading"><div></div><span>'.$load_string.'</span></li></ul></li>'.PHP_EOL;
                }
            } else {
                // If there are less parents now
                $diff = count($parent) - count($this_parent);

                if ($diff) {
                    $html .= str_repeat(
                        '<li class="last loading"><div></div><span>'.$load_string.'</span></li></ul>'.PHP_EOL,
                         $diff + 1
                    );
                }

                $parent = $this_parent;
            }

            if ($category !== null) {
                // Grab category name
                $rpos = strrpos($category, ' / ');
                if ($rpos) {
                    $category = substr($category, $rpos + 3);
                }

                $li_class = 'expandable closed';
                $div_class = 'hitarea closed-hitarea expandable-hitarea';

                if ($i == $len) {
                    $li_class .= ' lastExpandable';
                    $div_class .= ' lastExpandable-hitarea';
                }

                $html .= '<li class="'.$li_class.'" id="item_list_'.$id.'"><div class="'.$div_class.'"></div>';
                $html .= '<span class="folder">'.$category.'</span><ul style="display: none;">'.PHP_EOL;
            }
        }

        // Add hidden button images that can later be used/cloned by js TODO: add tooltip get_string
        foreach ($buttons as $classname => $pic) {
            $html .= '<img id="'.$classname.'_ex" src="'.$OUTPUT->pix_url('t/'.$pic).'"
                class="'.$classname.'" style="display: none;" />';
        }
    }

    return $html;
}

/*
 * Create a non-javascript version of treeview
 *
 * @param array $elements Array of items to display
 * @param string $error_string String to print if something goes wrong
 * @param string $actionurl URL to go to to assign an item
 * @param array $actionparams Array of url parameters to include when going to $actionurl
 * @param string $expandurl URL to go to to expand an item to view its children
 * @param array $parents Array of IDs of items that are parents. Used to decide if link to children
 *                       should be shown
 * @param array $disabledlist Array of IDs of items that should be disabled (non-draggable)
 * @return string HTML code displaying the treeview based on input params
 *
 */
function build_nojs_treeview($elements, $error_string, $actionurl, $actionparams, $expandurl, $expandparams, $parents = array(), $disabledlist = array()) {
    global $OUTPUT;
    $table = new html_table();

    if (is_array($elements) && !empty($elements)) {

        // Loop through elements
        foreach ($elements as $element) {
            $params = $actionparams + array('add' => $element->id);
            $cells = array();
            $cells[] = new html_table_cell($OUTPUT->single_button(new moodle_url($actionurl, $params), get_string('assign','totara_hierarchy'), 'get', array('disabled' => array_key_exists($element->id, $disabledlist))));
            // Element has children
            if (array_key_exists($element->id, $parents)) {
                $linktext = format_string($element->fullname);
                if (!empty($element->idnumber)) $linktext .= ' - '.$element->idnumber;
                $cellcontent = html_writer::link(new moodle_url($expandurl, array_merge($expandparams, array('parentid' => $element->id))), $linktext);
            } else {
                $cellcontent = format_string($element->fullname);
                if (!empty($element->idnumber)) $cellcontent .= ' - '.$element->idnumber;
            }
            $cells[] = $cellcontent;
            $table->data[] = new html_table_row($cells);
        }
    }
    else {
        $table->data[] = new html_table_row(array(new html_table_cell($error_string)));
    }
    return html_writer::table($table);
}

/*
 * Create a none js breadcrumb trail, indicating the current position in the framework
 * hierarchy and allowing the user to navigate between levels
 *
 * @param object $hierarchy Hierarchy to generate breadcrumbs for
 * @param integer $parentid Current items parent ID, used to determine what to show
 * @param string $url URL to assign to the breadcrumbs links
 * @param array $urlparams Array of url parameters to pass along with URL
 * @param boolean $allfws If true include link to all frameworks at start of breadcrumbs
 * @return string HTML to print the breadcrumbs trail
 *
 */
function build_nojs_breadcrumbs($hierarchy, $parentid, $url, $urlparams, $allfws=true) {

    $murl = new moodle_url($url, $urlparams);
    $nofwurl = $murl->out(false, array('frameworkid' => 0));

    $html = html_writer::start_tag('div', array('class' => 'breadcrumb')) . html_writer::tag('h2', get_string('youarehere','access'), array('class' => 'accesshide'));
    $html .= html_writer::start_tag('ul');
    $first = true;
    if ($allfws) {
        $first = false;
        $html .= html_writer::tag('li', html_writer::link($nofwurl, get_string('allframeworks','totara_hierarchy')), array('class' => 'first'));
    }
    if ($parentid) {
        if ($lineage = $hierarchy->get_item_lineage($parentid)) {
            // correct order for breadcrumbs
            $items = array_reverse($lineage);
            foreach ($items as $item) {
                $itemurl = $murl->out(false, array('parentid'=>$item->parentid));
                $html .= html_writer::start_tag('li') . html_writer::tag('span', '&nbsp;', array('class' => 'accesshide'));
                if (!$first) {
                    $html .= html_writer::tag('span', '&#x25BA;', array('class' => 'arrow sep'));
                } else {
                    $first = false;
                }
                $html .= html_writer::link($itemurl, format_string($item->fullname)) . html_writer::end_tag('li');
            }
        }
    }
    $html .= html_writer::end_tag('ul') . html_writer::end_tag('div');
    return $html;
}

/*
 * Create a non-javascript framework picker page, allowing the user to select which
 * framework to use to assign an item
 *
 * @param object $hierarchy Hierarchy to generate picker for
 * @param string $url URL to take the user to when they click a framework link
 * @params array $urlparams array of url parameters to pass along with URL
 * @return string HTML to print the framework picker list
 *
 */
function build_nojs_frameworkpicker($hierarchy, $url, $urlparams) {
    global $DB;
    $murl = new moodle_url($url, $urlparams);
    if ($fws = $DB->get_records($hierarchy->shortprefix.'_framework', null, 'sortorder')) {
        $out = html_writer::start_tag('div', array('id' => 'nojsinstructions'));
        $out .= html_writer::tag('p', get_string('pickaframework','totara_hierarchy'));
        $out .= html_writer::end_tag('div');
        $out .= html_writer::start_tag('div', array('class' => 'nojsselect')) . html_writer::start_tag('ul');
        foreach ($fws as $fw) {
            $fullurl = $murl->out(false, array('frameworkid' => $fw->id));
            $out .= html_writer::tag('li', html_writer::link($fullurl, format_string($fw->fullname)));
        }
        $out .= html_writer::end_tag('ul') . html_writer::end_tag('div');
        return $out;
    } else {
        print_error($hierarchy->prefix . 'noframeworks', 'totara_hierarchy');
    }
}

/*
 * Create a non-javascript position picker page, allowing the user to select which
 * position to use to assign an item
 *
 * @param string $url URL to take the user to when they click a position link
 * @params array $urlparams array of url parameters to pass along with URL
 * @return string HTML to print the position picker list
 */
function build_nojs_positionpicker($url, $urlparams) {
    global $USER, $CFG, $OUTPUT;
    // TODO add other html to this function (see picker above)
    $murl = new moodle_url($url, $urlparams);
    $html = '';
    $positionhierarchy = new position();
    $positions = $positionhierarchy->get_user_positions($USER);
    if ($positions) {
        $html .= $OUTPUT->container_start(null, 'nojsinstructions');
        $html .= html_writer::start_tag('p');
        $html .= get_string('chooseposition','position');
        $html .= html_writer::end_tag('p');
        $html .= $OUTPUT->container_end();
        $html .= $OUTPUT->container_start('nojsselect');
        $html .= html_writer::start_tag('ul');
        foreach ($positions as $position) {
            $fullurl = $murl->out(false, array('frameworkid' => $position->id));
            $html .= html_writer::start_tag('li');
            $html .= html_writer::link($fullurl, format_string($position->fullname));
            switch ($position->type) {
            case 1:
                $html .= ' (' . get_string('typeprimary', 'totara_hierarchy') . ')';
                break;
            case 2:
                $html .= ' (' . get_string('typesecondary', 'totara_hierarchy') . ')';
                break;
            case 3:
                $html .= ' (' . get_string('typeaspirational', 'totara_hierarchy') . ')';
                break;
            }
            $html .= html_writer::end_tag('li');
        }
        $html .= html_writer::end_tag('ul');
        $html .= $OUTPUT->container_end();
    } else {
        print_error('nopositions', 'position');
    }
    return $html;
}

/**
 * Return markup for 'Currently selected' info in a dialog
 * @param $label the label
 * @param $title the unique title of the dialog
 * @return  $html
 */
function dialog_display_currently_selected($label, $title='') {

    $outerid = "treeview_currently_selected_span_{$title}";
    $innerid = "treeview_selected_text_{$title}";
    $valid = "treeview_selected_val_{$title}";

    $html = ' ' . html_writer::start_tag('span', array('id' => $outerid, 'style' => 'display: none;'));
    $html .= '(' . html_writer::tag('label', $label, array('for' => $innerid)) . ':&nbsp;';
    $html .= html_writer::tag('em', html_writer::tag('span', '', array('id' => $innerid)));
    $html .= ')' . html_writer::end_tag('span');

    // Also add a hidden field that can hold the currently selected value
    $attr = array('type' => 'hidden', 'id' => $valid, 'name' => $valid, 'value' => '');
    $html .= html_writer::empty_tag('input', $attr);

    return $html;
}
