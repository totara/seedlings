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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/goal/lib.php');

// Setup / loading data.
$goalid         = required_param('goalid', PARAM_INT);
$assigntype     = required_param('assigntype', PARAM_INT);
$modid          = required_param('modid', PARAM_INT);

// Check if Goals are enabled.
goal::check_feature_enabled();

// Delete confirmation hash.
$delete = optional_param('delete', '', PARAM_ALPHANUM);

// Return to Goal or Mod_view.
$view_type = optional_param('view', false, PARAM_BOOL);

$strdelgoals = get_string('removegoal', 'totara_hierarchy');
$sitecontext = context_system::instance();

// Set up the page.
// String of params needed in non-js url strings.
$urlparams = array('goalid' => $goalid,
                   'assigntype' => $assigntype,
                   'modid' => $modid
                  );

// Set up the page.
$PAGE->set_url(new moodle_url('/totara/hierarchy/prefix/goal/assign/remove.php'), $urlparams);
$PAGE->set_context($sitecontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_totara_menu_selected('mygoals');
$PAGE->set_title($strdelgoals);
$PAGE->set_heading($strdelgoals);


// Set up some variables.
$type = goal::goal_assignment_type_info($assigntype, $goalid, $modid);
$strassig = format_string($type->goalname) . ' - ' . format_string($type->modname);

// Permissions check.
$context = context_system::instance();

// You must have some form of managegoals permission to see this page.
$admin = has_capability('totara/hierarchy:managegoalassignments', $context);
$manager = false;
$self = false;

if ($assigntype == GOAL_ASSIGNMENT_INDIVIDUAL) {
        $user_context = context_user::instance($modid);
        $manager = totara_is_manager($modid) && has_capability('totara/hierarchy:managestaffcompanygoal', $user_context);
        $self = ($USER->id == $modid) && has_capability('totara/hierarchy:manageowncompanygoal', $user_context);
}

if (!($admin || $manager || $self)) {
    print_error('error:deletegoalassignment', 'totara_hierarchy');
}

if ($view_type) {
    // If the flag is set, return to the goal item page.
    $returnurl = new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => 'goal', 'id' => $goalid));
} else if ($assigntype == GOAL_ASSIGNMENT_POSITION || $assigntype == GOAL_ASSIGNMENT_ORGANISATION) {
    // Return to viewing the hierarchy item.
    $returnurl = new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => $type->fullname, 'id' => $modid));
} else if ($assigntype == GOAL_ASSIGNMENT_AUDIENCE) {
    // Return to the audiences goal tab.
    $returnurl = new moodle_url('/totara/cohort/goals.php', array('id' => $modid));
} else {
    // Return to the users my goals page.
    $returnurl = new moodle_url('/totara/hierarchy/prefix/goal/mygoals.php', array('userid' => $modid));
}

$deleteurl = new moodle_url('/totara/hierarchy/prefix/goal/assign/remove.php',
    array('goalid' => $goalid, 'assigntype' => $assigntype, 'modid' => $modid,
    'view' => $view_type, 'delete' => md5($type->timecreated), 'sesskey' => $USER->sesskey));

if ($delete) {
    // Delete.
    if ($delete != md5($type->timecreated)) {
        print_error('error:checkvariable', 'totara_hierarchy');
    }

    require_sesskey();

    $delete_params = array($type->field => $modid);

    if ($type->companygoal) {
        $delete_params['goalid'] = $goalid;
    } else {
        $delete_params['id'] = $goalid;
    }

    if ($assigntype == GOAL_ASSIGNMENT_INDIVIDUAL) {
        goal::delete_user_assignments($delete_params);
    } else {
        // If it's not an individual assignment delete/transfer user assignments.
        $assignmentid = $DB->get_field($type->table, 'id', $delete_params);
        goal::delete_group_assignment($assigntype, $assignmentid, $type, $delete_params);
    }

    add_to_log(SITEID, 'goal', 'delete goal assignment', "item/view.php?id={$goalid}&amp;prefix=goal", $strassig);
    totara_set_notification(get_string('goaldeletedassignment', 'totara_hierarchy'), $returnurl,
            array('class' => 'notifysuccess'));
} else {
    // Display page.
    echo $OUTPUT->header();
    $strdelete = get_string('goalassigndeletecheck', 'totara_hierarchy');

    echo $OUTPUT->confirm($strdelete . html_writer::empty_tag('br') . html_writer::empty_tag('br') . $strassig,
            $deleteurl, $returnurl);

    echo $OUTPUT->footer();
}
