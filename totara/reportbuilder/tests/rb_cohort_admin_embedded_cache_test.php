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
 * Unit/functional tests to check Audiences Admin Screen reports caching
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');
require_once($CFG->dirroot . '/totara/cohort/lib.php');
require_once($CFG->dirroot . '/totara/cohort/rules/lib.php');

class totara_reportbuilder_rb_cohort_admin_embedded_cache_testcase extends reportcache_advanced_testcase {
    // Testcase data
    protected $report_builder_data = array('id' => 1, 'fullname' => 'Audience Admin Screen', 'shortname' => 'cohort_admin',
                                           'source' => 'cohort', 'hidden' => 1, 'embedded' => 1);

    protected $report_builder_columns_data = array(
                        array('id' => 1, 'reportid' => 1, 'type' => 'cohort', 'value' => 'namelink',
                              'heading' => 'A', 'sortorder' => 1),
                        array('id' => 2, 'reportid' => 1, 'type' => 'cohort', 'value' => 'idnumber',
                              'heading' => 'B', 'sortorder' => 2),
                        array('id' => 3, 'reportid' => 1, 'type' => 'cohort', 'value' => 'numofmembers',
                              'heading' => 'C', 'sortorder' => 3),
                        array('id' => 4, 'reportid' => 1, 'type' => 'cohort', 'value' => 'type',
                              'heading' => 'D', 'sortorder' => 4),
                        array('id' => 5, 'reportid' => 1, 'type' => 'cohort', 'value' => 'startdate',
                              'heading' => 'A', 'sortorder' => 5),
                        array('id' => 6, 'reportid' => 1, 'type' => 'cohort', 'value' => 'enddate',
                              'heading' => 'B', 'sortorder' => 6),
                        array('id' => 7, 'reportid' => 1, 'type' => 'cohort', 'value' => 'status',
                              'heading' => 'C', 'sortorder' => 7),
                        array('id' => 8, 'reportid' => 1, 'type' => 'cohort', 'value' => 'actions',
                              'heading' => 'D', 'sortorder' => 8));

    protected $report_builder_filters_data = array(
                        array('id' => 1, 'reportid' => 1, 'type' => 'cohort', 'value' => 'name',
                              'sortorder' => 1, 'advanced' => 0),
                        array('id' => 2, 'reportid' => 1, 'type' => 'cohort', 'value' => 'idnumber',
                              'sortorder' => 2, 'advanced' => 1),
                        array('id' => 3, 'reportid' => 1, 'type' => 'cohort', 'value' => 'type',
                              'sortorder' => 3, 'advanced' => 1));

    // Work data
    protected $users = array();
    protected $course1 = null;
    protected $course2 = null;
    protected $cohort1 = null;
    protected $cohort2 = null;
    protected $cohort3 = null;

    /**
     * Prepare mock data for testing
     *
     * Common part of all test cases:
     * - Add 8 users
     * - Create 3 static cohorts
     * - Add users1,4,6 to set cohort1
     * - Add User2,3,4,5 to cohort2 using thier firstname
     * - Cohor three without members
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
        $this->cohort2 = $this->getDataGenerator()->create_cohort(array('cohorttype' => cohort::TYPE_DYNAMIC));
        $this->cohort3 = $this->getDataGenerator()->create_cohort(array('cohorttype' => cohort::TYPE_DYNAMIC));

        cohort_add_member($this->cohort1->id, $this->users[1]->id);
        cohort_add_member($this->cohort1->id, $this->users[4]->id);
        cohort_add_member($this->cohort1->id, $this->users[6]->id);

        // create collection
        $rulesetid = cohort_rule_create_ruleset($this->cohort2->draftcollectionid);
        $ruleid = cohort_rule_create_rule($rulesetid, 'user', 'firstname');
        $values = array($this->users[2]->firstname,
                      $this->users[3]->firstname,
                      $this->users[4]->firstname,
                      $this->users[5]->firstname);
        $this->getDataGenerator()->create_cohort_rule_params($ruleid, array('equal' => COHORT_RULES_OP_IN_ISEQUALTO), $values);
        cohort_rules_approve_changes($this->cohort2);
    }

    /**
     * Test courses report
     * Test case:
     * - Common part (@see: self::setUp() )
     * - Find all cohorts
     * - Check that cohort1 has three members
     * - Check that cohort2 has four memebrs
     * - Check that cohort3 has zero members
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_cohort_admin($usecache) {
        $this->resetAfterTest();
        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }
        $result = $this->get_report_result($this->report_builder_data['shortname'], array(), $usecache);
        $this->assertCount(3, $result);
        $was = array();
        foreach ($result as $r) {
            $this->assertContains($r->cohort_namelink, array($this->cohort1->name, $this->cohort2->name, $this->cohort3->name));
            $this->assertNotContains($r->cohort_namelink, $was);
            $was[] = $r->cohort_namelink;

            switch ($r->id) {
                case $this->cohort1->id:
                    $this->assertEquals(3, $r->cohort_numofmembers);
                break;
                case $this->cohort2->id:
                    $this->assertEquals(4, $r->cohort_numofmembers);
                break;
                case $this->cohort3->id;
                    $this->assertEquals(0, $r->cohort_numofmembers);
                break;
            }
        }
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

        // Test user with manage capability can access report.
        assign_capability('moodle/cohort:manage', CAP_ALLOW, $roleuser->id, $syscontext);
        $syscontext->mark_dirty();
        $this->assertTrue($embeddedobject->is_capable($userid, $report),
                'user with capability moodle/cohort:manage cannot access report');
        assign_capability('moodle/cohort:manage', CAP_INHERIT, $roleuser->id, $syscontext);

        // Test user with view capability can access report.
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleuser->id, $syscontext);
        $syscontext->mark_dirty();
        $this->assertTrue($embeddedobject->is_capable($userid, $report),
                'user with capability moodle/cohort:view cannot access report');
        assign_capability('moodle/cohort:view', CAP_INHERIT, $roleuser->id, $syscontext);
    }
}
