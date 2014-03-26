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
 * Unit/functional tests to check Bookings reports caching
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot . '/mod/facetoface/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');

class rb_bookings_embedded_cache_test extends reportcache_advanced_testcase {
    // testcase data
    protected $report_builder_data = array(
        'id' => 8, 'fullname' => 'My Bookings', 'shortname' => 'bookings',
        'source' => 'facetoface_sessions', 'hidden' => 1, 'embedded' => 1, 'contentmode' => 2
    );

    protected $report_builder_columns_data = array(
        array('id' => 31, 'reportid' => 8, 'type' => 'course', 'value' => 'courselink',
              'heading' => 'A', 'sortorder' => 1),
        array('id' => 32, 'reportid' => 8, 'type' => 'facetoface', 'value' => 'name',
              'heading' => 'B', 'sortorder' => 2),
        array('id' => 33, 'reportid' => 8, 'type' => 'date', 'value' => 'sessiondate',
              'heading' => 'C', 'sortorder' => 3),
        array('id' => 34, 'reportid' => 8, 'type' => 'date', 'value' => 'timestart',
              'heading' => 'D', 'sortorder' => 4),
        array('id' => 35, 'reportid' => 8, 'type' => 'date', 'value' => 'timefinish',
              'heading' => 'E', 'sortorder' => 5),
        array('id' => 36, 'reportid' => 8, 'type' => 'status', 'value' => 'statuscode',
              'heading' => 'F', 'sortorder' => 6)
    );

    protected $report_builder_settings_data = array(
        array('id' => 1, 'reportid' => 8, 'type' => 'date_content', 'name' => 'enable', 'value' => '1'),
        array('id' => 2, 'reportid' => 8, 'type' => 'date_content',  'name' => 'when', 'value' => 'future')
    );

    protected $delta = 3600;

    // Work data
    protected $user1 = null;
    protected $user2 = null;
    protected $user3 = null;
    protected $user4 = null;
    protected $course1 = null;
    protected $course2 = null;
    protected static $ind = 0;

    /**
     * Prepare mock data for testing
     *
     * Common part of all test cases:
     * - Add four users
     * - Add two courses
     * - Enrol first three users to course1
     * - Enrol user3 and user4 to course2
     * - Create two bookings to user1, one for to user2, and one for user3
     * - Create four bookings (for each users in inverted time
     */
    protected function setUp() {
        global $POSITION_CODES, $POSITION_TYPES;

        parent::setup();
        $this->setAdminUser();
        // Common parts of test cases:
        // Create report record in database
        $this->loadDataSet($this->createArrayDataSet(array('report_builder' => array($this->report_builder_data),
                                                           'report_builder_columns' => $this->report_builder_columns_data,
                                                           'report_builder_settings' => $this->report_builder_settings_data)));
        // Create four users
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->user3 = $this->getDataGenerator()->create_user();
        $this->user4 = $this->getDataGenerator()->create_user();

        // Create two courses
        $this->course1 = $this->getDataGenerator()->create_course(array(), array('createsections' => true));
        $this->course2 = $this->getDataGenerator()->create_course(array(), array('createsections' => true));

        // Enrol first three users to course1
        // Enrol user3 and user4 to course2
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course1->id);
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course1->id);
        $this->getDataGenerator()->enrol_user($this->user3->id, $this->course1->id);
        $this->getDataGenerator()->enrol_user($this->user3->id, $this->course2->id);
        $this->getDataGenerator()->enrol_user($this->user4->id, $this->course2->id);

        // Create two bookings to user1, one for to user2, and one for user3
        $this->create_booking($this->user1, $this->user2, $this->course1);
        $this->create_booking($this->user1, $this->user3, $this->course1);

        // Assign user2 to be user1's manager and remove viewallmessages from manager role.
        $assignment = new position_assignment(
            array(
                'userid'    => $this->user1->id,
                'type'      => $POSITION_CODES[reset($POSITION_TYPES)]
            )
        );
        $assignment->managerid = $this->user2->id;
        assign_user_position($assignment, true);
    }

     /**
     * Create mock booking
     *
     * @param stdClass $initiator First attendee
     * @param stdClass $attender Second attendee
     * @param stdClass $course Course record
     * @param bool $timeinverse Invert future time to past
     */
    protected function create_booking($initiator, $attender, $course, $timeinverse = false) {
        self::$ind++;

        // Create activity
        $facetoface = new stdClass();
        $facetoface->id = self::$ind;
        $facetoface->course = $course->id;
        $facetoface->name = 'Name '.self::$ind;
        $facetoface->display = 6;
        $facetoface->reminderperiod = 2;
        $facetoface->timecreated = time();
        $facetoface->showoncal = 1;
        facetoface_add_instance($facetoface);

        // Add session
        $session = new stdClass();
        $session->id = self::$ind;
        $session->facetoface = $facetoface->id;
        $session->datetimeknown = 1;
        $session->capacity = 10;
        $session->allowoverbook = 0;
        $session->duration = 1;
        $session->normalcost = 0;
        $session->discountcost = 0;
        $session->roomid = 1;
        $session->usermodified = 2;

        $delta = ($timeinverse) ? -1 * $this->delta : $this->delta;
        $dates = new stdClass();
        $dates->sessionid = self::$ind;
        $dates->timestart = time() + $delta;
        $dates->timefinish = time() + $delta + abs($delta) * 0.5;
        $dates->sessiontimezone = 'Europe/London';
        $sessiondates = array($dates);
        facetoface_add_session($session, $sessiondates);

        $session->sessiondates = $sessiondates;
        facetoface_update_calendar_entries($session, $facetoface);

        // Signup for session
        facetoface_user_signup($session, $facetoface, $course, 0, MDL_F2F_INVITE, MDL_F2F_STATUS_BOOKED,
            $initiator->id, false);
        facetoface_user_signup($session, $facetoface, $course, 0, MDL_F2F_INVITE, MDL_F2F_STATUS_BOOKED,
            $attender->id, false);
    }

    /**
     * Test bookings with/without using cache
     * Test case:
     * - Common part (@see: self::setUp() )
     * - Check bookings for first user (2)
     * - Check bookings for second user (1)
     * - Check bookings for fourth user (0)
     *
     * @param int Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_bookings($usecache) {
        $this->resetAfterTest();
        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }
        $courseidalias = reportbuilder_get_extrafield_alias('course', 'courselink', 'course_id');
        $result = $this->get_report_result($this->report_builder_data['shortname'], array('userid' => $this->user1->id), $usecache);
        $this->assertCount(2, $result);
        foreach ($result as $r) {
            $this->assertEquals($this->course1->id, $r->$courseidalias);
        }
        $result = $this->get_report_result($this->report_builder_data['shortname'], array('userid' => $this->user2->id), $usecache);
        $this->assertCount(1, $result);
        foreach ($result as $r) {
            $this->assertEquals($this->course1->id, $r->$courseidalias);
        }
        $result = $this->get_report_result($this->report_builder_data['shortname'], array('userid' => $this->user4->id), $usecache);
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

        // Test user1's manager can access report.
        $this->assertTrue($embeddedobject->is_capable($this->user2->id, $report),
                'manager cannot access report');

        // Test that user3 cannot access the report for another user.
        $this->assertFalse($embeddedobject->is_capable($this->user3->id, $report),
                'user should not be able to access another user\'s report');
    }
}
