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
require_once($CFG->dirroot.'/totara/question/lib.php');
require_once($CFG->dirroot.'/totara/question/field/coursefromplan.class.php');


abstract class totara_question_testcase extends advanced_testcase {
    public function setUp() {
        parent::setUp();
        question_storage_mock::$maxid = 0;
        question_manager::reset();
    }

    /**
     * Create test scale with values
     * @global type $DB
     * @param type $prefix
     * @param type $type
     * @return \StdClass
     */
    protected function create_scale($prefix = 'appraisal', $type = multichoice::SCALE_TYPE_REVIEW) {
        global $DB;
        $scale = new StdClass();
        $scale->name = 'Scale';
        $scale->userid = 2;
        $scale->scaletype = $type;
        $scale->id = $DB->insert_record($prefix.'_scale', $scale);
        $scale->values = array();
        for ($num = 1; $num <= 3; $num++) {
            $value = new stdClass();
            $value->name = 'Option'.$num;
            $value->{$prefix.'scaleid'} = $scale->id;
            $value->score = $num;
            $value->id = $DB->insert_record($prefix.'_scale_value', $value);
            $scale->values[] = $value;
        }
        return $scale;
    }
}

class question_storage_mock extends question_storage {
    public static $maxid = 0;
    public function __construct($id = 0) {
        $this->id = $id;
        if ($id > self::$maxid) {
            self::$maxid = $id;
        }
    }

    public function save() {
        if (!$this->id) {
            $this->id = ++self::$maxid;
        }
    }

    public function access_export_storage_fields($obj) {
        return $this->export_storage_fields($obj);
    }

    public function access_import_storage_fields($obj) {
        return $this->import_storage_fields($obj);
    }
}

class mock_question_form extends question_base_form {
    public function definition() {
    }
}