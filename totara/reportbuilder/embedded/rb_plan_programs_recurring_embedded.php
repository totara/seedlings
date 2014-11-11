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
 * @author Ben Lobo <ben@benlobo.co.uk>
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

require_once($CFG->dirroot . '/totara/program/lib.php');

class rb_plan_programs_recurring_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;

    public function __construct($data) {

        $userid = array_key_exists('userid', $data) ? $data['userid'] : null;
        $programid = array_key_exists('programid', $data) ? $data['programid'] : null;

        $this->url = '/totara/plan/record/programs_recurring.php';
        $this->source = 'dp_program_recurring';
        $this->shortname = 'plan_programs_recurring';
        $this->fullname = get_string('recordoflearningprogramsrecurring', 'totara_plan');
        $this->columns = array(
            array(
                'type' => 'program_completion_history',
                'value' => 'courselink',
                'heading' => get_string('coursenamelink', 'totara_program'),
            ),
            array(
                'type' => 'program_completion_history',
                'value' => 'status',
                'heading' => get_string('completionstatus', 'totara_program'),
            ),
            array(
                'type' => 'program_completion_history',
                'value' => 'timecompleted',
                'heading' => get_string('completiondate', 'totara_program'),
            ),
        );

        // no restrictions
        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_NONE;

        if (isset($userid)) {
            $this->embeddedparams['userid'] = $userid;
        }

        if (isset($programid)) {
            $this->embeddedparams['programid'] = $programid;
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
