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
 */
global $CFG;

require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');
//require_once ($CFG->dirroot.'/totara/program/rb_sources/rb_source_program_overview.php');
class totara_program_rb_source_program_overview_testcase extends reportcache_advanced_testcase {
    protected $load = 0;
    protected $report_builder_data = array('id' => 123, 'fullname' => 'Program Overview', 'shortname' => 'report_program_overview',
                                           'source' => 'program_overview', 'hidden' => 0, 'embedded' => 0, 'accessmode' => 1);


    protected $report_builder_columns_data = array(
                        array('id' => 181, 'reportid' => 123, 'type' => 'prog', 'value' => 'shortname',
                              'heading' => 'A', 'sortorder' => 1),
                        array('id' => 182, 'reportid' => 123, 'type' => 'user', 'value' => 'organisation',
                              'heading' => 'B', 'sortorder' => 2),
                        array('id' => 183, 'reportid' => 123, 'type' => 'user', 'value' => 'position',
                              'heading' => 'C', 'sortorder' => 3),
                        array('id' => 184, 'reportid' => 123, 'type' => 'user', 'value' => 'namelink',
                              'heading' => 'D', 'sortorder' => 4),
                        array('id' => 185, 'reportid' => 123, 'type' => 'program_completion', 'value' => 'status',
                              'heading' => 'E', 'sortorder' => 5),
                        array('id' => 186, 'reportid' => 123, 'type' => 'program_completion', 'value' => 'timedue',
                              'heading' => 'F', 'sortorder' => 6),
                        array('id' => 187, 'reportid' => 123, 'type' => 'program_completion', 'value' => 'progress',
                              'heading' => 'G', 'sortorder' => 7),
                        array('id' => 188, 'reportid' => 123, 'type' => 'course', 'value' => 'shortname',
                              'heading' => 'H', 'sortorder' => 8),
                        array('id' => 189, 'reportid' => 123, 'type' => 'course', 'value' => 'status',
                              'heading' => 'I', 'sortorder' => 9),
                        array('id' => 190, 'reportid' => 123, 'type' => 'course', 'value' => 'finalgrade',
                              'heading' => 'J', 'sortorder' => 10));

    protected $report_builder_filters_data = array(
                        array('id' => 222, 'reportid' => 123, 'type' => 'prog', 'value' => 'id',
                              'sortorder' => 1, 'advanced' => 0));

    public function setUp() {
        if (getenv('load')) {
            $this->load = true;
        }
        $this->loadDataSet($this->createArrayDataSet(array('report_builder' => array($this->report_builder_data),
                                                           'report_builder_columns' => $this->report_builder_columns_data,
                                                           'report_builder_filters' => $this->report_builder_filters_data)));
    }

    protected function output_log($message) {
        if ($this->load) {
            print_r(date("h:i:s ").$message."\n");
            ob_flush();
        }
    }
    /**
     * This load test will not normally run in test suite
     * It doesn't test data consistency, but only performance.
     * Assignment of 10k users to 1 program takes about 5min (10 programs will take about one hour) on Core i7 with SSD in pgsql.
     * Report itself takes considerably small amount of time (less then 1 minute on i7+SSF).
     *
     * To run it set load=1 environment variable for phpunit:
     * @example :
     * env load=1 phpunit --filter rb_source_program_overview_test::test_load_overview
     */
    public function test_load_overview() {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $usernum = 10;
        $coursenum = 2;
        $programnum = 2;
        if ($this->load) {
            $usernum = 10000;
            $coursenum = 10;
            $programnum = 10;
        }
        $users = array();
        $programs = array();
        $courses = array();

        // Redirect event handlers (mail sending) as they take more than 16 hours to perform for 10000 users * 10 programs.
        $sink = $this->redirectEvents();

        $this->output_log("Create users ($usernum)...");
        for ($i = 0; $i < $usernum; $i++) {
            $user =  $this->getDataGenerator()->create_user();
            $users[$user->id] = $user;
        }

        $this->output_log("Create courses ($coursenum)...");
        for ($i = 0; $i < $coursenum; $i++) {
           $course = $this->getDataGenerator()->create_course();
           $courses[$course->id] = $course;
        }
        for ($prgcnt = 1; $prgcnt <= $programnum; $prgcnt++) {
            $starttime = time();
            $this->output_log("Create programs ({$prgcnt} of $programnum)...");
            $programs[$prgcnt] = $this->getDataGenerator()->create_program();
            // Add courses to programs.
            $this->getDataGenerator()->add_courseset_program($programs[$prgcnt]->id, array_keys($courses));

            // Add users to programs.
            $this->getDataGenerator()->assign_program($programs[$prgcnt]->id, array_keys($users));

            $left = (time() - $starttime) * ($programnum - $prgcnt);
            $togo = ceil($left / 60).' min';
            $this->output_log("Done. Approx. time left: $togo...");
        }
        $this->output_log("Start report testing...");
        $usecache = 0;
        $startime = microtime();
        $result = $this->get_report_result($this->report_builder_data['id'], array(), $usecache);
        $total = count($result);
        $duration = microtime_diff($startime, microtime());
        $this->output_log("Records: $total \n Report generated in: $duration sec");
        $this->assertEquals($usernum * $programnum, $total);

        $this->output_log("Start report testing...");
        $usecache = 1;
        $this->enable_caching($this->report_builder_data['id']);
        $startime = microtime();
        $result = $this->get_report_result($this->report_builder_data['id'], array(), $usecache);
        $total = count($result);
        $duration = microtime_diff($startime, microtime());
        $this->output_log("Records: $total \n Report generated in: $duration sec");
        $this->assertEquals($usernum * $programnum, $total);
    }
}
