<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas
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
 * @author Russell England <russell.england@catalyst-eu.net>
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/totara/certification/lib.php');

/**
 * A report builder source for Certifications
 */
class rb_source_dp_certification_history extends rb_base_source {

    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    /**
     * Constructor
     */
    public function __construct() {
        global $DB;
        $activeunique = $DB->sql_concat("'active'", 'id');
        $historyunique = $DB->sql_concat("'history'", 'id');
        $sql = '(SELECT ' . $activeunique . ' AS id,
                1 AS active,
                id AS completionid,
                certifid,
                userid,
                timecompleted,
                timeexpires
                FROM {certif_completion}
                UNION
                SELECT ' . $historyunique . ' AS id,
                0 AS active,
                id AS completionid,
                certifid,
                userid,
                timecompleted,
                timeexpires
                FROM {certif_completion_history}
                WHERE unassigned = 0)';
        $this->base = $sql;
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = array();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_dp_certification_history');
        parent::__construct();
    }


    //
    //
    // Methods for defining contents of source
    //
    //

    /**
     * Creates the array of rb_join objects required for this->joinlist
     *
     * @global object $CFG
     * @return array
     */
    protected function define_joinlist() {
        global $CFG;

        $joinlist = array();

        // to get access to position type constants
        require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_join.php');

        $joinlist[] = new rb_join(
                'prog',
                'LEFT',
                '{prog}',
                'prog.certifid = base.certifid',
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                array('base')
        );

        $joinlist[] = new rb_join(
                'prog_completion', // Table alias.
                'LEFT', // Type of join.
                '{prog_completion}',
                '(prog_completion.programid = prog.id
                    AND prog_completion.coursesetid = 0
                    AND prog_completion.userid = base.userid)',
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                array('base')
        );

        $joinlist[] =  new rb_join(
                'completion_organisation',
                'LEFT',
                '{org}',
                'completion_organisation.id = prog_completion.organisationid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                array('prog', 'prog_completion')
        );

        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_manager_tables_to_joinlist($joinlist, 'position_assignment', 'reportstoid');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_course_category_table_to_joinlist($joinlist, 'prog', 'category');

        return $joinlist;
    }


    /**
     * Creates the array of rb_column_option objects required for
     * $this->columnoptions
     *
     * @return array
     */
    protected function define_columnoptions() {
        $columnoptions = array();

        $columnoptions[] = new rb_column_option(
                'prog',
                'fullname',
                get_string('certificationname', 'totara_program'),
                'prog.fullname',
                array(
                    'joins' => 'prog',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
        );

        $columnoptions[] = new rb_column_option(
                'prog',
                'fullnamelink',
                get_string('certificationname', 'totara_program'),
                "prog.fullname",
                array(
                    'joins' => 'prog',
                    'defaultheading' => get_string('certificationname', 'totara_program'),
                    'displayfunc' => 'link_program_icon',
                    'extrafields' => array(
                        'programid' => 'prog.id',
                        'program_icon' => "prog.icon",
                        'program_visible' => 'prog.visible',
                        'program_audiencevisible' => 'prog.audiencevisible',
                        'userid' => 'base.userid',
                    ),
                )
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'active',
                get_string('current', 'rb_source_dp_certification_history'),
                'base.active',
                array(
                    'displayfunc' => 'yes_or_no',
                )
        );

        $columnoptions[] = new rb_column_option(
                'prog',
                'shortname',
                get_string('programshortname', 'totara_program'),
                'prog.shortname',
                array(
                    'joins' => 'prog',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
        );

        $columnoptions[] = new rb_column_option(
                'prog',
                'idnumber',
                get_string('programidnumber', 'totara_program'),
                'prog.idnumber',
                array(
                    'joins' => 'prog',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'certifid',
                get_string('certificationid', 'rb_source_dp_certification'),
                'base.certifid'
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'timecompleted',
                get_string('timecompleted', 'rb_source_dp_certification'),
                'base.timecompleted',
                array(
                    'displayfunc' => 'nice_date',
                    'dbdatatype' => 'timestamp'
                )
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'timeexpires',
                get_string('timeexpires', 'rb_source_dp_certification'),
                'base.timeexpires',
                array(
                    'displayfunc' => 'nice_date',
                    'dbdatatype' => 'timestamp'
                )
        );

        // Include some standard columns.
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_cohort_user_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions, 'course_category', 'prog');

        return $columnoptions;
    }


    /**
     * Creates the array of rb_filter_option objects required for $this->filteroptions
     * @return array
     */
    protected function define_filteroptions() {
        $filteroptions = array();

        $filteroptions[] = new rb_filter_option(
                'prog',
                'fullname',
                get_string('certificationname', 'totara_program'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'base',
                'active',
                get_string('current', 'rb_source_dp_certification_history'),
                'select',
                array(
                    'selectfunc' => 'yesno_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
        );

        $filteroptions[] = new rb_filter_option(
                'prog',
                'shortname',
                get_string('programshortname', 'totara_program'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'prog',
                'idnumber',
                get_string('programidnumber', 'totara_program'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'base',
                'certifid',
                get_string('certificationid', 'rb_source_dp_certification'),
                'int'
        );

        $filteroptions[] = new rb_filter_option(
                'base',
                'timecompleted',
                get_string('timecompleted', 'rb_source_dp_certification'),
                'date'
        );

        $filteroptions[] = new rb_filter_option(
                'base',
                'timeexpires',
                get_string('timeexpires', 'rb_source_dp_certification'),
                'date'
        );

        $this->add_user_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_cohort_user_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions);

        return $filteroptions;
    }


    /**
     * Creates the array of rb_content_option object required for $this->contentoptions
     * @return array
     */
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

        // Include the rb_user_content content options for this report
        $contentoptions[] = new rb_content_option(
            'user',
            get_string('users'),
            array(
                'userid' => 'base.userid',
                'managerid' => 'position_assignment.managerid',
                'managerpath' => 'position_assignment.managerpath',
                'postype' => 'position_assignment.type',
            ),
            'position_assignment'
        );
        return $contentoptions;
    }


    protected function define_paramoptions() {
        global $CFG;

        $paramoptions = array();
        require_once($CFG->dirroot.'/totara/plan/lib.php');

        $paramoptions[] = new rb_param_option(
                'userid',
                'base.userid'
        );
        $paramoptions[] = new rb_param_option(
                'certifid',
                'base.certifid'
        );
        $paramoptions[] = new rb_param_option(
                'active',
                'base.active'
        );
        $paramoptions[] = new rb_param_option(
                'visible',
                'prog.visible',
                'prog'
        );
        $paramoptions[] = new rb_param_option(
                'category',
                'prog.category',
                'prog'
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
                'type' => 'prog',
                'value' => 'fullnamelink',
            ),
            array(
                'type' => 'course_category',
                'value' => 'namelink',
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
                'type' => 'prog',
                'value' => 'fullname',
                'advanced' => 0,
            ),
            array(
                'type' => 'course_category',
                'value' => 'path',
                'advanced' => 0,
            ),
        );
        return $defaultfilters;
    }


    function rb_display_link_program_icon($certificationname, $row) {
        $program = new program($row->programid);
        return $program->display_link_program_icon($certificationname, $row->programid, $row->program_icon, $row->userid);
    }
}
