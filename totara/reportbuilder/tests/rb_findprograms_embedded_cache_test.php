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
 * Unit/functional tests to check Find Programs reports caching
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');


class totara_reportbuilder_rb_findprograms_embedded_cache_testcase extends reportcache_advanced_testcase {
    // testcase data
    protected $report_builder_data = array('id' => 7, 'fullname' => 'Find Programs', 'shortname' => 'findprograms',
                                           'source' => 'program', 'hidden' => 1, 'embedded' => 1);

    protected $report_builder_columns_data = array(
                        array('id' => 29, 'reportid' => 7, 'type' => 'prog', 'value' => 'proglinkicon',
                              'heading' => 'A', 'sortorder' => 1),
                        array('id' => 30, 'reportid' => 7, 'type' => 'course_category', 'value' => 'namelink',
                              'heading' => 'B', 'sortorder' => 2));

    protected $report_builder_filters_data = array(
                        array('id' => 16, 'reportid' => 7, 'type' => 'prog', 'value' => 'fullname',
                              'sortorder' => 1, 'advanced' => 0),
                        array('id' => 17, 'reportid' => 7, 'type' => 'course_category', 'value' => 'id',
                              'sortorder' => 2, 'advanced' => 1));

    // Work data
    protected $program1 = null;
    protected $program2 = null;
    protected $program3 = null;

    /**
     * Prepare mock data for testing
     *
     * Common part of all test cases:
     * - Add three programs
     * - Program1 and program2 have word 'level' in fullname
     */
    protected function setUp() {
        parent::setup();
        $this->loadDataSet($this->createArrayDataSet(array('report_builder' => array($this->report_builder_data),
                                                           'report_builder_columns' => $this->report_builder_columns_data,
                                                           'report_builder_filters' => $this->report_builder_filters_data)));

        $this->program1 = $this->getDataGenerator()->create_program(array('fullname'=> 'Program level 1'));
        $this->program2 = $this->getDataGenerator()->create_program(array('fullname'=> 'Program 2'));
        $this->program3 = $this->getDataGenerator()->create_program(array('fullname'=> 'Program level 3'));

        $this->user1 = $this->getDataGenerator()->create_user();
    }

    /**
     * Test programs report
     * Test case:
     * - Common part (@see: self::setUp() )
     * - Find all programs
     * - Find programs with word 'level' in fullname
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_findprograms($usecache) {
        $this->resetAfterTest();
        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }

        $result = $this->get_report_result($this->report_builder_data['shortname'], array(), $usecache);
        $this->assertCount(3, $result);

        $form = array('prog-fullname' => array('operator' => 0, 'value' => 'level'));
        $result = $this->get_report_result($this->report_builder_data['shortname'], array(), $usecache, $form);
        $this->assertCount(2, $result);
        $was = array();
        foreach ($result as $r) {
             $this->assertStringMatchesFormat('Program level %d', $r->prog_proglinkicon);
             $this->assertNotContains($r->id, $was);
             $was[] = $r->id;
        }
    }

    public function test_is_capable() {
        $this->resetAfterTest();

        // Set up report and embedded object for is_capable checks.
        $shortname = $this->report_builder_data['shortname'];
        $report = reportbuilder_get_embedded_report($shortname, array(), false, 0);
        $embeddedobject = $report->embedobj;
        $userid = $this->user1->id;

        // Test admin can access report.
        $this->assertTrue($embeddedobject->is_capable(2, $report),
                'admin cannot access report');

        // Test user can access report.
        $this->assertTrue($embeddedobject->is_capable($userid, $report),
                'user cannot access report');
    }
}
