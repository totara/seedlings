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
 * vendor/bin/phpunit totara_cohort_system_access_rules_testcase
 *
 */
class totara_cohort_system_access_rules_testcase extends advanced_testcase {

    private $cohort = null;
    private $ruleset = 0;
    private $usersodd = array();
    private $userseven = array();
    private $cohort_generator = null;

    public function setUp() {
        global $DB;

        parent::setup();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Set totara_cohort generator.
        $this->cohort_generator = $this->getDataGenerator()->get_plugin_generator('totara_cohort');

        // Create some test users.
        $this->assertEquals(2, $DB->count_records('user'));
        $timenow = time();
        for ($i = 1; $i <= 8; $i++) {
            $sufix = 'even';
            $login = $timenow - (7 * DAYSECS);
            if ($i%2 !== 0) {
                $sufix = 'odd';
                $login = $timenow - (10 * DAYSECS);
            }
            $data = array('firstaccess' => $login - DAYSECS, 'currentlogin' => $login + (5 * DAYSECS));
            $this->{'user'.$i} = $this->getDataGenerator()->create_user($data);
            $this->{'users'.$sufix}[] = $this->{'user'.$i}->id;
        }
        // Check the users were created. It should match 8 + 2 users(admin + guest).
        $this->assertEquals(10, $DB->count_records('user'));

        $this->userseven = array_flip($this->userseven);
        $this->usersodd = array_flip($this->usersodd);

        // Create a dynamic cohort.
        $this->cohort = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_DYNAMIC));
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));

        // Create ruleset.
        $this->ruleset = cohort_rule_create_ruleset($this->cohort->draftcollectionid);

    }

    /**
     * Data provider for first login rule.
     */
    public function data_first_login() {
        $data = array(
            array(array('operator' => COHORT_RULE_DATE_OP_BEFORE_FIXED_DATE, 'date' => time() - (10 * DAYSECS)), 4, array('usersodd')),
        );
        return $data;
    }

    /**
     * @dataProvider data_first_login
     */
    public function test_first_login_date($params, $usercount, $membersmatched) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Process members in cohort.
        $membersincohort = array();
        foreach ($membersmatched as $member) {
            $membersincohort = $membersincohort + $this->{$member};
        }

        // Exclude admin user from this cohort.
        $param = array('equal' => COHORT_RULES_OP_IN_NOTEQUALTO);
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'username', $param, array('admin'));

        // Create a first login rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'systemaccess', 'firstlogin', $params, array());
        cohort_rules_approve_changes($this->cohort);

        // It should match:
        // 1. data1: 4 (users which firstlogin is before 2 days from now) - users in usersodd array.
        $members = $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = ?', array($this->cohort->id));
        $this->assertEquals($usercount, count($members));
        $this->assertEmpty(array_diff_key(array_flip($members), $membersincohort));
    }

    /**
     * Data provider for last login rule.
     */
    public function data_last_login() {
        $data = array(
            array(array('operator' => COHORT_RULE_DATE_OP_AFTER_FIXED_DATE, 'date' => time() - (3 * DAYSECS)), 4, array('userseven')),
            array(array('operator' => COHORT_RULE_DATE_OP_BEFORE_PAST_DURATION, 'date' => 5), 4, array('usersodd')),
        );
        return $data;
    }

    /**
     * @dataProvider data_last_login
     */
    public function test_last_login_date($params, $usercount, $membersmatched) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Process members in cohort.
        $membersincohort = array();
        foreach ($membersmatched as $member) {
            $membersincohort = $membersincohort + $this->{$member};
        }

        // Exclude admin user from this cohort.
        $param = array('equal' => COHORT_RULES_OP_IN_NOTEQUALTO);
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'username', $param, array('admin'));

        // Create a last login rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'systemaccess', 'lastlogin', $params, array());
        cohort_rules_approve_changes($this->cohort);

        // It should match:
        // 1. data1: 4 (users which lastlogin was after today - 3 days ago) - users in userseven array.
        // 2. data2: 4 (users which lastlogin has been before the past duration of 5 days) - users in usersodd array.
        $members = $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = ?', array($this->cohort->id));
        $this->assertEquals($usercount, count($members));
        $this->assertEmpty(array_diff_key(array_flip($members), $membersincohort));
    }
}
