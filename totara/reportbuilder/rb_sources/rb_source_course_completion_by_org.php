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

class rb_source_course_completion_by_org extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    function __construct() {
        $this->base = '{course_completions}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_course_completion_by_org');

        parent::__construct();
        $this->populate_hierarchy_name_map(array('org'));
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    protected function define_joinlist() {
        global $CFG;

        // to get access to constants
        require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');

        // joinlist for this source
        $joinlist = array(
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
            new rb_join(
                'criteria',
                'LEFT',
                '{course_completion_criteria}',
                '(criteria.course = base.course AND ' .
                    'criteria.criteriatype = ' .
                    COMPLETION_CRITERIA_TYPE_GRADE . ')',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'critcompl',
                'LEFT',
                '{course_completion_crit_compl}',
                '(critcompl.userid = base.userid AND ' .
                    'critcompl.criteriaid = criteria.id AND ' .
                    '(critcompl.deleted IS NULL OR critcompl.deleted = 0))',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'criteria'
            ),
        );

        // include some standard joins
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_course_table_to_joinlist($joinlist, 'base', 'course');
        // requires the course join
        $this->add_course_category_table_to_joinlist($joinlist,
            'course', 'category');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        // requires the position_assignment join
        $this->add_manager_tables_to_joinlist($joinlist,
            'position_assignment', 'reportstoid');
        $this->add_tag_tables_to_joinlist('course', $joinlist, 'base', 'course');

        return $joinlist;
    }

    protected function define_columnoptions() {
        global $DB;

        $columnoptions = array(
            // non-aggregated columns
            new rb_column_option(
                'course_completion',
                'organisationid',
                get_string('completionorgid', 'rb_source_course_completion_by_org'),
                'base.organisationid'
            ),
            new rb_column_option(
                'course_completion',
                'organisationpath',
                get_string('completionorgpath', 'rb_source_course_completion_by_org'),
                'completion_organisation.path',
                array(
                    'joins' => 'completion_organisation',
                )
            ),
            new rb_column_option(
                'course_completion',
                'organisationpathtext',
                get_string('completionorgpathtext', 'rb_source_course_completion_by_org'),
                'completion_organisation.path',
                array(
                    'joins' => 'completion_organisation',
                    'displayfunc' => 'nice_hierarchy_path',
                    'extrafields' => array('hierarchytype' => '\'org\'')
                )
            ),
            new rb_column_option(
                'course_completion',
                'organisation',
                get_string('completionorgname', 'rb_source_course_completion_by_org'),
                'completion_organisation.fullname',
                array('joins' => 'completion_organisation',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            // aggregated columns
            new rb_column_option(
                'user',
                'allparticipants',
                get_string('participants', 'rb_source_course_completion_by_org'),
                $DB->sql_fullname('auser.firstname', 'auser.lastname'),
                array(
                    'joins' => 'auser',
                    'grouping' => 'comma_list_unique',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
            ),
            new rb_column_option(
                'course_completion',
                'total',
                get_string('numofrecords', 'rb_source_course_completion_by_org'),
                'base.id',
                array('grouping' => 'count')
            ),
            new rb_column_option(
                'course_completion',
                'completed',
                get_string('numcompleted', 'rb_source_course_completion_by_org'),
                'CASE WHEN base.timecompleted > 0 AND ' .
                    '(base.rpl IS NULL OR ' .
                    $DB->sql_isempty('base', 'rpl', false, false) .
                    ') THEN 1 ELSE NULL END',
                array('grouping' => 'count')
            ),
            new rb_column_option(
                'course_completion',
                'perccompleted',
                get_string('percentagecompleted', 'rb_source_course_completion_by_org'),
                'CASE WHEN base.timecompleted > 0 AND ' .
                    '(base.rpl IS NULL OR ' .
                    $DB->sql_isempty('base', 'rpl', false, false) .
                    ') THEN 1 ELSE 0 END',
                array('grouping' => 'percent')
            ),
            new rb_column_option(
                'course_completion',
                'completedrpl',
                get_string('numcompletedviarpl', 'rb_source_course_completion_by_org'),
                'CASE WHEN base.timecompleted > 0 AND ' .
                    '(base.rpl IS NOT NULL AND ' .
                    $DB->sql_isnotempty('base', 'rpl', false, false) .
                    ') THEN 1 ELSE NULL END',
                array('grouping' => 'count')
            ),
            new rb_column_option(
                'course_completion',
                'inprogress',
                get_string('numinprogress', 'rb_source_course_completion_by_org'),
                'CASE WHEN base.timestarted > 0 AND ' .
                    '(base.timecompleted IS NULL OR ' .
                    'base.timecompleted = 0) ' .
                    'THEN 1 ELSE NULL END',
                array('grouping' => 'count')
            ),
            new rb_column_option(
                'course_completion',
                'notstarted',
                get_string('numnotstarted', 'rb_source_course_completion_by_org'),
                'CASE WHEN base.timeenrolled > 0 AND ' .
                    '(base.timecompleted IS NULL OR ' .
                    'base.timecompleted = 0) AND ' .
                    '(base.timestarted IS NULL OR ' .
                    'base.timestarted = 0) ' .
                    'THEN 1 ELSE NULL END',
                array('grouping' => 'count')
            ),
            new rb_column_option(
                'course_completion',
                'earliest_completeddate',
                get_string('earliestcompletiondate', 'rb_source_course_completion_by_org'),
                'base.timecompleted',
                array(
                    'displayfunc' => 'nice_date',
                    'dbdatatype' => 'timestamp',
                    'grouping' => 'min',
                )
            ),
            new rb_column_option(
                'course_completion',
                'latest_completeddate',
                get_string('latestcompletiondate', 'rb_source_course_completion_by_org'),
                'base.timecompleted',
                array(
                    'displayfunc' => 'nice_date',
                    'dbdatatype' => 'timestamp',
                    'grouping' => 'max',
                )
            ),
        );

        // include some standard columns
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_course_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_tag_fields_to_columns('course', $columnoptions);

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(
            /*
            // array of rb_filter_option objects, e.g:
            new rb_filter_option(
                '',       // type
                '',       // value
                '',       // label
                '',       // filtertype
                array()   // options
            )
            */
            new rb_filter_option(
                'course_completion',
                'organisationid',
                get_string('officewhencompletedbasic', 'rb_source_course_completion_by_org'),
                'select',
                array(
                    'selectfunc' => 'organisations_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
            new rb_filter_option(
                'course_completion',
                'organisationpath',
                get_string('officewhencompleted', 'rb_source_course_completion_by_org'),
                'hierarchy',
                array(
                    'hierarchytype' => 'org',
                )
            ),
            // aggregated filters
            new rb_filter_option(
                'course_completion',
                'total',
                get_string('totalcompletions', 'rb_source_course_completion_by_org'),
                'number'
            ),
            new rb_filter_option(
                'course_completion',
                'completed',
                get_string('numcompleted', 'rb_source_course_completion_by_org'),
                'number'
            ),
            new rb_filter_option(
                'course_completion',
                'completedrpl',
                get_string('numcompletedviarpl', 'rb_source_course_completion_by_org'),
                'number'
            ),
            new rb_filter_option(
                'course_completion',
                'inprogress',
                get_string('numinprogress', 'rb_source_course_completion_by_org'),
                'number'
            ),
            new rb_filter_option(
                'course_completion',
                'notstarted',
                get_string('numnotstarted', 'rb_source_course_completion_by_org'),
                'number'
            ),
            new rb_filter_option(
                'user',
                'allparticipants',
                get_string('participants', 'rb_source_course_completion_by_org'),
                'text'
            ),
            new rb_filter_option(
                'course_completion',
                'enrolled',
                get_string('isenrolled', 'rb_source_course_completion'),
                'enrol',
                array(),
                // special enrol filter requires a composite field
                array('course' => 'base.course', 'user' => 'base.userid')
            ),
        );

        // include some standard filters
        $this->add_user_fields_to_filters($filteroptions);
        $this->add_course_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_tag_fields_to_filters('course', $filteroptions);

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
            ),
            new rb_content_option(
                'user',
                get_string('user', 'rb_source_course_completion_by_org'),
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
                get_string('completiondate', 'rb_source_course_completion_by_org'),
                'base.timecompleted'
            ),
        );
        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'userid',       // parameter name
                'base.userid',  // field
                null            // joins
            ),
            new rb_param_option(
                'courseid',
                'course.id',
                'course'
            ),
        );

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'course_completion',
                'value' => 'organisation',
            ),
            array(
                'type' => 'course_completion',
                'value' => 'completed',
            ),
            array(
                'type' => 'course_completion',
                'value' => 'total',
            ),
            array(
                'type' => 'course_completion',
                'value' => 'earliest_completeddate',
            ),
            array(
                'type' => 'course_completion',
                'value' => 'latest_completeddate',
            ),
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'course_completion',
                'value' => 'organisationpath',
                'advanced' => 1,
            ),
        );

        return $defaultfilters;
    }

    protected function define_requiredcolumns() {
        $requiredcolumns = array(
            /*
            // array of rb_column objects, e.g:
            new rb_column(
                '',         // type
                '',         // value
                '',         // heading
                '',         // field
                array(),    // options
            )
            */
        );
        return $requiredcolumns;
    }

    //
    //
    // Source specific column display methods
    //
    //

    // add methods here with [name] matching column option displayfunc
    //function rb_display_[name]($item, $row) {
        // variable $item refers to the current item
        // $row is an object containing the whole row
        // which will include any extrafields
        //
        // should return a string containing what should be displayed
    //}

    //
    //
    // Source specific filter display methods
    //
    //


} // end of rb_source_course_completion class

