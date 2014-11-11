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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot . '/totara/program/program_content.class.php');
require_once($CFG->dirroot . '/totara/program/program_courseset.class.php');
require_once($CFG->dirroot . '/totara/program/program_assignments.class.php');
require_once($CFG->dirroot . '/totara/program/program_messages.class.php');
require_once($CFG->dirroot . '/totara/program/program_message.class.php');
require_once($CFG->dirroot . '/totara/program/program_exceptions.class.php');
require_once($CFG->dirroot . '/totara/program/program_exception.class.php');
require_once($CFG->dirroot . '/totara/program/program_user_assignment.class.php');

define('STATUS_PROGRAM_INCOMPLETE', 0);
define('STATUS_PROGRAM_COMPLETE', 1);
define('STATUS_COURSESET_INCOMPLETE', 2);
define('STATUS_COURSESET_COMPLETE', 3);

define('TIME_SELECTOR_HOURS', 1);
define('TIME_SELECTOR_DAYS', 2);
define('TIME_SELECTOR_WEEKS', 3);
define('TIME_SELECTOR_MONTHS', 4);
define('TIME_SELECTOR_YEARS', 5);
define('TIME_SELECTOR_INFINITY', 6); // To infinity and beyond!!

define('DURATION_MINUTE', 60);
define('DURATION_HOUR',   60 * DURATION_MINUTE);
define('DURATION_DAY',    24 * DURATION_HOUR);
define('DURATION_WEEK',   7  * DURATION_DAY);
define('DURATION_MONTH',  30 * DURATION_DAY);
define('DURATION_YEAR',   365 * DURATION_DAY);

define('AVAILABILITY_NOT_TO_STUDENTS',0);
define('AVAILABILITY_TO_STUDENTS', 1);

define('PROGRAM_EXCEPTION_NONE', 0);
define('PROGRAM_EXCEPTION_RAISED', 1);
define('PROGRAM_EXCEPTION_DISMISSED', 2);
define('PROGRAM_EXCEPTION_RESOLVED', 3);

define('PROG_EXTENSION_GRANT', 1);
define('PROG_EXTENSION_DENY', 2);

global $TIMEALLOWANCESTRINGS;

$TIMEALLOWANCESTRINGS = array(
    TIME_SELECTOR_HOURS => 'hours',
    TIME_SELECTOR_DAYS => 'days',
    TIME_SELECTOR_WEEKS => 'weeks',
    TIME_SELECTOR_MONTHS => 'months',
    TIME_SELECTOR_YEARS => 'years',
    TIME_SELECTOR_INFINITY => 'infinity',
);


/**
 * Quick and light function for returning a program context
 *
 * @access  public
 * @param   $int    integer     Program id
 * @return  object  context instance
 */
function program_get_context($id) {
    // Quickly get context from program id
    return context_program::instance($id);
}


class program {

    public $id, $category, $sortorder, $fullname, $shortname;
    public $idnumber, $summary, $endnote, $visible;
    public $availablefrom, $availableuntil, $available;
    public $timecreated, $timemodified, $usermodified, $icon;
    public $content;

    protected $assignments, $messagesmanager;
    protected $exceptionsmanager, $context, $studentroleid;

    function __construct($id) {
        global $CFG, $DB;

        // get program db record
        $program = $DB->get_record('prog', array('id' => $id));

        if (!$program) {
            throw new ProgramException(get_string('programidnotfound', 'totara_program', $id));
        }

        // set details about this program
        $this->id = $id;
        $this->category = $program->category;
        $this->sortorder = $program->sortorder;
        $this->fullname = $program->fullname;
        $this->shortname = $program->shortname;
        $this->idnumber = $program->idnumber;
        $this->summary = $program->summary;
        $this->endnote = $program->endnote;
        $this->visible = $program->visible;
        $this->availablefrom = $program->availablefrom;
        $this->availableuntil = $program->availableuntil;
        $this->available = $program->available;
        $this->timecreated = $program->timecreated;
        $this->timemodified = $program->timemodified;
        $this->usermodified = $program->usermodified;
        $this->icon = $program->icon;
        $this->audiencevisible = $program->audiencevisible;
        $this->certifid = $program->certifid;

        $this->content = new prog_content($id);
        $this->assignments = new prog_assignments($id);

        $this->messagesmanager = new prog_messages_manager($id);
        $this->exceptionsmanager = new prog_exceptions_manager($id);

        $this->context = context_program::instance($this->id);
        $this->studentroleid = $CFG->learnerroleid;

        if (!$this->studentroleid) {
            print_error('error:failedtofindstudentrole', 'totara_program');
        }
    }

    public function get_content() {
        return $this->content;
    }

    public function get_context() {
        return $this->context;
    }

    public function get_assignments() {
        return $this->assignments;
    }

    public function get_messagesmanager() {
        return $this->messagesmanager;
    }

    public function get_exceptionsmanager() {
        return $this->exceptionsmanager;
    }

    /**
     * Deletes an entire program and all its data (content, assignments,
     * messages, exceptions)
     *
     * @return bool Success
     */
    public function delete() {
        global $DB, $USER;
        $result = true;

        // First delete this program from any users' learning plans
        // We do this before calling begin_sql() as we need these records to be
        // fully removed from the database before we call $this->unassign_learners()
        // or the users won't be properly unassigned
        $result = $result && $DB->delete_records('dp_plan_program_assign', array('programid' => $this->id));

        // Get all users who are automatically assigned, as we want to unassign them all.
        $users_to_unassign = $DB->get_fieldset_sql("SELECT DISTINCT userid FROM {prog_user_assignment} WHERE programid = ?", array($this->id));

        $transaction = $DB->start_delegated_transaction();

        // Unassign the users.
        if (!empty($users_to_unassign)) {
            $this->unassign_learners($users_to_unassign);
        }

        // delete all exceptions and exceptions data
        $this->exceptionsmanager->delete();

        // delete all messages and the log of sent messages
        $this->messagesmanager->delete();

        // delete all assignments
        $this->assignments->delete();

        // delete all content
        $this->content->delete();

        // delete certification
        if ($this->certifid) {
            certif_delete(CERTIFTYPE_PROGRAM, $this->certifid);
        }

        // delete the program itself
        $DB->delete_records('prog', array('id' => $this->id));

        $transaction->allow_commit();

        $event = \totara_program\event\program_deleted::create(
            array(
                'objectid' => $this->id,
                'context' => context_program::instance($this->id),
                'userid' => $USER->id,
                'other' => array(
                    'certifid' => empty($this->certifid) ? 0 : $this->certifid,
                ),
            )
        );
        $event->trigger();

        return true;
    }


    /**
     * Deletes the completion records for the program for the specified user.
     *
     * @param int $userid
     * @param bool $deletecompleted Whether to force deletion of records for completed programs
     * @return bool Deletion true|Exception
     */
    public function delete_completion_record($userid, $deletecompleted=false) {
        global $DB;

        if ($deletecompleted === true || ! $this->is_program_complete($userid)) {
            $DB->delete_records('prog_completion', array('programid' => $this->id, 'userid' => $userid));
        }

        return true;
    }

    /**
     * Checks all the assignments for the program and assigns and unassigns
     * learners to the program if they meet or don't meet the current
     * assignment criteria.
     *
     * Under certain conditions (e.g. completion date not allowing enough time
     * for a student to complete the program) users will not be assigned to the
     * program and exceptions will be raised instead.
     *
     * @return bool
     */
    public function update_learner_assignments() {
        global $DB, $ASSIGNMENT_CATEGORY_CLASSNAMES;

        // Get program assignments
        $prog_assignments = $this->assignments->get_assignments();
        if (!$prog_assignments) {
            $prog_assignments = $DB->get_records('prog_assignment', array('programid' => $this->id));
        }

        // Get user assignments only if there assignments for this program
        if ($prog_assignments) {
            $user_assignments = $DB->get_records('prog_user_assignment', array('programid' => $this->id), 'timeassigned DESC, userid');
        }

        $assigned_user_ids = array();
        $active_assignments = array();
        $message_queue = array();

        if ($prog_assignments) {
            foreach ($prog_assignments as $assign) {
                $active_assignments[] = $assign->id;
                $newassignusers = array();
                $newassigncount = 0;
                $fassignusers = array();
                $fassigncount = 0;

                // Create instance of assignment type so we can call functions on it.
                $assignment_class = new $ASSIGNMENT_CATEGORY_CLASSNAMES[$assign->assignmenttype]();

                // Get affected users.
                $affected_users = $assignment_class->get_affected_users_by_assignment($assign);

                // Delete user assigned that no longer match the category assignment.
                if (!empty($user_assignments)) {
                    // Get prog_user_assignment records for this assignment.
                    $select = "programid = ? AND assignmentid = ?";
                    $params = array($this->id, $assign->id);
                    $userassignments = $DB->get_fieldset_select('prog_user_assignment', 'userid', $select, $params);

                    // Transform $affected_users into an array of ids.
                    $useraffected = array();
                    foreach ($affected_users as $user) {
                        $useraffected[] = $user->id;
                    }

                    // Get users that no longer match the category assignment.
                    $userstoremove = array_diff($userassignments, $useraffected);

                    if (!empty($userstoremove)) {
                        $assignment_class->remove_outdated_assignments($this->id, $assign->assignmenttypeid, $userstoremove);
                    }
                }

                // Get user assignments for current assignment.
                foreach ($affected_users as $user) {
                    $assigned_user_ids[] = $user->id;

                    // Get the timedue from program completion as it may contain an extension date,
                    // when that extension date is set (approved by the manager), then due date should be that one.
                    $params = array ('programid' => $this->id, 'userid' => $user->id);
                    $timedue = $DB->get_field('prog_completion', 'timedue', $params);
                    $timedue = $this->make_timedue($user->id, $assign, $timedue);

                    $user_exists = false;
                    $user_assign_data = null;
                    foreach ($user_assignments as $ua) {
                        // Check user exists for current assignment
                        if ($user->id == $ua->userid && $ua->assignmentid == $assign->id) {
                            $sendmessage = false;

                            $exception = $DB->get_record('prog_exception', array('userid' => $ua->userid, 'assignmentid' => $ua->assignmentid));

                            if (!empty($exception) && $exception->timeraised == $ua->timeassigned) {
                                // This exception was raised the first time they were assigned, they haven't received an assignment message yet.
                                $sendmessage = true;
                            }

                            $user_exists = true;
                            $user_assign_data = $ua;
                            break;
                        }
                    }

                    if ($user_exists) {
                        // Check if updates need to be made

                        // Create user assignment object
                        $current_assignment = new user_assignment($user->id, $user_assign_data->assignmentid, $this->id);

                        // Skip resolved and dismissed exceptions to allow users to override group duedates.
                        $skipstatus = array(PROGRAM_EXCEPTION_RESOLVED, PROGRAM_EXCEPTION_DISMISSED);
                        if (isset($user_assign_data->exceptionstatus) && in_array($user_assign_data->exceptionstatus, $skipstatus)) {
                            continue;
                        }

                        if (!empty($current_assignment->completion) && $timedue != $current_assignment->completion->timedue) {
                            // The timedue has changed, we'll need to update it and check for exceptions.

                            if ($assign->completionevent == COMPLETION_EVENT_FIRST_LOGIN && $timedue === false) {
                                // this means that the user hasn't logged in yet
                                // create a future assignment so we can assign them when they do login
                                $fassigncount++;
                                $fassignusers[$user->id] = $user->id;
                                if ($fassigncount == BATCH_INSERT_MAX_ROW_COUNT) {
                                    $this->create_future_assignments_bulk($this->id, $fassignusers, $assign->id);
                                    $fassigncount = 0;
                                    $fassignusers = array();
                                }
                                continue;
                            }

                            $exceptions = $this->update_exceptions($user->id, $assign, $timedue);
                            // Update user assignment including checking for exceptions
                            if ($current_assignment->update($timedue)) {
                                $user_assign_todb = new stdClass();
                                $user_assign_todb->id = $user_assign_data->id;
                                $user_assign_todb->exceptionstatus = $exceptions ? PROGRAM_EXCEPTION_RAISED : PROGRAM_EXCEPTION_NONE;

                                $DB->update_record('prog_user_assignment', $user_assign_todb);
                            }

                            if (empty($exceptions) && $sendmessage) {
                                // Add the user to the message queue so they'll receive an assignment message.
                                $eventdata = new stdClass();
                                $eventdata->programid = $this->id;
                                $eventdata->userid = $user->id;

                                $queue = array("new:{$user->id}" => $eventdata);
                                $message_queue = array_merge($message_queue, $queue);
                            }
                        }
                    } else {
                        if ($assign->completionevent == COMPLETION_EVENT_FIRST_LOGIN && $timedue === false) {
                            // this means that the user hasn't logged in yet
                            // create a future assignment so we can assign them when they do login
                            $fassigncount++;
                            $fassignusers[$user->id] = $user->id;
                            if ($fassigncount == BATCH_INSERT_MAX_ROW_COUNT) {
                                $this->create_future_assignments_bulk($this->id, $fassignusers, $assign->id);
                                $fassigncount = 0;
                                $fassignusers = array();
                            }
                            continue;
                        }

                        $exceptions = $this->update_exceptions($user->id, $assign, $timedue);

                        // No record in user_assignment we need to make one
                        $newassigncount++;
                        $newassignusers[$user->id] = array('timedue' => $timedue, 'exceptions' => $exceptions);
                        if ($newassigncount == BATCH_INSERT_MAX_ROW_COUNT) {
                            $new_queue = $this->assign_learners_bulk($newassignusers, $assign);
                            $message_queue = array_merge($message_queue, $new_queue);
                            $newassigncount = 0;
                            $newassignusers = array();
                        }
                    }
                }
                if (!empty($fassignusers)) {
                    // bulk assign remaining future users
                    $this->create_future_assignments_bulk($this->id, $fassignusers, $assign->id);
                    unset($fassigncount, $fassignusers);
                }
                if (!empty($newassignusers)) {
                    // bulk assign remaining users
                    $new_queue = $this->assign_learners_bulk($newassignusers, $assign);
                    $message_queue = array_merge($message_queue, $new_queue);
                    unset($newassignusers, $newassigncount);
                }

            }
        }

        if (!empty($message_queue)) {
            // Send all the queued messages.
            foreach ($message_queue as $userid => $eventdata) {

                $event = \totara_program\event\program_assigned::create(
                    array(
                        'objectid' => $eventdata->programid,
                        'context' => context_program::instance($eventdata->programid),
                        'userid' => $eventdata->userid,
                    )
                );
                $event->trigger();
            }
        }

        // Get an array of user ids, of the users who should be in this program
        $users_who_should_be_assigned = $assigned_user_ids;

        if (count($users_who_should_be_assigned) > 0) {
            // Get a list of user ids that are ALREADY assigned, but shouldn't be anymore
            list($usql, $params) = $DB->get_in_or_equal($users_who_should_be_assigned, SQL_PARAMS_QM, '', false);
            $alreadyassigned_sql = "userid {$usql} AND";
        } else {
            // Get all users who are assigned, as we want to unassign them all
            $alreadyassigned_sql = '';
            $params = array();
        }

        $params[] = $this->studentroleid;
        $params[] = $this->context->id;

        $users_to_unassign = $DB->get_records_select('role_assignments', "{$alreadyassigned_sql} roleid = ? AND contextid = ?" , $params, '', 'userid as id');

        if ($users_to_unassign) {
            $this->unassign_learners(array_keys($users_to_unassign));
        }

        // Users can have multiple assignments to a program
        // We need this to clean up unnecessary redundant assignments caused
        // when removing an assignment type
        if (count($active_assignments) > 0) {
            list($usql, $params) = $DB->get_in_or_equal($active_assignments, SQL_PARAMS_QM, '', false);
            $params[] = $this->id;

            $DB->delete_records_select('prog_user_assignment', "assignmentid {$usql} AND programid = ?", $params);

            // delete any future_user_assignment records too that are related to
            // assignment types that have been deleted too
            $DB->delete_records_select('prog_future_user_assignment', "assignmentid {$usql} AND programid = ?", $params);
        }

        return true;
    }

    /**
     * Bulk create records in the future assignment table
     *
     * Used to track an assignment that cannot be made yet, but will be added
     * at some later time (e.g. first login assignments which will be applied the
     * first time the user logs in).
     *
     * @param integer $programid ID of the program
     * @param integer $userids IDs of the user being assigned
     * @param integer $assignment ID of the assignment (record in prog_assignment table)
     *
     * @return boolean True if the future assignment is saved successfully or already exists
     */
    function create_future_assignments_bulk($programid, $userids, $assignmentid) {
        global $DB;

        list($sqlin, $sqlparams) = $DB->get_in_or_equal($userids);
        $sqlparams[] = $programid;
        $sqlparams[] = $assignmentid;
        $sql = "SELECT u.id
            FROM {user} u
            WHERE u.id {$sqlin}
            AND u.id NOT IN (
                SELECT userid
                FROM {prog_future_user_assignment}
                WHERE programid = ?
                AND assignmentid = ?
            )";
        $users = $DB->get_records_sql($sql, $sqlparams);
        if (empty($users)) {
            return true;
        }

        $fassignments = array();
        foreach ($users as $user) {
            $assignment = new stdClass();
            $assignment->programid = $programid;
            $assignment->userid = $user->id;
            $assignment->assignmentid = $assignmentid;

            $fassignments[] = $assignment;
        }

        return $DB->insert_records_via_batch('prog_future_user_assignment', $fassignments);
    }

    /**
     * This function is ONLY used to create the initial user assignment
     * and check for exceptions when creating it
     *
     * Assigns a user to the program. Any users assigned to a program in this
     * way will have this program as part of their required (mandatory) learning
     * (as opposed to part of a learning plan).
     *
     * A 'program_assigned' event is triggered to notify any listening modules.
     *
     * @global object $CFG
     * @param int $userid
     * @param int $timedue The date the the program should be completed by
     * @param object $assignment_record A record from mdl_prog_assignment
     * @param bool $exceptions True if there are exceptions for this learner
     *
     * @return array list of users to send messages to, empty if insert fails
     */
    public function assign_learners_bulk($users, $assignment_record) {
        global $DB;

        if (empty($users)) {
            return array();
        }

        $now = time();

        // insert a completion record to store the status of the user's progress on the program
        // TO DO: eventually we need to have multiple completion records, linked to the assignment that made them

        // remove any existing records first - the latest assignment completion date should trump previous ones
        list($insql, $params) = $DB->get_in_or_equal(array_keys($users));
        $DB->delete_records_select('prog_completion', "userid $insql AND programid = ? AND coursesetid = ?", array_merge($params, array($this->id, 0)));

        $prog_completions = array();
        foreach ($users as $userid => $assigndata) {
            $pc = new stdClass();
            $pc->programid = $this->id;
            $pc->userid = $userid;
            $pc->coursesetid = 0;
            $pc->status = STATUS_PROGRAM_INCOMPLETE;
            $pc->timestarted = $now;
            $pc->timedue = $assigndata['timedue'];
            $prog_completions[] = $pc;
        }
        $DB->insert_records_via_batch('prog_completion', $prog_completions);
        unset($prog_completions);

        // insert or update a user assignment record to store the details of how this user was assigned to the program
        $user_assignments = array();
        foreach ($users as $userid => $assigndata) {
            $ua = new stdClass();
            $ua->programid = $this->id;
            $ua->userid = $userid;
            $ua->assignmentid = $assignment_record->id;
            $ua->timeassigned = $now;
            $ua->exceptionstatus = $assigndata['exceptions'] ? PROGRAM_EXCEPTION_RAISED : PROGRAM_EXCEPTION_NONE;

            $user_assignments[] = $ua;
        }
        $DB->insert_records_via_batch('prog_user_assignment', $user_assignments);
        unset($user_assignments);

        // Assign the student role to the user in the program context
        // This is what identifies the program as required learning.
        role_assign_bulk($this->studentroleid, array_keys($users), $this->context->id);

        $message_queue = array();
        foreach ($users as $userid => $assigndata) {
            if (!$assigndata['exceptions']) {
                $eventdata = new stdClass();
                $eventdata->programid = $this->id;
                $eventdata->userid = $userid;
                $message_queue["new:{$userid}"] = $eventdata; // Key has to be string to avoid duplicates.
            }
        }

        return $message_queue;
    }

    /**
     * Receives an array containing userids and unassigns all the users from the
     * program.
     *
     * A 'program_unassigned' event is triggered to notify any listening modules.
     *
     * @param array $userids Array containing userids
     * @return bool
     */
    public function unassign_learners($userids) {
        global $DB;
        //get the courses in this program
        $sql = "SELECT DISTINCT courseid
                  FROM {prog_courseset_course} csc
            INNER JOIN {prog_courseset} cs
                    ON csc.coursesetid = cs.id
                   AND cs.programid = ?";
        $courses = $DB->get_fieldset_sql($sql, array($this->id));

        if (!empty($courses)) {
            //get program course enrolment plugin
            $program_plugin = enrol_get_plugin('totara_program');
            foreach ($courses as $courseid) {
                $instance = $program_plugin->get_instance_for_course($courseid);
                if ($instance) {
                    //get all the active enrolments with this plugin for these users
                    list($in_sql, $in_params) = $DB->get_in_or_equal($userids);
                    array_push($in_params, $instance->id);
                    $active_enrolments = $DB->get_fieldset_select('user_enrolments', 'userid', "userid $in_sql AND enrolid = ?", $in_params);
                    foreach ($active_enrolments as $userid) {
                        $program_plugin->unenrol_user($instance, $userid);
                    }
               }
            }
        }
        foreach ($userids as $userid) {
            // Un-assign the student role from the user in the program context
            role_unassign($this->studentroleid, $userid, $this->context->id);

            // delete the users assignment records for this program
            $DB->delete_records('prog_user_assignment', array('programid' => $this->id, 'userid' => $userid));

            // delete future_user_assignment records too
            $DB->delete_records('prog_future_user_assignment', array('programid' => $this->id, 'userid' => $userid));

            // delete all exceptions
            $DB->delete_records('prog_exception', array('programid' => $this->id, 'userid' => $userid));

            // check if this program is also part of any of the user's learning plans
            if (!$this->assigned_to_users_non_required_learning($userid)) {
                // delete the completion record if the program is not complete
                if (!$this->is_program_complete($userid)) {
                    $this->delete_completion_record($userid);
                }
            }

            // trigger this event for any listening handlers to catch
            $event = \totara_program\event\program_unassigned::create(
                array(
                    'objectid' => $this->id,
                    'context' => context_program::instance($this->id),
                    'userid' => $userid,
                )
            );
            $event->trigger();
        }

        return true;

    }

    /**
     * Sets the time that a program is due for a learner
     *
     * @param int $userid The user's ID
     * @param int $timedue Timestamp indicating the date that the program is due to be completed by the user
     * @return bool Success
     */
    public function set_timedue($userid, $timedue) {
        global $DB;
        if ($completion = $DB->get_record('prog_completion', array('programid' => $this->id, 'userid' => $userid, 'coursesetid' => 0))) {
            $todb = new stdClass();
            $todb->id = $completion->id;
            $todb->timedue = $timedue;
            return $DB->update_record('prog_completion', $todb);
        } else {
            return false;
        }

    }

    /**
     * Calulates the date on which a program will be due for a learner when it
     * is first assigned based on the assignment record through which the user
     * is being assigned to the program.
     *
     * @global array $COMPLETION_EVENTS_CLASSNAMES
     * @param int $userid
     * @param object $assignment_record
     * @param int $timedue Timestamp possibly containing an extended due date
     * @return int A timestamp
     */
    public function make_timedue($userid, $assignment_record, $timedue) {

        if ($assignment_record->completionevent == COMPLETION_EVENT_NONE) {
            // Fixed time or Not Set?
            if ($assignment_record->completiontime == COMPLETION_TIME_UNKNOWN) {
                return COMPLETION_TIME_NOT_SET;
            } else if ($timedue > $assignment_record->completiontime) {
                return $timedue;
            } else {
                return $assignment_record->completiontime;
            }
        }

        // Else it's a relative event, need to do a lookup.
        global $COMPLETION_EVENTS_CLASSNAMES;

        if (!isset($COMPLETION_EVENTS_CLASSNAMES[$assignment_record->completionevent])) {
            throw new ProgramException(get_string('eventnotfound', 'totara_program', $assignment_record->completionevent));
        }

        // See if we can retrieve the object form the cache.
        if (isset($this->completion_object_cache[$assignment_record->completionevent])) {
            $event_object = $this->completion_object_cache[$assignment_record->completionevent];
        }
        else {
            // Else make it it and add to the cache for future use.
            $event_object = new $COMPLETION_EVENTS_CLASSNAMES[$assignment_record->completionevent]();
            $this->completion_object_cache[$assignment_record->completionevent] = $event_object;
        }

        $basetime = $event_object->get_timestamp($userid, $assignment_record);

        if ($basetime == false) {
            return false;
        }

        $timedue = $basetime + $assignment_record->completiontime;

        return $timedue;
    }
    private $completion_object_cache = array();

    /**
     * Determines if the program is assigned to the speficied user's required
     * (mandatory) learning
     *
     * @global object $CFG
     * @param int $userid
     * @return bool True if the program is mandatory, false if not
     */
    public function assigned_to_users_required_learning($userid) {
        global $DB;
        $sql = "SELECT p.id
                FROM {prog_user_assignment} AS p
                WHERE p.userid = ?
                AND p.programid = ?";

        return $DB->record_exists_sql($sql, array($userid, $this->id));
    }

    /**
     * Determines if the program is assigned to the speficied user's non-required
     * learning (i.e. part of a learning plan)
     *
     * @global object $CFG
     * @param int $userid
     * @return bool True if the program is assigned to a learning plan, false if not
     */
    public function assigned_to_users_non_required_learning($userid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/totara/plan/lib.php');

        $sql = "SELECT p.id
                FROM {dp_plan} AS p
                JOIN {dp_plan_program_assign} AS ppa ON p.id = ppa.planid
                WHERE p.userid = ?
                AND ppa.programid = ?
                AND p.status != ?
                AND ppa.approved = ?";

        return $DB->record_exists_sql($sql, array($userid, $this->id, DP_PLAN_STATUS_UNAPPROVED, DP_APPROVAL_APPROVED));
    }

    /**
     * Looks for courses that have been assigned to this certification but exists in another certification
     * that this user has already been assigned to.
     *
     * @global object $DB
     * @param int $userid
     * @return bool true if the user is on another certification with the same course
     */
    public function duplicate_course($userid) {
        global $DB;
        if (!isset($this->certifid) || empty($this->certifid)) {
            return false;
        }
        $sql = "SELECT DISTINCT pcc.courseid
                FROM {prog} p
                JOIN {prog_user_assignment} pua ON pua.programid = p.id AND pua.userid = :userid
                JOIN {prog_courseset} pc ON pc.programid = p.id
                JOIN {prog_courseset_course} pcc ON pcc.coursesetid = pc.id
                WHERE p.certifid IS NOT NULL
                AND p.id <> :programid1
                AND EXISTS (SELECT thispcc.courseid
                            FROM {prog_courseset} thispc
                            JOIN {prog_courseset_course} thispcc ON thispcc.coursesetid = thispc.id
                            WHERE thispcc.courseid = pcc.courseid
                            AND thispc.programid = :programid2)";
        return $DB->record_exists_sql($sql, array('userid' => $userid, 'programid1' => $this->id, 'programid2' => $this->id));
    }

    /**
     * Return true or false depending on whether or not the specified user has
     * completed this program
     *
     * @param int $userid
     * @return bool
     */
    public function is_program_complete($userid) {
        global $DB;
        if ($prog_completion_status = $DB->get_record('prog_completion', array('programid' => $this->id, 'userid' => $userid, 'coursesetid' => 0))) {
            if ($prog_completion_status->status == STATUS_PROGRAM_COMPLETE) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return true if the user has started but not completed this program, false
     * if not
     *
     * @param int $userid
     * @return bool
     */
    public function is_program_inprogress($userid) {
        global $DB;
        if ($prog_completion_status = $DB->get_record('prog_completion', array('programid' => $this->id, 'userid' => $userid, 'coursesetid' => 0))) {
            if ($prog_completion_status->status == STATUS_PROGRAM_INCOMPLETE && $prog_completion_status->timestarted > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Updates the completion record in the database for the specified user
     *
     * @param int $userid
     * @param array $completionsettings Contains the field values for the record
     * @return bool|int
     */
    public function update_program_complete($userid, $completionsettings) {
        global $CFG, $DB;

        $progcompleted_eventtrigger = false;

        // if the program is being marked as complete we need to trigger an
        // event to any listening modules
        if (array_key_exists('status', $completionsettings)) {
            if ($completionsettings['status'] == STATUS_PROGRAM_COMPLETE) {

                // flag that we need to trigger the program_completed event
                $progcompleted_eventtrigger = true;

                // get the user's position/organisation at time of completion
                require_once("{$CFG->dirroot}/totara/hierarchy/prefix/position/lib.php");
                $posids = pos_get_current_position_data($userid);
            }
        }

        if ($completion = $DB->get_record('prog_completion', array('programid' => $this->id, 'userid' => $userid, 'coursesetid' => 0))) {

            foreach ($completionsettings as $key => $val) {
                $completion->$key = $val;
            }

            if ($progcompleted_eventtrigger) {
                // record the user's pos/org at time of completion
                $completion->positionid = $posids['positionid'];
                $completion->organisationid = $posids['organisationid'];
            }

            $update_success = $DB->update_record('prog_completion', $completion);
            if ($progcompleted_eventtrigger) {
                // Trigger an event to notify any listeners that this program has been completed.
                $event = \totara_program\event\program_completed::create(
                    array(
                        'objectid' => $this->id,
                        'context' => context_program::instance($this->id),
                        'userid' => $userid,
                        'other' => array(
                            'certifid' => isset($this->certifid) ? $this->certifid : 0,
                        ),
                    )
                );
                $event->trigger();
            }

            return $update_success;

        } else {

            $now = time();

            $completion = new stdClass();
            $completion->programid = $this->id;
            $completion->userid = $userid;
            $completion->coursesetid = 0;
            $completion->status = STATUS_PROGRAM_INCOMPLETE;
            $completion->timecompleted = 0;
            $completion->timedue = 0;
            $completion->timestarted = $now;
            if ($progcompleted_eventtrigger) {
                // record the user's pos/org at time of completion
                $completion->positionid = $posids['positionid'];
                $completion->organisationid = $posids['organisationid'];
            }

            foreach ($completionsettings as $key => $val) {
                $completion->$key = $val;
            }

            $insert_success = $DB->insert_record('prog_completion', $completion);
            if ($progcompleted_eventtrigger) {
                // Trigger an event to notify any listeners that this program has been completed.
                $event = \totara_program\event\program_completed::create(
                    array(
                        'objectid' => $this->id,
                        'context' => context_program::instance($this->id),
                        'userid' => $userid,
                        'other' => array(
                            'certifid' => $this->certifid,
                        )
                    )
                );
                $event->trigger();
            }

            return $insert_success;
        }
    }

    /**
     * Returns an array containing all the userids who are currently registered
     * on the program. Optionally, will only return a subset of users with a
     * specific completion status
     *
     * @param int $status
     * @return array of userids for users in the program
     */
    public function get_program_learners($status=false) {
        global $DB;

        if ($status) {
            $statussql = 'AND status = ?';
            $statusparams = array($status);
        } else {
            $statussql = '';
            $statusparams = array();
        }

        // Query to retrive any users who are registered on the program
        $sql = "SELECT id FROM {user} WHERE id IN
            (SELECT DISTINCT userid FROM {prog_completion}
            WHERE coursesetid = 0 AND programid = ? {$statussql})";
        $params = array_merge(array($this->id), $statusparams);

        return $DB->get_fieldset_sql($sql, $params);
    }

    /**
     * Calculates how far through the program a specific user is and returns
     * the result as a percentage
     *
     * @param int $userid
     * @return float
     */
    public function get_progress($userid) {

        // first check if the whole program has been completed
        if ($this->is_program_complete($userid)) {
            return (float)100;
        }

        $certifpath = get_certification_path_user($this->certifid, $userid);
        $certifpath == CERTIFPATH_UNSET && $certifpath = CERTIFPATH_CERT;
        $courseset_groups = $this->content->get_courseset_groups($certifpath);
        $courseset_group_count = count($courseset_groups);
        $courseset_group_complete_count = 0;

        foreach ($courseset_groups as $courseset_group) {
            $courseset_group_complete = false;
            foreach ($courseset_group as $courseset) {
                // only one set in a group of course sets needs to be completed for the whole group to be considered complete
                if ($courseset->is_courseset_complete($userid)) {
                    $courseset_group_complete = true;
                    break;
                }
            }

            if ($courseset_group_complete) {
                $courseset_group_complete_count++;
            }
            }

        if ($courseset_group_count > 0) {
            return (float)($courseset_group_complete_count / $courseset_group_count) * 100;
        }
        return 0;
    }

    /**
     * Returns true or false depending on whether or not the specified user
     * can access the specified course based on whether or not the program
     * contains the course in any of its course sets and whether or not the
     * user has completed all pre-requisite groups of course sets
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public function can_enter_course($userid, $courseid) {

        $now = time();
        $available = !isset($this->available) || $this->available == AVAILABILITY_NOT_TO_STUDENTS;
        $from = !empty($this->availablefrom) && $this->availablefrom > $now;
        $until = !empty($this->availableuntil) && $this->availableuntil < $now;

        // Don't allow access to courses through unavailable programs.
        if ($available || $from || $until) {
            return false;
        }

        $certifpath = get_certification_path_user($this->certifid, $userid);
        $certifpath == CERTIFPATH_UNSET && $certifpath = CERTIFPATH_CERT;
        $courseset_groups = $this->content->get_courseset_groups($certifpath);

        $courseset_group_completed = false;
        $maxcompletedgroup = -1;
        $coursegroup = -1;

        // Find the last completed groupset, and which groupset the course is in.
        foreach ($courseset_groups as $groupkey => $courseset_group) {
            $thisgroupcomplete = false;
            foreach ($courseset_group as $courseset) {
                if ($courseset->is_courseset_complete($userid)) {
                    $thisgroupcomplete = true;
                    $maxcompletedgroup = $groupkey;
                }
                if ($coursegroup == -1 && $courseset->contains_course($courseid)) {
                    // Get the first occurance of the course.
                    $coursegroup = $groupkey;
                }
                if ($thisgroupcomplete && $courseset->contains_course($courseid)) {
                    // Create completion record if it does not exist.
                    $courseset->update_courseset_complete($userid, array());
                }
            }
        }

        // Allow access if the course is in the first group...
        if ($coursegroup == 0) {
            return true;
        } else if ($maxcompletedgroup >= 0 && $coursegroup > 0) {
            // Or an already completed group...
            if ($coursegroup <= $maxcompletedgroup) {
                return true;
            }
            // Or the next uncompleted group.
            if ($coursegroup == ($maxcompletedgroup+1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the HTML markup for displaying the view of the program. This can
     * vary depending on whether or not the viewer is enrolled on the program
     * and if the viewer is viewing someone else's program.
     *
     * @global object $CFG
     * @global object $USER
     * @param int $userid
     * @return string
     */
    public function display($userid=null) {
        global $CFG, $DB, $USER, $OUTPUT, $PAGE;

        $iscertif = (isset($this->certifid) && $this->certifid > 0) ? true : false;

        $out = '';

        if (!$this->is_accessible()) {
            // Return if program is not accessible
            return html_writer::tag('p', get_string('programnotcurrentlyavailable', 'totara_program'));
        }

        $message = '';

        $viewinganothersprogram = false;
        if ($userid && $userid != $USER->id) {
            $viewinganothersprogram = true;
            if (!$user = $DB->get_record('user', array('id' => $userid))) {
                print_error('error:failedtofinduser', 'totara_program', $userid);
            }
            $user->fullname = fullname($user);
            $user->wwwroot = $CFG->wwwroot;
            $message .= html_writer::tag('p', get_string('viewingxusersprogram', 'totara_program', $user));
        }

        $userassigned = $this->user_is_assigned($userid);
        $timedue = null;

        // Display the reason why this user has been assigned to the program (if it is mandatory for the user).
        if ($userassigned) {
            $prog_completion = $DB->get_record('prog_completion', array('programid' => $this->id, 'userid' => $userid, 'coursesetid' => 0));
            $user_assignments = $DB->get_records_select('prog_user_assignment', "programid = ? AND userid = ?", array($this->id, $userid));
            $timedue = $prog_completion->timedue;

            if (count($user_assignments) > 0) {
                if ($viewinganothersprogram) {
                    $message .= html_writer::tag('p', get_string('assignmentcriteriamanager', 'totara_program'));
                } else {
                    $message .= html_writer::tag('p', get_string('assignmentcriterialearner', 'totara_program'));
                }
                $message .= html_writer::start_tag('ul');
                foreach ($user_assignments as $user_assignment) {
                    if ($assignment = $DB->get_record('prog_assignment', array('id' => $user_assignment->assignmentid))) {
                        $user_assignment_ob = prog_user_assignment::factory($assignment->assignmenttype, $user_assignment->id);
                        $message .= $user_assignment_ob->display_criteria();
                    }
                }
                $message .= html_writer::end_tag('ul');
            }
        }

        // Show message box if there are any messages.
        if (!empty($message)) {
            $out .= html_writer::tag('div', $message, array('class' => 'notifymessage'));
        }

        if ($iscertif) {
            global $CERTIFPATH;
            $userid = (($userid) ? $userid : $USER->id);
            $certifcompletion = $DB->get_record('certif_completion', array('certifid' => $this->certifid, 'userid' =>  $userid));

            if ($certifcompletion) {
                $now = time();
                $out .= html_writer::start_tag('p', array('class' => 'certifpath'));
                if ($certifcompletion->certifpath == CERTIFPATH_CERT) {
                    if ($certifcompletion->renewalstatus == CERTIFRENEWALSTATUS_EXPIRED) {
                        $sql = 'SELECT MAX(timeexpires)
                                FROM {certif_completion_history}
                                WHERE userid = :uid
                                AND certifid = :cid';
                        $params = array('uid' => $userid, 'cid' => $this->certifid);
                        $pasttimedue = $DB->get_field_sql($sql, $params);

                        // Prog_completion timedue changes when setting completion for the assignment (Set completion link).
                        // If the certification for this user has expired and a new completion is set,
                        // we should take the new completion date as the due date. Following discussion in Zoho ticket #598.
                        if ($timedue < $pasttimedue) {
                            $timedue = $pasttimedue;
                            $out .= get_string('certexpired', 'totara_certification');
                        }
                    } else {
                        $out .= get_string('certinprogress', 'totara_certification');
                    }
                } else {
                    $timedue = $certifcompletion->timeexpires;
                    if ($now > $certifcompletion->timewindowopens) {
                        $out .= get_string('recertwindowopen', 'totara_certification');
                        $out .= get_string('recertwindowexpiredate', 'totara_certification',
                                userdate($certifcompletion->timeexpires, get_string('strftimedatetime', 'langconfig')));
                    } else {
                        $out .= get_string('certcomplete', 'totara_certification');
                        $out .= get_string('recertwindowopendate', 'totara_certification',
                                userdate($certifcompletion->timewindowopens, get_string('strftimedatetime', 'langconfig')));
                    }
                }
                $out .= html_writer::end_tag('p');
            }
        }

        // display the start date, due date and progress bar.
        if ($userassigned) {

            // Setup the request extension link.
            $request = '';
            if ($this->assigned_to_users_required_learning($userid) && $prog_completion->timedue != COMPLETION_TIME_NOT_SET && $prog_completion->timecompleted == 0) {
                // Only show the extension link if the user is assigned via required learning, and it has a due date and they haven't completed it yet.
                if (!$extension = $DB->get_record('prog_extension', array('userid' => $userid, 'programid' => $this->id, 'status' => 0))) {
                    if (!$viewinganothersprogram && totara_get_manager($userid)) {
                        // Show extension request link if it is their assignment and they have a manager to request it from.
                        $url = new moodle_url('/totara/program/view.php', array('id' => $this->id, 'extrequest' => '1'));
                        $request = ' ' . html_writer::link($url, get_string('requestextension', 'totara_program'), array('id' => 'extrequestlink'));
                    }
                } else {
                    // Show pending text if they have already requested an extension.
                    $request = ' ' . get_string('pendingextension', 'totara_program');
                }
            }

            if ($prog_completion) {
                $startdatestr = ($prog_completion->timestarted != 0
                                ? $this->display_date_as_text($prog_completion->timestarted)
                                : get_string('nostartdate', 'totara_program'));
                if ($iscertif) {
                    $duedatestr = $this->display_duedate($timedue, $prog_completion->userid, $certifcompletion->certifpath, $certifcompletion->status);
                } else {
                    $duedatestr = $this->display_duedate($timedue, $prog_completion->userid);
                }
                $duedatestr .= html_writer::empty_tag('br');
                $duedatestr .= $request;

                $out .= html_writer::start_tag('div', array('class' => 'programprogress'));
                $out .= html_writer::tag('div', get_string('startdate', 'totara_program') . ': '
                                . $startdatestr, array('class' => 'item'));
                $out .= html_writer::tag('div', get_string('duedate', 'totara_program').': '
                                . $duedatestr, array('class' => 'item'));
                $out .= html_writer::tag('div', get_string('progress', 'totara_program') . ': '
                                . $this->display_progress($userid), array('class' => 'item'));
                $out .= html_writer::end_tag('div');
            }
        }

        // Get summary and overview files.
        $programrenderer = $PAGE->get_renderer('totara_program');
        $progobj = new stdClass();
        $progobj->id = $this->id;
        $summary = $programrenderer->coursecat_programbox_content(new programcat_helper(), new program_in_list($progobj));
        $out .= $summary;
/* TODO: This needs fixing to work with the new catalogue
 *
        $summary = file_rewrite_pluginfile_urls($this->summary, 'pluginfile.php',
            context_program::instance($this->id)->id, 'totara_program', 'summary', 0);
        $out .= html_writer::tag('div', $summary, array('class' => 'summary'));
 t2-feature-certification */

        // course sets - for certify or recertify paths
        if ($iscertif) {
            if (is_siteadmin()) {
                $out .= $OUTPUT->heading(get_string('oricertpath', 'totara_certification'), 2);
                $out .= $this->display_courseset(CERTIFPATH_CERT, $userid, $viewinganothersprogram);

                $out .= html_writer::start_tag('div', array('class' => 'programrecert'));
                $out .= $OUTPUT->heading(get_string('recertpath', 'totara_certification'), 2);
                $out .= html_writer::end_tag('div');

                $out .= $this->display_courseset(CERTIFPATH_RECERT, $userid, $viewinganothersprogram);
            } else if ($certifcompletion) {
                // If on certification path OR if on recertification but not recertified before: display certification coursesset,
                // Else display recert path.
                $histcount = $DB->count_records('certif_completion_history', array('certifid' => $this->certifid, 'userid' => $userid, 'unassigned' => 0));

                if ($certifcompletion->certifpath == CERTIFPATH_CERT ||
                                ($certifcompletion->certifpath == CERTIFPATH_RECERT && !$histcount)) {
                    $out .= $OUTPUT->heading(get_string('oricertpath', 'totara_certification'), 2);
                    $out .= $this->display_courseset(CERTIFPATH_CERT, $userid, $viewinganothersprogram);
                } else {
                    $out .= html_writer::start_tag('div', array('class' => 'programrecert'));
                    $out .= $OUTPUT->heading(get_string('recertpath', 'totara_certification'), 2);
                    $out .= html_writer::end_tag('div');
                    $out .= $this->display_courseset(CERTIFPATH_RECERT, $userid, $viewinganothersprogram);
                }
            } else {
                $out .= $OUTPUT->heading(get_string('oricertpath', 'totara_certification'), 2);
                $out .= $this->display_courseset(CERTIFPATH_CERT, $userid, $viewinganothersprogram);
            }
        } else {
            $out .= $this->display_courseset(CERTIFPATH_STD, $userid, $viewinganothersprogram);
        }

        // only show end note when a program is complete
        $prog_owners_id = ($userid) ? $userid : $USER->id;
        $prog_completion = $DB->get_record('prog_completion', array('programid' => $this->id, 'userid' => $prog_owners_id, 'coursesetid' => 0));

        if ($prog_completion && $prog_completion->status == STATUS_PROGRAM_COMPLETE) {
            $out .= html_writer::start_tag('div', array('class' => 'programendnote'));
            $out .= $OUTPUT->heading(get_string('programends', 'totara_program'), 2);
            $out .= html_writer::tag('div', $this->endnote, array('class' => 'endnote'));
            $out .= html_writer::end_tag('div');
            $out .= html_writer::end_tag('div');
        }

        return $out;
    }

    function display_courseset($certifpath, $userid, $viewinganothersprogram) {
        $out = '';
        $courseset_groups = $this->content->get_courseset_groups($certifpath);

        // check if this is a recurring program
        if (count($courseset_groups) == 0) {
            $out .= html_writer::tag('p', get_string('nocoursecontent', 'totara_program'), array('class' => 'nocontent'));
        } else if (count($courseset_groups) == 1 && ($courseset_groups[0][0]->contenttype == CONTENTTYPE_RECURRING)) {
            $out .= $courseset_groups[0][0]->display($userid);
        } else {

            // Maintain a list of previous and future courseset, for use later
            $previous = array();
            $next = array();

            // get the course sets for this program
            $coursesets = $this->content->get_course_sets();

            // set up the array of next coursesets for use  later
            foreach ($coursesets as $courseset) {
                $next[] = $courseset;
            }

            // flag to determine whether or not to display active links to
            // courses in the course set groups in the program. The first group
            // will always be accessible.
            $courseset_group_accessible = true;;

            // display each course set
            foreach ($courseset_groups as $courseset_group) {

                // display each course set
                foreach ($courseset_group as $courseset) {
                    $previous[] = $courseset;
                    $next = array_splice($next, 1);
                    $out .= $courseset->display($userid, $previous, $next, $courseset_group_accessible, $viewinganothersprogram);
                }

                // check if the current course set group is complete. If not,
                // set a flag to prevent access to the courses in the following
                // course sets
                foreach ($courseset_group as $courseset) {
                    // only one set in a group of course sets needs to be completed for the whole group to be considered complete
                    if ($courseset->is_courseset_complete($userid)) {
                        $courseset_group_accessible = true;
                        break;
                    }
                    $courseset_group_accessible = false;
                }
            }
        }
        return $out;
    }

    /**
     * Display widget containing a program summary
     *
     * @return string $out
     */
    function display_summary_widget($userid=null) {
        global $USER;

        $params = array();
        if (($userid != null) && ($userid != $USER->id)) {
            $params['userid'] = $userid;
        }
        $params['id'] = $this->id;
        $url = new moodle_url("/totara/program/required.php", $params);
        $summary = file_rewrite_pluginfile_urls($this->summary, 'pluginfile.php',
                context_program::instance($this->id)->id, 'totara_program', 'summary', 0);

        $out = '';
        $out .= html_writer::start_tag('div', array('class' => 'cell'));
        $out .= html_writer::link($url, $this->fullname);
        $out .= html_writer::end_tag('div');
        $out .= html_writer::start_tag('div', array('class' => 'dp-summary-widget-description'));
        $out .= $summary . html_writer::end_tag('div');

        return $out;
    }

    /**
     * Display the due date for a program
     *
     * @param int $duedate
     * @param int $userid
     * @param int $certifpath   Optional param telling us the path of the certification
     * @param int $certstatus   Optional param telling us the status of the certification
     * @return string
     */
    function display_duedate($duedate, $userid, $certifpath = null, $certstatus = null) {
        global $OUTPUT;

        if (empty($duedate) || $duedate == COMPLETION_TIME_NOT_SET) {
            if ($certifpath == null && $certstatus == null) {
                // This is a program, display no due date set.
                return get_string('duedatenotset', 'totara_program');
            } else if ($certifpath == CERTIFPATH_CERT) {
                if ($certstatus == CERTIFSTATUS_EXPIRED) {
                    // This certification has expired.
                    return $OUTPUT->error_text(get_string('overdue', 'totara_program'));
                } else {
                    // This is the first run through of the certification and no due date was set.
                    return get_string('duedatenotset', 'totara_program');
                }
            } else {
                // Something has gone wrong here, a certification on recert should always have a duedate.
                print_error('error:recertduedatenotset', 'totara_program');
            }

        }

        $out = '';
        $out .= $this->display_date_as_text($duedate);

        // Highlight dates that are overdue or due soon if program is not complete.
        if (!$this->is_program_complete($userid)) {
            $out .= $this->display_duedate_highlight_info($duedate);
        }

        return $out;
    }

    /**
     * Display a date as text
     *
     * @param int $mydate
     * @return string
     */
    function display_date_as_text($mydate) {
        global $CFG;

        if (isset($mydate)) {
            return userdate($mydate, get_string('strftimedate', 'langconfig'), $CFG->timezone, false);
        } else {
            return '';
        }
    }

    /**
     * Display due date for a program with task info
     *
     * @param int $duedate
     * @return string
     */
    function display_duedate_highlight_info($duedate) {
        global $OUTPUT;

        $out = '';
        $now = time();
        if (isset($duedate)) {
            $out .= html_writer::empty_tag('br') . html_writer::start_tag('span', array('class' => 'plan_highlight'));
            if (($duedate < $now) && ($now - $duedate < 60*60*24)) {
                $out .= get_string('duetoday', 'totara_plan');
            } else if ($duedate < $now) {
                $out .= $OUTPUT->error_text(get_string('overdue', 'totara_plan'));
            } else if ($duedate - $now < 60*60*24*7) {
                $days = ceil(($duedate - $now)/(60*60*24));
                $out .= get_string('dueinxdays', 'totara_plan', $days);
            }
            $out .= html_writer::end_tag('span');
        }
        return $out;
    }

    /**
     * Determines and displays the progress of this program for a specified user.
     *
     * Progress is determined by course set completion statuses.
     *
     * @access  public
     * @param int $userid
     * @return  string
     */
    public function display_progress($userid) {
        global $DB, $PAGE;

        $prog_completion = $DB->get_record('prog_completion', array('programid' => $this->id, 'userid' => $userid, 'coursesetid' => 0));

        if (!$prog_completion) {
            $out = get_string('notassigned', 'totara_program');
            return $out;
        } else if ($prog_completion->status == STATUS_PROGRAM_COMPLETE) {
            $overall_progress = 100;
        } else {
            $overall_progress = $this->get_progress($userid);
        }

        $tooltipstr = 'DEFAULTTOOLTIP';

        // Get relevant progress bar and return for display
        $renderer = $PAGE->get_renderer('totara_core');
        return $renderer->print_totara_progressbar($overall_progress, 'medium', false, $tooltipstr);
    }

    public function display_timedue_date($completionstatus, $time, $format = '') {
        global $OUTPUT;

        if ($time == 0 || $time == COMPLETION_TIME_NOT_SET) {
            return get_string('noduedate', 'totara_plan');;
        }

        if (empty($format)) {
            $format = get_string('datepickerlongyearphpuserdate', 'totara_core');
        }

        $out = userdate($time, $format);

        $days = '';
        if ($completionstatus != STATUS_PROGRAM_COMPLETE) {
            $days_remaining = floor(($time - time()) / 86400);
            if ($days_remaining == 1) {
                $days = get_string('onedayremaining', 'totara_program');
            } else if ($days_remaining < 10 && $days_remaining > 0) {
                $days = get_string('daysremaining', 'totara_program', $days_remaining);
            } else if ($time < time()) {
                $days = get_string('overdue', 'totara_plan');
            }
        }
        if ($days != '') {
            $out .= html_writer::empty_tag('br') . $OUTPUT->error_text($days);
        }

        return $out;
    }

    public function display_link_program_icon($programname, $program_id, $program_icon, $userid = null) {
        global $OUTPUT, $USER;
        if ($userid == null) {
            $userid = $USER->id;
        }
        $programid = $program_id;

        $program = new program($programid);
        $icon = html_writer::empty_tag('img', array('src' => totara_get_icon($programid, TOTARA_ICON_TYPE_PROGRAM),
            'class' => 'course_icon', 'alt' => ''));

        if ($program->is_required_learning($userid) && $program->is_accessible()) {
            $html = $OUTPUT->action_link(
                new moodle_url('/totara/program/required.php', array('id' => $programid, 'userid' => $userid)),
                $icon . $programname
            );
        } else if ($program->is_accessible()) {
            $html = $OUTPUT->action_link(
                new moodle_url('/totara/program/view.php', array('id' => $programid)),
                $icon . $programname
            );
        } else {
            $html = $icon . $programname;
        }

        return $html;

    }

    /**
     * Generates the HTML to display the current number of exceptions and a link
     * to the exceptions report for the program
     *
     * @return string
     */
    public function display_exceptions_link() {
        global $PAGE;
        $out = '';
        $exceptionscount = $this->exceptionsmanager->count_exceptions();
        if ($exceptionscount && $exceptionscount>0) {
            $url = new moodle_url('/totara/program/exceptions.php', array('id' => $this->id));
            $renderer = $PAGE->get_renderer('totara_program');
            $out .= $renderer->print_exceptions_link($url, $exceptionscount);
        }
        return $out;
    }

    public function get_exception_count() {
        $exceptionscount = $this->exceptionsmanager->count_exceptions();

        if ($exceptionscount) {
            return $exceptionscount;
        } else {
            return false;
        }
    }

    /**
     * Generates the HTML to display the current stats of the program (live,
     * not available, etc)
     *
     * @return string
     */
    public function display_current_status() {
        global $PAGE, $CFG, $DB;
        require_once($CFG->dirroot . '/totara/cohort/lib.php');

        $data = new stdClass();
        $data->assignments = $this->assignments->count_active_user_assignments();
        $data->exceptions = $this->assignments->count_user_assignment_exceptions();
        $data->total = $this->assignments->count_total_user_assignments();
        $data->audiencevisibilitywarning = false;

        if (!empty($CFG->audiencevisibility)) {
            $coursesnovisible = $this->content->get_visibility_coursesets(TOTARA_SEARCH_OP_NOT_EQUAL, COHORT_VISIBLE_ALL);
            if (!empty($coursesnovisible)) {
                // Notify if there are courses in this program which don't have audience visibility to all.
                $data->audiencevisibilitywarning = true;
            }

            $audiencesql = "SELECT cm.id
                      FROM {cohort_visibility} cv
                      JOIN {cohort_members} cm
                        ON cv.cohortid = cm.cohortid
                       AND cv.instanceid = ?
                       AND cv.instancetype IN (?, ?)";
            $audienceparams = array($this->id, COHORT_ASSN_ITEMTYPE_PROGRAM, COHORT_ASSN_ITEMTYPE_CERTIF);
            if ($this->audiencevisible == COHORT_VISIBLE_ALL ||
                $data->assignments > 0 ||
                $this->audiencevisible == COHORT_VISIBLE_AUDIENCE && $DB->record_exists_sql($audiencesql, $audienceparams)) {
                $data->statusstr = 'programlive';
                $data->statusclass = 'notifynotice';
            } else {
                $data->statusstr = 'programnotlive';
                $data->statusclass = 'notifymessage';
            }

        } else {
            if ($this->visible ||
                $data->assignments > 0) {
                $data->statusstr = 'programlive';
                $data->statusclass = 'notifynotice';
            } else {
                $data->statusstr = 'programnotlive';
                $data->statusclass = 'notifymessage';
            }
        }

        if (!$this->is_accessible()) {
            $data->statusstr = 'programnotavailable';
        }

        $renderer = $PAGE->get_renderer('totara_program');
        return $renderer->render_current_status($data);
    }

    /**
     * Determines whether this program is viewable by the logged in user, or
     * the user passed in as the first parameter. This does not care whether
     * the user is enrolled or not.
     *
     * @global object $USER
     * @param object $user
     * @return boolean
     */
    public function is_viewable($user = null) {
        global $USER, $CFG, $DB;
        require_once($CFG->dirroot . '/totara/cohort/lib.php');

        if ($user == null) {
            $user = $USER;
        }

        $instancetype =  'program';
        if (!empty($this->certifid)) {
            $instancetype = 'certification';
        }

        $params = array('itemcontext' => CONTEXT_PROGRAM, 'instanceid' => $this->id);
        list($visibilitysql, $visibilityparams) = totara_visibility_where($user->id,
                                                                            'p.id',
                                                                            'p.visible',
                                                                            'p.audiencevisible',
                                                                            'p',
                                                                            $instancetype);
        $params = array_merge($params, $visibilityparams);
        $sql = "SELECT p.id
                FROM {prog} p
                LEFT JOIN {context} ctx ON p.id = ctx.instanceid AND contextlevel = :itemcontext
                WHERE p.id = :instanceid
                  AND {$visibilitysql}";

        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Checks accessiblity of the program for user if the user parameter is
     * passed to the function otherwise checks if the program is generally
     * accessible.
     *
     * @global object $USER
     * @param object $user If this parameter is included check availibilty to this user
     * @return boolean
     */
    public function is_accessible($user = null) {
        global $CFG;
        require_once($CFG->dirroot . '/totara/cohort/lib.php');

        // If a user is set check if they area a site admin, if so, let them have access.
        if (!empty($user->id)) {
            if (is_siteadmin($user->id)) {
                return true;
            }
        }

        // Check if this program is not available, if it's not then deny access
        if ($this->available == AVAILABILITY_NOT_TO_STUDENTS) {
            return false;
        }

        // Check if this program has from and until dates set, if so, enforce them
        if (!empty($this->availablefrom) || !empty($this->availableuntil)) {

            if (isset($user->timezone)) {
                $now = usertime(time(), $user->timezone);
            } else {
                $now = usertime(time());
            }

            // Check if the program isn't accessible yet
            if (!empty($this->availablefrom) && $this->availablefrom > $now) {
                return false;
            }

            // Check if the program isn't accessible anymore
            if (!empty($this->availableuntil) && $this->availableuntil < $now) {
                return false;
            }
        }

        // Check audience visibility.
        if ($this->certifid) {
            $capability = 'totara/certification:viewhiddencertifications';
        } else {
            $capability = 'totara/program:viewhiddenprograms';
        }

        $syscontext = context_system::instance();
        $canmanagevisibility = has_capability('totara/coursecatalog:manageaudiencevisibility', $syscontext) ||
            has_capability($capability, $syscontext);
        if (!empty($CFG->audiencevisibility) && !$canmanagevisibility && $this->audiencevisible == COHORT_VISIBLE_NOUSERS) {
            return false;
        }

        return true;
    }

    /**
     * Checks for exceptions given an assignment
     *
     */
    public function update_exceptions($userid, $assignment, $timedue) {
        // Changes are being made so old exceptions are no longer relevant.
        prog_exceptions_manager::delete_exceptions_by_assignment($assignment->id, $userid);
        $now = time();

        if ($this->assigned_to_users_non_required_learning($userid)) {
            $this->exceptionsmanager->raise_exception(EXCEPTIONTYPE_ALREADY_ASSIGNED, $userid, $assignment->id, $now);
            return true;
        }

        if ($this->duplicate_course($userid)) {
            $this->exceptionsmanager->raise_exception(EXCEPTIONTYPE_DUPLICATE_COURSE, $userid, $assignment->id, $now);
            return true;
        }

        if ($timedue == COMPLETION_TIME_UNKNOWN) {
            $this->exceptionsmanager->raise_exception(EXCEPTIONTYPE_COMPLETION_TIME_UNKNOWN, $userid, $assignment->id, $now);
            return true;
        }

        if ($timedue != COMPLETION_TIME_NOT_SET) {
            $certifpath = get_certification_path_user($this->certifid, $userid);
            if ($certifpath == CERTIFPATH_UNSET) {
                $certifpath = CERTIFPATH_CERT;
            }
            $total_time_allowed = $this->content->get_total_time_allowance($certifpath);
            $time_until_duedate = $timedue - $now;

            if ($time_until_duedate < $total_time_allowed) {
                $this->exceptionsmanager->raise_exception(EXCEPTIONTYPE_TIME_ALLOWANCE, $userid, $assignment->id, $now);
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if this proram is required learning for
     * given user or current user
     *
     * @param int $userid User ID to check (optional)
     * @return bool Returns true if this program is required learning
     */
    public function is_required_learning($userid=0) {
        global $DB, $USER;

        if (!$userid) {
            $userid = $USER->id;
        }
        list($usql, $params) = $DB->get_in_or_equal(array(PROGRAM_EXCEPTION_RAISED, PROGRAM_EXCEPTION_DISMISSED), SQL_PARAMS_QM, '', false);
        $params[] = $this->id;
        $params[] = $userid;
        $sql = "SELECT COUNT(*)
            FROM
                {prog_user_assignment}
            WHERE
                exceptionstatus $usql
            AND
                programid = ?
            AND
                userid = ?";
        if ($DB->count_records_sql($sql, $params) > 0 && !$this->is_program_complete($userid)) {
            return true;
        }

        return false;
    }

    /**
     * Checks if a user is assigned to a program
     *
     * @param int $userid
     * @param bool Returns true if a learner is assigned to a program
     */
    public function user_is_assigned($userid) {
        global $DB;

        if (!$userid) {
            return false;
        }

        // Check if there is a user assignment
        // (user is assigned to program in admin interface)
        list($usql, $params) = $DB->get_in_or_equal(array(PROGRAM_EXCEPTION_NONE, PROGRAM_EXCEPTION_RESOLVED));
        $params[] = $this->id;
        $params[] = $userid;
        $record_count = $DB->count_records_select('prog_user_assignment', " exceptionstatus $usql AND programid = ? AND userid = ?", $params);
        if ($record_count > 0) {
            return true;
        }

        // Check if the program is part of a learning plan
        if ($this->assigned_through_plan($userid)) {
            return true;
        }

        return false;
    }

    /**
     * Checks to see if a program is assigned to a user
     * through a plan and approved
     *
     * @param int $userid
     * @return bool Returns true if program is assigned to user
     */
    public function assigned_through_plan($userid) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/totara/plan/lib.php');

        $sql = "SELECT COUNT(*) FROM
                {dp_plan} p
            JOIN
                {dp_plan_program_assign} pa
            ON
                p.id = pa.planid
            WHERE
                p.userid = ?
            AND pa.programid = ?
            AND pa.approved = ?
            AND p.status >= ?";
        $params = array($userid, $this->id, DP_APPROVAL_APPROVED, DP_PLAN_STATUS_APPROVED);
        if ($DB->count_records_sql($sql, $params) > 0) {
            return true;
        }

        return false;
    }
}

/**
 * Class providing various utility functions for use by programs but which can
 * be used independently of and without instantiating a program object
 */
class program_utilities {

    /**
     * Given an integer and a time period (e.g. a day = 60*60*24) this function
     * calculates the length covered by the period and returns returns it as a
     * timestamp
     *
     * E.g. if $num = 4 and $period = 1 (hours) then the timestamp returned
     * would be the equivalent of 4 hours.
     *
     * @param int $num The number of units of the time pariod to calculate
     * @param int $period An integer denoting the time period (hours, days, weeks, etc)
     * @return int A timestamp
     */
    public static function duration_implode($num, $period) {

        $duration = 0;

        if ($period == TIME_SELECTOR_YEARS) {
            $duration = $num * DURATION_YEAR;
        } else if ($period == TIME_SELECTOR_MONTHS) {
            $duration = $num * DURATION_MONTH;
        } else if ($period == TIME_SELECTOR_WEEKS) {
            $duration = $num * DURATION_WEEK;
        } else if ($period == TIME_SELECTOR_DAYS) {
            $duration = $num * DURATION_DAY;
        } else if ($period == TIME_SELECTOR_HOURS) {
            $duration = $num * DURATION_HOUR;
        } else {
            $duration = 0;
        }

        return $duration;
    }

    /**
     * Given a timestamp representing a duration, this function factors the
     * timestamp out into a time period (e.g. an hour, a day, a week, etc)
     * and the number of units of the time period.
     *
     * This is mainly for use in forms which provide 2 fields for specifying
     * a duration.
     *
     * @global array $TIMEALLOWANCESTRINGS
     * @param int $duration
     * @return object Containing $num and $period properties
     */
    public static function duration_explode($duration) {
        global $TIMEALLOWANCESTRINGS;

        $ob = new stdClass();

        if ($duration == 0) {
            $ob->num = 0;
            $ob->period = TIME_SELECTOR_INFINITY;
        } else if ($duration % DURATION_YEAR == 0) {
            $ob->num = $duration / DURATION_YEAR;
            $ob->period = TIME_SELECTOR_YEARS;
        } else if ($duration % DURATION_MONTH == 0) {
            $ob->num = $duration / DURATION_MONTH;
            $ob->period = TIME_SELECTOR_MONTHS;
        } else if ($duration % DURATION_WEEK == 0) {
            $ob->num = $duration / DURATION_WEEK;
            $ob->period = TIME_SELECTOR_WEEKS;
        } else if ($duration % DURATION_DAY == 0) {
            $ob->num = $duration / DURATION_DAY;
            $ob->period = TIME_SELECTOR_DAYS;
        } else if ($duration % DURATION_HOUR == 0) {
            $ob->num = $duration / DURATION_HOUR;
            $ob->period = TIME_SELECTOR_HOURS;
        } else {
            $ob->num = 0;
            $ob->period = 0;
        }

        if (array_key_exists($ob->period, $TIMEALLOWANCESTRINGS)) {
            $ob->periodstr = strtolower(get_string($TIMEALLOWANCESTRINGS[$ob->period], 'totara_program'));
        } else {
            $ob->periodstr = '';
        }

        return $ob;

    }


    /**
     * Prints or returns the html for the time allowance fields
     *
     * @param <type> $prefix
     * @param <type> $periodvalue
     * @param <type> $numbervalue
     * @param <type> $return
     * @return <type>
     */
    public static function print_duration_selector($prefix, $periodelementname, $periodvalue, $numberelementname, $numbervalue, $includehours=true) {

        $timeallowances = array();
        if ($includehours) {
            $timeallowances[TIME_SELECTOR_HOURS] = get_string('hours', 'totara_program');
        }
        $timeallowances[TIME_SELECTOR_DAYS] = get_string('days', 'totara_program');
        $timeallowances[TIME_SELECTOR_WEEKS] = get_string('weeks', 'totara_program');
        $timeallowances[TIME_SELECTOR_MONTHS] = get_string('months', 'totara_program');
        $timeallowances[TIME_SELECTOR_YEARS] = get_string('years', 'totara_program');
        if ($periodvalue == '') { $periodvalue = '' . TIME_SELECTOR_DAYS; }
        $m_name = $prefix.$periodelementname;
        $m_id = $prefix.$periodelementname;
        $m_selected = $periodvalue;
        $m_nothing = '';
        $m_nothingvalue = '';
        $m_disabled = false;
        $m_tabindex = 0;

        $out = '';
        $out .= html_writer::empty_tag('input', array('type' => 'text', 'id' => $prefix.$numberelementname, 'name' => $prefix.$numberelementname, 'value' => $numbervalue, 'size' => '4', 'maxlength' => '3'));

        $attributes = array();
        $attributes['disabled'] = $m_disabled;
        $attributes['tabindex'] = $m_tabindex;
        $attributes['multiple'] = null;
        $attributes['class'] = null;
        $attributes['id'] = $m_id;
        $out .= html_writer::select($timeallowances, $m_name, $m_selected, array($m_nothingvalue=>$m_nothing), $attributes);

        return $out;
    }

    public static function get_standard_time_allowance_options($infinity=false) {
        $timeallowances = array(
            TIME_SELECTOR_DAYS => get_string('days', 'totara_program'),
            TIME_SELECTOR_WEEKS => get_string('weeks', 'totara_program'),
            TIME_SELECTOR_MONTHS => get_string('months', 'totara_program'),
            TIME_SELECTOR_YEARS => get_string('years', 'totara_program')
        );
        if ($infinity) {
            $timeallowances[TIME_SELECTOR_INFINITY] = get_string('infinity', 'totara_program');
        }
        return $timeallowances;
    }

}

class ProgramException extends Exception {

}
