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
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @package totara
 * @subpackage facetoface
 */

define('AJAX_SCRIPT', true);
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');
require_once($CFG->dirroot . '/mod/facetoface/attendee_position_form.php');
require_once($CFG->dirroot . '/mod/facetoface/signup_form.php');

$userid    = required_param('id', PARAM_INT); // Facetoface signup user ID.
$sessionid = required_param('s', PARAM_INT); // Facetoface session ID.

if (!$session = facetoface_get_session($sessionid)) {
    print_error('error:incorrectcoursemodulesession', 'facetoface');
}
if (!$facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface))) {
    print_error('error:incorrectfacetofaceid', 'facetoface');
}
if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
    print_error('error:coursemisconfigured', 'facetoface');
}
if (!$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $course->id)) {
    print_error('error:incorrectcoursemodule', 'facetoface');
}

// Check essential permissions.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/facetoface:changesignedupjobposition', $context);

$applicablepositions = get_position_assignments(false, $userid);

$usernamefields = get_all_user_name_fields(true, 'u');

$params = array('userid' => $userid);
$sql = "SELECT u.id, fs.id as signupid, fs.positionassignmentid, $usernamefields
        FROM {user} u
        LEFT JOIN {facetoface_signups} fs ON u.id = fs.userid AND fs.sessionid = $sessionid
        WHERE u.id = :userid";
$user = $DB->get_record_sql($sql, $params);

$formparams = array(
    'applicablepositions' => $applicablepositions,
    'selectedposition' => $user->positionassignmentid,
    'fullname' => fullname($user),
    'userid' => $userid,
    'sessionid' => $sessionid
);

$mform = new attendee_position_form(null, $formparams);

if ($fromform = $mform->get_data()) {

    if (!confirm_sesskey()) {
        echo json_encode(array('result' => 'error', 'error' => get_string('confirmsesskeybad', 'error')));
        die();
    }
    if (empty($fromform->submitbutton)) {
        echo json_encode(array('result' => 'error', 'error' => get_string('error:unknownbuttonclicked', 'totara_core')));
        die();
    }

    try {
        $positionassignmentid = $fromform->selectposition;
        $positionassignment = $applicablepositions[$positionassignmentid];

        $todb = new stdClass();
        $todb->id = $user->signupid;
        $todb->positionid = $positionassignment->positionid;
        $todb->positiontype = $positionassignment->positiontype;
        $todb->positionassignmentid = $positionassignment->id;
        $DB->update_record('facetoface_signups', $todb);
    } catch (Exception $e) {
        echo json_encode(array('result' => 'error', 'error' => $e->getMessage()));
        die();
    }

    add_to_log(
        $course->id,
        'facetoface',
        'update attendee position',
        "attendee_position.php?id={$userid}&s={$sessionid}",
        $sessionid,
        $cm->id
    );

    $label = position::position_label($positionassignment);

    echo json_encode(array('result' => 'success', 'id' => $userid, 'positiondisplayname' => $label));
} else {
    echo $mform->display();
}
