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
 * @subpackage feedback360
 */

global $CFG;
require_once($CFG->dirroot.'/totara/core/lib/assign/lib.php');
require_once($CFG->dirroot.'/totara/feedback360/lib.php');

class totara_assign_feedback360 extends totara_assign_core {

    protected static $module = 'feedback360';

    /**
     * Determine if the feedback360 can have assignments added or removed.
     *
     * @return bool
     */
    public function is_locked() {
        return $this->assignments_are_stored();
    }

    /**
     * Determines if assigned users have been stored in the user_assignement table.
     *
     * @return bool whether or not users have been stored in the user_assignments table.
     */
    public function assignments_are_stored() {
        return ($this->moduleinstance->status == feedback360::STATUS_ACTIVE ||
                $this->moduleinstance->status == feedback360::STATUS_COMPLETED);
    }

    public function delete_user_assignments() {
        global $DB;

        if ($this->is_locked()) {
            print_error('error:assignmentmoduleinstancelocked', 'totara_core');
        }

        $userassignments = $DB->get_records('feedback360_user_assignment', array('feedback360id' => $this->moduleinstanceid));

        // Delete all associated resp and email assignments.
        foreach ($userassignments as $userassignment) {
            $this->delete_resp_assignments($userassignment->id);
        }

        parent::delete_user_assignments();
    }

    public function delete_resp_assignments($uaid) {
        global $DB;

        // Get all the associated resp assignments.
        $resp_params = array('feedback360userassignmentid' => $uaid);
        $resp_assignments = $DB->get_records('feedback360_resp_assignment', $resp_params);

        foreach ($resp_assignments as $resp) {
            // Delete associated email assignment.
            if (!empty($resp->feedback360emailassignmentid)) {
                $email_params = array('id' => $resp->feedback360emailassignmentid);
                $DB->delete_records('feedback360_email_assignment', $email_params);
            }

            // Delete the resp assignment.
            $DB->delete_records('feedback360_resp_assignment', array('id' => $resp->id));
        }
    }

}
