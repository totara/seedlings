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
 * Test course completion rules.
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit totara_cohort_course_completion_rules_testcase
 *
 */
class totara_cohort_course_completion_rules_testcase extends reportcache_advanced_testcase {

    private $course1 = null;
    private $course2 = null;
    private $users = array();
    private $cohort = null;
    private $ruleset = 0;
    private $coursestocomplete = array();
    private $cohort_generator = null;
    const TEST_COURSE_COUNT_USERS = 53;

    /*
     * Course completion data:
     *-----------------*----------*--------------*-----------------*-----------------*
     | Group of users  |  course  | time started | time completed  | time completion |
     |-----------------*----------*--------------*-----------------*-----------------*
     | user1  - user15 |  course1 |  -10 days    |    -1 day       |     9 days      |
     |-----------------*----------*--------------*-----------------*-----------------*
     | user1  - user15 |  course2 |  -10 days    |    -5 days      |     5 days      |
     |-----------------*----------*--------------*-----------------*-----------------*
     | user16 - user36 |  course1 |  -5 days     |    -3 days      |     2 days      |
     |-----------------*----------*--------------*-----------------*-----------------*
     | user16 - user36 |  course2 |  -5 days     |    -5 days      |     0 days      |
     |-----------------*----------*--------------*-----------------*-----------------*
     | user37 - user50 |  course2 |  -7 days     |    -1 day       |     6 days      |
     *-----------------*----------*--------------*-----------------*-----------------*
    */
    public function setUp() {
        global $DB;

        parent::setup();
        $this->resetAfterTest(true);
        $this->setAdminUser();
        $this->preventResetByRollback();

        // Set totara_cohort generator.
        $this->cohort_generator = $this->getDataGenerator()->get_plugin_generator('totara_cohort');

        // Create users.
        for ($i = 1; $i <= self::TEST_COURSE_COUNT_USERS; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->users[$i] = $user;
        }
        $this->assertEquals(self::TEST_COURSE_COUNT_USERS, count($this->users));

        // Create a couple of courses.
        $now = time();
        $setting = array('enablecompletion' => 1, 'completionstartonenrol' => 1);
        $this->course1 = $this->getDataGenerator()->create_course($setting);
        $this->course2 = $this->getDataGenerator()->create_course($setting);
        $this->coursestocomplete[$this->course1->id] = 1;
        $this->coursestocomplete[$this->course2->id] = 5;
        $this->assertEquals(0, $DB->count_records('course_completions'));

        // Enrol some users and make them complete course1 and course2.
        $coursetocomplete = $this->coursestocomplete;
        for ($i = 1; $i <= 50; $i++) {
            if ($i <= 15) { // Users from 1 to 15 will complete both courses. Time started = now - 10 days.
                $timestarted = $now - (10 * DAYSECS);
            } else if ($i <= 36) { // Users from 16 to 36 will complete both course. Time started = now - 5 days.
                $timestarted = $now - (5 * DAYSECS);
                $coursetocomplete[$this->course1->id] = 3;
            } else { // Users from 37 to 50 will complete course2. Time started = now - 7 days.
                $timestarted = $now - (7 * DAYSECS);
                unset($coursetocomplete[$this->course1->id]);
                $coursetocomplete[$this->course2->id] = 1;
            }

            foreach ($coursetocomplete as $courseid => $completiondate) {
                $completionrpl = new completion_completion(array('userid' => $this->users[$i]->id, 'course' => $courseid, 'timestarted' => $timestarted));
                $completionrpl->rpl = 'completed via rpl';
                $completionrpl->status = COMPLETION_STATUS_COMPLETEVIARPL;
                $completionrpl->mark_complete($now - ($completiondate * DAYSECS));
            }
        }
        $this->assertEquals(86, $DB->count_records('course_completions'));

        // Create cohort.
        $this->cohort = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_DYNAMIC));
        $this->assertTrue($DB->record_exists('cohort', array('id' => $this->cohort->id)));
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));

        // Create ruleset.
        $this->ruleset = cohort_rule_create_ruleset($this->cohort->draftcollectionid);
    }

    /**
     * Data provider for course completion date rule.
     */
    public function data_course_completion_date() {
        $data = array(
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN, 'date' => 0),  array('course1'), 36),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN, 'date' => -1), array('course1'), 15),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_BEFORE_PAST_DURATION, 'date' => 5),  array('course2'), 36),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_WITHIN_PAST_DURATION, 'date' => 2), array('course2'), 14),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_WITHIN_FUTURE_DURATION, 'date' => 6), array('course2'), 3),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_AFTER_FUTURE_DURATION, 'date' => 2), array('course2'), 2),
        );
        return $data;
    }

    /**
     * @dataProvider data_course_completion_date
     */
    public function test_coursecompletion_date_rule($params, $courses, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Process listofids.
        $listofids = array();
        foreach ($courses as $course) {
            $listofids[] = $this->{$course}->id;
        }

        /**
         * Course completion data per users in course2:
         * user51 -> time started: -1day  - time completed = +1 day(future)  - completion time: 2 days
         * user52 -> time started: -3days - time completed = +3 days(future) - completion time: 6 days
         * user53 -> time started: -5days - time completed = +5 days(future) - completion time: 10 days
         */
        if ($params['operator'] === COHORT_RULE_COMPLETION_OP_WITHIN_FUTURE_DURATION ||
            $params['operator'] === COHORT_RULE_COMPLETION_OP_AFTER_FUTURE_DURATION) {
            // Make completion in the future to test this rule.
            $now = time();
            $days = 1;
            for ($i = 51; $i <= self::TEST_COURSE_COUNT_USERS; $i++) {
                $timestarted = $now - ($days * DAYSECS);
                $completionrpl = new completion_completion(
                    array('userid' => $this->users[$i]->id, 'course' => $this->course2->id, 'timestarted' => $timestarted)
                );
                $completionrpl->rpl = 'completed via rpl';
                $completionrpl->status = COMPLETION_STATUS_COMPLETEVIARPL;
                $completionrpl->mark_complete($now + ($days * DAYSECS));
                $days = $days + 2;
            }
        }

        // Use totara_date_parse_from_format to transform a date to int as we do when creating this type of rules.
        if ($params['operator'] === COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN ||
            $params['operator'] === COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN ) {
            $format = get_string('datepickerlongyearparseformat', 'totara_core');
            $time = time() + ($params['date'] * DAYSECS);
            $params['date'] = totara_date_parse_from_format($format, date($format, $time));
        }

        // Create course completion rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'learning', 'coursecompletiondate', $params, $listofids, 'listofids');
        cohort_rules_approve_changes($this->cohort);

        // It should match:
        // 1. data1: 36 users who complete course1 before today.
        // 2. data2: 15 users who complete course1 after yesterday.
        // 3. data3: 36 users who complete course2 in the past 5 days.
        // 4. data4: 14 users who complete course2 in within the past 2 days.
        // 5. data5: 03 users who will complete course2 within the upcoming 6 days.
        // 6. data6: 02 users who will complete course2 after the upcoming 2 days.
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    /**
     * Data provider for course completion duration rule.
     */
    public function data_course_completion_duration() {
        $data = array(
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN, 'date' => 2),  array('course1'), 21),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN, 'date' => 10), array('course1'), 36),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN, 'date' => 1),  array('course1'), 0),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN, 'date' => 8), array('course1'), 15),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN, 'date' => 2), array('course1'), 36),
        );
        return $data;
    }

    /**
     * @dataProvider data_course_completion_duration
     */
    public function test_coursecompletion_duration_rule($params, $courses, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Process listofids.
        $listofids = array();
        foreach ($courses as $course) {
            $listofids[] = $this->{$course}->id;
        }

        // Create a completion duration rule to bring users that had completed course1 within duration of 2 days.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'learning', 'coursecompletionduration', $params, $listofids, 'listofids');
        cohort_rules_approve_changes($this->cohort);

        // It should match:
        // 1. data1: 21 users that have completed course1 in less than 2 days.
        // 2. data2: 36 users that have completed course1 in less than 10 days.
        // 3. data3: 0 users have completed course1 in less than 1 day.
        // 4. data4: 15 users have completed course1 in a period grater than 8 days.
        // 5. data5: 36 users have completed course1 in a period grater than 2 days.
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }

    /**
     * Data provider for course completion list rule.
     */
    public function data_course_completion_list() {
        $data = array(
            array(array('operator' => COHORT_RULE_COMPLETION_OP_NONE), array('course1', 'course2'), 4),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_ANY), array('course1', 'course2'), 50),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_NOTALL), array('course1', 'course2'), 14),
            array(array('operator' => COHORT_RULE_COMPLETION_OP_ALL), array('course1', 'course2'), 36),
        );
        return $data;
    }

    /**
     * @dataProvider data_course_completion_list
     */
    public function test_coursecompletion_list($params, $listofcourses, $usercount) {
        global $DB;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Process listofids.
        $listofids = array();
        foreach ($listofcourses as $course) {
            $listofids[] = $this->{$course}->id;
        }

        // Create rule.
        $this->cohort_generator->create_cohort_rule_params($this->ruleset, 'learning', 'coursecompletionlist', $params, $listofids, 'listofids');
        cohort_rules_approve_changes($this->cohort);

        // It should match:
        // 1. data1: the skipusers + Admin
        // 2. data2: users who complete both courses (course1: 36 users, course2:50) = 50
        // 3. data3: users that doesn't complete all courses (users who do not complete course1).
        // 4. data4: users have completed course1 and course2 = 36.
        $this->assertEquals($usercount, $DB->count_records('cohort_members', array('cohortid' => $this->cohort->id)));
    }
}
