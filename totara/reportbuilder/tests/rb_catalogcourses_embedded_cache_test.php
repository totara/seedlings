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
 * Unit/functional tests to check faceted search of courses
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}
global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');

class totara_reportbuilder_rb_catalogcourses_embedded_cache_testcase extends reportcache_advanced_testcase {
    // Testcase data.
    protected $report_builder_data = array('id' => 59, 'fullname' => 'Courses', 'shortname' => 'catalogcourses',
                                           'source' => 'courses', 'hidden' => 1, 'embedded' => 1,
                                           'toolbarsearch' => 1);

    protected $report_builder_columns_data = array(
            array('id' => 249, 'reportid' => 59, 'type' => 'course', 'value' => 'courselinkicon',
                  'heading' => 'A', 'sortorder' => 1),
            array('id' => 250, 'reportid' => 59, 'type' => 'course', 'value' => 'startdate',
                  'heading' => 'B', 'sortorder' => 2),
            array('id' => 251, 'reportid' => 59, 'type' => 'course', 'value' => 'mods',
                  'heading' => 'C', 'sortorder' => 3));

    protected $report_builder_filters_data = array(
            array('id' => 171, 'reportid' => 59, 'type' => 'course', 'value' => 'coursetype',
                  'sortorder' => 1, 'advanced' => 0),
            array('id' => 172, 'reportid' => 59, 'type' => 'course', 'value' => 'mods',
                  'sortorder' => 2, 'advanced' => 1));

    protected $report_builder_cf_filters_data = array(
            array('id' => 173, 'reportid' => 59, 'type' => 'course', 'value' => 'custom_field_?_text',
                  'sortorder' => 3, 'advanced' => 1),
            array('id' => 174, 'reportid' => 59, 'type' => 'course', 'value' => 'custom_field_?_text',
                  'sortorder' => 4, 'advanced' => 0));

    // Work data.
    protected $user1 = null;
    protected $user2 = null;
    protected $course1 = null;
    protected $course2 = null;
    protected $course3 = null;
    protected $course4 = null;

    /**
     * Prepare mock data for testing
     *
     * Common part of all test cases:
     * - Add four courses
     */
    protected function setUp() {
        parent::setup();

        // Common parts of test cases:
        // Create report record in database.
        $this->loadDataSet($this->createArrayDataSet(
            array('report_builder' => array($this->report_builder_data),
                  'report_builder_columns' => $this->report_builder_columns_data,
                  'report_builder_filters' => $this->report_builder_filters_data)));

        $this->course1 = $this->getDataGenerator()->create_course(array('fullname'=> 'Intro'));
        $this->course2 = $this->getDataGenerator()->create_course(array('fullname'=> 'Basics'));
        $this->course3 = $this->getDataGenerator()->create_course(array('fullname'=> 'Advanced'));
        $this->course4 = $this->getDataGenerator()->create_course(array('fullname'=> 'Pro'));
    }

    /**
     * Test courses report
     * Test case:
     * - Add two multi-select customfields (cf1 and cf2)
     * - Add two options to each multi-select customfield (op1cf1, op2cf1, op1cf2, op2cf2)
     * - Enable op1cf1 for course1
     * - Enable op1cf1 and op1cf2 for course2
     * - Enable op1cf1, op2cf1, op1cf2, op2cf2 for course3
     * - Course4 has no enabled customfield options
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_courses($usecache) {
        $this->resetAfterTest();

        $cfids = $this->getDataGenerator()->add_multiselect_cf(array('cf1' => array('op1cf1', 'op2cf1'),
                    'cf2' => array('op1cf2', 'op2cf2')), 'course');
        $this->getDataGenerator()->set_multiselect_cf($this->course1, $cfids['cf1'],
                array('op1cf1'), 'course', 'course');
        $this->getDataGenerator()->set_multiselect_cf($this->course2, $cfids['cf1'],
                array('op2cf1'), 'course', 'course');
        $this->getDataGenerator()->set_multiselect_cf($this->course2, $cfids['cf2'],
                array('op2cf2'), 'course', 'course');
        $this->getDataGenerator()->set_multiselect_cf($this->course3, $cfids['cf1'],
                array('op1cf1', 'op2cf1'), 'course', 'course');
        $this->getDataGenerator()->set_multiselect_cf($this->course3, $cfids['cf2'],
                array('op1cf2', 'op2cf2'), 'course', 'course');

        // Add CF filters.
        $this->report_builder_cf_filters_data[0]['value'] = "custom_field_{$cfids['cf1']}_text";
        $this->report_builder_cf_filters_data[1]['value'] = "custom_field_{$cfids['cf2']}_text";
        $this->loadDataSet($this->createArrayDataSet(array(
                        'report_builder_filters' => $this->report_builder_cf_filters_data)));

        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }

        // No restrictions.
        $result = $this->get_report_result($this->report_builder_data['shortname'], array(),
                $usecache);
        $this->assertCount(4, $result);

        // Check one cf, one option.
        $form = array(
            "course-custom_field_{$cfids['cf1']}_text" =>
                array('operator' => 1, 'value' => array(md5('op1cf1') => 1)));

        $result = $this->get_report_result($this->report_builder_data['shortname'], array(),
                $usecache, $form);
        $this->assertCount(2, $result);
        foreach ($result as $res) {
            $this->assertTrue(in_array($res->course_courselinkicon,
                    array('Intro', 'Advanced')));
        }
        // Check both cf.
        $form = array("course-custom_field_{$cfids['cf1']}_text" =>
                        array('operator' => 1, 'value' => array(md5('op2cf1') => 1)),
                      "course-custom_field_{$cfids['cf2']}_text" =>
                        array('operator' => 1, 'value' => array(md5('op2cf2') => 1)));

        $result = $this->get_report_result($this->report_builder_data['shortname'], array(),
                $usecache, $form);
        $this->assertCount(2, $result);
        foreach ($result as $res) {
            $this->assertTrue(in_array($res->course_courselinkicon,
                    array('Basics', 'Advanced')));
        }

        // Check only second cf, both options.
        $form = array("course-custom_field_{$cfids['cf1']}_text" =>
                        array('operator' => 1, 'value' => array(md5('op1cf1') => 1)),
                      "course-custom_field_{$cfids['cf2']}_text" =>
                        array('operator' => 1,
                              'value' => array(md5('op1cf2') => 1, md5('op2cf2') => 1)));

        $result = $this->get_report_result($this->report_builder_data['shortname'], array(),
                $usecache, $form);
        $this->assertCount(1, $result);
        foreach ($result as $res) {
            $this->assertTrue(in_array($res->course_courselinkicon,
                    array('Advanced')));
        }
    }
}
