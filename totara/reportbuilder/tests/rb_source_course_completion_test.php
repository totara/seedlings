<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2014 onwards Totara Learning Solutions LTD
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
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package totara_reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

class totara_reportbuilder_rb_source_course_completion_testcase extends advanced_testcase {
    use totara_reportbuilder\phpunit\report_testing;

    public function test_report() {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $users = array();
        $users[] = $this->getDataGenerator()->create_user(array('institution' => 'ABC'));
        $users[] = $this->getDataGenerator()->create_user(array('institution' => 'ABC'));
        $users[] = $this->getDataGenerator()->create_user(array('institution' => 'XYZ'));
        $users[] = $this->getDataGenerator()->create_user(array('institution' => ''));

        $courses = array();
        $courses[] = $this->getDataGenerator()->create_course(
            array('fullname' => 'Name0', 'summary' => 'Summary0', 'enablecompletion' => 1, 'completionstartonenrol' => 1, 'coursetype' => 0));
        $courses[] = $this->getDataGenerator()->create_course(
            array('fullname' => 'Name1', 'summary' => 'Summary1', 'enablecompletion' => 1, 'completionstartonenrol' => 1, 'coursetype' => 1));
        $courses[] = $this->getDataGenerator()->create_course(
            array('fullname' => 'Name2', 'summary' => 'Summary2', 'enablecompletion' => 1, 'completionstartonenrol' => 1, 'coursetype' => 2));

        $this->getDataGenerator()->enrol_user($users[0]->id, $courses[0]->id);
        $this->getDataGenerator()->enrol_user($users[0]->id, $courses[1]->id);
        $this->getDataGenerator()->enrol_user($users[1]->id, $courses[0]->id);
        $this->getDataGenerator()->enrol_user($users[2]->id, $courses[0]->id);
        $this->getDataGenerator()->enrol_user($users[3]->id, $courses[0]->id);
        $this->getDataGenerator()->enrol_user($users[3]->id, $courses[1]->id);

        $rid = $this->create_report('course_completion', 'Test course report');

        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'course', 'fullname', null, null, null, 0);
        $this->add_column($report, 'course', 'name_and_summary', null, null, null, 0);
        $this->add_column($report, 'course_completion', 'status', null, null, null, 0);
        $this->add_column($report, 'user', 'institution', null, null, null, 0);

        $report = new reportbuilder($rid);
        list($sql, $params, $cache) = $report->build_query();

        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(6, $records);

        $record = reset($records);
        $record = (array)$record;
        $this->assertCount(9, $record);
        $this->assertArrayHasKey('id', $record);
        $this->assertArrayHasKey('course_fullname', $record);
        $this->assertArrayHasKey('course_name_and_summary', $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'filearea'), $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'component'), $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'context'), $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'recordid'), $record);
        $this->assertArrayHasKey('course_completion_status', $record);
        $this->assertArrayHasKey('user_institution', $record);

        // Try aggregation.

        $this->delete_columns($report);

        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'course', 'name_and_summary', null, null, null, 0);
        $this->add_column($report, 'course_completion', 'status', null, null, null, 0);
        $this->add_column($report, 'user', 'institution', null, 'countdistinct', null, 0);

        $report = new reportbuilder($rid);
        list($sql, $params, $cache) = $report->build_query();

        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(2, $records);

        $record = reset($records);
        $record = (array)$record;
        $this->assertCount(8, $record);
        $this->assertArrayHasKey('id', $record);
        $this->assertArrayHasKey('course_name_and_summary', $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'filearea'), $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'component'), $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'context'), $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'recordid'), $record);
        $this->assertArrayHasKey('course_completion_status', $record);
        $this->assertArrayHasKey('user_institution', $record);

        // Add filter.

        $todb = new stdClass();
        $todb->reportid = $report->_id;
        $todb->advanced = 0;
        $todb->region = rb_filter_type::RB_FILTER_REGION_STANDARD;
        $todb->type = 'course';
        $todb->value = 'fullname';
        $todb->filtername = 'pokus';
        $todb->customname = 1;
        $todb->sortorder = 1;
        $DB->insert_record('report_builder_filters', $todb);

        $report = new reportbuilder($rid);
        $filters = $report->get_filters();
        $this->assertCount(1, $filters);
        /** @var rb_filter_text $filter */
        $filter = reset($filters);
        $this->assertInstanceOf('rb_filter_text', $filter);
        $this->assertSame('course', $filter->type);
        $this->assertSame('fullname', $filter->value);
        $filter->set_data(array('operator' => 2, 'value' => 'Name0'));

        $report = new reportbuilder($rid);
        list($sql, $params, $cache) = $report->build_query(false, true);

        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $records);

        $record = reset($records);
        $record = (array)$record;
        $this->assertCount(8, $record);
        $this->assertArrayHasKey('id', $record);
        $this->assertSame('Name0<br />Summary0', $record['course_name_and_summary']);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'filearea'), $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'component'), $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'context'), $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'recordid'), $record);
        $this->assertArrayHasKey('course_completion_status', $record);
        $this->assertSame('3', $record['user_institution']);

        list($sql, $params, $cache) = $report->build_query(true, true);
        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $records);

        // Add sidebar filter.

        $todb = new stdClass();
        $todb->reportid = $report->_id;
        $todb->advanced = 0;
        $todb->region = rb_filter_type::RB_FILTER_REGION_SIDEBAR;
        $todb->type = 'course';
        $todb->value = 'coursetype';
        $todb->filtername = 'typ';
        $todb->customname = 1;
        $todb->sortorder = 2;
        $DB->insert_record('report_builder_filters', $todb);

        $report = new reportbuilder($rid);
        $filters = $report->get_filters();
        $this->assertCount(2, $filters);
        foreach ($filters as $filter) {
            if (get_class($filter) === 'rb_filter_multicheck') {
                $filter->set_data(array('operator' => 1, 'value' => array (0 => '1', 1 => '0', 2 => '0')));
            }
        }

        $report = new reportbuilder($rid);
        list($sql, $params, $cache) = $report->build_query(false, true);

        $records = $DB->get_records_sql($sql, $params);

        $this->assertCount(1, $records);

        // Try caching.

        $this->enable_caching($report->_id);

        $report = new reportbuilder($rid);
        list($sql, $params, $cache) = $report->build_query(false, true);
        $this->assertNotEmpty($cache);

        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $records);

        $record = reset($records);
        $record = (array)$record;
        $this->assertCount(8, $record);
        $this->assertArrayHasKey('id', $record);
        $this->assertSame('Name0<br />Summary0', $record['course_name_and_summary']);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'filearea'), $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'component'), $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'context'), $record);
        $this->assertArrayHasKey(reportbuilder_get_extrafield_alias('course', 'name_and_summary', 'recordid'), $record);
        $this->assertArrayHasKey('course_completion_status', $record);
        $this->assertSame('3', $record['user_institution']);

        list($sql, $params, $cache) = $report->build_query(true, true);
        $this->assertNotEmpty($cache);

        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $records);

        // Test the sidebar filter stuff - make sure there are no errors.

        $this->disable_caching($report->_id);

        require_once($CFG->dirroot . '/totara/reportbuilder/report_forms.php');
        $mformsidebar = new report_builder_sidebar_search_form($report->get_current_url(),
            array('report' => $report, 'fields' => $report->get_sidebar_filters()));

        $this->enable_caching($report->_id);

        require_once($CFG->dirroot . '/totara/reportbuilder/report_forms.php');
        $mformsidebar = new report_builder_sidebar_search_form($report->get_current_url(),
            array('report' => $report, 'fields' => $report->get_sidebar_filters()));
    }
}
