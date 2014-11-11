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
defined('MOODLE_INTERNAL') || die();

// TODO: These includes are VERY wrong, lib.php must include as little as possible! Solution is to create locallib.php and move most of the stuff there.

require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->dirroot.'/lib/adminlib.php');
require_once($CFG->dirroot . '/user/selector/lib.php');
require_once $CFG->dirroot.'/mod/facetoface/messaginglib.php';
require_once $CFG->dirroot.'/mod/facetoface/notification/lib.php';
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot . '/mod/facetoface/signup_form.php');

/**
 * Definitions for setting notification types
 */
/**
 * Utility definitions
 */
define('MDL_F2F_NONE',          0);
define('MDL_F2F_TEXT',          2);
define('MDL_F2F_BOTH',          3);
define('MDL_F2F_INVITE',        4);
define('MDL_F2F_CANCEL',        8);

/**
 * Definitions for use in forms
 */
define('MDL_F2F_INVITE_BOTH',        7);     // Send a copy of both 4+1+2
define('MDL_F2F_INVITE_TEXT',        6);     // Send just a plain email 4+2
define('MDL_F2F_INVITE_ICAL',        5);     // Send just a combined text/ical message 4+1
define('MDL_F2F_CANCEL_BOTH',        11);    // Send a copy of both 8+2+1
define('MDL_F2F_CANCEL_TEXT',        10);    // Send just a plan email 8+2
define('MDL_F2F_CANCEL_ICAL',        9);     // Send just a combined text/ical message 8+1

// Custom field related constants
define('CUSTOMFIELD_DELIMITER', '##SEPARATOR##');
define('CUSTOMFIELD_TYPE_TEXT',        0);
define('CUSTOMFIELD_TYPE_SELECT',      1);
define('CUSTOMFIELD_TYPE_MULTISELECT', 2);

// Calendar-related constants
define('CALENDAR_MAX_NAME_LENGTH', 32);
define('F2F_CAL_NONE',      0);
define('F2F_CAL_COURSE',    1);
define('F2F_CAL_SITE',      2);

// Signup status codes (remember to update $MDL_F2F_STATUS)
define('MDL_F2F_STATUS_USER_CANCELLED',     10);
// SESSION_CANCELLED is not yet implemented
define('MDL_F2F_STATUS_SESSION_CANCELLED',  20);
define('MDL_F2F_STATUS_DECLINED',           30);
define('MDL_F2F_STATUS_REQUESTED',          40);
define('MDL_F2F_STATUS_APPROVED',           50);
define('MDL_F2F_STATUS_WAITLISTED',         60);
define('MDL_F2F_STATUS_BOOKED',             70);
define('MDL_F2F_STATUS_NO_SHOW',            80);
define('MDL_F2F_STATUS_PARTIALLY_ATTENDED', 90);
define('MDL_F2F_STATUS_FULLY_ATTENDED',     100);
define('MDL_F2F_STATUS_NOT_SET',            110);

// Define bulk attendance options
define('MDL_F2F_SELECT_ALL', 10);
define('MDL_F2F_SELECT_NONE', 20);
define('MDL_F2F_SELECT_SET', 30);
define('MDL_F2F_SELECT_NOT_SET', 40);

// This array must match the status codes above, and the values
// must equal the end of the constant name but in lower case
global $MDL_F2F_STATUS, $F2F_SELECT_OPTIONS;
$MDL_F2F_STATUS = array(
    MDL_F2F_STATUS_USER_CANCELLED       => 'user_cancelled',
//  SESSION_CANCELLED is not yet implemented
//    MDL_F2F_STATUS_SESSION_CANCELLED    => 'session_cancelled',
    MDL_F2F_STATUS_DECLINED             => 'declined',
    MDL_F2F_STATUS_REQUESTED            => 'requested',
    MDL_F2F_STATUS_APPROVED             => 'approved',
    MDL_F2F_STATUS_WAITLISTED           => 'waitlisted',
    MDL_F2F_STATUS_BOOKED               => 'booked',
    MDL_F2F_STATUS_NO_SHOW              => 'no_show',
    MDL_F2F_STATUS_PARTIALLY_ATTENDED   => 'partially_attended',
    MDL_F2F_STATUS_FULLY_ATTENDED       => 'fully_attended',
    MDL_F2F_STATUS_NOT_SET              => 'not_set'
);

$F2F_SELECT_OPTIONS = array(
    MDL_F2F_SELECT_NONE    => get_string('selectnoneop', 'facetoface'),
    MDL_F2F_SELECT_ALL     => get_string('selectallop', 'facetoface'),
    MDL_F2F_SELECT_SET     => get_string('selectsetop', 'facetoface'),
    MDL_F2F_SELECT_NOT_SET => get_string('selectnotsetop', 'facetoface')
);


/**
 * Returns the human readable code for a face-to-face status
 *
 * @param int $statuscode One of the MDL_F2F_STATUS* constants
 * @return string Human readable code
 */
function facetoface_get_status($statuscode) {
    global $MDL_F2F_STATUS;
    // Check code exists
    if (!isset($MDL_F2F_STATUS[$statuscode])) {
        print_error('F2F status code does not exist: '.$statuscode);
    }

    // Get code
    $string = $MDL_F2F_STATUS[$statuscode];

    // Check to make sure the status array looks to be up-to-date
    if (constant('MDL_F2F_STATUS_'.strtoupper($string)) != $statuscode) {
        print_error('F2F status code array does not appear to be up-to-date: '.$statuscode);
    }

    return $string;
}

/**
 * Obtains the automatic completion state for this face to face activity based on any conditions
 * in face to face settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function facetoface_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    $result = $type;

    // Get face to face.
    if (!$facetoface = $DB->get_record('facetoface', array('id' => $cm->instance))) {
        print_error('cannotfindfacetoface');
    }

    // Only check for existence of tracks and return false if completionstatusrequired.
    // This means that if only view is required we don't end up with a false state.
    if ($facetoface->completionstatusrequired) {
        $completionstatusrequired = json_decode($facetoface->completionstatusrequired, true);
        if (empty($completionstatusrequired)) {
            return $result;
        }
        list($insql, $inparams) = $DB->get_in_or_equal(array_keys($completionstatusrequired));

        // Get user's face to face status.
        $sql = "SELECT f2fss.id AS signupstatusid, f2fss.statuscode, f2fsd.timefinish
                FROM {facetoface_sessions} f2fses
                LEFT JOIN {facetoface_signups} f2fs ON (f2fs.sessionid = f2fses.id)
                LEFT JOIN {facetoface_signups_status} f2fss ON (f2fss.signupid = f2fs.id)
                LEFT JOIN {facetoface_sessions_dates} f2fsd ON (f2fsd.sessionid = f2fses.id)
                WHERE f2fses.facetoface = ? AND f2fs.userid = ?
                  AND f2fss.statuscode $insql
                ORDER BY f2fsd.timefinish";
        $params = array_merge(array($facetoface->id, $userid), $inparams);
        $status = $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
        if ($status) {
            // Tell completion_criteria_activity::review exact time of completion, otherwise it will use time of review run.
            $cm->timecompleted = $status->timefinish;
            return completion_info::aggregate_completion_states($type, $result, true);
        }
        return completion_info::aggregate_completion_states($type, $result, false);
    }
    return $result;
}

/**
 * Sets activity completion state
 *
 * @param stdClass $facetoface object
 * @param int $userid User ID
 * @param int $completionstate Completion state
 */
function facetoface_set_completion($facetoface, $userid, $completionstate = COMPLETION_COMPLETE) {
    $course = new stdClass();
    $course->id = $facetoface->course;
    $completion = new completion_info($course);

    // Check if completion is enabled site-wide, or for the course
    if (!$completion->is_enabled()) {
        return;
    }

    $cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $facetoface->course);
    if (empty($cm) || !$completion->is_enabled($cm)) {
            return;
    }

    $completion->update_state($cm, $completionstate, $userid);
    $completion->invalidatecache($facetoface->course, $userid, true);
}

/**
 * Returns the effective cost of a session depending on the presence
 * or absence of a discount code.
 *
 * @param class $sessiondata contains the discountcost and normalcost
 */
function facetoface_cost($userid, $sessionid, $sessiondata) {
    global $CFG,$DB;

    $count = $DB->count_records_sql("SELECT COUNT(*)
                               FROM {facetoface_signups} su,
                                    {facetoface_sessions} se
                              WHERE su.sessionid = ?
                                AND su.userid = ?
                                AND su.discountcode IS NOT NULL
                                AND su.sessionid = se.id", array($sessionid, $userid));
    if ($count > 0) {
        return format_string($sessiondata->discountcost);
    } else {
        return format_string($sessiondata->normalcost);
    }
}

/**
 * Human-readable version of the duration field used to display it to
 * users
 *
 * @param   integer $duration duration in hours
 * @return  string
 */
function format_duration($duration) {

    $components = explode(':', $duration);

    // Default response
    $string = '';

    // Check for bad characters
    if (trim(preg_match('/[^0-9:\.\s]/', $duration))) {
        return $string;
    }

    if ($components and count($components) > 1) {
        // e.g. "1:30" => "1 hour and 30 minutes"
        $hours = round($components[0]);
        $minutes = round($components[1]);
    }
    else {
        // e.g. "1.5" => "1 hour and 30 minutes"
        $hours = floor($duration);
        $minutes = round(($duration - floor($duration)) * 60);
    }

    // Check if either minutes is out of bounds
    if ($minutes >= 60) {
        return $string;
    }

    if (1 == $hours) {
        $string = get_string('onehour', 'facetoface');
    } elseif ($hours > 1) {
        $string = get_string('xhours', 'facetoface', $hours);
    }

    // Insert separator between hours and minutes
    if ($string != '') {
        $string .= ' ';
    }

    if (1 == $minutes) {
        $string .= get_string('oneminute', 'facetoface');
    } elseif ($minutes > 0) {
        $string .= get_string('xminutes', 'facetoface', $minutes);
    }

    return $string;
}

/**
 * Converts minutes to hours
 */
function facetoface_minutes_to_hours($minutes) {
    if (!intval($minutes)) {
        return 0;
    }

    if ($minutes > 0) {
        $hours = floor($minutes / 60.0);
        $mins = $minutes - ($hours * 60.0);
        return "$hours:$mins";
    } else {
        return $minutes;
    }
}

/**
 * Converts hours to minutes
 */
function facetoface_hours_to_minutes($hours) {
    $components = explode(':', $hours);
    if ($components and count($components) > 1) {
        // e.g. "1:45" => 105 minutes
        $hours = $components[0];
        $minutes = $components[1];
        return $hours * 60.0 + $minutes;
    } else {
        // e.g. "1.75" => 105 minutes
        return round($hours * 60.0);
    }
}

/**
 * Turn undefined manager messages into empty strings and deal with checkboxes
 */
function facetoface_fix_settings($facetoface) {

    if (empty($facetoface->completionstatusrequired)) {
        $facetoface->completionstatusrequired = null;
    }
    if (empty($facetoface->reservecancel)) {
        $facetoface->reservecanceldays = 0;
    }
    if (empty($facetoface->emailmanagerconfirmation)) {
        $facetoface->confirmationinstrmngr = null;
    }
    if (empty($facetoface->emailmanagerreminder)) {
        $facetoface->reminderinstrmngr = null;
    }
    if (empty($facetoface->emailmanagercancellation)) {
        $facetoface->cancellationinstrmngr = null;
    }
    if (empty($facetoface->usercalentry)) {
        $facetoface->usercalentry = 0;
    }
    if (empty($facetoface->thirdpartywaitlist)) {
        $facetoface->thirdpartywaitlist = 0;
    }
    if (empty($facetoface->approvalreqd)) {
        $facetoface->approvalreqd = 0;
    }
    if (!empty($facetoface->shortname)) {
        $facetoface->shortname = textlib::substr($facetoface->shortname, 0, CALENDAR_MAX_NAME_LENGTH);
    }
    if (empty($facetoface->declareinterest)) {
        $facetoface->declareinterest = 0;
    }
    if (empty($facetoface->interestonlyiffull) || !$facetoface->declareinterest) {
        $facetoface->interestonlyiffull = 0;
    }
    if (empty($facetoface->selectpositiononsignup) || !$facetoface->selectpositiononsignup) {
        $facetoface->selectpositiononsignup = 0;
    }
    if (empty($facetoface->forceselectposition) || !$facetoface->forceselectposition) {
        $facetoface->forceselectposition = 0;
    }
    if (empty($facetoface->allowsignupnotedefault) || !$facetoface->allowsignupnotedefault) {
        $facetoface->allowsignupnotedefault = 0;
    }
}

/**
 * Given an object containing all the necessary data, (defined by the
 * form in mod.html) this function will create a new instance and
 * return the id number of the new instance.
 */
function facetoface_add_instance($facetoface) {
    global $DB;
    $facetoface->timemodified = time();

    facetoface_fix_settings($facetoface);
    if ($facetoface->id = $DB->insert_record('facetoface', $facetoface)) {
        facetoface_grade_item_update($facetoface);
    }

    //update any calendar entries
    if ($sessions = facetoface_get_sessions($facetoface->id)) {
        foreach ($sessions as $session) {
            facetoface_update_calendar_entries($session, $facetoface);
        }
    }

    // Add default notifications
    $defaults = array();
    $defaults['facetofaceid'] = $facetoface->id;
    $defaults['courseid'] = $facetoface->course;
    $defaults['type'] = MDL_F2F_NOTIFICATION_AUTO;
    $defaults['booked'] = 0;
    $defaults['waitlisted'] = 0;
    $defaults['cancelled'] = 0;
    $defaults['issent'] = 0;
    $defaults['status'] = 1;
    $defaults['ccmanager'] = 0;

    $confirmation = new facetoface_notification($defaults, false);
    $confirmation->title = get_string('setting:defaultconfirmationsubjectdefault', 'facetoface');
    $confirmation->body = text_to_html(get_string('setting:defaultconfirmationmessagedefault', 'facetoface'));
    $confirmation->managerprefix = text_to_html(get_string('setting:defaultconfirmationinstrmngrdefault', 'facetoface'));
    $confirmation->conditiontype = MDL_F2F_CONDITION_BOOKING_CONFIRMATION;
    $confirmation->ccmanager = 1;
    $confirmation->save();

    $waitlist = new facetoface_notification($defaults, false);
    $waitlist->title = get_string('setting:defaultwaitlistedsubjectdefault', 'facetoface');
    $waitlist->body = text_to_html(get_string('setting:defaultwaitlistedmessagedefault', 'facetoface'));
    $waitlist->conditiontype = MDL_F2F_CONDITION_WAITLISTED_CONFIRMATION;
    $waitlist->save();

    $cancellation = new facetoface_notification($defaults, false);
    $cancellation->title = get_string('setting:defaultcancellationsubjectdefault', 'facetoface');
    $cancellation->body = text_to_html(get_string('setting:defaultcancellationmessagedefault', 'facetoface'));
    $cancellation->managerprefix = text_to_html(get_string('setting:defaultcancellationinstrmngrdefault', 'facetoface'));
    $cancellation->conditiontype = MDL_F2F_CONDITION_CANCELLATION_CONFIRMATION;
    $cancellation->ccmanager = 1;
    $cancellation->cancelled = 1;
    $cancellation->save();

    $decline = new facetoface_notification($defaults, false);
    $decline->title = get_string('setting:defaultdeclinesubjectdefault', 'facetoface');
    $decline->body = text_to_html(get_string('setting:defaultdeclinemessagedefault', 'facetoface'));
    $decline->managerprefix = text_to_html(get_string('setting:defaultdeclineinstrmngrdefault', 'facetoface'));
    $decline->conditiontype = MDL_F2F_CONDITION_DECLINE_CONFIRMATION;
    $decline->ccmanager = 0;
    $decline->status = $facetoface->approvalreqd?1:0;
    $decline->save();

    $reminder = new facetoface_notification($defaults, false);
    $reminder->title = get_string('setting:defaultremindersubjectdefault', 'facetoface');
    $reminder->body = text_to_html(get_string('setting:defaultremindermessagedefault', 'facetoface'));
    $reminder->managerprefix = text_to_html(get_string('setting:defaultreminderinstrmngrdefault', 'facetoface'));
    $reminder->conditiontype = MDL_F2F_CONDITION_BEFORE_SESSION;
    $reminder->scheduleunit = MDL_F2F_SCHEDULE_UNIT_DAY;
    $reminder->scheduleamount = 2;
    $reminder->ccmanager = 1;
    $reminder->status = 0;
    $reminder->booked = 1;
    $reminder->save();

    $request = new facetoface_notification($defaults, false);
    $request->title = get_string('setting:defaultrequestsubjectdefault', 'facetoface');
    $request->body = text_to_html(get_string('setting:defaultrequestmessagedefault', 'facetoface'));
    $request->managerprefix = text_to_html(get_string('setting:defaultrequestinstrmngrdefault', 'facetoface'));
    $request->conditiontype = MDL_F2F_CONDITION_BOOKING_REQUEST;
    $request->ccmanager = 1;
    $request->save();

    $session_change = new facetoface_notification($defaults, false);
    $session_change->title = get_string('setting:defaultdatetimechangesubjectdefault', 'facetoface');
    $session_change->body = text_to_html(get_string('setting:defaultdatetimechangemessagedefault', 'facetoface'));
    $session_change->conditiontype = MDL_F2F_CONDITION_SESSION_DATETIME_CHANGE;
    $reminder->booked = 1;
    $reminder->waitlisted = 1;
    $session_change->save();

    $trainer_confirmation = new facetoface_notification($defaults, false);
    $trainer_confirmation->title = get_string('setting:defaulttrainerconfirmationsubjectdefault', 'facetoface');
    $trainer_confirmation->body = text_to_html(get_string('setting:defaulttrainerconfirmationmessagedefault', 'facetoface'));
    $trainer_confirmation->conditiontype = MDL_F2F_CONDITION_TRAINER_CONFIRMATION;
    $trainer_confirmation->save();

    $trainer_cancellation = new facetoface_notification($defaults, false);
    $trainer_cancellation->title = get_string('setting:defaulttrainersessioncancellationsubjectdefault', 'facetoface');
    $trainer_cancellation->body = text_to_html(get_string('setting:defaulttrainersessioncancellationmessagedefault', 'facetoface'));
    $trainer_cancellation->conditiontype = MDL_F2F_CONDITION_TRAINER_SESSION_CANCELLATION;
    $trainer_cancellation->save();

    $trainer_unassigned = new facetoface_notification($defaults, false);
    $trainer_unassigned->title = get_string('setting:defaulttrainersessionunassignedsubjectdefault', 'facetoface');
    $trainer_unassigned->body = text_to_html(get_string('setting:defaulttrainersessionunassignedmessagedefault', 'facetoface'));
    $trainer_unassigned->conditiontype = MDL_F2F_CONDITION_TRAINER_SESSION_UNASSIGNMENT;
    $trainer_unassigned->save();

    $cancelreservation = new facetoface_notification($defaults, false);
    $cancelreservation->title = get_string('setting:defaultcancelreservationsubjectdefault', 'facetoface');
    $cancelreservation->body = text_to_html(get_string('setting:defaultcancelreservationmessagedefault', 'facetoface'));
    $cancelreservation->conditiontype = MDL_F2F_CONDITION_RESERVATION_CANCELLED;
    $cancelreservation->cancelled = 1;
    $cancelreservation->save();

    $cancelallreservations = new facetoface_notification($defaults, false);
    $cancelallreservations->title = get_string('setting:defaultcancelallreservationssubjectdefault', 'facetoface');
    $cancelallreservations->body = text_to_html(get_string('setting:defaultcancelallreservationsmessagedefault', 'facetoface'));
    $cancelallreservations->conditiontype = MDL_F2F_CONDITION_RESERVATION_ALL_CANCELLED;
    $cancelallreservations->cancelled = 1;
    $cancelallreservations->save();

    return $facetoface->id;
}

/**
 * Given an object containing all the necessary data, (defined by the
 * form in mod.html) this function will update an existing instance
 * with new data.
 */
function facetoface_update_instance($facetoface, $instanceflag = true) {
    global $DB, $USER;

    if ($instanceflag) {
        $facetoface->id = $facetoface->instance;
    }

   facetoface_fix_settings($facetoface);
   if ($return = $DB->update_record('facetoface', $facetoface)) {
        facetoface_grade_item_update($facetoface);

        // Update any calendar entries
        if ($sessions = facetoface_get_sessions($facetoface->id)) {
            foreach ($sessions as $session) {
                facetoface_update_calendar_entries($session, $facetoface);
                // If manager changed from approval required to not
                if ($facetoface->approvalreqd == 0) {
                    // Check if we have the users who need approval
                    $attendees = facetoface_get_attendees($session->id, array(MDL_F2F_STATUS_REQUESTED));
                    if (count($attendees) > 0) {
                        // Update user status code from MDL_F2F_STATUS_REQUESTED to MDL_F2F_STATUS_BOOKED, otherwise these users will be hidden
                        foreach ($attendees as $i => $attendee) {
                            if (facetoface_update_signup_status($attendee->submissionid, MDL_F2F_STATUS_BOOKED, $USER->id, '', $attendee->grade)) {
                                // Send confirmation email that an user is booked and cc to user's manager if exists
                                facetoface_send_confirmation_notice($facetoface, $session, $attendee->id, 0, 0);
                            }
                        }
                    }
                }
            }
        }

    }
    return $return;
}

/**
 * Given an ID of an instance of this module, this function will
 * permanently delete the instance and any data that depends on it.
 */
function facetoface_delete_instance($id) {
    global $CFG, $DB;

    if (!$facetoface = $DB->get_record('facetoface', array('id' => $id))) {
        return false;
    }

    $result = true;

    $transaction = $DB->start_delegated_transaction();

    $DB->delete_records_select(
        'facetoface_signups_status',
        "signupid IN
        (
            SELECT
            id
            FROM
    {facetoface_signups}
    WHERE
    sessionid IN
    (
        SELECT
        id
        FROM
    {facetoface_sessions}
    WHERE
    facetoface = ? ))
    ", array($facetoface->id));

    $DB->delete_records_select('facetoface_signups', "sessionid IN (SELECT id FROM {facetoface_sessions} WHERE facetoface = ?)", array($facetoface->id));

    $DB->delete_records_select('facetoface_sessions_dates', "sessionid in (SELECT id FROM {facetoface_sessions} WHERE facetoface = ?)", array($facetoface->id));

    // Notifications.
    $DB->delete_records('facetoface_notification', array('facetofaceid' => $facetoface->id));
    $DB->delete_records_select('facetoface_notification_sent',
            "sessionid IN (SELECT id FROM {facetoface_sessions} WHERE facetoface = ?)", array($facetoface->id));
    $DB->delete_records_select('facetoface_notification_hist',
            "sessionid IN (SELECT id FROM {facetoface_sessions} WHERE facetoface = ?)", array($facetoface->id));

    $DB->delete_records('facetoface_interest', array('facetoface' => $facetoface->id));

    $DB->delete_records('facetoface_sessions', array('facetoface' => $facetoface->id));

    $DB->delete_records('facetoface', array('id' => $facetoface->id));

    $DB->delete_records('event', array('modulename' => 'facetoface', 'instance' => $facetoface->id));

    facetoface_grade_item_delete($facetoface);

    $transaction->allow_commit();

    return $result;
}

/**
 * Prepare the user data to go into the database.
 */
function cleanup_session_data($session) {

    // Convert hours (expressed like "1.75" or "2" or "3.5") to minutes
    $session->duration = facetoface_hours_to_minutes($session->duration);

    // Only numbers allowed here
    $session->capacity = preg_replace('/[^\d]/', '', $session->capacity);
    $MAX_CAPACITY = 100000;
    if ($session->capacity < 1) {
        $session->capacity = 1;
    }
    elseif ($session->capacity > $MAX_CAPACITY) {
        $session->capacity = $MAX_CAPACITY;
    }

    return $session;
}

/**
 * Create a new entry in the facetoface_sessions table
 */
function facetoface_add_session($session, $sessiondates) {
    global $USER, $DB;

    $session->timecreated = time();
    $session = cleanup_session_data($session);

    $session->id = $DB->insert_record('facetoface_sessions', $session);

    if (empty($sessiondates)) {
        // Insert a dummy date record.
        $date = new stdClass();
        $date->sessionid = $session->id;
        $date->timestart = 0;
        $date->timefinish = 0;
        $date->sessiontimezone = '';
        $DB->insert_record('facetoface_sessions_dates', $date);
    } else {
        foreach ($sessiondates as $date) {
            $date->sessionid = $session->id;
            $DB->insert_record('facetoface_sessions_dates', $date);
        }
    }

    return $session->id;
}

/**
 * Modify an entry in the facetoface_sessions table
 */
function facetoface_update_session($session, $sessiondates) {
    global $DB;

    $session->timemodified = time();
    $session = cleanup_session_data($session);

    $DB->update_record('facetoface_sessions', $session);
    $DB->delete_records('facetoface_sessions_dates', array('sessionid' => $session->id));

    if (empty($sessiondates)) {
        // Insert a dummy date record.
        $date = new stdClass();
        $date->sessionid = $session->id;
        $date->timestart = 0;
        $date->timefinish = 0;
        $date->sessiontimezone = '';
        $DB->insert_record('facetoface_sessions_dates', $date);
    } else {
        foreach ($sessiondates as $date) {
            $date->sessionid = $session->id;
            $date->id = $DB->insert_record('facetoface_sessions_dates', $date);
        }
    }

    return $session->id;
}

/**
 * A function to check if the dates in a session have been changed at all.
 *
 * @param array $olddates   The dates the session used to be set to
 * @param array $newdates   The dates the session is now set to
 *
 * @return boolean
 */
function facetoface_session_dates_check($olddates, $newdates) {
    // Dates have changed if the amount of dates has changed.
    if (count($olddates) != count($newdates)) {
        return true;
    }

    // Dates have changed if the time zone has changed.
    if (current($olddates)->sessiontimezone != current($newdates)->sessiontimezone) {
        return true;
    }

    // Try to match them up, keeping in mind they might not be in the same order.
    foreach ($olddates as $oldkey => $olddate) {
        foreach ($newdates as $newkey => $newdate) {
            if ($olddate->timestart == $newdate->timestart && $olddate->timefinish == $newdate->timefinish) {
                unset($olddates[$oldkey]);
                unset($newdates[$newkey]);
            }
        }
    }

    if (!empty($olddates) || !empty($newdates)) {
        // They didn't all match up, something changed.
        return true;
    } else {
        // They match, nothing to worry about.
        return false;
    }
}

function facetoface_update_calendar_entries($session, $facetoface = null){
    global $USER, $DB;

    if (empty($facetoface)) {
        $facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface));
    }

    //remove from all calendars
    facetoface_delete_user_calendar_events($session, 'booking');
    facetoface_delete_user_calendar_events($session, 'session');
    facetoface_remove_session_from_calendar($session, $facetoface->course);
    facetoface_remove_session_from_calendar($session, SITEID);

    if (empty($facetoface->showoncalendar) && empty($facetoface->usercalentry)) {
        return true;
    }

    //add to NEW calendartype
    if ($facetoface->usercalentry) {
    //get ALL enrolled/booked users
        $users  = facetoface_get_attendees($session->id);
        if (!in_array($USER->id, $users)) {
            facetoface_add_session_to_calendar($session, $facetoface, 'user', $USER->id, 'session');
        }

        foreach ($users as $user) {
            $eventtype = $user->statuscode == MDL_F2F_STATUS_BOOKED ? 'booking' : 'session';
            facetoface_add_session_to_calendar($session, $facetoface, 'user', $user->id, $eventtype);
        }
    }

    if ($facetoface->showoncalendar == F2F_CAL_COURSE) {
        facetoface_add_session_to_calendar($session, $facetoface, 'course');
    } else if ($facetoface->showoncalendar == F2F_CAL_SITE) {
        facetoface_add_session_to_calendar($session, $facetoface, 'site');
    }

    return true;
}

/**
 * Update attendee list status' on booking size change
 */
function facetoface_update_attendees($session) {
    global $USER, $DB;

    // Get facetoface
    $facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface));

    // Get course
    $course = $DB->get_record('course', array('id' => $facetoface->course));

    // Update user status'
    $users = facetoface_get_attendees($session->id, array(MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_WAITLISTED), true);

    if ($users) {
        // No/deleted session dates
        if (empty($session->datetimeknown)) {

            // Convert any bookings to waitlists
            foreach ($users as $user) {
                if ($user->statuscode == MDL_F2F_STATUS_BOOKED) {

                    if (!$user->id) {
                        // Cope with reserved spaces.
                        facetoface_update_signup_status($user->signupid, MDL_F2F_STATUS_WAITLISTED, $USER->id);
                    } else if (!facetoface_user_signup($session, $facetoface, $course, $user->discountcode, $user->notificationtype, MDL_F2F_STATUS_WAITLISTED, $user->id, true, $user->usernote)) {
                        // rollback_sql();
                        return false;
                    }
                }
            }

        // Session dates exist
        } else {
            // Convert earliest signed up users to booked, and make the rest waitlisted
            $capacity = $session->capacity;

            // Count number of booked users
            $booked = 0;
            foreach ($users as $user) {
                if ($user->statuscode == MDL_F2F_STATUS_BOOKED) {
                    $booked++;
                }
            }

            // If booked less than capacity, book some new users
            $facetoface_allowwaitlisteveryone = get_config(null, 'facetoface_allowwaitlisteveryone');
            if ($booked < $capacity && (!$session->waitlisteveryone || empty($facetoface_allowwaitlisteveryone))) {
                foreach ($users as $user) {
                    if ($booked >= $capacity) {
                        break;
                    }

                    if ($user->statuscode == MDL_F2F_STATUS_WAITLISTED) {

                        if (!$user->id) {
                            // Cope with reserved spaces.
                            facetoface_update_signup_status($user->signupid, MDL_F2F_STATUS_BOOKED, $USER->id);
                        } else if (!facetoface_user_signup($session, $facetoface, $course, $user->discountcode, $user->notificationtype, MDL_F2F_STATUS_BOOKED, $user->id, true, $user->usernote)) {
                            // rollback_sql();
                            return false;
                        }
                        $booked++;
                    }
                }
            }
        }
    }

    return $session->id;
}

/**
 * Return an array of all facetoface activities in the current course
 */
function facetoface_get_facetoface_menu() {
    global $CFG, $DB;
    if ($facetofaces = $DB->get_records_sql("SELECT f.id, c.shortname, f.name
                                            FROM {course} c, {facetoface} f
                                            WHERE c.id = f.course
                                            ORDER BY c.shortname, f.name")) {
        $i=1;
        foreach ($facetofaces as $facetoface) {
            $f = $facetoface->id;
            $facetofacemenu[$f] = $facetoface->shortname.' --- '.$facetoface->name;
            $i++;
        }
        return $facetofacemenu;
    } else {
        return '';
    }
}

/**
 * Delete entry from the facetoface_sessions table along with all
 * related details in other tables
 *
 * @param object $session Record from facetoface_sessions
 */
function facetoface_delete_session($session) {
    global $CFG, $DB;

    $facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface));

    // Cancel user signups (and notify users)
    $signedupusers = $DB->get_records_sql(
        "
            SELECT DISTINCT
                userid
            FROM
                {facetoface_signups} s
            LEFT JOIN
                {facetoface_signups_status} ss
             ON ss.signupid = s.id
            WHERE
                s.sessionid = ?
            AND ss.superceded = 0
            AND ss.statuscode >= ?
        ", array($session->id, MDL_F2F_STATUS_REQUESTED));

    if ($signedupusers and count($signedupusers) > 0) {
        foreach ($signedupusers as $user) {
            if (facetoface_user_cancel($session, $user->userid, true)) {
                facetoface_send_cancellation_notice($facetoface, $session, $user->userid);
            } else {
                return false; // Cannot rollback since we notified users already
            }
        }
    }

    // Load current trainers
    $trainers = $DB->get_records("facetoface_session_roles", array("sessionid" => $session->id));
    if ($trainers and count($trainers) > 0) {
        foreach ($trainers as $trainer) {
            facetoface_send_cancellation_notice($facetoface, $session, $trainer->userid);
        }
    }

    // Notify managers who had reservations.
    facetoface_notify_reserved_session_deleted($facetoface, $session);

    $transaction = $DB->start_delegated_transaction();

    // Remove entries from the teacher calendars
    $select = $DB->sql_like('description', ':attendess');
    $select .= " AND modulename = 'facetoface' AND eventtype = 'facetofacesession' AND instance = :facetofaceid";
    $params = array('attendess' => "%attendees.php?s={$session->id}%", 'facetofaceid' => $facetoface->id);
    $DB->delete_records_select('event', $select, $params);
    if ($facetoface->showoncalendar == F2F_CAL_COURSE) {
        // Remove entry from course calendar
        facetoface_remove_session_from_calendar($session, $facetoface->course);
    } else if ($facetoface->showoncalendar == F2F_CAL_SITE) {
        // Remove entry from site-wide calendar
        facetoface_remove_session_from_calendar($session, SITEID);
    }

    // Delete session details
    $DB->delete_records('facetoface_sessions_dates',array('sessionid' => $session->id));
    $DB->delete_records('facetoface_session_roles', array('sessionid' => $session->id));
    $DB->delete_records('facetoface_session_data',  array('sessionid' => $session->id));

    $DB->delete_records_select(
        'facetoface_signups_status',
        "signupid IN (SELECT id FROM {facetoface_signups} WHERE sessionid = {$session->id})");
    $DB->delete_records('facetoface_signups', array('sessionid' => $session->id));

    // Notifications.
    $DB->delete_records('facetoface_notification_sent', array('sessionid' => $session->id));
    $DB->delete_records('facetoface_notification_hist', array('sessionid' => $session->id));

    $DB->delete_records('facetoface_sessions', array('id' => $session->id));

    $transaction->allow_commit();

    return true;
}

/**
 * Notify managers that a session they had reserved spaces on has been deleted.
 *
 * @param object $facetoface
 * @param object $session
 */
function facetoface_notify_reserved_session_deleted($facetoface, $session) {
    global $CFG;

    $attendees = facetoface_get_attendees($session->id, array(MDL_F2F_STATUS_BOOKED), true);
    $reservedids = array();
    foreach ($attendees as $attendee) {
        if ($attendee->bookedby) {
            if (!$attendee->id) {
                // Managers can already get booking cancellation notices - just adding reserve cancellation notices.
                $reservedids[] = $attendee->bookedby;
            }
        }
    }
    if (!$reservedids) {
        return;
    }
    $reservedids = array_unique($reservedids);

    $ccmanager = !empty($facetoface->ccmanager);
    $facetoface->ccmanager = false; // Never Cc the manager's manager (that would just be too much).

    // Notify all managers that have reserved spaces for their team.
    $params = array(
        'facetofaceid'  => $facetoface->id,
        'type'          => MDL_F2F_NOTIFICATION_AUTO,
        'conditiontype' => MDL_F2F_CONDITION_RESERVATION_CANCELLED
    );

    $includeical = empty($CFG->facetoface_disableicalcancel);
    foreach ($reservedids as $reservedid) {
        facetoface_send_notice($facetoface, $session, $reservedid, $params, $includeical ? MDL_F2F_BOTH : MDL_F2F_TEXT, MDL_F2F_CANCEL);
    }

    $facetoface->ccmanager = $ccmanager;
}

/**
 * Function to be run periodically according to the moodle cron
 * Finds all facetoface notifications that have yet to be mailed out, and mails them.
 */
function facetoface_cron($testing = false) {
    global $DB;

    // Send notifications if enabled.
    $notificationdisable = get_config(null, 'facetoface_notificationdisable');
    if (empty($notificationdisable)) {
        // Find "instant" manual notifications that haven't yet been sent.
        if (!$testing) {
            mtrace('Checking for instant Face-to-face notifications');
        }
        $manual = $DB->get_records_select(
            'facetoface_notification',
            'type = ? AND issent <> ? AND status = 1',
            array(MDL_F2F_NOTIFICATION_MANUAL, MDL_F2F_NOTIFICATION_STATE_FULLY_SENT));
        if ($manual) {
            foreach ($manual as $notif) {
                $notification = new facetoface_notification((array)$notif, false);
                $notification->send_to_users();
            }
        }

        // Find scheduled notifications that haven't yet been sent
        if (!$testing) {
            mtrace('Checking for scheduled Face-to-face notifications');
        }
        $sched = $DB->get_records_select(
            'facetoface_notification',
            'scheduletime IS NOT NULL
            AND (type = ? OR type = ?)
            AND status = 1',
            array(MDL_F2F_NOTIFICATION_SCHEDULED, MDL_F2F_NOTIFICATION_AUTO));
        if ($sched) {
            foreach ($sched as $notif) {
                $notification = new facetoface_notification((array)$notif, false);
                $notification->send_scheduled();
            }
        }
    }

    // Find any reservations that are too close to the start of the session and delete them.
    facetoface_remove_reservations_after_deadline($testing);

    // Notify of sessions that are under capacity.
    if (!$testing) {
        mtrace("Checking for sessions below minimum capacity");
    }
    facetoface_notify_under_capacity();

    return true;
}

/**
 * Find any reservations that are too close to the start of the session and delete them.
 */
function facetoface_remove_reservations_after_deadline($testing) {
    global $DB;
    $sql = "SELECT DISTINCT su.id, s.id AS sessionid, f.id AS facetofaceid, su.bookedby
                  FROM {facetoface} f
                  JOIN {facetoface_sessions} s ON s.facetoface = f.id
                  JOIN {facetoface_sessions_dates} sd ON sd.sessionid = s.id
                  JOIN {facetoface_signups} su ON su.sessionid = s.id AND su.userid = 0
                  JOIN {facetoface_signups_status} sus ON sus.signupid = su.id AND sus.superceded = 0
                 WHERE f.reservecanceldays > 0 AND sd.timestart < (:timenow + (f.reservecanceldays * :daysecs))";
    $params = array('timenow' => time(), 'daysecs' => DAYSECS);
    $signups = $DB->get_records_sql($sql, $params);

    if ($signups) {
        $tonotify = array();
        if (!$testing) {
            mtrace('Removing unconfirmed face to face reservations for sessions that will be starting soon');
        }
        foreach ($signups as $signup) {
            if (!$testing) {
                mtrace("- signupid: {$signup->id}, sessionid: {$signup->sessionid}, facetofaceid: {$signup->facetofaceid}");
            }
            if (!isset($tonotify[$signup->facetofaceid])) {
                $tonotify[$signup->facetofaceid] = array();
            }
            if (!isset($tonotify[$signup->facetofaceid][$signup->sessionid])) {
                $tonotify[$signup->facetofaceid][$signup->sessionid] = array();
            }
            $tonotify[$signup->facetofaceid][$signup->sessionid][$signup->bookedby] = $signup->bookedby;
        }
        $signupids = array_keys($signups);
        $DB->delete_records_list('facetoface_signups_status', 'signupid', $signupids);
        $DB->delete_records_list('facetoface_signups', 'id', $signupids);

        // Send notifications if enabled.
        $notificationdisable = get_config(null, 'facetoface_notificationdisable');
        if (empty($notificationdisable)) {
            $notifyparams = array(
                'type' => MDL_F2F_NOTIFICATION_AUTO,
                'conditiontype' => MDL_F2F_CONDITION_RESERVATION_ALL_CANCELLED,
            );
            foreach ($tonotify as $facetofaceid => $sessions) {
                $facetoface = $DB->get_record('facetoface', array('id' => $facetofaceid));
                $notifyparams['facetofaceid'] = $facetoface->id;
                foreach ($sessions as $sessionid => $managers) {
                    $session = facetoface_get_session($sessionid);
                    foreach ($managers as $managerid) {
                        facetoface_send_notice($facetoface, $session, $managerid, $notifyparams);
                    }
                }
            }
        }
    }
}

/**
 * Send out email notifications for all sessions that are under capacity at the cut-off.
 */
function facetoface_notify_under_capacity() {
    global $DB;

    $lastcron = $DB->get_field('modules', 'lastcron', array('name' => 'facetoface'));
    $time = time();

    $params = array(
        'lastcron' => $lastcron,
        'now'      => $time
    );

    $sql = "SELECT s.*, minstart FROM {facetoface_sessions} s
            INNER JOIN (
                SELECT s.id as sessid, MIN(timestart) AS minstart
                FROM {facetoface_sessions} s
                INNER JOIN {facetoface_sessions_dates} d ON s.id = d.sessionid
                GROUP BY s.id
            ) dates ON dates.sessid = s.id
            WHERE datetimeknown = 1 AND mincapacity > 0 AND (minstart - cutoff) < :now AND (minstart - cutoff) >= :lastcron";

    $tocheck = $DB->get_records_sql($sql, $params);

    foreach ($tocheck as $session) {
        $booked = facetoface_get_num_attendees($session->id, MDL_F2F_STATUS_APPROVED);
        if ($booked >= $session->mincapacity) {
            continue;
        }

        // We've found a session that has not reached the minimum capacity by the cut-off - time to send out emails.
        $facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface));
        $cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $facetoface->course, false);
        $url = new moodle_url('/mod/facetoface/view.php', array('id' => $cm->id));
        $link = $url->out(false);

        // Get the start time of the first date for this session.
        if (!$session->minstart) {
            $starttime = get_string('nostarttime', 'facetoface');
        } else {
            $starttime = userdate($session->minstart, get_string('strftimedatetime'));
        }

        $info = (object)array(
            'name' => format_string($facetoface->name),
            'starttime' => $starttime,
            'capacity' => $session->capacity,
            'mincapacity' => $session->mincapacity,
            'booked' => $booked,
            'link' => $link,
        );

        $eventdata = (object)array(
            'subject' => get_string('sessionundercapacity', 'facetoface', format_string($facetoface->name)),
            'fullmessage' => get_string('sessionundercapacity_body', 'facetoface', $info),
            'msgtype' => TOTARA_MSG_TYPE_FACE2FACE,
            'msgstatus' => TOTARA_MSG_STATUS_NOTOK,
            'urgency' => TOTARA_MSG_URGENCY_NORMAL,
            'sendmail' => TOTARA_MSG_EMAIL_YES,
        );

        if (CLI_SCRIPT) {
            mtrace("Facetoface '{$info->name}' in course {$facetoface->course} is under capacity - {$info->booked}/{$info->capacity} (min capacity {$info->mincapacity}) - emailing course editors.");
        }

        $coursecontext = context_course::instance($facetoface->course);
        $usernamefields = get_all_user_name_fields(true, 'u');
        $users = get_users_by_capability($coursecontext, 'moodle/course:manageactivities',
             "u.id, $usernamefields, u.email, u.maildisplay, u.suspended, u.deleted, u.emailstop, u.auth");
        foreach ($users as $user) {
            $eventdata->userto = $user;
            tm_alert_send($eventdata);
        }
    }
}

/**
 * Returns true if the session has started, that is if one of the
 * session dates is in the past.
 *
 * @param class $session record from the facetoface_sessions table
 * @param integer $timenow current time
 */
function facetoface_has_session_started($session, $timenow) {

    if (!$session->datetimeknown) {
        return false; // no date set
    }

    foreach ($session->sessiondates as $date) {
        if ($date->timestart < $timenow) {
            return true;
        }
    }
    return false;
}

/**
 * Returns true if the session has started and has not yet finished.
 *
 * @param class $session record from the facetoface_sessions table
 * @param integer $timenow current time
 */
function facetoface_is_session_in_progress($session, $timenow) {
    if (!$session->datetimeknown) {
        return false;
    }
    $startedsessions = totara_search_for_value($session->sessiondates, 'timestart', TOTARA_SEARCH_OP_LESS_THAN, $timenow);
    $unfinishedsessions = totara_search_for_value($session->sessiondates, 'timefinish', TOTARA_SEARCH_OP_GREATER_THAN, $timenow);
    if (!empty($startedsessions) && !empty($unfinishedsessions)) {
        return true;
    }
    return false;
}

/**
 * Get all of the dates for a given session
 */
function facetoface_get_session_dates($sessionid) {
    global $DB;
    $ret = array();

    if ($dates = $DB->get_records('facetoface_sessions_dates', array('sessionid' => $sessionid), 'timestart')) {
        $i = 0;
        foreach ($dates as $date) {
            $ret[$i++] = $date;
        }
    }

    return $ret;
}

/**
 * Get a record from the facetoface_sessions table
 *
 * @param integer $sessionid ID of the session
 */
function facetoface_get_session($sessionid) {
    global $DB;
    $session = $DB->get_record('facetoface_sessions', array('id' => $sessionid));

    if ($session) {
        $session->sessiondates = facetoface_get_session_dates($sessionid);
        $session->duration = facetoface_minutes_to_hours($session->duration);
    }

    return $session;
}

/**
 * Get all records from facetoface_sessions for a given facetoface activity and location
 *
 * @param integer $facetofaceid ID of the activity
 * @param string $location location filter (optional)
 */
function facetoface_get_sessions($facetofaceid, $location='', $roomid=0) {
    global $CFG,$DB;

    $fromclause = "FROM {facetoface_sessions} s";
    $locationwhere = '';
    $locationparams = array();
    if (!empty($location)) {
        $fromclause = "FROM {facetoface_session_data} d
                       JOIN {facetoface_sessions} s ON s.id = d.sessionid";
        $locationwhere .= " AND d.data = ?";
        $locationparams[] = $location;
    }
    $roomwhere = '';
    $roomparams = array();
    if (!empty($roomid)) {
        $roomwhere = ' AND s.roomid = ? ';
        $roomparams[] = $roomid;
    }
    $sessions = $DB->get_records_sql("SELECT s.*
                                   $fromclause
                        LEFT OUTER JOIN (SELECT sessionid, min(timestart) AS mintimestart
                                           FROM {facetoface_sessions_dates} GROUP BY sessionid) m ON m.sessionid = s.id
                                  WHERE s.facetoface = ?
                                        $locationwhere
                                        $roomwhere
                               ORDER BY s.datetimeknown, m.mintimestart", array_merge(array($facetofaceid), $locationparams, $roomparams));

    if ($sessions) {
        foreach ($sessions as $key => $value) {
            $sessions[$key]->duration = facetoface_minutes_to_hours($sessions[$key]->duration);
            $sessions[$key]->sessiondates = facetoface_get_session_dates($value->id);
        }
    }
    return $sessions;
}

/**
 * Get a grade for the given user from the gradebook.
 *
 * @param integer $userid        ID of the user
 * @param integer $courseid      ID of the course
 * @param integer $facetofaceid  ID of the Face-to-face activity
 *
 * @return object String grade and the time that it was graded
 */
function facetoface_get_grade($userid, $courseid, $facetofaceid) {

    $ret = new stdClass();
    $ret->grade = 0;
    $ret->dategraded = 0;

    $grading_info = grade_get_grades($courseid, 'mod', 'facetoface', $facetofaceid, $userid);
    if (!empty($grading_info->items)) {
        $ret->grade = $grading_info->items[0]->grades[$userid]->str_grade;
        $ret->dategraded = $grading_info->items[0]->grades[$userid]->dategraded;
    }

    return $ret;
}

/**
 * Get list of users attending a given session
 *
 * @access public
 * @param integer Session ID
 * @param array $status Array of statuses to include
 * @param bool $includereserved optional - if true, then include 'reserved' spaces (note this will change the array index
 *                                to signupid instead of the user id, to prevent duplicates)
 * @return array
 */
function facetoface_get_attendees($sessionid, $status = array(MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_WAITLISTED), $includereserved = false) {
    global $DB;

    list($statussql, $statusparams) = $DB->get_in_or_equal($status);

    // Find the reservation details (and LEFT JOIN with the {user}, as that will be 0 for reservations).
    $reservedfields = '';
    $userjoin = 'JOIN';
    if ($includereserved) {
        $reservedfields = 'su.id AS signupid, bb.firstname AS bookedbyfirstname,
                        bb.lastname AS bookedbylastname, bb.id AS bookedby, ';
        $userjoin = 'LEFT JOIN {user} bb ON bb.id = su.bookedby
                     LEFT JOIN';
    }

    $usernamefields = get_all_user_name_fields(true, 'u');

    $sql = "
        SELECT
            {$reservedfields}
            u.id,
            su.id AS submissionid,
            {$usernamefields},
            u.email,
            s.discountcost,
            su.discountcode,
            su.notificationtype,
            f.id AS facetofaceid,
            f.course,
            ss.grade,
            ss.statuscode,
            ss.note AS usernote,
            (
                SELECT MIN(timecreated)
                FROM {facetoface_signups_status} ss2
                WHERE ss2.signupid = ss.signupid AND ss2.statuscode IN (?, ?)
            ) as timesignedup,
            ss.timecreated,
            p.fullname as positionname,
            pa.type as positiontype,
            pa.fullname as positionassignmentname
        FROM
            {facetoface} f
        JOIN
            {facetoface_sessions} s
         ON s.facetoface = f.id
        JOIN
            {facetoface_signups} su
         ON s.id = su.sessionid
        JOIN
            {facetoface_signups_status} ss
         ON su.id = ss.signupid
   LEFT JOIN
            {pos} p
         ON p.id = su.positionid
   LEFT JOIN
            {pos_assignment} pa
         ON pa.id = su.positionassignmentid
       {$userjoin}
            {user} u
         ON u.id = su.userid
        WHERE
            s.id = ?
        AND ss.statuscode {$statussql}
        AND ss.superceded != 1
        ORDER BY u.firstname, u.lastname ASC";

    $params = array_merge(array(MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_WAITLISTED, $sessionid), $statusparams);

    $records = $DB->get_records_sql($sql, $params);

    return $records;
}

/**
 * Get a single attendee of a session
 *
 * @access public
 * @param integer Session ID
 * @param integer User ID
 * @return false|object
 */
function facetoface_get_attendee($sessionid, $userid) {
    global $DB;

    $usernamefields = get_all_user_name_fields(true, 'u');
    $record = $DB->get_record_sql("
        SELECT
            u.id,
            su.id AS submissionid,
            {$usernamefields},
            u.email,
            s.discountcost,
            su.discountcode,
            su.notificationtype,
            f.id AS facetofaceid,
            f.course,
            ss.id AS statusid,
            ss.grade,
            ss.statuscode,
            ss.note AS usernote
        FROM
            {facetoface} f
        JOIN
            {facetoface_sessions} s
         ON s.facetoface = f.id
        JOIN
            {facetoface_signups} su
         ON s.id = su.sessionid
        JOIN
            {facetoface_signups_status} ss
         ON su.id = ss.signupid
        JOIN
            {user} u
         ON u.id = su.userid
        WHERE
            s.id = ?
        AND ss.superceded != 1
        AND u.id = ?
    ", array($sessionid, $userid));

    if (!$record) {
        return false;
    }

    return $record;
}

/**
 * Return all user fields to include in exports
 */
function facetoface_get_userfields() {
    global $CFG, $DB;

    static $userfields = null;
    if (null == $userfields) {
        $userfields = array();

        $fieldnames = array('firstname', 'lastname', 'email', 'city',
                            'idnumber', 'institution', 'department', 'address');
        if (!empty($CFG->facetoface_export_userprofilefields)) {
            $fieldnames = array_map('trim', explode(',', $CFG->facetoface_export_userprofilefields));
        }
        foreach ($fieldnames as $shortname) {
            if (get_string_manager()->string_exists($shortname, 'moodle')) {
                $userfields[$shortname] = get_string($shortname);
            } else {
                $userfields[$shortname] = $shortname;
            }
        }

        // Add custom fields.
        if (!empty($CFG->facetoface_export_customprofilefields)) {
            $customfields = array_map('trim', explode(',', $CFG->facetoface_export_customprofilefields));
            list($insql, $params) = $DB->get_in_or_equal($customfields);
            $sql = 'SELECT '.$DB->sql_concat("'customfield_'", 'f.shortname').' AS shortname, f.name
                FROM {user_info_field} f
                JOIN {user_info_category} c ON f.categoryid = c.id
                WHERE f.shortname '.$insql.'
                ORDER BY c.sortorder, f.sortorder';

            $customfields = $DB->get_records_sql_menu($sql, $params);
            if (!empty($customfields)) {
                $userfields = array_merge($userfields, $customfields);
            }
        }
        $userfields['managersemail'] = get_string('manageremail', 'facetoface');
    }

    return $userfields;
}

/**
 * Download the list of users attending at least one of the sessions
 * for a given facetoface activity
 */
function facetoface_download_attendance($facetofacename, $facetofaceid, $location, $format) {
    global $CFG, $DB;

    $timenow = time();
    $timeformat = str_replace(' ', '_', get_string('strftimedate', 'langconfig'));
    $downloadfilename = clean_filename($facetofacename.'_'.userdate($timenow, $timeformat));

    $dateformat = 0;
    if ('ods' === $format) {
        // OpenDocument format (ISO/IEC 26300)
        require_once($CFG->dirroot.'/lib/odslib.class.php');
        $downloadfilename .= '.ods';
        $workbook = new MoodleODSWorkbook('-');
    } else {
        // Excel format
        require_once($CFG->dirroot.'/lib/excellib.class.php');
        $downloadfilename .= '.xls';
        $workbook = new MoodleExcelWorkbook('-');
        $dateformat = $workbook->add_format();
        $dateformat->set_num_format(MoodleExcelWorkbook::NUMBER_FORMAT_STANDARD_DATE);
    }

    $workbook->send($downloadfilename);
    $worksheet = $workbook->add_worksheet('attendance');
    $courseid = $DB->get_field('facetoface', 'course', array('id' => $facetofaceid));
    $coursecontext = context_course::instance($courseid);
    facetoface_write_worksheet_header($worksheet, $coursecontext);
    facetoface_write_activity_attendance($worksheet, $coursecontext, 1, $facetofaceid, $location, '', '', $dateformat);
    $workbook->close();
    exit;
}

/**
 * Add the appropriate column headers to the given worksheet
 *
 * @param object $worksheet  The worksheet to modify (passed by reference)
 * @param object $context the course context of the facetoface instance
 * @returns integer The index of the next column
 */
function facetoface_write_worksheet_header(&$worksheet, $context) {
    $pos=0;
    $customfields = facetoface_get_session_customfields();
    foreach ($customfields as $field) {
        if (!empty($field->showinsummary)) {
            $worksheet->write_string(0, $pos++, $field->name);
        }
    }
    $worksheet->write_string(0, $pos++, get_string('sessionstartdateshort', 'facetoface'));
    $worksheet->write_string(0, $pos++, get_string('sessionfinishdateshort', 'facetoface'));
    $worksheet->write_string(0, $pos++, get_string('room', 'facetoface'));
    $worksheet->write_string(0, $pos++, get_string('timestart', 'facetoface'));
    $worksheet->write_string(0, $pos++, get_string('timefinish', 'facetoface'));
    $worksheet->write_string(0, $pos++, get_string('duration', 'facetoface'));
    $worksheet->write_string(0, $pos++, get_string('status', 'facetoface'));

    if ($trainerroles = facetoface_get_trainer_roles($context)) {
        foreach ($trainerroles as $role) {
            $worksheet->write_string(0, $pos++, get_string('role').': '.$role->localname);
        }
    }

    $userfields = facetoface_get_userfields();
    foreach ($userfields as $shortname => $fullname) {
        $worksheet->write_string(0, $pos++, $fullname);
    }

    $selectpositiononsignupglobal = get_config(null, 'facetoface_selectpositiononsignupglobal');
    if (!empty($selectpositiononsignupglobal)) {
        $worksheet->write_string(0, $pos++, get_string('selectedposition', 'mod_facetoface'));
    }

    $worksheet->write_string(0, $pos++, get_string('attendance', 'facetoface'));
    $worksheet->write_string(0, $pos++, get_string('datesignedup', 'facetoface'));

    return $pos;
}

/**
 * Write in the worksheet the given facetoface attendance information
 * filtered by location.
 *
 * This function includes lots of custom SQL because it's otherwise
 * way too slow.
 *
 * @param object  $worksheet    Currently open worksheet
 * @param object  $coursecontext context of the course containing this f2f activity
 * @param integer $startingrow  Index of the starting row (usually 1)
 * @param integer $facetofaceid ID of the facetoface activity
 * @param string  $location     Location to filter by
 * @param string  $coursename   Name of the course (optional)
 * @param string  $activityname Name of the facetoface activity (optional)
 * @param object  $dateformat   Use to write out dates in the spreadsheet
 * @returns integer Index of the last row written
 */
function facetoface_write_activity_attendance(&$worksheet, $coursecontext, $startingrow, $facetofaceid, $location,
                                              $coursename, $activityname, $dateformat) {
    global $CFG, $DB;

    $trainerroles = facetoface_get_trainer_roles($coursecontext);
    $userfields = facetoface_get_userfields();
    $customsessionfields = facetoface_get_session_customfields();
    $timenow = time();
    $i = $startingrow;

    $locationcondition = '';
    $locationparam = array();
    if (!empty($location)) {
        $locationcondition = "AND s.location = ?";
        $locationparam = array($location);
    }

    // Fast version of "facetoface_get_attendees()" for all sessions
    $sessionsignups = array();
    $signupsql = "
        SELECT
            su.id AS submissionid,
            s.id AS sessionid,
            u.*,
            f.course AS courseid,
            ss.grade,
            sign.timecreated,
            COALESCE (mu1.email, mu2.email) AS managersemail,
            p.fullname as positionname,
            pa.type as positiontype,
            pa.fullname as positionassignmentname
        FROM
            {facetoface} f
        JOIN
            {facetoface_sessions} s
         ON s.facetoface = f.id
        JOIN
            {facetoface_signups} su
         ON s.id = su.sessionid
        JOIN
            {facetoface_signups_status} ss
         ON su.id = ss.signupid
        LEFT JOIN
            (
            SELECT
                ss.signupid,
                MAX(ss.timecreated) AS timecreated
            FROM
                {facetoface_signups_status} ss
            INNER JOIN
                {facetoface_signups} s
             ON s.id = ss.signupid
            INNER JOIN
                {facetoface_sessions} se
             ON s.sessionid = se.id
            AND se.facetoface = $facetofaceid
            WHERE
                ss.statuscode IN (?,?)
            GROUP BY
                ss.signupid
            ) sign
         ON su.id = sign.signupid
        JOIN
            {user} u
            ON u.id = su.userid
        LEFT JOIN
            {pos} p
         ON p.id = su.positionid
        LEFT JOIN
            {pos_assignment} pa
         ON pa.id = su.positionassignmentid
        LEFT JOIN
            {user} mu1
            ON mu1.id = pa.managerid
        LEFT JOIN
            {pos_assignment} pa2
         ON pa2.userid = u.id AND pa2.type = ?
        LEFT JOIN
            {user} mu2
            ON mu2.id = pa2.managerid
        WHERE
            f.id = ?
        AND ss.superceded != 1
        AND ss.statuscode >= ?
        ORDER BY
            s.id, u.firstname, u.lastname";
    $signupparams =  array(MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_WAITLISTED, POSITION_TYPE_PRIMARY, $facetofaceid, MDL_F2F_STATUS_APPROVED);
    $signups = $DB->get_records_sql($signupsql, $signupparams);

    if ($signups) {
        // Get all grades at once
        $userids = array();
        foreach ($signups as $signup) {
            if ($signup->id > 0) {
                $userids[] = $signup->id;
            }
        }

        $usercustomfields = explode(',', $CFG->facetoface_export_customprofilefields);

        // Figure out which custom fields will need date/time formatting later on.
        $formatdate = array('firstaccess', 'lastaccess', 'lastlogin', 'currentlogin');
        list($cf_sql, $cf_param) = $DB->get_in_or_equal($usercustomfields);
        $sql = "SELECT " . $DB->sql_concat("'customfield_'", 'shortname') . " AS shortname
                FROM {user_info_field}
                WHERE shortname {$cf_sql}
                AND datatype = 'datetime'";
        $usercustomformats = $DB->get_records_sql($sql, $cf_param);

        $formatdate = array_merge($formatdate, array_keys($usercustomformats));

        foreach ($signups as $signup) {
            $userid = $signup->id;

            if (!empty($CFG->facetoface_export_customprofilefields)) {
                $customuserfields = facetoface_get_user_customfields($userid,
                    array_map('trim', $usercustomfields));
                foreach ($customuserfields as $fieldname => $value) {
                    if (!isset($signup->$fieldname)) {
                        $signup->$fieldname = $value;
                    }
                }
            }

            $sessionsignups[$signup->sessionid][$signup->id] = $signup;
        }
    }

    // Fast version of "facetoface_get_sessions($facetofaceid, $location)"
    $sql = "SELECT d.id as dateid, s.id, s.datetimeknown, s.capacity,
            s.duration, d.timestart, d.timefinish, d.sessiontimezone,
            r.name as roomname, r.building as building, r.address as address
              FROM {facetoface_sessions} s
              JOIN {facetoface_sessions_dates} d ON s.id = d.sessionid
              LEFT JOIN {facetoface_room} r ON s.roomid = r.id
              WHERE
                s.facetoface = ?
              AND d.sessionid = s.id
                   $locationcondition
                   ORDER BY s.datetimeknown, d.timestart";

    $sessions = $DB->get_records_sql($sql, array_merge(array($facetofaceid), $locationparam));

    $i = $i - 1; // will be incremented BEFORE each row is written

    $displaytimezones = get_config(null, 'facetoface_displaysessiontimezones');

    foreach ($sessions as $session) {
        $customdata = $DB->get_records('facetoface_session_data', array('sessionid' => $session->id), '', 'fieldid, data');

        $sessionstartdate = false;
        $sessionenddate = false;
        $starttime   = get_string('wait-listed', 'facetoface');
        $finishtime  = get_string('wait-listed', 'facetoface');
        $status      = get_string('wait-listed', 'facetoface');

        $sessiontrainers = facetoface_get_trainers($session->id);

        if ($session->datetimeknown) {
            // Display only the first date
            $sessionobj = facetoface_format_session_times($session->timestart, $session->timefinish, $session->sessiontimezone);
            $sessiontimezone = !empty($displaytimezones) ? $sessionobj->timezone : '';
            $starttime = $sessionobj->starttime . ' ' . $sessiontimezone;
            $finishtime = $sessionobj->endtime . ' ' . $sessiontimezone;

            if (method_exists($worksheet, 'write_date')) {
                // Needs the patch in MDL-20781
                $sessionstartdate = (int)$session->timestart;
                $sessionenddate = (int)$session->timefinish;
            } else {
                $sessionstartdate = $sessionobj->startdate;
                $sessionenddate = $sessionobj->enddate;
            }

            if ($session->timestart < $timenow) {
                $status = get_string('sessionover', 'facetoface');
            } else {
                $signupcount = 0;
                if (!empty($sessionsignups[$session->id])) {
                    $signupcount = count($sessionsignups[$session->id]);
                }

                if ($signupcount >= $session->capacity) {
                    $status = get_string('bookingfull', 'facetoface');
                } else {
                    $status = get_string('bookingopen', 'facetoface');
                }
            }
        }

        if (!empty($sessionsignups[$session->id])) {
            foreach ($sessionsignups[$session->id] as $attendee) {
                $i++; $j=0;

                // Custom session fields
                foreach ($customsessionfields as $field) {
                    if (empty($field->showinsummary)) {
                        continue; // skip
                    }

                    $data = '-';
                    if (!empty($customdata[$field->id])) {
                        if (CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                            $data = str_replace(CUSTOMFIELD_DELIMITER, "\n", $customdata[$field->id]->data);
                        } else {
                            $data = $customdata[$field->id]->data;
                        }
                    }
                    $worksheet->write_string($i, $j++, $data);
                }

                if (empty($sessionstartdate)) {
                    $worksheet->write_string($i, $j++, $status); // Session start date.
                    $worksheet->write_string($i, $j++, $status); // Session end date.
                }
                else {
                    if (method_exists($worksheet, 'write_date')) {
                        $worksheet->write_date($i, $j++, $sessionstartdate, $dateformat);
                        $worksheet->write_date($i, $j++, $sessionenddate, $dateformat);
                    }
                    else {
                        $worksheet->write_string($i, $j++, $sessionstartdate);
                        $worksheet->write_string($i, $j++, $sessionenddate);
                    }
                }
                //Room
                $roomname = isset($session->roomname) ? $session->roomname . ', ' : '';
                $building = isset($session->building) ? $session->building . ', ' : '';
                $address = isset($session->address) ? $session->address : '';
                $worksheet->write_string($i, $j++, $roomname . $building . $address);

                $worksheet->write_string($i,$j++,$starttime);
                $worksheet->write_string($i,$j++,$finishtime);
                $worksheet->write_number($i,$j++,(int)$session->duration);
                $worksheet->write_string($i,$j++,$status);

                if ($trainerroles) {
                    foreach (array_keys($trainerroles) as $roleid) {
                        if (!empty($sessiontrainers[$roleid])) {
                            $trainers = array();
                            foreach ($sessiontrainers[$roleid] as $trainer) {
                                $trainers[] = fullname($trainer);
                            }

                            $trainers = implode(', ', $trainers);
                        }
                        else {
                            $trainers = '-';
                        }

                        $worksheet->write_string($i, $j++, $trainers);
                    }
                }

                foreach ($userfields as $shortname => $fullname) {
                    $value = '-';
                    if (!empty($attendee->$shortname)) {
                        $value = $attendee->$shortname;
                    }

                    if (in_array($shortname, $formatdate)) {
                        if (method_exists($worksheet, 'write_date')) {
                            $worksheet->write_date($i, $j++, (int)$value, $dateformat);
                        } else {
                            $worksheet->write_string($i, $j++, userdate($value, get_string('strftimedate', 'langconfig')));
                        }
                    } else {
                        $worksheet->write_string($i,$j++,$value);
                    }
                }

                $selectpositiononsignupglobal = get_config(null, 'facetoface_selectpositiononsignupglobal');
                if (!empty($selectpositiononsignupglobal)) {
                    $label = position::position_label($attendee);
                    $worksheet->write_string($i, $j++, $label);
                }
                $worksheet->write_string($i,$j++,$attendee->grade);

                if (method_exists($worksheet,'write_date')) {
                    $worksheet->write_date($i, $j++, (int)$attendee->timecreated, $dateformat);
                } else {
                    $signupdate = userdate($attendee->timecreated, get_string('strftimedatetime', 'langconfig'));
                    if (empty($signupdate)) {
                        $signupdate = '-';
                    }
                    $worksheet->write_string($i,$j++, $signupdate);
                }

                if (!empty($coursename)) {
                    $worksheet->write_string($i, $j++, $coursename);
                }
                if (!empty($activityname)) {
                    $worksheet->write_string($i, $j++, $activityname);
                }
            }
        }
        else {
            // no one is sign-up, so let's just print the basic info
            $i++; $j=0;

            // Custom session fields
            foreach ($customsessionfields as $field) {
                if (empty($field->showinsummary)) {
                    continue; // skip
                }

                $data = '-';
                if (!empty($customdata[$field->id])) {
                    if (CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                        $data = str_replace(CUSTOMFIELD_DELIMITER, "\n", $customdata[$field->id]->data);
                    } else {
                        $data = $customdata[$field->id]->data;
                    }
                }
                $worksheet->write_string($i, $j++, $data);
            }

            if (empty($sessionstartdate)) {
                $worksheet->write_string($i, $j++, $status); // Session start date.
                $worksheet->write_string($i, $j++, $status); // Session end date.
            }
            else {
                if (method_exists($worksheet, 'write_date')) {
                    $worksheet->write_date($i, $j++, $sessionstartdate, $dateformat);
                    $worksheet->write_date($i, $j++, $sessionenddate, $dateformat);
                }
                else {
                    $worksheet->write_string($i, $j++, $sessionstartdate);
                    $worksheet->write_string($i, $j++, $sessionenddate);
                }
            }
            //Room
            $roomname = isset($session->roomname) ? $session->roomname . ', ' : '';
            $building = isset($session->building) ? $session->building . ', ' : '';
            $address = isset($session->address) ? $session->address : '';
            $worksheet->write_string($i, $j++, $roomname . $building . $address);

            $worksheet->write_string($i,$j++,$starttime);
            $worksheet->write_string($i,$j++,$finishtime);
            $worksheet->write_number($i,$j++,(int)$session->duration);
            $worksheet->write_string($i,$j++,$status);

            if ($trainerroles) {
                foreach (array_keys($trainerroles) as $roleid) {
                    if (!empty($sessiontrainers[$roleid])) {
                        $trainers = array();
                        foreach ($sessiontrainers[$roleid] as $trainer) {
                            $trainers[] = fullname($trainer);
                        }

                        $trainers = implode(', ', $trainers);
                    }
                    else {
                        $trainers = '-';
                    }

                    $worksheet->write_string($i, $j++, $trainers);
                }
            }

            foreach ($userfields as $unused) {
                $worksheet->write_string($i,$j++,'-');
            }
            // Grade/attendance
            $worksheet->write_string($i,$j++,'-');
            // Date signed up
            $worksheet->write_string($i,$j++,'-');

            if (!empty($coursename)) {
                $worksheet->write_string($i, $j++, $coursename);
            }
            if (!empty($activityname)) {
                $worksheet->write_string($i, $j++, $activityname);
            }
        }
    }

    return $i;
}

/**
 * Return an object with all values for a user's custom fields.
 *
 * This is about 15 times faster than the custom field API.
 *
 * @param array $fieldstoinclude Limit the fields returned/cached to these ones (optional)
 */
function facetoface_get_user_customfields($userid, $fieldstoinclude=null) {
    global $CFG, $DB;

    // Cache all lookup
    static $customfields = null;
    if (null == $customfields) {
        $customfields = array();
    }

    if (!empty($customfields[$userid])) {
        return $customfields[$userid];
    }

    $ret = new stdClass();

    $sql = 'SELECT '.$DB->sql_concat("'customfield_'", 'uif.shortname').' AS shortname, id.data
              FROM {user_info_field} uif
              JOIN {user_info_data} id ON id.fieldid = uif.id
              JOIN {user_info_category} c ON uif.categoryid = c.id
              WHERE id.userid = ? ';
    $params = array($userid);
    if (!empty($fieldstoinclude)) {
        list($insql, $inparams) = $DB->get_in_or_equal($fieldstoinclude);
        $sql .= ' AND uif.shortname '.$insql;
        $params = array_merge($params, $inparams);
    }
    $sql .= ' ORDER BY c.sortorder, uif.sortorder';

    $customfields = $DB->get_records_sql($sql, $params);
    foreach ($customfields as $field) {
        $fieldname = $field->shortname;
        $ret->$fieldname = $field->data;
    }

    $customfields[$userid] = $ret;
    return $ret;
}


/**
 * Add a record to the facetoface submissions table and sends out an
 * email confirmation
 *
 * @param class $session record from the facetoface_sessions table
 * @param class $facetoface record from the facetoface table
 * @param class $course record from the course table
 * @param string $discountcode code entered by the user
 * @param integer $notificationtype type of notifications to send to user
 * @see {{MDL_F2F_INVITE}}
 * @param integer $statuscode Status code to set
 * @param integer $userid user to signup
 * @param bool $notifyuser whether or not to send an email confirmation
 * @param string $usernote
 * @param class $positionassignment object containing information on selected position (positionid, type, assignmnetid)
 */
function facetoface_user_signup($session, $facetoface, $course, $discountcode,
                                $notificationtype, $statuscode, $userid = false,
                                $notifyuser = true, $usernote = '', $positionassignment = null) {

    global $DB, $OUTPUT, $USER;

    // Get user id
    if (!$userid) {
        $userid = $USER->id;
    }

    $return = false;
    $timenow = time();

    // Check to see if a signup already exists
    if ($existingsignup = $DB->get_record('facetoface_signups', array('sessionid' => $session->id, 'userid' => $userid))) {
        $usersignup = $existingsignup;
    } else {
        // Otherwise, prepare a signup object
        $usersignup = new stdClass();
        $usersignup->sessionid = $session->id;
        $usersignup->userid = $userid;
    }

    $usersignup->bookedby = $userid == $USER->id ? 0 : $USER->id;
    $usersignup->mailedreminder = 0;
    $usersignup->notificationtype = $notificationtype;

    // If the selected position information hasn't been supplied then we need to try to default it
    // we won't throw errors if it's not present as all we can do is throw exceptions that won't be handled and may break cron
    // in theory the only routes here that don't go through facetoface_user_import handle reservations which handled by a manager
    // or come from a wait list and so a position assignment should always be available.
    $selectpositiononsignupglobal = get_config(null, 'facetoface_selectpositiononsignupglobal');
    $positionrequired = !empty($selectpositiononsignupglobal) && !empty($facetoface->selectpositiononsignup);

    if ($positionrequired) {
        if ($positionassignment === null) {
            $positionassignment = pos_get_most_primary_position_assignment($userid);
        }

        if (!empty($positionassignment)) {
            $usersignup->positionid = $positionassignment->positionid;
            $usersignup->positiontype = $positionassignment->positiontype;
            $usersignup->positionassignmentid = $positionassignment->id;
        }
    }

    // If no position is wanted by the face to face or none is provided then record all info as null.
    if (!$positionrequired || empty($positionassignment)) {
        $usersignup->positionid = null;
        $usersignup->positiontype = null;
        $usersignup->positionassignmentid = null;
    }

    $usersignup->discountcode = trim(strtoupper($discountcode));
    if (empty($usersignup->discountcode)) {
        $usersignup->discountcode = null;
    }

    // Update/insert the signup record
    if (!empty($usersignup->id)) {
        $success = $DB->update_record('facetoface_signups', $usersignup);
    } else {
        $usersignup->id = $DB->insert_record('facetoface_signups', $usersignup);
        $success = (bool)$usersignup->id;
    }

    if (!$success) {
        print_error('error:couldnotupdatef2frecord', 'facetoface');
    }

    // Work out which status to use

    // If approval not required or self approval enabled.
    if (!$facetoface->approvalreqd || facetoface_session_has_selfapproval($facetoface, $session)) {
        $new_status = $statuscode;
    } else {
        // If approval required

        // Get current status (if any)
        $current_status =  $DB->get_field('facetoface_signups_status', 'statuscode', array('signupid' => $usersignup->id, 'superceded' => 0));

        // If approved, then no problem
        if ($current_status == MDL_F2F_STATUS_APPROVED) {
            $new_status = $statuscode;
        } else if ($session->datetimeknown) {
            // If currently on the waitlist they have already been approved, no need to approve them again.
            if ($current_status == MDL_F2F_STATUS_WAITLISTED) {
                $new_status = $statuscode;
            } else {
                // Otherwise, send manager request.
                $new_status = MDL_F2F_STATUS_REQUESTED;
            }
        } else {
            $new_status = MDL_F2F_STATUS_WAITLISTED;
        }
    }

    // Update status.
    if (!facetoface_update_signup_status($usersignup->id, $new_status, $USER->id, $usernote)) {
        print_error('error:f2ffailedupdatestatus', 'facetoface');
    }

    // Add to user calendar -- if facetoface usercalentry is set to true
    if ($facetoface->usercalentry && in_array($new_status, array(MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_WAITLISTED))) {
        facetoface_add_session_to_calendar($session, $facetoface, 'user', $userid, 'booking');
    }

    // If session has already started, do not send a notification
    if (facetoface_has_session_started($session, $timenow)) {
        $notifyuser = false;
    }

    // Send notification.
    $notifytype = ((int)$notificationtype == MDL_F2F_NONE ? false : true);
    $session->notifyuser = $notifyuser && $notifytype;

    switch ($new_status) {
        case MDL_F2F_STATUS_BOOKED:
            $error = facetoface_send_confirmation_notice($facetoface, $session, $userid, $notificationtype, false);
            break;

        case MDL_F2F_STATUS_WAITLISTED:
            $error = facetoface_send_confirmation_notice($facetoface, $session, $userid, $notificationtype, true);
            break;

        case MDL_F2F_STATUS_REQUESTED:
            $error = facetoface_send_request_notice($facetoface, $session, $userid);
            break;
    }

    if (!empty($error)) {
        if ($error == 'userdoesnotexist') {
            print_error($error, 'facetoface');
        } else {
            // Don't fail if email isn't sent, just display a warning
            echo $OUTPUT->notification(get_string($error, 'facetoface'), 'notifyproblem');
        }
    }

    if ($session->notifyuser) {
        if (!$DB->update_record('facetoface_signups', $usersignup)) {
            print_error('error:couldnotupdatef2frecord', 'facetoface');
        }
    }

    // Update course completion.
    if (in_array($new_status, array(MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_WAITLISTED))) {

        $completion = new completion_info($course);
        if ($completion->is_enabled()) {

            $ccdetails = array(
                'course' => $course->id,
                'userid' => $userid,
            );

            $cc = new completion_completion($ccdetails);
            $cc->mark_inprogress($timenow);
        }
    }

    facetoface_withdraw_interest($facetoface, $userid);

    return true;
}

/**
 * Update the signup status of a particular signup
 *
 * @param integer $signupid ID of the signup to be updated
 * @param integer $statuscode Status code to be updated to
 * @param integer $createdby User ID of the user causing the status update
 * @param string $note Cancellation reason or other notes
 * @param int $grade Grade
 * @param bool $usetransaction Set to true if database transactions are to be used
 *
 * @returns integer ID of newly created signup status, or false
 *
 */
function facetoface_update_signup_status($signupid, $statuscode, $createdby, $note='', $grade=NULL) {
    global $DB;
    $timenow = time();

    $signupstatus = new stdclass;
    $signupstatus->signupid = $signupid;
    $signupstatus->statuscode = $statuscode;
    $signupstatus->createdby = $createdby;
    $signupstatus->timecreated = $timenow;
    $signupstatus->note = $note;
    $signupstatus->grade = $grade;
    $signupstatus->superceded = 0;
    $signupstatus->mailed = 0;

    if ($statusid = $DB->insert_record('facetoface_signups_status', $signupstatus)) {
        // mark any previous signup_statuses as superceded
        $where = "signupid = ? AND ( superceded = 0 OR superceded IS NULL ) AND id != ?";
        $whereparams = array($signupid, $statusid);
        $DB->set_field_select('facetoface_signups_status', 'superceded', 1, $where, $whereparams);

        // Check for completions.
        $sql = "SELECT f2f.id, f2f.course, f2fs.userid
                FROM {facetoface_signups} f2fs
                    LEFT JOIN {facetoface_sessions} f2fses ON (f2fses.id = f2fs.sessionid)
                    LEFT JOIN {facetoface} f2f ON (f2f.id = f2fses.facetoface)
                WHERE f2fs.id = ?";

        $status = $DB->get_record_sql($sql, array($signupid));
        facetoface_set_completion($status, $status->userid, COMPLETION_UNKNOWN);
        $signup = $DB->get_record('facetoface_signups', array('id' => $signupid), '*', MUST_EXIST);
        $session = $DB->get_record('facetoface_sessions', array('id' => $signup->sessionid), '*', MUST_EXIST);
        $facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface), '*', MUST_EXIST);
        $moduleid = $DB->get_field('modules', 'id', array('name' => 'facetoface'), MUST_EXIST);
        $cm = $DB->get_record('course_modules', array('module' => $moduleid, 'instance' => $facetoface->id), '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        $signupstatus->id = $statusid;

        $event = \mod_facetoface\event\signupstatus_updated::create(array(
            'context' => $context,
            'objectid' => $signupstatus->id,
        ));
        $event->add_record_snapshot('facetoface_signups_status', $signupstatus);
        $event->trigger();

        return $statusid;
    } else {
        return false;
    }
}

/**
 * Cancel a user who signed up earlier
 *
 * @param class $session       Record from the facetoface_sessions table
 * @param integer $userid      ID of the user to remove from the session
 * @param bool $forcecancel    Forces cancellation of sessions that have already occurred
 * @param string $errorstr     Passed by reference. For setting error string in calling function
 * @param string $cancelreason Optional justification for cancelling the signup
 */
function facetoface_user_cancel($session, $userid=false, $forcecancel=false, &$errorstr=null, $cancelreason='') {
    if (!$userid) {
        global $USER;
        $userid = $USER->id;
    }

    // if $forcecancel is set, cancel session even if already occurred
    // used by facetotoface_delete_session()
    if (!$forcecancel) {
        $timenow = time();
        // don't allow user to cancel a session that has already occurred
        if (facetoface_has_session_started($session, $timenow)) {
            $errorstr = get_string('error:eventoccurred', 'facetoface');
            return false;
        }
    }

    if (facetoface_user_cancel_submission($session->id, $userid, $cancelreason)) {
        facetoface_remove_session_from_calendar($session, 0, $userid);

        facetoface_update_attendees($session);

        return true;
    }

    $errorstr = get_string('error:cancelbooking', 'facetoface');
    return false;
}


/**
 * Returns true if the user has registered for a session in the given
 * facetoface activity
 *
 * @global class $USER used to get the current userid
 * @param int $facetofaceid
 * @param int $sessionid session id if facetoface allows multiple sessions
 * @returns integer The session id that we signed up for, false otherwise
 */
function facetoface_check_signup($facetofaceid, $sessionid = null) {

    global $USER;

    if ($submissions = facetoface_get_user_submissions($facetofaceid, $USER->id, MDL_F2F_STATUS_REQUESTED, MDL_F2F_STATUS_FULLY_ATTENDED, $sessionid)) {
        return reset($submissions)->sessionid;
    } else {
        return false;
    }
}

/**
 * Human-readable version of the format of the manager's email address
 */
function facetoface_get_manageremailformat() {

    $addressformat = get_config(NULL, 'facetoface_manageraddressformat');

    if (!empty($addressformat)) {
        $readableformat = get_config(NULL, 'facetoface_manageraddressformatreadable');
        return get_string('manageremailformat', 'facetoface', $readableformat);
    }

    return '';
}

/**
 * Returns true if the given email address follows the format
 * prescribed by the site administrator
 *
 * @param string $manageremail email address as entered by the user
 */
function facetoface_check_manageremail($manageremail) {

    $addressformat = get_config(NULL, 'facetoface_manageraddressformat');

    if (empty($addressformat) || strpos($manageremail, $addressformat)) {
        return true;
    } else {
        return false;
    }
}

/**
 * Mark the fact that the user attended the facetoface session by
 * giving that user a grade of 100
 *
 * @param array $data array containing the sessionid under the 's' key
 *                    and every submission ID to mark as attended
 *                    under the 'submissionid_XXXX' keys where XXXX is
 *                     the ID of the signup
 */
function facetoface_take_attendance($data) {
    global $USER, $DB;

    $sessionid = $data->s;

    // Load session
    if (!$session = facetoface_get_session($sessionid)) {
        error_log('F2F: Could not load facetoface session');
        return false;
    }

    // Check facetoface has finished
    if ($session->datetimeknown && !facetoface_has_session_started($session, time())) {
        error_log('F2F: Can not take attendance for a session that has not yet started');
        return false;
    }

    // Record the selected attendees from the user interface - the other attendees will need their grades set
    // to zero, to indicate non attendance, but only the ticked attendees come through from the web interface.
    // Hence the need for a diff
    $selectedsubmissionids = array();

    // FIXME: This is not very efficient, we should do the grade
    // query outside of the loop to get all submissions for a
    // given Face-to-face ID, then call
    // facetoface_grade_item_update with an array of grade
    // objects.
    foreach ($data as $key => $value) {

        $submissionidcheck = substr($key, 0, 13);
        if ($submissionidcheck == 'submissionid_') {
            $submissionid = substr($key, 13);
            $selectedsubmissionids[$submissionid]=$submissionid;

            if (!$DB->record_exists('facetoface_signups', array('id' => $submissionid, 'sessionid' => $session->id))) {
                // The data is inconsistent, hacker?
                error_log("F2F: could not mark signup id '$submissionid' because it does not match session id $session->id");
                continue;
            }

            // Update status
            switch ($value) {

                case MDL_F2F_STATUS_NO_SHOW:
                    $grade = 0;
                    break;

                case MDL_F2F_STATUS_PARTIALLY_ATTENDED:
                    $grade = 50;
                    break;

                case MDL_F2F_STATUS_FULLY_ATTENDED:
                    $grade = 100;
                    break;

                default:
                    // This use has not had attendance set
                    // Jump to the next item in the foreach loop
                    continue 2;
            }

            facetoface_update_signup_status($submissionid, $value, $USER->id, '', $grade);

            if (!facetoface_take_individual_attendance($submissionid, $grade)) {
                error_log("F2F: could not mark '$submissionid' as ".$value);
                return false;
            }
        }
    }

    return true;
}

/**
 * Mark users' booking requests as declined or approved
 *
 * @param array $data array containing the sessionid under the 's' key
 *                    and an array of request approval/denies
 */
function facetoface_approve_requests($data) {
    global $USER, $DB;

    // Check request data
    if (empty($data->requests) || !is_array($data->requests)) {
        error_log('F2F: No request data supplied');
        return false;
    }

    $sessionid = $data->s;

    // Load session
    if (!$session = facetoface_get_session($sessionid)) {
        error_log('F2F: Could not load facetoface session');
        return false;
    }

    // Load facetoface
    if (!$facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface))) {
        error_log('F2F: Could not load facetoface instance');
        return false;
    }

    // Load course
    if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
        error_log('F2F: Could not load course');
        return false;
    }

    // Loop through requests
    foreach ($data->requests as $key => $value) {

        // Check key/value
        if (!is_numeric($key) || !is_numeric($value)) {
            continue;
        }

        // Load user submission
        if (!$attendee = facetoface_get_attendee($sessionid, $key)) {
            error_log('F2F: User '.$key.' not an attendee of this session');
            continue;
        }

        // Double-check request exists and not already approved or declined.
        $params = array(
            'signupid' => $attendee->submissionid,
            'statuscode' => MDL_F2F_STATUS_REQUESTED,
            'superceded' => 0);
        if (!$DB->record_exists('facetoface_signups_status', $params)) {
            continue;
        }

        // Update status
        switch ($value) {

            // Decline
            case 1:
                facetoface_update_signup_status(
                        $attendee->submissionid,
                        MDL_F2F_STATUS_DECLINED,
                        $USER->id
                );

                // Send a decline notice to the user.
                facetoface_send_decline_notice($facetoface, $session, $attendee->id);

                break;

            // Approve
            case 2:
                facetoface_update_signup_status(
                        $attendee->submissionid,
                        MDL_F2F_STATUS_APPROVED,
                        $USER->id
                );

                if (!$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $course->id)) {
                    print_error('error:incorrectcoursemodule', 'facetoface');
                }

                $contextmodule = context_module::instance($cm->id);

                // Check if there is capacity
                if (facetoface_session_has_capacity($session, $contextmodule)) {
                    $status = MDL_F2F_STATUS_BOOKED;
                } else {
                    if ($session->allowoverbook) {
                        $status = MDL_F2F_STATUS_WAITLISTED;
                    }
                }

                // Signup user
                if (!facetoface_user_signup(
                        $session,
                        $facetoface,
                        $course,
                        $attendee->discountcode,
                        $attendee->notificationtype,
                        $status,
                        $attendee->id,
                        true,
                        $attendee->usernote
                    )) {
                    continue;
                }

                break;

            case 0:
            default:
                // Change nothing
                continue;
        }
    }

    return true;
}

/*
 * Set the grading for an individual submission, to either 0 or 100 to indicate attendance
 * @param $submissionid The id of the submission in the database
 * @param $grading Grade to set
 */
function facetoface_take_individual_attendance($submissionid, $grading) {
    global $USER, $CFG, $DB;

    $timenow = time();

    $record = $DB->get_record_sql("SELECT f.*, s.userid
                                FROM {facetoface_signups} s
                                JOIN {facetoface_sessions} fs ON s.sessionid = fs.id
                                JOIN {facetoface} f ON f.id = fs.facetoface
                                JOIN {course_modules} cm ON cm.instance = f.id
                                JOIN {modules} m ON m.id = cm.module
                                WHERE s.id = ? AND m.name='facetoface'",
                            array($submissionid));

    $grade = new stdclass();
    $grade->userid = $record->userid;
    $grade->rawgrade = $grading;
    $grade->rawgrademin = 0;
    $grade->rawgrademax = 100;
    $grade->timecreated = $timenow;
    $grade->timemodified = $timenow;
    $grade->usermodified = $USER->id;

    return facetoface_grade_item_update($record, $grade);
}

/**
 * Used in many places to obtain properly-formatted session date and time info
 *
 * @param int $start a start time Unix timestamp
 * @param int $end an end time Unix timestamp
 * @param string $tz a session timezone
 * @return object Formatted date, start time, end time and timezone info
 */
function facetoface_format_session_times($start, $end, $tz) {

    $displaytimezones = get_config(null, 'facetoface_displaysessiontimezones');

    $formattedsession = new stdClass();
    $tzknown = false;
    if (!empty($tz)) {
        $targetTZ = $tz;
        $tzknown = true;
    } else {
        $targetTZ = totara_get_clean_timezone();
    }
    $formattedsession->startdate = userdate($start, get_string('sessiondateformat', 'facetoface'), $targetTZ);
    $formattedsession->starttime = userdate($start, get_string('sessiondatetimeformat', 'facetoface'), $targetTZ);
    $formattedsession->enddate = userdate($end, get_string('sessiondateformat', 'facetoface'), $targetTZ);
    $formattedsession->endtime = userdate($end, get_string('sessiondatetimeformat', 'facetoface'), $targetTZ);
    if (empty($displaytimezones)) {
        $formattedsession->timezone = '';
    } else if ($tzknown) {
        $formattedsession->timezone = get_string(strtolower($targetTZ), 'timezones');
    } else {
        $formattedsession->timezone = get_string('sessiontimezoneunknown', 'facetoface');
    }
    return $formattedsession;
}
/**
 * Called when viewing course page.
 *
 * @param cm_info $coursemodule
 */
function facetoface_cm_info_view(cm_info $coursemodule) {
    global $USER, $DB;
    $output = '';

    if (!($facetoface = $DB->get_record('facetoface', array('id' => $coursemodule->instance)))) {
        return null;
    }

    $coursemodule->set_name($facetoface->name);

    $contextmodule = context_module::instance($coursemodule->id);
    if (!has_capability('mod/facetoface:view', $contextmodule)) {
        return null; // Not allowed to view this activity.
    }
    // Can view attendees.
    $viewattendees = has_capability('mod/facetoface:viewattendees', $contextmodule);
    // Can see "view all sessions" link even if activity is hidden/currently unavailable.
    $iseditor = has_any_capability(array('mod/facetoface:viewattendees', 'mod/facetoface:editsessions',
                                        'mod/facetoface:addattendees', 'mod/facetoface:addattendees',
                                        'mod/facetoface:takeattendance'), $contextmodule);

    $timenow = time();

    $strviewallsessions = get_string('viewallsessions', 'facetoface');
    $sessions_url = new moodle_url('/mod/facetoface/view.php', array('f' => $facetoface->id));
    $htmlviewallsessions = html_writer::link($sessions_url, $strviewallsessions, array('class' => 'f2fsessionlinks f2fviewallsessions', 'title' => $strviewallsessions));

    $alreadydeclaredinterest = facetoface_user_declared_interest($facetoface);
    $declareinterest_enable = $alreadydeclaredinterest || facetoface_activity_can_declare_interest($facetoface);
    $declareinterest_label = $alreadydeclaredinterest ? get_string('declareinterestwithdraw', 'facetoface') : get_string('declareinterest', 'facetoface');
    $declareinterest_url = new moodle_url('/mod/facetoface/interest.php', array('f' => $facetoface->id));
    $declareinterest_link = html_writer::link($declareinterest_url, $declareinterest_label, array('class' => 'f2fsessionlinks f2fviewallsessions', 'title' => $declareinterest_label));

    if ($submissions = facetoface_get_user_submissions($facetoface->id, $USER->id)) {
        // User has signedup for the instance.
        if (!$facetoface->multiplesessions) {
            // First submission only.
            $submissions = array(array_shift($submissions));
        }
        foreach ($submissions as $submission) {

            if ($session = facetoface_get_session($submission->sessionid)) {
                $allowcancellation = false;
                if ($session->datetimeknown && facetoface_has_session_started($session, $timenow) && facetoface_is_session_in_progress($session, $timenow)) {
                    $status = get_string('sessioninprogress', 'facetoface');
                    if ($submission->statuscode == MDL_F2F_STATUS_WAITLISTED) {
                        $allowcancellation = true;
                    }
                } else if ($session->datetimeknown && facetoface_has_session_started($session, $timenow)) {
                    $status = get_string('sessionover', 'facetoface');
                    if ($submission->statuscode == MDL_F2F_STATUS_WAITLISTED) {
                        $allowcancellation = true;
                    }
                } else {
                    $status = get_string('bookingstatus', 'facetoface');
                }

                $sessiondates = '';

                if ($session->datetimeknown) {
                    foreach ($session->sessiondates as $date) {
                        if (!empty($sessiondates)) {
                            $sessiondates .= html_writer::empty_tag('br');
                        }
                        $sessionobj = facetoface_format_session_times($date->timestart, $date->timefinish, $date->sessiontimezone);
                        if ($sessionobj->startdate == $sessionobj->enddate) {
                            $sessiondatelangkey = !empty($sessionobj->timezone) ? 'sessionstartdateandtime' : 'sessionstartdateandtimewithouttimezone';
                            $sessiondates .= get_string($sessiondatelangkey, 'facetoface', $sessionobj);
                        } else {
                            $sessiondatelangkey = !empty($sessionobj->timezone) ? 'sessionstartfinishdateandtime' : 'sessionstartfinishdateandtimewithouttimezone';
                            $sessiondates .= get_string($sessiondatelangkey, 'facetoface', $sessionobj);
                        }
                    }
                } else {
                    $sessiondates = get_string('wait-listed', 'facetoface');
                }

                $span = html_writer::tag('span', get_string('options', 'facetoface').':', array('class' => 'f2fsessionnotice'));

                // Don't include the link to cancel a session if it has already occurred.
                $moreinfolink = '';
                $cancellink = '';
                $strcancelbooking = get_string('cancelbooking', 'facetoface');
                $cancel_url = new moodle_url('/mod/facetoface/cancelsignup.php', array('s' => $session->id));
                if (!facetoface_has_session_started($session, $timenow)) {
                    $strmoreinfo  = get_string('moreinfo', 'facetoface');
                    $signup_url   = new moodle_url('/mod/facetoface/signup.php', array('s' => $session->id));
                    $moreinfolink = html_writer::link($signup_url, $strmoreinfo, array('class' => 'f2fsessionlinks f2fsessioninfolink', 'title' => $strmoreinfo));

                    $cancellink = html_writer::link($cancel_url, $strcancelbooking, array('class' => 'f2fsessionlinks f2fviewallsessions', 'title' => $strcancelbooking));
                } else {
                    // Session is started.
                    if ($allowcancellation) {
                        $cancellink = html_writer::link($cancel_url, $strcancelbooking, array('class' => 'f2fsessionlinks f2fviewallsessions', 'title' => $strcancelbooking));
                    }
                }
                $cancellink = $session->allowcancellations ? $cancellink : '';

                // Get room data.
                $roomtext = '';
                $roomdata = $DB->get_record('facetoface_room', array('id' => $session->roomid));
                if (!empty($roomdata)) {
                    $roomtext  = isset($roomdata->name)     ? format_string($roomdata->name)    .', '.html_writer::empty_tag('br') : '';
                    $roomtext .= isset($roomdata->building) ? format_string($roomdata->building).', '.html_writer::empty_tag('br') : '';
                    $roomtext .= isset($roomdata->address)  ? format_string($roomdata->address) .', '.html_writer::empty_tag('br') : '';
                }

                // Don't include the link to view attendees if user is lacking capability.
                $attendeeslink = '';
                if ($viewattendees) {
                    $strseeattendees = get_string('seeattendees', 'facetoface');
                    $attendees_url = new moodle_url('/mod/facetoface/attendees.php', array('s' => $session->id));
                    $attendeeslink = html_writer::link($attendees_url, $strseeattendees, array('class' => 'f2fsessionlinks f2fviewattendees', 'title' => $strseeattendees));
                }


                $output .= html_writer::start_tag('div', array('class' => 'f2fsessiongroup'))
                    . html_writer::tag('span', $status, array('class' => 'f2fsessionnotice'))
                    . html_writer::start_tag('div', array('class' => 'f2fsession f2fsignedup'))
                    . html_writer::tag('div', $roomtext . $sessiondates, array('class' => 'f2fsessiontime'))
                    . html_writer::tag('div', $span . $moreinfolink . $attendeeslink . $cancellink, array('class' => 'f2foptions'))
                    . html_writer::end_tag('div')
                    . html_writer::end_tag('div');
            }
        }
        // Add "view all sessions" row to table.
        $output .= $htmlviewallsessions;

        if ($declareinterest_enable) {
            $output .= $declareinterest_link;
        }
    } else if ($sessions = facetoface_get_sessions($facetoface->id)) {
        if ($facetoface->display > 0) {
            $j=1;

            $sessionsinprogress = array();
            $futuresessions = array();

            foreach ($sessions as $session) {
                if (!facetoface_session_has_capacity($session, $contextmodule, MDL_F2F_STATUS_WAITLISTED) && !$session->allowoverbook) {
                    continue;
                }

                if ($session->datetimeknown && facetoface_has_session_started($session, $timenow) && !facetoface_is_session_in_progress($session, $timenow)) {
                    // Finished session, don't display.
                    continue;
                } else {
                    $signup_url   = new moodle_url('/mod/facetoface/signup.php', array('s' => $session->id));
                    $moreinfolink = html_writer::link($signup_url, get_string('signup', 'facetoface'), array('class' => 'f2fsessionlinks f2fsessioninfolink'));

                    $span = html_writer::tag('span', get_string('options', 'facetoface').':', array('class' => 'f2fsessionnotice'));
                }

                $multidate = '';
                $sessiondate = '';
                if ($session->datetimeknown) {
                    if (empty($session->sessiondates)) {
                        $sessiondate = get_string('unknowndate', 'facetoface');
                    } else {
                        $sessionobj = facetoface_format_session_times($session->sessiondates[0]->timestart, $session->sessiondates[0]->timefinish, $session->sessiondates[0]->sessiontimezone);
                        if ($sessionobj->startdate == $sessionobj->enddate) {
                            $sessiondatelangkey = !empty($sessionobj->timezone) ? 'sessionstartdateandtime' : 'sessionstartdateandtimewithouttimezone';
                            $sessiondate = get_string($sessiondatelangkey, 'facetoface', $sessionobj);
                        } else {
                            $sessiondatelangkey = !empty($sessionobj->timezone) ? 'sessionstartfinishdateandtime' : 'sessionstartfinishdateandtimewithouttimezone';
                            $sessiondate .= get_string($sessiondatelangkey, 'facetoface', $sessionobj);
                        }
                        if (count($session->sessiondates) > 1) {
                            $multidate = html_writer::empty_tag('br') . get_string('multidate', 'facetoface');
                        }
                    }
                } else {
                    $sessiondate = get_string('wait-listed', 'facetoface');
                }

                $locationstring = '';
                $roomdata = $DB->get_record('facetoface_room', array('id' => $session->roomid));
                if (!empty($roomdata)) {
                    $locationstring  = isset($roomdata->name)     ? format_string($roomdata->name)    .', '.html_writer::empty_tag('br') : '';
                    $locationstring .= isset($roomdata->building) ? format_string($roomdata->building).', '.html_writer::empty_tag('br') : '';
                    $locationstring .= isset($roomdata->address)  ? format_string($roomdata->address) .', '.html_writer::empty_tag('br') : '';
                }

                $sessionobject = new stdClass();
                $sessionobject->location = $locationstring;
                $sessionobject->date = $sessiondate;
                $sessionobject->multidate = $multidate;

                if ($session->datetimeknown && (facetoface_has_session_started($session, $timenow)) && facetoface_is_session_in_progress($session, $timenow)) {
                    $sessionsinprogress[] = $sessionobject;
                } else {
                    $sessionobject->options = $span;
                    $sessionobject->moreinfolink = $moreinfolink;
                    $futuresessions[] = $sessionobject;
                }

                $j++;
                if ($j > $facetoface->display) {
                    break;
                }
            }

            if (!empty($sessionsinprogress)) {
                $output .= html_writer::start_tag('div', array('class' => 'f2fsessiongroup'));
                $output .= html_writer::tag('span', get_string('sessioninprogress', 'facetoface'), array('class' => 'f2fsessionnotice'));

                foreach ($sessionsinprogress as $session) {
                    $output .= html_writer::start_tag('div', array('class' => 'f2fsession f2finprogress'))
                        . html_writer::tag('span', $session->location.$session->date.$session->multidate, array('class' => 'f2fsessiontime'))
                        . html_writer::end_tag('div');
                }
                $output .= html_writer::end_tag('div');
            }

            if (!empty($futuresessions)) {
                $output .= html_writer::start_tag('div', array('class' => 'f2fsessiongroup'));
                $output .= html_writer::tag('span', get_string('signupforsession', 'facetoface'), array('class' => 'f2fsessionnotice'));

                foreach ($futuresessions as $session) {
                    $output .= html_writer::start_tag('div', array('class' => 'f2fsession f2ffuture'))
                        . html_writer::tag('div', $session->location.$session->date.$session->multidate, array('class' => 'f2fsessiontime'))
                        . html_writer::tag('div', $session->options . $session->moreinfolink, array('class' => 'f2foptions'))
                        . html_writer::end_tag('div');
                }
                $output .= html_writer::end_tag('div');
            }

            $output .= ($iseditor || ($coursemodule->visible && $coursemodule->available)) ? $htmlviewallsessions : $strviewallsessions;

            if (($iseditor || ($coursemodule->visible && $coursemodule->available)) && $declareinterest_enable) {
                $output .= $declareinterest_link;
            }
        } else {
            // Show only name if session display is set to zero.
            $content = html_writer::tag('span', $htmlviewallsessions, array('class' => 'f2fsessionnotice f2factivityname'));
            $coursemodule->set_content($content);
            return;
        }
    } else if (has_capability('mod/facetoface:viewemptyactivities', $contextmodule)) {
        $content = html_writer::tag('span', $htmlviewallsessions, array('class' => 'f2fsessionnotice f2factivityname'));
        $coursemodule->set_content($content);
        return;
    } else {
        // Nothing to display to this user.
        $coursemodule->set_content('');
        return;
    }

    $coursemodule->set_content($output);
}


/**
 * Update grades by firing grade_updated event
 *
 * @param object $facetoface null means all facetoface activities
 * @param int $userid specific user only, 0 mean all (not used here)
 * @param bool $nullifnone If a single user is specified and $nullifnone is true, a grade item with a null rawgrade will be inserted
 */
function facetoface_update_grades($facetoface=null, $userid=0, $nullifnone = true) {
    global $DB;

    if (($facetoface != null) && $userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        facetoface_grade_item_update($facetoface, $grade);
    } else if ($facetoface != null) {
        facetoface_grade_item_update($facetoface);
    } else {
        $sql = "SELECT f.*, cm.idnumber as cmidnumber
                  FROM {facetoface} f
                  JOIN {course_modules} cm ON cm.instance = f.id
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name='facetoface'";
        if ($rs = $DB->get_recordset_sql($sql)) {
            foreach ($rs as $facetoface) {
                facetoface_grade_item_update($facetoface);
            }
            $rs->close();
        }
    }
    return true;
}

/**
 * Create grade item for given Face-to-face session
 *
 * @param int facetoface  Face-to-face activity (not the session) to grade
 * @param mixed grades    grades objects or 'reset' (means reset grades in gradebook)
 * @return int 0 if ok, error code otherwise
 */
function facetoface_grade_item_update($facetoface, $grades=NULL) {
    global $CFG, $DB;

    if (!isset($facetoface->cmidnumber)) {

        $sql = "SELECT cm.idnumber as cmidnumber
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name='facetoface' AND cm.instance = ?";
        $facetoface->cmidnumber = $DB->get_field_sql($sql, array($facetoface->id));
    }

    $params = array('itemname' => $facetoface->name,
                    'idnumber' => $facetoface->cmidnumber);

    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademin']  = 0;
    $params['gradepass'] = 100;
    $params['grademax']  = 100;

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    $retcode = grade_update('mod/facetoface', $facetoface->course, 'mod', 'facetoface',
                            $facetoface->id, 0, $grades, $params);
    return ($retcode === GRADE_UPDATE_OK);
}

/**
 * Delete grade item for given facetoface
 *
 * @param object $facetoface object
 * @return object facetoface
 */
function facetoface_grade_item_delete($facetoface) {
    $retcode = grade_update('mod/facetoface', $facetoface->course, 'mod', 'facetoface',
                            $facetoface->id, 0, NULL, array('deleted' => 1));
    return ($retcode === GRADE_UPDATE_OK);
}

/**
 * Return number of attendees signed up to a facetoface session
 *
 * @param integer $session_id
 * @param integer $status MDL_F2F_STATUS_* constant (optional)
 * @return integer
 */
function facetoface_get_num_attendees($session_id, $status = MDL_F2F_STATUS_BOOKED, $comp = '>=') {
    global $CFG, $DB;

    $sql = 'SELECT count(ss.id)
        FROM
            {facetoface_signups} su
        JOIN
            {facetoface_signups_status} ss
        ON
            su.id = ss.signupid
        WHERE
            sessionid = ?
        AND
            ss.superceded=0
        AND
        ss.statuscode ' . $comp . ' ?';

    // for the session, pick signups that haven't been superceded, or cancelled
    return (int) $DB->count_records_sql($sql, array($session_id, $status));
}

/**
 * Return all of a users' submissions to a facetoface
 *
 * @param integer $facetofaceid
 * @param integer $userid
 * @param boolean $includecancellations
 * @param integer $minimumstatus Minimum status level to return
 * @param integer $maximumstatus Maximum status level to return
 * @param integer $sessionid Session id
 * @return array submissions | false No submissions
 */
function facetoface_get_user_submissions($facetofaceid, $userid, $minimumstatus=MDL_F2F_STATUS_REQUESTED, $maximumstatus=MDL_F2F_STATUS_FULLY_ATTENDED, $sessionid = null) {
    global $DB;

    $whereclause = "s.facetoface = ? AND su.userid = ? AND ss.superceded != 1
            AND ss.statuscode >= ? AND ss.statuscode <= ?";
    $whereparams = array($facetofaceid, $userid, $minimumstatus, $maximumstatus);

    if (!empty($sessionid)) {
        $whereclause .= " AND s.id = ? ";
        $whereparams[] = $sessionid;
    }

    //TODO fix mailedconfirmation, timegraded, timecancelled, etc
    return $DB->get_records_sql("
        SELECT
            su.id,
            s.facetoface,
            s.id as sessionid,
            su.userid,
            0 as mailedconfirmation,
            su.discountcode,
            ss.timecreated,
            ss.timecreated as timegraded,
            s.timemodified,
            0 as timecancelled,
            su.notificationtype,
            ss.statuscode
        FROM
            {facetoface_sessions} s
        JOIN
            {facetoface_signups} su
         ON su.sessionid = s.id
        JOIN
            {facetoface_signups_status} ss
         ON su.id = ss.signupid
        WHERE
            {$whereclause}
        ORDER BY
            s.timecreated
    ", $whereparams);
}

/**
 * Cancel users' submission to a facetoface session
 *
 * @param integer $sessionid   ID of the facetoface_sessions record
 * @param integer $userid      ID of the user record
 * @param string $cancelreason Short justification for cancelling the signup
 * @return boolean success
 */
function facetoface_user_cancel_submission($sessionid, $userid, $cancelreason='') {
    global $DB, $USER;

    $signup = $DB->get_record('facetoface_signups', array('sessionid' => $sessionid, 'userid' => $userid));
    if (!$signup) {
        return true; // not signed up, nothing to do
    }

    $result = facetoface_update_signup_status($signup->id, MDL_F2F_STATUS_USER_CANCELLED, $USER->id, $cancelreason);

    if ($result) {
        // notify cancelled
        if (!$session = facetoface_get_session($sessionid)) {
            error_log('F2F: Could not load facetoface session');
            return false;
        }
        if (!$facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface))) {
            error_log('F2F: Could not load facetoface instance');
            return false;
        }
    }

    return $result;
}

/**
 * A list of actions in the logs that indicate view activity for participants
 */
function facetoface_get_view_actions() {
    return array('view', 'view all');
}

/**
 * A list of actions in the logs that indicate post activity for participants
 */
function facetoface_get_post_actions() {
    return array('cancel booking', 'signup');
}

/**
 * Return a small object with summary information about what a user
 * has done with a given particular instance of this module (for user
 * activity reports.)
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 */
function facetoface_user_outline($course, $user, $mod, $facetoface) {

    $result = new stdClass;

    $grade = facetoface_get_grade($user->id, $course->id, $facetoface->id);
    if ($grade->grade > 0) {
        $result = new stdClass;
        $result->info = get_string('grade') . ': ' . $grade->grade;
        $result->time = $grade->dategraded;
    }
    elseif ($submissions = facetoface_get_user_submissions($facetoface->id, $user->id)) {
        if ($facetoface->multiplesessions && (count($submissions) > 1) ) {
            $result->info = get_string('usersignedupmultiple', 'facetoface', count($submissions));
            $result->time = 0;
            foreach ($submissions as $submission) {
                if ($submission->timecreated > $result->time) {
                    $result->time = $submission->timecreated;
                }
            }
        } else {
            $result->info = get_string('usersignedup', 'facetoface');
            $result->time = reset($submissions)->timecreated;
        }
    }
    else {
        $result->info = get_string('usernotsignedup', 'facetoface');
    }

    return $result;
}

/**
 * Print a detailed representation of what a user has done with a
 * given particular instance of this module (for user activity
 * reports).
 */
function facetoface_user_complete($course, $user, $mod, $facetoface) {

    $grade = facetoface_get_grade($user->id, $course->id, $facetoface->id);

    if ($submissions = facetoface_get_user_submissions($facetoface->id, $user->id, MDL_F2F_STATUS_USER_CANCELLED, MDL_F2F_STATUS_FULLY_ATTENDED)) {
        print get_string('grade').': '.$grade->grade . html_writer::empty_tag('br');
        if ($grade->dategraded > 0) {
            $timegraded = trim(userdate($grade->dategraded, get_string('strftimedatetime')));
            print '('.format_string($timegraded).')'. html_writer::empty_tag('br');
        }
        echo html_writer::empty_tag('br');

        foreach ($submissions as $submission) {
            $timesignedup = trim(userdate($submission->timecreated, get_string('strftimedatetime')));
            print get_string('usersignedupon', 'facetoface', format_string($timesignedup)) . html_writer::empty_tag('br');

            if ($submission->timecancelled > 0) {
                $timecancelled = userdate($submission->timecancelled, get_string('strftimedatetime'));
                print get_string('usercancelledon', 'facetoface', format_string($timecancelled)) . html_writer::empty_tag('br');
            }
        }
    }
    else {
        print get_string('usernotsignedup', 'facetoface');
    }

    return true;
}

/**
 * Add a link to the session to the courses calendar.
 *
 * @param class   $session          Record from the facetoface_sessions table
 * @param class   $eventname        Name to display for this event
 * @param string  $calendartype     Which calendar to add the event to (user, course, site)
 * @param int     $userid           Optional param for user calendars
 * @param string  $eventtype        Optional param for user calendar (booking/session)
 */
function facetoface_add_session_to_calendar($session, $facetoface, $calendartype = 'none', $userid = 0, $eventtype = 'session') {
    global $CFG, $DB;

    if (empty($session->datetimeknown)) {
        return true; //date unkown, can't add to calendar
    }

    if (empty($facetoface->showoncalendar) && empty($facetoface->usercalentry)) {
        return true; //facetoface calendar settings prevent calendar
    }

    $description = '';
    if (!empty($facetoface->description)) {
        $description .= html_writer::tag('p', clean_param($facetoface->description, PARAM_CLEANHTML));
    }
    $description .= facetoface_print_session($session, false, true, true);
    $linkurl = new moodle_url('/mod/facetoface/signup.php', array('s' => $session->id));
    $linktext = get_string('signupforthissession', 'facetoface');

    if ($calendartype == 'site' && $facetoface->showoncalendar == F2F_CAL_SITE) {
        $courseid = SITEID;
        $description .= html_writer::link($linkurl, $linktext);
    } else if ($calendartype == 'course' && $facetoface->showoncalendar == F2F_CAL_COURSE) {
        $courseid = $facetoface->course;
        $description .= html_writer::link($linkurl, $linktext);
    } else if ($calendartype == 'user' && $facetoface->usercalentry) {
        $courseid = 0;
        $urlvar = ($eventtype == 'session') ? 'attendees' : 'signup';
        $linkurl = $CFG->wwwroot . "/mod/facetoface/" . $urlvar . ".php?s=$session->id";
        $description .= get_string("calendareventdescription{$eventtype}", 'facetoface', $linkurl);
    } else {
        return true;
    }

    $shortname = $facetoface->shortname;
    if (empty($shortname)) {
        $shortname = textlib::substr($facetoface->name, 0, CALENDAR_MAX_NAME_LENGTH);
    }

    $result = true;
    foreach ($session->sessiondates as $date) {
        $newevent = new stdClass();
        $newevent->name = $shortname;
        $newevent->description = $description;
        $newevent->format = FORMAT_HTML;
        $newevent->courseid = $courseid;
        $newevent->groupid = 0;
        $newevent->userid = $userid;
        $newevent->uuid = "{$session->id}";
        $newevent->instance = $session->facetoface;
        $newevent->modulename = 'facetoface';
        $newevent->eventtype = "facetoface{$eventtype}";
        $newevent->timestart = $date->timestart;
        $newevent->timeduration = $date->timefinish - $date->timestart;
        $newevent->visible = 1;
        $newevent->timemodified = time();

        if ($calendartype == 'user' && $eventtype == 'booking') {
            //Check for and Delete the 'created' calendar event to reduce multiple entries for the same event
            $DB->delete_records('event', array('userid' => $userid, 'instance' => $session->facetoface,
                                               'eventtype' => 'facetofacesession'));
        }

        $result = $result && $DB->insert_record('event', $newevent);
    }

    return $result;
}

/**
 * Remove all entries in the course calendar which relate to this session.
 *
 * @param class $session    Record from the facetoface_sessions table
 * @param integer $userid   ID of the user
 */
function facetoface_remove_session_from_calendar($session, $courseid = 0, $userid = 0) {
    global $DB;

    $params = array($session->facetoface, $userid, $courseid, $session->id);

    return $DB->delete_records_select('event', "modulename = 'facetoface' AND
                                                instance = ? AND
                                                userid = ? AND
                                                courseid = ? AND
                                                uuid = ?", $params);
}

/**
 * Update the date/time of events in the Moodle Calendar when a
 * session's dates are changed.
 *
 * @param class  $session    Record from the facetoface_sessions table
 * @param string $eventtype  Type of the event (booking or session)
 */
function facetoface_update_user_calendar_events($session, $eventtype) {
    global $DB;

    $facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface));

    if (empty($facetoface->usercalentry) || $facetoface->usercalentry == 0) {
        return true;
    }

    $users = facetoface_delete_user_calendar_events($session, $eventtype);

    // Add this session to these users' calendar
    foreach ($users as $user) {
        facetoface_add_session_to_calendar($session, $facetoface, 'user', $user->userid, $eventtype);
    }
    return true;
}

/**
 *Delete all user level calendar events for a face to face session
 *
 * @param class     $session    Record from the facetoface_sessions table
 * @param string    $eventtype  Type of the event (booking or session)
 */
function facetoface_delete_user_calendar_events($session, $eventtype) {
    global $CFG, $DB;

    $whereclause = "modulename = 'facetoface' AND
                    eventtype = 'facetoface$eventtype' AND
                    instance = ?";

    $whereparams = array($session->facetoface);

    if ('session' == $eventtype) {
        $likestr = "%attendees.php?s={$session->id}%";
        $like = $DB->sql_like('description', '?');
        $whereclause .= " AND $like";

        $whereparams[] = $likestr;
    }

    //users calendar
    $users = $DB->get_records_sql("SELECT DISTINCT userid
        FROM {event}
        WHERE $whereclause", $whereparams);

    if ($users && count($users) > 0) {
        // Delete the existing events
        $DB->delete_records_select('event', $whereclause, $whereparams);
    }

    return $users;
}

/**
 * Confirm that a user can be added to a session.
 *
 * @param class  $session Record from the facetoface_sessions table
 * @param object $context (optional) A context object (record from context table)
 * @return bool True if user can be added to session
 **/
function facetoface_session_has_capacity($session, $context = false, $status = MDL_F2F_STATUS_BOOKED) {

    if (empty($session)) {
        return false;
    }

    $signupcount = facetoface_get_num_attendees($session->id, $status);
    if ($signupcount >= $session->capacity) {
        // if session is full, check if overbooking is allowed for this user
        if (!$context || !has_capability('mod/facetoface:overbook', $context)) {
            return false;
        }
    }

    return true;
}

/**
 * Print the details of a session
 *
 * @param object $session         Record from facetoface_sessions
 * @param boolean $showcapacity   Show the capacity (true) or only the seats available (false)
 * @param boolean $calendaroutput Whether the output should be formatted for a calendar event
 * @param boolean $return         Whether to return (true) the html or print it directly (true)
 * @param boolean $hidesignup     Hide any messages relating to signing up
 */
function facetoface_print_session($session, $showcapacity, $calendaroutput=false, $return=false, $hidesignup=false) {
    global $CFG, $DB;

    $output = html_writer::start_tag('dl', array('class' => 'f2f'));

    $customfields = facetoface_get_session_customfields();
    $customdata = $DB->get_records('facetoface_session_data', array('sessionid' => $session->id), '', 'fieldid, data');
    foreach ($customfields as $field) {
        $data = '';
        if (!empty($customdata[$field->id])) {
            if (CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                $values = explode(CUSTOMFIELD_DELIMITER, format_string($customdata[$field->id]->data));
                $data = implode(html_writer::empty_tag('br'), $values);
            }
            else {
                $data = format_string($customdata[$field->id]->data);
            }
        }
        $output .= html_writer::tag('dt', str_replace(' ', '&nbsp;', format_string($field->name)));
        $output .= html_writer::tag('dd', $data);
    }

    $displaytimezones = get_config(null, 'facetoface_displaysessiontimezones');

    $strdatetime = str_replace(' ', '&nbsp;', get_string('sessiondatetime', 'facetoface'));
    if ($session->datetimeknown) {
        $html = '';
        foreach ($session->sessiondates as $date) {
            if (!empty($html)) {
                $html .= html_writer::empty_tag('br');
            }
            $sessionobj = facetoface_format_session_times($date->timestart, $date->timefinish, $date->sessiontimezone);
            if ($sessionobj->startdate == $sessionobj->enddate) {
                $html .= $sessionobj->startdate . ', ';
            } else {
                $html .= $sessionobj->startdate . ' - ' . $sessionobj->enddate . ', ';
            }

            $sessiontimezonestr = !empty($displaytimezones) ? $sessionobj->timezone : '';
            $html .= $sessionobj->starttime . ' - ' . $sessionobj->endtime . ' ' . $sessiontimezonestr;
        }
        $output .= html_writer::tag('dt', $strdatetime);
        $output .= html_writer::tag('dd', $html);
    } else {
        $output .= html_writer::tag('dt', $strdatetime);
        $output .= html_writer::tag('dd', html_writer::tag('em', get_string('wait-listed', 'facetoface')));
    }

    $signupcount = facetoface_get_num_attendees($session->id);
    $placesleft = $session->capacity - $signupcount;

    if ($showcapacity) {
        if ($session->allowoverbook) {
            $output .= html_writer::tag('dt', get_string('capacity', 'facetoface'));
            $output .= html_writer::tag('dd', get_string('capacityallowoverbook', 'facetoface', $session->capacity));
        } else {
            $output .= html_writer::tag('dt', get_string('capacity', 'facetoface'));
            $output .= html_writer::tag('dd', $session->capacity);
        }
    }
    elseif (!$calendaroutput) {
        $output .= html_writer::tag('dt', get_string('seatsavailable', 'facetoface'));
        $output .= html_writer::tag('dd', max(0, $placesleft));
    }

    // Display requires approval notification
    $facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface));

    if ($facetoface->approvalreqd) {
        $output .= html_writer::tag('dd', get_string('sessionrequiresmanagerapproval', 'facetoface'));
    }

    // Display waitlist notification
    if (!$hidesignup && $session->allowoverbook && $placesleft < 1) {
        $output .= html_writer::tag('dd', get_string('userwillbewaitlisted', 'facetoface'));
    }

    if (!empty($session->duration)) {
        $output .= html_writer::tag('dt', get_string('duration', 'facetoface'));
        $output .= html_writer::tag('dd', format_duration($session->duration));
    }

    // Display room information
    $session->room = $DB->get_record('facetoface_room', array('id' => $session->roomid));
    if (!empty($session->room)) {
        $roomstring = '';
        $roomstring = isset($session->room->name) ? format_string($session->room->name) . ', '. html_writer::empty_tag('br') : '';
        $roomstring .= isset($session->room->building) ? format_string($session->room->building) . ', ' . html_writer::empty_tag('br') : '';
        $roomstring .= isset($session->room->address) ? format_string($session->room->address) . html_writer::empty_tag('br') : '';

        $systemcontext = context_system::instance();
        $editoroptions = array(
            'noclean'  => false,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context'  => $systemcontext,
        );

        $session->room->descriptionformat = FORMAT_HTML;
        $session->room = file_prepare_standard_editor($session->room, 'description', $editoroptions, $systemcontext, 'facetoface', 'room', $session->room->id);

        $roomstring .= $session->room->description_editor['text'];

        $output .= html_writer::tag('dt', get_string('room', 'facetoface'));
        $output .= html_writer::tag('dd', $roomstring);
    }

    if (!empty($session->normalcost)) {
        $output .= html_writer::tag('dt', get_string('normalcost', 'facetoface'));
        $output .= html_writer::tag('dd', format_string($session->normalcost));
    }
    if (!empty($session->discountcost)) {
        $output .= html_writer::tag('dt', get_string('discountcost', 'facetoface'));
        $output .= html_writer::tag('dd', format_string($session->discountcost));
    }
    if (!empty($session->usernote)) {
        $output .= html_writer::tag('dt', get_string('usernote', 'facetoface'));
        $output .= html_writer::tag('dd', format_string($session->usernote));
    }

    // Display trainers.
    $courseid = $DB->get_field('facetoface', 'course', array('id' => $session->facetoface));
    $coursecontext = context_course::instance($courseid);

    if (!empty($session->details)) {
        if ($cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $courseid)) {
            $context = context_module::instance($cm->id);
            $session->details = file_rewrite_pluginfile_urls($session->details, 'pluginfile.php', $context->id, 'mod_facetoface', 'session', $session->id);
        }
        $details = format_text($session->details, FORMAT_HTML);
        $output .= html_writer::tag('dt', get_string('details', 'facetoface'));
        $output .= html_writer::tag('dd', $details);
    }

    $trainerroles = facetoface_get_trainer_roles($coursecontext);

    if ($trainerroles) {
        // Get trainers.
        $trainers = facetoface_get_trainers($session->id);

        foreach ($trainerroles as $role => $rolename) {
            $rolename = $rolename->localname;

            if (empty($trainers[$role])) {
                continue;
            }

            $trainer_names = array();
            foreach ($trainers[$role] as $trainer) {
                $trainer_url = new moodle_url('/user/view.php', array('id' => $trainer->id));
                $trainer_names[] = html_writer::link($trainer_url, fullname($trainer));
            }
            $output .= html_writer::tag('dt', $rolename);
            $output .= html_writer::tag('dd', implode(', ', $trainer_names));
        }
    }
    $output .= html_writer::end_tag('dl');

    return $output;
}

/**
 * Update the value of a customfield for the given session/notice.
 *
 * @param integer $fieldid    ID of a record from the facetoface_session_field table
 * @param string  $data       Value for that custom field
 * @param integer $otherid    ID of a record from the facetoface_(sessions|notice) table
 * @param string  $table      'session' or 'notice' (part of the table name)
 * @returns true if it succeeded, false otherwise
 */
function facetoface_save_customfield_value($fieldid, $data, $otherid, $table) {
    global $DB;

    $dbdata = null;
    if (is_array($data)) {
        $dbdata = trim(implode(CUSTOMFIELD_DELIMITER, $data), ';');
    }
    else {
        $dbdata = trim($data);
    }

    $newrecord = new stdClass();
    $newrecord->data = $dbdata;

    $fieldname = "{$table}id";
    if ($record = $DB->get_record("facetoface_{$table}_data", array('fieldid' => $fieldid, $fieldname => $otherid))) {
        if (empty($dbdata)) {
            // Clear out the existing value
            return $DB->delete_records("facetoface_{$table}_data", array('id' => $record->id));
        }

        $newrecord->id = $record->id;
        return $DB->update_record("facetoface_{$table}_data", $newrecord);
    }
    else {
        if (empty($dbdata)) {
            return true; // no need to store empty values
        }

        $newrecord->fieldid = $fieldid;
        $newrecord->$fieldname = $otherid;
        return $DB->insert_record("facetoface_{$table}_data", $newrecord);
    }
}

/**
 * Return the value of a customfield for the given session/notice.
 *
 * @param object  $field    A record from the facetoface_session_field table
 * @param integer $otherid  ID of a record from the facetoface_(sessions|notice) table
 * @param string  $table    'session' or 'notice' (part of the table name)
 * @returns string The data contained in this custom field (empty string if it doesn't exist)
 */
function facetoface_get_customfield_value($field, $otherid, $table) {
    global $DB;

    if ($record = $DB->get_record("facetoface_{$table}_data", array('fieldid' => $field->id, "{$table}id" => $otherid))) {
        if (!empty($record->data)) {
            if (CUSTOMFIELD_TYPE_MULTISELECT == $field->type) {
                return explode(CUSTOMFIELD_DELIMITER, $record->data);
            }
            return $record->data;
        }
    }
    return '';
}

/**
 * Return the values stored for all custom fields in the given session.
 *
 * @param integer $sessionid  ID of facetoface_sessions record
 * @returns array Indexed by field shortnames
 */
function facetoface_get_customfielddata($sessionid) {
    global $CFG, $DB;

    $sql = "SELECT f.shortname, d.data
              FROM {facetoface_session_field} f
              JOIN {facetoface_session_data} d ON f.id = d.fieldid
              WHERE d.sessionid = ?";

    $records = $DB->get_records_sql($sql, array($sessionid));

    return $records;
}

/**
 * Return a cached copy of all records in facetoface_session_field
 */
function facetoface_get_session_customfields() {
    global $DB;

    static $customfields = null;
    if (null == $customfields) {
        if (!$customfields = $DB->get_records('facetoface_session_field')) {
            $customfields = array();
        }
    }
    return $customfields;
}

function facetoface_update_trainers($facetoface, $session, $form) {
    global $DB;

    // If we recieved bad data
    if (!is_array($form)) {
        return false;
    }

    // Load current trainers
    $current_trainers = facetoface_get_trainers($session->id);
    // To collect trainers
    $new_trainers = array();
    $old_trainers = array();

    $transaction = $DB->start_delegated_transaction();

    // Loop through form data and add any new trainers
    foreach ($form as $roleid => $trainers) {

        // Loop through trainers in this role
        foreach ($trainers as $trainer) {

            if (!$trainer) {
                continue;
            }

            // If the trainer doesn't exist already, create it
            if (!isset($current_trainers[$roleid][$trainer])) {

                $newtrainer = new stdClass();
                $newtrainer->userid = $trainer;
                $newtrainer->roleid = $roleid;
                $newtrainer->sessionid = $session->id;
                $new_trainers[] = $newtrainer;

                if (!$DB->insert_record('facetoface_session_roles', $newtrainer)) {
                    print_error('error:couldnotaddtrainer', 'facetoface');
                    $transaction->force_transaction_rollback();
                    return false;
                }
            } else {
                unset($current_trainers[$roleid][$trainer]);
            }
        }
    }

    // Loop through what is left of old trainers, and remove
    // (as they have been deselected)
    if ($current_trainers) {
        foreach ($current_trainers as $roleid => $trainers) {
            // If no trainers left
            if (empty($trainers)) {
                continue;
            }

            // Delete any remaining trainers
            foreach ($trainers as $trainer) {
                $old_trainers[] = $trainer;
                if (!$DB->delete_records('facetoface_session_roles', array('sessionid' => $session->id, 'roleid' => $roleid, 'userid' => $trainer->id))) {
                    print_error('error:couldnotdeletetrainer', 'facetoface');
                    $transaction->force_transaction_rollback();
                    return false;
                }
            }
        }
    }

    $transaction->allow_commit();

    // Send a confirmation notice to new trainer
    foreach ($new_trainers as $i => $trainer) {
        facetoface_send_trainer_confirmation_notice($facetoface, $session, $trainer->userid);
    }

    // Send an unassignment notice to old trainer
    foreach ($old_trainers as $i => $trainer) {
        facetoface_send_trainer_session_unassignment_notice($facetoface, $session, $trainer->id);
    }

    return true;
}


/**
 * Return array of trainer roles configured for face-to-face
 * @param $context context of the course or activity
 * @return  array
 */
function facetoface_get_trainer_roles($context) {
    global $CFG, $DB;

    // Check that roles have been selected
    if (empty($CFG->facetoface_session_roles)) {
        return false;
    }

    if ($context) {
        $coursecontext = $context->get_course_context(false);
        if (!$coursecontext) {
            debugging('Invalid context specified as facetoface_get_trainer_roles() parameter');
            return array();
        }
    } else {
        $coursecontext = null;
    }

    // Parse roles
    $cleanroles = clean_param($CFG->facetoface_session_roles, PARAM_SEQUENCE);
    list($rolesql, $params) = $DB->get_in_or_equal(explode(',', $cleanroles));

    // Load role names
    $rolenames = $DB->get_records_sql("
        SELECT
            r.id,
            r.name
        FROM
            {role} r
        WHERE
            r.id {$rolesql}
        AND r.id <> 0
    ", $params);

    // Return roles and names
    if (!$rolenames) {
        return array();
    }

    $rolenames = role_fix_names($rolenames, $coursecontext);

    return $rolenames;
}


/**
 * Get all trainers associated with a session, optionally
 * restricted to a certain roleid
 *
 * If a roleid is not specified, will return a multi-dimensional
 * array keyed by roleids, with an array of the chosen roles
 * for each role
 *
 * @param   integer     $sessionid
 * @param   integer     $roleid (optional)
 * @return  array
 */
function facetoface_get_trainers($sessionid, $roleid = null) {
    global $CFG, $DB;

    $usernamefields = get_all_user_name_fields(true, 'u');
    $sql = "
        SELECT
            u.id,
            {$usernamefields},
            r.roleid
        FROM
            {facetoface_session_roles} r
        LEFT JOIN
            {user} u
         ON u.id = r.userid
        WHERE
            r.sessionid = ?
        ";
    $params = array($sessionid);

    if ($roleid) {
        $sql .= "AND r.roleid = ?";
        $params[] = $roleid;
    }

    $rs = $DB->get_recordset_sql($sql , $params);
    $return = array();
    foreach ($rs as $record) {
        // Create new array for this role
        if (!isset($return[$record->roleid])) {
            $return[$record->roleid] = array();
        }
        $return[$record->roleid][$record->id] = $record;
    }
    $rs->close();

    // If we are only after one roleid
    if ($roleid) {
        if (empty($return[$roleid])) {
            return false;
        }
        return $return[$roleid];
    }

    // If we are after all roles
    if (empty($return)) {
        return false;
    }

    return $return;
}

/**
 * Determines whether an activity requires the user to have a manager (either for
 * manager approval or to send notices to the manager)
 *
 * @param  object $facetoface A database fieldset object for the facetoface activity
 * @return boolean whether a person needs a manager to sign up for that activity
 */
function facetoface_manager_needed($facetoface){
    return $facetoface->approvalreqd
        || (isset($facetoface->confirmationinstrmngr) && !empty($facetoface->confirmationinstrmngr))
        || (isset($facetoface->reminderinstrmngr) && !empty($facetoface->reminderinstrmngr))
        || (isset($facetoface->cancellationinstrmngr) && !empty($facetoface->cancellationinstrmngr));
}

/**
 * Determines whether a session has the self-approval option
 *
 * @param  object $facetoface A database fieldset object for the facetoface activity
 * @param  object $session    A database fieldset object for the facetoface session
 * @return boolean whether a person needs a manager to sign up for that activity
 */
function facetoface_session_has_selfapproval($facetoface, $session) {
    return $facetoface->approvalreqd && $session->selfapproval;
}

/**
 * Add formslib fields for all custom fields defined site-wide.
 * (used by the session add/edit page and the site notices)
 */
function facetoface_add_customfields_to_form(&$mform, $customfields, $alloptional=false) {
    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";

        $options = array();
        if (!$field->required || $field->type == CUSTOMFIELD_TYPE_SELECT) {
            $options[''] = get_string('none');
        }
        foreach (explode(CUSTOMFIELD_DELIMITER, $field->possiblevalues) as $value) {
            $v = trim($value);
            if (!empty($v)) {
                $options[$v] = $v;
            }
        }

        switch ($field->type) {
        case CUSTOMFIELD_TYPE_TEXT:
            $mform->addElement('text', $fieldname, $field->name);
            break;
        case CUSTOMFIELD_TYPE_SELECT:
            $mform->addElement('select', $fieldname, $field->name, $options);
            break;
        case CUSTOMFIELD_TYPE_MULTISELECT:
            $select = &$mform->addElement('select', $fieldname, $field->name, $options);
            $select->setMultiple(true);
            break;
        default:
            error_log("facetoface: invalid field type for custom field ID $field->id");
            continue;
        }

        $mform->setType($fieldname, PARAM_TEXT);
        $mform->setDefault($fieldname, $field->defaultvalue);
        if ($field->required and !$alloptional) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
    }
}


/**
 * Get session cancellations
 *
 * @access  public
 * @param   integer $sessionid
 * @return  array
 */
function facetoface_get_cancellations($sessionid) {
    global $CFG, $DB;

    $usernamefields = get_all_user_name_fields(true, 'u');

    $instatus = array(MDL_F2F_STATUS_BOOKED, MDL_F2F_STATUS_WAITLISTED, MDL_F2F_STATUS_REQUESTED);
    list($insql, $inparams) = $DB->get_in_or_equal($instatus);
    // Nasty SQL follows:
    // Load currently cancelled users,
    // include most recent booked/waitlisted time also
    $sql = "
            SELECT
                u.id,
                su.id AS signupid,
                {$usernamefields},
                p.fullname as positionname,
                pa.fullname as positionassignmentname,
                pa.type as positiontype,
                MAX(ss.timecreated) AS timesignedup,
                c.timecreated AS timecancelled,
                " . $DB->sql_compare_text('c.note', 255) . " AS cancelreason
            FROM
                {facetoface_signups} su
            JOIN
                {user} u
             ON u.id = su.userid
            JOIN
                {facetoface_signups_status} c
             ON su.id = c.signupid
            AND c.statuscode = ?
            AND c.superceded = 0
            LEFT JOIN
                {facetoface_signups_status} ss
             ON su.id = ss.signupid
             AND ss.statuscode $insql
             AND ss.superceded = 1
           LEFT JOIN
             {pos} p
             ON p.id = su.positionid
           LEFT JOIN
             {pos_assignment} pa
             ON pa.id = su.positionassignmentid
            WHERE
                su.sessionid = ?
            GROUP BY
                su.id,
                u.id,
                {$usernamefields},
                c.timecreated,
                " . $DB->sql_compare_text('c.note', 255) . ",
                p.fullname,
                pa.type,
                pa.fullname
            ORDER BY
                " . $DB->sql_fullname('u.firstname', 'u.lastname') . ",
                c.timecreated
    ";
    $params = array_merge(array(MDL_F2F_STATUS_USER_CANCELLED), $inparams);
    $params[] = $sessionid;
    return $DB->get_records_sql($sql, $params);
}


/**
 * Get session unapproved requests
 *
 * @access  public
 * @param   integer $sessionid
 * @return  array|false
 */
function facetoface_get_requests($sessionid) {
    $usernamefields = get_all_user_name_fields(true, 'u');

    $select = "u.id, su.id AS signupid, ss.note as usernote, {$usernamefields}, u.email,
        ss.statuscode, ss.timecreated AS timerequested";

    return facetoface_get_users_by_status($sessionid, MDL_F2F_STATUS_REQUESTED, $select);
}

/**
 * Get session attendees by status
 *
 * @access  public
 * @param   integer $sessionid
 * @param   mixed   $status     Integer or array of integers
 * @param   string  $select     SELECT clause
 * @param   bool    $includereserved   optional - include 'reserved' users (note this will change the array index
 *                              to be the signupid, to avoid duplicate id problems).
 * @return  array|false
 */
function facetoface_get_users_by_status($sessionid, $status, $select = '', $includereserved = false) {
    global $DB;

    // If no select SQL supplied, use default
    if (!$select) {
        $usernamefields = get_all_user_name_fields(true, 'u');
        $select = "u.id, su.id AS signupid, {$usernamefields}, ss.timecreated, u.email";
        if ($includereserved) {
            $select = "su.id, ".$select;
        }
    }
    $userjoin = 'JOIN';
    if ($includereserved) {
        $userjoin = 'LEFT JOIN';
    }

    // Make string from array of statuses
    if (is_array($status)) {
        $status = implode(',', $status);
    }

    $sql = "
        SELECT {$select}
          FROM {facetoface_signups} su
          JOIN {facetoface_signups_status} ss ON su.id = ss.signupid
          $userjoin {user} u ON u.id = su.userid
         WHERE su.sessionid = ? AND ss.superceded != 1
           AND ss.statuscode = ?
         ORDER BY " . $DB->sql_fullname('u.firstname', 'u.lastname') . ", ss.timecreated
    ";

    return $DB->get_records_sql($sql, array($sessionid, $status));
}


/**
 * Returns all other caps used in module
 * @return array
 */
function facetoface_get_extra_capabilities() {
    return array('moodle/site:viewfullnames');
}


/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function facetoface_supports($feature) {
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_ARCHIVE_COMPLETION:      return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;

        default: return null;
    }
}

class facetoface_event_handler {

    /**
     * Event that is triggered when a user is deleted.
     *
     * Cancels a user from any future sessions when they are deleted
     * this is to make sure deleted users aren't using space in sessions
     * when there is limited capacity.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB;

        $userid = $event->objectid;
        if ($signups = $DB->get_records('facetoface_signups', array('userid' => $userid))) {
            foreach ($signups as $signup) {
                $session = facetoface_get_session($signup->sessionid);
                // Using $null, null fails because of passing by reference.
                facetoface_user_cancel($session, $userid, false, $null, get_string('userdeletedcancel', 'facetoface'));
            }
        }
        return true;
    }

    /**
     * Event that is triggered when a user is suspended.
     *
     * Cancels a user from any future sessions when they are suspended
     * this is to make sure suspended users aren't using space in sessions
     * when there is limited capacity.
     *
     * @param \totara_core\event\user_suspended $event
     */
    public static function user_suspended(\totara_core\event\user_suspended $event) {
        global $DB;

        $userid = $event->objectid;

        if ($signups = $DB->get_records('facetoface_signups', array('userid' => $userid))) {
            foreach ($signups as $signup) {
                $session = facetoface_get_session($signup->sessionid);
                // Using $null, null fails because of passing by reference.
                facetoface_user_cancel($session, $userid, false, $null, get_string('usersuspendedcancel', 'facetoface'));
            }
        }
        return true;
    }

    /**
     * Event that is triggered when a user is unenrolled from a course
     *
     * Cancels a user from any future sessions when they are unenrolled from a course,
     * this is to make sure unenrolled users aren't using space in sessions
     * when there is limited capacity
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_unenrolled(\core\event\user_enrolment_deleted $event) {
        global $DB;

        $uid = $event->relateduserid;
        $cid = $event->courseid;

        // Get all the facetofaces associated with the course.
        $f2fs = $DB->get_fieldset_select('facetoface', 'id', 'course = :cid', array('cid' => $cid));

        if (!empty($f2fs)) {
            // Get all the sessions for the facetofaces.
            list($insql, $inparams) = $DB->get_in_or_equal($f2fs);
            $sql = "SELECT id FROM {facetoface_sessions} WHERE facetoface {$insql}";
            $sessids = $DB->get_fieldset_sql($sql, $inparams);
            $strvar = new stdClass();
            $strvar->coursename = $DB->get_field('course', 'fullname', array('id' => $cid));

            foreach ($sessids as $sessid) {
                // Check if user is enrolled on any sessions in the future.
                if ($user = facetoface_get_attendee($sessid, $uid)) {
                    if (empty($strvar->username)) {
                        $strvar->username = fullname($user);
                    }

                    // And cancel them.
                    $sess = facetoface_get_session($sessid); // Get the proper session object, complete with dates.
                    facetoface_user_cancel($sess, $uid, false, $null, get_string('cancellationreasoncourseunenrollment', 'mod_facetoface', $strvar));
                }
            }
        }

        return true;
    }
}

/**
 * Called when displaying facetoface Task to check
 * capacity of the session.
 *
 * @param array Message data for a facetoface task
 * @return bool True if there is capacity in the session
 */
function facetoface_task_check_capacity($data) {
    $session = $data['session'];
    // Get session from database in case it has been updated
    $session = facetoface_get_session($session->id);
    if (!$session) {
        return false;
    }
    $facetoface = $data['facetoface'];

    if (!$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $facetoface->course)) {
        print_error('error:incorrectcoursemodule', 'facetoface');
    }
    $contextmodule = context_module::instance($cm->id);

    return (facetoface_session_has_capacity($session, $contextmodule) || $session->allowoverbook);
}


/**
 * Get available rooms for the specified time period
 *
 * Available rooms are rooms where the start- OR end times don't fall within that of another session's room,
 * as well as rooms where the start- AND end times don't encapsulate that of another session's room
 *
 * @param array $timeslots array of [timestart, timefinish] arrays
 * @param string $fields db fields for which data should be retrieved
 * @param array $excludesessions array of sessionids to exclude in availability checking
 * @return array rooms
 */
function facetoface_get_available_rooms($timeslots=array(), $fields='*', $excludesessions=array()) {
    global $DB;

    // Allow to have a room conflict, where type != 'external'
    $sqlwhere = "type != 'external' AND custom = 0 ";
    $params = array();
    $timeslotsql = array();
    $timeslotparams = array();
    foreach ($timeslots as $t) {
        $timestart = $t[0];
        $timefinish = $t[1];
        $timeslotsql[] = " (? > d.timestart AND d.timefinish > ?)";
        $timeslotparams = array_merge($timeslotparams, array($timefinish, $timestart));
    }

    if (!empty($timeslotsql)) {
        $sqlwhere .= 'AND ('.implode(' OR ', $timeslotsql).') ';
        $params = array_merge($params, $timeslotparams);
    }

    if (!empty($excludesessions)) {
        list($insql, $inparams) = $DB->get_in_or_equal($excludesessions, SQL_PARAMS_QM, 'param', false);
        $sqlwhere .= " AND s.id {$insql} ";
        $params = array_merge($params, $inparams);
    }

    //$sqlwhere .= !empty($timeslotsql) ? ' AND ('.implode(' OR ', $timeslotsql).') ' : '';

    $sql = "SELECT {$fields}
        FROM {facetoface_room}
        WHERE custom = 0
        AND id NOT IN
        (
            SELECT DISTINCT r.id
            FROM {facetoface_sessions} s
            INNER JOIN {facetoface_room} r ON s.roomid = r.id
            INNER JOIN {facetoface_sessions_dates} d ON s.id = d.sessionid
            WHERE {$sqlwhere}
        )";
        return $DB->get_records_sql($sql, $params);
}


/**
 * Saves room when updating a session includes checks for collision
 * detection and if there is a custom room defined then creates the
 * custom room record.
 *
 * @param $sessionid int ID of session to save room for
 * @param $data stdClass Form data containing room information
 *      either predefined room id or data for new custom room
 * @param boolean
 */
function facetoface_save_session_room($sessionid, $data) {
    global $CFG, $DB;

    // Get session and date info
    $session = $DB->get_record('facetoface_sessions', array('id' => $sessionid));

    $todb = new stdClass;
    $todb->id = $sessionid;

    if (empty($data->customroom)) {
        // Pre-defined room
        if (!empty($data->pdroomid) && $data->pdroomid == $session->roomid) {
            // Same room, no need to update
            return true;
        } elseif (!empty($data->pdroomid)) {
            // Ensure room is available
            $sessiondates = $DB->get_records('facetoface_sessions_dates', array('sessionid' => $sessionid));
            $timeslots = array();
            foreach ($sessiondates as $d) {
                $timeslots = array($d->timestart, $d->timefinish);
            }
            if (!$availablerooms = facetoface_get_available_rooms($timeslots, 'id', array($sessionid))) {
                // No pre-defined rooms available!
                return false;
            }

            if (!in_array($data->pdroomid, array_keys($availablerooms))) {
                // Selected pre-defined room not available!
                return false;
            }
        }

        $todb->roomid = $data->pdroomid;
    } else {
        // Custom room
        $sql = "SELECT r.*
            FROM {facetoface_sessions} s
            INNER JOIN {facetoface_room} r ON s.roomid = r.id
            WHERE s.id = ? AND r.custom = 1";
        if (!$room = $DB->get_record_sql($sql, array($sessionid))) {
            // Create
            $room = new stdClass();
            $room->custom = 1;
            $room->name = $data->croomname;
            $room->building = $data->croombuilding;
            $room->address = $data->croomaddress;
            $room->capacity = $data->croomcapacity;
            $room->timecreated = time();
            $room->timemodified = $room->timecreated;

            $roomid = $DB->insert_record('facetoface_room', $room);

            $todb->roomid = $roomid;
        } else {
            // Update
            $room->name = $data->croomname;
            $room->custom = 1;
            $room->building = $data->croombuilding;
            $room->address = $data->croomaddress;
            $room->capacity = $data->croomcapacity;
            $room->timemodified = time();

            $DB->update_record('facetoface_room', $room);
        }
    }

    if (isset($todb->roomid)) {
        $DB->update_record('facetoface_sessions', $todb);
    }

    if (empty($data->customroom)) {
        // Purge potentially orphaned custom room
        $DB->delete_records('facetoface_room', array('custom' => 1, 'id' => $session->roomid));
    }
    return true;
}


/**
 * Get sessions the occur at least partly during time periods
 *
 * @access  public
 * @param   array   $times          Array of dates defining time periods
 * @param   integer $userid         Limit sessions to those affecting a user (optional)
 * @param   string  $extrawhere     Custom WHERE additions (optional)
 * @return  array
 */
function facetoface_get_sessions_within($times, $userid = null, $extrawhere = '', $extraparams = array()) {
    global $CFG, $DB;

    $params = array();
    $select = "
             SELECT d.id,
                    c.id AS courseid,
                    c.fullname AS coursename,
                    f.name,
                    f.id AS f2fid,
                    s.id AS sessionid,
                    d.timestart,
                    d.timefinish
    ";

    $source = "
              FROM {facetoface_sessions_dates} d
        INNER JOIN {facetoface_sessions} s ON s.id = d.sessionid
        INNER JOIN {facetoface} f ON f.id = s.facetoface
        INNER JOIN {course} c ON f.course = c.id
    ";

    $twhere = array();
    foreach ($times as $time) {
        $twhere[] = 'd.timefinish > ? AND d.timestart < ?';
        $params = array_merge($params, array($time->timestart, $time->timefinish));
    }

    if ($times) {
        $where = 'WHERE s.datetimeknown = 1 AND ((' . implode(') OR (', $twhere) . '))';
    }

    // If userid supplied, only return sessions they are waitlisted, booked or attendees, or
    // have been assigned a role in
    if ($userid) {
        $select .= ", ss.statuscode, sr.roleid";

        $source .= "
            LEFT JOIN {facetoface_signups} su
                   ON su.sessionid = s.id AND su.userid = {$userid}
            LEFT JOIN {facetoface_signups_status} ss
                   ON su.id = ss.signupid AND ss.superceded != 1
            LEFT JOIN {facetoface_session_roles} sr
                   ON sr.sessionid = s.id AND sr.userid = {$userid}
        ";

        $where .= ' AND ((ss.id IS NOT NULL AND ss.statuscode >= ?) OR sr.id IS NOT NULL)';
        $params[]  = MDL_F2F_STATUS_WAITLISTED;
    }

    $params = array_merge($params, $extraparams);
    $sessions = $DB->get_record_sql($select.$source.$where.$extrawhere, $params, IGNORE_MULTIPLE);

    return $sessions;
}


/**
 * Get session info and role description from get_sessions_within output
 *
 * @access  public
 * @param   object  $user     User this $info relates to
 * @param   object  $info     Single result from facetoface_get_sessions_within()
 * @return  string
 */
function facetoface_get_session_involvement($user, $info) {
    global $USER;

    // Data to pass to lang string
    $data = new object();

    // Session time data
    $data->timestart = userdate($info->timestart, get_string('strftimetime'));
    $data->timefinish = userdate($info->timefinish, get_string('strftimetime'));
    $data->datestart = userdate($info->timestart, get_string('strftimedate'));
    $data->datefinish = userdate($info->timefinish, get_string('strftimedate'));
    $data->datetimestart = userdate($info->timestart, get_string('strftimedatetime'));
    $data->datetimefinish = userdate($info->timefinish, get_string('strftimedatetime'));

    // Session name/link
    $data->session = html_writer::link(new moodle_url('/mod/facetoface/view.php', array('f' => $info->f2fid)), format_string($info->name));

    // User's participation
    if (!empty($info->roleid)) {
        // Load roles (and cache)
        static $roles;
        if (!isset($roles)) {
            $context = context_course::instance($info->courseid);
            $roles = role_get_names($context);
        }

        // Check if role exists
        if (!isset($roles[$info->roleid])) {
            print_error('error:rolenotfound');
        }

        $data->participation = format_string($roles[$info->roleid]->localname);
        $strkey = "error:userassigned";
    } else {
        $strkey = "error:userbooked";
    }

    // Check if start/finish on the same day
    $strkey .= "sessionconflict";

    if ($data->datestart == $data->datefinish) {
        $strkey .= "sameday";
    } else {
        $strkey .= "multiday";
    }

    if ($user->id == $USER->id) {
        $strkey .= "selfsignup";
    }

    $data->fullname = fullname($user);

    return get_string($strkey, 'facetoface', $data);
}


/**
 * Import user and signup to session
 *
 * @access  public
 * @param   object  $course             Record from the course table
 * @param   object  $facetoface         Record from the facetoface table
 * @param   object  $session            Session to signup user to
 * @param   mixed   $userid             User to signup (normally int)
 * @param   array   $params             Optional suppressemail, ignoreconflicts, bulkaddsource, discountcode, notificationtype, autoenrol
 *          boolean $suppressemail      Suppress notifications flag
 *          boolean $ignoreconflicts    Ignore booking conflicts flag
 *          string  $bulkaddsource      Flag to indicate if $userid is actually another field
 *          string  $discountcode       Optional A user may specify a discount code
 *          integer $notificationtype   Optional A user may choose the type of notifications they will receive
 *          boolean $autoenrol          Optional If user not enrolled on the course then enrols them manually (default true)
 * @return  array
 */
function facetoface_user_import($course, $facetoface, $session, $userid, $params = array()) {
    global $DB, $CFG, $USER;

    $result = array();
    $result['id'] = $userid;

    $suppressemail    = (isset($params['suppressemail'])    ? $params['suppressemail']    : false);
    $ignoreconflicts  = (isset($params['ignoreconflicts'])  ? $params['ignoreconflicts']  : false);
    $bulkaddsource    = (isset($params['bulkaddsource'])    ? $params['bulkaddsource']    : 'bulkaddsourceuserid');
    $discountcode     = (isset($params['discountcode'])     ? $params['discountcode']     : '');
    $notificationtype = (isset($params['notificationtype']) ? $params['notificationtype'] : MDL_F2F_BOTH);
    $usernote         = (isset($params['usernote'])         ? $params['usernote']         : '');
    $autoenrol        = (isset($params['autoenrol'])        ? $params['autoenrol']        : true);

    if (isset($params['approvalreqd'])) {
        // Overwrite default behaviour as bulkadd_* is requested
        $facetoface->approvalreqd = $params['approvalreqd'];
        $facetoface->ccmanager = (isset($params['ccmanager']) ? $params['ccmanager'] : 0);
    }

    // Check parameters.
    if ($bulkaddsource == 'bulkaddsourceuserid') {
        if (!is_int($userid) && !ctype_digit($userid)) {
            $result['name'] = '';
            $result['result'] = get_string('error:userimportuseridnotanint', 'facetoface', $userid);
            return $result;
        }
    }

    // Get user.
    switch ($bulkaddsource) {
        case 'bulkaddsourceuserid':
            $user = $DB->get_record('user', array('id' => $userid));
            break;
        case 'bulkaddsourceidnumber':
            $user = $DB->get_record('user', array('idnumber' => $userid));
            break;
        case 'bulkaddsourceusername':
            $user = $DB->get_record('user', array('username' => $userid));
            break;
    }
    if (!$user) {
        $result['name'] = '';
        $a = array('fieldname' => get_string($bulkaddsource, 'facetoface'), 'value' => $userid);
        $result['result'] = get_string('userdoesnotexist', 'facetoface', $a);
        return $result;
    }

    if ($user->deleted) {
        $result['name'] = fullname($user);
        $result['result'] = get_string('error:userdeleted', 'facetoface', fullname($user));
        return $result;
    }

    if ($user->suspended) {
        $result['name'] = fullname($user);
        $result['result'] = get_string('error:usersuspended', 'facetoface', fullname($user));
        return $result;
    }

    $result['name'] = fullname($user);

    if (isguestuser($user)) {
        $a = array('fieldname' => get_string($bulkaddsource, 'facetoface'), 'value' => $userid);
        $result['result'] = get_string('cannotsignupguest', 'facetoface', $a);
        return $result;
    }

    // Make sure that the user is enroled in the course
    $cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $course->id, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    if (!is_enrolled($context, $user) && $autoenrol) {

        $defaultlearnerrole = $DB->get_record('role', array('id' => $CFG->learnerroleid));

        if (!enrol_try_internal_enrol($course->id, $user->id, $defaultlearnerrole->id, time())) {
            $result['result'] = get_string('error:enrolmentfailed', 'facetoface', fullname($user));
            return $result;
        }
    }

    // Check if they are already signed up
    $minimumstatus = ($session->datetimeknown) ? MDL_F2F_STATUS_BOOKED : MDL_F2F_STATUS_REQUESTED;
    // If multiple sessions are allowed then just check against this session
    // Otherwise check against all sessions
    $multisessionid = ($facetoface->multiplesessions ? $session->id : null);
    if (facetoface_get_user_submissions($facetoface->id, $user->id, $minimumstatus, MDL_F2F_STATUS_FULLY_ATTENDED, $multisessionid)) {
        if ($user->id == $USER->id) {
            $result['result'] = get_string('error:addalreadysignedupattendeeaddself', 'facetoface');
        } else {
            $result['result'] = get_string('error:addalreadysignedupattendee', 'facetoface');
        }
        return $result;
    }

    $facetoface_allowwaitlisteveryone = get_config(null, 'facetoface_allowwaitlisteveryone');
    if ($session->waitlisteveryone && !empty($facetoface_allowwaitlisteveryone)) {
        $status = MDL_F2F_STATUS_WAITLISTED;
    } else if (!facetoface_session_has_capacity($session, $context)) {
        if ($session->allowoverbook) {
            $status = MDL_F2F_STATUS_WAITLISTED;
        } else {
            $result['result'] = get_string('full', 'facetoface');
            return $result;
        }
    }

    // Check if we are waitlisting or booking
    if ($session->datetimeknown) {
        if (!isset($status)) {
            $status = MDL_F2F_STATUS_BOOKED;
        }

        // Check if there are any date conflicts
        if (!$ignoreconflicts) {
            $dates = facetoface_get_session_dates($session->id);
            if ($availability = facetoface_get_sessions_within($dates, $user->id)) {
                $result['result'] = facetoface_get_session_involvement($user, $availability);
                $result['conflict'] = true;
                return $result;
            }
        }
    } else {
        $status = MDL_F2F_STATUS_WAITLISTED;
    }

    $positionassignment = null;

    $selectpositiononsignupglobal = get_config(null, 'facetoface_selectpositiononsignupglobal');
    if (!empty($selectpositiononsignupglobal) && !empty($facetoface->selectpositiononsignup)) {

        // Get the position assignment record while checking that it is applicable for this activity/session.
        if (!empty($params['positionassignment'])) {
            $positionassignmentid = $params['positionassignment'];
            $applicablepositionassignments = get_position_assignments(false, $userid);
            if (!empty($applicablepositionassignments[$positionassignmentid])) {
                $positionassignment = $applicablepositionassignments[$positionassignmentid];
            }
        }

        // If that didn't work or no positionassignmentid provided try defaulting.
        if (!$positionassignment) {
            $positionassignment = pos_get_most_primary_position_assignment($userid);
        }

        // If we still don't have a position and it's mandated then error.
        if (!$positionassignment && !empty($facetoface->forceselectposition)) {
            $result['result'] = get_string('error:nopositionselected', 'facetoface');
            $result['nogoodpos'] = true;
            return $result;
        }
    }

    // Finally attempt to enrol
    if (!facetoface_user_signup(
        $session,
        $facetoface,
        $course,
        $discountcode,
        $notificationtype,
        $status,
        $user->id,
        !$suppressemail,
        $usernote,
        $positionassignment)) {
            $result['result'] = get_string('error:addattendee', 'facetoface', fullname($user));
            return $result;
    }

    $result['result'] = true;
    return $result;
}

/**
 * Return message describing bulk import results
 *
 * @access  public
 * @param   array       $results
 * @param   string      $type
 * @return  string
 */
function facetoface_generate_bulk_result_notice($results, $type = 'bulkadd') {
    $added          = $results[0];
    $errors         = $results[1];
    $result_message = '';

    $dialogid = 'f2f-import-results';
    $noticeclass = ($added) ? 'addedattendees' : 'noaddedattendees';
    // Generate messages
    if ($errors) {
        $result_message .= '<div class="' . $noticeclass . ' notifyproblem">';
        $result_message .= get_string($type.'attendeeserror', 'facetoface') . ' - ';

        if (count($errors) == 1 && is_string($errors[0])) {
            $result_message .= $errors[0];
        } else {
            $result_message .= get_string('xerrorsencounteredduringimport', 'facetoface', count($errors));
            $result_message .= ' <a href="#" id="'.$dialogid.'">('.get_string('viewresults', 'facetoface').')</a>';
        }
        $result_message .= '</div>';
    }
    if ($added) {
        $result_message .= '<div class="' . $noticeclass . ' notifysuccess">';
        $result_message .= get_string($type.'attendeessuccess', 'facetoface') . ' - ';
        $result_message .= get_string('successfullyaddededitedxattendees', 'facetoface', count($added));
        $result_message .= ' <a href="#" id="'.$dialogid.'">('.get_string('viewresults', 'facetoface').')</a>';
        $result_message .= '</div>';
    }

    return $result_message;
}


/**
 * Check if signup has been selected via the takeattendance interface
 *
 * Data is stored in the session and updated via AJAX
 *
 * @access  public
 * @param   integer     $sessionid  Session ID
 * @param   object      $signup     Signup to check
 * @return  bool
 */
function facetoface_is_signup_selected($sessionid, $signup) {
    // Check to see if selected
    if (facetoface_get_selected_signups($sessionid, array($signup))) {
        return true;
    } else {
        return false;
    }
}


/**
 * Filtered list of selected signups
 *
 * @access  public
 * @param   integer     $sessionid  Session ID
 * @param   array       $signups    Array of signup objects
 * @return  array
 */
function facetoface_get_selected_signups($sessionid, $signups = false) {
    global $MDL_F2F_STATUS;

    // Check if the session is empty
    if (empty($_SESSION['f2f-selection'][$sessionid])) {
        return array();
    }

    // Get selection "rules"
    $rules = $_SESSION['f2f-selection'][$sessionid];

    foreach ($signups as $index => $signup) {
        // Get signup id (two possible locations)
        if (isset($signup->submissionid)) {
            $signupid = $signup->submissionid;
        } else {
            $signupid = $signup->id;
        }

        // Check if there is a specific rule for this signup
        if (isset($rules['attendee_'.$signupid])) {
            if ($rules['attendee_'.$signupid] == "true") {
                continue;
            } else {
                unset($signups[$index]);
                continue;
            }
        }

        // Check grouping rules
        if (!empty($rules['all'])) {
            continue;
        }

        // Check if there is a status specific group
        $statuscode = $MDL_F2F_STATUS[$signup->statuscode];
        if (!empty($rules[$statuscode])) {
            continue;
        }

        // If no checks
        unset($signups[$index]);
    }

    return $signups;
}


/**
 * Kohl's KW - WP06A - Google calendar integration
 *
 * If the unassigned user belongs to a course with an upcoming
 * face-to-face session and they are signed-up to attend, cancel
 * the sign-up (and trigger notification).
 */
function facetoface_eventhandler_role_unassigned($ra) {
    global $CFG, $USER, $DB;

    $now = time();

    $ctx = context::instance_by_id($ra->contextid);
    if ($ctx->contextlevel == CONTEXT_COURSE) {
        // get all face-to-face activites in the course
        $activities = $DB->get_records('facetoface', array('course' => $ctx->instanceid));
        if ($activities) {
            foreach ($activities as $facetoface) {
                // get all upcoming sessions for each face-to-face
                $sql = "SELECT s.id, s.facetoface, s.datetimeknown, s.capacity,
                               s.duration, d.timestart, d.timefinish
                        FROM {facetoface_sessions} s
                        JOIN {facetoface_sessions_dates} d ON s.id = d.sessionid
                        WHERE
                            s.facetoface = ? AND d.sessionid = s.id AND
                            (s.datetimeknown = 0 OR d.timestart > ?)
                        ORDER BY s.datetimeknown, d.timestart
                ";

                if ($sessions = $DB->get_records_sql($sql, array($facetoface->id, $now))) {
                    $cancelreason = "Unenrolled from course";
                    foreach ($sessions as $session) {
                        $session = facetoface_get_session($session->id); // load dates etc.

                        // remove trainer session assignments for user (if any exist)
                        if ($trainers = facetoface_get_trainers($session->id)) {
                            foreach ($trainers as $role_id => $users) {
                                foreach ($users as $user_id => $trainer) {
                                    if ($trainer->id == $ra->userid) {
                                        $form = $trainers;
                                        unset($form[$role_id][$user_id]); // remove trainer
                                        facetoface_update_trainers($session->id, $form);
                                        break;
                                    }
                                }
                            }
                        }

                        // cancel learner signup for user (if any exist)
                        $errorstr = '';
                        if (facetoface_user_cancel($session, $ra->userid, true, $errorstr, $cancelreason)) {
                            facetoface_send_cancellation_notice($facetoface, $session, $ra->userid);
                        }
                    }
                }
            }
        }
    } else if ($ctx->contextlevel == CONTEXT_PROGRAM) {
        // nothing to do (probably)
    }

    return true;
}


/**
 * Kohl's KW - WP06A - Google calendar integration
 *
 * If the unassigned user belongs to a course with an upcoming
 * face-to-face session and they are signed-up to attend, cancel
 * the sign-up (and trigger notification).
 */
function facetoface_eventhandler_role_unassigned_bulk($event) {
    global $CFG, $USER, $DB;

    $now = time();

    $tmptable = $event['tmptable'];
    $hascontextid = $event['hascontextid'];
    $hasuserid = $event['hasuserid'];
    $hasroleid = $event['hasroleid'];
    $enrol = $event['enrol'];

    // Nothing to do if there are no contexts or userids
    if (!$hascontextid || !$hasuserid) {
        return true;
    }

    $sql = "SELECT DISTINCT cx.id, cx.* from {context} cx inner join {{$tmptable}} t on cx.id=t.contextid where cx.contextlevel=";
    $ctxlist = $DB->get_records_sql($sql, array(CONTEXT_COURSE));
    if (!$ctxlist) {
        return true;
    }

    foreach ($ctxlist as $ctx) {

        // get all face-to-face activites in the course
        $activities = $DB->get_records('facetoface', array('course' => $ctx->instanceid));
        if ($activities) {
            foreach ($activities as $facetoface) {
                // get all upcoming sessions for each face-to-face
                $sql = "SELECT s.id, s.facetoface, s.datetimeknown, s.capacity,
                               s.duration, d.timestart, d.timefinish
                        FROM {facetoface_sessions} s
                        JOIN {facetoface_sessions_dates} d ON s.id = d.sessionid
                        WHERE
                            s.facetoface = ? AND d.sessionid = s.id AND
                            (s.datetimeknown = 0 OR d.timestart > ?)
                        ORDER BY s.datetimeknown, d.timestart
                ";

                if ($sessions = get_records_sql($sql, array($facetoface->id, $now))) {
                    $cancelreason = "Unenrolled from course";
                    foreach ($sessions as $session) {
                        $session = facetoface_get_session($session->id); // load dates etc.

                        // remove trainer session assignments for user (if any exist)
                        if ($trainers = facetoface_get_trainers($session->id)) {
                            foreach ($trainers as $role_id => $users) {
                                foreach ($users as $user_id => $trainer) {
                                    if ( record_exists($t, 'userid', $trainer->id, 'contextid', $ctx->id) ) {
                                        $form = $trainers;
                                        unset($form[$role_id][$user_id]); // remove trainer
                                        facetoface_update_trainers($session->id, $form);
                                        break;
                                    }
                                }
                            }
                        }

                        $signups = $DB->get_records_sql("SELECT DISTINCT t.userid FROM {{$tmptable}} t INNER JOIN {facetoface_signups} fs ON t.userid=fs.userid WHERE fs.sessionid=?", array($session->id));
                        if (!$signups) {
                            $signups = array();
                        }
                        foreach ($signups as $signup) {
                            // cancel learner signup for user (if any exist)
                            $errorstr = '';
                            if (facetoface_user_cancel($session, $signup->userid, true, $errorstr, $cancelreason)) {
                                facetoface_send_cancellation_notice($facetoface, $session, $signup->userid);
                            }
                        }
                    }
                }
            }
        }
    }

    return true;
}


/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $facetofacenode The node to add module settings to
 */
function facetoface_extend_settings_navigation(settings_navigation $settings, navigation_node $facetofacenode) {
    global $PAGE, $DB;

    $mode = optional_param('mode', '', PARAM_ALPHA);
    $hook = optional_param('hook', 'ALL', PARAM_CLEAN);

    $context = context_module::instance($PAGE->cm->id);
    if (has_capability('moodle/course:manageactivities', $context)) {
        $facetofacenode->add(get_string('notifications', 'facetoface'), new moodle_url('/mod/facetoface/notification/index.php', array('update' => $PAGE->cm->id)), navigation_node::TYPE_SETTING);
    }

    $facetoface = $DB->get_record('facetoface', array('id' => $PAGE->cm->instance), '*', MUST_EXIST);
    if ($facetoface->declareinterest && has_capability('mod/facetoface:viewinterestreport', $context)) {
        $facetofacenode->add(get_string('declareinterestreport', 'facetoface'), new moodle_url('/mod/facetoface/interestreport.php', array('facetofaceid' => $PAGE->cm->instance)), navigation_node::TYPE_SETTING);
    }
}


// Download functions for attendees tables
/** Download data in ODS format
  *
  * @param array $fields Array of column headings
  * @param string $datarows Array of data to populate table with
  * @param string $file Name of file for exportig
  * @return Returns the ODS file
 */
function facetoface_download_ods($fields, $datarows, $file=null) {
    global $CFG, $DB;

    require_once("$CFG->libdir/odslib.class.php");
    $filename = clean_filename($file . '.ods');

    header("Content-Type: application/download\n");
    header("Content-Disposition: attachment; filename=$filename");
    header("Expires: 0");
    header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
    header("Pragma: public");

    $workbook = new MoodleODSWorkbook('-');
    $workbook->send($filename);

    $worksheet = array();

    $worksheet[0] = $workbook->add_worksheet('');
    $row = 0;
    $col = 0;

    foreach ($fields as $field) {
        $worksheet[0]->write($row, $col, strip_tags($field));
        $col++;
    }
    $row++;

    $numfields = count($fields);

    foreach ($datarows as $record) {
        for($col=0; $col<$numfields; $col++) {
            if (isset($record[$col])) {
                $worksheet[0]->write($row, $col, html_entity_decode($record[$col], ENT_COMPAT, 'UTF-8'));
            }
        }
        $row++;
    }

    $workbook->close();
    die;
}


/** Download data in XLS format
  *
  * @param array $fields Array of column headings
  * @param string $datarows Array of data to populate table with
  * @param string $file Name of file for exportig
  * @return Returns the Excel file
  */
function facetoface_download_xls($fields, $datarows, $file=null) {
    global $CFG, $DB;

    require_once($CFG->libdir . '/excellib.class.php');

    $filename = clean_filename($file . '.xls');

    header("Content-Type: application/download\n");
    header("Content-Disposition: attachment; filename=$filename");
    header("Expires: 0");
    header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
    header("Pragma: public");

    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send($filename);

    $worksheet = array();

    $worksheet[0] = $workbook->add_worksheet('');
    $row = 0;
    $col = 0;

    foreach ($fields as $field) {
        $worksheet[0]->write($row, $col, strip_tags($field));
        $col++;
    }
    $row++;

    $numfields = count($fields);

    foreach ($datarows as $record) {
        for ($col=0; $col<$numfields; $col++) {
            $worksheet[0]->write($row, $col, html_entity_decode($record[$col], ENT_COMPAT, 'UTF-8'));
        }
        $row++;
    }

    $workbook->close();
    die;
}


/** Download data in CSV format
  *
  * @param array $fields Array of column headings
  * @param string $datarows Array of data to populate table with
  * @param string $file Name of file for exportig
  * @return Returns the CSV file
  */
function facetoface_download_csv($fields, $datarows, $file=null) {
    global $DB;

    $filename = clean_filename($file . '.csv');
    $csv = '';

    header("Content-Type: application/download\n");
    header("Content-Disposition: attachment; filename=$filename");
    header("Expires: 0");
    header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
    header("Pragma: public");

    $delimiter = get_string('listsep', 'langconfig');
    $encdelim  = '&#' . ord($delimiter) . ';';
    $row = array();
    foreach ($fields as $field) {
        $row[] = str_replace($delimiter, $encdelim, strip_tags($field));
    }

    $csv .= implode($delimiter, $row) . "\n";

    $numfields = count($fields);

    foreach ($datarows as $record) {
        $row = array();
        for ($j=0; $j<$numfields; $j++) {
            if (isset($record[$j])) {
                $row[] = html_entity_decode(str_replace($delimiter, $encdelim, $record[$j]), ENT_COMPAT, 'UTF-8');
            } else {
                $row[] = '';
            }
        }
        $csv .= implode($delimiter, $row)."\n";
    }

    echo $csv;
    die;
}

/**
 * Main calendar hook for filtering f2f events (if necessary)
 *
 * @param array $events from the events table
 * @uses $SESSION->calendarfacetofacefilter - contains an assoc array of filter fieldids and vals
 *
 * @return void
 */
function facetoface_filter_calendar_events(&$events) {
    global $CFG, $SESSION;

    if (empty($SESSION->calendarfacetofacefilter)) {
        return;
    }
    $filters = $SESSION->calendarfacetofacefilter;
    foreach ($events as $eid => $event) {
        $event = new calendar_event($event);
        if ($event->modulename != 'facetoface') {
            continue;
        }

        $cfield_vals = facetoface_get_customfielddata($event->uuid);

        foreach ($filters as $shortname => $fval) {
            if (empty($fval)) {  // ignore empty filters
                continue;
            }
            if (empty($cfield_vals[$shortname]->data)) {
                // no reason comparing empty values :D
                unset($events[$eid]);
                break;
            }
            if ($fval != $cfield_vals[$shortname]->data) {
                unset($events[$eid]);
                break;
            }
        }
    }
}

/**
 * Main calendar hook for settinging f2f calendar filters
 *
 * @uses $SESSION->calendarfacetofacefilter - initialises assoc array of filter fieldids and vals
 *
 * @return void
 */
function facetoface_calendar_set_filter() {
    global $SESSION;

    $fields = facetoface_get_customfield_filters();

    $SESSION->calendarfacetofacefilter = array();
    foreach ($fields as $f) {
        $SESSION->calendarfacetofacefilter[$f->shortname] = optional_param("field_{$f->shortname}", '', PARAM_TEXT);
    }
}

/**
 * Get custom field filters that are currently selected in facetoface settings
 *
 * @return array Array of objects if any filter is found, empty array otherwise
 */
function facetoface_get_customfield_filters() {
    global $DB;

    $fields = array();
    $calendarcustomfields = get_config(null, 'facetoface_calendarfilters');
    if ($calendarcustomfields) {
        $customfieldids = array();
        $calendarcustomfields = explode(',', $calendarcustomfields);
        foreach ($calendarcustomfields as $filterkey) {
            if (is_numeric($filterkey)) {
                $customfieldids[] = $filterkey;
            }
        }
        if (!empty($customfieldids)) {
            list($sessionfieldids, $params) = $DB->get_in_or_equal($customfieldids);
            $sql = "SELECT * FROM {facetoface_session_field} WHERE id $sessionfieldids";
            $fields = $DB->get_records_sql($sql, $params);
        }
    }

    return $fields;
}

/**
 * Get the room record for the specified session
 *
 * @param int $sessionid
 *
 * @return object the room record or false if no room found
 */
function facetoface_get_session_room($sessionid) {
    global $DB;

    $sql = "SELECT r.*
        FROM {facetoface_sessions} s
        JOIN {facetoface_room} r ON s.roomid = r.id
        WHERE s.id = ?";

    return $DB->get_record_sql($sql, array($sessionid));
}

/**
 * Serves the facetoface and sessions details.
 *
 * @param stdClass $course course object
 * @param cm_info $cm course module object
 * @param context $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function facetoface_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea !== 'session') {
        return false;
    }

    $sessionid = (int)array_shift($args);

    if (!$session = $DB->get_record('facetoface_sessions', array('id' => $sessionid))) {
        return false;
    }

    if (!$facetoface = $DB->get_record('facetoface', array('id' => $cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_facetoface/$filearea/$sessionid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 360, 0, true, $options);
}

/**
 * Removes grades and resets completion
 *
 * @global object $CFG
 * @global object $DB
 * @param int $userid
 * @param int $courseid
 * @return boolean
 */
function facetoface_archive_completion($userid, $courseid) {
    global $DB, $CFG;

    require_once($CFG->libdir . '/completionlib.php');

    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $completion = new completion_info($course);

    // All face to face with this course and user
    $sql = "SELECT f.*
            FROM {facetoface} f
            WHERE f.course = :courseid
            AND EXISTS (SELECT su.id
                        FROM {facetoface_sessions} s
                        JOIN {facetoface_signups} su ON su.sessionid = s.id AND su.userid = :userid
                        WHERE s.facetoface = f.id)";
    $facetofaces = $DB->get_records_sql($sql, array('courseid' => $courseid, 'userid' => $userid));
    foreach ($facetofaces as $facetoface) {
        // Add an archive flag
        $params = array('facetofaceid' => $facetoface->id, 'userid' => $userid, 'archived' => 1, 'archived2' => 1);
        $sql = "UPDATE {facetoface_signups}
                SET archived = :archived
                WHERE userid = :userid
                AND archived <> :archived2
                AND EXISTS (SELECT {facetoface_sessions}.id
                            FROM {facetoface_sessions}
                            WHERE {facetoface_sessions}.id = {facetoface_signups}.sessionid
                            AND {facetoface_sessions}.facetoface = :facetofaceid)";
        $DB->execute($sql, $params);

        // Reset the grades
        facetoface_update_grades($facetoface, $userid, true);

        // Set completion to incomplete
        // Reset viewed
        $course_module = get_coursemodule_from_instance('facetoface', $facetoface->id, $courseid);
        $completion->set_module_viewed_reset($course_module, $userid);
        // And reset completion, in case viewed is not a required condition
        $completion->update_state($course_module, COMPLETION_INCOMPLETE, $userid);
        $completion->invalidatecache($courseid, $userid, true);
    }
}

/**
 * Get attendance status
 */
function get_attendance_status() {
    global $MDL_F2F_STATUS;

    // Look for status fully_attended, partially_attended and no_show.
    $statusoptions = array();
    foreach ($MDL_F2F_STATUS as $key => $value) {
        if ($key <= MDL_F2F_STATUS_BOOKED) {
            continue;
        }
        $statusoptions[$key] = get_string('status_' . $value, 'facetoface');
    }

    return array_reverse($statusoptions, true);
}
/**
 * Displays a bulk actions selector
 */
function display_bulk_actions_picker() {
    global $OUTPUT;

    $status_options = get_attendance_status();
    unset($status_options[MDL_F2F_STATUS_NOT_SET]);
    $out = $OUTPUT->container_start('facetoface-bulk-actions-picker');
    $select = html_writer::select($status_options, 'bulkattendanceop', '',
        array('' => get_string('bulkactions', 'facetoface')), array('class' => 'bulkactions'));
    $label = get_string('mark_selected_as', 'facetoface');
    $error = get_string('selectoptionbefore', 'facetoface');
    $hidenlabel = html_writer::tag('span', $error, array('id' => 'selectoptionbefore', 'class' => 'hide error'));
    $out .= $label;
    $out .= $select;
    $out .= $hidenlabel;
    $out .= $OUTPUT->container_end();

    return $out;
}

/**
 * Count how many spaces the current user has reserved in the given face to face instance.
 * @param object $facetoface
 * @param int $managerid
 * @return array 'all' => total count; sessionid => session count
 */
function facetoface_count_reservations($facetoface, $managerid) {
    global $DB;
    static $reservations = array();

    if (!isset($reservations[$facetoface->id])) {
        $sql = 'SELECT s.id, COUNT(*) AS reservecount
                  FROM {facetoface_sessions} s
                  JOIN {facetoface_signups} su ON su.sessionid = s.id
                 WHERE s.facetoface = :facetofaceid AND su.bookedby = :userid AND su.userid = 0
                 GROUP BY s.id';
        $params = array('facetofaceid' => $facetoface->id, 'userid' => $managerid);
        $reservations[$facetoface->id] = $DB->get_records_sql_menu($sql, $params);
        $reservations[$facetoface->id]['all'] = array_sum($reservations[$facetoface->id]);
    }

    return $reservations[$facetoface->id];
}

/**
 * Count how many allocations the current user has made in the given face to face instance.
 * @param object $facetoface
 * @param int $managerid
 * @return array 'all' => total count; sessionid => session count
 */
function facetoface_count_allocations($facetoface, $managerid) {
    global $DB;
    static $allocations = array();

    if (!isset($allocations[$facetoface->id])) {
        $sql = 'SELECT s.id, COUNT(*) AS allocatecount
                  FROM {facetoface_sessions} s
                  JOIN {facetoface_signups} su ON su.sessionid = s.id
                  JOIN {facetoface_signups_status} sus ON sus.signupid = su.id AND sus.superceded = 0
                                                       AND sus.statuscode > :cancelled
                 WHERE s.facetoface = :facetofaceid AND su.bookedby = :userid AND su.userid <> 0
                 GROUP BY s.id';
        $params = array('facetofaceid' => $facetoface->id, 'userid' => $managerid, 'cancelled' => MDL_F2F_STATUS_USER_CANCELLED);
        $allocations[$facetoface->id] = $DB->get_records_sql_menu($sql, $params);
        $allocations[$facetoface->id]['all'] = array_sum($allocations[$facetoface->id]);
    }

    return $allocations[$facetoface->id];
}

/**
 * Returns details of whether or not the user can reserve or allocate spaces for their team.
 * Note - an exception is throw if the managerid is set to another user and the current user is missing the
 * 'reserveother' capability
 *
 * @param object $facetoface
 * @param object[] $sessions
 * @param context $context
 * @param int $managerid optional defaults to current user
 * @throws moodle_exception
 * @return array with values 'allocate' - array how many spare allocations there are, per sesion + 'all'
 *                                        (false if not able to allocate)
 *                           'allocated' - array how many spaces have been allocated by this manager, per session + 'all'
 *                           'maxallocate' - the maximum number of spaces this manager could allocate, per session + 'all'
 *                           'reserve' - array how many spare reservations there are, per session + 'all'
 *                                       (false if not able to reserve)
 *                           'reserved' - array how many spaces have been reserved by this manager, per session + 'all'
 *                           'maxreserve' - array the maximum number of spaces this manager could still allocate, per session + 'all'
 *                           'reservedeadline' - any sessions that start after this date are able to reserve places
 *                           'reservecancel' - any sessions that before this date will have all reservations deleted
 */
function facetoface_can_reserve_or_allocate($facetoface, $sessions, $context, $managerid = null) {
    global $USER;

    $reserveother = has_capability('mod/facetoface:reserveother', $context);
    if (!$managerid || $managerid == $USER->id) {
        $managerid = $USER->id;
    } else {
        if (!$reserveother) {
            throw new moodle_exception('cannotreserveother', 'mod_facetoface');
        }
    }

    $ret = array(
        'allocate' => false, 'allocated' => array('all' => 0), 'maxallocate' => array('all' => 0),
        'reserve' => false, 'reserved' => array('all' => 0), 'maxreserve' => array('all' => 0),
        'reservedeadline' => 0, 'reservecancel' => 0, 'reserveother' => false
    );
    if (!$facetoface->managerreserve) {
        return $ret; // Manager reservations disabled for this activity.
    }

    $ret['reserveother'] = $reserveother;
    $ret['reservedeadline'] = time() + ($facetoface->reservedays * DAYSECS);
    $ret['reservecancel'] = time() + ($facetoface->reservecanceldays * DAYSECS);

    if (!has_capability('mod/facetoface:reservespace', $context, $managerid)) {
        return $ret; // Manager is not allowed to reserve/allocate any spaces.
    }

    if (!totara_get_staff($managerid)) {
        return $ret; // No staff to allocate spaces to.
    }

    // Allowed to make allocations / reservations - gather some details about the spaces remaining.
    $allocations = facetoface_count_allocations($facetoface, $managerid);
    $reservations = facetoface_count_reservations($facetoface, $managerid);
    foreach ($sessions as $session) {
        if (!isset($allocations[$session->id])) {
            $allocations[$session->id] = 0;
        }
        if (!isset($reservations[$session->id])) {
            $reservations[$session->id] = 0;
        }
    }
    $ret['allocate'] = array();
    $ret['allocated'] = $allocations;
    $ret['maxallocate'] = array();
    $ret['reserve'] = array();
    $ret['reserved'] = $reservations;
    $ret['maxreserve'] = array();

    foreach ($allocations as $sid => $allocation) {
        $reservation = isset($reservations[$sid]) ? $reservations[$sid] : 0;
        // Max allocation = overall max - allocations for other sessions - reservations for other sessions.
        $ret['maxallocate'][$sid] = $facetoface->maxmanagerreserves - ($allocations['all'] - $allocation);
        $ret['maxallocate'][$sid] -= ($reservations['all'] - $reservation);
        $ret['allocate'][$sid] = $ret['maxallocate'][$sid] - $allocation; // Number left to allocate.

        // Max reservations = overall max - allocations (all) - reservations for other sessions
        $ret['maxreserve'][$sid] = $facetoface->maxmanagerreserves - $allocations['all'];
        $ret['maxreserve'][$sid] -= ($reservations['all'] - $reservation);
        $ret['reserve'][$sid] = $ret['maxreserve'][$sid] - $reservation; // Number left to reserve.

        // Make sure no values are < 0 (e.g. if the allocation limit has changed).
        $ret['maxallocate'][$sid] = max(0, $ret['maxallocate'][$sid]);
        $ret['allocate'][$sid] = max(0, $ret['allocate'][$sid]);
        $ret['maxreserve'][$sid] = max(0, $ret['maxreserve'][$sid]);
        $ret['reserve'][$sid] = max(0, $ret['reserve'][$sid]);
    }

    return $ret;
}

/**
 * Given the number of spaces the manager has reserved / allocated (from 'can_reserve_or_allocate')
 * and the overall remaining capacity of the particular session, work out how many spaces they can
 * actually reserve/allocate for this session.
 *
 * @param int $sessionid
 * @param array $reserveinfo
 * @param int $capacityleft
 * @return array - see facetoface_can_reserve_or_allocate for details
 */
function facetoface_limit_reserveinfo_to_capacity_left($sessionid, $reserveinfo, $capacityleft) {
    if (!empty($reserveinfo['reserve'])) {
        if ($reserveinfo['reserve'][$sessionid] > $capacityleft) {
            $reserveinfo['reserve'][$sessionid] = $capacityleft;
            $reserveinfo['maxreserve'][$sessionid] = $reserveinfo['reserve'][$sessionid] + $reserveinfo['reserved'][$sessionid];
        }
    }
    if (!empty($reserveinfo['allocate'])) {
        if ($reserveinfo['allocate'][$sessionid] > $capacityleft) {
            $reserveinfo['allocate'][$sessionid] = $capacityleft;
            $reserveinfo['maxallocate'][$sessionid] = $reserveinfo['allocate'][$sessionid] + $reserveinfo['allocated'][$sessionid];
        }
    }

    return $reserveinfo;
}

/**
 * Given the session details, determines if reservations are still allowed, or if the deadline has now passed.
 *
 * @param array $reserveinfo
 * @param object $session
 * @return array - see facetoface_can_reserve_or_allocate for details, but adds two new values:
 *                  'reservepastdeadline' - true if the deadline for adding new reservations has passed
 *                  'reservepastcancel' - true if all existing reservations should be cancelled
 */
function facetoface_limit_reserveinfo_by_session_date($reserveinfo, $session) {
    $reserveinfo['reservepastdeadline'] = false;
    $reserveinfo['reservepastcancel'] = false;
    if ($session->datetimeknown) {
        $firstdate = reset($session->sessiondates);
        if (!isset($reserveinfo['reservedeadline']) || $firstdate->timestart <= $reserveinfo['reservedeadline']) {
            $reserveinfo['reservepastdeadline'] = true;
        }
        if (!isset($reserveinfo['reservecancel']) || $firstdate->timestart <= $reserveinfo['reservecancel']) {
            $reserveinfo['reservepastcancel'] = true;
        }
    }

    return $reserveinfo;
}

/**
 * Add the number of reservations requested (it is assumed that all capacity checks have
 * already been done by this point, so no extra checking is performed).
 *
 * @param object $session the session the reservations are for
 * @param int $bookedby the user making the reservations
 * @param int $number how many reservations to make
 * @param int $waitlisted how many reservations to add to the waitlist (not included in $number)
 */
function facetoface_add_reservations($session, $bookedby, $number, $waitlisted) {
    global $DB;

    $usersignup = (object)array(
        'sessionid' => $session->id,
        'userid' => 0,
        'notificationtype' => MDL_F2F_NOTIFICATION_AUTO,
        'archived' => 0,
        'bookedby' => $bookedby,
    );

    for ($i=0; $i<($number+$waitlisted); $i++) {
        $usersignup->id = $DB->insert_record('facetoface_signups', $usersignup);
        if ($session->datetimeknown && ($i < $number)) {
            $status = MDL_F2F_STATUS_BOOKED;
        } else {
            $status = MDL_F2F_STATUS_WAITLISTED;
        }
        facetoface_update_signup_status($usersignup->id, $status, $bookedby);
    }

    facetoface_update_attendees($session);
}

/**
 * Remove the (up to) the given number of reservations originally made by the given user.
 *
 * @param object $facetoface
 * @param object $session the session to remove the reservations from
 * @param int $bookedby the user who made the original reservations
 * @param int $number the number of reservations to remove
 * @param bool $sendnotification
 */
function facetoface_remove_reservations($facetoface, $session, $bookedby, $number, $sendnotification = false) {
    global $DB;

    $sql = 'SELECT su.id
              FROM {facetoface_signups} su
              JOIN {facetoface_signups_status} sus ON sus.signupid = su.id AND sus.superceded = 0
             WHERE su.sessionid = :sessionid AND su.userid = 0 AND su.bookedby = :bookedby
             ORDER BY sus.statuscode ASC, id DESC';
    // Start by deleting low-status reservations (cancelled, waitlisted), then order by most recently booked.
    $params = array('sessionid' => $session->id, 'bookedby' => $bookedby);

    $reservations = $DB->get_records_sql($sql, $params, 0, $number);
    $removecount = count($reservations);
    foreach ($reservations as $reservation) {
        $DB->delete_records('facetoface_signups_status', array('signupid' => $reservation->id));
        $DB->delete_records('facetoface_signups', array('id' => $reservation->id));
    }

    if ($removecount && $sendnotification) {
        $params = array(
            'facetofaceid' => $facetoface->id,
            'type' => MDL_F2F_NOTIFICATION_AUTO,
            'conditiontype' => MDL_F2F_CONDITION_RESERVATION_CANCELLED,
        );
        facetoface_send_notice($facetoface, $session, $bookedby, $params);
    }

    facetoface_update_attendees($session);
}

/**
 * Replace the manager reservations for this session with allocations for the given userids.
 * The list of userids still to be allocated will be returned.
 * Note: There are no checks made to see if the given users have already booked on a session, etc. -
 * it is assumed that any such checks have been completed before calling this function.
 *
 * @param object $session
 * @param object $facetoface
 * @param object $course
 * @param int $bookedby
 * @param int[] $userids
 * @throws moodle_exception
 * @return int[]
 */
function facetoface_replace_reservations($session, $facetoface, $course, $bookedby, $userids) {
    global $DB, $CFG;

    $facetoface->approvalreqd = false; // Make sure they are directly signed-up.

    $sql = 'SELECT su.id, sus.statuscode, su.discountcode, su.notificationtype
              FROM {facetoface_signups} su
              JOIN {facetoface_signups_status} sus ON sus.signupid = su.id AND sus.superceded = 0
             WHERE su.sessionid = :sessionid AND su.userid = 0 AND su.bookedby = :bookedby
             ORDER BY sus.statuscode DESC, id DESC';
    // Prioritise allocating high-status reservations (booked) over lower-status reservations (waitinglist)
    $params = array('sessionid' => $session->id, 'bookedby' => $bookedby);
    $reservations = $DB->get_records_sql($sql, $params, 0, count($userids));

    foreach ($reservations as $reservation) {
        $userid = array_shift($userids);
        // Make sure that the user is enroled in the course
        $context = context_course::instance($course->id);
        if (!is_enrolled($context, $userid)) {
            $defaultlearnerrole = $DB->get_record('role', array('id' => $CFG->learnerroleid));
            if (!enrol_try_internal_enrol($course->id, $userid, $defaultlearnerrole->id, time())) {
                throw new moodle_exception('unabletoenrol', 'mod_facetoface');
            }
        }

        if ($oldbooking = $DB->get_record('facetoface_signups', array('sessionid' => $session->id, 'userid' => $userid))) {
            // This could happen if a user booked themselves, then cancelled and are now being allocated by their manager.

            // Delete the reservation completely.
            $DB->delete_records('facetoface_signups_status', array('signupid' => $reservation->id));
            $DB->delete_records('facetoface_signups', array('id' => $reservation->id));

            // Update the bookedby field.
            $DB->set_field('facetoface_signups', 'bookedby', $bookedby, array('id' => $oldbooking->id));

        } else {
            // Switch the booking over to the given user.
            $upd = (object)array(
                'id' => $reservation->id,
                'userid' => $userid,
                'sessionid' => $session->id,
            );
            $DB->update_record('facetoface_signups', $upd);
        }

        // Make sure the status is set and the correct notification messages are sent.
        facetoface_user_signup($session, $facetoface, $course, $reservation->discountcode, $reservation->notificationtype,
                               $reservation->statuscode, $userid);
    }

    return $userids;
}

/**
 * Allocate spaces to all the users specified.
 * Note: there are no checks done against the user's allocation limit.
 *
 * @param object $session
 * @param object $facetoface
 * @param object $course
 * @param int $bookedby
 * @param int[] $userids
 * @param int $capacityleft how much (non-waitlist) space there is left on the session
 * @throws moodle_exception
 */
function facetoface_allocate_spaces($session, $facetoface, $course, $bookedby, $userids, $capacityleft) {
    global $DB, $CFG;

    $facetoface->approvalreqd = false; // Make sure they are directly signed-up.

    foreach ($userids as $userid) {
        // Make sure that the user is enroled in the course
        $context = context_course::instance($course->id);
        if (!is_enrolled($context, $userid)) {
            $defaultlearnerrole = $DB->get_record('role', array('id' => $CFG->learnerroleid));
            if (!enrol_try_internal_enrol($course->id, $userid, $defaultlearnerrole->id, time())) {
                throw new moodle_exception('unabletoenrol', 'mod_facetoface');
            }
        }

        $status = MDL_F2F_STATUS_BOOKED;
        if ($capacityleft <= 0) {
            $status = MDL_F2F_STATUS_WAITLISTED;
        }

        // Make sure the status is set and the correct notification messages are sent.
        if (facetoface_user_signup($session, $facetoface, $course, null, MDL_F2F_NOTIFICATION_AUTO, $status, $userid)) {
            $DB->set_field('facetoface_signups', 'bookedby', $bookedby, array('sessionid' => $session->id, 'userid' => $userid));
        }
        $capacityleft--;
    }
}

/**
 * Remove the given allocations and, optionally, convert them back into reservations.
 *
 * @param object $session
 * @param object $facetoface
 * @param object $course
 * @param int[] $userids
 * @param bool $converttoreservations if true, convert allocations to reservations, if false, just cancel
 * @param int $managerid optional defaults to current user
 */
function facetoface_remove_allocations($session, $facetoface, $course, $userids, $converttoreservations, $managerid = null) {
    global $DB, $USER;

    if (!$managerid) {
        $managerid = $USER->id;
    }

    foreach ($userids as $userid) {
        if ($converttoreservations) {
            $DB->set_field('facetoface_signups', 'userid', 0, array('sessionid' => $session->id, 'userid' => $userid,
                                                                    'bookedby' => $managerid));
        } else {
            facetoface_user_cancel($session, $userid);
        }
    }
}

/**
 * Get a list of staff who can be allocated / deallocated + reasons why other users cannot be allocated.
 *
 * @param object $facetoface
 * @param object $session
 * @param int $managerid optional
 * @return object containing potential - list of users who could be allocated
 *                           current - list of users who are already allocated
 *                           othersession - users allocated to another sesssion
 *                           cannotunallocate - users who cannot be unallocated (also listed in 'current')
 */
function facetoface_get_staff_to_allocate($facetoface, $session, $managerid = null) {
    global $DB, $USER;

    if (!$managerid) {
        $managerid = $USER->id;
    }
    $ret = (object)array('potential' => array(), 'current' => array(), 'othersession' => array(), 'cannotunallocate' => array());
    if (!$staff = totara_get_staff($managerid)) {
        return $ret;
    }

    list($usql, $params) = $DB->get_in_or_equal($staff, SQL_PARAMS_NAMED);
    // Get list of sign-ups that already exist for these users.
    $sql = "SELECT u.*, su.sessionid, su.bookedby, b.firstname AS bookedbyfirstname, b.lastname AS bookedbylastname,
                   su.statuscode
              FROM {user} u
              LEFT JOIN (
                  SELECT xsu.sessionid, xsu.bookedby, xsu.userid, sus.statuscode
                    FROM {facetoface_signups} xsu
                    JOIN {facetoface_signups_status} sus ON sus.signupid = xsu.id AND sus.superceded = 0
                    JOIN {facetoface_sessions} s ON s.id = xsu.sessionid
                   WHERE s.facetoface = :facetofaceid AND sus.statuscode > :status
              ) su ON su.userid = u.id
              LEFT JOIN {user} b ON b.id = su.bookedby
             WHERE u.id $usql";

    $params['facetofaceid'] = $facetoface->id;
    // Statuses greater than declined to handle cases where people change their mind.
    $params['status'] = MDL_F2F_STATUS_DECLINED;
    $users = $DB->get_records_sql($sql, $params);

    foreach ($users as $user) {
        if (!$user->sessionid) {
            // User has no bookings for this activity => potentially can be allocated.
            $ret->potential[$user->id] = $user;
        } else {
            if ($user->bookedby != $managerid) {
                if ($user->bookedby == 0) {
                    $user->cannotremove = 'selfbooked';
                } else {
                    $user->cannotremove = 'otherbookedby'; // Booked by someone else - cannot be unbooked.
                }
                $ret->cannotunallocate[$user->id] = $user;
            }

            if ($user->sessionid != $session->id) {
                // Allocated to a different session - cannot be booked/unbooked.
                $ret->othersession[$user->id] = $user;
            } else {
                if (empty($user->cannotremove) && $user->statuscode && $user->statuscode > MDL_F2F_STATUS_BOOKED) {
                    $user->cannotremove = 'attendancetaken'; // Attendance taken - cannot be unbooked.
                    $ret->cannotunallocate[$user->id] = $user;
                }
                $ret->current[$user->id] = $user;
            }
        }
    }

    return $ret;
}

/**
 * Get a full list of all managers on the system.
 *
 * @return array
 */
function facetoface_get_manager_list() {
    global $CFG, $DB;

    $ret = array();


    $usernamefields = get_all_user_name_fields(true, 'u');
    $sql = "SELECT u.id, {$usernamefields}
              FROM {pos_assignment} pa
              JOIN {user} u ON u.id = pa.managerid
             GROUP BY u.id, u.firstname, u.lastname
             ORDER BY u.lastname, u.firstname";
    $managers = $DB->get_records_sql($sql);
    foreach ($managers as $manager) {
        $ret[$manager->id] = fullname($manager);
    }

    if (!empty($CFG->enabletempmanagers)) {
        $sql = "SELECT u.id, {$usernamefields}
                  FROM {temporary_manager} tm
                  JOIN {user} u ON u.id = tm.tempmanagerid
                 WHERE tm.expirytime > ?
                 GROUP BY u.id, u.firstname, u.lastname
                 ORDER BY u.lastname, u.firstname";
        $params = array(time());
        $tempmanagers = $DB->get_records_sql($sql, $params);
        foreach ($tempmanagers as $tempmanager) {
            $ret[$tempmanager->id] = fullname($tempmanager);
        }
    }

    return $ret;
}

/**
 * Returns the details of all the other reservations made in the current face to face
 * by the given manager
 *
 * @param object $facetoface
 * @param object $session
 * @param int $managerid
 * @return object[]
 */
function facetoface_get_other_reservations($facetoface, $session, $managerid) {
    global $DB;

    // Get a list of all the bookings the manager has made (not including the current session).
    $sql = "SELECT su.id, s.id AS sessionid, s.datetimeknown, u.id AS userid, u.firstname, u.lastname
              FROM {facetoface_signups} su
              JOIN {facetoface_sessions} s ON s.id = su.sessionid
              JOIN {facetoface_signups_status} sus ON sus.signupid = su.id AND sus.superceded = 0
                                                   AND sus.statuscode > :cancelled
              LEFT JOIN {user} u ON u.id = su.userid
             WHERE su.bookedby = :managerid AND su.sessionid <> :sessionid AND s.facetoface = :facetofaceid
             ORDER BY s.id";
    $params = array('managerid' => $managerid, 'sessionid' => $session->id, 'facetofaceid' => $facetoface->id,
                    'cancelled' => MDL_F2F_STATUS_USER_CANCELLED);

    return $DB->get_records_sql($sql, $params);
}

/**
 * Format the dates for the given session, when listing the other bookings made by a given manager
 * in a particular face to face instance.
 *
 * @param $session
 * @return string
 */
function facetoface_format_session_dates($session) {
    if ($session->datetimeknown) {
        $formatteddates = array();
        foreach ($session->sessiondates as $date) {
            $formatteddate = '';
            $sessionobj = facetoface_format_session_times($date->timestart, $date->timefinish, $date->sessiontimezone);
            if ($sessionobj->startdate == $sessionobj->enddate) {
                $formatteddate .= $sessionobj->startdate . ', ';
            } else {
                $formatteddate .= $sessionobj->startdate . ' - ' . $sessionobj->enddate . ', ';
            }
            $formatteddate .= $sessionobj->starttime . ' - ' . $sessionobj->endtime . ' ' . $sessionobj->timezone;
            $formatteddates[] = $formatteddate;
        }
        $formatteddates = '<li>'.implode('</li><li>', $formatteddates).'</li>';
        $ret = html_writer::tag('ul', $formatteddates);
    } else {
        $ret = html_writer::tag('em', get_string('wait-listed', 'facetoface'));
    }
    return $ret;
}

/**
 * Get the relevant session rooms for a facetoface activity
 *
 * @param int $facetofaceid
 *
 * @return array containing facetoface_room table db objects
 */
function facetoface_get_rooms($facetofaceid) {
    global $DB;

    $sql = "SELECT DISTINCT r.id, r.name, r.building, r.address
        FROM {facetoface_sessions} s
        JOIN {facetoface_room} r ON s.roomid = r.id
        WHERE s.facetoface = ?";

    return $DB->get_records_sql($sql, array($facetofaceid));
}

/**
 * Determines whether the user has already expressed interest in this activity.
 *
 * @param  object $facetoface A database fieldset object for the facetoface activity
 * @param  object $userid     Default to current user if null
 * @return boolean whether a person needs a manager to sign up for that activity
 */
function facetoface_user_declared_interest($facetoface, $userid = null) {
    global $DB, $USER;

    if (is_null($userid)) {
        $userid = $USER->id;
    }

    return $DB->record_exists('facetoface_interest', array('facetoface' => $facetoface->id, 'userid' => $userid));
}

/**
 * Determines whether the user can declare interest in the activity.
 *
 * @param  object $facetoface A database fieldset object for the facetoface activity
 * @param  object $userid     Default to current user if null
 * @return boolean whether a person needs a manager to sign up for that activity
 */
function facetoface_activity_can_declare_interest($facetoface, $userid = null) {
    global $DB, $USER;

    // "Declare interest" must be turned on for the activity.
    if (!$facetoface->declareinterest) {
        return false;
    }

    // If user already declared interest, cannot declare again.
    if (facetoface_user_declared_interest($facetoface, $userid)) {
        return false;
    }

    // Check that the user has no existing signup.
    if (is_null($userid)) {
        $userid = $USER->id;
    }
    $sql = "
    SELECT snp.id
    FROM {facetoface_sessions} ssn
    JOIN {facetoface_signups} snp ON (snp.sessionid = ssn.id)
    JOIN {facetoface_signups_status} sst ON (sst.signupid = snp.id AND sst.superceded = :superceded)
    WHERE ssn.facetoface = :f2fid
    AND snp.userid = :userid
    AND sst.statuscode >= :statusrequested
    AND sst.statuscode <= :statusbooked
    ";
    $params = array(
        'f2fid' => $facetoface->id,
        'userid' => $userid,
        'superceded' => 0,
        'statusrequested' => MDL_F2F_STATUS_REQUESTED,
        'statusbooked' => MDL_F2F_STATUS_BOOKED,
    );
    if ($DB->record_exists_sql($sql, $params)) {
        return false;
    }

    // If "only when full" is turned on, allow only when all sessions are fully booked.
    if ($facetoface->interestonlyiffull) {
        $sessions = $DB->get_records('facetoface_sessions', array('facetoface' => $facetoface->id));
        if (!$cm = get_coursemodule_from_instance('facetoface', $facetoface->id)) {
            print_error('error:incorrectcoursemodule', 'facetoface');
        }
        $contextmodule = context_module::instance($cm->id);
        foreach ($sessions as $session) {
            if ($session->allowoverbook || facetoface_session_has_capacity($session, $contextmodule)) {
                return false;
            }
        }
    }
    return true;
}

/**
 * Declares interest in a facetoface activity for a user.
 * Assume we have already checked that no existing decleration exists
 * And all the necessary permissions
 *
 * @param  object $facetoface A database fieldset object for the facetoface activity
 * @param  string $reason     Reason provided by user
 * @param  object $userid     Default to current user if null
 * @return boolean            Success
 */
function facetoface_declare_interest($facetoface, $reason = '', $userid = null) {
    global $DB, $USER;

    if (is_null($userid)) {
        $userid = $USER->id;
    }

    $toinsert = (object)array(
        'facetoface' => $facetoface->id,
        'userid' => $userid,
        'timedeclared' => time(),
        'reason' => $reason,
    );

    return $DB->insert_record('facetoface_interest', $toinsert);
}

/**
 * Withdraws interest from a facetoface activity for a user.
 * @param  object $facetoface A database fieldset object for the facetoface activity
 * @param  int    $userid     Default to current user if null
 * @return boolean            Success
 */
function facetoface_withdraw_interest($facetoface, $userid = null) {
    global $DB, $USER;

    if (is_null($userid)) {
        $userid = $USER->id;
    }

    return $DB->delete_records('facetoface_interest', array('facetoface' => $facetoface->id, 'userid' => $userid));
}

/**
 * Called after each config setting update.
 */
function facetoface_displaysessiontimezones_updated() {
    global $DB;

    $sessions = $DB->get_recordset('facetoface_sessions');
    foreach ($sessions as $s) {
        $session = facetoface_get_session($s->id);
        facetoface_update_calendar_entries($session);
    }
    $sessions->close();
}

/**
 * Cancels waitlisted users from an array as booked on a session
 * @param int    $sessionid  ID of the session to use
 * @param array  $userids    Array of user ids to confirm
 */
function facetoface_confirm_attendees($sessionid, $userids) {
    global $DB, $USER;

    $session = facetoface_get_session($sessionid);
    $facetoface = $DB->get_record('facetoface', array('id'=>$session->facetoface));

    foreach ($userids as $userid) {
        $conditions = array('sessionid' => $sessionid, 'userid' => $userid);
        $existingsignup = $DB->get_record('facetoface_signups', $conditions, '*', MUST_EXIST);
        facetoface_update_signup_status($existingsignup->id, MDL_F2F_STATUS_BOOKED, $USER->id);
        facetoface_send_confirmation_notice($facetoface, $session, $userid, $existingsignup->notificationtype, false);
    }
}

/**
 * Cancels waitlisted users from an array on a session
 * @param int    $sessionid  ID of the session to use
 * @param array  $userids    Array of user ids to cancel
 */
function facetoface_cancel_attendees($sessionid, $userids) {
    global $DB, $USER;

    foreach ($userids as $userid) {
        $params = array('sessionid' => $sessionid, 'userid' => $userid);
        $existingsignup = $DB->get_record('facetoface_signups', $params, '*', MUST_EXIST);
        facetoface_update_signup_status($existingsignup->id, MDL_F2F_STATUS_USER_CANCELLED, $USER->id);
    }
}

/**
 * Randomly books waitlisted users on to a session
 * @param int $sessionid  ID of the session to use
 */
function facetoface_waitlist_randomly_confirm_users($sessionid, $userids) {
    $session = facetoface_get_session($sessionid);
    $signupcount = facetoface_get_num_attendees($sessionid);

    $numtoconfirm = $session->capacity - $signupcount;

    if (count($userids) <= $session->capacity) {
        $winners = $userids;
    } else {
        $winners = array_rand(array_flip($userids), $numtoconfirm);

        if ($numtoconfirm == 1) {
            $winners = array($winners);
        }
    }

    facetoface_confirm_attendees($sessionid, $winners);

    return $winners;
}
