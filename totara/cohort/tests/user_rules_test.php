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
 * Test user rules.
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit totara_cohort_user_rules_testcase
 *
 */
class totara_cohort_user_rules_testcase extends reportcache_advanced_testcase {

    private $cohort_generator = null;
    private $cohort = null;
    private $ruleset = 0;
    const TEST_USER_COUNT_MEMBERS = 53;

    public function setUp() {
        parent::setup();
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->preventResetByRollback();
        $userscreated = 0;

        // Set totara_cohort generator.
        $this->cohort_generator = $this->getDataGenerator()->get_plugin_generator('totara_cohort');

        // Create users.
        for ($i = 1; $i <= self::TEST_USER_COUNT_MEMBERS; $i++) {
            $userdata = array(
                'username' => 'user' . $i,
                'idnumber' => 'USER00' . $i,
                'email' => 'user' . $i . '@123.com',
                'city' => 'Valencia',
                'country' => 'ES',
                'lang' => 'es',
                'institution' => 'UV',
                'department' => 'system',
            );

            if ($i <= 10) {
                $userdata['idnumber'] = 'USERX0' . $i;
            }

            if ($i%2 == 0) {
                $userdata['firstname'] = 'nz_' . $i . '_testuser';
                $userdata['lastname'] = 'NZ FAMILY NAME';
                $userdata['city'] = 'wellington';
                $userdata['country'] = 'NZ';
                $userdata['email'] = 'user' . $i . '@nz.com';
                $userdata['lang'] = 'en';
                $userdata['institution'] = 'Totara';
            }

            $this->getDataGenerator()->create_user($userdata);
            $userscreated++;
        }
        $this->assertEquals(self::TEST_USER_COUNT_MEMBERS, $userscreated);

        // Creating an empty dynamic cohort.
        $this->cohort = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_DYNAMIC));
        $this->assertTrue($DB->record_exists('cohort', array('id' => $this->cohort->id)));
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));

        // Creating a ruleset.
        $this->ruleset = cohort_rule_create_ruleset($this->cohort->draftcollectionid);
    }

    /**
     * Data provider for the idnumber rule.
     */
    public function data_id_number() {
        $data = array(
            array(array('equal' => COHORT_RULES_OP_IN_CONTAINS), array('USER00'), 43),
            array(array('equal' => COHORT_RULES_OP_IN_CONTAINS), array('USERX0'), 10),
            array(array('equal' => COHORT_RULES_OP_IN_NOTCONTAIN), array('USER00'), 11),
            array(array('equal' => COHORT_RULES_OP_IN_STARTSWITH), array('USER'), 53),
        );
        return $data;
    }

    /**
     * Test the idnumber text rule.
     * @dataProvider data_id_number
     */
    public function test_idnumber_text_rule($params, $listofvalues, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'idnumber', $params, $listofvalues);
        cohort_rules_approve_changes($this->cohort);
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    /**
     * Data provider for the username rule.
     */
    public function data_username() {
        $data = array(
            array(array('equal' => COHORT_RULES_OP_IN_STARTSWITH), array('user'), 53),
            array(array('equal' => COHORT_RULES_OP_IN_ISEQUALTO), array('user1'), 1),
        );
        return $data;
    }

    /**
     * Test the username text rule.
     * @dataProvider data_username
     */
    public function test_username_text_rule($params, $listofvalues, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a username rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'username', $params, $listofvalues);
        cohort_rules_approve_changes($this->cohort);
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    /**
     * Data provider for email rule.
     */
    public function data_email() {
        $data = array(
            array(array('equal' => COHORT_RULES_OP_IN_CONTAINS), array('@nz.com'), 26),
            array(array('equal' => COHORT_RULES_OP_IN_CONTAINS), array('@123'), 27),
        );
        return $data;
    }

    /**
     * Test the email text rule.
     * @dataProvider data_email
     */
    public function test_email_text_rule($params, $listofvalues, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create an email rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'email', $params, $listofvalues);
        cohort_rules_approve_changes($this->cohort);
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    /**
     * Data provider for firstname rule.
     */
    public function data_firstname() {
        $data = array(
            array(array('equal' => COHORT_RULES_OP_IN_ENDSWITH), array('testuser'), 26),
        );
        return $data;
    }

    /**
     * Test the firstname text rule.
     * @dataProvider data_firstname
     */
    public function test_firstname_text_rule($params, $listofvalues, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a firstname rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'firstname', $params, $listofvalues);
        cohort_rules_approve_changes($this->cohort);
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    /**
     * Data provider for lastname rule.
     */
    public function data_lastname() {
        $data = array(
            array(array('equal' => COHORT_RULES_OP_IN_ENDSWITH), array('NZ FAMILY NAME'), 26),
        );
        return $data;
    }

    /**
     * Test the lastname text rule.
     * @dataProvider data_lastname
     */
    public function test_lastname_text_rule($params, $listofvalues, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a lastname rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'lastname', $params, $listofvalues);
        cohort_rules_approve_changes($this->cohort);
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    /**
     * Data provider for city rule.
     */
    public function data_city() {
        $data = array(
            array(array('equal' => COHORT_RULES_OP_IN_ISEQUALTO), array('Valencia'), 27),
        );
        return $data;
    }

    /**
     * Test the city text rule.
     * @dataProvider data_city
     */
    public function test_city_text_rule($params, $listofvalues, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create a city rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'city', $params, $listofvalues);
        cohort_rules_approve_changes($this->cohort);
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    /**
     * Data provider for institution rule.
     */
    public function data_institution() {
        $data = array(
            array(array('equal' => COHORT_RULES_OP_IN_ISEQUALTO), array('UV'), 27),
        );
        return $data;
    }

    /**
     * Test the institution text rule.
     * @dataProvider data_institution
     */
    public function test_institution_text_rule($params, $listofvalues, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create an institution rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'institution', $params, $listofvalues);
        cohort_rules_approve_changes($this->cohort);
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    /**
     * Data provider for department rule.
     */
    public function data_department() {
        $data = array(
            array(array('equal' => COHORT_RULES_OP_IN_NOTEQUALTO), array('system'), 1),
            array(array('equal' => COHORT_RULES_OP_IN_ISEQUALTO), array('system'), 53),
        );
        return $data;
    }

    /**
     * Test the department text rule.
     * @dataProvider data_department
     */
    public function test_department_text_rule($params, $listofvalues, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Add a rule with department not equal to system. It should not match any of the users created.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'department', $params, $listofvalues);
        cohort_rules_approve_changes($this->cohort);
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }
}
