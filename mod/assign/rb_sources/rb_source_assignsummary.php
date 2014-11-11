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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package mod_assign
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_assignsummary extends rb_base_source {

    public $base, $joinlist, $columnoptions, $filteroptions;
    public $defaultcolumns, $defaultfilters, $requiredcolumns;
    public $sourcetitle;

    public function __construct() {
        global $DB;
        $this->base = "(" .
        " SELECT a.id AS id," .
        " a.course AS assignment_course," .
        " a.name AS assignment_name," .
        " {$DB->sql_order_by_text('a.intro', '255')} AS assignment_intro," .
        " AVG(ag.grade) AS average_grade," .
        " SUM(ag.grade) AS sum_grade," .
        " MIN(ag.grade) AS min_grade," .
        " MAX(ag.grade) AS max_grade," .
        " MIN(asb.timemodified) AS min_timemodified," .
        " MAX(asb.timemodified) AS max_timemodified," .
        " MIN(ag.timemodified) AS min_timemarked," .
        " MAX(ag.timemodified) AS max_timemarked," .
        " a.grade AS assignment_maxgrade," .
        " COUNT(asb.userid) AS user_count" .
        " FROM {assign_submission} asb" .
        " INNER JOIN {assign} a ON asb.assignment = a.id" .
        " INNER JOIN {assign_grades} ag ON ag.assignment = a.id AND ag.userid = asb.userid" .
        " WHERE ag.grade > -1" . // Meaningful aggregations are only possible for numeric grade scales.
        " GROUP BY a.id, a.course, a.name, {$DB->sql_order_by_text('a.intro', '255')}, a.grade" .
        " )";
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->requiredcolumns = array();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_assignsummary');
        parent::__construct();
    }

    /**
     * Define join list
     * @return array
     */
    protected function define_joinlist() {
        $a = array();

        // Join courses and categories.
        $this->add_course_table_to_joinlist($a, 'base', 'assignment_course');
        $this->add_course_category_table_to_joinlist($a, 'course', 'category');

        return $a;
    }

    /**
     * Define column options
     * @return array
     */
    protected function define_columnoptions() {

        $columnoptions = array(
            // Assignment name.
            new rb_column_option(
                'base',
                'name',
                get_string('assignmentname', 'rb_source_assignsummary'),
                'base.assignment_name',
                array('dbdatatype' => 'char',
                'outputformat' => 'text')
            ),

            // Assignment intro.
            new rb_column_option(
                'base',
                'intro',
                get_string('assignmentintro', 'rb_source_assignsummary'),
                'base.assignment_intro',
                array('dbdatatype' => 'text',
                'outputformat' => 'text')
            ),

            // Assignment maxgrade.
            new rb_column_option(
                'base',
                'maxgrade',
                get_string('assignmentmaxgrade', 'rb_source_assignsummary'),
                'base.assignment_maxgrade'
            ),

            // User count.
            new rb_column_option(
                'base',
                'user_count',
                get_string('usercount', 'rb_source_assignsummary'),
                'base.user_count'
            )
        );

        // Aggregate functions.
        $cols = array('average', 'sum', 'min', 'max');
        foreach ($cols as $col) {
            $columnoptions[] = new rb_column_option(
                'base',
                $col,
                get_string("{$col}grade", 'rb_source_assignsummary'),
                "base.{$col}_grade",
                array('displayfunc' => 'roundgrade')
            );
        }

        // MIN/MAX time modified.
        $cols = array('min', 'max');
        foreach ($cols as $col) {
            $columnoptions[] = new rb_column_option(
                'base',
                "{$col}_timemodified",
                get_string("{$col}lastmodified", 'rb_source_assignsummary'),
                "base.{$col}_timemodified",
                array('displayfunc' => 'nice_datetime')
            );
        }

        // MIN/MAX time marked.
        $cols = array('min', 'max');
        foreach ($cols as $col) {
            $columnoptions[] = new rb_column_option(
                'base',
                "{$col}_timemarked",
                get_string("{$col}lastmarked", 'rb_source_assignsummary'),
                "base.{$col}_timemarked",
                array('displayfunc' => 'nice_datetime')
            );
        }

        // Course and category fields.
        $this->add_course_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    /**
     * Define filter options
     * @return array
     */
    protected function define_filteroptions() {
        $filteroptions = array();

        // assignment columns
        $cols = array('name', 'intro');
        foreach ($cols as $col) {
            $a[] = new rb_filter_option(
                'base',
                $col,
                get_string("assignment{$col}", 'rb_source_assignsummary'),
                'text'
            );
        }


        // min/max last modified
        $cols = array('min', 'max');
        foreach ($cols as $col) {
            $a[] = new rb_filter_option(
                'base',
                "{$col}_timemodified",
                get_string("{$col}lastmodified", 'rb_source_assignsummary'),
                'date'
            );
        }

        // min/max last marked
        $cols = array('min', 'max');
        foreach ($cols as $col) {
            $filteroptions[] = new rb_filter_option(
                'base',
                "{$col}_timemarked",
                get_string("{$col}lastmarked", 'rb_source_assignsummary'),
                'date'
            );
        }

        // Course and category filters.
        $this->add_course_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    /**
     * Define default columns.
     * @return array
     */
    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'base',
                'value' => 'name'
            ),
            array(
                'type' => 'base',
                'value' => 'user_count'
            ),
            array(
                'type' => 'base',
                'value' => 'average'
            )
        );

        return $defaultcolumns;
    }

    /**
     * Display a number rounded to the nearest integer
     * @param string $field
     * @param object $record
     * @param boolean $isexport
     */
    public function rb_display_roundgrade($field, $record, $isexport) {
        return (integer)round($field);
    }
}
