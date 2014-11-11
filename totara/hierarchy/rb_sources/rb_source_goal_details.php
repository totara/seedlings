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

global $CFG;
require_once($CFG->dirroot.'/totara/hierarchy/prefix/goal/lib.php');

class rb_source_goal_details extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions, $paramoptions;
    public $contentoptions, $defaultcolumns, $defaultfilters, $embeddedparams;
    public $sourcetitle, $shortname, $sourcewhere;


    public function __construct() {
        $this->base = '{goal_record}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->embeddedparams = $this->define_embeddedparams();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_goal_details');
        $this->shortname = 'goal_details';
        $this->sourcewhere = 'base.deleted = 0';

        parent::__construct();
    }


    protected function define_joinlist() {
        $joinlist = array(
            new rb_join(
                'goal',
                'LEFT',
                '{goal}',
                'goal.id = base.goalid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'goalframework',
                'LEFT',
                '{goal_framework}',
                'goalframework.id = goal.frameworkid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'goal'
            ),
            new rb_join(
                'scalevalue',
                'LEFT',
                '{goal_scale_values}',
                'scalevalue.id = base.scalevalueid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            )
        );

        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_manager_tables_to_joinlist($joinlist, 'position_assignment', 'reportstoid');

        return $joinlist;
    }


    protected function define_columnoptions() {
        $columnoptions = array(
            new rb_column_option(
                'goal',
                'scalevalueid',
                '',
                'base.scalevalueid',
                array('selectable' => false)
            ),
            new rb_column_option(
                'goal',
                'goalid',
                '',
                'base.goalid',
                array('selectable' => false)
            ),
            new rb_column_option(
                'goal',
                'name',
                get_string('goalnamecolumn', 'rb_source_goal_details'),
                'goal.fullname',
                array('joins' => 'goal',
                      'defaultheading' => get_string('goalnameheading', 'rb_source_goal_details'),
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'goal',
                'frameworkname',
                get_string('goalframeworknamecolumn', 'rb_source_goal_details'),
                'goalframework.fullname',
                array('joins' => 'goalframework',
                      'defaultheading' => get_string('goalframeworknameheading', 'rb_source_goal_details'),
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'goal',
                'userstatus',
                get_string('goaluserstatuscolumn', 'rb_source_goal_details'),
                'scalevalue.name',
                array('joins' => 'scalevalue',
                      'defaultheading' => get_string('goaluserstatusheading', 'rb_source_goal_details'),
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'goal',
                'statushistorylink',
                get_string('goalstatushistorylinkcolumn', 'rb_source_goal_details'),
                'base.userid',
                array('defaultheading' => get_string('goalstatushistorylinkheading', 'rb_source_goal_details'),
                      'displayfunc' => 'status_history_link',
                      'extrafields' => array('goalid' => 'base.goalid'),
                      'noexport' => true)
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
                'goal',
                'goalid',
                get_string('goalnamecolumn', 'rb_source_goal_details'),
                'select',
                array('selectfunc' => 'goal')
            ),
            new rb_filter_option(
                'goal',
                'scalevalueid',
                get_string('goaluserstatuscolumn', 'rb_source_goal_details'),
                'select',
                array('selectfunc' => 'scalevalue')
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
                'goalid',
                'base.goalid'
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
                get_string('user', 'rb_source_goal_details'),
                array(
                    'userid' => 'base.userid',
                    'managerid' => 'position_assignment.managerid',
                    'managerpath' => 'position_assignment.managerpath',
                    'postype' => 'position_assignment.type',
                ),
                'position_assignment'
            ),
        );
        return $contentoptions;
    }


    public function rb_display_status_history_link($userid, $row, $isexport = false) {
        if ($isexport) {
            return '';
        }

        if ($userid == 0) {
            return '';
        }

        $url = new moodle_url('/totara/hierarchy/prefix/goal/statushistoryreport.php',
                array('userid' => $userid, 'itemandscope' => $row->goalid . '_' . goal::SCOPE_COMPANY, 'clearfilters' => 1));

        return html_writer::link($url, get_string('goalstatushistorylinkheading', 'rb_source_goal_details'));
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


    /**
     * Filter goal.
     *
     * @return array
     */
    public function rb_filter_goal() {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/totara/hierarchy/prefix/goal/lib.php");

        $goals = array();

        $sql = 'SELECT g.*, gs.name AS scalename
                  FROM {goal} g
                  JOIN {goal_framework} gfwk
                    ON g.frameworkid = gfwk.id
                  JOIN {goal_scale_assignments} gsa
                    ON gfwk.id = gsa.frameworkid
                  JOIN {goal_scale} gs
                    ON gsa.scaleid = gs.id';

        $goallist = $DB->get_records_sql($sql);

        foreach ($goallist as $goal) {
            $goals[$goal->id] = format_string($goal->fullname) . ': ' . format_string($goal->scalename);
        }

        return $goals;
    }


    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelink'
            ),
            array(
                'type' => 'user',
                'value' => 'position'
            ),
            array(
                'type' => 'user',
                'value' => 'organisation'
            ),
            array(
                'type' => 'user',
                'value' => 'managername'
            ),
            array(
                'type' => 'goal',
                'value' => 'name'
            ),
            array(
                'type' => 'goal',
                'value' => 'userstatus'
            )
        );

        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array();

        return $defaultfilters;
    }

    protected function define_embeddedparams() {
        $embeddedparams = array();

        return $embeddedparams;
    }

}
