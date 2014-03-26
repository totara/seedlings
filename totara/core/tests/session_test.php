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
 * @author Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 * @package totara
 * @subpackage totaracore
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

class session_test extends basic_testcase {
    protected $realsession;

    protected $queue_key_data = array('key0', 'key1');

    protected $queue_data = array(
        'key0' => 'data0',
        'key1' => array('data1', 'data2'),
    );

    protected $notification_data = array(
        array(
            'message' => 'message',
        ),
        array(
            'option' => 'option1',
        ),
        'expected_result' => array(
            'message' => 'message',
            'option' => 'option1',
        ),
    );

    // Clear moodle's $SESSION before testing.
    protected function setUp() {
        global $SESSION;
        parent::setUp();

        $this->realsession = $SESSION;

        $SESSION = new stdClass();
    }

    // Restore moodle's $SESSION after testing.
    protected function tearDown() {
        global $SESSION;
        $SESSION = $this->realsession;
    }

    public function test_totara_queue() {
        global $SESSION;

        // Test totara_queue_append.
        $key = $this->queue_key_data[0];
        totara_queue_append($key, $this->queue_data[$key]);
        $this->assertEquals($SESSION->totara_queue[$key][0], $this->queue_data[$key]);

        $key = $this->queue_key_data[1];
        totara_queue_append($key, $this->queue_data[$key][0]);
        totara_queue_append($key, $this->queue_data[$key][1]);
        $this->assertSame($SESSION->totara_queue[$key], $this->queue_data[$key]);

        // Test totara_queue_shift.
        $key = $this->queue_key_data[0];
        $this->assertEquals(totara_queue_shift($key), $this->queue_data[$key]);
        $this->assertNull(totara_queue_shift($key));

        $key = $this->queue_key_data[1];
        $this->assertSame(totara_queue_shift($key, true), $this->queue_data[$key]);
        $this->assertEquals(totara_queue_shift($key, true), array());
    }

    public function test_totara_notifications() {
        global $SESSION;

        // Test notifications without options.
        // Test totara_set_notification.
        totara_set_notification($this->notification_data[0]['message']);
        $this->assertEquals($SESSION->totara_queue['notifications'][0], $this->notification_data[0]);

        // Test totara_get_notifications.
        $this->assertEquals(totara_get_notifications(), array($this->notification_data[0]));
        $this->assertEquals(totara_get_notifications(), array());

        // Test notifications with options.
        // Test totara_set_notification.
        totara_set_notification($this->notification_data[0]['message'], null, $this->notification_data[1]);
        $this->assertEquals($SESSION->totara_queue['notifications'][0]['message'], $this->notification_data['expected_result']['message']);
        $this->assertEquals($SESSION->totara_queue['notifications'][0]['option'], $this->notification_data['expected_result']['option']);

        // Test totara_get_notifications.
        $this->assertEquals(totara_get_notifications(), array($this->notification_data['expected_result']));
        $this->assertEquals(totara_get_notifications(), array());
    }
}
