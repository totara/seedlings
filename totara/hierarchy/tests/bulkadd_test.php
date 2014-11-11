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
 * @package totara
 * @subpackage hierarchy
 */

/**
 * Unit tests for add_multiple_hierarchy_items()
 *
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
 * We add two items in several places:
 * 1. To the root level
 * 2. Attached in the middle of hierarchy (to F)
 * 3. Attached to tip of hierarchy (to D)
 * 4. Attached to the end of the hierarchy (to J)
 *
 * @author Simon Coggins <simonc@catalyst.net.nz>
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

global $CFG;
require_once($CFG->dirroot . '/totara/hierarchy/lib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/organisation/lib.php');


class bulkaddhierarchyitems_test extends advanced_testcase {

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


    // test adding to the top level of a hierarchy
    function test_add_multiple_hierarchy_items_to_root() {
        global $DB;

        $org = new organisation();

        // test items to insert
        $item1 = new stdClass();
        $item1->fullname = 'Item 1';
        $item1->shortname = 'I1';
        $item1->description= 'Description Item 1';
        $item1->typeid = 0;
        $item2 = new stdClass();
        $item2->fullname = 'Item 2';
        $item2->shortname = 'I2';
        $item2->description= 'Description Item 2';
        $item2->typeid = 1;

        $items = array($item1, $item2);
        $parent = 0;

        // check items are added in the right place
        $before = $DB->get_records_menu('org', null, 'sortthread', 'id,sortthread');
        $this->assertTrue((bool)$org->add_multiple_hierarchy_items($parent, $items, 1, false));
        $after = $DB->get_records_menu('org', null, 'sortthread', 'id,sortthread');

        // new items should have been added to the end
        // all others should stay the same
        $before[11] = '04';
        $before[12] = '05';
        $this->assertEquals($before, $after);

        // get the items
        $this->assertTrue((bool)$item1 = $DB->get_record('org', array('id' => 11)));
        $this->assertTrue((bool)$item2 = $DB->get_record('org', array('id' => 12)));

        // check depthlevel set right
        $this->assertEquals(1, $item1->depthlevel);
        $this->assertEquals(1, $item2->depthlevel);

        // check path set right
        $this->assertEquals('/11', $item1->path);
        $this->assertEquals('/12', $item2->path);

        // check parentid set right
        $this->assertEquals(0, $item1->parentid);
        $this->assertEquals(0, $item2->parentid);

        // check the typeid set right
        $this->assertEquals(0, $item1->typeid);
        $this->assertEquals(1, $item2->typeid);

        $this->resetAfterTest(true);
    }

    // test adding to an item in the middle of a hierarchy
    function test_add_multiple_hierarchy_items_to_branch() {
        global $DB;

        $org = new organisation();

        // test items to insert
        $item1 = new stdClass();
        $item1->fullname = 'Item 1';
        $item1->shortname = 'I1';
        $item1->description= 'Description Item 1';
        $item1->typeid = 0;
        $item2 = new stdClass();
        $item2->fullname = 'Item 2';
        $item2->shortname = 'I2';
        $item2->description= 'Description Item 2';
        $item2->typeid = 1;

        $items = array($item1, $item2);
        $parent = 6;

        // check items are added in the right place
        $before = $DB->get_records_menu('org', null, 'sortthread', 'id,sortthread');
        $this->assertTrue((bool)$org->add_multiple_hierarchy_items($parent, $items, 1, false));
        $after = $DB->get_records_menu('org', null, 'sortthread', 'id,sortthread');

        // new items should have been inserted after parent's last child
        $before[11] = '02.01.03';
        $before[12] = '02.01.04';
        // all others should have stayed the same
        $this->assertEquals($before, $after);

        // get the items
        $this->assertTrue((bool)$item1 = $DB->get_record('org', array('id' => 11)));
        $this->assertTrue((bool)$item2 = $DB->get_record('org', array('id' => 12)));

        // check depthlevel set right
        $this->assertEquals(3, $item1->depthlevel);
        $this->assertEquals(3, $item2->depthlevel);

        // check path set right
        $this->assertEquals('/5/6/11', $item1->path);
        $this->assertEquals('/5/6/12', $item2->path);

        // check parentid set right
        $this->assertEquals(6, $item1->parentid);
        $this->assertEquals(6, $item2->parentid);

        // check the typeid set right
        $this->assertEquals(0, $item1->typeid);
        $this->assertEquals(1, $item2->typeid);

        $this->resetAfterTest(true);
    }

    // test adding to an item at the tip of a hierarchy
    function test_add_multiple_hierarchy_items_to_leaf() {
        global $DB;

        $org = new organisation();

        // test items to insert
        $item1 = new stdClass();
        $item1->fullname = 'Item 1';
        $item1->shortname = 'I1';
        $item1->description= 'Description Item 1';
        $item1->typeid = 0;
        $item2 = new stdClass();
        $item2->fullname = 'Item 2';
        $item2->shortname = 'I2';
        $item2->description= 'Description Item 2';
        $item2->typeid = 1;

        $items = array($item1, $item2);
        $parent = 4;

        // check items are added in the right place
        $before = $DB->get_records_menu('org', null, 'sortthread', 'id,sortthread');
        $this->assertTrue((bool)$org->add_multiple_hierarchy_items($parent, $items, 1, false));
        $after = $DB->get_records_menu('org', null, 'sortthread', 'id,sortthread');

        // new items should have been inserted directly after parent
        $before[11] = '01.01.02.01';
        $before[12] = '01.01.02.02';
        // all others should stay the same
        $this->assertEquals($before, $after);

        // get the items
        $this->assertTrue((bool)$item1 = $DB->get_record('org', array('id' => 11)));
        $this->assertTrue((bool)$item2 = $DB->get_record('org', array('id' => 12)));

        // check depthlevel set right
        $this->assertEquals(4, $item1->depthlevel);
        $this->assertEquals(4, $item2->depthlevel);

        // check path set right
        $this->assertEquals('/1/2/4/11', $item1->path);
        $this->assertEquals('/1/2/4/12', $item2->path);

        // check parentid set right
        $this->assertEquals(4, $item1->parentid);
        $this->assertEquals(4, $item2->parentid);

        // check the typeid set right
        $this->assertEquals(0, $item1->typeid);
        $this->assertEquals(1, $item2->typeid);

        $this->resetAfterTest(true);
    }


    // test adding to the end of a hierarchy
    function test_add_multiple_hierarchy_items_to_end() {
        global $DB;

        $org = new organisation();

        // test items to insert
        $item1 = new stdClass();
        $item1->fullname = 'Item 1';
        $item1->shortname = 'I1';
        $item1->description= 'Description Item 1';
        $item1->typeid = 0;
        $item2 = new stdClass();
        $item2->fullname = 'Item 2';
        $item2->shortname = 'I2';
        $item2->description= 'Description Item 2';
        $item2->typeid = 1;

        $items = array($item1, $item2);
        $parent = 10;

        // check items are added in the right place
        $before = $DB->get_records_menu('org', null, 'sortthread', 'id,sortthread');
        $this->assertTrue((bool)$org->add_multiple_hierarchy_items($parent, $items, 1, false));
        $after = $DB->get_records_menu('org', null, 'sortthread', 'id,sortthread');

        // new items should have been added to the end
        // all others should stay the same
        $before[11] = '03.01';
        $before[12] = '03.02';
        $this->assertEquals($before, $after);

        // get the items
        $this->assertTrue((bool)$item1 = $DB->get_record('org', array('id' => 11)));
        $this->assertTrue((bool)$item2 = $DB->get_record('org', array('id' => 12)));

        // check depthlevel set right
        $this->assertEquals(2, $item1->depthlevel);
        $this->assertEquals(2, $item2->depthlevel);

        // check path set right
        $this->assertEquals('/10/11', $item1->path);
        $this->assertEquals('/10/12', $item2->path);

        // check parentid set right
        $this->assertEquals(10, $item1->parentid);
        $this->assertEquals(10, $item2->parentid);

        // check the typeid set right
        $this->assertEquals(0, $item1->typeid);
        $this->assertEquals(1, $item2->typeid);

        $this->resetAfterTest(true);
    }
}
