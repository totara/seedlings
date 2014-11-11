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
 * @author Andrew Hancox <andrewdchancox@googlemail.com> on behalf of Synergy Learning
 * @package totara
 * @subpackage enrol_totara_facetoface
 */

/**
 * Self enrol external PHPunit tests
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/enrol/totara_facetoface/externallib.php');

class enrol_totara_facetoface_external_testcase extends externallib_advanced_testcase {

    /**
     * Test get_instance_info
     */
    public function test_get_instance_info() {
        global $DB;

        $this->resetAfterTest(true);

        // Check if totara_facetoface enrolment plugin is enabled.
        $selfplugin = enrol_get_plugin('totara_facetoface');
        $this->assertNotEmpty($selfplugin);

        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->assertNotEmpty($studentrole);

        $course = self::getDataGenerator()->create_course();

        // Add enrolment methods for course.
        $instanceid1 = $selfplugin->add_instance($course, array('status' => ENROL_INSTANCE_ENABLED,
                                                                'name' => 'Test instance 1',
                                                                'customint6' => 1,
                                                                'roleid' => $studentrole->id));
        $instanceid2 = $selfplugin->add_instance($course, array('status' => ENROL_INSTANCE_DISABLED,
                                                                'customint6' => 1,
                                                                'name' => 'Test instance 2',
                                                                'roleid' => $studentrole->id));

        $instanceid3 = $selfplugin->add_instance($course, array('status' => ENROL_INSTANCE_ENABLED,
                                                                'roleid' => $studentrole->id,
                                                                'customint6' => 1,
                                                                'name' => 'Test instance 3'));

        $user1 = $this->getDataGenerator()->create_user();

        $facetofacegenerator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');
        $facetoface = $facetofacegenerator->create_instance(array('course' => $course->id));
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + (YEARSECS);
        $sessiondate->timefinish = time() + (YEARSECS + MINSECS);
        $sessiondate->sessiontimezone = 'Pacific/Auckland';
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1'
        );
        $facetofacegenerator->add_session($sessiondata);

        $enrolmentmethods = $DB->get_records('enrol', array('courseid' => $course->id, 'status' => ENROL_INSTANCE_ENABLED));
        // 3 in Moodle, 4 in Totara because program plugin enabled by default.
        $this->assertCount(4, $enrolmentmethods);

        $instanceinfo1 = enrol_totara_facetoface_external::get_instance_info($instanceid1);

        $this->assertEquals($instanceid1, $instanceinfo1['id']);
        $this->assertEquals($course->id, $instanceinfo1['courseid']);
        $this->assertEquals('totara_facetoface', $instanceinfo1['type']);
        $this->assertEquals('Test instance 1', $instanceinfo1['name']);
        $this->assertTrue($instanceinfo1['status']);

        $instanceinfo2 = enrol_totara_facetoface_external::get_instance_info($instanceid2);
        $this->assertEquals($instanceid2, $instanceinfo2['id']);
        $this->assertEquals($course->id, $instanceinfo2['courseid']);
        $this->assertEquals('totara_facetoface', $instanceinfo2['type']);
        $this->assertEquals('Test instance 2', $instanceinfo2['name']);
        $this->assertEquals(get_string('cannotenrol', 'enrol_totara_facetoface'), $instanceinfo2['status']);

        $instanceinfo3 = enrol_totara_facetoface_external::get_instance_info($instanceid3);
        $this->assertEquals($instanceid3, $instanceinfo3['id']);
        $this->assertEquals($course->id, $instanceinfo3['courseid']);
        $this->assertEquals('totara_facetoface', $instanceinfo3['type']);
        $this->assertEquals('Test instance 3', $instanceinfo3['name']);
        $this->assertTrue($instanceinfo3['status']);
    }
}