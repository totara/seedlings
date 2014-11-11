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
 * feedback module PHPUnit archive test class
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit mod_feedback_archive_testcase mod/feedback/tests/archive_test.php
 *
 * @package    mod_feedback
 * @subpackage phpunit
 * @author     Russell England <russell.england@catalyst-eu.net>
 * @copyright  Catalyst IT Ltd 2013 <http://catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 *
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/feedback/lib.php');
require_once($CFG->libdir . '/completionlib.php');

class mod_feedback_archive_testcase extends advanced_testcase {
    /**
     * Is archive completion supported?
     */
    public function test_module_supports_archive_completion() {
        $this->assertTrue(feedback_supports(FEATURE_ARCHIVE_COMPLETION));
    }

    /**
     * @depends test_module_supports_archive_completion
     */
    public function test_archive() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course
        $this->assertEquals(1, $DB->count_records('course')); // Site course
        $coursedefaults = array('enablecompletion' => COMPLETION_ENABLED);
        $course = $this->getDataGenerator()->create_course($coursedefaults);
        $this->assertEquals(2, $DB->count_records('course')); // Site course + this course

        // Check it has course competion
        $completioninfo = new completion_info($course);
        $this->assertEquals(COMPLETION_ENABLED, $completioninfo->is_enabled());

        // Create a feedback and add it to the course
        $this->assertEquals(0, $DB->count_records('feedback'));
        $completiondefaults = array(
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionview' => COMPLETION_VIEW_REQUIRED
        );
        $feedback = $this->getDataGenerator()->create_module(
                'feedback',
                array('course' => $course->id, 'completionsubmit' => 1), // User must submit feedback for it to complete
                $completiondefaults);
        $this->assertEquals(1, $DB->count_records('feedback'));

        // Create a feedback question - need to create manually because the
        // feedback_item_textfield->save_item() function depends on form->get_data().
        $this->assertEquals(0, $DB->count_records('feedback_item'));
        $item = new stdClass();
        $item->feedback = $feedback->id;
        $item->template = 0;
        $item->name = 'What is 1+1';
        $item->label = 'Whatis11';
        $item->presentation = '0|10';
        $item->typ = 'numeric';
        $item->hasvalue = 1;
        $item->position = 1;
        $item->required = 0;
        $item->dependitem = 0;
        $item->dependvalue = '';
        $item->options = '';
        $itemid = $DB->insert_record('feedback_item', $item);
        $this->assertEquals(1, $DB->count_records('feedback_item'));

        // Create a user
        $this->assertEquals(2, $DB->count_records('user')); // Guest + Admin
        $user = $this->getDataGenerator()->create_user();
        $this->assertEquals(3, $DB->count_records('user')); // Guest + Admin + this user

        // Enrol user on course
        $this->assertTrue($this->getDataGenerator()->enrol_user($user->id, $course->id));

        // Get the course module
        $course_module = get_coursemodule_from_instance('feedback', $feedback->id, $course->id);
        $this->assertEquals(COMPLETION_TRACKING_MANUAL, $completioninfo->is_enabled($course_module));

        // Check it isn't complete
        $params = array('userid' => $user->id, 'coursemoduleid' => $course_module->id);
        $completionstate = $DB->get_field('course_modules_completion', 'completionstate', $params);
        $this->assertEmpty($completionstate);

        // Set viewed
        $completioninfo->set_module_viewed($course_module, $user->id);

        // Enter feedback
        // Save values for the questions - as in /mod/feedback/complete.php
        // feedback_save_values() - depends on optional_params() so need to do save the values manually
        $this->assertEquals(0, $DB->count_records('feedback_completed'));
        $time = time();
        $timemodified = mktime(0, 0, 0, date('m', $time), date('d', $time), date('Y', $time));
        $completed = new stdClass();
        $completed->feedback           = $feedback->id;
        $completed->userid             = $user->id;
        $completed->guestid            = false;
        $completed->timemodified       = $timemodified;
        $completed->anonymous_response = false;
        $completedid = $DB->insert_record('feedback_completed', $completed);
        $this->assertEquals(1, $DB->count_records('feedback_completed'));

        $this->assertEquals(0, $DB->count_records('feedback_value'));
        $itemobj = feedback_get_item_class($item->typ); // $item created above
        $value = new stdClass();
        $value->item = $itemid;
        $value->completed = $completedid;
        $value->course_id = $course->id;
        $value->value = $itemobj->create_value(5); // a numeric value between 0 and 10
        $DB->insert_record('feedback_value', $value);
        $this->assertEquals(1, $DB->count_records('feedback_value'));

        $this->assertEquals(0, $DB->count_records('feedback_tracking'));
        $tracking = new stdClass();
        $tracking->userid = $user->id;
        $tracking->feedback = $feedback->id;
        $tracking->completed = $completedid;
        $DB->insert_record('feedback_tracking', $tracking);
        $this->assertEquals(1, $DB->count_records('feedback_tracking'));

        // Update completion state
        $completioninfo = new completion_info($course);
        if ($completioninfo->is_enabled($course_module) && $feedback->completionsubmit) {
            $completioninfo->update_state($course_module, COMPLETION_COMPLETE);
        }

        // Check its completed
        $completionstate = $DB->get_field('course_modules_completion', 'completionstate', $params, MUST_EXIST);
        $this->assertEquals(COMPLETION_COMPLETE, $completionstate);

        // Archive it
        $this->assertEquals(0, $DB->count_records('feedback_completed_history'));
        $this->assertEquals(0, $DB->count_records('feedback_value_history'));
        feedback_archive_completion($user->id, $course->id);
        // Check there is no feedback
        $this->assertEquals(0, $DB->count_records('feedback_completed'));
        $this->assertEquals(0, $DB->count_records('feedback_value'));
        // Check there is a history record
        $this->assertEquals(1, $DB->count_records('feedback_completed_history'));
        $this->assertEquals(1, $DB->count_records('feedback_value_history'));

        // Check its incomplete
        $completionstate = $DB->get_field('course_modules_completion', 'completionstate', $params, MUST_EXIST);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completionstate);

        // Check we can report on it
        $totalcount = 0;
        $filters = array('page' => 0, 'perpage' => 20, 'coursename' => $course->fullname);
        $archives = feedback_archive_get_list($filters, $totalcount);
        $this->assertNotEmpty($archives);
    }
}
