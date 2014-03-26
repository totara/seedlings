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
 * competency/evidenceitem/lib.php
 *
 * Library of functions related to competency evidence items
 *
 * Note: Functions in this library should have names beginning with "comp_evitem_",
 * in order to avoid name collisions
 *
 */

/**
 * Return a lookup array of the existing evidence items for a competency, for
 * determining whether the competency already has this evidence item assigned
 * to it.
 *
 * The unique combination key for evidence items is (competencyid, itemtype, iteminstance).
 * The format of the returned array is that for each evidence item assigned to
 * the competency, there's one entry in the array, with its key and its value
 * equal to the evidence item's itemtype, a hyphen, and its iteminstance.
 *
 * @param int $competency_id
 * @return array
 */
function comp_evitem_get_lookup($competency_id) {
    global $DB;

    // Get a list of all the existing evidence items, to grey out the duplicates
    // below. For ease of checking, we'll just pull the itemtype and iteminstance
    // concatenated together with hyphens.
    $existingevidencerecs = $DB->get_records('comp_criteria', array('competencyid' => $competency_id));
    if (!$existingevidencerecs) {
        return array();
    }

    $existingevidencelookup = array();
    foreach ($existingevidencerecs as $rec) {
        // itemtype-iteminstance => itemtype-iteminstance
        $existingevidencelookup["{$rec->itemtype}-{$rec->iteminstance}"] = "{$rec->itemtype}-{$rec->iteminstance}";
    }
    return $existingevidencelookup;
}

/**
 * Print the list of evidence items for a given course, with links to assign them to
 * a specific competency via hierarchy/prefix/competency/evidenceitem/add.php
 *
 * @global object $CFG
 * @param object $course A record from the 'course' table
 * @param int $competency_id
 * @param string $addurl The URL to make the links go to (should be okay to append additional URL parameters with an ampersand on the end)
 */
function comp_evitem_print_course_evitems($course, $competency_id, $addurl ) {
    global $DB;

    $alreadystr = get_string('alreadyselected', 'totara_core');
    $existingevidencelookup = comp_evitem_get_lookup($competency_id);

    // Evidence type available
    $available = false;

    // Activity completion
    $completion_info = new completion_info($course);
    if ($completion_info->is_enabled()) {
        $evidence = $completion_info->get_activities();

        if ($evidence) {
            $available = true;
            foreach ($evidence as $activity) {
                echo html_writer::start_tag('div');
                if (array_key_exists("activitycompletion-{$activity->id}", $existingevidencelookup)) {
                    echo html_writer::tag('span', get_string('activitycompletion', 'totara_hierarchy') . ' - ' . format_string($activity->name) . ' ' . $alreadystr, array('class' => "unclickable"));
                } else {
                    echo html_writer::start_tag('span', array('type' => 'activitycompletion', 'id' => $activity->id));
                    echo $OUTPUT->action_link(new moodle_url('#'), get_string('activitycompletion', 'totara_hierarchy') . ' - ' . format_string($activity->name));
                    echo html_writer::tag('span', get_string('add'), array('class' => 'addbutton'));
                    echo html_writer::end_tag('span');
                }
                echo html_writer::end_tag('div');
            }
        }
    }

    // Course completion
    if ($completion_info->is_enabled() &&
        $completion_info->has_criteria()) {

        $available = true;
        echo html_writer::start_tag('div');
        if (array_key_exists("coursecompletion-{$course->id}", $existingevidencelookup)) {
            echo html_writer::tag('span', get_string('coursecompletion', 'totara_core') . ' ' . $alreadystr, array('class' => "unclickable"));
        } else {
            echo html_writer::start_tag('span', array('class' => 'coursecompletion', 'id' => $course->id));
            echo html_writer::link('#', get_string('coursecompletion'));
            echo html_writer::tag('span', get_string('add'), array('class' => 'addbutton'));
            echo html_writer::end_tag('span');
        }
        echo html_writer::end_tag('div');
    }

    // Course grade
    $course_grade = $DB->get_record_select('grade_items', 'itemtype = ? AND courseid = ?', array('course', $course->id));

    if ($course_grade) {
        $available = true;
        echo html_writer::start_tag('div');
        if (array_key_exists("coursegrade-{$course->id}", $existingevidencelookup)) {
            echo html_writer::tag('span', get_string('coursegrade', 'completion') . ' ' . $alreadystr, array('class' => "unclickable"));
        } else {
            echo html_writer::start_tag('span', array('class' => 'coursegrade', 'id' => $course->id));
            echo html_writer::link('#', get_string('coursegrade', 'completion'));
            echo html_writer::tag('span', get_string('add'), array('class' => 'addbutton'));
            echo html_writer::end_tag('span');
        }
        echo html_writer::end_tag('div');
    }

    // Keep a hidden competency id val for use by javascripts
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'id' => "evitem_competency_id", 'value' => $competency_id));

    if (!$available) {
        echo html_writer::tag('em', get_string('noevidencetypesavailable', 'totara_hierarchy'));
    }
}
