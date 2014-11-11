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
 * @author Francois Marier <francois@catalyst.net.nz>
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @package totara
 * @subpackage reportbuilder
 */

require_once(dirname(dirname(dirname(__FILE__)))."/config.php");
require_once($CFG->dirroot . '/mod/facetoface/lib.php');

define('MAX_USERS_PER_PAGE', 1000);

$s              = required_param('s', PARAM_INT); // facetoface session ID
$add            = optional_param('add', 0, PARAM_BOOL);
$remove         = optional_param('remove', 0, PARAM_BOOL);
$recipients     = optional_param('recipients', '', PARAM_SEQUENCE);

if (!$session = facetoface_get_session($s)) {
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
$context = context_module::instance($cm->id);

// Check essential permissions
require_login($course, false, $cm);
require_capability('mod/facetoface:viewattendees', $context);

// Recipients
$recipients = explode(',', $recipients);

foreach ($recipients as $key => $recipient) {
    if (!$recipient) {
        unset($recipients[$key]);
    }
}

// Handle the POST actions sent to the page
if ($frm = data_submitted()) {
    // Add button
    if ($add and !empty($frm->addselect) and confirm_sesskey()) {
        require_capability('mod/facetoface:addattendees', $context);

        foreach ($frm->addselect as $adduser) {
            if (!$adduser = clean_param($adduser, PARAM_INT)) {
                continue; // invalid userid
            }

            $recipients[] = $adduser;
        }
    }
    // Remove button
    else if ($remove and !empty($frm->removeselect) and confirm_sesskey()) {
        require_capability('mod/facetoface:removeattendees', $context);

        foreach ($frm->removeselect as $removeuser) {
            if (!$removeuser = clean_param($removeuser, PARAM_INT)) {
                continue; // invalid userid
            }

            $recipients = array_diff($recipients, array($removeuser));
        }
    }
}

// Main page
// Get the list of currently selected recipients
$existingusers = array();
if ($recipients) {
    list($insql, $params) = $DB->get_in_or_equal($recipients);

    $existingusers = $DB->get_records_sql('
        SELECT id, firstname, lastname, email
        FROM {user}
        WHERE id ' . $insql, $params);
}

$existingcount = $existingusers ? count($existingusers) : 0;

$sql  = "
    FROM {user}
   WHERE id IN
        (
        SELECT s.userid
            FROM {facetoface_signups} s
            WHERE s.sessionid = ?
        )
   ORDER BY lastname ASC, firstname ASC
";

// Get all available attendees
$availableusers = $DB->get_records_sql('SELECT id, firstname, lastname, email ' . $sql, array($session->id));
$availableusers = array_diff_key($availableusers, $existingusers);

$usercount = count($availableusers);


// Prints a form to add/remove users from the recipients list
include('editrecipients.html');
