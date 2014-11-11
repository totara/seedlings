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
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

class rb_appraisal_detail_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $columns, $filters;
    public $contentmode, $embeddedparams;

    public function __construct() {
        $this->url = '/totara/appraisal/detailreport.php';
        $this->source = 'appraisal_detail';
        $this->shortname = 'appraisal_detail';
        $this->fullname = get_string('sourcetitle', 'rb_source_appraisal_detail');

        $this->columns = $this->define_columns();
        $this->filters = $this->define_filters();

        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_NONE;

        parent::__construct();
    }

    /**
     * Check if the user is capable of accessing this report.
     * We use $reportfor instead of $USER->id and $report->get_param_value() instead of getting params
     * some other way so that the embedded report will be compatible with the scheduler (in the future).
     *
     * @param int $reportfor userid of the user that this report is being generated for
     * @param reportbuilder $report the report object - can use get_param_value to get params
     * @return boolean true if the user can access this report
     */
    public function is_capable($reportfor, $report) {
        $systemcontext = context_system::instance();
        return has_capability('totara/appraisal:manageappraisals', $systemcontext, $reportfor);
    }

    protected function define_columns() {
        $columns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
                'heading' => get_string('embeddednameheading', 'rb_source_appraisal')
            ),
            array(
                'type' => 'user',
                'value' => 'managername',
                'heading' => get_string('embeddedmanagerheading', 'rb_source_appraisal')
            ),
            array(
                'type' => 'userappraisal',
                'value' => 'status',
                'heading' => get_string('embeddedstatusheading', 'rb_source_appraisal')
            ),
            array(
                'type' => 'roleall',
                'value' => 'answersall',
                'heading' => get_string('embeddedanswersfromallheading', 'rb_source_appraisal_detail')
            )
        );

        return $columns;
    }

    protected function define_filters() {
        $filters = array(
            array(
                'type' => 'user',
                'value' => 'fullname',
                'advanced' => 0
            ),
            array(
                'type' => 'userappraisal',
                'value' => 'status',
                'advanced' => 0
            ),
            array(
                'type' => 'user',
                'value' => 'managername',
                'advanced' => 0
            )
        );

        return $filters;
    }

}
