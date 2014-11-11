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

require_once($CFG->dirroot.'/totara/reportbuilder/embedded/rb_findcourses_embedded.php');

class rb_catalogcourses_embedded extends rb_base_embedded {
    public function __construct($data) {
        $this->url = '/totara/coursecatalog/courses.php';
        $this->source = 'courses';
        $this->shortname = 'catalogcourses';
        $this->fullname = get_string('catalogcourses', 'totara_coursecatalog');
        $this->defaultsortcolumn = 'course_courselinkicon';

        $this->columns = array(
            array(
                'type' => 'course',
                'value' => 'courseexpandlink',
                'heading' => get_string('coursename', 'totara_reportbuilder')
            ),
            array(
                'type' => 'course',
                'value' => 'startdate',
                'heading' => get_string('report:startdate', 'totara_reportbuilder')
            ),
            array(
                'type' => 'course',
                'value' => 'mods',
                'heading' => get_string('content', 'totara_reportbuilder')
            )
        );

        $this->filters = array(
            array(
                'type' => 'course',
                'value' => 'coursetype',
                'region' => rb_filter_type::RB_FILTER_REGION_SIDEBAR,
                'fieldname' => get_string('type', 'totara_reportbuilder')
            ),
            array(
                'type' => 'course',
                'value' => 'mods',
                'region' => rb_filter_type::RB_FILTER_REGION_SIDEBAR,
                'fieldname' => get_string('content', 'totara_reportbuilder')
            )
        );

        $this->toolbarsearchcolumns = array(
            array(
                'type' => 'course',
                'value' => 'fullname'
            ),
            array(
                'type' => 'course',
                'value' => 'summary'
            )
        );

        // No restrictions.
        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_NONE;

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
        return true;
    }

    public function get_extrabuttons() {
        global $OUTPUT, $CFG;

        $defaultcat = $CFG->defaultrequestcategory;
        $catcontext = context_coursecat::instance($defaultcat);

        if (has_capability('moodle/course:create', $catcontext)) {
            $createurl = new moodle_url("/course/edit.php", array('category' => $defaultcat));
            $createbutton = new single_button($createurl, get_string('addcourse', 'totara_coursecatalog'), 'get');
            return $OUTPUT->render($createbutton);
        }

        return false;
    }
}
