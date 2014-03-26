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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');
require_once($CFG->dirroot . '/totara/plan/components/course/rpl_form.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

$id = required_param('id', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);

require_login();
$plan = new development_plan($id);

//Permissions check
$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/totara/plan/components/course/rpl.php', array('id' => $id, 'courseid' => $courseid)));
if (!has_capability('totara/plan:accessanyplan', $systemcontext) && ($plan->get_setting('view') < DP_PERMISSION_ALLOW)) {
        print_error('error:nopermissions', 'totara_plan');
}

$userid = $plan->userid;
$componentname = 'course';
$component = $plan->get_component($componentname);

if ($component->get_setting('setcompletionstatus') != DP_PERMISSION_ALLOW) {
    print_error('error:coursecompletionpermission', 'totara_plan');
}

// Check completion is enabled for course
$course = new stdClass();
$course->id = $courseid;
$info = new completion_info($course);

if (!$info->is_enabled()) {
    print_error('completionnotenabled', 'completion', $component->get_url());
}

// Check course RPLs are enabled
if (!$CFG->enablecourserpl) {
    print_error('error:courserplsaredisabled', 'completion', $component->get_url());
}

if ($rpl = $DB->get_record('course_completions', array('userid' => $userid, 'course' => $courseid))) {
    $rpltext = $rpl->rpl;
    $rplid = $rpl->id;
} else {
    $rpltext = '';
    $rplid = 0;
}

$mform = new totara_course_rpl_form(null, compact('id','rplid','rpltext','courseid','userid'));

$returnurl = $component->get_url();

if ($mform->is_cancelled()) {
    redirect($returnurl);
}
if ($fromform = $mform->get_data()) {
    if (empty($fromform->submitbutton)) {
        totara_set_notification(get_string('error:unknownbuttonclicked', 'totara_plan'), $returnurl);
    }

    $rpl = $fromform->rpl;

    // Get completion object
    $params = array(
        'userid'    => $fromform->userid,
        'course'    => $fromform->courseid,
        'id'        => isset($fromform->rplid) ? $fromform->rplid : null
    );

    // Completion
    // Load course completion
    $completion = new completion_completion($params);

    /// Complete user
    if (strlen($rpl)) {
         $completion->rpl = $rpl;
        $completion->mark_complete();
        $alert_detail = new stdClass();
        $alert_detail->itemname = $DB->get_field('course', 'fullname', array('id' => $completion->course));
        $alert_detail->text = get_string('completedviarpl', 'totara_plan', $completion->rpl);
        $component->send_component_complete_alert($alert_detail);

        add_to_log(SITEID, 'plan', 'completed course', "component.php?id={$plan->id}&amp;c=course", "{$alert_detail->itemname} RPL set (ID:{$completion->course})");

        // If no RPL, uncomplete user, and let aggregation do its thing
    } else {
        $completion->delete();
    }

    totara_set_notification(
        get_string('rplupdated', 'totara_plan'),
        $returnurl,
        array('class' => 'notifysuccess')
    );
}


$fullname = $plan->name;
$pagetitle = format_string(get_string('learningplan', 'totara_plan').': '.$fullname);
dp_get_plan_base_navlinks($plan->userid);
$PAGE->navbar->add($fullname, $plan->get_display_url());
$PAGE->navbar->add(get_string($component->component, 'totara_plan'));


///
/// Display page
///
$PAGE->set_title($pagetitle);
echo $OUTPUT->header();

// Plan menu
echo dp_display_plans_menu($plan->userid,$plan->id,$plan->role);

// Plan page content
echo $OUTPUT->container_start('', 'dp-plan-content');

print $plan->display_plan_message_box();

echo $OUTPUT->heading($fullname);
print $plan->display_tabs($componentname);

$mform->display();

echo $OUTPUT->container_end();
echo $OUTPUT->footer();
