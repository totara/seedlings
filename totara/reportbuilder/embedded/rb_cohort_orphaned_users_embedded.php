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
global $CFG;
require_once($CFG->dirroot.'/cohort/lib.php');

class rb_cohort_orphaned_users_embedded extends rb_base_embedded {
    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;

    public function __construct($data) {
        $this->url = '/totara/cohort/orphanreport.php';
        $this->source = 'cohort_orphaned_users';
        $this->shortname = 'cohort_orphaned_users';
        $this->fullname = get_string('orphanedusersreport', 'totara_cohort');
        $this->columns = array(
            array(
                'type' => 'user',
                'value' => 'namelinkicon',
                'heading' => get_string('userfullname', 'totara_reportbuilder')
            ),
            array(
                'type' => 'user',
                'value' => 'idnumber',
                'heading' => get_string('useridnumber', 'totara_reportbuilder')
            ),
            array(
                'type' => 'user',
                'value' => 'email',
                'heading' => get_string('useremail', 'totara_reportbuilder')
            ),
            array(
                'type' => 'user',
                'value' => 'position',
                'heading' => get_string('userspos', 'totara_reportbuilder')
            ),
            array(
                'type' => 'user',
                'value' => 'organisation',
                'heading' => get_string('usersorgname', 'totara_reportbuilder')
            ),
        );

        // no filters
        $this->filters = array(
            array(
                'type' => 'user',
                'value' => 'fullname',
                'advanced' => 0,
            ),
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
        $context = context_system::instance();
        return has_capability('moodle/cohort:view', $context, $reportfor);
    }
}
