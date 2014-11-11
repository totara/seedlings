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
 * Unit tests for appraisal_stage class of totara/appraisal/lib.php
 */
global $CFG;
require_once($CFG->dirroot.'/totara/appraisal/tests/appraisal_testcase.php');

class appraisal_stage_test extends appraisal_testcase {

    /**
     * Default appraisal definition for stages testing
     *
     * @var array
     */
    protected $def = array();

    /**
     * Same as @see self::$def but with manager role involved
     * @var array
     */
    protected $defmngr = array();

    public function setUp() {
        parent::setUp();
        $this->def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => time() + 86400, 'locks' => array(appraisal::ROLE_LEARNER => 1), 'pages' => array(
                array('name' => 'Page1', 'questions' => array(
                    array('name' => 'Text1', 'type' => 'text', 'roles' => array(appraisal::ROLE_LEARNER => 7))
                ))
            )),
            array('name' => 'St2', 'timedue' => time() + 2 * 86400, 'pages' => array(
                array('name' => 'Page2', 'questions' => array(
                    array('name' => 'Text2', 'type' => 'text', 'roles' => array(appraisal::ROLE_LEARNER => 7))
                ))
            )),
            array('name' => 'St3', 'timedue' => time() + 3 * 86400, 'pages' => array(
                array('name' => 'Page3', 'questions' => array(
                    array('name' => 'Text3', 'type' => 'text', 'roles' => array(appraisal::ROLE_LEARNER => 7))
                ))
            ))
        ));

        $this->defmngr = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => time() + 86400, 'locks' => array(appraisal::ROLE_LEARNER => 1), 'pages' => array(
                array('name' => 'Page1', 'questions' => array(
                    array('name' => 'Text1', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 7, appraisal::ROLE_MANAGER => 7))
                ))
            )),
            array('name' => 'St2', 'timedue' => time() + 2 * 86400, 'pages' => array(
                array('name' => 'Page2', 'questions' => array(
                    array('name' => 'Text2', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 7, appraisal::ROLE_MANAGER => 7))
                ))
            )),
            array('name' => 'St3', 'timedue' => time() + 3 * 86400, 'pages' => array(
                array('name' => 'Page3', 'questions' => array(
                    array('name' => 'Text3', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 7, appraisal::ROLE_MANAGER => 7))
                ))
            ))
        ));
    }

    public function test_stage_add() {
        $this->resetAfterTest();
        $appraisal = appraisal::build(array('name' => 'Appraisal'));
        $data = new stdClass();
        $data->appraisalid = $appraisal->id;
        $data->name = 'Stage';
        $data->description = 'Description';
        $data->timedue = time();
        $data->locks = array(appraisal::ROLE_LEARNER => 1, appraisal::ROLE_APPRAISER => 1);

        $stage = new appraisal_stage();
        $stage->set($data);
        $stage->save();
        $stagetest = new appraisal_stage($stage->id);
        $datatest = $stagetest->get();
        $this->assertGreaterThan(1, $datatest->id);
        $this->assertEquals($data->name, $datatest->name);
        $this->assertEquals($data->description, $datatest->description);
        $this->assertEquals($data->timedue, $datatest->timedue);
        $this->assertArrayHasKey(appraisal::ROLE_LEARNER, $datatest->locks);
        $this->assertArrayHasKey(appraisal::ROLE_APPRAISER, $datatest->locks);
    }

    public function test_stage_edit() {
        $this->resetAfterTest();
        $appraisal = appraisal::build(array('name' => 'Appraisal'));
        $data = new stdClass();
        $data->appraisalid = $appraisal->id;
        $data->name = 'Stage';
        $data->description = 'Description';
        $data->timedue = time();
        $data->locks = array(appraisal::ROLE_LEARNER => 1, appraisal::ROLE_APPRAISER => 1);

        $stage = new appraisal_stage();
        $stage->set($data);
        $stage->save();
        $id = $stage->id;
        unset($stage);

        $data2 = new stdClass();
        $data2->name = ' Renamed Stage';
        $data2->description = 'Other Description';
        $data2->timedue = time()+10;
        $data2->locks = array(appraisal::ROLE_MANAGER => 1);
        $stage = new appraisal_stage($id);
        $stage->set($data2);
        $stage->save();
        $stagetest = new appraisal_stage($id);
        $datatest2 = $stagetest->get();
        unset($stage, $stagetest);

        $data3 = new stdClass();
        $data3->locks = array();
        $stage = new appraisal_stage($id);
        $stage->set($data3);
        $stage->save();
        $stagetest = new appraisal_stage($id);
        $datatest3 = $stagetest->get();

        $this->assertEquals($id, $datatest2->id);
        $this->assertEquals($data2->name, $datatest2->name);
        $this->assertEquals($data2->description, $datatest2->description);
        $this->assertEquals($data2->timedue, $datatest2->timedue);
        $this->assertCount(1, $datatest2->locks);
        $this->assertEquals(1, $datatest2->locks[appraisal::ROLE_MANAGER]);

        $this->assertEquals($id, $datatest3->id);
        $this->assertEquals($data2->name, $datatest3->name);
        $this->assertEquals($data2->description, $datatest3->description);
        $this->assertEquals($data2->timedue, $datatest3->timedue);
        $this->assertEmpty($datatest3->locks);
    }

    public function test_stage_delete() {
        $this->resetAfterTest();
        $def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'Stage 1'), array('name' => 'Stage 2')
        ));
        $appraisal = appraisal::build($def);

        $stages = appraisal_stage::fetch_appraisal($appraisal->id, true);
        current($stages)->delete();
        $stagestest = appraisal_stage::fetch_appraisal($appraisal->id, true);
        $this->assertCount(1, $stagestest);
    }

    public function test_stage_duplicate() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $time = time();
        $def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'Stage 1', 'timedue' => $time, 'description' => 'Description',
                  'locks' => array(appraisal::ROLE_LEARNER => 1)), array('name' => 'Stage 2')
        ));
        $appraisal = appraisal::build($def);
        $appraisal2 = appraisal::build(array('name' => 'Appraisal 2'));

        $stages = appraisal_stage::fetch_appraisal($appraisal->id, true);
        $stage = current($stages);
        $stage2 = next($stages);
        $stage->duplicate($appraisal2->id, 1);
        $stage2->duplicate($appraisal2->id, 1);

        $stagestest = appraisal_stage::fetch_appraisal($appraisal2->id, true);

        $this->assertCount(2, $stagestest);
        foreach ($stagestest as $test) {
            switch ($test->name) {
                case 'Stage 1':
                    $this->assertEquals('Description', $test->description);
                    $this->assertEquals($time+86400, $test->timedue);
                    $this->assertEmpty($test->locks);
                    break;
                default:
                    $this->assertEquals('Stage 2', $test->name);
                    $this->assertEmpty($test->timedue, $test->timedue);
                    $this->assertEmpty($test->locks);
            }
            $this->assertEquals($appraisal2->id, $test->appraisalid);
            $this->assertNotEmpty($test->id);
            $this->assertNotContains($test->id, array($stage->id, $stage2->id));
        }
    }

    public function test_stage_locks() {
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
        $stage1 = new appraisal_stage($map['stages']['St1']);
        $this->assertTrue($stage1->is_locked($roleassignment));
        $this->assertFalse($stage1->is_locked($roleassignment2));

        $this->answer_question($appraisal, $roleassignment, $map['questions']['Text2'], 'completestage');
        $stage2 = new appraisal_stage($map['stages']['St2']);
        $this->assertFalse($stage2->is_locked($roleassignment));
        $this->assertFalse($stage2->is_locked($roleassignment2));
    }

    public function test_stage_complete_role() {
        $this->resetAfterTest();

        list($appraisal, $users) = $this->prepare_appraisal_with_users($this->defmngr);
        $appraisal->validate();
        $appraisal->activate();
        $user = current($users);
        $user2 = next($users);
        $learnerassignment = appraisal_role_assignment::get_role($appraisal->id, $user->id, $user->id, appraisal::ROLE_LEARNER);
        $learnerassignment2 = appraisal_role_assignment::get_role($appraisal->id, $user2->id, $user2->id, appraisal::ROLE_LEARNER);
        $adminassignment = appraisal_role_assignment::get_role($appraisal->id, $user->id, 2, appraisal::ROLE_MANAGER);
        $userassignment = appraisal_user_assignment::get_user($appraisal->id, $learnerassignment->subjectid);
        $map = $this->map($appraisal);

        // Complete for role.
        $stage1 = new appraisal_stage($map['stages']['St1']);
        $stage2 = new appraisal_stage($map['stages']['St2']);
        $stage1->complete_for_role($learnerassignment);
        $this->assertTrue($stage1->is_completed($learnerassignment));
        $this->assertFalse($stage1->is_completed($adminassignment));
        $this->assertFalse($stage1->is_completed($learnerassignment2));
        $this->assertFalse($stage2->is_completed($learnerassignment));
        $active = appraisal_stage::get_active($appraisal->id, $userassignment);
        $this->assertEquals($stage1->id, $active->id);
    }

    public function test_stage_complete_user() {
        $this->resetAfterTest();

        list($appraisal, $users) = $this->prepare_appraisal_with_users($this->defmngr);
        $appraisal->validate();
        $appraisal->activate();
        $user = current($users);
        $user2 = next($users);
        $learnerassignment = appraisal_role_assignment::get_role($appraisal->id, $user->id, $user->id, appraisal::ROLE_LEARNER);
        $learnerassignment2 = appraisal_role_assignment::get_role($appraisal->id, $user2->id, $user2->id, appraisal::ROLE_LEARNER);
        $adminassignment = appraisal_role_assignment::get_role($appraisal->id, $user->id, 2, appraisal::ROLE_MANAGER);
        $adminassignment2 = appraisal_role_assignment::get_role($appraisal->id, $user2->id, 2, appraisal::ROLE_MANAGER);
        $map = $this->map($appraisal);

        // Complete for user.
        $stage1 = new appraisal_stage($map['stages']['St1']);
        $stage2 = new appraisal_stage($map['stages']['St2']);
        $stage1->complete_for_role($learnerassignment);
        $stage1->complete_for_role($adminassignment);
        $this->assertTrue($stage1->is_completed($learnerassignment));
        $this->assertTrue($stage1->is_completed($adminassignment));
        $this->assertFalse($stage2->is_completed($learnerassignment));
        $this->assertFalse($stage1->is_completed($learnerassignment2));
        $this->assertFalse($stage1->is_completed($adminassignment2));

        $userassignment = appraisal_user_assignment::get_user($appraisal->id, $learnerassignment->subjectid);
        $userassignment2 = appraisal_user_assignment::get_user($appraisal->id, $learnerassignment2->subjectid);

        $active = appraisal_stage::get_active($appraisal->id, $userassignment);
        $active2 = appraisal_stage::get_active($appraisal->id, $userassignment2);
        $this->assertEquals($stage1->id, $active2->id);
        $this->assertEquals($stage2->id, $active->id);
    }

    public function test_stage_get_list() {
        $this->resetAfterTest();
        $defmngr = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => time() + 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text1', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 7, appraisal::ROLE_MANAGER => 7))
                ))
            )),
            array('name' => 'St2', 'timedue' => time() + 2 * 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text2', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 1, appraisal::ROLE_MANAGER => 7))
                ))
            )),
            array('name' => 'St3', 'timedue' => time() + 3 * 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text3', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 7, appraisal::ROLE_MANAGER => 1))
                ))
            ))
        ));
        list($appraisal, $users) = $this->prepare_appraisal_with_users($defmngr);
        $map = $this->map($appraisal);
        $list = appraisal_stage::get_list($appraisal->id);
        $this->assertArrayHasKey(appraisal::ROLE_LEARNER, $list[$map['stages']['St1']]->roles);
        $this->assertArrayHasKey(appraisal::ROLE_MANAGER, $list[$map['stages']['St1']]->roles);
        $this->assertArrayNotHasKey(appraisal::ROLE_LEARNER, $list[$map['stages']['St2']]->roles);
        $this->assertArrayHasKey(appraisal::ROLE_MANAGER, $list[$map['stages']['St2']]->roles);
        $this->assertArrayHasKey(appraisal::ROLE_LEARNER, $list[$map['stages']['St3']]->roles);
        $this->assertArrayNotHasKey(appraisal::ROLE_MANAGER, $list[$map['stages']['St3']]->roles);
    }

    public function test_stage_get_stages_for_roles() {
        $this->resetAfterTest();
        // Prepare stages with different roles, and different rights of each role.
        $defmngr = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => time() + 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text1', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 7, appraisal::ROLE_MANAGER => 3))
                ))
            )),
            array('name' => 'St2', 'timedue' => time() + 2 * 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text2', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 6, appraisal::ROLE_MANAGER => 2))
                ))
            )),
            array('name' => 'St3', 'timedue' => time() + 3 * 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text3', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3))
                ))
            )),
            array('name' => 'St4', 'timedue' => time() + 3 * 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text4', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 4, appraisal::ROLE_MANAGER => 7))
                ))
            ))
        ));
        list($appraisal) = $this->prepare_appraisal_with_users($defmngr);
        $map = $this->map($appraisal);

        $all = appraisal_stage::get_stages($appraisal->id);
        $mngr = appraisal_stage::get_stages($appraisal->id, array(appraisal::ROLE_MANAGER));
        $canview = appraisal_stage::get_stages($appraisal->id, array(), appraisal::ACCESS_CANVIEWOTHER);
        $mngrmstanswr = appraisal_stage::get_stages($appraisal->id, array(appraisal::ROLE_MANAGER), appraisal::ACCESS_MUSTANSWER);
        $lrnranswrnview = appraisal_stage::get_stages($appraisal->id, array(appraisal::ROLE_LEARNER),
            appraisal::ACCESS_CANVIEWOTHER | appraisal::ACCESS_CANANSWER);

        // Check stages returned without roles/rights restrictions.
        $this->assertCount(4, $all);
        // Check stages for manager.
        $this->assertCount(3, $mngr);
        $this->assertArrayNotHasKey($map['stages']['St3'], $mngr);
        // Check stages for can view rights wo/role restrictions.
        $this->assertCount(3, $canview);
        $this->assertArrayNotHasKey($map['stages']['St2'], $canview);
        // Check stages for manager role that must answer.
        $this->assertCount(1, $mngrmstanswr);
        $this->assertArrayHasKey($map['stages']['St4'], $mngrmstanswr);
        // Check stages for learner role that can view and can answer.
        $this->assertCount(2, $lrnranswrnview);
        $this->assertArrayHasKey($map['stages']['St1'], $lrnranswrnview);
        $this->assertArrayHasKey($map['stages']['St3'], $lrnranswrnview);
    }

    public function test_stage_fetch_appraisal() {
        $this->resetAfterTest();
        list($appraisal) = $this->prepare_appraisal_with_users($this->def);

        $stdclasses = appraisal_stage::fetch_appraisal($appraisal->id, false);
        $stages = appraisal_stage::fetch_appraisal($appraisal->id, true);
        // Check instances / stdClass.
        $this->assertCount(3, $stdclasses);
        $this->assertCount(3, $stages);
        $this->assertContainsOnlyInstancesOf('stdClass', $stdclasses);
        $this->assertContainsOnlyInstancesOf('appraisal_stage', $stages);
    }

    public function test_stage_is_overdue() {
        $this->resetAfterTest();
        list($appraisal) = $this->prepare_appraisal_with_users($this->def);
        $map = $this->map($appraisal);
        $stage = new appraisal_stage($map['stages']['St1']);
        $stagedue = $stage->timedue;

        // Check for overdue.
        $this->assertTrue($stage->is_overdue($stagedue + 1));

        // Check for not overdue.
        $this->assertFalse($stage->is_overdue($stagedue));
        $this->assertFalse($stage->is_overdue($stagedue - 1));
    }

    public function test_stage_get_may_answer() {
        $this->resetAfterTest();
        $defmngr = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => time() + 86400, 'locks' => array(appraisal::ROLE_LEARNER => 1), 'pages' => array(
                array('name' => 'Page1', 'questions' => array(
                    array('name' => 'Text1', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 3, appraisal::ROLE_MANAGER => 7))
                ))
            )),
            array('name' => 'St2', 'timedue' => time() + 2 * 86400, 'pages' => array(
                array('name' => 'Page2', 'questions' => array(
                    array('name' => 'Text2', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 2))
                ))
            )),
            array('name' => 'St3', 'timedue' => time() + 3 * 86400, 'pages' => array(
                array('name' => 'Page3', 'questions' => array(
                    array('name' => 'Text3', 'type' => 'text',
                          'roles' => array(appraisal::ROLE_LEARNER => 1, appraisal::ROLE_MANAGER => 1))
                ))
            ))
        ));
        list($appraisal) = $this->prepare_appraisal_with_users($defmngr);
        $map = $this->map($appraisal);
        $stage1 = new appraisal_stage($map['stages']['St1']);
        $stage2 = new appraisal_stage($map['stages']['St2']);
        $stage3 = new appraisal_stage($map['stages']['St3']);

        // Stage 1 - both can answer.
        $both = $stage1->get_may_answer();
        $this->assertCount(2, $both);
        $this->assertArrayHasKey(appraisal::ROLE_LEARNER, $both);
        $this->assertArrayHasKey(appraisal::ROLE_MANAGER, $both);
        // Stage 2 - only learner can answer.
        $lrnr = $stage2->get_may_answer();
        $this->assertCount(1, $lrnr);
        $this->assertArrayHasKey(appraisal::ROLE_LEARNER, $lrnr);
        // Stage 3 - nobody can answer.
        $none = $stage3->get_may_answer();
        $this->assertEmpty($none);
    }

    public function test_stage_validate() {
        $this->resetAfterTest();
        $def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'St1', 'timedue' => 0, 'pages' => array(
                array('name' => 'Page1', 'questions' => array(
                    array('name' => 'Text1', 'type' => 'text', 'roles' => array(appraisal::ROLE_LEARNER => 7))
                ))
            )),
            array('name' => 'St2', 'timedue' => time() - 1, 'pages' => array(
                array('name' => 'Page2', 'questions' => array(
                    array('name' => 'Text2', 'type' => 'text', 'roles' => array(appraisal::ROLE_LEARNER => 7))
                ))
            )),
            array('name' => 'St3', 'timedue' => time() + 3 * 86400, 'pages' => array(
                array('name' => 'Page3', 'questions' => array(
                    array('name' => 'Text3', 'type' => 'text', 'roles' => array(appraisal::ROLE_LEARNER => 1))
                ))
            )),
            array('name' => 'St4', 'timedue' => time() + 4 * 86400)
        ));

        list($appraisal) = $this->prepare_appraisal_with_users($def);
        $map = $this->map($appraisal);
        $stage1 = new appraisal_stage($map['stages']['St1']);
        $stage2 = new appraisal_stage($map['stages']['St2']);
        $stage3 = new appraisal_stage($map['stages']['St3']);
        $stage4 = new appraisal_stage($map['stages']['St4']);
        $err1 = $stage1->validate();
        $err2 = $stage2->validate();
        $err3 = $stage3->validate();
        $err4 = $stage4->validate();

        // Stage has no due date.
        $this->assertArrayHasKey('stagedue'.$stage1->id, $err1);
        // Stage is already outdated.
        $this->assertArrayHasKey('stagedue'.$stage2->id, $err2);
        // Stage doesn't have answerable questions.
        $this->assertArrayHasKey('stagenocananswer'.$stage3->id, $err3);
        // Stage has no pages.
        $this->assertArrayHasKey('stageempty'.$stage4->id, $err4);
    }


}