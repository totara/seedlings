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
 * Unit tests for appraisal_question class of totara/appraisal/lib.php
 */
global $CFG;
require_once($CFG->dirroot.'/totara/appraisal/tests/appraisal_testcase.php');

class appraisal_question_test extends appraisal_testcase {

    /**
     * Appraisal with one stage, two pages, and four questions (3 in Page1 + 1 in Page2)
     * @var array
     */
    protected $def = array();

    /**
     * Appraisal with one stage, two pages, and four questions (3 in Page1 + 1 in Page2) and two roles
     * @var array
     */
    protected $defmngr = array();

    public function setUp() {
        parent::setUp();
        $this->def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => time() + 86400, 'locks' => array(appraisal::ROLE_LEARNER => 1), 'pages' => array(
                array('name' => 'Page1', 'questions' => array(
                    array('name' => 'Text1', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3)),
                    array('name' => 'Text2', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3)),
                    array('name' => 'Text3', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3))
                )),
                array('name' => 'Page2', 'questions' => array(
                    array('name' => 'Text4', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3))
                ))
            ))
        ));
        $this->defmngr = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => time() + 86400, 'locks' => array(appraisal::ROLE_LEARNER => 1), 'pages' => array(
                array('name' => 'Page1', 'questions' => array(
                    array('name' => 'Text1', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 1, appraisal::ROLE_MANAGER => 3)),
                    array('name' => 'Text2', 'type' => 'longtext',
                          'roles' => array(appraisal::ROLE_LEARNER => 2, appraisal::ROLE_MANAGER => 7)),
                    array('name' => 'Text3', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3))
                )),
                array('name' => 'Page2', 'questions' => array(
                    array('name' => 'Text4', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 6, appraisal::ROLE_MANAGER => 2))
                ))
            ))
        ));
    }
    public function test_question_create() {
        $this->resetAfterTest();
        $appraisal = appraisal::build(array('name' => 'Appraisal', 'stages' => array(array('name' => 'St1', 'pages' => array(
            array('name' => 'Page1')
        )))));
        $map = $this->map($appraisal);

        $data = new stdClass();
        $data->id = 0;
        $data->name = 'Question';
        $data->roles = array(appraisal::ROLE_LEARNER => appraisal::ACCESS_CANANSWER);
        $data->sortorder = 5;
        $data->appraisalstagepageid = $map['pages']['Page1'];
        $question = new appraisal_question();
        $question->attach_element('text');
        $question->set($data);
        $question->save();
        $questionid = $question->id;
        unset($question);
        $questiontest = new appraisal_question($questionid);

        $this->assertGreaterThan(0, $questiontest->id);
        $this->assertEquals('Question', $questiontest->name);
        $this->assertEquals(0, $questiontest->sortorder);
        $this->assertCount(1, $questiontest->roles);
        $this->assertEquals(array(appraisal::ROLE_LEARNER => appraisal::ACCESS_CANANSWER), $questiontest->roles);
    }

    public function test_question_edit() {
        $this->resetAfterTest();
        $appraisal = appraisal::build(array('name' => 'Appraisal', 'stages' => array(array('name' => 'St1', 'pages' => array(
            array('name' => 'Page1')
        )))));
        $map = $this->map($appraisal);

        $data = new stdClass();
        $data->id = 0;
        $data->name = 'Question';
        $data->roles = array(appraisal::ROLE_LEARNER => appraisal::ACCESS_CANANSWER);
        $data->sortorder = 5;
        $data->appraisalstagepageid = $map['pages']['Page1'];
        $question = new appraisal_question();
        $question->attach_element('text');
        $question->set($data);
        $question->save();
        $questionid = $question->id;
        unset($question);
        $questionedit = new appraisal_question($questionid);
        $dataedit = new stdClass();
        $dataedit->id = 0;
        $dataedit->name = 'Question2';
        $dataedit->sortorder = 3;
        $dataedit->roles = array(appraisal::ROLE_APPRAISER => appraisal::ACCESS_CANVIEWOTHER);
        $questionedit->set($dataedit)->save();
        $questioneditid = $questionedit->id;
        unset($questionedit);
        $questiontest = new appraisal_question($questioneditid);

        $this->assertEquals($questionid, $questionid);
        $this->assertEquals('Question2', $questiontest->name);
        $this->assertEquals(0, $questiontest->sortorder);
        $this->assertCount(1, $questiontest->roles);
        $this->assertEquals(array(appraisal::ROLE_APPRAISER => appraisal::ACCESS_CANVIEWOTHER), $questiontest->roles);
    }

    public function test_question_delete() {
        $this->resetAfterTest();
        $appraisal = appraisal::build($this->def);
        $map = $this->map($appraisal);
        appraisal_question::delete($map['questions']['Text1']);
        $questions = appraisal_question::fetch_page($map['pages']['Page1']);

        $this->assertCount(2, $questions);
        $this->assertArrayHasKey($map['questions']['Text2'], $questions);
        $this->assertArrayHasKey($map['questions']['Text3'], $questions);
    }

    public function test_question_duplicate() {
        $this->resetAfterTest();

        $appraisal = appraisal::build($this->def);
        $map = $this->map($appraisal);

        $quest1 = new appraisal_question($map['questions']['Text1']);
        $quest2 = $quest1->duplicate($map['pages']['Page2']);

        $this->assertEquals($map['questions']['Text1'], $quest1->id);
        $this->assertGreaterThan($quest1->id, $quest2->id);
        $this->assertEquals($map['pages']['Page2'], $quest2->appraisalstagepageid);
        $this->assertEquals('Text1', $quest2->name);
        $this->assertEquals('text', $quest2->datatype);
        $this->assertEquals(1, $quest2->sortorder);
        $this->assertEquals(0, $quest1->sortorder);
        $this->assertNotEquals(spl_object_hash($quest1->get_element()), spl_object_hash($quest2->get_element()));
    }

    public function test_question_reorder() {
        $this->resetAfterTest();

        $appraisal = appraisal::build($this->def);
        $map = $this->map($appraisal);

        // Move first text to last position.
        appraisal_question::reorder($map['questions']['Text1'], 2);
        $text1 = new appraisal_question($map['questions']['Text1']);
        $text2 = new appraisal_question($map['questions']['Text2']);
        $text3 = new appraisal_question($map['questions']['Text3']);
        $this->assertEquals(0, $text2->sortorder);
        $this->assertEquals(1, $text3->sortorder);
        $this->assertEquals(2, $text1->sortorder);

        // Move second text to first position (Text3 became second after first reordering).
        appraisal_question::reorder($map['questions']['Text3'], 0);
        $text1->load();
        $text2->load();
        $text3->load();
        $this->assertEquals(0, $text3->sortorder);
        $this->assertEquals(1, $text2->sortorder);
        $this->assertEquals(2, $text1->sortorder);
    }

    public function test_question_fetch_appraisal() {
        $this->resetAfterTest();
        $appraisal = appraisal::build($this->defmngr);
        $map = $this->map($appraisal);

        // Check all appraisal.
        $all = appraisal_question::fetch_appraisal($appraisal->id);
        $this->assertCount(4, $all);
        // Check only certain role: Manager.
        $mngr = appraisal_question::fetch_appraisal($appraisal->id, appraisal::ROLE_MANAGER);
        $this->assertCount(3, $mngr);
        $this->assertArrayNotHasKey($map['questions']['Text3'], $mngr);
        // Check only certain rights: Can view.
        $view = appraisal_question::fetch_appraisal($appraisal->id, null, appraisal::ACCESS_CANVIEWOTHER);
        $this->assertCount(3, $view);
        $this->assertArrayNotHasKey($map['questions']['Text4'], $view);
        // Check only datatypes.
        $text = appraisal_question::fetch_appraisal($appraisal->id, null, null, 'text');
        $this->assertCount(3, $text);
        $this->assertArrayNotHasKey($map['questions']['Text2'], $text);
        $this->assertArrayHasKey($map['questions']['Text3'], $text);
        // Check role and rights: Learner + Can Answer.
        $lrnranswr = appraisal_question::fetch_appraisal($appraisal->id, appraisal::ROLE_LEARNER, appraisal::ACCESS_CANANSWER);
        $this->assertCount(3, $lrnranswr);
        $this->assertArrayNotHasKey($map['questions']['Text1'], $lrnranswr);
        // Check role and rights and datatype: Learner + Can Answer + text.
        $lrnranswrtext = appraisal_question::fetch_appraisal($appraisal->id, appraisal::ROLE_LEARNER, appraisal::ACCESS_CANANSWER,
                'text');
        $this->assertCount(2, $lrnranswrtext);
        $this->assertArrayHasKey($map['questions']['Text3'], $lrnranswrtext);
        $this->assertArrayHasKey($map['questions']['Text4'], $lrnranswrtext);
        // Check instances.
        $inst = appraisal_question::fetch_appraisal($appraisal->id, appraisal::ROLE_LEARNER, null, '', true);
        $this->assertCount(4, $inst);
        $this->assertContainsOnlyInstancesOf('appraisal_question', $inst);
    }

    public function test_question_fetch_page_role() {
        $this->resetAfterTest();
        list($appraisal, $users) = $this->prepare_appraisal_with_users($this->defmngr);
        $appraisal->validate();
        $appraisal->activate();
        $map = $this->map($appraisal);
        $user1 = current($users);

        $mngrassignment = appraisal_role_assignment::get_role($appraisal->id, $user1->id, 2, appraisal::ROLE_MANAGER);
        $lrnrassignment = appraisal_role_assignment::get_role($appraisal->id, $user1->id, $user1->id, appraisal::ROLE_LEARNER);

        // Check managers questions.
        $mngr = appraisal_question::fetch_page_role($map['pages']['Page1'], $mngrassignment);
        $this->assertCount(2, $mngr);
        $this->assertArrayNotHasKey($map['questions']['Text3'], $mngr);
        // Check learners questions.
        $lrnr = appraisal_question::fetch_page_role($map['pages']['Page1'], $lrnrassignment);
        $this->assertCount(3, $lrnr);
    }

    public function test_user_can_view() {
        $this->resetAfterTest();
        list($appraisal, $users) = $this->prepare_appraisal_with_users($this->defmngr);
        $appraisal->validate();
        $appraisal->activate();
        $map = $this->map($appraisal);
        $user1 = current($users);

        $mngrassignment = appraisal_role_assignment::get_role($appraisal->id, $user1->id, 2, appraisal::ROLE_MANAGER);
        $lrnrassignment = appraisal_role_assignment::get_role($appraisal->id, $user1->id, $user1->id, appraisal::ROLE_LEARNER);

        // Question Text1: Learner cannot view others, but manager can.
        $quest1 = new appraisal_question($map['questions']['Text1']);
        $this->assertTrue($quest1->user_can_view($lrnrassignment->id, 2));
        $this->assertTrue($quest1->user_can_view($mngrassignment->id, $user1->id));
        // Question Text2: Learner not view others, but manager can't.
        $quest2 = new appraisal_question($map['questions']['Text2']);
        $this->assertTrue($quest2->user_can_view($lrnrassignment->id, 2));
        $this->assertFalse($quest2->user_can_view($mngrassignment->id, $user1->id));
    }

    public function test_get_roles_involved() {
        $this->resetAfterTest();
        $appraisal = appraisal::build($this->defmngr);
        $map = $this->map($appraisal);
        $quest1 = new appraisal_question($map['questions']['Text1']);
        $quest3 = new appraisal_question($map['questions']['Text3']);

        // Check that Text1 involved two roles.
        $all = $quest1->get_roles_involved();
        $this->assertCount(2, $all);
        // Check that Text1 involved Manager who can answer.
        $mngr = $quest1->get_roles_involved(appraisal::ACCESS_CANANSWER);
        $this->assertCount(1, $mngr);
        $this->assertContains(appraisal::ROLE_MANAGER, $mngr);
        // Check that Text1 involved both roles who can view other.
        $view = $quest1->get_roles_involved(appraisal::ACCESS_CANVIEWOTHER);
        $this->assertCount(2, $view);
        $this->assertContains(appraisal::ROLE_MANAGER, $view);
        $this->assertContains(appraisal::ROLE_LEARNER, $view);
        // Check that Text3 has not roles who must answer.
        $none = $quest3->get_roles_involved(appraisal::ACCESS_MUSTANSWER);
        $this->assertEmpty($none);
    }

    public function test_move() {
        $this->resetAfterTest();

        $appraisal = appraisal::build($this->def);
        $map = $this->map($appraisal);

        $question1 = new appraisal_question($map['questions']['Text1']);
        $this->assertEquals(0, $question1->sortorder);
        $question1->move($map['pages']['Page2']);
        $this->assertEquals(0, $question1->sortorder);
        $this->assertEquals($map['pages']['Page2'], $question1->appraisalstagepageid);
    }

    public function test_is_locked() {
        $this->resetAfterTest();

        $def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => time() + 86400, 'locks' => array(appraisal::ROLE_LEARNER => 1), 'pages' => array(
                array('name' => 'Page1', 'questions' => array(
                    array('name' => 'Text1', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3))
                )),
            )),
            array('name' => 'St2', 'timedue' => time() + 2 * 86400, 'pages' => array(
                array('name' => 'Page2', 'questions' => array(
                    array('name' => 'Text2', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3))
                ))
            ))
        ));

        list($appraisal, $users) = $this->prepare_appraisal_with_users($def);
        $appraisal->validate();
        $appraisal->activate();
        $user = current($users);
        $user2 = next($users);
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $user->id, $user->id, appraisal::ROLE_LEARNER);
        $roleassignment2 = appraisal_role_assignment::get_role($appraisal->id, $user2->id, $user2->id, appraisal::ROLE_LEARNER);
        $map = $this->map($appraisal);

        $this->answer_question($appraisal, $roleassignment, $map['questions']['Text1'], 'completestage');
        $question1 = new appraisal_question($map['questions']['Text1']);
        $this->assertTrue($question1->is_locked($roleassignment));
        $this->assertFalse($question1->is_locked($roleassignment2));

        $this->answer_question($appraisal, $roleassignment, $map['questions']['Text2'], 'completestage');
        $question2 = new appraisal_question($map['questions']['Text2']);
        $this->assertTrue($question2->is_locked($roleassignment));
        $this->assertFalse($question2->is_locked($roleassignment2));
    }
}
