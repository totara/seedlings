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
 * @author Maria Torres <maria.torres@totaralms.com>
 * @package totara
 * @subpackage cohort/rules
 */
/**
 * This class is an ajax back-end for updating operators AND/OR
 */
define('AJAX_SCRIPT', true);
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/cohort/lib.php');

$id = required_param('id', PARAM_INT);
$type = required_param('type', PARAM_ALPHA);
$value = required_param('value', PARAM_INT);
$cohortid = required_param('cohortid', PARAM_INT);

$syscontext = context_system::instance();
require_capability('totara/cohort:managerules', $syscontext);

$sql = "SELECT c.idnumber, c.draftcollectionid, crc.rulesetoperator, crc.status
        FROM {cohort} c
        INNER JOIN {cohort_rule_collections} crc ON c.draftcollectionid = crc.id
        WHERE c.id = ?";

if ($cohort = $DB->get_record_sql($sql, array($cohortid), '*', MUST_EXIST)) {

    if ($type === 'cohortoperator') {
        // Update cohort operator
        if ($value != $cohort->rulesetoperator) {
            $rulecollection = new stdClass();
            $rulecollection->id = $cohort->draftcollectionid;
            $rulecollection->rulesetoperator = $value;
            $rulecollection->status = COHORT_COL_STATUS_DRAFT_CHANGED;
            $rulecollection->timemodified = time();
            $rulecollection->modifierid = $USER->id;
            if($DB->update_record('cohort_rule_collections', $rulecollection)) {
                echo json_encode(array('action' => 'updcohortop', 'ruleid' => $rulecollection->id, 'value' => $value));
            }
        }
    } else {
        $operator = $DB->get_field('cohort_rulesets', 'operator', array('id' => $id));
        if ($operator != $value) {
            // Update resulset operator
            $ruleset = new stdClass();
            $ruleset->id = $id;
            $ruleset->operator = $value;
            $ruleset->timemodified = time();
            $ruleset->modifierid = $USER->id;
            if ($DB->update_record('cohort_rulesets', $ruleset)) {
                echo json_encode(array('action' => 'updrulesetop', 'ruleid' => $ruleset->id, 'value' => $value));
            }

            // Update cohort rule collection
            $rulecollection = new stdClass;
            $rulecollection->id = $cohort->draftcollectionid;
            $rulecollection->status = COHORT_COL_STATUS_DRAFT_CHANGED;
            $DB->update_record('cohort_rule_collections', $rulecollection);
        }
    }

    add_to_log(SITEID, 'cohort', 'edit rule operators', 'cohort/view.php?id='.$cohortid, $cohort->idnumber);
}

exit();