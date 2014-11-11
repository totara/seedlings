<?php // $Id$
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

/*
 * Unit tests for get_items_excluding_children()
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot . '/totara/hierarchy/lib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/organisation/lib.php');

class getitemsexcludingchildren_test extends advanced_testcase {

    protected function setUp() {
        global $DB;
        parent::setup();


        $this->org_data = Array();

        $this->org1 = new stdClass();
        $this->org1->id = 1;
        $this->org1->fullname = 'Organisation A';
        $this->org1->shortname = 'Org A';
        $this->org1->description = 'Org Description A';
        $this->org1->idnumber = 'OA';
        $this->org1->frameworkid = 1;
        $this->org1->path = '/1';
        $this->org1->parentid = 0;
        $this->org1->sortthread = '01';
        $this->org1->depthlevel = 1;
        $this->org1->visible = 1;
        $this->org1->timecreated = 1234567890;
        $this->org1->timemodified = 1234567890;
        $this->org1->usermodified = 2;
        $this->org1->typeid = 0;
        $this->org_data[] = $this->org1;

        $this->org2 = new stdClass();
        $this->org2->id = 2;
        $this->org2->fullname = 'Organisation B';
        $this->org2->shortname = 'Org B';
        $this->org2->description = 'Org Description B';
        $this->org2->idnumber = 'OB';
        $this->org2->frameworkid = 1;
        $this->org2->path = '/1/2';
        $this->org2->parentid = 1;
        $this->org2->sortthread = '01.01';
        $this->org2->depthlevel = 2;
        $this->org2->visible = 1;
        $this->org2->timecreated = 1234567890;
        $this->org2->timemodified = 1234567890;
        $this->org2->usermodified = 2;
        $this->org2->typeid = 0;
        $this->org_data[] = $this->org2;

        $this->org3 = new stdClass();
        $this->org3->id = 3;
        $this->org3->fullname = 'Organisation C';
        $this->org3->shortname = 'Org C';
        $this->org3->description = 'Org Description C';
        $this->org3->idnumber = 'OC';
        $this->org3->frameworkid = 1;
        $this->org3->path = '/1/2/3';
        $this->org3->parentid = 2;
        $this->org3->sortthread = '01.01.01';
        $this->org3->depthlevel = 3;
        $this->org3->visible = 1;
        $this->org3->timecreated = 1234567890;
        $this->org3->timemodified = 1234567890;
        $this->org3->usermodified = 2;
        $this->org3->typeid = 0;
        $this->org_data[] = $this->org3;

        $this->org4 = new stdClass();
        $this->org4->id = 4;
        $this->org4->fullname = 'Organisation D';
        $this->org4->shortname = 'Org D';
        $this->org4->description = 'Org Description D';
        $this->org4->idnumber = 'OD';
        $this->org4->frameworkid = 1;
        $this->org4->path = '/1/2/4';
        $this->org4->parentid = 2;
        $this->org4->sortthread = '01.01.02';
        $this->org4->depthlevel = 3;
        $this->org4->visible = 1;
        $this->org4->timecreated = 1234567890;
        $this->org4->timemodified = 1234567890;
        $this->org4->usermodified = 2;
        $this->org4->typeid = 0;
        $this->org_data[] = $this->org4;

        $this->org5 = new stdClass();
        $this->org5->id = 5;
        $this->org5->fullname = 'Organisation E';
        $this->org5->shortname = 'Org E';
        $this->org5->description = 'Org Description E';
        $this->org5->idnumber = 'OE';
        $this->org5->frameworkid = 1;
        $this->org5->path = '/5';
        $this->org5->parentid = 0;
        $this->org5->sortthread = '02';
        $this->org5->depthlevel = 1;
        $this->org5->visible = 1;
        $this->org5->timecreated = 1234567890;
        $this->org5->timemodified = 1234567890;
        $this->org5->usermodified = 2;
        $this->org5->typeid = 0;
        $this->org_data[] = $this->org5;

        $this->org6 = new stdClass();
        $this->org6->id = 6;
        $this->org6->fullname = 'Organisation F';
        $this->org6->shortname = 'Org F';
        $this->org6->description = 'Org Description F';
        $this->org6->idnumber = 'OF';
        $this->org6->frameworkid = 1;
        $this->org6->path = '/5/6';
        $this->org6->parentid = 5;
        $this->org6->sortthread = '02.01';
        $this->org6->depthlevel = 2;
        $this->org6->visible = 1;
        $this->org6->timecreated = 1234567890;
        $this->org6->timemodified = 1234567890;
        $this->org6->usermodified = 2;
        $this->org6->typeid = 0;
        $this->org_data[] = $this->org6;

        $this->org7 = new stdClass();
        $this->org7->id = 7;
        $this->org7->fullname = 'Organisation G';
        $this->org7->shortname = 'Org G';
        $this->org7->description = 'Org Description G';
        $this->org7->idnumber = 'OG';
        $this->org7->frameworkid = 1;
        $this->org7->path = '/5/6/7';
        $this->org7->parentid = 6;
        $this->org7->sortthread = '02.01.01';
        $this->org7->depthlevel = 3;
        $this->org7->visible = 1;
        $this->org7->timecreated = 1234567890;
        $this->org7->timemodified = 1234567890;
        $this->org7->usermodified = 2;
        $this->org7->typeid = 0;
        $this->org_data[] = $this->org7;

        $this->org8 = new stdClass();
        $this->org8->id = 8;
        $this->org8->fullname = 'Organisation H';
        $this->org8->shortname = 'Org H';
        $this->org8->description = 'Org Description H';
        $this->org8->idnumber = 'OH';
        $this->org8->frameworkid = 1;
        $this->org8->path = '/5/6/8';
        $this->org8->parentid = 6;
        $this->org8->sortthread = '02.01.02';
        $this->org8->depthlevel = 3;
        $this->org8->visible = 1;
        $this->org8->timecreated = 1234567890;
        $this->org8->timemodified = 1234567890;
        $this->org8->usermodified = 2;
        $this->org8->typeid = 0;
        $this->org_data[] = $this->org8;

        $this->org9 = new stdClass();
        $this->org9->id = 9;
        $this->org9->fullname = 'Organisation I';
        $this->org9->shortname = 'Org I';
        $this->org9->description = 'Org Description I';
        $this->org9->idnumber = 'OI';
        $this->org9->frameworkid = 1;
        $this->org9->path = '/5/6/8/9';
        $this->org9->parentid = 8;
        $this->org9->sortthread = '02.01.02.01';
        $this->org9->depthlevel = 4;
        $this->org9->visible = 1;
        $this->org9->timecreated = 1234567890;
        $this->org9->timemodified = 1234567890;
        $this->org9->usermodified = 2;
        $this->org9->typeid = 0;
        $this->org_data[] = $this->org9;

        $this->org10 = new stdClass();
        $this->org10->id = 10;
        $this->org10->fullname = 'Organisation J';
        $this->org10->shortname = 'Org J';
        $this->org10->description = 'Org Description J';
        $this->org10->idnumber = 'OJ';
        $this->org10->frameworkid = 1;
        $this->org10->path = '/10';
        $this->org10->parentid = 0;
        $this->org10->sortthread = '03';
        $this->org10->depthlevel = 1;
        $this->org10->visible = 1;
        $this->org10->timecreated = 1234567890;
        $this->org10->timemodified = 1234567890;
        $this->org10->usermodified = 2;
        $this->org10->typeid = 0;
        $this->org_data[] = $this->org10;

        $DB->insert_records_via_batch('org', $this->org_data);
    }

/*
 * Testing hierarchy:
 *
 * A
 * |_B
 * | |_C
 * | |_D
 * E
 * |_F
 * | |_G
 * | |_H
 * |   |_I
 * J
 *
 */
    function test_cases_with_no_children() {
        $org = new organisation();

        // cases where no items are the children of any others
        $testcases = array(
            array(2,5,10),
            array(2),
            array(1,9),
            array(4,8),
        );

        foreach ($testcases as $testcase) {
            // should match exactly without change
            $output = $org->get_items_excluding_children($testcase);
            $this->assertEquals($testcase, $output);
        }
        $this->resetAfterTest(false);
    }

    function test_cases_with_duplicates() {
        $org = new organisation();

        // cases where there are duplicates
        $testcases = array(
            array(2,5,10,5),
            array(2,2),
            array(1,9,1,9),
            array(4,8,4),
        );

        foreach ($testcases as $testcase) {
            // should match the unique elements of the array
            $output = $org->get_items_excluding_children($testcase);
            $this->assertEquals(array_unique($testcase), $output);
        }
        $this->resetAfterTest(false);
    }


    function test_cases_with_children() {
        $org = new organisation();

        // cases where no items are the children of any others
        $testcases = array(
            array('before' => array(1,3,5,7,9), 'after' => array(1,5)),
            array('before' => array(1,2,3,4,5,6,7,8,9,10), 'after' => array(1,5,10)),
            array('before' => array(2,4,6,9), 'after' => array(2,6)),
            array('before' => array(8,9), 'after' => array(8)),
        );

        foreach ($testcases as $testcase) {
            // should match the 'after' state
            $output = $org->get_items_excluding_children($testcase['before']);
            $this->assertEquals($testcase['after'], $output);
        }
        $this->resetAfterTest(false);
    }

    function test_cases_with_duplicates_and_children() {
        $org = new organisation();

        // cases where no items are the children of any others
        $testcases = array(
            array('before' => array(1,3,5,1,7,9,1), 'after' => array(1,5)),
            array('before' => array(1,2,3,3,4,5,9,6,7,8,2,9,10), 'after' => array(1,5,10)),
            array('before' => array(2,2,2,2,4,9,6,9), 'after' => array(2,6)),
            array('before' => array(8,9,8), 'after' => array(8)),
        );

        foreach ($testcases as $testcase) {
            // should match the 'after' state
            $output = $org->get_items_excluding_children($testcase['before']);
            $this->assertEquals($testcase['after'], $output);
        }
        $this->resetAfterTest(false);
    }
}
