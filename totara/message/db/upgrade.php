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

require_once($CFG->dirroot.'/totara/core/db/utils.php');

/**
 * Upgrade code for the oauth plugin
 */

function xmldb_totara_message_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    $result = true;

    if ($oldversion < 2012012701) {

        // change user preferences for both tasks and alerts
        $types = array('alert' => 'totara_msg_send_alrt_emails', 'task' => 'totara_msg_send_task_emails');
        foreach ($types as $type => $oldsetting) {

            // find old settings
            $prefs = $DB->get_records('user_preferences', array('name' => $oldsetting));
            foreach ($prefs as $pref) {
                $newpref = "totara_{$type}";
                if ($pref->value == 1) {
                    $newpref .= ',email';
                }

                // set new ones
                set_user_preference("message_provider_totara_message_{$type}_loggedin", $newpref, $pref->userid);
                set_user_preference("message_provider_totara_message_{$type}_loggedoff", $newpref, $pref->userid);

                // remove the old setting
                unset_user_preference($oldsetting, $pref->userid);
            }
        }
        echo $OUTPUT->notification('Update user notification preferences', 'notifysuccess');
    }

    if ($oldversion < 2012012702) {
        //fix old 1.9 totara_msg tables

        // drop the existing message index and remove null constraint on roleid
        // needed as we reuse this table in 2.2 but roleid no longer exists (will be dropped later)
        $table = new xmldb_table('message_metadata');
        $index = new xmldb_index('role');
        $index->setUnique(true);
        $index->setFields(array('roleid', 'messageid'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        $field = new xmldb_field('roleid', XMLDB_TYPE_INTEGER, 10, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }
        echo $OUTPUT->notification('Fix message_metadata role properties', 'notifysuccess');

        // Recreate messages in new tables
        $table = new xmldb_table('message20');
        if ($dbman->table_exists($table)) {
            require_once($CFG->dirroot.'/totara/message/messagelib.php');
            //first, simple copy of contents of message_read_20 to message_read
            $msgs = $DB->get_records('message_read20');
            foreach ($msgs as $msg) {
                unset($msg->id);
                //fix contexturl to change /local/ to /totara/ for totara modules only
                $msg->contexturl = str_replace('/local/plan','/totara/plan', $msg->contexturl);
                $msg->contexturl = str_replace('/local/program','/totara/program', $msg->contexturl);
                //1.1 bug, many messages are set as format_plain when they should be format_html
                $msg->fullmessageformat = FORMAT_HTML;
                $DB->insert_record('message_read', $msg);
            }
            //now the unread messages
            $msgs = $DB->get_records_sql('SELECT
                                    m.id,
                                    m.useridfrom,
                                    m.useridto,
                                    m.subject,
                                    m.fullmessage,
                                    m.timecreated,
                                    m.alert,
                                    d.roleid,
                                    d.msgstatus,
                                    d.msgtype,
                                    d.urgency,
                                    d.icon,
                                    d.onaccept,
                                    d.onreject,
                                    d.oninfo,
                                    m.contexturl,
                                    m.contexturlname,
                                    p.name as processor
                                    FROM {message20} m LEFT JOIN {message_metadata} d ON d.messageid = m.id
                                    LEFT JOIN {message_processors20} p on d.processorid = p.id
                                    ', array());

            // truncate the old metadata
            $DB->delete_records('message_metadata', null);

            //disable emails during the port
            $orig_emailstatus = $DB->get_field('message_processors', 'enabled', array('name' => 'email'));
            if ($orig_emailstatus == 1) {
                $DB->set_field('message_processors', 'enabled', '0', array('name' => 'email'));
            }

            $count = count($msgs);
            if ($count > 0) {
                $pbar = new progress_bar('migratetotaramessages', 500, true);
                $i = 0;
                // now recreate the messages
                foreach ($msgs as $msg) {
                    $i++;
                    /* SCANMSG: need to check other messages for local/ in the contexturl */
                    //fix contexturl to change /local/ to /totara/ for totara modules only
                    $msg->contexturl = str_replace('/local/plan','/totara/plan', $msg->contexturl);
                    $msg->contexturl = str_replace('/local/program','/totara/program', $msg->contexturl);
                    if (!$userto = $DB->get_record('user', array('id' => $msg->useridto))) {
                        // don't recreate if we don't know who it's to
                        continue;
                    }
                    $msg->userto = $userto;
                    if (!$userfrom = $DB->get_record('user', array('id' => $msg->useridfrom))) {
                        // don't recreate if we don't know who it's from
                        continue;
                    }
                    $msg->userfrom = $userfrom;
                    //1.1 bug, many messages are set as format_plain when they should be format_html
                    $msg->fullmessageformat = FORMAT_HTML;
                    !empty($msg->onaccept) && $msg->onaccept = unserialize($msg->onaccept);
                    !empty($msg->onreject) && $msg->onreject = unserialize($msg->onreject);
                    !empty($msg->oninfo) && $msg->oninfo = unserialize($msg->oninfo);
                    if ($msg->processor == 'totara_task') {
                        tm_task_send($msg);
                    } else {
                        tm_alert_send($msg);
                    }
                    // Unset the user records to save memory, we don't need them
                    unset($msg->userto);
                    unset($msg->userfrom);
                    upgrade_set_timeout(60*5); // set up timeout, may also abort execution
                    $pbar->update($i, $count, "Migrating totara messages - message $i/$count.");
                }
                $pbar->update($count, $count, "Migrated totara messages - done!");
            }

            //re-enable emails if they were originally turned on
            if ($orig_emailstatus == 1) {
                $DB->set_field('message_processors', 'enabled', '1', array('name' => 'email'));
            }
            echo $OUTPUT->notification('totara/message: Recreated existing alerts and tasks ('.count($msgs).')', 'notifysuccess');
        }

        // drop tables
        $tables = array('message20', 'message_read20', 'message_working20', 'message_processors20', 'message_providers20');
        foreach ($tables as $tablename) {
            $table = new xmldb_table($tablename);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }
        echo $OUTPUT->notification('Dropping obsolete totara_msg tables', 'notifysuccess');

        // remove the roleid
        $table = new xmldb_table('message_metadata');
        $field = new xmldb_field('roleid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        echo $OUTPUT->notification('Removing message_metadata roleid field', 'notifysuccess');
    }

    if ($oldversion < 2012092500) {
        //ensure oninfo field exists T-9963
        $table = new xmldb_table('message_metadata');
        $field = new xmldb_field('oninfo', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'onreject');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2012120400) {
        // add field to track metadata for read messages
        $table = new xmldb_table('message_metadata');
        $field = new xmldb_field('messagereadid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'onreject');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // add an index to the new field
        $table = new xmldb_table('message_metadata');
        $index = new xmldb_index('messageread');
        $index->setUnique(false);
        $index->setFields(array('messagereadid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // allow messageid to be null

        // need to drop the index first
        $table = new xmldb_table('message_metadata');
        $index = new xmldb_index('message');
        $index->setUnique(true);
        $index->setFields(array('messageid'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        // now allow null messageid
        $table = new xmldb_table('message_metadata');
        $field = new xmldb_field('messageid', XMLDB_TYPE_INTEGER, 10, null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }
        // readd a non-unique index
        $table = new xmldb_table('message_metadata');
        $index = new xmldb_index('message', XMLDB_INDEX_NOTUNIQUE, array('messageid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        totara_upgrade_mod_savepoint(true, 2012120400, 'totara_message');
    }

    if ($oldversion < 2013020700) {
        $reportlist = $DB->get_fieldset_select('report_builder', 'id', 'source = ?', array('totaramessages'));
        if ($reportlist) {
            list($reportssql, $reportsparam) = $DB->get_in_or_equal($reportlist);
        }
        if (!empty($reportssql)) {
            // Remove status and statementurl fields from reports
            $params = array_merge(array('message_values', 'statementurl', 'status_text'), $reportsparam);
            $DB->delete_records_select('report_builder_columns', "type = ? AND (value = ? OR value = ?) AND reportid ".$reportssql, $params);
        }
        totara_upgrade_mod_savepoint(true, 2013020700, 'totara_message');
    }

    return $result;
}
