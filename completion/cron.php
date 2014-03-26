<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Cron job for reviewing and aggregating course completion criteria
 *
 * @package core_completion
 * @category completion
 * @copyright 2009 Catalyst IT Ltd
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/completionlib.php');

/**
 * Update all criteria completions that require the cron
 */
function completion_cron() {

    completion_cron_criteria();

    completion_cron_completions();
}

/**
 * Run installed criteria's data aggregation methods
 *
 * Loop through each installed criteria and run the
 * cron() method if it exists
 *
 * @return void
 */
function completion_cron_criteria() {

    // Process each criteria type
    global $CFG, $COMPLETION_CRITERIA_TYPES;

    foreach ($COMPLETION_CRITERIA_TYPES as $type) {

        $object = 'completion_criteria_'.$type;
        require_once $CFG->dirroot.'/completion/criteria/'.$object.'.php';

        $class = new $object();

        // Run the criteria type's cron method, if it has one
        if (method_exists($class, 'cron')) {

            if (debugging()) {
                mtrace('Running '.$object.'->cron()');
            }
            $class->cron();
        }
    }
}

/**
 * Aggregate each user's criteria completions
 */
function completion_cron_completions() {
    global $DB;

    if (debugging()) {
        mtrace('Aggregating completions');
    }

    // Wait one sec to prevent timestamp overlap with "reaggregate"
    // being set in completion_cron_criteria()
    sleep(1);

    // Save time started
    $timestarted = time();

    // Grab all criteria and their associated criteria completions
    $sql = '
        SELECT
            crc.*
        FROM
            {course_completions} crc
        INNER JOIN
            {course} c
         ON crc.course = c.id
        WHERE
            c.enablecompletion = 1
        AND crc.timecompleted IS NULL
        AND crc.reaggregate > 0
        AND crc.reaggregate < :timestarted
    ';

    $rs = $DB->get_recordset_sql($sql, array('timestarted' => $timestarted));

    // Check if result is not empty
    if ($rs->valid()) {

        // Grab records for current user/course
        foreach ($rs as $record) {
            // Load completion object (without hitting db again)
            $completion = new completion_completion((array) $record, false);

            // Recalculate course's criteria
            completion_handle_criteria_recalc($completion->course, $completion->userid);

            // Aggregate the criteria and complete if necessary
            $completion->aggregate();
        }
    }

    $rs->close();

    // Mark all users as aggregated
    $sql = "
        UPDATE
            {course_completions}
        SET
            reaggregate = 0
        WHERE
            reaggregate < :timestarted
        AND reaggregate > 0
    ";

    $DB->execute($sql, array('timestarted' => $timestarted));
}
