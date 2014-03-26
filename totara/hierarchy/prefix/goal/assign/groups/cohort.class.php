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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */

/**
 * base org grouping assignment class
 * will mostly be extended by child classes in each totara module, but is generic and functional
 * enough to still be useful for simple assignment cases
 */
global $CFG;
require_once($CFG->dirroot.'/totara/core/lib/assign/lib.php');
require_once($CFG->dirroot.'/totara/core/lib/assign/groups/cohort.class.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/goal/lib.php');

class totara_assign_goal_grouptype_cohort extends totara_assign_core_grouptype_cohort {

    // Code to accept data from generate_item_selector().
    public function handle_item_selector($data) {
        global $DB, $USER;

        $goal = new goal();

        // Check target table exists!
        $dbman = $DB->get_manager();
        $table = new xmldb_table($this->tablename);
        if (!$dbman->table_exists($table)) {
            print_error('error:assigntablenotexist', 'totara_core', $this->tablename);
        }

        $modulekeyfield = "{$this->module}id";
        $grouptypekeyfield = "{$this->grouptype}id";

        // Add only the new records.
        $existingassignments = $DB->get_fieldset_select($this->tablename, $grouptypekeyfield, "{$modulekeyfield} = ?",
                array($modulekeyfield => $this->moduleinstanceid));
        foreach ($data['listofvalues'] as $assignedgroupid) {
            if (!in_array($assignedgroupid, $existingassignments)) {
                // Create the assignment.
                $todb = new stdClass();
                $todb->$modulekeyfield = $this->moduleinstanceid;
                $todb->$grouptypekeyfield = $assignedgroupid;
                $todb->timemodified = time();
                $todb->usermodified = $USER->id;
                $todb->id = $DB->insert_record($this->tablename, $todb);

                // Create all the user assignments to go along with it.
                $goal->create_user_assignments(GOAL_ASSIGNMENT_AUDIENCE, $todb);
            }
        }
        return true;
    }

    // Code to validate data from generate_item_selector().
    public function validate_item_selector() {
        return true;
    }
}
