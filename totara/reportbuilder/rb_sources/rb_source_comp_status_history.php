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
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_comp_status_history extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    public function __construct() {
        $this->base = '{comp_record_history}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_comp_status_history');

        parent::__construct();
    }

    protected function define_joinlist() {
        $joinlist = array(
            new rb_join(
                'competency',
                'LEFT',
                '{comp}',
                'competency.id = base.competencyid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'scalevalue',
                'LEFT',
                '{comp_scale_values}',
                'scalevalue.id = base.proficiency',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'usermodified',
                'LEFT',
                '{user}',
                'usermodified.id = base.usermodified',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'completion_organisation',
                'LEFT',
                '{org}',
                'completion_organisation.id = user.organisationid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'completion_position',
                'LEFT',
                '{pos}',
                'completion_position.id = user.positionid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            )
        );

        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_manager_tables_to_joinlist($joinlist, 'position_assignment', 'reportstoid');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');

        return $joinlist;
    }

    protected function define_columnoptions() {
        global $DB;

        $columnoptions = array(
            new rb_column_option(
                'competency',
                'competencyid',
                get_string('compidcolumn', 'rb_source_comp_status_history'),
                'base.competencyid',
                array('selectable' => false)
            ),
            new rb_column_option(
                'history',
                'scalevalueid',
                get_string('compscalevalueidcolumn', 'rb_source_comp_status_history'),
                'base.proficiency',
                array('selectable' => false)
            ),
            new rb_column_option(
                'competency',
                'fullname',
                get_string('compnamecolumn', 'rb_source_comp_status_history'),
                'competency.fullname',
                array('defaultheading' => get_string('compnameheading', 'rb_source_comp_status_history'),
                      'joins' => 'competency',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'history',
                'scalevalue',
                get_string('compscalevaluecolumn', 'rb_source_comp_status_history'),
                'scalevalue.name',
                array('joins' => 'scalevalue',
                      'defaultheading' => get_string('compscalevalueheading', 'rb_source_comp_status_history'),
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'history',
                'timemodified',
                get_string('comptimemodifiedcolumn', 'rb_source_comp_status_history'),
                'base.timemodified',
                array('defaultheading' => get_string('comptimemodifiedheading', 'rb_source_comp_status_history'),
                      'displayfunc' => 'nice_datetime',
                      'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'history',
                'usermodifiednamelink',
                get_string('compusermodifiedcolumn', 'rb_source_comp_status_history'),
                $DB->sql_fullname('usermodified.firstname', 'usermodified.lastname'),
                array('defaultheading' => get_string('compusermodifiedheading', 'rb_source_comp_status_history'),
                      'joins' => 'usermodified',
                      'displayfunc' => 'link_user',
                      'extrafields' => array('user_id' => 'usermodified.id'))
            )
        );

        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_cohort_user_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'competency',
                'competencyid',
                get_string('compnamecolumn', 'rb_source_comp_status_history'),
                'hierarchy_multi',
                array(
                    'hierarchytype' => 'comp'
                )
            ),
            new rb_filter_option(
                'history',
                'timemodified',
                get_string('comptimemodifiedcolumn', 'rb_source_comp_status_history'),
                'date',
                array('includetime' => true)
            )
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
                get_string('completedorg', 'rb_source_competency_evidence'),
                'completion_organisation.path',
                'completion_organisation'
            ),
            new rb_content_option(
                'user',
                get_string('user', 'rb_source_competency_evidence'),
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
                get_string('completiondate', 'rb_source_competency_evidence'),
                'base.timemodified'
            ),
        );
        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'userid',
                'base.userid'
            ),
            new rb_param_option(
                'competencyid',
                'base.competencyid'
            ),
        );

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelink'
            ),
            array(
                'type' => 'competency',
                'value' => 'fullname'
            ),
            array(
                'type' => 'history',
                'value' => 'scalevalue'
            ),
            array(
                'type' => 'history',
                'value' => 'timemodified'
            ),
            array(
                'type' => 'history',
                'value' => 'usermodifiednamelink'
            )
        );
        return $defaultcolumns;
    }


}
