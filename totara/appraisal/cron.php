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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_appraisal
 */

require_once($CFG->dirroot.'/totara/appraisal/lib.php');

/**
 * Run cron for appraisal
 */
function appraisal_cron($time = 0) {
    global $DB;

    // Execute the cron if Appraisals are not disabled.
    if (!totara_feature_disabled('appraisals')) {

        // Update learner assignments for active appraisals.
        $appraisals = $DB->get_records('appraisal', array('status' => appraisal::STATUS_ACTIVE));
        foreach ($appraisals as $app) {
            $appraisal = new appraisal($app->id);
            $appraisal->check_assignment_changes();
        }

        // Send scheduled appraisals messages.
        if (!$time) {
            $time = time();
        }
        totara_appraisal_observer::send_scheduled($time);
    }
}
