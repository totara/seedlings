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
 * @author Andrew Hancox <andrewdchancox@googlemail.com> on behalf of Synergy Learning
 * @package totara
 * @subpackage enrol_totara_facetoface
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');
require_once($CFG->dirroot . '/enrol/totara_facetoface/lib.php');

$eid = required_param('eid', PARAM_INT); // Enrolment id.
$confirm = optional_param('confirm', false, PARAM_BOOL);

$enrol = $DB->get_record('enrol', array('id' => $eid, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $enrol->courseid), '*', MUST_EXIST);
$totara_facetoface = new enrol_totara_facetoface_plugin();
$baseurl = new moodle_url('/enrol/totara_facetoface/withdraw.php', array('eid' => $eid));
$returnurl = new moodle_url('/enrol/index.php', array('id' => $course->id));

require_login();

if ($confirm) {
    require_sesskey();

    // Cancel any f2f sign-up requests.
    $sql = "
    SELECT sst.*
    FROM {facetoface} f2f
    JOIN {facetoface_sessions} ssn ON (ssn.facetoface = f2f.id)
    JOIN {facetoface_signups} snp ON (snp.sessionid = ssn.id AND snp.userid = :userid)
    JOIN {facetoface_signups_status} sst ON (sst.signupid = snp.id)
    WHERE f2f.course = :courseid
    AND sst.superceded = :superceded
    AND sst.statuscode = :statuscode
    ";
    $params = array(
        'userid' => $USER->id,
        'courseid' => $course->id,
        'superceded' => 0,
        'statuscode' => MDL_F2F_STATUS_REQUESTED,
    );
    $requests = $DB->get_records_sql($sql, $params);
    foreach ($requests as $request) {
        facetoface_update_signup_status($request->signupid, MDL_F2F_STATUS_USER_CANCELLED, $USER->id);
    }

    // Should not be necessary as event would have deleted the pending record, but just in case.
    $DB->delete_records('enrol_totara_f2f_pending', array('enrolid' => $eid, 'userid' => $USER->id));

    redirect($returnurl);
}

$context = context_course::instance($course->id);
$PAGE->set_context($context);
$PAGE->set_url('/enrol/f2fidrect/withdraw.php', array('eid' => $eid));
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('withdrawconfifm', 'enrol_totara_facetoface'), 3);

$withdrawurl = clone($baseurl);
$withdrawurl->param('confirm', 1);
$div = '';
$div .= $OUTPUT->single_button($withdrawurl, get_string('confirm'));
$div .= $OUTPUT->single_button($returnurl, get_string('cancel'));
echo html_writer::tag('div', $div);

echo $OUTPUT->footer($course);
