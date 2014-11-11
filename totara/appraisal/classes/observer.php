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
 * @package totara
 * @subpackage appraisal
 */

defined('MOODLE_INTERNAL') || die();

class totara_appraisal_observer {

    /**
     * Event that is triggered when a user is deleted.
     *
     * Checks for any appraisals roles the user may have had and archives them.
     *
     * @param \core\event\user_deleted $event
     *
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/appraisal/lib.php');

        $userid = $event->objectid;
        $transaction = $DB->start_delegated_transaction();

        // Delete all user_assignments and associated data for the user.
        appraisal::delete_learner_assignments($userid);

        // Unassign all role_assignments for the user, but retain associated data.
        appraisal::unassign_user_roles($userid);

        $transaction->allow_commit();
    }

    /**
     * Activation message handler
     * If message is not immediate - add scheduled event
     * Also process stage_due as technically it's not an event but scheduled action
     *
     * @param \totara_appraisal\event\appraisal_activation $event
     */
    public static function appraisal_activation(\totara_appraisal\event\appraisal_activation $event) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/totara/appraisal/lib.php'); // We should move all the classes into self loading ones.

        $time = $event->other['time'];
        $appraisalid = $event->objectid;

        $sql = "SELECT id FROM {appraisal_event} WHERE triggered = 0 AND event IN (?, ?) AND appraisalid = ?";
        $params = array(appraisal_message::EVENT_APPRAISAL_ACTIVATION, appraisal_message::EVENT_STAGE_DUE, $appraisalid);
        $events = $DB->get_records_sql($sql, $params);
        foreach ($events as $id => $eventdata) {
            $eventmessage = new appraisal_message($id);
            self::process_event($eventmessage, $time);
        }
    }

    /**
     * Stage complete message handler
     *
     * @param \totara_appraisal\event\appraisal_stage_completion $event
     */
    public static function appraisal_stage_completion(\totara_appraisal\event\appraisal_stage_completion $event) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/totara/appraisal/lib.php'); // We should move all the classes into self loading ones.

        $time = $event->other['time'];
        $stageid = $event->other['stageid'];
        $sql = "SELECT id FROM {appraisal_event} WHERE triggered = 0 AND event = ? AND appraisalstageid = ?";
        $params = array(appraisal_message::EVENT_STAGE_COMPLETE, $stageid);
        $events = $DB->get_records_sql($sql, $params);
        foreach ($events as $id => $eventdata) {
            self::process_event(new appraisal_message($id), $time);
        }
    }

    /**
     * Send or schedule messages according time and settings
     *
     * @param appraisal_message $event
     * @param int $time
     */
    protected static function process_event(appraisal_message $event, $time) {
        if ($event->is_immediate()) {
            $event->send();
        } else {
            $event->schedule($event->get_schedule_from($time));
            $event->save();
        }
    }


    /**
     * Get's all scheduled untriggered messages and send's them
     *
     * @param int $time current time
     */
    public static function send_scheduled($time) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/totara/appraisal/lib.php'); // We should move all the classes into self loading ones.

        $sql = "SELECT ae.id
                FROM {appraisal_event} ae JOIN {appraisal} a ON (ae.appraisalid = a.id)
                WHERE a.status = ? AND timescheduled > 0 AND triggered = 0";
        $events = $DB->get_records_sql($sql, array(appraisal::STATUS_ACTIVE));
        foreach ($events as $id => $eventdata) {
            $event = new appraisal_message($id);
            if ($event->is_time($time)) {
                $event->send();
            }
        }
    }

}
