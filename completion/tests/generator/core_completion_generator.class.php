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
 * @author Maria Torres <maria.torres@totaralms.com>
 * @package completion
 * @subpackage tests_generator
 */

/**
 * Data generator.
 *
 * @package    core_completion
 * @category   test_genertor
 */

defined('MOODLE_INTERNAL') || die();

class core_completion_generator extends component_generator_base {
    /**
     * Set activity completion for the course.
     *
     * @param int $courseid The course id
     * @param array $activities Array of activity objects that will be set for the course completion
     * @param int $activityaggregation One of COMPLETION_AGGREGATION_ALL or COMPLETION_AGGREGATION_ANY
     */
    public function set_activity_completion($courseid, $activities, $activityaggregation = COMPLETION_AGGREGATION_ALL) {
        global $CFG;
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_activity.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria.php');

        $criteriaactivity = array();
        foreach ($activities as $activity) {
            $criteriaactivity[$activity->cmid] = 1;
        }

        if (!empty($criteriaactivity)) {
            $data = new stdClass();
            $data->id = $courseid;
            $data->activity_aggregation = $activityaggregation;
            $data->criteria_activity_value = $criteriaactivity;

            // Set completion criteria activity.
            $criterion = new completion_criteria_activity();
            $criterion->update_config($data);

            // Handle activity aggregation.
            $aggdata = array(
                'course'        => $data->id,
                'criteriatype'  => COMPLETION_CRITERIA_TYPE_ACTIVITY
            );

            $aggregation = new completion_aggregation($aggdata);
            $aggregation->setMethod($data->activity_aggregation);
            $aggregation->save();
        }
    }

    /**
     * Enable completion tracking for this course.
     *
     * @param object $course
     */
    public function enable_completion_tracking($course) {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        // Update course completion settings.
        $course->enablecompletion = COMPLETION_ENABLED;
        $course->completionstartonenrol = 1;
        $course->completionprogressonview = 1;
        update_course($course);
    }
}
