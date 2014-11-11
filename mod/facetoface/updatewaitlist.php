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
 * @author Andrew Davidson <andrew.davidson@synergy-learning.com>
 * @package mod_facetoface
 */
/**
 * This class is an ajax back-end for updating attendance
 */
define('AJAX_SCRIPT', true);
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot.'/mod/facetoface/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$data = required_param('datasubmission', PARAM_SEQUENCE);

$data = explode(',', $data);

if (!$session = facetoface_get_session($sessionid)) {
    print_error('error:incorrectcoursemodulesession', 'facetoface');
}
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('error:coursemisconfigured', 'facetoface');
}
if (!$cm = get_coursemodule_from_instance('facetoface', $session->facetoface, $course->id)) {
    print_error('error:incorrectcoursemodule', 'facetoface');
}

// Check essential permissions.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/facetoface:takeattendance', $context);

require_sesskey();

$result = array('result'=>'failure');
switch($action) {
    case 'confirmattendees':
        facetoface_confirm_attendees($sessionid, $data);
        $result['result'] = 'success';
        break;
    case 'cancelattendees':
        facetoface_cancel_attendees($sessionid, $data);
        $result['result'] = 'success';
        break;
    case 'playlottery':
        facetoface_waitlist_randomly_confirm_users($sessionid, $data);
        $result['result'] = 'success';
        break;
    case 'checkcapacity':
        $session = facetoface_get_session($sessionid);
        $signupcount = facetoface_get_num_attendees($session->id);

        if (($signupcount + count($data)) > $session->capacity) {
            $result['result'] = 'overcapacity';
        } else {
            $result['result'] = 'undercapacity';
        }
        echo json_encode($result);
        die();
        break;
}
$attendees = facetoface_get_attendees($sessionid, $status = array(MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_USER_CANCELLED));
$result['attendees'] = array_keys($attendees);
echo json_encode($result);