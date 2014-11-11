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
 * Unit/functional tests to check Record of Learning: Objectives reports caching
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}
global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/filters/lib.php');


class totara_reportbuilder_rb_filters_lib_testcase extends advanced_testcase {

    protected function setUp() {
        parent::setup();
    }

    /**
     * Test for required regions
     */
    public function test_get_all_regions() {
        $regions = rb_filter_type::get_all_regions();
        $this->assertGreaterThanOrEqual(2, count($regions));
        $this->assertArrayHasKey(rb_filter_type::RB_FILTER_REGION_STANDARD, $regions);
        $this->assertArrayHasKey(rb_filter_type::RB_FILTER_REGION_SIDEBAR, $regions);
    }
}
