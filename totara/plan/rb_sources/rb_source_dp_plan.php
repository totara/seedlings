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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/totara/plan/lib.php');

/**
 * A report builder source for development plans
 */
class rb_source_dp_plan extends rb_base_source {

    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    /**
     * Constructor
     */
    public function __construct() {
        $this->base = '{dp_plan}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = array();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_dp_plan');
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
     * @return array
     */
    protected function define_joinlist() {
        $joinlist = array();

        $joinlist[] = new rb_join(
                'template',
                'LEFT',
                '{dp_template}',
                'base.templateid = template.id',
                REPORT_BUILDER_RELATION_MANY_TO_ONE,
                array()
        );

        $this->add_user_table_to_joinlist($joinlist, 'base','userid');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_manager_tables_to_joinlist($joinlist, 'position_assignment', 'reportstoid');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');

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
                'plan',
                'name',
                get_string('planname', 'rb_source_dp_plan'),
                'base.name',
                array(
                    'defaultheading' => get_string('plan', 'rb_source_dp_plan'),
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
        );
        $columnoptions[] = new rb_column_option(
                'plan',
                'planlink',
                get_string('plannamelink', 'rb_source_dp_plan'),
                'base.name',
                array(
                    'defaultheading' => get_string('plan', 'rb_source_dp_plan'),
                    'displayfunc' => 'planlink',
                    'extrafields' => array( 'plan_id' => 'base.id' )
                )
        );
        $columnoptions[] = new rb_column_option(
                'plan',
                'description',
                get_string('description', 'rb_source_dp_plan'),
                'base.description',
                array(
                    'defaultheading' => get_string('description', 'rb_source_dp_plan'),
                    'displayfunc' => 'tinymce_textarea',
                    'extrafields' => array(
                            'filearea' => '\'dp_plan\'',
                            'component' => '\'totara_plan\'',
                            'fileid' => 'base.id'
                    ),
                    'dbdatatype' => 'text',
                    'outputformat' => 'text'
                )
        );
        $columnoptions[] = new rb_column_option(
                'plan',
                'startdate',
                get_string('planstartdate', 'rb_source_dp_plan'),
                'base.startdate',
                array(
                    'displayfunc' => 'nice_date',
                    'dbdatatype' => 'timestamp'
                )
        );
        $columnoptions[] = new rb_column_option(
                'plan',
                'enddate',
                get_string('planenddate', 'rb_source_dp_plan'),
                'base.enddate',
                array(
                    'displayfunc' => 'nice_date',
                    'dbdatatype' => 'timestamp'
                )
        );
        $columnoptions[] = new rb_column_option(
                'plan',
                'timecompleted',
                get_string('timecompleted', 'rb_source_dp_plan'),
                'base.timecompleted',
                array(
                    'displayfunc' => 'nice_date',
                    'dbdatatype' => 'timestamp'
                )
        );
        $columnoptions[] = new rb_column_option(
                'plan',
                'status',
                get_string('planstatus', 'rb_source_dp_plan'),
                'base.status',
                array(
                    'displayfunc' => 'plan_status'
                )
        );

        $columnoptions[] = new rb_column_option(
                'template',
                'name',
                get_string('templatename', 'rb_source_dp_plan'),
                'template.fullname',
                array(
                    'defaultheading' => get_string('plantemplate', 'rb_source_dp_plan'),
                    'joins' => 'template',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
        );
        $columnoptions[] = new rb_column_option(
                'template',
                'startdate',
                get_string('templatestartdate', 'rb_source_dp_plan'),
                'template.startdate',
                array(
                    'joins' => 'template',
                    'displayfunc' => 'nice_date',
                    'dbdatatype' => 'timestamp'
                )
        );
        $columnoptions[] = new rb_column_option(
                'template',
                'enddate',
                get_string('templateenddate', 'rb_source_dp_plan'),
                'template.enddate',
                array(
                    'joins' => 'template',
                    'displayfunc' => 'nice_date',
                    'dbdatatype' => 'timestamp'
                )
        );

        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_cohort_user_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    /**
     * Creates the array of rb_filter_option objects required for $this->filteroptions
     * @return array
     */
    protected function define_filteroptions() {
        $filteroptions = array();

        $filteroptions[] = new rb_filter_option(
                'plan',
                'name',
                get_string('planname', 'rb_source_dp_plan'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'template',
                'name',
                get_string('templatename', 'rb_source_dp_plan'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'plan',
                'description',
                get_string('plandescription', 'rb_source_dp_plan'),
                'textarea'
        );

        $filteroptions[] = new rb_filter_option(
                'plan',
                'startdate',
                get_string('planstartdate', 'rb_source_dp_plan'),
                'date'
        );

        $filteroptions[] = new rb_filter_option(
                'plan',
                'enddate',
                get_string('planenddate', 'rb_source_dp_plan'),
                'date'
        );

        $filteroptions[] = new rb_filter_option(
                'plan',
                'timecompleted',
                get_string('plancompletiondate', 'rb_source_dp_plan'),
                'date'
        );

        $filteroptions[] = new rb_filter_option(
                'plan',
                'status',
                get_string('planstatus', 'rb_source_dp_plan'),
                'select',
                array(
                    'selectfunc' => 'plan_status',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
        );

        $this->add_user_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_cohort_user_fields_to_filters($filteroptions);

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

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelinkicon',
            ),
            array(
                'type' => 'plan',
                'value' => 'planlink',
            ),
            array(
                'type' => 'template',
                'value' => 'name',
            ),
            array(
                'type' => 'plan',
                'value' => 'startdate',
            ),
            array(
                'type' => 'plan',
                'value' => 'enddate',
            ),
            array(
                'type' => 'plan',
                'value' => 'status',
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
                    'type' => 'plan',
                    'value' => 'name',
                    'advanced' => 0,
                ),
            array(
                    'type' => 'plan',
                    'value' => 'startdate',
                    'advanced' => 1,
                ),
            array(
                    'type' => 'plan',
                    'value' => 'enddate',
                    'advanced' => 1,
                ),
            array(
                    'type' => 'plan',
                    'value' => 'status',
                    'advanced' => 0,
                ),
            );

        return $defaultfilters;
    }

    function rb_filter_plan_status() {
        $out = array();
        $out[DP_PLAN_STATUS_UNAPPROVED] = get_string('unapproved', 'totara_plan');
        $out[DP_PLAN_STATUS_PENDING] = get_string('unapproved', 'totara_plan');
        $out[DP_PLAN_STATUS_APPROVED] = get_string('approved', 'totara_plan');
        $out[DP_PLAN_STATUS_COMPLETE] = get_string('complete', 'totara_plan');

        return $out;

    }

}
