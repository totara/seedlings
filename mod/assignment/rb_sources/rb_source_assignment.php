<?php

defined('MOODLE_INTERNAL') || die();

class rb_source_assignment extends rb_base_source {

    const LANG = 'rb_source_assignment';

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
        $this->base = '{assignment_submissions}';
        $this->joinlist = $this->_define_joinlist();
        $this->columnoptions = $this->_define_columnoptions();
        $this->filteroptions = $this->_define_filteroptions();
        $this->requiredcolumns = $this->_define_requiredcolumns();
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

        // join assignment
        $cond = 'assignment.id = base.assignment';
        $a[] = new rb_join(
            'assignment',
            'INNER',
            '{assignment}',
            $cond,
            REPORT_BUILDER_RELATION_MANY_TO_ONE
        );

        // join grade_items
        $cond = 'grade_items.courseid = assignment.course AND grade_items.itemmodule = \'assignment\' AND grade_items.iteminstance = assignment.id';
        $a[] = new rb_join(
            'grade_items',
            'INNER',
            '{grade_items}',
            $cond,
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'assignment'
        );

        // join grade_grades
        $cond = 'grade_grades.itemid = grade_items.id AND grade_grades.userid = base.userid';
        $a[] = new rb_join(
            'grade_grades',
            'LEFT',
            '{grade_grades}',
            $cond,
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'grade_items'
        );

        // join scale
        $cond = 'scale.id = grade_items.scaleid';
        $a[] = new rb_join(
            'scale',
            'LEFT',
            '{scale}',
            $cond,
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'grade_items'
        );

        // join users, courses and categories
        $this->add_user_table_to_joinlist($a, 'base', 'userid');
        $this->add_course_table_to_joinlist($a, 'assignment', 'course');
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
            'assignment',
            'name',
            $this->_get_string("assignmentname"),
            "assignment.name",
            array('joins' => 'assignment',
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );

        // Assignment intro.
        $a[] = new rb_column_option(
            'assignment',
            'intro',
            $this->_get_string("assignmentintro"),
            "assignment.intro",
            array('joins' => 'assignment',
                  'dbdatatype' => 'text',
                  'outputformat' => 'text')
        );

        // Assignment type.
        $a[] = new rb_column_option(
            'assignment',
            'assignmenttype',
            $this->_get_string('assignmenttype'),
            'assignment.assignmenttype',
            array('displayfunc' => 'assignmenttype', 'joins' => 'assignment')
        );

        // grade scale values
        $a[] = new rb_column_option(
            'scale',
            'scale_values',
            $this->_get_string('gradescalevalues'),
            'scale.scale',
            array('displayfunc' => 'scalevalues', 'joins' => 'scale')
        );

        // submission grade
        $a[] = new rb_column_option(
            'base',
            'grade',
            $this->_get_string('submissiongrade'),
            'base.grade',
            array('displayfunc' => 'submissiongrade', 'extrafields' => array('scale_values' => 'scale.scale'))
        );

        // submission comment
        $a[] = new rb_column_option(
            'base',
            'comment',
            $this->_get_string('submissioncomment'),
            'base.submissioncomment',
            array('dbdatatype' => 'text',
                  'outputformat' => 'text')
        );

        // last modified
        $a[] = new rb_column_option(
            'base',
            'timemodified',
            $this->_get_string('lastmodified'),
            'base.timemodified',
            array('displayfunc' => 'nice_datetime')
        );

        // last marked
        $a[] = new rb_column_option(
            'base',
            'timemarked',
            $this->_get_string('lastmarked'),
            'base.timemarked',
            array('displayfunc' => 'nice_datetime')
        );

        // max grade
        $a[] = new rb_column_option(
            'grade_grades',
            'maxgrade',
            $this->_get_string('maxgrade'),
            'grade_grades.rawgrademax',
            array('displayfunc' => 'maxgrade', 'joins' => 'grade_grades')
        );

        // min grade
        $a[] = new rb_column_option(
            'grade_grades',
            'mingrade',
            $this->_get_string('mingrade'),
            'grade_grades.rawgrademin',
            array('displayfunc' => 'mingrade', 'joins' => 'grade_grades')
        );

        // user, course and category fields
        $this->add_user_fields_to_columns($a);
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
                'assignment',
                $col,
                $this->_get_string("assignment{$col}"),
                'text'
            );
        }

        // assignment type
        $a[] = new rb_filter_option(
            'assignment',
            'assignmenttype',
            $this->_get_string('assignmenttype'),
            'select',
            array('selectfunc' => 'assignmenttype')
        );

        // submission grade
        $a[] = new rb_filter_option(
            'base',
            'grade',
            $this->_get_string('submissiongrade'),
            'number'
        );

        // last modified
        $a[] = new rb_filter_option(
            'base',
            'timemodified',
            $this->_get_string('lastmodified'),
            'date'
        );

        // last marked
        $a[] = new rb_filter_option(
            'base',
            'timemarked',
            $this->_get_string('lastmarked'),
            'date'
        );

        // user, course and category filters
        $this->add_user_fields_to_filters($a);
        $this->add_course_fields_to_filters($a);
        $this->add_course_category_fields_to_filters($a);

        return $a;
    }

    /**
     * define required columns
     * @return array
     */
    protected function _define_requiredcolumns() {
        $a = array();

        // scale id
        $a[] = new rb_column(
            'scale',
            'scaleid',
            '',
            'scale.id',
            array('hidden' => true, 'joins' => 'scale')
        );

        return $a;
    }

    /**
     * define default columns
     * @return array
     */
    protected function _define_defaultcolumns() {
        $a = array();
        $a[] = array('type' => 'assignment', 'value' => 'name');
        $a[] = array('type' => 'user', 'value' => 'fullname');
        $a[] = array('type' => 'base', 'value' => 'grade');
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
     * display the scale values
     * @param string $field
     * @param object $record
     * @param boolean $isexport
     */
    public function rb_display_scalevalues($field, $record, $isexport) {
        // if there's no scale values, return an empty string
        if (empty($record->scale_values)) {
            return '';
        }

        // if there are scale values, format them nicely
        $v = explode(',', $record->scale_values);
        $v = implode(', ', $v);
        return $v;
    }

    /**
     * display the submission grade
     * @param string $field
     * @param object $record
     * @param boolean $isexport
     */
    public function rb_display_submissiongrade($field, $record, $isexport) {
        // if there's no grade (yet), then return a string saying so
        if ($field == -1) {
            return $this->_get_string('nograde');
        }

        // if there's no scale values, return the raw grade
        if (empty($record->scale_values)) {
            return (integer)$field;
        }

        // if there are scale values, work out which scale value was achieved
        $v = explode(',', $record->scale_values);
        $i = (integer)$field - 1;
        return $v[$i];
    }

    /**
     * display the max grade
     * @param string $field
     * @param object $record
     * @param boolean $isexport
     */
    public function rb_display_maxgrade($field, $record, $isexport) {
        // if there's no scale values, return the raw grade
        if (empty($record->scale_values)) {
            return (integer)$field;
        }

        // if there are scale values, work out which scale value is the maximum
        $v = explode(',', $record->scale_values);
        $i = (integer)count($v) - 1;
        return $v[$i];
    }

    /**
     * display the min grade
     * @param string $field
     * @param object $record
     * @param boolean $isexport
     */
    public function rb_display_mingrade($field, $record, $isexport) {
        // if there's no scale values, return the raw grade
        if (empty($record->scale_values)) {
            return (integer)$field;
        }

        // if there are scale values, work out which scale value is the minimum
        $v = explode(',', $record->scale_values);
        return $v[0];
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
