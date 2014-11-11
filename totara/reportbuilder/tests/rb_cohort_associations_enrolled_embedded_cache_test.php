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
 * Unit/functional tests to check Audience: Enrolled Learning reports caching
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');
require_once($CFG->dirroot . '/totara/cohort/lib.php');

class totara_reportbuilder_rb_cohort_associations_enrolled_embedded_cache_testcase extends reportcache_advanced_testcase {
    // testcase data
    protected $report_builder_data = array('id' => 5, 'fullname' => 'Audience: Enrolled Learning', 'shortname' => 'cohort_associations_enrolled',
                                           'source' => 'cohort_associations', 'hidden' => 1, 'embedded' => 1);

    protected $report_builder_columns_data = array(
                        array('id' => 21, 'reportid' => 5, 'type' => 'associations', 'value' => 'nameiconlink',
                              'heading' => 'A', 'sortorder' => 1),
                        array('id' => 22, 'reportid' => 5, 'type' => 'associations', 'value' => 'type',
                              'heading' => 'B', 'sortorder' => 2),
                        array('id' => 23, 'reportid' => 5, 'type' => 'associations', 'value' => 'programcompletionlink',
                              'heading' => 'C', 'sortorder' => 3),
                        array('id' => 24, 'reportid' => 5, 'type' => 'associations', 'value' => 'actionsenrolled',
                              'heading' => 'D', 'sortorder' => 4));

    protected $report_builder_filters_data = array(
                        array('id' => 10, 'reportid' => 5, 'type' => 'associations', 'value' => 'name',
                              'sortorder' => 1, 'advanced' => 0),
                        array('id' => 11, 'reportid' => 5, 'type' => 'associations', 'value' => 'type',
                              'sortorder' => 2, 'advanced' => 1));

    // Work data
    protected $users = array();
    protected $course1 = null;
    protected $course2 = null;
    protected $cohort1 = null;
    protected $cohort2 = null;

    protected static $ind = 0;

    /**
     * Prepare mock data for testing
     *
     * Common part of all test cases:
     * - Add 6 users(0-5)
     * - Add two cohorts
     * - Add two courses
     * - Add users0,2,3 to cohort1
     * - Add users1,2 to cohort2
     * - Enrol users4 to course1
     * - Users4-5 not in cohorts
     * - Course2 has no enrolments
     *
     */
    protected function setUp() {
        parent::setup();
        $this->setAdminUser();
        $this->getDataGenerator()->reset();
        // Common parts of test cases:
        // Create report record in database
        $this->loadDataSet($this->createArrayDataSet(array('report_builder' => array($this->report_builder_data),
                                                           'report_builder_columns' => $this->report_builder_columns_data,
                                                           'report_builder_filters' => $this->report_builder_filters_data)));
        for ($a = 0; $a <= 7; $a++) {
            $data = array('firstname' => 'User'.$a);
            $this->users[] = $this->getDataGenerator()->create_user($data);
        }

        $this->course1 = $this->getDataGenerator()->create_course(array('fullname'=> 'Into'));
        $this->course2 = $this->getDataGenerator()->create_course(array('fullname'=> 'Basics'));

        $this->cohort1 = $this->getDataGenerator()->create_cohort(array('cohorttype' => cohort::TYPE_STATIC));
        $this->cohort2 = $this->getDataGenerator()->create_cohort(array('cohorttype' => cohort::TYPE_STATIC));

        cohort_add_member($this->cohort1->id, $this->users[0]->id);
        cohort_add_member($this->cohort1->id, $this->users[2]->id);
        cohort_add_member($this->cohort1->id, $this->users[3]->id);
        cohort_add_member($this->cohort2->id, $this->users[1]->id);
        cohort_add_member($this->cohort2->id, $this->users[2]->id);

        $this->getDataGenerator()->enrol_user($this->users[4]->id, $this->course1->id);
    }

    /**
     * Test courses report
     * Test case:
     * - Common part (@see: self::setUp() )
     * - Add course1 to enrolled cohorts
     * - Check that course appeared in report
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_cohort_associations_enrolled($usecache) {
        $this->resetAfterTest();
        $this->create_enrol_cohort($this->cohort1->id, $this->course1->id);

        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }

        $result = $this->get_report_result($this->report_builder_data['shortname'],
                array('cohortid' => $this->cohort1->id), $usecache);
        $this->assertCount(1, $result);

        $r = array_shift($result);
        $this->assertEquals($this->course1->fullname, $r->associations_nameiconlink);
    }

    public function test_is_capable() {
        global $DB;
        $this->resetAfterTest();

        // Set up report and embedded object for is_capable checks.
        $syscontext = context_system::instance();
        $shortname = $this->report_builder_data['shortname'];
        $report = reportbuilder_get_embedded_report($shortname, array(), false, 0);
        $embeddedobject = $report->embedobj;
        $roleuser = $DB->get_record('role', array('shortname'=>'user'));
        $userid = $this->users[1]->id;

        // Test admin can access report.
        $this->assertTrue($embeddedobject->is_capable(2, $report),
                'admin cannot access report');

        // Test user cannot access report.
        $this->assertFalse($embeddedobject->is_capable($userid, $report),
                'user should not be able to access report');

        // Test user with only manage capability cannot access report.
        assign_capability('moodle/cohort:manage', CAP_ALLOW, $roleuser->id, $syscontext);
        $syscontext->mark_dirty();
        $this->assertFalse($embeddedobject->is_capable($userid, $report),
                'user with capability moodle/cohort:manage should not be able to access report');
        assign_capability('moodle/cohort:manage', CAP_INHERIT, $roleuser->id, $syscontext);

        // Test user with only view capability cannot access report.
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleuser->id, $syscontext);
        $syscontext->mark_dirty();
        $this->assertFalse($embeddedobject->is_capable($userid, $report),
                'user with capability moodle/cohort:view should not be able to access report');
        assign_capability('moodle/cohort:view', CAP_INHERIT, $roleuser->id, $syscontext);

        // Test user with both view and manage capability can access report.
        assign_capability('moodle/cohort:manage', CAP_ALLOW, $roleuser->id, $syscontext);
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleuser->id, $syscontext);
        $syscontext->mark_dirty();
        $this->assertTrue($embeddedobject->is_capable($userid, $report),
                'user with capability moodle/cohort:view and moodle/cohort:manage cannot access report');
        assign_capability('moodle/cohort:manage', CAP_INHERIT, $roleuser->id, $syscontext);
        assign_capability('moodle/cohort:view', CAP_INHERIT, $roleuser->id, $syscontext);
    }

    /**
     * Add mock of particular params to cohort rules
     *
     * @param int $cohortid
     * @param int $courseid
     */
    protected function create_enrol_cohort($cohortid, $courseid) {
        global $DB;
        self::$ind++;
        $todb = new stdClass();
        $todb->id = self::$ind;
        $todb->status = 1;
        $todb->courseid = $courseid;
        $todb->customint1 = $cohortid;
        $todb->sortorder = self::$ind;
        $todb->enrol = 'cohort';
        $DB->insert_record('enrol', $todb);
    }
}
