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

class rb_source_course_completion extends rb_base_source {
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
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_course_completion');

        parent::__construct();
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
            new rb_join(
                'grade_items',
                'LEFT',
                '{grade_items}',
                '(grade_items.courseid = base.course AND ' .
                    'grade_items.itemtype = \'course\')',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'grade_grades',
                'LEFT',
                '{grade_grades}',
                '(grade_grades.itemid = grade_items.id AND ' .
                    'grade_grades.userid = base.userid)',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'grade_items'
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
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_cohort_course_tables_to_joinlist($joinlist, 'base', 'course');

        return $joinlist;
    }

    protected function define_columnoptions() {
        $columnoptions = array(
            new rb_column_option(
                'course_completion',
                'status',
                get_string('completionstatus', 'rb_source_course_completion'),
                'base.status',
                array('displayfunc' => 'completion_status')
            ),
            new rb_column_option(
                'course_completion',
                'completeddate',
                get_string('completiondate', 'rb_source_course_completion'),
                'base.timecompleted',
                array('displayfunc' => 'nice_date', 'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'course_completion',
                'starteddate',
                get_string('datestarted', 'rb_source_course_completion'),
                'base.timestarted',
                array('displayfunc' => 'nice_date', 'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'course_completion',
                'enrolleddate',
                get_string('dateenrolled', 'rb_source_course_completion'),
                'base.timeenrolled',
                array('displayfunc' => 'nice_date', 'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'course_completion',
                'organisationid',
                get_string('completionorgid', 'rb_source_course_completion'),
                'base.organisationid'
            ),
            new rb_column_option(
                'course_completion',
                'organisationid2',
                get_string('completionorgid', 'rb_source_course_completion'),
                'base.organisationid',
                array('selectable' => false)
            ),
            new rb_column_option(
                'course_completion',
                'organisationpath',
                get_string('completionorgpath', 'rb_source_course_completion'),
                'completion_organisation.path',
                array('joins' => 'completion_organisation', 'selectable' => false)
            ),
            new rb_column_option(
                'course_completion',
                'organisation',
                get_string('completionorgname', 'rb_source_course_completion'),
                'completion_organisation.fullname',
                array('joins' => 'completion_organisation',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'course_completion',
                'positionid',
                get_string('completionposid', 'rb_source_course_completion'),
                'base.positionid'
            ),
            new rb_column_option(
                'course_completion',
                'positionid2',
                get_string('completionposid', 'rb_source_course_completion'),
                'base.positionid',
                array('selectable' => false)
            ),
            new rb_column_option(
                'course_completion',
                'positionpath',
                get_string('completionpospath', 'rb_source_course_completion'),
                'completion_position.path',
                array('joins' => 'completion_position', 'selectable' => false)
            ),
            new rb_column_option(
                'course_completion',
                'position',
                get_string('completionposname', 'rb_source_course_completion'),
                'completion_position.fullname',
                array('joins' => 'completion_position',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'course_completion',
                'grade',
                get_string('grade', 'rb_source_course_completion'),
                'grade_grades.finalgrade',
                array(
                    'joins' => 'grade_grades',
                    'extrafields' => array('rplgrade' => 'base.rplgrade', 'course_completion_status' => 'base.status'),
                    'displayfunc' => 'course_grade_percent',
                )
            ),
            new rb_column_option(
                'course_completion',
                'passgrade',
                get_string('passgrade', 'rb_source_course_completion'),
                'criteria.gradepass',
                array(
                    'joins' => 'criteria',
                    'displayfunc' => 'percent',
                )
            ),
            new rb_column_option(
                'course_completion',
                'gradestring',
                get_string('requiredgrade', 'rb_source_course_completion'),
                'grade_grades.finalgrade',
                array(
                    'joins' => array('criteria', 'grade_grades'),
                    'displayfunc' => 'grade_string',
                    'extrafields' => array(
                        'gradepass' => 'criteria.gradepass',
                        'rplgrade'  => 'base.rplgrade',
                        'course_completion_status' => 'base.status'
                    ),
                    'defaultheading' => get_string('grade', 'rb_source_course_completion'),
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
        $this->add_cohort_user_fields_to_columns($columnoptions);
        $this->add_cohort_course_fields_to_columns($columnoptions);

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
                'completeddate',
                get_string('datecompleted', 'rb_source_course_completion'),
                'date'
            ),
            new rb_filter_option(
                'course_completion',
                'starteddate',
                get_string('datestarted', 'rb_source_course_completion'),
                'date'
            ),
            new rb_filter_option(
                'course_completion',
                'enrolleddate',
                get_string('dateenrolled', 'rb_source_course_completion'),
                'date'
            ),
            new rb_filter_option(
                'course_completion',
                'status',
                get_string('completionstatus', 'rb_source_course_completion'),
                'multicheck',
                array(
                    'selectfunc' => 'completion_status_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                    'showcounts' => array(
                            'joins' => array("LEFT JOIN {course_completions} ccs_filter ON base.id = ccs_filter.id"),
                            'dataalias' => 'ccs_filter',
                            'datafield' => 'status')
                )
            ),
            new rb_filter_option(
                'course_completion',
                'organisationid',
                get_string('officewhencompletedbasic', 'rb_source_course_completion'),
                'select',
                array(
                    'selectfunc' => 'organisations_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
            new rb_filter_option(
                'course_completion',
                'organisationpath',
                get_string('orgwhencompleted', 'rb_source_course_completion'),
                'hierarchy',
                array(
                    'hierarchytype' => 'org',
                )
            ),
            new rb_filter_option(
                'course_completion',
                'organisationid2',
                get_string('multiorgwhencompleted', 'rb_source_course_completion'),
                'hierarchy_multi',
                array(
                    'hierarchytype' => 'org',
                )
            ),
            new rb_filter_option(
                'course_completion',
                'positionid',
                get_string('poswhencompletedbasic', 'rb_source_course_completion'),
                'select',
                array(
                    'selectfunc' => 'positions_list',
                    'attributes' => rb_filter_option::select_width_limiter()
                )
            ),
            new rb_filter_option(
                'course_completion',
                'positionid2',
                get_string('multiposwhencompleted', 'rb_source_course_completion'),
                'hierarchy_multi',
                array(
                    'hierarchytype' => 'pos',
                )
            ),
            new rb_filter_option(
                'course_completion',
                'positionpath',
                get_string('poswhencompleted', 'rb_source_course_completion'),
                'hierarchy',
                array(
                    'hierarchytype' => 'pos',
                )
            ),
            new rb_filter_option(
                'course_completion',
                'grade',
                get_string('grade', 'rb_source_course_completion'),
                'number'
            ),
            new rb_filter_option(
                'course_completion',
                'passgrade',
                'Required Grade',
                'number'
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
        $this->add_cohort_user_fields_to_filters($filteroptions);
        $this->add_cohort_course_fields_to_filters($filteroptions);

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
                get_string('orgwhencompleted', 'rb_source_course_completion'),
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
                get_string('completiondate', 'rb_source_course_completion'),
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
                'base.course'
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
                'type' => 'course',
                'value' => 'courselink',
            ),
            array(
                'type' => 'user',
                'value' => 'organisation',
            ),
            array(
                'type' => 'course_completion',
                'value' => 'organisation',
            ),
            array(
                'type' => 'user',
                'value' => 'position',
            ),
            array(
                'type' => 'course_completion',
                'value' => 'position',
            ),
            array(
                'type' => 'course_completion',
                'value' => 'status',
            ),
            array(
                'type' => 'course_completion',
                'value' => 'completeddate',
            ),
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'user',
                'value' => 'fullname',
            ),
            array(
                'type' => 'user',
                'value' => 'organisationpath',
                'advanced' => 1,
            ),
            array(
                'type' => 'course_completion',
                'value' => 'organisationpath',
                'advanced' => 1,
            ),
            array(
                'type' => 'user',
                'value' => 'positionpath',
                'advanced' => 1,
            ),
            array(
                'type' => 'course_completion',
                'value' => 'positionpath',
                'advanced' => 1,
            ),
            array(
                'type' => 'course',
                'value' => 'fullname',
                'advanced' => 1,
            ),
            array(
                'type' => 'course_category',
                'value' => 'path',
                'advanced' => 1,
            ),
            array(
                'type' => 'course_completion',
                'value' => 'completeddate',
                'advanced' => 1,
            ),
            array(
                'type' => 'course_completion',
                'value' => 'status',
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
                array()     // options
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

    function rb_display_completion_status($status, $row, $isexport) {
        global $CFG;
        require_once($CFG->dirroot.'/completion/completion_completion.php');
        global $COMPLETION_STATUS;

        if (!array_key_exists((int)$status, $COMPLETION_STATUS)) {
            return '';
        }
        $string = $COMPLETION_STATUS[(int)$status];
        if (empty($string)) {
            return '';
        } else {
            return get_string($string, 'completion');
        }
    }

    //
    //
    // Source specific filter display methods
    //
    //

    function rb_filter_completion_status_list() {
        global $CFG;
        require_once($CFG->dirroot.'/completion/completion_completion.php');
        global $COMPLETION_STATUS;

        $statuslist = array();
        foreach ($COMPLETION_STATUS as $key => $value) {
            $statuslist[(string)$key] = get_string($value, 'completion');
        }
        return $statuslist;
    }
} // end of rb_source_course_completion class

