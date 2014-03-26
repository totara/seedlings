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
 * @subpackage cohort/rules
 */
/**
 * This class is an ajax back-end for deleting a single rule param
 */
define('AJAX_SCRIPT', true);
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/cohort/lib.php');

$ruleparamid = required_param('ruleparamid', PARAM_INT);

$syscontext = context_system::instance();
require_capability('totara/cohort:managerules', $syscontext);

if (!$ruleparam = $DB->get_record('cohort_rule_params', array('id' => $ruleparamid), '*', MUST_EXIST)) {
    exit;
}

$sql = "SELECT crc.id AS collectionid, crs.id AS rulesetid, cr.id AS ruleid
    FROM {cohort_rule_params} crp
    INNER JOIN {cohort_rules} cr ON crp.ruleid = cr.id
    INNER JOIN {cohort_rulesets} crs ON cr.rulesetid = crs.id
    INNER JOIN {cohort_rule_collections} crc ON crs.rulecollectionid = crc.id
    WHERE crp.id = ?";
$ruledetails = $DB->get_record_sql($sql, array($ruleparam->id));

// Delete param
$DB->delete_records('cohort_rule_params', array('id' => $ruleparam->id));
$return = json_encode(array('action' => 'delruleparam', 'ruleparamid' => $ruleparam->id));

// Delete rule if no more params
if (!$DB->record_exists('cohort_rule_params', array('ruleid' => $ruledetails->ruleid, 'name' => $ruleparam->name))) {
    // Delete any orphan params first
    $DB->delete_records('cohort_rule_params', array('ruleid' => $ruledetails->ruleid));

    $DB->delete_records('cohort_rules', array('id' => $ruledetails->ruleid));
    $return = json_encode(array('action' => 'delrule', 'ruleid' => $ruledetails->ruleid));

    // Delete ruleset if no more rules
    if (!$DB->record_exists('cohort_rules', array('rulesetid' => $ruledetails->rulesetid))) {
        $DB->delete_records('cohort_rulesets', array('id' => $ruledetails->rulesetid));
        $return = json_encode(array('action' => 'delruleset', 'rulesetid' => $ruledetails->rulesetid));
    }
}

echo $return;

add_to_log(SITEID, 'cohort', 'delete rule param ' . $ruleparam->id, 'totara/cohort/rules.php');

// update rule collection status
$colldetails = new stdClass;
$colldetails->id = $ruledetails->collectionid;
$colldetails->timemodified = time();
$colldetails->modifierid = $USER->id;
$colldetails->status = COHORT_COL_STATUS_DRAFT_CHANGED;
$DB->update_record('cohort_rule_collections', $colldetails);
