<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas
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
 * totara/plan/objectivescales/lib.php
 *
 * Library of functions related to Objective priorities.
 */


/**
 * Determine whether an objective scale is assigned to any plan templates
 *
 * There is a less strict version of this function:
 * {@link dp_objective_scale_is_used()} which tells you if the scale
 * values are actually assigned.
 *
 * @param int $scaleid The scale to check
 * @return boolean
 */
function dp_objective_scale_is_assigned($scaleid) {
    global $DB;
    return $DB->record_exists('dp_objective_settings', array('objectivescale' => $scaleid));
}

/**
 * Determine whether a scale is in use or not.
 *
 * "in use" means that items are assigned any of the scale's values.
 * Therefore if we delete this scale or alter its values, it'll cause
 * the data in the database to become corrupt
 *
 * There is an even stricter version of this function:
 * {@link dp_objective_scale_is_assigned()} which tells you if the scale
 * even is assigned to any plan templates.
 *
 * @param int $scaleid The scale to check
 * @return boolean
 */
function dp_objective_scale_is_used($scaleid) {
    global $DB;

    $sql = "SELECT
                o.id
            FROM
                {dp_plan_objective} o
            LEFT JOIN
                {dp_objective_scale_value} osv
            ON osv.id = o.scalevalueid
            WHERE osv.objscaleid = ?
    ";

    return $DB->record_exists_sql($sql, array($scaleid));
}

/**
 * A function to display a table list of competency scales
 * @param array $scales the scales to display in the table
 * @return html
 */
function dp_objective_display_table($objectives, $editingon=0) {
    global $CFG, $OUTPUT;

    $sitecontext = context_system::instance();

    // Cache permissions
    $can_edit = has_capability('totara/plan:manageobjectivescales', $sitecontext);
    $can_delete = has_capability('totara/plan:manageobjectivescales', $sitecontext);

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

    if ($objectives) {
        $table = new html_table();
        $table->head  = array(get_string('scale'), get_string('used'));
        if ($editingon) {
            $table->head[] = $stroptions;
        }

        $table->data = array();
        $spacer = $OUTPUT->spacer(array('width' => 11, 'height' => 11));
        $count = 0;
        $numvalues = count($objectives);
        foreach ($objectives as $objective) {
            $buttons = array();
            $scale_used = dp_objective_scale_is_used($objective->id);
            $scale_assigned = dp_objective_scale_is_assigned($objective->id);
            $count++;
            $line = array();

            $title = $OUTPUT->action_link(new moodle_url("/totara/plan/objectivescales/view.php", array('id' => $objective->id)), format_string($objective->name));
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

            if ($editingon) {
                if ($can_edit) {
                    $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/objectivescales/edit.php', array('id' => $objective->id)), new pix_icon('t/edit', $stredit));
                }

                if ($can_delete) {
                    if ($scale_used) {
                        $buttons[] = $OUTPUT->pix_icon('t/delete_grey', get_string('error:nodeleteobjectivescaleinuse', 'totara_plan'), 'totara_core',
                            array('class' => 'iconsmall action-icon', 'title' => get_string('error:nodeleteobjectivescaleinuse', 'totara_plan')));
                    } else if ($scale_assigned) {
                        $buttons[] = $OUTPUT->pix_icon('t/delete_grey', get_string('error:nodeleteobjectivescaleassigned', 'totara_plan'), 'totara_core',
                            array('class' => 'iconsmall action-icon', 'title' => get_string('error:nodeleteobjectivescaleassigned', 'totara_plan')));
                    } else {
                        $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/objectivescales/index.php', array('delete' => $objective->id)), new pix_icon('t/delete', $strdelete));
                    }
                }

                // If value can be moved up
                if ($can_edit && $count > 1) {
                    $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/objectivescales/index.php', array('moveup' => $objective->id)), new pix_icon('t/up', $str_moveup));
                } else {
                    $buttons[] = $spacer;
                }

                // If value can be moved down
                if ($can_edit && $count < $numvalues) {
                    $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/plan/objectivescales/index.php', array('movedown' => $objective->id)), new pix_icon('t/down', $str_movedown));
                } else {
                    $buttons[] = $spacer;
                }
                $line[] = implode($buttons, '');
            }

            $table->data[] = $line;
        }
    }
    echo $OUTPUT->heading(get_string('objectivescales', 'totara_plan'));

    if ($objectives) {
        echo html_writer::table($table);
    } else {
        echo html_writer::tag('p', get_string('noobjectivesdefined', 'totara_plan'));
    }

    $button = $OUTPUT->single_button(new moodle_url("edit.php"), get_string('objectivesscalecreate', 'totara_plan'), 'get');
    echo $OUTPUT->container($button, "buttons");
}

/**
 * Gets the default objective scale (the one with the lowest sortorder)
 *
 * @return object|false the objective if found
 */
function dp_objective_default_scale() {
    global $DB;
    $objective = $DB->get_records('dp_objective_scale', null, 'sortorder', '*', 0, 1);
    return reset($objective);
}

/**
 * Gets the id of the default objective scale (the one with the lowest sortorder)
 *
 * @return integer|false the objective id if found
 */
function dp_objective_default_scale_id() {
    $objective = dp_objective_default_scale();
    if ($objective && isset($objective->id)) {
        return $objective->id;
    } else {
        return false;
    }
}
