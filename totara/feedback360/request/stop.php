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
require_once($CFG->dirroot . '/totara/feedback360/lib.php');

// This page cancels and unreplied feedback.
$userformid = required_param('userformid', PARAM_INT);
$confirmation = optional_param('confirm', null, PARAM_ALPHANUM);

if (!$userform = $DB->get_record('feedback360_user_assignment', array('id' => $userformid))) {
    print_error('userassignmentnotfound', 'totara_feedback360');
}

$systemcontext = context_system::instance();
$usercontext = context_user::instance($userform->userid);
$cancelstr = get_string('cancelrequest', 'totara_feedback360');
$ret_url = new moodle_url('/totara/feedback360/index.php', array('userid' => $userform->userid));

$PAGE->set_url(new moodle_url('/totara/feedback360/request/stop.php', array('userformid' => $userformid)));
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');

// Check user has permission to request feedback, and set up the page.
$owner = $DB->get_record('user', array('id' => $userform->userid));
if ($USER->id == $userform->userid) {
    require_capability('totara/feedback360:manageownfeedback360', $systemcontext);
    $asmanager = false;

    $strmyfeedback = get_string('myfeedback', 'totara_feedback360');
    $PAGE->set_totara_menu_selected('myfeedback');
    $PAGE->set_title($cancelstr);
    $PAGE->set_heading($cancelstr);
    $PAGE->navbar->add(get_string('feedback360', 'totara_feedback360'), new moodle_url('/totara/feedback360/index.php'));
    $PAGE->navbar->add($strmyfeedback);
} else if (totara_is_manager($userform->userid)) {
    require_capability('totara/feedback360:managestafffeedback', $usercontext);
    $asmanager = true;

    $userxfeedback = get_string('userxfeedback360', 'totara_feedback360', fullname($owner));
    $PAGE->set_totara_menu_selected('myteam');
    $PAGE->navbar->add(get_string('myteam', 'totara_core'), new moodle_url('/my/teammembers.php'));
    $PAGE->navbar->add($userxfeedback);
    $PAGE->set_title($userxfeedback);
    $PAGE->set_heading($userxfeedback);
} else {
    print_error('error:accessdenied', 'totara_feedback');
}

$PAGE->navbar->add($cancelstr);

if (!empty($confirmation)) {
    $valid = sha1($userform->feedback360id . ':' . $userform->userid . ':' . $userform->timedue);
    if ($confirmation == $valid) {
        $success = get_string('cancelrequestsuccess', 'totara_feedback360');

        feedback360::cancel_user_assignment($userformid, $asmanager);
        totara_set_notification($success, $ret_url, array('class' => 'notifysuccess'));
    } else {
        print_error('validationfailed', 'totara_feedback360');
    }
}


// Confirmation setup.
$renderer = $PAGE->get_renderer('totara_feedback360');

echo $renderer->header();

echo $renderer->display_userview_header($owner);

$strdelete = get_string('cancelrequestconfirm', 'totara_feedback360');

$sql = "SELECT *
        FROM {feedback360_resp_assignment}
        WHERE feedback360userassignmentid = :uaid
        AND timecompleted > 0";
if ($DB->record_exists_sql($sql, array('uaid' => $userformid))) {
    $strdelete .= get_string('cancelrequestcontinued', 'totara_feedback360');
}

$confirm = sha1($userform->feedback360id . ':' . $userform->userid . ':' . $userform->timedue);
$del_params = array('userformid' => $userformid, 'confirm' => $confirm);
$del_url = new moodle_url('/totara/feedback360/request/stop.php', $del_params);

echo $renderer->confirm($strdelete, $del_url, $ret_url);

echo $renderer->footer();
