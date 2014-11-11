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
 * Test organisation rules.
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit totara_cohort_organisation_rules_testcase
 *
 */
class totara_cohort_organisation_rules_testcase extends advanced_testcase {

    private $org1 = null;
    private $org2 = null;
    private $org3 = null;
    private $org4 = null;
    private $org5 = null;
    private $orgfw = null;
    private $cohort = null;
    private $ruleset = 0;
    private $usersorg1 = array();
    private $usersorg2 = array();
    private $usersorg3 = array();
    private $usersorg4 = array();
    private $cohort_generator = null;
    private $hierarchy_generator = null;
    const TEST_ORGANISATION_COUNT_MEMBERS = 23;

    /**
     * Users per organisation:
     *
     * org1 ----> user3, user6, user9, user12, user15, user18, user21.
     *
     * org2 ----> user2, user4, user8, user10, user14, user16, user20, user22.
     *
     * org3 ----> user1, user5, user7, user11, user13, user17, user19, user23.
     */
    public function setUp() {
        global $DB;

        parent::setup();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Set totara_cohort generator.
        $this->cohort_generator = $this->getDataGenerator()->get_plugin_generator('totara_cohort');

        // Set totara_hierarchy generator.
        $this->hierarchy_generator = $this->getDataGenerator()->get_plugin_generator('totara_hierarchy');

        // Create organisations and organisation fw.
        $this->orgfw = $this->hierarchy_generator->create_framework('org', 'orgfw');

        // Create organisations and organisation hierarchies.
        $this->assertEquals(0, $DB->count_records('org'));
        $this->org1 = $this->hierarchy_generator->create_hierarchy($this->orgfw, 'organisation', 'orgname1', array('idnumber' => 'org1'));
        $this->org2 = $this->hierarchy_generator->create_hierarchy($this->orgfw, 'organisation', 'orgname2', array('idnumber' => 'org2'));
        $this->org3 = $this->hierarchy_generator->create_hierarchy($this->orgfw, 'organisation', 'orgname3', array('idnumber' => 'org3'));
        $this->assertEquals(3, $DB->count_records('org'));

        // Create some test users and assign them to an organisation.
        $this->assertEquals(2, $DB->count_records('user'));

        for ($i = 1; $i <= self::TEST_ORGANISATION_COUNT_MEMBERS; $i++) {
            $this->{'user'.$i} = $this->getDataGenerator()->create_user();
            if ($i%3 === 0) {
                $orgid = $this->org1; // 7 users.
                $org = 'org1';
            } else if ($i%2 === 0){
                $orgid = $this->org2; // 8 users.
                $org = 'org2';
            } else {
                $orgid = $this->org3; // 8 users.
                $org = 'org3';
            }
            $this->hierarchy_generator->assign_primary_position($this->{'user'.$i}->id, null, $orgid, null);
            array_push($this->{'users'.$org}, $this->{'user'.$i}->id);
        }

        // Verify the users were created. It should match TEST_ORGANISATION_COUNT_MEMBERS + 2 users(admin + guest).
        $this->assertEquals(self::TEST_ORGANISATION_COUNT_MEMBERS + 2, $DB->count_records('user'));

        $this->usersorg1 = array_flip($this->usersorg1);
        $this->usersorg2 = array_flip($this->usersorg2);
        $this->usersorg3 = array_flip($this->usersorg3);

        // Check that organisations were assigned correctly.
        $this->assertEquals(7, $DB->count_records('pos_assignment', array('organisationid' => $this->org1)));
        $this->assertEquals(8, $DB->count_records('pos_assignment', array('organisationid' => $this->org2)));
        $this->assertEquals(8, $DB->count_records('pos_assignment', array('organisationid' => $this->org3)));

        // Creating dynamic cohort and check that there are no members in the new cohort.
        $this->cohort = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_DYNAMIC));
        $this->assertTrue($DB->record_exists('cohort', array('id' => $this->cohort->id)));
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));

        // Create ruleset.
        $this->ruleset = cohort_rule_create_ruleset($this->cohort->draftcollectionid);
    }

    public function test_organisation_idnumber_rule() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Add a rule that matches users for the organisation org1. It should match 7 users.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'org', 'idnumber', array('equal' => COHORT_RULES_OP_IN_ISEQUALTO),  array('org1'));
        cohort_rules_approve_changes($this->cohort);

        $members = $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = ?', array($this->cohort->id));
        $this->assertEquals(7, count($members));
        $this->assertEmpty(array_diff_key(array_flip($members), $this->usersorg1));
    }

    public function test_organisation_type_rule() {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create type of organisation.
        $newtype = new stdClass();
        $newtype->shortname = 'type1';
        $newtype->fullname = 'type1';
        $newtype->idnumber = 'typeID1';
        $newtype->description = '';
        $newtype->timecreated = time();
        $newtype->usermodified = $USER->id;
        $newtype->timemodified = time();
        $orgtype1 = $DB->insert_record('org_type', $newtype);

        // Verify the record was created correctly.
        $this->assertInternalType('int', $orgtype1);

        // Assign the type organisation to org1.
        $this->assertTrue($DB->set_field('org', 'typeid', $orgtype1, array('id' => $this->org1)));

        // Create a rule that matches users in the previous created type.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'org', 'orgtype', array('equal' => COHORT_RULES_OP_IN_EQUAL), array($orgtype1));
        cohort_rules_approve_changes($this->cohort);

        // It should match 7 users (org1).
        $members = $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = ?', array($this->cohort->id));
        $this->assertEquals(7, count($members));
        $this->assertEmpty(array_diff_key(array_flip($members), $this->usersorg1));
    }

    /**
     * Data provider for organisation rule.
     */
    public function data_organisation_hierarchy() {
        $data = array(
            array(array('equal' => 1, 'includechildren' => 1),  array('org1'), 10, array('org1', 'org4')),
            array(array('equal' => 1, 'includechildren' => 0),  array('org1'), 7, array('org1')),
            array(array('equal' => 0, 'includechildren' => 1),  array('org1', 'org2'), 8, array('org3')),
        );
        return $data;
    }

   /**
    * Test organisation rule.
    *
    *  Hierarchy of organisations with their users assigned:
    *
    * org1
    *   |----> user3, user6, user9, user12, user15, user18, user21.
    *   |----> org4
    *            |----> newuser1, newuser3, newuser5.
    *
    * org2
    *   |----> user2, user4, user8, user10, user14, user16, user20, user22.
    *   |----> org5
    *            |----> newuser2, newuser4.
    *
    * @dataProvider data_organisation_hierarchy
    */
    public function test_organisation_rule($params, $organisations, $usercount, $membersmatched) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Create some organisatiosn to make a hierarchy.
        $data = array('idnumber' => 'og4', 'parentid' => $this->org1);
        $this->org4 = $this->hierarchy_generator->create_hierarchy($this->orgfw, 'organisation', 'org4', $data);
        $data = array('idnumber' => 'org5', 'parentid' => $this->org2);
        $this->org5 = $this->hierarchy_generator->create_hierarchy($this->orgfw, 'organisation', 'org5', $data);

        // Process organisations.
        $listofvalues = array();
        foreach ($organisations as $org) {
            $listofvalues[] = $this->{$org};
        }

        // Create some users and assign them to the new organisations.
        $newuser1 = $this->getDataGenerator()->create_user(array('username' => 'newuser1'));
        $newuser2 = $this->getDataGenerator()->create_user(array('username' => 'newuser2'));
        $newuser3 = $this->getDataGenerator()->create_user(array('username' => 'newuser3'));
        $newuser4 = $this->getDataGenerator()->create_user(array('username' => 'newuser4'));
        $newuser5 = $this->getDataGenerator()->create_user(array('username' => 'newuser5'));

        // Assign organisations.
        $this->hierarchy_generator->assign_primary_position($newuser1->id, null, $this->org4, null);
        $this->hierarchy_generator->assign_primary_position($newuser2->id, null, $this->org5, null);
        $this->hierarchy_generator->assign_primary_position($newuser3->id, null, $this->org4, null);
        $this->hierarchy_generator->assign_primary_position($newuser4->id, null, $this->org5, null);
        $this->hierarchy_generator->assign_primary_position($newuser5->id, null, $this->org4, null);
        $this->usersorg4 = array_flip(array($newuser1->id, $newuser3->id, $newuser5->id));

        $this->assertEquals(3, $DB->count_records('pos_assignment', array('organisationid' => $this->org4)));
        $this->assertEquals(2, $DB->count_records('pos_assignment', array('organisationid' => $this->org5)));

        // Process list of users that should match the data.
        $membersincohort = array();
        foreach ($membersmatched as $member) {
            $membersincohort = $membersincohort + $this->{'users'.$member};
        }

        // Exclude admin user from this cohort.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'user', 'username', array('equal' => COHORT_RULES_OP_IN_NOTEQUALTO), array('admin'));

        // Create organisation rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'org', 'id', $params, $listofvalues);
        cohort_rules_approve_changes($this->cohort);

        // It should match:
        // 1. data1: 10 users: 7 from org1 and 3 from org4 which is a children of org1.
        // 2. data2: 7 users: users from org1 without include its children.
        // 3. data3: 8 users: users from org3.
        $members = $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = ?', array($this->cohort->id));
        $this->assertEquals($usercount, count($members));
        $this->assertEmpty(array_diff_key(array_flip($members), $membersincohort));
    }
}
