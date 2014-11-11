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
 * @subpackage question
 */

global $CFG;
require_once($CFG->dirroot.'/totara/question/tests/question_testcase.php');

class question_field_multichoice_test extends totara_question_testcase {
    public function test_add_choices_menu() {
        $storage = new question_storage_mock(1);
        $storage->answerfield = 'appraisalroleassignmentid';
        $storage->prefix = 'appraisal';
        $man = new question_manager();
        $question = $man->create_element($storage, 'multichoicemulti');
        $form = new mock_question_form();
        $question->add_settings_form_elements($form->_form);
        $this->assertTrue($form->_form->elementExists('choice[0]'));
        $this->assertTrue($form->_form->elementExists('savegroup'));
        $this->assertTrue($form->_form->elementExists('listtype'));
    }

    public function test_define_set_get() {
        list($storage, $man, $question) = $this->prepare_multichoice_options();
        unset($question);
        $man->reset();
        $questiontest = $man->create_element($storage, 'multichoicemulti');
        $toform = $questiontest->define_get(new stdClass());
        $this->assertEquals($toform->{'listtype[list]'}, multichoice::DISPLAY_MENU);
        $this->assertGreaterThan(0, $toform->selectchoices);
        $this->assertEquals($toform->choice, array(array('option' => 'Option1', 'default' => 1),
                                  array('option' => 'Option2'),
                                  array('option' => 'Option3', 'default' => 1)));
    }

    public function test_define_validate() {
        $storage = new question_storage_mock(1);
        $storage->answerfield = 'appraisalroleassignmentid';
        $storage->prefix = 'appraisal';
        $man = new question_manager();
        $question = $man->create_element($storage, 'multichoicemulti');

        $datalist = new stdClass();
        $datalist->name = 'name';
        $datalist->listtype = array('list' => 0);
        $datalist->saveoptions = 0;
        $datalist->saveoptionsname = '';
        $datalist->selectchoices = 0;
        $datalist->choice = array(array('option' => 'Option'));
        $errlist = $question->define_validate_all($datalist, array());
        $this->assertCount(1, $errlist);
        $this->assertArrayHasKey('listtype', $errlist);
        unset($datalist, $errlist);

        $datasavegrp = new stdClass();
        $datasavegrp->name = 'name';
        $datasavegrp->listtype = array('list' => multichoice::DISPLAY_MENU);
        $datasavegrp->saveoptions = 1;
        $datasavegrp->saveoptionsname = '';
        $datasavegrp->selectchoices = 1;
        $datasavegrp->choice = array(array('option' => ''));
        $errsavegrp = $question->define_validate_all($datasavegrp, array());
        $this->assertCount(1, $errsavegrp);
        $this->assertArrayHasKey('savegroup', $errsavegrp);
        unset($datasavegrp, $errsavegrp);

        $datachoice = new stdClass();
        $datachoice->name = 'name';
        $datachoice->listtype = array('list' => multichoice::DISPLAY_MENU);
        $datachoice->saveoptions = 1;
        $datachoice->saveoptionsname = 'Test';
        $datachoice->selectchoices = 0;
        $datachoice->choice = array(array('option' => ''));
        $errchoice = $question->define_validate_all($datachoice, array());
        $this->assertCount(1, $errchoice);
        $this->assertArrayHasKey('choiceheader', $errchoice);
        unset($datachoice, $errchoice);
    }

    public function test_get_choice_list() {
        list(, , $question) = $this->prepare_multichoice_options();
        $toform = $question->define_get(new stdClass());
        $test = $question->get_choice_list($toform->selectchoices);
        $was = array();
        foreach ($test as $key => $option) {
            $this->assertGreaterThan(0, $key);
            $this->assertContains($option, array('Option1', 'Option2', 'Option3'));
            $this->assertNotContains($option, $was);
            $this->assertArrayNotHasKey($key, $was);
            $was[$key] = $option;
        }
    }

    public function test_activate() {
        list(, , $question) = $this->prepare_multichoice_options();
        // Check initial state.
        $oldscaleid = $question->param1;
        $question->activate();
        $newscaleid = $question->param1;
        $this->assertNotEquals($newscaleid, $oldscaleid);
        $test = $question->get_choice_list($newscaleid);
        $this->assertContains('Option1', $test);
        $this->assertContains('Option2', $test);
        $this->assertContains('Option3', $test);
    }

    public function test_add_field_specific_edit_elements() {
        $man = new question_manager(2, 3);
        list(, , $question) = $this->prepare_multichoice_options($man);
        $question->activate();
        $form = new mock_question_form();
        $question->add_field_specific_edit_elements($form->_form);
        $this->assertTrue($form->_form->elementExists('data_1_3'));
        $element = $form->_form->getElement('data_1_3');
        $this->assertCount(3, $element->_options);
        foreach ($element->_options as $option) {
            $this->assertContains($option['text'], array('Option1', 'Option2', 'Option3'));
        }
    }

    /**
     * Create tesing multichoice question
     * @param question_manager $man
     * @return array
     */
    protected function prepare_multichoice_options(question_manager $man = null) {
        $this->resetAfterTest();
        $storage = new question_storage_mock(1);
        $storage->answerfield = 'appraisalroleassignmentid';
        $storage->prefix = 'appraisal';
        if (!$man) {
            $man = new question_manager();
        }
        $question = $man->create_element($storage, 'multichoicemulti');
        $fromform = new stdClass();
        $fromform->listtype = array('list' => multichoice::DISPLAY_MENU);
        $fromform->selectchoices = 0;
        $fromform->saveoptions = 1;
        $fromform->saveoptionsname = 'Three options';
        $fromform->choice = array(array('option' => 'Option1', 'default' => 1),
                                  array('option' => 'Option2', 'default' => 0),
                                  array('option' => 'Option3', 'default' => 1));
        $question->define_set($fromform);
        return array($storage, $man, $question);
    }
}