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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */

class rb_cohort_admin_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;

    public function __construct($data) {
        $contextid = array_key_exists('contextid', $data) ? $data['contextid'] : null;

        $this->url = '/cohort/index.php';
        $this->source = 'cohort';
        $this->shortname = 'cohort_admin';
        $this->fullname = get_string('cohortadminreportname', 'totara_cohort');
        $this->columns = array(
            array(
                'type' => 'cohort',
                'value' => 'namelink',
                'heading' => get_string('name', 'totara_cohort')
            ),
            array(
                'type' => 'cohort',
                'value' => 'idnumber',
                'heading' => get_string('idnumber', 'totara_cohort')
            ),
            array(
                'type' => 'cohort',
                'value' => 'numofmembers',
                'heading' => get_string('numofmembers', 'totara_cohort')
            ),
            array(
                'type' => 'cohort',
                'value' => 'type',
                'heading' => get_string('type', 'totara_cohort')
            ),
            array(
                'type' => 'cohort',
                'value' => 'startdate',
                'heading' => get_string('startdate', 'totara_cohort')
            ),
            array(
                'type' => 'cohort',
                'value' => 'enddate',
                'heading' => get_string('enddate', 'totara_cohort')
            ),
            array(
                'type' => 'cohort',
                'value' => 'status',
                'heading' => get_string('status', 'totara_cohort')
            ),
            array(
                'type' => 'cohort',
                'value' => 'actions',
                'heading' => get_string('actions', 'totara_cohort')
            )
        );

        // no filters
        $this->filters = array(
            array(
                'type' => 'cohort',
                'value' => 'name',
                'advanced' => 0,
            ),
            array(
                'type' => 'cohort',
                'value' => 'idnumber',
                'advanced' => 1,
            ),
            array(
                'type' => 'cohort',
                'value' => 'type',
                'advanced' => 1,
            ),
        );

        // No restrictions.
        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_NONE;

        // Set the context.
        if (isset($contextid)) {
            $this->embeddedparams['contextid'] = $contextid;
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
        global $DB;

        $contextid = $report->get_param_value('contextid');
        if ($contextid) {
            $context = context::instance_by_id($contextid, MUST_EXIST);
        } else {
            $context = context_system::instance();
        }

        if ($context->contextlevel != CONTEXT_COURSECAT && $context->contextlevel != CONTEXT_SYSTEM) {
            return false;
        }

        if ($context->contextlevel == CONTEXT_COURSECAT) {
            $category = $DB->get_record('course_categories', array('id'=>$context->instanceid));
            if (empty($category)) {
                return false;
            }
        }

        if (!has_capability('moodle/cohort:manage', $context, $reportfor) &&
            !has_capability('moodle/cohort:view', $context, $reportfor)) {
            return false;
        }

        return true;
    }
}
