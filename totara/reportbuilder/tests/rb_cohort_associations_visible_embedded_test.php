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
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    //  It must be included from a Moodle page.
}

global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');

class totara_reportbuilder_rb_cohort_associations_visible_embedded_testcase extends advanced_testcase {
    /**
     * Prepare mock data for testing.
     */
    protected function setUp() {
        parent::setup();
        $this->setAdminUser();
        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Create users.
        $this->user1 = $this->getDataGenerator()->create_user();
    }

    public function test_is_capable() {
        global $DB;

        // Set up report and embedded object for is_capable checks.
        $syscontext = context_system::instance();
        $shortname = 'cohort_associations_visible';
        $report = reportbuilder_get_embedded_report($shortname, array(), false, 0);
        $embeddedobject = $report->embedobj;
        $roleuser = $DB->get_record('role', array('shortname'=>'user'));
        $userid = $this->user1->id;

        // Test admin can access report.
        $this->assertTrue($embeddedobject->is_capable(2, $report),
                'admin cannot access report');

        // Test user cannot access report.
        $this->assertFalse($embeddedobject->is_capable($userid, $report),
                'user should not be able to access report');

        // Test user with view capability can access report.
        assign_capability('moodle/cohort:view', CAP_ALLOW, $roleuser->id, $syscontext);
        $syscontext->mark_dirty();
        $this->assertTrue($embeddedobject->is_capable($userid, $report),
                'user with capability moodle/cohort:view cannot access report');
        assign_capability('moodle/cohort:view', CAP_INHERIT, $roleuser->id, $syscontext);
    }
}
