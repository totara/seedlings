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
 * Test audience member rule.
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit totara_cohort_audience_member_rule_testcase
 *
 */
class totara_cohort_audience_member_rule_testcase extends advanced_testcase {

    private $cohort_generator = null;
    private $userscohort1 = array();
    private $userscohort2 = array();
    private $noincohort1 = array();
    private $cohort1 = null;
    private $cohort2 = null;

    public function setUp() {
        global $DB;

        parent::setup();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $users = array();

        // Set totara_cohort generator.
        $this->cohort_generator = $this->getDataGenerator()->get_plugin_generator('totara_cohort');

        // Create some test users.
        $this->assertEquals(2, $DB->count_records('user'));
        for ($i = 1; $i <= 8; $i++) {
            $this->{'user'.$i} = $this->getDataGenerator()->create_user();
            $users[] = $this->{'user'.$i}->id;
        }
        // Verify the users were created. It should match $this->countmembers + 2 users(admin + guest).
        $this->assertEquals(10, $DB->count_records('user'));

        // Create cohort1.
        $this->cohort1 = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_STATIC));
        $this->assertTrue($DB->record_exists('cohort', array('id' => $this->cohort1->id)));
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $this->cohort1->id)));

        // Assign users to cohort1.
        $this->userscohort1 = array_slice($users, 1, 4);
        $this->cohort_generator->cohort_assign_users($this->cohort1->id, $this->userscohort1);
        $this->assertEquals(4, $DB->count_records('cohort_members', array('cohortid' => $this->cohort1->id)));

        // Create cohort2.
        $this->cohort2 = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_STATIC));
        $this->assertTrue($DB->record_exists('cohort', array('id' => $this->cohort2->id)));
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $this->cohort2->id)));

        // Assign users to cohort2.
        $this->userscohort2 = array_slice($users, 5, 7);
        $this->cohort_generator->cohort_assign_users($this->cohort2->id, $this->userscohort2);
        $this->assertEquals(3, $DB->count_records('cohort_members', array('cohortid' => $this->cohort2->id)));

        $this->userscohort1 = array_flip($this->userscohort1);
        $this->userscohort2 = array_flip($this->userscohort2);
        $this->noincohort1 = array_flip(array(get_admin()->id, $users[0]));
    }

    /**
     * Data provider for the audience member rule.
     */
    public function member_rule_params() {
        $data = array(
            array(array('incohort' => 1), array('cohort1'), 4, array('userscohort1')),
            array(array('incohort' => 0), array('cohort1'), 5, array('userscohort2', 'noincohort1')),
            array(array('incohort' => 1), array('cohort1', 'cohort2'), 7, array('userscohort1', 'userscohort2')),
        );
        return $data;
    }

    /**
     * Test audience member rule.
     * @dataProvider member_rule_params
     */
    public function test_audience_member($incohortparam, $cohortidsparam, $usercount, $membersmatched) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $cohort3 = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_DYNAMIC));
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $cohort3->id)));

        // Create ruleset 1.
        $ruleset = cohort_rule_create_ruleset($cohort3->draftcollectionid);

        // Process cohortidsparam.
        $cohortids = array();
        foreach ($cohortidsparam as $cohort) {
            $cohortids[] = $this->{$cohort}->id;
        }

        // Process members in cohort.
        $membersincohort = array();
        foreach ($membersmatched as $member) {
            $membersincohort = $membersincohort + $this->{$member};
        }

        // Create rule1.
        $this->cohort_generator->create_cohort_rule_params($ruleset, 'cohort', 'cohortmember', $incohortparam, $cohortids, 'cohortids');
        cohort_rules_approve_changes($cohort3);

        // It should match $usercount users.
        $members = $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = ?', array($cohort3->id));
        $this->assertEquals($usercount, count($members));
        $this->assertEmpty(array_diff_key(array_flip($members), $membersincohort));
    }
}
