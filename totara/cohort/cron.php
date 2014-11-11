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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */

function tcohort_cron() {
    global $CFG, $DB;
    require_once($CFG->dirroot . "/totara/cohort/rules/lib.php");

    // Check if we need to run hourly tasks for cleanup and membership sync.
    $trace = new text_progress_trace();
    $runhourlycron = true;
    $timenow = time();
    $hourlycron = 60 * 60; // one hour.
    $lasthourlycron = get_config('totara_cohort', 'lasthourlycron');
    if ($lasthourlycron && ($timenow - $lasthourlycron <= $hourlycron)) {
        // Not enough time has elapsed to rerun hourly cron.
        $trace->output("No need to run cohort member hourly sync - has already been run recently.");
        if (isset($CFG->debugcron) && $CFG->debugcron) {
            $trace->output("DEBUG - run cohort member syncing anyway");
        } else {
            $runhourlycron = false;
        }
    }

    if ($runhourlycron) {
        // Clean up obsolete rule collections.
        $obsoleted = $DB->get_fieldset_select('cohort_rule_collections', 'id', 'status = ?', array(COHORT_COL_STATUS_OBSOLETE));
        if (!empty($obsoleted)) {
            $trace->output(date("H:i:s", $timenow).' Cleaning up obsolete rule collections...');
            foreach ($obsoleted as $obsolete) {
                cohort_rules_delete_collection($obsolete);
            }
        }
        // Sync dynamic audience members.
        $trace->output(date("H:i:s", time()).' Syncing dynamic audience members');
        totara_cohort_check_and_update_dynamic_cohort_members(null, $trace);
    }

    $trace->output(date("H:i:s", time()).' Sending queued cohort notifications...');
    totara_cohort_send_queued_notifications();
    $trace->output(date("H:i:s", time()). ' Finished sending queued cohort notifications...');
}
