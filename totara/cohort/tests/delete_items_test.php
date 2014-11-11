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
 * Test system access rules.
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit totara_cohort_delete_items_testcase
 *
 */
class totara_cohort_delete_items_testcase extends advanced_testcase {

    private $pos1 = null;
    private $pos2 = null;
    private $cohort = null;
    private $cohort_generator = null;
    private $hierarchy_generator = null;

    public function setUp() {
        global $DB;

        parent::setup();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Set totara_cohort generator.
        $this->cohort_generator = $this->getDataGenerator()->get_plugin_generator('totara_cohort');

        // Set totara_hierarchy generator.
        $this->hierarchy_generator = $this->getDataGenerator()->get_plugin_generator('totara_hierarchy');

        // Create 3 users.
        $user1 = $this->getDataGenerator()->create_user(array('username' => 'user1'));
        $user2 = $this->getDataGenerator()->create_user(array('username' => 'user2'));
        $user3 = $this->getDataGenerator()->create_user(array('username' => 'user3'));

        // Create 2 positions
        $posfw = $this->hierarchy_generator->create_framework('pos', 'posfw');
        $this->pos1 = $this->hierarchy_generator->create_hierarchy($posfw, 'position', 'pos1');
        $this->pos2 = $this->hierarchy_generator->create_hierarchy($posfw, 'position', 'pos2');

        // Assign position to users.
        $this->hierarchy_generator->assign_primary_position($user1->id, null, null, $this->pos1);
        $this->hierarchy_generator->assign_primary_position($user2->id, null, null, $this->pos1);
        $this->hierarchy_generator->assign_primary_position($user3->id, null, null, $this->pos2);

        $this->assertEquals(2, $DB->count_records('pos_assignment', array('positionid' => $this->pos1)));
        $this->assertEquals(1, $DB->count_records('pos_assignment', array('positionid' => $this->pos2)));

        // Create a dynamic cohort.
        $this->cohort = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_DYNAMIC));
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));

        $ruleset = cohort_rule_create_ruleset($this->cohort->draftcollectionid);

        // Create a position rule that match users from pos1 and pos2.
        $params =  array(
            'equal' => 1,
            'includechildren' => 0
        );
        $listofvalues = array($this->pos1, $this->pos2);
        $this->cohort_generator->create_cohort_rule_params($ruleset, 'pos', 'id', $params, $listofvalues, 'listofvalues');
        cohort_rules_approve_changes($this->cohort);
        $this->assertEquals(3, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    public function test_delete_rule_param() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Get the rule param of cohort 1.
        $audience = $DB->get_record('cohort', array('id' => $this->cohort->id));
        $ruleids = $this->cohort_generator->cohort_get_ruleids($audience->draftcollectionid, 'pos', 'id');
        $ruleid = reset($ruleids);

        // Get the pos1 rule param.
        $params = array('ruleid' => $ruleid, 'name' => 'listofvalues', 'value' => $this->pos1);
        $ruleparam = $DB->get_record('cohort_rule_params', $params);

        // The dynamic cohort has pos1 and pos2 as item of the rule. Delete pos1 from the list of params.
        cohort_delete_param($ruleparam);

        // Approve to see the changes.
        $audience = $DB->get_record('cohort', array('id' => $this->cohort->id));
        cohort_rules_approve_changes($audience);
        $this->assertEquals(1, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    public function test_delete_ruleset() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a ruleset.
        $audience = $DB->get_record('cohort', array('id' => $this->cohort->id));
        $ruleset = cohort_rule_create_ruleset($audience->draftcollectionid);

        // Create a rule based on username equal to user1.
        $param = array('equal' => COHORT_RULES_OP_IN_ISEQUALTO);
        $this->cohort_generator->create_cohort_rule_params($ruleset, 'user', 'username', $param, array('user1'));
        cohort_rules_approve_changes($audience);
        $this->assertEquals(1, $DB->count_records('cohort_members', array('cohortid' => $audience->id)));

        // Get the last rule.
        $audience = $DB->get_record('cohort', array('id' => $this->cohort->id));
        $ruleids = $this->cohort_generator->cohort_get_ruleids($audience->draftcollectionid, 'user', 'username');
        $ruleid = reset($ruleids);

        // If we delete the rule param, it should delete the rule and the ruleset all together.
        $ruleparam = $DB->get_record('cohort_rule_params', array('ruleid' => $ruleid, 'name' => 'listofvalues'));
        cohort_delete_param($ruleparam);

        // Approve to see the changes.
        $audience = $DB->get_record('cohort', array('id' => $this->cohort->id));
        cohort_rules_approve_changes($audience);
        $this->assertEquals(3, $DB->count_records('cohort_members', array('cohortid' => $audience->id)));
    }

    public function test_cancel_changes() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Add a new ruleset.
        $audience = $DB->get_record('cohort', array('id' => $this->cohort->id));
        $ruleset = cohort_rule_create_ruleset($audience->draftcollectionid);

        // Create a rule based on username equal to user1.
        $param = array('equal' => COHORT_RULES_OP_IN_ISEQUALTO);
        $this->cohort_generator->create_cohort_rule_params($ruleset, 'user', 'username', $param, array('user1'));

        // There should be 1 rule for username in the draftcollection id.
        $audience = $DB->get_record('cohort', array('id' => $this->cohort->id));
        $ruleids = $this->cohort_generator->cohort_get_ruleids($audience->draftcollectionid, 'user', 'username');
        $this->assertEquals(1, count($ruleids));

        // Cancel changes.
        cohort_rules_cancel_changes($audience);
        $audience = $DB->get_record('cohort', array('id' => $this->cohort->id));
        $ruleids = $this->cohort_generator->cohort_get_ruleids($audience->draftcollectionid, 'user', 'username');
        $this->assertEquals(0, count($ruleids));

        // It should match the same users as before.
        $this->assertEquals(3, $DB->count_records('cohort_members', array('cohortid' => $audience->id)));
    }
}
