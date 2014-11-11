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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_appraisal
 */

/**
 * Local db upgrades for Totara Core
 */

require_once($CFG->dirroot.'/totara/core/db/utils.php');
require_once($CFG->dirroot.'/totara/appraisal/lib.php');

/**
 * Local database upgrade script
 *
 * @param   integer $oldversion Current (pre-upgrade) local db version timestamp
 * @return  boolean $result
 */
function xmldb_totara_appraisal_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2013080501) {

        // Define field appraisalscalevalueid to be added to appraisal_review_data.
        $table = new xmldb_table('appraisal_review_data');
        $field = new xmldb_field('appraisalscalevalueid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0,
                'appraisalquestfieldid');

        // Conditionally launch add field appraisalscalevalueid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Appraisal savepoint reached.
        upgrade_plugin_savepoint(true, 2013080501, 'totara', 'appraisal');
    }

    if ($oldversion < 2014061600) {
        require_once($CFG->dirroot.'/totara/appraisal/lib.php');
        $usercount = $DB->count_records('user', array('deleted' => 1));
        if ($usercount > 0) {
            // This could take some time and use a lot of resources.
            set_time_limit(0);
            raise_memory_limit(MEMORY_EXTRA);
            $i = 0;
            $deletedusers = $DB->get_recordset('user', array('deleted' => 1), null, 'id, username, firstname, lastname, email, idnumber, picture, mnethostid');
            $context = context_system::instance();
            $pbar = new progress_bar('fixdeleteduserappraisal', 500, true);
            $pbar->update($i, $usercount, "Fixing Appraisals for deleted users - {$i}/{$usercount}.");
            foreach ($deletedusers as $user) {
                $event = \core\event\user_deleted::create(
                    array(
                        'objectid' => $user->id,
                        'context' => $context,
                        'other' => array(
                            'username' => $user->username,
                            'email' => $user->email,
                            'idnumber' => $user->idnumber,
                            'picture' => $user->picture,
                            'mnethostid' => $user->mnethostid
                        )
                ));
                totara_appraisal_observer::user_deleted($event);
                $i++;
                $pbar->update($i, $usercount, "Fixing Appraisals for deleted users - {$i}/{$usercount}.");
            }
            $deletedusers->close();
        }
        upgrade_plugin_savepoint(true, 2014061600, 'totara', 'appraisal');
    }

    if ($oldversion < 2014062000) {
        $users = $DB->get_fieldset_select('user', 'id', 'deleted = ? ', array(1));

        $transaction = $DB->start_delegated_transaction();

        if (!empty($users)) {

            $now = time();

            // First try and complete the stage so the user can continue the appraisal.
            $sql = "SELECT ara.*, aua.activestageid, aua.appraisalid, aua.userid AS subjectid
                      FROM {appraisal_role_assignment} ara
                      JOIN {appraisal_user_assignment} aua
                        ON ara.appraisaluserassignmentid = aua.id
                      JOIN {user} u
                        ON ara.userid = u.id
                       AND u.deleted = ?";
            $roleassignments = $DB->get_records_sql($sql, array(1));

            $completionsql = "SELECT 1
                                FROM {appraisal_role_assignment} ara
                           LEFT JOIN {appraisal_stage_data} asd
                                  ON asd.appraisalroleassignmentid = ara.id
                                 AND asd.appraisalstageid = ?
                               WHERE ara.appraisaluserassignmentid = ?
                                 AND ara.userid <> 0
                                 AND asd.timecompleted IS NULL";

            $todb = new stdClass();
            $todb->timecompleted = $now;
            foreach ($roleassignments as $role) {
                $todb->appraisalroleassignmentid = $role->id;
                $todb->appraisalstageid = $role->activestageid;
                $DB->insert_record('appraisal_stage_data', $todb);

                // Check if all assigned roles have completed the appraisal.
                if (!$DB->record_exists_sql($completionsql, array($role->activestageid, $role->appraisaluserassignmentid))) {
                    $stages = $DB->get_records('appraisal_stage', array('appraisalid' => $role->appraisalid), 'timedue, id');

                    // Find the next stage.
                    $currentstage = reset($stages);
                    for ($i = 0; $i < count($stages) - 1; $i++) {
                        if ($currentstage->id == $role->activestageid) {
                            $currentstage = next($stages);
                            $nextstageid = $currentstage->id;
                            break;
                        }
                        $currentstage = next($stages);
                    }

                    // Move to the next stage or mark the appraisal as complete.
                    if (!empty($nextstageid)) {
                        $DB->set_field('appraisal_user_assignment', 'activestageid', $nextstageid,
                            array('userid' => $role->subjectid, 'appraisalid' => $role->appraisalid));
                        $nextstageid = 0;
                    } else {
                        // Mark the user as complete for this appraisal.
                        $DB->set_field('appraisal_user_assignment', 'timecompleted', $now, array('id' => $role->appraisaluserassignmentid));

                        // Check if all users are complete.
                        $unfinished = $DB->count_records('appraisal_user_assignment', array('appraisalid' => $role->appraisalid, 'timecompleted' => null));
                        if (!$unfinished) {
                            // Mark this appraisal as complete.
                            $DB->set_field('appraisal', 'status', appraisal::STATUS_COMPLETED, array('id' => $role->appraisalid));
                            $DB->set_field('appraisal', 'timefinished', $now, array('id' => $role->appraisalid));
                        }
                    }
                }
            }

            // Then flag all the role_assignments as empty. Chunk the data in case there are more than 65535 deleted users.
            $length = 1000;
            $chunked_datarows = array_chunk($users, $length);
            unset($users);
            foreach ($chunked_datarows as $key => $chunk) {
                list($insql, $inparam) = $DB->get_in_or_equal($chunk);
                $sql = "UPDATE {appraisal_role_assignment}
                       SET userid = 0
                       WHERE userid {$insql}";
                $DB->execute($sql, $inparam);
                unset($chunked_datarows[$key]);
                unset($chunk);
                unset($sql);
            }
            unset($chunked_datarows);
        }

        $transaction->allow_commit();

        upgrade_plugin_savepoint(true, 2014062000, 'totara', 'appraisal');
    }

    if ($oldversion < 2014090100) {
        $transaction = $DB->start_delegated_transaction();
        $records = $DB->get_recordset_select('appraisal_stage', ' timedue > ?', array(0), ' id ASC', 'id, timedue');
        foreach ($records as $record) {
            $timestring = date('H:i:s', $record->timedue);
            if ($timestring !== '23:59:59') {
                $datestring = date('Y-m-d', $record->timedue);
                $datestring .= " 23:59:59";
                if ($newtimestamp = totara_date_parse_from_format('Y-m-d H:i:s', $datestring)) {
                    $DB->set_field('appraisal_stage', 'timedue', $newtimestamp, array('id' => $record->id));
                }
            }
        }
        $transaction->allow_commit();
        upgrade_plugin_savepoint(true, 2014090100, 'totara', 'appraisal');
    }


    // This is the Totara 2.7 upgrade line.
    // All following versions need to be bumped up during merging from 2.6 until we have separate t2-release-27 branch!


    if ($oldversion < 2014090801) {
        $table = new xmldb_table('appraisal_user_assignment');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecompleted');

        if (!$dbman->field_exists($table, $field)) {
            $transaction = $DB->start_delegated_transaction();

            // TODO: Adding fields while in transaction is not allowed - see T-12870
            $dbman->add_field($table, $field);

            // Migrate status from appraisal status.
            $appraisals = $DB->get_records('appraisal');
            foreach ($appraisals as $appraisal) {
                $sql = "UPDATE {appraisal_user_assignment} SET status = ? WHERE appraisalid = ?";
                $params = array($appraisal->status, $appraisal->id);
                $DB->execute($sql, $params);
            }

            // Finally set the status for all completed users at once.
            $sql = "UPDATE {appraisal_user_assignment} SET status = ? WHERE timecompleted IS NOT NULL";
            $params = array(appraisal::STATUS_COMPLETED);
            $DB->execute($sql, $params);

            $transaction->allow_commit();
        }

        upgrade_plugin_savepoint(true, 2014090801, 'totara', 'appraisal');
    }

    if ($oldversion < 2014090802) {

        // Define table appraisal_role_changes to be created.
        $table = new xmldb_table('appraisal_role_changes');

        // Adding fields to table appraisal_role_changes.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userassignmentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('originaluserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('newuserid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('role', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

        // Adding keys to table appraisal_role_changes.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for appraisal_role_changes.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2014090802, 'totara', 'appraisal');
    }

    if ($oldversion < 2014090803) {
        // Adding a timecreated field to appraisals role assignments.
        $table = new xmldb_table('appraisal_role_assignment');
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, 0);

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2014090803, 'totara', 'appraisal');
    }

    return true;
}
