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
 * @subpackage feedback360
 */
global $CFG;
require_once($CFG->dirroot.'/totara/feedback360/tests/feedback360_testcase.php');

class feedback360_responder_test extends feedback360_testcase {

    public function setUp() {
        parent::setUp();
        $this->preventResetByRollback();
    }

    public function test_edit() {
        global $DB;
        $this->resetAfterTest();
        list($fdbck, $users) = $this->prepare_feedback_with_users();
        $fdbck->activate();
        $user = current($users);
        $time = time();
        $userassignment = $DB->get_record('feedback360_user_assignment', array('feedback360id' => $fdbck->id,
            'userid' => $user->id));
        $respuser = $this->getDataGenerator()->create_user();
        $response = $this->assign_resp($fdbck, $user->id, $respuser->id);
        $response->viewed = true;
        $response->timeassigned = $time;
        $response->timecompleted = $time + 1;
        $response->save();
        $respid = $response->id;
        unset($response);

        $resptest = new feedback360_responder($respid);
        $this->assertEquals(true, $resptest->viewed);
        $this->assertEquals($time, $resptest->timeassigned);
        $this->assertEquals($time + 1, $resptest->timecompleted);
        $this->assertEquals($fdbck->id, $resptest->feedback360id);
        $this->assertEquals($userassignment->id, $resptest->feedback360userassignmentid);
        $this->assertEquals($respuser->id, $resptest->userid);
        $this->assertEquals(feedback360_responder::TYPE_USER, $resptest->type);
        $this->assertEquals($user->id, $resptest->subjectid);
        $this->assertLessThan(5, abs($resptest->timedue - time()));
    }

    public function test_by_preview() {
        $this->resetAfterTest();
        list($fdbck) = $this->prepare_feedback_with_users();
        $preview = feedback360_responder::by_preview($fdbck->id);
        $this->assertEquals($fdbck->id, $preview->feedback360id);
        $this->assertTrue($preview->is_fake());
        $this->assertFalse($preview->is_email());
        // Preview simulates user response.
        $this->assertTrue($preview->is_user());
    }

    public function test_by_user() {
        $this->resetAfterTest();
        list($fdbck, $users) = $this->prepare_feedback_with_users();
        $user = current($users);
        $respuser = $this->getDataGenerator()->create_user();
        $response = $this->assign_resp($fdbck, $user->id, $respuser->id);
        $respid = $response->id;
        unset($response);

        $byuser = feedback360_responder::by_user($respuser->id, $fdbck->id, $user->id);
        $this->assertEquals($fdbck->id, $byuser->feedback360id);
        $this->assertFalse($byuser->is_fake());
        $this->assertFalse($byuser->is_email());
        $this->assertTrue($byuser->is_user());
        $this->assertEquals($respid, $byuser->id);
        $this->assertEquals($respuser->id, $byuser->userid);
        $this->assertEquals(feedback360_responder::TYPE_USER, $byuser->type);
        $this->assertEquals($user->id, $byuser->subjectid);
    }

    public function test_by_email() {
        global $CFG, $DB;
        $this->preventResetByRollback();
        $this->resetAfterTest();

        $oldlog = ini_get('error_log');
        ini_set('error_log', "$CFG->dataroot/testlog.log"); // Prevent standard logging.
        unset_config('noemailever');

        list($fdbck, $users) = $this->prepare_feedback_with_users();
        $fdbck->activate();
        $user = current($users);
        $time = time();
        $email = 'somebody@example.com';
        $userassignment = $DB->get_record('feedback360_user_assignment', array('feedback360id' => $fdbck->id,
            'userid' => $user->id));

        // Make sure we are redirecting emails.
        $sink = $this->redirectEmails();
        $this->assertTrue(phpunit_util::is_redirecting_phpmailer());

        feedback360_responder::update_external_assignments(array($email), array(), $userassignment->id, $time);

        // Get the email that we just sent.
        $emails = $sink->get_messages();
        $this->assertCount(1, $sink->get_messages());
        $sink->close();

        $emailassignmentrecord = $DB->get_record('feedback360_email_assignment', array('email' => $email), '*', MUST_EXIST);
        $byemail = feedback360_responder::by_email($email, $emailassignmentrecord->token);
        $this->assertEquals($fdbck->id, $byemail->feedback360id);
        $this->assertFalse($byemail->is_fake());
        $this->assertTrue($byemail->is_email());
        $this->assertFalse($byemail->is_user());
        $this->assertEmpty($byemail->userid);
        $this->assertEquals(feedback360_responder::TYPE_EMAIL, $byemail->type);
        $this->assertEquals($user->id, $byemail->subjectid);
        $this->assertEquals($email, $byemail->email);

        ini_set('error_log', $oldlog);
    }

    public function test_complete() {
        $this->resetAfterTest();
        list($fdbck, $users) = $this->prepare_feedback_with_users();
        $user = current($users);
        $response = $this->assign_resp($fdbck, $user->id);
        $time = time();

        $this->assertFalse($response->is_completed());
        $response->complete($time);
        $this->assertTrue($response->is_completed());
        $this->assertEquals($time, $response->timecompleted);

        $respid = $response->id;
        unset($response);
        $respload = new feedback360_responder($respid);
        $this->assertTrue($respload->is_completed());
        $this->assertEquals($time, $respload->timecompleted);
    }

    public function test_update_timedue() {
        $this->resetAfterTest();
        list($fdbck, $users) = $this->prepare_feedback_with_users();
        $user = current($users);
        $response = $this->assign_resp($fdbck, $user->id);
        $time = time() + 86400;
        $this->assertLessThan(5, abs($response->timedue-time()));
        $respid = $response->id;
        unset($response);
        feedback360_responder::update_timedue($time, $respid);
        $resptest = new feedback360_responder($respid);
        $this->assertEquals($time, $resptest->timedue);
    }
}