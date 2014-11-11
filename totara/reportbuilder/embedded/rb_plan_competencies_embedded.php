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

class rb_plan_competencies_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;
    public $defaultsortcolumn, $defaultsortorder;

    public function __construct($data) {
        $userid = array_key_exists('userid', $data) ? $data['userid'] : null;
        $compid = array_key_exists('competency', $data) ? $data['competencyid'] : null;
        $rolstatus = array_key_exists('rolstatus', $data) ? $data['rolstatus'] : null;

        $this->url = '/totara/plan/record/competencies.php';
        $this->source = 'dp_competency';
        $this->defaultsortcolumn = 'competency_fullname';
        $this->shortname = 'plan_competencies';
        $this->fullname = get_string('recordoflearningcompetencies', 'totara_plan');
        $this->columns = array(
            array(
                'type' => 'plan',
                'value' => 'planlink',
                'heading' => get_string('plan', 'totara_plan'),
            ),
            array(
                'type' => 'plan',
                'value' => 'status',
                'heading' => get_string('planstatus', 'totara_plan'),
            ),
            array(
                'type' => 'competency',
                'value' => 'fullname',
                'heading' => get_string('competency', 'totara_hierarchy'),
            ),
            array(
                'type' => 'competency',
                'value' => 'priority',
                'heading' => get_string('priority', 'rb_source_dp_competency'),
            ),
            array(
                'type' => 'competency',
                'value' => 'duedate',
                'heading' => get_string('duedate', 'rb_source_dp_competency'),
            ),
            array(
                'type' => 'competency',
                'value' => 'proficiencyandapproval',
                'heading' => get_string('status', 'rb_source_dp_competency'),
            ),
        );

        $this->filters = array(
            array(
                'type' => 'competency',
                'value' => 'fullname',
                'advanced' => 0,
            ),
            array(
                'type' => 'competency',
                'value' => 'priority',
                'advanced' => 1,
            ),
            array(
                'type' => 'competency',
                'value' => 'duedate',
                'advanced' => 1,
            ),
            array(
                'type' => 'plan',
                'value' => 'name',
                'advanced' => 1,
            ),
        );

        // no restrictions
        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_NONE;

        $this->embeddedparams = array();
        if (isset($userid)) {
            $this->embeddedparams['userid'] = $userid;
        }
        if (isset($rolstatus)) {
            $this->embeddedparams['rolstatus'] = $rolstatus;
        }
        if (isset($compid)) {
            $this->embeddedparams['competencyid'] = $compid;
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
