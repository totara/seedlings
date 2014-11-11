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
 * @author Piers Harding <piers@catalyst.net.nz>
 * @package totara
 * @subpackage message
 */

require_once($CFG->dirroot . '/totara/message/lib.php');

// how many locked crons to ignore before starting to print errors
define('TOTARA_MSG_CRON_WAIT_NUM', 10);
// how often to print errors (1 for every time, 2 every other time, etc)
define('TOTARA_MSG_CRON_ERROR_FREQ', 10);

// age for expiring undismissed alerts - days
define('TOTARA_MSG_CRON_DISMISS_ALERTS', 30);

// age for expiring undismissed tasks - days
define('TOTARA_MSG_CRON_DISMISS_TASKS', 30);

// age for purging messages - days
define('TOTARA_MSG_CRON_PURGE', 1);


/**
 * Run the cron functions required by messages
 *
 * @return boolean True if completes successfully, false otherwise
 */
function message_cron() {
    global $CFG, $DB;

    // dismiss old alerts
    $time = time() - (TOTARA_MSG_CRON_DISMISS_ALERTS * (24 * 60 * 60));
    $msgs = tm_messages_get_by_time('totara_alert', $time);
    $deleted = array();
    foreach ($msgs as $msg) {
        tm_message_dismiss($msg->id);
        //store message ids for bulk delete
        if (!in_array($msg->id, $deleted)) {
            $deleted[] = $msg->id;
        }
    }

    // dismiss old taskes
    $time = time() - (TOTARA_MSG_CRON_DISMISS_TASKS * (24 * 60 * 60));
    $msgs = tm_messages_get_by_time('totara_task', $time);
    foreach ($msgs as $msg) {
        tm_message_dismiss($msg->id);
        //store message ids for bulk delete
        if (!in_array($msg->id, $deleted)) {
            $deleted[] = $msg->id;
        }
    }

    //delete the message records
    $DB->delete_records_list('message', 'id', $deleted);
    // tidy up orphaned metadata records - shouldn't be any - but odd things could happen with core messages cron
    $sql = "SELECT mm.id
            FROM {message_metadata} mm
            LEFT JOIN {message} m ON mm.messageid = m.id
            LEFT JOIN {message_read} mr ON mm.messagereadid = mr.id
            WHERE m.id IS NULL AND mr.id IS NULL";
    $allidstodelete = $DB->get_fieldset_sql($sql);

    if (!empty($allidstodelete)) {
        // We may have really large numbers so split it up into smaller batches.
        $batchidstodelete = array_chunk($allidstodelete, 25000);

        foreach ($batchidstodelete as $idstodelete) {
            list($insql, $params) = $DB->get_in_or_equal($idstodelete);
            $sql = "DELETE
                    FROM {message_metadata}
                    WHERE id {$insql}";
            $DB->execute($sql, $params);
        }
    }

    return true;
}


/**
 * get message ids by time
 *
 * @param string $type - message type
 * @param string $time_created - timecreated before
 * @return array of messages
 */
function tm_messages_get_by_time($type, $time_created) {
        global $USER, $CFG, $DB;

        // select only particular type
        $processor = $DB->get_record('message_processors', array('name' => $type));
        if (empty($processor)) {
            return false;
        }

        // hunt for messages
        $msgs = $DB->get_records_sql("SELECT m.id
                                        FROM ({message} m INNER JOIN  {message_working} w ON m.id = w.unreadmessageid)
                                        WHERE w.processorid = ? AND m.timecreated < ?", array($processor->id, $time_created));
        return $msgs;
}
