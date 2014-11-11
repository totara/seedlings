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
 * @editor David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage hierarchy
 */

/*
 * PhpUnit tests for hierarchy/lib.php
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot . '/totara/hierarchy/lib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/competency/lib.php');


class hierarchylib_test extends advanced_testcase {
    // test data for database

    protected function setUp() {
        global $DB;
        parent::setup();

        if (!isset($this->frame_data)) {
        //set up some hierarchy frameworks
        $this->frame_data = Array();

        $this->frame1 = new stdClass();
        $this->frame1->id = 1;
        $this->frame1->fullname = 'Framework 1';
        $this->frame1->shortname = 'FW1';
        $this->frame1->description = 'Description 1';
        $this->frame1->sortorder = 1;
        $this->frame1->idnumber = 'ID1';
        $this->frame1->visible = 1;
        $this->frame1->timecreated = 1265963591;
        $this->frame1->timemodified = 1265963591;
        $this->frame1->usermodified = 2;
        $this->frame1->hidecustomfields = 1;
        $this->frame_data[] = $this->frame1;

        $this->frame2 = new stdClass();
        $this->frame2->id = 2;
        $this->frame2->fullname = 'Framework 2';
        $this->frame2->shortname = 'FW2';
        $this->frame2->description = 'Description 2';
        $this->frame2->sortorder = 2;
        $this->frame2->idnumber = 'ID2';
        $this->frame2->visible = 1;
        $this->frame2->timecreated = 1265963591;
        $this->frame2->timemodified = 1265963591;
        $this->frame2->usermodified = 2;
        $this->frame2->hidecustomfields = 1;
        $this->frame_data[] = $this->frame2;


        //Set up some hierarchy types
        $this->type_data = Array();

        $this->type1 = new stdClass();
        $this->type1->id = 1;
        $this->type1->fullname = 'type 1';
        $this->type1->shortname = 'type 1';
        $this->type1->description = 'Description 1';
        $this->type1->timecreated = 1265963591;
        $this->type1->timemodified = 1265963591;
        $this->type1->usermodified = 2;
        $this->type_data[] = $this->type1;

        $this->type2 = new stdClass();
        $this->type2->id = 2;
        $this->type2->fullname = 'type 2';
        $this->type2->shortname = 'type 2';
        $this->type2->description = 'Description 2';
        $this->type2->timecreated = 1265963591;
        $this->type2->timemodified = 1265963591;
        $this->type2->usermodified = 2;
        $this->type_data[] = $this->type2;

        $this->type3 = new stdClass();
        $this->type3->id = 3;
        $this->type3->fullname = 'type 3';
        $this->type3->shortname = 'type 3';
        $this->type3->description = 'Description 3';
        $this->type3->timecreated = 1265963591;
        $this->type3->timemodified = 1265963591;
        $this->type3->usermodified = 2;
        $this->type_data[] = $this->type3;


        //set up some competencies
        $this->comp_data = Array();

        $this->comp1 = new stdClass();
        $this->comp1->id = 1;
        $this->comp1->fullname = 'Competency 1';
        $this->comp1->shortname = 'Comp 1';
        $this->comp1->description = 'Competency Description 1';
        $this->comp1->idnumber = 'C1';
        $this->comp1->frameworkid = 1;
        $this->comp1->path = '/1';
        $this->comp1->parentid = 0;
        $this->comp1->sortthread = '01';
        $this->comp1->visible = 1;
        $this->comp1->aggregationmethod = 1;
        $this->comp1->proficiencyexpected = 1;
        $this->comp1->evidencecount = 0;
        $this->comp1->timecreated = 1265963591;
        $this->comp1->timemodified = 1265963591;
        $this->comp1->usermodified = 2;
        $this->comp1->depthlevel = 1;
        $this->comp1->typeid = 1;
        $this->comp_data[] = $this->comp1;

        $this->comp2 = new stdClass();
        $this->comp2->id = 2;
        $this->comp2->fullname = 'Competency 2';
        $this->comp2->shortname = 'Comp 2';
        $this->comp2->description = 'Competency Description 2';
        $this->comp2->idnumber = 'C2';
        $this->comp2->frameworkid = 1;
        $this->comp2->path = '/1/2';
        $this->comp2->parentid = 1;
        $this->comp2->sortthread = '01.01';
        $this->comp2->visible = 1;
        $this->comp2->aggregationmethod = 1;
        $this->comp2->proficiencyexpected = 1;
        $this->comp2->evidencecount = 0;
        $this->comp2->timecreated = 1265963591;
        $this->comp2->timemodified = 1265963591;
        $this->comp2->usermodified = 2;
        $this->comp2->depthlevel = 2;
        $this->comp2->typeid = 2;
        $this->comp_data[] = $this->comp2;

        $this->comp3 = new stdClass();
        $this->comp3->id = 3;
        $this->comp3->fullname = 'F2 Competency 1';
        $this->comp3->shortname = 'F2 Comp 1';
        $this->comp3->description = 'F2 Competency Description 1';
        $this->comp3->idnumber = 'F2 C1';
        $this->comp3->frameworkid = 2;
        $this->comp3->path = '/3';
        $this->comp3->parentid = 0;
        $this->comp3->sortthread = '01';
        $this->comp3->visible = 1;
        $this->comp3->aggregationmethod = 1;
        $this->comp3->proficiencyexpected = 1;
        $this->comp3->evidencecount = 0;
        $this->comp3->timecreated = 1265963591;
        $this->comp3->timemodified = 1265963591;
        $this->comp3->usermodified = 2;
        $this->comp3->depthlevel = 2;
        $this->comp3->typeid = 2;
        $this->comp_data[] = $this->comp3;

        $this->comp4 = new stdClass();
        $this->comp4->id = 4;
        $this->comp4->fullname = 'Competency 3';
        $this->comp4->shortname = 'Comp 3';
        $this->comp4->description = 'Competency Description 3';
        $this->comp4->idnumber = 'C3';
        $this->comp4->frameworkid = 1;
        $this->comp4->path = '/1/4';
        $this->comp4->parentid = 1;
        $this->comp4->sortthread = '01.02';
        $this->comp4->visible = 1;
        $this->comp4->aggregationmethod = 1;
        $this->comp4->proficiencyexpected = 1;
        $this->comp4->evidencecount = 0;
        $this->comp4->timecreated = 1265963591;
        $this->comp4->timemodified = 1265963591;
        $this->comp4->usermodified = 2;
        $this->comp4->depthlevel = 2;
        $this->comp4->typeid = 0;
        $this->comp_data[] = $this->comp4;

        $this->comp5 = new stdClass();
        $this->comp5->id = 5;
        $this->comp5->fullname = 'Competency 4';
        $this->comp5->shortname = 'Comp 4';
        $this->comp5->description = 'Competency Description 4';
        $this->comp5->idnumber = 'C4';
        $this->comp5->frameworkid = 1;
        $this->comp5->path = '/5';
        $this->comp5->parentid = 0;
        $this->comp5->sortthread = '02';
        $this->comp5->visible = 1;
        $this->comp5->aggregationmethod = 1;
        $this->comp5->proficiencyexpected = 1;
        $this->comp5->evidencecount = 0;
        $this->comp5->timecreated = 1265963591;
        $this->comp5->timemodified = 1265963591;
        $this->comp5->usermodified = 2;
        $this->comp5->depthlevel = 1;
        $this->comp5->typeid = 0;
        $this->comp_data[] = $this->comp5;


        //set up a hierarchy custom type field
        $this->type_field_data = new stdClass();
        $this->type_field_data->id = 1;
        $this->type_field_data->fullname = 'Custom Field 1';
        $this->type_field_data->shortname = 'CF1';
        $this->type_field_data->typeid = 2;
        $this->type_field_data->datatype = 'checkbox';
        $this->type_field_data->description = 'Custom Field Description 1';
        $this->type_field_data->sortorder = 1;
        $this->type_field_data->hidden = 0;
        $this->type_field_data->locked = 0;
        $this->type_field_data->required = 0;
        $this->type_field_data->forceunique = 0;


        $this->type_data_data = new stdClass();
        $this->type_data_data->id = 1;
        $this->type_data_data->data = 1;
        $this->type_data_data->fieldid = 1;
        $this->type_data_data->competencyid = 2;


        //set up evidence data
        $this->competency_data = new stdClass();
        $this->competency_data->id = 1;
        $this->competency_data->userid = 1;
        $this->competency_data->competencyid = 1;
        $this->competency_data->timecreated = 1265963591;
        $this->competency_data->timemodified = 1265963591;
        $this->competency_data->reaggregate = 1;
        $this->competency_data->manual = 1;
        $this->competency_data->iteminstance = 1;
        $this->competency_data->usermodified = 2;
        $this->competency_data->itemid = 1;


        //set up a competency template
        $this->template_data = new stdClass();
        $this->template_data->id = 1;
        $this->template_data->frameworkid = 1;
        $this->template_data->fullname = 'framework 1';
        $this->template_data->visible = 1;
        $this->template_data->competencycount = 1;
        $this->template_data->timecreated = 1265963591;
        $this->template_data->timemodified = 1265963591;
        $this->template_data->usermodified = 2;


        //set up competency template assignments
        $this->template_assignment_data = new stdClass();
        $this->template_assignment_data->id = 1;
        $this->template_assignment_data->templateid = 1;
        $this->template_assignment_data->type = 1;
        $this->template_assignment_data->instanceid = 1;
        $this->template_assignment_data->timecreated = 1265963591;
        $this->template_assignment_data->usermodified = 2;


        //set up org/pos competency links
        $this->org_pos_data = new stdClass();
        $this->org_pos_data->id = 1;
        $this->org_pos_data->positionid = 1;
        $this->org_pos_data->organisationid = 1;
        $this->org_pos_data->timecreated = 1265963591;
        $this->org_pos_data->timemodified = 1265963591;
        $this->org_pos_data->usermodified = 2;


        //set up relations
        $this->relations_data = new stdClass();
        $this->relations_data->id = 1;
        $this->relations_data->id1 = 1;
        $this->relations_data->id2 = 1;


        //set up competency scale assignments
        $this->scale_assignments_data = new stdClass();
        $this->scale_assignments_data->id = 1;
        $this->scale_assignments_data->scaleid = 1;
        $this->scale_assignments_data->frameworkid = 1;
        $this->scale_assignments_data->timemodified = 1;
        $this->scale_assignments_data->usermodified = 1;


        //set up plan competencies
        $this->plan_competency_assign_data = new stdClass();
        $this->plan_competency_assign_data->id = 1;
        $this->plan_competency_assign_data->planid = 1;
        $this->plan_competency_assign_data->competencyid = 5;


        //set up plan courses
        $this->plan_course_assign_data = new stdClass();
        $this->plan_course_assign_data->id = 2;
        $this->plan_course_assign_data->planid = 1;
        $this->plan_course_assign_data->courseid = 3;


        //set up event handlers
        $this->events_handlers_data = new stdClass();
        $this->events_handlers_data->id = 1;
        $this->events_handlers_data->eventname = 'fakeevent';
        $this->events_handlers_data->component = '';
        $this->events_handlers_data->handlerfile = '';
        $this->events_handlers_data->handlerfunction = '';
        $this->events_handlers_data->schedule = '';
        $this->events_handlers_data->status = 0;
        $this->events_handlers_data->internal = 1;

}

        $DB->insert_records_via_batch('comp_framework', $this->frame_data);
        $DB->insert_records_via_batch('comp_type', $this->type_data);
        $DB->insert_records_via_batch('comp', $this->comp_data);
        $DB->insert_record('comp_type_info_field', $this->type_field_data);
        $DB->insert_record('comp_type_info_data', $this->type_data_data);
        $DB->insert_record('comp_record', $this->competency_data);
        $DB->insert_record('comp_criteria', $this->competency_data);
        $DB->insert_record('comp_criteria_record', $this->competency_data);
        $DB->insert_record('comp_template', $this->template_data);
        $DB->insert_record('comp_template_assignment', $this->template_assignment_data);
        $DB->insert_record('org_competencies', $this->org_pos_data);
        $DB->insert_record('pos_competencies', $this->org_pos_data);
        $DB->insert_record('comp_relations', $this->relations_data);
        $DB->insert_record('comp_scale_assignments', $this->scale_assignments_data);
        $DB->insert_record('dp_plan_competency_assign', $this->plan_competency_assign_data);
        $DB->insert_record('dp_plan_course_assign', $this->plan_course_assign_data);
        $DB->insert_record('events_handlers', $this->events_handlers_data);

        // create the competency object
        $this->competency = new competency();
        $this->competency->frameworkid = 1;
        // create 2nd competency object with no frameworkid specified
        $this->nofwid = new competency();

        // create some sample objects
        // framework
        $this->fw1 = new stdClass();
        $this->fw1->fullname = 'Framework 1';
        $this->fw1->shortname = 'FW1';
        $this->fw1->idnumber = 'ID1';
        $this->fw1->description = 'Description 1';
        $this->fw1->sortorder = '1';
        $this->fw1->visible = '1';
        $this->fw1->hidecustomfields = '1';
        $this->fw1->timecreated = '1265963591';
        $this->fw1->timemodified = '1265963591';
        $this->fw1->usermodified = '2';
        $this->fw1->id = '1';
        // hierarchy type
        $this->type1 = new stdClass();
        $this->type1->id = 1;
        $this->type1->fullname = 'type 1';
        $this->type1->shortname = 'type 1';
        $this->type1->description = 'Description 1';
        $this->type1->timecreated = '1265963591';
        $this->type1->timemodified = '1265963591';
        $this->type1->usermodified = '2';
        $this->type1->idnumber = null;
        // competency
        $this->c1 = new stdClass();
        $this->c1->id = '1';
        $this->c1->fullname = 'Competency 1';
        $this->c1->shortname = 'Comp 1';
        $this->c1->description = 'Competency Description 1';
        $this->c1->idnumber = 'C1';
        $this->c1->frameworkid = '1';
        $this->c1->path = '/1';
        $this->c1->parentid = '0';
        $this->c1->sortthread = '01';
        $this->c1->visible = '1';
        $this->c1->aggregationmethod = '1';
        $this->c1->proficiencyexpected = '1';
        $this->c1->evidencecount = '0';
        $this->c1->timecreated = '1265963591';
        $this->c1->timemodified = '1265963591';
        $this->c1->usermodified = '2';
        $this->c1->depthlevel = '1';
        $this->c1->typeid = '1';
        // another competency
        $this->c2 = new stdClass();
        $this->c2->id = '1';
        $this->c2->fullname = 'Competency 2';
        $this->c2->shortname = 'Comp 2';
        $this->c2->description = 'Competency Description 2';
        $this->c2->idnumber = 'C2';
        $this->c2->frameworkid = '1';
        $this->c2->path = '/1/2';
        $this->c2->parentid = '1';
        $this->c2->sortthread = '01.01';
        $this->c2->visible = '1';
        $this->c2->aggregationmethod = '1';
        $this->c2->evidencecount = '0';
        $this->c2->proficiencyexpected = '1';
        $this->c2->timecreated = '1265963591';
        $this->c2->timemodified = '1265963591';
        $this->c2->usermodified = '2';
        $this->c2->depthlevel = '2';
        $this->c2->typeid = '2';

        //Expected custom field return data for get_custom_fields
        $this->cf1 = new stdClass();
        $this->cf1->id = 1;
        $this->cf1->data = 1;
        $this->cf1->fieldid = 1;
        $this->cf1->competencyid = 2;
        $this->cf1->datatype = 'checkbox';
        $this->cf1->hidden = 0;
        $this->cf1->fullname = 'Custom Field 1';
    }

    function test_hierarchy_get_framework() {
        global $DB;
        $competency = $this->competency;
        $fw1 = $this->fw1;

        // specifying id should get that framework
        $this->assertEquals('Framework 2', $competency->get_framework(2)->fullname);
        // not specifying id should get first framework (by sort order)
        $this->assertEquals($fw1->fullname, $competency->get_framework()->fullname);
        // the framework returned should contain all the necessary fields
        $this->assertEquals($fw1, $competency->get_framework(1));
        // clear all frameworks
        $DB->delete_records('comp_framework');
        // if no frameworks exist should return false
        $this->assertFalse((bool)$competency->get_framework(0, false, true));

        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_type_by_id() {
        $competency = $this->competency;
        $type1 = $this->type1;
        // the type returned should contain all the necessary fields
        $this->assertEquals($type1, $competency->get_type_by_id(1));
        // the type with the correct id should be returned
        $this->assertEquals('type 2', $competency->get_type_by_id(2)->fullname);
        // false should be returned if the type doesn't exist
        $this->assertFalse((bool)$competency->get_type_by_id(999));
        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_frameworks() {
        global $DB;
        $competency = $this->competency;
        $fw1 = $this->fw1;
        // should return an array of frameworks
        $this->assertTrue((bool)is_array($competency->get_frameworks()));
        // the array should include all frameworks
        $this->assertEquals(2, count($competency->get_frameworks()));
        // each array element should contain a framework
        $this->assertEquals($fw1, current($competency->get_frameworks()));
        // clear out the framework
        $DB->delete_records('comp_framework');
        // if no frameworks exist should return false
        $this->assertFalse((bool)$competency->get_frameworks());
        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_types() {
        global $DB;
        $competency = $this->competency;
        $type1 = $this->type1;
        // should return an array of types
        $this->assertTrue((bool)is_array($competency->get_types()));
        // the array should include all types (in this framework)
        $this->assertEquals(3, count($competency->get_types()));
        // each array element should contain a type
        $this->assertEquals($type1, current($competency->get_types()));
        // clear out the types
        $DB->delete_records('comp_type');
        // if no types exist should return false
        $this->assertFalse((bool)$competency->get_types());
        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_custom_fields() {
        $competency = $this->competency;
        $customfields = $competency->get_custom_fields(2);

        //Returned value is an array
        $this->assertTrue((bool)is_array($customfields));

        //Returned array is not empty
        $this->assertFalse((bool)empty($customfields));

        //Returned array contains one item
        $this->assertEquals(1, count($customfields));

        //Returned array is identical to expected data
        $expected_data = array(
            'id' => $this->cf1->id,
            'data' => $this->cf1->data,
            'fieldid' => $this->cf1->fieldid,
            'competencyid' => $this->cf1->competencyid,
            'datatype' => $this->cf1->datatype,
            'hidden' => $this->cf1->hidden,
            'fullname' => $this->cf1->fullname
        );
        $expected = array($this->cf1->id => (object) $expected_data);
        $this->assertEquals($expected, $customfields);

        //Empty array is returned for a non-existent item id
        $this->assertEquals(array(), $competency->get_custom_fields(9000));
        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_item() {
        $competency = $this->competency;
        $c1 = $this->c1;
        // the item returned should contain all the necessary fields
        $this->assertEquals($c1, $competency->get_item(1));
        // the item should match the id requested
        $this->assertEquals('Competency 2', $competency->get_item(2)->fullname);
        // should return false if the item doesn't exist
        $this->assertFalse((bool)$competency->get_item(999));
        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_items() {
        global $DB;
        $competency = $this->competency;
        $c1 = $this->c1;
        // should return an array of items
        $this->assertTrue((bool)is_array($competency->get_items()));
        // the array should include all items
        $this->assertEquals(4, count($competency->get_items()));
        // each array element should contain an item object
        $this->assertEquals($c1, current($competency->get_items()));
        // clear out the items
        $DB->delete_records('comp');
        // if no items exist should return false
        $this->assertFalse((bool)$competency->get_items());
        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_items_by_parent() {
        global $DB;
        $competency = $this->competency;
        $c1 = $this->c1;
        // should return an array of items belonging to specified parent
        $this->assertTrue((bool)is_array($competency->get_items_by_parent(1)));
        // should return one element per item
        $this->assertEquals(2, count($competency->get_items_by_parent(1)));
        // each array element should contain an item
        $this->assertEquals('Competency 2', current($competency->get_items_by_parent(1))->fullname);
        // if no parent specified should return root level items
        $this->assertEquals($c1, current($competency->get_items_by_parent()));
        // clear out the items
        $DB->delete_records('comp');
        // if no items exist should return false for root items and parents
        $this->assertFalse((bool)$competency->get_items_by_parent());
        $this->assertFalse((bool)$competency->get_items_by_parent(1));
        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_all_root_items() {
        global $DB;
        $competency = $this->competency;
        $nofwid = $this->nofwid;
        $c1 = $this->c1;
        // should return root items for framework where id specified
        $this->assertEquals($c1, current($competency->get_all_root_items()));
        // should return all root items (cross framework) if no fwid given
        $this->assertEquals(3, count($nofwid->get_all_root_items()));
        // should return all root items, even if fwid given, if $all set to true
        $this->assertEquals(3, count($competency->get_all_root_items(true)));
        // clear out the items
        $DB->delete_records('comp');
        // if no items exist should return false
        $this->assertFalse((bool)$competency->get_all_root_items());
        $this->assertFalse((bool)$nofwid->get_all_root_items());
        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_item_descendants() {
        $competency = $this->competency;
        $c1 = $this->c1;
        $nofwid = $this->nofwid;

        // create an object of the expected format
        $obj = new StdClass();
        $obj->fullname = $c1->fullname;
        $obj->parentid = $c1->parentid;
        $obj->path = $c1->path;
        $obj->sortthread = $c1->sortthread;
        $obj->id = $c1->id;

        // should return an array of items
        $this->assertTrue((bool)is_array($competency->get_item_descendants(1)));
        // array elements should match an expected format
        $this->assertEquals($obj, current($competency->get_item_descendants(1)));
        // should return the item with the specified ID and all its descendants
        $this->assertEquals(3, count($competency->get_item_descendants(1)));
        // should still return itself if an item has no descendants
        $this->assertEquals(1, count($competency->get_item_descendants(2)));
        // should work the same for different frameworks
        $this->assertEquals(1, count($nofwid->get_item_descendants(3)));
        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_hierarchy_item_adjacent_peer() {
        $competency = $this->competency;
        $c1 = $this->c1;
        $c2 = $this->c2;

        // if an adjacent peer exists, should return its id
        $this->assertEquals(4, $competency->get_hierarchy_item_adjacent_peer($c2, HIERARCHY_ITEM_BELOW));
        // should return false if no adjacent peer exists in the direction specified
        $this->assertFalse((bool)$competency->get_hierarchy_item_adjacent_peer($c2, HIERARCHY_ITEM_ABOVE));
        $this->assertFalse((bool)$competency->get_hierarchy_item_adjacent_peer($c1, HIERARCHY_ITEM_ABOVE));
        // should return false if item is not valid
        $this->assertFalse((bool)$competency->get_hierarchy_item_adjacent_peer(null));
        $this->resetAfterTest(true);
    }

    function test_hierarchy_make_hierarchy_list() {
        global $DB;
        $competency = $this->competency;
        $c1 = $this->c1;

        // standard list with default options
        $competency->make_hierarchy_list($list);
        // list with other options
        $competency->make_hierarchy_list($list2, null, true, true);

        // value should be fullname by default
        $this->assertEquals($c1->fullname, $list[1]);
        // value should be shortname if required
        $this->assertEquals($c1->shortname, $list2[1]);
        // should include all children unless specified
        $this->assertFalse((bool)array_search('Comp 1 (and all children)', $list));
        // should include all children row if required
        $this->assertEquals('1,2,4', array_search('Comp 1 (and all children)', $list2));

        // clear out the items
        $DB->delete_records('comp');
        // if no items exist should return false
        $competency->make_hierarchy_list($list3);
        // should return empty list if no items found
        $this->assertEquals(array(), $list3);
        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_item_lineage() {
        $competency = $this->competency;
        $c1 = $this->c1;
        $nofwid = $this->nofwid;

        // expected format of result
        $obj = new stdClass();
        $obj->fullname = $c1->fullname;
        $obj->parentid = $c1->parentid;
        $obj->depthlevel = $c1->depthlevel;
        $obj->id = (int) $c1->id;

        // should return an array of items
        $this->assertTrue((bool)is_array($competency->get_item_lineage(2)));
        // array elements should match an expected format
        $this->assertEquals($obj, current($competency->get_item_lineage(2)));
        // should return the item with the specified ID and all its parents
        $this->assertEquals(2, count($competency->get_item_lineage(2)));
        // should still return itself if an item has no parents
        $this->assertEquals(1, count($competency->get_item_lineage(1)));
        $this->assertEquals('Competency 1', current($competency->get_item_lineage(1))->fullname);
        // should work the same for different frameworks
        $this->assertEquals(1, count($nofwid->get_item_lineage(3)));
        // NOTE function ignores fwid of current hierarchy object
        // not sure that this is correct behaviour
        $this->assertEquals('F2 Competency 1', current($competency->get_item_lineage(3))->fullname);
        $this->resetAfterTest(true);
    }

    // skipped tests for the following display functions:
    // get_editing_button()
    // display_framework_selector()
    // display_add_item_button()
    // display_add_type_button()

    function test_hierarchy_hide_item() {
        global $DB;
        $competency = $this->competency;
        $competency->hide_item(1);
        $visible = $DB->get_field('comp', 'visible', array('id' => 1));
        // item should not be visible
        $this->assertEquals(0, $visible);
        // also test show item
        $competency->show_item(1);
        $visible = $DB->get_field('comp', 'visible', array('id' => 1));
        // item should be visible again
        $this->assertEquals(1, $visible);
        $this->resetAfterTest(true);
    }

    function test_hierarchy_hide_framework() {
        global $DB;
        $competency = $this->competency;
        $competency->hide_framework(1);
        $visible =  $DB->get_field('comp_framework', 'visible', array('id' => 1));
        // framework should not be visible
        $this->assertEquals(0, $visible);
        // also test show framework
        $competency->show_framework(1);
        $visible =  $DB->get_field('comp_framework', 'visible', array('id' => 1));
        // framework should be visible again
        $this->assertEquals(1, $visible);
        $this->resetAfterTest(true);
    }

    function test_hierarchy_framework_sortorder_offset() {
        $competency = $this->competency;
        $this->assertEquals(1002, $competency->get_framework_sortorder_offset());
        $this->resetAfterTest(true);
    }

    function test_hierarchy_move_framework() {
        global $DB;
        $competency = $this->competency;
        $f1_before =  $DB->get_field('comp_framework', 'sortorder', array('id' => 1));
        $f2_before =  $DB->get_field('comp_framework', 'sortorder', array('id' => 2));
        // a successful move should return true
        $this->assertTrue((bool)$competency->move_framework(2, true));
        $f1_after =  $DB->get_field('comp_framework', 'sortorder', array('id' => 1));
        $f2_after =  $DB->get_field('comp_framework', 'sortorder', array('id' => 2));
        // frameworks should have swapped sort orders
        $this->assertEquals($f1_before, $f2_after);
        $this->assertEquals($f2_before, $f1_after);
        // a failed move should return false
        $this->assertFalse((bool)$competency->move_framework(2, true));
        $this->resetAfterTest(true);
    }

    function test_hierarchy_delete_hierarchy_item() {
        global $DB;
        $competency = $this->competency;
        // function should return true
        $this->assertTrue((bool)$competency->delete_hierarchy_item(1, false));
        // the item should have be deleted
        $this->assertFalse((bool)$competency->get_item(1));
        // the item's children should also have been deleted
        $this->assertFalse((bool)$competency->get_items_by_parent(1));
        // custom field data for items and children should also be deleted
        $this->assertFalse((bool)$DB->get_records('comp_type_info_data', array('competencyid' => 2)));
        // non descendants in same framework should not be deleted
        $this->assertEquals(1, count($competency->get_items()));
        $this->resetAfterTest(true);
    }

    function test_hierarchy_delete_framework() {
        global $DB;
        $competency = $this->competency;
        // function should return null
        $this->assertTrue((bool)$competency->delete_framework(false));
        // items should have been deleted
        $this->assertFalse((bool)$competency->get_items());
        // types should still all exist because they are framework independant
        $this->assertEquals(3, count($competency->get_types()));
        // the framework should have been deleted
        $this->assertFalse((bool)$DB->get_records('comp_framework', array('id' => 1)));
        $this->resetAfterTest(true);
    }

    function test_hierarchy_delete_type() {
        global $DB;
        $competency = $this->competency;

        // delete all items to make deleting types possible
        $DB->delete_records('comp');

        $before = count($competency->get_types());
        // should return true if type is deleted
        $this->assertTrue((bool)$competency->delete_type(2));
        $after = count($competency->get_types());
        // should have deleted the type
        $this->assertNotEquals($before, $after);
        $this->resetAfterTest(true);
    }

    function test_hierarchy_delete_type_metadata() {
        global $DB;
        $competency = $this->competency;

        // function should return null
        $this->assertTrue((bool)$competency->delete_type_metadata(2));
        // should have deleted all fields for the type
        $this->assertFalse((bool)$DB->get_records('comp_type_info_field', array('typeid' => 2)));

        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_item_data() {
        $competency = $this->competency;
        $c1 = $this->c1;
        // should return an array of info
        $this->assertTrue((bool)is_array($competency->get_item_data($c1)));
        // if no params requested, should return default ones (includes aggregation method which
        // is specific to competencies)
        $this->assertEquals(6, count($competency->get_item_data($c1)));
        // should return the correct number of fields requested
        $this->assertEquals(4, count($competency->get_item_data($c1, array('sortthread', 'description'))));
        // should return the correct information based on fields requested
        $result = current($competency->get_item_data($c1, array('description')));
        $this->assertEquals('Description', $result['title']);
        $this->assertEquals('Competency Description 1', $result['value']);
        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_max_depth() {
        $competency = $this->competency;
        $nofwid = $this->nofwid;
        $nofwid->frameworkid = 999;
        // should return the correct maximum depth level if there are depth levels
        $this->assertEquals(2, $competency->get_max_depth());
        // should return null for framework with no depth levels
        $this->assertNull($nofwid->get_max_depth());
        $this->resetAfterTest(true);
    }

    function test_hierarchy_get_all_parents() {
        global $DB;
        $competency = $this->competency;
        $nofwid = $this->nofwid;
        // should return an array containing all items that have children
        // array should contain an item that has children
        $this->assertTrue((bool)array_key_exists(1, $competency->get_all_parents()));
        // array should not contain an item if it does not have children
        $this->assertFalse((bool)array_key_exists(2, $competency->get_all_parents()));
        // should work even if frameworkid not set
        $this->assertFalse((bool)array_key_exists(3, $nofwid->get_all_parents()));

        // clear out all items
        $DB->delete_records('comp');
        // should return an empty array if no parents found
        $this->assertEquals(array(), $competency->get_all_parents());
        $this->resetAfterTest(true);
    }

    function test_get_short_prefix(){
        $shortprefix = hierarchy::get_short_prefix('competency');
        $this->assertEquals('comp', $shortprefix);
        $this->resetAfterTest(true);
    }

    function test_reorder_hierarchy_item() {
        global $DB;
        $competency = $this->competency;

        $this->assertEquals('01.01', $DB->get_field('comp', 'sortthread', array('id' => 2)));
        $this->assertEquals('01.02', $DB->get_field('comp', 'sortthread', array('id' => 4)));
        $this->assertTrue((bool)$competency->reorder_hierarchy_item(2, 4));
        $this->assertEquals('01.02', $DB->get_field('comp', 'sortthread', array('id' => 2)));
        $this->assertEquals('01.01', $DB->get_field('comp', 'sortthread', array('id' => 4)));
        $this->resetAfterTest(true);
    }

    function test_get_extra_fields() {
        $competency = new competency();
        $position = new position();
        $organisation = new organisation();

        $this->assertEquals(array('evidencecount'), $competency->get_extrafields());
        $this->assertNull($position->get_extrafields());
        $this->assertNull($organisation->get_extrafields());

        $this->resetAfterTest(true);
    }


    function test_update_hierarchy_item() {
        global $DB;
        $competency = $DB->get_record('comp', array('id' => 2));
        $competency->fullname = 'UPDATED2';

        $this->assertEquals($competency, $this->competency->update_hierarchy_item(2, $competency));

        $afterupdate = $DB->get_records('comp', array('id' => 2));
        $this->assertEquals('UPDATED2', $afterupdate[2]->fullname);

        $this->resetAfterTest(true);
    }

    function test_move_hierarchy_item() {
        global $DB;
        $competency = $DB->get_record('comp', array('id' => 4));

        $this->assertEquals(1, $competency->parentid);
        $this->assertTrue((bool)$this->competency->move_hierarchy_item($competency, $competency->frameworkid, 5));

        $competency = $DB->get_record('comp', array('id' => 4));
        $this->assertEquals(5, $competency->parentid);

        $this->resetAfterTest(true);
    }

    function test_get_unclassified_items() {
        global $DB;
        $competency = $this->competency;
        $unclassified = $competency->get_unclassified_items();

        $this->assertEquals(2, count($unclassified));
        $this->assertEquals($DB->get_record('comp', array('id' => 4)), $unclassified[4]);
        $this->assertEquals($DB->get_record('comp', array('id' => 5)), $unclassified[5]);

        $this->resetAfterTest(true);
    }

    function test_get_item_stats() {
        $info = $this->competency->get_item_stats(1);
        $this->assertEquals('Competency 1', $info['itemname']);
        $this->assertEquals(2, $info['children']);

        $info = $this->competency->get_item_stats(3);
        $this->assertEquals('F2 Competency 1', $info['itemname']);
        $this->assertEquals(0, $info['children']);

        $this->resetAfterTest(true);
    }

    function test_get_items_excluding_children() {
        $excluded = $this->competency->get_items_excluding_children(array(1, 2, 3, 4, 5));
        $this->assertEquals(array(1, 3, 5), $excluded);

        $this->resetAfterTest(true);
    }

    function test_is_child_of() {
        global $DB;
        $competency1 = $DB->get_record('comp', array('id' => 1));
        $competency2 = $DB->get_record('comp', array('id' => 2));
        $competency4 = $DB->get_record('comp', array('id' => 4));

        $this->assertTrue((bool)$this->competency->is_child_of($competency2, 1));
        $this->assertTrue((bool)$this->competency->is_child_of($competency4, array(1, 3, 5)));
        $this->assertFalse((bool)$this->competency->is_child_of($competency2, array(3, 2, 4, 5, 6)));
        $this->assertFalse((bool)$this->competency->is_child_of($competency4, 2));
        $this->assertFalse((bool)$this->competency->is_child_of($competency1, array(0,1,2,3,4,5)));

        $this->resetAfterTest(true);
    }

    function test_get_parent_list() {
        $competency = $this->competency;

        $inctop = $competency->get_parent_list($competency->get_items(), array(), true);
        $noinctop = $competency->get_parent_list($competency->get_items(), array(), false);
        $expectedinctop =
                array('0'=>'Top',
                      '1'=>'Competency 1',
                      '2'=>'&nbsp;&nbsp;&nbsp;&nbsp;Competency 2',
                      '4'=>'&nbsp;&nbsp;&nbsp;&nbsp;Competency 3',
                      '5'=>'Competency 4');
        $this->assertEquals($expectedinctop, $inctop);
        $expectednoinctop =
                array('1'=>'Competency 1',
                      '2'=>'&nbsp;&nbsp;&nbsp;&nbsp;Competency 2',
                      '4'=>'&nbsp;&nbsp;&nbsp;&nbsp;Competency 3',
                      '5'=>'Competency 4');
        $this->assertEquals($expectednoinctop, $noinctop);

        $inctop = $competency->get_parent_list($competency->get_items(), 1, true);
        $noinctop = $competency->get_parent_list($competency->get_items(), 1, false);
        $this->assertEquals(array('0'=>'Top', '5'=>'Competency 4'), $inctop);
        $this->assertEquals(array('5'=>'Competency 4'), $noinctop);

        $this->resetAfterTest(true);
    }

    /* TODO
    function test_get_next_child_sortthread() {

    }

    function test_get_types_list() {

    }

    function test_move_sortthread() {

    }

    function test_swap_item_sortthreads(){

    }

    function test_increment_sortthread() {

    }

    function test_fix_sortthreads() {

    }

    function test_support_old_url_syntax() {

    }

    function test_add_hierarchy_item() {

    }

    function test_add_multiple_hierarchy_items() {

    }

     */

}
