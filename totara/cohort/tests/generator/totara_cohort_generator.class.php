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
 * @package totara_cohort
 * @subpackage tests_generator
 */

/**
* Data generator.
*
* @package    totara_cohort
* @category   test
*/

defined('MOODLE_INTERNAL') || die();

class totara_cohort_generator extends component_generator_base {
    protected static $ind = 0;

    /**
     * Creates audiences.
     *
     * @param int $numaudience Number of audiences
     * @param array $userids users id to be added to the audience of last user
     * @return array $result Array of audiences id
     */
    public function create_audiences($numaudience, $userids) {
        $result = array();
        $size = floor(count($userids) / $numaudience);
        $listofusers = array_chunk($userids, $size);
        $nextnumber = 1;
        foreach ($listofusers as $users) {
            if ($cohort = $this->create_cohort()) {
                $this->cohort_assign_users($cohort->id, $users);
                $result[$nextnumber] = $cohort->id;
            }
            $nextnumber++;
        }

        return $result;
    }

    /**
     * Create an Audience.
     *
     * @param array $record Info related to the cohort table
     * @return mixed Info related to the cohort table
     */
    public function create_cohort($record = array()) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/cohort/lib.php');

        $record = (array) $record;
        $idnumber = totara_cohort_next_automatic_id();

        $cohort = new stdClass();
        $cohort->name = (isset($record['name'])) ? $record['name'] : 'tool_generator_' . $idnumber;
        $cohort->idnumber = (isset($record['idnumber'])) ? $record['idnumber'] : $idnumber;
        $cohort->contextid = (isset($record['contextid'])) ? $record['contextid'] : context_system::instance()->id;
        $cohort->cohorttype = (isset($record['cohorttype'])) ? $record['cohorttype'] : cohort::TYPE_STATIC;
        $cohort->description = (isset($record['description'])) ? $record['description'] : 'Audience create by tool_generator';
        $cohort->descriptionformat = (isset($record['descriptionformat'])) ? $record['descriptionformat'] : FORMAT_HTML;

        // Create cohort.
        $id = cohort_add_cohort($cohort);

        return $DB->get_record('cohort', array('id'=>$id), '*', MUST_EXIST);
    }
    /**
     * Assign users to the cohort.
     *
     * @param int $cohortid Cohort ID
     * @param array $userids Array of users IDs that need to be assigned to the audience
     */
    public function cohort_assign_users($cohortid, $userids = array()) {
        global $CFG;
        require_once($CFG->dirroot . '/totara/cohort/lib.php');

        // Assign audience.
        if (!empty($userids)) {
            foreach ($userids as $key => $userid) {
                cohort_add_member($cohortid, $userid);
            }
        }
    }

    /**
     * Add particular mock params to cohort rules
     *
     * @param int $ruleset Ruleset ID
     * @param string $ruletype Rule type
     * @param string $rulename Rule name
     * @param array $ruleparams Params to add
     * @param array $rulevalues List of values
     * @param string $paramname Current possible values (listofvalues, listofids, managerid, cohortids)
     */
    public function create_cohort_rule_params($ruleset, $ruletype, $rulename, $ruleparams, $rulevalues, $paramname = 'listofvalues') {
        global $DB, $USER;
        $data = array($ruleparams);
        foreach($rulevalues as $l) {
            $data[] = array($paramname => $l);
        }
        $ruleid = cohort_rule_create_rule($ruleset, $ruletype, $rulename);
        foreach($data as $d) {
            foreach ($d as $name => $value) {
                $todb = new stdClass();
                $todb->id = self::$ind;
                $todb->ruleid = $ruleid;
                $todb->name = $name;
                $todb->value = $value;
                $todb->timecreated = time();
                $todb->timemodified = time();
                $todb->modifierid = $USER->id;
                $DB->insert_record('cohort_rule_params', $todb);
                self::$ind++;
            }
        }
    }

    /**
     * Get array of rule IDs based on
     *
     * @param int $collectionid Collection id where the rules are
     * @param string $rulegroup Group where the rule belongs to
     * @param string $rulename Name of the type of rule we are dealing with
     *
     * @return  array of ruleids
     */
    public function cohort_get_ruleids($collectionid, $rulegroup, $rulename) {
        global $DB;

        $sql = "SELECT cr.id
            FROM {cohort_rule_collections} crc
            INNER JOIN {cohort_rulesets} crs
              ON crc.id = crs.rulecollectionid
            INNER JOIN {cohort_rules} cr
              ON cr.rulesetid = crs.id
            WHERE crc.id = ?
              AND cr.ruletype = ?
              AND cr.name = ?";

        return $DB->get_fieldset_sql($sql, array($collectionid, $rulegroup, $rulename));
    }
}
