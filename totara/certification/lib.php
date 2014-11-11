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
 * @author Jon Sharp <jonathans@catalyst-eu.net>
 * @package totara
 * @subpackage certification
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->dirroot.'/totara/program/program_assignments.class.php'); // For assignment category literals.

// Certification types (learning component types).
define('CERTIFTYPE_PROGRAM', 1);
define('CERTIFTYPE_COURSE', 2);
define('CERTIFTYPE_COMPETENCY', 3);
global $CERTIFTYPE;
$CERTIFTYPE = array(
    'type_notset',
    'type_program',
    'type_course',
    'type_competency',
);

// Certification completion status, status column in certif_completion.
define('CERTIFSTATUS_UNSET', 0);
define('CERTIFSTATUS_ASSIGNED', 1);
define('CERTIFSTATUS_INPROGRESS', 2);
define('CERTIFSTATUS_COMPLETED', 3);
define('CERTIFSTATUS_EXPIRED', 4);

global $CERTIFSTATUS;
$CERTIFSTATUS = array(
    CERTIFSTATUS_UNSET => 'status_unset',
    CERTIFSTATUS_ASSIGNED => 'status_assigned',
    CERTIFSTATUS_INPROGRESS => 'status_inprogress',
    CERTIFSTATUS_COMPLETED => 'status_completed',
    CERTIFSTATUS_EXPIRED => 'status_expired',
);

// Renewal status column in course.
define('CERTIFRENEWALSTATUS_NOTDUE', 0);
define('CERTIFRENEWALSTATUS_DUE', 1);
define('CERTIFRENEWALSTATUS_EXPIRED', 2);

global $CERTIFRENEWALSTATUS;
$CERTIFRENEWALSTATUS = array(
    CERTIFRENEWALSTATUS_NOTDUE => 'renewalstatus_notdue',
    CERTIFRENEWALSTATUS_DUE => 'renewalstatus_dueforrenewal',
    CERTIFRENEWALSTATUS_EXPIRED => 'renewalstatus_expired',
);

// When the re-certifcation completion statuses.
define('CERTIFRECERT_UNSET', 0);
define('CERTIFRECERT_COMPLETION', 1);
define('CERTIFRECERT_EXPIRY', 2);

global $CERTIFRECERT;
$CERTIFRECERT = array(
    CERTIFRECERT_UNSET => 'unset',
    CERTIFRECERT_COMPLETION => get_string('editdetailsrccmpl', 'totara_certification'),
    CERTIFRECERT_EXPIRY => get_string('editdetailsrcexp', 'totara_certification'),
);

// Certifcation path constants.
define('CERTIFPATH_UNSET', 0);
define('CERTIFPATH_STD', 1);
define('CERTIFPATH_CERT', 1);
define('CERTIFPATH_RECERT', 2);

global $CERTIFPATH;
$CERTIFPATH = array(
    CERTIFPATH_UNSET => 'unset',
    CERTIFPATH_CERT => 'certification',
    CERTIFPATH_RECERT => 'recertification',
);

global $CERTIFPATHSUF;
$CERTIFPATHSUF = array(
    CERTIFPATH_UNSET => '_',
    CERTIFPATH_CERT => '_ce',
    CERTIFPATH_RECERT => '_rc',
);

class certification_event_handler {

    /**
     * User is assigned to a program event handler
     *
     * @param \totara_program\event\program_assigned $event
     */
    public static function assigned(\totara_program\event\program_assigned $event) {
        global $DB;

        $programid = $event->objectid;
        $userid = $event->userid;
        $prog = $DB->get_record('prog', array('id' => $programid));

        if ($prog->certifid) {
            assign_certification_stage($prog->certifid, $userid);
        }
    }

    /**
     * User is unassigned to a program event handler
     * Delete certification completion record
     *
     * @param \totara_program\event\program_unassigned $event
     */
    public static function unassigned(\totara_program\event\program_unassigned $event) {
        global $DB;

        $programid = $event->objectid;
        $userid = $event->userid;
        $prog = $DB->get_record('prog', array('id' => $programid));

        if ($prog->certifid) {
            $params = array('certifid' => $prog->certifid, 'userid' => $userid);
            if ($completionrecord = $DB->get_record('certif_completion', $params)) {
                if (!in_array($completionrecord->status, array(CERTIFSTATUS_UNSET, CERTIFSTATUS_ASSIGNED))) {
                    copy_certif_completion_to_hist($prog->certifid, $userid, true);
                }
                $DB->delete_records('certif_completion', $params);
            }
        }
    }

    /**
     * Program completion event handler
     *
     * @param \totara_program\event\program_completed $event
     */
    public static function completed(\totara_program\event\program_completed $event) {
        global $DB;

        if (!empty($event->other['certifid'])) {
            complete_certification_stage($event->other['certifid'], $event->userid);
        }
    }
}

/**
 * Run the certification cron
 */
function totara_certification_cron() {
    global $CFG;
    require_once($CFG->dirroot . '/totara/certification/cron.php');
    certification_cron();
}

// Stages functions.

/**
 * Assign certification to user
 *
 * Assign to cert path to do initial certification.
 * Note: If learner has prior completion of courses in the program, they are assigned to the CERT path here.
 * When cron invokes program competion (assuming all courses in the program are completed),
 * they will be set on RECERT path in the program completion event handler
 *
 * @param integer $certificationid
 * @param integer $userid
 */
function assign_certification_stage($certificationid, $userid) {
    global $DB;

    /* Current assignment:
     * When an assignment is updated - this event (sometimes) gets called too
     * the changed data is program competion (due) date - which does not affect certif_completion
     * so we just need to check if already exists.
     *
     * Assignment completion
     * Relative date added   - not called
     * Relative date removed - called (not unassign)
     * Fixed date added      - not called
     * Fixed date removed    - not called (nor unassign)
     */

    $completionid = $DB->get_field('certif_completion', 'id', array('certifid' => $certificationid, 'userid' => $userid));

    if (!$completionid) {
        // Create new.
        write_certif_completion($certificationid, $userid, CERTIFPATH_CERT);
    }
}

/**
 * Updates a course & users's certif_completion record's status to 'in progress'
 * Can be called multiple times without a problem as will only overwrite appropriate statuses
 *
 * called from completion/completion_completion.php on first acces of course (also by cron with user being cron
 * user (eg admin) - why?)
 *
 * @param int $courseid
 * @param int $userid
 * @return boolean (false if not a course&user)
 */
function inprogress_certification_stage($courseid, $userid) {
    global $DB;
    $certificationids = find_certif_from_course($courseid);

    if (!count($certificationids)) {
        return false;
    }

    // Could be multiple certification records so find the one this user is doing.
    list($usql, $params) = $DB->get_in_or_equal(array_keys($certificationids));
    $sql = "SELECT id, status, renewalstatus, certifid
            FROM {certif_completion} cfc
            WHERE cfc.certifid $usql AND cfc.userid = ?";

    $params[] = $userid;

    $completion_records = $DB->get_records_sql($sql, $params);

    $count = count($completion_records);
    if ($count == 0) {
        // If 0 then this course & user is not in an assigned certification.
        return false;
    } else if ($count > 1) {
        // A problem TODO - eg user is doing 2 certifs which both have this course in them.
        return false;
    }

    $completion_record = reset($completion_records);

    // Change only from specific states as function can be called at any time (whenever course is viewed)
    // from unset, assigned, expired - any time
    // from completed when renewal status is dueforrenewal.
    if ($completion_record->status < CERTIFSTATUS_INPROGRESS
        || $completion_record->status == CERTIFSTATUS_EXPIRED
        || $completion_record->status == CERTIFSTATUS_COMPLETED && $completion_record->renewalstatus == CERTIFRENEWALSTATUS_DUE) {
        $todb = new StdClass();
        $todb->id = $completion_record->id;
        $todb->status = CERTIFSTATUS_INPROGRESS;
        $todb->timemodified = time();

        $DB->update_record('certif_completion', $todb);
    }

    return true;
}

/**
 * Could come from assign processing (When user has prior completion of courses in program etc,
 * or when user completes program etc)
 *
 * @param integer certificationid
 * @param integer userid
 * @return boolean
 */
function complete_certification_stage($certificationid, $userid) {
    global $DB;

    // Set for recertification - dates etc.
    write_certif_completion($certificationid, $userid, CERTIFPATH_RECERT);

    // Set course renewal status to not due.
    $courseids = array();
    $courses = find_courses_for_certif($certificationid, 'c.id, c.fullname');
    foreach ($courses as $course) {
        $courseids[] = $course->id;
    }
    set_course_renewalstatus($courseids, $userid, CERTIFRENEWALSTATUS_NOTDUE);

    return true;
}

/**
 * Triggered by the cron, gets all certifications that have the
 * re-certify window due to be open and perform actions
 *
 * @return int Count of certification completion records
 */
function recertify_window_opens_stage() {
    global $DB, $CFG;

    // Find any users who have reached this point.
    $sql = "SELECT cfc.id as uniqueid, u.*, cf.id as certifid, cfc.userid, p.id as progid
            FROM {certif_completion} cfc
            JOIN {certif} cf on cf.id = cfc.certifid
            JOIN {prog} p on p.certifid = cf.id
            JOIN {user} u on u.id = cfc.userid
            WHERE cfc.timewindowopens < ?
                  AND cfc.status = ?
                  AND cfc.renewalstatus = ?
                  AND u.deleted = 0";

    $results = $DB->get_records_sql($sql, array(time(), CERTIFSTATUS_COMPLETED, CERTIFRENEWALSTATUS_NOTDUE));

    require_once($CFG->dirroot.'/course/lib.php'); // Archive_course_activities().

    // For each certification & user.
    foreach ($results as $user) {
        // Archive completion.
        copy_certif_completion_to_hist($user->certifid, $user->id);

        $courses = find_courses_for_certif($user->certifid, 'c.id, c.fullname');

        // Reset course_completions, course_module_completions, program_completion records.
        reset_certifcomponent_completions($user, $courses);

        // Set the renewal status of the certification/program to due for renewal.
        $DB->set_field('certif_completion', 'renewalstatus', CERTIFRENEWALSTATUS_DUE,
                        array('certifid' => $user->certifid, 'userid' => $user->id));

        // Sort out the messages manager.
        if (isset($messagesmanagers[$user->progid])) {
            // Use the existing messages manager object if it is available.
            $messagesmanager = $messagesmanagers[$user->progid];
        } else {
            // Create a new messages manager object and store it if it has not already been instantiated.
            $messagesmanager = new prog_messages_manager($user->progid);
            $messagesmanagers[$user->progid] = $messagesmanager;
        }

        $messages = $messagesmanager->get_messages();

        foreach ($messages as $message) {
            if ($message->messagetype == MESSAGETYPE_RECERT_WINDOWOPEN) {
                // This function checks prog_messagelog for existing record. If it exists, the message is not sent.
                $message->send_message($user);
            }
        }
    }

    return count($results);
}

/**
 * Triggered by the cron, run actions needed when a certification's
 * re-certify window is about to close
 *
 * @return int Count of certification completion records
 */
function recertify_window_abouttoclose_stage() {
    global $DB, $CFG;

    // Need these when called from cron.
    require_once($CFG->dirroot . '/totara/program/program_messages.class.php');
    require_once($CFG->dirroot . '/totara/program/program_message.class.php');
    require_once($CFG->dirroot . '/totara/program/program.class.php');

    // See if there are any programs & users where:
    // now > (timeexpires - offset-for-that-certif/prog)
    // now < timeexpires (to minimise number of send attempts).

    list($statussql, $statusparams) = $DB->get_in_or_equal(array(CERTIFSTATUS_COMPLETED, CERTIFSTATUS_INPROGRESS));

    $uniqueid = $DB->sql_concat('cfc.id', "'_'", 'pm.id');
    $sql = "SELECT {$uniqueid} as uniqueid, u.*, p.id as progid, pm.id as pmid
            FROM {certif_completion} cfc
            JOIN {certif} cf on cf.id = cfc.certifid
            JOIN {prog} p ON p.certifid = cf.id
            JOIN {prog_message} pm ON pm.programid = p.id
            JOIN {user} u ON u.id = cfc.userid
            WHERE cfc.status {$statussql}
                  AND cfc.renewalstatus = ?
                  AND ? > (cfc.timeexpires - pm.triggertime)
                  AND ? < cfc.timeexpires
                  AND pm.messagetype = ?
                  AND u.deleted = 0";

    $now = time();
    $params = array_merge($statusparams, array(CERTIFRENEWALSTATUS_DUE, $now, $now, MESSAGETYPE_RECERT_WINDOWDUECLOSE));
    $results = $DB->get_records_sql($sql, $params);

    foreach ($results as $user) {
        // Sort out the messages manager.
        if (isset($messagesmanagers[$user->progid])) {
            // Use the existing messages manager object if it is available.
            $messagesmanager = $messagesmanagers[$user->progid];
        } else {
            // Create a new messages manager object and store it if it has not already been instantiated.
            $messagesmanager = new prog_messages_manager($user->progid);
            $messagesmanagers[$user->progid] = $messagesmanager;
        }

        $messages = $messagesmanager->get_messages();

        foreach ($messages as $message) {
            if ($message->id == $user->pmid) {
                // This function checks prog_messagelog for existing record. If it exists, the message is not sent.
                $message->send_message($user);
            }
        }
    }

    return count($results);
}

/**
 * Triggered by cron, run actions to expire a certification stage
 *
 * @return int Count of certification completion records
 */
function recertify_expires_stage() {
    global $DB;

    // Find any users who have reached this point.
    list($statussql, $statusparams) = $DB->get_in_or_equal(array(CERTIFSTATUS_COMPLETED, CERTIFSTATUS_INPROGRESS));
    $sql = "SELECT cfc.id as uniqueid, u.*, cf.id as certifid, p.id as progid
            FROM {certif_completion} cfc
            JOIN {certif} cf ON cf.id = cfc.certifid
            JOIN {prog} p ON p.certifid = cf.id
            JOIN {user} u ON u.id = cfc.userid
            WHERE ? > cfc.timeexpires
                AND cfc.renewalstatus = ?
                AND u.deleted = 0
                AND cfc.status {$statussql}";

    $params = array_merge(array(time(), CERTIFRENEWALSTATUS_DUE), $statusparams);
    $results = $DB->get_records_sql($sql, $params);

    foreach ($results as $user) {
        // Set the renewal status of the certification to Expired.
        // Assign the user back to the original certification path. This means the content of their certification
        // will change to show the original set of courses.
        write_certif_completion($user->certifid, $user->id, CERTIFPATH_CERT, CERTIFRENEWALSTATUS_EXPIRED);

        // For each course in the certification, set the renewal status to Expired.
        $courseids = array();
        $courses = find_courses_for_certif($user->certifid, 'c.id, c.fullname');
        foreach ($courses as $course) {
            $courseids[] = $course->id;
        }

        set_course_renewalstatus($courseids, $user->id, CERTIFRENEWALSTATUS_EXPIRED);

        // Sort out the messages manager.
        if (isset($messagesmanagers[$user->progid])) {
            // Use the existing messages manager object if it is available.
            $messagesmanager = $messagesmanagers[$user->progid];
        } else {
            // Create a new messages manager object and store it if it has not already been instantiated.
            $messagesmanager = new prog_messages_manager($user->progid);
            $messagesmanagers[$user->progid] = $messagesmanager;
        }

        $messages = $messagesmanager->get_messages();

        foreach ($messages as $message) {
            if ($message->messagetype == MESSAGETYPE_RECERT_FAILRECERT) {
                // This function checks prog_messagelog for existing record. If it exists, the message is not sent.
                $message->send_message($user);
            }
        }
    }

    return count($results);
}

/**
 * Get time of last completed certification course
 *
 * @param integer $certificationid
 * @param integer $userid
 * @return integer
 */
function certif_get_content_completion_time($certificationid, $userid) {
    global $DB;
    $courses = find_courses_for_certif($certificationid, 'c.id');
    $courselist = array_keys($courses);
    list($incourse, $params) = $DB->get_in_or_equal($courselist, SQL_PARAMS_NAMED);

    $sql = "SELECT MAX(timecompleted) AS maxtimecompleted
            FROM {course_completions}
            WHERE course $incourse
              AND userid = :userid";
    $params['userid'] = $userid;
    $completion = $DB->get_record_sql($sql, $params);
    if (!$completion) {
        return 0;
    }
    return $completion->maxtimecompleted;
}

/**
 * Create/update certif_completion record at start of path (assign or complete stages)
 *
 * @param integer $certificationid
 * @param integer $userid
 * @param integer $certificationpath
 * @param integer $renewalstatus
 */
function write_certif_completion($certificationid, $userid, $certificationpath = CERTIFPATH_CERT,
                                                            $renewalstatus = CERTIFRENEWALSTATUS_NOTDUE) {
    global $DB, $CFG;

    $certification = $DB->get_record('certif', array('id' => $certificationid));
    if (!$certification) {
        print_error('error:incorrectcertifid', 'totara_certification', null, $certificationid);
    }

    $certificationcompletion = $DB->get_record('certif_completion', array('certifid' => $certificationid, 'userid' => $userid));

    $now = time();

    // Create certification completion record.
    $todb = new StdClass();
    $todb->certifid = $certificationid;
    $todb->userid = $userid;
    $todb->renewalstatus = $renewalstatus;
    $todb->certifpath = $certificationpath;
    if ($certificationpath == CERTIFPATH_RECERT) { // Recertifying.
        $todb->status = CERTIFSTATUS_COMPLETED;
        $lastcompleted = certif_get_content_completion_time($certificationid, $userid);
        // If no courses completed, maintain default behaviour.
        if (!$lastcompleted) {
            $lastcompleted = time();
        }
        // The base date is 'now' if the COMPLETION option set (or if first re-certification (where timexpires would be 0))
        // else its the expired date (ie at the end of the full certification period).
        //
        // Prior learning:
        // Normally when the program completion event is called (and hence this function) we just need to record the current
        // date-time and calculate the new expiry etc.
        // However with prior learning, where courses may have been completed before being added to a program,
        // the preferred date is the date of the last course. As there is currently no way to differentiate between a user/program
        // which is prior learning and not, we have to do this check for all program completions - rather than just using
        // the current time.
        // Note: the completion date in prog_completion will still be 'now' - not the last course-completion date so will
        // differ from certification completion.
        $base = get_certiftimebase($certification->recertifydatetype, $certificationcompletion->timeexpires, $lastcompleted);
        $todb->timeexpires = get_timeexpires($base, $certification->activeperiod);
        $todb->timewindowopens = get_timewindowopens($todb->timeexpires, $certification->windowperiod);
        $todb->timecompleted = $lastcompleted;
    } else { // Certifying.
        $todb->status =  CERTIFSTATUS_ASSIGNED;
        // Window/expires not relevant for CERTIFPATH_CERT as should be doing in program 'due' time.
        $todb->timewindowopens = 0;
        $todb->timeexpires = 0;
        $todb->timecompleted = 0;
    }

    $todb->timemodified = $now;

    if ($certificationcompletion) {
        $todb->id = $certificationcompletion->id;
        $DB->update_record('certif_completion', $todb);
    } else {
        $id = $DB->insert_record('certif_completion', $todb);
    }
}

/**
 * Copy a certif_completion record to certif_completion_history
 *
 * @param integer $certificationid
 * @param integer $userid
 * @param bool $unassigned
 * @return boolean
 */
function copy_certif_completion_to_hist($certificationid, $userid, $unassigned = false) {
    global $DB;

    $certificationcompletion = $DB->get_record('certif_completion', array('certifid' => $certificationid, 'userid' => $userid));

    if (!$certificationcompletion) {
        print_error('error:incorrectid', 'totara_certification');
    }

    $certificationcompletion->timemodified = time();
    $certificationcompletion->unassigned = $unassigned;
    $completionhistory = $DB->get_record('certif_completion_history',
            array('certifid' => $certificationid, 'userid' => $userid, 'timeexpires' => $certificationcompletion->timeexpires));
    if ($completionhistory) {
        $certificationcompletion->id = $completionhistory->id;
        $DB->update_record('certif_completion_history', $certificationcompletion);
    } else {
        unset($certificationcompletion->id);
        $DB->insert_record('certif_completion_history', $certificationcompletion);
    }
    return true;
}

/**
 * Set course renewal status
 *
 * @param array $courseids
 * @param integer $userid
 * @param integer $renewalstatus
 */
function set_course_renewalstatus($courseids, $userid, $renewalstatus) {
    global $DB;

    if (!empty($courseids)) {
        list($coursesql, $courseparams) = $DB->get_in_or_equal($courseids);
        $sql = "UPDATE {course_completions}
                SET renewalstatus = ?
                WHERE userid = ? AND course {$coursesql}";

        $params = array_merge(array($renewalstatus, $userid), $courseparams);
        $DB->execute($sql, $params);
    }
}

/**
 * Checks if re-certification window is open
 * Note: does not check if expired as want user to do anytime after open
 *
 * @param integer $certificationid
 * @param integer $userid
 * @return boolean
 */
function certif_iswindowopen($certificationid, $userid) {
    global $DB;

    $timewindowopens = $DB->get_field('certif_completion', 'timewindowopens',
                                          array('certifid' => $certificationid, 'userid' =>  $userid));
    $now = time();
    if ($timewindowopens && $now > $timewindowopens) {
        return true;
    }
    return false;
}

/**
 * Find if a course exists in a certification
 *
 * @param integer $courseid
 * @param string $fields
 * @return array
 */
function find_certif_from_course($courseid, $fields='cf.id') {
    global $DB;

    // If course is in 2 coursesets - eg in cert and recert paths, then 2 records will be returned
    // for a certification, so use DISTINCT.
    $sql = "SELECT DISTINCT $fields
            FROM {course} c
            JOIN {prog_courseset_course} pcc on pcc.courseid = c.id
            JOIN {prog_courseset} pc on pc.id = pcc.coursesetid
            JOIN {prog} p on p.id = pc.programid
            JOIN {certif} cf on cf.id = p.certifid
            WHERE c.id = ?";

    $certificationrecords = $DB->get_records_sql($sql, array($courseid));

    return $certificationrecords;
}

/**
 * Find all courses associated with a certification
 *
 * @param integer $certifid
 * @param string $fields
 * @return array
 */
function find_courses_for_certif($certifid, $fields='c.id, c.fullname') {
    global $DB;

    $sql = "SELECT DISTINCT $fields
            FROM {certif} cf
            JOIN {prog} p on p.certifid = cf.id
            JOIN {prog_courseset} pc on pc.programid = p.id
            JOIN {prog_courseset_course} pcc on pcc.coursesetid = pc.id
            JOIN {course} c on c.id = pcc.courseid
            WHERE cf.id = ? ";
    $certificationrecords = $DB->get_records_sql($sql, array($certifid));

    return $certificationrecords;
}

/**
 * Send message defined in program_message.class.php to user
 * and also to manager if specified in settings
 *
 * @param integer $userid
 * @param integer $progid
 * @param integer $msgtype
 */
function send_certif_message($progid, $userid, $msgtype) {
    global $DB;

    $user = $DB->get_record('user', array('id' => $userid));
    $messagesmanager = new prog_messages_manager($progid);
    $messages = $messagesmanager->get_messages();

    $params = array('contextlevel' => CONTEXT_PROGRAM, 'progid' => $progid, 'userid' => $userid);

    // Take into account the visiblity of the certification before sending messages.
    list($visibilitysql, $visibilityparams) = totara_visibility_where(null, 'p.id', 'p.visible',
        'p.audiencevisible', 'p', 'certification');
    $params = array_merge($params, $visibilityparams);

    $now = time();
    $certif = $DB->get_record_sql("SELECT cc.*
                                   FROM {certif_completion} cc
                                   JOIN {prog} p ON p.certifid = cc.certifid
                                   JOIN {context} ctx ON p.id = ctx.instanceid AND contextlevel =:contextlevel
                                   WHERE p.id =:progid AND cc.userid =:userid AND {$visibilitysql}", $params);
    // If messagetype set up for this program, send notifications to user and the user's manager (if set on message).
    foreach ($messages as $message) {
        if ($message->messagetype == $msgtype) {
            if ($msgtype == MESSAGETYPE_RECERT_WINDOWDUECLOSE) {
                // ONLY send the ones that are due.
                if($now > ($certif->timeexpires - $message->triggertime) && $now < $certif->timeexpires) {
                    $sent = $DB->get_records('prog_messagelog', array('messageid' => $message->id, 'userid' => $userid));
                    // DON'T send them more than once.
                    if(empty($sent)) {
                        $message->send_message($user);
                    }
                }
            } else {
               $message->send_message($user); // Prog_eventbased_message.send_message() program_message.class.php.
               // This function checks prog_messagelog for existing record, checking messageid and userid (and coursesetid(=0))
               // messageid is id of message in prog_message (ie for this prog and message type)
               // if exists, the message is not sent.
            }
        }
    }
}

/**
 * Get current certifpath of user for given certification
 *
 * @param integer $certificationid ID of certification to check
 * @param integer $userid User Id to find certification path for
 * @return integer Current path of given user on certification
 */
function get_certification_path_user($certificationid, $userid) {
    global $DB;

    $certifpath = $DB->get_field('certif_completion', 'certifpath', array('certifid' => $certificationid, 'userid' => $userid));

    if ($certifpath) {
        return $certifpath;
    } else {
        return CERTIFPATH_UNSET;
    }
}

/**
 * Read from formdata and get the certifpath based on matching a given fromfield and value
 *   [certifpath_rc] => 2
 *   [setprefixes_rc] =>
 *   [contenttype_rc] => 1
 *   [addcontent_rc] => Add
 *   field = 'addcontent' and fieldvalue = 'Add' would return 2
 * @param StdClass $formdata
 * @return int CERTIFPATH constant
 */
function get_certification_path_field($formdata, $field, $fieldvalue) {
    foreach (array('_ce', '_rc') as $suffix) {
        if (!isset($formdata->{$field.$suffix})
            || empty($formdata->{$field.$suffix})
            || $formdata->{$field.$suffix} != $fieldvalue) {
            continue;
        } else {
            return $formdata->{'certifpath'.$suffix};
        }
    }
    return null;
}

/**
 * A list of certifications that match a search
 *
 * @uses $DB, $USER
 * @param array $searchterms Words to search for in an array
 * @param string $sort Sort sql
 * @param int $page The results page to return
 * @param int $recordsperpage Number of search results per page
 * @param int $totalcount Passed in by reference. Total count so we can calculate number of pages
 * @param string $whereclause Addition where clause
 * @param array $whereparams Parameters needed for $whereclause
 * @return object {@link $COURSE} records
 */
// TODO: Fix this function to work in Moodle 2 way
// See lib/datalib.php -> get_courses_search for example.
function certif_get_certifications_search($searchterms, $sort='fullname ASC', $page=0, $recordsperpage=50, &$totalcount,
                                                                                                $whereclause, $whereparams) {
    global $DB, $USER;

    $regexp    = $DB->sql_regex(true);
    $notregexp = $DB->sql_regex(false);

    $fullnamesearch = '';
    $summarysearch = '';
    $idnumbersearch = '';
    $shortnamesearch = '';

    $fullnamesearchparams = array();
    $summarysearchparams = array();
    $idnumbersearchparams = array();
    $shortnamesearchparams = array();
    $params = array();

    foreach ($searchterms as $searchterm) {
        if ($fullnamesearch) {
            $fullnamesearch .= ' AND ';
        }
        if ($summarysearch) {
            $summarysearch .= ' AND ';
        }
        if ($idnumbersearch) {
            $idnumbersearch .= ' AND ';
        }
        if ($shortnamesearch) {
            $shortnamesearch .= ' AND ';
        }

        if (substr($searchterm, 0, 1) == '+') {
            $searchterm      = substr($searchterm, 1);
            $summarysearch  .= " summary $regexp '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $fullnamesearch .= " fullname $regexp '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $idnumbersearch  .= " idnumber $regexp '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $shortnamesearch  .= " shortname $regexp '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
        } else if (substr($searchterm, 0, 1) == "-") {
            $searchterm      = substr($searchterm, 1);
            $summarysearch  .= " summary $notregexp '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $fullnamesearch .= " fullname $notregexp '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $idnumbersearch .= " idnumber $notregexp '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $shortnamesearch .= " shortname $notregexp '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
        } else {
            $summarysearch .= $DB->sql_like('summary', '?', false, true, false) . ' ';
            $summarysearchparams[] = '%' . $searchterm . '%';

            $fullnamesearch .= $DB->sql_like('fullname', '?', false, true, false) . ' ';
            $fullnamesearchparams[] = '%' . $searchterm . '%';

            $idnumbersearch .= $DB->sql_like('idnumber', '?', false, true, false) . ' ';
            $idnumbersearchparams[] = '%' . $searchterm . '%';

            $shortnamesearch .= $DB->sql_like('shortname', '?', false, true, false) . ' ';
            $shortnamesearchparams[] = '%' . $searchterm . '%';
        }
    }

    // If search terms supplied, include in where.
    if (count($searchterms)) {
        $where = "
            WHERE (( $fullnamesearch ) OR ( $summarysearch ) OR ( $idnumbersearch ) OR ( $shortnamesearch ))
            AND category > 0
        ";
        $params = array_merge($params, $fullnamesearchparams, $summarysearchparams, $idnumbersearchparams, $shortnamesearchparams);
    } else {
        // Otherwise return everything.
        $where = " WHERE category > 0 ";
    }

    // Add any additional sql supplied to where clause.
    if ($whereclause) {
        $where .= " AND {$whereclause}";
        $params = array_merge($params, $whereparams);
    }

    // See also certif_get_certifications_page.
    $sql = "SELECT cf.id, cf.learningcomptype
             ,p.id as pid,p.fullname,p.visible,p.category,p.icon,p.available,p.availablefrom,p.availableuntil
            ,ctx.id AS ctxid, ctx.path AS ctxpath
            ,ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel
            FROM {certif} cf
            JOIN {prog} p ON p.certifid = cf.id AND cf.learningcomptype=".CERTIFTYPE_PROGRAM."
            JOIN {context} ctx ON (p.id = ctx.instanceid AND ctx.contextlevel = ".CONTEXT_PROGRAM.")
            $where
            ORDER BY " . $sort;

    $certifications = array();

    $limitfrom = $page * $recordsperpage;
    $limitto   = $limitfrom + $recordsperpage;
    $c = 0; // Counts how many visible certifications we've seen.

    $rs = $DB->get_recordset_sql($sql, $params);

    foreach ($rs as $certification) {
        if (!is_siteadmin($USER->id)) {
            // Check if this certification is not available, if it's not then deny access.
            if ($certification->available == 0) {
                continue;
            }

            if (isset($USER->timezone)) {
                $now = usertime(time(), $USER->timezone);
            } else {
                $now = usertime(time());
            }

            // Check if the certificationme isn't accessible yet.
            if ($certification->availablefrom > 0 && $certification->availablefrom > $now) {
                continue;
            }

            // Check if the certificationme isn't accessible anymore.
            if ($certification->availableuntil > 0 && $certification->availableuntil < $now) {
                continue;
            }
        }

        if ($certification->visible || has_capability('totara/certification:viewhiddencertifications',
                                                                    program_get_context($certification->pid))) {
            // Don't exit this loop till the end
            // we need to count all the visible courses
            // to update $totalcount.
            if ($c >= $limitfrom && $c < $limitto) {
                $certifications[] = $certification;
            }
            $c++;
        }
    }

    $rs->close();

    // Update total count for pass-by-reference variable.
    $totalcount = $c;
    return $certifications;
}

/**
 * Returns list of certifications, for whole site, or category
 * (This is the counterpart to get_courses_page in /lib/datalib.php)
 *
 * Similar to certif_get_certifications, but allows paging
 * @param int $categoryid
 * @param string $sort
 * @param string $fields
 * @param int $totalcount
 * @param int $limitfrom
 * @param int $limitnum
 *
 * @return object list of visible certifications
 */
function certif_get_certifications_page($categoryid="all", $sort="sortorder ASC",
                          $fields="p.id as pid,p.sortorder,p.shortname,p.fullname,p.summary,p.visible",
                          &$totalcount, $limitfrom="", $limitnum="") {

    global $DB;

    $params = array(CONTEXT_PROGRAM);
    $categoryselect = "";
    if ($categoryid != "all" && is_numeric($categoryid)) {
        $categoryselect = " WHERE p.category = ? ";
        $params[] = $categoryid;
    }

    // Pull out all certification-programs matching the category.
    $visiblecertifications = array();

    // Add audience visibility setting.
    list($visibilitysql, $visibilityparams) = totara_visibility_where(null, 'p.id', 'p.visible',
        'p.audiencevisible', 'p', 'certification');
    $params = array_merge($params, $visibilityparams);

    $certifselect = "SELECT $fields, 'certification' AS listtype,
                          ctx.id AS ctxid, ctx.path AS ctxpath,
                          ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel
                     FROM {certif} cf
                     JOIN {prog} p ON (p.certifid = cf.id)
                     JOIN {context} ctx ON (p.id = ctx.instanceid AND ctx.contextlevel = ?)
                     {$categoryselect} AND {$visibilitysql}
                     ORDER BY {$sort}";

    $rs = $DB->get_recordset_sql($certifselect, $params);

    $totalcount = 0;

    if (!$limitfrom) {
        $limitfrom = 0;
    }

    // Iteration will have to be done inside loop to keep track of the limitfrom and limitnum.
    foreach ($rs as $certification) {
        $totalcount++;
        if ($totalcount > $limitfrom && (!$limitnum or count($visiblecertifications) < $limitnum)) {
            $visiblecertifications [] = $certification;
        }
    }

    $rs->close();

    return $visiblecertifications;
}

/**
 * Get progress bar for ROL etc
 *
 * @param integer $certificationcompletionid
 * @return string Markup for producing a progress bar
 */
function certification_progress($certificationcompletionid) {
    global $DB, $PAGE;

    $certificationcompletion = $DB->get_record('certif_completion', array('id' => $certificationcompletionid),
                                                'status, renewalstatus');

    if ($certificationcompletion->status == CERTIFSTATUS_INPROGRESS) {
        // In progress.
        $overall_progress = 50;
    } else if ($certificationcompletion->status == CERTIFSTATUS_COMPLETED
                    && $certificationcompletion->renewalstatus != CERTIFRENEWALSTATUS_DUE) {
        // Completed and not due for renewal.
        $overall_progress = 100;
    } else {
        // Assume its assigned & due or overdue.
        $overall_progress = 0;
    }

    $tooltipstr = 'DEFAULTTOOLTIP';

    // Get relevant progress bar and return for display.
    $renderer = $PAGE->get_renderer('totara_core');
    return $renderer->print_totara_progressbar($overall_progress, 'medium', false, $tooltipstr);
}

/**
 * (This is the counterpart to print_courses in /course/lib.php)
 *
 * Prints non-editing view of certifs in a category
 *
 * @global $CFG
 * @global $USER
 * @param int|object $category
 */
function certif_print_certifications($category) {
    // Category is 0 (for all certifications) or an object.
    global $OUTPUT, $USER;

    $fields = "cf.id,cf.learningcomptype,p.sortorder,p.shortname,p.fullname,p.summary,p.visible,p.icon,p.certifid,p.id as pid";

    if (!is_object($category) && $category==0) {
        $categories = get_child_categories(0);  // Parent = 0  ie top-level categories only.
        if (is_array($categories) && count($categories) == 1) {
            $category = array_shift($categories);
            $certifications = certif_get_certifications($category->id, 'p.sortorder ASC', $fields);
        } else {
            $certifications = certif_get_certifications('all', 'p.sortorder ASC', $fields);
        }
        unset($categories);
    } else {
        $certifications = certif_get_certifications($category->id, 'p.sortorder ASC', $fields);
    }

    if ($certifications) {
        foreach ($certifications as $certification) {
            certif_print_certification($certification);
        }
    } else {
        echo $OUTPUT->heading(get_string('nocertifications', 'totara_certification'));
        $context = context_system::instance();
        if (has_capability('totara/certification:createcertification', $context)) {
            $options = array();
            $options['category'] = $category->id;
            echo html_writer::start_tag('div', array('class' => 'addcertificationbutton'));
            echo $OUTPUT->single_button(new moodle_url('/totara/certification/add.php', $options), get_string("addnewcertification", 'totara_certification'), 'get');
            echo html_writer::end_tag('div');
        }
    }
}

/**
 * Print a description of a certification, suitable for browsing in a list.
 * (This is the counterpart to print_course in /course/lib.php)
 *
 * @param object $certification the certification object.
 * @param string $highlightterms (optional) some search terms that should be highlighted in the display.
 */
function certif_print_certification($certification, $highlightterms = '') {
    global $PAGE, $CERTIFTYPE;

    $prog = new program($certification->pid);
    $accessible = false;
    if ($prog->is_accessible()) {
        $accessible = true;
    }

    if (isset($certification->context)) {
        $context = $certification->context;
    } else {
        $context = context_program::instance($certification->pid);
    }

    // Object for all info required by renderer.
    $data = new stdClass();

    $data->accessible = $accessible;
    $data->visible = $certification->visible;
    $data->icon = (empty($certification->icon)) ? 'default' : $certification->icon;
    $data->progid = $certification->pid;
    $data->certifid = $certification->id;
    $data->learningcomptypestr = get_string($CERTIFTYPE[$certification->learningcomptype], 'totara_certification');
    $data->fullname = $certification->fullname;
    $data->summary = file_rewrite_pluginfile_urls($certification->summary, 'pluginfile.php',
        context_program::instance($certification->pid)->id, 'totara_program', 'summary', 0);
    $data->highlightterms = $highlightterms;

    $renderer = $PAGE->get_renderer('totara_certification');
    echo $renderer->print_certification($data);
}

/**
 * Returns list of certifications, for whole site, or category
 * (This is the counterpart to get_courses in /lib/datalib.php)
 */
function certif_get_certifications($categoryid="all", $sort="cf.sortorder ASC", $fields="cf.*") {

    global $DB;

    $params = array('contextlevel' => CONTEXT_PROGRAM);
    if ($categoryid != "all" && is_numeric($categoryid)) {
        $categoryselect = "WHERE p.category = :category";
        $params['category'] = $categoryid;
    } else {
        $categoryselect = "";
    }

    if (empty($sort)) {
        $sortstatement = "";
    } else {
        $sortstatement = "ORDER BY $sort";
    }

    // Add audience visibility setting.
    list($visibilitysql, $visibilityparams) = totara_visibility_where(null, 'p.id', 'p.visible',
        'p.audiencevisible', 'p', 'certification');
    $params = array_merge($params, $visibilityparams);

    // Pull out all certifications matching the category
    // the program join effectively removes programs which
    // are not certification-programs.
    $certifications = $DB->get_records_sql("SELECT $fields,
                        ctx.id AS ctxid, ctx.path AS ctxpath,
                        ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel
                        FROM {certif} cf
                        JOIN {prog} p ON (p.certifid = cf.id)
                        JOIN {context} ctx ON (p.id = ctx.instanceid AND ctx.contextlevel = :contextlevel)
                        {$categoryselect} AND {$visibilitysql}
                        {$sortstatement}", $params
                    );

    return $certifications;
}

/**
 * Remove existing certif_completions if user unassigned
 * (effectively if no corresponding rec in prog_user_assignment)
 *
 * @param StdClass $program
 */
function delete_removed_users($program) {
    global $DB;

    // Get user assignments only if there are assignments for this program.
    $user_assignments = $DB->get_records('prog_user_assignment', array('programid' => $program->id));

    $certificationcompletions = $DB->get_records('certif_completion', array('certifid' => $program->certifid));

    // Check if completion record is not present in program user assignment but
    // is in certification completion so delete from certification completions.
    foreach ($user_assignments as $assignment) {
        foreach ($certificationcompletions as $k => $certification) {
            if ($certification->userid == $assignment->userid) {
                unset($certificationcompletions[$k]);
            }
        }
    }

    // Remove and certification completions left in list, as no longer in program user assignment.
    if (count($certificationcompletions)) {
        list($usql, $params) = $DB->get_in_or_equal(array_keys($certificationcompletions));
        $DB->delete_records_select('certif_completion', "id $usql", $params);
    }
}

/**
 * Reset the component records of the certification so user can take the certification-program again
 *
 * @param StdClass $certifcompletion
 * @param array $courses
 */
function reset_certifcomponent_completions($certifcompletion, $courses=null) {
    global $DB;

    $certificationid = $certifcompletion->certifid;
    $userid = $certifcompletion->userid;

    $transaction = $DB->start_delegated_transaction();

    // Program completion.
    // If the coursesetid is 0 then its a program completion record otherwise its a courseset completion.
    $prog = $DB->get_record('prog', array('certifid' => $certificationid));

    // Set program completion main record first.
    if ($pcp = $DB->get_record('prog_completion', array('programid' => $prog->id, 'userid' => $userid, 'coursesetid' => 0))) {
        $pcp->status = STATUS_PROGRAM_INCOMPLETE;
        $pcp->timestarted = 0;
        $pcp->timedue = 0;
        $pcp->timecompleted = 0;
        $DB->update_record('prog_completion', $pcp);
    } else {
        print_error('error:missingprogcompletion', 'totara_certification', '', $certifcompletion);
    }

    // This clears both courseset paths as could end up having to
    // do certification path if recertification expires.
    // Note: historic import does not have prog_completion records where coursesetid is not 0.
    $sql = "UPDATE {prog_completion}
        SET status = ?,
            timestarted = 0,
            timedue = 0,
            timecompleted = 0
        WHERE programid = ?
            AND userid = ?
            AND coursesetid <> 0";
    $DB->execute($sql, array(STATUS_COURSESET_INCOMPLETE, $prog->id, $userid));

    // Course_completions (get list of courses if not done in calling function).
    // Note: course_completion.renewalstatus is set to due at this point - would need to add that flag to cc processing
    // if not deleting record?
    if ($courses == null) {
        $courses = find_courses_for_certif($certificationid, 'c.id'); // All paths.
    }
    $courseids = array_keys($courses);

    foreach ($courseids as $courseid) {
        // Call course/lib.php functions.
        archive_course_completion($userid, $courseid);
        archive_course_activities($userid, $courseid);
    }

    // Remove mesages for prog&user so we can resend them.
    certif_delete_messagelog($prog->id, $userid, MESSAGETYPE_RECERT_WINDOWOPEN);
    certif_delete_messagelog($prog->id, $userid, MESSAGETYPE_RECERT_WINDOWDUECLOSE);
    certif_delete_messagelog($prog->id, $userid, MESSAGETYPE_RECERT_FAILRECERT);

    $transaction->allow_commit();
}

/**
 * Delete certification records
 *
 * @param integer $learningcomptype
 * @param integer $certifid
 */
function certif_delete($learningcomptype, $certifid) {
    global $DB, $CERTIFTYPE;

    $transaction = $DB->start_delegated_transaction();

    $DB->delete_records('certif', array('id' => $certifid));
    $DB->delete_records('certif_completion', array('certifid' => $certifid));
    $DB->delete_records('certif_completion_history', array('certifid' => $certifid));

    $transaction->allow_commit();
}

/**
 * Deletes selected records in the message log so a repeat message can be sent if required,
 * (send_message() will suppress otherwise)
 *
 * @param integer $progid
 * @param integer $userid
 * @param integer $messagetype
 */
function certif_delete_messagelog($progid, $userid, $messagetype) {
    global $DB;

    $userids = array($userid);
    if ($manager = totara_get_manager($userid)) {
        $userids[] = $manager->id;
    }

    list($useridsql, $useridparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
    $params = array_merge(array('programid' => $progid, 'messagetype' => $messagetype), $useridparams);

    $sql = "SELECT DISTINCT pml.id
            FROM {prog_messagelog} pml
            JOIN {prog_message} pm ON pm.id = pml.messageid AND pm.programid = :programid AND pm.messagetype = :messagetype
            WHERE pml.userid {$useridsql}";
    if ($messages = $DB->get_recordset_sql($sql, $params)) {
        $todelete = array();
        foreach ($messages as $message) {
            $todelete[] = $message->id;
        }
        if (!empty($todelete)) {
            list($deletesql, $deleteparams) = $DB->get_in_or_equal($todelete, SQL_PARAMS_NAMED, 'd', true);
            $DB->delete_records_select('prog_messagelog', 'id ' . $deletesql, $deleteparams);
        }
    }

}

/**
 * Get the time the re-certification is estimated from: the actual completion
 * date (now) or the original expiration date.
 *
 * @param integer $recertifydatetype
 * @param integer $timeexpires
 * @param integer $timecompleted
 * @return integer
 */
function get_certiftimebase($recertifydatetype, $timeexpires, $timecompleted) {
    if ($recertifydatetype == CERTIFRECERT_COMPLETION || $timeexpires == 0) {
        return $timecompleted;
    } else {
        return $timeexpires;
    }
}

/**
 * Work out the certification expiry time
 *
 * @param string $activeperiod (relative time string)
 * @param integer $base (from get_certiftimebase())
 * @return integer
 */
function get_timeexpires($base, $activeperiod) {
    if (empty($activeperiod)) {
        print_error('error:nullactiveperiod', 'totara_certification');
    }
    return strtotime($activeperiod, $base);
}


/**
 * Work out the window open time
 *
 * @param integer $timeexpires
 * @param string $windowperiod (relative time string)
 * @return integer
 */
function get_timewindowopens($timeexpires, $windowperiod) {
    if (empty($windowperiod)) {
        print_error('error:nullwindowperiod', 'totara_certification');
    }
    return strtotime('-'.$windowperiod, $timeexpires);
}

/**
 * Can the current user delete certifications in this category?
 *
 * @param int $categoryid
 * @return boolean
 */
function certif_can_delete_certifications($categoryid) {
    global $DB;

    $context = context_coursecat::instance($categoryid);
    $sql = context_helper::get_preload_record_columns_sql('ctx');
    $programcontexts = $DB->get_records_sql('SELECT ctx.instanceid AS progid, ' .
                    $sql . ' FROM {context} ctx ' .
                    'JOIN {prog} p ON ctx.instanceid = p.id ' .
                    'WHERE ctx.path like :pathmask AND ctx.contextlevel = :programlevel AND p.certifid IS NOT NULL',
                    array('pathmask' => $context->path. '/%', 'programlevel' => CONTEXT_PROGRAM));
    foreach ($programcontexts as $ctxrecord) {
        context_helper::preload_from_record($ctxrecord);
        $programcontext = context_program::instance($ctxrecord->progid);
        if (!has_capability('totara/certification:deletecertification', $programcontext)) {
            return false;
        }
    }

    return true;
}

/**
 * Returns true if the category has certifications in it
 * (count does not include child categories)
 *
 * @param coursecat $category
 * @return bool
 */
function certif_has_certifications($category) {
    global $DB;
    return $DB->record_exists_sql("SELECT 1 FROM {prog} WHERE category = :category AND certifid IS NOT NULL",
                    array('category' => $category->id));
}
