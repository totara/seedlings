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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage plan
 */

/**
 * Library of functions related to Learning Plan priorities.
 */

require_once($CFG->dirroot . '/totara/plan/lib.php');

/**
 * Determine whether an priority scale is assigned to any components of
 * any plan templates
 *
 * There is a less strict version of this function:
 * {@link dp_priority_scale_is_used()} which tells you if the scale
 * values are actually assigned.
 *
 * @param int $scaleid The scale to check
 * @return boolean
 */
function dp_priority_scale_is_assigned($scaleid) {
    global $CFG, $DB, $DP_AVAILABLE_COMPONENTS;

    $count = 0;
    foreach ($DP_AVAILABLE_COMPONENTS as $c) {
        $count += $DB->count_records("dp_{$c}_settings", array('priorityscale' => $scaleid));
    }
    return $count > 0 ? true : false;
}


/**
 * Determine whether a scale is in use or not.
 *
 * "in use" means that items are assigned any of the scale's values.
 * Therefore if we delete this scale or alter its values, it'll cause
 * the data in the database to become corrupt
 *
 * There is an even stricter version of this function:
 * {@link dp_priority_scale_is_assigned()} which tells you if the scale
 * even is assigned to any components of any plan templates.
 *
 * @param int $scaleid The scale to check
 * @return boolean
 */
function dp_priority_scale_is_used($scaleid) {
    global $CFG, $DP_AVAILABLE_COMPONENTS;

    $used = false;
    foreach ($DP_AVAILABLE_COMPONENTS as $component) {
        $component_class = "dp_{$component}_component";
        $component_class_file = $CFG->dirroot . "/totara/plan/components/{$component}/{$component}.class.php";
        if (!is_readable($component_class_file)) {
            continue;
        }
        require_once($component_class_file);
        if (!class_exists($component_class)) {
            continue;
        }
        $used = $used || call_user_func(array($component_class,
            'is_priority_scale_used'), $scaleid);
    }
    return $used;
}


/**
 * A function to display a table list of competency scales
 * @param array $scales the scales to display in the table
 * @return html
 */
function dp_priority_display_table($priorities, $editingon=0) {
    global $CFG, $OUTPUT;

    $sitecontext = context_system::instance();

    // Cache permissions
    $can_edit = has_capability('totara/plan:managepriorityscales', $sitecontext);
    $can_delete = has_capability('totara/plan:managepriorityscales', $sitecontext);

    // Make sure user has capability to edit
    if (!(($can_edit || $can_delete) && $editingon)) {
        $editingon = 0;
    }

    $stredit = get_string('edit');
    $strdelete = get_string('delete');
    $stroptions = get_string('options', 'totara_core');
    $str_moveup = get_string('moveup');
    $str_movedown = get_string('movedown');
    ///
    /// Build page
    ///

    if ($priorities) {
        $table = new html_table();
        $table->head  = array(get_string('scale'), get_string('used'));
        if ($editingon) {
            $table->head[] = $stroptions;
        }

        $table->data = array();
        $spacer = $OUTPUT->spacer(array('width' => 11, 'height' => 11));
        $count = 0;
        $numvalues = count($priorities);
        foreach ($priorities as $priority) {
            $scale_used = dp_priority_scale_is_used($priority->id);
            $scale_assigned = dp_priority_scale_is_assigned($priority->id);
            $count++;
            $line = array();

            $title = html_writer::link(new moodle_url('/totara/plan/priorityscales/view.php', array('id' => $priority->id)), format_string($priority->name));
            if ($count == 1) {
                $title .= ' ('.get_string('default').')';
            }
            $line[] = $title;

            if ($scale_used) {
                $line[] = get_string('yes');
            } else if ($scale_assigned) {
                $line[] = get_string('assignedonly', 'totara_plan');
            } else {
                $line[] = get_string('no');
            }

            $buttons = array();
            if ($editingon) {
                if ($can_edit) {
                    $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/priorityscales/edit.php', array('id' => $priority->id)), new pix_icon('t/edit', $stredit));
                }

                if ($can_delete) {
                    if ($scale_used) {
                        $buttons[] = $OUTPUT->pix_icon('t/delete_grey', get_string('error:nodeletepriorityscaleinuse', 'totara_plan'), 'totara_core',
                            array('class' => 'iconsmall action-icon', 'title' => get_string('error:nodeletepriorityscaleinuse', 'totara_plan')));
                    } else if ($scale_assigned) {
                        $buttons[] = $OUTPUT->pix_icon('t/delete_grey', get_string('error:nodeletepriorityscaleassigned', 'totara_plan'), 'totara_core',
                            array('class' => 'iconsmall action-icon', 'title' => get_string('error:nodeletepriorityscaleassigned', 'totara_plan')));
                    } else {
                        $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/priorityscales/index.php', array('delete' => $priority->id)), new pix_icon('t/delete', $strdelete));
                    }
                }
                // If value can be moved up
                if ($can_edit && $count > 1) {
                    $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/priorityscales/index.php', array('moveup' => $priority->id)), new pix_icon('t/up', $str_moveup));
                } else {
                    $buttons[] = $spacer;
                }

                // If value can be moved down
                if ($can_edit && $count < $numvalues) {
                    $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/priorityscales/index.php', array('movedown' => $priority->id)), new pix_icon('t/down', $str_movedown));
                } else {
                    $buttons[] = $spacer;
                }
                $line[] = implode($buttons, '');
            }

            $table->data[] = $line;
        }
    }
    echo $OUTPUT->heading(get_string('priorityscales', 'totara_plan'));

    if ($priorities) {
        echo html_writer::table($table);
    } else {
        echo html_writer::tag('p', get_string('noprioritiesdefined', 'totara_plan'));
    }

    $button = $OUTPUT->single_button(new moodle_url("edit.php"), get_string('priorityscalecreate', 'totara_plan'), 'get');
    echo $OUTPUT->container($button, "buttons");
}

/**
 * Gets the default priority scale (the one with the lowest sortorder)
 *
 * @return object the priority
 */
function dp_priority_default_scale() {
    global $DB;

    $result = $DB->get_records('dp_priority_scale', null, 'sortorder', '*', '', 1);
    return reset($result);
}

/**
 * Gets the id of the default priority scale (the one with the lowest sortorder)
 *
 * @return object the priority
 */
function dp_priority_default_scale_id() {
    $priority = dp_priority_default_scale();
    if ($priority && isset($priority->id)) {
        return $priority->id;
    } else {
        return false;
    }
}
