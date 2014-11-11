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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

// needed for approval constants etc
require_once($CFG->dirroot . '/totara/plan/lib.php');
// needed for instatiating and checking programs
require_once($CFG->dirroot . '/totara/program/lib.php');

class rb_source_dp_program extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle, $instancetype;

    function __construct() {
        $this->base = '{prog}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->instancetype = 'program';
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_dp_program');
        $this->sourcewhere = 'base.certifid IS NULL';
        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    protected function define_joinlist() {
        global $DB;

        $joinlist = array(
            new rb_join(
                'program_completion', // table alias
                'INNER', // type of join
                '{prog_completion}',
                'base.id = program_completion.programid AND program_completion.coursesetid = 0', //how it is joined
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                array('base')
            ),
            new rb_join(
                'prog_user_assignment', // table alias
                'LEFT', // type of join
                '(SELECT pua2.*
                  FROM (SELECT MAX(id) as id, programid, userid
                        FROM {prog_user_assignment}
                        GROUP BY userid, programid) AS pua
                  INNER JOIN {prog_user_assignment} AS pua2
                    ON pua2.id = pua.id)',
                'program_completion.programid = prog_user_assignment.programid AND program_completion.userid = prog_user_assignment.userid', //how it is joined
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                array('program_completion')
            ),
            new rb_join(
                'dp_plan', // table alias
                'LEFT', // type of join
                '{dp_plan}', // actual table name
                'dp_plan.id = prog_plan_assignment.planid', //how it is joined
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                array('prog_plan_assignment')
            ),
            new rb_join(
                'prog_plan_assignment', // table alias
                'LEFT', // type of join
                '{dp_plan_program_assignment}', // actual table name
                'base.id = prog_plan_assignment.programid = ', //how it is joined
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                array('base')
            ),
            new rb_join(
                'program_completion_history',
                'LEFT',
                '(SELECT ' . $DB->sql_concat('userid', 'programid') . ' uniqueid,
                    userid,
                    programid,
                    COUNT(id) historycount
                    FROM {prog_completion_history} program_completion_history
                    GROUP BY userid, programid)',
                '(base.id = program_completion_history.programid AND ' .
                    'prog_user_assignment.userid = program_completion_history.userid)',
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                array('base', 'prog_user_assignment')
            ),
        );
        $joinlist[] =  new rb_join(
                'completion_organisation',
                'LEFT',
                '{org}',
                'completion_organisation.id = program_completion.organisationid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
        );
        $this->add_context_table_to_joinlist($joinlist, 'base', 'id', CONTEXT_PROGRAM, 'INNER');
        $this->add_course_category_table_to_joinlist($joinlist, 'base', 'category');
        $this->add_cohort_program_tables_to_joinlist($joinlist, 'base', 'id');
        $this->add_user_table_to_joinlist($joinlist, 'program_completion', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'program_completion', 'userid');
        $this->add_manager_tables_to_joinlist($joinlist, 'position_assignment', 'reportstoid');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'program_completion', 'userid');

        return $joinlist;
    }

    protected function define_columnoptions() {
        $columnoptions = array();

        $columnoptions[] = new rb_column_option(
            'program',
            'fullname',
            get_string('programname', 'totara_program'),
            "base.fullname",
            array('joins' => 'base',
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'program',
            'shortname',
            get_string('programshortname', 'totara_program'),
            "base.shortname",
            array('joins' => 'base',
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'program',
            'idnumber',
           get_string('programidnumber', 'totara_program'),
            "base.idnumber",
            array('joins' => 'base',
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'program',
            'id',
            get_string('programid', 'totara_program'),
            "base.id",
            array('joins' => 'base')
        );
        $columnoptions[] = new rb_column_option(
            'program',
            'proglinkicon',
            get_string('prognamelinkedicon', 'totara_program'),
            "base.fullname",
            array(
                'joins' => 'program_completion',
                'displayfunc' => 'link_program_icon',
                'defaultheading' => get_string('programname', 'totara_program'),
                'extrafields' => array(
                    'program_id' => "base.id",
                    'program_icon' => "base.icon",
                    'program_visible' => 'base.visible',
                    'program_audiencevisible' => 'base.audiencevisible',
                    'userid' => "program_completion.userid"
                )
            )
        );

        $columnoptions[] = new rb_column_option(
            'program',
            'timedue',
            get_string('programduedate', 'totara_program'),
            "program_completion.timedue",
            array(
                'joins' => 'program_completion',
                'displayfunc' => 'timedue_date',
                'dbdatatype' => 'timestamp',
                'extrafields' => array(
                    'program_id' => "base.id",
                    'completionstatus' => "program_completion.status"
                )
            )
        );

        $columnoptions[] = new rb_column_option(
            'program',
            'mandatory',
            get_string('programmandatory', 'totara_program'),
            "prog_user_assignment.id",
            array(
                'joins' => 'prog_user_assignment',
                'displayfunc' => 'mandatory_status',
            )
        );

        $columnoptions[] = new rb_column_option(
            'program',
            'recurring',
            get_string('programrecurring', 'totara_program'),
            "base.id",
            array(
                'joins' => 'program_completion',
                'displayfunc' => 'recurring_status',
                'extrafields' => array(
                    'userid' => "program_completion.userid"
                )
            )
        );

        $columnoptions[] = new rb_column_option(
            'program_completion',
            'status',
            get_string('completionstatus', 'rb_source_dp_course'),
            "program_completion.status",
            array(
                'joins' => array('program_completion'),
                'displayfunc' => 'program_completion_progress',
                'defaultheading' => get_string('progress', 'rb_source_dp_course'),
                'extrafields' => array(
                    'programid' => "base.id",
                    'userid' => "program_completion.userid"
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'program_completion',
            'starteddate',
            get_string('starteddate', 'rb_source_program_completion'),
            'program_completion.timestarted',
            array('joins' => array('program_completion'), 'displayfunc' => 'prog_date')
        );
        $columnoptions[] = new rb_column_option(
            'program_completion',
            'completeddate',
            get_string('completeddate', 'rb_source_program_completion'),
            'program_completion.timecompleted',
            array('joins' => array('program_completion'), 'displayfunc' => 'prog_date')
        );
        $columnoptions[] = new rb_column_option(
            'program_completion_history',
            'program_previous_completion',
            get_string('program_previous_completion', 'rb_source_dp_program'),
            'program_completion_history.historycount',
            array(
                'joins' => 'program_completion_history',
                'defaultheading' => get_string('program_previous_completion', 'rb_source_dp_program'),
                'displayfunc' => 'program_previous_completion',
                'extrafields' => array(
                    'program_id' => "base.id",
                    'userid' => "program_completion.userid"
                ),
            )
        );
        $columnoptions[] = new rb_column_option(
            'program_completion_history',
            'program_completion_history_count',
            get_string('program_completion_history_count', 'rb_source_dp_program'),
            'program_completion_history.historycount',
            array(
                'joins' => 'program_completion_history',
                'dbdatatype' => 'integer',
            )
        );

        // Include some standard columns.
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_cohort_user_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions, 'course_category', 'base');
        $this->add_cohort_program_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    function rb_display_program_completion_progress($status,$row) {
        $program = new program($row->programid);
        return $program->display_progress($row->userid);
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

    function rb_display_timedue_date($time,$row) {
        $program = new program($row->program_id);
        return $program->display_timedue_date($row->completionstatus, $time);
    }

    function rb_display_mandatory_status($id) {
        global $OUTPUT;
        if (!empty($id)) {
            return $OUTPUT->pix_icon('/i/valid', get_string('yes'));
        }
        return get_string('no');
    }

    function rb_display_recurring_status($programid, $row) {
        global $OUTPUT;

        $userid = $row->userid;

        $program = new program($programid);
        $program_content = $program->get_content();
        $coursesets = $program_content->get_course_sets();
        if (isset($coursesets[0])) {
            $courseset = $coursesets[0];
            if ($courseset->is_recurring()) {
                $recurringcourse = $courseset->course;
                $link = get_string('yes');
                $link .= $OUTPUT->action_link(new moodle_url('/totara/plan/record/programs_recurring.php', array('programid' => $program->id, 'userid' => $userid)), get_string('viewrecurringprogramhistory', 'totara_program'));
                return $link;
            }
        }
        return get_string('no');
    }

    function rb_display_link_program_icon($programname, $row) {
        $program = new program($row->program_id);
        return $program->display_link_program_icon($programname, $row->program_id, $row->program_icon, $row->userid);
    }

    public function rb_display_program_previous_completion($name, $row) {
        global $OUTPUT;
        return $OUTPUT->action_link(new moodle_url('/totara/plan/record/programs.php',
                array('program_id' => $row->program_id, 'userid' => $row->userid, 'history' => 1)), $name);
    }

    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'program',
                'fullname',
                get_string('programname', 'totara_program'),
                'text'
            )
        );
        $filteroptions[] = new rb_filter_option(
                'program_completion_history',
                'program_completion_history_count',
                get_string('program_completion_history_count', 'rb_source_dp_program'),
                'number'
        );

        $this->add_user_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_cohort_user_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions, 'base', 'category');
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
                'base.id'
            ),
            new rb_param_option(
                'visible',
                'base.visible'
            ),
            new rb_param_option(
                'category',
                'base.category'
            ),
            new rb_param_option(
                'userid',
                'program_completion.userid',
                'program_completion'
            ),
        );

        $paramoptions[] = new rb_param_option(
                'programstatus',
                'program_completion.status',
                'program_completion'
        );

        $paramoptions[] = new rb_param_option(
            'exceptionstatus',
            'CASE WHEN prog_user_assignment.exceptionstatus IN (' . PROGRAM_EXCEPTION_NONE . ',' . PROGRAM_EXCEPTION_RESOLVED .')
                THEN 0 ELSE 1 END',
            'prog_user_assignment',
            'int'
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
                'type' => 'program',
                'value' => 'proglinkicon',
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
                'type' => 'program',
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

    protected function define_requiredcolumns() {
        $requiredcolumns = array();

        $requiredcolumns[] = new rb_column(
            'ctx',
            'id',
            '',
            "ctx.id",
            array('joins' => 'ctx')
        );

        $requiredcolumns[] = new rb_column(
            'base',
            'visible',
            '',
            "base.visible"
        );

        $requiredcolumns[] = new rb_column(
            'base',
            'audiencevisible',
            '',
            "base.audiencevisible"
        );

        $requiredcolumns[] = new rb_column(
            'base',
            'available',
            '',
            "base.available"
        );

        $requiredcolumns[] = new rb_column(
            'base',
            'availablefrom',
            '',
            "base.availablefrom"
        );

        $requiredcolumns[] = new rb_column(
            'base',
            'availableuntil',
            '',
            "base.availableuntil"
        );

        return $requiredcolumns;
    }

    public function post_config(reportbuilder $report) {
        $reportfor = $report->reportfor; // ID of the user the report is for.
        $fieldalias = 'base';
        $fieldbaseid = $report->get_field('base', 'id', 'base.id');
        $fieldvisible = $report->get_field('base', 'visible', 'base.visible');
        $fieldaudvis = $report->get_field('base', 'audiencevisible', 'base.audiencevisible');
        $report->set_post_config_restrictions(totara_visibility_where($reportfor,
            $fieldbaseid, $fieldvisible, $fieldaudvis, $fieldalias, 'program', $report->is_cached()));
    }
} // end of rb_source_courses class
