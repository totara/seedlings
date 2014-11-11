<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2009 Catalyst IT LTD
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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Francois Marier <francois@catalyst.net.nz>
 * @package blocks
 * @subpackage facetoface
 */

// Displays booking history for the current user

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once('lib.php');

require_login(null, false);

$sid        = required_param('session', PARAM_INT);
$userid     = optional_param('userid', $USER->id, PARAM_INT);

// get all the required records
if (!$user = $DB->get_record('user', array('id' => $userid))) {
    print_error('error:invaliduserid', 'block_facetoface');
}
if (!$session = facetoface_get_session($sid)) {
    print_error('error:invalidsessionid', 'block_facetoface');
}
if (!$facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface))) {
    print_error('error:invalidfacetofaceid', 'block_facetoface');
}
if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
    print_error('error:invalidcourseid', 'block_facetoface');
}
if (!$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $course->id)) {
    print_error('error:incorrectcoursemoduleid', 'facetoface');
}
$contextmodule = context_module::instance($cm->id);

if ($userid != $USER->id) {
    $contextuser = context_user::instance($userid);
    if (has_capability('block/facetoface:viewbookings', $contextuser)) {
        $PAGE->set_context($contextmodule);
    } else {
        require_login($course, false, $cm);
        require_capability('mod/facetoface:viewattendees', $contextmodule);
    }
} else {
    require_login($course, false, $cm);
}

$pagetitle = format_string(get_string('bookinghistory', 'block_facetoface'));
$PAGE->navbar->add(get_string('bookings', 'block_facetoface'), new moodle_url('/blocks/facetoface/mysignups.php', array('userid' => $userid)));
$PAGE->navbar->add($pagetitle);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_url('/blocks/facetoface/mysessions.php');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

// Get signups from the DB
$bookings = $DB->get_records_sql("SELECT ss.timecreated, ss.statuscode, ss.grade, ss.note,
                                   c.id as courseid, c.fullname AS coursename,
                                   f.name, f.id as facetofaceid, s.id as sessionid,
                                   d.id, d.timestart, d.timefinish, d.sessiontimezone
                              FROM {facetoface_sessions_dates} d
                              JOIN {facetoface_sessions} s ON s.id = d.sessionid
                              JOIN {facetoface} f ON f.id = s.facetoface
                              JOIN {facetoface_signups} su ON su.sessionid = s.id
                              JOIN {facetoface_signups_status} ss ON ss.signupid = su.id
                              JOIN {course} c ON f.course = c.id
                              WHERE su.userid = ? AND su.sessionid = ? AND f.id = ?
                              ORDER BY ss.timecreated ASC", array($user->id, $session->id, $session->facetoface));

// Get session times
$sessiontimes = facetoface_get_session_dates($session->id);

if ($user->id != $USER->id) {
    $fullname = html_writer::link(new moodle_url('/user/view.php', array('id' => $user->id, 'course' => $course->id)), fullname($user));
    $heading = get_string('bookinghistoryfor', 'block_facetoface', $fullname);
    echo $OUTPUT->heading($heading);
} else {
    echo html_writer::empty_tag('br');
}

echo $OUTPUT->heading(get_string('sessiondetails', 'block_facetoface'));

// print the session information
$viewattendees = has_capability('mod/facetoface:viewattendees', $contextmodule);
echo facetoface_print_session($session, $viewattendees, false, false, true);

// print the booking history
if ($bookings and count($bookings) > 0) {

    $table = new html_table();
    $table->summary = get_string('sessionsdetailstablesummary', 'facetoface');
    $table->attributes['class'] = 'generaltable bookinghistory';

    foreach ($bookings as $booking) {

        $row = array(
            get_string('status_'.facetoface_get_status($booking->statuscode), 'facetoface'),
            userdate($booking->timecreated, get_string('strftimedatetime'))
        );

        if (strlen(trim($booking->note))) {
            $row[] = $booking->note;
        }

        $table->data[] = $row;
    }

} else {
    // no booking history available
    $table = new html_table();
    $table->summary = get_string('sessionsdetailstablesummary', 'facetoface');
    $table->attributes['class'] = 'generaltable bookinghistory';
    $table->align = array('center');

    if ($user->id != $USER->id) {
       $table->data[] = array(get_string('nobookinghistoryfor','block_facetoface',fullname($user)));
    } else {
       $table->data[] = array(get_string('nobookinghistory','block_facetoface'));
    }
}

echo $OUTPUT->heading(get_string('bookinghistory', 'block_facetoface'));
echo html_writer::table($table);

echo $OUTPUT->box_end();
echo $OUTPUT->footer();

