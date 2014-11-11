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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_program_completion extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;
    public $sourcewhere;

    function __construct() {
        global $CFG;
        $this->base = '{prog_completion}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle','rb_source_program_completion');
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
        global $CFG;

        $joinlist = array(
            new rb_join(
                'program',
                'INNER',
                '{prog}',
                "program.id = base.programid",
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'base'
            ),
            new rb_join(
                'completion_organisation',
                'LEFT',
                '{org}',
                'completion_organisation.id = base.organisationid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'completion_position',
                'LEFT',
                '{pos}',
                'completion_position.id = base.positionid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
        );

        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        // requires the position_assignment join
        $this->add_manager_tables_to_joinlist($joinlist,
            'position_assignment', 'reportstoid');
        $this->add_course_category_table_to_joinlist($joinlist, 'program', 'category');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_cohort_program_tables_to_joinlist($joinlist, 'base', 'programid');

        return $joinlist;
    }

    protected function define_columnoptions() {
        $columnoptions = array();

        $columnoptions[] = new rb_column_option(
            'progcompletion',
            'status',
            get_string('programcompletionstatus','rb_source_program_completion'),
            "base.status",
            array(
                'displayfunc' => 'program_completion_status'
            )
        );
        $columnoptions[] = new rb_column_option(
            'progcompletion',
            'starteddate',
            get_string('starteddate', 'rb_source_program_completion'),
            'base.timestarted',
            array('displayfunc' => 'prog_date', 'dbdatatype' => 'timestamp')
        );
        $columnoptions[] = new rb_column_option(
            'progcompletion',
            'completeddate',
            get_string('completeddate', 'rb_source_program_completion'),
            'base.timecompleted',
            array('displayfunc' => 'prog_date', 'dbdatatype' => 'timestamp')
        );
        $columnoptions[] = new rb_column_option(
            'progcompletion',
            'duedate',
            get_string('duedate', 'rb_source_program_completion'),
            'base.timedue',
            array('displayfunc' => 'prog_date', 'dbdatatype' => 'timestamp')
        );

        $columnoptions[] =new rb_column_option(
            'progcompletion',
            'organisationid',
            get_string('completionorgid', 'rb_source_program_completion'),
            'base.organisationid'
        );
        $columnoptions[] =new rb_column_option(
            'progcompletion',
            'organisationid2',
            get_string('completionorgid', 'rb_source_program_completion'),
            'base.organisationid',
            array('selectable' => false)
        );
        $columnoptions[] =new rb_column_option(
            'progcompletion',
            'organisationpath',
            get_string('completionorgpath', 'rb_source_program_completion'),
            'completion_organisation.path',
            array('joins' => 'completion_organisation', 'selectable' => false)
        );
        $columnoptions[] =new rb_column_option(
            'progcompletion',
            'organisation',
            get_string('completionorgname', 'rb_source_program_completion'),
            'completion_organisation.fullname',
            array('joins' => 'completion_organisation',
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] =new rb_column_option(
            'progcompletion',
            'positionid',
            get_string('completionposid', 'rb_source_program_completion'),
            'base.positionid'
        );
        $columnoptions[] =new rb_column_option(
            'progcompletion',
            'positionid2',
            get_string('completionposid', 'rb_source_program_completion'),
            'base.positionid',
            array('selectable' => false)
        );
        $columnoptions[] =new rb_column_option(
            'progcompletion',
            'positionpath',
            get_string('completionpospath', 'rb_source_program_completion'),
            'completion_position.path',
            array('joins' => 'completion_position', 'selectable' => false)
        );
        $columnoptions[] =new rb_column_option(
            'progcompletion',
            'position',
            get_string('completionposname', 'rb_source_program_completion'),
            'completion_position.fullname',
            array('joins' => 'completion_position',
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );

        // include some standard columns
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions, 'course_category', 'program');
        $this->add_program_fields_to_columns($columnoptions);
        $this->add_cohort_user_fields_to_columns($columnoptions);
        $this->add_cohort_program_fields_to_columns($columnoptions);

        return $columnoptions;
    }


    protected function define_filteroptions() {
        $filteroptions = array();

        $filteroptions[] = new rb_filter_option(
            'progcompletion',
            'starteddate',
            get_string('starteddate', 'rb_source_program_completion'),
            'date'
        );
        $filteroptions[] = new rb_filter_option(
            'progcompletion',
            'completeddate',
            get_string('completeddate', 'rb_source_program_completion'),
            'date'
        );
        $filteroptions[] = new rb_filter_option(
            'progcompletion',
            'duedate',
            get_string('duedate', 'rb_source_program_completion'),
            'date'
        );
        $filteroptions[] = new rb_filter_option(
            'progcompletion',
            'status',
            get_string('programcompletionstatus', 'rb_source_program_completion'),
            'select',
            array (
                'selectchoices' => array(
                    0 => get_string('incomplete', 'totara_program'),
                    1 => get_string('complete', 'totara_program'),
                ),
                'attributes' => rb_filter_option::select_width_limiter(),
                'simplemode' => true,
            )
        );

        $filteroptions[] = new rb_filter_option(
            'progcompletion',
            'organisationid',
            get_string('orgwhencompletedbasic', 'rb_source_program_completion'),
            'select',
            array(
                'selectfunc' => 'organisations_list',
                'attributes' => rb_filter_option::select_width_limiter(),
            )
        );
        $filteroptions[] = new rb_filter_option(
            'progcompletion',
            'organisationid2',
            get_string('multiorgwhencompleted', 'rb_source_program_completion'),
            'hierarchy_multi',
            array(
                'hierarchytype' => 'org',
            )
        );
        $filteroptions[] = new rb_filter_option(
            'progcompletion',
            'organisationpath',
            get_string('orgwhencompleted', 'rb_source_program_completion'),
            'hierarchy',
            array(
                'hierarchytype' => 'org',
            )
        );
        $filteroptions[] = new rb_filter_option(
            'progcompletion',
            'positionid',
            get_string('poswhencompletedbasic', 'rb_source_program_completion'),
            'select',
            array(
                'selectfunc' => 'positions_list',
                'attributes' => rb_filter_option::select_width_limiter()
            )
        );
        $filteroptions[] = new rb_filter_option(
            'progcompletion',
            'positionid2',
            get_string('multiposwhencompleted', 'rb_source_program_completion'),
            'hierarchy_multi',
            array(
                'hierarchytype' => 'pos',
            )
        );
        $filteroptions[] = new rb_filter_option(
            'progcompletion',
            'positionpath',
            get_string('poswhencompleted', 'rb_source_program_completion'),
            'hierarchy',
            array(
                'hierarchytype' => 'pos',
            )
        );

        $this->add_user_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions, 'prog', 'category');
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_program_fields_to_filters($filteroptions);
        $this->add_cohort_user_fields_to_filters($filteroptions);
        $this->add_cohort_program_fields_to_filters($filteroptions);

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
                get_string('orgwhencompleted', 'rb_source_program_completion'),
                'completion_organisation.path',
                'completion_organisation'
            ),
            new rb_content_option(
                'user',
                get_string('user', 'rb_source_course_completion'),
                array(
                    'userid' => 'base.userid',
                    'managerid' => 'position_assignment.managerid',
                    'managerpath' => 'position_assignment.managerpath',
                    'postype' => 'position_assignment.type',
                ),
                'position_assignment'
            ),
            new rb_content_option(
                'date',
                get_string('completeddate', 'rb_source_program_completion'),
                'base.timecompleted'
            ),
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
                'userid',
                'base.userid'
            ),
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
                'value' => 'proglinkicon',
            ),
            array(
                'type' => 'progcompletion',
                'value' => 'status',
            ),
            array(
                'type' => 'progcompletion',
                'value' => 'duedate',
            ),
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'prog',
                'value' => 'fullname',
                'advanced' => 0,
            ),
            array(
                'type' => 'user',
                'value' => 'fullname',
                'advanced' => 0,
            ),
            array(
                'type' => 'progcompletion',
                'value' => 'status',
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


    function rb_display_program_completion_status($status, $row) {
        if (is_null($status)) {
            return '';
        }
        if ($status) {
            return get_string('complete', 'totara_program');
        } else {
            return get_string('incomplete', 'totara_program');
        }
    }

    /**
     * Reformat a timestamp into a date, handling -1 which is used by program code for no date.
     *
     * If not -1 just call the regular date display function.
     *
     * @param integer $date Unix timestamp
     * @param object $row Object containing all other fields for this row
     *
     * @return string Date in a nice format
     */
    public function rb_display_prog_date($date, $row) {
        if ($date == -1) {
            return '';
        } else {
            return $this->rb_display_nice_date($date, $row);
        }
    }

    //
    //
    // Source specific filter display methods
    //
    //


} // end of rb_source_program_completion class
