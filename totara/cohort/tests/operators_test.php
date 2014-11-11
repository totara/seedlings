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
 * @subpackage cohort
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');
require_once($CFG->dirroot . '/totara/cohort/lib.php');

/**
 * Test operators (AND/OR) in cohort and rulesets.
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit totara_cohort_operators_testcase
 *
 */
class totara_cohort_operators_testcase extends reportcache_advanced_testcase {

    private $cohort_generator = null;
    private $cohort = null;
    private $ruleset = 0;
    private $course1 = null;
    private $course2 = null;
    const TEST_OPERATOR_USER_COUNT_MEMBERS = 30;

    public function setUp() {
        global $DB;
        parent::setup();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->preventResetByRollback();

        $users = array();
        $userdata = array();
        $timestartedvalues = array(-2, -5, -7);
        $timecompletedvalues = array(-1, -2, 1);

        // Set totara_cohort generator.
        $this->cohort_generator = $this->getDataGenerator()->get_plugin_generator('totara_cohort');

        // Create some users.
        $this->assertEquals(2, $DB->count_records('user'));
        for ($i = 1; $i <= self::TEST_OPERATOR_USER_COUNT_MEMBERS; $i++) {
            $userdata['country'] = 'ES';
            $userdata['city'] = 'Valencia';
            $userdata['email'] = 'user' . $i . '@val.com';
            $userdata['institution'] = 'UV';
            $userdata['department'] = 'system';
            if ($i%2 === 0) {
                $userdata['country'] = 'NZ';
                $userdata['city'] = 'wellington';
                $userdata['email'] = 'user' . $i . '@nz.com';
                $userdata['institution'] = 'Totara';
            }
            $this->{'user'.$i} = $this->getDataGenerator()->create_user($userdata);
            $users[$i] = $this->{'user'.$i}->id;
        }
        $this->assertEquals(self::TEST_OPERATOR_USER_COUNT_MEMBERS + 2, $DB->count_records('user'));

        // Create some courses.
        $setting = array('enablecompletion' => 1, 'completionstartonenrol' => 1);
        $this->course1 = $this->getDataGenerator()->create_course($setting);
        $this->course2 = $this->getDataGenerator()->create_course($setting);
        $courses = array($this->course1->id, $this->course2->id);

        // Make completion of the course.
        $now = time();
        for ($i = 1; $i <= self::TEST_OPERATOR_USER_COUNT_MEMBERS; $i++) {
            // Set timestarted y timecompleted.
            if ($i <= 10) {
                $timestarted = $now + ($timestartedvalues[0] * DAYSECS);
                $timecompleted = $now + ($timecompletedvalues[0] * DAYSECS);
            } else if ($i < 20){
                $timestarted = $now + ($timestartedvalues[1] * DAYSECS);
                $timecompleted = $now + ($timecompletedvalues[1] * DAYSECS);
            } else {
                $timestarted = $now + ($timestartedvalues[2] * DAYSECS);
                $timecompleted = $now + ($timecompletedvalues[2] * DAYSECS);
            }

            foreach ($courses as $courseid) {
                $completionrpl = new completion_completion(array('userid' => $users[$i], 'course' => $courseid, 'timestarted' => $timestarted));
                $completionrpl->rpl = 'completed via rpl';
                $completionrpl->status = COMPLETION_STATUS_COMPLETEVIARPL;
                $completionrpl->mark_complete($timecompleted);
            }
        }

        // Creating dynamic cohort.
        $this->cohort = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_DYNAMIC));
        $this->assertTrue($DB->record_exists('cohort', array('id' => $this->cohort->id)));

        // Creating a ruleset.
        $this->ruleset = cohort_rule_create_ruleset($this->cohort->draftcollectionid);
    }

    public function test_ruleset_operator() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Add rule which username starts with user. It should match the 30 users.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'username', array('equal' => COHORT_RULES_OP_IN_STARTSWITH), array('user'));
        cohort_rules_approve_changes($this->cohort);
        $this->assertEquals(30, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));

        // Add rule which match users from valencia. It should match the 15 users.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'city', array('equal' => COHORT_RULES_OP_IN_ISEQUALTO), array('Valencia'));
        cohort_rules_approve_changes($this->cohort);
        $this->assertEquals(15, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));

        // Get ruleset ID. It may be different that the ID in $ruleset.
        $audience = $DB->get_record('cohort', array('id' => $this->cohort->id));
        $rulsetid = $DB->get_field('cohort_rulesets', 'id', array('rulecollectionid' => $audience->draftcollectionid, 'sortorder' => 1));

        // Changes operator AND for OR in the previous rule. It should match 30 users again.
        $result = totara_cohort_update_operator($audience->id, $rulsetid, COHORT_OPERATOR_TYPE_RULESET, COHORT_RULES_OP_OR);
        $this->assertTrue($result);

        // Update users.
        $audience = $DB->get_record('cohort', array('id' => $this->cohort->id));
        cohort_rules_approve_changes($audience);

        // As the ruleset operator is OR any user that meets the condition is assigned to the cohort.
        $this->assertEquals(30, $DB->count_records('cohort_members', array('cohortid' => $audience->id)));
    }

    public function test_cohort_operator() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create ruleset 1. with rules that matches all users from spain(ES) that belongs to the "system" department
        // and had completion for course1 and course2 in less than 2 days.
        $paramequal = array('equal' => COHORT_RULES_OP_IN_ISEQUALTO);
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'country', $paramequal, array('ES'));
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'department', $paramequal, array('system'));

        // Create another rule that matches completion for courses in less than 2 days.
        $params = array(
            'operator' => COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN,
            'date' => 2
        );
        $listofids = array($this->course1->id, $this->course2->id);
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'learning', 'coursecompletionduration', $params, $listofids, 'listofids');
        cohort_rules_approve_changes($this->cohort);

        $this->assertEquals(5, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));

        // Make sure cohort operator is AND.
        totara_cohort_update_operator($this->cohort->id, $this->cohort->id, COHORT_OPERATOR_TYPE_COHORT, COHORT_RULES_OP_AND);
        $this->assertEquals(COHORT_RULES_OP_AND, cohort_collection_get_rulesetoperator($this->cohort->id, 'draft'));

        // Create ruleset 2. with rules that matches all users from New Zealand (NZ).
        // who had completion for course2 in less than 3 days.
        $audience = $DB->get_record('cohort', array('id' => $this->cohort->id));
        $ruleset2 = cohort_rule_create_ruleset($audience->draftcollectionid);

        // Add rule to match users from New Zealand (NZ).
        $this->cohort_generator->create_cohort_rule_params($ruleset2, 'user', 'country', $paramequal, array('NZ'));

        // Create rule 'learning','coursecompletiondate' that matches users who finish course2 in a duration of 4 days.
        $params =  array(
            'operator' => COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN,
            'date'     => 4
        );
        $listofids = array($this->course2->id);
        $this->cohort_generator->create_cohort_rule_params($ruleset2, 'learning', 'coursecompletionduration', $params, $listofids, 'listofids');
        cohort_rules_approve_changes($audience);

        // It should be 0 because there is no users who match the conditions for the rulesets.
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $audience->id)));

        // Change cohort operator to OR.
        totara_cohort_update_operator($audience->id, $audience->id, COHORT_OPERATOR_TYPE_COHORT, COHORT_RULES_OP_OR);
        $this->assertEquals(COHORT_RULES_OP_OR, cohort_collection_get_rulesetoperator($audience->id, 'draft'));

        // Update users.
        $audience = $DB->get_record('cohort', array('id' => $audience->id));
        cohort_rules_approve_changes($audience);

        // Now, the cohort should have 11 users (5 that match ruleset1 and 6 that match ruleset2).
        $this->assertEquals(11, $DB->count_records('cohort_members', array('cohortid' => $audience->id)));
    }
}
