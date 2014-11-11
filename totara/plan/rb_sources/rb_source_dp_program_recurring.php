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
 * @subpackage plan
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

// needed for approval constants etc
require_once($CFG->dirroot . '/totara/plan/lib.php');
// needed for instatiating and checking programs
require_once($CFG->dirroot . '/totara/program/lib.php');

class rb_source_dp_program_recurring extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;
    public $sourcewhere;

    function __construct() {
        $this->base = '{prog_completion_history}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_dp_program_recurring');
        // only consider whole programs - not courseset completion
        $this->sourcewhere = 'base.coursesetid = 0';
        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    protected function define_joinlist() {

        $joinlist = array(
            new rb_join(
                'prog', // table alias
                'LEFT', // type of join
                '{prog}',
                '(base.programid = prog.id AND prog.certifid IS NULL)', // how it is joined
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
        );

        $joinlist[] =  new rb_join(
                'completion_organisation',
                'LEFT',
                '{org}',
                'completion_organisation.id = base.organisationid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
        );
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_manager_tables_to_joinlist($joinlist, 'position_assignment', 'reportstoid');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');

        return $joinlist;
    }

    protected function define_columnoptions() {
        $columnoptions = array();

        $columnoptions[] = new rb_column_option(
            'program',
            'fullname',
            get_string('programname', 'totara_program'),
            "prog.fullname",
            array('joins' => 'prog',
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'program',
            'proglinkicon',
            get_string('prognamelinkedicon', 'totara_program'),
            "prog.fullname",
            array(
                'joins' => 'prog',
                'displayfunc' => 'link_program_icon',
                'defaultheading' => get_string('programname', 'totara_program'),
                'extrafields' => array(
                    'programid' => "prog.id",
                    'program_icon' => "prog.icon",
                    'program_visible' => 'prog.visible',
                    'program_audiencevisible' => 'prog.audiencevisible',
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'program',
            'shortname',
            get_string('programshortname', 'totara_program'),
            "prog.shortname",
            array('joins' => 'prog',
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'program',
            'idnumber',
            get_string('programidnumber', 'totara_program'),
            "prog.idnumber",
            array('joins' => 'prog',
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'program',
            'id',
            get_string('programid', 'totara_program'),
            "base.programid"
        );

        $columnoptions[] = new rb_column_option(
            'program_completion_history',
            'courselink',
            get_string('coursenamelink', 'totara_program'),
            "base.recurringcourseid",
            array(
                'displayfunc' => 'link_course_name',
            )
        );

        $columnoptions[] = new rb_column_option(
            'program_completion_history',
            'status',
            get_string('completionstatus', 'totara_program'),
            "base.status",
            array(
                'displayfunc' => 'program_completion_status',
                'extrafields' => array(
                    'programid' => "base.id",
                    'userid' => "base.userid"
                )
            )
        );

        $columnoptions[] = new rb_column_option(
            'program_completion_history',
            'timecompleted',
            get_string('completiondate', 'totara_program'),
            "base.timecompleted",
            array(
                'displayfunc' => 'completion_date',
                'dbdatatype' => 'timestamp',
            )
        );

        $columnoptions[] = new rb_column_option(
            'program_completion_history',
            'timedue',
            get_string('duedate', 'totara_program'),
            "base.timedue",
            array(
                'displayfunc' => 'completion_date',
                'dbdatatype' => 'timestamp',
            )
        );

        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_cohort_user_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    function rb_display_link_program_icon($programname, $row) {
        $program = new program($row->programid);
        return $program->display_link_program_icon($programname, $row->programid, $row->program_icon);
    }


    function rb_display_program_completion_status($status,$row) {
        global $OUTPUT;

        if ($status == STATUS_PROGRAM_COMPLETE) {
            return get_string('complete', 'totara_program');
        } else if ($status == STATUS_PROGRAM_INCOMPLETE) {
            return $OUTPUT->error_text(get_string('incomplete', 'totara_program'));
        } else {
            return get_string('unknownstatus', 'totara_program');
        }

    }

    function rb_display_completion_date($time) {
        if ($time == 0) {
            return '';
        } else {
            return userdate($time, get_string('datepickerlongyearphpuserdate', 'totara_core'));
        }
    }

    function rb_display_link_course_name($courseid) {
        global $DB, $OUTPUT;

        $html = '';

        if ($course = $DB->get_record('course', array('id' => $courseid))) {
            $html = $OUTPUT->action_link(new moodle_url('/course/view.php', array('id' => $course->id)), format_string($course->fullname));
        } else {
            $html = get_string('coursenotfound', 'totara_plan');
        }

        return $html;
    }

    protected function define_filteroptions() {
        $filteroptions = array();
        $filteroptions[] = new rb_filter_option(
                'program',
                'fullname',
                get_string('programname', 'totara_program'),
                'text'
            );
        $filteroptions[] = new rb_filter_option(
                'program',
                'shortname',
                get_string('programshortname', 'totara_program'),
                'text'
            );
        $filteroptions[] = new rb_filter_option(
                'program',
                'idnumber',
                get_string('programidnumber', 'totara_program'),
                'text'
            );
        $filteroptions[] = new rb_filter_option(
                'program',
                'id',
                get_string('programid', 'totara_program'),
                'int'
            );
        $filteroptions[] = new rb_filter_option(
                'program_completion_history',
                'timedue',
                get_string('programduedate', 'totara_program'),
                'date'
            );
        $filteroptions[] = new rb_filter_option(
                'program_completion_history',
                'timecompleted',
                get_string('completiondate', 'totara_program'),
                'date'
            );

        $this->add_user_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_cohort_user_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    protected function define_contentoptions() {
        $contentoptions = array(
            new rb_content_option(
                'current_pos',
                get_string('currentpos', 'totara_reportbuilder'),
                'position.path',
                'position'
            ),
            new rb_content_option(
                'current_org',
                get_string('currentorg', 'totara_reportbuilder'),
                'organisation.path',
                'organisation'
            ),
            new rb_content_option(
                'completed_org',
                get_string('orgwhencompleted', 'rb_source_course_completion_by_org'),
                'completion_organisation.path',
                'completion_organisation'
            )
        );
        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'programid',
                'base.programid'
            ),
            new rb_param_option(
                'visible',
                'prog.visible',
                'prog'
            ),
            new rb_param_option(
                'userid',
                'base.userid'
            ),
        );

        $paramoptions[] = new rb_param_option(
                'programstatus',
                'base.status'
        );

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
            ),
            array(
                'type' => 'program_completion_history',
                'value' => 'courselink',
            ),
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'user',
                'value' => 'fullname',
                'advanced' => 0,
            ),
            array(
                'type' => 'program',
                'value' => 'fullname',
                'advanced' => 0,
            ),
        );
        return $defaultfilters;
    }

    protected function define_requiredcolumns() {
        $requiredcolumns = array();
        return $requiredcolumns;
    }


    //
    //
    // Source specific column display methods
    //
    //


    //
    //
    // Source specific filter display methods
    //
    //



} // end of rb_source_dp_program_recurring class
