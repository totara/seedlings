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

class question_field_review_test extends totara_question_testcase {
    public function test_prepare_stub() {
        $this->resetAfterTest();
        $storage = new question_storage_mock(1);
        $storage->answerfield = 'appraisalroleassignmentid';
        $storage->prefix = 'appraisal';
        $man = new question_manager(5, 6);
        $question = $man->create_element($storage, 'coursefromplan');
        $this->assertInstanceOf('review', $question);
        // Check without scale.
        $item = new stdClass();
        $item->itemid = 10;
        $item->scope = 7;
        $stubres = $question->prepare_stub($item);
        // Check with scale.
        $scale = $this->create_scale();
        $storage2 = new question_storage_mock(2);
        $storage2->prefix = 'appraisal';
        $storage2->answerfield = 'appraisalroleassignmentid';
        $question2 = $man->create_element($storage2, 'coursefromplan');
        $this->assertInstanceOf('review', $question2);
        $question2->param1 = $scale->id;
        $item2 = new stdClass();
        $item2->itemid = 11;
        $item2->scope = 8;
        $stubsres = $question2->prepare_stub($item2);

        $stub = current($stubres);
        $this->assertGreaterThan(0, $stub->id);
        $this->assertInstanceOf('stdClass', $stub);
        $this->assertEquals(10, $stub->itemid);
        $this->assertEquals(7, $stub->scope);
        $this->assertEmpty($stub->content);
        $this->assertEquals(1, $stub->appraisalquestfieldid);
        $this->assertEquals(6, $stub->appraisalroleassignmentid);

        $this->assertCount(3, $stubsres);
        foreach ($stubsres as $stub) {
            $this->assertGreaterThan(0, $stub->id);
            $this->assertInstanceOf('stdClass', $stub);
            $this->assertEquals(11, $stub->itemid);
            $this->assertEquals(8, $stub->scope);
            $this->assertEmpty($stub->content);
            $this->assertEquals(2, $stub->appraisalquestfieldid);
            $this->assertEquals(6, $stub->appraisalroleassignmentid);
        }
    }

    public function test_stub_exists() {
        $this->resetAfterTest();
        $storage = new question_storage_mock(1);
        $storage->answerfield = 'appraisalroleassignmentid';
        $storage->prefix = 'appraisal';
        $man = new question_manager(5, 6);
        $question = $man->create_element($storage, 'coursefromplan');
        $this->assertInstanceOf('review', $question);
        $item = new stdClass();
        $item->itemid = 10;
        $item->scope = 7;
        $question->prepare_stub($item);
        $noitem = new stdClass();
        $noitem->itemid = 9;
        $noitem->scope = 7;

        $this->assertTrue($question->stub_exists((array)$item));
        $this->assertFalse($question->stub_exists((array)$noitem));
    }

    public function test_get_grouped_items() {
        $this->resetAfterTest();
        // Mock review question.
        $storage = new question_storage_mock(1);
        $storage->answerfield = 'appraisalroleassignmentid';
        $storage->prefix = 'appraisal';
        $question = new question_mockreview($storage);
        $items = $question->get_grouped_items();
        // Check item.
        $this->assertArrayHasKey(2, $items);
        $this->assertArrayHasKey(3, $items[2]);
        $this->assertArrayHasKey(4, $items[2][3]);
        $this->assertArrayHasKey(5, $items[2][3][4]);
        $this->assertInstanceOf('stdClass', $items[2][3][4][5]);
        $this->assertObjectHasAttribute('content', $items[2][3][4][5]);
        $this->assertEquals('C2', $items[2][3][4][5]->content);
    }
}

class question_mockreview extends question_coursefromplan {
    public function get_items() {
        $field = $this->answerfield;
        $value = $this->prefix.'scalevalueid';
        $rows = array(
            (object)array('scope' => 2, 'itemid' => 3, $field => 4, $value => 5, 'content' => 'C2'),
            );
        return $rows;
    }
}