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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage cohort/rules
 */
/**
 * This file defines the sqlhandler class, used by rules for dynamic cohorts
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
 * This class generates the SQL snippet for the rule, and it also stores and retrieves the parameters
 * for this rule, from the database.
 */
abstract class cohort_rule_sqlhandler {

    /**
     * A list of the parameters that will need to be stored/retrieved from the DB for this
     * rule. This should only contain parameters that can be configured by the website user;
     * parameters that are configured in settings.php (i.e. the definition of this rule type)
     * should not go in the database, but should be extracted from settings.php at runtime.
     *
     * The key is the name of the variable, the value is 0 for a scalar, 1 for an array
     * @var array
     */
    public $params = array(
        'operator' => 0,
        'lov' => 1
    );

    /**
     * The actual values populated into the parameters
     * @var array
     */
    public $paramvalues = array();

    /**
     *
     * @var int
     */
    public $ruleid = false;

    /**
     * Returns the SQL snippet that can be added to a where clause to include this rule.
     * The "mdl_user" table will have the alias "u" in this query.
     * @return str
     */
    public abstract function get_sql_snippet();

    /**
     * Get the rule instance's parameters from the DB
     * @param $ruleinstanceid
     */
    final public function fetch($ruleinstanceid=false) {
        global $DB;
        if ($ruleinstanceid) {
            $this->ruleid = $ruleinstanceid;
        } else if ($this->ruleid) {
            $ruleinstanceid = $this->ruleid;
        } else {
            return false;
        }

        $records = $DB->get_records('cohort_rule_params', array('ruleid' => $ruleinstanceid), 'name, value');
        if (!$records) {
            $records = array();
        }

        foreach ($this->params as $name=>$isarray){
            if ($isarray) {
                $this->{$name} = array();
            }
        }

        foreach( $records as $rec ){
            if (!array_key_exists($rec->name, $this->params)){
                // todo: an error message?
                //return false;
                continue;
            } else {
                // It should be an array
                if ($this->params[$rec->name]) {
                    $this->{$rec->name}[] = $rec->value;
                } else {
                    $this->{$rec->name} = $rec->value;
                }
            }
        }

        // Verify that all the params were populated
        $this->paramvalues = array();
        foreach ($this->params as $name=>$isarray) {
            if (!isset($this->{$name})) {
                // todo: an error message?
                //return false;
                continue;
            }
            $this->paramvalues[$name] = $this->{$name};
        }

        return true;
    }

    /**
     * Writes the rule instance's parameters to the DB
     * @param $ruleinstanceid
     */
    final public function write($ruleinstanceid = false) {
        global $USER, $DB;

        if ($ruleinstanceid) {
            $this->ruleid = $ruleinstanceid;
        } else if ($this->ruleid) {
            $ruleinstanceid = $this->ruleid;
        } else {
            return false;
        }

        // Get the cohort rule collection
        $sql = "SELECT DISTINCT crs.rulecollectionid
            FROM {cohort_rules} cr
            INNER JOIN {cohort_rulesets} crs ON cr.rulesetid = crs.id
            WHERE cr.id = ?";
        if (!$rulecollectionid = $DB->get_field_sql($sql, array($ruleinstanceid))) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();

        // Get any existing param records for this rule, so we can compare them with the new ones
        $existingrecords = $DB->get_records('cohort_rule_params', array('ruleid' => $ruleinstanceid), 'name, value', 'id, name, value');

        // Clean up the DB record and index them by param name to make later comparison easier
        $existingbyname = array();
        if ($existingrecords) {
            foreach ($existingrecords as $rec) {

                // A stored param that doesn't match any of the params for this ruletype. Delete it!
                if (!array_key_exists($rec->name, $this->params)) {
                    //todo: an error message?
                    $DB->delete_records('cohort_rule_params', array('id' => $rec->id));
                    continue;
                }

                if ($this->params[$rec->name]) {
                    // Array parameter
                    if (!isset($existingbyname[$rec->name])){
                        // Initialize the array
                        $existingbyname[$rec->name] = array($rec->value=>$rec);
                    } else {
                        $existingbyname[$rec->name][$rec->value] = $rec;
                    }
                } else {
                    // Non-array parameter
                    $existingbyname[$rec->name] = $rec;
                }
            }
            unset($existingrecords);
        }

        // Go over each param for this rule
        $ruleschanged = false;
        foreach ($this->params as $name=>$isarray) {
            if (!isset($this->{$name})) {
                // todo: error message?
                $this->{$name} = '';
            }

            if ($isarray) {

                if (!is_array($this->{$name})) {
                    // todo: error message?
                    $this->{$name} = array($this->{$name});
                }

                // Remove duplicates from input
                $this->{$name} = array_unique($this->{$name});

                if (isset($existingbyname[$name])) {

                    // $existing will have mdl_cohort_rule_params.value as the array key, and
                    // the full DB record as the array value
                    $existing =& $existingbyname[$name];

                    // Insert all the values present in $this->{$name} but not in the DB
                    $toinsert = array_diff(
                        $this->{$name},
                        array_keys($existing)
                    );
                    if (!empty($toinsert)) {
                        $ruleschanged = true;
                    }

                    // Delete all the values present in the DB but not in $this->{$name}
                    $todelete = array_diff(
                        array_keys($existing),
                        $this->{$name}
                    );
                    if (!empty($todelete)) {
                        $ruleschanged = true;
                    }

                    foreach ($toinsert as $val) {
                        $todb = new stdClass();
                        $todb->ruleid = $ruleinstanceid;
                        $todb->name = $name;
                        $todb->value = trim($val);
                        $todb->timecreated = $todb->timemodified = time();
                        $todb->modifierid = $USER->id;

                        if (!($todb->value == '')) {
                            $DB->insert_record('cohort_rule_params', $todb);
                        }
                    }

                    foreach ($todelete as $val) {
                        $DB->delete_records('cohort_rule_params', array('id' => $existing[$val]->id));
                    }

                } else {
                    // none of this array's values exist in the DB, so just insert all the records
                    foreach ($this->{$name} as $val) {
                        $todb = new stdClass();
                        $todb->ruleid = $ruleinstanceid;
                        $todb->name = $name;
                        $todb->value = trim($val);
                        $todb->timecreated = $todb->timemodified = time();
                        $todb->modifierid = $USER->id;

                        if (!($todb->value == '')) {
                            $DB->insert_record('cohort_rule_params', $todb);
                        }
                    }
                }

            } else { // if ($isarray)

                if (isset($existingbyname[$name])) {
                    // it exists in the DB, so update if the DB value doesn't match
                    $rec = $existingbyname[$name];
                    if ($rec->value != $this->{$name}) {
                        $todb = new stdClass();
                        $todb->id = $rec->id;
                        $todb->value = $this->{$name};
                        $todb->timemodified = time();
                        $todb->modifierid = $USER->id;
                        $DB->update_record('cohort_rule_params', $todb);
                        $ruleschanged = true;
                    }
                } else {
                    // it doesn't exist in the DB, so insert it
                    $todb = new stdClass();
                    $todb->ruleid = $ruleinstanceid;
                    $todb->name = $name;
                    $todb->value = $this->{$name};
                    $todb->timecreated = $todb->timemodified = time();
                    $todb->modifierid = $USER->id;
                    $DB->insert_record('cohort_rule_params', $todb);
                    $ruleschanged = true;
                }
            }
        }

        if ($ruleschanged) {
            // Update collection status
            $todb = new stdClass;
            $todb->id = $rulecollectionid;
            $todb->status = COHORT_COL_STATUS_DRAFT_CHANGED;
            $DB->update_record('cohort_rule_collections', $todb);
        }

        $transaction->allow_commit();
    }
}
