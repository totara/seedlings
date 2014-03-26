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
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
///
/// Setup / loading data
///

// Plan id
$id = required_param('id', PARAM_INT);

//get the delimited lists of competency ids and course ids in the form $compid_$courseid
$linkedcoursedata = optional_param_array('linkedcourses', array(), PARAM_TEXT);
$mandatorycoursedata = optional_param('mandatory', '', PARAM_TEXT);

$linkedcourses = array();
$mandatorycourses = array();

//organise the mandatorycourse data
$data = explode(',', $mandatorycoursedata);
foreach ($data as $compitem) {
    list($compid, $courseid) = explode ('_', $compitem);
    if (!isset($mandatorycourses[$compid])) {
        $mandatorycourses[$compid] = array();
    }
    $mandatorycourses[$compid][] = $courseid;
}

//organise the linkedcourse data
foreach ($linkedcoursedata as $compitem) {
    list($compid, $courseid) = explode ('_', $compitem);
    if (!isset($linkedcourses[$compid])) {
        $linkedcourses[$compid] = array();
    }
    $linkedcourses[$compid][] = $courseid;
}

// Updated course lists
$idlist = optional_param('update', null, PARAM_SEQUENCE);
if ($idlist == null) {
    $idlist = array();
}
else {
    $idlist = explode(',', $idlist);
}

$plan = new development_plan($id);
$componentname = 'competency';
$component = $plan->get_component($componentname);

// Basic access control checks
if (!$component->can_update_items()) {
    print_error('error:cannotupdateitems', 'totara_plan');
}

/* TODO re-add when messages in transactions re-enabled MDL-30029
$transaction = $DB->start_delegated_transaction();
*/
$component->update_assigned_items($idlist);
// now assign the linked courses
if (count($linkedcourses) != 0) {
    foreach ($linkedcourses as $compid => $courses) {
        foreach ($courses as $key => $course) {
            // add course if it's not already in this plan
            // @todo what if course is assigned but not approved?
            if (!$plan->get_component('course')->is_item_assigned($course)) {
                // Last "false" is because it was assigned automatically
                $plan->get_component('course')->assign_new_item($course, true, false);
            }
            // Now we need to grab the assignment ID
            $assignmentid = $DB->get_field('dp_plan_course_assign', 'id', array('planid' => $plan->id, 'courseid' => $course), MUST_EXIST);

            // Get the competency assignment ID from the competency
            $compassignid = $DB->get_field('dp_plan_competency_assign', 'id', array('competencyid' => $compid, 'planid' => $plan->id), MUST_EXIST);

            // Check if this is mandatory
            if (in_array($course, $mandatorycourses[$compid])) {
                $mandatory = 'course';
            } else {
                $mandatory = '';
            }
            // Create relation
            $plan->add_component_relation('competency', $compassignid, 'course', $assignmentid, $mandatory);
        }
    }
}

/* TODO re-add when messages in transactions re-enabled MDL-30029
$transaction->allow_commit();
*/
echo $component->display_list();
echo $plan->display_plan_message_box();
