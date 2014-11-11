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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

require_login();

///
/// Load parameters
///
$id = required_param('id', PARAM_INT); // Plan id.
$submitted = optional_param('submitbutton', null, PARAM_TEXT); // Form submitted.

///
/// Load data
///
$currenturl = qualified_me();
$PAGE->set_context(context_system::instance());
$PAGE->set_url($currenturl);
$PAGE->set_pagelayout('noblocks');
$planurl = "{$CFG->wwwroot}/totara/plan/view.php?id={$id}";
$plan = new development_plan($id);

if (!dp_can_view_users_plans($plan->userid)) {
    print_error('error:nopermissions', 'totara_plan');
}


// Redirect if plan complete.
if ($plan->status == DP_PLAN_STATUS_COMPLETE) {
    totara_set_notification(
        get_string('plancomplete', 'totara_plan'),
        $planurl
    );
}


// Get all components.
$components = $plan->get_components();

// Get items the current user can approve.
$requested_items = $plan->has_pending_items(null, true, true);

// If no items.
if (!$requested_items) {
    totara_set_notification(
        get_string('noitemsrequiringapproval', 'totara_plan'),
        $planurl
    );
}

$require_approval = array();
// Get list of only items that require approval.
foreach ($components as $componentname => $component) {
    if (!empty($requested_items[$componentname])) {
        $require_approval[$componentname] = $component;
    }
}


// Flag this page as the review page.
$plan->reviewing_pending = true;


///
/// Process data
///
if ($submitted && confirm_sesskey()) {

    // Loop through components.
    $errors = 0;
    foreach ($components as $componentname => $component) {

        // Update settings.
        $result = $component->process_settings_update();

        if ($result === false) {
            $errors += 1;
        }
    }

    if ($errors) {
        totara_set_notification(get_string('error:problemapproving', 'totara_plan'));
    }

    redirect($plan->get_display_url());
}


$fullname = $plan->name;
$pagetitle = format_string(get_string('learningplan', 'totara_plan').': '.$fullname);
dp_get_plan_base_navlinks($plan->userid);
$PAGE->navbar->add($fullname, new moodle_url('/totara/plan/view.php', array('id' => $id)));
$PAGE->navbar->add(get_string('pendingitems', 'totara_plan'));

///
/// Display page
///

$PAGE->set_title($pagetitle);
$PAGE->set_heading(format_string($SITE->fullname));

$output = $PAGE->get_renderer('totara_plan');

echo $output->header();

// Plan menu.
echo dp_display_plans_menu($plan->userid, $plan->id, $plan->role);

// Plan page content.
echo $output->container_start('', 'dp-plan-content');

echo $plan->display_plan_message_box();

echo $output->heading($fullname);
echo $plan->display_tabs('pendingitems');

echo $output->totara_print_approval_form($requested_items, $require_approval);

echo $output->container_end();
echo $output->footer();

