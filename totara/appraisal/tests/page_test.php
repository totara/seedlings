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
 * Unit tests for appraisal_page class of totara/appraisal/lib.php
 */
global $CFG;
require_once($CFG->dirroot.'/totara/appraisal/tests/appraisal_testcase.php');

class appraisal_page_test extends appraisal_testcase {

    /**
     * Appraisal with one stage, two pages (one for each stage), and one role.
     * @var array
     */
    protected $def = array();

    /**
     * Appraisal with one stage, three pages, and two roles.
     * @var array
     */
    protected $defmngr = array();

    public function setUp() {
        parent::setUp();
        $this->defmngr = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => time() + 86400, 'locks' => array(appraisal::ROLE_LEARNER => 1), 'pages' => array(
                array('name' => 'Page1', 'questions' => array(
                    array('name' => 'Text1', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3, appraisal::ROLE_MANAGER => 3))
                )),
                array('name' => 'Page2', 'questions' => array(
                    array('name' => 'Text2', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3, appraisal::ROLE_MANAGER => 3))
                )),
                array('name' => 'Page3', 'questions' => array(
                    array('name' => 'Text3', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3, appraisal::ROLE_MANAGER => 3))
                ))
            ))
        ));

        $this->def = array('name' => 'Appraisal', 'stages' => array(
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
    }

    public function test_page_add() {
        $this->resetAfterTest();
        $appraisal = appraisal::build(array('name' => 'Appraisal', 'stages' => array(array('name' => 'St1'))));
        $map = $this->map($appraisal);
        $data = new stdClass();
        $data->appraisalstageid = $map['stages']['St1'];
        $data->name = 'Page';
        $data->sortorder = 5;
        $page = new appraisal_page();
        $page->set($data);
        $page->save();

        $pagetest = new appraisal_page($page->id);
        // Check name and stage id.
        $this->assertEquals('Page', $pagetest->name);
        $this->assertEquals($map['stages']['St1'], $pagetest->appraisalstageid);
        // Check that sortorder is fixed to 0.
        $this->assertEquals(0, $pagetest->sortorder);
    }

    public function test_page_edit() {
        $this->resetAfterTest();
        $appraisal = appraisal::build($this->defmngr);
        $map = $this->map($appraisal);

        $page = new appraisal_page($map['pages']['Page1']);

        $data = new stdClass();
        $data->name = 'MyPage';
        $data->id = 10;

        $page->set($data);
        $page->save();

        $pagetest = new appraisal_page($map['pages']['Page1']);
        $this->assertEquals('MyPage', $pagetest->name);
        // Check that other values was not changed.
        $this->assertEquals($map['pages']['Page1'], $pagetest->id);
    }

    public function test_page_delete() {
        $this->resetAfterTest();
        $appraisal = appraisal::build($this->defmngr);
        $map = $this->map($appraisal);

        $page = new appraisal_page($map['pages']['Page1']);
        appraisal_page::delete($page->id);
        unset($page);

        $pages = appraisal_page::fetch_stage($map['stages']['St1']);
        $this->assertCount(2, $pages);
        $this->assertArrayHasKey($map['pages']['Page2'], $pages);
        $this->assertArrayHasKey($map['pages']['Page3'], $pages);
    }

    public function test_page_reorder() {
        $this->resetAfterTest();
        $appraisal = appraisal::build($this->defmngr);
        $map = $this->map($appraisal);

        // Move first page to last position.
        appraisal_page::reorder($map['pages']['Page1'], 2);
        $page1 = new appraisal_page($map['pages']['Page1']);
        $page2 = new appraisal_page($map['pages']['Page2']);
        $page3 = new appraisal_page($map['pages']['Page3']);
        $this->assertEquals(0, $page2->sortorder);
        $this->assertEquals(1, $page3->sortorder);
        $this->assertEquals(2, $page1->sortorder);

        // Move second page to first position (Page3 became second after first reordering).
        appraisal_page::reorder($map['pages']['Page3'], 0);
        $page1->load();
        $page2->load();
        $page3->load();
        $this->assertEquals(0, $page3->sortorder);
        $this->assertEquals(1, $page2->sortorder);
        $this->assertEquals(2, $page1->sortorder);
    }

    public function test_page_move() {
        $this->resetAfterTest();

        $appraisal = appraisal::build($this->def);
        $map = $this->map($appraisal);

        $page1 = new appraisal_page($map['pages']['Page1']);
        $this->assertEquals(0, $page1->sortorder);
        $page1->move($map['stages']['St2']);
        $this->assertEquals(1, $page1->sortorder);
        $this->assertEquals($map['stages']['St2'], $page1->appraisalstageid);
    }

    public function test_page_duplicate() {
        $this->resetAfterTest();
        $appraisal = appraisal::build($this->def);
        $map = $this->map($appraisal);

        $page1 = new appraisal_page($map['pages']['Page1']);
        $page2 = $page1->duplicate($map['stages']['St2']);

        $this->assertEquals($map['pages']['Page1'], $page1->id);
        $this->assertGreaterThan($page1->id, $page2->id);
        $this->assertEquals($map['stages']['St2'], $page2->appraisalstageid);
        $this->assertEquals('Page1', $page2->name);
        $this->assertEquals(1, $page2->sortorder);
        $this->assertEquals(0, $page1->sortorder);
    }

    public function test_page_complete_role() {
        $this->resetAfterTest();
        list($appraisal, $users) = $this->prepare_appraisal_with_users($this->defmngr);
        $appraisal->validate();
        $appraisal->activate();

        $user = current($users);
        $user2 = next($users);
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $user->id, $user->id, appraisal::ROLE_LEARNER);
        $map = $this->map($appraisal);
        $page = new appraisal_page($map['pages']['Page1']);
        $page->complete_for_role($roleassignment);
        $roleassignmenttest = appraisal_role_assignment::get_role($appraisal->id, $user->id, $user->id, appraisal::ROLE_LEARNER);
        $roleassignment2 =  appraisal_role_assignment::get_role($appraisal->id, $user2->id, $user2->id, appraisal::ROLE_LEARNER);
        $managerassignment = appraisal_role_assignment::get_role($appraisal->id, $user->id, 2, appraisal::ROLE_MANAGER);

        // Check that active page id changed for user1.
        $this->assertEquals($map['pages']['Page2'], $roleassignmenttest->activepageid);
        $this->assertEquals($map['pages']['Page2'], $roleassignment->activepageid);
        // Check that active page is not changed for user2.
        $this->assertNull($roleassignment2->activepageid);
        // Check that active page not changed for admin (manager).
        $this->assertNull($managerassignment->activepageid);
    }

    public function test_validate() {
        $this->resetAfterTest();
        $def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => time() + 86400, 'pages' => array(
                array('name' => 'Page1', 'questions' => array())
            ))
        ));
        list($appraisal) = $this->prepare_appraisal_with_users($def);
        $map = $this->map($appraisal);
        $page = new appraisal_page($map['pages']['Page1']);
        $err = $page->validate();
        // Page mustn't be empty.
        $this->assertArrayHasKey('page' . $page->id, $err);
    }

    public function test_is_locked() {
        $this->resetAfterTest();

        list($appraisal, $users) = $this->prepare_appraisal_with_users($this->def);
        $appraisal->validate();
        $appraisal->activate();
        $user = current($users);
        $user2 = next($users);
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $user->id, $user->id, appraisal::ROLE_LEARNER);
        $roleassignment2 = appraisal_role_assignment::get_role($appraisal->id, $user2->id, $user2->id, appraisal::ROLE_LEARNER);
        $map = $this->map($appraisal);

        $this->answer_question($appraisal, $roleassignment, $map['questions']['Text1'], 'completestage');
        $page1 = new appraisal_page($map['pages']['Page1']);
        $this->assertTrue($page1->is_locked($roleassignment));
        $this->assertFalse($page1->is_locked($roleassignment2));

        $this->answer_question($appraisal, $roleassignment, $map['questions']['Text2'], 'completestage');
        $page2 = new appraisal_page($map['pages']['Page2']);
        $this->assertTrue($page2->is_locked($roleassignment));
        $this->assertFalse($page2->is_locked($roleassignment2));
    }

    public function test_get_applicable_pages() {
        $this->resetAfterTest();
        $defmngr = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => time() + 86400, 'locks' => array(appraisal::ROLE_LEARNER => 1), 'pages' => array(
                array('name' => 'Page1', 'questions' => array(
                    array('name' => 'Text1', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 2, appraisal::ROLE_MANAGER => 1))
                )),
                array('name' => 'Page2', 'questions' => array(
                    array('name' => 'Text2', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3)),
                    array('name' => 'Text2', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 7, appraisal::ROLE_MANAGER => 6))
                )),
                array('name' => 'Page3', 'questions' => array(
                    array('name' => 'Text3', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 1))
                ))
            )),
            array('name' => 'St2', 'timedue' => time() + 86400, 'locks' => array(appraisal::ROLE_LEARNER => 1), 'pages' => array(
                array('name' => 'Page4', 'questions' => array(
                    array('name' => 'Text4', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 7, appraisal::ROLE_MANAGER => 2))
                ))
            ))
        ));
        $appraisal = appraisal::build($defmngr);
        $map = $this->map($appraisal);

        // Check manager role w/o rights.
        $mngrall = appraisal_page::get_applicable_pages($map['stages']['St1'], appraisal::ROLE_MANAGER);
        $this->assertCount(2, $mngrall);
        $this->assertArrayHasKey($map['pages']['Page1'], $mngrall);
        $this->assertArrayHasKey($map['pages']['Page2'], $mngrall);
        // Check learner rights can answer.
        $lrnranswr = appraisal_page::get_applicable_pages($map['stages']['St1'], appraisal::ROLE_LEARNER,
            appraisal::ACCESS_CANANSWER);
        $this->assertCount(2, $lrnranswr);
        $this->assertArrayHasKey($map['pages']['Page1'], $lrnranswr);
        $this->assertArrayHasKey($map['pages']['Page2'], $lrnranswr);
        // Check canview with previous pages of St2.
        $lrnrview = appraisal_page::get_applicable_pages($map['stages']['St2'], appraisal::ROLE_LEARNER,
            appraisal::ACCESS_CANVIEWOTHER | appraisal::ACCESS_MUSTANSWER, true);
        $this->assertCount(2, $lrnrview);
        $this->assertArrayHasKey($map['pages']['Page4'], $lrnrview);
        $this->assertArrayHasKey($map['pages']['Page2'], $lrnrview);
    }

    public function test_get_may_answer() {
        $this->resetAfterTest();
        $defmngr = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => time() + 86400, 'locks' => array(appraisal::ROLE_LEARNER => 1), 'pages' => array(
                array('name' => 'Page1', 'questions' => array(
                    array('name' => 'Text1', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3, appraisal::ROLE_MANAGER => 1))
                )),
                array('name' => 'Page2', 'questions' => array(
                    array('name' => 'Text2', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3, appraisal::ROLE_MANAGER => 1)),
                    array('name' => 'Text2.1', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_APPRAISER => 7, appraisal::ROLE_MANAGER => 6))
                )),
                array('name' => 'Page3', 'questions' => array(
                    array('name' => 'Text3', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 1, appraisal::ROLE_MANAGER => 1))
                ))
            ))
        ));
        $appraisal = appraisal::build($defmngr);
        $map = $this->map($appraisal);
        // Page1 is only learner.
        $page1 = new appraisal_page($map['pages']['Page1']);
        $page1rls = $page1->get_may_answer();
        $this->assertCount(1, $page1rls);
        $this->assertArrayHasKey(appraisal::ROLE_LEARNER, $page1rls);
        // Page2 learner, manager, appraiser.
        $page2 = new appraisal_page($map['pages']['Page2']);
        $page2rls = $page2->get_may_answer();
        $this->assertCount(3, $page2rls);
        $this->assertArrayHasKey(appraisal::ROLE_LEARNER, $page2rls);
        $this->assertArrayHasKey(appraisal::ROLE_MANAGER, $page2rls);
        $this->assertArrayHasKey(appraisal::ROLE_APPRAISER, $page2rls);
        // Page3 nobody.
        $page3 = new appraisal_page($map['pages']['Page3']);
        $page3rls = $page3->get_may_answer();
        $this->assertEmpty($page3rls);
    }
}