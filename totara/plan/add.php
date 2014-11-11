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
 * @package totara
 * @subpackage plan
 */

/**
 * Page for adding a plan
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');
require_once($CFG->dirroot . '/totara/plan/edit_form.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

global $USER;

require_login();

$userid = required_param('userid', PARAM_INT); // user id
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/totara/plan/add.php', array('userid' => $userid));
$PAGE->set_pagelayout('noblocks');
$ownplan = ($userid == $USER->id);
$menuitem = ($ownplan) ? 'learningplans' : 'myteam';
$PAGE->set_totara_menu_selected($menuitem);

///
/// Permission checks
///
if (!dp_can_view_users_plans($userid)) {
    print_error('error:nopermissions', 'totara_plan');
}


// START PERMISSION HACK
if ($userid != $USER->id) {
    // Make sure user is manager
    if (totara_is_manager($userid) || is_siteadmin()) {
        $role = 'manager';
    } else {
        print_error('error:nopermissions', 'totara_plan');
    }
} else {
    $role = 'learner';
}

// Check if a users has add plan on any template
$templates = dp_get_templates();
$canaddplan = false;
foreach ($templates as $template) {
    if (dp_get_template_permission($template->id, 'plan', 'create', $role) == DP_PERMISSION_ALLOW) {
        $canaddplan = true;
    }
}

if (!$canaddplan) {
    print_error('error:nopermissions', 'totara_plan');
}
// END HACK


///
/// Data and actions
///
$currenturl = qualified_me();
$allplansurl = "{$CFG->wwwroot}/totara/plan/index.php?userid={$userid}";

$obj = new stdClass();
$obj->id = 0;
$obj->description = '';
$obj->descriptionformat = FORMAT_HTML;
$obj = file_prepare_standard_editor($obj, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                    'totara_plan', 'dp_plan', $obj->id);

$form = new plan_edit_form($currenturl, array('action' => 'add', 'role' => $role));

if ($form->is_cancelled()) {
    redirect($allplansurl);
}

// Handle form submit
if ($data = $form->get_data()) {
    if (isset($data->submitbutton)) {
        $transaction = $DB->start_delegated_transaction();
        // Set up the plan
        $newid = $DB->insert_record('dp_plan', $data);
        $data->id = $newid;
        $plan = new development_plan($newid);
        // Update plan status and plan history
        $plan->set_status(DP_PLAN_STATUS_UNAPPROVED, DP_PLAN_REASON_CREATE);

        $components = $plan->get_components();

        foreach ($components as $componentname => $stuff) {
            $component = $plan->get_component($componentname);
            if ($component->get_setting('enabled')) {

                // Automatically add items from this component
                $component->plan_create_hook();
            }

            //Free memory
            unset($component);
        }

        $transaction->allow_commit();

        // Send out a notification?
        if ($plan->is_active()) {
            if ($role == 'manager') {
                $plan->send_alert(true,'learningplan-update.png','plan-add-learner-short','plan-add-learner-long');
            }
        }
        $data = file_postupdate_standard_editor($data, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_plan', 'dp_plan', $data->id);
        $DB->set_field('dp_plan', 'description', $data->description, array('id' => $data->id));
        $viewurl = "{$CFG->wwwroot}/totara/plan/view.php?id={$newid}";
        add_to_log(SITEID, 'plan', 'created', "view.php?id={$newid}", $plan->name);

        // Free memory
        unset($plan);

        totara_set_notification(get_string('plancreatesuccess', 'totara_plan'), $viewurl, array('class' => 'notifysuccess'));
    }
}


///
/// Display
///
$heading = get_string('createnewlearningplan', 'totara_plan');
$pagetitle = format_string(get_string('learningplan', 'totara_plan').': '.$heading);
dp_get_plan_base_navlinks($userid);
$PAGE->navbar->add($heading);

$jsmodule = array(
    'name' => 'totara_plan_template',
    'fullpath' => '/totara/plan/templates.js',
    'requires' => array('json'));

$json_templates = json_encode($templates);
$args = array('args' => '{"templates":' . $json_templates . '}');

$PAGE->requires->js_init_call('M.totara_plan_template.init', $args, false, $jsmodule);

$PAGE->set_title($pagetitle);
$PAGE->set_heading(format_string($SITE->fullname));
echo $OUTPUT->header();

// Plan menu
echo dp_display_plans_menu($userid);

// Plan page content
echo $OUTPUT->container_start('', 'dp-plan-content');

if ($USER->id != $userid) {
    echo dp_display_user_message_box($userid);
}

echo $OUTPUT->heading($heading);

echo html_writer::tag('p', get_string('createplan_instructions', 'totara_plan'));

$form->set_data((object)array('userid' => $userid));
$form->display();

echo $OUTPUT->container_end();

echo $OUTPUT->footer();
