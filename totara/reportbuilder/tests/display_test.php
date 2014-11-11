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

class totara_reportbuilder_display_testcase extends advanced_testcase {
    use totara_reportbuilder\phpunit\report_testing;

    public function test_aggregation() {
        global $DB, $CFG;
        require_once($CFG->libdir . '/excellib.class.php');
        require_once($CFG->libdir . '/odslib.class.php');

        $syscontext = context_system::instance();

        $this->resetAfterTest();
        $this->setAdminUser(); // We need permissions to view all reports.

        $user = $this->getDataGenerator()->create_user();
        $user->firstaccess  = strtotime('2013-01-10 10:00:00 UTC');
        $user->timemodified = strtotime('2013-01-10 10:00:00 UTC');
        $user->lastlogin    = 0;
        $user->currentlogin = strtotime('2013-01-10 10:00:00 UTC'); // This is the lastlogin in reports.
        $user->timecreated  = strtotime('2013-01-10 10:00:00 UTC');
        $user->firstname  = 'řízek';
        $DB->update_record('user', $user);

        $usercontext = context_user::instance($user->id);

        // Let's create some user fields, there is no suitable API here, let's do it the raw way.

        $field = new stdClass();
        $field->name = 'Some text';
        $field->shortname = 'sometext';
        $field->datatype = 'textarea';
        $field->description = 'some description';
        $field->descriptionformat = FORMAT_HTML;
        $field->defaultdata = '';
        $field->defaultdataformat = FORMAT_HTML;
        $field->id = $DB->insert_record('user_info_field', $field);

        $uf = new stdClass();
        $uf->userid = $user->id;
        $uf->fieldid = $field->id;
        $uf->data = 'Some html <strong>text</strong><script></script>';
        $uf->dataformat = FORMAT_HTML;
        $DB->insert_record('user_info_data', $uf);

        $rid = $this->create_report('user', 'Test user report 1');

        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'user', 'id', null, null, null, 0);
        $this->add_column($report, 'user', 'firstaccess', 'month', null, null, 0);
        $this->add_column($report, 'user', 'timemodified', null, null, null, 0);
        $this->add_column($report, 'user', 'lastlogin', null, null, null, 0);
        $this->add_column($report, 'user', 'firstname', null, null, null, 0);
        $this->add_column($report, 'user', 'timecreated', 'weekday', null, null, 0);
        $this->add_column($report, 'user', 'custom_field_'.$field->id, null, null, null, 0);

        $report = new reportbuilder($rid);

        // Let's hack the column options in memory only, hopefully this will continue working in the future...
        $report->columns['user-firstaccess']->displayfunc = 'month';
        $report->columns['user-timemodified']->displayfunc = 'nice_date';
        $report->columns['user-lastlogin']->displayfunc = 'nice_datetime';
        $report->columns['user-firstname']->displayfunc = 'ucfirst';
        $report->columns['user-timecreated']->displayfunc = 'weekday';
        $report->columns['user-custom_field_'.$field->id]->displayfunc = 'userfield_textarea';

        list($sql, $params, $cache) = $report->build_query();

        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(3, $records);
        $row = $records[$user->id];

        $this->assertInstanceOf('stdClass', $row);
        $processed = $report->src->process_data_row($row, 'html', $report);

        $this->assertSame('3', $processed[0]);
        $this->assertSame('January', $processed[1]);
        $this->assertSame('10 Jan 2013', $processed[2]);
        $this->assertStringStartsWith('10 Jan 2013 at ', $processed[3]);
        $this->assertSame('Řízek', $processed[4]);
        $this->assertSame('Thursday', $processed[5]);
        $this->assertSame('Some html <strong>text</strong>', $processed[6]);

        $processed = $report->src->process_data_row($row, 'pdf', $report);

        $this->assertSame('3', $processed[0]);
        $this->assertSame('January', $processed[1]);
        $this->assertSame('10 Jan 2013', $processed[2]);
        $this->assertStringStartsWith('10 Jan 2013 at ', $processed[3]);
        $this->assertSame('Řízek', $processed[4]);
        $this->assertSame('Thursday', $processed[5]);
        $this->assertSame('Some html TEXT', $processed[6]);

        $processed = $report->src->process_data_row($row, 'excel', $report);

        $this->assertSame('3', $processed[0]);
        $this->assertSame('January', $processed[1]);
        $this->assertSame('date', $processed[2][0]);
        $this->assertSame('1357812000', $processed[2][1]);
        $this->assertInstanceOf('MoodleExcelFormat', $processed[2][2]);
        $this->assertSame('date', $processed[3][0]);
        $this->assertSame('1357812000', $processed[3][1]);
        $this->assertInstanceOf('MoodleExcelFormat', $processed[3][2]);
        $this->assertSame('Řízek', $processed[4]);
        $this->assertSame('Thursday', $processed[5]);
        $this->assertSame('Some html TEXT', $processed[6]);

        $processed = $report->src->process_data_row($row, 'ods', $report);

        $this->assertSame('3', $processed[0]);
        $this->assertSame('January', $processed[1]);
        $this->assertSame('date', $processed[2][0]);
        $this->assertSame('1357812000', $processed[2][1]);
        $this->assertInstanceOf('MoodleODSFormat', $processed[2][2]);
        $this->assertSame('date', $processed[3][0]);
        $this->assertSame('1357812000', $processed[3][1]);
        $this->assertInstanceOf('MoodleODSFormat', $processed[3][2]);
        $this->assertSame('Řízek', $processed[4]);
        $this->assertSame('Thursday', $processed[5]);
        $this->assertSame('Some html TEXT', $processed[6]);

        // Now try the custom fields in course.

        $course = $this->getDataGenerator()->create_course(array('summary' => 'Some summary', 'summaryformat' => FORMAT_MOODLE));

        $s = 1;
        $filefield = new stdClass();
        $filefield->fullname = "File field";
        $filefield->shortname = "filefield";
        $filefield->datatype = 'file';
        $filefield->sortorder = $s++;
        $filefield->hidden = 0;
        $filefield->locked = 0;
        $filefield->required = 0;
        $filefield->forceunique = 0;
        $filefield->id = $DB->insert_record('course_info_field', $filefield);

        $f = new stdClass();
        $f->fieldid = $filefield->id;
        $f->courseid = $course->id;
        $f->data = '';
        $f->id = $DB->insert_record('course_info_data', $f);
        $f->data = $f->id;
        $DB->update_record('course_info_data', $f);

        $fs = get_file_storage();
        $file = array('contextid' => $syscontext->id, 'component' => 'totara_customfield', 'filearea' => 'course_filemgr',
                        'itemid' => $f->data, 'filepath' => '/', 'filename' => 'readme.txt');
        $fileurl = 'http://www.example.com/moodle/pluginfile.php/'.$syscontext->id.'/totara_customfield/course_filemgr/'.
                        $f->data.'/readme.txt';
        $fs->create_file_from_string($file, 'hi!');

        $multiselect = new stdClass();
        $multiselect->fullname = "Multiselect field";
        $multiselect->shortname = "multiselectfield";
        $multiselect->datatype = 'multiselect';
        $multiselect->sortorder = $s++;
        $multiselect->hidden = 0;
        $multiselect->locked = 0;
        $multiselect->required = 0;
        $multiselect->forceunique = 0;
        $multiselect->param1 = '[{"option":"volba1","icon":"business-modelling","default":"0","delete":0},';
        $multiselect->param1 .= '{"option":"volba2","icon":"developing-strengths-into-talents","default":"0","delete":0}]';
        $multiselect->id = $DB->insert_record('course_info_field', $multiselect);

        $mf = new stdClass();
        $mf->fieldid = $multiselect->id;
        $mf->courseid = $course->id;
        $mf->data = '{"9efde54a5d26d0f4c0d91aa6607c56b4":{"option":"volba1","icon":"business-modelling","default":1,"delete":0}}';
        $mf->id = $DB->insert_record('course_info_data', $mf);

        $dp = new stdClass();
        $dp->dataid = $mf->id;
        $dp->value = '9efde54a5d26d0f4c0d91aa6607c56b4';
        $DB->insert_record('course_info_data_param', $dp);

        $areafield = new stdClass();
        $areafield->fullname = "Area field";
        $areafield->shortname = "areafield";
        $areafield->datatype = 'textarea';
        $areafield->sortorder = $s++;
        $areafield->hidden = 0;
        $areafield->locked = 0;
        $areafield->required = 0;
        $areafield->forceunique = 0;
        $areafield->param1 = 30;
        $areafield->param1 = 10;
        $areafield->id = $DB->insert_record('course_info_field', $areafield);

        $f = new stdClass();
        $f->fieldid = $areafield->id;
        $f->courseid = $course->id;
        $f->data = 'Some html <strong>text</strong><script></script>';;
        $f->id = $DB->insert_record('course_info_data', $f);

        $rid = $this->create_report('courses', 'Test courses report 1');

        $report = new reportbuilder($rid, null, false, null, null, true);

        $this->add_column($report, 'course', 'summary', null, null, null, 0);
        $this->add_column($report, 'course', 'custom_field_'.$filefield->id, null, null, null, 0);
        $this->add_column($report, 'course', 'custom_field_'.$multiselect->id.'_text', null, null, null, 0);
        $this->add_column($report, 'course', 'custom_field_'.$multiselect->id.'_icon', null, null, null, 0);
        $this->add_column($report, 'course', 'custom_field_'.$areafield->id, null, null, null, 0);

        $report = new reportbuilder($rid);

        list($sql, $params, $cache) = $report->build_query();

        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $records);
        $row = reset($records);

        $processed = $report->src->process_data_row($row, 'html', $report);

        $this->assertSame('<div class="text_to_html">Some summary</div>', $processed[0]);
        $this->assertSame('<a class="icon" href="'.$fileurl.'"><img alt="File" class="smallicon" title="File" src="http://www.example.com/moodle/theme/image.php/_s/standardtotararesponsive/core/1/f/text" />readme.txt</a>', $processed[1]);
        $this->assertSame('volba1', $processed[2]);
        $this->assertSame('<img src="http://www.example.com/moodle/theme/image.php/_s/standardtotararesponsive/totara_core/1//courseicons/business-modelling" id="icon_preview" class="course_icon" alt="volba1" title="volba1" />', $processed[3]);
        $this->assertSame('Some html <strong>text</strong><script></script>', $processed[4]);

        $processed = $report->src->process_data_row($row, 'pdf', $report);

        $this->assertSame('Some summary', $processed[0]);
        $this->assertSame('readme.txt', $processed[1]);
        $this->assertSame('volba1', $processed[2]);
        $this->assertSame('volba1', $processed[3]);
        $this->assertSame('Some html TEXT', $processed[4]);
    }
}
