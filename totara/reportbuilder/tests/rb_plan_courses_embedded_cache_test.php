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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 *
 * Unit/functional tests to check Record of Learning: Courses reports caching
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');

class totara_reportbuilder_rb_plan_courses_embedded_cache_testcase extends reportcache_advanced_testcase {
    // testcase data
    protected $report_builder_data = array('id' => 11, 'fullname' => 'Record of Learning: Courses', 'shortname' => 'plan_courses',
                                           'source' => 'dp_course', 'hidden' => 1, 'embedded' => 1);

    protected $report_builder_columns_data = array(
                        array('id' => 49, 'reportid' => 11, 'type' => 'course', 'value' => 'coursetypeicon',
                              'heading' => 'A', 'sortorder' => 1),
                        array('id' => 50, 'reportid' => 11, 'type' => 'course', 'value' => 'courselink',
                              'heading' => 'B', 'sortorder' => 2),
                        array('id' => 51, 'reportid' => 11, 'type' => 'plan', 'value' => 'planlink',
                              'heading' => 'C', 'sortorder' => 3),
                        array('id' => 52, 'reportid' => 11, 'type' => 'plan', 'value' => 'courseduedate',
                              'heading' => 'D', 'sortorder' => 4),
                        array('id' => 53, 'reportid' => 11, 'type' => 'course_completion', 'value' => 'statusandapproval',
                              'heading' => 'E', 'sortorder' => 5));

    protected $report_builder_filters_data = array(
                        array('id' => 22, 'reportid' => 11, 'type' => 'course', 'value' => 'courselink',
                              'sortorder' => 1, 'advanced' => 0),
                        array('id' => 23, 'reportid' => 11, 'type' => 'plan', 'value' => 'name',
                              'sortorder' => 2, 'advanced' => 1),
                        array('id' => 24, 'reportid' => 11, 'type' => 'plan', 'value' => 'courseduedate',
                              'sortorder' => 3, 'advanced' => 1));

    // Work data
    protected $user1 = null;
    protected $user2 = null;
    protected $user3 = null;
    protected $course1 = null;
    protected $course2 = null;
    protected $course3 = null;
    protected $course4 = null;

    /**
     * Prepare mock data for testing
     *
     * Common part of all test cases:
     * - Add 3 users
     * - Create four courses
     * - Enrol user1 to course1,3
     * - Enrol user2 to course2,3,4
     * - User3 is not enroled
     *
     */
    protected function setUp() {
        global $DB, $POSITION_CODES, $POSITION_TYPES;

        parent::setup();
        $this->setAdminUser();
        $this->getDataGenerator()->reset();
        // Common parts of test cases:
        // Create report record in database
        $this->loadDataSet($this->createArrayDataSet(array('report_builder' => array($this->report_builder_data),
                                                           'report_builder_columns' => $this->report_builder_columns_data,
                                                           'report_builder_filters' => $this->report_builder_filters_data)));
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->user3 = $this->getDataGenerator()->create_user();
        $this->user4 = $this->getDataGenerator()->create_user();

        $this->course1 = $this->getDataGenerator()->create_course();
        $this->course2 = $this->getDataGenerator()->create_course();
        $this->course3 = $this->getDataGenerator()->create_course();
        $this->course4 = $this->getDataGenerator()->create_course();

        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course1->id);
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course3->id);
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course2->id);
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course3->id);
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course4->id);

        $syscontext = context_system::instance();

        // Assign user2 to be user1's manager and remove viewallmessages from manager role.
        $assignment = new position_assignment(
            array(
                'userid'    => $this->user1->id,
                'type'      => $POSITION_CODES[reset($POSITION_TYPES)]
            )
        );
        $assignment->managerid = $this->user2->id;
        assign_user_position($assignment, true);
        $rolemanager = $DB->get_record('role', array('shortname'=>'manager'));
        assign_capability('totara/plan:accessanyplan', CAP_PROHIBIT, $rolemanager->id, $syscontext);

        // Assign user3 to course creator role and add viewallmessages to course creator role.
        $rolecoursecreator = $DB->get_record('role', array('shortname'=>'coursecreator'));
        role_assign($rolecoursecreator->id, $this->user3->id, $syscontext);
        assign_capability('totara/plan:accessanyplan', CAP_ALLOW, $rolecoursecreator->id, $syscontext);

        $syscontext->mark_dirty();
    }

    /**
     * Test courses report
     * Test case:
     * - Common part (@see: self::setUp() )
     * - Check that user1 has two courses (1 and 3)
     * - Check that user2 has three course (2,3,4)
     * - Check that user3 doesn't have any courses
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_plan_courses($usecache) {
        $this->resetAfterTest();
        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }
        $courseidalias = reportbuilder_get_extrafield_alias('course', 'courselink', 'course_id');
        $result = $this->get_report_result($this->report_builder_data['shortname'], array('userid' => $this->user1->id,), $usecache);
        $this->assertCount(2, $result);
        $was = array();
        foreach($result as $r) {
            $this->assertContains($r->$courseidalias, array($this->course1->id, $this->course3->id));
            $this->assertNotContains($r->course_courselink, $was);
            $was[] = $r->course_courselink;
        }

        $result = $this->get_report_result($this->report_builder_data['shortname'], array('userid' => $this->user2->id,), $usecache);
        $this->assertCount(3, $result);
        $was = array();
        foreach($result as $r) {
            $this->assertContains($r->$courseidalias, array($this->course2->id, $this->course3->id, $this->course4->id));
            $this->assertNotContains($r->course_courselink, $was);
            $was[] = $r->course_courselink;
        }

        $result = $this->get_report_result($this->report_builder_data['shortname'], array('userid' => $this->user3->id,), $usecache);
        $this->assertCount(0, $result);
    }

    public function test_is_capable() {
        $this->resetAfterTest();

        // Set up report and embedded object for is_capable checks.
        $shortname = $this->report_builder_data['shortname'];
        $report = reportbuilder_get_embedded_report($shortname, array('userid' => $this->user1->id), false, 0);
        $embeddedobject = $report->embedobj;

        // Test admin can access report.
        $this->assertTrue($embeddedobject->is_capable(2, $report),
                'admin cannot access report');

        // Test user1 can access report for self.
        $this->assertTrue($embeddedobject->is_capable($this->user1->id, $report),
                'user cannot access their own report');

        // Test user1's manager can access report (we have removed accessanyplan from manager role).
        $this->assertTrue($embeddedobject->is_capable($this->user2->id, $report),
                'manager cannot access report');

        // Test user3 can access report using accessanyplan (we give 'coursecreator' role access to accessanyplan).
        $this->assertTrue($embeddedobject->is_capable($this->user3->id, $report),
                'user with accessanyplan cannot access report');

        // Test that user4 cannot access the report for another user.
        $this->assertFalse($embeddedobject->is_capable($this->user4->id, $report),
                'user should not be able to access another user\'s report');
    }
}
