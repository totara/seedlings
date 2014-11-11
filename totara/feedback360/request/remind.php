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
require_once($CFG->dirroot . '/totara/message/messagelib.php');

// Retrieve some parameters.
$userformid = required_param('userformid', PARAM_INT);
$confirmation = optional_param('confirm', null, PARAM_ALPHANUM);

// Set up some variables.
$remindstr = get_string('remindresponders', 'totara_feedback360');
$userform = $DB->get_record('feedback360_user_assignment', array('id' => $userformid));
$ret_url = new moodle_url('/totara/feedback360/index.php', array('userid'=> $userform->userid));
$resp_assignments = $DB->get_records('feedback360_resp_assignment', array('feedback360userassignmentid' => $userformid));
$feedback = $DB->get_record('feedback360', array('id' => $userform->feedback360id));
$systemcontext = context_system::instance();
$usercontext = context_user::instance($userform->userid);

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
    $PAGE->set_title($remindstr);
    $PAGE->set_heading($remindstr);
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

$PAGE->navbar->add($remindstr);

// Check confirmation before sending the requests, to help reduce spamming.
if (!empty($confirmation)) {
    $valid = sha1($userform->feedback360id . ':' . $userform->userid . ':' . $userform->timedue);
    if ($confirmation == $valid) {
        $requester = $DB->get_record('user', array('id' => $userform->userid));
        $stringmanager = get_string_manager();

        // Create the replacement variables for the email strings.
        $remvars = new stdClass();
        if (!empty($userform->timedue)) {
            $duedate = userdate($userform->timedue, get_string('datepickerlongyearphpuserdate', 'totara_core'));
            $remvars->timedue = get_string('byduedate' , 'totara_feedback360', $duedate);
        } else {
            $remvars->timedue = '';
        }

        if ($asmanager) {
            $remvars->userfrom = fullname($USER);
            $remvars->staffname = fullname($requester);
            $sendfrom = $USER;
        } else {
            $remvars->requestername = fullname($requester);
            $sendfrom = $requester;
        }

        // Go through each responder.
        foreach ($resp_assignments as $resp_assignment) {
            if (!empty($resp_assignment->timecompleted)) {
                // Skip anyone who has replied, we don't need to remind them.
                continue;
            }

            if (!empty($resp_assignment->feedback360emailassignmentid)) {
                // External user, send them an email.
                $email_assignment = $DB->get_record('feedback360_email_assignment',
                        array('id' => $resp_assignment->feedback360emailassignmentid));

                // Set up some variables for the email.
                $params = array('email' => $email_assignment->email, 'token' => $email_assignment->token);
                $url = new moodle_url('/totara/feedback360/feedback.php', $params);
                $remvars->link = html_writer::link($url, get_string('urlrequesturlmask', 'totara_feedback360'));
                $remvars->url = $url->out();

                if ($asmanager) {
                    $emailplain = get_string('managerreminderemailbody', 'totara_feedback360', $remvars);
                    $emailhtml = get_string('managerreminderemailbodyhtml', 'totara_feedback360', $remvars);
                    $emailsubject = get_string('managerreminderemailsubject', 'totara_feedback360', $remvars);
                } else {
                    $emailplain = get_string('reminderemailbody', 'totara_feedback360', $remvars);
                    $emailhtml = get_string('reminderemailbodyhtml', 'totara_feedback360', $remvars);
                    $emailsubject = get_string('reminderemailsubject', 'totara_feedback360', $remvars);
                }

                // Send the feedback reminder email.
                $userto = \totara_core\totara_user::get_external_user($email_assignment->email);
                email_to_user($userto, $sendfrom, $emailsubject, $emailplain, $emailhtml);
            } else {
                // Internal user, send them an alert.
                $userto = $DB->get_record('user', array('id' => $resp_assignment->userid));

                $user_assignment = $DB->get_record('feedback360_user_assignment',
                        array('id' => $resp_assignment->feedback360userassignmentid));

                // Send an internal alert to the system user to remind them.
                $params = array('userid' => $user_assignment->userid, 'feedback360id' => $user_assignment->feedback360id);
                $url = new moodle_url('/totara/feedback360/feedback.php', $params);
                $remvars->link = html_writer::link($url, get_string('urlrequesturlmask', 'totara_feedback360'));
                $remvars->url = $url->out();

                // Send a task to the requested user.
                $eventdata = new stdClass();
                $eventdata->userto = $userto;
                $eventdata->userfrom = $requester;
                $eventdata->icon = 'feedback360-remind';
                if ($asmanager) {
                    $eventdata->subject = $stringmanager->get_string('managerreminderemailsubject', 'totara_feedback360',
                            $remvars, $userto->lang);
                    $eventdata->fullmessage = $stringmanager->get_string('managerreminderemailbody', 'totara_feedback360',
                            $remvars, $userto->lang);
                    $eventdata->fullmessagehtml = $stringmanager->get_string('managerreminderemailbodyhtml', 'totara_feedback360',
                            $remvars, $userto->lang);
                } else {
                    $eventdata->subject = $stringmanager->get_string('reminderemailsubject', 'totara_feedback360',
                            $remvars, $userto->lang);
                    $eventdata->fullmessage = $stringmanager->get_string('reminderemailbody', 'totara_feedback360',
                            $remvars, $userto->lang);
                    $eventdata->fullmessagehtml = $stringmanager->get_string('reminderemailbodyhtml', 'totara_feedback360',
                            $remvars, $userto->lang);
                }

                tm_alert_send($eventdata);
            }
        }

        // Redirect.
        $success = get_string('reminderssent', 'totara_feedback360', format_string($feedback->name));
        totara_set_notification($success, $ret_url, array('class' => 'notifysuccess'));
    }
}

// Set up the confirmation dialog.

$renderer = $PAGE->get_renderer('totara_feedback360');

echo $renderer->header();

echo $renderer->display_userview_header($owner);

$spacer = html_writer::empty_tag('br');
$system = '';
$external = '';
foreach ($resp_assignments as $resp_assignment) {
    if (!empty($resp_assignment->timecompleted)) {
        // Skip anyone who has replied, we don't need to remind them.
        continue;
    }

    if (!empty($resp_assignment->feedback360emailassignmentid)) {
        $external .= format_string($DB->get_field('feedback360_email_assignment', 'email',
                array('id' => $resp_assignment->feedback360emailassignmentid)));
        $external .= $spacer;
    } else {
        $system .= fullname($DB->get_record('user', array('id' => $resp_assignment->userid))) . $spacer;
    }
}
$strremind = get_string('reminderconfirm', 'totara_feedback360') . $spacer . $system . $spacer . $external;
$confirm = sha1($userform->feedback360id . ':' . $userform->userid . ':' . $userform->timedue);
$rem_params = array('userformid' => $userformid, 'confirm' => $confirm);
$rem_url = new moodle_url('/totara/feedback360/request/remind.php', $rem_params);

echo $renderer->confirm($strremind, $rem_url, $ret_url);

echo $renderer->footer();
