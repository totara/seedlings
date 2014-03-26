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
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/lib.php');

// Check if Goals are enabled.
goal::check_feature_enabled();

$goalpersonalid = required_param('goalpersonalid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$delete = optional_param('del', '', PARAM_ALPHANUM);

// Check permissions before we do anything.
$context = context_user::instance($userid);
$can_edit = has_capability('totara/hierarchy:managegoalassignments', context_system::instance())
    || (totara_is_manager($userid) && has_capability('totara/hierarchy:managestaffpersonalgoal', $context))
    || ($USER->id == $userid && has_capability('totara/hierarchy:manageownpersonalgoal', $context));

if (!$can_edit) {
    print_error('error:deleteusergoals', 'totara_hierarchy');
}

$ret_url = new moodle_url("/totara/hierarchy/prefix/goal/mygoals.php", array('userid' => $userid));

$goalpersonal = goal::get_goal_item(array('id' => $goalpersonalid), goal::SCOPE_PERSONAL);
if (empty($goalpersonal)) {
    // Goal isn't there to delete, just return.
    redirect($ret_url);
}

$strdelgoals = get_string('removegoal', 'totara_hierarchy');

// Set up the page.
// String of params needed in non-js url strings.
$urlparams = array('goalpersonalid' => $goalpersonalid,
                   'userid' => $userid,
                  );

// Set up the page.
$PAGE->set_url(new moodle_url('/totara/hierarchy/prefix/goal/item/delete.php'), $urlparams);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_totara_menu_selected('mygoals');
$PAGE->set_title($strdelgoals);
$PAGE->set_heading($strdelgoals);


if ($delete) {
    // Delete.

    if ($delete != md5($goalpersonal->timemodified)) {
        print_error('error:deletetypecheckvariable', 'totara_hierarchy');
    }

    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    // Do the deletion.
    if (goal::delete_goal_item(array('id' => $goalpersonalid), goal::SCOPE_PERSONAL)) {
        $success = get_string('deletedpersonalgoal', 'totara_hierarchy', format_string($goalpersonal->name));
        add_to_log(SITEID, 'goal', 'delete personal goal', $ret_url,
                format_string($goalpersonal->name) . " (ID $goalpersonalid)");
        totara_set_notification($success, $ret_url, array('class' => 'notifysuccess'));
    } else {
        // Failure.
        $error = get_string('error:deletepersonalgoal', 'totara_hierarchy', format_string($goalpersonal->name));
        totara_set_notification($error, $ret_url);
    }
}

// Display confirmation.
$PAGE->navbar->add(get_string('mygoals', 'totara_hierarchy'),
    new moodle_url('/totara/hierarchy/item/prefix/goal/mygoals.php', array('userid' => $userid)));
$PAGE->navbar->add(format_string($goalpersonal->name),
    new moodle_url('/totara/hierarchy/prefix/goal/item/view.php', array('goalpersonalid' => $goalpersonalid)));
$PAGE->navbar->add(get_string('deletegoal', 'totara_hierarchy'));

echo $OUTPUT->header();

$str_param = new stdClass();
$str_param->goalname = $goalpersonal->name;
$str_param->username = fullname($DB->get_record('user', array('id' => $userid)));

$strdelete = get_string('confirmpersonaldelete', 'totara_hierarchy', $str_param);

$del_params = array('goalpersonalid' => $goalpersonalid, 'userid' => $userid, 'del' => md5($goalpersonal->timemodified),
        'sesskey' => $USER->sesskey);
$del_url = new moodle_url("/totara/hierarchy/prefix/goal/item/delete.php", $del_params);

echo $OUTPUT->confirm($strdelete, $del_url, $ret_url);

echo $OUTPUT->footer();
