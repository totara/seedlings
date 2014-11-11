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
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package totara
 * @subpackage program
 */

/**
 * Cron job for managing program schedules
 */

require_once $CFG->dirroot . '/totara/program/lib.php';
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

/**
 * Update programs
 *
 * @return  void
 */
function program_cron() {
    $result = true;

    // Don't run programs cron if programs and certifications are disabled.
    if (totara_feature_disabled('programs') &&
        totara_feature_disabled('certifications')) {
        return false;
    }

    // Run the tasks that should be run once a day
    $result = $result && program_daily_cron();

    // Run the tasks that should be run hourly
    $result = $result && program_hourly_cron();

    return $result;
}

/**
 * Cron tasks that should be run more regularly
 *
 * @return bool Success
 */
function program_hourly_cron() {
    global $CFG;

    $timenow  = time();
    $hourlycron = 60 * 60; // one hour
    $lasthourlycron = get_config(null, 'totara_program_lasthourlycron');

    if ($lasthourlycron && ($timenow - $lasthourlycron <= $hourlycron)) {
        // not enough time has elapsed to rerun hourly cron
        mtrace("No need to run program hourly cron - has already been run recently.");
        if (isset($CFG->debugcron) && $CFG->debugcron) {
            mtrace("DEBUG - running anyway");
        } else {
            return true;
        }
    }

    if (!set_config('totara_program_lasthourlycron', $timenow)) {
        mtrace("Error: could not update lasthourlycron timestamp for program module.");
    }

    program_cron_first_login_assignments();

    program_cron_availability_checks();

    return true;
}

/**
 * Cron tasks that should only be run once a day
 *
 * @return bool Success
 */
function program_daily_cron() {
    global $CFG;

    $timenow  = time();
    $dailycron = 60 * 60 * 24; // one day
    $lastdailycron = get_config(null, 'totara_program_lastdailycron');

    if ($lastdailycron && ($timenow - $lastdailycron <= $dailycron)) {
        // not enough time has elapsed to rerun daily cron
        mtrace("No need to run program daily cron - has already been run today.");
        if (isset($CFG->debugcron) && $CFG->debugcron) {
            mtrace("DEBUG - running anyway");
        } else {
            return true;
        }
    }

    if (!set_config('totara_program_lastdailycron', $timenow)) {
        mtrace("Error: could not update lastdailycron timestamp for program module.");
    }

    // Check for any program completions
    program_cron_completions();

    // Check if any users are due to re-take any recurring programs
    // Note: This should be done before program_cron_user_assignments() as
    // the recurrence task removes assignments so that they can be re-assigned
    // if necessary
    program_cron_recurrence();

    // Check if any courses in recurring programs that were not completed when
    // the recurring course was switched to a newer version of the course
    // have subsequently been completed and mark them as complete in the
    // history table.
    program_cron_recurrence_history();

    // Check that program user assignments are correct
    program_cron_user_assignments();

    // Makes copies of any recurring courses as necessary
    program_cron_copy_recurring_courses();

    // Switches any expired courses with their new courses in recurring programs as necessary
    program_cron_switch_recurring_courses();

    // Sends any messages that are due to be sent
    program_cron_send_messages();

    //tidy up enrolment plugins on courses
    program_cron_clean_enrolment_plugins();

    return true;
}

/**
 * These functions are all globbed together to avoid instantiating programs
 * over and over unnecessarily
 */
function program_cron_send_messages() {

    // this will be populated with programs and keyed with the program id as
    // the programs are instantiated. This will save us having to instantiate
    // the same program more than once
    $programs = array();

    // Send alerts if any programs are due
    program_cron_programs_due($programs);

    // Send alerts if any course sets are due
    program_cron_coursesets_due($programs);

    // Send alerts if any programs are overdue
    program_cron_programs_overdue($programs);

    // Send alerts if any course sets are overdue
    program_cron_coursesets_overdue($programs);

    // Send alerts if any course sets are overdue
    program_cron_learner_followups($programs);

    // Send alerts if any programs have outstanding exceptions
    program_cron_exceptions_raised($programs);
}

/**
 * Checks if the enrolment plugin is enabled in any courses which are part of programs and ensures
 * the plugin is enabled (when required) ore removed (if no longer required)
 *
 * @global object $DB
 */
function program_cron_clean_enrolment_plugins() {
    global $DB;

    //get program enrolment plugin
    $program_plugin = enrol_get_plugin('totara_program');

    //fix courses that are in a courseset but do not have the enrolment plugin
    $program_courses = prog_get_courses_associated_with_programs();
    foreach ($program_courses as $course) {
        $program_plugin->add_instance($course);
    }

    // now the other way round: get courses with the plugin that are NOT in coursesets -
    //need to check if they are linked to a program via a competency
    $params = array('totara_program');
    if (count($program_courses) > 0) {
        list($notinsql, $notinparams) = $DB->get_in_or_equal(array_keys($program_courses), SQL_PARAMS_QM, 'param', false);
        $courseidclause = " AND courseid $notinsql";
        $params = array_merge($params, $notinparams);
    } else {
        $courseidclause = '';
    }
    $sql = "SELECT DISTINCT courseid
                FROM {enrol}
                WHERE enrol = ?
                $courseidclause";
    $unused_program_courses = $DB->get_recordset_sql($sql, $params);
    foreach ($unused_program_courses as $course) {
        $instance = $program_plugin->get_instance_for_course($course->courseid);
        if ($instance) {
            $program_plugin->delete_instance($instance);
        }
    }
}

/**
 * Checks if the enrolenddates in any courses in recurring programs have expired
 * and therefore need to be switched over as the new recurring course in the
 * program.
 *
 * @global object $CFG, $DB
 */
function program_cron_switch_recurring_courses() {
    global $CFG, $DB;

    $debugging = debugging();
    $now = time();

    if ($debugging) {
        mtrace('Checking if any courses in recurring programs have expired and need to be switched');
    }

    $recurring_programs = prog_get_recurring_programs();
    $program_plugin = enrol_get_plugin('totara_program');
    //get_archetype_roles returns an array, get the first element of it
    $studentroles = get_archetype_roles('student');
    $studentrole = array_shift($studentroles);

    foreach ($recurring_programs as $program) {

        $content = $program->get_content();
        $coursesets = $content->get_course_sets();

        // retrieve the recurring course set
        $courseset = $coursesets[0];

        // retrieve the recurring course
        $course = $courseset->course;

        // if the start date of the recurring course is in the future then
        // we don't need to switch over yet
        if ($course->startdate > $now) {
            continue;
        }

        // check that the next course has been created for this program
        if ($recurrence_rec = $DB->get_record('prog_recurrence', array('programid' => $program->id, 'currentcourseid' => $course->id))) {

            // check that the next course actually exists
            if ($newcourse = $DB->get_record('course', array('id' => $recurrence_rec->nextcourseid))) {
                //add the program enrolment plugin to this course
                $instanceid = $program_plugin->add_instance($newcourse);
                $instance = $DB->get_record('enrol', array('id' => $instanceid));

                // Before we set the new course in the program, we have to first save the history
                // record of any users who have not completed the current course and notify
                // those users that the course has been changed so that they can complete
                // the course independently. They can view the record of their complete/incomplete
                // recurring program history via a link in their record of learning.

                // Query to retrieve all the users and their completion status
                $sql = "SELECT pc.id, completionid, completionstatus, u.*
                        FROM (SELECT DISTINCT
                                userid AS id,
                                id AS completionid,
                                status AS completionstatus
                            FROM {prog_completion}
                            WHERE programid = ?
                            AND coursesetid = ?) AS pc
                        JOIN {user} AS u
                        ON pc.id = u.id";

                // get all the users matching the query
                $users = $DB->get_records_sql($sql, array($program->id, 0));
                foreach ($users as $user) {
                    //enrol all users assigned to the program in the new course
                    $program_plugin->enrol_user($instance, $user->id, $studentrole->id);
                    //handle history and messaging for users who did not complete
                    if ($user->completionstatus == STATUS_PROGRAM_INCOMPLETE) {
                        $transaction = $DB->start_delegated_transaction();
                        // copy the existing completion records for the user in to the
                        // history table so that we have a record to show that the
                        // course has not been completed
                        $select = "programid = ? AND userid = ? AND coursesetid = 0";
                        $params = array($program->id, $user->id);
                        $completion_records_history = $DB->get_records_select('prog_completion', $select, $params);
                        $backup_success = true;
                        foreach ($completion_records_history as $completion_record) {
                            // we need to store the id of the course that belonged to this recurring program at the time
                            // it was added to the history table so that we can report on the course history later if necessary
                            $completion_record->recurringcourseid = $course->id;
                            $backup_success = $DB->insert_record('prog_completion_history', $completion_record);
                        }
                        $transaction->allow_commit();

                        // send a message to the user to let them know that the course
                        // has changed and that they haven't completed it
                        $stringmanager = get_string_manager();
                        $messagedata = new stdClass();
                        $messagedata->userto = $user;
                        //stop user from emailing themselves, use support instead
                        $messagedata->userfrom = core_user::get_support_user();
                        $messagedata->subject = $stringmanager->get_string('z:incompleterecurringprogramsubject', 'totara_program', null, $user->lang);
                        $messagedata->fullmessage = $stringmanager->get_string('z:incompleterecurringprogrammessage', 'totara_program', null, $user->lang);
                        $messagedata->contexturl = $CFG->wwwroot . '/course/view.php?id=' . $course->id;
                        $messagedata->contexturlname = $stringmanager->get_string('launchcourse', 'totara_program', null, $user->lang);
                        $messagedata->icon = 'program-update';
                        $messagedata->msgtype = TOTARA_MSG_TYPE_PROGRAM;
                        $result = tm_alert_send($messagedata);
                    }
                }

                // Now we can make the next course visible and set it as the current course in the program
                $courseset->course = $newcourse;
                $DB->update_record('course', (object)array('id' => $newcourse->id, 'visible' => true));
                $courseset->save_set();
            }

            // delete the record from the recurrence table (otherwise the system
            // won't create a new copy of the recurring course when this one
            // expires in the future)
            $DB->delete_records('prog_recurrence', array('programid' => $program->id, 'currentcourseid' => $course->id));

        }
    }
}

/**
 * Checks if any courses in recurring programs are due to have new copies made
 * based on the enrolment end dates of the course. If any are found that need to
 * be copied, a backup and restore is carried out and a record is added to the
 * 'prog_recurrence' table to enable the system to know that the course has been
 * copied.
 *
 * @global object $DB
 */
function program_cron_copy_recurring_courses() {
    global $DB, $USER, $CFG;

    $debugging = debugging();
    $now = time();

    if ($debugging) {
        mtrace('Checking if any courses in recurring programs need to be copied');
    }

    $recurring_programs = prog_get_recurring_programs();

    foreach ($recurring_programs as $program) {

        $content = $program->get_content();
        $coursesets = $content->get_course_sets();

        // retrieve the recurring course set
        $courseset = $coursesets[0];

        // retrieve the recurring course
        $course = $courseset->course;

        // if the start date of the recurring course is too far in the
        // future (based on the recurcreatetime value set by the program creator)
        // we don't need to create the new course yet
         if (($course->startdate + $courseset->recurrencetime - $now) > $courseset->recurcreatetime) {
             continue;
         }

        // check if a course has already been created for this program. If so,
        // and the course actually exists, we don't need to do anything
        if ($recurrence_rec = $DB->get_record('prog_recurrence', array('programid' => $program->id, 'currentcourseid' => $course->id))) {
            if ($DB->record_exists('course', array('id' => $recurrence_rec->nextcourseid))) {
                continue;
            } else {
                // this means the next course must have been deleted so we need to create a new one
                $DB->delete_records('prog_recurrence', array('programid' => $program->id, 'currentcourseid' => $course->id));
            }
        }

        // So if processing has reached this far it means the existing course
        // needs to be backed up and restored to a new course

        //Backup course
        $bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id);
        $bc->update_plan_setting('userscompletion', 0);

        // Set userinfo to false to avoid restoring grades into the new course.
        $plan = $bc->get_plan();
        $settings = $plan->get_settings();
        $sections = $DB->get_fieldset_select('course_sections', 'id', 'course = :cid', array('cid' => $course->id));

        foreach ($sections as $section) {
            $settings["section_{$section}_userinfo"]->set_value(false);
        }

        $bc->execute_plan();

        if ($backupfile = $bc->get_results()) {
            if ($debugging) {
                mtrace("Course '{$course->fullname}' with id {$course->id} successfully backed up");
            }

            $backupfile = $backupfile['backup_destination'];
            $bc->destroy();

            $fullname = $course->fullname;
            if (preg_match('/ ([0-9]{2}\/[0-9]{2}\/[0-9]{4})$/', $fullname)) {
                $fullname = substr($fullname, 0, -11);
            }
            $shortname = $course->shortname;
            if (preg_match('/\-([0-9]{2}\/[0-9]{2}\/[0-9]{4})$/', $shortname)) {
                $shortname = substr($shortname, 0, -12);
            }

            $context = context_course::instance($course->id);

            // Unzip backup to a temporary folder
            $tempfolder = time() . $USER->id;
            check_dir_exists($CFG->dataroot . '/temp/backup');
            $backupfile->extract_to_pathname(get_file_packer(), $CFG->dataroot . '/temp/backup/' . $tempfolder);

            //Execute in transaction to prevent course creation if restore fails
            $transaction = $DB->start_delegated_transaction();

            if ($newcourseid = restore_dbops::create_new_course($fullname, $shortname, $course->category)) {
                $rc = new restore_controller($tempfolder, $newcourseid, backup::INTERACTIVE_NO, backup::MODE_SAMESITE,
                    $USER->id, backup::TARGET_NEW_COURSE);
                $rc->execute_precheck();
                $rc->execute_plan();

                // Update properties of a new course
                $newstartdate = $now + $courseset->recurcreatetime;
                $datestr = userdate($newstartdate, '%d/%m/%Y', null, false);
                $DB->update_record('course', (object)array(
                    'id' => $newcourseid,
                    'shortname' => $shortname . '-' . trim($datestr),
                    'fullname' => $fullname . ' ' . trim($datestr),
                    'icon' => $course->icon,
                    'startdate' => $newstartdate,
                    'visible' => false
                ));

                //Update enrolment dates for each user
                $enrolments = $DB->get_records_sql("
                        SELECT uenr.id
                        FROM
                            {user_enrolments} AS uenr
                        INNER JOIN {enrol} enr
                            ON uenr.enrolid = enr.id
                        WHERE enr.courseid = ?", array($newcourseid));

                foreach ($enrolments as $enrolment) {
                    $DB->update_record('user_enrolments', (object)array(
                        'id' => $enrolment->id,
                        'timestart' => $newstartdate
                    ));
                }

                if ($debugging) {
                    mtrace("Course '{$fullname}' with id {$newcourseid} was successfully restored");
                }

                $transaction->allow_commit();

                // create a new record to enable the system to find the new course
                // when it is time to switch the old course for the new course
                // in the recurring program
                $new_recurrence_rec = new stdClass();
                $new_recurrence_rec->programid = $program->id;
                $new_recurrence_rec->currentcourseid = $course->id;
                $new_recurrence_rec->nextcourseid = $newcourseid;
                $DB->insert_record('prog_recurrence', $new_recurrence_rec);
            } else {
                if ($debugging) {
                    mtrace("Backup file was NOT successfully restored because a new course could not be created to complete the restore");
                }
            }
        } else {
            if ($debugging) {
                mtrace("Course with id {$course->id} was NOT backed up");
            }
        }
    }
}

/**
 * Determine whether or not any users have completed any programs
 *
 * @return void
 */
function program_cron_completions() {
    global $DB;

    if (debugging()) {
        mtrace('Checking program completions');
    }

    // get all programs
    $program_records = $DB->get_records('prog');

    foreach ($program_records as $program_record) {

        $program = new program($program_record->id);

        // get all the users enrolled on this program
        $program_users = $program->get_program_learners();

        if (count($program_users) == 0) {
            continue;
        }

        if (!empty($program_users)) {
            foreach ($program_users as $userid) {
                prog_update_completion($userid, $program);
            }
        }
    }
}

/**
 * Checks that all programs have the correct user assignments and assigns or
 * unassigns as necessary or raises exceptions if issues are found
 */
function program_cron_user_assignments() {
    global $DB;

    if (debugging()) {
        mtrace('Checking program user assignments');
    }

    // get all programs
    $program_records = $DB->get_records('prog');
    foreach ($program_records as $program_record) {
        $program = new program($program_record->id);
        $program->update_learner_assignments();
    }
}

/**
 * Checks if any program due messages need to be sent and sends them
 *
 * @global object $CFG
 * @param array $programs An array of program objects. This is passed by reference so that it can be populated and re-used
 */
function program_cron_programs_due(&$programs) {
    global $DB;

    if (debugging()) {
        mtrace('Checking programs that are due to be completed');
    }

    $now = time();

    // Query to retrieve all users who need to be sent program due messages
    // based on when the program is due to be completed and whether or not
    // there are any program due messages defined by the program with trigger
    // times that match the user's due dates
    $sql = "SELECT u.*, pc.programid, pc.timedue, pm.id AS messageid, pm.triggertime
            FROM {user} AS u
            INNER JOIN {prog_completion} AS pc ON u.id = pc.userid
            INNER JOIN {prog_user_assignment} AS pua ON (pc.userid = pua.userid AND pc.programid = pua.programid)
            INNER JOIN {prog_message} AS pm ON pc.programid = pm.programid
            WHERE pc.timecompleted = ?
            AND pc.coursesetid = ?
            AND pm.messagetype = ?
            AND pc.timedue > 0 AND (pc.timedue - pm.triggertime) < ?
            ORDER BY pc.programid, u.id";

    // Get the records.
    $rs = $DB->get_recordset_sql($sql, array(0, 0, MESSAGETYPE_PROGRAM_DUE, $now));

    foreach ($rs as $user) {
        if (isset($programs[$user->programid])) { // Use the existing program object if it is available.
            $program = $programs[$user->programid];
        } else { // Create a new program object and store it if it has not already been instantiated.
            $program = new program($user->programid);
            $programs[$user->programid] = $program;
        }

        $messagesmanager = $program->get_messagesmanager();
        $messages = $messagesmanager->get_messages();
        $isviewable = $program->is_viewable($user);

        // Send program due notifications to user and (optionally) the user's manager.
        foreach ($messages as $message) {
            if ($message->id == $user->messageid && $message->messagetype == MESSAGETYPE_PROGRAM_DUE && $isviewable) {
                $message->send_message($user);
            }
        }
    }

    $rs->close();
}

/**
 * Checks if any course set due messages need to be sent and sends them
 *
 * @global object $CFG
 * @param array $programs An array of program objects. This is passed by reference so that it can be populated and re-used
 */
function program_cron_coursesets_due(&$programs) {
    global $DB;

    if (debugging()) {
        mtrace('Checking course sets that are due to be completed');
    }

    $now = time();

    // Query to retrieve all users who need to be sent course set due messages
    // based on when the course set is due to be completed and whether or not
    // there are any course set due messages defined by the program with trigger
    // times that match the user's due dates
    $sql = "SELECT u.*, pc.programid, pc.timedue, pm.id AS messageid, pm.triggertime, pc.coursesetid
            FROM {user} AS u
            INNER JOIN {prog_completion} AS pc ON u.id = pc.userid
            INNER JOIN {prog_user_assignment} AS pua ON (pc.userid = pua.userid AND pc.programid = pua.programid)
            INNER JOIN {prog_message} AS pm ON pc.programid = pm.programid
            WHERE pc.timecompleted = ?
            AND pc.coursesetid <> ?
            AND pm.messagetype = ?
            AND pc.timedue > 0 AND (pc.timedue - pm.triggertime) < ?
            ORDER BY pc.programid, u.id";

    // get the records
    $rs = $DB->get_recordset_sql($sql, array(0, 0, MESSAGETYPE_COURSESET_DUE, $now));

    foreach ($rs as $user) {

        if (isset($programs[$user->programid])) { // Use the existing program object if it is available
            $program = $programs[$user->programid];
        } else { // Create a new program object and store it if it has not already been instantiated
            $program = new program($user->programid);
            $programs[$user->programid] = $program;
        }

        $messagesmanager = $program->get_messagesmanager();
        $messages = $messagesmanager->get_messages();
        $isviewable = $program->is_viewable($user);

        // send course set due notifications to user and (optionally) the user's manager
        foreach ($messages as $message) {
            if ($message->id == $user->messageid && $message->messagetype == MESSAGETYPE_COURSESET_DUE && $isviewable) {
                $message->send_message($user, null, array('coursesetid' => $user->coursesetid));
            }
        }
    }

    $rs->close();
}

/**
 * Checks if any program overdue messages need to be sent and sends them
 *
 * @global object $CFG
 * @param array $programs An array of program objects. This is passed by reference so that it can be populated and re-used
 */
function program_cron_programs_overdue(&$programs) {
    global $DB;

    if (debugging()) {
        mtrace('Checking programs that are overdue');
    }

    $now = time();

    // Query to retrieve all users who need to be sent overdue messages
    // based on their program due dates and the trigger dates in any program
    // overdue messages that are defined by the program
    $sql = "SELECT u.*, pc.programid, pc.timedue, pm.id AS messageid, pm.triggertime
            FROM {user} AS u
            INNER JOIN {prog_completion} AS pc ON u.id = pc.userid
            INNER JOIN {prog_user_assignment} AS pua ON (pc.userid = pua.userid AND pc.programid = pua.programid)
            INNER JOIN {prog_message} AS pm ON pc.programid = pm.programid
            WHERE pc.timecompleted = ?
            AND pc.coursesetid = ?
            AND pm.messagetype = ?
            AND pc.timedue > 0 AND (pc.timedue + pm.triggertime) < ?
            ORDER BY pc.programid, u.id";

    // get the records
    $rs = $DB->get_recordset_sql($sql, array(0, 0, MESSAGETYPE_PROGRAM_OVERDUE, $now));

    foreach ($rs as $user) {

        if (isset($programs[$user->programid])) { // Use the existing program object if it is available
            $program = $programs[$user->programid];
        } else { // Create a new program object and store it if it has not already been instantiated
            $program = new program($user->programid);
            $programs[$user->programid] = $program;
        }

        $messagesmanager = $program->get_messagesmanager();
        $messages = $messagesmanager->get_messages();
        $isviewable = $program->is_viewable($user);

        // send program overdue notifications to user and (optionally) the user's manager
        foreach ($messages as $message) {
            if ($message->id == $user->messageid && $message->messagetype == MESSAGETYPE_PROGRAM_OVERDUE && $isviewable) {
                $message->send_message($user);
            }
        }
    }

    $rs->close();
}

/**
 * Checks if any course set overdue messages need to be sent and sends them
 *
 * @global object $CFG
 * @param array $programs An array of program objects. This is passed by reference so that it can be populated and re-used
 */
function program_cron_coursesets_overdue(&$programs) {
    global $DB;

    if (debugging()) {
        mtrace('Checking course sets that are overdue');
    }

    $now = time();

    // Query to retrieve all users who need to be sent overdue messages
    // based on their course set due dates and the trigger dates in any course set
    // overdue messages that are defined by the program
    $sql = "SELECT u.*, pc.programid, pc.timedue, pm.id AS messageid, pm.triggertime, pc.coursesetid
            FROM {user} AS u
            INNER JOIN {prog_completion} AS pc ON u.id = pc.userid
            INNER JOIN {prog_user_assignment} AS pua ON (pc.userid = pua.userid AND pc.programid = pua.programid)
            INNER JOIN {prog_message} AS pm ON pc.programid = pm.programid
            WHERE pc.timecompleted = ?
            AND pc.coursesetid <> ?
            AND pm.messagetype = ?
            AND pc.timedue > 0 AND (pc.timedue + pm.triggertime) < ?
            ORDER BY pc.programid, u.id";

    // get the records
    $rs = $DB->get_recordset_sql($sql, array(0, 0, MESSAGETYPE_COURSESET_OVERDUE, $now));

    foreach ($rs as $user) {

        if (isset($programs[$user->programid])) { // Use the existing program object if it is available
            $program = $programs[$user->programid];
        } else { // Create a new program object and store it if it has not already been instantiated
            $program = new program($user->programid);
            $programs[$user->programid] = $program;
        }

        $messagesmanager = $program->get_messagesmanager();
        $messages = $messagesmanager->get_messages();
        $isviewable = $program->is_viewable($user);

        // send course set overdue notifications to user and (optionally) the user's manager
        foreach ($messages as $message) {
            if ($message->id == $user->messageid && $message->messagetype == MESSAGETYPE_COURSESET_OVERDUE && $isviewable) {
                $message->send_message($user, null, array('coursesetid' => $user->coursesetid));
            }
        }
    }

    $rs->close();
}

/**
 * Checks if any course set overdue messages need to be sent and sends them
 *
 * @global object $CFG
 * @param array $programs An array of program objects. This is passed by reference so that it can be populated and re-used
 */
function program_cron_learner_followups(&$programs) {
    global $DB;

    if (debugging()) {
        mtrace('Checking for any follow-up messages to be sent');
    }

    $now = time();

    // Query to retrieve all users who need to be sent follow-up messages
    // based on their course completion dates and the trigger dates in any
    // follow-up messages that are defined by the program
    $sql = "SELECT u.id, pc.programid, pc.timecompleted, pm.id AS messageid, pm.triggertime
            FROM {user} AS u
            INNER JOIN {prog_completion} AS pc ON u.id = pc.userid
            INNER JOIN {prog_user_assignment} AS pua ON (pc.userid = pua.userid AND pc.programid = pua.programid)
            INNER JOIN {prog_message} AS pm ON pc.programid = pm.programid
            WHERE pc.status = ?
            AND pm.messagetype = ?
            AND (pc.timecompleted + pm.triggertime) < ?
            ORDER BY pc.programid, u.id";

    // get the records
    $rs = $DB->get_recordset_sql($sql, array(STATUS_PROGRAM_COMPLETE, MESSAGETYPE_LEARNER_FOLLOWUP, $now));

    foreach ($rs as $user) {

        if (isset($programs[$user->programid])) { // Use the existing program object if it is available
            $program = $programs[$user->programid];
        } else { // Create a new program object and store it if it has not already been instantiated
            $program = new program($user->programid);
            $programs[$user->programid] = $program;
        }

        $messagesmanager = $program->get_messagesmanager();
        $messages = $messagesmanager->get_messages();
        $isviewable = $program->is_viewable($user);

        // send course set overdue notifications to user and (optionally) the user's manager
        foreach ($messages as $message) {
            if ($message->id == $user->messageid && $message->messagetype == MESSAGETYPE_LEARNER_FOLLOWUP && $isviewable) {
                $message->send_message($user);
            }
        }
    }

    $rs->close();
}

/**
 * Checks if any unhandled exceptions exist in any programs and send an alert to the admin
 *
 * @global object $CFG
 * @param array $programs An array of program objects. This is passed by reference so that it can be populated and re-used
 */
function program_cron_exceptions_raised(&$programs) {
    global $DB;

    if (debugging()) {
        mtrace('Checking if any exceptions exist');
    }

    if (!$admin = get_admin()) {
        mtrace('Unable to determine admin user in program_cron_exceptions_raised. Not checking for exceptions.');
        return;
    }

    // Query to retrieve any programs that have unhandled exceptions
    $sql = "SELECT DISTINCT(p.id) AS id
            FROM {prog} AS p
            JOIN {prog_exception} AS pe
               ON p.id = pe.programid
            WHERE p.exceptionssent = ?";

    $progsfound = $DB->get_records_sql($sql, array(0));

    foreach ($progsfound as $progfound) {

        if (isset($programs[$progfound->id])) { // Use the existing program object if it is available
            $program = $programs[$progfound->id];
        } else { // Create a new program object and store it if it has not already been instantiated
            $program = new program($progfound->id);
            $programs[$progfound->id] = $program;
        }

        $messagesmanager = $program->get_messagesmanager();
        $messages = $messagesmanager->get_messages();

        // send alerts for each program to the admin user

        foreach ($messages as $message) {
            if ($message->messagetype == MESSAGETYPE_EXCEPTION_REPORT) {

                // Update program with exceptions sent
                $prog_notify_todb = new stdClass;
                $prog_notify_todb->id = $message->programid;
                $prog_notify_todb->exceptionssent = 1;
                $DB->update_record('prog', $prog_notify_todb);

                $message->send_message($admin);
            }
        }
    }

}



/**
 * Loops through all the existing recurring programs and finds any users who
 * have completed the program and are due to re-take it. It then backs up the
 * completion history for the user and unassigns the user from the program so
 * that the user will be re-assigned the next time the cron task to assign
 * learners is run
 *
 * @global <type> $CFG
 */
function program_cron_recurrence() {
    global $DB;

    if (debugging()) {
        mtrace('Checking program recurrence');
    }

    $recurring_programs = prog_get_recurring_programs();

    foreach ($recurring_programs as $program) {

        $content = $program->get_content();
        $coursesets = $content->get_course_sets();

        // retrieve the recurring course set
        $courseset = $coursesets[0];

        // retrieve the recurring course
        $recurringcourse = $courseset->course;

        $now = time();
        $recurrencetime = $courseset->recurrencetime;
        $recurrencetime_comparison = $now - $recurrencetime;

        // Query to retrieve all the users assigned to this program (i.e. as
        // part of their required learning) who have completed the program
        // and whose completion dates are beyond the recurrence time period
        $sql = "SELECT pcpua.id, completionid, userassignmentid, assignmentid, u.*
                FROM {user} AS u
                JOIN (SELECT DISTINCT
                        pc.userid AS id,
                        pc.id AS completionid,
                        pua.id AS userassignmentid,
                        pua.assignmentid
                    FROM {prog_completion} AS pc
                    JOIN {prog_user_assignment} AS pua
                    ON pc.userid = pua.userid
                    WHERE pc.programid = ?
                    AND pc.status = ?
                    AND pc.coursesetid = ?
                    AND pc.timecompleted < ?) AS pcpua
                ON u.id = pcpua.id";

        // get all the users matching the query
        $users = $DB->get_records_sql($sql, array($program->id, STATUS_PROGRAM_COMPLETE, 0, $recurrencetime_comparison));
        foreach ($users as $user) {

            $transaction = $DB->start_delegated_transaction();

            // copy the existing completion records for the user in to a
            // history table so that we have a record of past completions
            $select = "programid = ? AND userid = ?";
            $params = array($program->id, $user->id);
            $completion_records_history = $DB->get_records_select('prog_completion', $select, $params);
            $backup_success = true;
            foreach ($completion_records_history as $completion_record) {
                // we need to store the id of the course that belonged to this recurring program at the time
                // it was added to the history table so that we can report on the course history later if necessary
                $completion_record->recurringcourseid = $recurringcourse->id;

                $DB->insert_record('prog_completion_history', $completion_record);
            }

            // delete all the previous completion records for this user in this program.
            // A new completion record will be added when the user is re-assigned when the
            // assignments cron task runs
            $DB->delete_records('prog_completion', array('programid' => $program->id, 'userid' => $user->id));

            // delete the user's assignment record for this program.
            // This will be re-created and the user will be re-assigned to the program
            // when the assignments cron task runs
            $DB->delete_records('prog_user_assignment', array('programid' => $program->id, 'userid' => $user->id));

            $transaction->allow_commit();
        }
    }
}

/**
 * Finds any users in the 'prog_completion_history' table who have incomplete
 * recurring programs and checks if the course that belonged to the program at
 * the time when the entry was added to the table has since been completed.
 *
 */
function program_cron_recurrence_history() {
    global $DB;
    if (debugging()) {
        mtrace('Checking program recurrence history');
    }

    $history_records = $DB->get_records('prog_completion_history', array('status' => STATUS_PROGRAM_INCOMPLETE));

    foreach ($history_records as $history_record) {

        if ($course = $DB->get_record('course', array('id' => $history_record->recurringcourseid))) {

            // create a new completion object for this course
            $completion_info = new completion_info($course);
            // check if the course is complete
            if ($completion_info->is_course_complete($history_record->userid)) {
                $history_record->status = STATUS_PROGRAM_COMPLETE;
                $history_record->timecompleted = time();
                $DB->update_record('prog_completion_history', $history_record);
            }
        }
    }
}


/**
 * Looks for users with future assignment records who have logged in
 *
 * If any are found an event is triggered to activate the future assignment.
 * This function should only be needed to catch logins via third-party
 * authentication plugins, since all the existing auth plugins have had an
 * event trigger added.
 */
function program_cron_first_login_assignments() {
    global $DB;
    $pending_user_sql = "SELECT u.*, pfa.programid
                        FROM {user} u
                        INNER JOIN {prog_future_user_assignment} pfa
                        ON pfa.userid = u.id
                        WHERE u.firstaccess > 0";

    $pending_users = $DB->get_records_sql($pending_user_sql);
    foreach ($pending_users as $pending_user) {
        // Skip update if the program is not accesible for the user.
        $program = new program($pending_user->programid);
        if ($program->is_viewable($pending_user)) {
            prog_assignments_firstlogin($pending_user);
        }
    }
}

/**
 * Checks whether programs are available or not
 * if they are switched to unavailable checks course enrolments
 */
function program_cron_availability_checks() {
    global $PAGE, $DB;

    if (CLI_SCRIPT) {
        mtrace('Checking programs availability');
    }

    $now = time();
    $debugging = debugging();
    $unavailable = $DB->get_records('prog', array('available' => AVAILABILITY_NOT_TO_STUDENTS));
    $available = $DB->get_records('prog', array('available' => AVAILABILITY_TO_STUDENTS));
    $program_plugin = enrol_get_plugin('totara_program');

    // Check unavailable programs haven't become available.
    foreach ($unavailable as $program) {
        if (CLI_SCRIPT && $debugging) {
            mtrace("Checking if Program-{$program->id} is still unavailable...");
        }

        if ((!empty($program->availablefrom) && $program->availablefrom <= $now) &&
            (!empty($program->availableuntil) && $program->availableuntil >= $now)) {

            if (CLI_SCRIPT && $debugging) {
                mtrace("Marking Program-{$program->id} as available.");
            }

            // Mark program as available.
            $program->available = AVAILABILITY_TO_STUDENTS;
            $DB->update_record('prog', $program);
        }
    }

    // Check available programs haven't become unavailable.
    foreach ($available as $program) {
        if (CLI_SCRIPT && $debugging) {
            mtrace("Checking if Program-{$program->id} is still available...");
        }

        if ((!empty($program->availablefrom) && $program->availablefrom >= $now) ||
            (!empty($program->availableuntil) && $program->availableuntil <= $now)) {

                if (CLI_SCRIPT && $debugging) {
                    mtrace("Marking Program-{$program->id} as unavailable...");
                }

                // Mark program as unavailable.
                $program->available = AVAILABILITY_NOT_TO_STUDENTS;
                $DB->update_record('prog', $program);

                // Update course enrolments for the program.
                prog_update_available_enrolments($program_plugin, $program->id, $debugging);
        }
    }
}
