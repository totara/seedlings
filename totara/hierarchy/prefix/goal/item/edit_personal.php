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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/config.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/item/edit_form.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/lib.php');

// Check if Goals are enabled.
goal::check_feature_enabled();

$userid = optional_param('userid', $USER->id, PARAM_INT);
$goalpersonalid = optional_param('goalpersonalid', 0, PARAM_INT);

require_login();

$strmygoals = get_string('mygoals', 'totara_hierarchy');
$mygoalsurl = new moodle_url('/totara/hierarchy/prefix/goal/mygoals.php', array('userid' => $userid));
$pageurl = new moodle_url('/totara/hierarchy/prefix/goal/item/edit_personal.php', array('userid' => $userid));

$context = context_user::instance($userid);
$PAGE->set_context($context);

$goal = new goal();
if (!$permissions = $goal->get_permissions(null, $userid)) {
    // Error setting up page permissions.
    print_error('error:viewusergoals', 'totara_hierarchy');
}

extract($permissions);

if (!empty($goalpersonalid)) {
    $goalpersonal = goal::get_goal_item(array('id' => $goalpersonalid), goal::SCOPE_PERSONAL);
    $goalpersonal->goalpersonalid = $goalpersonal->id;
    $goalname = format_string($goalpersonal->name);

    // Check the specific permissions for this goal.
    if (!$can_edit[$goalpersonal->assigntype]) {
        print_error('error:editgoals', 'totara_hierarchy');
    }
} else {
    $goalpersonal = new stdClass();
    $goalpersonal->userid = $userid;
    $goalname = get_string('addgoalpersonal', 'totara_hierarchy');

    // Check they have generic permissions to create a personal goal for this user.
    if (!$can_edit[GOAL_ASSIGNMENT_SELF] && !$can_edit[GOAL_ASSIGNMENT_MANAGER] && !$can_edit[GOAL_ASSIGNMENT_ADMIN]) {
        print_error('error:createpersonalgoal', 'totara_hierarchy');
    }
}

// Set up the page.
$PAGE->navbar->add($strmygoals, $mygoalsurl);
$PAGE->navbar->add($goalname);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_totara_menu_selected('mygoals');
$PAGE->set_title($strmygoals);
$PAGE->set_heading($strmygoals);

$mform = new goal_edit_personal_form();

// Handle the form.
if ($mform->is_cancelled()) {
    // Cancelled.
    redirect("{$CFG->wwwroot}/totara/hierarchy/prefix/goal/mygoals.php?userid={$userid}");
} else if ($fromform = $mform->get_data()) {
    // Update data.
    $todb = new stdClass();
    $todb->userid = $fromform->userid;
    $todb->scaleid = $fromform->scaleid;
    $todb->name = $fromform->name;
    $todb->usermodified = $USER->id;
    $todb->timemodified = time();
    if (isset($fromform->targetdate)) {
        if (empty($fromform->targetdate)) {
            $todb->targetdate = 0;
        } else {
            $todb->targetdate = $fromform->targetdate;
        }
    }

    $existingrecord = null;
    if (!empty($fromform->goalpersonalid)) {
        $existingrecord = goal::get_goal_item(array('id' => $fromform->goalpersonalid), goal::SCOPE_PERSONAL);
    }

    if (isset($existingrecord)) {
        // Handle updates.

        // Set the existing goal id.
        $todb->id = $fromform->goalpersonalid;

        // If the scale changes then set the current scale value to default.
        if ($todb->scaleid != $existingrecord->scaleid) {
            $todb->scalevalueid = $DB->get_field('goal_scale', 'defaultid', array('id' => $todb->scaleid));
        }

        $fromform = file_postupdate_standard_editor($fromform, 'description', $TEXTAREA_OPTIONS, $context,
            'totara_hierarchy', 'goal', $fromform->goalpersonalid);
        $todb->description = $fromform->description;

        // Update the record.
        goal::update_goal_item($todb, goal::SCOPE_PERSONAL);

        $log_action = 'update';
    } else {
        // Handle creating a new goal.

        // Set the assignment type self/manager/admin.
        if ($USER->id == $todb->userid && $can_edit[GOAL_ASSIGNMENT_SELF]) {
            // They are assigning it to themselves.
            $todb->assigntype = GOAL_ASSIGNMENT_SELF;
        } else if (totara_is_manager($todb->userid) && $can_edit[GOAL_ASSIGNMENT_MANAGER]) {
            // They are assigning it to their team.
            $todb->assigntype = GOAL_ASSIGNMENT_MANAGER;
        } else if ($can_edit[GOAL_ASSIGNMENT_ADMIN]) {
            // Last option, they are an admin assigning it to someone.
            $todb->assigntype = GOAL_ASSIGNMENT_ADMIN;
        } else {
            print_error('error:createpersonalgoal', 'totara_hierarchy');
        }

        // Set the user/time created.
        $todb->usercreated = $USER->id;
        $todb->timecreated = time();

        // Set the current scale value to default.
        $todb->scalevalueid = $DB->get_field('goal_scale', 'defaultid', array('id' => $todb->scaleid));

        // Insert the record.
        $todb->id = goal::insert_goal_item($todb, goal::SCOPE_PERSONAL);

        // We need to know the new id before we can process the editor and save the description.
        $fromform = file_postupdate_standard_editor($fromform, 'description', $TEXTAREA_OPTIONS, $context,
            'totara_hierarchy', 'goal', $todb->id);
        $DB->set_field('goal_personal', 'description', $fromform->description, array('id' => $todb->id));

        $log_action = 'create';
    }

    // Add the action to the site logs.
    add_to_log(SITEID, 'goal', $log_action . ' personal goal', "edit_personal.php?user={$userid}", $todb->name, 0, $todb->userid);

    redirect("{$CFG->wwwroot}/totara/hierarchy/prefix/goal/mygoals.php?userid={$todb->userid}");
}

// Display the page and form.
echo $OUTPUT->header();

$mform->set_data($goalpersonal);
$mform->display();

echo $OUTPUT->footer();
