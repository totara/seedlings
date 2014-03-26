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
 * Unit tests for appraisal class of totara/appraisal/lib.php
 */
global $CFG;
require_once($CFG->dirroot.'/totara/appraisal/tests/appraisal_testcase.php');

class appraisal_test extends appraisal_testcase {

    public function skip_test_set_status() {
        $appraisal = new appraisal();

        $this->setExpectedException('appraisal_exception');
        $appraisal->set_status($appraisal::STATUS_CLOSED);

        $appraisal->set_status($appraisal::STATUS_ACTIVE);
        $this->assertNull($appraisal->timefinished);

        $appraisal->set_status($appraisal::STATUS_COMPLETED);
        $this->assertNotNull($appraisal->timefinished);
    }

    public function test_appraisal_create() {
        $this->resetAfterTest();
        $appraisal = new appraisal();
        $data = new stdClass();
        $data->name = 'Appraisal 1';
        $data->description = 'description';
        $appraisal->set($data);
        $appraisal->save();
        $id = $appraisal->id;
        unset($appraisal);

        $check = new appraisal($id);
        $this->assertEquals($check->id, $id);
        $this->assertEquals($check->name, 'Appraisal 1');
        $this->assertEquals($check->description, 'description');
    }

    public function test_appraisal_edit() {
        $this->resetAfterTest();
        $def = array('name' => 'Appraisal', 'description' => 'Description');
        $appraisal = appraisal::build($def);

        $this->assertEquals($appraisal->name, 'Appraisal');
        $this->assertEquals($appraisal->description, 'Description');

        $data = new stdClass();
        $data->name = 'New Appraisal';
        $data->description = 'New Description';
        $appraisal->set($data)->save();
        $check = new appraisal($appraisal->id);
        unset($appraisal);
        $this->assertEquals($check->name, $data->name);
        $this->assertEquals($check->description, $data->description);
    }

    public function test_appraisal_delete() {
        $this->resetAfterTest();
        $wasappraisals = appraisal::fetch_all();
        $def1 = array('name' => 'Appraisal1');
        $def2 = array('name' => 'Appraisal2');
        $appraisal1 = appraisal::build($def1);
        $appraisal2 = appraisal::build($def1);
        $appraisal1->delete();
        $nowappraisals = appraisal::fetch_all();

        $this->assertEquals(count($wasappraisals)+1, count($nowappraisals));
        $this->assertTrue(isset($nowappraisals[$appraisal2->id]));
    }

    public function test_appraisal_duplicate() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $def = array('name' => 'Appraisal', 'description' => 'Description');
        $appraisal1 = appraisal::build($def);
        $cloned = appraisal::duplicate_appraisal($appraisal1->id);
        $appraisal2 = new appraisal($cloned->id);

        $this->assertEquals($appraisal1->name, $appraisal2->name);
        $this->assertEquals($appraisal1->description, $appraisal2->description);
        $this->assertGreaterThan($appraisal1->id, $appraisal2->id);
        $this->assertEmpty($appraisal2->timestarted);
        $this->assertEmpty($appraisal2->timefinished);
        $this->assertEquals($appraisal1->status, appraisal::STATUS_DRAFT);
    }

    public function test_appraisal_activate() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        list($appraisal1) = $this->prepare_appraisal_with_users();
        $this->assertEmpty($appraisal1->validate());

        $appraisal1->activate();

        $this->assertEquals(appraisal::STATUS_ACTIVE, $appraisal1->status);
        $dbman = $DB->get_manager();
        $this->assertTrue($dbman->table_exists('appraisal_quest_data_'.$appraisal1->id));
        $assign2 = new totara_assign_appraisal('appraisal', $appraisal1);
        $this->assertTrue($assign2->assignments_are_stored());
        $this->assertCount(2, $assign2->get_current_users());
    }

    public function test_appraisal_validate_wrong_status() {
        $this->resetAfterTest();
        $this->setAdminUser();

        list($appraisal) = $this->prepare_appraisal_with_users();
        $this->assertEmpty($appraisal->validate());
        $appraisal->activate();

        $res = $appraisal->validate();
        $this->assertCount(1, $res);
        $this->assertEquals(array('status'), array_keys($res));

    }

    public function test_appraisal_validate_no_roles() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'Stage', 'timedue' => time() + 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text', 'type' => 'text', 'roles' => array(appraisal::ROLE_LEARNER => 1))
                ))
            ))
        ));
        list($appraisal) = $this->prepare_appraisal_with_users($def);
        $res = $appraisal->validate();
        $this->assertArrayHasKey('roles', $res);
    }

    public function test_appraisal_answers() {
        $this->resetAfterTest();
        $this->setAdminUser();
        list($appraisal, $users) = $this->prepare_appraisal_with_users();
        $appraisal->validate();
        $appraisal->activate();

        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $users[0]->id, $users[0]->id,
                appraisal::ROLE_LEARNER);
        $this->answer_question($appraisal, $roleassignment, 0, 'completestage');
        $map = $this->map($appraisal);

        $saved = $appraisal->get_answers($map['pages']['Page'], $roleassignment);

        $questions = appraisal_question::fetch_appraisal($appraisal->id, null, null, array(), false);
        $question = new appraisal_question(current($questions)->id, $roleassignment);
        $field = $question->get_element()->get_prefix_form();

        $this->assertEquals('test', $saved->$field);
    }

    public function test_appraisal_complete_user() {
        $this->resetAfterTest();
        $this->setAdminUser();
        list($appraisal, $users) = $this->prepare_appraisal_with_users();
        $appraisal->validate();
        $appraisal->activate();

        $this->assertEquals(2, $appraisal->count_incomplete_userassignments());

        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $users[0]->id, $users[0]->id,
                appraisal::ROLE_LEARNER);
        $this->answer_question($appraisal, $roleassignment, 0, 'completestage');

        $updateduserassignment = appraisal_user_assignment::get_user($appraisal->id, $users[0]->id);
        $updateduser2assignment = appraisal_user_assignment::get_user($appraisal->id, $users[1]->id);

        $this->assertEquals(1, $appraisal->count_incomplete_userassignments());
        $this->assertTrue($appraisal->is_locked($updateduserassignment));
        $this->assertFalse($appraisal->is_locked($updateduser2assignment));
        $this->assertEquals(appraisal::STATUS_ACTIVE, $appraisal->status);
    }

    public function test_appraisal_complete() {
        $this->resetAfterTest();
        $this->setAdminUser();
        list($appraisal, $users) = $this->prepare_appraisal_with_users();
        $appraisal->validate();
        $appraisal->activate();

        $this->assertEquals(2, $appraisal->count_incomplete_userassignments());

        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $users[0]->id, $users[0]->id,
                appraisal::ROLE_LEARNER);
        $roleassignment2 = appraisal_role_assignment::get_role($appraisal->id, $users[1]->id, $users[1]->id,
                appraisal::ROLE_LEARNER);
        $this->answer_question($appraisal, $roleassignment, '', 'completestage');
        $this->answer_question($appraisal, $roleassignment2, '', 'completestage');

        $updateduserassignment = appraisal_user_assignment::get_user($appraisal->id, $users[0]->id);
        $updateduser2assignment = appraisal_user_assignment::get_user($appraisal->id, $users[1]->id);

        $this->assertEquals(0, $appraisal->count_incomplete_userassignments());
        $this->assertTrue($appraisal->is_locked($updateduserassignment));
        $this->assertTrue($appraisal->is_locked($updateduser2assignment));
        $this->assertEquals(appraisal::STATUS_COMPLETED, $appraisal->status);
    }

    public function test_appraisal_role_involved() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'Stage', 'timedue' => time() + 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text', 'type' => 'text', 'roles' => array(
                        appraisal::ROLE_LEARNER => 7,
                        appraisal::ROLE_MANAGER => 1,
                        appraisal::ROLE_APPRAISER => 2
                    ))
                ))
            ))
        ));
        list($appraisal) = $this->prepare_appraisal_with_users($def);

        $all = $appraisal->get_roles_involved();
        $this->assertContains(appraisal::ROLE_LEARNER, $all);
        $this->assertContains(appraisal::ROLE_MANAGER, $all);
        $this->assertContains(appraisal::ROLE_APPRAISER, $all);
        $this->assertNotContains(appraisal::ROLE_TEAM_LEAD, $all);

        $canviewothers = $appraisal->get_roles_involved(1);
        $this->assertContains(appraisal::ROLE_LEARNER, $canviewothers);
        $this->assertContains(appraisal::ROLE_MANAGER, $canviewothers);
        $this->assertNotContains(appraisal::ROLE_APPRAISER, $canviewothers);
        $this->assertNotContains(appraisal::ROLE_TEAM_LEAD, $canviewothers);

        $cananswer = $appraisal->get_roles_involved(2);
        $this->assertContains(appraisal::ROLE_LEARNER, $cananswer);
        $this->assertNotContains(appraisal::ROLE_MANAGER, $cananswer);
        $this->assertContains(appraisal::ROLE_APPRAISER, $cananswer);
        $this->assertNotContains(appraisal::ROLE_TEAM_LEAD, $cananswer);
    }

    public function test_appraisal_get_user_appraisal() {
        $this->resetAfterTest();
        $this->setAdminUser();
        list($appraisal1, $users1) = $this->prepare_appraisal_with_users();
        list($appraisal2) = $this->prepare_appraisal_with_users(array(), $users1);
        list($appraisal3, $users3) = $this->prepare_appraisal_with_users();
        foreach (array($appraisal1, $appraisal2, $appraisal3) as $appr) {
            $appr->validate();
            $appr->activate();
        }
        $appraisal2->close();
        $user4 = $this->getDataGenerator()->create_user();

        $this->setUser($users1[0]);
        $users1allappr = appraisal::get_user_appraisals($users1[0]->id, appraisal::ROLE_LEARNER);
        $users1actappr = appraisal::get_user_appraisals($users1[0]->id, appraisal::ROLE_LEARNER, appraisal::STATUS_CLOSED);
        $this->setUser($users3[0]);
        $users3actappr = appraisal::get_user_appraisals($users3[0]->id, appraisal::ROLE_LEARNER, appraisal::STATUS_CLOSED);
        $users3drftappr = appraisal::get_user_appraisals($users3[0]->id, appraisal::ROLE_LEARNER, appraisal::STATUS_ACTIVE);
        $users3managappr = appraisal::get_user_appraisals($users3[0]->id, appraisal::ROLE_MANAGER);
        $user4appr = appraisal::get_user_appraisals($user4->id, appraisal::ROLE_LEARNER);

        $this->assertCount(2, $users1allappr);
        $this->assertContains($appraisal1->id, array(current($users1allappr)->id, next($users1allappr)->id));
        $this->assertCount(1, $users1actappr);
        $this->assertEquals($appraisal2->id, current($users1actappr)->id);
        $this->assertEmpty($users3actappr);
        $this->assertCount(1, $users3drftappr);
        $this->assertEquals($appraisal3->id, current($users3drftappr)->id);
        $this->assertEmpty($users3managappr);
        $this->assertEmpty($user4appr);
    }
}