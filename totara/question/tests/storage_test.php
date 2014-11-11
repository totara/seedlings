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
require_once($CFG->dirroot.'/totara/question/field/text.class.php');

class question_storage_test extends totara_question_testcase {
    public function test_attach_element() {
        $storage = new question_storage_mock(1);
        $quest = new question_text($storage);
        $storage->attach_element($quest);

        // Check that element is attached and cross linked.
        $this->assertEquals(spl_object_hash($storage->get_element()), spl_object_hash($quest));
        $this->assertEquals($quest->id, $storage->id);
    }
    public function test_get_id() {
        $storage = new question_storage_mock(2);
        $this->assertEquals(2, $storage->getid());
        $storage2 = new question_storage_mock();
        $this->assertEquals(0, $storage2->id);
        $newid = $storage2->getid();
        $this->assertEquals(3, $newid);
        $this->assertEquals(3, $storage2->id);
    }

    public function test_get_set_isset() {
        // Check that param1 can set complex data.
        $param = array('key' => 'tested');
        $storage = new question_storage_mock();
        $storage->param1 = $param;
        $this->assertTrue(isset($storage->param1));
        $tostore = new stdClass();
        $storage->access_export_storage_fields($tostore);
        $this->assertInternalType('string', $tostore->param1);
        $tostore->param1 = str_replace('tested', 'passed', $tostore->param1);
        $storage->access_import_storage_fields($tostore);
        $testparam = $storage->param1;
        $this->assertArrayHasKey('key', $testparam);
        $this->assertEquals('passed', $testparam['key']);
    }
}
