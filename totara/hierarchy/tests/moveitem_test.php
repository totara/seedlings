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
 * @author Simon Coggins <simonc@catalyst.net.nz>
 * @package totara
 * @subpackage hierarchy
 */

/*
 * Unit tests for move_hierarchy_item()
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot . '/totara/hierarchy/lib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/organisation/lib.php');

class movehierarchyitem_test extends advanced_testcase {
//TODO: add tests for moving hierarchy items between frameworks

    protected function setUp() {
        global $DB;
        parent::setup();

        $this->frame_data = Array();

        $this->frame1 = new stdClass();
        $this->frame1->id = 1;
        $this->frame1->fullname = 'Framework A';
        $this->frame1->shortname = 'FW A';
        $this->frame1->description = 'Org Framework Description A';
        $this->frame1->idnumber = 'FA';
        $this->frame1->visible = 1;
        $this->frame1->timecreated = 1234567890;
        $this->frame1->timemodified = 1234567890;
        $this->frame1->usermodified = 2;
        $this->frame1->sortorder = 1;
        $this->frame1->hidecustomfields = 1;
        $this->frame_data[] = $this->frame1;

        $this->frame2 = new stdClass();
        $this->frame2->id = 2;
        $this->frame2->fullname = 'Framework B';
        $this->frame2->shortname = 'FW B';
        $this->frame2->description = 'Org Framework Description B';
        $this->frame2->idnumber = 'FB';
        $this->frame2->visible = 1;
        $this->frame2->timecreated = 1234567890;
        $this->frame2->timemodified = 1234567890;
        $this->frame2->usermodified = 2;
        $this->frame2->sortorder = 2;
        $this->frame2->hidecustomfields = 1;
        $this->frame_data[] = $this->frame2;

        $DB->insert_records_via_batch('org_framework', $this->frame_data);

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

        $this->org11 = new stdClass();
        $this->org11->id = 11;
        $this->org11->fullname = 'Organisation 1';
        $this->org11->shortname = 'Org 1';
        $this->org11->description = 'Org Description 1';
        $this->org11->idnumber = 'O1';
        $this->org11->frameworkid = 2;
        $this->org11->path = '/11';
        $this->org11->parentid = 0;
        $this->org11->sortthread = '01';
        $this->org11->depthlevel = 1;
        $this->org11->visible = 1;
        $this->org11->timecreated = 1234567890;
        $this->org11->timemodified = 1234567890;
        $this->org11->usermodified = 2;
        $this->org11->typeid = 0;
        $this->org_data[] = $this->org11;

        $this->org12 = new stdClass();
        $this->org12->id = 12;
        $this->org12->fullname = 'Organisation 2';
        $this->org12->shortname = 'Org 2';
        $this->org12->description = 'Org Description 2';
        $this->org12->idnumber = 'O2';
        $this->org12->frameworkid = 2;
        $this->org12->path = '/11/12';
        $this->org12->parentid = 11;
        $this->org12->sortthread = '01.01';
        $this->org12->depthlevel = 2;
        $this->org12->visible = 1;
        $this->org12->timecreated = 1234567890;
        $this->org12->timemodified = 1234567890;
        $this->org12->usermodified = 2;
        $this->org12->typeid = 0;
        $this->org_data[] = $this->org12;

        $this->org13 = new stdClass();
        $this->org13->id = 13;
        $this->org13->fullname = 'Organisation 3';
        $this->org13->shortname = 'Org 3';
        $this->org13->description = 'Org Description 3';
        $this->org13->idnumber = 'O3';
        $this->org13->frameworkid = 2;
        $this->org13->path = '/11/12/13';
        $this->org13->parentid = 12;
        $this->org13->sortthread = '01.01.01';
        $this->org13->depthlevel = 3;
        $this->org13->visible = 1;
        $this->org13->timecreated = 1234567890;
        $this->org13->timemodified = 1234567890;
        $this->org13->usermodified = 2;
        $this->org13->typeid = 0;
        $this->org_data[] = $this->org13;

        $this->org14 = new stdClass();
        $this->org14->id = 14;
        $this->org14->fullname = 'Organisation 4';
        $this->org14->shortname = 'Org 4';
        $this->org14->description = 'Org Description 4';
        $this->org14->idnumber = 'O4';
        $this->org14->frameworkid = 2;
        $this->org14->path = '/11/14';
        $this->org14->parentid = 11;
        $this->org14->sortthread = '01.02';
        $this->org14->depthlevel = 2;
        $this->org14->visible = 1;
        $this->org14->timecreated = 1234567890;
        $this->org14->timemodified = 1234567890;
        $this->org14->usermodified = 2;
        $this->org14->typeid = 0;
        $this->org_data[] = $this->org14;

        $this->org15 = new stdClass();
        $this->org15->id = 15;
        $this->org15->fullname = 'Organisation 5';
        $this->org15->shortname = 'Org 5';
        $this->org15->description = 'Org Description 5';
        $this->org15->idnumber = 'O5';
        $this->org15->frameworkid = 2;
        $this->org15->path = '/11/15';
        $this->org15->parentid = 11;
        $this->org15->sortthread = '01.03';
        $this->org15->depthlevel = 2;
        $this->org15->visible = 1;
        $this->org15->timecreated = 1234567890;
        $this->org15->timemodified = 1234567890;
        $this->org15->usermodified = 2;
        $this->org15->typeid = 0;
        $this->org_data[] = $this->org15;

        $this->org16 = new stdClass();
        $this->org16->id = 16;
        $this->org16->fullname = 'Organisation 6';
        $this->org16->shortname = 'Org 6';
        $this->org16->description = 'Org Description 6';
        $this->org16->idnumber = 'O6';
        $this->org16->frameworkid = 2;
        $this->org16->path = '/11/16';
        $this->org16->parentid = 11;
        $this->org16->sortthread = '01.04';
        $this->org16->depthlevel = 2;
        $this->org16->visible = 1;
        $this->org16->timecreated = 1234567890;
        $this->org16->timemodified = 1234567890;
        $this->org16->usermodified = 2;
        $this->org16->typeid = 0;
        $this->org_data[] = $this->org16;

        $DB->insert_records_via_batch('org', $this->org_data);
    }

/*
 * Testing hierarchy:
 *
 * FRAMEWORK 1:
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
 * FRAMEWORK 2:
 * 1
 * |_2
 * | |_3
 * |
 * |_4
 * |
 * |_5
 * |
 * |_6
 *
 */
    function test_new_parent_id() {
        global $DB;

        $org = new organisation();

        $item = $DB->get_records('org', array('id' => 6));
        $newparent = 3;
        $newframework = '1';

        $before = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,parentid');
        $this->assertTrue((bool)$org->move_hierarchy_item($item[6], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,parentid');

        // all that should have changed is item 6 should now have 3 as a parentid
        // others should stay the same
        $before[6] = 3;
        $this->assertEquals($before, $after);

        // now test moving to the top level
        $item = $DB->get_records('org', array('id' => 6));
        $newparent = 0;

        $before = $after;
        $this->assertTrue((bool)$org->move_hierarchy_item($item[6], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,parentid');
        $before[6] = 0;
        $this->assertEquals($before, $after);

        // now test moving from the top level
        $item = $DB->get_records('org', array('id' => 1));
        $newparent = 6;

        $before = $after;
        $this->assertTrue((bool)$org->move_hierarchy_item($item[1], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,parentid');

        $this->resetAfterTest(true);
    }

    function test_new_depthlevel() {
        global $DB;

        $org = new organisation();

        $item = $DB->get_records('org', array('id' => 6));
        $newparent = 3;
        $newframework = 1;

        $before = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,depthlevel');
        $this->assertTrue((bool)$org->move_hierarchy_item($item[6], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,depthlevel');
        // item and all it's children should have changed
        $before[6] = 4;
        $before[7] = 5;
        $before[8] = 5;
        $before[9] = 6;
        // everything else stays the same
        $this->assertEquals($before, $after);

        // now try attaching to top level
        $item = $DB->get_records('org', array('id' => 6));
        $newparent = 0;

        $before = $after;
        $this->assertTrue((bool)$org->move_hierarchy_item($item[6], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,depthlevel');
        // item and all it's children should have changed
        $before[6] = 1;
        $before[7] = 2;
        $before[8] = 2;
        $before[9] = 3;
        // everything else stays the same
        $this->assertEquals($before, $after);

        // now try moving from the top level
        $item = $DB->get_records('org', array('id' => 1));
        $newparent = 10;
        $before = $after;
        $this->assertTrue((bool)$org->move_hierarchy_item($item[1], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,depthlevel');
        // item and all it's children should have changed
        $before[1] = 2;
        $before[2] = 3;
        $before[3] = 4;
        $before[4] = 4;
        // everything else stays the same
        $this->assertEquals($before, $after);


        $this->resetAfterTest(true);
    }

    function test_new_path() {
        global $DB;

        $org = new organisation();

        $item = $DB->get_records('org', array('id' => 6));
        $newparent = 3;
        $newframework = 1;

        $before = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,path');
        $this->assertTrue((bool)$org->move_hierarchy_item($item[6], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,path');
        // item and all it's children should have changed
        $before[6] = '/1/2/3/6';
        $before[7] = '/1/2/3/6/7';
        $before[8] = '/1/2/3/6/8';
        $before[9] = '/1/2/3/6/8/9';
        // everything else stays the same
        $this->assertEquals($before, $after);

        // now try attaching to top level
        $item = $DB->get_records('org', array('id' => 6));
        $newparent = 0;

        $before = $after;
        $this->assertTrue((bool)$org->move_hierarchy_item($item[6], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,path');
        // item and all it's children should have changed
        $before[6] = '/6';
        $before[7] = '/6/7';
        $before[8] = '/6/8';
        $before[9] = '/6/8/9';
        // everything else stays the same
        $this->assertEquals($before, $after);

        // now try moving from the top level
        $item = $DB->get_records('org', array('id' => 1));
        $newparent = 10;
        $before = $after;
        $this->assertTrue((bool)$org->move_hierarchy_item($item[1], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,path');
        // item and all it's children should have changed
        $before[1] = '/10/1';
        $before[2] = '/10/1/2';
        $before[3] = '/10/1/2/3';
        $before[4] = '/10/1/2/4';
        $this->assertEquals($before, $after);
        // everything else stays the same
        $this->resetAfterTest(true);
    }

    function test_new_sortorder() {
        global $DB;

        $org = new organisation();

        $item = $DB->get_records('org', array('id' => 6));
        $newparent = 3;
        $newframework = 1;

        $before = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,sortthread');
        $this->assertTrue((bool)$org->move_hierarchy_item($item[6], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,sortthread');
        // item and all it's children should have changed
        $before[6] = '01.01.01.01';
        $before[7] = '01.01.01.01.01';
        $before[8] = '01.01.01.01.02';
        $before[9] = '01.01.01.01.02.01';
        // displaced items and everything else stays the same
        $this->assertEquals($before, $after);


        // now try attaching to top level
        $item = $DB->get_records('org', array('id' =>  6));
        $newparent = 0;

        $before = $after;
        $this->assertTrue((bool)$org->move_hierarchy_item($item[6], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,sortthread');
        // item and all it's children should have changed
        $before[6] = '04';
        $before[7] = '04.01';
        $before[8] = '04.02';
        $before[9] = '04.02.01';
        // displaced items and everything else stays the same
        $this->assertEquals($before, $after);

        // now try moving from the top level
        $item = $DB->get_records('org', array('id' => 1));
        $newparent = 10;
        $before = $after;
        $this->assertTrue((bool)$org->move_hierarchy_item($item[1], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '1'), 'sortthread', 'id,sortthread');
        // item and all it's children should have changed
        $before[1] = '03.01';
        $before[2] = '03.01.01';
        $before[3] = '03.01.01.01';
        $before[4] = '03.01.01.02';
        // displayed items and everything else stays the same
        $this->assertEquals($before, $after);
        $this->resetAfterTest(true);
    }

    function test_moving_subtree() {
        global $DB;

        $org = new organisation();

        $item = $DB->get_records('org', array('id' => 12));
        $newparent = 14;
        $newframework = 2;

        $before = $DB->get_records_menu('org', array('frameworkid' => '2'), 'sortthread', 'id,sortthread');
        $this->assertTrue((bool)$org->move_hierarchy_item($item[12], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '2'), 'sortthread', 'id,sortthread');

        // item and all it's children should have changed
        $before[12] = '01.02.01';
        $before[13] = '01.02.01.01';
        // displaced items and everything else stays the same
        $this->assertEquals($before, $after);
        $this->resetAfterTest(true);
    }

    // these moves should fail and nothing should change
    function test_bad_moves() {
        global $DB;

        $org = new organisation();

        // you shouldn't be able to move an item into it's own child
        $item = $DB->get_records('org', array('id' => 12));
        $newparent = 13;
        $newframework = 2;

        $before = $DB->get_records_menu('org', array('frameworkid' => '2'), 'sortthread', 'id,sortthread');
        // this should fail
        $this->assertFalse((bool)$org->move_hierarchy_item($item[12], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '2'), 'sortthread', 'id,sortthread');
        // everything stays the same
        $this->assertEquals($before, $after);


        // you shouldn't be able move to parent that doesn't exist
        $newparent = 999;

        $before = $DB->get_records_menu('org', array('frameworkid' => '2'), 'sortthread', 'id,sortthread');
        // this should fail
        $this->assertFalse((bool)$org->move_hierarchy_item($item[12], $newframework, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '2'), 'sortthread', 'id,sortthread');
        // everything stays the same
        $this->assertEquals($before, $after);


        // item must be an object
        $item = 1234;
        $newparent = 0;

        $before = $DB->get_records_menu('org', array('frameworkid' => '2'), 'sortthread', 'id,sortthread');
        // this should fail
        $this->assertFalse((bool)$org->move_hierarchy_item($item, $item, $newparent));
        $after = $DB->get_records_menu('org', array('frameworkid' => '2'), 'sortthread', 'id,sortthread');
        // everything stays the same
        $this->assertEquals($before, $after);
        $this->resetAfterTest(true);
    }
}
