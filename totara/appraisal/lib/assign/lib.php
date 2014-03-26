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
 * @subpackage appraisal
 */

global $CFG;
require_once($CFG->dirroot.'/totara/core/lib/assign/lib.php');
require_once($CFG->dirroot.'/totara/appraisal/lib.php');

class totara_assign_appraisal extends totara_assign_core {

    protected static $module = 'appraisal';

    public function store_user_assignments() {
        parent::store_user_assignments();

        $this->store_role_assignments();
    }

    /**
     * Create appraisal role assignment records
     *
     * @access private
     * @return void
     */
    private function store_role_assignments() {
        global $DB;

        // Clear any existing records (so that we don't create duplicates).
        $this->delete_role_assignments();

        // Get all roles required for this appraisal.
        $appraisal = new appraisal($this->moduleinstanceid);
        $roles = $appraisal->get_roles_involved();

        foreach ($roles as $key => $role) {
            switch ($role) {
                case appraisal::ROLE_LEARNER:
                    $select = "SELECT ua.id, ua.userid, {$role}
                                 FROM {appraisal_user_assignment} ua
                                WHERE ua.appraisalid = {$this->moduleinstanceid}";
                    break;
                case appraisal::ROLE_MANAGER:
                    $select = "SELECT ua.id, pa.managerid, {$role}
                                 FROM (SELECT * FROM {appraisal_user_assignment}
                                        WHERE appraisalid = {$this->moduleinstanceid}) ua
                                 JOIN (SELECT * FROM {pos_assignment} WHERE type = 1) pa
                                   ON ua.userid = pa.userid
                                WHERE pa.managerid IS NOT NULL";
                    break;
                case appraisal::ROLE_TEAM_LEAD:
                    $select = "SELECT ua.id, pa2.managerid, {$role}
                                 FROM (SELECT * FROM {appraisal_user_assignment}
                                        WHERE appraisalid = {$this->moduleinstanceid}) ua
                                 JOIN (SELECT * FROM {pos_assignment} WHERE type = 1) pa
                                   ON ua.userid = pa.userid
                                 JOIN (SELECT * FROM {pos_assignment} WHERE type = 1) pa2
                                   ON pa.managerid = pa2.userid
                                WHERE pa2.managerid IS NOT NULL";
                    break;
                case appraisal::ROLE_APPRAISER:
                    $select = "SELECT ua.id, pa.appraiserid, {$role}
                                 FROM (SELECT * FROM {appraisal_user_assignment}
                                        WHERE appraisalid = {$this->moduleinstanceid}) ua
                                 JOIN (SELECT * FROM {pos_assignment} WHERE type = 1) pa
                                   ON ua.userid = pa.userid
                                WHERE pa.appraiserid IS NOT NULL";
                    break;
                default:
                    // Error.
                    continue 2;
                    break;
            }
            $sql = "INSERT INTO {appraisal_role_assignment}
                        (appraisaluserassignmentid, userid, appraisalrole)
                        ({$select})";
            $DB->execute($sql);
        }
    }

    /**
     * Delete all of this appraisal's assignments
     *
     * @access public
     * @return void
     */
    public function delete_user_assignments() {
        $this->delete_role_assignments();

        parent::delete_user_assignments();
    }

    /**
     * Delete this appraisal's role assignments
     *
     * @access public
     * @return void
     */
    private function delete_role_assignments() {
        global $DB;

        $sqlstagedata =
            "DELETE FROM {appraisal_stage_data}
              WHERE appraisalroleassignmentid IN
                    (SELECT ara.id
                       FROM {appraisal_role_assignment} ara
                       JOIN {appraisal_user_assignment} aua
                         ON ara.appraisaluserassignmentid = aua.id
                      WHERE aua.appraisalid = {$this->moduleinstanceid})";
        $DB->execute($sqlstagedata);

        $sqlroleassignment =
            "DELETE FROM {appraisal_role_assignment}
              WHERE appraisaluserassignmentid IN
                    (SELECT id
                       FROM {appraisal_user_assignment}
                      WHERE appraisalid = {$this->moduleinstanceid})";
        $DB->execute($sqlroleassignment);
    }

    /**
     * Determine if the appraisal can have assignments added or removed.
     *
     * @return bool
     */
    public function is_locked() {
        return !appraisal::is_draft($this->moduleinstanceid);;
    }

    /**
     * Determines if assigned users have been stored in the user_assignement table, via store_user_assignments.
     *
     * @return bool whether or not users have been stored in the user_assignments table.
     */
    public function assignments_are_stored() {
        return ($this->moduleinstance->status != appraisal::STATUS_DRAFT);
    }

}
