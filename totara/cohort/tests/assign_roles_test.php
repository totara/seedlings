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
 * Test assign roles to cohorts.
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit totara_cohort_assign_roles_testcase
 *
 */
class totara_cohort_assign_roles_testcase extends advanced_testcase {

    private $cohort = null;
    private $context = null;
    private $assignableroles = array();
    private $cohort_generator = null;
    const TEST_ROLES_COUNT_USERS = 8;

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
        for ($i = 1; $i <= self::TEST_ROLES_COUNT_USERS; $i++) {
            $this->{'user'.$i} = $this->getDataGenerator()->create_user();
            $users[] = $this->{'user'.$i}->id;
        }
        // Check users were created. It should match TEST_ROLES_COUNT_USERS + 2 users(admin + guest).
        $this->assertEquals(self::TEST_ROLES_COUNT_USERS + 2, $DB->count_records('user'));

        // Create a cohort.
        $this->cohort = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_STATIC));
        $this->assertTrue($DB->record_exists('cohort', array('id' => $this->cohort->id)));

        // Check that there are no members in the new cohort.
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));

        // Assign users to the cohort.
        $this->cohort_generator->cohort_assign_users($this->cohort->id, $users);
        $this->assertEquals(self::TEST_ROLES_COUNT_USERS, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));

        // Get list of assignable roles.
        $this->context = context_system::instance();
        $this->assignableroles = get_assignable_roles($this->context, ROLENAME_BOTH, false);
        $this->assertNotEmpty($this->assignableroles, 'There are no roles to assign in the system context');
    }

    public function test_assign_roles_to_cohort() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $roles = array();
        $roleidsassigned = array();

        // Check there are no records in cohort_role or role_assignments.
        $this->assertEquals(0, $DB->count_records('role_assignments', array('itemid' => $this->cohort->id)));
        $this->assertEquals(0, $DB->count_records('cohort_role', array('cohortid' => $this->cohort->id)));

        // Make an array of key => values (roles => context) needed to process the assignment.
        foreach ($this->assignableroles as $key => $value) {
            $roles[$key] = $this->context->id;
        }

        // Assign roles to the cohort and verify it was successful.
        $this->assertTrue(totara_cohort_process_assigned_roles($this->cohort->id, $roles));

        // Validate the roles were added correctly to all members in the cohort.
        $roleids = array_keys($this->assignableroles);
        $rolesincohort = totara_get_cohort_roles($this->cohort->id);
        foreach ($rolesincohort as $role) {
            $roleidsassigned[] = $role->roleid;
        }

        // First validation: Roles were assigned in the cohort_role table.
        $this->assertEquals($roleids, $roleidsassigned);

        // Second validation: Roles were assigned in the role_assignments table.
        $countmembers = count(totara_get_members_cohort($this->cohort->id));
        foreach ($this->assignableroles as $key => $value) {
            $params = array(
                'component' => 'totara_cohort',
                'itemid'    => $this->cohort->id,
                'contextid' => $this->context->id,
                'roleid'    => $key
            );
            $this->assertEquals($countmembers, $DB->count_records('role_assignments', $params));
        }

        // Unassign all roles assigned and verify it was successful.
        $this->assertTrue(totara_cohort_process_assigned_roles($this->cohort->id, array()));

        // First validation: Roles were unassigned in the cohort_role table.
        $this->assertEmpty(totara_get_cohort_roles($this->cohort->id));

        // Second validation: Roles were un-assigned from the role_assignments table.
        foreach ($this->assignableroles as $key => $value) {
            $params = array(
                'component' => 'totara_cohort',
                'itemid'    => $this->cohort->id,
                'contextid' => $this->context->id,
                'roleid'    => $key
            );
            $this->assertEquals(0, $DB->count_records('role_assignments', $params));
        }

        // Make sure members are still in the cohort.
        $members = totara_get_members_cohort($this->cohort->id);
        $this->assertEquals(self::TEST_ROLES_COUNT_USERS, count($members));
    }
}
