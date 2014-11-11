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

require_once($CFG->dirroot . '/mod/facetoface/lib.php');

class rb_pastbookings_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;

    public function __construct($data) {
        global $DB;

        $userid = array_key_exists('userid', $data) ? $data['userid'] : null;

        $this->url = '/my/pastbookings.php';
        $this->source = 'facetoface_sessions';
        $this->shortname = 'pastbookings';
        $this->fullname = get_string('mypastbookings', 'totara_core');
        $this->columns = array(
            array(
                'type' => 'course',
                'value' => 'courselink',
                'heading' => get_string('coursename', 'totara_reportbuilder'),
            ),
            array(
                'type' => 'facetoface',
                'value' => 'name',
                'heading' => get_string('sessname', 'rb_source_facetoface_sessions'),
            ),
            array(
                'type' => 'date',
                'value' => 'sessiondate',
                'heading' => get_string('sessdate', 'rb_source_facetoface_sessions'),
            ),
            array(
                'type' => 'date',
                'value' => 'timestart',
                'heading' => get_string('sessstart', 'rb_source_facetoface_sessions'),
            ),
            array(
                'type' => 'date',
                'value' => 'timefinish',
                'heading' => get_string('sessfinish', 'rb_source_facetoface_sessions'),
            ),
            array(
                'type' => 'status',
                'value' => 'statuscode',
                'heading' => get_string('status', 'rb_source_facetoface_sessions'),
            ),
        );

        // only add facilitator column if role exists
        if ($DB->get_field('role', 'id', array('shortname' => 'facilitator'))) {
            $this->columns[] = array(
                'type' => 'role',
                'value' => 'facilitator',
                'heading' => get_string('facilitator', 'rb_source_facetoface_sessions'),
            );
        }

        // no filters
        $this->filters = array();

        // only show future bookings
        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_ALL;
        $this->contentsettings = array(
            'date' => array(
                'enable' => 1,
                'when' => 'past',
            ),
        );

        // also limited to single user by embedded params
        $this->embeddedparams = array(
            'status' => '!' . MDL_F2F_STATUS_USER_CANCELLED,
        );
        if (isset($userid)) {
            $this->embeddedparams['userid'] = $userid;
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
        return true;
    }
}
