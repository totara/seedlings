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
 * @package modules
 * @subpackage facetoface
 */

require_once '../../config.php';
require_once 'lib.php';
require_once 'cancelsignup_form.php';

$s  = required_param('s', PARAM_INT); // facetoface session ID
$confirm           = optional_param('confirm', false, PARAM_BOOL);
$backtoallsessions = optional_param('backtoallsessions', 0, PARAM_INT);

if (!$session = facetoface_get_session($s)) {
    print_error('error:incorrectcoursemodulesession', 'facetoface');
}
if (!$facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface))) {
    print_error('error:incorrectfacetofaceid', 'facetoface');
}
if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
    print_error('error:coursemisconfigured', 'facetoface');
}
if (!$cm = get_coursemodule_from_instance("facetoface", $facetoface->id, $course->id)) {
    print_error('error:incorrectcoursemoduleid', 'facetoface');
}

require_course_login($course);
$context = context_course::instance($course->id);
require_capability('mod/facetoface:view', $context);

$returnurl = "$CFG->wwwroot/course/view.php?id=$course->id";
if ($backtoallsessions) {
    $returnurl = "$CFG->wwwroot/mod/facetoface/view.php?f=$backtoallsessions";
}

$mform = new mod_facetoface_cancelsignup_form(null, compact('s', 'backtoallsessions'));
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'facetoface', $returnurl);
    }

    $forcecancel = false;
    $timenow = time();
    $bookedsession = facetoface_get_user_submissions($facetoface->id, $USER->id, MDL_F2F_STATUS_WAITLISTED, MDL_F2F_STATUS_WAITLISTED, $session->id);
    if (!empty($bookedsession) && facetoface_has_session_started($session, $timenow)) {
        $forcecancel = true;
    }

    $errorstr = '';
    if (facetoface_user_cancel($session, false, $forcecancel, $errorstr, $fromform->cancelreason)) {
        add_to_log($course->id, 'facetoface', 'cancel booking', "cancelsignup.php?s=$session->id", $facetoface->id, $cm->id);

        $message = get_string('bookingcancelled', 'facetoface');

        if ($session->datetimeknown) {
            $error = facetoface_send_cancellation_notice($facetoface, $session, $USER->id);
            if (empty($error)) {
                if ($session->datetimeknown && isset($facetoface->cancellationinstrmngr) && !empty($facetoface->cancellationstrmngr)) {
                    $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('cancellationsentmgr', 'facetoface');
                }
                else {
                    $message .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . get_string('cancellationsent', 'facetoface');
                }
            } else {
                print_error($error, 'facetoface');
            }
        }

        totara_set_notification($message, $returnurl, array('class' => 'notifysuccess'));
    }
    else {
        add_to_log($course->id, 'facetoface', "cancel booking (FAILED)", "cancelsignup.php?s=$session->id", $facetoface->id, $cm->id);
        $timemessage = 4;
        redirect($returnurl, $errorstr, $timemessage);
    }

    redirect($returnurl);
}

$pagetitle = format_string($facetoface->name);

$PAGE->set_cm($cm);
$PAGE->set_url('/mod/facetoface/cancelsignup.php', array('s' => $s, 'backtoallsessions' => $backtoallsessions, 'confirm' => $confirm));

$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

$heading = get_string('cancelbookingfor', 'facetoface', $facetoface->name);

$viewattendees = has_capability('mod/facetoface:viewattendees', $context);
$multisessionid = ($facetoface->multiplesessions ? $session->id : null);
$signedup = facetoface_check_signup($facetoface->id, $multisessionid);

echo $OUTPUT->box_start();
echo $OUTPUT->heading($heading);

if ($signedup) {
    facetoface_print_session($session, $viewattendees);
    $mform->display();
}
else {
    print_error('notsignedup', 'facetoface', $returnurl);
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);
