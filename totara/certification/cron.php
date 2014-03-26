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
 * @author Jon Sharp <jonathans@catalyst-eu.net>
 * @package totara
 * @subpackage certification
 */

/**
 * Cron job for managing certification schedules
 */

require_once($CFG->dirroot.'/totara/certification/lib.php');


/**
 * Update certifications
 *
 * @return  void
 */
function certification_cron() {
    $result = false;

    if (!totara_feature_disabled('certifications')) {
        // Run the tasks that should be run hourly.
        $result = certification_hourly_cron();
    }

    return $result;
}

/**
 * Cron tasks that should be run more regularly
 *
 * @return bool Success
 */
function certification_hourly_cron() {
    global $CFG;

    $timenow  = time();
    $hourlycron = 3600; // One hour.

    $lasthourlycron = get_config(null, 'totara_certification_lasthourlycron');

    if ($lasthourlycron && ($timenow - $lasthourlycron <= $hourlycron)) {
        // Not enough time has elapsed to rerun hourly cron.
        mtrace("No need to run certification hourly cron - has already been run recently.");
        if (isset($CFG->debugcron) && $CFG->debugcron) {
            mtrace("DEBUG - running anyway");
        } else {
            return true;
        }
    }

    if (!set_config('totara_certification_lasthourlycron', $timenow)) {
        mtrace("Error: could not update lasthourlycron timestamp for certification module.");
    }

    mtrace("Doing recertify_window_opens_stage");
    $processed = recertify_window_opens_stage();
    mtrace("... ".$processed.' processed');

    mtrace("Doing recertify_window_abouttoclose_stage");
    $processed = recertify_window_abouttoclose_stage();
    mtrace("... ".$processed.' processed');

    mtrace("Doing recertify_expires_stage");
    $processed = recertify_expires_stage();
    mtrace("... ".$processed.' processed');

     mtrace("done");

    return true;
}

