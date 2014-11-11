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
 * @package totara_appraisal
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once(__DIR__ . '/appraisal_testcase.php');

class totara_appraisal_rb_source_appraisal_detail_testcase extends appraisal_testcase {
    use totara_reportbuilder\phpunit\report_testing;

    public function test_report() {
        global $DB, $SESSION;

        $this->resetAfterTest();
        $this->setAdminUser();

        $users = array();
        $users[] = $this->getDataGenerator()->create_user();

        list($appraisal) = $this->prepare_appraisal_with_users(array(), $users);

        list($errors, $warnings) = $appraisal->validate();
        $this->assertEmpty($errors);
        $this->assertEmpty($warnings);

        /** @var appraisal $appraisal */
        $appraisal->activate();
        $roleassignment = appraisal_role_assignment::get_role($appraisal->id, $users[0]->id, $users[0]->id, appraisal::ROLE_LEARNER);
        $this->answer_question($appraisal, $roleassignment, 0, 'completestage');

        $rid = $this->create_report('appraisal_detail', 'Test report');

        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'appraisal', 'name', null, null, null, 0);
        $this->add_column($report, 'rolelearner', 'answers', null, null, null, 0);

        $SESSION->reportbuilder[$rid]['appraisalid'] = $appraisal->get()->id;
        $report = new reportbuilder($rid);

        list($sql, $params, $cache) = $report->build_query();

        $records = $DB->get_records_sql($sql, $params);

        $this->assertCount(1, $records);
        $record = reset($records);
        $this->assertSame($appraisal->get()->name, $record->appraisal_name);

        $record = (array)$record;
        $this->assertCount(3, $record);
        $this->assertArrayHasKey('id', $record);
        $this->assertArrayHasKey('appraisal_name', $record);

        $this->add_column($report, 'appraisal', 'timestarted', null, 'minimum', null, 0);

        $report = new reportbuilder($rid);
        list($sql, $params, $cache) = $report->build_query();
        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $records);

        $this->assertFalse($report->src->cacheable);
        $this->enable_caching($report->_id);

        $report = new reportbuilder($rid);
        list($sql, $params, $cache) = $report->build_query();
        $this->assertSame(array(), $cache);
        $records = $DB->get_records_sql($sql, $params);
        $this->assertCount(1, $records);
    }
}
