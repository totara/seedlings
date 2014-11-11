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
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @package totara
 * @subpackage reportbuilder
 */

class rb_facetoface_interest_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;

    public function __construct($data) {
        $this->url = '/mod/facetoface/interestreport.php';
        $this->source = 'facetoface_interest';
        $this->shortname = 'facetoface_interest';
        $this->fullname = get_string('declareinterestreport', 'mod_facetoface');
        $this->columns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
                'heading' => get_string('name', 'rb_source_user'),
            ),
            array(
                'type' => 'user',
                'value' => 'email',
                'heading' => get_string('useremail', 'totara_reportbuilder'),
            ),
            array(
                'type' => 'facetoface',
                'value' => 'timedeclared',
                'heading' => get_string('declareinterestreportdate', 'rb_source_facetoface_interest'),
            ),
            array(
                'type' => 'facetoface',
                'value' => 'reason',
                'heading' => get_string('declareinterestreportreason', 'rb_source_facetoface_interest'),
            ),
        );

        $this->filters = array(
            array(
                'type' => 'facetoface',
                'value' => 'reason',
                'advanced' => 0,
            ),
        );

        // No restrictions.
        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_NONE;

        $facetofaceid = array_key_exists('facetofaceid', $data) ? $data['facetofaceid'] : null;
        if ($facetofaceid != null) {
            $this->embeddedparams['facetofaceid'] = $facetofaceid;
        }

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
        $facetofaceid = $report->get_param_value('facetofaceid');

        if ($facetofaceid) {
            $cm = get_coursemodule_from_instance('facetoface', $facetofaceid);

            // Users can only view this report if they have the viewinterestreport capability for this context.
            return (has_capability('mod/facetoface:viewinterestreport', context_module::instance($cm->id), $reportfor));
        } else {
            return true;
        }
    }
}
