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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package totara
 * @subpackage core
 */

/**
 * base assignment classes totara_assign_core and totara_assign_core_groups
 * will mostly be extended by child classes in each totara module, but is generic and functional
 * enough to still be useful for simple assignment cases
 *
 * Both expect at least one assign/groups/*.class.php grouping class to exist
 */
require_once($CFG->dirroot.'/totara/reportbuilder/lib.php');

class totara_assign_core {

    /**
     * Reference to the module.
     *
     * @access  public
     * @var     string
     */
    protected static $module = 'core';

    /**
     * Id of the module instance - appraisalid, programid, planid etc.
     *
     * @access  public
     * @var     int
     */
    protected $moduleinstanceid;

    /**
     * The actual module instance - appraisal, program, plan etc.
     *
     * @access  public
     * @var     object
     */
    protected $moduleinstance;

    /**
     * Basepath to the module assign directory where the modules assignment classes will live.
     *
     * @access  public
     * @var     string
     */
    protected $basepath;

    public function __construct($module, $moduleinstance) {
        global $CFG;
        $this->moduleinstanceid = $moduleinstance->id;
        $this->moduleinstance = $moduleinstance;
        self::$module = $module;
        $this->basepath = $CFG->dirroot . "/totara/{$module}/lib/assign/";
    }

    /**
     * Given a grouptype string, create an instance from the appropriate classfile.
     * Returns a grouptype object which can be used to manage assignedgroups of that type.
     * @access  public
     * @param string $grouptype
     * @return appropriate assignment grouptype object
     */
    public function load_grouptype($grouptype) {
        $module = self::$module;
        $classname = "totara_assign_{$module}_grouptype_{$grouptype}";
        $classfile = $this->basepath . "groups/{$grouptype}.class.php";

        // Check class file exists.
        if (!file_exists($classfile)) {
            print_error('error:assignmentprefixnotfound', 'totara_core', '', $grouptype);
        }

        // Load class file.
        require_once($classfile);

        // Check class exists.
        if (!class_exists($classname)) {
            print_error('error:assignmentprefixnotfound', 'totara_core', '', $grouptype);
        }

        // Instantiate and return an object of that class.
        return new $classname($this);
    }

    /**
     * Loop through code folder to find grouptype classes.
     * Override in child class to limit assignable grouptypes.
     *  e.g. return array('pos', 'org', 'cohort');
     * @access  public
     * @return  array of prefixes
     */
    public static function get_assignable_grouptypes() {
        global $CFG;

        static $grouptypes = array();
        if (!empty($grouptypes)) {
            return $grouptypes;
        }

        // Loop through code folder to find grouptype classes.
        $module = self::$module;
        // Loop through code folder to find group classes.
        $basepath = $CFG->dirroot . "/totara/{$module}/lib/assign/";
        if (is_dir($basepath . 'groups')) {
            $classfiles = glob($basepath . 'groups/*.class.php');
            if (is_array($classfiles)) {
                foreach ($classfiles as $filename) {
                    // Add them all to an array.
                    $grouptypes[] = str_replace('.class.php', '', basename($filename));
                }
            }
        }
        return $grouptypes;
    }

    /**
     * Get array of grouptype prefixes and displaynames
     * @access  public
     * @return  array('pos' => 'Position', 'org' => 'Organisation', 'cohort' => 'Audience')
     */
    public function get_assignable_grouptype_names() {
        $return = array();
        foreach ($this->get_assignable_grouptypes() as $grouptype) {
            $grouptypeobj = $this->load_grouptype($grouptype);
            $return[$grouptype] = $grouptypeobj->get_grouptype_displayname();
        }
        return $return;
    }

    /**
     * Get the name of a group assignment
     *
     * @param  string  grouptype
     * @param  int     groupid    The id of the group assignment
     * @return string
     */
    public function get_group_instance_name($grouptype, $groupid) {
        $groups = $this->get_assignable_grouptypes();

        if (!in_array($grouptype, $groups)) {
            print_error('error:invalidgrouptype', 'totara_core');
        }

        $group = $this->load_grouptype($grouptype);
        return $group->get_instance_name($groupid);
    }

    /**
     * Delete an assigned group
     * @access  public
     * @param $grouptype string grouptype prefix e.g. 'org'
     * @param $deleteid int id of the actual assigned group record
     * @return void
     */
    public function delete_assigned_group($grouptype, $deleteid) {
        if (!in_array($grouptype, $this->get_assignable_grouptypes())) {
            print_error('error:assigncannotdeletegrouptypex', 'totara_core', $grouptype);
        }
        if ($this->is_locked()) {
            print_error('error:assignmentmoduleinstancelocked', 'totara_core');
        }
        $grouptypeobj = $this->load_grouptype($grouptype);
        $grouptypeobj->delete($deleteid);
    }

    /**
     * Delete all of this module's assigned groups and clear out user_assignment table
     * @access public
     * @return void
     */
    public function delete() {
        if ($this->is_locked()) {
            print_error('error:assignmentmoduleinstancelocked', 'totara_core');
        }

        // Clear module user assignment table.
        $this->delete_user_assignments();

        // Delete each assigned group.
        $assignedgroups = $this->get_current_assigned_groups();
        foreach ($assignedgroups as $assignedgroup) {
            $this->delete_assigned_group($assignedgroup->grouptype, $assignedgroup->assignedgroupid);
        }
    }

    /**
     * Delete records from the user_assignment table
     * @access public
     * @return void
     */
    public function delete_user_assignments() {
        global $DB;

        if ($this->is_locked()) {
            print_error('error:assignmentmoduleinstancelocked', 'totara_core');
        }

        // Clear module user assign table.
        $tablename = self::$module . "_user_assignment";
        $modulekey = self::$module . "id";
        $moduleinstanceid = $this->moduleinstanceid;
        $DB->delete_records($tablename, array($modulekey => $moduleinstanceid));
    }

    /**
     * Query child classes to get back combined array of objects of all currently assigned groups.
     * Array should be passed to module renderer to do the actual display.
     * @return array of objects
     */
    public function get_current_assigned_groups() {
        global $DB;

        $sqlallassignedgroups = '';
        foreach ($this->get_assignable_grouptypes() as $grouptype) {
            // Instantiate a group object.
            $grouptypeobj = $this->load_grouptype($grouptype);
            $groupunion = (empty($sqlallassignedgroups)) ? "" : " UNION ";
            $sqlallassignedgroups .= $groupunion . $grouptypeobj->get_current_assigned_groups_sql($this->moduleinstanceid);
        }
        $assignedgroups = $DB->get_records_sql($sqlallassignedgroups, array());

        $grouptypenames = $this->get_assignable_grouptype_names();

        foreach ($assignedgroups as $assignedgroup) {
            $grouptypeobj = $this->load_grouptype($assignedgroup->grouptype);
            $includedids = $grouptypeobj->get_groupassignment_ids($assignedgroup);
            $assignedgroup->groupusers = $grouptypeobj->get_assigned_user_count($includedids);
            $assignedgroup->grouptypename = $grouptypenames[$assignedgroup->grouptype];
        }
        return $assignedgroups;
    }

    /**
     * Get all users currently assigned, either from the user assignemt table (if saved)
     * or else calculated from groups. If not saved, query child classes to get back list
     * of all users.
     *
     * @access public
     * @param $search string A search string to limit the results by (matches user firstname or lastname).
     * @param $limitfrom int
     * @param $limitnum int
     * @param $active boolean A flag that makes the function return only the active users.
     * @return recordset Containing basic information about users.
     */
    public function get_current_users($search=null, $limitfrom=null, $limitnum=null, $forcegroup=false) {
        global $DB;
        // How the current user list is calculated depends of the status.
        // It could be static (from a table) or dynamic (calculated).
        $liveassignments = $this->assignments_are_stored() && !$forcegroup;
        if ($liveassignments) {
            list($joinsql, $params, $joinalias) = $this->get_users_from_assignments_sql('u', 'id');
        } else {
            list($joinsql, $params, $joinalias) = $this->get_users_from_groups_sql('u', 'id');
        }

        // Get WHERE clause to restrict by search if required.
        list($searchsql, $searchparams) = $this->get_user_search_where_sql($search, 'u');
        $searchsql = !empty($searchsql) ? $searchsql : "1 = 1";

        // Get WHERE clause for any further restrictions.
        list($extrasql, $extraparams) = $this->get_user_extra_search_where_sql('u', $joinalias, $liveassignments);
        $extrasql = !empty($extrasql) ? $extrasql : "1 = 1";

        // Combine the two WHERE clauses.
        $wheresql = "WHERE {$searchsql} AND {$extrasql}";
        $whereparams = array_merge($searchparams, $extraparams);

        $usernamefields = get_all_user_name_fields(true, 'u');
        $sql = "SELECT u.id, {$usernamefields} FROM {user} u {$joinsql} {$wheresql}";
        $params = array_merge($params, $whereparams);

        $sql .= " ORDER BY u.firstname, u.lastname";

        return $DB->get_recordset_sql($sql, $params, $limitfrom, $limitnum);
    }


    /**
     * Get the number of users currently assigned, either from the user assignemt table (if saved)
     * or else calculated from groups. If not saved, query child classes to get count of all users.
     *
     * @access public
     * @param $search string A search string to limit the results by (matches user firstname or lastname).
     * @return int A count of the number of users assigned.
     */
    public function get_current_users_count($search=null) {
        global $DB;
        // How the current user list is calculated depends of the status.
        // It could be static (from a table) or dynamic (calculated).
        $liveassignments = $this->assignments_are_stored();
        if ($liveassignments) {
            list($joinsql, $params, $joinalias) = $this->get_users_from_assignments_sql('u', 'id');
        } else {
            list($joinsql, $params, $joinalias) = $this->get_users_from_groups_sql('u', 'id');
        }

        // Get WHERE clause to restrict by search if required.
        list($searchsql, $searchparams) = $this->get_user_search_where_sql($search, 'u');
        $searchsql = !empty($searchsql) ? $searchsql : "1 = 1";

        // Get WHERE clause for any further restrictions.
        list($extrasql, $extraparams) = $this->get_user_extra_search_where_sql('u', $joinalias, $liveassignments);
        $extrasql = !empty($extrasql) ? $extrasql : "1 = 1";

        // Combine the two WHERE clauses.
        $wheresql = "WHERE {$searchsql} AND {$extrasql}";
        $whereparams = array_merge($searchparams, $extraparams);

        // Do the count.
        $sql = "SELECT COUNT(*) FROM {user} u {$joinsql} {$wheresql}";
        $params = array_merge($params, $whereparams);

        return $DB->count_records_sql($sql, $params);
    }


    /**
     * Return the SQL WHERE clause and parameters to search by the query provided.
     *
     * Currently this does a partial match of the query against the user's first or last name.
     *
     * @param string $search    The string being searched for.
     * @param string $useralias The alias of the user table being used in your query.
     *
     * @return array(sql,params) Array containing "WHERE ..." sql snippet and parameters to use in another query.
     */
    protected function get_user_search_where_sql($search, $useralias) {
        global $DB;
        if (empty($search)) {
            return array('', array());
        }

        $likeparam = '%' . $DB->sql_like_escape($search) . '%';

        $sql = "(" .
            $DB->sql_like("{$useralias}.firstname", '?', false, false) .
            " OR " .
            $DB->sql_like("{$useralias}.lastname", '?', false, false) .
            ")";
        $params = array($likeparam, $likeparam);

        return array($sql, $params);
    }

    /**
     * Over ride in module code to add specific module related search queries.
     * It should look something like this.
     *
     * @param  $useralias       string  The alias of the user table.
     * @param  $joinalias       string  The alias of the joined table.
     * @param  $liveassignments boolean Flags which $joinalias table is being used.
     *
     * @return string  The additional where statement.
     */
    public function get_user_extra_search_where_sql($useralias, $joinalias, $liveassignments) {

        // If the extra search is on the $useralias table it is safe to put here.
        $sql = "";
        $params = array();

        if ($liveassignments) {
            // Extra search where clause on the $joinalias user_assignments table.
        } else {
            // Extra search where clause on the $joinalias group_assignments table.
        }

        return array($sql, $params);
    }

    /**
     * Returns the SQL to join to a table via userid, such that only records belonging to users
     * who are currently in the assignment will be left.
     *
     * This query finds users based on the entries in the
     * user_assignments table (as opposed to dynamically via groups).
     *
     * Example usage:
     *
     * list($assignsql, $assignparams, tablealias) = $assign->get_users_from_assignments('u', 'id');
     *
     * // return names of all users currently assigned
     * $sql = "SELECT u.firstname,u.lastname FROM {user} u $assignsql";
     * $users = $DB->get_records_sql($sql, $assignparams);
     *
     * @param $table  string The alias of the table you want to join to.
     *                       You MUST use a table alias, not just the table's name.
     * @param $field  string The name of the field containing user ids in the table you are joining to.
     *
     * @return array  An array containing an SQL snippet, parameters to restrict the users and the table alias.
     */
    public function get_users_from_assignments_sql($table, $field) {
        $module = self::$module;

        // Use a random string for the user_assignment alias to minimise risk of collision when joined.
        $uaalias = 'ua_' . random_string(15);

        // A bit of extra cleaning on table/field names since they aren't parameterized.
        $table = clean_param($table, PARAM_ALPHANUMEXT);
        $field = clean_param($field, PARAM_ALPHANUMEXT);

        // Just get a list of userids from the user_assignment table.
        $sql =  " JOIN {{$module}_user_assignment} {$uaalias}
            ON ({$table}.{$field} = {$uaalias}.userid
            AND {$uaalias}.{$module}id = ?)";

        $params = array($this->moduleinstanceid);

        return array($sql, $params, $uaalias);
    }


    /**
     * Returns the SQL to join to a table via userid, such that only records belonging to users
     * who are currently in the assignment will be left.
     *
     * This query finds users based on the entries in the
     * assignment groups tables (as opposed to a static list
     * via user_assignment table).
     *
     * Example usage:
     *
     * list($assignsql, $assignparams, $tablealias) = $assign->get_users_from_groups_sql('u', 'id');
     *
     * // return names of all users currently assigned
     * $sql = "SELECT u.firstname,u.lastname FROM {user} u $assignsql";
     * $users = $DB->get_records_sql($sql, $assignparams);
     *
     * @param $table  string The alias of the table you want to join to.
     *                       You MUST use a table alias, not just the table's name.
     * @param $field  string The name of the field containing user ids in the table you are joining to.
     *
     * @return array  An array containing an SQL snippeti, parameters to restrict the users and table alias.
     */
    public function get_users_from_groups_sql($table, $field) {
        $module = self::$module;
        $assignedgroups = $this->get_current_assigned_groups();

        // Use a random string for the subquery alias to minimise risk of collision when joined.
        $sqalias = 'sq_' . random_string(15);

        // A bit of extra cleaning on table/field names since they aren't parameterized.
        $table = clean_param($table, PARAM_ALPHANUMEXT);
        $field = clean_param($field, PARAM_ALPHANUMEXT);

        // If there are no assigned groups then there can't be any assigned users.
        // Need to craft a query that removes all users when joined.
        if (empty($assignedgroups)) {
            $sql = "JOIN (SELECT 0 AS userid) {$sqalias}
                ON {$sqalias}.userid = {$table}.{$field}";
            return array($sql, array(), $sqalias);
        }

        $sqls = array();
        $params = array();

        // Each type of assignment will generate its own SQL, we just
        // need to join them together with a UNION, excluding any
        // duplicates.
        foreach ($assignedgroups as $assignedgroup) {
            $grouptypeobj = $this->load_grouptype($assignedgroup->grouptype);
            list($groupsql, $groupparams) =
                $grouptypeobj->get_current_assigned_users_sql($assignedgroup);
            $sqls[] = $groupsql;
            $params = array_merge($params, $groupparams);
        }

        $allgroupsql = implode(" \nUNION\n    ", $sqls);

        // Now join to the UNIONed queries as a subquery.
        $sql = " JOIN (\n{$allgroupsql}\n) {$sqalias}
            ON {$sqalias}.userid = {$table}.{$field}";
        return array($sql, $params, $sqalias);
    }


    /**
     * Given a set of userids, return information on how they were assigned.
     *
     * Note: If any of the $userids provided are not assigned at all, no
     *       key for that user will appear in the output array.
     *
     * @param array $userids An array of userids to find out about
     * @param array array Array keyed by userid containing details about
     *                    each group that caused the user to be assigned.
     */
    public function get_group_assignedvia_details($userids) {
        $assignedgroups = $this->get_current_assigned_groups();
        // If there are no assigned groups then there can't be any assigned users.
        if (empty($assignedgroups)) {
            return array();
        }

        // To achieve this in a scaleable way, we query each group
        // and get all matching IDs in one go, then aggregate into
        // an array.
        $assignedviadetails = array();
        foreach ($assignedgroups as $assignedgroup) {
            $string = $assignedgroup->grouptypename . ' ' . $assignedgroup->sourcefullname;
            if (!empty($assignedgroup->includechildren)) {
                $string .= get_string('assignincludechildren', 'totara_core');
            }
            $grouptypeobj = $this->load_grouptype($assignedgroup->grouptype);
            $groupids = $grouptypeobj->filter_only_assigned_users($assignedgroup, $userids);

            foreach ($groupids as $userid) {
                $assignedviadetails[$userid][$assignedgroup->id] = $string;
            }
        }
        return $assignedviadetails;
    }


    /**
     * Get all users that are currently assigned (in group tables) but have not been stored (in assignment table).
     *
     * @access public
     * @param $limitfrom int
     * @param $limitnum int
     * @return recordset Containing basic information about users.
     */
    public function get_unstored_users($limitfrom=null, $limitnum=null) {
        global $DB;

        list($assignjoinsql, $assignparams) = $this->get_users_from_assignments_sql('u', 'id');
        list($groupjoinsql, $groupparams) = $this->get_users_from_groups_sql('u', 'id');

        $params = array_merge($groupparams, $assignparams);

        $sql = "SELECT u.id, u.firstname, u.lastname FROM {user} u " . $groupjoinsql . "
                WHERE u.id NOT IN (SELECT u.id FROM {user} u " . $assignjoinsql . ")";

        return $DB->get_recordset_sql($sql, $params, $limitfrom, $limitnum);
    }


    /**
     * Get all users that are currently stored (in assignment table) but are not assigned (in group tables).
     *
     * @access public
     * @param $limitfrom int
     * @param $limitnum int
     * @return recordset Containing basic information about users.
     */
    public function get_removed_users($limitfrom=null, $limitnum=null) {
        global $DB;

        list($assignjoinsql, $assignparams, $assignalias) = $this->get_users_from_assignments_sql('u', 'id');
        list($groupjoinsql, $groupparams, $groupalias) = $this->get_users_from_groups_sql('u', 'id');

        $params = array_merge($assignparams, $groupparams);

        $sql = "SELECT u.id, u.firstname, u.lastname, {$assignalias}.id as userassignmentid
                FROM {user} u " . $assignjoinsql . "
                WHERE u.id NOT IN (SELECT u.id FROM {user} u " . $groupjoinsql . ")";

        return $DB->get_recordset_sql($sql, $params, $limitfrom, $limitnum);
    }


    /**
     * Get the users according to the current state of the assigned groups and store to module_user_assignment
     * @access public
     * @return void
     */
    public function store_user_assignments($newusers = null, $processor = null) {
        global $DB;

        $module = self::$module;
        $modulekey = "{$module}id";
        $moduleinstanceid = $this->moduleinstanceid;
        $tablename = "{$module}_user_assignment";

        $transaction = $DB->start_delegated_transaction();

        if (!$newusers) {
            // Clear out the user assignment table first to prevent duplicates.
            $DB->delete_records($tablename, array($modulekey => $moduleinstanceid));

            // Get recordset containing current user ids.
            $users = $this->get_current_users();
        } else {
            $users = $newusers;
        }

        if (empty($processor)) {
            // Define a default processor function to reformat the data on the fly.
            $processor = function($record, $modulekey, $moduleinstanceid) {
                $todb = new stdClass();
                $todb->$modulekey = $moduleinstanceid;
                $todb->userid = $record->id;
                return $todb;
            };
        }

        // Pass required data into the processor as an argument.
        $processordata = array(
            'modulekey' => $modulekey,
            'moduleinstanceid' => $moduleinstanceid
        );

        // Accept the recordset and save to user_assignment table in batches.
        $DB->insert_records_via_batch($tablename, $users, $processor, $processordata);

        $users->close();

        $transaction->allow_commit();
    }


    /**
     * Duplicate the assign onto the new moduleinstance.
     * @param $newmoduleinstance object The module instance to assign the new assign to.
     */
    public function duplicate($targetmoduleinstance) {
        // Find the class of this instance (may be subclassed).
        $mymoduleinstanceclass = get_class($this);

        /* Note: There's no need to load the subclass file now as it must have been loaded to call
         * the subclass's duplicate method. */

        // Create a new instance of the same class, calling the subclass's contructor (if defined).
        /* Note: If the subclass has a constructor that requires more parameters than just a module
         * type and an assign instance then the subclass must also subclass duplicate. */
        $newassign = new $mymoduleinstanceclass(self::$module, $targetmoduleinstance);

        // Iterate over each group.
        $assignedgroups = $this->get_current_assigned_groups();
        foreach ($assignedgroups as $assignedgroup) {
            $grouptypeobj = $this->load_grouptype($assignedgroup->grouptype);
            $grouptypeobj->duplicate($assignedgroup, $newassign);
        }
    }

    /**
     * Get functions to return class properties
     * @access  public
     * @return mixed
     */
    public function get_assign_module() {
        return self::$module;
    }

    public function get_assign_moduleinstanceid() {
        return $this->moduleinstanceid;
    }

    public function get_assign_moduleinstance() {
        return $this->moduleinstance;
    }

    /**
     * Can optionally be implemented by children.
     * Prevents add and remove when locked.
     */
    public function is_locked() {
        return false;
    }

    /**
     * Should be overridden by subclasses to determine if the users have been stored in the module's user_assignment table.
     *
     * @return bool whether or not users have been stored in the user_assignments table.
     */
    public function assignments_are_stored() {
        return false;
    }

}


abstract class totara_assign_core_grouptype {

    // The module class object.
    protected $assignment;

    protected $params = array(
        'equal' => 0,
        'includechildren' => 0,
        'listofvalues' => 1,
    );

    abstract public function generate_item_selector($hidden=array(), $groupinstanceid=false);
    abstract public function handle_item_selector($data);
    abstract public function get_instance_name($instanceid);

    public function __construct($assignobject) {
        // Store the whole assignment object from totara_assign or child class of totara_assign.
        $this->assignment = $assignobject;
    }

    public function validate_item_selector() {
        // Over-ride in child classes that need to perform validation on submitted dialog info.
        return true;
    }

    /**
     * Loads and returns the child assignment class object
     * @param object $assignobject base assignment class
     * @return object child class
     */
    public static function load_grouptype($assignobject) {
        $classname = "totara_assign_{$assignobject->module}_group_{$assignobject->grouptype}";
        // Check group class file exists.
        $classfile = $assignobject->basepath . "groups/{$assignobject->grouptype}.class.php";
        if (!file_exists($classfile)) {
            print_error('error:assignmentprefixnotfound', 'totara_core', '', $assignobject->grouptype);
        }
        // Load class file.
        require_once($classfile);
        // Check class exists.
        if (!class_exists($classname)) {
            print_error('error:assignmentprefixnotfound', 'totara_core', '', $assignobject->grouptype);
        }
        // Instantiate and return an object of that class.
        return new $classname($assignobject);
    }

    /**
     * Gets simple array of all ids from $tablename from the assigned group (and their children)
     * @access public
     * @return array of ids
     */
    public function get_assigned_group_includedids() {
        global $DB;

        // Get information about current assigned groups.
        $sqlassignedgroups = $this->get_current_assigned_groups_sql($this->moduleinstanceid);
        $assignedgroups = $DB->get_records_sql($sqlassignedgroups, array());

        // Aggregate assignments from each group.
        $allincludedids = array();
        foreach ($assignedgroups as $assignedgroup) {
            $includedids = $this->get_groupassignment_ids($assignedgroup);
            $allincludedids = array_merge($allincludedids, $includedids);
        }
        return $allincludedids;
    }


    /**
     * Stub function to be implemented by children.
     * @return object child class
     */
    public function get_current_assigned_groups() {
        return array();
    }

    /**
     * Stub function to be implemented by children.
     */
    public function duplicate($assignedgroup, $newassign) {
    }

    /**
     * Given an array of userids, this function reduces the list to
     * only include those who were assigned via this group.
     *
     * Avoid passing all assigned users into this function because
     * they are used in an IN() SQL statement, instead restrict list
     * to only the users you need to know about.
     *
     * @param $assignedgroup object Object containing data about a group as generated by {@link get_current_assigned_groups()}
     * @param $userids array Array of user ids.
     * @return array Array of user ids from the input which are assigned
     *               via this method.
     */
    public function filter_only_assigned_users($assignedgroup, $userids) {
        global $DB;
        if (empty($userids)) {
            return array();
        }

        // Join to restrict by assigned users.
        list ($joinsql, $joinparams) = $this->get_current_assigned_users_sql($assignedgroup);

        // Where clause to filter by list of user ids.
        list ($insql, $inparams) = $DB->get_in_or_equal($userids);

        // Combine into a single query.
        $sql = "SELECT * FROM ({$joinsql}) sq WHERE sq.userid {$insql}";
        $params = array_merge($joinparams, $inparams);

        return $DB->get_fieldset_sql($sql, $params);
    }
}

require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_hierarchy.class.php');
require_once($CFG->dirroot.'/totara/hierarchy/lib.php');

/**
 * Cohort multi-picker dialog class.
 */

class totara_assign_ui_picker_cohort extends totara_dialog_content {
    public $handlertype = 'treeview';
    public $params = array(
        'equal' => 1,
        'listofvalues' => 1,
        'includechildren' => 0
    );

    /**
     * Helper function to override the parameter defaults
     * @param   $newparams    array parameters to be overridden
     * @return  void
     */
    public function set_parameters($newparams = array()) {
        if (!is_array($newparams)) {
            print_error('error:assignmentbadparameters', 'totara_core', null, null);
            die();
        }
        foreach ($newparams as $key => $val) {
            $this->params[$key] = $val;
        }
    }

    /**
     * Returns markup to be used in the selected pane of a multi-select dialog
     *
     * @param   $elements    array elements to be created in the pane
     * @return  $html
     */
    public function populate_selected_items_pane($elements) {
        $html = '';
        return $html .= parent::populate_selected_items_pane($elements);
    }

    /**
     * Generates the content of the dialog
     * @param   $hidden array of extra hidden parameters
     * @param   $selectedids Items that have already been selected to be grayed out in the picker
     * @return  void
     */
    public function generate_item_selector($hidden = array(), $selectedids = array()) {
        global $DB;

        // Get cohorts.
        $sql = "SELECT c.id,
                CASE WHEN c.idnumber IS NULL OR c.idnumber = '' OR c.idnumber = '0'
                    THEN c.name
                    ELSE " . $DB->sql_concat("c.name", "' ('", "c.idnumber", "')'") .
                "END AS fullname
            FROM {cohort} c
            ORDER BY c.name, c.idnumber";
        $items = $DB->get_records_sql($sql, array());

        // Set up dialog.
        $dialog = $this;
        if (!empty($hidden)) {
            $this->set_parameters($hidden);
        }
        $dialog->type = totara_dialog_content::TYPE_CHOICE_MULTI;
        $dialog->items = $items;
        $dialog->selected_title = 'itemstoadd';
        $dialog->searchtype = 'cohort';

        $alreadyselected = array();
        if (!empty($selectedids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($selectedids);
            $sql = "SELECT c.id, c.name
                  FROM {cohort} c
                 WHERE c.id $insql";
            $alreadyselected = $DB->get_records_sql($sql, $inparams);
        }

        $dialog->disabled_items = $alreadyselected;
        $dialog->unremovable_items = $alreadyselected;
        $dialog->urlparams = $this->params;
        // Display.
        $markup = $dialog->generate_markup();
        echo $markup;
    }

    /**
     * Duplicate the group onto the new assign.
     */
    public function duplicate($newassign) {
        // Find the class of this instance (may be subclassed).
        $mygroupclass = get_class($this);

        // Create a new instance of the same class, calling the subclass's contructor (if defined).
        /* If the subclass has a constructor that requires more parameters than just an assign
         * instance then the subclass must also subclass duplicate. */
        new $mygroupclass($newassign);
    }

}

/**
 * Hierarchy multi-picker dialog class.
 */
class totara_assign_ui_picker_hierarchy extends totara_dialog_content_hierarchy_multi {
    public $params = array(
        'equal' => 0,
        'includechildren' => 0,
        'listofvalues' => 1,
    );
    public $handlertype = 'treeview';
    public $prefix;
    public $shortprefix;

    public function __construct($prefix, $frameworkid = 0, $showhidden = false) {
        $this->prefix = $prefix;
        $this->shortprefix = hierarchy::get_short_prefix($prefix);
        parent::__construct($this->prefix, $frameworkid, $showhidden);
    }

    /**
     * Helper function to override the parameter defaults
     * @param   $newparams    array parameters to be overridden
     * @return  void
     */
    public function set_parameters($newparams = array()) {
        if (!is_array($newparams)) {
            print_error('error:assignmentbadparameters', 'totara_core', null, null);
            die();
        }
        foreach ($newparams as $key => $val) {
            $this->params[$key] = $val;
        }
    }

    /**
     * Returns markup to be used in the selected pane of a multi-select dialog
     *
     * @param   $elements    array elements to be created in the pane
     * @return  $html
     */
    public function populate_selected_items_pane($elements, $overridden = false) {

        if (!$overridden) {
            $childmenu = array();
            $childmenu[0] = get_string('includechildrenno', 'totara_cohort');
            $childmenu[1] = get_string('includechildrenyes', 'totara_cohort');
            $selected = isset($this->params['includechildren']) ? $this->params['includechildren'] : '';
            $html = html_writer::select($childmenu, 'includechildren', $selected, array(),
                array('id' => 'id_includechildren', 'class' => 'assigngrouptreeviewsubmitfield'));
        } else {
            $html = '';
        }

        return $html . parent::populate_selected_items_pane($elements);
    }

    /**
     * Generates the content of the dialog
     * @param   $hidden array of extra hidden parameters
     * @param   $selectedids Items that have already been selected to be grayed out in the picker
     * @return  void
     */
    public function generate_item_selector($hidden = array(), $selectedids = array()) {
        global $DB;

        // Parent id.
        $parentid = optional_param('parentid', 0, PARAM_INT);

        // Only return generated tree html.
        $treeonly = optional_param('treeonly', false, PARAM_BOOL);

        $frameworkid = optional_param('frameworkid', 0, PARAM_INT);
        $switchframework = optional_param('switchframework', false, PARAM_BOOL);

        // Setup page.
        $hierarchy = $this->shortprefix;
        $alreadyselected = array();
        if (!empty($selectedids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($selectedids);
            $sql = "SELECT hier.id, hier.fullname
                  FROM {{$hierarchy}} hier
                 WHERE hier.id $insql";
            $alreadyselected = $DB->get_records_sql($sql, $inparams);
        }

        // Load dialog content generator.
        $dialog = $this;

        if ($switchframework) {
            $dialog->set_framework($frameworkid);
        }

        // Toggle treeview only display.
        $dialog->show_treeview_only = $treeonly;

        // Load items to display.
        $dialog->load_items($parentid);

        if (!empty($hidden)) {
            $dialog->urlparams = $hidden;
        }

        // Set disabled/selected items.
        $dialog->disabled_items = $alreadyselected;
        if (isset($this->includechildren)) {
            $dialog->includechildren = $this->includechildren;
        }

        // Set title.
        $dialog->select_title = '';
        $dialog->selected_title = '';

        // Display.
        $markup = $dialog->generate_markup();
        // Hack to get around the hack that prevents deleting items via dialogs.
        $hackedmarkup = str_replace('<td class="selected" ', '<td class="selected selected-shown" ', $markup);
        echo $hackedmarkup;
    }
}

/**
 * Initialises Javascript for dialogs and (optionally) a paginated datatable
 * @param   $module string The Totara module
 * @param   $itemid int id of the object the dialogs will be assigning groups to
 * @param   $datatable boolean Whether to start up the Javascript for a datatable
 * @param   $notice string The html output of a notice to display on change
 * @return  void
 */
function totara_setup_assigndialogs($module, $itemid, $datatable = false, $notice = "") {
    global $CFG, $PAGE;
    // Setup custom javascript.
    $jselements = array(
        TOTARA_JS_DIALOG,
        TOTARA_JS_TREEVIEW,
        TOTARA_JS_UI);
    if ($datatable) {
        $jselements[] = TOTARA_JS_DATATABLES;
    }
    local_js(
        $jselements
    );
    $PAGE->requires->strings_for_js(array('assigngroup'), 'totara_' . $module);
    $jsmodule = array(
        'name' => 'totara_assigngroups',
        'fullpath' => '/totara/core/lib/assign/assigngroup_dialog.js',
        'requires' => array('json'));
    $args = array('args' => '{"module":"'.$module.'","sesskey":"'.sesskey().'","notice":"'.addslashes_js($notice).'"}');

    $PAGE->requires->js_init_call('M.totara_assigngroupdialog.init', $args, false, $jsmodule);

    if ($datatable) {
        $PAGE->requires->js_init_code('
                $(document).ready(function() {
                    var oTable = $("#datatable").dataTable( {
                    "bProcessing": true,
                    "bServerSide": true,
                    "sPaginationType": "full_numbers",
                    "sAjaxSource": "'.$CFG->wwwroot.'/totara/'.$module.'/lib/assign/ajax.php",
                    "fnServerParams": function ( aoData ) {
                            aoData.push( { "name": "module", "value": "'.$module.'" } );
                            aoData.push( { "name": "itemid", "value": "'.$itemid.'" } );
                    },
                    "oLanguage" : {
                        "sEmptyTable":     "'.addslashes_js(get_string('datatable:sEmptyTable', 'totara_core')).'",
                        "sInfo":           "'.addslashes_js(get_string('datatable:sInfo', 'totara_core')).'",
                        "sInfoEmpty":      "'.addslashes_js(get_string('datatable:sInfoEmpty', 'totara_core')).'",
                        "sInfoFiltered":   "'.addslashes_js(get_string('datatable:sInfoFiltered', 'totara_core')).'",
                        "sInfoPostFix":    "'.addslashes_js(get_string('datatable:sInfoPostFix', 'totara_core')).'",
                        "sInfoThousands":  "'.addslashes_js(get_string('datatable:sInfoThousands', 'totara_core')).'",
                        "sLengthMenu":     "'.addslashes_js(get_string('datatable:sLengthMenu', 'totara_core')).'",
                        "sLoadingRecords": "'.addslashes_js(get_string('datatable:sLoadingRecords', 'totara_core')).'",
                        "sProcessing":     "'.addslashes_js(get_string('datatable:sProcessing', 'totara_core')).'",
                        "sSearch":         "'.addslashes_js(get_string('datatable:sSearch', 'totara_core')).'",
                        "sZeroRecords":    "'.addslashes_js(get_string('datatable:sZeroRecords', 'totara_core')).'",
                        "oPaginate": {
                            "sFirst":    "'.addslashes_js(get_string('datatable:oPaginate:sFirst', 'totara_core')).'",
                            "sLast":     "'.addslashes_js(get_string('datatable:oPaginate:sLast', 'totara_core')).'",
                            "sNext":     "'.addslashes_js(get_string('datatable:oPaginate:sNext', 'totara_core')).'",
                            "sPrevious": "'.addslashes_js(get_string('datatable:oPaginate:sPrevious', 'totara_core')).'"
                        }
                    }
                } );
                oTable.fnSetFilteringDelay(500);
            });
        ');
    }
}
