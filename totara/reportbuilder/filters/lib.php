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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * The base filter class. All abstract classes must be implemented.
 */
class rb_filter_type {
    const RB_FILTER_REGION_STANDARD = 0;
    const RB_FILTER_REGION_SIDEBAR = 1;

    public $type;
    public $value;
    public $advanced;
    public $region;
    public $filtertype;
    public $label;
    /**
     * @var mixed $field string|array Direct access to this field is @deprecated and prohibited.
     *
     * Must be set to private to enforce children classes use get_field
     * User should use $obj->get_field() instead
     * To handle more than one field property should be passed as an array:
     *
     * @example field = array('course' => 'base.course', 'user' => 'base.userid')
     */
    private $field;
    public $fieldalias;
    public $joins;
    protected $options;
    protected $report;
    public $grouping;
    public $name;
    public $filterid;
    /**
     * Constructor
     *
     * @param string $type The filter type (from the db or embedded source)
     * @param string $value The filter value (from the db or embedded source)
     * @param integer $advanced If the filter should be shown by default (0) or only
     *                          when advanced options are shown (1)
     * @param integer $region Which region this filter appears in.
     * @param reportbuilder object $report The report this filter is for
     *
     * @return filter_* object
     */
    public function __construct($type, $value, $advanced, $region, $report) {
        $this->type = $type;
        $this->value = $value;
        $this->advanced = $advanced;
        $this->region = $region;
        $this->report = $report;
        $this->name = "{$type}-{$value}";

        // get this filter's settings based on the option from the report's source
        if (!$filteroption = $this->get_filteroption($type, $value)) {
            return false;
        }

        $this->label = $filteroption->label;
        $this->filtertype = $filteroption->filtertype;

        // there must be a columnoption, unless the filter is providing the field
        // data directly
        if (empty($filteroption->field)) {
            if (!$columnoption = $this->get_columnoption($type, $value)) {
                return false;
            }
        } else {
            $columnoption = null;
        }

        $this->field = $this->define_field($filteroption, $columnoption);
        $this->fieldalias = $type . '_' . $value;

        $this->joins = $this->get_joins($filteroption, $columnoption);
        if ($this->joins === false) {
            return false;
        }
        $this->grouping = $this->get_grouping($filteroption, $columnoption);

        $this->options = isset($filteroption->filteroptions) ? $filteroption->filteroptions : array();
        // if the filter defines a selectfunc option, call the function
        // and save the return value to selectchoices
        if (isset($this->options['selectfunc'])) {
            $this->options['selectchoices'] = $this->get_select_choices($this->options['selectfunc']);
        }
    }

    /**
     * Restore state of filters
     *
     * @param reportbuilder $report Report builder instance
     */
    public function set_report(reportbuilder $report) {
        $this->report = $report;
    }

    /**
     * This method allows to get 'field' property
     * It's made for backward compatibility
     * Direct access to field ($this->field) is @deprecated since version 2.2.12
     * User $this->get_field() instead
     */
    public function __get($name) {
        if ($name == 'field') {
            return $this->get_field();
        }

    }

    /**
     * Return SQL snippet for field name depending of report cache settings
     */
    public function get_field() {
        if ($this->report->is_cached()) {
            return $this->fieldalias;
        }
        return $this->field;
    }
    /**
     * Given a type and value, return the matching filteroption from the report source
     *
     * @param string $type The filter type
     * @param string $value The filter value
     *
     * @return object|false A filteroption, or false if not found
     */
    private function get_filteroption($type, $value) {
        $sourcename = get_class($this->report->src);
        $filteroption = reportbuilder::get_single_item($this->report->src->filteroptions, $type, $value);

        if (!$filteroption) {
            $a = new stdClass();
            $a->type = $type;
            $a->value = $value;
            $a->source = $sourcename;
            debugging(get_string('error:filteroptiontypexandvalueynotfoundinz', 'totara_reportbuilder', $a), DEBUG_DEVELOPER);
            return false;
        }

        return $filteroption;
    }

    /**
     * Given a type and value, return the matching columnoption from the report source
     *
     * @param string $type The filter type
     * @param string $value The filter value
     *
     * @return object|false A columnoption, or false if not found
     */
    private function get_columnoption($type, $value) {

        $sourcename = get_class($this->report->src);
        $columnoption = reportbuilder::get_single_item($this->report->columnoptions, $type, $value);

        if (!$columnoption) {
            $a = new stdClass();
            $a->type = $type;
            $a->value = $value;
            $a->source = $sourcename;
            debugging(get_string('error:columnoptiontypexandvalueynotfoundinz', 'totara_reportbuilder', $a), DEBUG_DEVELOPER);
            return false;
        }

        return $columnoption;
    }

    /**
     * Return an SQL snippet describing field information for this filter
     *
     * Includes any aggregation/grouping function that the filter is using
     *
     * @param object $filteroption The filteroption to get a field from
     * @param object $columnoption The columnoption associated with this filter, or
     *                             null if not required
     * @return string The SQL snippet to use in WHERE or HAVING clause
     */
    private function define_field($filteroption, $columnoption) {
        // determine whether to get field data from a column or the filter itself
        $option = empty($filteroption->field) ? $columnoption : $filteroption;

        $field = $option->field;
        $grouping = !empty($option->grouping) ? $option->grouping: 'none';

        // Now apply grouping to field.
        $src = $this->report->src;
        if ($grouping == 'none') {
            return $field;
        } else {
            $groupfunc = "rb_group_{$grouping}";
            if (!method_exists($src, $groupfunc)) {
                throw new ReportBuilderException(get_string('groupingfuncnotinfieldoftypeandvalue',
                    'totara_reportbuilder',
                    (object)array('groupfunc' => $groupfunc, 'type' => $option->type, 'value' => $option->value)));
            }
            return $src->$groupfunc($field);
        }
    }

    /**
     * Return one or more rb_join names indicating joins required by this filter
     *
     * These are obtained either from the columnoption this filter is based on or provided
     * by the filteroption explicitly
     *
     * @param object $filteroption The filteroption to get a field from
     * @param object $columnoption The columnoption associated with this filter, or
     *                             null if not required
     * @return string|array Joins to include in the query when this filter is active
     */
    private function get_joins($filteroption, $columnoption) {
        // determine whether to get joins from a column or the filter itself
        $option = empty($filteroption->field) ? $columnoption : $filteroption;

        $joins = isset($option->joins) ? $option->joins : array();

        // validate joins against the report's source
        if (!reportbuilder::check_joins($this->report->src->joinlist, $joins)) {
            $a = new stdClass();
            $a->type = $option->type;
            $a->value = $option->value;
            $a->source = get_class($this->report->src);
            debugging(get_string('error:joinsforfiltertypexandvalueynotfoundinz', 'totara_reportbuilder', $a), DEBUG_DEVELOPER);
            return false;
        }

        return $joins;
    }

    /**
     * Get the grouping of the filteroption provided
     *
     * @param object $filteroption The filteroption to get a field from
     * @param object $columnoption The columnoption associated with this filter, or
     *                             null if not required
     * @return string the grouping of this filter
     */
    private function get_grouping($filteroption, $columnoption) {
        // determine whether to get joins from a column or the filter itself
        $option = empty($filteroption->field) ? $columnoption : $filteroption;

        return isset($option->grouping) ? $option->grouping : array();
    }

    /**
     * Call the named function from the report source and return the choices returned
     *
     * @param string $selectfunc Name of the function to call
     * @return array Array representing a set of choices for the filter
     */
    private function get_select_choices($selectfunc) {
            $selectfunc = 'rb_filter_' . $selectfunc;
            if (method_exists($this->report->src, $selectfunc)) {
                $selectchoices = $this->report->src->$selectfunc($this->report);
            } else {
                debugging("Filter function '{$selectfunc}' not found for filter '{$this->name}}' in source '" .
                    get_class($this->report->src) . "'", DEBUG_DEVELOPER);
                $selectchoices = array();
            }
            return $selectchoices;
    }

    /**
     * Factory method for creating a filter object
     *
     * @param string $type The filter type (from the db or embedded source)
     * @param string $value The filter value (from the db or embedded source)
     * @param integer $advanced If the filter should be shown by default (0) or only
     *                          when advanced options are shown (1)
     * @param reportbuilder object $report The report this filter is for
     *
     * @return @object A filter_[type] object or false
     */
    public static function get_filter($type, $value, $advanced, $region, $report) {
        global $CFG;

        // figure out what sort of filter it is
        if (!$filtertype = self::get_filter_type($type, $value, $report)) {
            return false;
        }
        $filename = "{$CFG->dirroot}/totara/reportbuilder/filters/{$filtertype}.php";
        if (!is_readable($filename)) {
            return false;
        }
        require_once($filename);
        $classname = "rb_filter_{$filtertype}";
        if (!class_exists($classname)) {
            return false;
        }

        return new $classname($type, $value, $advanced, $region, $report);
    }


    /**
     * Get a filter's filtertype by looking up from the filteroption in the report's source
     *
     * @param string $type The type of filter
     * @param string $value The filter value
     * @param object $report The report object
     *
     * @return string|false The filtertype of the filter from this report's source, if found
     */
    static function get_filter_type($type, $value, $report) {
        $filteroptions = $report->src->filteroptions;
        if (!$filteroption = reportbuilder::get_single_item($filteroptions, $type, $value)) {
            return false;
        }

        if (!isset($filteroption->filtertype)) {
            return false;
        }

        return $filteroption->filtertype;
    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array $data filter settings
     * @return array containing the filtering condition SQL clause and params
     */
    function get_sql_filter($data) {
        print_error('abstractmethodcalled', 'totara_reportbuilder', '', 'get_sql_filter()');
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        print_error('abstractmethodcalled', 'totara_reportbuilder', '', 'check_data()');
    }

    /**
     * Saves data
     *
     * @param int $data the data to set
     */
    function set_data($data) {
        global $SESSION;
        $fieldname = $this->name;
        $SESSION->reportbuilder[$this->report->_id][$fieldname] = $data;
    }

    /**
     * Has data
     *
     * @return bool true if the filter is active.
     */
    public function has_data() {
        global $SESSION;
        $fieldname = $this->name;
        return isset($SESSION->reportbuilder[$this->report->_id][$fieldname]);
    }

    /**
     * Removes saved data
     *
     * By convention, all additional parameters should have suffixes beginning with '_'.
     * If not (such as the "date" filter type) then this method must be overridden to unset them.
     */
    function unset_data() {
        global $SESSION;

        $fieldname = $this->name;
        unset($SESSION->reportbuilder[$this->report->_id][$fieldname]);

        // Unset the main parameter.
        unset($_POST[$fieldname]);

        $fieldname .= '_';
        foreach ($_POST as $postkey => $unusedpostvalue) {
            if (strpos($postkey, $fieldname) === 0) {
                unset($_POST[$postkey]);
            }
        }
    }

    /**
     * Get showcount params.
     *
     * @return returns the showcount parameters, otherwise false.
     * If true then filter needs to define save_temp_data, restore_temp_data, set_counts.
     */
    public function get_showcount_params() {
        return false;
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        print_error('abstractmethodcalled', 'totara_reportbuilder', '', 'setupForm()');
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        print_error('abstractmethodcalled', 'totara_reportbuilder', '', 'get_label()');
    }

    public static function get_all_regions() {
        $regions = array();
        $regions[self::RB_FILTER_REGION_STANDARD] = 'standard';
        $regions[self::RB_FILTER_REGION_SIDEBAR] = 'sidebar';
        return $regions;
    }
}


/**
 * Return an SQL snippet to search for the given keywords
 *
 * @param string $field the field to search in
 * @param array $keywords Array of strings to search for
 * @param boolean $negate negate the conditions
 * @param string $operator can be 'contains', 'equal', 'startswith', 'endswith'
 *
 * @return array containing SQL clause and params
 */
function search_get_keyword_where_clause($field, $keywords, $negate=false, $operator='contains') {
    global $DB;

    if ($negate) {
        $not = true;
        $token = ' OR ';
    } else {
        $not = false;
        $token = ' AND ';
    }

    $presign = '';
    $postsign = '';
    switch ($operator) {
        case 'contains':
            $presign = $postsign = '%';
            break;
        case 'startswith':
            $presign = '';
            $postsign = '%';
            break;
        case 'endswith':
            $presign = '%';
            $postsign = '';
            break;
        default:
            break;
    }

    $queries = array();
    $params = array();
    $count = 1;
    foreach ($keywords as $keyword) {
        $uniqueparam = rb_unique_param("skww{$operator}_{$count}_");
        $queries[] = $DB->sql_like($field, ":{$uniqueparam}", false, true, $not);
        $params[$uniqueparam] = $presign.$DB->sql_like_escape($keyword).$postsign;

        $count++;
    }

    $sql = '(' . implode($token, $queries) . ')';

    return array($sql, $params);
}

