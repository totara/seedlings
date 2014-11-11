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
 * @author Maria Torres <maria.torres@totaralms.com>
 * @package totara
 * @subpackage totara_plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('totara/plan:accessplan', $context);

///
/// Setup / loading data
///

// Plan id
$id = required_param('planid', PARAM_INT);
$competencyid = required_param('competencyid', PARAM_INT);

// Updated course lists
$idlist = optional_param('update', null, PARAM_SEQUENCE);
if ($idlist == null) {
    $idlist = array();
} else {
    $idlist = explode(',', $idlist);
}

$plan = new development_plan($id);
$plancompleted = $plan->status == DP_PLAN_STATUS_COMPLETE;
$component = $plan->get_component('competency');

// Basic access control checks
if (!$component->can_update_items()) {
    print_error('error:cannotupdateitems', 'totara_plan');
}

if ($plancompleted) {
    print_error('plancompleted', 'totara_plan');
}

// Get mandatory list
$mandatory_list = $component->get_mandatory_linked_components($competencyid, 'competency');

// Assign the linked courses
if (count($idlist) != 0) {
    foreach ($idlist as $key => $course) {
        // Add course if it's not already in this plan
        if (!$plan->get_component('course')->is_item_assigned($course)) {
            // Last "false" is because it was assigned automatically
            $plan->get_component('course')->assign_new_item($course, true, false);
        }
        // Now we need to grab the assignment ID
        $assignmentid = $DB->get_field('dp_plan_course_assign', 'id', array('planid' => $plan->id, 'courseid' => $course), MUST_EXIST);

        // Check if this is mandatory
        $mandatory = in_array($assignmentid, $mandatory_list) ? 'course' : '';

        // Create relation
        $plan->add_component_relation('competency', $competencyid, 'course', $assignmentid, $mandatory);
    }
}

if ($linkedcourses = $component->get_linked_components($competencyid, 'course')) {
    echo $plan->get_component('course')->display_linked_courses($linkedcourses, $mandatory_list);
} else {
    $coursename = get_string('courseplural', 'totara_plan');
    echo html_writer::tag('p',
        get_string('nolinkedx', 'totara_plan', strtolower($coursename)),
        array('class' => 'noitems-assigncourses'));
}