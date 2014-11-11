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

global $USER_ASSIGNMENT_CLASSNAMES;

$USER_ASSIGNMENT_CLASSNAMES = array(
    ASSIGNTYPE_ORGANISATION => 'prog_organisation_assignment',
    ASSIGNTYPE_POSITION     => 'prog_position_assignment',
    ASSIGNTYPE_COHORT       => 'prog_cohort_assignment',
    ASSIGNTYPE_MANAGER      => 'prog_manager_assignment',
    ASSIGNTYPE_INDIVIDUAL   => 'prog_individual_assignment'
);

abstract class prog_user_assignment {

    protected $id, $programid, $userid, $assignmentid, $timeassigned;
    protected $assignment;

    public function __construct($id) {
        global $DB;
        // get user assignment db record
        $userassignment = $DB->get_record('prog_user_assignment', array('id' => $id));

        if (!$userassignment) {
            throw new UserAssignmentException('User assignment record not found');
        }

        // set details about this user assignment
        $this->id = $id;
        $this->programid = $userassignment->programid;
        $this->userid = $userassignment->userid;
        $this->assignmentid = $userassignment->assignmentid;
        $this->timeassigned = $userassignment->timeassigned;

        $this->assignment = $DB->get_record('prog_assignment', array('id' => $userassignment->assignmentid));
        if (!$this->assignment) {
            throw new UserAssignmentException(get_string('error:assignmentnotfound', 'totara_program'));
        }
        // $this->completion = get_record('prog_completion', 'programid', $userassignment->programid, 'userid', $userassignment->userid, 'courseset', 0);

    }

    public static function factory($assignmenttype, $assignmentid) {
        global $USER_ASSIGNMENT_CLASSNAMES;

        if (!array_key_exists($assignmenttype, $USER_ASSIGNMENT_CLASSNAMES)) {
            throw new UserAssignmentException(get_string('error:userassignmenttypenotfound', 'totara_program'));
        }

        if (class_exists($USER_ASSIGNMENT_CLASSNAMES[$assignmenttype])) {
            $classname = $USER_ASSIGNMENT_CLASSNAMES[$assignmenttype];
            return new $classname($assignmentid);
        } else {
            throw new UserAssignmentException(get_string('error:userassignmentclassnotfound', 'totara_program'));
        }
    }

    abstract public function display_criteria();

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
     * Conveinence function to return a list of assignments for a particular
     * program and user
     * @param int $programid
     * @param int $userid
     * @return array of records or false
     */
    public static function get_user_assignments($programid, $userid) {
        global $DB;
        return $DB->get_records_select('prog_user_assignment', "programid = ? AND userid = ?", array($programid, $userid));
    }

}

class prog_organisation_assignment extends prog_user_assignment {

    public function display_criteria() {
        global $DB;
        $organisation_name = $DB->get_field('org', 'fullname', array('id' => $this->assignment->assignmenttypeid));
        $out = '';
        $out .= html_writer::start_tag('li', array('class' => 'assignmentcriteria'));
        $out .= html_writer::start_tag('span', array('class' => 'criteria'));
        $out .= get_string('memberoforg', 'totara_program', $organisation_name);
        $out .= html_writer::end_tag('span');
        $out .= html_writer::end_tag('li');
        return $out;
    }

}

class prog_position_assignment extends prog_user_assignment {

    public function display_criteria() {
        global $DB;
        $position_name = $DB->get_field('pos', 'fullname', array('id' => $this->assignment->assignmenttypeid));
        $out = '';
        $out .= html_writer::start_tag('li', array('class' => 'assignmentcriteria'));
        $out .= html_writer::start_tag('span', array('class' => 'criteria'));
        $out .= get_string('holdposof', 'totara_program', $position_name);
        $out .= html_writer::end_tag('span');
        $out .= html_writer::end_tag('li');
        return $out;
    }

}

class prog_cohort_assignment extends prog_user_assignment {

    public function display_criteria() {
        global $DB;
        $cohort_name = $DB->get_field('cohort', 'name', array('id' => $this->assignment->assignmenttypeid));
        $out = '';
        $out .= html_writer::start_tag('li', array('class' => 'assignmentcriteria'));
        $out .= html_writer::start_tag('span', array('class' => 'criteria'));
        $out .= get_string('memberofcohort', 'totara_program', $cohort_name);
        $out .= html_writer::end_tag('span');
        $out .= html_writer::end_tag('li');
        return $out;
    }

}

class prog_manager_assignment extends prog_user_assignment {

    public function display_criteria() {
        global $DB;
        $managers_name = $DB->get_record_select('user', "id = ?", array($this->assignment->assignmenttypeid), $DB->sql_fullname() . ' as fullname');
        $out = '';
        $out .= html_writer::start_tag('li', array('class' => 'assignmentcriteria'));
        $out .= html_writer::start_tag('span', array('class' => 'criteria'));
        $out .= get_string('partofteam', 'totara_program', $managers_name->fullname);
        $out .= html_writer::end_tag('span');
        $out .= html_writer::end_tag('li');
        return $out;
    }

}

class prog_individual_assignment extends prog_user_assignment {

    public function display_criteria() {
        $out = '';
        $out .= html_writer::start_tag('li', array('class' => 'assignmentcriteria'));
        $out .= html_writer::start_tag('span', array('class' => 'criteria'));
        $out .= get_string('assignedasindividual', 'totara_program');
        $out .= html_writer::end_tag('span');
        $out .= html_writer::end_tag('li');
        return $out;
    }
}

class UserAssignmentException extends Exception { }
