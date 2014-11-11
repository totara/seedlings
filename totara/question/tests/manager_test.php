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

class question_manager_test extends totara_question_testcase {
    public function test_add_db_table() {
        $storage = new question_storage_mock(1);
        $man = new question_manager();
        $elems = array(
            // Add instance of question_base with one db field.
            $man->create_element($storage, 'text'),
            // Add instance of question_storage with three db fields.
            new question_longtext(new question_storage_mock(2))
        );
        $table = new xmldb_table('mock');
        $table->addField(new xmldb_field('data_0'));
        $list = $man->get_xmldb($elems);
        question_manager::add_db_table($list, $table);
        $fields = $table->getFields();
        $this->assertCount(5, $fields);
        foreach ($fields as $field) {
            $this->assertEquals('data_', substr($field->getName(), 0, 5));
        }
    }

    public function test_create_element() {
        $man = new question_manager();
        $storage1 = new question_storage_mock(1);
        $storage1->datatype = 'longtext';
        $storage2 = new question_storage_mock(2);
        $storage3 = new question_storage_mock(3);
        $dtype = new stdClass();
        $dtype->datatype = 'text';

        $elem1 = $man->create_element($storage1);
        $elem2 = $man->create_element($storage2, 'text');
        $elem3 = $man->create_element($storage3, $dtype);
        $elem2copy = $man->create_element($storage2, 'text');

        $this->assertInstanceOf('question_longtext', $elem1);
        $this->assertInstanceOf('question_text', $elem2);
        $this->assertInstanceOf('question_text', $elem3);
        $this->assertEquals(spl_object_hash($elem2), spl_object_hash($elem2copy));
    }

    public function test_get_xml_db() {
        $man = new question_manager();
        $storage = new question_storage_mock(1);
        $elems = array(
            // Add instance of question_base with one db field.
            $man->create_element($storage, 'text'),
            // Add instance of question_storage with three db fields.
            new question_longtext(new question_storage_mock(2))
        );
        $list = $man->get_xmldb($elems);
        $this->assertCount(4, $list);
        $this->assertContainsOnlyInstancesOf('xmldb_object', $list);
    }

    public function test_get_registered_elements() {
        $elems = question_manager::get_registered_elements();
        $this->assertGreaterThanOrEqual(18, count($elems));
        $man = new question_manager();
        $group = 0;
        $num = 0;
        foreach ($elems as $datatype => $elem) {
            $storage = new question_storage_mock(++$num);
            $quest = $man->create_element($storage, $datatype);
            $this->assertGreaterThanOrEqual($group, $elem['group']);
            $group = $elem['group'];
            $this->assertInstanceOf('question_base', $quest);
            $this->assertEquals('question_', substr($elem['classname'], 0, 9));
            $this->assertInstanceOf($elem['classname'], $quest);
        }
    }
}