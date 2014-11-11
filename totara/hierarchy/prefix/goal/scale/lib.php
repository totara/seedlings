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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage totara_hierarchy
 */

/**
 * goal/lib.php
 *
 * Library of functions related to goal scales.
 *
 * Note: Functions in this library should have names beginning with "goal_scale",
 * in order to avoid name collisions
 */

/**
 * Determine whether an goal scale is assigned to any frameworks
 *
 * There is a less strict version of this function:
 * {@link goal_scale_is_used()} which tells you if the scale
 * values are actually assigned.
 *
 * @param int $objectiveid
 * @return boolean
 */
function goal_scale_is_assigned($scaleid) {
    global $DB;
    return $DB->record_exists('goal_scale_assignments', array('scaleid' => $scaleid));
}


/**
 * Determine whether a scale is in use or not.
 *
 * "in use" means that items are assigned any of the scale's values.
 * Therefore if we delete this scale or alter its values, it'll cause
 * the data in the database to become corrupt
 *
 * There is an even stricter version of this function:
 * {@link goal_scale_is_assigned()} which tells you if the scale
 * even is assigned to any frameworks
 *
 * @param <type> $scaleid
 * @return boolean
 */
function goal_scale_is_used($scaleid) {
    global $DB;

    // Find any personal goals using this scale in goal_personal.
    $personal_use = $DB->record_exists('goal_personal', array('scaleid' => $scaleid, 'deleted' => 0));

    // Find any company goals using this scale in goal_record.
    $values = $DB->get_fieldset_select('goal_scale_values', 'id', 'scaleid = :scid', array('scid' => $scaleid));
    list($sqlin, $params) = $DB->get_in_or_equal($values, SQL_PARAMS_NAMED);
    $company_sql = "SELECT gr.*
                      FROM {goal_record} gr
                     WHERE gr.scalevalueid {$sqlin}
                       AND deleted = 0";

    $company_use = $DB->get_records_sql($company_sql, $params);

    return $personal_use || !empty($company_use);
}


/**
 * Returns the ID of the scale value that is marked as proficient, if
 * there is only one. If there are none, or multiple it returns false
 *
 * @param integer $scaleid ID of the scale to check
 * @return integer|false The ID of the sole proficient scale value or false
 */
function goal_scale_only_proficient_value($scaleid) {
    global $DB;
    $sql = "
        SELECT csv.id
        FROM {goal_scale_values} csv
        INNER JOIN (
            SELECT scaleid, SUM(proficient) AS sum
            FROM {goal_scale_values}
            GROUP BY scaleid
        ) count
        ON count.scaleid = csv.scaleid
        WHERE proficient = 1
            AND sum = 1
            AND csv.scaleid = ?";

    return $DB->get_field_sql($sql, array($scaleid));
}


/**
 * Get goal scales available for use by frameworks
 *
 * @return array
 */
function goal_scales_available() {
    global $DB;

    $sql = "
        SELECT
            id,
            name
        FROM {goal_scale} scale
        WHERE EXISTS
        (
            SELECT
                1
            FROM
                {goal_scale_values} scaleval
            WHERE
                scaleval.scaleid = scale.id
        )
        ORDER BY
            name ASC
    ";

    return $DB->get_records_sql($sql);
}


/**
 * A function to display a table list of goal scales.
 * No return - this echos html to output.
 *
 * @param array $scales the scales to display in the table
 */
function goal_scale_display_table($scales) {
    global $OUTPUT;

    $sitecontext = context_system::instance();

    // Cache permissions.
    $can_edit = has_capability('totara/hierarchy:updategoalscale', $sitecontext);
    $can_delete = has_capability('totara/hierarchy:deletegoalscale', $sitecontext);
    $can_add = has_capability('totara/hierarchy:creategoalscale', $sitecontext);
    $can_view = has_capability('totara/hierarchy:viewgoalscale', $sitecontext);

    // Make sure user has capability to view the table.
    if (!$can_view) {
        return;
    }

    $stredit = get_string('edit');
    $strdelete = get_string('delete');
    $stroptions = get_string('options', 'totara_core');

    //
    // Build page.
    //

    if ($scales) {
        $table = new html_table();
        $table->head  = array(get_string('scale'), get_string('used'));
        if ($can_edit || $can_delete) {
            $table->head[] = $stroptions;
        }

        $table->data = array();
        foreach ($scales as $scale) {
            $scale_used = goal_scale_is_used($scale->id);
            $scale_assigned = goal_scale_is_assigned($scale->id);
            $line = array();
            $line[] = $OUTPUT->action_link(new moodle_url('/totara/hierarchy/prefix/goal/scale/view.php',
                array('id' => $scale->id, 'prefix' => 'goal')), format_string($scale->name));
            if ($scale_used) {
                $line[] = get_string('yes');
            } else if ($scale_assigned) {
                $line[] = get_string('assignedonly', 'totara_hierarchy');
            } else {
                $line[] = get_string('no');
            }

            $buttons = array();
            if ($can_edit || $can_delete) {
                if ($can_edit) {
                    $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/hierarchy/prefix/goal/scale/edit.php',
                        array('id' => $scale->id, 'prefix' => 'goal')),
                        new pix_icon('t/edit', $stredit), null, array('title' => $stredit));
                }

                if ($can_delete) {
                    if ($scale_used) {
                        $buttons[] = $OUTPUT->pix_icon('t/delete_grey',
                                get_string('error:nodeletegoalscaleinuse', 'totara_hierarchy'), 'totara_core',
                                array('class' => 'iconsmall disabled',
                                      'title' => get_string('error:nodeletegoalscaleinuse', 'totara_hierarchy')));
                    } else if ($scale_assigned) {
                        $buttons[] = $OUTPUT->pix_icon('t/delete_grey',
                                get_string('error:nodeletegoalscaleassigned', 'totara_hierarchy'), 'totara_core',
                                array('class' => 'iconsmall disabled',
                                      'title' => get_string('error:nodeletegoalscaleassigned', 'totara_hierarchy')));
                    } else {
                        $buttons[] = $OUTPUT->action_icon(new moodle_url('/totara/hierarchy/prefix/goal/scale/delete.php',
                            array('id' => $scale->id, 'prefix' => 'goal')), new pix_icon('t/delete', $strdelete), null,
                            array('title' => $strdelete));
                    }
                }
                $line[] = implode($buttons, '');
            }

            $table->data[] = $line;
        }
    }

    echo $OUTPUT->heading(get_string('goalscales', 'totara_hierarchy'));

    if ($scales) {
        echo html_writer::table($table);
    } else {
        echo html_writer::tag('p', get_string('noscalesdefined', 'totara_hierarchy'));
    }

    if ($can_add) {
        $buttonurl = new moodle_url('/totara/hierarchy/prefix/goal/scale/edit.php', array('prefix' => 'goal'));
        echo html_writer::tag('div',
            $OUTPUT->single_button($buttonurl, get_string('scalesgoalcustomcreate', 'totara_hierarchy'), 'get')
            . $OUTPUT->help_icon('goalscalesgeneral', 'totara_hierarchy'),
            array('class' => 'buttons'));
    }
}
