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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/mod/facetoface/lib.php');
require_once($CFG->dirroot.'/totara/core/searchlib.php');
require_once($CFG->dirroot.'/totara/core/utils.php');

define('MAX_USERS_PER_PAGE', 1000);

$s              = required_param('s', PARAM_INT); // facetoface session ID
$add            = optional_param('add', 0, PARAM_BOOL);
$remove         = optional_param('remove', 0, PARAM_BOOL);
$searchtext     = optional_param('searchtext', '', PARAM_CLEAN); // search string
$searchbutton   = optional_param('searchbutton', 0, PARAM_BOOL);
$suppressemail  = optional_param('suppressemail', false, PARAM_BOOL); // send email notifications
$clear          = optional_param('clear', false, PARAM_BOOL); // new add/edit session, clear previous results
$onlycontent    = optional_param('onlycontent', false, PARAM_BOOL); // return content of attendees page
$attendees      = optional_param('attendees', '', PARAM_SEQUENCE);
$removedusers   = optional_param('removedusers', '', PARAM_SEQUENCE); // Cancellations and removed users list
$save           = optional_param('save', false, PARAM_BOOL);

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


// Check essential permissions
require_course_login($course);
$context = context_course::instance($course->id);
require_capability('mod/facetoface:viewattendees', $context);

// Get some language strings
$strsearch = get_string('search');
$strshowall = get_string('showall', 'moodle', '');
$strsearchresults = get_string('searchresults');
$strfacetofaces = get_string('modulenameplural', 'facetoface');
$strfacetoface = get_string('modulename', 'facetoface');

// Set wait-list
$waitlist = $session->datetimeknown ? 0 : 1;

// Set removed users
$removed = $removedusers ? explode(',', $removedusers) : array();

// Get facetoface cancellations and add them to the removed attendees list
if (!$removed) {
    $removed = array_keys(facetoface_get_cancellations($s));
}

// Setup attendees array
if ($clear) {
    if ($session->datetimeknown) {
        $attendees = facetoface_get_attendees($session->id, array(MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_NO_SHOW,
            MDL_F2F_STATUS_PARTIALLY_ATTENDED, MDL_F2F_STATUS_FULLY_ATTENDED));
    } else {
        $attendees = facetoface_get_attendees($session->id, array(MDL_F2F_STATUS_WAITLISTED, MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_NO_SHOW,
            MDL_F2F_STATUS_PARTIALLY_ATTENDED, MDL_F2F_STATUS_FULLY_ATTENDED));
    }
} else {
    if ($attendees) {
        $attendee_array = explode(',', $attendees);
        list($attendeesin, $params) = $DB->get_in_or_equal($attendee_array);
        $attendees = $DB->get_records_sql("SELECT u.*, ss.statuscode
                                        FROM {user} u
                                        LEFT JOIN {facetoface_signups} su
                                          ON u.id = su.userid
                                         AND su.sessionid = {$session->id}
                                        LEFT JOIN {facetoface_signups_status} ss
                                          ON su.id = ss.signupid
                                         AND ss.superceded != 1
                                       WHERE u.id {$attendeesin}", $params);
    }
}

if (!$attendees) {
    $attendees = array();
}

// Set takeattendance base on the attendes number
$sessionstarted = facetoface_has_session_started($session, time());
$takeattendance = ($attendees && $session->datetimeknown && $sessionstarted) ? 1 : 0;

// Get users waiting approval to add to the "already attending" list as we do not want to add them again
$waitingapproval = facetoface_get_requests($session->id);

// Set requireapproval
$requireapproval = ($waitingapproval) ? 1 : 0;

// If we are finished editing, save
if ($save && $onlycontent) {

    if (empty($_SESSION['f2f-bulk-results'])) {
        $_SESSION['f2f-bulk-results'] = array();
    }

    $added  = array();
    $errors = array();

    // Original booked attendees plus those awaiting approval
    if ($session->datetimeknown) {
        $original = facetoface_get_attendees($session->id, array(MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_NO_SHOW,
            MDL_F2F_STATUS_PARTIALLY_ATTENDED, MDL_F2F_STATUS_FULLY_ATTENDED));
    } else {
        $original = facetoface_get_attendees($session->id, array(MDL_F2F_STATUS_WAITLISTED, MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_NO_SHOW,
            MDL_F2F_STATUS_PARTIALLY_ATTENDED, MDL_F2F_STATUS_FULLY_ATTENDED));
    }
    // Add those awaiting approval
    foreach ($waitingapproval as $waiting) {
        if (!isset($original[$waiting->id])) {
            $original[$waiting->id] = $waiting;
        }
    }

    // Adding new attendees.
    // Check if we need to add anyone.
    $attendeestoadd = array_diff_key($attendees, $original);
    if (!empty($attendeestoadd) && has_capability('mod/facetoface:addattendees', $context)) {
        // Prepare params
        $params = array();
        $params['suppressemail'] = $suppressemail;
        // Do not need the approval, change the status
        $params['approvalreqd'] = 0;
        // If it is a list of user, do not need to notify manager
        $params['ccmanager'] = 0;
        foreach ($attendeestoadd as $attendee) {
            $result = facetoface_user_import($course, $facetoface, $session, $attendee->id, $params);
            if ($result['result'] !== true) {
                $errors[] = $result;
            } else {
                $result['result'] = get_string('addedsuccessfully', 'facetoface');
                $added[] = $result;
            }
        }
    }

    // Removing old attendees.
    // Check if we need to remove anyone.
    $attendeestoremove = array_diff_key($original, $attendees);
    if (!empty($attendeestoremove) && has_capability('mod/facetoface:removeattendees', $context)) {
        foreach ($attendeestoremove as $attendee) {
            $result = array();
            $result['id'] = $attendee->id;
            $result['name'] = fullname($attendee);

            if (facetoface_user_cancel($session, $attendee->id, true, $cancelerr)) {
                // Notify the user of the cancellation if the session hasn't started yet
                $timenow = time();
                if (!$suppressemail and !facetoface_has_session_started($session, $timenow)) {
                    facetoface_send_cancellation_notice($facetoface, $session, $attendee->id);
                }
                $result['result'] = get_string('removedsuccessfully', 'facetoface');
                $added[] = $result;
            } else {
                $result['result'] = $cancelerr;
                $errors[] = $result;
            }
        }
    }

    $_SESSION['f2f-bulk-results'][$session->id] = array($added, $errors);

    $result_message = facetoface_generate_bulk_result_notice(array($added, $errors), 'addedit');
    $numattendees = facetoface_get_num_attendees($session->id);
    $overbooked = ($numattendees > $session->capacity);
    if ($overbooked) {
        $overbookedmessage = get_string('capacityoverbookedlong', 'facetoface', array('current' => $numattendees, 'maximum' => $session->capacity));
        $result_message .= $OUTPUT->notification($overbookedmessage, 'notifynotice');
    }

    require($CFG->dirroot . '/mod/facetoface/attendees.php');
    die();
}

// Add the waiting-approval users - we don't want to add them again
foreach ($waitingapproval as $waiting) {
    if (!isset($attendees[$waiting->id])) {
        $attendees[$waiting->id] = $waiting;
    }
}
// Handle the POST actions sent to the page
if ($frm = data_submitted()) {
    require_sesskey();
    // Add button
    if ($add and !empty($frm->addselect)) {
        foreach ($frm->addselect as $adduser) {
            if (!$adduser = clean_param($adduser, PARAM_INT)) {
                continue; // invalid userid
            }

            $adduser = $DB->get_record('user', array('id' => $adduser), 'id, lastname, firstname, email');
            $adduser->statuscode = MDL_F2F_STATUS_BOOKED;
            if ($adduser) {
                $attendees[$adduser->id] = $adduser;
            }
        }
        // Remove any attendees from the removed users list
        $removed = array_diff($removed, array_keys($attendees));
    } else if ($remove and !empty($frm->removeselect)) { // Remove button
        foreach ($frm->removeselect as $removeuser) {
            if (!$removeuser = clean_param($removeuser, PARAM_INT)) {
                continue; // invalid userid
            }

            if (isset($attendees[$removeuser])) {
                // Real cancellation - The user is signed up for this session and has a status code
                if ($attendees[$removeuser]->statuscode) {
                    $removed[] = $removeuser;
                }
                unset($attendees[$removeuser]);
            }
        }

    } else if (!$searchbutton) { // Initialize search if "Show all" button is clicked
        $searchtext = '';
    }

    // Set takeattendance for the new users
    $attendance = totara_search_for_value($attendees, 'statuscode', TOTARA_SEARCH_OP_GREATER_THAN, MDL_F2F_STATUS_REQUESTED);
    $takeattendance = ($attendance && $session->datetimeknown && $sessionstarted) ? 1 : 0;

    // Set approval required for the new users
    $requireapproval = (totara_search_for_value($attendees, 'statuscode', TOTARA_SEARCH_OP_EQUAL, MDL_F2F_STATUS_REQUESTED)) ? 1 : 0;

}

// Main page
$attendeescount = count($attendees);

$where = "username <> 'guest' AND deleted = 0 AND suspended = 0 AND confirmed = 1";
$params = array();

// Apply search terms
$searchtext = trim($searchtext);
if ($searchtext !== '') {   // Search for a subset of remaining users
    $fullname  = $DB->sql_fullname();
    $fields = array($fullname, 'email', 'idnumber', 'username');
    $keywords = totara_search_parse_keywords($searchtext);
    list($searchwhere, $searchparams) = totara_search_get_keyword_where_clause($keywords, $fields);

    $where .= ' AND ' . $searchwhere;
    $params = array_merge($params, $searchparams);
}

// All non-signed up system users
if ($attendees) {
    list($attendee_sql, $attendee_params) = $DB->get_in_or_equal(array_keys($attendees), SQL_PARAMS_QM, 'param', false);
    $where .= ' AND u.id ' . $attendee_sql;
    $params = array_merge($params, $attendee_params);
}

$usercountrow = $DB->get_record_sql("SELECT COUNT(u.id) as num
                                               FROM {user} u
                                               LEFT JOIN {facetoface_signups} su
                                                 ON u.id = su.userid
                                                AND su.sessionid = {$session->id}
                                               LEFT JOIN {facetoface_signups_status} ss
                                                 ON su.id = ss.signupid
                                                AND ss.superceded != 1
                                      WHERE {$where} ", $params);

$usercount = $usercountrow->num;

if ($usercount <= MAX_USERS_PER_PAGE) {
    $availableusers = $DB->get_recordset_sql("SELECT u.id, u.firstname, u.lastname, u.email, ss.statuscode
                                        FROM {user} u
                                        LEFT JOIN {facetoface_signups} su
                                          ON u.id = su.userid
                                         AND su.sessionid = {$session->id}
                                        LEFT JOIN {facetoface_signups_status} ss
                                          ON su.id = ss.signupid
                                         AND ss.superceded != 1
                                       WHERE {$where}
                                       ORDER BY u.lastname ASC, u.firstname ASC", $params);
}

// Prints a form to add/remove users from the session
include('editattendees.html');
