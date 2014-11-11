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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage appraisal
 *
 * Unit tests for appraisal_message class of totara/appraisal/lib.php
 */
global $CFG;
require_once($CFG->dirroot.'/totara/appraisal/tests/appraisal_testcase.php');

class appraisal_message_test extends appraisal_testcase {
    public function test_create() {
        $this->resetAfterTest();
        list($appraisal) = $this->prepare_appraisal_with_users();
        $map = $this->map($appraisal);

        $msg = new appraisal_message();
        $msg->event_stage($map['stages']['Stage'], appraisal_message::EVENT_STAGE_DUE);
        $msg->set_delta(-3, appraisal_message::PERIOD_DAY);
        $roles = array(appraisal::ROLE_LEARNER, appraisal::ROLE_MANAGER);
        $msg->set_roles($roles, 1);
        foreach ($roles as $role) {
            $msg->set_message($role, 'Title '.$role, 'Body '.$role);
        }
        $msg->save();
        $msgid = $msg->id;
        unset($msg);

        $msgtest = new appraisal_message($msgid);
        $this->assertEquals($appraisal->id, $msgtest->appraisalid);
        $this->assertEquals($map['stages']['Stage'], $msgtest->stageid);
        $this->assertEquals(appraisal_message::EVENT_STAGE_DUE, $msgtest->type);
        $this->assertEquals(-3, $msgtest->delta);
        $this->assertEquals(1, $msgtest->deltaperiod);
        $this->assertEquals($roles, $msgtest->roles);
        $this->assertEquals(true, $msgtest->stageiscompleted);
        foreach ($roles as $role) {
            $content = $msgtest->get_message($role);
            $this->assertEquals('Title '.$role, $content->name);
            $this->assertEquals('Body '.$role, $content->content);
        }
    }

    public function test_edit() {
        $this->resetAfterTest();
        // Create appraisal with messages.
        list($appraisal) = $this->prepare_appraisal_with_users();
        $map = $this->map($appraisal);

        $msg = new appraisal_message();
        $msg->event_stage($map['stages']['Stage'], appraisal_message::EVENT_STAGE_DUE);
        $msg->set_delta(-3, appraisal_message::PERIOD_DAY);
        $roles = array(appraisal::ROLE_LEARNER, appraisal::ROLE_MANAGER);
        $msg->set_roles($roles, 0);
        foreach ($roles as $role) {
            $msg->set_message($role, 'Title '.$role, 'Body '.$role);
        }
        $msg->save();
        $msgid = $msg->id;
        unset($msg);

        // Edit this message.
        $msgedit = new appraisal_message($msgid);
        $msgedit->event_appraisal($appraisal->id);
        $msgedit->set_delta(0);
        $roles = array(appraisal::ROLE_LEARNER, appraisal::ROLE_APPRAISER);
        $msgedit->set_roles($roles, 0);
        $msgedit->set_message(0, 'Title 0', 'Body 0');
        $msgedit->save();

        // Check changes.
        $msgtest = new appraisal_message($msgid);
        $this->assertEquals($appraisal->id, $msgtest->appraisalid);
        $this->assertEquals(0, $msgtest->stageid);
        $this->assertEquals(appraisal_message::EVENT_APPRAISAL_ACTIVATION, $msgtest->type);
        $this->assertEquals(0, $msgtest->delta);
        $this->assertEquals(0, $msgtest->deltaperiod);
        $this->assertEquals($roles, $msgtest->roles);
        $this->assertEquals(0, $msgtest->stageiscompleted);
        foreach ($roles as $role) {
            $content = $msgtest->get_message($role);
            $this->assertEquals('Title 0', $content->name);
            $this->assertEquals('Body 0', $content->content);
        }
    }

    public function test_delete() {
        $this->resetAfterTest();
        list($appraisal) = $this->prepare_appraisal_with_users();
        $map = $this->map($appraisal);
        list($appraisal2) = $this->prepare_appraisal_with_users();
        $roles = array(appraisal::ROLE_LEARNER, appraisal::ROLE_MANAGER);

        // Create three messages: 2x appraisal, and Stage.
        $msg1 = new appraisal_message();
        $msg1->event_stage($map['stages']['Stage'], appraisal_message::EVENT_STAGE_DUE);
        $msg1->set_delta(-3, appraisal_message::PERIOD_DAY);
        $msg1->set_roles($roles, 0);
        $msg1->set_message(0, 'Title 0', 'Body 0');
        $msg1->save();
        $msg1id = $msg1->id;
        unset($msg1);

        $msg2 = new appraisal_message();
        $msg2->event_appraisal($appraisal->id);
        $msg2->set_roles($roles, 0);
        $msg2->set_message(0, 'Title 0', 'Body 0');
        $msg2->save();
        $msg2id = $msg2->id;
        unset($msg2);

        $msg3 = new appraisal_message();
        $msg3->event_appraisal($appraisal->id);
        $msg3->set_roles($roles, 0);
        $msg3->set_message(0, 'Title 0', 'Body 0');
        $msg3->save();
        $msg3id = $msg3->id;
        unset($msg3);

        $msg4 = new appraisal_message();
        $msg4->event_appraisal($appraisal2->id);
        $msg4->set_roles($roles, 0);
        $msg4->set_message(0, 'Title 0', 'Body 0');
        $msg4->save();
        $msg4id = $msg4->id;
        unset($msg4);

        // Delete one related to appraisal.
        appraisal_message::delete($msg2id);
        $list1 = appraisal_message::get_list($appraisal->id);
        $this->assertCount(2, $list1);
        $this->assertArrayHasKey($msg1id, $list1);
        $this->assertArrayHasKey($msg3id, $list1);

        // Delete stage1.
        appraisal_message::delete_stage($map['stages']['Stage']);
        $list2 = appraisal_message::get_list($appraisal->id);
        $this->assertCount(1, $list2);
        $this->assertArrayHasKey($msg3id, $list1);

        // Delete all appraisal.
        appraisal_message::delete_appraisal($appraisal->id);
        $list3 = appraisal_message::get_list($appraisal->id);
        $this->assertEmpty($list3);
        $list4 = appraisal_message::get_list($appraisal2->id);
        $this->assertCount(1, $list4);
        $this->assertArrayHasKey($msg4id, $list4);
    }

    public function test_is_time() {
        $this->resetAfterTest();
        list($appraisal) = $this->prepare_appraisal_with_users();
        $map = $this->map($appraisal);
        $roles = array(appraisal::ROLE_LEARNER);

        $msgpast = new appraisal_message();
        $msgpast->event_stage($map['stages']['Stage'], appraisal_message::EVENT_STAGE_DUE);
        $msgpast->set_delta(-1, appraisal_message::PERIOD_DAY);
        $msgpast->set_roles($roles, 0);
        $msgpast->set_message(0, 'Title 0', 'Body 0');
        $msgpast->save();
        $msgpastid = $msgpast->id;
        unset($msgpast);

        $msgfuture = new appraisal_message();
        $msgfuture->event_stage($map['stages']['Stage'], appraisal_message::EVENT_STAGE_DUE);
        $msgfuture->set_delta(2, appraisal_message::PERIOD_WEEK);
        $msgfuture->set_roles($roles, 0);
        $msgfuture->set_message(0, 'Title 0', 'Body 0');
        $msgfuture->save();
        $msgfutureid = $msgfuture->id;
        unset($msgfuture);

        $appraisal->validate();
        $appraisal->activate();
        $stage = new appraisal_stage($map['stages']['Stage']);
        $stagedue = $stage->timedue;

        $msgpastact = new appraisal_message($msgpastid);
        $msgfutureact = new appraisal_message($msgfutureid);

        // Check past time (happened).
        $pstistime = $stagedue - 86400;

        $this->assertTrue($msgpastact->is_time($pstistime));

        // Check past time (not happened).
        $pstnotistime = $stagedue - 86400 - 1;
        $this->assertFalse($msgpastact->is_time($pstnotistime));

        // Check future time (happened).
        $ftristime = $stagedue + 86400 * 14;
        $this->assertTrue($msgfutureact->is_time($ftristime));

        // Check future time (not happened).
        $ftrnotistime = $stagedue + 86400 * 14 - 1;
        $this->assertFalse($msgfutureact->is_time($ftrnotistime));
    }

    public function test_duplicate_appraisal() {
        $this->resetAfterTest();
        list($appraisal) = $this->prepare_appraisal_with_users();
        list($appraisal2) = $this->prepare_appraisal_with_users();
        $map = $this->map($appraisal);
        $map2 = $this->map($appraisal2);
        $roles = array(appraisal::ROLE_LEARNER);

        $msgappr = new appraisal_message();
        $msgappr->event_appraisal($appraisal->id);
        $msgappr->set_delta(-1, appraisal_message::PERIOD_DAY);
        $msgappr->set_roles($roles, 1);
        $msgappr->set_message(0, 'Title 0', 'Body 0');
        $msgappr->save();
        $msgapprid = $msgappr->id;
        unset($msgappr);

        $appraisal->validate();
        $appraisal->activate();

        // Check appraisal activation.
        appraisal_message::duplicate_appraisal($appraisal->id, $appraisal2->id);
        $list = appraisal_message::get_list($appraisal2->id);
        $this->assertCount(1, $list);
        $msgtest = new appraisal_message(current($list)->id);
        $this->assertEquals(0, $msgtest->timescheduled);
        $this->assertGreaterThan($msgapprid, $msgtest->id);
        $this->assertEquals($appraisal2->id, $msgtest->appraisalid);
        $this->assertEquals(appraisal_message::EVENT_APPRAISAL_ACTIVATION, $msgtest->type);
        $this->assertEquals(-1, $msgtest->delta);
        $this->assertEquals(1, $msgtest->deltaperiod);
        $this->assertEquals($roles, $msgtest->roles);
        $this->assertEquals(1, $msgtest->stageiscompleted);
        foreach ($roles as $role) {
            $content = $msgtest->get_message($role);
            $this->assertEquals('Title 0', $content->name);
            $this->assertEquals('Body 0', $content->content);
        }
    }

    public function test_duplicate_stage() {
        $this->resetAfterTest();
        list($appraisal) = $this->prepare_appraisal_with_users();
        list($appraisal2) = $this->prepare_appraisal_with_users();
        $map = $this->map($appraisal);
        $map2 = $this->map($appraisal2);
        $roles = array(appraisal::ROLE_LEARNER);

        $msgstage = new appraisal_message();
        $msgstage->event_stage($map['stages']['Stage'], appraisal_message::EVENT_STAGE_DUE);
        $msgstage->set_delta(-1, appraisal_message::PERIOD_DAY);
        $msgstage->set_roles($roles, 1);
        $msgstage->set_message(0, 'Title 0', 'Body 0');
        $msgstage->save();
        $msgstageid = $msgstage->id;
        unset($msgstage);

        $appraisal->validate();
        $appraisal->activate();

        // Check stage duplicate.
        appraisal_message::duplicate_stage($map['stages']['Stage'], $map2['stages']['Stage']);

        $list = appraisal_message::get_list($appraisal2->id);
        $this->assertCount(1, $list);
        $msgtest = new appraisal_message(current($list)->id);
        $this->assertEquals(0, $msgtest->timescheduled);
        $this->assertGreaterThan($msgstageid, $msgtest->id);
        $this->assertEquals($appraisal2->id, $msgtest->appraisalid);
        $this->assertEquals($map2['stages']['Stage'], $msgtest->stageid);
        $this->assertEquals(appraisal_message::EVENT_STAGE_DUE, $msgtest->type);
        $this->assertEquals(-1, $msgtest->delta);
        $this->assertEquals(1, $msgtest->deltaperiod);
        $this->assertEquals($roles, $msgtest->roles);
        $this->assertEquals(1, $msgtest->stageiscompleted);
        foreach ($roles as $role) {
            $content = $msgtest->get_message($role);
            $this->assertEquals('Title 0', $content->name);
            $this->assertEquals('Body 0', $content->content);
        }
    }

    public function test_set_message() {
        $this->resetAfterTest();
        list($appraisal) = $this->prepare_appraisal_with_users();
        $map = $this->map($appraisal);
        $roles = array(appraisal::ROLE_LEARNER, appraisal::ROLE_MANAGER, appraisal::ROLE_APPRAISER, appraisal::ROLE_TEAM_LEAD);

        // Separate messages for roles.
        $msg = new appraisal_message();
        $msg->set_roles($roles, 0);
        foreach ($roles as $role) {
            $msg->set_message($role, 'Title '.$role, 'Body '.$role);
        }
        foreach ($roles as $role) {
            $content = $msg->get_message($role);
            $this->assertEquals('Title '.$role, $content->name);
            $this->assertEquals('Body '.$role, $content->content);
        }

        // Common message for roles.
        $msg2 = new appraisal_message();
        $msg2->set_roles($roles, 0);
        $msg->set_message(0, 'Title', 'Body');
        foreach ($roles as $role) {
            $content = $msg->get_message($role);
            $this->assertEquals('Title', $content->name);
            $this->assertEquals('Body', $content->content);
        }
    }

    public function test_get_schedule_from() {
        $this->resetAfterTest();
        list($appraisal) = $this->prepare_appraisal_with_users();
        $map = $this->map($appraisal);

        $msgappr = new appraisal_message();
        $msgappr->event_appraisal($appraisal->id);

        $msgstage = new appraisal_message();
        $msgstage->event_stage($map['stages']['Stage'], appraisal_message::EVENT_STAGE_DUE);
        $stage = new appraisal_stage($map['stages']['Stage']);
        $stagedue = $stage->timedue;

        // Check appraisal event +3 days.
        $msgappr->set_delta(3, appraisal_message::PERIOD_DAY);
        $this->assertEquals(1000259200, $msgappr->get_schedule_from(1000000000));

        // Check appraisal event immediate.
        $msgappr->set_delta(0);
        $this->assertEquals(1000000000, $msgappr->get_schedule_from(1000000000));

        // Check stagedue event -1 months.
        $msgstage->set_delta(-1, appraisal_message::PERIOD_MONTH);
        $this->assertEquals($stagedue - 2592000, $msgstage->get_schedule_from(1000000000));

        // Check stagedue event immediate.
        $msgstage->set_delta(0);
        $this->assertEquals($stagedue, $msgstage->get_schedule_from(1000000000));

        // Check stagedue event -1 week.
        $msgstage->set_delta(-1, appraisal_message::PERIOD_WEEK);
        $this->assertEquals($stagedue - 604800, $msgstage->get_schedule_from(1000000000));
    }

    public function test_process_event() {
        global $CFG, $UNITTEST;
        // Function in lib/moodlelib.php email_to_user require this.
        if (!isset($UNITTEST)) {
            $UNITTEST = new stdClass();
            $UNITTEST->running = true;
        }
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $oldlog = ini_get('error_log');
        ini_set('error_log', "$CFG->dataroot/testlog.log"); // Prevent standard logging.
        unset_config('noemailever');

        $user = $this->getDataGenerator()->create_user();
        list($appraisal) = $this->prepare_appraisal_with_users(array(), array($user));

        $map = $this->map($appraisal);
        $roles = array(appraisal::ROLE_LEARNER);
        $stage = new appraisal_stage($map['stages']['Stage']);
        $stagedue = $stage->timedue;

        $msgapprnow = new appraisal_message();
        $msgapprnow->event_appraisal($appraisal->id);
        $msgapprnow->set_delta(0);
        $msgapprnow->set_roles($roles, 1);
        $msgapprnow->set_message(0, 'Title 0', 'Body 0');
        $msgapprnow->save();
        $msgapprnowid = $msgapprnow->id;
        unset($msgapprnow);

        $msgstageapprltr = new appraisal_message();
        $msgstageapprltr->event_appraisal($appraisal->id);
        $msgstageapprltr->set_delta(1, appraisal_message::PERIOD_DAY);
        $msgstageapprltr->set_roles($roles, 1);
        $msgstageapprltr->set_message(0, 'Title 0', 'Body 0');
        $msgstageapprltr->save();
        $msgstageapprltrid = $msgstageapprltr->id;
        unset($msgstageapprltr);

        $msgstageahead = new appraisal_message();
        $msgstageahead->event_stage($map['stages']['Stage'], appraisal_message::EVENT_STAGE_DUE);
        $msgstageahead->set_delta(-1, appraisal_message::PERIOD_DAY);
        $msgstageahead->set_roles($roles, 1);
        $msgstageahead->set_message(0, 'Title 0', 'Body 0');
        $msgstageahead->save();
        $msgstageaheadid = $msgstageahead->id;
        unset($msgstageahead);

        $msgstagecomp = new appraisal_message();
        $msgstagecomp->event_stage($map['stages']['Stage'], appraisal_message::EVENT_STAGE_COMPLETE);
        $msgstagecomp->set_delta(0);
        $msgstagecomp->set_roles($roles, 1);
        $msgstagecomp->set_message(0, 'Title 0', 'Body 0');
        $msgstagecomp->save();
        $msgstagecompid = $msgstagecomp->id;
        unset($msgstagecomp);

        $wastime = time() - 1;
        $appraisal->validate();

        // Make sure we are redirecting emails.
        $sink = $this->redirectEmails();
        $this->assertTrue(phpunit_util::is_redirecting_phpmailer());

        $appraisal->activate();

        // Get the email that we just sent.
        $emails = $sink->get_messages();
        $this->assertCount(1, $sink->get_messages());
        $sink->close();

        $nowtime = time() + 1;

        $msgapprnowtest = new appraisal_message($msgapprnowid);
        $msgstageapprltrtest = new appraisal_message($msgstageapprltrid);
        $msgstageaheadtest = new appraisal_message($msgstageaheadid);

        // Take into account time changes.
        // Check event activation - immediate.
        $this->assertEquals(1, $msgapprnowtest->wastriggered);
        // Check event activation - postponed.
        $this->assertEquals(0, $msgstageapprltrtest->wastriggered);
        $this->assertGreaterThan($wastime + 86400, $msgstageapprltrtest->timescheduled);
        $this->assertLessThan($nowtime + 86400, $msgstageapprltrtest->timescheduled);
        // Check event stage due - ahead notification.
        $this->assertEquals(0, $msgstageaheadtest->wastriggered);
        $this->assertEquals($stagedue - 86400, $msgstageaheadtest->timescheduled);
        // Check event stage completion - immediate (not completed).
        $this->assertEquals(0, $msgstageaheadtest->wastriggered);
        // Check event stage completion - immediate (completed).
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $user->id, $user->id, appraisal::ROLE_LEARNER);

        // Redirect emails.
        $sink2 = $this->redirectEmails();

        $this->answer_question($appraisal, $roleassignment, 0, 'completestage');

        $emails = $sink2->get_messages();
        $this->assertCount(1, $sink2->get_messages());
        $sink2->close();

        $msgstageiscomptest = new appraisal_message($msgstagecompid);
        $this->assertEquals(1, $msgstageiscomptest->wastriggered);

        ini_set('error_log', $oldlog);
    }
}
