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
$userid     = optional_param('userid',     $USER->id, PARAM_INT);

$action = optional_param('action',          '', PARAM_ALPHA); // one of: '', export
$format = optional_param('format',       'ods', PARAM_ALPHA); // one of: ods, xls

// filter options
$coursename   = optional_param('coursename', '', PARAM_TEXT);
$courseid     = optional_param('courseid',   '', PARAM_TEXT);
$trainer      = optional_param('trainer',    '', PARAM_TEXT);
$location     = optional_param('location',   '', PARAM_TEXT);

$startdate = make_timestamp($startyear, $startmonth, $startday);
$enddate = make_timestamp($endyear, $endmonth, $endday);

$urlparams = array('startyear' => $startyear, 'startmonth' => $startmonth, 'startday' => $startday,
    'endyear' => $endyear, 'endmonth' => $endmonth, 'endday' => $endday, 'userid' => $userid);

$coursenamesql = '';
$coursenameparam = array();
$courseidsql = '';
$courseidparam = array();
if ($coursename) {
    $coursenamesql = ' AND c.fullname = ?';
    $coursenameparam[] = $coursename;
}
if ($courseid) {
    $courseidsql = ' AND c.idnumber = ?';
    $courseidparam[] = $courseid;
}

$records = '';

// Get all Face-to-face session dates from the DB
$records = $DB->get_records_sql("SELECT d.id, cm.id AS cmid, c.id AS courseid, c.fullname AS coursename,
                                   c.idnumber as cidnumber, f.name, f.id as facetofaceid, s.id as sessionid,
                                   s.datetimeknown, d.timestart, d.timefinish, d.sessiontimezone, su.nbbookings
                              FROM {facetoface_sessions_dates} d
                              JOIN {facetoface_sessions} s ON s.id = d.sessionid
                              JOIN {facetoface} f ON f.id = s.facetoface
                   LEFT OUTER JOIN (SELECT sessionid, count(sessionid) AS nbbookings
                                      FROM {facetoface_signups} su
                                 LEFT JOIN {facetoface_signups_status} ss
                                        ON ss.signupid = su.id AND ss.superceded = 0
                                     WHERE ss.statuscode >= ?
                                  GROUP BY sessionid) su ON su.sessionid = d.sessionid
                              JOIN {course} c ON f.course = c.id

                              JOIN {course_modules} cm ON cm.course = f.course
                                   AND cm.instance = f.id
                              JOIN {modules} m ON m.id = cm.module

                             WHERE d.timestart >= ? AND d.timefinish <= ?
                                   AND m.name = 'facetoface'
                                $coursenamesql
                                $courseidsql", array_merge(array(MDL_F2F_STATUS_BOOKED, $startdate, $enddate), $coursenameparam, $courseidparam));

$show_location = add_location_info($records);

// Only keep the sessions for which this user can see attendees
$dates = array();
if ($records) {
    $capability = 'mod/facetoface:viewattendees';

    // Check the system context first
    $contextsystem = context_system::instance();
    if (has_capability($capability, $contextsystem)) {

        // check if the location or trainer filters need to be used
        if ($location or $trainer) {
            foreach ($records as $record) {

                if ($record->location === $location) {
                    $dates[] = $record;
                }

                if (isset($record->trainers)) {
                    foreach ($record->trainers as $t) {
                        if ($t === $trainer) {
                            $dates[] = $record;
                            continue;
                        }
                    }
                }
            }
        } else {
            $dates = $records;
        }

    } else {
        foreach ($records as $record) {
            if ($location || $trainer) {

                // Check at course level first
                $contextcourse = context_course::instance($record->courseid);
                if (has_capability($capability, $contextcourse)) {
                    if ($record->location === $location) {
                        $dates[] = $record;
                    }

                    if (isset($record->trainers)) {
                        foreach ($record->trainers as $t) {
                            if ($t === $trainer) {
                                $dates[] = $record;
                                continue;
                            }
                        }
                    }
                    continue;
                }

                // Check at module level if the first check failed
                $contextmodule = context_module::instance($record->cmid);
                if (has_capability($capability, $contextmodule)) {
                    if ($record->location === $location) {
                        $dates[] = $record;
                    }

                    if (isset($record->trainers)) {
                        foreach ($record->trainers as $t) {
                            if ($t === $trainer) {
                                $dates[] = $record;
                                continue;
                            }
                        }
                    }
                }
            }
        }
    }
}
$nbdates = count($dates);

// Process actions if any
if ('export' == $action) {
    export_spreadsheet($dates, $format, true);
    exit;
}

// format the session and dates to only show one booking where they span multiple dates
// i.e. multiple days startdate = firstday, finishdate = last day
$groupeddates = group_session_dates($dates);

$pagetitle = format_string(get_string('facetoface', 'facetoface') . ' ' . get_string('sessions', 'block_facetoface'));
$PAGE->navbar->add($pagetitle);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_url('/blocks/facetoface/mysessions.php');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

// show tabs
$currenttab = 'attendees';
include_once('tabs.php');

$renderer = $PAGE->get_renderer('block_facetoface');

if (empty($users)) {
    // Date range form
    echo $OUTPUT->heading(get_string('filters', 'block_facetoface'), 2);
    echo html_writer::start_tag('form', array('method' => 'get', 'action' => '')) . html_writer::start_tag('p');
    print_facetoface_filters($startdate, $enddate, $coursename, $courseid, $location, $trainer);
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('apply', 'block_facetoface'))) . html_writer::end_tag('p') . html_writer::end_tag('form');
}

// Show all session dates
if ($nbdates > 0) {
    echo $OUTPUT->heading(get_string('sessiondatesview', 'block_facetoface'), 2);
    echo $renderer->print_dates($groupeddates, true, false, false, false, false, $show_location);

    // Export form
    echo $OUTPUT->heading(get_string('exportsessiondates', 'block_facetoface'), 3);
    echo html_writer::start_tag('form', array('method' => 'post', 'action' => "")) . html_writer::start_tag('p');
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'startyear', 'value' => $startyear));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'startmonth', 'value' => $startmonth));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'startday', 'value' => $startday));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'endyear', 'value' => $endyear));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'endmonth', 'value' => $endmonth));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'endday', 'value' => $endday));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action', 'value' => 'export'));

    echo get_string('format', 'facetoface').':&nbsp;';
    echo html_writer::start_tag('select', array('name' => 'format'));
    echo html_writer::tag('option', get_string('excelformat', 'facetoface'), array('value' => 'excel', 'selected' => 'selected'));
    echo html_writer::tag('option', get_string('odsformat', 'facetoface'), array('value' => 'ods'));
    echo html_writer::end_tag('select');

    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('exporttofile', 'facetoface'))). html_writer::end_tag('p') . html_writer::end_tag('form');
} else {
    echo $OUTPUT->heading(get_string('sessiondatesview', 'block_facetoface'), 2);
    echo html_writer::tag('p', get_string('sessiondatesviewattendeeszero', 'block_facetoface'));
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer();

