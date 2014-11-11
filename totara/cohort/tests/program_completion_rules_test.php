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
require_once($CFG->dirroot . '/totara/program/cron.php');

/**
 * Test program completion rules.
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit totara_cohort_program_completion_rules_testcase
 *
 */
class totara_cohort_program_completion_rules_testcase extends reportcache_advanced_testcase {

    private $userprograms = array();
    private $cohort_generator = null;
    private $cohort = null;
    private $ruleset = 0;
    private $program1 = null;
    private $program2 = null;

    /*
     * Program completion data:
     *-----------------*---------------------*--------------*-----------------*-----------------*
     |      users      |       programs      | time started | time completed  | time completion |
     |-----------------*---------------------*--------------*-----------------*-----------------*
     |      user1      | program1            |  -5 days     |    -1 day       |     4 days      |
     |-----------------*---------------------*--------------*-----------------*-----------------*
     |      user2      | program1            |  -5 days     |    -2 days      |     3 days      |
     |-----------------*---------------------*--------------*-----------------*-----------------*
     |      user3      | program1 - program2 |  -3 days     |    -1 day       |     2 days      |
     |-----------------*---------------------*--------------*-----------------*-----------------*
     |      user4      | program2            |  -2 days     |    -1 day       |     1 days      |
     |-----------------*---------------------*--------------*----------------*-----------------*
     |      user5      | program2            |  -4 days     |    -2 days      |     2 days      |
     |-----------------*---------------------*--------------*-----------------*-----------------*
     |      user6      | program2            |  -3 days     | +1 day(future)  |     4 days      |
     |-----------------*---------------------*--------------*-----------------*-----------------*
     |      user7      | program2            |  -7 days     |    -1 day       |     6 days      |
     |-----------------*---------------------*--------------*-----------------*-----------------*
     |      user8      |  - - - - - - - -    |   - - - - -  |   - - - - - - - |   - - - - -     |
     *-----------------*---------------------*--------------*-----------------*-----------------*
    */
    public function setUp() {
        global $DB, $CFG;

        parent::setup();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->preventResetByRollback();

        // Set totara_cohort generator.
        $this->cohort_generator = $this->getDataGenerator()->get_plugin_generator('totara_cohort');

        // Create 8 users.
        $this->assertEquals(2, $DB->count_records('user'));
        for ($i = 1; $i <= 8; $i++) {
            $this->{'user'.$i} = $this->getDataGenerator()->create_user();
        }
        $this->assertEquals(10, $DB->count_records('user'));

        // Create a couple of courses.
        $this->assertEquals(1, $DB->count_records('course'));
        $setting = array('enablecompletion' => 1, 'completionstartonenrol' => 1);
        $course1 = $this->getDataGenerator()->create_course($setting);
        $course2 = $this->getDataGenerator()->create_course($setting);
        $this->assertEquals(3, $DB->count_records('course'));

        // Create two programs.
        $this->assertEquals(0, $DB->count_records('prog'));
        $this->program1 = $this->getDataGenerator()->create_program();
        $this->program2 = $this->getDataGenerator()->create_program();
        $this->assertEquals(2, $DB->count_records('prog'));

        // Assign courses to programs.
        $this->getDataGenerator()->add_courseset_program($this->program1->id, array($course1->id));
        $this->getDataGenerator()->add_courseset_program($this->program2->id, array($course2->id));

        // Assign users to programs.
        $usersprogram1 = array($this->user1->id, $this->user2->id, $this->user3->id);
        $this->getDataGenerator()->assign_program($this->program1->id, $usersprogram1);
        if (!empty($CFG->messaging)) {
            $this->assertDebuggingCalled(null, null, '', 3);
        }
        $usersprogram2 = array($this->user3->id, $this->user4->id, $this->user5->id, $this->user6->id, $this->user7->id);
        $this->getDataGenerator()->assign_program($this->program2->id, $usersprogram2);
        if (!empty($CFG->messaging)) {
            $this->assertDebuggingCalled(null, null, '', 5);
        }
        $this->userprograms[$this->program1->id] = $usersprogram1;
        $this->userprograms[$this->program2->id] = $usersprogram2;

        // Create timestarted for each user.
        $now = time();
        $timestarted = array();
        $timestarted[$this->user1->id] = $now - (5 * DAYSECS);
        $timestarted[$this->user2->id] = $now - (5 * DAYSECS);
        $timestarted[$this->user3->id] = $now - (3 * DAYSECS);
        $timestarted[$this->user4->id] = $now - (2 * DAYSECS);
        $timestarted[$this->user5->id] = $now - (4 * DAYSECS);
        $timestarted[$this->user6->id] = $now - (3 * DAYSECS);
        $timestarted[$this->user7->id] = $now - (7 * DAYSECS);

        // Create timecompleted for each user.
        $timecompleted = array();
        $timecompleted[$this->user1->id] = $now - (1 * DAYSECS);
        $timecompleted[$this->user2->id] = $now - (2 * DAYSECS);
        $timecompleted[$this->user3->id] = $now - (1 * DAYSECS);
        $timecompleted[$this->user4->id] = $now - (1 * DAYSECS);
        $timecompleted[$this->user5->id] = $now - (2 * DAYSECS);
        $timecompleted[$this->user6->id] = $now + (1 * DAYSECS);
        $timecompleted[$this->user7->id] = $now - (1 * DAYSECS);

        // Make completion for programs.
        foreach ($this->userprograms as $programid => $users) {
            $program = new program($programid);
            foreach ($users as $userid) {
                $completionsettings = array(
                    'status'        => STATUS_PROGRAM_COMPLETE,
                    'timestarted'   => $timestarted[$userid],
                    'timecompleted' => $timecompleted[$userid],
                );
                $program->update_program_complete($userid, $completionsettings);
            }
        }

        // Create a dynamic cohort.
        $this->cohort = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_DYNAMIC));
        $this->assertTrue($DB->record_exists('cohort', array('id' => $this->cohort->id)));
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));

        // Create a ruleset.
        $this->ruleset = cohort_rule_create_ruleset($this->cohort->draftcollectionid);
    }

    /**
     * Data provider for program completion date rule.
     */
    public function data_program_completion_date() {
        $now = time();
        $data = array(
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN, 'date' => $now - DAYSECS),  array('program1'), 1),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN, 'date' => $now - DAYSECS), array('program1'), 2),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_BEFORE_PAST_DURATION, 'date' => 1),  array('program2'), 4),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_WITHIN_PAST_DURATION, 'date' => 3), array('program2'), 4),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_WITHIN_FUTURE_DURATION, 'date' => 1), array('program2'), 1),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_AFTER_FUTURE_DURATION, 'date' => 6), array('program2'), 2),
        );
        return $data;
    }

    /**
     * Test program completion date rule.
     * @dataProvider data_program_completion_date
     */
    public function test_programcompletion_date($params, $programs, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Process listofids.
        $listofids = array();
        foreach ($programs as $program) {
            $listofids[] = $this->{$program}->id;
        }

        /**
         * Program completion data per users in program2:
         * user3 -> time started: -1day  - time completed = +1 day(future)  - completion time: 2 days
         * user4 -> time started: -3days - time completed = +3 days(future) - completion time: 6 days
         * user5 -> time started: -5days - time completed = +5 days(future) - completion time: 10 days
         * user6 -> time started: -7days - time completed = +7 days(future) - completion time: 14 days
         * user7 -> time started: -9days - time completed = +9 days(future) - completion time: 18 days
         */
        if ($params['operator'] === COHORT_RULE_COMPLETION_OP_AFTER_FUTURE_DURATION) {
            // Make completion in the future to test this rule.
            $now = time();
            $days = 1;
            $users = $this->userprograms[$this->program2->id];
            foreach ($users as $user) {
                $timestarted = $now - ($days * DAYSECS);
                $timecompleted = $now + ($days * DAYSECS);
                $program = new program($this->program2->id);
                $completionsettings = array(
                    'status'        => STATUS_PROGRAM_COMPLETE,
                    'timestarted'   => $timestarted,
                    'timecompleted' => $timecompleted
                );
                $program->update_program_complete($user, $completionsettings);
                $days= $days + 2;
            }
        }

        // Create a program completion date rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'learning', 'programcompletiondate', $params, $listofids, 'listofids');
        cohort_rules_approve_changes($this->cohort);

        // It should match:
        // 1. data1: 1 (users who complete program1 before yesterday).
        // 2. data2: 2 (users who have complete the list of programs after the date specified).
        // 3. data3: 4 (users who complete the program in the past 1 day).
        // 4. data4: 4 (users who finish program2 in within the past 3 days).
        // 5. data5: 1 (users who will finish program2 within the upcoming 1 days).
        // 6. data6: 2 (users who will finish program2 after the upcoming 6 days).
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    /**
     * Data provider for program completion duration rule.
     */
    public function data_program_completion_duration() {
        $data = array(
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN, 'date' => 2),  array('program1'), 1),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN, 'date' => 3), array('program1'), 2),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN, 'date' => 2),  array('program1', 'program2'), 1),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN, 'date' => 3), array('program2'), 2),
        );
        return $data;
    }

    /**
     * Test program completion duration rule.
     * @dataProvider data_program_completion_duration
     */
    public function test_programcompletion_duration($params, $programs, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Process listofids.
        $listofids = array();
        foreach ($programs as $program) {
            $listofids[] = $this->{$program}->id;
        }

        // Create a completion duration rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'learning', 'programcompletionduration', $params, $listofids, 'listofids');
        cohort_rules_approve_changes($this->cohort);

        // It should match:
        // 1. data1: 1 (users who have completed program1 in a period less than 2 days).
        // 2. data2: 2 (users who have completed program1 in a period less than 3 days).
        // 3. data3: 1 (users that had completed program1 and program2 within duration of more than 2 days).
        // 4. data4: 2 (users that had completed program2 in a period grater than 3 days).
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    /**
     * Data provider for program completion list rule.
     */
    public function data_program_completion_list() {
        $data = array(
            array(array('operator' => COHORT_RULE_COMPLETION_OP_NONE),  array('program1', 'program2'), 2),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_ANY), array('program1', 'program2'), 7),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_NOTALL),  array('program1', 'program2'), 6),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_ALL), array('program1', 'program2'), 1),
        );
        return $data;
    }

    /**
     * Test program completion list rule.
     * @dataProvider data_program_completion_list
     */
    public function test_programcompletion_list($params, $programs, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Process listofids.
        $listofids = array();
        foreach ($programs as $program) {
            $listofids[] = $this->{$program}->id;
        }

        // Create a program completion list rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'learning', 'programcompletionlist', $params, $listofids, 'listofids');
        cohort_rules_approve_changes($this->cohort);

        // It should match:
        // 1. data1: 2 (users that had not completed program2 or program3) => user8 + Admin.
        // 2. data2: 7 (users who completed one of the programs).
        // 3. data3: 6 (users who have not completed all programs).
        // 4. data4: 1 (users who has completed both programs).
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }
}
