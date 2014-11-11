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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */

/**
 * goal/lib.php
 *
 * Library to construct goal hierarchies
 */
require_once("{$CFG->dirroot}/totara/hierarchy/lib.php");
require_once("{$CFG->dirroot}/totara/core/utils.php");
require_once("{$CFG->dirroot}/totara/core/js/lib/setup.php");

define('GOAL_ASSIGNMENT_INDIVIDUAL', 1);
define('GOAL_ASSIGNMENT_AUDIENCE', 2);
define('GOAL_ASSIGNMENT_POSITION', 3);
define('GOAL_ASSIGNMENT_ORGANISATION', 4);
define('GOAL_ASSIGNMENT_SELF', 5);
define('GOAL_ASSIGNMENT_MANAGER', 6);
define('GOAL_ASSIGNMENT_ADMIN', 7);

/**
 * Object that holds methods and attributes for goal operations.
 * @abstract
 */
class goal extends hierarchy {

    const SCOPE_COMPANY = 2;
    const SCOPE_PERSONAL = 1;

    /**
     * The base table prefix for the class.
     */
    const PREFIX = 'goal';
    const SHORT_PREFIX = 'goal';
    public $prefix = self::PREFIX;
    public $shortprefix = self::SHORT_PREFIX;
    protected $extrafields = array();

    /**
     * Delete goal framework and updated associated scales.
     *
     * @access public
     * @param boolean $triggerevent Whether the delete item event should be triggered or not
     * @return true
     */
    public function delete_framework($triggerevent = true) {
        global $DB;

        // Start transaction.
        $transaction = $DB->start_delegated_transaction();

        // Run parent method.
        parent::delete_framework();
        // Delete references to scales.
        if ($DB->count_records($this->shortprefix.'_scale_assignments', array('frameworkid' => $this->frameworkid))) {
            $DB->delete_records($this->shortprefix.'_scale_assignments', array('frameworkid' => $this->frameworkid));
        }
        // End transaction.
        $transaction->allow_commit();
        return true;
    }

    /**
     * Delete all data associated with the goals.
     *
     * This method is protected because it deletes the goals, but doesn't use
     * transactions.
     * Use {@link hierarchy::delete_hierarchy_item()} to recursively delete an item and
     * all its children
     *
     * @param array $items Array of IDs to be deleted
     *
     * @return boolean True if items and associated data were successfully deleted
     */
    protected function _delete_hierarchy_items($items) {
        global $DB;

        // First call the deleter for the parent class.
        if (!parent::_delete_hierarchy_items($items)) {
            return false;
        }

        list($items_sql, $items_params) = $DB->get_in_or_equal($items);

        // Delete rows from item history table (need to link goal_record to history and specify scope = company).
        $sql = "DELETE FROM {goal_item_history}
                 WHERE itemid IN (SELECT id FROM {goal_record} WHERE goalid " . $items_sql . ")
                   AND scope = ?";
        $DB->execute($sql, array_merge($items_params, array(self::SCOPE_COMPANY)));

        // Delete rows from all these other tables.
        $db_data = array(
            $this->shortprefix.'_grp_pos' => 'goalid',
            $this->shortprefix.'_grp_org' => 'goalid',
            $this->shortprefix.'_grp_cohort' => 'goalid',
            $this->shortprefix.'_user_assignment' => 'goalid',
            $this->shortprefix.'_record' => 'goalid'
        );

        foreach ($db_data as $table => $field) {
            $select = "$field {$items_sql}";
            $DB->delete_records_select($table, $select, $items_params);
        }

        return true;
    }

    /**
     * Run any code before printing header
     * @param $page string Unique identifier for page
     * @return void
     */
    public function hierarchy_page_setup($page = '', $item = null) {
        global $CFG, $PAGE;

        if (!in_array($page, array('template/view', 'item/view', 'item/add'))) {
            return;
        }

        // Setup custom javascript.
        require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

        // Setup lightbox.
        local_js(array(
            TOTARA_JS_DIALOG,
            TOTARA_JS_TREEVIEW
        ));

        $PAGE->requires->strings_for_js(array('chooseposition', 'choosemanager', 'chooseorganisation', 'assigngroup'),
                'totara_hierarchy');

        // Selector setup.
        $jsmodule = array(
            'name' => 'totara_assigngroups',
            'fullpath' => '/totara/hierarchy/prefix/goal/assign/assigngroup_dialog.js',
            'requires' => array('json'));
        $args = array('args' => "{\"module\":\"goal\"}");
        $PAGE->requires->js_init_call('M.totara_assigngroupdialog.init', $args, false, $jsmodule);
    }


    /**
     * Print any extra markup to display on the hierarchy view item page
     *
     * @param object $item Goal being viewed
     *
     * @return void
     */
    public function display_extra_view_info($item) {
        global $PAGE, $CFG;

        require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/assign/lib.php');

        // Set up some variables.
        $renderer = $PAGE->get_renderer('totara_hierarchy');
        $can_assign = has_capability('totara/hierarchy:managegoalassignments', context_system::instance());
        $assignclass = new totara_assign_goal('goal', $item);
        $options = array(
            "" => get_string('assigngroup', 'totara_hierarchy'),
            "org" => get_string('addorganisations', 'totara_hierarchy'),
            'pos' => get_string('addpositions', 'totara_hierarchy'),
            'cohort' => get_string('addcohorts', 'totara_hierarchy'),
        );

        // Get all assignments for $item.
        $assignments = $assignclass->get_current_assigned_groups();

        // Put an empty line between the goal table and assignments table.
        echo html_writer::empty_tag('br');

        // Display the assignments table.
        echo html_writer::start_tag('div', array('class' => 'list-assigned'));
        echo $renderer->print_goal_view_assignments($item, $can_assign, $assignments);
        echo html_writer::end_tag('div');

        // Display the assignments selector.
        if ($can_assign) {
            echo html_writer::select($options, 'groupselector', null, null,
                    array('class' => 'group_selector', 'itemid' => $item->id));
        }
    }


    /**
     * Return hierarchy prefix specific data about an item.
     *
     * The returned array should have the structure:
     * array(
     *  0 => array('title' => $title, 'value' => $value),
     *  1 => ...
     * )
     *
     * @param $item object Item being viewed
     * @param $cols array optional Array of columns and their raw data to be returned
     * @return array
     */
    public function get_item_data($item, $cols = null) {
        global $DB, $CFG;

        // Set up the default items.
        $data = parent::get_item_data($item, $cols);

        // Display the target date if appropriate.
        if (!empty($item->targetdate)) {
            $format_date = userdate($item->targetdate, get_string('datepickerlongyearphpuserdate', 'totara_core'),
                    $CFG->timezone, false);
            $data[] = array(
                'title' => get_string('goaltargetdate', 'totara_hierarchy'),
                'value' => $format_date,
            );
        }

        // Display the scale if appropriate.
        $scaleid = $DB->get_field('goal_scale_assignments', 'scaleid', array('frameworkid' => $item->frameworkid));

        if (!empty($scaleid)) {
            $data[] = array(
                'title' => get_string('goalscale', 'totara_hierarchy'),
                'value' => $DB->get_field('goal_scale', 'name', array('id' => $scaleid)),
            );
        }

        return $data;
    }


    /**
     * Get the goal scale for this goal (including all the scale's
     * values in an attribute called valuelist).
     *
     * @return object
     */
    public function get_goal_scale() {
        global $DB;
        $sql = "
            SELECT scale.*
            FROM
                {{$this->shortprefix}_scale_assignments} sa,
                {{$this->shortprefix}_scale} scale
            WHERE
                sa.scaleid = scale.id
                AND sa.frameworkid = ?
        ";
        $scale = $DB->get_record_sql($sql, array($this->frameworkid));

        $valuelist = $DB->get_records($this->shortprefix.'_scale_values', array('scaleid' => $scale->id), 'sortorder');
        if ($valuelist) {
            $scale->valuelist = $valuelist;
        }
        return $scale;
    }

    /**
     * Get scales for a goal
     *
     * @return array
     */
    public function get_scales() {
        global $DB;
        return $DB->get_records($this->shortprefix.'_scale', null, 'name');
    }

    /**
     * Extra form elements to include in the add/edit form for items of this prefix
     *
     * @param object &$mform Moodle form object (passed by reference)
     */
    public function add_additional_item_form_fields(&$mform) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

        // Javascript include.
        local_js(array(
            TOTARA_JS_DIALOG,
            TOTARA_JS_UI,
            TOTARA_JS_ICON_PREVIEW
        ));

        $frameworkid = $this->frameworkid;

        // Get the name of the framework's scale. (Note this code expects there.
        // To be only one scale per framework, even though the DB structure.
        // Allows there to be multiple since we're using a go-between table).
        $scaledesc = $DB->get_field_sql("
                SELECT s.name
                FROM
                {{$this->shortprefix}_scale} s,
                {{$this->shortprefix}_scale_assignments} a
                WHERE
                a.frameworkid = ?
                AND a.scaleid = s.id
        ", array($frameworkid));

        $mform->addElement('static', 'scalename', get_string('scale'), ($scaledesc) ? format_string($scaledesc) : get_string('none'));
        $mform->addHelpButton('scalename', 'goalscale', 'totara_hierarchy');

        $mform->addElement('date_selector', 'targetdate', get_string('goaltargetdate', 'totara_hierarchy'), array('optional' => true));
        $mform->addHelpButton('targetdate', 'goaltargetdate', 'totara_hierarchy');
        $mform->setType('targetdate', PARAM_INT);

        $mform->addElement('hidden', 'proficiencyexpected', 1);
        $mform->setType('proficiencyexpected', PARAM_INT);
        $mform->addElement('hidden', 'evidencecount', 0);
        $mform->setType('evidencecount', PARAM_INT);
    }

    /**
     * Display addition fields in the goals description, namely the target date.
     *
     * @param $item object          The database record of a goal to display
     * @param $cssclass string      Any extra css to apply to the extra fields
     * @return string               The html output for the extra fields
     */
    public function display_additional_item_form_fields($item, $cssclass) {
        global $CFG;

        $out = '';

        // Display the goal's target date.
        if (!empty($item->targetdate)) {
            $targetdate = userdate($item->targetdate, get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false);
            $out .= html_writer::tag('div', html_writer::tag('strong', get_string('goaltargetdate', 'totara_hierarchy') . ': ') . $targetdate,
                array('class' => 'itemtargetdate ' . $cssclass));
        }

        return $out;
    }

    /**
     * Returns various stats about an item, used for listed what will be deleted
     *
     * @param integer $id ID of the item to get stats for
     * @return array Associative array containing stats
     */
    public function get_item_stats($id) {
        global $DB;

        if (!$data = parent::get_item_stats($id)) {
            return false;
        }

        // Should always include at least one item (itself).
        if (!$children = $this->get_item_descendants($id)) {
            return false;
        }

        $ids = array_keys($children);

        list($idssql, $idsparams) = sql_sequence('goalid', $ids);
        // Number of user assignments for the goal.
        $data['user_assignments'] = $DB->count_records_select('goal_user_assignment', $idssql, $idsparams);

        return $data;
    }

    /**
     * Given some stats about an item, return a formatted delete message
     *
     * @param array $stats Associative array of item stats
     * @return string Formatted delete message
     */
    public function output_delete_message($stats) {
        $message = parent::output_delete_message($stats);

        if ($stats['user_assignments'] > 0) {
            $message .= get_string('deleteincludexuserassignments', 'totara_hierarchy',
                $stats['user_assignments']) . html_writer::empty_tag('br');
        }

        return $message;
    }

    /**
     * Updates all the assignments for a goal using update_user_assignments().
     *
     * @param int $goalid   The id of the goal to update
     */
    public function update_goal_user_assignments($goalid) {
        global $DB;

        // Personal and Individual assignment's don't need to be updated here, that is all action based.

        // Get all position assignments for the goal, and update them.
        $pos_assignments = $DB->get_records('goal_grp_pos', array('goalid' => $goalid));
        foreach ($pos_assignments as $assignment) {
            $this->update_user_assignments($goalid, GOAL_ASSIGNMENT_POSITION, $assignment);
        }

        // Get all organisation assignments for the goal, and update them.
        $org_assignments = $DB->get_records('goal_grp_org', array('goalid' => $goalid));
        foreach ($org_assignments as $assignment) {
            $this->update_user_assignments($goalid, GOAL_ASSIGNMENT_ORGANISATION, $assignment);
        }

        // Get all cohort assignments for the goal, and update them.
        $cohort_assignments = $DB->get_records('goal_grp_cohort', array('goalid' => $goalid));
        foreach ($cohort_assignments as $assignment) {
            $this->update_user_assignments($goalid, GOAL_ASSIGNMENT_AUDIENCE, $assignment);
        }
    }

    /**
     * Update the users assigned to a goal.
     *
     * @param int       $goalid         The id of the goal to update.
     * @param int       $type           The GOAL_ASSIGNMENT_TYPE we are updating
     * @param stdClass  $assignment     A record from goal_grp_type
     */
    public function update_user_assignments($goalid, $type, $assignment) {
        global $DB, $USER;

        // Set up some variables that we are going to need.
        switch ($type) {
            case GOAL_ASSIGNMENT_AUDIENCE:
                $item_table = 'cohort_members';
                $item_field = 'cohortid';
                $assign_field = 'cohortid';
                $include_children = false;
                break;
            case GOAL_ASSIGNMENT_POSITION:
                $item_table = 'pos_assignment';
                $item_field = 'positionid';
                $assign_field = 'posid';
                $include_children = $assignment->includechildren;
                break;
            case GOAL_ASSIGNMENT_ORGANISATION:
                $item_table = 'pos_assignment';
                $item_field = 'organisationid';
                $assign_field = 'orgid';
                $include_children = $assignment->includechildren;
                break;
        }

        // Set up the default scale value.
        $sql = "SELECT s.defaultid
                FROM {goal} g
                JOIN {goal_scale_assignments} sa
                    ON g.frameworkid = sa.frameworkid
                JOIN {goal_scale} s
                    ON sa.scaleid = s.id
                WHERE g.id = ?";
        $scale = $DB->get_record_sql($sql, array($goalid));
        $defaultscalevalueid = $scale->defaultid;

        // Get new assignments as array($userid).
        $new = $DB->get_fieldset_select($item_table, 'userid', "{$item_field} = ?", array($assignment->$assign_field));

        // Get current assignments as array($userid).
        $params = array('assigntype' => $type, 'assignmentid' => $assignment->id);
        $current = $DB->get_records('goal_user_assignment', $params);

        $current_index = array();
        foreach ($current as $id => $item) {
            $current_index[$id] = $item->userid;
        }

        $user_assignment = new stdClass();
        $user_assignment->assigntype = $type;
        $user_assignment->assignmentid = $assignment->id;
        $user_assignment->goalid = $goalid;
        $user_assignment->timemodified = time();
        $user_assignment->usermodified = $USER->id;

        $default_scale = new stdClass();
        $default_scale->scalevalueid = $defaultscalevalueid;
        $default_scale->goalid = $goalid;

        foreach ($new as $user) {
            if ($index = array_search($user, $current_index, true)) {
                // Already exists, we're cool. Pop off the current array.
                unset($current[$index]);
            } else {
                $user_assignment->userid = $user;
                $default_scale->userid = $user;

                $DB->insert_record('goal_user_assignment', $user_assignment);
                $goalrecords = self::get_goal_items(array('goalid' => $goalid, 'userid' => $user), self::SCOPE_COMPANY);
                if (empty($goalrecords)) {
                    self::insert_goal_item($default_scale, self::SCOPE_COMPANY);
                }
            }
        }

        // Anything left on current will be a removed assignment.
        foreach ($current as $deleted) {
            $deleted->timemodified = time();
            $deleted->extrainfo = "OLD:{$type},{$assignment->$assign_field}";
            $deleted->assigntype = GOAL_ASSIGNMENT_INDIVIDUAL;
            $deleted->assignmentid = 0;
            $DB->update_record('goal_user_assignment', $deleted);
        }
    }

    /**
     * Deletes or transfers all user assignments for a given group assignment.
     *
     * @param int $assigntype   The GOAL_ASSIGNMENT_TYPE
     * @param int $assignmentid The id of the assignment record in goal_grp_type
     * @param int $type         goal_assignment_type_info
     * @param array $delete_params
     */
    public static function delete_group_assignment($assigntype, $assignmentid, $type, $delete_params) {
        global $DB;

        if ($assigntype == GOAL_ASSIGNMENT_INDIVIDUAL) {
            // This should not be called on individual assignments.
            print_error('error:deletingindiviualassignments', 'totara_hierarchy');
        }

        // Delete all records of the specified assigntype and assignmentid.
        self::delete_user_assignments(array('assigntype' => $assigntype, 'assignmentid' => $assignmentid));

        // Then delete the group assignment record.
        $DB->delete_records($type->table, $delete_params);
    }

    /**
     * Delete one or more goal_user_assignment records.
     * Checks to see if the last user assignment record has been deleted, then removes the goal_record record.
     *
     * @param array $conditions Specific conditions to match on when deleting.
     */
    public static function delete_user_assignments($conditions) {
        global $DB;

        // Remember which records we are about to delete.
        $userassignments = $DB->get_records('goal_user_assignment', $conditions);

        // Delete the records.
        $DB->delete_records('goal_user_assignment', $conditions);

        // For each goal/user user_assginment deleted, see if there are any other user_assignments with matching goalid/userid.
        // If not found then the last user_assignment was just deleted, so delete the matching goal_record.
        foreach ($userassignments as $userassignment) {
            $recordparams = array('goalid' => $userassignment->goalid, 'userid' => $userassignment->userid);
            if (!$DB->record_exists('goal_user_assignment', $recordparams)) {
                self::delete_goal_item($recordparams, self::SCOPE_COMPANY);
            }
        }
    }

    /**
     * Create the user assignments for a group assignment.
     *
     * @param int       $type               The GOAL_ASSIGNMENT_TYPE
     * @param stdclass  $assignment         A record from the goal_grp_type table
     * @param string    $includechildren    Whether or not to include children,
     *                  And whether it is the goal or assignment items children.
     *                  'GOAL/POS/ORG' indicates which children to add.
     */
    public function create_user_assignments($type, $assignment, $includechildren = null) {
        global $DB, $USER, $CFG;

        require_once($CFG->dirroot . '/totara/hierarchy/prefix/position/lib.php');
        require_once($CFG->dirroot . '/totara/hierarchy/prefix/organisation/lib.php');

        $typeinfo = self::goal_assignment_type_info($type);
        $field = $typeinfo->field;

        // Get all the users assigned to $item.
        $users = $DB->get_fieldset_select($typeinfo->members_table, 'userid',
                "{$typeinfo->members_field} = ?", array($assignment->$field));

        $childusers = array();
        if (!empty($includechildren)) {
            if ($type == GOAL_ASSIGNMENT_AUDIENCE) {
                // Cohort's do not have children, this should never happen.
                print_error('error:includechildrencohort', 'totara_hierarchy');
            }

            $item = new $typeinfo->fullname();
            $itemfield = $assignment->$field;

            $children = $item->get_item_descendants($itemfield);

            foreach ($children as $child) {
                $assigned = $DB->get_fieldset_select($typeinfo->members_table, 'userid',
                        "{$typeinfo->members_field} = ?", array($child->id));
                $childusers = array_merge($childusers, $assigned);
            }
        }

        // Set up the user assignment data.
        $user_assignment = new stdClass();
        $user_assignment->assigntype = $type;
        $user_assignment->assignmentid = $assignment->id;
        $user_assignment->goalid = $assignment->goalid; // This is here to order by later.
        $user_assignment->timemodified = time();
        $user_assignment->usermodified = $USER->id;

        // Set up the default scale value.
        $sql = "SELECT s.defaultid
                FROM {goal} g
                JOIN {goal_scale_assignments} sa
                    ON g.frameworkid = sa.frameworkid
                JOIN {goal_scale} s
                    ON sa.scaleid = s.id
                WHERE g.id = ?";
        $scale = $DB->get_record_sql($sql, array($assignment->goalid));
        $default_scale = new stdClass();
        $default_scale->goalid = $assignment->goalid;
        $default_scale->scalevalueid = $scale->defaultid;

        // Create assignments for all the users.
        foreach ($users as $user) {
            $user_assignment->userid = $user;
            $default_scale->userid = $user;

            $DB->insert_record('goal_user_assignment', $user_assignment);
            $goalrecords = self::get_goal_items(array('goalid' => $assignment->goalid, 'userid' => $user),
                    self::SCOPE_COMPANY);
            if (empty($goalrecords)) {
                self::insert_goal_item($default_scale, self::SCOPE_COMPANY);
            }
        }

        foreach ($childusers as $childuser) {
            $user_assignment->userid = $childuser;
            $default_scale->userid = $childuser;

            // This means it was an assignment created by a parent (position etc).
            $user_assignment->extrainfo = "PAR:{$type},{$assignment->id}";

            $DB->insert_record('goal_user_assignment', $user_assignment);
            $goalrecords = self::get_goal_items(array('goalid' => $assignment->goalid, 'userid' => $childuser),
                    self::SCOPE_COMPANY);
            if (empty($goalrecords)) {
                self::insert_goal_item($default_scale, self::SCOPE_COMPANY);
            }
        }
    }

    /**
     * Get all the company level goals that a user is assigned to.
     *
     * @param   int     $userid     The id of the user to get the records of
     * @param   array   $canedit    An array holding all of the editing permissions
     * @param   boolean $display    Whether or not to join on the custom field data
     * @return  array(object)
     */
    public static function get_user_assignments($userid, $canedit = null, $display = false) {
        global $CFG, $DB, $OUTPUT;

        $sql = "SELECT gua.*
            FROM {goal_user_assignment} gua
            JOIN {goal} g ON (g.id = gua.goalid AND g.visible = 1)
            WHERE userid = :userid
            ORDER BY goalid, assigntype";
        $assignments = $DB->get_records_sql($sql, array('userid' => $userid));

        $assignment_info = array();
        foreach ($assignments as $assignment) {
            // Retrieve the users scale value for the assigned goal.
            $goalrecord = self::get_goal_item(array('userid' => $assignment->userid, 'goalid' => $assignment->goalid),
                    self::SCOPE_COMPANY, MUST_EXIST);
            $scalevalueid = $goalrecord->scalevalueid;

            if ($assignment->assigntype == GOAL_ASSIGNMENT_INDIVIDUAL && $canedit) {
                // Set up the edit and delete icons.
                $del_params = array('goalid' => $assignment->goalid, 'assigntype' => $assignment->assigntype,
                    'modid' => $assignment->userid);
                $del_url = new moodle_url('/totara/hierarchy/prefix/goal/assign/remove.php', $del_params);
                $del_str = get_string('delete');
                $del_button = ' ' . $OUTPUT->action_icon($del_url, new pix_icon('t/delete', $del_str));
            } else {
                $del_button = '';
            }

            $goalid = $assignment->goalid; // Saves space.
            if (!empty($assignment_info[$goalid])) {
                // If the item already exists just add a new assignment type in the same table cell.
                $assignment_info[$goalid]->via .= html_writer::empty_tag('br') . html_writer::empty_tag('hr') .
                    self::get_assignment_string(self::SCOPE_COMPANY, $assignment) . $del_button;
            } else {
                // Create a new object for this role.
                $assignment_info[$goalid] = new stdClass();

                // Just get the goal out of the database.
                if ($display) {
                    // Join in the custom field data required by hierarchy_display_item().
                    $custom_fields = $DB->get_records('goal_type_info_field');
                    $select = "SELECT g.*";
                    $from   = " FROM {goal} g";
                    foreach ($custom_fields as $custom_field) {
                        // Add one join per custom field.
                        $fieldid = $custom_field->id;
                        $select .= ", cf_{$fieldid}.id AS cf_{$fieldid}_itemid, cf_{$fieldid}.data AS cf_{$fieldid}";
                        $from .= " LEFT JOIN {goal_type_info_data} cf_{$fieldid}
                                ON g.id = cf_{$fieldid}.goalid AND cf_{$fieldid}.fieldid = {$fieldid}";
                    }
                    $where = " WHERE g.id = :goalid";
                    $goal = $DB->get_record_sql($select.$from.$where, array('goalid' => $goalid));
                    $assignment_info[$goalid]->goal = $goal;
                } else {
                    $goal = $DB->get_record('goal', array('id' => $goalid));
                }

                if (has_capability('totara/hierarchy:viewgoal', context_system::instance())) {
                    // Put a link in so they can actually see what the goal is.
                    $goal_params = array('id' => $goalid, 'prefix' => 'goal');
                    $goal_url = new moodle_url('/totara/hierarchy/item/view.php', $goal_params);
                    $assignment_info[$goalid]->goalname = html_writer::link($goal_url, format_string($goal->fullname));
                } else {
                    // Just the name.
                    $assignment_info[$goalid]->goalname = format_string($goal->fullname);
                }

                // Fill in the appropriate data.
                $assignment_info[$goalid]->assignmentid = $assignment->id;
                $assignment_info[$goalid]->targetdate = $goal->targetdate;
                $assignment_info[$goalid]->scalevalueid = $scalevalueid;
                $assignment_info[$goalid]->via = self::get_assignment_string(self::SCOPE_COMPANY, $assignment) . $del_button;
            }
        }

        return $assignment_info;
    }

    /**
     * Get a goal_record or goal_personal record that match the conditions.
     * If you get goal_record record then you need to retrieve the goal record to get name, description etc.
     *
     * @param array $conditions
     * @param int $scope
     * @param int $strictness
     * @param bool $includedeleted
     * @return object
     */
    public static function get_goal_item($conditions, $scope, $strictness = IGNORE_MISSING, $includedeleted = false) {
        global $DB;

        if (!$includedeleted) {
            $conditions['deleted'] = 0;
        }

        if ($scope == self::SCOPE_COMPANY) {
            return $DB->get_record('goal_record', $conditions, '*', $strictness);
        } else if ($scope == self::SCOPE_PERSONAL) {
            return $DB->get_record('goal_personal', $conditions, '*', $strictness);
        }
    }

    /**
     * Get an array of all goal_record or goal_personal records that match the conditions.
     * If you get goal_record records then you need to retrieve the "goal" records to get name, description etc.
     *
     * @param array $conditions
     * @param int $scope
     * @param bool $includedeleted
     * @return array of objects
     */
    public static function get_goal_items($conditions, $scope, $includedeleted = false) {
        global $DB;

        if (!$includedeleted) {
            $conditions['deleted'] = 0;
        }

        if ($scope == self::SCOPE_COMPANY) {
            return $DB->get_records('goal_record', $conditions);
        } else if ($scope == self::SCOPE_PERSONAL) {
            return $DB->get_records('goal_personal', $conditions);
        }
    }

    /**
     * Update an existing goal_record or goal_personal record.
     * Records history if scalevalueid has changed (safe to call multiple times without changes).
     * Cannot update a deleted goal item - you must undelete it manually (with $DB->set_field) first.
     *
     * @param object $todb
     * @param int $scope
     * @return bool
     */
    public static function update_goal_item($todb, $scope) {
        global $DB;

        // Get the existing record so that we can compare scalevalueid and to confirm that we have exactly one record to update.
        $existingitem = self::get_goal_item(array('id' => $todb->id, 'deleted' => 0), $scope, MUST_EXIST);

        // Update the record.
        if ($scope == self::SCOPE_COMPANY) {
            $result = $DB->update_record('goal_record', $todb);
        } else if ($scope == self::SCOPE_PERSONAL) {
            $result = $DB->update_record('goal_personal', $todb);
        }

        // Only record history if the update was successful and the scalevalue was changed.
        if ($result && isset($todb->scalevalueid) && $existingitem->scalevalueid != $todb->scalevalueid) {
            self::insert_goal_item_history($todb, $scope);
        }

        return $result;
    }

    /**
     * Insert a goal_record or goal_personal record.
     * Records history for the new scalevalueid.
     *
     * @param object $todb requires SCOPE_PERSONAL: userid; SCOPE_COMPANY: userid, goalid
     * @param int $scope
     * @return int id of the new goal item (goalrecordid or goalpersonalid)
     */
    public static function insert_goal_item($todb, $scope) {
        global $DB;

        if ($scope == self::SCOPE_COMPANY) {
            // Check if there is a deleted goal_record to replace.
            $params = array('userid' => $todb->userid, 'goalid' => $todb->goalid);
            $existingrecord = self::get_goal_item($params, self::SCOPE_COMPANY, IGNORE_MISSING, true);

            // We always want to specify a scalevalueid when adding, even if we are actually undeleting.
            if (!isset($todb->scalevalueid)) {
                $todb->scalevalueid = 0;
            }

            if (!empty($existingrecord)) {
                // Undelete the existing record.
                $DB->set_field('goal_record', 'deleted', 0, $params);

                // Get the id of the existing record so we can do an update instead of an insert.
                $todb->id = $existingrecord->id;

                // Update the item (will add history).
                self::update_goal_item($todb, self::SCOPE_COMPANY);
            } else {
                // Insert the new goal_record.
                $todb->id = $DB->insert_record('goal_record', $todb);

                // Add history.
                self::insert_goal_item_history($todb, self::SCOPE_COMPANY);
            }
        } else {
            // Insert the new goal_personal (don't check for deleted record - users can only create new personal goals).
            $todb->id = $DB->insert_record('goal_personal', $todb);

            // Add history.
            self::insert_goal_item_history($todb, self::SCOPE_PERSONAL);
        }

        return $todb->id;
    }

    /**
     * Mark a goal_record or goal_personal record as being deleted.
     *
     * @param array $conditions
     * @param int $scope
     * @return bool
     */
    public static function delete_goal_item($conditions, $scope) {
        global $DB;

        // Set the deleted field to 1/true.
        if ($scope == self::SCOPE_COMPANY) {
            return $DB->set_field('goal_record', 'deleted', 1, $conditions);
        } else if ($scope == self::SCOPE_PERSONAL) {
            return $DB->set_field('goal_personal', 'deleted', 1, $conditions);
        }
    }

    /**
     * Insert a goal_item_history record.
     * Uses the values from a $todb record to construct the history record.
     * This is only used to store changes to scalevalueid.
     * This will create multiple records if the scalevalueid has not changed, so check before calling it!
     *
     * @param object $todb
     * @param int $scope
     * @return bool true if successful
     */
    private static function insert_goal_item_history($todb, $scope) {
        global $DB, $USER;

        $history = new stdClass();
        $history->scope = $scope;
        $history->itemid = $todb->id;
        $history->scalevalueid = $todb->scalevalueid;
        $history->timemodified = time();
        $history->usermodified = $USER->id;

        return $DB->insert_record('goal_item_history', $history);
    }

    public static function get_status_string() {
        return 'status';
    }

    /**
     * This function takes the type of goal, the assignment type and returns
     * a string formatted for the my goals page
     *
     * @param int $scope
     * @param object $assignment   The database record of a goal assignment
     * @return string              Formatted for the my goals table
     */
    public static function get_assignment_string($scope, $assignment) {
        global $DB;

        if ($scope == self::SCOPE_PERSONAL) {
            // Handle personal goals.
            switch ($assignment->assigntype) {
                case GOAL_ASSIGNMENT_SELF:
                    // Self: unassign?
                    $assignment_string = get_string('goalassignmentself', 'totara_hierarchy');
                    break;
                case GOAL_ASSIGNMENT_MANAGER:
                    // Mananger: {$managername}.
                    $manager = fullname($DB->get_record('user', array('id' => $assignment->usercreated)));
                    $assignment_string = get_string('goalassignmentmanager', 'totara_hierarchy', $manager);
                    break;
                case GOAL_ASSIGNMENT_ADMIN:
                    // Admin: {$adminname}.
                    $admin = fullname($DB->get_record('user', array('id' => $assignment->usercreated)));
                    $assignment_string = get_string('goalassignmentadmin', 'totara_hierarchy', $admin);
                    break;
                default:
                    // Unrecognised assignment type.
                    print_error('invalidassigntype', 'totara_hierarchy', 'personal');
            }
        } else if ($scope == self::SCOPE_COMPANY) {
            // Handle company goals.
            switch ($assignment->assigntype) {
                case GOAL_ASSIGNMENT_INDIVIDUAL:
                    // Individual: {$indiname}.

                    if (!empty($assignment->extrainfo) && ($extrainfo = str_replace('OLD:', '', $assignment->extrainfo))) {
                        // This is an old assignment that has been transfered.
                        $args = explode(',', $extrainfo);
                        $type = self::goal_assignment_type_info($args[0], $assignment->goalid, $args[1]);
                        $replace = new stdClass();
                        $replace->type = get_string('assign' . $type->fullname, 'totara_hierarchy');
                        $replace->name = format_string($type->modname);
                        $assignment_string = get_string('oldgoalassignment', 'totara_hierarchy', $replace);
                    } else {
                        // This is your average individual assignment.
                        $user = $DB->get_record('user', array('id' => $assignment->usermodified));
                        $name = fullname($user);
                        $assignment_string = get_string('goalassignmentindividual', 'totara_hierarchy', $name);
                    }

                    break;
                case GOAL_ASSIGNMENT_AUDIENCE:
                    // Audience: {$audiencename}.
                    $sql = " SELECT c.name
                             FROM {cohort} c
                             JOIN {goal_grp_cohort} g
                             ON g.cohortid = c.id
                             WHERE g.id = ?";
                    $audience = $DB->get_record_sql($sql, array($assignment->assignmentid));
                    $assignment_string = get_string('goalassignmentaudience', 'totara_hierarchy',
                            format_string($audience->name));
                    break;
                case GOAL_ASSIGNMENT_POSITION:
                    // Position: {$posname}.
                    $sql = " SELECT p.fullname
                             FROM {pos} p
                             JOIN {goal_grp_pos} g
                             ON g.posid = p.id
                             WHERE g.id = ?";
                    $pos = $DB->get_record_sql($sql, array($assignment->assignmentid));
                    $assignment_string = get_string('goalassignmentposition', 'totara_hierarchy',
                            format_string($pos->fullname));
                    break;
                case GOAL_ASSIGNMENT_ORGANISATION:
                    // Organisation: {$orgname}.
                    $sql = " SELECT o.fullname
                             FROM {org} o
                             JOIN {goal_grp_org} g
                             ON g.orgid = o.id
                             WHERE g.id = ?";
                    $org = $DB->get_record_sql($sql, array($assignment->assignmentid));
                    $assignment_string = get_string('goalassignmentorganisation', 'totara_hierarchy',
                            format_string($org->fullname));
                    break;
                default:
                    // Unrecognised assignment type.
                    print_error('invalidassigntype', 'totara_hierarchy', 'company');
            }
        } else {
            // Unrecognised goal type.
            print_error('invalidgoaltype', 'totara_hierarchy');
        }
        return $assignment_string;
    }

    /**
     * Get all the assignment instances for a given pos/org/cohort
     *
     * @param int $modtype     The type of module (pos/org/cohort)
     * @param int $modid       The id of the module to retrieve assignments of
     *
     * @return array()         An array of assignment objects
     */
    public static function get_modules_assigned_goals($modtype, $modid) {
        global $DB;

        switch ($modtype) {
            case GOAL_ASSIGNMENT_AUDIENCE:
                $table = 'goal_grp_cohort';
                $modfield = 'cohortid';
                $mod = null;
                break;
            case GOAL_ASSIGNMENT_POSITION:
                $table = 'goal_grp_pos';
                $modfield = 'posid';
                $mod = $DB->get_record('pos', array('id' => $modid));
                $prefix = 'pos';
                break;
            case GOAL_ASSIGNMENT_ORGANISATION:
                $table = 'goal_grp_org';
                $modfield = 'orgid';
                $mod = $DB->get_record('org', array('id' => $modid));
                $prefix = 'org';
                break;
        }

        $sql = "SELECT ga.*, g.fullname
                    FROM {{$table}} ga
                    LEFT JOIN {goal} g
                        ON g.id = ga.goalid
                    WHERE ga.{$modfield} = ?";
        $mod_assignments = $DB->get_records_sql($sql, array($modid));

        // Check for any parent assignments that have include children.
        $parent_assignments = array();
        if (!empty($mod)) {
            $parent_assignments = self::check_parent_assignments($table, $prefix, $modfield, $mod);
        }

        return array_merge($mod_assignments, $parent_assignments);
    }

    public static function check_parent_assignments($table, $prefix, $field, $mod) {
        global $DB;

        $path = trim($mod->path, '/');
        $parents = explode('/', $path);

        $parent_assignments = array();
        foreach ($parents as $parent) {
            if ($parent == $mod->id) {
                // Skip the child item.
                continue;
            }
            $sql = "SELECT ga.*, g.fullname, mo.fullname as parentname
                    FROM {{$table}} ga
                    LEFT JOIN {goal} g
                        ON g.id = ga.goalid
                    LEFT JOIN {{$prefix}} mo
                        ON ga.{$field} = mo.id
                    WHERE ga.{$field} = ?
                    AND ga.includechildren = 1";
            $parent_assignments = array_merge($parent_assignments, $DB->get_records_sql($sql, array($parent)));
        }

        return $parent_assignments;
    }



    /**
     * Checks whether there is already an assignment between a goal instance of type
     *
     * @oaram int $modtype      An value of GOAL_ASSIGNMENT_TYPE
     * @param int $modid        The id of the GOAL_ASSIGNMENT_TYPE
     * @param int $goalid       The id of a goal
     * @return boolean          Whether goalid is assigned to modid
     */
    public static function currently_assigned($modtype, $modid, $goalid) {
        global $DB;

        $type_info = self::goal_assignment_type_info($modtype);
        $modfield = $type_info->field;
        $params = array('goalid' => $goalid, $modfield => $modid);
        if ($modtype == GOAL_ASSIGNMENT_INDIVIDUAL) {
            $params['assigntype'] = $modtype;
        }

        return $DB->record_exists($type_info->table, $params);
    }

    /**
     * This was being done all over the place so it should make a good lib function,
     * get information about a given GOAL_ASSIGNMENT_TYPE as defined at the top of this file.
     *
     * @param int $assigntype   The assignment type to retrieve information for
     * @param int $goalid       The id of the associated goal or goal_personal, used to retrieve the name
     * @param int $modid
     * @return object           An object containing all the relevant information
     */
    public static function goal_assignment_type_info($assigntype, $goalid = null, $modid = null) {
        global $DB;

        $assigntype_info = new stdClass();

        switch ($assigntype) {
            case GOAL_ASSIGNMENT_INDIVIDUAL:
                $assigntype_info->table = 'goal_user_assignment';
                $assigntype_info->field = 'userid';
                $assigntype_info->fullname = 'goal'; // Only used to set up pages.
                $assigntype_info->shortname = 'goal';
                $assigntype_info->companygoal = true;

                // Get the goal to retrieve a few variables.
                $goal = !empty($goalid) ? $DB->get_record('goal', array('id' => $goalid)) : null;

                $assigntype_info->goalname = !empty($goal) ? $goal->fullname : '';
                $assigntype_info->timecreated = !empty($goal) ? $goal->timecreated : 0;
                $assigntype_info->modname = !empty($modid) ? fullname($DB->get_record('user', array('id' => $modid))) : '';
                break;
            case GOAL_ASSIGNMENT_AUDIENCE:
                $assigntype_info->members_table = 'cohort_members';
                $assigntype_info->members_field = 'cohortid';
                $assigntype_info->table = 'goal_grp_cohort';
                $assigntype_info->field = 'cohortid';
                $assigntype_info->fullname = 'cohort';
                $assigntype_info->shortname = 'cohort';
                $assigntype_info->companygoal = true;

                // Get the goal to retrieve a few variables.
                $goal = !empty($goalid) ? $DB->get_record('goal', array('id' => $goalid)) : null;

                $assigntype_info->goalname = !empty($goal) ? $goal->fullname : '';
                $assigntype_info->timecreated = !empty($goal) ? $goal->timecreated : 0;
                $assigntype_info->modname = !empty($modid) ? $DB->get_field('cohort', 'name', array('id' => $modid)) : '';
                break;
            case GOAL_ASSIGNMENT_POSITION:
                $assigntype_info->members_table= 'pos_assignment';
                $assigntype_info->members_field= 'positionid';
                $assigntype_info->table = 'goal_grp_pos';
                $assigntype_info->field = 'posid';
                $assigntype_info->fullname = 'position';
                $assigntype_info->shortname = 'pos';
                $assigntype_info->companygoal = true;

                // Get the goal to retrieve a few variables.
                $goal = !empty($goalid) ? $DB->get_record('goal', array('id' => $goalid)) : null;

                $assigntype_info->goalname = !empty($goal) ? $goal->fullname : '';
                $assigntype_info->timecreated = !empty($goal) ? $goal->timecreated : 0;
                $assigntype_info->modname = !empty($modid) ? $DB->get_field('pos', 'fullname', array('id' => $modid)) : '';
                break;
            case GOAL_ASSIGNMENT_ORGANISATION:
                $assigntype_info->members_table = 'pos_assignment';
                $assigntype_info->members_field = 'organisationid';
                $assigntype_info->table = 'goal_grp_org';
                $assigntype_info->field = 'orgid';
                $assigntype_info->fullname = 'organisation';
                $assigntype_info->shortname = 'org';
                $assigntype_info->companygoal = true;

                // Get the goal to retrieve a few variables.
                $goal = !empty($goalid) ? $DB->get_record('goal', array('id' => $goalid)) : null;

                $assigntype_info->goalname = !empty($goal) ? $goal->fullname : '';
                $assigntype_info->timecreated = !empty($goal) ? $goal->timecreated : 0;
                $assigntype_info->modname = !empty($modid) ? $DB->get_field('org', 'fullname', array('id' => $modid)) : '';
                break;
            case GOAL_ASSIGNMENT_SELF:
            case GOAL_ASSIGNMENT_MANAGER:
            case GOAL_ASSIGNMENT_ADMIN:
                $assigntype_info->table = 'goal_personal';
                $assigntype_info->field = 'userid';
                $assigntype_info->fullname = 'personal';
                $assigntype_info->shortname = 'user';
                $assigntype_info->companygoal = false;

                // Get the goal to retrieve a few variables.
                $goal = !empty($goalid) ? self::get_goal_item(array('id' => $goalid), self::SCOPE_PERSONAL) : null;

                $assigntype_info->goalname = !empty($goal) ? $goal->name : '';
                $assigntype_info->timecreated = !empty($goal) ? $goal->timecreated : 0;
                $assigntype_info->modname = !empty($modid) ? fullname($modid) : '';
                break;
        }

        /*
         * Object being returned should contain:
         * string   $table          The name of the assignments table for that type
         * string   $field          The name of the unique identifier for that type
         * string   $modname        The fullname of the assignment type
         * string   $goalname       The fullname of the goal
         * string   $modname        The fullname of the associated item
         */
        return $assigntype_info;
    }

    /**
     * Changes a default grp_type into a GOAL_ASSIGNMENT_X value
     *
     * @param string $grp_type  The type as handed back by totara_assign_goal class
     *                          accepted values of 'cohort/org/pos/'
     * @return int              The integer constant for the given string
     */
    public static function grp_type_to_assignment($grp_type) {
        switch ($grp_type) {
            case 'cohort' :
                return GOAL_ASSIGNMENT_AUDIENCE;
            case 'pos' :
                return GOAL_ASSIGNMENT_POSITION;
            case 'org' :
                return GOAL_ASSIGNMENT_ORGANISATION;
        }

        // We have gone through the switch without finding it, something is wrong.
        return false;
    }

    public static function can_view_goals($userid = null) {
        global $DB, $USER;

        if (!isloggedin()) {
            return false;
        }

        if (!$userid) {
            $userid = $USER->id;
        }

        $usercontext = context_user::instance($userid);

        // If they can view personal goals, and either have some to view or can add more.
        $view_personal = has_capability('totara/hierarchy:viewownpersonalgoal', $usercontext);
        if ($view_personal) {
            $has_personal = $DB->record_exists('goal_personal', array('userid' => $userid, 'deleted' => 0));
            if ($has_personal) {
                return true;
            }
            $add_personal = has_capability('totara/hierarchy:manageownpersonalgoal', $usercontext);
            if ($add_personal) {
                return true;
            }
        }

        // If they can view company goals, and either have some to view or can add more.
        $view_company = has_capability('totara/hierarchy:viewowncompanygoal', $usercontext);
        if ($view_company) {
            $has_company = $DB->record_exists('goal_user_assignment', array('userid' => $userid));
            if ($has_company) {
                return true;
            }
            $add_company = has_capability('totara/hierarchy:manageowncompanygoal', $usercontext);
            if ($add_company) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a users goal permissions within a specified user_context,
     * use extract on the page calling this to cache the permissions on the page.
     *
     * @param context $context don't ever really need this, just here to match the hierarchy one.
     * @param int $user The user id of the context to check the permissions in
     *
     * @return array()  ->can_view_personal
     *                  ->can_edit_personal
     *                  ->can_view_company
     *                  ->can_edit_company
     *                  ->can_edit
     */
    public function get_permissions($context = null, $user = null) {
        global $USER;

        $permissions = parent::get_permissions($context, $user);

        // Plus all the extra goals permissions.
        if (!empty($user)) {
            $userid = is_object($user) ? $user->id : $user;
            $context = context_user::instance($userid);
            $syscontext = context_system::instance();

            // Set up permissions checks so we don't have to do them everytime.
            if (has_capability('totara/hierarchy:managegoalassignments', $syscontext)) {
                // Admin permissions, can do anything with this one permission.
                $permissions['can_view_personal'] = true;
                $permissions['can_edit_personal'] = true;
                $permissions['can_view_company'] = true;
                $permissions['can_edit_company'] = true;
                $permissions['can_edit'] = array(
                    GOAL_ASSIGNMENT_INDIVIDUAL => true,
                    GOAL_ASSIGNMENT_SELF => true,
                    GOAL_ASSIGNMENT_MANAGER => true,
                    GOAL_ASSIGNMENT_ADMIN => true,
                );
            } else if (totara_is_manager($userid)) {
                // Manager permissions.
                $permissions['can_view_personal'] = has_capability('totara/hierarchy:viewstaffpersonalgoal', $context);
                $permissions['can_edit_personal'] = has_capability('totara/hierarchy:managestaffpersonalgoal', $context);
                $permissions['can_view_company'] = has_capability('totara/hierarchy:viewstaffcompanygoal', $context);
                $permissions['can_edit_company'] = has_capability('totara/hierarchy:managestaffcompanygoal', $context);
                $permissions['can_edit'] = array(
                    GOAL_ASSIGNMENT_INDIVIDUAL => $permissions['can_edit_company'],
                    GOAL_ASSIGNMENT_SELF => $permissions['can_edit_personal'],
                    GOAL_ASSIGNMENT_MANAGER => $permissions['can_edit_personal'],
                    GOAL_ASSIGNMENT_ADMIN => false,
                );
            } else if ($userid == $USER->id) {
                // User permissions.
                $permissions['can_view_personal'] = has_capability('totara/hierarchy:viewownpersonalgoal', $context);
                $permissions['can_edit_personal'] = has_capability('totara/hierarchy:manageownpersonalgoal', $context);
                $permissions['can_view_company'] = has_capability('totara/hierarchy:viewowncompanygoal', $context);
                $permissions['can_edit_company'] = has_capability('totara/hierarchy:manageowncompanygoal', $context);
                $permissions['can_edit'] = array(
                    GOAL_ASSIGNMENT_INDIVIDUAL => $permissions['can_edit_company'],
                    GOAL_ASSIGNMENT_SELF => $permissions['can_edit_personal'],
                    GOAL_ASSIGNMENT_MANAGER => false,
                    GOAL_ASSIGNMENT_ADMIN => false,
                );
            } else {
                return false;
            }
        }

        return $permissions;
    }

    /**
     * Check if goal feature is disabled
     *
     * @return Nothing but print an error if goals are not enabled
     */
    public static function check_feature_enabled() {
        if (totara_feature_disabled('goals')) {
            print_error('goalsdisabled', 'totara_hierarchy');
        }
    }
}
