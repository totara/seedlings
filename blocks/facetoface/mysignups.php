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

// Displays sessions for which the current user is a "teacher" (can see attendees' list)
// as well as the ones where the user is signed up (i.e. a "student")

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once('lib.php');

$PAGE->set_context(context_system::instance());
require_login();

$timenow = time();
$timelater = $timenow + 13 * WEEKSECS;

$startyear  = optional_param('startyear',  strftime('%Y', $timenow), PARAM_INT);
$startmonth = optional_param('startmonth', strftime('%m', $timenow), PARAM_INT);
$startday   = optional_param('startday',   strftime('%d', $timenow), PARAM_INT);
$endyear    = optional_param('endyear',    strftime('%Y', $timelater), PARAM_INT);
$endmonth   = optional_param('endmonth',   strftime('%m', $timelater), PARAM_INT);
$endday     = optional_param('endday',     strftime('%d', $timelater), PARAM_INT);

$action = optional_param('action',          '', PARAM_ALPHA); // one of: '', export
$format = optional_param('format',       'ods', PARAM_ALPHA); // one of: ods, xls

$userid = optional_param('userid', $USER->id, PARAM_INT);
if (!$user = $DB->get_record('user', array('id' => $userid))) {
    print_error('error:invaliduserid', 'block_facetoface', 'mysignups.php');
}

if ($userid != $USER->id) {
    $contextuser = context_user::instance($userid);
    require_capability('block/facetoface:viewbookings', $contextuser);
}

$search = optional_param('search', '', PARAM_TEXT); // search string

$startdate = make_timestamp($startyear, $startmonth, $startday);
$enddate = make_timestamp($endyear, $endmonth, $endday);

$urlparams = array('startyear' => $startyear, 'startmonth' => $startmonth, 'startday' => $startday,
    'endyear' => $endyear, 'endmonth' => $endmonth, 'endday' => $endday, 'userid' => $userid);

// Process actions if any
if ('export' == $action) {
    export_spreadsheet($dates, $format, true);
    exit;
}

$signups = '';
$users = '';

if ($search) {
    $users = get_users_search($search);
} else {
    // Get all Face-to-face signups from the DB
    $signups = $DB->get_records_sql("SELECT d.id, c.id as courseid, c.fullname AS coursename, f.name,
                                       f.id as facetofaceid, s.id as sessionid, s.datetimeknown,
                                       d.timestart, d.timefinish, d.sessiontimezone, su.userid, ss.statuscode as status
                                  FROM {facetoface_sessions_dates} d
                                  JOIN {facetoface_sessions} s ON s.id = d.sessionid
                                  JOIN {facetoface} f ON f.id = s.facetoface
                                  JOIN {facetoface_signups} su ON su.sessionid = s.id
                                  JOIN {facetoface_signups_status} ss ON su.id = ss.signupid AND ss.superceded = 0
                                  JOIN {course} c ON f.course = c.id
                                 WHERE d.timestart >= ? AND d.timefinish <= ? AND
                                       su.userid = ?", array($startdate, $enddate, $user->id));

    $show_location = add_location_info($signups);
}

// format the session and dates to only show one booking where they span multiple dates
// i.e. multiple days startdate = firstday, finishdate = last day
$groupeddates = array();
if ($signups and count($signups > 0)) {
    $groupeddates = group_session_dates($signups);
}

// out of the results separate out the future sessions
$futuresessions = future_session_dates($groupeddates);
$nbfuture = 0;
if ($futuresessions and count($futuresessions) > 0) {
    $nbfuture = count($futuresessions);
}

// and the past sessions
$pastsessions = past_session_dates($groupeddates);
$nbpast = 0;
if ($pastsessions and count($pastsessions) > 0) {
    $nbpast = count($pastsessions);
}

$pagetitle = format_string(get_string('facetoface', 'facetoface') . ' ' . get_string('bookings', 'block_facetoface'));
$PAGE->navbar->add($pagetitle);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_url('/blocks/facetoface/mysignups.php');
$PAGE->set_pagelayout('standard');
echo $OUTPUT->header();
echo $OUTPUT->box_start();

// show tabs
$currenttab = 'attending';
include_once('tabs.php');

if (empty($users)) {

    $renderer = $PAGE->get_renderer('block_facetoface');

    // Date range form
    echo html_writer::start_tag('form', array('method' => 'get', 'action' => "")) . html_writer::start_tag('p');
    echo get_string('daterange', 'block_facetoface') . ' ';
    echo html_writer::select_time('days', 'startday', $startdate);
    echo html_writer::select_time('months', 'startmonth', $startdate);
    echo html_writer::select_time('years', 'startyear', $startdate);
    echo ' ' . strtolower(get_string('to')) . ' ';
    echo html_writer::select_time('days', 'endday', $enddate);
    echo html_writer::select_time('months', 'endmonth', $enddate);
    echo html_writer::select_time('years', 'endyear', $enddate);
    echo ' ' . html_writer::empty_tag('input', array('type' => 'hidden', 'value' => $userid, 'name' => 'userid'));
    echo ' ' . html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('apply', 'block_facetoface'))) . html_writer::end_tag('p') . html_writer::end_tag('form');

    // Show sign-ups
    if ($userid != $USER->id) {
        echo $OUTPUT->heading(get_string('futurebookingsfor', 'block_facetoface', fullname($user)));
    } else {
        echo $OUTPUT->heading(get_string('futurebookings', 'block_facetoface'));
    }
    if ($nbfuture > 0) {
        echo $renderer->print_dates($futuresessions, false, false, true, false, false, $show_location);
    }
    else{
        echo html_writer::tag('p', get_string('signedupinzero', 'block_facetoface'));
    }

    // Show past bookings
    if ($userid != $USER->id) {
        echo $OUTPUT->heading(get_string('pastbookingsfor', 'block_facetoface', fullname($user)));
    } else {
        echo $OUTPUT->heading(get_string('pastbookings', 'block_facetoface'));
    }
    if ($nbpast > 0) {
        echo $renderer->print_dates($pastsessions, false, true, false, false, false, $show_location);
    }
    else{
        echo html_writer::tag('p', get_string('signedupinzero', 'block_facetoface'));
    }
} else if ($users) {
    if (count($users) > 0) {
        echo $OUTPUT->heading(get_string('searchedusers', 'block_facetoface', count($users)));
        foreach ($users as $u) {
            echo html_writer::link(new moodle_url('/blocks/facetoface/mysignups.php', array_merge($urlparams, array('userid' => $u->id))), fullname($u)) . html_writer::empty_tag('br');
        }
    }
}

echo $OUTPUT->heading(get_string('searchusers', 'block_facetoface'));

echo html_writer::start_tag('form', array('class' => 'learnersearch', 'id' => 'searchquery', 'method' => 'post', 'action' => $CFG->wwwroot.'/blocks/facetoface/mysignups.php'));
echo $OUTPUT->container_start('usersearch');
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'startyear', 'value' => $startyear));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'startmonth', 'value' => $startmonth));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'startday', 'value' => $startday));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'endyear', 'value' => $endyear));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'endmonth', 'value' => $endmonth));
echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'endday', 'value' => $endday));
echo html_writer::empty_tag('input', array('class' => 'searchform', 'type' => 'text', 'name' => 'search', 'size' => '35', 'maxlength' => '255', 'value' => $search));
echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Search'));
echo $OUTPUT->container_end();
echo html_writer::end_tag('form');

echo $OUTPUT->box_end();
echo $OUTPUT->footer();

