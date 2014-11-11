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
 * @subpackage reportbuilder
 *
 * Unit tests for totara/reportbuilder/lib.php
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_base_content.php');
require_once($CFG->dirroot . '/totara/core/lib/scheduler.php');

class totara_reportbuilder_lib_testcase extends advanced_testcase {
    /** @var reportbuilder */
    public $rb;

    protected function setUp() {
        global $DB,$CFG;
        parent::setup();
        $this->setAdminUser();

        //create all the dummy data to put into the phpunit database tables
        $this->rb_data = new stdclass();
        $this->rb_data->id = 1;
        $this->rb_data->fullname = 'Test Report';
        $this->rb_data->shortname = 'test_report';
        $this->rb_data->source = 'competency_evidence';
        $this->rb_data->hidden = 0;
        $this->rb_data->accessmode = 0;
        $this->rb_data->contentmode = 0;
        $this->rb_data->description = '';
        $this->rb_data->recordsperpage = 40;
        $this->rb_data->defaultsortcolumn = 'user_fullname';
        $this->rb_data->defaultsortorder = 4;
        $this->rb_data->embedded = 0;

        $this->rb_col_data = Array();

        $this->rb_col_data1 = new stdClass();
        $this->rb_col_data1->id = 1;
        $this->rb_col_data1->reportid = 1;
        $this->rb_col_data1->type = 'user';
        $this->rb_col_data1->value = 'namelink';
        $this->rb_col_data1->heading = 'Participant';
        $this->rb_col_data1->sortorder = 1;
        $this->rb_col_data1->hidden = 0;
        $this->rb_col_data1->customheading = 1;
        $this->rb_col_data[] = $this->rb_col_data1;

        $this->rb_col_data2 = new stdClass();
        $this->rb_col_data2->id = 2;
        $this->rb_col_data2->reportid = 1;
        $this->rb_col_data2->type = 'competency';
        $this->rb_col_data2->value = 'competencylink';
        $this->rb_col_data2->heading = 'Competency';
        $this->rb_col_data2->sortorder = 2;
        $this->rb_col_data2->hidden = 0;
        $this->rb_col_data2->customheading = 0;
        $this->rb_col_data[] = $this->rb_col_data2;

        $this->rb_col_data3 = new stdClass();
        $this->rb_col_data3->id = 3;
        $this->rb_col_data3->reportid = 1;
        $this->rb_col_data3->type = 'user';
        $this->rb_col_data3->value = 'organisation';
        $this->rb_col_data3->heading = 'Office';
        $this->rb_col_data3->sortorder = 3;
        $this->rb_col_data3->hidden = 0;
        $this->rb_col_data3->customheading = 0;
        $this->rb_col_data[] = $this->rb_col_data3;

        $this->rb_col_data4 = new stdClass();
        $this->rb_col_data4->id = 4;
        $this->rb_col_data4->reportid = 1;
        $this->rb_col_data4->type = 'competency_evidence';
        $this->rb_col_data4->value = 'organisation';
        $this->rb_col_data4->heading = 'Completion Office';
        $this->rb_col_data4->sortorder = 4;
        $this->rb_col_data4->hidden = 0;
        $this->rb_col_data4->customheading = 0;
        $this->rb_col_data[] = $this->rb_col_data4;

        $this->rb_col_data5 = new stdClass();
        $this->rb_col_data5->id = 5;
        $this->rb_col_data5->reportid = 1;
        $this->rb_col_data5->type = 'user';
        $this->rb_col_data5->value = 'position';
        $this->rb_col_data5->heading = 'Position';
        $this->rb_col_data5->sortorder = 5;
        $this->rb_col_data5->hidden = 0;
        $this->rb_col_data5->customheading = 0;
        $this->rb_col_data[] = $this->rb_col_data5;

        $this->rb_col_data6 = new stdClass();
        $this->rb_col_data6->id = 6;
        $this->rb_col_data6->reportid = 1;
        $this->rb_col_data6->type = 'competency_evidence';
        $this->rb_col_data6->value = 'position';
        $this->rb_col_data6->heading ='Completion Position';
        $this->rb_col_data6->sortorder = 6;
        $this->rb_col_data6->hidden = 0;
        $this->rb_col_data6->customheading = 0;
        $this->rb_col_data[] = $this->rb_col_data6;

        $this->rb_col_data7 = new stdClass();
        $this->rb_col_data7->id = 7;
        $this->rb_col_data7->reportid = 1;
        $this->rb_col_data7->type = 'competency_evidence';
        $this->rb_col_data7->value = 'proficiency';
        $this->rb_col_data7->heading = 'Proficiency';
        $this->rb_col_data7->sortorder = 7;
        $this->rb_col_data7->hidden = 0;
        $this->rb_col_data7->customheading = 0;
        $this->rb_col_data[] = $this->rb_col_data7;

        $this->rb_col_data8 = new stdClass();
        $this->rb_col_data8->id = 8;
        $this->rb_col_data8->reportid = 1;
        $this->rb_col_data8->type = 'competency_evidence';
        $this->rb_col_data8->value = 'completeddate';
        $this->rb_col_data8->heading = 'Completion Date';
        $this->rb_col_data8->sortorder = 8;
        $this->rb_col_data8->hidden = 0;
        $this->rb_col_data8->customheading = 0;
        $this->rb_col_data[] = $this->rb_col_data8;

        $this->rb_filter_data = Array();

        $this->rb_filter_data1 = new stdClass();
        $this->rb_filter_data1->id = 1;
        $this->rb_filter_data1->reportid = 1;
        $this->rb_filter_data1->type = 'user';
        $this->rb_filter_data1->value = 'fullname';
        $this->rb_filter_data1->advanced = 0;
        $this->rb_filter_data1->sortorder = 1;
        $this->rb_filter_data[] = $this->rb_filter_data1;

        $this->rb_filter_data2 = new stdClass();
        $this->rb_filter_data2->id = 2;
        $this->rb_filter_data2->reportid = 1;
        $this->rb_filter_data2->type = 'user';
        $this->rb_filter_data2->value = 'organisationid';
        $this->rb_filter_data2->advanced = 0;
        $this->rb_filter_data2->sortorder = 2;
        $this->rb_filter_data[] = $this->rb_filter_data2;

        $this->rb_filter_data3 = new stdClass();
        $this->rb_filter_data3->id = 3;
        $this->rb_filter_data3->reportid = 1;
        $this->rb_filter_data3->type = 'competency_evidence';
        $this->rb_filter_data3->value = 'organisationid';
        $this->rb_filter_data3->advanced = 0;
        $this->rb_filter_data3->sortorder = 3;
        $this->rb_filter_data[] = $this->rb_filter_data3;

        $this->rb_filter_data4 = new stdClass();
        $this->rb_filter_data4->id = 4;
        $this->rb_filter_data4->reportid = 1;
        $this->rb_filter_data4->type = 'user';
        $this->rb_filter_data4->value = 'positionid';
        $this->rb_filter_data4->advanced = 0;
        $this->rb_filter_data4->sortorder = 4;
        $this->rb_filter_data[] = $this->rb_filter_data4;

        $this->rb_filter_data5 = new stdClass();
        $this->rb_filter_data5->id = 5;
        $this->rb_filter_data5->reportid = 1;
        $this->rb_filter_data5->type = 'competency_evidence';
        $this->rb_filter_data5->value = 'positionid';
        $this->rb_filter_data5->advanced = 0;
        $this->rb_filter_data5->sortorder = 5;
        $this->rb_filter_data[] = $this->rb_filter_data5;

        $this->rb_filter_data6 = new stdClass();
        $this->rb_filter_data6->id = 6;
        $this->rb_filter_data6->reportid = 1;
        $this->rb_filter_data6->type = 'competency';
        $this->rb_filter_data6->value = 'fullname';
        $this->rb_filter_data6->advanced = 0;
        $this->rb_filter_data6->sortorder = 6;
        $this->rb_filter_data[] = $this->rb_filter_data6;

        $this->rb_filter_data7 = new stdClass();
        $this->rb_filter_data7->id = 7;
        $this->rb_filter_data7->reportid = 1;
        $this->rb_filter_data7->type = 'competency_evidence';
        $this->rb_filter_data7->value = 'completeddate';
        $this->rb_filter_data7->advanced = 0;
        $this->rb_filter_data7->sortorder = 7;
        $this->rb_filter_data[] = $this->rb_filter_data7;

        $this->rb_filter_data8 = new stdClass();
        $this->rb_filter_data8->id = 8;
        $this->rb_filter_data8->reportid = 1;
        $this->rb_filter_data8->type = 'competency_evidence';
        $this->rb_filter_data8->value = 'proficiencyid';
        $this->rb_filter_data8->advanced = 0;
        $this->rb_filter_data8->sortorder = 8;
        $this->rb_filter_data[] = $this->rb_filter_data8;

        $this->rb_settings_data = array();

        $this->rb_settings_data1 = new stdClass();
        $this->rb_settings_data1->id = 1;
        $this->rb_settings_data1->reportid = 1;
        $this->rb_settings_data1->type = 'role_access';
        $this->rb_settings_data1->name = 'activeroles';
        $this->rb_settings_data1->value = '1|2';
        $this->rb_settings_data[] = $this->rb_settings_data1;

        $this->rb_settings_data2 = new stdClass();
        $this->rb_settings_data2->id = 2;
        $this->rb_settings_data2->reportid = 1;
        $this->rb_settings_data2->type = 'role_access';
        $this->rb_settings_data2->name = 'enable';
        $this->rb_settings_data2->value = 1;
        $this->rb_settings_data[] = $this->rb_settings_data2;

        $this->rb_saved_data = new stdClass();
        $this->rb_saved_data->id = 1;
        $this->rb_saved_data->reportid = 1;
        $this->rb_saved_data->userid = 2;
        $this->rb_saved_data->name = 'Saved Search';
        $this->rb_saved_data->search = 'a:1:{s:13:"user-fullname";a:1:{i:0;a:2:{s:8:"operator";i:0;s:5:"value";s:1:"a";}}}';
        $this->rb_saved_data->ispublic = 1;

        $this->role_assignments_data = new stdClass();
        $this->role_assignments_data->id = 1;
        $this->role_assignments_data->roleid = 1;
        $this->role_assignments_data->contextid = 1;
        $this->role_assignments_data->userid = 2;
        $this->role_assignments_data->hidden = 0;
        $this->role_assignments_data->timestart = 0;
        $this->role_assignments_data->timeend = 0;
        $this->role_assignments_data->timemodified = 0;
        $this->role_assignments_data->modifierid = 2;
        $this->role_assignments_data->enrol = 'manual';
        $this->role_assignments_data->sortorder = 0;

        $this->user_info_field_data = new stdClass();
        $this->user_info_field_data->id = 1;
        $this->user_info_field_data->shortname = 'datejoined';
        $this->user_info_field_data->name = 'Date Joined';
        $this->user_info_field_data->datatype = 'text';
        $this->user_info_field_data->description = '';
        $this->user_info_field_data->categoryid = 1;
        $this->user_info_field_data->sortorder = 1;
        $this->user_info_field_data->required = 0;
        $this->user_info_field_data->locked = 0;
        $this->user_info_field_data->visible = 1;
        $this->user_info_field_data->forceunique = 0;
        $this->user_info_field_data->signup = 0;
        $this->user_info_field_data->defaultdata = '';
        $this->user_info_field_data->param1 = 30;
        $this->user_info_field_data->param2 = 2048;
        $this->user_info_field_data->param3 = 0;
        $this->user_info_field_data->param4 = '';
        $this->user_info_field_data->param5 = '';

        $this->pos_type_info_field_data = new stdClass();
        $this->pos_type_info_field_data->id = 1;
        $this->pos_type_info_field_data->shortname = 'checktest';
        $this->pos_type_info_field_data->typeid = 1;
        $this->pos_type_info_field_data->datatype = 'checkbox';
        $this->pos_type_info_field_data->description = '';
        $this->pos_type_info_field_data->sortorder = 1;
        $this->pos_type_info_field_data->hidden = 0;
        $this->pos_type_info_field_data->locked = 0;
        $this->pos_type_info_field_data->required = 0;
        $this->pos_type_info_field_data->forceunique = 0;
        $this->pos_type_info_field_data->defaultdata = 0;
        $this->pos_type_info_field_data->param1 = null;
        $this->pos_type_info_field_data->param2 = null;
        $this->pos_type_info_field_data->param3 = null;
        $this->pos_type_info_field_data->param4 = null;
        $this->pos_type_info_field_data->param5 = null;
        $this->pos_type_info_field_data->fullname = 'Checkbox test';

        $this->org_type_info_field_data = new stdClass();
        $this->org_type_info_field_data->id = 1;
        $this->org_type_info_field_data->shortname = 'checktest';
        $this->org_type_info_field_data->typeid = 1;
        $this->org_type_info_field_data->datatype = 'checkbox';
        $this->org_type_info_field_data->description = '';
        $this->org_type_info_field_data->sortorder = 1;
        $this->org_type_info_field_data->hidden = 0;
        $this->org_type_info_field_data->locked = 0;
        $this->org_type_info_field_data->required = 0;
        $this->org_type_info_field_data->forceunique = 0;
        $this->org_type_info_field_data->defaultdata = 0;
        $this->org_type_info_field_data->param1 = null;
        $this->org_type_info_field_data->param2 = null;
        $this->org_type_info_field_data->param3 = null;
        $this->org_type_info_field_data->param4 = null;
        $this->org_type_info_field_data->param5 = null;
        $this->org_type_info_field_data->fullname = 'Checkbox test';

        $this->comp_type_info_field_data = new stdClass();
        $this->comp_type_info_field_data->id = 1;
        $this->comp_type_info_field_data->shortname = 'checktest';
        $this->comp_type_info_field_data->typeid = 1;
        $this->comp_type_info_field_data->datatype = 'checkbox';
        $this->comp_type_info_field_data->description = '';
        $this->comp_type_info_field_data->sortorder = 1;
        $this->comp_type_info_field_data->hidden = 0;
        $this->comp_type_info_field_data->locked = 0;
        $this->comp_type_info_field_data->required = 0;
        $this->comp_type_info_field_data->forceunique = 0;
        $this->comp_type_info_field_data->defaultdata = 0;
        $this->comp_type_info_field_data->param1 = null;
        $this->comp_type_info_field_data->param2 = null;
        $this->comp_type_info_field_data->param3 = null;
        $this->comp_type_info_field_data->param4 = null;
        $this->comp_type_info_field_data->param5 = null;
        $this->comp_type_info_field_data->fullname = 'Checkbox test';

        $this->comp_data = array();

        $this->comp_data1 = new stdClass();
        $this->comp_data1->id = 1;
        $this->comp_data1->fullname = 'Competency 1';
        $this->comp_data1->shortname =  'Comp 1';
        $this->comp_data1->description = 'Competency Description 1';
        $this->comp_data1->idnumber = 'C1';
        $this->comp_data1->frameworkid = 1;
        $this->comp_data1->path = '/1';
        $this->comp_data1->depthlevel = 1;
        $this->comp_data1->parentid = 0;
        $this->comp_data1->sortthread = '01';
        $this->comp_data1->visible = 1;
        $this->comp_data1->aggregationmethod = 1;
        $this->comp_data1->proficiencyexpected = 1;
        $this->comp_data1->evidencecount = 0;
        $this->comp_data1->timecreated = 1265963591;
        $this->comp_data1->timemodified = 1265963591;
        $this->comp_data1->usermodified = 2;
        $this->comp_data[] = $this->comp_data1;

        $this->comp_data2 = new stdClass();
        $this->comp_data2->id = 2;
        $this->comp_data2->fullname = 'Competency 2';
        $this->comp_data2->shortname = 'Comp 2';
        $this->comp_data2->description = 'Competency Description 2';
        $this->comp_data2->idnumber = 'C2';
        $this->comp_data2->frameworkid = 1;
        $this->comp_data2->path = '/1/2';
        $this->comp_data2->depthlevel = 2;
        $this->comp_data2->parentid = 1;
        $this->comp_data2->sortthread = '01.01';
        $this->comp_data2->visible = 1;
        $this->comp_data2->aggregationmethod = 1;
        $this->comp_data2->proficiencyexpected = 1;
        $this->comp_data2->evidencecount = 0;
        $this->comp_data2->timecreated = 1265963591;
        $this->comp_data2->timemodified = 1265963591;
        $this->comp_data2->usermodified = 2;
        $this->comp_data[] = $this->comp_data2;

        $this->comp_data3 = new stdClass();
        $this->comp_data3->id = 3;
        $this->comp_data3->fullname = 'F2 Competency 1';
        $this->comp_data3->shortname = 'F2 Comp 1';
        $this->comp_data3->description = 'F2 Competency Description 1';
        $this->comp_data3->idnumber = 'F2 C1';
        $this->comp_data3->frameworkid = 2;
        $this->comp_data3->path = '/3';
        $this->comp_data3->depthlevel = 1;
        $this->comp_data3->parentid = 0;
        $this->comp_data3->sortthread = '01';
        $this->comp_data3->visible = 1;
        $this->comp_data3->aggregationmethod = 1;
        $this->comp_data3->proficiencyexpected = 1;
        $this->comp_data3->evidencecount = 0;
        $this->comp_data3->timecreated = 1265963591;
        $this->comp_data3->timemodified = 1265963591;
        $this->comp_data3->usermodified = 2;
        $this->comp_data[] = $this->comp_data3;

        $this->comp_data4 = new stdClass();
        $this->comp_data4->id = 4;
        $this->comp_data4->fullname = 'Competency 3';
        $this->comp_data4->shortname = 'Comp 3';
        $this->comp_data4->description = 'Competency Description 3';
        $this->comp_data4->idnumber = 'C3';
        $this->comp_data4->frameworkid = 1;
        $this->comp_data4->path = '/1/4';
        $this->comp_data4->depthlevel = 2;
        $this->comp_data4->parentid = 1;
        $this->comp_data4->sortthread = '01.02';
        $this->comp_data4->visible = 1;
        $this->comp_data4->aggregationmethod = 1;
        $this->comp_data4->proficiencyexpected = 1;
        $this->comp_data4->evidencecount = 0;
        $this->comp_data4->timecreated = 1265963591;
        $this->comp_data4->timemodified = 1265963591;
        $this->comp_data4->usermodified = 2;
        $this->comp_data[] = $this->comp_data4;

        $this->comp_data5 = new stdClass();
        $this->comp_data5->id = 5;
        $this->comp_data5->fullname = 'Competency 4';
        $this->comp_data5->shortname = 'Comp 4';
        $this->comp_data5->description = 'Competency Description 4';
        $this->comp_data5->idnumber = 'C4';
        $this->comp_data5->frameworkid = 1;
        $this->comp_data5->path = '/5';
        $this->comp_data5->depthlevel = 1;
        $this->comp_data5->parentid = 0;
        $this->comp_data5->sortthread = '02';
        $this->comp_data5->visible = 1;
        $this->comp_data5->aggregationmethod = 1;
        $this->comp_data5->proficiencyexpected = 1;
        $this->comp_data5->evidencecount = 0;
        $this->comp_data5->timecreated = 1265963591;
        $this->comp_data5->timemodified = 1265963591;
        $this->comp_data5->usermodified = 2;
        $this->comp_data[] = $this->comp_data5;

        $this->org_data = new stdClass();
        $this->org_data->id = 1;
        $this->org_data->fullname = 'Distric Office';
        $this->org_data->shortname = 'DO';
        $this->org_data->description = '';
        $this->org_data->idnumber = '';
        $this->org_data->frameworkid = 1;
        $this->org_data->path = '/1';
        $this->org_data->depthlevel = 1;
        $this->org_data->parentid = 0;
        $this->org_data->sortthread = '01';
        $this->org_data->visible = 1;
        $this->org_data->timecreated = 0;
        $this->org_data->timemodified = 0;
        $this->org_data->usermodified = 2;

        $this->pos_data = new stdClass();
        $this->pos_data->id = 1;
        $this->pos_data->fullname = 'Data Analyst';
        $this->pos_data->shortname = 'Data Analyst';
        $this->pos_data->idnumber = '';
        $this->pos_data->description = '';
        $this->pos_data->frameworkid = 1;
        $this->pos_data->path = '/1';
        $this->pos_data->depthlevel = 1;
        $this->pos_data->parentid = 0;
        $this->pos_data->sortthread = '01';
        $this->pos_data->visible = 1;
        $this->pos_data->timevalidfrom = 0;
        $this->pos_data->timevalidto = 0;
        $this->pos_data->timecreated = 0;
        $this->pos_data->timemodified = 0;
        $this->pos_data->usermodified = 2;

        $this->comp_scale_values_data = array();

        $this->comp_scales1 = new stdClass();
        $this->comp_scales1->id = 1;
        $this->comp_scales1->name = 'Competent';
        $this->comp_scales1->idnumber = '';
        $this->comp_scales1->description = '';
        $this->comp_scales1->scaleid = 1;
        $this->comp_scales1->numericscore = '';
        $this->comp_scales1->sortorder = 1;
        $this->comp_scales1->timemodified = 0;
        $this->comp_scales1->usermodified = 2;
        $this->comp_scales1->proficient = 1;
        $this->comp_scales_values_data[] = $this->comp_scales1;

        $this->comp_scales2 = new stdClass();
        $this->comp_scales2->id  = 2;
        $this->comp_scales2->name = 'Partially Competent';
        $this->comp_scales2->idnumber = '';
        $this->comp_scales2->description = '';
        $this->comp_scales2->scaleid = 1;
        $this->comp_scales2->numericscore = '';
        $this->comp_scales2->sortorder = 2;
        $this->comp_scales2->timemodified = 0;
        $this->comp_scales2->usermodified = 2;
        $this->comp_scales2->proficient = 0;
        $this->comp_scales_values_data[] = $this->comp_scales2;

        $this->comp_scales3 = new stdClass();
        $this->comp_scales3->id = 3;
        $this->comp_scales3->name = 'Not Competent';
        $this->comp_scales3->idnumber = '';
        $this->comp_scales3->description = '';
        $this->comp_scales3->scaleid = 1;
        $this->comp_scales3->numericscore = '';
        $this->comp_scales3->sortorder = 3;
        $this->comp_scales3->timemodified = 0;
        $this->comp_scales3->usermodified = 2;
        $this->comp_scales3->proficient = 0;
        $this->comp_scales_values_data[] = $this->comp_scales3;

        $this->comp_evidence_data = new stdClass();
        $this->comp_evidence_data->id = 1;
        $this->comp_evidence_data->userid = 2;
        $this->comp_evidence_data->competencyid = 1;
        $this->comp_evidence_data->positionid = 1;
        $this->comp_evidence_data->organisationid = 1;
        $this->comp_evidence_data->assessorid = 1;
        $this->comp_evidence_data->assessorname = 'Assessor';
        $this->comp_evidence_data->assessmenttype = '';
        $this->comp_evidence_data->proficiency = 1;
        $this->comp_evidence_data->timecreated = 1100775600;
        $this->comp_evidence_data->timemodified = 1100775600;
        $this->comp_evidence_data->reaggregate = 0;
        $this->comp_evidence_data->manual = 1;

        $this->pos_assignment_data = new stdClass();
        $this->pos_assignment_data->id = 1;
        $this->pos_assignment_data->fullname = 'Title';
        $this->pos_assignment_data->shortname = 'Title';
        $this->pos_assignment_data->organisationid = 1;
        $this->pos_assignment_data->positionid = 1;
        $this->pos_assignment_data->userid = 2;
        $this->pos_assignment_data->type = 1;
        $this->pos_assignment_data->timecreated = 1;
        $this->pos_assignment_data->timemodified = 1;
        $this->pos_assignment_data->usermodified = 1;

        $this->tag_data = new stdClass();
        $this->tag_data->id = 1;
        $this->tag_data->userid = 2;
        $this->tag_data->name = 'test';
        $this->tag_data->rawname = 'test';
        $this->tag_data->tagtype = 'official';

        //insert the dummy data into the phpunit database
        $DB->insert_record('report_builder', $this->rb_data);
        $DB->insert_records_via_batch('report_builder_columns', $this->rb_col_data);
        $DB->insert_records_via_batch('report_builder_filters', $this->rb_filter_data);
        $DB->insert_records_via_batch('report_builder_settings', $this->rb_settings_data);
        $DB->insert_record('report_builder_saved', $this->rb_saved_data);
        $DB->insert_record('user_info_field', $this->user_info_field_data);
        $DB->insert_record('pos_type_info_field', $this->pos_type_info_field_data);
        $DB->insert_record('org_type_info_field', $this->org_type_info_field_data);
        $DB->insert_record('comp_type_info_field', $this->comp_type_info_field_data);
        $DB->insert_records_via_batch('comp', $this->comp_data);
        $DB->insert_record('org', $this->org_data);
        $DB->insert_record('pos', $this->pos_data);
        $DB->insert_records_via_batch('comp_scale_values', $this->comp_scale_values_data);
        $DB->insert_record('comp_record', $this->comp_evidence_data);
        $DB->insert_record('role_assignments', $this->role_assignments_data);
        $DB->insert_record('pos_assignment', $this->pos_assignment_data);
        $DB->insert_record('tag', $this->tag_data);

        $data = array(
            // show report for a specific user
            'userid' => 2,
        );
        $this->embed = reportbuilder_get_embedded_report_object('plan_competencies', $data);
        $this->shortname = 'plan_competencies';

        // db version of report
        $this->rb = new reportbuilder(1);
        $this->resetAfterTest(true);
    }

    function test_reportbuilder_initialize_db_instance() {
        $rb = $this->rb;
        // should create report builder object with the correct properties
        $this->assertEquals('Test Report', $rb->fullname);
        $this->assertEquals('test_report', $rb->shortname);
        $this->assertEquals('competency_evidence', $rb->source);
        $this->assertEquals(0, $rb->hidden);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_initialize_embedded_instance() {
        $rb = new reportbuilder(null, $this->shortname, $this->embed);
        // should create embedded report builder object with the correct properties
        $this->assertEquals('Record of Learning: Competencies', $rb->fullname);
        $this->assertEquals('plan_competencies', $rb->shortname);
        $this->assertEquals('dp_competency', $rb->source);
        $this->assertEquals(1, $rb->hidden);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_restore_saved_search() {
        global $SESSION, $USER, $DB;
        $rb = new reportbuilder(1, null, null, 1);

        // ensure that saved search belongs to current user
        $todb = new stdclass();
        $todb->id = 1;
        $todb->userid = $USER->id;
        $DB->update_record('report_builder_saved', $todb);

        // should be able to restore a saved search
        $this->assertTrue((bool)$rb->restore_saved_search());
        // the correct SESSION var should now be set
        // the SESSION var should be set to the value specified by the saved search
        $this->assertEquals(array('user-fullname' => array(0 => array('operator' => 0, 'value' => 'a'))),
                $SESSION->reportbuilder[1]);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_get_filters() {
        $rb = $this->rb;
        $filters = $rb->get_filters();

        // should return the current filters for this report
        $this->assertTrue((bool)is_array($filters));
        $this->assertEquals(8, count($filters));
        $this->assertEquals('user', current($filters)->type);
        $this->assertEquals('fullname', current($filters)->value);
        $this->assertEquals('0', current($filters)->advanced);
        $this->assertEquals('User\'s Fullname contains "content"',
                current($filters)->get_label(array('operator' => 0, 'value' => 'content')));
        $this->assertEquals('User\'s Fullname doesn\'t contain "nocontent"',
                current($filters)->get_label(array('operator' => 1, 'value' => 'nocontent')));
        $this->assertEquals('User\'s Fullname is equal to "fullname"',
                current($filters)->get_label(array('operator' => 2, 'value' => 'fullname')));
        $this->assertEquals('User\'s Fullname starts with "start"',
                current($filters)->get_label(array('operator' => 3, 'value' => 'start')));
        $this->assertEquals('User\'s Fullname ends with "end"',
                current($filters)->get_label(array('operator' => 4, 'value' => 'end')));
        $this->assertEquals('User\'s Fullname is empty',
                current($filters)->get_label(array('operator' => 5, 'value' => '')));

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_get_columns() {
        global $DB;

        $rb = $this->rb;
        $columns = $rb->get_columns();
        // should return the current columns for this report
        $this->assertTrue((bool)is_array($columns));
        $this->assertEquals(8, count($columns));
        $this->assertEquals('user', current($columns)->type);
        $this->assertEquals('namelink', current($columns)->value);
        $this->assertEquals('Participant', current($columns)->heading);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_create_embedded_record() {
        global $DB;

        $rb = new reportbuilder(null, $this->shortname, $this->embed);
        // should create a db record for the embedded report
        $this->assertTrue((bool)$record = $DB->get_records('report_builder', array('shortname' => $this->shortname)));
        // there should be db records in the columns table
        $this->assertTrue((bool)$DB->get_records('report_builder_columns', array('reportid' => $record[2]->id)));

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_create_shortname() {
        $shortname1 = reportbuilder::create_shortname('name');
        $shortname2 = reportbuilder::create_shortname('My Report with special chars\'"%$*[]}~');
        $shortname3 = reportbuilder::create_shortname('Space here');
        // should prepend 'report_' to name
        $this->assertEquals('report_name', $shortname1);
        // special chars should be stripped
        $this->assertEquals('report_my_report_with_special_chars', $shortname2);
        // spaces should be replaced with underscores and upper case moved to lower case
        $this->assertEquals('report_space_here', $shortname3);
        // create a db entry
        $rb = new reportbuilder(null, $shortname3, $this->embed);
        $existingname = reportbuilder::create_shortname('space_here');
        // should append numbers to suggestion if shortname already exists
        $this->assertEquals('report_space_here1', $existingname);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_report_url() {
        global $CFG;
        $rb = $this->rb;
        // a normal report should return the report.php url
        $this->assertEquals('/totara/reportbuilder/report.php?id=1', substr($rb->report_url(), strlen($CFG->wwwroot)));
        $rb2 = new reportbuilder(null, $this->shortname, $this->embed);
        // an embedded report should return the embedded url (this page)
        $this->assertEquals($CFG->wwwroot . '/totara/plan/record/competencies.php', $rb2->report_url());

        $this->resetAfterTest(true);
    }


    // not tested as difficult to do in a useful way
    // get_current_url() not tested
    // leaving get_current_admin_options() until after changes to capabilities
    function test_reportbuilder_get_current_params() {
        $rb = new reportbuilder(null, $this->shortname, $this->embed);
        $paramoption = new stdClass();
        $paramoption->name = 'userid';
        $paramoption->field = 'base.userid';
        $paramoption->joins = '';
        $paramoption->type = 'int';
        $param = new rb_param('userid',array($paramoption));
        $param->value = 2;
        // should return the expected embedded param
        $this->assertEquals(array($param), $rb->get_current_params());

        $this->resetAfterTest(true);
    }


    // display_search() and get_sql_filter() not tested as they print output directly to screen
    function test_reportbuilder_is_capable() {
        global $USER, $DB;

        $rb = $this->rb;

        // should return true if accessmode is zero
        $this->assertTrue((bool)$rb->is_capable(1));
        $todb = new stdClass();
        $todb->id = 1;
        $todb->accessmode = REPORT_BUILDER_CONTENT_MODE_ANY;
        $DB->update_record('report_builder',$todb);
        // should return true if accessmode is 1 and admin an allowed role
        $this->assertTrue((bool)$rb->is_capable(1, 2));
        // should return false if access mode is 1 and admin not an allowed role
        $DB->delete_records('report_builder_settings', array('reportid' => 1));
        $this->assertFalse((bool)$rb->is_capable(1));
        $todb = new stdClass();
        $todb->reportid = 1;
        $todb->type = 'role_access';
        $todb->name = 'activeroles';
        $todb->value = 1;
        $DB->insert_record('report_builder_settings',$todb);
        $todb = new stdClass();
        $todb->reportid = 1;
        $todb->type = 'role_access';
        $todb->name = 'enable';
        $todb->value = '1';
        $DB->insert_record('report_builder_settings', $todb);
        // should return true if accessmode is 1 and admin is only allowed role
        $this->assertTrue((bool)$rb->is_capable(1, 2));

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_get_param_restrictions() {
        $rb = new reportbuilder(null, $this->shortname, $this->embed);
        // should return the correct SQL fragment if a parameter restriction is set
        $restrictions = $rb->get_param_restrictions();
        $this->assertRegExp('(base.userid\s+=\s+:[a-z0-9]+)', $restrictions[0]);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_get_content_restrictions() {
        global $DB;

        $rb = $this->rb;

        // should return ( 1=1 ) if content mode = 0
        $restrictions = $rb->get_content_restrictions();
        $this->assertEquals('( 1=1 )', $restrictions[0]);
        $todb = new stdClass();
        $todb->id = 1;
        $todb->contentmode = REPORT_BUILDER_CONTENT_MODE_ANY;
        $DB->update_record('report_builder', $todb);
        $rb = new reportbuilder(1);
        // should return (1=0) if content mode = 1 but no restrictions set
        // using 1=0 instead of FALSE for MSSQL support
        $restrictions = $rb->get_content_restrictions();
        $this->assertEquals('(1=0)', $restrictions[0]);
        $todb = new stdClass();
        $todb->reportid = 1;
        $todb->type = 'date_content';
        $todb->name = 'enable';
        $todb->value = 1;
        $DB->insert_record('report_builder_settings', $todb);
        $todb->name = 'when';
        $todb->value = 'future';
        $DB->insert_record('report_builder_settings', $todb);
        $todb->type = 'user_content';
        $todb->name = 'enable';
        $todb->value = 1;
        $DB->insert_record('report_builder_settings', $todb);
        $todb->name = 'who';
        $todb->value = rb_user_content::USER_OWN;
        $DB->insert_record('report_builder_settings', $todb);
        $rb = new reportbuilder(1);
        $restrictions = $rb->get_content_restrictions();
        // should return the appropriate SQL snippet to OR the restrictions if content mode = 1
        $this->assertRegExp('/\(base\.userid\s+=\s+:[a-z0-9]+\s+OR\s+\(base\.timemodified\s+>\s+[0-9]+\s+AND\s+base\.timemodified\s+!=\s+0\s+\)\)/', $restrictions[0]);
        $todb = new stdClass();
        $todb->id = 1;
        $todb->contentmode = REPORT_BUILDER_CONTENT_MODE_ALL;
        $DB->update_record('report_builder', $todb);
        $rb = new reportbuilder(1);
        $restrictions = $rb->get_content_restrictions();
        // should return the appropriate SQL snippet to AND the restrictions if content mode = 2
        $this->assertRegExp('/\(base\.userid\s+=\s+:[a-z0-9]+\s+AND\s+\(base\.timemodified\s+>\s+[0-9]+\s+AND\s+base\.timemodified\s+!=\s+0\s+\)\)/', $restrictions[0]);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_get_restriction_descriptions() {
        global $USER, $DB;

        $rb = $this->rb;
        // should return empty array if content mode = 0
        $this->assertEquals(array(), $rb->get_restriction_descriptions('content'));
        $todb = new stdClass();
        $todb->id = 1;
        $todb->contentmode = REPORT_BUILDER_CONTENT_MODE_ANY;
        $DB->update_record('report_builder', $todb);
        $rb = new reportbuilder(1);
        // should return an array with empty string if content mode = 1 but no restrictions set
        $this->assertEquals(array(''), $rb->get_restriction_descriptions('content'));
        $todb = new stdClass();
        $todb->reportid = 1;
        $todb->type = 'date_content';
        $todb->name = 'enable';
        $todb->value = 1;
        $DB->insert_record('report_builder_settings', $todb);
        $todb->name = 'when';
        $todb->value = 'future';
        $DB->insert_record('report_builder_settings', $todb);
        $todb->type = 'user_content';
        $todb->name = 'enable';
        $todb->value = 1;
        $DB->insert_record('report_builder_settings', $todb);
        $todb->name = 'who';
        $todb->value = rb_user_content::USER_OWN;
        $DB->insert_record('report_builder_settings', $todb);
        $rb = new reportbuilder(1);
        // should return the appropriate text description if content mode = 1
        $this->assertRegExp('/The user is ".*" or The completion date occurred after .*/', current($rb->get_restriction_descriptions('content')));
        $todb = new stdClass();
        $todb->id = 1;
        $todb->contentmode = REPORT_BUILDER_CONTENT_MODE_ALL;
        $DB->update_record('report_builder', $todb);
        $rb = new reportbuilder(1);
        // should return the appropriate array of text descriptions if content mode = 2
        $restrictions = $rb->get_restriction_descriptions('content');
        $firstrestriction = current($restrictions);
        $secondrestriction = next($restrictions);
        $this->assertRegExp('/^The user is ".*"$/', $firstrestriction);
        $this->assertRegExp('/^The completion date occurred after/', $secondrestriction);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_get_column_fields() {
        $rb = $this->rb;
        $columns = $rb->get_column_fields();
        // should return an array
        $this->assertTrue((bool)is_array($columns));
        // the array should contain the correct number of columns
        $this->assertEquals(10, count($columns));
        // the strings should have the correct format
        // can't check exactly because different dbs use different concat format
        $this->assertRegExp('/auser\.firstname/', current($columns));
        $this->assertRegExp('/auser\.lastname/', current($columns));
        $this->assertRegExp('/user_namelink/', current($columns));

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_get_joins() {
        global $CFG;
        $rb = $this->rb;
        $obj1 = new stdClass();
        $obj1->joins = array('auser','competency');
        $obj2 = new stdClass();
        $obj2->joins = 'position';
        $columns = $rb->get_joins($obj1, 'test');
        // should return an array
        $this->assertTrue((bool)is_array($columns));
        // the array should contain the correct number of columns
        $this->assertEquals(2, count($columns));
        $userjoin = new rb_join(
            'auser',
            'LEFT',
            '{user}',
            'auser.id = base.userid',
            1,
            'base'
        );
        // the strings should have the correct format
        $this->assertEquals($userjoin, current($columns));
        // should also work with string instead of array
        $columns2 = $rb->get_joins($obj2, 'test');
        $this->assertTrue((bool)is_array($columns2));
        // the array should contain the correct number of columns
        $this->assertEquals(2, count($columns2));
        $posjoin = new rb_join(
            'position',
            'LEFT',
            '{pos}',
            'position.id = position_assignment.positionid',
            1,
            'position_assignment'
        );
        // the strings should have the correct format
        $this->assertEquals($posjoin, current($columns2));

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_get_content_joins() {
        $rb = $this->rb;
        // should return an empty array if content mode = 0
        $this->assertEquals(array(), $rb->get_content_joins());
        // TODO test other options
        // can't do with competency evidence as no joins required

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_get_column_joins() {
        global $CFG;
        $rb = $this->rb;
        $columns = $rb->get_column_joins();
        // should return an array
        $this->assertTrue((bool)is_array($columns));
        // the array should contain the correct number of columns
        $this->assertEquals(9, count($columns));
        $userjoin = new rb_join(
            'auser',
            'LEFT',
            '{user}',
            'auser.id = base.userid',
            1,
            'base'
        );
        // the strings should have the correct format
        $this->assertEquals($userjoin, current($columns));

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_get_filter_joins() {
        global $CFG,$SESSION;
        $rb = $this->rb;
        // set a filter session var
        $SESSION->reportbuilder[1] = array('user-fullname' => 'unused', 'user-positionid' => 'unused');
        $columns = $rb->get_filter_joins();
        // should return an array
        $this->assertTrue((bool)is_array($columns));
        // the array should contain the correct number of columns
        $this->assertEquals(2, count($columns));

        $userjoin = new rb_join(
            'auser',
            'LEFT',
            '{user}',
            'auser.id = base.userid',
            1,
            'base'
        );
        // the strings should have the correct format
        $this->assertEquals($userjoin, current($columns));
        unset($SESSION->reportbuilder[1]);

        $this->resetAfterTest(true);
    }

    /*
    function test_reportbuilder_sort_join() {
        $rb = $this->rb;
        // should return the correct values for valid joins
        $this->assertEquals(-1, $rb->sort_join('user','position_assignment'));
        $this->assertEquals(1, $rb->sort_join('position_assignment','user'));
        $this->assertEquals(0, $rb->sort_join('user','user'));
        // should throw errors if invalid keys provided
        $this->expectError('Missing array key in sort_join(). Add \'junk\' to order array.');
        $this->assertEquals(-1, $rb->sort_join('user', 'junk'));
        $this->expectError('Missing array key in sort_join(). Add \'junk\' to order array.');
        $this->assertEquals(1, $rb->sort_join('junk', 'user'));
        $this->expectError('Missing array keys in sort_join(). Add \'junk\' and \'junk2\' to order array.');
        $this->assertEquals(0, $rb->sort_join('junk', 'junk2'));
    }
     */

    function test_reportbuilder_build_query() {
        global $SESSION;
        $filtername = 'filtering_test_report';
        // create a complex set of filtering criteria
        $SESSION->$filtername = array(
            'user-fullname' => array(
                array(
                    'operator' => 0,
                    'value' => 'John',
                )
            ),
            'user-organisationpath' => array(
                array(
                    'operator' => 1,
                    'value' => '21',
                    'recursive' => 1,
                )
            ),
            'competency-fullname' => array(
                array(
                    'operator' => 0,
                    'value' => 'fire',
                )
            ),
            'competency_evidence-completeddate' => array(
                array(
                    'after' => 0,
                    'before' => 1271764800,
                )
            ),
            'competency_evidence-proficiencyid' => array(
                array(
                    'operator' => 1,
                    'value' => '3',
                )
            ),
        );
        $rb = $this->rb;
        $sql_count_filtered = $rb->build_query(true, true);
        $sql_count_unfiltered = $rb->build_query(true, false);
        $sql_query_filtered = $rb->build_query(false, true);
        $sql_query_unfiltered = $rb->build_query(false, false);
        // if counting records, the SQL should include the string "count(*)"
        $this->assertRegExp('/count\(\*\)/i', $sql_count_filtered[0]);
        $this->assertRegExp('/count\(\*\)/i', $sql_count_unfiltered[0]);
        // if not counting records, the SQL should not include the string "count(*)"
        $this->assertNotRegExp('/count\(\*\)/i', $sql_query_filtered[0]);
        $this->assertNotRegExp('/count\(\*\)/i', $sql_query_unfiltered[0]);
        // if not filtered, the SQL should include the string "where (1=1) " with no other clauses
        $this->assertRegExp('/where \(\s+1=1\s+\)\s*/i', $sql_count_unfiltered[0]);
        $this->assertRegExp('/where \(\s+1=1\s+\)\s*/i', $sql_query_unfiltered[0]);
        // hard to do further testing as no actual data or tables exist

        // delete complex query from session
        unset($SESSION->$filtername);

        $this->resetAfterTest(true);
    }

    // can't test the following functions as data and tables don't exist
    // get_full_count()
    // get_filtered_count()
    // export_data()
    // display_table()
    // fetch_data()
    // add_admin_columns()


    function test_reportbuilder_check_sort_keys() {
        global $SESSION;
        // set a bad sortorder key
        $SESSION->flextable['test_report']->sortby['bad_key'] = 4;
        $before = count($SESSION->flextable['test_report']->sortby);
        $rb = $this->rb;
        // run the function
        $rb->check_sort_keys();
        $after = count($SESSION->flextable['test_report']->sortby);
        // the bad sort key should have been deleted
        $this->assertEquals(1, $before - $after);

        $this->resetAfterTest(true);
    }

    // skipping tests for the following as they just print HTML
    // export_select()
    // view_button()
    // save_button()
    // saved_menu()
    // edit_button()

    // skipping tests for the following as they output files
    // download_ods()
    // download_csv()
    // download_xls()


    function test_reportbuilder_get_content_options() {
        $rb = $this->rb;
        $contentoptions = $rb->get_content_options();
        // should return an array of content options
        $this->assertTrue((bool)is_array($contentoptions));
        // should have the appropriate format
        $this->assertEquals('current_pos', current($contentoptions));

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_get_filters_select() {
        $rb = $this->rb;
        $options = $rb->get_filters_select();
        // should return an array
        $this->assertTrue((bool)is_array($options));
        // the strings should have the correct format
        $this->assertEquals("User's Fullname", $options['User']['user-fullname']);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_get_columns_select() {
        $rb = $this->rb;
        $options = $rb->get_columns_select();
        // should return an array
        $this->assertTrue((bool)is_array($options));
        // the strings should have the correct format
        $this->assertEquals("User's Fullname", $options['User']['user-fullname']);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_delete_column() {
        $rb = $this->rb;
        $before = count($rb->columns);
        $rb->delete_column(999);
        $afterfail = count($rb->columns);
        // should not delete column if cid doesn't match
        $this->assertEquals($before, $afterfail);
        // should return true if successful
        $this->assertTrue((bool)$rb->delete_column(4));
        $after = count($rb->columns);
        // should be one less column after successful delete operation
        $this->assertEquals($before - 1, $after);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_delete_filter() {
        $rb = $this->rb;
        $before = count($rb->filters);
        $rb->delete_filter(999);
        $afterfail = count($rb->filters);
        // should not delete filter if fid doesn't match
        $this->assertEquals($before, $afterfail);
        // should return true if successful
        $this->assertTrue((bool)$rb->delete_filter(4));
        $after = count($rb->filters);
        // should be one less filter after successful delete operation
        $this->assertEquals($before - 1, $after);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_move_column() {
        $rb = $this->rb;
        reset($rb->columns);
        $firstbefore = current($rb->columns);
        $secondbefore = next($rb->columns);
        $thirdbefore = next($rb->columns);
        // should not be able to move first column up
        $this->assertFalse((bool)$rb->move_column(1, 'up'));
        reset($rb->columns);
        $firstafter = current($rb->columns);
        $secondafter = next($rb->columns);
        $thirdafter = next($rb->columns);
        // columns should not change if trying to do a bad column move
        $this->assertEquals($firstbefore, $firstafter);
        $this->assertEquals($secondbefore, $secondafter);
        // should be able to move first column down
        $this->assertTrue((bool)$rb->move_column(1, 'down'));
        reset($rb->columns);
        $firstafter = current($rb->columns);
        $secondafter = next($rb->columns);
        $thirdafter = next($rb->columns);
        // columns should change if move is valid
        $this->assertNotEquals($firstbefore, $firstafter);
        // moved columns should have swapped
        $this->assertEquals($firstbefore, $secondafter);
        $this->assertEquals($secondbefore, $firstafter);
        // unmoved columns should stay the same
        $this->assertEquals($thirdbefore, $thirdafter);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_move_filter() {
        $rb = $this->rb;
        reset($rb->filters);
        $firstbefore = current($rb->filters);
        $secondbefore = next($rb->filters);
        $thirdbefore = next($rb->filters);
        // should not be able to move first filter up
        $this->assertFalse((bool)$rb->move_filter(1, 'up'));
        reset($rb->filters);
        $firstafter = current($rb->filters);
        $secondafter = next($rb->filters);
        $thirdafter = next($rb->filters);
        // filters should not change if trying to do a bad filter move
        $this->assertEquals($firstbefore, $firstafter);
        $this->assertEquals($secondbefore, $secondafter);
        // should be able to move first filter down
        $this->assertTrue((bool)$rb->move_filter(1, 'down'));
        reset($rb->filters);
        $firstafter = current($rb->filters);
        $secondafter = next($rb->filters);
        $thirdafter = next($rb->filters);
        // filters should change if move is valid
        $this->assertNotEquals($firstbefore, $firstafter);
        // moved filters should have swapped
        $this->assertEquals($firstbefore, $secondafter);
        $this->assertEquals($secondbefore, $firstafter);
        // unmoved filters should stay the same
        $this->assertEquals($thirdbefore, $thirdafter);

        $this->resetAfterTest(true);
    }

    function test_reportbuilder_create_attachment() {
        global $CFG;

        $sched = new stdClass();
        $sched->id = 1;
        $sched->reportid = 1;
        $sched->format = 1;
        $sched->exporttofilesystem = 0;
        $sched->savedsearchid = 0;

        $filename = reportbuilder_create_attachment($sched, 2);
        $this->assertTrue((bool)file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . $filename));
        unlink($CFG->dataroot . DIRECTORY_SEPARATOR . $filename);
        unset($sched);

        $sched = new stdClass();
        $sched->id = 2;
        $sched->reportid = 1;
        $sched->format = 2;
        $sched->exporttofilesystem = 0;
        $sched->savedsearchid = 0;

        $filename = reportbuilder_create_attachment($sched, 2); // format 2
        $this->assertTrue((bool)file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . $filename));
        unlink($CFG->dataroot . DIRECTORY_SEPARATOR . $filename);
        unset($sched);

        $sched = new stdClass();
        $sched->id = 3;
        $sched->reportid = 1;
        $sched->format = 4;
        $sched->exporttofilesystem = 0;
        $sched->savedsearchid = 0;

        $filename = reportbuilder_create_attachment($sched, 2); // format 4
        $this->assertTrue((bool)file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . $filename));
        unlink($CFG->dataroot . DIRECTORY_SEPARATOR . $filename);
        unset($sched);

        $this->resetAfterTest(true);
    }

    public function test_get_search_columns() {
        global $DB;
        // Add two reports.
        $rb2data = array(array('id' => 2, 'fullname' => 'Courses', 'shortname' => 'mycourses',
                         'source' => 'courses', 'hidden' => 1, 'embedded' => 1),
                         array('id' => 3, 'fullname' => 'Courses2', 'shortname' => 'mycourses2',
                         'source' => 'courses', 'hidden' => 1, 'embedded' => 1));

        $rbsearchcolsdata = array(
                        array('id' => 100, 'reportid' => 1, 'type' => 'course', 'value' => 'fullname',
                              'heading' => 'A', 'sortorder' => 1),
                        array('id' => 101, 'reportid' => 1, 'type' => 'course', 'value' => 'summary',
                              'heading' => 'B', 'sortorder' => 2),
                        array('id' => 102, 'reportid' => 2, 'type' => 'course', 'value' => 'fullname',
                              'heading' => 'C', 'sortorder' => 1));

        // Add search columns to two reports.
        $this->loadDataSet($this->createArrayDataSet(array(
            'report_builder' => $rb2data,
            'report_builder_search_cols' => $rbsearchcolsdata)));

        // Test result for reports with/without search columns.
        $report1 = reportbuilder_get_embedded_report('test_report', array(), false, 0);
        $cols1 = $report1->get_search_columns();
        $this->assertCount(2, $cols1);
        $this->assertArrayHasKey(100, $cols1);
        $this->assertArrayHasKey(101, $cols1);

        $report3 = reportbuilder_get_embedded_report('mycourses2', array(), false, 0);
        $cols3 = $report3->get_search_columns();
        $this->assertEmpty($cols3);
    }

    public function test_delete_search_column() {
        global $DB;
        // Add two reports.
        $rb2data = array(array('id' => 2, 'fullname' => 'Courses', 'shortname' => 'mycourses',
                         'source' => 'courses', 'hidden' => 1, 'embedded' => 1));

        $rbsearchcolsdata = array(
                        array('id' => 100, 'reportid' => 2, 'type' => 'course', 'value' => 'coursetypeicon',
                              'heading' => 'A', 'sortorder' => 1),
                        array('id' => 101, 'reportid' => 2, 'type' => 'course', 'value' => 'courselink',
                              'heading' => 'B', 'sortorder' => 2));

        // Add search columns to two reports.
        $this->loadDataSet($this->createArrayDataSet(array(
            'report_builder' => $rb2data,
            'report_builder_search_cols' => $rbsearchcolsdata)));

        // Test result for reports with/without search columns.
        $report2 = reportbuilder_get_embedded_report('mycourses', array(), false, 0);
        $report2->delete_search_column(100);
        $cols2 = $report2->get_search_columns();
        $this->assertCount(1, $cols2);
        $this->assertArrayHasKey(101, $cols2);
    }

    public function test_get_search_columns_select() {
        $report1 = reportbuilder_get_embedded_report('test_report', array(), false, 0);
        $cols1 = $report1->get_search_columns_select();
        // Current test report has at least three groups. Check some items inside aswell.
        $this->assertGreaterThanOrEqual(3, count($cols1));
        $compevidstr = get_string('type_competency_evidence', 'rb_source_competency_evidence');
        $compstr = get_string('type_competency', 'rb_source_dp_competency');
        $userstr = get_string('type_user', 'totara_reportbuilder');
        $this->assertArrayHasKey($compevidstr, $cols1);
        $this->assertArrayHasKey($compstr, $cols1);
        $this->assertArrayHasKey($userstr, $cols1);

        $this->assertArrayHasKey('competency_evidence-organisation', $cols1[$compevidstr]);
        $this->assertArrayHasKey('competency-fullname', $cols1[$compstr]);
        $this->assertArrayHasKey('user-fullname', $cols1[$userstr]);
    }

    /**
     * Also test get_sidebar_filters
     */
    public function test_get_standard_filters() {
        global $DB;
        // Add reports.
        $rb2data = array(array('id' => 59, 'fullname' => 'Courses', 'shortname' => 'mycourses',
                         'source' => 'courses', 'hidden' => 1, 'embedded' => 1),
                         array('id' => 3, 'fullname' => 'Courses2', 'shortname' => 'mycourses2',
                         'source' => 'courses', 'hidden' => 1, 'embedded' => 1));
        $rbfiltersdata = array(
            array('id' => 171, 'reportid' => 59, 'type' => 'course', 'value' => 'coursetype',
                  'sortorder' => 1, 'advanced' => 0, 'region' => rb_filter_type::RB_FILTER_REGION_SIDEBAR),
            array('id' => 172, 'reportid' => 59, 'type' => 'course', 'value' => 'mods',
                  'sortorder' => 2, 'advanced' => 1, 'region' => rb_filter_type::RB_FILTER_REGION_SIDEBAR),
            array('id' => 173, 'reportid' => 59, 'type' => 'course', 'value' => 'startdate',
                  'sortorder' => 3, 'advanced' => 0, 'region' => rb_filter_type::RB_FILTER_REGION_STANDARD),
            array('id' => 174, 'reportid' => 59, 'type' => 'course', 'value' => 'name_and_summary',
                  'sortorder' => 4, 'advanced' => 1, 'region' => rb_filter_type::RB_FILTER_REGION_STANDARD)
            );
        // Add filters to report.
        $this->loadDataSet($this->createArrayDataSet(array(
            'report_builder' => $rb2data,
            'report_builder_filters' => $rbfiltersdata)));

        // Report 59 has two sidebar filters.
        $report59 = reportbuilder_get_embedded_report('mycourses', array(), false, 0);
        $side59 = $report59->get_sidebar_filters();
        $this->assertCount(2, $side59);
        $this->assertArrayHasKey('course-coursetype', $side59);
        $this->assertArrayHasKey('course-mods', $side59);

        // Report 59 has two standard filters.
        $std59 = $report59->get_standard_filters();
        $this->assertCount(2, $std59);
        $this->assertArrayHasKey('course-startdate', $std59);
        $this->assertArrayHasKey('course-name_and_summary', $std59);

        // Report 3 doesn't have filters.
        $report3 = reportbuilder_get_embedded_report('mycourses2', array(), false, 0);
        $side3 = $report3->get_sidebar_filters();
        $std3 = $report3->get_standard_filters();
        $this->assertEmpty($side3);
        $this->assertEmpty($std3);
    }

    public function test_get_all_filter_joins() {
        $report = reportbuilder_get_embedded_report('test_report', array(), false, 0);
        $joins = $report->get_all_filter_joins();

        $this->assertNotEmpty($joins);
        $this->assertContainsOnlyInstancesOf('rb_join', $joins);
    }

    public function test_get_filters_select() {
        $report = reportbuilder_get_embedded_report('test_report', array(), false, 0);
        $filters = $report->get_filters_select();

        $compevidstr = get_string('type_competency_evidence', 'rb_source_competency_evidence');
        $compstr = get_string('type_competency', 'rb_source_dp_competency');
        $userstr = get_string('type_user', 'totara_reportbuilder');
        $this->assertArrayHasKey($compevidstr, $filters);
        $this->assertArrayHasKey($compstr, $filters);
        $this->assertArrayHasKey($userstr, $filters);

        $this->assertArrayHasKey('competency_evidence-completeddate', $filters[$compevidstr]);
        $this->assertArrayHasKey('competency-fullname', $filters[$compstr]);
        $this->assertArrayHasKey('user-fullname', $filters[$userstr]);
    }

    public function test_get_all_filters_select() {
        $report1 = reportbuilder_get_embedded_report('test_report', array(), false, 0);
        $filters = $report1->get_all_filters_select();

        $this->assertArrayHasKey('allstandardfilters', $filters);
        $this->assertArrayHasKey('unusedstandardfilters', $filters);
        $this->assertArrayHasKey('allsidebarfilters', $filters);
        $this->assertArrayHasKey('unusedsidebarfilters', $filters);
        $this->assertArrayHasKey('allsearchcolumns', $filters);
        $this->assertArrayHasKey('unusedsearchcolumns', $filters);

        // Check couple filters that should be in every category.
        $userstr = get_string('type_user', 'totara_reportbuilder');
        $compevidstr = get_string('type_competency_evidence', 'rb_source_competency_evidence');
        foreach ($filters as $key => $filter) {
            if (strpos($key, 'unused') === false) {
                $this->assertArrayHasKey($compevidstr, $filter);
                $this->assertGreaterThan(0, $filter[$compevidstr]);
            }
            // Check only rare-used filter. If it dissappear choose another filter for test.
            $this->assertArrayHasKey($userstr, $filter);
            $this->assertGreaterThan(0, $filter[$userstr]);
        }

    }
}
