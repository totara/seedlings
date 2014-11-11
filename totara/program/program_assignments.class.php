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

require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

define('ASSIGNTYPE_ORGANISATION', 1);
define('ASSIGNTYPE_POSITION', 2);
define('ASSIGNTYPE_COHORT', 3);
define('ASSIGNTYPE_MANAGER', 4);
define('ASSIGNTYPE_INDIVIDUAL', 5);

global $ASSIGNMENT_CATEGORY_CLASSNAMES;

$ASSIGNMENT_CATEGORY_CLASSNAMES = array(
    ASSIGNTYPE_ORGANISATION => 'organisations_category',
    ASSIGNTYPE_POSITION     => 'positions_category',
    ASSIGNTYPE_COHORT       => 'cohorts_category',
    ASSIGNTYPE_MANAGER      => 'managers_category',
    ASSIGNTYPE_INDIVIDUAL   => 'individuals_category'
);

define('COMPLETION_TIME_NOT_SET', -1);
define('COMPLETION_TIME_UNKNOWN', 0);
define('COMPLETION_EVENT_NONE', 0);
define('COMPLETION_EVENT_FIRST_LOGIN', 1);
define('COMPLETION_EVENT_POSITION_START_DATE', 2);
define('COMPLETION_EVENT_PROGRAM_COMPLETION', 3);
define('COMPLETION_EVENT_COURSE_COMPLETION', 4);
define('COMPLETION_EVENT_PROFILE_FIELD_DATE', 5);
define('COMPLETION_EVENT_ENROLLMENT_DATE', 6);

global $COMPLETION_EVENTS_CLASSNAMES;

$COMPLETION_EVENTS_CLASSNAMES = array(
    COMPLETION_EVENT_FIRST_LOGIN            => 'prog_assigment_completion_first_login',
    COMPLETION_EVENT_POSITION_START_DATE    => 'prog_assigment_completion_position_start_date',
    COMPLETION_EVENT_PROGRAM_COMPLETION     => 'prog_assigment_completion_program_completion',
    COMPLETION_EVENT_COURSE_COMPLETION      => 'prog_assigment_completion_course_completion',
    COMPLETION_EVENT_PROFILE_FIELD_DATE     => 'prog_assigment_completion_profile_field_date',
    COMPLETION_EVENT_ENROLLMENT_DATE        => 'prog_assigment_completion_enrollment_date',
);

/**
 * Class representing the program assignments
 */
class prog_assignments {

    protected $assignments;

    function __construct($programid) {
        $this->programid = $programid;
        $this->init_assignments($programid);
    }

    /**
     * Resets the assignments property so that it contains only the assignments
     * that are currently stored in the database. This is necessary after
     * assignments are updated
     *
     * @param int $programid
     */
    public function init_assignments($programid) {
        global $DB;
        $this->assignments = array();
        $assignments = $DB->get_records('prog_assignment', array('programid' => $programid));
        $this->assignments = $assignments;
    }

    public function get_assignments() {
        return $this->assignments;
    }

    public static function factory($assignmenttype) {
        global $ASSIGNMENT_CATEGORY_CLASSNAMES;

        if (!array_key_exists($assignmenttype, $ASSIGNMENT_CATEGORY_CLASSNAMES)) {
            throw new Exception('Assignment category type not found');
        }

        if (class_exists($ASSIGNMENT_CATEGORY_CLASSNAMES[$assignmenttype])) {
            $classname = $ASSIGNMENT_CATEGORY_CLASSNAMES[$assignmenttype];
            return new $classname();
        } else {
            throw new Exception('Assignment category class not found');
        }
    }

    /**
     * Deletes all the assignments and user assignments for this program
     *
     * @return bool true|Exception
     */
    public function delete() {
        global $DB;
        $transaction = $DB->start_delegated_transaction();

        // delete all user assignments
        $DB->delete_records('prog_user_assignment', array('programid' => $this->programid));
        // also delete future user assignments
        $DB->delete_records('prog_future_user_assignment', array('programid' => $this->programid));
        // delete all configured assignments
        $DB->delete_records('prog_assignment', array('programid' => $this->programid));
        // delete all exceptions
        $DB->delete_records('prog_exception', array('programid' => $this->programid));

        $transaction->allow_commit();

        return true;
    }

    /**
     * Returns the number of assignments found for the current program
     * who dont have exceptions
     *
     * @return integer The number of user assignments
     */
    public function count_active_user_assignments() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/program/program.class.php');

        list($exception_sql, $params) = $DB->get_in_or_equal(array(PROGRAM_EXCEPTION_NONE, PROGRAM_EXCEPTION_RESOLVED));
        $params[] = $this->programid;

        $count = $DB->count_records_sql("SELECT COUNT(DISTINCT userid) FROM {prog_user_assignment} WHERE exceptionstatus {$exception_sql} AND programid = ?", $params);
        return $count;
    }

    /**
     * Returns the total user assignments for the current program
     *
     * @return integer The number of users assigned to the current program
     */
    public function count_total_user_assignments() {
        global $DB;

        // also include future assignments in total
        $sql = "SELECT COUNT(DISTINCT userid) FROM (SELECT userid FROM {prog_user_assignment} WHERE programid = ?
            UNION SELECT userid FROM {prog_future_user_assignment} WHERE programid = ?) q";
        $count = $DB->count_records_sql($sql, array($this->programid, $this->programid));

        return $count;
    }

    /**
     * Returns the number of users found for the current program
     * who have exceptions
     *
     * @return integer The number of users
     */
    public function count_user_assignment_exceptions() {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT ex.userid)
                FROM {prog_exception} ex
                INNER JOIN {user} us ON us.id = ex.userid
                WHERE ex.programid = ? AND us.deleted = ?";
        return $DB->count_records_sql($sql, array($this->programid, 0));
    }

    /**
     * Returns an HTML string suitable for displaying as the label for the
     * assignments in the program overview form
     *
     * @return string
     */
    public function display_form_label() {
        $out = '';
        $out .= get_string('instructions:assignments1', 'totara_program');
        return $out;
    }

    /**
     * Returns an HTML string suitable for displaying as the element body
     * for the assignments in the program overview form
     *
     * @return string
     */
    public function display_form_element() {
        global $OUTPUT, $ASSIGNMENT_CATEGORY_CLASSNAMES;

        $emptyarray = array(
            'typecount' => 0,
            'users'     => 0
        );

        $assignmentdata = array(
            ASSIGNTYPE_ORGANISATION => $emptyarray,
            ASSIGNTYPE_POSITION => $emptyarray,
            ASSIGNTYPE_COHORT => $emptyarray,
            ASSIGNTYPE_MANAGER => $emptyarray,
            ASSIGNTYPE_INDIVIDUAL => $emptyarray,
        );

        $out = '';

        if (count($this->assignments)) {

            $usertotal = 0;

            foreach ($this->assignments as $assignment) {
                $assignmentob = prog_assignments::factory($assignment->assignmenttype);

                $assignmentdata[$assignment->assignmenttype]['typecount']++;

                $users = $assignmentob->get_affected_users_by_assignment($assignment);
                $usercount = count($users);
                if ($users) {
                    $assignmentdata[$assignment->assignmenttype]['users'] += $usercount;
                }
                $usertotal += $usercount;
            }

            $table = new html_table();
            $table->head = array(
                get_string('overview', 'totara_program'),
                get_string('numlearners', 'totara_program')
            );
            $table->data = array();

            $categoryrow = 0;
            foreach ($assignmentdata as $categorytype => $data) {
                $categoryclassname = $ASSIGNMENT_CATEGORY_CLASSNAMES[$categorytype];

                $styleclass = ($categoryrow % 2 == 0) ? 'even' : 'odd';

                $row = array();
                $row[] = $data['typecount'].' '.get_string($categoryclassname, 'totara_program');
                $row[] = $data['users'];

                $table->data[] = $row;
                $table->rowclass[] = $styleclass;

                $categoryrow++;
            }
            $helpbutton = $OUTPUT->help_icon('totalassignments', 'totara_program');
            $table->data[] = array(
                html_writer::tag('strong', get_string('totalassignments', 'totara_program')),
                html_writer::tag('strong', $usertotal)
            );
            $table->rowclass[] = 'total';

            $out .= html_writer::table($table, true);

        } else {
            $out .= get_string('noprogramassignments', 'totara_program');
        }

        return $out;
    }

    /**
     * Returns the script to be run when a specific completion event is chosen
     *
     * @global array $COMPLETION_EVENTS_CLASSNAMES
     * @param string $name
     * @return string
     */
    public static function get_completion_events_script($name="eventtype") {
        global $COMPLETION_EVENTS_CLASSNAMES;

        $out = '';

        $out .= "
            function handle_completion_selection() {
                var eventselected = $('#eventtype option:selected').val();
                eventid = eventselected;
        ";

        // Get the script that should be run if we select a specific event
        foreach ($COMPLETION_EVENTS_CLASSNAMES as $class) {
            $event = new $class();
            $out .= "if (eventid == ". $event->get_id() .") { " . $event->get_script() . " }";
        }

        $out .= "
            };
        ";

        return $out;
    }

    public static function get_confirmation_template() {
        global $ASSIGNMENT_CATEGORY_CLASSNAMES;

        $table = new html_table();
        $table->head = array('', get_string('added', 'totara_program'), get_string('removed', 'totara_program'));
        $table->data = array();
        foreach ($ASSIGNMENT_CATEGORY_CLASSNAMES as $classname) {
            $category = new $classname();
            $spanadded = html_writer::tag('span', '0', array('class' => 'added_'.$category->id));
            $spanremoved = html_writer::tag('span', '0', array('class' => 'removed_'.$category->id));
            $table->data[] = array($category->name, $spanadded, $spanremoved);
        }

        $spanTotalAdded = html_writer::tag('strong', html_writer::tag('span', '0', array('class' => 'total_added')));
        $spanTotalRemoved = html_writer::tag('strong', html_writer::tag('span', '0', array('class' => 'total_removed')));
        $table->data[] = array(html_writer::tag('strong', get_string('total')), $spanTotalAdded, $spanTotalRemoved);

        $tableHTML = html_writer::table($table, true);
        // Strip new lines as they screw up the JS
        $order   = array("\r\n", "\n", "\r");
        $table = str_replace($order, '', $tableHTML);

        $data = array();
        $data['html'] = html_writer::tag('div', get_string('youhavemadefollowingchanges', 'totara_program') . html_writer::empty_tag('br') . html_writer::empty_tag('br') . $tableHTML . html_writer::empty_tag('br') . get_string('tosaveassignments','totara_program'));

        return json_encode($data);
    }
}

/**
 * Abstract class for a category which appears on the program assignments screen.
 */
abstract class prog_assignment_category {
    public $id;
    public $name = '';
    public $table = '';
    protected $buttonname = '';
    protected $headers = array(); // array of headers as strings?
    protected $data = array(); // array of arrays of strings (html)

    /**
     * Prints out the actual html for the category, by looking at the headers
     * and data which should have been set by sub class
     *
     * @param bool $return
     * @return string html
     */
    function display() {
        global $PAGE;
        $renderer = $PAGE->get_renderer('totara_program');
        return $renderer->assignment_category_display($this, $this->headers, $this->buttonname, $this->data);
    }

    /**
     * Checks whether this category has any items by looking
     * @return int
     */
    function has_items() {
        return count($this->data);
    }

    /**
     * Builds the table that appears for this category by filling $this->headers
     * and $this->data
     *
     * @param string $prefix
     * @param int $programid
     */
    abstract function build_table($programid);

    /**
     * Builds a single row by looking at the passed in item
     *
     * @param object $item
     */
    abstract function build_row($item);

    /**
     * Returns any javascript that should be loaded to be used by the category
     *
     * @access  public
     * @param   int     $programid
     */
    abstract function get_js($programid);

    /**
     * Gets the number of affected users
     */
    abstract function user_affected_count($item);

    /**
     * Gets the affected users for the given item record
     *
     * @param object $item An object containing data about the assignment
     * @param int $userid (optional) Only look at this user
     */
    abstract function get_affected_users($item, $userid=0);

    /**
     * Retrieves an array of all the users affected by an assignment based on the
     * assignment record
     *
     * @param object $assignment The db record from 'prog_assignment' for this assignment
     * @param int $userid (optional) only look at this user
     */
    abstract function get_affected_users_by_assignment($assignment, $userid=0);

    /**
     * Updates the assignments by looking at the post data
     *
     * @param object $data  The data we will be updating assignments with
     * @param bool $delete  A flag to stop deletion/rebuild from external pages
     */
    function update_assignments($data, $delete = true) {
        global $DB;

        // Store list of seen ids
        $seenids = array();

        // If theres inputs for this assignment category (this)
        if (isset($data->item[$this->id])) {

            // Get the list of item ids
            $itemids = array_keys($data->item[$this->id]);
            $seenids = $itemids;

            $insertssql = array();
            $insertsparams = array();
            // Get a list of assignments
            $sql = "SELECT p.assignmenttypeid as hashkey, p.* FROM {prog_assignment} p WHERE programid = ? AND assignmenttype = ?";
            $assignment_hashmap = $DB->get_records_sql($sql, array($data->id, $this->id));

            foreach ($itemids as $itemid) {
                $object = isset($assignment_hashmap[$itemid]) ? $assignment_hashmap[$itemid] : false;
                if ($object !== false) {
                    $original_object = clone $object;
                }

                if (!$object) {
                    $object = new stdClass(); //same for all cats
                    $object->programid = $data->id; //same for all cats
                    $object->assignmenttype = $this->id;
                    $object->assignmenttypeid = $itemid;
                }

                // Let the inheriting object deal with the include children field as it's specific to them
                $object->includechildren = $this->get_includechildren($data, $object);

                // Get the completion time.
                $object->completiontime = !empty($data->completiontime[$this->id][$itemid]) ?
                    $data->completiontime[$this->id][$itemid] : COMPLETION_TIME_NOT_SET;

                // Get the completion event.
                $object->completionevent = isset($data->completionevent[$this->id][$itemid]) ?
                    $data->completionevent[$this->id][$itemid] : COMPLETION_EVENT_NONE;

                // Get the completion instance.
                $object->completioninstance = !empty($data->completioninstance[$this->id][$itemid]) ?
                    $data->completioninstance[$this->id][$itemid] : 0;

                if ($object->completiontime != COMPLETION_TIME_NOT_SET) {
                    if ($object->completionevent == COMPLETION_EVENT_NONE) {
                        // Convert fixed dates.
                        $object->completiontime = totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'), $object->completiontime);
                    } else {
                        // Convert relative dates.
                        $parts = explode(' ', $object->completiontime);
                        if (!isset($parts[0]) || !isset($parts[1])) {
                            continue;
                        }
                        $num = $parts[0];
                        $period = $parts[1];
                        $object->completiontime = program_utilities::duration_implode($num, $period);
                    }
                }

                if (isset($object->id)) {
                    // Check if we actually need an update..
                    if ($original_object->includechildren != $object->includechildren ||
                        $original_object->completiontime != $object->completiontime ||
                        $original_object->completionevent != $object->completionevent ||
                        $original_object->completioninstance != $object->completioninstance) {

                        if (!$DB->update_record('prog_assignment', $object)) {
                            print_error('error:updatingprogramassignment', 'totara_program');
                        }
                    }
                } else {
                    // Create new assignment
                    $insertssql[] = "(?, ?, ?, ?, ?, ?, ?)";
                    $insertsparams[] = array($object->programid, $object->assignmenttype, $object->assignmenttypeid, $object->includechildren, $object->completiontime, $object->completionevent, $object->completioninstance);
                    $this->_add_assignment_hook($object);
                }
            }

            // Execute inserts
            if (count($insertssql) > 0) {
                $sql = "INSERT INTO {prog_assignment} (programid, assignmenttype, assignmenttypeid, includechildren, completiontime, completionevent, completioninstance) VALUES " . implode(', ', $insertssql);
                $params = array();
                foreach ($insertsparams as $p) {
                    $params = array_merge($params, $p);
                }
                $DB->execute($sql, $params);
            }
        }

        if ($delete) {
            // Delete any records which exist in the prog_assignment table but that
            // weren't submitted just now. Also delete any existing exceptions that
            // related to the assignment being deleted
            $where = "programid = ? AND assignmenttype = ?";
            $params = array($data->id, $this->id);
            if (count($seenids) > 0) {
                list($idssql, $idsparams) = $DB->get_in_or_equal($seenids, SQL_PARAMS_QM, 'param', false);
                $where .= " AND assignmenttypeid {$idssql}";
                $params = array_merge($params, $idsparams);
            }
            $assignments_to_delete = $DB->get_records_select('prog_assignment', $where, $params);
            foreach ($assignments_to_delete as $assignment_to_delete) {
                // delete any exceptions related to this assignment
                prog_exceptions_manager::delete_exceptions_by_assignment($assignment_to_delete->id);

                // delete any future user assignments related to this assignment
                $DB->delete_records('prog_future_user_assignment', array('assignmentid' => $assignment_to_delete->id, 'programid' => $data->id));
            }
            $DB->delete_records_select('prog_assignment', $where, $params);
        }
    }

    /**
     * Remove user assignments from programs where users not longer belong to the category assignment.
     *
     * @param int $programid Program ID where users are assigned
     * @param int $assignmenttypeid
     * @param array $userids Array of user IDs that we want to remove
     * @return bool $success
     */
    function remove_outdated_assignments($programid, $assignmenttypeid, $userids) {
        global $DB;
        $success = false;

        // Do nothing if it's not a group assignment or the id of the assignment type is not given or no users are passed.
        if ($this->id == ASSIGNTYPE_INDIVIDUAL) {
            return $success;
        }

        if (empty($programid)) {
            return $success;
        }

        if (empty($assignmenttypeid)) {
            return $success;
        }

        if (empty($userids)) {
            return $success;
        }

        list($sql, $params) = $DB->get_in_or_equal($userids);
        $params[] = $programid;
        $params[] = $this->id;
        $params[] = $assignmenttypeid;

        $sql = "DELETE FROM {prog_user_assignment}
            WHERE userid {$sql}
              AND programid = ?
              AND assignmentid IN (SELECT id FROM {prog_assignment} WHERE assignmenttype = ? AND assignmenttypeid = ?)";
        $success = $DB->execute($sql, $params);

        return $success;
    }

    /**
     * Called when an assignment of this category is going to be added
     * @param $object
     */
    protected function _add_assignment_hook($object) {
        return true;
    }

    /**
     * Called when an assignment of this list is going to be deleted
     * @param $object
     */
    protected function _delete_assignment_hook($object) {
        return true;
    }

    /**
     * Gets the include children part from the post data
     * @param <type> $data
     * @param <type> $object
     */
    abstract function get_includechildren($data, $object);

    function get_completion($item) {
        global $CFG, $OUTPUT;
        $completion_string = get_string('setcompletion', 'totara_program');

        $show_deletecompletionlink = false;
        if (empty($item->completiontime)) {
            $item->completiontime = COMPLETION_TIME_NOT_SET;
        }

        if (!isset($item->completionevent)) {
            $item->completionevent = 0;
        }

        if (!isset($item->completioninstance)) {
            $item->completioninstance = 0;
        }

        if ($item->completionevent == COMPLETION_EVENT_NONE) {
            // Completiontime must be a timestamp.
            if ($item->completiontime != COMPLETION_TIME_NOT_SET) {
                // Print a date.
                $item->completiontime = trim(userdate($item->completiontime,
                    get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false));
                $completion_string = self::build_completion_string($item->completiontime, $item->completionevent, $item->completioninstance);
                $show_deletecompletionlink = true;
            }
        } else {
            $parts = program_utilities::duration_explode($item->completiontime);
            $item->completiontime = $parts->num . ' ' . $parts->period;
            $completion_string = self::build_completion_string(
                $item->completiontime, $item->completionevent, $item->completioninstance);
            $show_deletecompletionlink = true;
        }

        $html = '';
        if ($item->completiontime != COMPLETION_TIME_NOT_SET && !empty($item->completiontime)) {
            $html .= html_writer::empty_tag('input', array('type' => 'hidden',
                'name' => 'completiontime['.$this->id.']['.$item->id.']', 'value' => $item->completiontime));
        }
        if ($item->completionevent != COMPLETION_EVENT_NONE) {
            $html .= html_writer::empty_tag('input', array('type' => 'hidden',
                'name' => 'completionevent['.$this->id.']['.$item->id.']', 'value' => $item->completionevent));
        }
        if (!empty($item->completioninstance)) {
            $html .= html_writer::empty_tag('input', array('type' => 'hidden',
                'name' => 'completioninstance['.$this->id.']['.$item->id.']', 'value' => $item->completioninstance));
        }
        $html .= html_writer::link('#', $completion_string, array('class' => 'completionlink'));
        if ($show_deletecompletionlink) {
            $html .= $OUTPUT->action_icon('#', new pix_icon('t/delete', get_string('removecompletiondate', 'totara_program')), null, array('class' => 'deletecompletiondatelink'));
        }
        return $html;
    }

    public function build_first_table_cell($name, $id, $itemid) {
        global $OUTPUT;
        $output = html_writer::start_tag('div', array('class' => 'totara-item-group'));
        $output .= format_string($name);
        $output .= $OUTPUT->action_icon('#', new pix_icon('t/delete', get_string('delete')), null,
            array('class' => 'deletelink totara-item-group-icon'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'item['.$id.']['.$itemid.']', 'value' => '1'));
        return $output;
    }

    public static function build_completion_string($completiontime, $completionevent, $completioninstance) {
        global $COMPLETION_EVENTS_CLASSNAMES, $TIMEALLOWANCESTRINGS;

        if (isset($COMPLETION_EVENTS_CLASSNAMES[$completionevent])) {
            $eventobject = new $COMPLETION_EVENTS_CLASSNAMES[$completionevent];

            // $completiontime comes in the form '1 2' where 1 is the num and 2 is the period

            $parts = explode(' ',$completiontime);

            if (!isset($parts[0]) || !isset($parts[1])) {
                return '';
            }

            $a = new stdClass();
            $a->num = $parts[0];
            if (isset($TIMEALLOWANCESTRINGS[$parts[1]])) {
                $a->period = get_string($TIMEALLOWANCESTRINGS[$parts[1]], 'totara_program');
            } else {
                return '';
            }
            $a->event = $eventobject->get_completion_string();
            $a->instance = $eventobject->get_item_name($completioninstance);

            if (!empty($a->instance)) {
                $a->instance = "'$a->instance'";
            }

            return get_string('completewithinevent', 'totara_program', $a);
        }
        else {
            $datepattern = get_string('datepickerlongyearregexphp', 'totara_core');
            if (preg_match($datepattern, $completiontime, $matches) == 0) {
                return '';
            } else {
                return get_string('completebytime', 'totara_program', $completiontime);
            }
        }
    }

    static function get_categories() {
        $tempcategories = array(
            new organisations_category(),
            new positions_category(),
            new cohorts_category(),
            new managers_category(),
            new individuals_category(),
        );
        $categories = array();
        foreach ($tempcategories as $category) {
            $categories[$category->id] = $category;
        }
        return $categories;
    }
}

class organisations_category extends prog_assignment_category {


    function __construct() {
        $this->id = ASSIGNTYPE_ORGANISATION;
        $this->name = get_string('organisations', 'totara_program');
        $this->buttonname = get_string('addorganisationstoprogram', 'totara_program');
    }

    function build_table($programid) {
        global $DB;

        $this->headers = array(
            get_string('organisationname', 'totara_program'),
            get_string('allbelow', 'totara_program'),
            get_string('complete','totara_program'),
            get_string('numlearners','totara_program')
        );

        // Go to the database and gets the assignments
        $items = $DB->get_records_sql(
            "SELECT org.id, org.fullname, org.path, prog_assignment.includechildren, prog_assignment.completiontime, prog_assignment.completionevent, prog_assignment.completioninstance
        FROM {prog_assignment} prog_assignment
        INNER JOIN {org} org on org.id = prog_assignment.assignmenttypeid
        WHERE prog_assignment.programid = ?
        AND prog_assignment.assignmenttype = ?", array($programid, $this->id));

        // Convert these into html
        if (!empty($items)) {
            foreach ($items as $item) {
                $this->data[] = $this->build_row($item);
            }
        }
    }

    function get_item($itemid) {
        global $DB;
        return $DB->get_record('org', array('id' => $itemid));
    }

    function build_row($item) {

        if (is_int($item)) {
            $item = $this->get_item($item);
        }

        $checked = (isset($item->includechildren) && $item->includechildren == 1) ? true : false;

        $row = array();
        $row[] = $this->build_first_table_cell($item->fullname, $this->id, $item->id);
        $row[] = html_writer::checkbox('includechildren['.$this->id.']['.$item->id.']', '', $checked);
        $row[] = $this->get_completion($item);
        $row[] = $this->user_affected_count($item);

        return $row;
    }

    /**
     * Returns a count of all the users who are assigned to an organisation
     *
     * @global object $CFG
     * @param object $item The organisation record
     * @return int
     */
    function user_affected_count($item) {
        return $this->get_affected_users($item, $userid=0, true);
    }

    /**
     * Returns an array of records containing all the users who are assigned
     * to an organisation
     *
     * @global object $CFG
     * @param object $item The assignment record
     * @param boolean $count If true return the record count instead of the records
     * @return integer|array Record count or array of records
     */
    function get_affected_users($item, $userid=0, $count=false) {
        global $DB;

        $params = array();
        $where = "pa.organisationid = ?";
        $params[] = $item->id;
        if (isset($item->includechildren) && $item->includechildren == 1 && isset($item->path)) {
            $children = $DB->get_fieldset_select('org', 'id', $DB->sql_like('path', '?'), array($item->path . '/%'));
            $children[] = $item->id;
            //replace the existing $params
            list($usql, $params) = $DB->get_in_or_equal($children);
            $where = "pa.organisationid {$usql}";
        }
        if ($userid) {
            $where .= " AND u.id=$userid";
        }

        $select = $count ? 'COUNT(u.id)' : 'u.id';

        $sql = "SELECT $select
                FROM {pos_assignment} AS pa
                INNER JOIN {user} AS u ON pa.userid=u.id
                WHERE $where
                AND u.deleted = 0";
        if ($count) {
            return $DB->count_records_sql($sql, $params);
        }
        else {
            return $DB->get_records_sql($sql, $params);
        }
    }

    function get_affected_users_by_assignment($assignment, $userid = 0) {
        global $DB;

        // Query to retrieves the data required to determine the number of users
        //affected by an assignment
        $sql = "SELECT org.id,
                        org.fullname,
                        org.path,
                        prog_assignment.includechildren,
                        prog_assignment.completiontime,
                        prog_assignment.completionevent,
                        prog_assignment.completioninstance
                FROM {prog_assignment} prog_assignment
                INNER JOIN {org} org ON org.id = prog_assignment.assignmenttypeid
                WHERE prog_assignment.id = ?";

        if ($item = $DB->get_record_sql($sql, array($assignment->id))) {
            return $this->get_affected_users($item, $userid);
        } else {
            return array();
        }

    }

    function get_includechildren($data, $object) {
        if (!isset($data->includechildren)
            || !isset($data->includechildren[$this->id])
            || !isset($data->includechildren[$this->id][$object->assignmenttypeid])) {

            return 0;
        }
        return 1;
    }

    function get_js($programid) {
        $title = addslashes_js(get_string('addorganisationstoprogram', 'totara_program'));
        $url = 'find_hierarchy.php?type=organisation&table=org&programid='.$programid;
        return "M.totara_programassignment.add_category({$this->id}, 'organisations', '{$url}', '{$title}');";
    }
}

class positions_category extends prog_assignment_category {

    function __construct() {
        $this->id = ASSIGNTYPE_POSITION;
        $this->name = get_string('positions', 'totara_program');
        $this->buttonname = get_string('addpositiontoprogram', 'totara_program');
    }

    function build_table($programid) {
        global $DB;
        $this->headers = array(
            get_string('positionsname', 'totara_program'),
            get_string('allbelow', 'totara_program'),
            get_string('complete','totara_program'),
            get_string('numlearners','totara_program')
        );

        // Go to the database and gets the assignments
        $items = $DB->get_records_sql(
            "SELECT pos.id, pos.fullname, pos.path, prog_assignment.includechildren, prog_assignment.completiontime, prog_assignment.completionevent, prog_assignment.completioninstance
               FROM {prog_assignment} prog_assignment
         INNER JOIN {pos} pos on pos.id = prog_assignment.assignmenttypeid
              WHERE prog_assignment.programid = ?
                AND prog_assignment.assignmenttype = ?", array($programid, $this->id));

        // Convert these into html
        foreach ($items as $item) {
            $this->data[] = $this->build_row($item);
        }
    }

    function get_item($itemid) {
        global $DB;
        return $DB->get_record('pos', array('id' => $itemid));
    }

    function build_row($item) {
        if (is_int($item)) {
            $item = $this->get_item($item);
        }

        $checked = (isset($item->includechildren) && $item->includechildren == 1) ? true : false;

        $row = array();
        $row[] = $this->build_first_table_cell($item->fullname, $this->id, $item->id);
        $row[] = html_writer::checkbox('includechildren['.$this->id.']['.$item->id.']', '', $checked);
        $row[] = $this->get_completion($item);
        $row[] = $this->user_affected_count($item);

        return $row;
    }

    /**
     * Returns a count of all the users who are assigned to a position
     *
     * @global object $CFG
     * @param object $item The organisation record
     * @return int
     */
    function user_affected_count($item) {
        return $this->get_affected_users($item, 0, true);
    }

    /**
     * Returns an array of records containing all the users who are assigned
     * to a position
     *
     * @global object $CFG
     * @param object $assignment The assignment record
     * @param boolean $count If true return the record count instead of the records
     * @return integer|array Record count or array of records
     */
    function get_affected_users($item, $userid = 0, $count=false) {
        global $DB;

        $where = "pa.positionid = ?";
        $params = array($item->id);
        if (isset($item->includechildren) && $item->includechildren == 1 && isset($item->path)) {
            $children = $DB->get_fieldset_select('pos', 'id', $DB->sql_like('path', '?'), array($item->path . '/%'));
            $children[] = $item->id;
            // Replace the existing $params.
            list($usql, $params) = $DB->get_in_or_equal($children);
            $where = "pa.positionid $usql";
        }

        $select = $count ? 'COUNT(u.id)' : 'u.id';

        $sql = "SELECT $select
                FROM {pos_assignment} pa
                INNER JOIN {user} u ON pa.userid = u.id
                WHERE $where
                AND pa.type = ?
                AND u.deleted = 0";
        $params[] = POSITION_TYPE_PRIMARY;
        if ($userid) {
            $sql .= " AND u.id = ?";
            $params[] = $userid;
        }
        if ($count) {
            return $DB->count_records_sql($sql, $params);
        }
        else {
            return $DB->get_records_sql($sql, $params);
        }
    }

    function get_affected_users_by_assignment($assignment, $userid = 0) {
        global $DB;

        // Query to retrieves the data required to determine the number of users
        // affected by an assignment.
        $sql = "SELECT pos.id,
                        pos.fullname,
                        pos.path,
                        prog_assignment.includechildren,
                        prog_assignment.completiontime,
                        prog_assignment.completionevent,
                        prog_assignment.completioninstance
                FROM {prog_assignment} prog_assignment
                INNER JOIN {pos} pos on pos.id = prog_assignment.assignmenttypeid
                WHERE prog_assignment.id = ?";

        if ($item = $DB->get_record_sql($sql, array($assignment->id))) {
            return $this->get_affected_users($item, $userid);
        } else {
            return array();
        }

    }

    function get_includechildren($data, $object) {
        if (!isset($data->includechildren)
            || !isset($data->includechildren[$this->id])
            || !isset($data->includechildren[$this->id][$object->assignmenttypeid])) {

            return 0;
        }
        return 1;
    }

    function get_js($programid) {
        $title = addslashes_js(get_string('addpositiontoprogram', 'totara_program'));
        $url = 'find_hierarchy.php?type=position&table=pos&programid='.$programid;
        return "M.totara_programassignment.add_category({$this->id}, 'positions', '{$url}', '{$title}');";
    }
}

class cohorts_category extends prog_assignment_category {

    function __construct() {
        $this->id = ASSIGNTYPE_COHORT;
        $this->name = get_string('cohorts', 'totara_program');
        $this->buttonname = get_string('addcohortstoprogram', 'totara_program');
    }

    function build_table($programid) {
        global $DB;
        $this->headers = array(
            get_string('cohortname', 'totara_program'),
            get_string('type', 'totara_program'),
            get_string('complete','totara_program'),
            get_string('numlearners','totara_program')
        );

        // Go to the database and gets the assignments.
        $items = $DB->get_records_sql(
            "SELECT cohort.id, cohort.name as fullname, cohort.cohorttype, prog_assignment.completiontime, prog_assignment.completionevent, prog_assignment.completioninstance
            FROM {prog_assignment} prog_assignment
            INNER JOIN {cohort} cohort ON cohort.id = prog_assignment.assignmenttypeid
            WHERE prog_assignment.programid = ?
            AND prog_assignment.assignmenttype = ?", array($programid, $this->id));

        // Convert these into html.
        if (!empty($items)) {
            foreach ($items as $item) {
                $this->data[] = $this->build_row($item);
            }
        }
    }

    function get_item($itemid) {
        global $DB;
        return $DB->get_record('cohort', array('id' => $itemid), 'id, name as fullname, cohorttype');
    }

    function build_row($item) {
        global $CFG;

        require_once($CFG->dirroot.'/cohort/lib.php');

        if (is_int($item)) {
            $item = $this->get_item($item);
        }

        $cohorttypes = cohort::getCohortTypes();
        $cohortstring = $cohorttypes[$item->cohorttype];

        $row = array();
        $row[] = $this->build_first_table_cell($item->fullname, $this->id, $item->id);
        $row[] = $cohortstring;
        $row[] = $this->get_completion($item);
        $row[] = $this->user_affected_count($item);

        return $row;
    }

    function user_affected_count($item) {
        return $this->get_affected_users($item, 0, true);
    }

    function get_affected_users($item, $userid = 0, $count = false) {
        global $DB;
        $select = $count ? 'COUNT(u.id)' : 'u.id';
        $sql = "SELECT $select
                  FROM {cohort_members} AS cm
            INNER JOIN {user} AS u ON cm.userid=u.id
                 WHERE cm.cohortid = ?
                   AND u.deleted = 0";
        $params = array($item->id);
        if ($userid) {
            $sql .= " AND u.id = ?";
            $params[] = $userid;
        }
        if ($count) {
            return $DB->count_records_sql($sql, $params);
        }
        else {
            return $DB->get_records_sql($sql, $params);
        }
    }

    function get_affected_users_by_assignment($assignment, $userid = 0) {
        $item = new stdClass();
        $item->id = $assignment->assignmenttypeid;
        return $this->get_affected_users($item, $userid);
    }

    /**
     * Unused by the cohorts category, so just return zero
     */
    function get_includechildren($data, $object) {
        return 0;
    }

    function get_js($programid) {
        $title = addslashes_js(get_string('addcohortstoprogram', 'totara_program'));
        $url = 'find_cohort.php?programid='.$programid;
        return "M.totara_programassignment.add_category({$this->id}, 'cohorts', '{$url}', '{$title}');";
    }
    protected function _add_assignment_hook($object) {
        return true;
    }

    protected function _delete_assignment_hook($object) {
        return true;
    }
}

class managers_category extends prog_assignment_category {

    function __construct() {
        $this->id = ASSIGNTYPE_MANAGER;
        $this->name = get_string('managementhierarchy', 'totara_program');
        $this->buttonname = get_string('addmanagerstoprogram', 'totara_program');
    }

    function build_table($programid) {
        global $DB;
        $this->headers = array(
            get_string('managername', 'totara_program'),
            get_string('for', 'totara_program'),
            get_string('complete','totara_program'),
            get_string('numlearners','totara_program')
        );

        // Go to the database and gets the assignments.
        $items = $DB->get_records_sql("
            SELECT u.id, " . $DB->sql_fullname('u.firstname', 'u.lastname') . " as fullname,
                pa.managerpath AS path, prog_assignment.includechildren, prog_assignment.completiontime,
                prog_assignment.completionevent, prog_assignment.completioninstance
              FROM {prog_assignment} prog_assignment
        INNER JOIN {user} u ON u.id = prog_assignment.assignmenttypeid
         LEFT JOIN {pos_assignment} pa ON pa.userid = u.id AND pa.type = ?
             WHERE prog_assignment.programid = ?
               AND prog_assignment.assignmenttype = ?
        ", array(POSITION_TYPE_PRIMARY, $programid, $this->id));

        // Convert these into html.
        if (!empty($items)) {
            foreach ($items as $item) {
                //sometimes a manager may not have a pos_assignment record e.g. top manager in the tree
                //so we need to set a default path
                if (empty($item->path)) {
                    $item->path = '/' . $item->id;
                }
                $this->data[] = $this->build_row($item);
            }
        }
    }

    function get_item($itemid) {
        global $DB;
        $sql = "SELECT u.id, " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname, pa.managerpath AS path
                  FROM {user} AS u
             LEFT JOIN {pos_assignment} pa ON u.id = pa.userid AND pa.type = ?
                 WHERE u.id = ?";
        // Sometimes a manager may not have a pos_assignment record e.g. top manager in the tree
        // so we need to set a default path.
        $item = $DB->get_record_sql($sql, array(POSITION_TYPE_PRIMARY, $itemid));
        if (empty($item->path)) {
            $item->path = "/{$itemid}";
        }
        return $item;
    }

    function build_row($item) {
        global $OUTPUT;

        if (is_int($item)) {
            $item = $this->get_item($item);
        }

        $selectedid = (isset($item->includechildren) && $item->includechildren == 1) ? 1 : 0;
        $options = array(
            0 => get_string('directteam', 'totara_program'),
            1 => get_string('allbelowlower', 'totara_program'));

        $row = array();
        $row[] = $this->build_first_table_cell($item->fullname, $this->id, $item->id);
        $row[] = html_writer::select($options, 'includechildren['.$this->id.']['.$item->id.']', $selectedid);
        $row[] = $this->get_completion($item);
        $row[] = $this->user_affected_count($item);

        return $row;
    }

    function user_affected_count($item) {
        return $this->get_affected_users($item, 0, true);
    }

    function get_affected_users($item, $userid = 0, $count=false) {
        global $DB;
        $primarytype = POSITION_TYPE_PRIMARY;

        if (isset($item->includechildren) && $item->includechildren == 1 && isset($item->path)) {
            // For a manager's entire team.
            $where = "pa.type = ? AND " . $DB->sql_like('pa.managerpath', '?');
            $params = array($primarytype, $item->path . '/%');
        } else {
            // For a manager's direct team.
            $where = "pa.type = ? AND pa.managerid = ?";
            $params = array($primarytype, $item->id);
        }

        $select = $count ? 'COUNT(pa.userid) AS id' : 'pa.userid AS id';

        $sql = "SELECT $select
                FROM {pos_assignment} pa
                INNER JOIN {user} u ON (pa.userid = u.id AND u.deleted = 0)
                WHERE {$where}";
        if ($userid) {
            $sql .= " AND u.id = ?";
            $params[] = $userid;
        }

        if ($count) {
            return $DB->count_records_sql($sql, $params);
        } else {
            return $DB->get_records_sql($sql, $params);
        }
    }

    function get_affected_users_by_assignment($assignment, $userid = 0) {
        global $DB;
        $primarytype = POSITION_TYPE_PRIMARY;

        // Query to retrieves the data required to determine the number of users
        // affected by an assignment.
        $sql = "SELECT u.id,
                        pa.managerpath AS path,
                        prog_assignment.includechildren
                  FROM {prog_assignment} prog_assignment
            INNER JOIN {user} u ON u.id = prog_assignment.assignmenttypeid
             LEFT JOIN {pos_assignment} pa ON u.id = pa.userid AND pa.type = ?
                 WHERE prog_assignment.id = ?";

        if ($item = $DB->get_record_sql($sql, array(POSITION_TYPE_PRIMARY, $assignment->id))) {
            // Sometimes a manager may not have a pos_assignment record e.g. top manager in the tree.
            // So we need to set a default path.
            if (empty($item->path)) {
                $item->path = "/{$item->id}";
            }
            return $this->get_affected_users($item, $userid);
        } else {
            return array();
        }

    }

    function get_includechildren($data, $object) {
        return $data->includechildren[$this->id][$object->assignmenttypeid];
    }

    function get_js($programid) {
        $title = addslashes_js(get_string('addmanagerstoprogram', 'totara_program'));
        $url = 'find_manager_hierarchy.php?programid='.$programid;
        return "M.totara_programassignment.add_category({$this->id}, 'managers', '{$url}', '{$title}');";
    }
}

class individuals_category extends prog_assignment_category {

    function __construct() {
        $this->id = ASSIGNTYPE_INDIVIDUAL;
        $this->name = get_string('individuals', 'totara_program');
        $this->buttonname = get_string('addindividualstoprogram', 'totara_program');
    }

    function build_table($programid) {
        global $DB;
        $this->headers = array(
            get_string('individualname', 'totara_program'),
            get_string('userid', 'totara_program'),
            get_string('complete','totara_program')
        );

        // Go to the database and gets the assignments.
        $items = $DB->get_records_sql(
            "SELECT individual.id, " . $DB->sql_fullname('individual.firstname', 'individual.lastname') . " as fullname, prog_assignment.completiontime, prog_assignment.completionevent, prog_assignment.completioninstance
               FROM {prog_assignment} prog_assignment
         INNER JOIN {user} individual ON individual.id = prog_assignment.assignmenttypeid
              WHERE prog_assignment.programid = ?
                AND prog_assignment.assignmenttype = ?", array($programid, $this->id));

        // Convert these into html.
        if (!empty($items)) {
            foreach ($items as $item) {
                $this->data[] = $this->build_row($item);
            }
        }
    }

    function get_item($itemid) {
        global $DB;

        return $DB->get_record_select('user',"id = ?", array($itemid), 'id, ' . $DB->sql_fullname() . ' as fullname');
    }

    function build_row($item) {
        global $OUTPUT;

        if (is_int($item)) {
            $item = $this->get_item($item);
        }

        $row = array();
        $row[] = $this->build_first_table_cell($item->fullname, $this->id, $item->id);
        $row[] = $item->id;
        $row[] = $this->get_completion($item);

        return $row;
    }

    function user_affected_count($item) {
        return 1;
    }

    function get_affected_users($item, $userid = 0) {
        $user = (object)array('id'=>$item->assignmenttypeid);
        return array($user);
    }

    function get_affected_users_by_assignment($assignment, $userid = 0) {
        return $this->get_affected_users($assignment, $userid);
    }

    function get_includechildren($data, $object) {
        return 0;
    }

    function get_js($programid) {
        $title = addslashes_js(get_string('addindividualstoprogram', 'totara_program'));
        $url = 'find_individual.php?programid='.$programid;
        return "M.totara_programassignment.add_category({$this->id}, 'individuals', '{$url}', '{$title}');";
    }
}

class user_assignment {
    public $userid, $assignment, $timedue;

    public function __construct($userid, $assignment, $programid) {
        global $DB;
        $this->userid = $userid;
        $this->assignment = $assignment;
        $this->programid = $programid;

        $this->completion = $DB->get_record('prog_completion', array('programid' => $programid,
                                                                     'userid' => $userid,
                                                                     'coursesetid' => '0'));
    }

    /*
     *  Updates timedue for a user assignment
     *
     *  @param $timedue int New timedue
     *  @return bool Success
     */
    public function update($timedue) {
        global $DB;
        if (!empty($this->completion)) {
            $completion_todb = new stdClass();
            $completion_todb->id = $this->completion->id;
            $completion_todb->timedue = $timedue;

            $DB->update_record('prog_completion', $completion_todb);

            return true;
        }

        return false;
    }
}

abstract class prog_assignment_completion_type {
    abstract public function get_id();
    abstract public function get_name();
    abstract public function get_script();
    abstract public function get_item_name($instanceid);
    abstract public function get_completion_string();
    abstract public function get_timestamp($userid, $assignobject);
}

class prog_assigment_completion_first_login extends prog_assignment_completion_type {
    private $timestamps;

    public function get_id() {
        return COMPLETION_EVENT_FIRST_LOGIN;
    }
    public function get_name() {
        return get_string('firstlogin', 'totara_program');
    }
    public function get_script() {
        return "
            totaraDialogs['completionevent'].clear();
        ";
    }
    public function get_item_name($instanceid) {
        return '';
    }
    public function get_completion_string() {
        return get_string('firstlogin', 'totara_program');
    }
    public function get_timestamp($userid, $assignobject) {
        global $DB;
        $rec = $DB->get_record('user', array('id' => $userid), 'id, firstaccess, lastaccess');
        $firstaccess = empty($rec->firstaccess) ? $rec->lastaccess : $rec->firstaccess;

        return $firstaccess;
    }
}

class prog_assigment_completion_position_start_date extends prog_assignment_completion_type {
    private $names, $timestamps;
    public function get_id() {
        return COMPLETION_EVENT_POSITION_START_DATE;
    }
    public function get_name() {
        return get_string('positionstartdate', 'totara_program');
    }
    public function get_script() {
        global $CFG;

        return "
            totaraDialogs['completionevent'].default_url = '$CFG->wwwroot/totara/program/assignment/completion/find_position.php?';
            totaraDialogs['completionevent'].open();

            $('#instancetitle').unbind('click').click(function() {
                handle_completion_selection();
                return false;
            });
        ";
    }
    private function load_data() {
        global $DB;
        $this->names = $DB->get_records_select('pos', '', null, '', 'id, fullname');
        $this->timestamps = $DB->get_records_select('prog_pos_assignment', 'type = ?', array(POSITION_TYPE_PRIMARY), '',
            'id, ' . $DB->sql_concat('userid', "'-'", 'positionid') . ' as hash, timeassigned');
    }
    public function get_item_name($instanceid) {
        // Lazy load data when required.
        if (!isset($this->names)) {
            $this->load_data();
        }
        return $this->names[$instanceid]->fullname;
    }
    public function get_completion_string() {
        return get_string('startinposition', 'totara_program');
    }
    public function get_timestamp($userid, $assignobject) {
        // Lazy load data when required.
        if (!isset($this->timestamps)) {
            $this->load_data();
        }
        if (isset($this->timestamps[$userid . '-' . $assignobject->completioninstance])) {
            return $this->timestamps[$userid . '-' . $assignobject->completioninstance]->timeassigned;
        }
        return false;
    }
}

class prog_assigment_completion_program_completion extends prog_assignment_completion_type {
    private $names, $timestamps;
    public function get_id() {
        return COMPLETION_EVENT_PROGRAM_COMPLETION;
    }
    public function get_name() {
        return get_string('programcompletion', 'totara_program');
    }
    public function get_script() {
        global $CFG;

        return "
            totaraDialogs['completionevent'].default_url = '$CFG->wwwroot/totara/program/assignment/completion/find_program.php?';
            totaraDialogs['completionevent'].open();

            $('#instancetitle').unbind('click').click(function() {
                handle_completion_selection();
                return false;
            });

            $('.folder').removeClass('clickable').addClass('unclickable');
        ";
    }
    private function load_data() {
        global $DB;
        $this->names = $DB->get_records_select('prog', '', null, '', 'id, fullname');
        // Prog_completion records where coursesetid = 0 are the master record for the program as a whole.
        $this->timestamps = $DB->get_records_select('prog_completion', 'coursesetid = 0', null, '',
            $DB->sql_concat('userid', "'-'", 'programid') . ' as hash, timecompleted');
    }
    public function get_item_name($instanceid) {
        // Lazy load data when required.
        if (!isset($this->names)) {
            $this->load_data();
        }
        return $this->names[$instanceid]->fullname;
    }
    public function get_completion_string() {
        return get_string('completionofprogram', 'totara_program');
    }
    public function get_timestamp($userid, $assignobject) {
        // Lazy load data when required.
        if (!isset($this->timestamps)) {
            $this->load_data();
        }
        if (isset($this->timestamps[$userid . '-' . $assignobject->completioninstance])) {
            return $this->timestamps[$userid . '-' . $assignobject->completioninstance]->timecompleted;
        }
        return false;
    }
}

class prog_assigment_completion_course_completion extends prog_assignment_completion_type {
    private $names, $timestamps;
    public function get_id() {
        return COMPLETION_EVENT_COURSE_COMPLETION;
    }
    public function get_name() {
        return get_string('coursecompletion', 'totara_program');
    }
    public function get_script() {
        global $CFG;

        return "
            totaraDialogs['completionevent'].default_url = '$CFG->wwwroot/totara/program/assignment/completion/find_course.php?';
            totaraDialogs['completionevent'].open();

            $('#instancetitle').unbind('click').click(function() {
                handle_completion_selection();
                return false;
            });

            $('.folder').removeClass('clickable').addClass('unclickable');
        ";
    }
    private function load_data() {
        global $DB;
        $this->names = $DB->get_records_select('course', '', null, '', 'id, fullname');
        $this->timestamps = $DB->get_records_select('course_completions', '', null, '',
            $DB->sql_concat('userid', "'-'", 'course') . ' as hash, timecompleted');
    }
    public function get_item_name($instanceid) {
        // Lazy load data when required.
        if (!isset($this->names)) {
            $this->load_data();
        }
        return $this->names[$instanceid]->fullname;
    }
    public function get_completion_string() {
        return get_string('completionofcourse', 'totara_program');
    }
    public function get_timestamp($userid, $assignobject) {
        // Lazy load data when required.
        if (!isset($this->timestamps)) {
            $this->load_data();
        }
        if (isset($this->timestamps[$userid . '-' . $assignobject->completioninstance])) {
            return $this->timestamps[$userid . '-' . $assignobject->completioninstance]->timecompleted;
        }
        return false;
    }
}

class prog_assigment_completion_profile_field_date extends prog_assignment_completion_type {
    private $names, $timestamps;
    public function get_id() {
        return COMPLETION_EVENT_PROFILE_FIELD_DATE;
    }
    public function get_name() {
        return get_string('profilefielddate', 'totara_program');
    }
    public function get_script() {
        global $CFG;

        return "
            totaraDialogs['completionevent'].default_url = '$CFG->wwwroot/totara/program/assignment/completion/find_profile_field.php?';
            totaraDialogs['completionevent'].open();

            $('#instancetitle').unbind('click').click(function() {
                handle_completion_selection();
                return false;
            });
        ";
    }
    private function load_data() {
        global $DB;
        $this->names = $DB->get_records_select('user_info_field', '', null, '', 'id, name');
        $this->timestamps = $DB->get_records_select('user_info_data', '', null, '',
            $DB->sql_concat('userid', "'-'", 'fieldid') . ' as hash, data');
    }
    public function get_item_name($instanceid) {
        // Lazy load data when required.
        if (!isset($this->names)) {
            $this->load_data();
        }
        return $this->names[$instanceid]->name;
    }
    public function get_completion_string() {
        return get_string('dateinprofilefield', 'totara_program');
    }
    public function get_timestamp($userid, $assignobject) {
        // Lazy load data when required.
        if (!isset($this->timestamps)) {
            $this->load_data();
        }
        if (!isset($this->timestamps[$userid . '-' . $assignobject->completioninstance])) {
            return false;
        }

        $date = $this->timestamps[$userid . '-' . $assignobject->completioninstance]->data;
        $date = trim($date);

        if (empty($date)) {
            return false;
        }

        // Check if the profile field contains a date in UNIX timestamp form..
        $timestamppattern = '/^[0-9]+$/';
        if (preg_match($timestamppattern, $date, $matches) > 0) {
            return $date;
        }

        // Check if the profile field contains a date in the lanconfig form...
        $datepattern = get_string('datepickerlongyearregexphp', 'totara_core');
        if (preg_match($datepattern, $date, $matches) > 0) {
            list($day, $month, $year) = explode('/', $date);
            $date = $month.'/'.$day.'/'.$year;
            return strtotime($date);
        }

        // Last ditch attempt, try using strtotime to convert the string into a timestamp..
        $result = strtotime($date);
        if ($result != false) {
            return $result;
        }

        // Else we couldn't match a date, so return false.
        return false;
    }
}

class prog_assigment_completion_enrollment_date extends prog_assignment_completion_type {

    public function get_id() {
        return COMPLETION_EVENT_ENROLLMENT_DATE;
    }
    public function get_name() {
        return get_string('programenrollmentdate', 'totara_program');
    }
    public function get_script() {
        return "
            totaraDialogs['completionevent'].clear();
        ";
    }
    public function get_item_name($instanceid) {
        return '';
    }
    public function get_completion_string() {
        return get_string('programenrollmentdate', 'totara_program');
    }
    public function get_timestamp($userid, $assignobject) {
        global $DB;

        $date = time();
        $params = array('userid' => $userid, 'assignmentid' => $assignobject->id);
        if ($user = $DB->get_record('prog_user_assignment', $params, 'id, timeassigned')) {
            $date = $user->timeassigned;
        }

        return $date;
    }
}
