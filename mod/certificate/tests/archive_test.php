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
 * Tests deleting course completion records and archives activities for a course
 * Certificate module PHPUnit archive test class
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit mod_certificate_archive_testcase mod/certificate/tests/archive_test.php
 *
 * @package    mod_certificate
 * @subpackage phpunit
 * @author     Russell England <russell.england@catalyst-eu.net>
 * @copyright  Catalyst IT Ltd 2013 <http://catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/certificate/lib.php');
require_once($CFG->libdir . '/completionlib.php');

class mod_certificate_archive_testcase extends advanced_testcase {
    /**
     * Is archive completion supported?
     */
    public function test_module_supports_archive_completion() {
        $this->assertTrue(certificate_supports(FEATURE_ARCHIVE_COMPLETION));
    }

    /**
     * @depends test_module_supports_archive_completion
     */
    public function test_archive() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course
        $this->assertEquals(1, $DB->count_records('course')); // Site course
        $coursedefaults = array('enablecompletion' => COMPLETION_ENABLED);
        $course = $this->getDataGenerator()->create_course($coursedefaults);
        $this->assertEquals(2, $DB->count_records('course')); // Site course + this course

        // Check it has course competion
        $completioninfo = new completion_info($course);
        $this->assertEquals(COMPLETION_ENABLED, $completioninfo->is_enabled());

        // Create a certificate and add it to the course
        $this->assertEquals(0, $DB->count_records('certificate'));
        $completiondefaults = array(
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => COMPLETION_VIEW_REQUIRED
        );
        $certificate = $this->getDataGenerator()->create_module(
                'certificate',
                array('course' => $course->id),
                $completiondefaults);
        $this->assertEquals(1, $DB->count_records('certificate'));

        // Create a user
        $this->assertEquals(2, $DB->count_records('user')); // Guest + Admin
        $user = $this->getDataGenerator()->create_user();
        $this->assertEquals(3, $DB->count_records('user')); // Guest + Admin + this user

        // Enrol user on course
        $this->assertTrue($this->getDataGenerator()->enrol_user($user->id, $course->id));

        // Complete certificate
        $course_module = get_coursemodule_from_instance('certificate', $certificate->id, $course->id);
        $this->assertEquals(COMPLETION_TRACKING_MANUAL, $completioninfo->is_enabled($course_module));

        // Create a certificate for the user - this replicates a user going to mod/certificate/view.php
        $this->assertEquals(0, $DB->count_records('certificate_issues'));
        $certificateissue = certificate_get_issue($course, $user, $certificate, $course_module);
        $this->assertNotEmpty($certificateissue);
        $this->assertEquals(1, $DB->count_records('certificate_issues'));

        // Check it isn't complete
        $params = array('userid' => $user->id, 'coursemoduleid' => $course_module->id);
        $completionstate = $DB->get_field('course_modules_completion', 'completionstate', $params);
        $this->assertEmpty($completionstate);

        // Complete the certificate
        $completioninfo->set_module_viewed($course_module, $user->id); // Depends on whether a view is required to complete

        // Check its completed
        $completionstate = $DB->get_field('course_modules_completion', 'completionstate', $params, MUST_EXIST);
        $this->assertEquals(COMPLETION_COMPLETE, $completionstate);

        // Archive it
        $this->assertEquals(0, $DB->count_records('certificate_issues_history'));
        certificate_archive_completion($user->id, $course->id);
        // Check there is no certificate issue
        $this->assertEquals(0, $DB->count_records('certificate_issues'));
        // Check there is a history record
        $this->assertEquals(1, $DB->count_records('certificate_issues_history'));

        // Check its incomplete
        $completionstate = $DB->get_field('course_modules_completion', 'completionstate', $params, MUST_EXIST);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completionstate);

        // Check we can report on it
        $totalcount = 0;
        $filters = array('page' => 0, 'perpage' => 20, 'coursename' => $course->fullname);
        $archives = certificate_archive_get_list($filters, $totalcount);
        $this->assertNotEmpty($archives);
    }
}
