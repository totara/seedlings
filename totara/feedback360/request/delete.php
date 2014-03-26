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
 * @subpackage totara_feedback360
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

// The feedback360userassignmentid and user id used to identify the record.
$userform = required_param('userform', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$email = optional_param('email', '', PARAM_EMAIL);

// Confirmation hash.
$delete = optional_param('del', '', PARAM_ALPHANUM);

// Set up some variables.
$systemcontext = context_system::instance();
$usercontext = context_user::instance($userform->userid);
$strdelrequest = get_string('removerequest', 'totara_feedback360');
$resp_params = array('feedback360userassignmentid' => $userform, 'userid' => $userid);
$resp_assignment = $DB->get_record('feedback360_resp_assignment', $resp_params);

// Check user has permission to request feedback.
if ($USER->id == $userform->userid) {
    require_capability('totara/feedback360:manageownfeedback360', $systemcontext);
} else if (totara_is_manager($userform->userid)) {
    require_capability('totara/feedback360:managestafffeedback', $usercontext);
}

$returnurl = new moodle_url('/totara/feedback360/request.php',
        array('action' => 'users', 'userid' => $userid, 'formid' => $userform));

// Set up the page.
$urlparams = array('userid' => $userid, 'userform' => $userform);
$PAGE->set_url(new moodle_url('/totara/feedback360/request/delete.php'), $urlparams);
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_totara_menu_selected('myappraisals');
$PAGE->set_title($strdelrequest);
$PAGE->set_heading($strdelrequest);

if ($delete) {
    require_sesskey();

    $request_record = $DB->get_record('feedback360_resp_assignment',
            array('feedback360userassignmentid' => $userform, 'userid' => $userid));

    // Delete.
    if ($delete != md5($request_record->timeassigned)) {
        print_error('error:requestdeletefailure', 'totara_feedback360');
    }

    if (isset($request_record->feedback360emailassignmentid)) {
        // Delete email.
        $DB->delete_records('feedback360_email_assignment', array('id' => $request_record->feedback360emailassignmentid));
    }

    // Then delete the assignment.
    $DB->delete_records('feedback360_resp_assignment', array('id' => $request_record->id));

    add_to_log(SITEID, 'feedback360', 'delete feedback request',
            "request.php?action=users&amp;userid={$userid}&amp;formid={$userform}");
    totara_set_notification(get_string('feedback360requestdeleted', 'totara_feedback360'), $returnurl,
            array('class' => 'notifysuccess'));
} else {
    // Display confirmation page.
    echo $OUTPUT->header();
    $delete_params = array('userform' => $userform, 'userid' => $userid,
        'del' => md5($resp_assignment->timeassigned), 'sesskey' => sesskey());
    if (!empty($email)) {
        $delete_param['email'] = $email;
    }

    $deleteurl = new moodle_url('/totara/feedback360/request/delete.php', $delete_params);
    if (!empty($email)) {
        $username = $email;
    } else {
        $username = fullname($DB->get_record('user', array('id' => $userid)));
    }

    echo $OUTPUT->confirm(get_string('removerequestconfirm', 'totara_feedback360', $username), $deleteurl, $returnurl);

    echo $OUTPUT->footer();
}
