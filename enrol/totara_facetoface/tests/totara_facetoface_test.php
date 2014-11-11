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
 * Face-to-Face Direct enrolment plugin tests.
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/enrol/totara_facetoface/lib.php');

/**
 * Class enrol_totara_facetoface_testcase
 * @group enrol_totara_facetoface
 */
class enrol_totara_facetoface_testcase extends advanced_testcase {

    protected $pos_framework_data = array(
        'id' => 1, 'fullname' => 'Postion Framework 1', 'shortname' => 'PFW1', 'idnumber' => 'ID1',
        'description' => 'Description 1', 'sortorder' => 1, 'visible' => 1, 'hidecustomfields' => 0,
        'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
    );

    protected $pos_data = array(
        array('id' => 1, 'fullname' => 'Data Analyst', 'shortname' => 'Analyst', 'idnumber' => 'DA1',
            'frameworkid' => 1, 'path' => '/1', 'depthlevel' => 1, 'parentid' => 0, 'sortthread' => '01',
            'visible' => 1, 'timevalidfrom' => 0, 'timevalidto' => 0, 'timecreated' => 0, 'timemodified' => 0,
            'usermodified' => 2)
    );

    protected $pos_assignment_data = array(
        array('id' => 1, 'fullname' => 'Test Assignment 1', 'shortname' => 'Test 1', 'positionid' => 1,
            'timecreated' => 0, 'timemodified' => 0, 'usermodified' => 2),
    );

    protected function enable_plugin() {
        $enabled = enrol_get_plugins(true);
        $enabled['totara_facetoface'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    protected function disable_plugin() {
        $enabled = enrol_get_plugins(true);
        unset($enabled['totara_facetoface']);
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    public function test_basics() {

        $this->resetAfterTest();

        $this->assertFalse(enrol_is_enabled('totara_facetoface'));
        $plugin = enrol_get_plugin('totara_facetoface');
        $this->assertInstanceOf('enrol_totara_facetoface_plugin', $plugin);

        self::enable_plugin();
        $this->assertTrue(enrol_is_enabled('totara_facetoface'));
    }

    public function test_sync_nothing() {
        global $SITE;

        $selfplugin = enrol_get_plugin('totara_facetoface');

        $trace = new null_progress_trace();

        // Just make sure the sync does not throw any errors when nothing to do.
        $selfplugin->sync($trace, null);
        $selfplugin->sync($trace, $SITE->id);
    }

    public function test_longtimnosee() {
        global $DB;

        $this->resetAfterTest();

        $selfplugin = enrol_get_plugin('totara_facetoface');
        $manualplugin = enrol_get_plugin('manual');
        $this->assertNotEmpty($manualplugin);

        self::enable_plugin();

        $now = time();

        $trace = new null_progress_trace();

        // Prepare some data.

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->assertNotEmpty($studentrole);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->assertNotEmpty($teacherrole);

        $record = array('firstaccess' => $now - DAYSECS * 800);
        $record['lastaccess'] = $now - DAYSECS * 100;
        $user1 = $this->getDataGenerator()->create_user($record);
        $record['lastaccess'] = $now - DAYSECS * 10;
        $user2 = $this->getDataGenerator()->create_user($record);
        $record['lastaccess'] = $now - DAYSECS * 1;
        $user3 = $this->getDataGenerator()->create_user($record);
        $record['lastaccess'] = $now - 10;
        $user4 = $this->getDataGenerator()->create_user($record);

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();
        $context1 = context_course::instance($course1->id);
        $context2 = context_course::instance($course2->id);
        $context3 = context_course::instance($course3->id);

        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'totara_facetoface')));
        $instance1 = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance2 = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance3 = $DB->get_record('enrol', array('courseid' => $course3->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $id = $selfplugin->add_instance($course3, array('status' => ENROL_INSTANCE_ENABLED, 'roleid' => $teacherrole->id));
        $instance3b = $DB->get_record('enrol', array('id' => $id), '*', MUST_EXIST);
        unset($id);

        $this->assertEquals($studentrole->id, $instance1->roleid);
        $instance1->customint2 = DAYSECS * 14;
        $DB->update_record('enrol', $instance1);
        $selfplugin->enrol_user($instance1, $user1->id, $studentrole->id);
        $selfplugin->enrol_user($instance1, $user2->id, $studentrole->id);
        $selfplugin->enrol_user($instance1, $user3->id, $studentrole->id);
        $this->assertEquals(3, $DB->count_records('user_enrolments'));
        $DB->insert_record(
            'user_lastaccess',
            array('userid' => $user2->id, 'courseid' => $course1->id, 'timeaccess' => $now - DAYSECS * 20)
        );
        $DB->insert_record(
            'user_lastaccess',
            array('userid' => $user3->id, 'courseid' => $course1->id, 'timeaccess' => $now - DAYSECS * 2)
        );
        $DB->insert_record(
            'user_lastaccess',
            array('userid' => $user4->id, 'courseid' => $course1->id, 'timeaccess' => $now - MINSECS)
        );

        $this->assertEquals($studentrole->id, $instance3->roleid);
        $instance3->customint2 = DAYSECS * 50;
        $DB->update_record('enrol', $instance3);
        $selfplugin->enrol_user($instance3, $user1->id, $studentrole->id);
        $selfplugin->enrol_user($instance3, $user2->id, $studentrole->id);
        $selfplugin->enrol_user($instance3, $user3->id, $studentrole->id);
        $selfplugin->enrol_user($instance3b, $user1->id, $teacherrole->id);
        $selfplugin->enrol_user($instance3b, $user4->id, $teacherrole->id);
        $this->assertEquals(8, $DB->count_records('user_enrolments'));
        $DB->insert_record(
            'user_lastaccess',
            array('userid' => $user2->id, 'courseid' => $course3->id, 'timeaccess' => $now - DAYSECS * 11)
        );
        $DB->insert_record(
            'user_lastaccess',
            array('userid' => $user3->id, 'courseid' => $course3->id, 'timeaccess' => $now - DAYSECS * 200)
        );
        $DB->insert_record(
            'user_lastaccess',
            array('userid' => $user4->id, 'courseid' => $course3->id, 'timeaccess' => $now - DAYSECS * 200)
        );

        $maninstance2 = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $maninstance3 = $DB->get_record('enrol', array('courseid' => $course3->id, 'enrol' => 'manual'), '*', MUST_EXIST);

        $manualplugin->enrol_user($maninstance2, $user1->id, $studentrole->id);
        $manualplugin->enrol_user($maninstance3, $user1->id, $teacherrole->id);

        $this->assertEquals(10, $DB->count_records('user_enrolments'));
        $this->assertEquals(9, $DB->count_records('role_assignments'));
        $this->assertEquals(7, $DB->count_records('role_assignments', array('roleid' => $studentrole->id)));
        $this->assertEquals(2, $DB->count_records('role_assignments', array('roleid' => $teacherrole->id)));

        // Execute sync - this is the same thing used from cron.

        $selfplugin->sync($trace, $course2->id);
        $this->assertEquals(10, $DB->count_records('user_enrolments'));

        $this->assertTrue($DB->record_exists('user_enrolments', array('enrolid' => $instance1->id, 'userid' => $user1->id)));
        $this->assertTrue($DB->record_exists('user_enrolments', array('enrolid' => $instance1->id, 'userid' => $user2->id)));
        $this->assertTrue($DB->record_exists('user_enrolments', array('enrolid' => $instance3->id, 'userid' => $user1->id)));
        $this->assertTrue($DB->record_exists('user_enrolments', array('enrolid' => $instance3->id, 'userid' => $user3->id)));
        $selfplugin->sync($trace, null);
        $this->assertEquals(6, $DB->count_records('user_enrolments'));
        $this->assertFalse($DB->record_exists('user_enrolments', array('enrolid' => $instance1->id, 'userid' => $user1->id)));
        $this->assertFalse($DB->record_exists('user_enrolments', array('enrolid' => $instance1->id, 'userid' => $user2->id)));
        $this->assertFalse($DB->record_exists('user_enrolments', array('enrolid' => $instance3->id, 'userid' => $user1->id)));
        $this->assertFalse($DB->record_exists('user_enrolments', array('enrolid' => $instance3->id, 'userid' => $user3->id)));

        $this->assertEquals(6, $DB->count_records('role_assignments'));
        $this->assertEquals(4, $DB->count_records('role_assignments', array('roleid' => $studentrole->id)));
        $this->assertEquals(2, $DB->count_records('role_assignments', array('roleid' => $teacherrole->id)));
    }

    public function test_expired() {
        global $DB;
        $this->resetAfterTest();

        $selfplugin = enrol_get_plugin('totara_facetoface');
        $manualplugin = enrol_get_plugin('manual');
        $this->assertNotEmpty($manualplugin);

        self::enable_plugin();

        $now = time();

        $trace = new null_progress_trace();

        // Prepare some data.

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->assertNotEmpty($studentrole);
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $this->assertNotEmpty($teacherrole);
        $managerrole = $DB->get_record('role', array('shortname' => 'manager'));
        $this->assertNotEmpty($managerrole);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();
        $context1 = context_course::instance($course1->id);
        $context2 = context_course::instance($course2->id);
        $context3 = context_course::instance($course3->id);

        $this->assertEquals(3, $DB->count_records('enrol', array('enrol' => 'totara_facetoface')));
        $instance1 = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $this->assertEquals($studentrole->id, $instance1->roleid);
        $instance2 = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $this->assertEquals($studentrole->id, $instance2->roleid);
        $instance3 = $DB->get_record('enrol', array('courseid' => $course3->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $this->assertEquals($studentrole->id, $instance3->roleid);
        $id = $selfplugin->add_instance($course3, array('status' => ENROL_INSTANCE_ENABLED, 'roleid' => $teacherrole->id));
        $instance3b = $DB->get_record('enrol', array('id' => $id), '*', MUST_EXIST);
        $this->assertEquals($teacherrole->id, $instance3b->roleid);
        unset($id);

        $maninstance2 = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $maninstance3 = $DB->get_record('enrol', array('courseid' => $course3->id, 'enrol' => 'manual'), '*', MUST_EXIST);

        $manualplugin->enrol_user($maninstance2, $user1->id, $studentrole->id);
        $manualplugin->enrol_user($maninstance3, $user1->id, $teacherrole->id);

        $this->assertEquals(2, $DB->count_records('user_enrolments'));
        $this->assertEquals(2, $DB->count_records('role_assignments'));
        $this->assertEquals(1, $DB->count_records('role_assignments', array('roleid' => $studentrole->id)));
        $this->assertEquals(1, $DB->count_records('role_assignments', array('roleid' => $teacherrole->id)));

        $selfplugin->enrol_user($instance1, $user1->id, $studentrole->id);
        $selfplugin->enrol_user($instance1, $user2->id, $studentrole->id);
        $selfplugin->enrol_user($instance1, $user3->id, $studentrole->id, 0, $now - MINSECS);

        $selfplugin->enrol_user($instance3, $user1->id, $studentrole->id, 0, 0);
        $selfplugin->enrol_user($instance3, $user2->id, $studentrole->id, 0, $now - MINSECS);
        $selfplugin->enrol_user($instance3, $user3->id, $studentrole->id, 0, $now + MINSECS);
        $selfplugin->enrol_user($instance3b, $user1->id, $teacherrole->id, $now - WEEKSECS, $now - MINSECS);
        $selfplugin->enrol_user($instance3b, $user4->id, $teacherrole->id);

        role_assign($managerrole->id, $user3->id, $context1->id);

        $this->assertEquals(10, $DB->count_records('user_enrolments'));
        $this->assertEquals(10, $DB->count_records('role_assignments'));
        $this->assertEquals(7, $DB->count_records('role_assignments', array('roleid' => $studentrole->id)));
        $this->assertEquals(2, $DB->count_records('role_assignments', array('roleid' => $teacherrole->id)));

        // Execute tests.

        $this->assertEquals(ENROL_EXT_REMOVED_KEEP, $selfplugin->get_config('expiredaction'));
        $selfplugin->sync($trace, null);
        $this->assertEquals(10, $DB->count_records('user_enrolments'));
        $this->assertEquals(10, $DB->count_records('role_assignments'));

        $selfplugin->set_config('expiredaction', ENROL_EXT_REMOVED_SUSPENDNOROLES);
        $selfplugin->sync($trace, $course2->id);
        $this->assertEquals(10, $DB->count_records('user_enrolments'));
        $this->assertEquals(10, $DB->count_records('role_assignments'));

        $selfplugin->sync($trace, null);
        $this->assertEquals(10, $DB->count_records('user_enrolments'));
        $this->assertEquals(7, $DB->count_records('role_assignments'));
        $this->assertEquals(5, $DB->count_records('role_assignments', array('roleid' => $studentrole->id)));
        $this->assertEquals(1, $DB->count_records('role_assignments', array('roleid' => $teacherrole->id)));
        $this->assertFalse(
            $DB->record_exists(
                'role_assignments',
                array('contextid' => $context1->id, 'userid' => $user3->id, 'roleid' => $studentrole->id)
            )
        );
        $this->assertFalse(
            $DB->record_exists(
                'role_assignments',
                array('contextid' => $context3->id, 'userid' => $user2->id, 'roleid' => $studentrole->id)
            )
        );
        $this->assertFalse(
            $DB->record_exists(
                'role_assignments',
                array('contextid' => $context3->id, 'userid' => $user1->id, 'roleid' => $teacherrole->id)
            )
        );
        $this->assertTrue(
            $DB->record_exists(
                'role_assignments',
                array('contextid' => $context3->id, 'userid' => $user1->id, 'roleid' => $studentrole->id)
            )
        );

        $selfplugin->set_config('expiredaction', ENROL_EXT_REMOVED_UNENROL);

        role_assign($studentrole->id, $user3->id, $context1->id);
        role_assign($studentrole->id, $user2->id, $context3->id);
        role_assign($teacherrole->id, $user1->id, $context3->id);
        $this->assertEquals(10, $DB->count_records('user_enrolments'));
        $this->assertEquals(10, $DB->count_records('role_assignments'));
        $this->assertEquals(7, $DB->count_records('role_assignments', array('roleid' => $studentrole->id)));
        $this->assertEquals(2, $DB->count_records('role_assignments', array('roleid' => $teacherrole->id)));

        $selfplugin->sync($trace, null);
        $this->assertEquals(7, $DB->count_records('user_enrolments'));
        $this->assertFalse($DB->record_exists('user_enrolments', array('enrolid' => $instance1->id, 'userid' => $user3->id)));
        $this->assertFalse($DB->record_exists('user_enrolments', array('enrolid' => $instance3->id, 'userid' => $user2->id)));
        $this->assertFalse($DB->record_exists('user_enrolments', array('enrolid' => $instance3b->id, 'userid' => $user1->id)));
        $this->assertEquals(6, $DB->count_records('role_assignments'));
        $this->assertEquals(5, $DB->count_records('role_assignments', array('roleid' => $studentrole->id)));
        $this->assertEquals(1, $DB->count_records('role_assignments', array('roleid' => $teacherrole->id)));
    }

    public function test_send_expiry_notifications() {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Messaging does not like transactions.

        $selfplugin = enrol_get_plugin('totara_facetoface');
        $manualplugin = enrol_get_plugin('manual');

        self::enable_plugin();

        $now = time();
        $admin = get_admin();

        $trace = new null_progress_trace();

        // Note: hopefully nobody executes the unit tests the last second before midnight.

        $selfplugin->set_config('expirynotifylast', $now - DAYSECS);
        $selfplugin->set_config('expirynotifyhour', 0);

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->assertNotEmpty($studentrole);
        $editingteacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->assertNotEmpty($editingteacherrole);
        $managerrole = $DB->get_record('role', array('shortname' => 'manager'));
        $this->assertNotEmpty($managerrole);

        $user1 = $this->getDataGenerator()->create_user(array('lastname' => 'xuser1'));
        $user2 = $this->getDataGenerator()->create_user(array('lastname' => 'xuser2'));
        $user3 = $this->getDataGenerator()->create_user(array('lastname' => 'xuser3'));
        $user4 = $this->getDataGenerator()->create_user(array('lastname' => 'xuser4'));
        $user5 = $this->getDataGenerator()->create_user(array('lastname' => 'xuser5'));
        $user6 = $this->getDataGenerator()->create_user(array('lastname' => 'xuser6'));
        $user7 = $this->getDataGenerator()->create_user(array('lastname' => 'xuser6'));
        $user8 = $this->getDataGenerator()->create_user(array('lastname' => 'xuser6'));

        $course1 = $this->getDataGenerator()->create_course(array('fullname' => 'xcourse1'));
        $course2 = $this->getDataGenerator()->create_course(array('fullname' => 'xcourse2'));
        $course3 = $this->getDataGenerator()->create_course(array('fullname' => 'xcourse3'));
        $course4 = $this->getDataGenerator()->create_course(array('fullname' => 'xcourse4'));

        $this->assertEquals(4, $DB->count_records('enrol', array('enrol' => 'manual')));
        $this->assertEquals(4, $DB->count_records('enrol', array('enrol' => 'totara_facetoface')));

        $maninstance1 = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $instance1 = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance1->expirythreshold = DAYSECS * 4;
        $instance1->expirynotify    = 1;
        $instance1->notifyall       = 1;
        $instance1->status          = ENROL_INSTANCE_ENABLED;
        $DB->update_record('enrol', $instance1);

        $maninstance2 = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $instance2 = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance2->expirythreshold = DAYSECS * 1;
        $instance2->expirynotify    = 1;
        $instance2->notifyall       = 1;
        $instance2->status          = ENROL_INSTANCE_ENABLED;
        $DB->update_record('enrol', $instance2);

        $maninstance3 = $DB->get_record('enrol', array('courseid' => $course3->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $instance3 = $DB->get_record('enrol', array('courseid' => $course3->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance3->expirythreshold = DAYSECS * 1;
        $instance3->expirynotify    = 1;
        $instance3->notifyall       = 0;
        $instance3->status          = ENROL_INSTANCE_ENABLED;
        $DB->update_record('enrol', $instance3);

        $maninstance4 = $DB->get_record('enrol', array('courseid' => $course4->id, 'enrol' => 'manual'), '*', MUST_EXIST);
        $instance4 = $DB->get_record('enrol', array('courseid' => $course4->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance4->expirythreshold = DAYSECS * 1;
        $instance4->expirynotify    = 0;
        $instance4->notifyall       = 0;
        $instance4->status          = ENROL_INSTANCE_ENABLED;
        $DB->update_record('enrol', $instance4);

        // Suspended users are not notified.
        $selfplugin->enrol_user($instance1, $user1->id, $studentrole->id, 0, $now + DAYSECS * 1, ENROL_USER_SUSPENDED);
        // Above threshold are not notified.
        $selfplugin->enrol_user($instance1, $user2->id, $studentrole->id, 0, $now + DAYSECS * 5);
        // Less than one day after threshold - should be notified.
        $selfplugin->enrol_user($instance1, $user3->id, $studentrole->id, 0, $now + DAYSECS * 3 + HOURSECS);
        // Less than one day after threshold - should be notified.
        $selfplugin->enrol_user($instance1, $user4->id, $studentrole->id, 0, $now + DAYSECS * 4 - MINSECS * 3);
        // Should have been already notified.
        $selfplugin->enrol_user($instance1, $user5->id, $studentrole->id, 0, $now + HOURSECS);
        // Already expired.
        $selfplugin->enrol_user($instance1, $user6->id, $studentrole->id, 0, $now - MINSECS);
        $manualplugin->enrol_user($maninstance1, $user7->id, $editingteacherrole->id);
        // Highest role --> enroller.
        $manualplugin->enrol_user($maninstance1, $user8->id, $managerrole->id);

        $selfplugin->enrol_user($instance2, $user1->id, $studentrole->id);
        // Above threshold are not notified.
        $selfplugin->enrol_user($instance2, $user2->id, $studentrole->id, 0, $now + DAYSECS * 1 + MINSECS * 3);
        // Less than one day after threshold - should be notified.
        $selfplugin->enrol_user($instance2, $user3->id, $studentrole->id, 0, $now + DAYSECS * 1 - HOURSECS);

        $manualplugin->enrol_user($maninstance3, $user1->id, $editingteacherrole->id);
        // Above threshold are not notified.
        $selfplugin->enrol_user($instance3, $user2->id, $studentrole->id, 0, $now + DAYSECS * 1 + MINSECS);
        // Less than one day after threshold - should be notified.
        $selfplugin->enrol_user($instance3, $user3->id, $studentrole->id, 0, $now + DAYSECS * 1 - HOURSECS);

        $manualplugin->enrol_user($maninstance4, $user4->id, $editingteacherrole->id);
        $selfplugin->enrol_user($instance4, $user5->id, $studentrole->id, 0, $now + DAYSECS * 1 + MINSECS);
        $selfplugin->enrol_user($instance4, $user6->id, $studentrole->id, 0, $now + DAYSECS * 1 - HOURSECS);

        // The notification is sent out in fixed order first individual users,
        // then summary per course by enrolid, user lastname, etc.
        $this->assertGreaterThan($instance1->id, $instance2->id);
        $this->assertGreaterThan($instance2->id, $instance3->id);

        $sink = $this->redirectMessages();

        $selfplugin->send_expiry_notifications($trace);

        $messages = $sink->get_messages();

        $this->assertEquals(2+1 + 1+1 + 1 + 0, count($messages));

        // First individual notifications from course1.
        $this->assertEquals($user3->id, $messages[0]->useridto);
        $this->assertEquals($user8->id, $messages[0]->useridfrom);
        $this->assertContains('xcourse1', $messages[0]->fullmessagehtml);

        $this->assertEquals($user4->id, $messages[1]->useridto);
        $this->assertEquals($user8->id, $messages[1]->useridfrom);
        $this->assertContains('xcourse1', $messages[1]->fullmessagehtml);

        // Then summary for course1.
        $this->assertEquals($user8->id, $messages[2]->useridto);
        $this->assertEquals($admin->id, $messages[2]->useridfrom);
        $this->assertContains('xcourse1', $messages[2]->fullmessagehtml);
        $this->assertNotContains('xuser1', $messages[2]->fullmessagehtml);
        $this->assertNotContains('xuser2', $messages[2]->fullmessagehtml);
        $this->assertContains('xuser3', $messages[2]->fullmessagehtml);
        $this->assertContains('xuser4', $messages[2]->fullmessagehtml);
        $this->assertContains('xuser5', $messages[2]->fullmessagehtml);
        $this->assertNotContains('xuser6', $messages[2]->fullmessagehtml);

        // First individual notifications from course2.
        $this->assertEquals($user3->id, $messages[3]->useridto);
        $this->assertEquals($admin->id, $messages[3]->useridfrom);
        $this->assertContains('xcourse2', $messages[3]->fullmessagehtml);

        // Then summary for course2.
        $this->assertEquals($admin->id, $messages[4]->useridto);
        $this->assertEquals($admin->id, $messages[4]->useridfrom);
        $this->assertContains('xcourse2', $messages[4]->fullmessagehtml);
        $this->assertNotContains('xuser1', $messages[4]->fullmessagehtml);
        $this->assertNotContains('xuser2', $messages[4]->fullmessagehtml);
        $this->assertContains('xuser3', $messages[4]->fullmessagehtml);
        $this->assertNotContains('xuser4', $messages[4]->fullmessagehtml);
        $this->assertNotContains('xuser5', $messages[4]->fullmessagehtml);
        $this->assertNotContains('xuser6', $messages[4]->fullmessagehtml);

        // Only summary in course3.
        $this->assertEquals($user1->id, $messages[5]->useridto);
        $this->assertEquals($admin->id, $messages[5]->useridfrom);
        $this->assertContains('xcourse3', $messages[5]->fullmessagehtml);
        $this->assertNotContains('xuser1', $messages[5]->fullmessagehtml);
        $this->assertNotContains('xuser2', $messages[5]->fullmessagehtml);
        $this->assertContains('xuser3', $messages[5]->fullmessagehtml);
        $this->assertNotContains('xuser4', $messages[5]->fullmessagehtml);
        $this->assertNotContains('xuser5', $messages[5]->fullmessagehtml);
        $this->assertNotContains('xuser6', $messages[5]->fullmessagehtml);

        // Make sure that notifications are not repeated.
        $sink->clear();

        $selfplugin->send_expiry_notifications($trace);
        $this->assertEquals(0, $sink->count());

        // Use invalid notification hour to verify that before the hour the notifications are not sent.
        $selfplugin->set_config('expirynotifylast', time() - DAYSECS);
        $selfplugin->set_config('expirynotifyhour', '24');

        $selfplugin->send_expiry_notifications($trace);
        $this->assertEquals(0, $sink->count());

        $selfplugin->set_config('expirynotifyhour', '0');
        $selfplugin->send_expiry_notifications($trace);
        $this->assertEquals(6, $sink->count());
    }

    public function test_show_enrolme_link() {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Messaging does not like transactions.

        $selfplugin = enrol_get_plugin('totara_facetoface');

        self::enable_plugin();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->assertNotEmpty($studentrole);

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();
        $course4 = $this->getDataGenerator()->create_course();
        $course5 = $this->getDataGenerator()->create_course();
        $course6 = $this->getDataGenerator()->create_course();
        $course7 = $this->getDataGenerator()->create_course();
        $course8 = $this->getDataGenerator()->create_course();
        $course9 = $this->getDataGenerator()->create_course();
        $course10 = $this->getDataGenerator()->create_course();
        $course11 = $this->getDataGenerator()->create_course();

        $cohort1 = $this->getDataGenerator()->create_cohort();
        $cohort2 = $this->getDataGenerator()->create_cohort();

        $facetofacegenerator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');
        for ($i = 1; $i<12; $i++) {
            $varname = 'course'.$i;
            $facetoface = $facetofacegenerator->create_instance(array('course' => $$varname->id));

            $sessiondate = new stdClass();
            $sessiondate->timestart = time() + (YEARSECS);
            $sessiondate->timefinish = time() + (YEARSECS + MINSECS);
            $sessiondate->sessiontimezone = 'Pacific/Auckland';
            $sessiondata = array(
                'facetoface' => $facetoface->id,
                'capacity' => 1,
                'allowoverbook' => 0,
                'sessiondates' => array($sessiondate),
                'datetimeknown' => '1'
            );
            $facetofacegenerator->add_session($sessiondata);
        }

        // New enrolments are allowed and enrolment instance is enabled.
        $instance1 = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance1->customint6 = 1;
        $DB->update_record('enrol', $instance1);
        $selfplugin->update_status($instance1, ENROL_INSTANCE_ENABLED);

        // New enrolments are not allowed, but enrolment instance is enabled.
        $instance2 = $DB->get_record('enrol', array('courseid' => $course2->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance2->customint6 = 0;
        $DB->update_record('enrol', $instance2);
        $selfplugin->update_status($instance2, ENROL_INSTANCE_ENABLED);

        // New enrolments are allowed , but enrolment instance is disabled.
        $instance3 = $DB->get_record('enrol', array('courseid' => $course3->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance3->customint6 = 1;
        $DB->update_record('enrol', $instance3);
        $selfplugin->update_status($instance3, ENROL_INSTANCE_DISABLED);

        // New enrolments are not allowed and enrolment instance is disabled.
        $instance4 = $DB->get_record('enrol', array('courseid' => $course4->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance4->customint6 = 0;
        $DB->update_record('enrol', $instance4);
        $selfplugin->update_status($instance4, ENROL_INSTANCE_DISABLED);

        // Cohort member test.
        $instance5 = $DB->get_record('enrol', array('courseid' => $course5->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance5->customint6 = 1;
        $instance5->customint5 = $cohort1->id;
        $DB->update_record('enrol', $instance1);
        $selfplugin->update_status($instance5, ENROL_INSTANCE_ENABLED);

        $id = $selfplugin->add_instance($course5, $selfplugin->get_instance_defaults());
        $instance6 = $DB->get_record('enrol', array('id' => $id), '*', MUST_EXIST);
        $instance6->customint6 = 1;
        $instance6->customint5 = $cohort2->id;
        $DB->update_record('enrol', $instance1);
        $selfplugin->update_status($instance6, ENROL_INSTANCE_ENABLED);

        // Enrol start date is in future.
        $instance7 = $DB->get_record('enrol', array('courseid' => $course6->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance7->customint6 = 1;
        $instance7->enrolstartdate = time() + MINSECS;
        $DB->update_record('enrol', $instance7);
        $selfplugin->update_status($instance7, ENROL_INSTANCE_ENABLED);

        // Enrol start date is in past.
        $instance8 = $DB->get_record('enrol', array('courseid' => $course7->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance8->customint6 = 1;
        $instance8->enrolstartdate = time() - MINSECS;
        $DB->update_record('enrol', $instance8);
        $selfplugin->update_status($instance8, ENROL_INSTANCE_ENABLED);

        // Enrol end date is in future.
        $instance9 = $DB->get_record('enrol', array('courseid' => $course8->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance9->customint6 = 1;
        $instance9->enrolenddate = time() + MINSECS;
        $DB->update_record('enrol', $instance9);
        $selfplugin->update_status($instance9, ENROL_INSTANCE_ENABLED);

        // Enrol end date is in past.
        $instance10 = $DB->get_record('enrol', array('courseid' => $course9->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance10->customint6 = 1;
        $instance10->enrolenddate = time() - MINSECS;
        $DB->update_record('enrol', $instance10);
        $selfplugin->update_status($instance10, ENROL_INSTANCE_ENABLED);

        // Maximum enrolments reached.
        $instance11 = $DB->get_record('enrol', array('courseid' => $course10->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance11->customint6 = 1;
        $instance11->customint3 = 1;
        $DB->update_record('enrol', $instance11);
        $selfplugin->update_status($instance11, ENROL_INSTANCE_ENABLED);
        $selfplugin->enrol_user($instance11, $user2->id, $studentrole->id);

        // Maximum enrolments not reached.
        $instance12 = $DB->get_record('enrol', array('courseid' => $course11->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance12->customint6 = 1;
        $instance12->customint3 = 1;
        $DB->update_record('enrol', $instance12);
        $selfplugin->update_status($instance12, ENROL_INSTANCE_ENABLED);

        $this->setUser($user1);
        $this->assertTrue($selfplugin->show_enrolme_link($instance1));
        $this->assertFalse($selfplugin->show_enrolme_link($instance2));
        $this->assertFalse($selfplugin->show_enrolme_link($instance3));
        $this->assertFalse($selfplugin->show_enrolme_link($instance4));
        $this->assertFalse($selfplugin->show_enrolme_link($instance7));
        $this->assertTrue($selfplugin->show_enrolme_link($instance8));
        $this->assertTrue($selfplugin->show_enrolme_link($instance9));
        $this->assertFalse($selfplugin->show_enrolme_link($instance10));
        $this->assertFalse($selfplugin->show_enrolme_link($instance11));
        $this->assertTrue($selfplugin->show_enrolme_link($instance12));

        require_once("$CFG->dirroot/cohort/lib.php");
        cohort_add_member($cohort1->id, $user1->id);

        $this->assertTrue($selfplugin->show_enrolme_link($instance5));
        $this->assertFalse($selfplugin->show_enrolme_link($instance6));
    }

    /**
     * This will check user enrolment only, rest has been tested in test_show_enrolme_link.
     */
    public function test_can_enrol() {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $selfplugin = enrol_get_plugin('totara_facetoface');

        self::enable_plugin();

        $expectederrorstring = get_string('cannotenrol', 'enrol_totara_facetoface');

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $guest = $DB->get_record('user', array('id' => $CFG->siteguest));

        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->assertNotEmpty($studentrole);
        $editingteacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $this->assertNotEmpty($editingteacherrole);

        $course1 = $this->getDataGenerator()->create_course();

        $facetofacegenerator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');
        $facetoface = $facetofacegenerator->create_instance(array('course' => $course1->id));

        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + (YEARSECS);
        $sessiondate->timefinish = time() + (YEARSECS + MINSECS);
        $sessiondate->sessiontimezone = 'Pacific/Auckland';
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 1,
            'allowoverbook' => 0,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1'
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        facetoface_get_session($sessionid);

        $instance1 = $DB->get_record('enrol', array('courseid' => $course1->id, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
        $instance1->customint6 = 1;
        $DB->update_record('enrol', $instance1);
        $selfplugin->update_status($instance1, ENROL_INSTANCE_ENABLED);
        $selfplugin->enrol_user($instance1, $user2->id, $editingteacherrole->id);

        $this->setUser($guest);
        $this->assertSame($expectederrorstring, $selfplugin->can_self_enrol($instance1, true));

        $this->setUser($user1);
        $this->assertTrue($selfplugin->can_self_enrol($instance1, true));

        // Active enroled user.
        $this->setUser($user2);
        $selfplugin->enrol_user($instance1, $user1->id, $studentrole->id);
        $this->setUser($user1);
        $this->assertSame($expectederrorstring, $selfplugin->can_self_enrol($instance1, true));
    }

    public function test_enrol_totara_facetoface_find_best_session() {
        global $CFG;
        $CFG->debug = false; // Suppress debugging as faked raises a message fails the test.

        $this->resetAfterTest();
        $this->preventResetByRollback();

        self::enable_plugin();

        $user1 = $this->getDataGenerator()->create_user(); // Creates the session.
        $user2 = $this->getDataGenerator()->create_user(); // Used to fill up a session.
        $user3 = $this->getDataGenerator()->create_user(); // Used to fill up a session.
        $user4 = $this->getDataGenerator()->create_user(); // Used to fill up a session.

        $course1 = $this->getDataGenerator()->create_course();
        $facetofacegenerator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');

        $facetoface = $facetofacegenerator->create_instance(array('course' => $course1->id, 'multiplesessions' => 1));

        // We're going to add progressively better sessions and make sure the correct one gets picked.

        // Create fully booked session without wait list.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + (YEARSECS);
        $sessiondate->timefinish = time() + (YEARSECS + MINSECS);
        $sessiondate->sessiontimezone = 'Pacific/Auckland';
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 1,
            'allowoverbook' => 0,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1'
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);

        facetoface_user_import($course1, $facetoface, $session, $user2->id);
        $totara_facetoface = enrol_get_plugin('totara_facetoface');

        $this->assertNull(enrol_totara_facetoface_find_best_session($totara_facetoface, $facetoface->id));

        // Create fully booked session with 2 persion wait list (cap 1, 3 enrolled) with waitlist but no date.
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 1,
            'allowoverbook' => 1,
            'datetimeknown' => 0
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        facetoface_user_import($course1, $facetoface, $session, $user2->id);
        facetoface_user_import($course1, $facetoface, $session, $user3->id);
        facetoface_user_import($course1, $facetoface, $session, $user4->id);
        $totara_facetoface = enrol_get_plugin('totara_facetoface');

        $best = enrol_totara_facetoface_find_best_session($totara_facetoface, $facetoface->id);
        $this->assertEquals($session->id, $best->id);

        // Create fully booked session with 1 person wait list (cap 1, 2 enrolled) but no date.
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 1,
            'allowoverbook' => 1,
            'datetimeknown' => 0
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        facetoface_user_import($course1, $facetoface, $session, $user2->id);
        facetoface_user_import($course1, $facetoface, $session, $user3->id);
        $totara_facetoface = enrol_get_plugin('totara_facetoface');

        $best = enrol_totara_facetoface_find_best_session($totara_facetoface, $facetoface->id);
        $this->assertEquals($session->id, $best->id);

        // Create session with capacity but no date.
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 2,
            'allowoverbook' => 1,
            'datetimeknown' => 0
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        facetoface_user_import($course1, $facetoface, $session, $user2->id);
        $totara_facetoface = enrol_get_plugin('totara_facetoface');

        $best = enrol_totara_facetoface_find_best_session($totara_facetoface, $facetoface->id);
        $this->assertEquals($session->id, $best->id);

        // Create session with more capacity but no date.
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'datetimeknown' => 0
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        facetoface_user_import($course1, $facetoface, $session, $user2->id);
        $totara_facetoface = enrol_get_plugin('totara_facetoface');

        $best = enrol_totara_facetoface_find_best_session($totara_facetoface, $facetoface->id);
        $this->assertEquals($session->id, $best->id);

        // Create session with capacity and date in 2 years.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + (YEARSECS * 2);
        $sessiondate->timefinish = time() + (YEARSECS * 2 + MINSECS);
        $sessiondate->sessiontimezone = 'Pacific/Auckland';
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1'
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        facetoface_user_import($course1, $facetoface, $session, $user2->id);
        $totara_facetoface = enrol_get_plugin('totara_facetoface');

        $best = enrol_totara_facetoface_find_best_session($totara_facetoface, $facetoface->id);
        $this->assertEquals($session->id, $best->id);

        // Create session with capacity and date in 1 years.
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
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        facetoface_user_import($course1, $facetoface, $session, $user2->id);
        $totara_facetoface = enrol_get_plugin('totara_facetoface');

        $best = enrol_totara_facetoface_find_best_session($totara_facetoface, $facetoface->id);
        $this->assertEquals($session->id, $best->id);

    }

    public function test_enrol_totara_facetoface_get_sessions_to_autoenrol() {
        global $CFG;
        $CFG->debug = false; // Suppress debugging as faked raises a message fails the test.

        $this->resetAfterTest();
        $this->preventResetByRollback();

        self::enable_plugin();

        $user1 = $this->getDataGenerator()->create_user(); // Creates the session.
        $user2 = $this->getDataGenerator()->create_user(); // Used to fill up a session.

        $course1 = $this->getDataGenerator()->create_course();
        $facetofacegenerator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');

        // We're going to add two sessions to this face to face, only the best (second) should get picked.
        $facetoface = $facetofacegenerator->create_instance(array('course' => $course1->id, 'multiplesessions' => 0));
        $facetofaces[$facetoface->id] = $facetoface;

        $sessionstoautoenrol = array();
        $sessionsnottoautoenrol = array();

        // Create session with capacity and date in 2 years.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + (YEARSECS * 2);
        $sessiondate->timefinish = time() + (YEARSECS * 2 + MINSECS);
        $sessiondate->sessiontimezone = 'Pacific/Auckland';
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1'
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        $sessionsnottoautoenrol[] = $session;

        // Create session with capacity and date in 1 year.
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
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        $sessionstoautoenrol[] = $session;

        // We're going to add two sessions to this face to face and enable multiple reg.
        // First two should get picked, third is already signed on.
        $facetoface = $facetofacegenerator->create_instance(array('course' => $course1->id, 'multiplesessions' => 1));
        $facetofaces[$facetoface->id] = $facetoface;

        // Create session with capacity and date in 2 years.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + (YEARSECS * 2);
        $sessiondate->timefinish = time() + (YEARSECS * 2 + MINSECS);
        $sessiondate->sessiontimezone = 'Pacific/Auckland';
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1'
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        $sessionstoautoenrol[] = $session;

        // Create session with capacity and date in 1 years.
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
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        $sessionstoautoenrol[] = $session;

        // Create session with capacity and date in 1 years.
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
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        facetoface_user_import($course1, $facetoface, $session, $user2->id, array('ignoreconflicts' => true));
        $sessionsnottoautoenrol[] = $session;

        // We're going to add two sessions to this face to face, disable multiple enrolments, and enrol user2 on one.
        // None should be returned.
        $facetoface = $facetofacegenerator->create_instance(array('course' => $course1->id, 'multiplesessions' => 0));
        $facetofaces[$facetoface->id] = $facetoface;

        // Create session with capacity and date in 2 years.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + (YEARSECS * 2);
        $sessiondate->timefinish = time() + (YEARSECS * 2 + MINSECS);
        $sessiondate->sessiontimezone = 'Pacific/Auckland';
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1'
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        $sessionsnottoautoenrol[] = $session;

        // Create session with capacity and date in 1 years.
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
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);
        facetoface_user_import($course1, $facetoface, $session, $user2->id, array('ignoreconflicts' => true));
        $sessionsnottoautoenrol[] = $session;

        $totara_facetoface = enrol_get_plugin('totara_facetoface');
        $sessions = enrol_totara_facetoface_get_sessions_to_autoenrol($totara_facetoface, $course1, $facetofaces, $user2);

        foreach ($sessionstoautoenrol as $session) {
            $this->assertArrayHasKey($session->id, $sessions);
        }

        foreach ($sessionsnottoautoenrol as $session) {
            $this->assertArrayNotHasKey($session->id, $sessions);
        }

        $this->assertEquals(count($sessions), count($sessionstoautoenrol));

    }
}
