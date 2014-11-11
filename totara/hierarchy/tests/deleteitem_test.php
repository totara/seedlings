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
 * @package totara
 * @subpackage hierarchy
 */

/**
 * Unit tests for delete_hierarchy_item().
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

global $CFG;
require_once($CFG->dirroot . '/totara/hierarchy/lib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/competency/lib.php');

class deleteitem_test extends advanced_testcase {

    protected $comp_framework_data = array(
        array(
            'id' => 1, 'fullname' => 'Competency Framework 1', 'shortname' => 'FW1', 'idnumber' => 'ID1', 'description' => 'Description 1',
            'sortorder' => 1, 'visible' => 1, 'hidecustomfields' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        )
    );

    /*
     * Testing hierarchy:
     *
     * 1
     * |_2
     * | |_3
     * | |_4
     * 5
     * |_6
     * | |_7
     * | |_8
     * |   |_9
     * 10
     *
     */
    protected $comp_data = array(
        array(
            'id' => 1, 'fullname' => 'Competency 1', 'shortname' =>  'Comp 1', 'description' => 'Competency Description 1', 'idnumber' => 'C1',
            'frameworkid' => 1, 'path' => '/1', 'depthlevel' => 1, 'parentid' => 0, 'sortthread' => '01', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 2, 'fullname' => 'Competency 2', 'shortname' => 'Comp 2', 'description' => 'Competency Description 2', 'idnumber' => 'C2',
            'frameworkid' => 1,  'path' => '/1/2', 'depthlevel' => 2, 'parentid' => 1, 'sortthread' => '01.01', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 3, 'fullname' => 'Competency 3', 'shortname' => 'Comp 3', 'description' => 'Competency Description 3', 'idnumber' => 'C3',
            'frameworkid' => 1, 'path' => '/1/2/3', 'depthlevel' => 3, 'parentid' => 2, 'sortthread' => '01.01.01', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 4, 'fullname' => 'Competency 4', 'shortname' => 'Comp 4', 'description' => 'Competency Description 4', 'idnumber' => 'C4',
            'frameworkid' => 1, 'path' => '/1/2/4', 'depthlevel' => 3, 'parentid' => 2, 'sortthread' => '01.01.02', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 5, 'fullname' => 'Competency 5', 'shortname' => 'Comp 5', 'description' => 'Competency Description 5', 'idnumber' => 'C5',
            'frameworkid' => 1, 'path' => '/5', 'depthlevel' => 1, 'parentid' => 0, 'sortthread' => '02', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 6, 'fullname' => 'Competency 6', 'shortname' =>  'Comp 6', 'description' => 'Competency Description 6', 'idnumber' => 'C6',
            'frameworkid' => 1, 'path' => '/5/6', 'depthlevel' => 2, 'parentid' => 5, 'sortthread' => '02.01', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 7, 'fullname' => 'Competency 7', 'shortname' => 'Comp 7', 'description' => 'Competency Description 7', 'idnumber' => 'C7',
            'frameworkid' => 1,  'path' => '/5/6/7', 'depthlevel' => 3, 'parentid' => 6, 'sortthread' => '02.01.01', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 8, 'fullname' => 'F2 Competency 8', 'shortname' => 'Comp 8', 'description' => 'Competency Description 8', 'idnumber' => 'C8',
            'frameworkid' => 1, 'path' => '/5/6/8', 'depthlevel' => 3, 'parentid' => 6, 'sortthread' => '02.01.02', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 9, 'fullname' => 'Competency 9', 'shortname' => 'Comp 9', 'description' => 'Competency Description 9', 'idnumber' => 'C9',
            'frameworkid' => 1, 'path' => '/5/6/8/9', 'depthlevel' => 4, 'parentid' => 8, 'sortthread' => '02.01.02.01', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
        array(
            'id' => 10, 'fullname' => 'Competency 10', 'shortname' => 'Comp 10', 'description' => 'Competency Description 10', 'idnumber' => 'C10',
            'frameworkid' => 1, 'path' => '/10', 'depthlevel' => 1, 'parentid' => 0, 'sortthread' => '03', 'visible' => 1, 'aggregationmethod' => 1,
            'proficiencyexpected' => 1, 'evidencecount' => 0, 'timecreated' => 1265963591, 'timemodified' => 1265963591, 'usermodified' => 2,
        ),
    );

    protected function setUp() {
        parent::setUp();

        $this->loadDataSet($this->createArrayDataset(
            array(
                'comp_framework' => $this->comp_framework_data,
                'comp' => $this->comp_data
            )
        ));
    }

    public function test_ordering_after_delete() {
        global $DB;
        $this->resetAfterTest();

        $hierarchy = new competency();

        $before = $DB->get_records_menu('comp', null, 'sortthread', 'id,sortthread');
        $this->assertTrue($hierarchy->delete_hierarchy_item(1, false));
        $after = $DB->get_records_menu('comp', null, 'sortthread', 'id,sortthread');

        // Items 1-4 should have been deleted (all children of item 1).
        unset($before[1]);
        unset($before[2]);
        unset($before[3]);
        unset($before[4]);
        $this->assertEquals($before, $after);
    }

    public function test_ordering_after_delete2() {
        global $DB;
        $this->resetAfterTest();

        $hierarchy = new competency();

        $before = $DB->get_records_menu('comp', null, 'sortthread', 'id,sortthread');
        $this->assertTrue($hierarchy->delete_hierarchy_item(2, false));
        $after = $DB->get_records_menu('comp', null, 'sortthread', 'id,sortthread');

        // Items 2-4 should have been deleted (all children of item 2).
        unset($before[2]);
        unset($before[3]);
        unset($before[4]);
        $this->assertEquals($before, $after);
    }

    public function test_ordering_after_delete3() {
        global $DB;
        $this->resetAfterTest();

        $hierarchy = new competency();

        $before = $DB->get_records_menu('comp', null, 'sortthread', 'id,sortthread');
        $this->assertTrue($hierarchy->delete_hierarchy_item(9, false));
        $after = $DB->get_records_menu('comp', null, 'sortthread', 'id,sortthread');

        // Items 9 should have been deleted (no children).
        unset($before[9]);
        $this->assertEquals($before, $after);
    }

    public function test_ordering_after_delete4() {
        global $DB;
        $this->resetAfterTest();

        $hierarchy = new competency();

        $before = $DB->get_records_menu('comp', null, 'sortthread', 'id,sortthread');
        $this->assertTrue($hierarchy->delete_hierarchy_item(10, false));
        $after = $DB->get_records_menu('comp', null, 'sortthread', 'id,sortthread');

        // Items 10 should have been deleted (no children).
        unset($before[10]);
        // No sort changes expected.
        $this->assertEquals($before, $after);
    }
}
