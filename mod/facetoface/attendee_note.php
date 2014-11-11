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
 * @author Oleg Demeshev <oleg.demeshev@totaralms.com>
 * @package totara
 * @subpackage facetoface
 */

define('AJAX_SCRIPT', true);
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');
require_once($CFG->dirroot . '/mod/facetoface/attendee_note_form.php');

$userid    = required_param('id', PARAM_INT); // Facetoface signup user ID.
$sessionid = required_param('s', PARAM_INT); // Facetoface session ID.

require_sesskey();

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
if (!has_capability('mod/facetoface:manageattendeesnote', $context) || (bool)$session->availablesignupnote == false) {
    print_error('nopermissions', 'error', '', 'Update attendee note');
}

$PAGE->get_renderer('mod_facetoface');

$attendee_note = new attendee_note($userid, $sessionid);
$attendee = $attendee_note->get();
$defaults = new stdClass();
$defaults->id = $attendee->userid;
$defaults->s = $attendee->sessionid;
$defaults->usernote = $attendee->usernote;
$defaults->fullname = $attendee->fullname;
$mform = new attendee_note_form(null, array('attendee' => $defaults));

if ($fromform = $mform->get_data()) {

    if (empty($fromform->submitbutton)) {
        echo json_encode(array('result' => 'error', 'error' => get_string('error:unknownbuttonclicked', 'totara_core')));
        die();
    }

    try {
        $attendee_note->set($fromform)->save();
    } catch(Exception $e) {
        echo json_encode(array('result' => 'error', 'error' => $e->getMessage()));
        die();
    }

    add_to_log($course->id, 'facetoface', 'update attendee note', "attendee_note.php?id={$userid}&s={$sessionid}", $sessionid, $cm->id);

    $attendee = $attendee_note->get();
    echo json_encode(array('result' => 'success', 'id' => $attendee->userid, 'usernote' => $attendee->usernote));
} else {
    echo $mform->display();
}