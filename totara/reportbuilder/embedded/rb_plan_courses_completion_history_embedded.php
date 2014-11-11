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
 * @author Russell England <russell.england@catalyst-eu.net>
 * @package totara
 * @subpackage reportbuilder
 */

class rb_plan_courses_completion_history_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;
    public $defaultsortcolumn, $defaultsortorder;

    public function __construct($data) {
        $userid = array_key_exists('userid', $data) ? $data['userid'] : null;
        $courseid = array_key_exists('courseid', $data) ? $data['courseid'] : null;

        $url = new moodle_url('/totara/plan/record/courses.php', array('history' => 1));

        $this->url = $url->out_as_local_url();
        $this->source = 'dp_course_completion_history';
        $this->shortname = 'plan_courses_completion_history';
        $this->defaultsortcolumn = 'timecompleted';
        $this->fullname = get_string('recordoflearningcoursescompletionhistory', 'totara_plan');
        $this->columns = array(
            array(
                'type' => 'course',
                'value' => 'coursetypeicon',
                'heading' => get_string('coursetypeicon', 'totara_reportbuilder'),
            ),
            array(
                'type' => 'course',
                'value' => 'courselink',
                'heading' => get_string('coursetitle', 'rb_source_dp_course'),
            ),
            array(
                'type' => 'base',
                'value' => 'timecompleted',
                'heading' => get_string('timecompleted', 'rb_source_dp_course_completion_history'),
            ),
            array(
                'type' => 'base',
                'value' => 'grade',
                'heading' => get_string('grade', 'rb_source_dp_course_completion_history'),
            )
        );

        $this->filters = array(
            array(
                'type' => 'course',
                'value' => 'courselink',
                'advanced' => 0,
            ),
            array(
                'type' => 'base',
                'value' => 'timecompleted',
                'advanced' => 0,
            ),
            array(
                'type' => 'base',
                'value' => 'grade',
                'advanced' => 0,
            ),
        );

        // no restrictions
        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_NONE;

        $this->embeddedparams = array();
        if (isset($userid)) {
            $this->embeddedparams['userid'] = $userid;
        }
        if (isset($courseid)) {
            $this->embeddedparams['courseid'] = $courseid;
        }

        parent::__construct();
    }

    /**
     * Check if the user is capable of accessing this report.
     * We use $reportfor instead of $USER->id and $report->get_param_value() instead of getting report params
     * some other way so that the embedded report will be compatible with the scheduler (in the future).
     *
     * @param int $reportfor userid of the user that this report is being generated for
     * @param reportbuilder $report the report object - can use get_param_value to get params
     * @return boolean true if the user can access this report
     */
    public function is_capable($reportfor, $report) {
        global $USER;
        // If no user param passed, assume current user only.
        if (!($subjectid = $report->get_param_value('userid'))) {
            $subjectid = $USER->id;
        }
        // Users can only view their own and their staff's pages or if they are an admin.
        return ($reportfor == $subjectid ||
                totara_is_manager($subjectid, $reportfor) ||
                has_capability('totara/plan:accessanyplan', context_system::instance(), $reportfor) ||
                has_capability('totara/core:viewrecordoflearning', context_user::instance($reportfor), $reportfor));
    }
}
