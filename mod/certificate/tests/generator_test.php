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
 * PHPUnit certificate generator testcase
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit mod_certificate_generator_testcase mod/feedback/tests/generator_test.php
 *
 * @package    mod_certificate
 * @subpackage phpunit
 * @author     Russell England <russell.england@catalyst-eu.net>
 * @copyright  Catalyst IT Ltd 2013 <http://catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

defined('MOODLE_INTERNAL') || die();

class mod_certificate_generator_testcase extends advanced_testcase {
    public function test_generator() {
        global $DB;

        $this->resetAfterTest(true);

        $this->assertEquals(0, $DB->count_records('certificate'));

        $course = $this->getDataGenerator()->create_course();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_certificate');
        $this->assertInstanceOf('mod_certificate_generator', $generator);
        $this->assertEquals('certificate', $generator->get_modulename());

        $generator->create_instance(array('course' => $course->id));
        $generator->create_instance(array('course' => $course->id));
        $certificate = $generator->create_instance(array('course' => $course->id));
        $this->assertEquals(3, $DB->count_records('certificate'));

        $cm = get_coursemodule_from_instance('certificate', $certificate->id);
        $this->assertEquals($certificate->id, $cm->instance);
        $this->assertEquals('certificate', $cm->modname);
        $this->assertEquals($course->id, $cm->course);

        $context = context_module::instance($cm->id);
        $this->assertEquals($certificate->cmid, $context->instanceid);
    }
}
