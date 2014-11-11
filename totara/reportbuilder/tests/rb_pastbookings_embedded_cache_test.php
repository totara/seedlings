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
require_once($CFG->dirroot . '/totara/reportbuilder/tests/rb_bookings_embedded_cache_test.php');

class totara_reportbuilder_rb_pastbookings_embedded_cache_testcase extends totara_reportbuilder_rb_bookings_embedded_cache_testcase {
    // testcase data
    protected $report_builder_data = array('id' => 9, 'fullname' => 'My Past Bookings', 'shortname' => 'pastbookings',
                                           'source' => 'facetoface_sessions', 'hidden' => 1, 'embedded' => 1,
                                           'contentmode' => 2);

    protected $report_builder_settings_data = array(
        array('id' => 3, 'reportid' => 9, 'type' => 'date_content', 'name' => 'enable', 'value' => '1'),
        array('id' => 4, 'reportid' => 9, 'type' => 'date_content',  'name' => 'when', 'value' => 'past')
    );
    protected $delta = -3600;

    public function setUp() {
        foreach($this->report_builder_columns_data as &$e) {
            $e['reportid'] = 9;
        }
        parent::setUp();
    }

    /**
     * Test past bookings with/without using cache
     * Test case:
     * - Common part (@see: self::setUp() )
     * - Check bookings for first user (2)
     * - Check bookings for second user (1)
     * - Check bookings for fourth user (0)
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_bookings($usecache) {
        parent::test_bookings($usecache);
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
