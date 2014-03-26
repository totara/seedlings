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
require_once 'renderer.php';

global $DB, $OUTPUT;

$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$f = optional_param('f', 0, PARAM_INT); // facetoface ID
$location = optional_param('location', '', PARAM_TEXT); // location
$download = optional_param('download', '', PARAM_ALPHA); // download attendance

if ($id) {
    if (!$cm = $DB->get_record('course_modules', array('id' => $id))) {
        print_error('error:incorrectcoursemoduleid', 'facetoface');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('error:coursemisconfigured', 'facetoface');
    }
    if (!$facetoface = $DB->get_record('facetoface', array('id' => $cm->instance))) {
        print_error('error:incorrectcoursemodule', 'facetoface');
    }
}
elseif ($f) {
    if (!$facetoface = $DB->get_record('facetoface', array('id' => $f))) {
        print_error('error:incorrectfacetofaceid', 'facetoface');
    }
    if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
        print_error('error:coursemisconfigured', 'facetoface');
    }
    if (!$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $course->id)) {
        print_error('error:incorrectcoursemoduleid', 'facetoface');
    }
}
else {
    print_error('error:mustspecifycoursemodulefacetoface', 'facetoface');
}

$context = context_module::instance($cm->id);
$PAGE->set_url('/mod/facetoface/view.php', array('id' => $cm->id));
$PAGE->set_context($context);
$PAGE->set_cm($cm);
$PAGE->set_pagelayout('standard');

if (!empty($download)) {
    require_capability('mod/facetoface:viewattendees', $context);
    facetoface_download_attendance($facetoface->name, $facetoface->id, $location, $download);
    exit();
}

require_course_login($course, true, $cm);
require_capability('mod/facetoface:view', $context);

add_to_log($course->id, 'facetoface', 'view', "view.php?id=$cm->id", $facetoface->id, $cm->id);

$title = $course->shortname . ': ' . format_string($facetoface->name);

$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
$PAGE->set_button(update_module_button($cm->id, '', get_string('modulename', 'facetoface')));

$pagetitle = format_string($facetoface->name);

$f2f_renderer = $PAGE->get_renderer('mod_facetoface');

$completion=new completion_info($course);
$completion->set_module_viewed($cm);

echo $OUTPUT->header();

if (empty($cm->visible) and !has_capability('mod/facetoface:viewemptyactivities', $context)) {
    notice(get_string('activityiscurrentlyhidden'));
}
echo $OUTPUT->box_start();
echo $OUTPUT->heading(get_string('allsessionsin', 'facetoface', $facetoface->name), 2);

if ($facetoface->intro) {
    echo $OUTPUT->box_start('generalbox','description');
    $facetoface->intro = file_rewrite_pluginfile_urls($facetoface->intro, 'pluginfile.php', $context->id, 'mod_facetoface', 'intro', null);
    echo format_text($facetoface->intro, $facetoface->introformat);
    echo $OUTPUT->box_end();
}

$locations = get_locations($facetoface->id);
if (count($locations) > 2) {

    echo html_writer::start_tag('form', array('action' => 'view.php', 'method' => 'get'));
    echo html_writer::start_tag('div') . html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'f', 'value' => $facetoface->id));
    echo html_writer::select($locations, 'location', $location, '');
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('showbylocation', 'facetoface')));
    echo html_writer::end_tag('div'). html_writer::end_tag('form');
}

print_session_list($course->id, $facetoface, $location);

if (has_capability('mod/facetoface:viewattendees', $context)) {
    echo $OUTPUT->heading(get_string('exportattendance', 'facetoface'));
    echo html_writer::start_tag('form', array('action' => 'view.php', 'method' => 'get'));
    echo html_writer::start_tag('div') . html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'f', 'value' => $facetoface->id));
    echo get_string('format', 'facetoface') . '&nbsp;';
    $formats = array('excel' => get_string('excelformat', 'facetoface'),
                     'ods' => get_string('odsformat', 'facetoface'));
    echo html_writer::select($formats, 'download', 'excel', '');
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('exporttofile', 'facetoface')));
    echo html_writer::end_tag('div'). html_writer::end_tag('form');
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);

function print_session_list($courseid, $facetoface, $location) {
    global $CFG, $USER, $DB, $OUTPUT, $PAGE;

    $f2f_renderer = $PAGE->get_renderer('mod_facetoface');

    $timenow = time();

    $context = context_course::instance($courseid);
    $f2f_renderer->setcontext($context);
    $viewattendees = has_capability('mod/facetoface:viewattendees', $context);
    $editsessions = has_capability('mod/facetoface:editsessions', $context);

    $bookedsession = null;
    $submissions = facetoface_get_user_submissions($facetoface->id, $USER->id);
    if (!$facetoface->multiplesessions) {
         $submission = array_shift($submissions);
         $bookedsession = $submission;
    }

    $customfields = facetoface_get_session_customfields();

    $upcomingarray = array();
    $previousarray = array();
    $upcomingtbdarray = array();

    if ($sessions = facetoface_get_sessions($facetoface->id, $location)) {
        foreach ($sessions as $session) {

            $sessionstarted = false;
            $sessionfull = false;
            $sessionwaitlisted = false;
            $isbookedsession = false;

            $sessiondata = $session;
            if ($facetoface->multiplesessions) {
                $submission = facetoface_get_user_submissions($facetoface->id, $USER->id,
                        MDL_F2F_STATUS_REQUESTED, MDL_F2F_STATUS_FULLY_ATTENDED, $session->id);
                $bookedsession = array_shift($submission);
            }
            $sessiondata->bookedsession = $bookedsession;

            if ($session->roomid) {
                $room = $DB->get_record('facetoface_room', array('id' => $session->roomid));
                $sessiondata->room = $room;
            }

            // Add custom fields to sessiondata
            $customdata = $DB->get_records('facetoface_session_data', array('sessionid' => $session->id), '', 'fieldid, data');
            $sessiondata->customfielddata = $customdata;

            // Is session waitlisted
            if (!$session->datetimeknown) {
                $sessionwaitlisted = true;
            }

            // Check if session is started
            if ($session->datetimeknown && facetoface_has_session_started($session, $timenow) && facetoface_is_session_in_progress($session, $timenow)) {
                $sessionstarted = true;
            }
            elseif ($session->datetimeknown && facetoface_has_session_started($session, $timenow)) {
                $sessionstarted = true;
            }

            // Put the row in the right table
            if ($sessionstarted) {
                $previousarray[] = $sessiondata;
            }
            elseif ($sessionwaitlisted) {
                $upcomingtbdarray[] = $sessiondata;
            }
            else { // Normal scheduled session
                $upcomingarray[] = $sessiondata;
            }
        }
    }

    $displaytimezones = get_config(null, 'facetoface_displaysessiontimezones');

    // Upcoming sessions
    echo $OUTPUT->heading(get_string('upcomingsessions', 'facetoface'));
    if (empty($upcomingarray) && empty($upcomingtbdarray)) {
        print_string('noupcoming', 'facetoface');
    }
    else {
        $upcomingarray = array_merge($upcomingarray, $upcomingtbdarray);
        // Include information about reservations when drawing the list of sessions.
        $reserveinfo = facetoface_can_reserve_or_allocate($facetoface, $sessions, $context);
        echo html_writer::tag('p', get_string('lastreservation', 'mod_facetoface', $facetoface));
        echo $f2f_renderer->print_session_list_table($customfields, $upcomingarray, $viewattendees, $editsessions, $displaytimezones, $reserveinfo);
    }

    if ($editsessions) {
        echo html_writer::tag('p', html_writer::link(new moodle_url('sessions.php', array('f' => $facetoface->id)), get_string('addsession', 'facetoface')));
    }

    // Previous sessions
    if (!empty($previousarray)) {
        echo $OUTPUT->heading(get_string('previoussessions', 'facetoface'));
        echo $f2f_renderer->print_session_list_table($customfields, $previousarray, $viewattendees, $editsessions, $displaytimezones);
    }
}

/**
 * Get facetoface locations
 *
 * @param   interger    $facetofaceid
 * @return  array
 */
function get_locations($facetofaceid) {
    global $CFG, $DB;

    $locationfieldid = $DB->get_field('facetoface_session_field', 'id', array('shortname' => 'location'));
    if (!$locationfieldid) {
        return array();
    }

    $sql = "SELECT DISTINCT d.data AS location
              FROM {facetoface} f
              JOIN {facetoface_sessions} s ON s.facetoface = f.id
              JOIN {facetoface_session_data} d ON d.sessionid = s.id
             WHERE f.id = ? AND d.fieldid = ?";

    if ($records = $DB->get_records_sql($sql, array($facetofaceid, $locationfieldid))) {
        $locationmenu[''] = get_string('alllocations', 'facetoface');

        $i=1;
        foreach ($records as $record) {
            $locationmenu[$record->location] = $record->location;
            $i++;
        }

        return $locationmenu;
    }

    return array();
}
