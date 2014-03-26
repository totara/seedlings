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

class feedback360_question_test extends feedback360_testcase {
    public function test_create() {
        $this->resetAfterTest();
        list($fdbck) = $this->prepare_feedback_with_users(1, 0);

        $data = new stdClass();
        $data->id = 0;
        $data->name = 'Question';
        $data->sortorder = 5;
        $data->required = 1;
        $data->feedback360id = $fdbck->id;
        $question = new feedback360_question();
        $question->attach_element('text');
        $question->set($data);
        $question->save();
        $questionid = $question->id;
        unset($question);
        $questiontest = new feedback360_question($questionid);

        $this->assertGreaterThan(0, $questiontest->id);
        $this->assertEquals('Question', $questiontest->name);
        $this->assertEquals('text', $questiontest->datatype);
        $this->assertEquals(0, $questiontest->sortorder);
        $this->assertEquals(1, $questiontest->required);
    }

    public function test_edit() {
        $this->resetAfterTest();
        list(, , $quests) = $this->prepare_feedback_with_users(1, 1);
        $quest = current($quests);
        $this->assertGreaterThan(0, $quest->id);
        $this->assertEquals('Text1', $quest->name);
        $this->assertEquals(0, $quest->sortorder);
        $this->assertEquals(0, $quest->required);
        $questid = $quest->id;
        $quest->name = 'Question';
        $quest->sortorder = 2;
        $quest->required = 1;
        $quest->save();
        unset($quest);
        $questiontest = new feedback360_question($questid);
        $this->assertEquals('Question', $questiontest->name);
        $this->assertEquals('text', $questiontest->datatype);
        // Manual sortorder must be ignored.
        $this->assertEquals(0, $questiontest->sortorder);
        $this->assertEquals(1, $questiontest->required);
    }

    public function test_delete() {
        $this->resetAfterTest();
        list($fdbck, , $quests) = $this->prepare_feedback_with_users(1, 3);
        $quest1 = current($quests);
        $quest2 = next($quests);
        $quest3 = next($quests);

        // Remove first one.
        feedback360_question::delete($quest1->id);
        $list = feedback360_question::get_list($fdbck->id);
        $this->assertCount(2, $list);
        $this->assertArrayHasKey($quest2->id, $list);
        $this->assertArrayHasKey($quest3->id, $list);
        unset($list);

        // Activate.
        $fdbck->activate();
        $this->assertFalse(feedback360_question::delete($quest2->id));
        $list2 = feedback360_question::get_list($fdbck->id);
        $this->assertArrayHasKey($quest2->id, $list2);
        unset($list2);

        // Close.
        $fdbck->set_status(feedback360::STATUS_CLOSED);
        feedback360_question::delete($quest2->id);
        $list3 = feedback360_question::get_list($fdbck->id);
        $this->assertCount(2, $list3);
        $this->assertArrayHasKey($quest3->id, $list3);
    }

    public function test_duplicate() {
        $this->resetAfterTest();
        list(, , $quests) = $this->prepare_feedback_with_users();
        list($fdbck2) = $this->prepare_feedback_with_users();
        $quest = current($quests);
        $questid = $quest->id;
        $quest->required = true;
        $quest->save();
        $copy = $quest->duplicate($fdbck2->id);

        // Check that original id was not changed during duplication.
        $this->assertEquals($questid, $quest->id);
        $this->assertGreaterThan($quest->id, $copy->id);
        $this->assertEquals($fdbck2->id, $copy->feedback360id);
        $this->assertEquals('Text1', $copy->name);
        $this->assertEquals('text', $copy->datatype);
        $this->assertEquals(0, $quest->sortorder);
        $this->assertEquals(1, $copy->sortorder);
        $this->assertEquals(1, $quest->required);
        $this->assertEquals(1, $copy->required);
        $this->assertNotEquals(spl_object_hash($quest->get_element()), spl_object_hash($copy->get_element()));
    }

    public function test_user_can_view() {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $oldlog = ini_get('error_log');
        ini_set('error_log', "$CFG->dataroot/testlog.log"); // Prevent standard logging.
        unset_config('noemailever');

        list($fdbck, $users, $quests) = $this->prepare_feedback_with_users(1, 3);
        list($fdbck2, $users2) = $this->prepare_feedback_with_users();
        $fdbck->activate();
        $fdbck2->activate();
        $assigneduser = current($users);
        $respuser = $this->getDataGenerator()->create_user();
        $response = $this->assign_resp($fdbck, $assigneduser->id, $respuser->id);
        $otheruser = $this->getDataGenerator()->create_user();
        $otheruser2 = current($users2);
        $question = new feedback360_question(current($quests)->id);

        $email = 'somebody@example.com';
        $userassignment = $DB->get_record('feedback360_user_assignment', array('feedback360id' => $fdbck->id,
            'userid' => $assigneduser->id));

        // Make sure we are redirecting emails.
        $sink = $this->redirectEmails();
        $this->assertTrue(phpunit_util::is_redirecting_phpmailer());

        feedback360_responder::update_external_assignments(array($email), array(), $userassignment->id, time());

        // Get the email that we just sent.
        $emails = $sink->get_messages();
        $this->assertCount(1, $sink->get_messages());
        $sink->close();

        $emailresponserecord = $DB->get_record('feedback360_email_assignment', array('email' => $email), '*', MUST_EXIST);
        $emailresponse = feedback360_responder::by_email($email, $emailresponserecord->token);

        // Check user assigned can view.
        $this->assertTrue($question->user_can_view($response->id, $assigneduser->id));
        // Check user responder can view.
        $this->assertTrue($question->user_can_view($response->id, $respuser->id));
        // Check email responder can view.
        $guest = guest_user();
        $this->assertTrue($question->user_can_view($emailresponse->id, $guest->id));
        // Check other user cannot view.
        $this->assertFalse($question->user_can_view($response->id, $otheruser->id));
        $this->assertFalse($question->user_can_view($response->id, $otheruser2->id));

        ini_set('error_log', $oldlog);
    }

    public function test_reorder() {
        $this->resetAfterTest();
        list(, , $quests) = $this->prepare_feedback_with_users(1, 3);
        $quest1 = current($quests);
        $quest2 = next($quests);
        $quest3 = next($quests);

        // Move first text to last position.
        feedback360_question::reorder($quest1->id, 2);
        $quest1->load();
        $quest2->load();
        $quest3->load();
        $this->assertEquals(0, $quest2->sortorder);
        $this->assertEquals(1, $quest3->sortorder);
        $this->assertEquals(2, $quest1->sortorder);

        // Move second text to first position (Text3 became second after first reordering).
        feedback360_question::reorder($quest3->id, 0);
        $quest1->load();
        $quest2->load();
        $quest3->load();
        $this->assertEquals(0, $quest3->sortorder);
        $this->assertEquals(1, $quest2->sortorder);
        $this->assertEquals(2, $quest1->sortorder);
    }
}