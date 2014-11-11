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
 * @author Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 * @package totara
 * @subpackage totaracore
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

global $CFG;
require_once($CFG->dirroot . '/totara/hierarchy/prefix/position/lib.php');

class totaralib_test extends advanced_testcase {
    protected $user, $manager, $teamleader, $appraiser, $invaliduserid = 9999;

    protected $context_data = array('id' => '1', 'contextlevel' => CONTEXT_USER, 'instanceid' => 999);

    protected $role_data = array(
        array('id' => 1, 'name' => 'Manager', 'shortname' => 'manager', 'description' => 'Manager Role', 'sortorder' => 1),
        array('id' => 2, 'name' => 'Teamleader', 'shortname' => 'teamleader', 'description' => 'Team Leader Role', 'sortorder' => 2),
        array('id' => 3, 'name' => 'Appraiser', 'shortname' => 'appraiser', 'description' => 'Appraiser Role', 'sortorder' => 3),
    );

    protected $role_assignments_data = array(
        array('id' => 1, 'roleid' => 1, 'contextid' => 1),
        array('id' => 2, 'roleid' => 2, 'contextid' => 1),
        array('id' => 3, 'roleid' => 3, 'contextid' => 1),
    );

    protected $pos_assignment_data = array(
        array('id' => 1, 'fullname' => 'Pos fullname 1', 'type' => POSITION_TYPE_PRIMARY, 'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 1),
        array('id' => 2, 'fullname' => 'Pos fullname 2', 'type' => POSITION_TYPE_PRIMARY, 'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 1),
    );

    protected function setUp() {
        global $DB;
        parent::setUp();

        $this->user = $this->getDataGenerator()->create_user();
        $this->manager = $this->getDataGenerator()->create_user();
        $this->teamleader = $this->getDataGenerator()->create_user();
        $this->appraiser = $this->getDataGenerator()->create_user();

        $DB->insert_record('context', $this->context_data);
        $DB->delete_records('role');
        $DB->insert_record('role', $this->role_data[0]);
        $DB->insert_record('role', $this->role_data[1]);
        $DB->insert_record('role', $this->role_data[2]);
        $DB->insert_record('role_assignments', array_merge($this->role_assignments_data[0], array('userid' => $this->manager->id)));
        $DB->insert_record('role_assignments', array_merge($this->role_assignments_data[1], array('userid' => $this->teamleader->id)));
        $DB->insert_record('role_assignments', array_merge($this->role_assignments_data[2], array('userid' => $this->appraiser->id)));
        $DB->insert_record('pos_assignment', array_merge($this->pos_assignment_data[0],
                           array('userid' => $this->user->id, 'managerid' => $this->manager->id, 'appraiserid' => $this->appraiser->id)));
        $DB->insert_record('pos_assignment', array_merge($this->pos_assignment_data[1],
                           array('userid' => $this->manager->id, 'managerid' => $this->teamleader->id)));
    }

    public function test_totara_is_manager() {
        $this->resetAfterTest();

        // Totara_is_manager should return true when there is a role assignment for managerid at the user context for userid.
        $this->assertTrue(totara_is_manager($this->user->id, $this->manager->id));

        // Totara_is_manager should return false when there is not role assignment record for managerid on userid's user context.
        $this->assertFalse(totara_is_manager($this->user->id, $this->invaliduserid));
        $this->assertFalse(totara_is_manager($this->user->id, $this->appraiser->id));
        $this->assertFalse(totara_is_manager($this->user->id, $this->teamleader->id));
    }

    public function test_totara_get_manager() {
        $this->resetAfterTest();

        // Return value should be user object.
        $this->assertEquals(totara_get_manager($this->user->id)->id, $this->manager->id);

        // Totara_get_manager returns get_record_sql. expecting false here.
        $this->assertFalse(totara_get_manager($this->teamleader->id));
    }

    public function test_totara_get_teamleader() {
        $this->resetAfterTest();

        // Return value should be user object.
        $this->assertEquals(totara_get_teamleader($this->user->id)->id, $this->teamleader->id);

        // Totara_get_manager returns get_record_sql. expecting false here.
        $this->assertFalse(totara_get_teamleader($this->manager->id));
    }

    public function test_totara_get_appraiser() {
        $this->resetAfterTest();

        // Return value should be user object.
        $this->assertEquals(totara_get_appraiser($this->user->id)->id, $this->appraiser->id);

        // Totara_get_manager returns get_record_sql. expecting false here.
        $this->assertFalse(totara_get_appraiser($this->manager->id));
    }

    public function test_totara_get_staff() {
        $this->resetAfterTest();

        // Expect array of id numbers.
        $this->assertEquals(totara_get_staff($this->manager->id), array($this->user->id));

        // Expect false when the 'managerid' being inspected has no staff.
        $this->assertFalse(totara_get_staff($this->user->id));
    }

    public function test_totara_create_icon_picker() {
        $this->resetAfterTest();

        // Test with js.
        $mform = new MoodleQuickForm('mform', 'post', '');
        $picker = totara_create_icon_picker($mform, 'edit', 'course', 'default', 0, '_tst');
        $this->assertArrayHasKey('icon_tst', $picker);
        $this->assertArrayHasKey('currenticon_tst', $picker);
        $this->assertCount(2, $picker);
        $this->assertInstanceOf('MoodleQuickForm_hidden', $picker['icon_tst']);
        $this->assertInstanceOf('MoodleQuickForm_static', $picker['currenticon_tst']);
        // Check for link to choose icon.
        $this->assertFalse(strpos($picker['currenticon_tst']->_text, '<a') === false);

        $mform = new MoodleQuickForm('mform', 'post', '');
        $picker = totara_create_icon_picker($mform, '', 'course', '', 0, '_tst');
        $this->assertArrayHasKey('currenticon_tst', $picker);
        $this->assertCount(1, $picker);
        $this->assertInstanceOf('MoodleQuickForm_static', $picker['currenticon_tst']);
        // No link to choose icon, only preview.
        $this->assertTrue(strpos($picker['currenticon_tst']->_text, '<a') === false);

        // Test with nojs.
        $mform = new MoodleQuickForm('mform', 'post', '');
        $picker = totara_create_icon_picker($mform, 'edit', 'course', '', 1, '_tst');
        $this->assertArrayHasKey('icon_tst', $picker);
        $this->assertArrayHasKey('currenticon_tst', $picker);
        $this->assertCount(2, $picker);
        $this->assertInstanceOf('MoodleQuickForm_select', $picker['icon_tst']);
        $this->assertInstanceOf('MoodleQuickForm_static', $picker['currenticon_tst']);
        $this->assertContainsOnly('array', $picker['icon_tst']->_options);

        $mform = new MoodleQuickForm('mform', 'post', '');
        $picker = totara_create_icon_picker($mform, '', 'course', '', 1, '_tst');
        $this->assertArrayHasKey('currenticon_tst', $picker);
        $this->assertCount(1, $picker);
        $this->assertInstanceOf('MoodleQuickForm_static', $picker['currenticon_tst']);
    }
}
