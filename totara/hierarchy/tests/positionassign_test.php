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
 * @subpackage totara_hierarchy
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

global $CFG;
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/totara/hierarchy/lib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/position/lib.php');

class positionassign_test extends advanced_testcase {

    protected $user1, $user2, $user3, $user4, $user5;

    protected $pos_framework_data = array(
        'id' => 1, 'fullname' => 'Postion Framework 1', 'shortname' => 'PFW1', 'idnumber' => 'ID1', 'description' => 'Description 1',
        'sortorder' => 1, 'visible' => 1, 'hidecustomfields' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
    );

    protected $pos_data = array(
        array('id' => 1, 'fullname' => 'Data Analyst', 'shortname' => 'Analyst', 'idnumber' => 'DA1', 'frameworkid' => 1,
              'path' => '/1', 'depthlevel' => 1, 'parentid' => 0, 'sortthread' => '01', 'visible' => 1, 'timevalidfrom' => 0, 'timevalidto' => 0,
              'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 2),
        array('id' => 2, 'fullname' => 'Software Developer', 'shortname' => 'Developer', 'idnumber' => 'SD1', 'frameworkid' => 1,
              'path' => '/2', 'depthlevel' => 1, 'parentid' => 0, 'sortthread' => '01', 'visible' => 1, 'timevalidfrom' => 0, 'timevalidto' => 0,
              'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 2)
    );

    protected $pos_assignment_data = array(
        array('id' => 1, 'fullname' => 'Test Assignment 1', 'shortname' => 'Test 1', 'positionid' => 1,
              'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 2),
        array('id' => 2, 'fullname' => 'Test Assignment 2', 'shortname' => 'Test 2', 'positionid' => 1,
              'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 2),
        array('id' => 3, 'fullname' => 'Test Assignment 3', 'shortname' => 'Test 3', 'positionid' => 1,
              'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 2),
        array('id' => 4, 'fullname' => 'Test Assignment 5', 'shortname' => 'Test 5', 'positionid' => 1,
              'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 2),
    );

    protected function setUp() {
        global $DB;
        parent::setUp();

        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->user3 = $this->getDataGenerator()->create_user();
        $this->user4 = $this->getDataGenerator()->create_user();
        $this->user5 = $this->getDataGenerator()->create_user();

        $DB->insert_record('pos_framework', $this->pos_framework_data);
        $DB->insert_record('pos', $this->pos_data[0]);
        $DB->insert_record('pos', $this->pos_data[1]);
        // Primary pos assignments:
        // user1
        //  |
        //  |-user2
        //  |-user3
        // user5
        $DB->insert_record('pos_assignment', array_merge($this->pos_assignment_data[0],
            array('userid' => $this->user2->id, 'managerid' => $this->user1->id, 'managerpath' => "/{$this->user1->id}/{$this->user2->id}")));
        $DB->insert_record('pos_assignment', array_merge($this->pos_assignment_data[1],
            array('userid' => $this->user3->id, 'managerid' => $this->user1->id, 'managerpath' => "/{$this->user1->id}/{$this->user3->id}")));
        $DB->insert_record('pos_assignment', array_merge($this->pos_assignment_data[2],
            array('userid' => $this->user1->id, 'managerid' => null, 'managerpath' => "/{$this->user1->id}")));
        $DB->insert_record('pos_assignment', array_merge($this->pos_assignment_data[3],
            array('userid' => $this->user5->id, 'managerid' => null, 'managerpath' => "/{$this->user5->id}")));

        // Secondary pos assignment, change manager assignments to:
        // user2
        //  |
        //  |-user1
        //  |-user3
        // user5
        $DB->insert_record('pos_assignment', array_merge($this->pos_assignment_data[0],
            array('type' => POSITION_TYPE_SECONDARY, 'userid' => $this->user1->id,
            'managerid' => $this->user2->id, 'managerpath' => "/{$this->user2->id}/{$this->user1->id}")));
        $DB->insert_record('pos_assignment', array_merge($this->pos_assignment_data[1],
            array('type' => POSITION_TYPE_SECONDARY, 'userid' => $this->user3->id,
            'managerid' => $this->user2->id, 'managerpath' => "/{$this->user2->id}/{$this->user3->id}")));
        $DB->insert_record('pos_assignment', array_merge($this->pos_assignment_data[2],
            array('type' => POSITION_TYPE_SECONDARY, 'userid' => $this->user2->id,
            'managerid' => null, 'managerpath' => "/{$this->user2->id}")));
        $DB->insert_record('pos_assignment', array_merge($this->pos_assignment_data[3],
            array('type' => POSITION_TYPE_SECONDARY, 'userid' => $this->user5->id,
            'managerid' => null, 'managerpath' => "/{$this->user5->id}")));
    }

    public function test_assign_top_level_user() {
        global $DB;
        $this->resetAfterTest();

        // Assign to top level user.
        // Assign B->A then check B.
        $assignment = new position_assignment(array('userid' => $this->user2->id, 'type' => POSITION_TYPE_PRIMARY));
        $assignment->managerid = $this->user5->id;

        assign_user_position($assignment, true);

        if (!$field = $DB->get_field('pos_assignment', 'managerpath',
            array('type' => POSITION_TYPE_PRIMARY, 'userid' => $this->user2->id))) {

            $this->fail();
        }
        // Check correct path.
        $path = "/{$this->user5->id}/{$this->user2->id}";
        $this->assertEquals($path, $field);

        // Reassign A and check B updates correctly.
        $assignment2 = new position_assignment(array('userid' => $this->user5->id, 'type' => POSITION_TYPE_PRIMARY));
        $assignment2->managerid = $this->user3->id;
        assign_user_position($assignment2, true);

        if (!$field = $DB->get_field('pos_assignment', 'managerpath',
            array('type' => POSITION_TYPE_PRIMARY, 'userid' => $this->user2->id))) {

            $this->fail();
        }
        // Check correct path.
        $path = "/{$this->user1->id}/{$this->user3->id}/{$this->user5->id}/{$this->user2->id}";
        $this->assertEquals($path, $field);
    }

    public function test_assign_lower_level_user() {
        global $DB;
        $this->resetAfterTest();

        // Assign to a lower level user.
        // Assign B->A where A is assigned to X.
        $assignment = new position_assignment(array('userid' => $this->user5->id, 'type' => POSITION_TYPE_PRIMARY));
        $assignment->managerid = $this->user2->id;
        assign_user_position($assignment, true);

        if (!$field = $DB->get_field('pos_assignment', 'managerpath',
            array('type' => POSITION_TYPE_PRIMARY, 'userid' => $this->user5->id))) {

            $this->fail();
        }
        $path = "/{$this->user1->id}/{$this->user2->id}/{$this->user5->id}";
        $this->assertEquals($path, $field);

        // Reassign A to a new parent and check B is updated.
        $assignment2 = new position_assignment(array('userid' => $this->user2->id, 'type' => POSITION_TYPE_PRIMARY));
        $assignment2->managerid = $this->user3->id;
        assign_user_position($assignment2, true);

        if (!$field = $DB->get_field('pos_assignment', 'managerpath',
            array('type' => POSITION_TYPE_PRIMARY, 'userid' => $this->user5->id))) {

            $this->fail();
        }
        $path = "/{$this->user1->id}/{$this->user3->id}/{$this->user2->id}/{$this->user5->id}";
        $this->assertEquals($path, $field);
    }

    public function test_assign_to_user_wo_assignment() {
        global $DB;
        $this->resetAfterTest();

        // Assign to a user without a position assignment.
        // Assign B->A where A doens't have a pos_assignment record.
        $assignment = new position_assignment(array('userid' => $this->user5->id, 'type' => POSITION_TYPE_PRIMARY));
        $assignment->managerid = $this->user4->id;
        assign_user_position($assignment, true);

        $pos_assignment = array('fullname' => 'Test Assignment 5', 'shortname' => 'Test 5',
            'type' => POSITION_TYPE_PRIMARY, 'timecreated' => 1326139697,
            'timemodified' => 1326139697, 'usermodified' => 1, 'userid' => $this->user4->id, 'positionid' => 1);

        $assignment2 = new position_assignment($pos_assignment);
        $assignment2->managerid = $this->user3->id;
        assign_user_position($assignment2, true);

        // Assign A->C and check B updates correctly.
        if (!$field = $DB->get_field('pos_assignment', 'managerpath',
            array('type' => POSITION_TYPE_PRIMARY, 'userid' => $this->user5->id))) {

            $this->fail();
        }
        $path = "/{$this->user1->id}/{$this->user3->id}/{$this->user4->id}/{$this->user5->id}";
        $this->assertEquals($path, $field);
    }

    public function test_assign_secondary_position() {
        global $DB;
        $this->resetAfterTest();

        // Assign to top level user with secondary position.
        // Assign B->A then check B.
        $assignment = new position_assignment(array('userid' => $this->user2->id, 'type' => POSITION_TYPE_SECONDARY));
        $assignment->managerid = $this->user5->id;

        assign_user_position($assignment, true);

        if (!$field = $DB->get_field('pos_assignment', 'managerpath',
            array('type' => POSITION_TYPE_SECONDARY, 'userid' => $this->user3->id))) {

            $this->fail();
        }
        // Check correct path.
        $path = "/{$this->user5->id}/{$this->user2->id}/{$this->user3->id}";
        $this->assertEquals($path, $field);

        // Make another secondary assignment.
        $assignment = new position_assignment(array('userid' => $this->user1->id, 'type' => POSITION_TYPE_SECONDARY));
        $assignment->managerid = $this->user4->id;

        assign_user_position($assignment, true);

        // Check primary managerpaths are unchanged.
        $primaries = $DB->get_records('pos_assignment', array('type' => POSITION_TYPE_PRIMARY), 'userid', 'id,userid,managerpath');
        foreach ($primaries as $primary) {
            if ($primary->userid == $this->user1->id) {
                $this->assertEquals("/{$this->user1->id}", $primary->managerpath);
            }
            if ($primary->userid == $this->user2->id) {
                $this->assertEquals("/{$this->user1->id}/{$this->user2->id}", $primary->managerpath);
            }
            if ($primary->userid == $this->user3->id) {
                $this->assertEquals("/{$this->user1->id}/{$this->user3->id}", $primary->managerpath);
            }
            if ($primary->userid == $this->user4->id) {
                $this->assertEquals("/{$this->user4->id}", $primary->managerpath);
            }
        }
    }
}
