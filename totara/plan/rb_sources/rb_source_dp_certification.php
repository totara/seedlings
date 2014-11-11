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
require_once($CFG->dirroot . '/totara/program/lib.php');

/**
 * A report builder source for Certifications
 */
class rb_source_dp_certification extends rb_base_source {

    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle, $instancetype;

    /**
     * Constructor
     */
    public function __construct() {
        $this->base = '{prog}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->instancetype = 'certification';
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_dp_certification');
        $this->sourcewhere = '(base.certifid > 0)';
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
        global $CFG, $DB;

        $joinlist = array();

        // to get access to position type constants
        require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_join.php');

        $joinlist[] = new rb_join(
                'certif',
                'INNER',
                '{certif}',
                'certif.id = base.certifid',
                REPORT_BUILDER_RELATION_MANY_TO_ONE,
                array('base')
        );

        $joinlist[] = new rb_join(
                'certif_completion',
                'INNER',
                '(SELECT ' . $DB->sql_concat("'active'", 'cc.id') . ' AS uniqueid,
                        cc.id,
                        cc.certifid,
                        cc.userid,
                        cc.certifpath,
                        cc.status,
                        cc.renewalstatus,
                        cc.timewindowopens,
                        cc.timeexpires,
                        cc.timecompleted,
                        cc.timemodified,
                        0 as unassigned
                    FROM {certif_completion} cc
                    UNION
                    SELECT ' . $DB->sql_concat("'history'", 'cch.id') . ' AS uniqueid,
                        cch.id,
                        cch.certifid,
                        cch.userid,
                        cch.certifpath,
                        cch.status,
                        cch.renewalstatus,
                        cch.timewindowopens,
                        cch.timeexpires,
                        cch.timecompleted,
                        cch.timemodified,
                        cch.unassigned
                    FROM {certif_completion_history} cch
                    LEFT JOIN {certif_completion} cc ON cc.certifid = cch.certifid AND cc.userid = cch.userid
                    WHERE cch.unassigned = 1
                    AND cc.id IS NULL)',
                '(certif_completion.certifid = base.certifid)',
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                array('base')
        );

        $joinlist[] = new rb_join(
                'certif_completion_history',
                'LEFT',
                '(SELECT ' . $DB->sql_concat('userid', 'certifid') . ' AS uniqueid,
                    userid,
                    certifid,
                    COUNT(id) AS historycount
                    FROM {certif_completion_history}
                    WHERE unassigned = 0
                    GROUP BY userid, certifid)',
                '(certif_completion_history.certifid = base.certifid
                    AND certif_completion_history.userid = certif_completion.userid)',
                REPORT_BUILDER_RELATION_MANY_TO_ONE,
                array('base', 'certif_completion')
        );

        $joinlist[] =  new rb_join(
                'prog_completion', // Table alias.
                'LEFT', // Type of join.
                '{prog_completion}',
                '(prog_completion.programid = base.id
                    AND prog_completion.coursesetid = 0
                    AND prog_completion.userid = certif_completion.userid)',
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                array('base', 'certif_completion')
        );

        $joinlist[] =  new rb_join(
                'completion_organisation',
                'LEFT',
                '{org}',
                'completion_organisation.id = prog_completion.organisationid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                array('prog_completion')
        );
        $this->add_context_table_to_joinlist($joinlist, 'base', 'id', CONTEXT_PROGRAM, 'INNER');
        $this->add_course_category_table_to_joinlist($joinlist, 'base', 'category');
        $this->add_cohort_program_tables_to_joinlist($joinlist, 'base', 'id');
        $this->add_user_table_to_joinlist($joinlist, 'certif_completion', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'certif_completion', 'userid');
        $this->add_manager_tables_to_joinlist($joinlist, 'position_assignment', 'reportstoid');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'certif_completion', 'userid');

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
                'base',
                'fullname',
                get_string('certificationname', 'totara_program'),
                'base.fullname',
                array(
                    'joins' => 'base',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'fullnamelink',
                get_string('certificationname', 'totara_program'),
                "base.fullname",
                array(
                    'joins' => array('base', 'certif_completion'),
                    'defaultheading' => get_string('certificationname', 'totara_program'),
                    'displayfunc' => 'link_program_icon',
                    'extrafields' => array(
                        'programid' => 'base.id',
                        'program_icon' => "base.icon",
                        'program_visible' => 'base.visible',
                        'program_audiencevisible' => 'base.audiencevisible',
                        'userid' => 'certif_completion.userid'
                    ),
                )
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'shortname',
                get_string('programshortname', 'totara_program'),
                'base.shortname',
                array(
                    'joins' => 'base',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'idnumber',
                get_string('programidnumber', 'totara_program'),
                'base.idnumber',
                array(
                    'joins' => 'base',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
        );

        $columnoptions[] = new rb_column_option(
                'base',
                'certifid',
                get_string('certificationid', 'rb_source_dp_certification'),
                'base.certifid',
                array(
                    'joins' => 'base',
                )
        );

        // The date to use for sorting when there is no due date. Sufficiently far in the future.
        $neverduedate = 100000000000;

        $columnoptions[] = new rb_column_option(
                'certif_completion',
                'timedue',
                get_string('certificationduedate', 'totara_program'),
                'CASE WHEN certif_completion.timeexpires > 0
                           THEN certif_completion.timeexpires
                      WHEN prog_completion.timedue IS NULL OR prog_completion.timedue = 0
                           OR prog_completion.timedue = ' . COMPLETION_TIME_NOT_SET . '
                           THEN ' . $neverduedate . '
                      WHEN prog_completion.timedue > ' . time() . ' AND certif_completion.certifpath = ' . CERTIFPATH_CERT . '
                           THEN prog_completion.timedue
                      ELSE 0 END',
                array(
                    'joins' => array('prog_completion', 'certif_completion'),
                    'displayfunc' => 'timedue_date',
                    'dbdatatype' => 'timestamp',
                    'extrafields' => array(
                        'timedue' => 'prog_completion.timedue',
                        'status' => 'certif_completion.status',
                        'programid' => 'base.id',
                        'certifpath' => 'certif_completion.certifpath',
                        'timeexpires' => 'certif_completion.timeexpires',
                        'userid' => 'prog_completion.userid',
                    )
                )
        );

        $columnoptions[] = new rb_column_option(
                'certif_completion',
                'certifpath',
                get_string('certifpath', 'rb_source_dp_certification'),
                'certif_completion.certifpath',
                array(
                    'joins' => 'certif_completion',
                    'displayfunc' => 'certif_certifpath'
                )
        );

        $columnoptions[] = new rb_column_option(
                'certif_completion',
                'status',
                get_string('status', 'rb_source_dp_certification'),
                'certif_completion.status',
                array(
                    'joins' => 'certif_completion',
                    'displayfunc' => 'certif_status',
                    'extrafields' => array(
                        'unassigned' => 'certif_completion.unassigned'
                    )
                )
        );

        $columnoptions[] = new rb_column_option(
                'certif_completion',
                'renewalstatus',
                get_string('renewalstatus', 'rb_source_dp_certification'),
                'certif_completion.renewalstatus',
                array(
                    'joins' => 'certif_completion',
                    'displayfunc' => 'certif_renewalstatus',
                    'extrafields' => array(
                        'unassigned' => 'certif_completion.unassigned'
                    )
                )
        );

        $columnoptions[] = new rb_column_option(
                'certif_completion',
                'timewindowopens',
                get_string('timewindowopens', 'rb_source_dp_certification'),
                'certif_completion.timewindowopens',
                array(
                    'joins' => 'certif_completion',
                    'displayfunc' => 'timewindowopens',
                    'extrafields' => array(
                        'status' => 'certif_completion.status'
                    )
                )
        );

        $columnoptions[] = new rb_column_option(
                'certif_completion',
                'timeexpires',
                get_string('timeexpires', 'rb_source_dp_certification'),
                'certif_completion.timeexpires',
                array(
                    'joins' => 'certif_completion',
                    'displayfunc' => 'timeexpires',
                    'extrafields' => array(
                        'status' => 'certif_completion.status'
                    )
                )
        );

        $columnoptions[] = new rb_column_option(
                'certif_completion',
                'timecompleted',
                get_string('timecompleted', 'rb_source_dp_certification'),
                'certif_completion.timecompleted',
                array(
                    'joins' => 'certif_completion',
                    'displayfunc' => 'nice_date',
                    'dbdatatype' => 'timestamp'
                )
        );

        $columnoptions[] = new rb_column_option(
                'certif_completion_history',
                'historylink',
                get_string('historylink', 'rb_source_dp_certification'),
                'certif_completion_history.historycount',
                array(
                    'joins' => 'certif_completion_history',
                    'defaultheading' => get_string('historylink', 'rb_source_dp_certification'),
                    'displayfunc' => 'historylink',
                    'extrafields' => array(
                        'certifid' => 'certif_completion.certifid',
                        'userid' => 'certif_completion.userid',
                    ),
                )
        );

        $columnoptions[] = new rb_column_option(
                'certif_completion_history',
                'historycount',
                get_string('historycount', 'rb_source_dp_certification'),
                'certif_completion_history.historycount',
                array(
                    'joins' => 'certif_completion_history',
                    'dbdatatype' => 'integer'
                )
        );
        $columnoptions[] = new rb_column_option(
            'certif_completion',
            'progress',
            get_string('progress', 'rb_source_dp_course'),
            "certif_completion.status",
            array(
                'joins' => array('certif_completion'),
                'displayfunc' => 'progress',
                'defaultheading' => get_string('progress', 'rb_source_dp_course'),
                'extrafields' => array(
                    'programid' => "base.id",
                    'userid' => "certif_completion.userid"
                )
            )
        );

        // Include some standard columns.
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_cohort_user_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions, 'course_category', 'base');

        return $columnoptions;
    }


    /**
     * Creates the array of rb_filter_option objects required for $this->filteroptions
     * @return array
     */
    protected function define_filteroptions() {
        $filteroptions = array();

        $filteroptions[] = new rb_filter_option(
                'base',
                'fullname',
                get_string('certificationname', 'totara_program'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'base',
                'shortname',
                get_string('programshortname', 'totara_program'),
                'text'
        );

        $filteroptions[] = new rb_filter_option(
                'base',
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
                'certif_completion',
                'timedue',
                get_string('certificationduedate', 'totara_program'),
                'date'
        );

        $filteroptions[] = new rb_filter_option(
                'certif_completion',
                'certifpath',
                get_string('certifpath', 'rb_source_dp_certification'),
                'select',
                array(
                    'selectfunc' => 'certifpath',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
        );

        $filteroptions[] = new rb_filter_option(
                'certif_completion',
                'status',
                get_string('status', 'rb_source_dp_certification'),
                'select',
                array(
                    'selectfunc' => 'status',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
        );

        $filteroptions[] = new rb_filter_option(
                'certif_completion',
                'renewalstatus',
                get_string('renewalstatus', 'rb_source_dp_certification'),
                'select',
                array(
                    'selectfunc' => 'renewalstatus',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
        );

        $filteroptions[] = new rb_filter_option(
                'certif_completion',
                'timewindowopens',
                get_string('timewindowopens', 'rb_source_dp_certification'),
                'date'
        );

        $filteroptions[] = new rb_filter_option(
                'certif_completion',
                'timeexpires',
                get_string('timeexpires', 'rb_source_dp_certification'),
                'date'
        );

        $filteroptions[] = new rb_filter_option(
                'certif_completion',
                'timecompleted',
                get_string('timecompleted', 'rb_source_dp_certification'),
                'date'
        );

        $filteroptions[] = new rb_filter_option(
                'certif_completion_history',
                'historycount',
                get_string('historycount', 'rb_source_dp_certification'),
                'number'
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
                'userid' => 'certif_completion.userid',
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
                'certif_completion.userid',
                'certif_completion',
                'int'
        );
        // OR status = ' . CERTIFSTATUS_EXPIRED . '
        $paramoptions[] = new rb_param_option(
                'rolstatus',
                '(CASE WHEN prog_completion.status = ' . STATUS_PROGRAM_COMPLETE . ' OR certif_completion.unassigned = 1 THEN \'completed\' ELSE \'active\' END)',
                'prog_completion',
                'string'
        );
        $paramoptions[] = new rb_param_option(
                'category',
                'base.category',
                'base'
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
                'type' => 'base',
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
                'type' => 'base',
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
            $fieldbaseid, $fieldvisible, $fieldaudvis, $fieldalias, 'certification', $report->is_cached()));
    }


    function rb_display_link_program_icon($certificationname, $row) {
        $program = new program($row->programid);
        return $program->display_link_program_icon($certificationname, $row->programid, $row->program_icon, $row->userid);
    }


    function rb_display_timedue_date($time, $row) {
        global $OUTPUT;

        $program = new program($row->programid);

        if (empty($row->timeexpires)) {
            if (empty($row->timedue) || $row->timedue == COMPLETION_TIME_NOT_SET) {
                // There is no time due set.
                return get_string('duedatenotset', 'totara_program');
            } else if ($row->timedue > time() && $row->certifpath == CERTIFPATH_CERT) {
                // User is still in the first stage of certification, not overdue yet.
                return $program->display_duedate($row->timedue, $row->userid, $row->certifpath, $row->status);
            } else {
                // Looks like the certification has expired, overdue!
                return $OUTPUT->error_text(get_string('overdue', 'totara_program'));
            }
        } else {
            return $program->display_duedate($row->timeexpires, $row->userid, $row->certifpath, $row->status);
        }

        return '';
    }


    function rb_display_timewindowopens($time, $row) {
        global $OUTPUT;
        $out = '';

        if (!empty($time)) {
            $out = userdate($time, get_string('strfdateshortmonth', 'langconfig'));

            $extra = '';
            if ($time < time()) {
                // Window is currently open or expired.
                if ($row->status != CERTIFSTATUS_EXPIRED) {
                    $extra = $OUTPUT->notification(get_string('windowopen', 'totara_certification'), 'notifysuccess');
                } else {
                    $extra = $OUTPUT->notification(get_string('status_expired', 'totara_certification'), 'notifyproblem');
                }
            } else {
                // Window is sometime in the future.
                $days_remaining = floor(($time - time()) / 86400);

                if ($days_remaining == 1) {
                    $extra = $OUTPUT->notification(get_string('windowopenin1day', 'totara_certification'), 'notifynotice');
                } else if ($days_remaining < 10 && $days_remaining > 0) {
                    $extra = $OUTPUT->notification(get_string('windowopeninxdays', 'totara_certification', $days_remaining), 'notifynotice');
                }
            }

            if (!empty($extra)) {
                $out .= html_writer::empty_tag('br') . $extra;
            }
        }
        return $out;
    }


    function rb_display_timeexpires($time, $row) {
        global $OUTPUT;

        $out = '';

        if (!empty($time)) {
            $out = userdate($time, get_string('strfdateshortmonth', 'langconfig'));

            $days = '';
            if ($row->status != CERTIFSTATUS_EXPIRED) {
                $days_remaining = floor(($time - time()) / 86400);
                if ($days_remaining == 1) {
                    $days = get_string('onedayremaining', 'totara_program');
                } else if ($days_remaining < 10 && $days_remaining > 0) {
                    $days = get_string('daysremaining', 'totara_program', $days_remaining);
                } else if ($time < time()) {
                    $days = get_string('overdue', 'totara_plan');
                }
                if ($days != '') {
                    $out .= html_writer::empty_tag('br') . $OUTPUT->error_text($days);
                }
            } else if ($row->status != CERTIFSTATUS_EXPIRED) {
                $out .= html_writer::empty_tag('br') . $OUTPUT->error_text(get_string('expired'));
            }
        }
        return $out;
    }


    public function rb_display_historylink($name, $row) {
        global $OUTPUT;
        return $OUTPUT->action_link(new moodle_url('/totara/plan/record/certifications.php',
                array('certifid' => $row->certifid, 'userid' => $row->userid, 'history' => 1)), $name);
    }


    function rb_display_certif_certifpath($certifpath, $row) {
        global $CERTIFPATH;
        if ($certifpath && isset($CERTIFPATH[$certifpath])) {
            return get_string($CERTIFPATH[$certifpath], 'totara_certification');
        }
    }


    function rb_display_certif_status($status, $row) {
        global $CERTIFSTATUS;
        if ($status && isset($CERTIFSTATUS[$status])) {
            $unassigned = '';
            if ($row->unassigned) {
                $unassigned = get_string('unassigned', 'rb_source_dp_certification');
            }
            return get_string($CERTIFSTATUS[$status], 'totara_certification') .' '. $unassigned;
        }
    }

    function rb_display_certif_renewalstatus($renewalstatus, $row) {
        global $CERTIFRENEWALSTATUS;
        if ($renewalstatus && isset($CERTIFRENEWALSTATUS[$renewalstatus])) {
            return get_string($CERTIFRENEWALSTATUS[$renewalstatus], 'totara_certification');
        } else {
            return get_string($CERTIFRENEWALSTATUS[CERTIFRENEWALSTATUS_NOTDUE], 'totara_certification');
        }
    }

    function rb_display_progress($status, $row) {
        $program = new program($row->programid);
        return $program->display_progress($row->userid);
    }


    function rb_filter_certifpath() {
        global $CERTIFPATH;

        $out = array();
        foreach ($CERTIFPATH as $code => $cpstring) {
            $out[$code] = get_string($cpstring, 'totara_certification');
        }
        return $out;
    }


    function rb_filter_status() {
        global $CERTIFSTATUS;

        $out = array();
        foreach ($CERTIFSTATUS as $code => $statusstring) {
            $out[$code] = get_string($statusstring, 'totara_certification');
        }
        return $out;
    }


    function rb_filter_renewalstatus() {
        global $CERTIFRENEWALSTATUS;

        $out = array();
        foreach ($CERTIFRENEWALSTATUS as $code => $statusstring) {
            $out[$code] = get_string($statusstring, 'totara_certification');
        }
        return $out;
    }
}
