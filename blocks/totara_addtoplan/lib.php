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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */
require_once($CFG->dirroot . '/totara/plan/lib.php');

function totara_block_addtoplan_get_content($courseid, $userid) {
    global $CFG, $DB, $OUTPUT;

    $plans = dp_get_plans($userid, array(DP_PLAN_STATUS_UNAPPROVED, DP_PLAN_STATUS_PENDING, DP_PLAN_STATUS_APPROVED));

    $course_include = $CFG->dirroot . '/totara/plan/components/course/course.class.php';
    if (file_exists($course_include)) {
        require_once($course_include);
    } else {
        return '';
    }

    // Get plans that contain course to exclude them from the list
    $plans_with_course = dp_course_component::get_plans_containing_item($courseid, $userid);

    if ($plans_with_course) {
        if ($exclude_plans = array_values($plans_with_course)) {
            foreach ($exclude_plans as $eid) {
                unset($plans[$eid]);
            }
        }
    }

    $html = $OUTPUT->container_start(null, 'block_totara_addtoplan_text');

    if (!empty($plans)) {
        $html .= html_writer::tag('p', get_string('addtoplanhint', 'block_totara_addtoplan'));
        $html .= $OUTPUT->container_start('buttons plan-add-item-button-wrapper', 'block_addtoplan_button');
        $html .= $OUTPUT->container_start('singlebutton dp-plan-assign-button');
        $html .= $OUTPUT->container_start();
        $html .= html_writer::start_tag('form');
        $planoptions = array();
        foreach ($plans as $plan) {
            if (empty($plan->name)) {
                continue;
            }
            $planoptions[$plan->id] = $plan->name;
        }
        $html .= html_writer::select($planoptions, 'block_addtoplan_selector', '', false, array('id' => 'block_addtoplan_selector'));
        $html .= html_writer::empty_tag('input', array('type' => 'submit', 'class' => 'plan-add-item-button', 'id' => 'show-course-dialog', 'value' => get_string('add', 'block_totara_addtoplan')));
        $html .= html_writer::end_tag('form');
        $html .= $OUTPUT->container_end();
        $html .= $OUTPUT->container_end();
        $html .= $OUTPUT->container_end();
    }

    // Display list of plans
    if (!empty($exclude_plans) && is_array($exclude_plans)) {
        // Get correct string
        $planstring = '';
        $planstring .= (empty($plans) ? 'course' : 'coursealready');
        $planstring .= 'inplan';
        $planstring .= (count($exclude_plans) == 1 ? '' : 's');

        $html .= html_writer::tag('p', html_writer::tag('strong', get_string($planstring, 'block_totara_addtoplan')));
        $inplans = array();
        foreach ($exclude_plans as $planid) {
            $inplans[$planid] = $DB->get_field('dp_plan', 'name', array('id' => $planid));
        }
        $html .= html_writer::alist($inplans, null, 'ul');
    }

    $html .= $OUTPUT->container_end();  // block_totara_addtoplan_text

    return $html;
}
?>
