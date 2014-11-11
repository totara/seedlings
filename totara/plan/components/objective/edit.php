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

/**
 * A page to handle editing an objective in a plan.
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once($CFG->dirroot . '/totara/plan/components/objective/edit_form.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

global $USER;

require_login();

///
/// Load parameters
///
$planid = required_param('id', PARAM_INT);
$objectiveid = optional_param('itemid', null, PARAM_INT); // Objective id; 0 if creating a new objective
$deleteflag = optional_param('d', false, PARAM_BOOL);
$deleteyes = optional_param('deleteyes', false, PARAM_BOOL);
$deleteno = optional_param('deleteno', null, PARAM_TEXT);
if ($deleteno == null) {
    $deleteno = false;
} else {
    $deleteno = true;
}

///
/// Load data
///
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_pagelayout('noblocks');
$PAGE->set_url(new moodle_url('/totara/plan/components/objective/edit.php', array('id' => $planid)));
$plan = new development_plan($planid);

$ownplan = ($USER->id == $plan->userid);
$menuitem = ($ownplan) ? 'learningplans' : 'myteam';
$PAGE->set_totara_menu_selected($menuitem);

$plancompleted = $plan->status == DP_PLAN_STATUS_COMPLETE;
$componentname = 'objective';
$component = $plan->get_component($componentname);
if ($objectiveid == null) {
    $objective = new stdClass();
    $objective->itemid = 0;
    $objective->description = '';
    $action = 'add';
} else {
    if (!$objective = $DB->get_record('dp_plan_objective', array('id' => $objectiveid))) {
        print_error('error:objectiveidincorrect', 'totara_plan');
    }
    $objective->itemid = $objective->id;
    $objective->id = $objective->planid;
    unset($objective->planid);

    if ($deleteflag) {
        $action = 'delete';
    } else {
        $action = 'edit';
    }
}

$objallurl = $component->get_url();
if ($objectiveid) {
    $objviewurl = "{$CFG->wwwroot}/totara/plan/components/objective/view.php?id={$planid}&amp;itemid={$objectiveid}";
} else {
    $objviewurl = $objallurl;
}


///
/// Permissions check
///
require_capability('totara/plan:accessplan', $context);
if (!$component->can_update_items()) {
    print_error('error:cannotupdateobjectives', 'totara_plan');
}
if ($plancompleted) {
    print_error('plancompleted', 'totara_plan');
}

$objective->descriptionformat = FORMAT_HTML;
$objective = file_prepare_standard_editor($objective, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                          'totara_plan', 'dp_plan_objective', $objective->itemid);
$mform = $component->objective_form($objectiveid);
if (isset($objective->duedate)) {
    $objective->duedate = userdate($objective->duedate, get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false);
}
$mform->set_data($objective);

if ($deleteyes) {
    require_sesskey();
    if (!$component->delete_objective($objectiveid)) {
        print_error('error:objectivedeleted', 'totara_plan');
    } else {
        totara_set_notification(get_string('objectivedeleted', 'totara_plan'), $objallurl, array('class' => 'notifysuccess'));
    }
} else if ($deleteno) {
    redirect($objallurl);

} else if ($mform->is_cancelled()) {

    if ($action == 'add') {
        redirect($objallurl);
    } else {
        redirect($objviewurl);
    }

} if ($data = $mform->get_data()) {
    // A New objective
    if (empty($data->itemid)) {
        $result = $component->create_objective(
                $data->fullname,
                isset($data->description) ? $data->description : null,
                isset($data->priority) ? $data->priority : null,
                !empty($data->duedate) ? totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'), $data->duedate) : null,
                isset($data->scalevalueid) ? $data->scalevalueid : null
        );
        if (!$result) {
            print_error('error:objectiveupdated', 'totara_plan');
        } else {
            $data->itemid = $result;
            $notification = get_string('objectivecreated', 'totara_plan');
            add_to_log(SITEID, 'plan', 'created objective', "component.php?id={$planid}&amp;c=objective", $data->fullname);
        }
    } else {
        $record = new stdClass();
        $record->id = $data->itemid;
        $record->planid = $data->id;
        $record->fullname = $data->fullname;
        $record->description = ''; //handled later
        $record->priority = isset($data->priority)?$data->priority:null;
        $record->duedate = !empty($data->duedate)? totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'), $data->duedate):null;
        $record->scalevalueid = $data->scalevalueid;
        $record->approved = $component->approval_status_after_update();

        $DB->update_record('dp_plan_objective', $record);
        // Only send notificaitons when plan not draft
        if ($plan->status != DP_PLAN_STATUS_UNAPPROVED) {
            // Check for changes and send alerts accordingly
            $updated = false;
            foreach (array('fullname', 'description', 'priority', 'duedate', 'approved') as $attribute) {
                if ($record->$attribute != $objective->$attribute) {
                    $updated = $attribute;
                    break;
                }
            }
            // updated?
            if ($updated) {
                $component->send_edit_alert($record, $updated);
            }
            // status?
            if ($record->scalevalueid != $objective->scalevalueid) {
                $component->send_status_alert($record);
            }
        }
        add_to_log(SITEID, 'plan', 'updated objective', "component.php?id={$record->planid}&amp;c=objective", $record->fullname);
        $notification = get_string('objectiveupdated', 'totara_plan');
    }
    $data = file_postupdate_standard_editor($data, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_plan', 'dp_plan_objective', $data->itemid);
    $DB->set_field('dp_plan_objective', 'description', $data->description, array('id' => $data->itemid));
    totara_set_notification($notification, $objviewurl, array('class' => 'notifysuccess'));
}

///
/// Display page
///
$fullname = $plan->name;
$pagetitle = format_string(get_string('learningplan', 'totara_plan').': '.$fullname);
dp_get_plan_base_navlinks($plan->userid);
$PAGE->navbar->add($fullname, new moodle_url('/totara/plan/view.php', array('id' => $planid)));
$PAGE->navbar->add(get_string($component->component, 'totara_plan'));
$PAGE->set_title($pagetitle);
$PAGE->set_heading(format_string($SITE->fullname));
echo $OUTPUT->header();

// Plan menu
echo dp_display_plans_menu($plan->userid,$plan->id,$plan->role);

// Plan page content
echo $OUTPUT->container_start('', 'dp-plan-content');
print $plan->display_plan_message_box();
print $plan->display_tabs($componentname);

switch($action) {
    case 'add':
        echo $OUTPUT->heading(get_string('addnewobjective', 'totara_plan'));
        print $component->display_back_to_index_link();
        $mform->display();
        break;
    case 'delete':
        echo $OUTPUT->heading(get_string('deleteobjective', 'totara_plan'));
        print $component->display_back_to_index_link();
        $component->display_objective_detail($objectiveid);
        require_once($CFG->dirroot . '/totara/plan/components/evidence/evidence.class.php');
        $evidence = new dp_evidence_relation($plan->id, $componentname, $objectiveid);
        echo $evidence->display_delete_warning();
        echo $OUTPUT->confirm(get_string('deleteobjectiveareyousure', 'totara_plan'),
                new moodle_url('/totara/plan/components/objective/edit.php',
                    array('id' => $planid, 'itemid' => $objectiveid, 'deleteyes' => 'Yes', 'sesskey' => sesskey())),
                new moodle_url('/totara/plan/components/objective/edit.php',
                        array('id' => $planid, 'itemid' => $objectiveid, 'deleteno' => 'No')));
        break;
    case 'edit':
        echo $OUTPUT->heading(get_string('editobjective', 'totara_plan', $objective->fullname));
        print $component->display_back_to_index_link();
        $mform->display();
        break;
}

echo $OUTPUT->container_end();

echo $OUTPUT->footer();
