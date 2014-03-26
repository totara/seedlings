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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Francois Marier <francois@catalyst.net.nz>
 * @package modules
 * @subpackage facetoface
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once('lib.php');
require_once('session_form.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$f = optional_param('f', 0, PARAM_INT); // facetoface Module ID
$s = optional_param('s', 0, PARAM_INT); // facetoface session ID
$c = optional_param('c', 0, PARAM_INT); // copy session
$d = optional_param('d', 0, PARAM_INT); // delete session
$confirm = optional_param('confirm', false, PARAM_BOOL); // delete confirmation

$nbdays = 1; // default number to show

define('SECONDS_IN_AN_HOUR', 60 * 60);

$session = null;
if ($id && !$s) {
    if (!$cm = $DB->get_record('course_modules', array('id' => $id))) {
        print_error('error:incorrectcoursemoduleid', 'facetoface');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('error:coursemisconfigured', 'facetoface');
    }
    if (!$facetoface =$DB->get_record('facetoface',array('id' => $cm->instance))) {
        print_error('error:incorrectcoursemodule', 'facetoface');
    }
} else if ($s) {
     if (!$session = facetoface_get_session($s)) {
         print_error('error:incorrectcoursemodulesession', 'facetoface');
     }
     if (!$facetoface = $DB->get_record('facetoface',array('id' => $session->facetoface))) {
         print_error('error:incorrectfacetofaceid', 'facetoface');
     }
     if (!$course = $DB->get_record('course', array('id'=> $facetoface->course))) {
         print_error('error:coursemisconfigured', 'facetoface');
     }
     if (!$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $course->id)) {
         print_error('error:incorrectcoursemoduleid', 'facetoface');
     }
     if (!$session->roomid == 0 && !$sroom = $DB->get_record('facetoface_room', array('id' => $session->roomid))) {
        print_error('error:incorrectroomid', 'facetoface');
     }

     $nbdays = count($session->sessiondates);
} else {
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

require_course_login($course);
$errorstr = '';
$context = context_course::instance($course->id);
$module_context = context_module::instance($cm->id);
require_capability('mod/facetoface:editsessions', $context);


local_js(array(
    TOTARA_JS_DIALOG,
    TOTARA_JS_TREEVIEW
));

$PAGE->requires->string_for_js('save', 'totara_core');
$PAGE->requires->string_for_js('error:addpdroom-dialognotselected', 'totara_core');
$PAGE->requires->strings_for_js(array('cancel', 'ok'), 'moodle');
$PAGE->requires->strings_for_js(array('chooseroom', 'pdroomcapacityexceeded'), 'facetoface');

$display_selected = json_encode(dialog_display_currently_selected(get_string('selected', 'facetoface'), 'addpdroom-dialog'));
$args = array('args' => '{"sessionid":'.$s.','.
                         '"display_selected_item":'.$display_selected.'}');

$jsmodule = array(
    'name' => 'totara_f2f_room',
    'fullpath' => '/mod/facetoface/sessions.js',
    'requires' => array('json', 'totara_core'));
$PAGE->requires->js_init_call('M.totara_f2f_room.init', $args, false, $jsmodule);

$returnurl = "view.php?f=$facetoface->id";

$editoroptions = array(
    'noclean'  => false,
    'maxfiles' => EDITOR_UNLIMITED_FILES,
    'maxbytes' => $course->maxbytes,
    'context'  => $module_context,
);

// Handle deletions
if ($d and $confirm) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    if (facetoface_delete_session($session)) {
        add_to_log($course->id, 'facetoface', 'delete session', 'sessions.php?s='.$session->id, $facetoface->id, $cm->id);
    }
    else {
        add_to_log($course->id, 'facetoface', 'delete session (FAILED)', 'sessions.php?s='.$session->id, $facetoface->id, $cm->id);
        print_error('error:couldnotdeletesession', 'facetoface', $returnurl);
    }
    redirect($returnurl);
}

$customfields = facetoface_get_session_customfields();

$sessionid = isset($session->id) ? $session->id : 0;

$details = new stdClass();
$details->id = $sessionid;
$details->details = isset($session->details) ? $session->details : '';
$details->detailsformat = FORMAT_HTML;
$details = file_prepare_standard_editor($details, 'details', $editoroptions, $module_context, 'mod_facetoface', 'session', $sessionid);
if (isset($session)) {
    $defaulttimezone = empty($session->sessiondates[0]->sessiontimezone) ? get_string('sessiontimezoneunknown', 'facetoface') : $session->sessiondates[0]->sessiontimezone;
} else {
    $defaulttimezone = totara_get_clean_timezone();
}

$mform = new mod_facetoface_session_form(null, compact('id', 'f', 's', 'c', 'nbdays', 'customfields', 'course', 'editoroptions', 'defaulttimezone'));
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) { // Form submitted

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'facetoface', $returnurl);
    }

    // Pre-process fields
    if (empty($fromform->allowoverbook)) {
        $fromform->allowoverbook = 0;
    }

    if (empty($fromform->normalcost)) {
        $fromform->normalcost = 0;
    }
    if (empty($fromform->discountcost)) {
        $fromform->discountcost = 0;
    }

    //check dates and calculate total duration
    $sessiondates = array();
    if ($fromform->datetimeknown) {
        $fromform->duration = 0;
    }
    for ($i = 0; $i < $fromform->date_repeats; $i++) {
        if (!empty($fromform->datedelete[$i])) {
            continue; // skip this date
        }
        $timezonefield = $fromform->sessiontimezone;
        $timestartfield = "timestart[$i]_raw";
        $timefinishfield = "timefinish[$i]_raw";
        if (!empty($fromform->$timestartfield) && !empty($fromform->$timefinishfield) && !empty($timezonefield[$i])) {
            $date = new stdClass();
            //Use the raw ISO date string to get an accurate Unix timestamp
            $date->sessiontimezone = $timezonefield[$i];
            $startdt = new DateTime($fromform->$timestartfield, new DateTimeZone($date->sessiontimezone));
            $finishdt = new DateTime($fromform->$timefinishfield, new DateTimeZone($date->sessiontimezone));
            $date->timestart = $startdt->getTimestamp();
            $date->timefinish = $finishdt->getTimestamp();
            if ($fromform->datetimeknown) {
                $fromform->duration += ($date->timefinish - $date->timestart)/SECONDS_IN_AN_HOUR; // Convert seconds to hours
            }
            $sessiondates[] = $date;
        }
    }

    $todb = new stdClass();
    $todb->facetoface = $facetoface->id;
    $todb->datetimeknown = $fromform->datetimeknown;
    $todb->capacity = $fromform->capacity;
    $todb->allowoverbook = $fromform->allowoverbook;
    $todb->duration = $fromform->duration;
    $todb->normalcost = $fromform->normalcost;
    $todb->discountcost = $fromform->discountcost;
    $todb->usermodified = $USER->id;
    $todb->roomid = 0;

    $transaction = $DB->start_delegated_transaction();

    $update = false;
    if (!$c and $session != null) {
        $update = true;
        $todb->id = $session->id;
        $sessionid = $session->id;
        if (!facetoface_update_session($todb, $sessiondates)) {
            add_to_log($course->id, 'facetoface', 'update session (FAILED)', "sessions.php?s=$session->id", $facetoface->id, $cm->id);
            print_error('error:couldnotupdatesession', 'facetoface', $returnurl);
        }
    } else {
        if (!$sessionid = facetoface_add_session($todb, $sessiondates)) {
            add_to_log($course->id, 'facetoface', 'add session (FAILED)', 'sessions.php?f='.$facetoface->id, $facetoface->id, $cm->id);
            print_error('error:couldnotaddsession', 'facetoface', $returnurl);
        }
    }

    // Save session room info.
    if (!facetoface_save_session_room($sessionid, $fromform)) {
        add_to_log($course->id, 'facetoface', 'save room (FAILED)', 'room/manage.php', $facetoface->id, $cm->id);
        print_error('error:couldnotsaveroom', 'facetoface');
    }

    foreach ($customfields as $field) {
        // Need to be able to clear fields.
        $fieldname = "custom_$field->shortname";
        if (!isset($fromform->$fieldname)) {
            $fromform->$fieldname = '';
        }

        if (!facetoface_save_customfield_value($field->id, $fromform->$fieldname, $sessionid, 'session')) {
            print_error('error:couldnotsavecustomfield', 'facetoface', $returnurl);
        }
    }

    $transaction->allow_commit();

    // Retrieve record that was just inserted/updated.
    if (!$session = facetoface_get_session($sessionid)) {
        print_error('error:couldnotfindsession', 'facetoface', $returnurl);
    }

    if ($update) {
        // Now that we have updated the session record fetch the rest of the data we need.
        facetoface_update_attendees($session);
    }

    // Save trainer roles.
    if (isset($fromform->trainerrole)) {
        facetoface_update_trainers($facetoface, $session, $fromform->trainerrole);
    }

    // Save any calendar entries.
    $session->sessiondates = $sessiondates;
    facetoface_update_calendar_entries($session, $facetoface);

    if ($update) {
        add_to_log($course->id, 'facetoface', 'updated session', "sessions.php?s=$session->id", $facetoface->id, $cm->id);
    } else {
        add_to_log($course->id, 'facetoface', 'added session', 'sessions.php?f='.$facetoface->id, $facetoface->id, $cm->id);
    }

    $data = file_postupdate_standard_editor($fromform, 'details', $editoroptions, $module_context, 'mod_facetoface', 'session', $session->id);
    $DB->set_field('facetoface_sessions', 'details', $data->details, array('id' => $session->id));

    redirect($returnurl);
} else if ($session != null) { // Edit mode
    // Set values for the form
    $toform = new stdClass();
    $toform = file_prepare_standard_editor($details, 'details', $editoroptions, $module_context, 'mod_facetoface', 'session', $session->id);

    $toform->datetimeknown = (1 == $session->datetimeknown);
    $toform->capacity = $session->capacity;
    $toform->allowoverbook = $session->allowoverbook;
    $toform->duration = $session->duration;
    $toform->normalcost = $session->normalcost;
    $toform->discountcost = $session->discountcost;

    if ($session->sessiondates) {
        $i = 0;
        foreach ($session->sessiondates as $date) {
            $idfield = "sessiondateid[$i]";
            $timestartfield = "timestart[$i]";
            $timefinishfield = "timefinish[$i]";
            $timezonefield = "sessiontimezone[$i]";

            $toform->$idfield = $date->id;
            $toform->$timestartfield = $date->timestart;
            $toform->$timefinishfield = $date->timefinish;
            $toform->$timezonefield = $date->sessiontimezone;
            $i++;
        }
    }

    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";
        $toform->$fieldname = facetoface_get_customfield_value($field, $session->id, 'session');
        if (empty($toform->$fieldname)) {
            $toform->$fieldname = $field->defaultvalue;
        }
    }

    if (!empty($sroom->id)) {
        if (!$sroom->custom) {
            // Pre-defined room
            $toform->pdroomid = $session->roomid;
            $toform->pdroomcapacity = $sroom->capacity;
        } else {
            // Custom room
            $toform->customroom = 1;
            $toform->croomname = $sroom->name;
            $toform->croombuilding = $sroom->building;
            $toform->croomaddress = $sroom->address;
            $toform->croomcapacity = $sroom->capacity;
        }
    }

    $mform->set_data($toform);
}

if ($c) {
    $heading = get_string('copyingsession', 'facetoface', $facetoface->name);
}
else if ($d) {
    $heading = get_string('deletingsession', 'facetoface', $facetoface->name);
}
else if ($id or $f) {
    $heading = get_string('addingsession', 'facetoface', $facetoface->name);
}
else {
    $heading = get_string('editingsession', 'facetoface', $facetoface->name);
}

$pagetitle = format_string($facetoface->name);

$PAGE->set_cm($cm);
$PAGE->set_url('/mod/facetoface/sessions.php', array('f' => $f));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

echo $OUTPUT->box_start();
echo $OUTPUT->heading($heading);

if (!empty($errorstr)) {
    echo $OUTPUT->container(html_writer::tag('span', $errorstr, array('class' => 'errorstring')), array('class' => 'notifyproblem'));
}

if ($d) {
    $viewattendees = has_capability('mod/facetoface:viewattendees', $context);
    facetoface_print_session($session, $viewattendees);
    $optionsyes = array('sesskey' => sesskey(), 's' => $session->id, 'd' => 1, 'confirm' => 1);
    echo $OUTPUT->confirm(get_string('deletesessionconfirm', 'facetoface', format_string($facetoface->name)),
        new moodle_url('sessions.php', $optionsyes),
        new moodle_url($returnurl));
}
else {
    $mform->display();
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);
