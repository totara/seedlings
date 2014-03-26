<?php

defined('MOODLE_INTERNAL') || die();

class rb_source_assignmentsummary extends rb_base_source {

    const LANG = 'rb_source_assignmentsummary';

    /**
     * @var string
     */
    public $base;

    /**
     * @var array
     */
    public $joinlist;

    /**
     * @var array
     */
    public $columnoptions;

    /**
     * @var array
     */
    public $filteroptions;

    /**
     * @var array
     */
    public $requiredcolumns;

    /**
     * @var string
     */
    public $sourcetitle;

    /**
     * c'tor
     */
    public function __construct() {
        global $DB;
        $this->base = "(" .
        " SELECT a.id AS id," .
        " a.course AS assignment_course," .
        " a.name AS assignment_name," .
        " {$DB->sql_order_by_text('a.intro', '255')} AS assignment_intro," .
        " a.assignmenttype AS assignment_type," .
        " AVG(asb.grade) AS average_grade," .
        " SUM(asb.grade) AS sum_grade," .
        " MIN(asb.grade) AS min_grade," .
        " MAX(asb.grade) AS max_grade," .
        " MIN(asb.timemodified) AS min_timemodified," .
        " MAX(asb.timemodified) AS max_timemodified," .
        " MIN(asb.timemarked) AS min_timemarked," .
        " MAX(asb.timemarked) AS max_timemarked," .
        " a.grade AS assignment_maxgrade," .
        " COUNT(asb.userid) AS user_count" .
        " FROM {assignment_submissions} asb" .
        " INNER JOIN {assignment} a ON asb.assignment = a.id" .
        " WHERE a.grade > -1" . // meaningful aggregations are only possible for numeric grade scales
        " AND asb.grade > -1" . // meaningful aggregations are only possible for graded/marked assignment submissions
        " GROUP BY a.id, a.course, a.name, {$DB->sql_order_by_text('a.intro', '255')}, a.assignmenttype, a.grade" .
        " )";
        $this->joinlist = $this->_define_joinlist();
        $this->columnoptions = $this->_define_columnoptions();
        $this->filteroptions = $this->_define_filteroptions();
        $this->requiredcolumns = array();
        $this->defaultcolumns = $this->_define_defaultcolumns();
        $this->sourcetitle = $this->_get_string('sourcetitle');
        parent::__construct();
    }

    /**
     * define join list
     * @return array
     */
    protected function _define_joinlist() {
        $a = array();

        // join courses and categories
        $this->add_course_table_to_joinlist($a, 'base', 'assignment_course');
        $this->add_course_category_table_to_joinlist($a, 'course', 'category');

        return $a;
    }

    /**
     * define column options
     * @return array
     */
    protected function _define_columnoptions() {
        $a = array();

        // Assignment name.
        $a[] = new rb_column_option(
            'base',
            'name',
            $this->_get_string("assignmentname"),
            "base.assignment_name",
            array('dbdatatype' => 'char',
                  'outputformat' => 'text')
        );

        // Assignment intro.
        $a[] = new rb_column_option(
            'base',
            'intro',
            $this->_get_string("assignmentintro"),
            "base.assignment_intro",
            array('dbdatatype' => 'text',
                  'outputformat' => 'text')
        );

        // Assignment maxgrade.
        $a[] = new rb_column_option(
            'base',
            'maxgrade',
            $this->_get_string("assignmentmaxgrade"),
            "base.assignment_maxgrade"
        );

        // assignment type
        $a[] = new rb_column_option(
            'base',
            'type',
            $this->_get_string('assignmenttype'),
            'base.assignment_type',
            array('displayfunc' => 'assignmenttype')
        );

        // aggregate functions
        $cols = array('average', 'sum', 'min', 'max');
        foreach ($cols as $col) {
            $a[] = new rb_column_option(
                'base',
                $col,
                $this->_get_string("{$col}grade"),
                "base.{$col}_grade",
                array('displayfunc' => 'roundgrade')
            );
        }

        // min/max time modified
        $cols = array('min', 'max');
        foreach ($cols as $col) {
            $a[] = new rb_column_option(
                'base',
                "{$col}_timemodified",
                $this->_get_string("{$col}lastmodified"),
                "base.{$col}_timemodified",
                array('displayfunc' => 'nice_datetime')
            );
        }

        // min/max time marked
        $cols = array('min', 'max');
        foreach ($cols as $col) {
            $a[] = new rb_column_option(
                'base',
                "{$col}_timemarked",
                $this->_get_string("{$col}lastmarked"),
                "base.{$col}_timemarked",
                array('displayfunc' => 'nice_datetime')
            );
        }

        // user count
        $a[] = new rb_column_option(
            'base',
            'user_count',
            $this->_get_string('usercount'),
            'base.user_count'
        );

        // course and category fields
        $this->add_course_fields_to_columns($a);
        $this->add_course_category_fields_to_columns($a);

        return $a;
    }

    /**
     * define filter options
     * @return array
     */
    protected function _define_filteroptions() {
        $a = array();

        // assignment columns
        $cols = array('name', 'intro');
        foreach ($cols as $col) {
            $a[] = new rb_filter_option(
                'base',
                $col,
                $this->_get_string("assignment{$col}"),
                'text'
            );
        }

        // assignment type
        $a[] = new rb_filter_option(
            'base',
            'type',
            $this->_get_string('assignmenttype'),
            'select',
            array('selectfunc' => 'assignmenttype')
        );

        // min/max last modified
        $cols = array('min', 'max');
        foreach ($cols as $col) {
            $a[] = new rb_filter_option(
                'base',
                "{$col}_timemodified",
                $this->_get_string("{$col}lastmodified"),
                'date'
            );
        }

        // min/max last marked
        $cols = array('min', 'max');
        foreach ($cols as $col) {
            $a[] = new rb_filter_option(
                'base',
                "{$col}_timemarked",
                $this->_get_string("{$col}lastmarked"),
                'date'
            );
        }

        // course and category filters
        $this->add_course_fields_to_filters($a);
        $this->add_course_category_fields_to_filters($a);

        return $a;
    }

    /**
     * define default columns
     * @return array
     */
    protected function _define_defaultcolumns() {
        $a = array();
        $a[] = array('type' => 'base', 'value' => 'name');
        $a[] = array('type' => 'base', 'value' => 'user_count');
        $a[] = array('type' => 'base', 'value' => 'average');
        return $a;
    }

    /**
     * display the assignment type
     * @param string $field
     * @param object $record
     * @param boolean $isexport
     */
    public function rb_display_assignmenttype($field, $record, $isexport) {
        return get_string("type{$field}", 'assignment');
    }

    /**
     * display a number rounded to the nearest integer
     * @param string $field
     * @param object $record
     * @param boolean $isexport
     */
    public function rb_display_roundgrade($field, $record, $isexport) {
        return (integer)round($field);
    }

    /**
     * filter assignment types
     * @return array
     */
    public function rb_filter_assignmenttype() {
        global $CFG;
        require_once("{$CFG->dirroot}/mod/assignment/lib.php");
        return assignment_types();
    }

    /**
     * gets a language string
     * @param string $key
     * @return string
     */
    private function _get_string($key) {
        return get_string($key, self::LANG);
    }

}
