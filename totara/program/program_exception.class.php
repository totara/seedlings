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


abstract class prog_exception {
    public $id, $programid, $exceptiontype, $userid, $timeraised;

    public function __construct($programid, $exceptionob=null) {

        if (is_object($exceptionob)) {
            $this->id = $exceptionob->id;
            $this->programid = $exceptionob->programid;
            $this->exceptiontype = $exceptionob->exceptiontype;
            $this->userid = $exceptionob->userid;
            $this->timeraised = $exceptionob->timeraised;
            $this->assignmentid = $exceptionob->assignmentid;
        } else {
            $this->id = 0;
            $this->programid = $programid;
            $this->exceptiontype = 0;
            $this->userid = 0;
            $this->timeraised = time();
            $this->assignmentid = 0;
        }

    }

    public static function insert_exception($programid, $exceptiontype, $userid, $assignmentid, $timeraised=null) {
        global $DB;

        if (!$timeraised) {
            $timeraised = time();
        }

        $exception = new stdClass();
        $exception->programid = $programid;
        $exception->exceptiontype = $exceptiontype;
        $exception->userid = $userid;
        $exception->timeraised = $timeraised;
        $exception->assignmentid = $assignmentid;

        if ($exceptionid = $DB->insert_record('prog_exception', $exception)) {
            $prog_notify_todb = new stdClass;
            $prog_notify_todb->id = $programid;
            $prog_notify_todb->exceptionssent = 0;
            $DB->update_record('prog', $prog_notify_todb);

            return $exceptionid;
        } else {
            return false;
        }
    }

    /**
     *  Checks if an exception exists
     *
     *  @param int $programid
     *  @param int $exceptiontype
     *  @param int $userid
     *  @return bool True if exception exists
     */
    public static function exception_exists($programid, $exceptiontype, $userid) {
        global $DB;
        return $DB->record_exists_select('prog_exception', "programid = ? AND exceptiontype = ? AND userid = ?", array($programid, $exceptiontype, $userid));
    }


    /**
     *  Deletes and exception given an ID
     *
     *  @param int $exceptionid
     *  @return bool Success status
     */
    public static function delete_exception($exceptionid) {
        global $DB;

        return $DB->delete_records('prog_exception', array('id' => $exceptionid));
    }

    public function handles($action) {
        return $action == SELECTIONACTION_DISMISS_EXCEPTION ? true : false;
    }

    public function handle($action=null) {

        if (!$this->handles($action)) {
            return true;
        }

        switch($action) {
            case SELECTIONACTION_DISMISS_EXCEPTION:
                return $this->dismiss_exception();
                break;
            default:
                return true;
                break;
        }
    }

    protected function override_and_add_program() {
        global $DB;
        $program = new program($this->programid);

        $assignid = $DB->get_field('prog_user_assignment', 'id', array('assignmentid' => $this->assignmentid, 'userid' => $this->userid));

        if (!empty($assignid)) {
            $learner_assign_todb = new stdClass();
            $learner_assign_todb->id = $assignid;
            $learner_assign_todb->exceptionstatus = PROGRAM_EXCEPTION_RESOLVED;

            if (!$DB->update_record('prog_user_assignment', $learner_assign_todb)) {
                return false;
            }

            // Event trigger to send notification when exception is resolved.
            $event = \totara_program\event\program_assigned::create(
                array(
                    'objectid' => $this->programid,
                    'context' => context_program::instance($this->programid),
                    'userid' => $this->userid,
                )
            );
            $event->trigger();
        }

        return prog_exception::delete_exception($this->id);
    }

    /**
     * Work out a viable due date and then proceed with the assignment
     * @return boolean success
     */
    protected function set_auto_time_allowance() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/totara/program/program.class.php');

        $program = new program($this->programid);

        $assignment_record = $DB->get_record('prog_assignment', array('id' => $this->assignmentid));
        if (!$assignment_record) {
            return false;
        }

        // Get the total time allowed for the content in the program.
        require_once($CFG->dirroot . '/totara/certification/lib.php');
        $certifpath = get_certification_path_user($program->certifid, $this->userid);
        $certifpath == CERTIFPATH_UNSET && $certifpath = CERTIFPATH_CERT;
        $total_time_allowed = $program->content->get_total_time_allowance($certifpath);

        // Give the user this much time plus one week.
        $timedue = time() + $total_time_allowed + 604800;

        // Update prog_completion.
        $assignment = new user_assignment($this->userid, $this->assignmentid, $this->programid);
        if (!$assignment->update($timedue)) {
            return false;
        }

        // Update user_assignment.
        $assignid = $DB->get_field('prog_user_assignment', 'id', array('assignmentid' => $this->assignmentid, 'userid' => $this->userid));

        if (!empty($assignid)) {
            $learner_assign_todb = new stdClass();
            $learner_assign_todb->id = $assignid;
            $learner_assign_todb->exceptionstatus = PROGRAM_EXCEPTION_RESOLVED;

            $DB->update_record('prog_user_assignment', $learner_assign_todb);

            $event = \totara_program\event\program_assigned::create(
                array(
                    'objectid' => $this->programid,
                    'context' => context_program::instance($this->programid),
                    'userid' => $this->userid,
                )
            );
            $event->trigger();
        }

        return prog_exception::delete_exception($this->id);
    }

    /**
     * Dismiss and ignore this exception
     *
     * @return boolean success
     */
    private function dismiss_exception() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/program/program.class.php');

        // Update user_assignment.
        $assignid = $DB->get_field('prog_user_assignment', 'id', array('assignmentid' => $this->assignmentid, 'userid' => $this->userid));

        if (!empty($assignid)) {
            $learner_assign_todb = new stdClass();
            $learner_assign_todb->id = $assignid;
            $learner_assign_todb->exceptionstatus = PROGRAM_EXCEPTION_DISMISSED;
            if (!$DB->update_record('prog_user_assignment', $learner_assign_todb)) {
                return false;
            }
        }

        return prog_exception::delete_exception($this->id);
    }
}

class time_allowance_exception extends prog_exception {

    public function __construct($programid, $exceptionob=null) {
        parent::__construct($programid, $exceptionob);
        $this->exceptiontype = EXCEPTIONTYPE_TIME_ALLOWANCE;
    }

    public function handles($action) {
        return in_array($action, array(SELECTIONACTION_OVERRIDE_EXCEPTION,
            SELECTIONACTION_AUTO_TIME_ALLOWANCE, SELECTIONACTION_DISMISS_EXCEPTION));
    }

    public function handle($action=null) {

        if (!$this->handles($action)) {
            return true;
        }

        switch ($action) {
            case SELECTIONACTION_AUTO_TIME_ALLOWANCE:
                return $this->set_auto_time_allowance();
                break;
            case SELECTIONACTION_OVERRIDE_EXCEPTION:
                return $this->override_and_add_program();
                break;
            default:
                return parent::handle($action);
                break;
        }
    }
}

class already_assigned_exception extends prog_exception {

    public function __construct($programid, $exceptionob=null) {
        parent::__construct($programid, $exceptionob);
        $this->exceptiontype = EXCEPTIONTYPE_ALREADY_ASSIGNED;
    }

    public function handles($action) {
        return in_array($action, array(SELECTIONACTION_OVERRIDE_EXCEPTION,
            SELECTIONACTION_DISMISS_EXCEPTION));
    }

    public function handle($action=null) {

        if (!$this->handles($action)) {
            return true;
        }

        switch ($action) {
            case SELECTIONACTION_OVERRIDE_EXCEPTION:
                return $this->override_and_add_program();
                break;
            default:
                return parent::handle($action);;
                break;
        }
    }

}

class duplicate_course_exception extends prog_exception {

    public function __construct($programid, $exceptionob=null) {
        parent::__construct($programid, $exceptionob);
        $this->exceptiontype = EXCEPTIONTYPE_DUPLICATE_COURSE;
    }

    public function handles($action) {
        return in_array($action, array(SELECTIONACTION_OVERRIDE_EXCEPTION,
            SELECTIONACTION_DISMISS_EXCEPTION));
    }

    public function handle($action=null) {

        if (!$this->handles($action)) {
            return true;
        }

        switch ($action) {
            case SELECTIONACTION_OVERRIDE_EXCEPTION:
                return $this->override_and_add_program();
                break;
            default:
                return parent::handle($action);;
                break;
        }
    }

}

class completion_time_unknown_exception extends prog_exception {
    public function __construct($programid, $exceptionob=null) {
        parent::__construct($programid, $exceptionob);
        $this->exceptiontype = EXCEPTIONTYPE_COMPLETION_TIME_UNKNOWN;
    }

    public function handles($action) {
        return in_array($action, array(SELECTIONACTION_AUTO_TIME_ALLOWANCE,
            SELECTIONACTION_DISMISS_EXCEPTION));
    }

    public function handle($action=null) {
        if (!$this->handles($action)) {
            return true;
        }

        switch ($action) {
            case SELECTIONACTION_AUTO_TIME_ALLOWANCE:
                return $this->set_auto_time_allowance();
                break;
            default:
                return parent::handle($action);
                break;
        }
    }
}

class unknown_exception extends prog_exception {
    public function __construct($programid, $exceptionob=null) {
        parent::__construct($programid, $exceptionob);
        $this->exceptiontype = EXCEPTIONTYPE_UNKNOWN;
    }

    public function handles ($action) {
        switch ($action) {
            case SELECTIONACTION_DISMISS_EXCEPTION:
                return true;
                break;
            default:
                return false;
                break;
        }
    }

    public function handle ($action = null) {
        if (!$this->handles($action)) {
            return true;
        }

        switch ($action) {
            default:
                return parent::handle($action);
                break;
        }
    }
}
