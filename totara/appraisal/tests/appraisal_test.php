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
        list($errors, $warnings) = $appraisal1->validate();
        $this->assertEmpty($errors);
        $this->assertEmpty($warnings);

        $appraisal1->activate();

        $this->assertEquals(appraisal::STATUS_ACTIVE, $appraisal1->status);
        $dbman = $DB->get_manager();
        $this->assertTrue($dbman->table_exists('appraisal_quest_data_'.$appraisal1->id));
        $assign2 = new totara_assign_appraisal('appraisal', $appraisal1);
        $this->assertTrue($assign2->assignments_are_stored());
        // The function get_current_users() returns a recordset so need to loop through to count.
        $users = $assign2->get_current_users();
        $count = 0;
        foreach ($users as $user) {
            $count++;
        }
        $this->assertEquals(2, $count);

    }

    public function test_appraisal_validate_wrong_status() {
        $this->resetAfterTest();
        $this->setAdminUser();

        list($appraisal) = $this->prepare_appraisal_with_users();
        list($errors, $warnings) = $appraisal->validate();
        $this->assertEmpty($errors);
        $this->assertEmpty($warnings);
        $appraisal->activate();

        list($errors, $warnings) = $appraisal->validate();
        $this->assertCount(1, $errors);
        $this->assertEquals(array('status'), array_keys($errors));

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
        list($errors, $warnings) = $appraisal->validate();
        $this->assertArrayHasKey('roles', $errors);
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

        // Appraisals no longer auto complete due to dynamic assignments, check it is still open.
        $this->assertEquals(appraisal::STATUS_ACTIVE, $appraisal->status);
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

    public function test_active_appraisal_add_group () {
        global $DB;

        // Set up active appraisal.
        $this->resetAfterTest();
        $this->setAdminUser();

        list($appraisal) = $this->prepare_appraisal_with_users();
        list($errors, $warnings) = $appraisal->validate();
        $this->assertEmpty($errors);
        $this->assertEmpty($warnings);

        $this->assertEquals(appraisal::STATUS_DRAFT, $appraisal->status);
        $count = $DB->count_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(0, $count);

        $appraisal->activate();

        $this->assertEquals(appraisal::STATUS_ACTIVE, $appraisal->status);
        $count = $DB->count_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(2, $count);

        // Set up group.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $user1->id);
        cohort_add_member($cohort->id, $user2->id);

        // Add group.
        $urlparams = array('includechildren' => false, 'listofvalues' => array($cohort->id));
        $assign = new totara_assign_appraisal('appraisal', $appraisal);
        $grouptypeobj = $assign->load_grouptype('cohort');
        $grouptypeobj->handle_item_selector($urlparams);

        // There should still only be 2 user assignments.
        $this->assertEquals(appraisal::STATUS_ACTIVE, $appraisal->status);
        $count = $DB->count_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(2, $count);

        // Force user assignments update.
        $appraisal->check_assignment_changes();

        // Check users have now gone up to 4.
        $this->assertEquals(appraisal::STATUS_ACTIVE, $appraisal->status);
        $count = $DB->count_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(4, $count);
    }

    public function test_active_appraisal_remove_group () {
        global $DB;

        // Set up active appraisal.
        $this->resetAfterTest();
        $this->setAdminUser();

        list($appraisal) = $this->prepare_appraisal_with_users();
        list($errors, $warnings) = $appraisal->validate();
        $this->assertEmpty($errors);
        $this->assertEmpty($warnings);

        // Set up group.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $user1->id);
        cohort_add_member($cohort->id, $user2->id);

        // Add group.
        $urlparams = array('includechildren' => false, 'listofvalues' => array($cohort->id));
        $assign = new totara_assign_appraisal('appraisal', $appraisal);
        $grouptypeobj = $assign->load_grouptype('cohort');
        $grouptypeobj->handle_item_selector($urlparams);

        $this->assertEquals(appraisal::STATUS_DRAFT, $appraisal->status);
        $count = $DB->count_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(0, $count);

        $appraisal->activate();

        $this->assertEquals(appraisal::STATUS_ACTIVE, $appraisal->status);
        $count = $DB->count_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(4, $count);

        // Remove one of the groups.
        $assign->delete_assigned_group('cohort', $cohort->id);

        // Force user assignments update.
        $appraisal->check_assignment_changes();

        // Check appraisal is still active, and total user assignments are still 4.
        $this->assertEquals(appraisal::STATUS_ACTIVE, $appraisal->status);
        $count = $DB->count_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(4, $count);
        // Only 2 user assignments should be active.
        $count = $DB->count_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id, 'status' => appraisal::STATUS_ACTIVE));
        $this->assertEquals(2, $count);
        // There should be 2 closed user assignments, the removed 2.
        $count = $DB->count_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id, 'status' => appraisal::STATUS_CLOSED));
        $this->assertEquals(2, $count);
    }

    /**
     * Test activating an appraisal when an assigned user is missing required roles.
     *
     * User position assignment structure
     * $user1 ------| Manager   | 0
     *              | Teamlead  | 0
     *              | Appraiser | 0
     */
    public function test_activation_with_missing_roles() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Set up.
        $roles = array();
        $roles[appraisal::ROLE_LEARNER] = 6;
        $roles[appraisal::ROLE_MANAGER] = 6;
        $roles[appraisal::ROLE_TEAM_LEAD] = 6;
        $roles[appraisal::ROLE_APPRAISER] = 6;

        $def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'Stage', 'timedue' => time() + 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text', 'type' => 'text', 'roles' => $roles)
                ))
            ))
        ));

        $user1 = $this->getDataGenerator()->create_user();

        list($appraisal) = $this->prepare_appraisal_with_users($def, array($user1));
        list($errors, $warnings) = $appraisal->validate();

        // This should only generate role warnings not errors.
        $this->assertEmpty($errors);

        // There should be a warning for manager, teamlead and appraiser.
        $this->assertEquals(3, count($warnings));

        $this->assertEquals(appraisal::STATUS_DRAFT, $appraisal->status);
        $count = $DB->count_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(0, $count);

        $appraisal->activate();

        $this->assertEquals(appraisal::STATUS_ACTIVE, $appraisal->status);
        $count = $DB->count_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(1, $count);
    }

    /**
     * Test removing the position assignments of an assigned user
     * while they are assigned to an active appraisal.
     *
     * User position assignment structure
     * $manager ----| Manager   | $teamlead1
     *
     * $user1 ------| Manager   | $manager     -> 0
     *              | Teamlead  | $teamlead    -> 0
     *              | Appraiser | $appraiser   -> 0
     *
     * $user2 ------| Manager   | $manager
     *              | Teamlead  | $teamlead
     *              | Appraiser | $appraiser
     */
    public function test_active_appraisal_role_removal() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Set up appraisal.
        $roles = array();
        $roles[appraisal::ROLE_LEARNER] = 6;
        $roles[appraisal::ROLE_MANAGER] = 6;
        $roles[appraisal::ROLE_TEAM_LEAD] = 6;
        $roles[appraisal::ROLE_APPRAISER] = 6;

        $def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'Stage', 'timedue' => time() + 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text', 'type' => 'text', 'roles' => $roles)
                ))
            ))
        ));
        $appraisal = appraisal::build($def);

        // Set up group.
        $teamlead = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();
        $appraiser = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $assignment = new position_assignment(array('userid' => $manager->id, 'type' => 1));
        $assignment->managerid = $teamlead->id;
        assign_user_position($assignment, true);

        $assignment = new position_assignment(array('userid' => $user1->id, 'type' => 1));
        $assignment->managerid = $manager->id;
        $assignment->appraiserid = $appraiser->id;
        assign_user_position($assignment, true);

        $assignment = new position_assignment(array('userid' => $user2->id, 'type' => 1));
        $assignment->managerid = $manager->id;
        $assignment->appraiserid = $appraiser->id;
        assign_user_position($assignment, true);

        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $user1->id);
        cohort_add_member($cohort->id, $user2->id);

        // Assign group and activate.
        $urlparams = array('includechildren' => false, 'listofvalues' => array($cohort->id));
        $assign = new totara_assign_appraisal('appraisal', $appraisal);
        $grouptypeobj = $assign->load_grouptype('cohort');
        $grouptypeobj->handle_item_selector($urlparams);

        $appraisal->activate();

        // That should have created 2 user assignments.
        $userassignments = $DB->get_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(2, count($userassignments));

        // And 4 role assignments per userassignment.
        foreach ($userassignments as $aua) {
            $countrole = $DB->count_records('appraisal_role_assignment', array('appraisaluserassignmentid' => $aua->id));
            $this->assertEquals(4, $countrole);
        }

        // Now Change user1s position assignment.
        $assignment = new position_assignment(array('userid' => $user1->id, 'type' => 1));
        $assignment->managerid = 0;
        $assignment->appraiserid = 0;
        assign_user_position($assignment, true);

        // User1 should now be missing all roles (except learner).
        $missing = $appraisal->get_missingrole_users(true);
        $this->assertEquals(1, count($missing));
        $this->assertEquals(3, count($missing[$user1->id]));

        // Check that the changedrole function finds the 3 roles that have changed.
        $changedroles = $appraisal->get_changedrole_users();
        $this->assertEquals(3, count($changedroles));

        $appraisal->check_assignment_changes();

        // Check that the changedrole function now resolves to 0.
        $changedroles = $appraisal->get_changedrole_users();
        $this->assertEquals(0, count($changedroles));

        // There should still be 2 user assignments.
        $userassignments = $DB->get_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(2, count($userassignments));

        // Each with the corresponding 4 role assignments.
        foreach ($userassignments as $aua) {
            $countrole = $DB->count_records('appraisal_role_assignment', array('appraisaluserassignmentid' => $aua->id));
            $this->assertEquals(4, $countrole);
        }

        // But user1s role assignments should have the userid set to 0.
        $userassig = $userassignments[1]->userid == $user1->id ? $userassignments[1] : $userassignments[2];
        $countrole = $DB->count_records('appraisal_role_assignment', array('appraisaluserassignmentid' => $userassig->id, 'userid' => 0));
        $this->assertEquals(3, $countrole);
    }

    /**
     * Test changing the position assignments of an assigned user
     * while they are assigned to an active appraisal.
     *
     * User position assignment structure
     * $manager1 ---| Manager   | $teamlead1
     *
     * $manager2 ---| Manager   | $teamlead2
     *
     * $user1 ------| Manager   | $manager1     -> $manager2
     *              | Teamlead  | $teamlead1    -> $teamlead2
     *              | Appraiser | $appraiser1   -> $appraiser2
     *
     * $user2 ------| Manager   | $manager1
     *              | Teamlead  | $teamlead1
     *              | Appraiser | $appraiser1
     */
    public function test_active_appraisal_role_reassignment() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Set up appraisal.
        $roles = array();
        $roles[appraisal::ROLE_LEARNER] = 6;
        $roles[appraisal::ROLE_MANAGER] = 6;
        $roles[appraisal::ROLE_TEAM_LEAD] = 6;
        $roles[appraisal::ROLE_APPRAISER] = 6;

        $def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'Stage', 'timedue' => time() + 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text', 'type' => 'text', 'roles' => $roles)
                ))
            ))
        ));
        $appraisal = appraisal::build($def);

        // Set up group.
        $teamlead = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();
        $appraiser = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $teamlead2 = $this->getDataGenerator()->create_user();
        $manager2 = $this->getDataGenerator()->create_user();
        $appraiser2 = $this->getDataGenerator()->create_user();

        $assignment = new position_assignment(array('userid' => $manager->id, 'type' => 1));
        $assignment->managerid = $teamlead->id;
        assign_user_position($assignment, true);

        $assignment = new position_assignment(array('userid' => $manager2->id, 'type' => 1));
        $assignment->managerid = $teamlead2->id;
        assign_user_position($assignment, true);

        $assignment = new position_assignment(array('userid' => $user1->id, 'type' => 1));
        $assignment->managerid = $manager->id;
        $assignment->appraiserid = $appraiser->id;
        assign_user_position($assignment, true);

        $assignment = new position_assignment(array('userid' => $user2->id, 'type' => 1));
        $assignment->managerid = $manager->id;
        $assignment->appraiserid = $appraiser->id;
        assign_user_position($assignment, true);

        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $user1->id);
        cohort_add_member($cohort->id, $user2->id);

        // Assign group and activate.
        $urlparams = array('includechildren' => false, 'listofvalues' => array($cohort->id));
        $assign = new totara_assign_appraisal('appraisal', $appraisal);
        $grouptypeobj = $assign->load_grouptype('cohort');
        $grouptypeobj->handle_item_selector($urlparams);

        $appraisal->activate();

        // That should have created 2 user assignments.
        $userassignments = $DB->get_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(2, count($userassignments));

        // And 4 role assignments per userassignment.
        foreach ($userassignments as $aua) {
            $countrole = $DB->count_records('appraisal_role_assignment', array('appraisaluserassignmentid' => $aua->id));
            $this->assertEquals(4, $countrole);
        }

        // Now Change user1s position assignment.
        $assignment = new position_assignment(array('userid' => $user1->id, 'type' => 1));
        $assignment->managerid = $manager2->id;
        $assignment->appraiserid = $appraiser2->id;
        assign_user_position($assignment, true);

        // There should be no missing roles.
        $missing = $appraisal->get_missingrole_users(true);
        $this->assertEquals(0, count($missing));

        // Check that the changedrole function finds the 3 roles that have changed.
        $changedroles = $appraisal->get_changedrole_users();
        $this->assertEquals(3, count($changedroles));

        $appraisal->check_assignment_changes();

        // Check that the changedrole function now resolves to 0.
        $changedroles = $appraisal->get_changedrole_users();
        $this->assertEquals(0, count($changedroles));

        // There should still be 2 user assignments.
        $userassignments = $DB->get_records('appraisal_user_assignment', array('appraisalid' => $appraisal->id));
        $this->assertEquals(2, count($userassignments));

        // Each with the corresponding 4 role assignments.
        foreach ($userassignments as $aua) {
            $countrole = $DB->count_records('appraisal_role_assignment', array('appraisaluserassignmentid' => $aua->id));
            $this->assertEquals(4, $countrole);
        }

        // Check user1s role assignments have been swapped to the new users.
        $userassig = $userassignments[1]->userid == $user1->id ? $userassignments[1] : $userassignments[2];
        $roles = $DB->get_records('appraisal_role_assignment', array('appraisaluserassignmentid' => $userassig->id));
        foreach ($roles as $role) {
            switch ($role->appraisalrole) {
                case appraisal::ROLE_LEARNER :
                    $this->assertEquals($user1->id, $role->userid);
                    break;
                case appraisal::ROLE_MANAGER :
                    $this->assertEquals($manager2->id, $role->userid);
                    break;
                case appraisal::ROLE_TEAM_LEAD :
                    $this->assertEquals($teamlead2->id, $role->userid);
                    break;
                case appraisal::ROLE_APPRAISER :
                    $this->assertEquals($appraiser2->id, $role->userid);
                    break;
            }
        }
    }

    /**
     * Test deleting an assigned user while they are assigned to an active appraisal
     *
     * User position assignment structure
     * $manager ----| Manager   | $teamlead
     *
     * $user1   ----| Manager   | $manager      -> null
     *              | Teamlead  | $teamlead     -> null
     *              | Appraiser | $appraiser    -> null
     *
     * $user2   ----| Manager   | $user1        -> 0
     *              | Teamlead  | $manager      -> 0
     *              | Appraiser | $appraiser
     */
    public function test_active_appraisal_user_deletion() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Set up appraisal.
        $roles = array();
        $roles[appraisal::ROLE_LEARNER] = 6;
        $roles[appraisal::ROLE_MANAGER] = 6;
        $roles[appraisal::ROLE_TEAM_LEAD] = 6;
        $roles[appraisal::ROLE_APPRAISER] = 6;

        $def = array('name' => 'Appraisal', 'stages' => array(
            array('name' => 'Stage', 'timedue' => time() + 86400, 'pages' => array(
                array('name' => 'Page', 'questions' => array(
                    array('name' => 'Text', 'type' => 'text', 'roles' => $roles)
                ))
            ))
        ));
        $appraisal = appraisal::build($def);
        $answertable = 'appraisal_quest_data_'.$appraisal->id;

        // Create users.
        $teamlead = $this->getDataGenerator()->create_user();
        $manager = $this->getDataGenerator()->create_user();
        $appraiser = $this->getDataGenerator()->create_user();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Set up position assignments.
        $assignment = new position_assignment(array('userid' => $manager->id, 'type' => 1));
        $assignment->managerid = $teamlead->id;
        assign_user_position($assignment, true);

        $assignment = new position_assignment(array('userid' => $user1->id, 'type' => 1));
        $assignment->managerid = $manager->id;
        $assignment->appraiserid = $appraiser->id;
        assign_user_position($assignment, true);

        $assignment = new position_assignment(array('userid' => $user2->id, 'type' => 1));
        $assignment->managerid = $user1->id;
        $assignment->appraiserid = $appraiser->id;
        assign_user_position($assignment, true);

        // Create group and assign users.
        $cohort = $this->getDataGenerator()->create_cohort();
        cohort_add_member($cohort->id, $user1->id);
        cohort_add_member($cohort->id, $user2->id);

        // Assign group and activate.
        $urlparams = array('includechildren' => false, 'listofvalues' => array($cohort->id));
        $assign = new totara_assign_appraisal('appraisal', $appraisal);
        $grouptypeobj = $assign->load_grouptype('cohort');
        $grouptypeobj->handle_item_selector($urlparams);

        $appraisal->activate();

        $ua1 = $DB->get_record('appraisal_user_assignment', array('appraisalid' => $appraisal->id, 'userid' => $user1->id));
        $ua2 = $DB->get_record('appraisal_user_assignment', array('appraisalid' => $appraisal->id, 'userid' => $user2->id));

        // This should have created 2 user assignments and 8 role assignments.
        $this->assertEquals(2, $DB->count_records('appraisal_user_assignment'));
        $this->assertEquals(8, $DB->count_records('appraisal_role_assignment'));
        $this->assertEquals(0, $DB->count_records('appraisal_quest_data_'.$appraisal->id));

        // Now create some answer data for user1.
        $user1roles = array();
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $user1->id, $user1->id,
            appraisal::ROLE_LEARNER);
        $user1roles[] = $roleassignment->id;
        $this->answer_question($appraisal, $roleassignment, 0, 'completestage');
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $user1->id, $manager->id,
                appraisal::ROLE_MANAGER);
        $user1roles[] = $roleassignment->id;
        $this->answer_question($appraisal, $roleassignment, 0, 'completestage');
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $user1->id, $teamlead->id,
                appraisal::ROLE_TEAM_LEAD);
        $user1roles[] = $roleassignment->id;
        $this->answer_question($appraisal, $roleassignment, 0, 'completestage');
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $user1->id, $appraiser->id,
                appraisal::ROLE_APPRAISER);
        $user1roles[] = $roleassignment->id;
        $this->answer_question($appraisal, $roleassignment, 0, 'completestage');

        // Now create some answer data for user2.
        $user2roles = array();
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $user2->id, $user2->id,
                appraisal::ROLE_LEARNER);
        $user2roles[] = $roleassignment->id;
        $this->answer_question($appraisal, $roleassignment, 0, 'completestage');
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $user2->id, $user1->id,
                appraisal::ROLE_MANAGER);
        $user2roles[] = $roleassignment->id;
        $this->answer_question($appraisal, $roleassignment, 0, 'completestage');
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $user2->id, $manager->id,
                appraisal::ROLE_TEAM_LEAD);
        $user2roles[] = $roleassignment->id;
        $this->answer_question($appraisal, $roleassignment, 0, 'completestage');
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $user2->id, $appraiser->id,
                appraisal::ROLE_APPRAISER);
        $user2roles[] = $roleassignment->id;
        $this->answer_question($appraisal, $roleassignment, 0, 'completestage');

        list($u1insql, $u1param) = $DB->get_in_or_equal($user1roles);
        $u1sql = "SELECT COUNT(*) FROM {{$answertable}} where appraisalroleassignmentid " . $u1insql;
        list($u2insql, $u2param) = $DB->get_in_or_equal($user2roles);
        $u2sql = "SELECT COUNT(*) FROM {{$answertable}} where appraisalroleassignmentid " . $u2insql;

        // There should now be 8 answer records, 4 per user_assignment.
        $this->assertEquals(8, $DB->count_records($answertable));
        $this->assertEquals(4, $DB->count_records_sql($u1sql, $u1param));
        $this->assertEquals(4, $DB->count_records_sql($u2sql, $u2param));

        // First half of the delete, remove user_assignment records.
        appraisal::delete_learner_assignments($user1->id);

        // This should have deleted user1's user assignment and associated role assignments.
        $this->assertEquals(1, $DB->count_records('appraisal_user_assignment'));
        $this->assertEquals(0, $DB->count_records('appraisal_user_assignment', array('userid' => $user1->id)));
        $this->assertEquals(4, $DB->count_records('appraisal_role_assignment'));
        $this->assertEquals(0, $DB->count_records('appraisal_role_assignment', array('appraisaluserassignmentid' => $ua1->id)));
        $this->assertEquals(4, $DB->count_records('appraisal_role_assignment', array('appraisaluserassignmentid' => $ua2->id)));
        $this->assertEquals(4, $DB->count_records($answertable));
        $this->assertEquals(0, $DB->count_records_sql($u1sql, $u1param));
        $this->assertEquals(4, $DB->count_records_sql($u2sql, $u2param));

        // Second half of the delete, unassign role_assignment records.
        appraisal::unassign_user_roles($user1->id);

        // This should have left the role_assignments and associated data alone but set the userid to 0.
        $this->assertEquals(1, $DB->count_records('appraisal_user_assignment'));
        $this->assertEquals(1, $DB->count_records('appraisal_user_assignment', array('userid' => $user2->id)));
        $this->assertEquals(4, $DB->count_records('appraisal_role_assignment', array('appraisaluserassignmentid' => $ua2->id)));
        $this->assertEquals(1, $DB->count_records('appraisal_role_assignment', array('appraisaluserassignmentid' => $ua2->id, 'userid' => 0)));
        $this->assertEquals(4, $DB->count_records($answertable));
        $this->assertEquals(0, $DB->count_records_sql($u1sql, $u1param));
        $this->assertEquals(4, $DB->count_records_sql($u2sql, $u2param));
    }
}
