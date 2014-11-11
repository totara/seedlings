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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage program
 */

defined('MOODLE_INTERNAL') || die();

class totara_program_observer {

    /**
     * Handler function called when a program_assigned event is triggered
     *
     * @param \totara_program\event\program_assigned $event
     * @return bool Success status
     */
    public static function assigned(\totara_program\event\program_assigned $event) {
        global $DB;

        $programid = $event->objectid;
        $userid = $event->userid;

        try {
            $messagesmanager = new prog_messages_manager($programid);
            $program = new program($programid);
            $user = $DB->get_record('user', array('id' => $userid));
            $isviewable = $program->is_viewable($user);
            $messages = $messagesmanager->get_messages();
            $completion = $DB->get_field('prog_completion', 'status', array('programid' => $programid, 'userid' => $userid, 'coursesetid' => 0));
        } catch (exception $e) {
            return true;
        }

        // Send notifications to user and (optionally) the user's manager.
        foreach ($messages as $message) {
            if ($message->messagetype == MESSAGETYPE_ENROLMENT) {
                if ($user && $completion != STATUS_PROGRAM_COMPLETE && $isviewable) {
                    $message->send_message($user);
                }
            }
        }
        return true;
    }

    /**
     * Handler function called when a program_unassigned event is triggered
     *
     * @param \totara_program\event\program_unassigned $event
     * @return bool Success status
     */
    public static function unassigned(\totara_program\event\program_unassigned $event) {
        global $DB;

        $programid = $event->objectid;
        $userid = $event->userid;

        try {
            $messagesmanager = new prog_messages_manager($programid);
            $program = new program($programid);
            $user = $DB->get_record('user', array('id' => $userid));
            $isviewable = $program->is_viewable($user);
            $messages = $messagesmanager->get_messages();
        } catch (ProgramException $e) {
            return true;
        }

        // Send notifications to user and (optionally) the user's manager.
        foreach ($messages as $message) {
            if ($message->messagetype == MESSAGETYPE_UNENROLMENT) {
                if ($user && $isviewable) {
                    $message->send_message($user);
                }
            }
        }

        return true;
    }

    /**
     * Handler function called when a program_completed event is triggered
     *
     * @param \totara_program\event\program_completed $event
     * @return bool Success status
     */
    public static function completed(\totara_program\event\program_completed $event) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/totara/plan/lib.php');

        $programid = $event->objectid;
        $userid = $event->userid;

        try {
            $messagesmanager = new prog_messages_manager($programid);
            $program = new program($programid);
            $user = $DB->get_record('user', array('id' => $userid));
            $isviewable = $program->is_viewable($user);
            $messages = $messagesmanager->get_messages();
        } catch (ProgramException $e) {
            return true;
        }

        // Send notification to user.
        foreach ($messages as $message) {
            if ($message->messagetype == MESSAGETYPE_PROGRAM_COMPLETED) {
                if ($user && $isviewable) {
                    $message->send_message($user);
                }
            }
        }

        // Auto plan completion hook.
        dp_plan_item_updated($userid, 'program', $programid);

        return true;
    }

    /**
     * Handler function called when a courseset_completed event is triggered
     *
     * @param \totara_program\event\program_courseset_completed $event
     * @return bool Success status
     */
    public static function courseset_completed(\totara_program\event\program_courseset_completed $event) {
        global $DB;

        $programid = $event->objectid;
        $userid = $event->userid;
        $coursesetid = $event->other['coursesetid'];

        try {
            $messagesmanager = new prog_messages_manager($programid);
            $messages = $messagesmanager->get_messages();
        } catch (ProgramException $e) {
            return true;
        }

        // Send notification to user.
        foreach ($messages as $message) {
            if ($message->messagetype == MESSAGETYPE_COURSESET_COMPLETED) {
                if ($user = $DB->get_record('user', array('id' => $userid))) {
                    $message->send_message($user, null, array('coursesetid' => $coursesetid));
                }
            }
        }

        return true;
    }

    /**
     * Event that is triggered when a user is deleted.
     *
     * Cancels a user from any programs they are associated with, tables to clear are
     * prog_assignment
     * prog_future_user_assignment
     * prog_user_assignment
     * prog_exception
     * prog_extension
     * prog_messagelog
     *
     * @param \core\event\user_deleted $event
     *
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB;

        $userid = $event->objectid;

        // We don't want to send messages or anything so just wipe the records from the DB.
        $transaction = $DB->start_delegated_transaction();

        // Delete all the individual assignments for the user.
        $DB->delete_records('prog_assignment', array('assignmenttype' => ASSIGNTYPE_INDIVIDUAL, 'assignmenttypeid' => $userid));

        // Delete any future assignments for the user.
        $DB->delete_records('prog_future_user_assignment', array('userid' => $userid));

        // Delete all the program user assignments for the user.
        $DB->delete_records('prog_user_assignment', array('userid' => $userid));

        // Delete all the program exceptions for the user.
        $DB->delete_records('prog_exception', array('userid' => $userid));

        // Delete all the program extensions for the user.
        $DB->delete_records('prog_extension', array('userid' => $userid));

        // Delete all the program message logs for the user.
        $DB->delete_records('prog_messagelog', array('userid' => $userid));

        $transaction->allow_commit();
    }

    /*
     * This function is to cope with program assignments set up
     * with completion deadlines 'from first login' where the
     * user had not yet logged in.
     *
     * @param \totara_core\event\user_firstlogin $event
     * @return boolean True if all the update_learner_assignments() succeeded or there was nothing to do
     */
    public static function assignments_firstlogin(\totara_core\event\user_firstlogin $event) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/program/lib.php');

        prog_assignments_firstlogin($DB->get_record('user', array('id' => $event->objectid)));

        return true;
    }
}
