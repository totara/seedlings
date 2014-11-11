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

class totara_reportbuilder_events_testcase extends advanced_testcase {
    use totara_reportbuilder\phpunit\report_testing;

    public function test_report_created_event() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $rid = $this->create_report('user', 'Test user report');
        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'user', 'id', null, null, null, 0);
        $this->add_column($report, 'user', 'firstname', null, null, null, 0);

        $report = new reportbuilder($rid);

        $event = \totara_reportbuilder\event\report_created::create_from_report($report, false);
        $event->trigger();
        $data = $event->get_data();
        $this->assertSame($report, $event->get_report());
        $this->assertSame($report->_id, $event->objectid);
        $this->assertSame('c', $data['crud']);
        $this->assertFalse($data['other']['embedded']);

        $this->assertEventContextNotUsed($event);
        $this->assertEventLegacyLogData(array(SITEID, 'reportbuilder', 'new report', 'report.php?id=1', 'Test user report (ID='.$report->_id.')'), $event);

        // Let's created embedded report, we should get an event there.

        $sink = $this->redirectEvents();
        $emreport = new reportbuilder(null, 'cohort_members');
        $this->assertInstanceOf('reportbuilder', $emreport);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('totara_reportbuilder\event\report_created', $event);
        $data = $event->get_data();
        $this->assertTrue($data['other']['embedded']);
    }

    public function test_report_updated_event() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $rid = $this->create_report('user', 'Test user report');
        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'user', 'id', null, null, null, 0);
        $this->add_column($report, 'user', 'firstname', null, null, null, 0);

        $report = new reportbuilder($rid);

        $event = \totara_reportbuilder\event\report_updated::create_from_report($report, 'columns');
        $event->trigger();
        $data = $event->get_data();
        $this->assertSame($report, $event->get_report());
        $this->assertSame($report->_id, $event->objectid);
        $this->assertSame('u', $data['crud']);
        $this->assertSame('columns', $data['other']['area']);

        $this->assertEventContextNotUsed($event);
        $this->assertEventLegacyLogData(array(SITEID, 'reportbuilder', 'update report', 'report.php?id=1', 'Test user report (ID='.$report->_id.')'), $event);
    }

    public function test_report_deleted_event() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $rid = $this->create_report('user', 'Test user report');
        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'user', 'id', null, null, null, 0);
        $this->add_column($report, 'user', 'firstname', null, null, null, 0);

        $report = new reportbuilder($rid);

        $DB->delete_records('report_builder', array('id' => $report->_id));

        $event = \totara_reportbuilder\event\report_deleted::create_from_report($report, false);
        $event->trigger();
        $data = $event->get_data();
        $this->assertSame($report, $event->get_report());
        $this->assertSame($report->_id, $event->objectid);
        $this->assertSame('d', $data['crud']);
        $this->assertFalse($data['other']['reloaded']);

        $this->assertEventContextNotUsed($event);
        $this->assertEventLegacyLogData(array(SITEID, 'reportbuilder', 'delete report', 'index.php', 'Test user report (ID='.$report->_id.')'), $event);

        // Embedded report.

        $report = new reportbuilder(null, 'cohort_members');
        $this->assertInstanceOf('reportbuilder', $report);

        $DB->delete_records('report_builder', array('id' => $report->_id));

        $event = \totara_reportbuilder\event\report_deleted::create_from_report($report, true);
        $event->trigger();
        $data = $event->get_data();
        $this->assertSame($report, $event->get_report());
        $this->assertSame($report->_id, $event->objectid);
        $this->assertSame('d', $data['crud']);
        $this->assertTrue($data['other']['reloaded']);

        $this->assertEventContextNotUsed($event);
        $this->assertEventLegacyLogData(array(SITEID, 'reportbuilder', 'reload report', 'index.php', 'Audience members (ID='.$report->_id.')'), $event);
    }

    public function test_report_viewed_event() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $rid = $this->create_report('user', 'Test user report');
        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'user', 'id', null, null, null, 0);
        $this->add_column($report, 'user', 'firstname', null, null, null, 0);

        $report = new reportbuilder($rid);

        $event = \totara_reportbuilder\event\report_viewed::create_from_report($report);
        $event->trigger();
        $data = $event->get_data();
        $this->assertSame($report, $event->get_report());
        $this->assertSame($report->_id, $event->objectid);
        $this->assertSame('r', $data['crud']);

        $this->assertEventContextNotUsed($event);
        $this->assertEventLegacyLogData(array(SITEID, 'reportbuilder', 'view report', 'report.php?id=1', 'Test user report'), $event);
    }

    public function test_report_exported_event() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $rid = $this->create_report('user', 'Test user report');
        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'user', 'id', null, null, null, 0);
        $this->add_column($report, 'user', 'firstname', null, null, null, 0);

        $report = new reportbuilder($rid);

        $event = \totara_reportbuilder\event\report_exported::create_from_report($report, REPORT_BUILDER_EXPORT_PDF_LANDSCAPE);
        $event->trigger();
        $data = $event->get_data();
        $this->assertSame($report, $event->get_report());
        $this->assertSame($report->_id, $event->objectid);
        $this->assertSame('r', $data['crud']);
        $this->assertSame(REPORT_BUILDER_EXPORT_PDF_LANDSCAPE, $data['other']['format']);

        $this->assertEventContextNotUsed($event);
        $this->assertEventLegacyLogData(array(SITEID, 'reportbuilder', 'export report', 'report.php?id=1', 'Test user report'), $event);
    }
}
