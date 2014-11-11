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

// Test that questions (all) doesn't have public variables.
global $CFG;
require_once($CFG->dirroot.'/totara/question/tests/question_testcase.php');

class question_base_test extends totara_question_testcase {
    public function test_add_settings_form_elements() {
        $storage = new question_storage_mock(1);
        $man = new question_manager();
        $question = $man->create_element($storage, 'text');
        $form = new mock_question_form();
        $question->add_settings_form_elements($form->_form);
        $element = $form->_form->getElement('name');
        $this->assertInstanceOf('MoodleQuickForm_text', $element);
    }

    public function test_add_field_form_elements() {
        $storage = new question_storage_mock(1);
        $man = new question_manager();
        $question = $man->create_element($storage, 'text');
        $form = new mock_question_form();
        $question->cananswer = true;
        $question->add_field_form_elements($form->_form);
        $this->assertTrue($form->_form->elementExists('question'));
        $this->assertTrue($form->_form->elementExists('data_1_0'));

        // Check can answer off.
        $storagero = new question_storage_mock(1);
        $questionro = $man->create_element($storagero, 'text');
        $formro = new mock_question_form();
        $questionro->cananswer = false;
        $questionro->add_field_form_elements($formro->_form);
        $this->assertTrue($formro->_form->elementExists('question'));
        $this->assertFalse($formro->_form->elementExists('data_1_0'));
    }

    public function test_set_required() {
        $storage = new question_storage_mock(1);
        $man = new question_manager();
        $question = $man->create_element($storage, 'text');
        $question->set_required(true);
        $this->assertTrue($question->required);
        $this->assertFalse($question->viewonly);
        $form = new mock_question_form();
        $question->add_field_form_elements($form->_form);
        $this->assertTrue($form->_form->elementExists('data_1_0'));
        $this->assertTrue($form->_form->isElementRequired('data_1_0'));

    }

    public function test_set_readonly() {
        $storage = new question_storage_mock(1);
        $man = new question_manager();
        $question = $man->create_element($storage, 'text');
        $this->assertFalse($question->viewonly);
        $question->set_viewonly(true);
        $this->assertFalse($question->required);
        $this->assertTrue($question->viewonly);
        $form = new mock_question_form();
        $question->add_field_form_elements($form->_form);
        $this->assertTrue($form->_form->elementExists('data_1_0'));
        $elem = $form->_form->getElement('data_1_0');
        $this->assertEquals('static', $elem->_type);
    }

    public function test_set_data_get_data() {
        $man = new question_manager(5, 6);

        $storage = new question_storage_mock(1);
        $datadb = new stdClass();
        $datadb->data_1 = 'Test1';
        $question = $man->create_element($storage, 'text');
        $question->set_as_db($datadb);
        $resultdbdb = $question->get_as_db(new stdClass());
        $resultdbform = $question->get_as_form(new stdClass());

        $storage2 = new question_storage_mock(2);
        $question2 = $man->create_element($storage2, 'text');
        $dataform = new stdClass();
        $dataform->data_2_6 = 'Test2';
        $question2->set_as_form($dataform);
        $resultformdb = $question2->get_as_db(new stdClass());
        $resultformform = $question2->get_as_form(new stdClass());

        $this->assertObjectHasAttribute('data_1', $resultdbdb);
        $this->assertEquals('Test1', $resultdbdb->data_1);
        $this->assertObjectHasAttribute('data_1_6', $resultdbform);
        $this->assertEquals('Test1', $resultdbform->data_1_6);
        $this->assertObjectHasAttribute('data_2', $resultformdb);
        $this->assertEquals('Test2', $resultformdb->data_2);
        $this->assertObjectHasAttribute('data_2_6', $resultformform);
        $this->assertEquals('Test2', $resultformform->data_2_6);
    }

    public function test_get_type() {
        $man = new question_manager();
        $storage = new question_storage_mock(1);
        $question = $man->create_element($storage, 'text');
        $this->assertEquals('text', $question->get_type());
    }

    public function test_get_name() {
        $man = new question_manager();
        $storage = new question_storage_mock(1);
        $storage->name = 'New test';
        $question = $man->create_element($storage, 'text');
        $this->assertEquals('New test', $question->get_name());
        // Check default behaviour for title.
        $this->assertEquals('New test', $question->get_title());
    }

    public function test_get_prefix_db() {
        $man = new question_manager(5, 6);
        $storage = new question_storage_mock(1);
        $question = $man->create_element($storage, 'text');
        $this->assertEquals('data_1', $question->get_prefix_db());
        $this->assertEquals('data_1_6', $question->get_prefix_form());
    }

    public function test_to_html() {
        $man = new question_manager();
        $storage = new question_storage_mock(1);
        $question = $man->create_element($storage, 'text');
        // Assert default behaviour. Only format changes.
        $this->assertEquals('test&gt;', $question->to_html('<a href="http://example.com/"><b>test></b></a>'));
    }
}