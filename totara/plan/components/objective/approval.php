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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

require_login();

$id = required_param('id', PARAM_INT); // plan id
$caid = required_param('itemid', PARAM_INT); // objective assignment id
$action = required_param('action', PARAM_TEXT); // what to do
$confirm = optional_param('confirm', 0, PARAM_INT); // confirm the action

$plan = new development_plan($id);
$componentname = 'objective';
$component = $plan->get_component($componentname);
$currenturl = new moodle_url('/totara/plan/components/objective/approval.php', array('id' => $id, 'itemid' => $caid, 'action' => $action));
$returnurl = $component->get_url();
$canapproveobjective = $component->get_setting('updateobjective') == DP_PERMISSION_APPROVE;

if ($confirm) {
    if (!confirm_sesskey()) {
        totara_set_notification(get_string('confirmsesskeybad','error'), $returnurl);
    }
    if (!$canapproveobjective) {
        // no permission to complete the action
        totara_set_notification(get_string('nopermission', 'totara_plan'),
            $returnurl);
        die();
    }

    $todb = new stdClass();
    $todb->id = $caid;
    if ($action == 'decline') {
        $todb->approved = DP_APPROVAL_DECLINED;
    } else if ($action == 'approve') {
        $todb->approved = DP_APPROVAL_APPROVED;
    }

    $DB->update_record('dp_plan_objective', $todb);
    //@todo send notifications/emails
    totara_set_notification(get_string('request'.$action, 'totara_plan'), $returnurl, array('class' => 'notifysuccess'));

}

$fullname = $plan->name;
$pagetitle = format_string(get_string('learningplan', 'totara_plan').': '.$fullname);
dp_get_plan_base_navlinks($plan->userid);
$PAGE->navbar->add($fullname, new moodle_url('/totara/plan/view.php', array('id' => $planid)));
$PAGE->navbar->add(get_string($component->component, 'totara_plan'), $component->get_url());
$PAGE->navbar->add(get_string('itemapproval', 'totara_plan'));
$PAGE->set_title($pagetitle);

echo $OUTPUT->header();

echo $OUTPUT->heading($fullname);

echo $OUTPUT->confirm(get_string('confirmrequest'.$action, 'totara_plan'), $currenturl.'&amp;confirm=1&amp;sesskey='.sesskey(), $returnurl);

$component->display_objective_detail($caid);

echo $OUTPUT->footer();
