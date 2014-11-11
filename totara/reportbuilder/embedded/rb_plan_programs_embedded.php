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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

require_once($CFG->dirroot . '/totara/program/lib.php');

class rb_plan_programs_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;

    public function __construct($data) {

        $userid = array_key_exists('userid', $data) ? $data['userid'] : null;
        $rolstatus = array_key_exists('rolstatus', $data) ? $data['rolstatus'] : null;
        $exceptionstatus = array_key_exists('exceptionstatus', $data) ? $data['exceptionstatus'] : null;

        $this->url = '/totara/plan/record/programs.php';
        $this->source = 'dp_program';
        $this->shortname = 'plan_programs';
        $this->fullname = get_string('recordoflearningprograms', 'totara_plan');
        $this->columns = array(
            array(
                'type' => 'program',
                'value' => 'proglinkicon',
                'heading' => get_string('programname', 'totara_program'),
            ),
            array(
                'type' => 'program',
                'value' => 'mandatory',
                'heading' => get_string('mandatory', 'totara_program'),
            ),
            array(
                'type' => 'program',
                'value' => 'recurring',
                'heading' => get_string('recurring', 'totara_program'),
            ),
            array(
                'type' => 'program',
                'value' => 'timedue',
                'heading' => get_string('duestatus', 'totara_program'),
            ),
            array(
                'type' => 'program_completion_history',
                'value' => 'program_previous_completion',
                'heading' => get_string('program_previous_completion', 'rb_source_dp_program'),
            ),
            array(
                'type' => 'program_completion',
                'value' => 'status',
                'heading' => get_string('progress', 'totara_program'),
            ),
            array(
                'type' => 'program_completion',
                'value' => 'starteddate',
                'heading' => get_string('starteddate', 'rb_source_program_completion'),
            ),
            array(
                'type' => 'program_completion',
                'value' => 'completeddate',
                'heading' => get_string('completeddate', 'rb_source_program_completion'),
            ),
        );

        $this->filters = array(
            array(
                'type' => 'program',
                'value' => 'fullname',
                'advanced' => 0,
            ),
            array(
                'type' => 'course_category',
                'value' => 'path',
                'advanced' => 1,
            ),
            array(
                'type' => 'program_completion_history',
                'value' => 'program_completion_history_count',
                'advanced' => 1,
            ),
        );

        // no restrictions
        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_NONE;

        // don't include the front page (site-level course)
        $this->embeddedparams = array(
            'category' => '!0',
        );

        if (isset($userid)) {
            $this->embeddedparams['userid'] = $userid;
        }

        if (isset($rolstatus)) {
            $this->embeddedparams['programstatus'] = null;
            switch ($rolstatus) {
                case 'active':
                    $this->embeddedparams['programstatus'] = '!'.STATUS_PROGRAM_COMPLETE;
                break;
                case 'completed':
                    $this->embeddedparams['programstatus'] = STATUS_PROGRAM_COMPLETE;
                break;
            }
            $this->embeddedparams['rolstatus'] = $rolstatus;
        }

        if (isset($exceptionstatus)) {
            $this->embeddedparams['exceptionstatus'] = $exceptionstatus;
        }

        $context = context_system::instance();
        if (!has_capability('totara/program:viewhiddenprograms', $context)) {
            // don't show hidden programs to non-admins
            $this->embeddedparams['visible'] = 1;
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
