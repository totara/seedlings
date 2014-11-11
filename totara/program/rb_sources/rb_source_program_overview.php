<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2013 onwards, Catalyst IT
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
 * @author Matt Clarkson <mattc@catalyst.net.nz>
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_program_overview extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

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
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_program_overview');
        parent::__construct();
    }

    //
    // Methods for defining contents of source.
    //
    protected function define_joinlist() {
        global $CFG;

        $joinlist = array();

        $this->add_program_table_to_joinlist($joinlist, 'base', 'programid');
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_manager_tables_to_joinlist($joinlist, 'position_assignment', 'reportstoid');
        $this->add_course_category_table_to_joinlist($joinlist, 'course', 'category');

        $joinlist[] = new rb_join(
            'prog_courseset',
            'INNER',
            '{prog_courseset}',
            "prog_courseset.programid = base.programid AND base.coursesetid = 0",
            REPORT_BUILDER_RELATION_ONE_TO_MANY,
            'base'
        );

        $joinlist[] = new rb_join(
            'prog_completion',
            'LEFT',
            '{prog_completion}',
            "prog_completion.programid = base.programid AND prog_completion.userid = base.userid AND prog_courseset.id = prog_completion.coursesetid",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'prog_courseset'
        );


        $joinlist[] = new rb_join(
            'prog_courseset_course',
            'INNER',
            '{prog_courseset_course}',
            "prog_courseset_course.coursesetid = prog_courseset.id",
            REPORT_BUILDER_RELATION_ONE_TO_MANY,
            'prog_courseset'
        );

        $joinlist[] = new rb_join(
            'course',
            'INNER',
            '{course} ', // Intentional space to stop report builder adding unwanted custom course fields.
            "prog_courseset_course.courseid = course.id",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'prog_courseset_course'
        );

        $joinlist[] = new rb_join(
            'course_completions',
            'LEFT',
            '{course_completions}',
            "course_completions.course = course.id AND course_completions.userid = base.userid",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'course'
        );

        $joinlist[] = new rb_join(
            'grade_items',
            'LEFT',
            '{grade_items}',
            "grade_items.itemtype = 'course' AND grade_items.courseid = course.id",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'course'
        );

        $joinlist[] = new rb_join(
            'grade_grades',
            'LEFT',
            '{grade_grades}',
            "grade_grades.itemid = grade_items.id AND grade_grades.userid = base.userid",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'grade_items'
        );

        require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');

        $joinlist[] = new rb_join(
            'criteria',
            'LEFT',
            '{course_completion_criteria}',
            "criteria.course = prog_courseset_course.courseid AND criteria.criteriatype = " . COMPLETION_CRITERIA_TYPE_GRADE,
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'prog_courseset_course'
        );

        $joinlist[] = new rb_join(
            'cplorganisation',
            'LEFT',
            '{org}',
            "cplorganisation.id = base.organisationid",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'base'
        );

        $joinlist[] = new rb_join(
            'cplorganisation_type',
            'LEFT',
            '{org}',
            "cplorganisation_type.id = cplorganisation.typeid",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'cplorganisation'
        );

        $joinlist[] = new rb_join(
            'cplposition',
            'LEFT',
            '{pos}',
            "cplposition.id = base.positionid",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'base'
        );

        $joinlist[] = new rb_join(
            'cplposition_type',
            'LEFT',
            '{pos_type}',
            "cplposition_type.id = cplposition.typeid",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'cplposition'
        );

        return $joinlist;
    }

    protected function define_columnoptions() {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/completion/completion_completion.php');

        $columnoptions = array();

        // Include some standard columns.
        $this->add_program_fields_to_columns($columnoptions, 'program');
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);

        // Programe completion cols.
        $columnoptions[] = new rb_column_option(
            'program_completion',
            'timedue',
            get_string('duedate', 'rb_source_program_overview'),
            'base.timedue',
            array(
                'joins' => 'base',
                'displayfunc' => 'prog_date',
                'dbdatatype' => 'timestamp',
            )
        );

        $columnoptions[] = new rb_column_option(
            'program_completion',
            'timestarted',
            get_string('timestart', 'rb_source_program_overview'),
            'base.timestarted',
            array(
                'joins' => 'base',
                'displayfunc' => 'prog_date',
                'dbdatatype' => 'timestamp',
                'extrafields' => array('prog_id' => 'program.id')
            )
        );

        $columnoptions[] = new rb_column_option(
            'program_completion',
            'timecompleted',
            get_string('timecompleted', 'rb_source_program_overview'),
            'base.timecompleted',
            array(
                'joins' => 'base',
                'displayfunc' => 'prog_date',
                'dbdatatype' => 'timestamp',
            )
        );

        $columnoptions[] = new rb_column_option(
            'program_completion',
            'status',
            get_string('programcompletionstatus','rb_source_program_overview'),
            "base.status",
            array(
                'joins' => 'base',
                'displayfunc' => 'program_completion_status'
            )
        );

        $columnoptions[] = new rb_column_option(
            'program_completion',
            'progress',
            get_string('programcompletionprogress','rb_source_program_overview'),
            $DB->sql_concat_join("'|'", array(sql_cast2char('prog_courseset.id'), sql_cast2char("prog_completion.status"))),
            array(
                'displayfunc' => 'program_completion_progress',
                'grouping' => 'comma_list',
                'joins' => 'prog_completion',
            )
        );

        // Organisation Cols.
        $columnoptions[] = new rb_column_option(
            'program_completion',
            'orgshortname',
            get_string('completionorganisationshortname', 'rb_source_program_overview'),
            'cplorganisation.shortname',
            array(
                'joins' => 'cplorganisation',
                'dbdatatype' => 'char',
                'outputformat' => 'text'
            )

        );

        $columnoptions[] = new rb_column_option(
            'program_completion',
            'orgfullname',
            get_string('completionorganisationfullname', 'rb_source_program_overview'),
            'cplorganisation.fullname',
            array(
                'joins' => 'cplorganisation',
                'dbdatatype' => 'char',
                'outputformat' => 'text'
            )
        );

        $columnoptions[] = new rb_column_option(
            'program_completion',
            'orgtype',
            get_string('completionorganisationtype', 'rb_source_program_overview'),
            'cplorganisation_type.fullname',
            array(
                'joins' => 'cplorganisation_type',
                'dbdatatype' => 'char',
                'outputformat' => 'text'
            )
        );

        // Completion Position Cols.
        $columnoptions[] = new rb_column_option(
            'program_completion',
            'fullname',
            get_string('completionpositionfullname', 'rb_source_program_overview'),
            'cplposition.fullname',
            array(
                'joins' => 'cplposition',
                'dbdatatype' => 'char',
                'outputformat' => 'text'
            )

        );

        $columnoptions[] = new rb_column_option(
            'program_completion',
            'type',
            get_string('completionpositiontype', 'rb_source_program_overview'),
            'cplposition_type.fullname',
            array(
                'joins' => 'cplposition_type',
                'dbdatatype' => 'char',
                'outputformat' => 'text'
            )

        );

        $columnoptions[] = new rb_column_option(
            'program_completion',
            'idnumber',
            get_string('completionpositionidnumber', 'rb_source_program_overview'),
            'cplposition.idnumber',
            array(
                'joins' => 'cplposition',
                'dbdatatype' => 'char',
                'outputformat' => 'text'
            )

        );

        // Course completion cols.
        $columnoptions[] = new rb_column_option(
            'course',
            'shortname',
            get_string('courseshortname', 'rb_source_program_overview'),
            'COALESCE('.$DB->sql_concat_join("'|'", array('course.shortname', sql_cast2char('course.id'))).', \'-\')',
            array(
                'joins' => 'course',
                'grouping' => 'comma_list',
                'displayfunc' => 'list_to_newline_coursename',
                'style' => array('white-space' => 'pre'),
            )

        );

        $columnoptions[] = new rb_column_option(
            'course',
            'status',
            get_string('coursecompletionstatus', 'rb_source_program_overview'),
            sql_cast2char('COALESCE(course_completions.status, '.COMPLETION_STATUS_NOTYETSTARTED.')'),
            array(
                'joins' => 'course_completions',
                'grouping' => 'comma_list',
                'displayfunc' => 'course_completion_status',
                'style' => array('white-space' => 'pre'),
            )

        );

        $columnoptions[] = new rb_column_option(
            'course',
            'timeenrolled',
            get_string('coursecompletiontimeenrolled', 'rb_source_program_overview'),
            'COALESCE('.sql_cast2char('course_completions.timeenrolled').', \'-\')',
            array(
                'joins' => 'course_completions',
                'grouping' => 'comma_list',
                'displayfunc' => 'list_to_newline_date',
                'style' => array('white-space' => 'pre'),
            )
        );

        $columnoptions[] = new rb_column_option(
            'course',
            'timestarted',
            get_string('coursecompletiontimestarted', 'rb_source_program_overview'),
            'COALESCE('.sql_cast2char('course_completions.timestarted').', \'-\')',
            array(
                'joins' => 'course_completions',
                'grouping' => 'comma_list',
                'displayfunc' => 'list_to_newline_date',
                'style' => array('white-space' => 'pre'),
            )
        );

        $columnoptions[] = new rb_column_option(
            'course',
            'timecompleted',
            get_string('coursecompletiontimecompleted', 'rb_source_program_overview'),
            'COALESCE('.sql_cast2char('course_completions.timestarted').', \'-\')',
            array(
                'joins' => 'course_completions',
                'grouping' => 'comma_list',
                'displayfunc' => 'list_to_newline_date',
                'style' => array('white-space' => 'pre'),
            )
        );

        // Course grade.
        $columnoptions[] = new rb_column_option(
            'course',
            'finalgrade',
            get_string('finalgrade', 'rb_source_program_overview'),
            'COALESCE('.sql_cast2char('grade_grades.finalgrade').', \'-\')',
            array(
                'joins' => 'grade_grades',
                'grouping' => 'comma_list',
                'displayfunc' => 'list_to_newline',
                'style' => array('white-space' => 'pre'),
            )
        );

        $columnoptions[] = new rb_column_option(
            'course',
            'gradepass',
            get_string('gradepass', 'rb_source_program_overview'),
            'COALESCE('.sql_cast2char('criteria.gradepass').', \'-\')',
            array(
                'joins' => 'criteria',
                'grouping' => 'comma_list',
                'displayfunc' => 'list_to_newline',
                'style' => array('white-space' => 'pre'),
            )
        );


        // Course category.
        $columnoptions[] = new rb_column_option(
            'course',
            'name',
            get_string('coursecategory', 'totara_reportbuilder'),
            "course_category.name",
            array(
                'joins' => 'course_category',
                'grouping' => 'comma_list',
                'displayfunc' => 'list_to_newline',
                'style' => array('white-space' => 'pre'),
                'dbdatatype' => 'char',
                'outputformat' => 'text'
            )
        );

        $columnoptions[] = new rb_column_option(
            'course',
            'namelink',
            get_string('coursecategorylinked', 'totara_reportbuilder'),
            "course_category.name",
            array(
                'joins' => 'course_category',
                'displayfunc' => 'link_course_category',
                'defaultheading' => get_string('category', 'totara_reportbuilder'),
                'extrafields' => array('cat_id' => "course_category.id", 'cat_visible' => "course_category.visible"),
                'grouping' => 'comma_list',
                'displayfunc' => 'list_to_newline',
                'style' => array('white-space' => 'pre'),
            )
        );

        $columnoptions[] = new rb_column_option(
            'course',
            'id',
            get_string('coursecategoryid', 'totara_reportbuilder'),
            "course_category.idnumber",
            array(
                'joins' => array('course', 'course_category'),
                'grouping' => 'comma_list',
                'displayfunc' => 'list_to_newline',
                'style' => array('white-space' => 'pre'),
                'dbdatatype' => 'char',
                'outputformat' => 'text'
            )
        );

        return $columnoptions;
    }


    protected function define_filteroptions() {
        $filteroptions = array();

        $this->add_user_fields_to_filters($filteroptions);
        $this->add_program_fields_to_filters($filteroptions);

        $filteroptions[] = new rb_filter_option(
            'prog',
            'id',
            get_string('programnameselect', 'rb_source_program_overview'),
            'select',
            array(
                'selectfunc' => 'program_list',
                'attributes' => rb_filter_option::select_width_limiter(),
                'simplemode' => true,
                'noanychoice' => true,
            )
        );

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
                'cplorganisation.path',
                'cplorganisation'
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
                'visible',
                'program.visible',
                'program'
            ),
        );
        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array('type' => 'prog', 'value' => 'shortname'),
            array('type' => 'user', 'value' => 'organisation'),
            array('type' => 'user', 'value' => 'position'),
            array('type' => 'user', 'value' => 'namelink'),
            array('type' => 'program_completion', 'value' => 'status'),
            array('type' => 'program_completion', 'value' => 'timedue'),
            array('type' => 'program_completion', 'value' => 'progress'),
            array('type' => 'course', 'value' => 'shortname'),
            array('type' => 'course', 'value' => 'status'),
            array('type' => 'course', 'value' => 'finalgrade'),
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'prog',
                'value' => 'id',
                'advanced' => 0,
            ),
        );
        return $defaultfilters;
    }

    protected function define_requiredcolumns() {
        $requiredcolumns = array();
        $requiredcolumns[] = new rb_column(
            'prog',
            'groupbycol',
            '',
            "program.id",
            array(
                'joins' => 'program',
                'hidden' => true,
            )
        );
        return $requiredcolumns;
    }

    //
    // Source specific column display methods.
    //
    function rb_display_program_completion_status($status, $row) {
        if (is_null($status)) {
            return '';
        }
        if ($status) {
            return get_string('complete', 'rb_source_program_overview');
        } else {
            return get_string('incomplete', 'rb_source_program_overview');
        }
    }

    function rb_display_course_completion_status($status, $row) {
        global $COMPLETION_STATUS;

        $items = explode(', ', $status);
        foreach ($items as $key => $item) {
            if (in_array($item, array_keys($COMPLETION_STATUS))) {
                $items[$key] = get_string('coursecompletion_'.$COMPLETION_STATUS[$item], 'rb_source_program_overview');
            } else {
                $items[$key] = get_string('coursecompletion_notyetstarted', 'rb_source_program_overview');
            }
        }
        return implode($items, "\n");
    }

    function rb_display_list_to_newline($status, $row) {
        $items = explode(', ', $status);
        return implode($items, "\n");
    }

    function rb_display_list_to_newline_date($date, $row) {
         $items = explode(', ', $date);
         foreach ($items as $key => $item) {
             $items[$key] = $this->rb_display_prog_date($item, $row);
         }

        return implode($items, "\n");
    }

    function rb_display_list_to_newline_coursename($date, $row) {
         $items = explode(', ', $date);
         foreach ($items as $key => $item) {
             list($coursename, $id) = explode('|', $item);
             $url = new moodle_url('/course/view.php', array('id' => $id));
             $items[$key] = html_writer::link($url, format_string($coursename));
         }

        return implode($items, "\n");
    }

    function rb_display_program_completion_progress($status, $row, $isexport = false) {
        global $PAGE;

        $completions = array();
        $tempcompletions = explode(', ', $status);
        foreach($tempcompletions as $completion) {
            $coursesetstatus = explode("|", $completion);
            if (isset($coursesetstatus[1])) {
                $completions[$coursesetstatus[0]] = $coursesetstatus[1];
            } else {
                $completions[$coursesetstatus[0]] =  STATUS_COURSESET_INCOMPLETE;
            }
        }

        $cnt = count($completions);
        if ($cnt == 0) {
            return '-';
        }
        $complete = 0;

        foreach ($completions as $comp) {
            if ($comp == STATUS_COURSESET_COMPLETE) {
                $complete++;
            }
        }

        $percentage = round(($complete / $cnt) * 100, 2);
        $totara_renderer = $PAGE->get_renderer('totara_core');

        // Get relevant progress bar and return for display.
        return $totara_renderer->print_totara_progressbar($percentage, 'medium', $isexport, $percentage . '%');
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

    // Source specific filter display methods.
    function rb_filter_program_list() {
        global $CFG;

        require_once($CFG->dirroot . '/totara/program/lib.php');

        $list = array();

        if ($progs = prog_get_programs('all', 'p.fullname', 'p.id, p.fullname', 'program')) {
            foreach ($progs as $prog) {
                $list[$prog->id] = format_string($prog->fullname);
            }
        }
        return ($list);
    }


}
