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

require_once('rb_source_goal_details.php');

class rb_source_goal_status_history extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions, $paramoptions;
    public $contentoptions, $defaultcolumns, $defaultfilters, $embeddedparams;
    public $sourcetitle, $shortname;


    public function __construct() {
        global $DB;

        $this->base = '(SELECT gih.id, gih.scope, goal.id AS itemid, goal.fullname, gr.userid, gih.scalevalueid,
                               gih.timemodified, gih.usermodified,
                               ' . $DB->sql_concat('goal.id', "'_'", 'gih.scope') . ' AS itemandscope
                          FROM {goal_item_history} gih
                          JOIN {goal_record} gr ON gih.itemid = gr.id AND gih.scope = ' . goal::SCOPE_COMPANY . '
                          JOIN {goal} goal ON gr.goalid = goal.id
                         UNION
                        SELECT gih.id, gih.scope, gp.id AS itemid, gp.name AS fullname, gp.userid, gih.scalevalueid,
                               gih.timemodified, gih.usermodified,
                               ' . $DB->sql_concat('gp.id', "'_'", 'gih.scope') . ' AS itemandscope
                          FROM {goal_item_history} gih
                          JOIN {goal_personal} gp ON gih.itemid = gp.id AND gih.scope = ' . goal::SCOPE_PERSONAL . ')';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_goal_status_history');
        $this->shortname = 'goal_status_history';

        parent::__construct();
    }


    protected function define_joinlist() {
        $joinlist = array(
            new rb_join(
                'scalevalue',
                'LEFT',
                '{goal_scale_values}',
                'scalevalue.id = base.scalevalueid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'usermodified',
                'LEFT',
                '{user}',
                'usermodified.id = base.usermodified',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            )
        );

        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_manager_tables_to_joinlist($joinlist, 'position_assignment', 'reportstoid');

        return $joinlist;
    }


    protected function define_columnoptions() {
        global $DB;

        $columnoptions = array(
            new rb_column_option(
                'item',
                'itemandscope',
                '',
                'base.itemandscope',
                array('selectable' => false)
            ),
            new rb_column_option(
                'item',
                'scalevalueid',
                '',
                'base.scalevalueid',
                array('selectable' => false)
            ),
            new rb_column_option(
                'item',
                'fullname',
                get_string('goalnamecolumn', 'rb_source_goal_status_history'),
                'base.fullname',
                array('defaultheading' => get_string('goalnameheading', 'rb_source_goal_status_history'),
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'item',
                'scope',
                get_string('goalscopecolumn', 'rb_source_goal_status_history'),
                'base.scope',
                array('defaultheading' => get_string('goalscopeheading', 'rb_source_goal_status_history'),
                      'displayfunc' => 'scope')
            ),
            new rb_column_option(
                'history',
                'scalevalue',
                get_string('goalscalevaluecolumn', 'rb_source_goal_status_history'),
                'scalevalue.name',
                array('joins' => 'scalevalue',
                      'defaultheading' => get_string('goalscalevalueheading', 'rb_source_goal_status_history'),
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'history',
                'timemodified',
                get_string('goaltimemodifiedcolumn', 'rb_source_goal_status_history'),
                'base.timemodified',
                array('defaultheading' => get_string('goaltimemodifiedheading', 'rb_source_goal_status_history'),
                      'displayfunc' => 'nice_datetime',
                      'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'history',
                'usermodifiednamelink',
                get_string('goalusermodifiedcolumn', 'rb_source_goal_status_history'),
                $DB->sql_fullname('usermodified.firstname', 'usermodified.lastname'),
                array('defaultheading' => get_string('goalusermodifiedheading', 'rb_source_goal_status_history'),
                      'joins' => 'usermodified',
                      'displayfunc' => 'link_user',
                      'extrafields' => array('user_id' => 'usermodified.id'),
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            )
        );

        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);

        return $columnoptions;
    }


    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'item',
                'scope',
                get_string('goalscopecolumn', 'rb_source_goal_status_history'),
                'select',
                array('selectfunc' => 'scope')
            ),
            new rb_filter_option(
                'item',
                'itemandscope',
                get_string('goalcompanynamecolumn', 'rb_source_goal_status_history'),
                'select',
                array('selectfunc' => 'company_goal')
            ),
            new rb_filter_option(
                'item',
                'scalevalueid',
                get_string('goalscalevaluecolumn', 'rb_source_goal_status_history'),
                'select',
                array('selectfunc' => 'scalevalue')
            ),
            new rb_filter_option(
                'history',
                'timemodified',
                get_string('goaltimemodifiedcolumn', 'rb_source_goal_status_history'),
                'date',
                array('includetime' => true)
            )
        );

        $this->add_user_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);

        return $filteroptions;
    }


    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'itemandscope',
                'base.itemandscope',
                null,
                'string'
            ),
            new rb_param_option(
                'userid',
                'base.userid'
            )
        );

        return $paramoptions;
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
                'user',
                get_string('user', 'rb_source_goal_status_history'),
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
                get_string('modifieddate', 'rb_source_goal_status_history'),
                'base.timemodified'
            ),
        );
        return $contentoptions;
    }


    /**
     * Filter scope.
     *
     * @return array
     */
    public function rb_filter_scope() {
        $scopes = array(goal::SCOPE_COMPANY => get_string('goalscopecompany', 'rb_source_goal_status_history'),
                        goal::SCOPE_PERSONAL => get_string('goalscopepersonal', 'rb_source_goal_status_history'));
        return $scopes;
    }


    /**
     * Filter item.
     *
     * @return array
     */
    public function rb_filter_company_goal() {
        global $DB;

        $goals = array();

        $sql = 'SELECT goal.id, goal.fullname
                  FROM {goal} goal';

        $goallist = $DB->get_records_sql($sql);

        foreach ($goallist as $goal) {
            $goals[$goal->id . '_' . goal::SCOPE_COMPANY] = $goal->fullname;
        }

        return $goals;
    }

    /**
     * Filter scale value (status).
     *
     * @return array
     */
    public function rb_filter_scalevalue() {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/totara/hierarchy/prefix/goal/lib.php");

        $scalevalues = array();

        $sql = 'SELECT gsv.*, gs.name AS scalename
                  FROM {goal_scale_values} gsv
                  JOIN {goal_scale} gs
                    ON gsv.scaleid = gs.id
                 ORDER BY gs.name, gsv.sortorder';

        $goalscalevalues = $DB->get_records_sql($sql);

        foreach ($goalscalevalues as $goalscalevalue) {
            $scalevalues[$goalscalevalue->id] = format_string($goalscalevalue->scalename) . ': ' . format_string($goalscalevalue->name);
        }

        return $scalevalues;
    }


    public function rb_display_scope($scope) {
        $scopes = $this->rb_filter_scope();

        return $scopes[$scope];
    }


    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelink'
            ),
            array(
                'type' => 'item',
                'value' => 'fullname'
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
