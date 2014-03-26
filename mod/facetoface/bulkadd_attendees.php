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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage facetoface
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');

// Face-to-face session ID
$s = required_param('s', PARAM_INT);

// Upload type
$type = required_param('type', PARAM_ALPHA);
// Send email notifications
$suppressemail  = optional_param('suppressemail', false, PARAM_BOOL);
// Show dialog
$dialog = optional_param('dialog', false, PARAM_BOOL);

// Load data
if (!$session = facetoface_get_session($s)) {
    print_error('error:incorrectcoursemodulesession', 'facetoface');
}
if (!$facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface))) {
    print_error('error:incorrectfacetofaceid', 'facetoface');
}
if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
    print_error('error:coursemisconfigured', 'facetoface');
}
// Check capability
require_login($facetoface->course);

// Generate url
$url = new moodle_url('/mod/facetoface/bulkadd_attendees.php', array('s' => $s, 'type' => $type, 'action' => 'attendees'));

// Generate form

if ($type === 'file') {

    class facetoface_bulkadd_file_form extends moodleform {
        function definition() {
            $mform =& $this->_form;
            $mform->addElement('file', 'userfile', get_string('csvtextfile', 'facetoface'));
            $mform->setType('userfile', PARAM_FILE);
            $mform->addRule('userfile', null, 'required');
            $encodings = textlib::get_encodings();
            $mform->addElement('select', 'encoding', get_string('encoding', 'grades'), $encodings);
            $mform->addElement('checkbox', 'suppressemail', '', get_string('suppressemailforattendees', 'facetoface'));
        }
    }

    $form = new facetoface_bulkadd_file_form($url, null, 'post', '', null, true, 'bulkaddfile');

} else if ($type === 'input') {

    class facetoface_bulkadd_input_form extends moodleform {
        function definition() {
            $this->_form->addElement('textarea', 'csvinput', get_string('csvtextinput', 'facetoface'));
            $this->_form->addElement('checkbox', 'suppressemail', '', get_string('suppressemailforattendees', 'facetoface'));
        }
    }

    $formurl = clone($url);
    $formurl->param('onlycontent', 1);
    $form = new facetoface_bulkadd_input_form($formurl, null, 'post', '', null, true, 'bulkaddinput');
    unset($formurl);
} else {
    error('Invalid parameters supplied');
    die();
}

$bulkaddsource = get_config(null, 'facetoface_bulkaddsource');
if (empty($bulkaddsource)) {
    $bulkaddsource = 'bulkaddsourceidnumber';
}

// Check if data submitted
if ($data = $form->get_data()) {

    // Handle data
    $rawinput = null;
    if ($type === 'input') {
        $rawinput = $data->csvinput;
    } else if ($type === 'file') {
        // Large files are likely to take their time and memory. Let PHP know
        // that we'll take longer, and that the process should be recycled soon
        // to free up memory.
        @set_time_limit(0);
        @raise_memory_limit("192M");
        if (function_exists('apache_child_terminate')) {
            @apache_child_terminate();
        }

        $text = $form->get_file_content('userfile');

        // Trim utf-8 bom.
        $text = textlib::trim_utf8_bom($text);
        // Do the encoding conversion.
        $rawinput = textlib::convert($text, $data->encoding);
    }

    // Replace commas with newlines and remove carriage returns.
    $rawinput = str_replace(array("\r\n", "\r", ","), "\n", $rawinput);

    $addusers = clean_param($rawinput, PARAM_NOTAGS);
    // Turn into array.
    $addusers = explode("\n", $addusers);
    // Remove any leading or trailing spaces.
    $addusers = array_map('trim', $addusers);
    // Filter out empty strings/false/null using array_filter.
    $addusers = array_filter($addusers);

    // Bulk add results
    $errors = array();
    $added = array();

    // Check for data
    if (!$addusers) {
        $errors[] = get_string('error:nodatasupplied', 'facetoface');
    } else {
        $params = array();
        $params['bulkaddsource'] = $bulkaddsource;
        $params['suppressemail'] = $suppressemail;
        // Do not need the approval, change the status
        $params['approvalreqd'] = 0;
        // If it is a list of user, do not need to notify manager
        $params['ccmanager'] = 0;

        // Load users
        foreach ($addusers as $adduser) {
            $result = facetoface_user_import($course, $facetoface, $session, $adduser, $params);
            if ($result['result'] !== true) {
                $errors[] = $result;
            } else {
                $result['result'] = get_string('addedsuccessfully', 'facetoface');
                $added[] = $result;
            }
        }
    }

    // Record results in session for results dialog to access
    if (empty($_SESSION['f2f-bulk-results'])) {
        $_SESSION['f2f-bulk-results'] = array();
    }
    $_SESSION['f2f-bulk-results'][$session->id] = array($added, $errors, $bulkaddsource);

    $result_message = facetoface_generate_bulk_result_notice(array($added, $errors));
    $numattendees = facetoface_get_num_attendees($session->id);
    $overbooked = ($numattendees > $session->capacity);
    if ($overbooked) {
        $overbookedmessage = get_string('capacityoverbookedlong', 'facetoface', array('current' => $numattendees, 'maximum' => $session->capacity));
        $result_message .= $OUTPUT->notification($overbookedmessage, 'notifynotice');
    }

    require($CFG->dirroot.'/mod/facetoface/attendees.php');
    die();
}

if (!$dialog) {
    require($CFG->dirroot.'/mod/facetoface/attendees.php');
    die();
}

// Display form
$form->display();

// Help text
$bulkaddsourcestring = get_string($bulkaddsource, 'facetoface');
$notestring = get_string('bulkaddhelptext', 'facetoface', $bulkaddsourcestring);
notify($notestring, 'notifyinfo', 'left');
