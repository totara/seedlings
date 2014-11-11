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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 *
 * Unit/functional tests to check Tasks reports caching
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot . '/totara/message/messagelib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');

class totara_reportbuilder_rb_tasks_embedded_cache_testcase extends reportcache_advanced_testcase {
    // testcase data
    protected $report_builder_data = array('id' => 16, 'fullname' => 'Tasks', 'shortname' => 'tasks',
                                           'source' => 'totaramessages', 'hidden' => 1, 'embedded' => 1);

    protected $report_builder_columns_data = array(
                        array('id' => 75, 'reportid' => 16, 'type' => 'message_values', 'value' => 'msgtype',
                              'heading' => 'A', 'sortorder' => 1),
                        array('id' => 76, 'reportid' => 16, 'type' => 'user', 'value' => 'namelink',
                              'heading' => 'B', 'sortorder' => 2),
                        array('id' => 77, 'reportid' => 16, 'type' => 'message_values', 'value' => 'statement',
                              'heading' => 'C', 'sortorder' => 3),
                        array('id' => 78, 'reportid' => 16, 'type' => 'message_values', 'value' => 'sent',
                              'heading' => 'D', 'sortorder' => 4)
                            );

    protected $report_builder_filters_data = array(
        array('reportid' => 16, 'type' => 'message_values', 'value' => 'category', 'sortorder' => 1, 'advanced' => 0)
    );

    // Work data
    protected $user1 = null;
    protected $user2 = null;
    protected $user3 = null;

    /**
     * Prepare mock data for testing
     * - Add 3 users,
     * - Create tasks for users: 3->1, 2-1, 1-2, 1-2 (again), 3-2 (2 tasks to user1, 3 tasks to user2)
     */
    protected function setUp() {
        global $CFG, $DB, $POSITION_CODES, $POSITION_TYPES;

        $this->setAdminUser();
        parent::setup();
        $this->resetAfterTest(true);
        $this->preventResetByRollback();
        $this->cleanup();

        // Common parts of test cases:
        // Create report record in database
        $this->loadDataSet($this->createArrayDataSet(array('report_builder' => array($this->report_builder_data),
                                                           'report_builder_columns' => $this->report_builder_columns_data)));
        // Create three users
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->user3 = $this->getDataGenerator()->create_user();
        $this->user4 = $this->getDataGenerator()->create_user();

        // Create 2 tasks to user1 and 3 to user2
        $this->create_task($this->user3, $this->user1);
        $this->create_task($this->user2, $this->user1);
        $this->create_task($this->user1, $this->user2);
        $this->create_task($this->user3, $this->user2);

        $info = $this->create_task($this->user1, $this->user2);

        phpunit_util::reset_debugging();
        // Add message of different type (not task).
        tm_alert_send($info);
        if (!empty($CFG->messaging)) {
            $this->assertDebuggingCalled();
        }

        $syscontext = context_system::instance();

        // Assign user2 to be user1's manager and remove viewallmessages from manager role.
        $assignment = new position_assignment(
            array(
                'userid'    => $this->user1->id,
                'type'      => $POSITION_CODES[reset($POSITION_TYPES)]
            )
        );
        $assignment->managerid = $this->user2->id;
        assign_user_position($assignment, true);
        $rolemanager = $DB->get_record('role', array('shortname'=>'manager'));
        assign_capability('totara/message:viewallmessages', CAP_PROHIBIT, $rolemanager->id, $syscontext);

        // Assign user3 to course creator role and add viewallmessages to course creator role.
        $rolecoursecreator = $DB->get_record('role', array('shortname'=>'coursecreator'));
        role_assign($rolecoursecreator->id, $this->user3->id, $syscontext);
        assign_capability('totara/message:viewallmessages', CAP_ALLOW, $rolecoursecreator->id, $syscontext);

        $syscontext->mark_dirty();
    }

    protected function tearDown() {
        global $DB;
        $DB->execute('DELETE FROM {user} WHERE id='.$this->user1->id);
        $DB->execute('DELETE FROM {user} WHERE id='.$this->user2->id);
        $DB->execute('DELETE FROM {user} WHERE id='.$this->user3->id);
        $this->cleanup();
    }

    protected function cleanup() {
        global $DB;
        $DB->execute('DELETE FROM {report_builder} WHERE 1=1');
        $DB->execute('DELETE FROM {report_builder_columns} WHERE 1=1');
        $DB->execute('DELETE FROM {message_metadata} WHERE 1=1');
        $DB->execute('DELETE FROM {message_read} WHERE 1=1');
        $DB->execute('DELETE FROM {message} WHERE 1=1');
        reportbuilder_purge_all_cache(true);
    }

    /**
     * Create mock task
     * @param stdClass $from From user
     * @param stdClass $to To user
     */
    protected function create_task($from, $to) {
        global $CFG;
        $ind = rand(0, 1000);
        $event = new stdClass;
        $event->userfrom = $from;
        $event->userto = $to;
        $event->contexturl = 'http://localhost/#' . $ind;
        $event->icon = 'program-approve';
        $event->subject = 'Message #' . $ind;
        $event->fullmessage = 'Full message #' . $ind;
        $event->fullmessagehtml = '<div style="color:red">Full HTML Message #' . $ind . '</div>';
        tm_task_send($event);

        if (!empty($CFG->messaging)) {
            $this->assertDebuggingCalled(); // Caused by sending of message.
        }
        return $event;
    }

    /**
     * Test alerts with/without using cache
     * Test case:
     * - Set up
     * - Prepare cache
     * - Check alerts for first user (2 alertS)
     * - Check alerts for second user (3 alerts)
     * - Check alerts for third user (0 alerts)
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_tasks($usecache) {
        $this->resetAfterTest(true);
        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }
        $useridalias = reportbuilder_get_extrafield_alias('user', 'namelink', 'user_id');
        $result = $this->get_report_result($this->report_builder_data['shortname'], array('userid' => $this->user1->id), $usecache);
        $this->assertCount(2, $result);
        foreach ($result as $r) {
            $this->assertContains($r->$useridalias, array($this->user2->id, $this->user3->id));
        }

        $result = $this->get_report_result($this->report_builder_data['shortname'], array('userid' => $this->user2->id), $usecache);
        $this->assertCount(3, $result);
        foreach ($result as $r) {
            $this->assertContains($r->$useridalias, array($this->user1->id, $this->user3->id));
        }

        $result = $this->get_report_result($this->report_builder_data['shortname'], array('userid' => $this->user3->id), $usecache);
        $this->assertCount(0, $result);
    }

    /**
     * Test failing filters with/without using cache
     * It's retrospective test for filters that failed on some reasons
     *
     * Test case:
     * - Set up
     * - Add filter
     * - Prepare report
     * - Check filter execution
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_filters($usecache) {
        $this->resetAfterTest(true);
        $this->loadDataSet($this->createArrayDataSet(array('report_builder_filters' => $this->report_builder_filters_data)));

        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }
        $form = array('message_values-category' => array('operator' => 1, 'value' => array('program' => 1)));
        $result = $this->get_report_result($this->report_builder_data['shortname'],
                array('userid' => $this->user2->id), $usecache, $form);
        $this->assertCount(3, $result);
    }

    public function test_is_capable() {
        $this->resetAfterTest();

        // Set up report and embedded object for is_capable checks.
        $shortname = $this->report_builder_data['shortname'];
        $report = reportbuilder_get_embedded_report($shortname, array('userid' => $this->user1->id), false, 0);
        $embeddedobject = $report->embedobj;

        // Test admin can access report.
        $this->assertTrue($embeddedobject->is_capable(2, $report),
                'admin cannot access report');

        // Test user1 can access report for self.
        $this->assertTrue($embeddedobject->is_capable($this->user1->id, $report),
                'user cannot access their own report');

        // Test user1's manager can access report (we have removed viewallmessages from manager role).
        $this->assertTrue($embeddedobject->is_capable($this->user2->id, $report),
                'manager cannot access report');

        // Test user3 can access report using viewallmessages (we give 'coursecreator' role access to viewallmessages).
        $this->assertTrue($embeddedobject->is_capable($this->user3->id, $report),
                'user with viewallmessages cannot access report');

        // Test that user4 cannot access the report for another user.
        $this->assertFalse($embeddedobject->is_capable($this->user4->id, $report),
                'user should not be able to access another user\'s report');
    }
}
