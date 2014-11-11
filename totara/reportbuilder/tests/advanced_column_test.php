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

class totara_reportbuilder_advanced_column_testcase extends advanced_testcase {
    use totara_reportbuilder\phpunit\report_testing;

    public function test_aggregation() {
        global $DB;

        $this->resetAfterTest();

        $users = array();
        $users[0] = $DB->get_record('user', array('username'=>'admin'));
        $users[1] = $DB->get_record('user', array('username'=>'guest'));
        $users[2] = $this->getDataGenerator()->create_user();
        $users[3] = $this->getDataGenerator()->create_user();
        $users[4] = $this->getDataGenerator()->create_user();
        $users[5] = $this->getDataGenerator()->create_user();

        $usermap = array();
        foreach ($users as $i => $user) {
            $usermap[$user->id] = $i;
        }

        $users[0]->institution = 'abc';
        $users[0]->firstaccess = 10;
        $users[0]->timemodified = 10;
        $users[0]->currentlogin = 10;
        $users[0]->timecreated = 111;

        $users[1]->institution = 'abc';
        $users[1]->firstaccess = 5;
        $users[1]->timemodified = 100;
        $users[1]->currentlogin = 100;
        $users[1]->timecreated = 111;

        $users[2]->institution = 'def';
        $users[2]->firstaccess = 3;
        $users[2]->timemodified = 0;
        $users[2]->currentlogin = 0;
        $users[2]->timecreated = 111;

        $users[3]->institution = '';
        $users[3]->firstaccess = 0;
        $users[3]->timemodified = 0;
        $users[3]->currentlogin = 0;
        $users[3]->timecreated = 111;

        $users[4]->institution = '';
        $users[4]->firstaccess = 0;
        $users[4]->timemodified = 0;
        $users[4]->currentlogin = 0;
        $users[4]->timecreated = 111;

        $users[5]->institution = '';
        $users[5]->firstaccess = 0;
        $users[5]->timemodified = 0;
        $users[5]->currentlogin = -10;
        $users[5]->timecreated = 111;

        foreach ($users as $user) {
            $DB->update_record('user', $user);
        }

        $rid = $this->create_report('user', 'Test user report 1');

        // Test counts and stats.

        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'user', 'username', null, 'countany', '', 0);
        $this->add_column($report, 'user', 'institution', null, 'countdistinct', '', 0);
        $this->add_column($report, 'user', 'firstaccess', null, 'avg', '', 0);
        $this->add_column($report, 'user', 'timemodified', null, 'maximum', '', 0);
        $this->add_column($report, 'user', 'lastlogin', null, 'minimum', '', 0);
        $this->add_column($report, 'user', 'timecreated', null, 'sum', '', 0);
        $this->add_column($report, 'user', 'id', null, 'stddev', '', 0);

        $report = new reportbuilder($rid, null, false, null, null, true);

        list($sql, $params, $cache) = $report->build_query(false, false, false);

        $count = 0;
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $count++;
            $this->assertSame('6', $record->user_username);
            $this->assertSame('3', $record->user_institution);
            $this->assertEquals(3, $record->user_firstaccess, '', 0.0001);
            $this->assertSame('100', $record->user_timemodified);
            $this->assertSame('-10', $record->user_lastlogin);
            $this->assertSame('666', $record->user_timecreated);
            $this->assertTrue(is_numeric($record->user_id)); // Who wants to calculate this exactly?
        }
        $records->close();
        $this->assertEquals(1, $count);
        $this->delete_columns($report);

        // Test 'countany'.

        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'user', 'institution', null, null, '', 0);
        $this->add_column($report, 'user', 'id', null, 'countany', '', 0);

        $report = new reportbuilder($rid, null, false, null, null, true);

        list($sql, $params, $cache) = $report->build_query(false, false, false);

        $count = 0;
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $count++;
            if ($record->user_institution === 'abc') {
                $this->assertEquals(2, $record->user_id);
            } else if ($record->user_institution === 'def') {
                $this->assertEquals(1, $record->user_id);
            } else if ($record->user_institution === '') {
                $this->assertEquals(3, $record->user_id); // Admin and guest do not have institution too.
            } else {
                $this->fail('Unknown institution ' . $record->user_institution);
            }
        }
        $records->close();
        $this->assertEquals(3, $count);
        $this->delete_columns($report);

        // Test 'groupconcat'.

        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'user', 'username', null, 'groupconcat', '', 0);

        $report = new reportbuilder($rid, null, false, null, null, true);

        list($sql, $params, $cache) = $report->build_query(false, false, false);

        $expected = array();
        foreach ($users as $user) {
            $expected[] = $user->username;
        }
        sort($expected);

        $count = 0;
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $result = explode(', ', $record->user_username);
            sort($result);
            $this->assertSame($expected, $result);
            $count++;
        }
        $records->close();
        $this->assertEquals(1, $count);
        $this->delete_columns($report);

        // Test 'groupconcatdistinct'.

        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'user', 'institution', null, 'groupconcatdistinct', '', 0);

        $report = new reportbuilder($rid, null, false, null, null, true);

        list($sql, $params, $cache) = $report->build_query(false, false, false);

        $expected = array('', 'abc', 'def');
        sort($expected);

        $count = 0;
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $result = explode(', ', $record->user_institution);
            sort($result);
            $this->assertSame($expected, $result);
            $count++;
        }
        $records->close();
        $this->assertEquals(1, $count);
        $this->delete_columns($report);
    }

    public function test_transform() {
        global $DB;

        $this->resetAfterTest();

        $users = array();
        $users[] = $this->getDataGenerator()->create_user();
        $users[] = $this->getDataGenerator()->create_user();
        $users[] = $this->getDataGenerator()->create_user();
        $users[] = $this->getDataGenerator()->create_user();

        $usermap = array();
        foreach ($users as $i => $user) {
            $usermap[$user->id] = $i;
        }

        // Let's use dates that are in the same timezone in most of the world,
        // -10 hours zone cannot run these tests, sorry.

        $users[0]->timecreated = strtotime('2013-01-10 10:00:00 UTC');
        $users[0]->firstaccess = strtotime('2013-01-11 10:00:00 UTC');
        $users[1]->timecreated = strtotime('2013-10-10 10:00:00 UTC');
        $users[1]->firstaccess = strtotime('2013-10-10 10:00:00 UTC');
        $users[2]->timecreated = strtotime('2013-12-24 10:00:00 UTC');
        $users[2]->firstaccess = 0;
        $users[3]->timecreated = strtotime('2014-11-30 10:00:00 UTC');
        $users[3]->firstaccess = 0;

        foreach ($users as $user) {
            $DB->update_record('user', $user);
        }

        $expected = array(
            'day' => array(
                $users[0]->id => array('timecreated' => '10', 'firstaccess' => '11'),
                $users[1]->id => array('timecreated' => '10', 'firstaccess' => '10'),
                $users[2]->id => array('timecreated' => '24', 'firstaccess' => null),
                $users[3]->id => array('timecreated' => '30', 'firstaccess' => null),
            ),
            'dayyear' => array(
                $users[0]->id => array('timecreated' => '010', 'firstaccess' => '011'),
                $users[1]->id => array('timecreated' => '283', 'firstaccess' => '283'),
                $users[2]->id => array('timecreated' => '358', 'firstaccess' => null),
                $users[3]->id => array('timecreated' => '334', 'firstaccess' => null),
            ),
            'month' => array(
                $users[0]->id => array('timecreated' => '01', 'firstaccess' => '01'),
                $users[1]->id => array('timecreated' => '10', 'firstaccess' => '10'),
                $users[2]->id => array('timecreated' => '12', 'firstaccess' => null),
                $users[3]->id => array('timecreated' => '11', 'firstaccess' => null),
            ),
            'quarter' => array(
                $users[0]->id => array('timecreated' => '1', 'firstaccess' => '1'),
                $users[1]->id => array('timecreated' => '4', 'firstaccess' => '4'),
                $users[2]->id => array('timecreated' => '4', 'firstaccess' => null),
                $users[3]->id => array('timecreated' => '4', 'firstaccess' => null),
            ),
            'weekday' => array(
                $users[0]->id => array('timecreated' => '5', 'firstaccess' => '6'),
                $users[1]->id => array('timecreated' => '5', 'firstaccess' => '5'),
                $users[2]->id => array('timecreated' => '3', 'firstaccess' => null),
                $users[3]->id => array('timecreated' => '1', 'firstaccess' => null),
            ),
            'year' => array(
                $users[0]->id => array('timecreated' => '2013', 'firstaccess' => '2013'),
                $users[1]->id => array('timecreated' => '2013', 'firstaccess' => '2013'),
                $users[2]->id => array('timecreated' => '2013', 'firstaccess' => null),
                $users[3]->id => array('timecreated' => '2014', 'firstaccess' => null),
            ),
            'yearmonth' => array(
                $users[0]->id => array('timecreated' => '2013-01', 'firstaccess' => '2013-01'),
                $users[1]->id => array('timecreated' => '2013-10', 'firstaccess' => '2013-10'),
                $users[2]->id => array('timecreated' => '2013-12', 'firstaccess' => null),
                $users[3]->id => array('timecreated' => '2014-11', 'firstaccess' => null),
            ),
            'yearmonthday' => array(
                $users[0]->id => array('timecreated' => '2013-01-10', 'firstaccess' => '2013-01-11'),
                $users[1]->id => array('timecreated' => '2013-10-10', 'firstaccess' => '2013-10-10'),
                $users[2]->id => array('timecreated' => '2013-12-24', 'firstaccess' => null),
                $users[3]->id => array('timecreated' => '2014-11-30', 'firstaccess' => null),
            ),
            'yearquarter' => array(
                $users[0]->id => array('timecreated' => '2013-1', 'firstaccess' => '2013-1'),
                $users[1]->id => array('timecreated' => '2013-4', 'firstaccess' => '2013-4'),
                $users[2]->id => array('timecreated' => '2013-4', 'firstaccess' => null),
                $users[3]->id => array('timecreated' => '2014-4', 'firstaccess' => null),
            ),
        );

        $rid = $this->create_report('user', 'Test user report 1');

        foreach ($expected as $transform => $results) {
            $report = new reportbuilder($rid, null, false, null, null, true);
            $this->add_column($report, 'user', 'id', null, null, '', 0);
            $this->add_column($report, 'user', 'timecreated', $transform, null, '', 0);
            $this->add_column($report, 'user', 'firstaccess', $transform, null, '', 0);

            $report = new reportbuilder($rid, null, false, null, null, true);

            list($sql, $params, $cache) = $report->build_query(false, false, false);

            $count = 0;
            $records = $DB->get_recordset_sql($sql, $params);
            foreach ($records as $record) {
                $count++;
                if (isguestuser($record->user_id)) {
                    continue;
                }
                if (is_siteadmin($record->user_id)) {
                    continue;
                }
                $i = $usermap[$record->user_id];
                if ($transform === 'hour') {
                    // We do not know the database timezone, let's just verify it is some two digit number.
                    $this->assertRegExp('/\d\d/', $record->user_timecreated, $record->user_timecreated,
                                            "Unexpected result of transform '$transform' of timecreated for user $i");
                    $this->assertRegExp('/\d\d/', $record->user_firstaccess, $record->user_firstaccess,
                                            "Unexpected result of transform '$transform' of firstaccess for user $i");
                } else {
                    $this->assertSame($results[$record->user_id]['timecreated'], $record->user_timecreated,
                                            "Unexpected result of transform '$transform' of timecreated for user $i");
                    $this->assertSame($results[$record->user_id]['firstaccess'], $record->user_firstaccess,
                                            "Unexpected result of transform '$transform' of firstaccess for user $i");
                }
            }
            $records->close();
            $this->assertEquals(6, $count);
            $this->delete_columns($report);
        }
    }

    public function test_caching() {
        global $DB, $CFG;

        $this->resetAfterTest();

        set_config('enablereportcaching', 1);
        $this->assertNotEmpty($CFG->enablereportcaching);

        $users = array();
        $users[0] = $DB->get_record('user', array('username'=>'admin'));
        $users[1] = $DB->get_record('user', array('username'=>'guest'));
        $users[2] = $this->getDataGenerator()->create_user();
        $users[3] = $this->getDataGenerator()->create_user();

        $users[0]->timecreated = strtotime('2013-01-10 10:00:00 UTC');
        $users[0]->institution = '';
        $users[1]->timecreated = strtotime('2013-02-10 10:00:00 UTC');
        $users[1]->institution = '';
        $users[2]->timecreated = strtotime('2013-03-10 10:00:00 UTC');
        $users[2]->institution = 'aa';
        $users[3]->timecreated = strtotime('2013-04-10 10:00:00 UTC');
        $users[3]->institution = 'bb';

        foreach ($users as $user) {
            $DB->update_record('user', $user);
        }

        $usermap = array();
        foreach ($users as $i => $user) {
            $usermap[$user->id] = $i;
        }

        $rid = $this->create_report('user', 'Test user report 1');

        $report = new reportbuilder($rid, null, false, null, null, true);
        $this->add_column($report, 'user', 'timecreated', 'year', null, '', 0);
        $this->add_column($report, 'user', 'institution', null, 'countdistinct', '', 0);

        $report = new reportbuilder($rid, null, false, null, null, true);

        list($sql, $params, $cache) = $report->build_query(false, false, false);

        $records = array();
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $record) {
            $records[] = $record;
        }

        $this->assertCount(1, $records);

        $this->assertEquals('2013', $records[0]->user_timecreated);
        $this->assertEquals('3', $records[0]->user_institution);

        $DB->execute('UPDATE {report_builder} SET cache = 1 WHERE id = ?', array($rid));
        reportbuilder_schedule_cache($rid, array('initschedule' => 1));
        $result = reportbuilder_generate_cache($rid);
        $this->assertTrue($result);

        // Test cache returns the same result.

        $report = new reportbuilder($rid);
        list($cachesql, $cacheparams, $cache) = $report->build_query(false, false, true);
        $this->assertNotEquals($sql, $cachesql);

        $result = array();
        $rs = $DB->get_recordset_sql($cachesql, $cacheparams);
        foreach ($rs as $record) {
            $result[] = $record;
        }
        $this->assertCount(1, $result);
        $this->assertEquals($records, $result);
    }
}
