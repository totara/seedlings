
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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage totaracore
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

global $CFG;
require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');

class completion_test extends advanced_testcase {
    protected $user_man, $user_rpl, $course, $mod1, $mod2, $comp_rpl, $comp_man, $modcomp1_man, $modcomp2_man, $now;



    protected function setUp() {
        global $DB;

        parent::setUp();

        $this->now = time();

        // Create test users.
        $this->user_man = $this->getDataGenerator()->create_user();
        $this->user_rpl = $this->getDataGenerator()->create_user();

        // Create test course.
        $record = new stdClass();
        $record->enablecompletion = 1;
        $this->course = $this->getDataGenerator()->create_course($record);

        // Add test modules to the course.
        $record = new stdClass();
        $record->course = $this->course->id;
        $record->completion = 2;
        $this->mod1 = $this->getDataGenerator()->create_module('choice', $record);
        $this->mod2 = $this->getDataGenerator()->create_module('choice', $record);

        // Set up the courses completion criteria based on both modules.
        $data = new stdClass();
        $data->id = $this->course->id;
        $data->overall_aggregation = COMPLETION_AGGREGATION_ALL;
        $data->criteria_activity_value = array($this->mod1->id => 1, $this->mod2->id => 1);
        $criterion = new completion_criteria_activity();
        $criterion->update_config($data);

        // Assign users to the course.
        $this->getDataGenerator()->enrol_user($this->user_man->id, $this->course->id);
        $this->getDataGenerator()->enrol_user($this->user_rpl->id, $this->course->id);

        // Create completion objects.
        $this->comp_rpl = new stdClass();
        $this->comp_rpl->userid = $this->user_rpl->id;
        $this->comp_rpl->course = $this->course->id;
        $this->comp_rpl->timeenrolled = $this->now;
        $this->comp_rpl->timestarted = $this->now;
        $this->comp_rpl->timecompleted = $this->now;
        $this->comp_rpl->rpl = 'ripple';
        $this->comp_rpl->status = COMPLETION_STATUS_COMPLETEVIARPL;

        $this->comp_man = new stdClass();
        $this->comp_man->userid = $this->user_man->id;
        $this->comp_man->course = $this->course->id;
        $this->comp_man->timeenrolled = $this->now;
        $this->comp_man->timestarted = $this->now;
        $this->comp_man->timecompleted = $this->now;
        $this->comp_man->status = COMPLETION_STATUS_COMPLETE;
    }

    /**
     * This tests maintaining RPL completion data when an activity is reset.
     */
    public function test_modupdate_rpl() {
        global $DB;

        $this->resetAfterTest();

        $DB->insert_record('course_completions', $this->comp_rpl);

        // Make sure the record is there.
        $this->assertEquals(1, $DB->count_records('course_completions', array('course' => $this->course->id)));

        // Trigger completion refresh for course module.
        $mod = $DB->get_record('course_modules', array('id' => $this->mod1->id));
        $completion = new completion_info($this->course);
        $completion->reset_all_state($mod);

        // Make sure the record is still there, creates an empty record for other user.
        $this->assertEquals(2, $DB->count_records('course_completions', array('course' => $this->course->id)));

        // Verify the data is intact.
        $comp = $DB->get_record('course_completions', array('userid' => $this->user_rpl->id));
        $this->assertEquals('ripple', $comp->rpl);
        $this->assertEquals($this->now, $comp->timecompleted);
    }

    /**
     * This tests that an incorrect non RPL completion is reset correctly.
     */
    public function test_modupdate_manual_reset() {
        global $DB;

        $this->resetAfterTest();
        $completion = new completion_info($this->course);

        $DB->insert_record('course_completions', $this->comp_man);

        // Make sure the record is there.
        $this->assertEquals(1, $DB->count_records('course_completions', array('course' => $this->course->id)));

        // Trigger completion refresh for course module.
        $mod = $DB->get_record('course_modules', array('id' => $this->mod1->id));
        $completion->reset_all_state($mod);

        // Make sure the record is still there, creates an empty record for other user.
        $this->assertEquals(2, $DB->count_records('course_completions', array('course' => $this->course->id)));

        // Verify the data has been reset.
        $comp = $DB->get_record('course_completions', array('userid' => $this->user_man->id));
        $this->assertEquals('', $comp->rpl);
        $this->assertEquals(null, $comp->timecompleted);
    }

    /**
     * This tests that a correct non RPL completion is maintained through reset correctly.
     */
    public function test_modupdate_manual_maintain() {
        global $DB;

        $this->resetAfterTest();
        $completion = new completion_info($this->course);

        // We havent made them yet so there shouldnt be any criteria records.
        $this->assertEquals(0, $DB->count_records('course_completion_crit_compl', array('course' => $this->course->id)));

        // Now mark the user as complete correctly, by completing each criteria.
        $critids = $DB->get_fieldset_select('course_completion_criteria', 'id', 'course = ?', array($this->course->id));
        foreach ($critids as $critid) {
            $params = array(
                'userid' => $this->user_man->id,
                'course' => $this->course->id,
                'criteriaid' => $critid
            );
            $critcomp = new completion_criteria_completion($params);
            $critcomp->mark_complete();
        }

        // There should now be 2 criteria completion records.
        $this->assertEquals(2, $DB->count_records('course_completion_crit_compl', array('course' => $this->course->id)));

        // And they should both be marked as complete and belong to user_man.

        // And one completed course completion for the manual user.
        $this->assertEquals(1, $DB->count_records('course_completions', array('course' => $this->course->id)));

        // Reset one of the activity modules.
        $mod = $DB->get_record('course_modules', array('id' => $this->mod1->id));
        $completion->reset_all_state($mod);

        // After the reset there should still be the two records from before, and an empty one for the rpl user.
        $this->assertEquals(3, $DB->count_records('course_completion_crit_compl', array('course' => $this->course->id)));

        // Check that user_man is still marked as complete.
        $records = $DB->get_records('course_completion_crit_compl', array('userid' => $this->user_man->id));
        foreach ($records as $record) {
            $this->assertGreaterThanOrEqual(0, $record->timecompleted);
        }

        // Check there is now 2 course completion records.
        $this->assertEquals(2, $DB->count_records('course_completions', array('course' => $this->course->id)));

        // And that user_man has been marked for re-aggregation.
        $records = $DB->get_records('course_completions', array('userid' => $this->user_man->id));
        foreach ($records as $record) {
            $this->assertEquals(null, $record->timecompleted);
            $this->assertGreaterThanOrEqual($this->now, $record->reaggregate);
        }
    }
}
