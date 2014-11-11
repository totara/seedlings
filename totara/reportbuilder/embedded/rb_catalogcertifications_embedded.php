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
require_once($CFG->dirroot.'/totara/reportbuilder/embedded/rb_findprograms_embedded.php');

class rb_catalogcertifications_embedded extends rb_base_embedded {
    public function __construct($data) {
        $this->url = '/totara/coursecatalog/certifications.php';
        $this->source = 'certification';
        $this->shortname = 'catalogcertifications';
        $this->fullname = get_string('catalogcertifications', 'totara_coursecatalog');

        $this->columns = array(
            array(
                'type' => 'prog',
                'value' => 'progexpandlink',
                'heading' => get_string('certifname', 'totara_certification')
            ),
            array(
                'type' => 'prog',
                'value' => 'summary',
                'heading' => get_string('programsummary', 'totara_certification')
            )
        );

        // No filters.
        $this->filters = array();

        $this->toolbarsearchcolumns = array(
            array(
                'type' => 'prog',
                'value' => 'fullname'
            ),
            array(
                'type' => 'prog',
                'value' => 'summary'
            )
        );

        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_ALL;

        $this->contentsettings = array(
            'prog_availability' => array(
                'enable' => 1
            )
        );

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

        if (has_capability('totara/certification:createcertification', $catcontext)) {
            $createurl = new moodle_url("/totara/program/add.php", array('category' => $defaultcat, 'iscertif' => 1));
            $createbutton = new single_button($createurl, get_string('addcertification', 'totara_coursecatalog'), 'get');
            return $OUTPUT->render($createbutton);
        }

        return false;
    }
}
