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
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}
global $CFG;
require_once($CFG->dirroot . '/totara/core/lib/scheduler.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');

/**
 * Test report scheduler class
 */
class scheduler_test extends PHPUnit_Framework_TestCase {
    /**
     * Test basic scheduler functionality
     */
    public function test_scheduler_basic() {
        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $row = new stdClass();
        $row->data = 'Some data';
        $row->schedule = 0;
        $row->frequency = scheduler::DAILY;
        $row->nextevent = 100;
        $row->timezone = 'UTC';

        $scheduler = new scheduler($row);
        $timestamp = time();
        $scheduler->set_time($timestamp);
        $this->assertFalse($scheduler->is_changed());

        $scheduler->do_asap();
        $this->assertLessThan($timestamp, $scheduler->get_scheduled_time());
        $this->assertTrue($scheduler->is_changed());
        $this->assertTrue($scheduler->is_time());

        $scheduler->next($timestamp);
        $this->assertGreaterThan($timestamp, $scheduler->get_scheduled_time());
        $this->assertTrue($scheduler->is_changed());
        $this->assertFalse($scheduler->is_time());
        date_default_timezone_set($tz);
    }

    /**
     * Test plan for schedule estimations
     */
    public function schedule_plan() {
        $data = array(
            array(scheduler::DAILY, 10, 1389394800, 1389394800, 1389434400),
            array(scheduler::DAILY, 15, 1394202900, 1394202900, 1394204400),
            array(scheduler::DAILY, 15, 1394204400, 1394204400, 1394290800),
            array(scheduler::WEEKLY, 4, 1389484800, 1389484800, 1389830400),
            array(scheduler::WEEKLY, 5, 1394118600, 1394118600, 1394150400),
            array(scheduler::WEEKLY, 5, 1394205000, 1394205000, 1394150400),
            array(scheduler::WEEKLY, 5, 1394291400, 1394291400, 1394755200),
            array(scheduler::MONTHLY, 6, 1389052800, 1389052800, 1391644800),
            array(scheduler::MONTHLY, 31, 1391212800, 1391212800, 1393545600),
            array(scheduler::MONTHLY, 31, 1454284800, 1454284800, 1456704000),
            array(scheduler::MONTHLY, 29, 1394041665, 1394041665, 1396051200),
            array(scheduler::MONTHLY, 1, 1394041665, 1394041665, 1396310400),
            array(scheduler::MONTHLY, 5, 1394041665, 1394041665, 1393977600),
        );
        return $data;
    }
    /**
     * Test scheduler calculations
     * @dataProvider schedule_plan
     */
    public function test_scheduler_timing($frequency, $schedule, $currentevent, $currenttime, $expectedevent) {
        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $row = new stdClass();
        $row->data = 'Some data';
        $row->schedule = $schedule;
        $row->frequency = $frequency;
        $row->nextevent = $currentevent;
        $row->timezone = 'UTC';

        $scheduler = new scheduler($row);
        $scheduler->next($currenttime, false);
        $time = $scheduler->get_scheduled_time();
        $this->assertEquals($expectedevent, $time, "\n".$time.' = '.date('r ', $time)."\n");
        date_default_timezone_set($tz);
    }

    /**
     * Test scheduler mapping to db object row
     */
    public function test_scheduler_map() {
        $map = array(
            'nextevent' => 'test_event',
            'frequency' => 'test_frequency',
            'schedule' => 'test_schedule',
            'timezone' => 'test_timezone'
        );
        $row = new stdClass();
        $row->data = 'Some data';
        $row->test_schedule = 0;
        $row->test_frequency = 0;
        $row->test_event = 0;
        $row->test_timezone = 'Pacific/Auckland';

        $scheduler = new scheduler($row, $map);
        $scheduler->from_array(array(
            'frequency' => scheduler::DAILY,
            'schedule' => 10,
            'initschedule' => false,
            'timezone' => 'UTC'
        ));

        $this->assertTrue($scheduler->is_changed());
        $this->assertEquals(10, $row->test_schedule);
        $this->assertEquals(scheduler::DAILY, $row->test_frequency);
        $this->assertEquals($scheduler->get_scheduled_time(), $row->test_event);
        $this->assertEquals('UTC', $row->test_timezone);
    }
}