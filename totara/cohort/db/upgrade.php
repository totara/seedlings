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

require_once($CFG->dirroot . '/totara/core/db/utils.php');

/**
 * DB upgrades for Totara dynamic cohorts
 */

function xmldb_totara_cohort_upgrade($oldversion) {

    global $CFG, $DB, $OUTPUT, $USER, $PAGE;

    require_once($CFG->dirroot . '/totara/cohort/rules/lib.php');
    require_once($CFG->dirroot . '/totara/cohort/lib.php');

    $dbman = $DB->get_manager();

    // Totara 2.2+ upgrade

    if ($oldversion < 2012060500) {
        // Remove old cron stuff
        $DB->delete_records('config', array('name' => 'local_cohort_cron'));
        $DB->delete_records('config', array('name' => 'local_cohort_lastcron'));

        // Define field modifierid to be added to cohort
        $table = new xmldb_table('cohort');
        $field = new xmldb_field('modifierid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, time(), 'timemodified');

        // Launch add field modifierid
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field visibility to be added to cohort
        $table = new xmldb_table('cohort');
        $field = new xmldb_field('visibility');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'cohorttype');

        // Launch add field visibility
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field alertmembers to be added to cohort
        $table = new xmldb_table('cohort');
        $field = new xmldb_field('alertmembers');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'visibility');

        // Launch add field alertmembers
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field startdate to be added to cohort
        $table = new xmldb_table('cohort');
        $field = new xmldb_field('startdate');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'alertmembers');

        // Launch add field startdate
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field enddate to be added to cohort
        $table = new xmldb_table('cohort');
        $field = new xmldb_field('enddate');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'startdate');

        // Launch add field enddate
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field active to be added to cohort
        $table = new xmldb_table('cohort');
        $field = new xmldb_field('active');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'enddate');

        // Launch add field active
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field rulesetoperator to be added to cohort
        $table = new xmldb_table('cohort');
        $field = new xmldb_field('rulesetoperator');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'active');

        // Launch add field rulesetoperator
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field calculationstatus to be added to cohort
        $table = new xmldb_table('cohort');
        $field = new xmldb_field('calculationstatus');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'active');

        // Launch add field calculationstatus
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field activecollectionid to be added to cohort
        $table = new xmldb_table('cohort');
        $field = new xmldb_field('activecollectionid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'calculationstatus');

        // Launch add field activecollectionid
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field draftcollectionid to be added to cohort
        $table = new xmldb_table('cohort');
        $field = new xmldb_field('draftcollectionid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'activecollectionid');

        // Launch add field draftcollectionid
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table cohort_rulesets to be created
        $table = new xmldb_table('cohort_rulesets');

        // Adding fields to table cohort_rulesets
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('rulecollectionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('operator', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('modifierid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);

        // Adding keys to table cohort_rulesets
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Launch create table for cohort_rulesets
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table cohort_rules to be created
        $table = new xmldb_table('cohort_rules');

        // Adding fields to table cohort_rules
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('rulesetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('ruletype', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('modifierid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);

        // Adding keys to table cohort_rules
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('rulesetid', XMLDB_KEY_FOREIGN, array('rulesetid'), 'ruleset', array('id'));

        // Launch create table for cohort_rules
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table cohort_rule_params to be created
        $table = new xmldb_table('cohort_rule_params');

        // Adding fields to table cohort_rule_params
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('ruleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('value', XMLDB_TYPE_CHAR, '255', null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('modifierid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);

        // Adding keys to table cohort_rule_params
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table cohort_rule_params
        $table->add_index('ruleid', XMLDB_INDEX_NOTUNIQUE, array('ruleid'));

        // Launch create table for cohort_rule_params
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table cohort_rule_collections to be created
        $table = new xmldb_table('cohort_rule_collections');

        // Adding fields to table cohort_rule_collections
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('rulesetoperator', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('modifierid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);

        // Adding keys to table cohort_rule_collections
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Launch create table for cohort_rule_collections
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table cohort_associations to be dropped
        $table = new xmldb_table('cohort_associations');
        // Launch delete table for cohort_associations
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // Convert old-style dynamic cohorts criteria to new-style dynamic cohort rules
        $transaction = $DB->start_delegated_transaction();

        try {
            // Locate the dynamic cohorts
            $cohorts = $DB->get_records('cohort', array('cohorttype' => cohort::TYPE_DYNAMIC), 'id', 'id, name, rulesetoperator');

            foreach ($cohorts as $cohort) {

                // Create a rule collection for each existing dynamic cohort
                $todb = new stdClass();
                $todb->cohortid = $cohort->id;
                $todb->rulesetoperator = $cohort->rulesetoperator;
                $todb->status = COHORT_COL_STATUS_ACTIVE;
                $todb->timecreated = $todb->timemodified = time();
                $todb->modifierid = $USER->id;
                $collectionid = $DB->insert_record('cohort_rule_collections', $todb);

                $todb2 = new stdClass();
                $todb2->id = $cohort->id;
                $todb2->activecollectionid = $collectionid;
                $DB->update_record('cohort', $todb2);

                // Locate its criteria
                $criteria = $DB->get_records('cohort_criteria', array('cohortid' => $cohort->id));

                foreach ($criteria as $criterion) {

                    // Make sure the criteria are valid
                    if (
                        empty($criterion->profilefield)
                        && empty($criterion->positionid)
                        && empty($criterion->organisationid)
                    ) {
                        continue;
                    }

                    // Create a ruleset for them.
                    $todb = new stdClass();
                    $todb->rulecollectionid = $collectionid;
                    $todb->operator = COHORT_RULES_OP_AND;
                    $todb->sortorder = $DB->get_field('cohort_rulesets',
                        '(CASE WHEN MAX(sortorder) IS NULL THEN 0 ELSE MAX(sortorder) END) + 1',
                        array('rulecollectionid' => $collectionid));
                    $todb->name = get_string('rulesetname', 'totara_cohort', $todb->sortorder);
                    $todb->timecreated = $todb->timemodified = time();
                    $todb->modifierid = $USER->id;

                    $rulesetid = $DB->insert_record('cohort_rulesets', $todb);

                    // Create a user field rule
                    if (!empty($criterion->profilefield) && !empty($criterion->profilefieldvalues)) {
                        if (substr($criterion->profilefield, 0, 11) == 'customfield') {
                            // Find out what type of field this is
                            $fieldid = (int)substr($criterion->profilefield, 11);
                            $fieldtype = $DB->get_field('user_info_field', 'datatype', array('id' => $fieldid));
                            //textarea criteria types no longer supported in 2.2, warn the admin
                            if ($fieldtype == 'textarea') {
                                $warning = get_string('error:badruleonupgrade', 'totara_cohort', $cohort->name);
                                echo $OUTPUT->container($warning, 'notifynotice');
                                //continue on to next rule
                                continue;
                            }
                            // Create a custom field rule if a 2.2-style rule definition exists
                            if ($ruledef = cohort_rules_get_rule_definition('usercustomfields', $criterion->profilefield . '_0')) {
                                $rulesql = $ruledef->sqlhandler;

                                switch ($fieldtype) {
                                    case 'datetime':
                                        $dates = explode(',', $criterion->profilefieldvalues);
                                        // Create 'between' dates by adding 2 rules: before and after
                                        foreach ($dates as $d) {
                                            // before
                                            $rulesql->date = $d;
                                            $rulesql->operator = COHORT_RULE_DATE_OP_BEFORE_FIXED_DATE;
                                            $ruleid = cohort_rule_create_rule($rulesetid, 'user', $criterion->profilefield);
                                            $rulesql->write($ruleid);
                                            // after
                                            $rulesql->date = $d;
                                            $rulesql->operator = COHORT_RULE_DATE_OP_AFTER_FIXED_DATE;
                                            $ruleid = cohort_rule_create_rule($rulesetid, 'user', $criterion->profilefield);
                                            $rulesql->write($ruleid);
                                        }
                                        break;
                                    case 'checkbox':
                                    case 'menu':
                                    case 'text':
                                        $rulesql->equal = 1;
                                        $rulesql->listofvalues = explode(',', $criterion->profilefieldvalues);
                                        $ruleid = cohort_rule_create_rule($rulesetid, 'user', $criterion->profilefield);
                                        $rulesql->write($ruleid);
                                        break;
                                    default:
                                        mtrace('ERROR: Could not convert old dynamic cohort criterion on unknown custom field type "'.$fieldtype.'".');
                                }
                            }
                        } else {
                            // Create a non-custom field rule
                            $ruledef = cohort_rules_get_rule_definition('user', $criterion->profilefield);
                            $rulesql = $ruledef->sqlhandler;
                            $rulesql->equal = 1;
                            $rulesql->listofvalues = explode(',', $criterion->profilefieldvalues);

                            $ruleid = cohort_rule_create_rule($rulesetid, 'user', $criterion->profilefield);
                            $rulesql->write($ruleid);
                        }
                    }

                    // Create a position field rule
                    if (!empty($criterion->positionid)) {
                        $posrule = cohort_rules_get_rule_definition('pos', 'id');
                        $possql = $posrule->sqlhandler;
                        $possql->equal = 1;
                        $possql->listofvalues = array($criterion->positionid);
                        $possql->includechildren = $criterion->positionincludechildren;

                        $posruleid = cohort_rule_create_rule($rulesetid, 'pos', 'id');
                        $possql->write($posruleid);
                    }

                    // Create an organisation field rule
                    if (!empty($criterion->organisationid)) {
                        $orgrule = cohort_rules_get_rule_definition('org', 'id');
                        $orgsql = $orgrule->sqlhandler;
                        $orgsql->equal = 1;
                        $orgsql->listofvalues = array($criterion->organisationid);
                        $orgsql->includechildren = $criterion->orgincludechildren;

                        $orgruleid = cohort_rule_create_rule($rulesetid, 'org', 'id');
                        $orgsql->write($orgruleid);
                    }
                }  // criteria foreach

                // Create a draft rule collection for each dynamic cohort
                $draftid = cohort_rules_clone_collection($collectionid, COHORT_COL_STATUS_DRAFT_UNCHANGED);
                if ($draftid) {
                    $todb = new stdClass();
                    $todb->id = $cohort->id;
                    $todb->draftcollectionid = $draftid;
                    $DB->update_record('cohort', $todb);
                }

            }  // cohort foreach

            $transaction->allow_commit();

        } catch (Exception $e) {
            $transaction->rollback($e); // rethrows exception
        }

        // Define field rulesetoperator to be dropped from cohort
        // Drop the rulesetoperator column from mdl_cohort (because now it's in the rule collection)
        $table = new xmldb_table('cohort');
        $field = new xmldb_field('rulesetoperator');

        // Launch drop field rulesetoperator
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define table cohort_criteria to be dropped
        $table = new xmldb_table('cohort_criteria');

        // Launch drop table for cohort_criteria
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        set_config('cohort_lastautoidnumber', 0);
        set_config('cohort_autoidformat', 'AUD%04d');

        upgrade_plugin_savepoint(true, 2012060500, 'totara', 'cohort');
    }

    if ($oldversion < 2012061800) {
        /// Define table cohort_msg_queue to be created
        $table = new xmldb_table('cohort_msg_queue');

        /// Adding fields to table cohort_msg_queue
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('action', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('processed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);
        $table->add_field('modifierid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);

        /// Adding keys to table cohort_msg_queue
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        /// Adding indexes to table cohort_msg_queue
        $table->add_index('cohortidaction', XMLDB_INDEX_NOTUNIQUE, array('cohortid', 'action'));

        // Launch create table for cohort_msg_queue
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2012061800, 'totara', 'cohort');
    }

    if ($oldversion < 2012072600) {
        // Ensure auto idnumbers for cohorts are set up
        if (!isset($CFG->cohort_lastautoidnumber)) {
            set_config('cohort_lastautoidnumber', 0);
        }
        if (!isset($CFG->cohort_autoidformat)) {
            set_config('cohort_autoidformat', 'AUD%04d');
        }
        upgrade_plugin_savepoint(true, 2012072600, 'totara', 'cohort');
    }

    if ($oldversion < 2012080202) {
        // Add cohort alert global config
        if (get_config('cohort', 'alertoptions') === false) {
            set_config('alertoptions', implode(',', array_keys($COHORT_ALERT)), 'cohort');
        }
        upgrade_plugin_savepoint(true, 2012080202, 'totara', 'cohort');
    }

    if ($oldversion < 2012082100) {
        // Migrate all old user custom field rules
        $sql = "UPDATE {cohort_rules}
            SET name = " . $DB->sql_concat('name', "'_0'") . ", ruletype = 'usercustomfields'
            WHERE ruletype = 'user'
            AND " . $DB->sql_like('name', '?');
        $DB->execute($sql, array('customfield%'));

        upgrade_plugin_savepoint(true, 2012082100, 'totara', 'cohort');
    }

    if ($oldversion < 2012102300) {
        //Cohort plan creation history table
        $table = new xmldb_table('cohort_plan_history');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('templateid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('usercreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);
        $table->add_field('planstatus', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('affectedusers', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null);
        $table->add_field('manual', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', null);
        $table->add_field('auto', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', null);
        $table->add_field('completed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('cohortid', XMLDB_KEY_FOREIGN, array('cohortid'), 'cohort', array('id'));
        $table->add_key('templateid', XMLDB_KEY_FOREIGN, array('templateid'), 'db_template', array('id'));

        $table->setComment('A table to store the history of plans created for cohorts.');

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2012102300, 'totara', 'cohort');
    }

    if ($oldversion < 2013100100) {

        // Define field broken to be added to cohort
        $table = new xmldb_table('cohort');
        $field = new xmldb_field('broken');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'draftcollectionid');

        // Launch add field broken
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2013100100, 'totara', 'cohort');
    }

    if ($oldversion < 2013100300) {
        // Define table cohort_visibility to be created.
        $table = new xmldb_table('cohort_visibility');

        // Adding fields to table cohort_visibility.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('instancetype', XMLDB_TYPE_INTEGER, '4', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table cohort_visibility.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('cohortid', XMLDB_KEY_FOREIGN, array('cohortid'), 'cohort', array('id'));
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', array('id'));

        // Adding indexes to table cohort_visibility.
        $table->add_index('uix_co_inst_type_val', XMLDB_INDEX_UNIQUE, array('cohortid', 'instanceid', 'instancetype'));

        // Conditionally launch create table for cohort_visibility.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Cohort savepoint reached.
        upgrade_plugin_savepoint(true, 2013100300, 'totara', 'cohort');
    }

    if ($oldversion < 2013103000) {
        // Don't run again if this upgrade has occurred before.
        $hasrun = get_config('totara_cohort', 'cohort_rule_fix_has_run');
        if (empty($hasrun)) {

            $brokenrules = totara_get_text_broken_rules();

            if (!empty($brokenrules)) {
                echo $OUTPUT->notification(
                    get_string('cohortbugheading', 'totara_cohort'),
                    'notifysuccess');

                $rulestofix = array();
                $unfixablerules = array();

                // Determine which of the broken rules we can fix automatically.
                foreach ($brokenrules as $rule) {
                    if (totara_cohort_is_rule_fixable($rule)) {
                        $rulestofix[] = $rule;
                    } else {
                        $unfixablerules[] = $rule;
                    }
                }

                // Fix those that we can.
                if (!empty($rulestofix)) {
                    echo $OUTPUT->notification(
                        get_string('cohortbugfixingxrules', 'totara_cohort', count($rulestofix)),
                        'notifysuccess');
                    totara_cohort_remap_rules($rulestofix);
                }

                // Record that we've run this upgrade now.
                set_config('cohort_rule_fix_has_run', 1, 'totara_cohort');

                // Advise users about remaining bad rules.
                if (!empty($unfixablerules)) {
                    $output = $PAGE->get_renderer('totara_core', null);
                    echo $output->show_text_broken_rules($unfixablerules);
                } else {
                    echo $OUTPUT->notification(
                        get_string('cohortbugnounfixable', 'totara_cohort'),
                        'notifysuccess');
                }

            }
        }

        // Adding foreign keys.
        $tables = array(
            'cohort_rule_collections' => array(
                new xmldb_key('cohorulecoll_coh_fk', XMLDB_KEY_FOREIGN, array('cohortid'), 'cohort', 'id'),
                new xmldb_key('cohorulecoll_mod_fk', XMLDB_KEY_FOREIGN, array('modifierid'), 'user', 'id')),
            'cohort_rulesets' => array(
                new xmldb_key('cohorule_rul_fk', XMLDB_KEY_FOREIGN, array('rulecollectionid'), 'cohort_rule_collections', 'id'),
                new xmldb_key('cohorule_mod_fk', XMLDB_KEY_FOREIGN, array('modifierid'), 'user', 'id')),
            'cohort_rules' => array(
                new xmldb_key('cohorules_rul_fk', XMLDB_KEY_FOREIGN, array('rulesetid'), 'cohort_rulesets', 'id'),
                new xmldb_key('cohorules_mod_fk', XMLDB_KEY_FOREIGN, array('modifierid'), 'user', 'id')),
            'cohort_rule_params' => array(
                new xmldb_key('cohorulepara_rul_fk', XMLDB_KEY_FOREIGN, array('ruleid'), 'cohort_rules', 'id'),
                new xmldb_key('cohorulepara_mod_fk', XMLDB_KEY_FOREIGN, array('modifierid'), 'user', 'id')),
            'cohort_msg_queue' => array(
                new xmldb_key('cohomsgqueu_coh_fk', XMLDB_KEY_FOREIGN, array('cohortid'), 'cohort', 'id'),
                new xmldb_key('cohomsgqueu_use_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', 'id'),
                new xmldb_key('cohomsgqueu_mod_fk', XMLDB_KEY_FOREIGN, array('modifierid'), 'user', 'id')),
            'cohort_plan_history' => array(
                new xmldb_key('cohoplanhist_use_fk', XMLDB_KEY_FOREIGN, array('usercreated'), 'user', 'id')));

        foreach ($tables as $tablename => $keys) {
            $table = new xmldb_table($tablename);
            foreach ($keys as $key) {
                $dbman->add_key($table, $key);
            }
        }

        // Cohort savepoint reached.
        upgrade_plugin_savepoint(true, 2013103000, 'totara', 'cohort');
    }

    if ($oldversion < 2014031900) {
        // Find and fix all missing course_completions records for courses enrolled via an Audience.
        require_once($CFG->dirroot . '/completion/completion_completion.php');
        $sql = "SELECT DISTINCT courseid FROM {enrol} WHERE enrol = ?";
        if ($courses = $DB->get_fieldset_sql($sql, array('cohort'))) {
            foreach ($courses as $courseid) {
                completion_start_user_bulk($courseid);
            }
        }
        upgrade_plugin_savepoint(true, 2014031900, 'totara', 'cohort');
    }

    if ($oldversion < 2014040700) {
        // Define table cohort_role to be created.
        $table = new xmldb_table('cohort_role');

        // Conditionally launch create table for cohort_role.
        if (!$dbman->table_exists($table)) {

            // Adding fields to table cohort_role.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('roleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            // Adding keys to table cohort_role.
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('cohortid', XMLDB_KEY_FOREIGN, array('cohortid'), 'cohort', array('id'));
            $table->add_key('roleid', XMLDB_KEY_FOREIGN, array('roleid'), 'role', array('id'));
            $table->add_key('usermodified', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', array('id'));

            // Adding indexes to table cohort_role.
            $table->add_index('uix_co_rol_ctx', XMLDB_INDEX_UNIQUE, array('cohortid', 'roleid', 'contextid'));

            // Adding comment to table cohort_role.
            $table->setComment('A table to store roles assigned to a cohort');

            // Creating the table.
            $dbman->create_table($table);
        }

        // Cohort savepoint reached.
        upgrade_plugin_savepoint(true, 2014040700, 'totara', 'cohort');
    }

    if ($oldversion < 2014090300) {
        // Check certifid exists. This is done to avoid errors if upgrading from older versions where the field doesn't exist.
        $table = new xmldb_table('prog');
        $field = new xmldb_field('certifid');
        if ($dbman->field_exists($table, $field)) {
            // Find and fix all certification type records in cohort_visibility table.
            $sql = "SELECT cv.id FROM {cohort_visibility} cv
                    INNER JOIN {prog} p ON cv.instanceid = p.id
                    WHERE cv.instancetype = ?
                      AND p.certifid IS NOT NULL";
            if ($certifications = $DB->get_fieldset_sql($sql, array(COHORT_ASSN_ITEMTYPE_PROGRAM))) {
                list($insql, $inparams) = $DB->get_in_or_equal($certifications);
                $update = "UPDATE {cohort_visibility}
                           SET instancetype = " . COHORT_ASSN_ITEMTYPE_CERTIF ."
                           WHERE id $insql";
                $DB->execute($update, $inparams);
            }
        }
        upgrade_plugin_savepoint(true, 2014090300, 'totara', 'cohort');
    }

    return true;
}
