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

class rb_source_competency_evidence extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    function __construct() {
        $this->base = '{comp_record}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_competency_evidence');

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
                'competency',
                'LEFT',
                '{comp}',
                'competency.id = base.competencyid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'scale_values',
                'LEFT',
                '{comp_scale_values}',
                'scale_values.id = base.proficiency',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'assessor',
                'LEFT',
                '{user}',
                'assessor.id = base.assessorid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
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

        // include some standard joins
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        // requires the position_assignment join
        $this->add_manager_tables_to_joinlist($joinlist,
            'position_assignment', 'reportstoid');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');

        return $joinlist;
    }

    protected function define_columnoptions() {
        global $DB;

        $columnoptions = array(
            new rb_column_option(
                'competency_evidence',  // Type.
                'proficiency',          // Value.
                get_string('proficiency', 'rb_source_competency_evidence'), // Name.
                'scale_values.name',    // Field.
                array('joins' => 'scale_values',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text') // Options.
            ),
            new rb_column_option(
                'competency_evidence',
                'proficiencyid',
                get_string('proficiencyid', 'rb_source_competency_evidence'),
                'base.proficiency'
            ),
            new rb_column_option(
                'competency_evidence',
                'completeddate',
                get_string('completiondate', 'rb_source_competency_evidence'),
                'base.timemodified',
                array('displayfunc' => 'nice_date', 'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'competency_evidence',
                'organisationid',
                get_string('completionorgid', 'rb_source_competency_evidence'),
                'base.organisationid'
            ),
            new rb_column_option(
                'competency_evidence',
                'organisationid2',
                get_string('completionorgid', 'rb_source_competency_evidence'),
                'base.organisationid',
                array('selectable' => false)
            ),
            new rb_column_option(
                'competency_evidence',
                'organisationpath',
                get_string('completionorgpath', 'rb_source_competency_evidence'),
                'completion_organisation.path',
                array('joins' => 'completion_organisation')
            ),
            new rb_column_option(
                'competency_evidence',
                'organisation',
                get_string('completionorgname', 'rb_source_competency_evidence'),
                'completion_organisation.fullname',
                array('joins' => 'completion_organisation',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'competency_evidence',
                'positionid',
                get_string('completionposid', 'rb_source_competency_evidence'),
                'base.positionid'
            ),
            new rb_column_option(
                'competency_evidence',
                'positionid2',
                get_string('completionposid', 'rb_source_competency_evidence'),
                'base.positionid',
                array('selectable' => false)
            ),
            new rb_column_option(
                'competency_evidence',
                'positionpath',
                get_string('completionpospath', 'rb_source_competency_evidence'),
                'completion_position.path',
                array('joins' => 'completion_position')
            ),
            new rb_column_option(
                'competency_evidence',
                'position',
                get_string('completionposname', 'rb_source_competency_evidence'),
                'completion_position.fullname',
                array('joins' => 'completion_position',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'competency_evidence',
                'assessor',
                get_string('assessorname', 'rb_source_competency_evidence'),
                $DB->sql_fullname("assessor.firstname", "assessor.lastname"),
                array('joins' => 'assessor',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'competency_evidence',
                'assessorname',
                get_string('assessororg', 'rb_source_competency_evidence'),
                'base.assessorname',
                array('dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'competency',
                'fullname',
                get_string('competencyname', 'rb_source_competency_evidence'),
                'competency.fullname',
                array('joins' => 'competency',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'competency',
                'shortname',
                get_string('competencyshortname', 'rb_source_competency_evidence'),
                'competency.shortname',
                array('joins' => 'competency',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'competency',
                'idnumber',
                get_string('competencyid', 'rb_source_competency_evidence'),
                'competency.idnumber',
                array('joins' => 'competency',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'competency',
                'competencylink',
                get_string('competencylinkname', 'rb_source_competency_evidence'),
                'competency.fullname',
                array(
                    'joins' => 'competency',
                    'displayfunc' => 'link_competency',
                    'defaultheading' => get_string('competencyname', 'rb_source_competency_evidence'),
                    'extrafields' => array('competency_id' => 'competency.id'),
                )
            ),
            new rb_column_option(
                'competency',
                'id',
                get_string('competencyid', 'rb_source_competency_evidence'),
                'base.competencyid'
            ),
            new rb_column_option(
                'competency',
                'id2',
                get_string('competencyid', 'rb_source_competency_evidence'),
                'base.competencyid',
                array('selectable' => false)
            ),
            new rb_column_option(
                'competency',
                'path',
                get_string('competencypath', 'rb_source_competency_evidence'),
                'competency.path',
                array('joins' => 'competency')
            ),
            new rb_column_option(
                'competency',
                'statushistorylink',
                get_string('statushistorylinkcolumn', 'rb_source_competency_evidence'),
                'base.userid',
                array('defaultheading' => get_string('statushistorylinkheading', 'rb_source_competency_evidence'),
                      'displayfunc' => 'status_history_link',
                      'extrafields' => array('competencyid' => 'base.competencyid'),
                      'noexport' => true,
                      'nosort' => true)
            )
        );

        // include some standard columns
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_cohort_user_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'competency_evidence',  // type
                'completeddate',        // value
                get_string('completeddate', 'rb_source_competency_evidence'),       // label
                'date',                 // filtertype
                array()                 // options
            ),
            new rb_filter_option(
                'competency_evidence',
                'proficiencyid',
                get_string('proficiency', 'rb_source_competency_evidence'),
                'select',
                array(
                    'selectfunc' => 'proficiency_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
            new rb_filter_option(
                'competency_evidence',
                'organisationid',
                get_string('officewhencompletedbasic', 'rb_source_competency_evidence'),
                'select',
                array(
                    'selectfunc' => 'organisations_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
            new rb_filter_option(
                'competency_evidence',
                'organisationid2',
                get_string('multiorg', 'rb_source_competency_evidence'),
                'hierarchy_multi',
                array(
                    'hierarchytype' => 'org',
                )
            ),
            new rb_filter_option(
                'competency_evidence',
                'organisationpath',
                get_string('organisationwhencompleted', 'rb_source_competency_evidence'),
                'hierarchy',
                array(
                    'hierarchytype' => 'org',
                )
            ),
            new rb_filter_option(
                'competency_evidence',
                'positionid',
                get_string('positionwhencompletedbasic', 'rb_source_competency_evidence'),
                'select',
                array(
                    'selectfunc' => 'positions_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
            new rb_filter_option(
                'competency_evidence',
                'positionid2',
                get_string('multipos', 'rb_source_competency_evidence'),
                'hierarchy_multi',
                array(
                    'hierarchytype' => 'pos',
                )
            ),
            new rb_filter_option(
                'competency_evidence',
                'positionpath',
                get_string('positionwhencompleted', 'rb_source_competency_evidence'),
                'hierarchy',
                array(
                    'hierarchytype' => 'pos',
                )
            ),
            new rb_filter_option(
                'competency_evidence',
                'assessor',
                get_string('assessorname', 'rb_source_competency_evidence'),
                'text'
            ),
            new rb_filter_option(
                'competency_evidence',
                'assessorname',
                get_string('assessororg', 'rb_source_competency_evidence'),
                'text'
            ),
            new rb_filter_option(
                'competency',
                'path',
                get_string('competency', 'rb_source_competency_evidence'),
                'hierarchy',
                array(
                    'hierarchytype' => 'comp',
                )
            ),
            new rb_filter_option(
                'competency',
                'fullname',
                get_string('competencyname', 'rb_source_competency_evidence'),
                'text'
            ),
            new rb_filter_option(
                'competency',
                'shortname',
                get_string('competencyshortname', 'rb_source_competency_evidence'),
                'text'
            ),
            new rb_filter_option(
                'competency',
                'idnumber',
                get_string('competencyid', 'rb_source_competency_evidence'),
                'text'
            ),
            new rb_filter_option(
                'competency',
                'id2',
                get_string('multicomp', 'rb_source_competency_evidence'),
                'hierarchy_multi',
                array(
                    'hierarchytype' => 'comp',
                )
            ),

        );
        // include some standard filters
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
                'userid',       // parameter name
                'base.userid',  // field
                null            // joins
            ),
            new rb_param_option(
                'compid',
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
                'value' => 'competencylink',
            ),
            array(
                'type' => 'user',
                'value' => 'organisation',
            ),
            array(
                'type' => 'competency_evidence',
                'value' => 'organisation',
            ),
            array(
                'type' => 'user',
                'value' => 'position',
            ),
            array(
                'type' => 'competency_evidence',
                'value' => 'position',
            ),
            array(
                'type' => 'competency_evidence',
                'value' => 'proficiency',
            ),
            array(
                'type' => 'competency_evidence',
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
                'advanced' => 0,
            ),
            array(
                'type' => 'user',
                'value' => 'organisationpath',
                'advanced' => 1,
            ),
            array(
                'type' => 'competency_evidence',
                'value' => 'organisationpath',
                'advanced' => 1,
            ),
            array(
                'type' => 'user',
                'value' => 'positionpath',
                'advanced' => 1,
            ),
            array(
                'type' => 'competency_evidence',
                'value' => 'positionpath',
                'advanced' => 1,
            ),
            array(
                'type' => 'competency',
                'value' => 'fullname',
                'advanced' => 1,
            ),
            array(
                'type' => 'competency_evidence',
                'value' => 'completeddate',
                'advanced' => 1,
            ),
            array(
                'type' => 'competency_evidence',
                'value' => 'proficiencyid',
                'advanced' => 1,
            ),
        );
        return $defaultfilters;
    }

    public function rb_display_status_history_link($userid, $row, $isexport = false) {
        if ($isexport) {
            return '';
        }

        if ($userid == 0) {
            return '';
        }

        $url = new moodle_url('/totara/hierarchy/prefix/competency/statushistoryreport.php',
                array('userid' => $userid, 'competencyid' => $row->competencyid));

        return html_writer::link($url, get_string('statushistorylinkheading', 'rb_source_competency_evidence'));
    }

    //
    //
    // Source specific column display methods
    //
    //

    // link competency to competency view page
    // requires the competency_id extra field
    // in column definition
    function rb_display_link_competency($comp, $row) {
        $compid = $row->competency_id;
        $url = new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => 'competency', 'id' => $compid));
        return html_writer::link($url, $comp);
    }

    //
    //
    // Source specific filter display methods
    //
    //

    function rb_filter_proficiency_list() {
        global $DB;

        $values = $DB->get_records_menu('comp_scale_values', null, 'scaleid, sortorder', 'id, name');

        $scales = array();
        foreach ($values as $value) {
            $scales[] = format_string($value);
        }

        // include all possible scale values (from every scale)
        return $scales;
    }

} // end of rb_source_competency_evidence class

