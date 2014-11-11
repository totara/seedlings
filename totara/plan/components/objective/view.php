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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Peter Bulmer <peterb@catalyst.net.nz>
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');
require_once($CFG->dirroot . '/totara/plan/components/objective/edit_form.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once($CFG->dirroot . '/totara/plan/components/evidence/evidence.class.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

$id = required_param('id', PARAM_INT); // plan id
$caid = required_param('itemid', PARAM_INT); // objective assignment id
$action = optional_param('action', 'view', PARAM_TEXT);

require_login();
$plan = new development_plan($id);

//Permissions check
$systemcontext = context_system::instance();
if (!has_capability('totara/plan:accessanyplan', $systemcontext) && ($plan->get_setting('view') < DP_PERMISSION_ALLOW)) {
        print_error('error:nopermissions', 'totara_plan');
}

// Check the item is in this plan
if (!$DB->record_exists('dp_plan_objective', array('planid' => $plan->id, 'id' => $caid))) {
    print_error('error:itemnotinplan', 'totara_plan');
}

$plancompleted = $plan->status == DP_PLAN_STATUS_COMPLETE;
$componentname = 'objective';
$component = $plan->get_component($componentname);

$evidence = new dp_evidence_relation($id, $componentname, $caid);

$objectivename = get_string($componentname, 'totara_plan');
$coursename = get_string('courseplural', 'totara_plan');
$currenturl = new moodle_url('/totara/plan/components/objective/view.php', array('id' => $id, 'itemid' => $caid));
$canupdate = $component->can_update_items();

$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('noblocks');
$PAGE->set_url($currenturl);
$ownplan = ($USER->id == $plan->userid);
$menuitem = ($ownplan) ? 'learningplans' : 'myteam';
$PAGE->set_totara_menu_selected($menuitem);

/// Javascript stuff
// If we are showing dialog
if ($canupdate) {
    // Setup lightbox
    local_js(array(
        TOTARA_JS_DIALOG,
        TOTARA_JS_TREEVIEW
    ));

    $sesskey = sesskey();
    $PAGE->requires->string_for_js('save', 'totara_core');
    $PAGE->requires->string_for_js('cancel', 'moodle');
    $PAGE->requires->string_for_js('addlinkedcourses', 'totara_plan');
    $PAGE->requires->string_for_js('addlinkedevidence', 'totara_plan');

    $jsmodule = array(
                                'name' => 'totara_plan_objective_find_course',
                                'fullpath' => '/totara/plan/components/objective/find-course.js',
                                'requires' => array('json'));
    $PAGE->requires->js_init_call('M.totara_plan_objective_find_course.init', array('args' => '{"plan_id":'.$id.',"objective_id":'.$caid.'}'), false, $jsmodule);

        // Get evidence picker
    $jsmodule_evidence = array(
        'name' => 'totara_plan_find_evidence',
        'fullpath' => '/totara/plan/components/evidence/find-evidence.js',
        'requires' => array('json'));
    $PAGE->requires->js_init_call('M.totara_plan_find_evidence.init',
            array('args' => '{"plan_id":'.$id.', "component_name":"'.$componentname.'", "item_id":'.$caid.'}'),
            false, $jsmodule_evidence);
}

// Check if we are performing an action
if ($data = data_submitted() && $canupdate) {
    require_sesskey();

    if ($action === 'removelinkedcourses' && !$plan->is_complete()) {
        $deletions = array();
        // Load existing list of linked courses
        $fullidlist = $component->get_linked_components($caid, 'course');
        // Grab all linked items for deletion
        $course_assigns = optional_param_array('delete_linked_course_assign', array(), PARAM_BOOL);
        if ($course_assigns) {
            foreach ($course_assigns as $linkedid => $delete) {
                if (!$delete) {
                    continue;
                }

                $deletions[] = $linkedid;
            }

            if ($fullidlist && $deletions) {
                $newidlist = array_diff($fullidlist, $deletions);
                $component->update_linked_components($caid, 'course', $newidlist);
            }
        }

        if ($deletions) {
            totara_set_notification(get_string('selectedlinkedcoursesremovedfromobjective', 'totara_plan'), $currenturl, array('class' => 'notifysuccess'));
        } else {
            redirect($currenturl);
        }
        die();
    }


    if ($action === 'removelinkedevidence' && !$plan->is_complete()) {
        $selectedids = optional_param_array('delete_linked_evidence', array(), PARAM_BOOL);
        $evidence->remove_linked_evidence($selectedids, $currenturl);
    }
}


$mform = $component->objective_form($caid);

if ($data = $mform->get_data()) {
    if (isset($data->edit)) {
        redirect("{$CFG->wwwroot}/totara/plan/components/objective/edit.php?id={$id}&itemid={$caid}");
    } else if (isset($data->delete)) {
        redirect("{$CFG->wwwroot}/totara/plan/components/objective/edit.php?id={$id}&itemid={$caid}&d=1");
    }
}

$fullname = $plan->name;
$pagetitle = format_string(get_string('learningplan', 'totara_plan').': '.$fullname);
$PAGE->set_title($pagetitle);
$PAGE->set_heading(format_string($SITE->fullname));

dp_get_plan_base_navlinks($plan->userid);

$PAGE->navbar->add($fullname, new moodle_url('/totara/plan/view.php', array('id' => $id)));
$PAGE->navbar->add(get_string($component->component, 'totara_plan'), $component->get_url());
$PAGE->navbar->add(get_string('viewitem', 'totara_plan'));

$plan->print_header($componentname);

echo $component->display_back_to_index_link();
$component->display_objective_detail($caid, true);


if ($plan->get_component('course')->get_setting('enabled')) {
    echo html_writer::empty_tag('br');
    echo $OUTPUT->heading(get_string('linkedx', 'totara_plan', $coursename), 3);

    $class = 'plan-remove-selected';
    $url = new moodle_url($currenturl, array('action' => 'removelinkedcourses', 'sesskey' => sesskey()));
    echo html_writer::start_tag('form', array('id' => 'dp-component-update', 'action' => $url->out(false), 'method' => 'POST'));
    echo $OUTPUT->container_start('', 'dp-objective-courses-container');

    if ($linkedcourses = $component->get_linked_components($caid, 'course')) {
        echo $plan->get_component('course')->display_linked_courses($linkedcourses);
    } else {
        $class = 'plan-remove-selected-hidden';
        echo html_writer::tag('p', get_string('nolinkedx', 'totara_plan', strtolower($coursename)), array('class' => 'noitems-assigncourses'));
    }
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('removeselected', 'totara_plan'), 'class' => $class, 'id' => 'remove-selected-course'));
    echo $OUTPUT->container_end();
    echo html_writer::end_tag('form');

    if (!$plancompleted) {
        echo $component->display_course_picker($caid);
    }
}

// Display linked evidence
echo $evidence->display_linked_evidence($currenturl, $canupdate, $plancompleted);

// Comments
echo $OUTPUT->heading(get_string('comments', 'totara_plan'), 3, null, 'comments');
require_once($CFG->dirroot.'/comment/lib.php');
comment::init();
$options = new stdClass;
$options->area    = 'plan_objective_item';
$options->context = $systemcontext;
$options->itemid  = $caid;
$options->showcount = true;
$options->component = 'totara_plan';
$options->autostart = true;
$options->notoggle = true;
$comment = new comment($options);
echo $comment->output(true);

echo $OUTPUT->container_end();

echo $OUTPUT->footer();
