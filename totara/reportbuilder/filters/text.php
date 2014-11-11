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
 * Generic filter for text fields.
 */
class rb_filter_text extends rb_filter_type {

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    function getOperators() {
        return array(0 => get_string('contains', 'filters'),
                     1 => get_string('doesnotcontain', 'filters'),
                     2 => get_string('isequalto', 'filters'),
                     3 => get_string('startswith', 'filters'),
                     4 => get_string('endswith', 'filters'),
                     5 => get_string('isempty', 'filters'));
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        global $SESSION;
        $label = format_string($this->label);
        $advanced = $this->advanced;

        $objs = array();
        $objs[] =& $mform->createElement('select', $this->name . '_op', null, $this->getOperators());
        $objs[] =& $mform->createElement('text', $this->name, null);
        $mform->setType($this->name . '_op', PARAM_INT);
        $mform->setType($this->name, PARAM_TEXT);
        $grp =& $mform->addElement('group', $this->name . '_grp', $label, $objs, '', false);
        $mform->addHelpButton($grp->_name, 'filtertext', 'filters');
        $mform->disabledIf($this->name, $this->name . '_op', 'eq', 5);
        if ($advanced) {
            $mform->setAdvanced($this->name . '_grp');
        }

        // set default values
        if (isset($SESSION->reportbuilder[$this->report->_id][$this->name])) {
            $defaults = $SESSION->reportbuilder[$this->report->_id][$this->name];
        }
        if (isset($defaults['operator'])) {
            $mform->setDefault($this->name . '_op', $defaults['operator']);
        }
        if (isset($defaults['value'])) {
            $mform->setDefault($this->name, $defaults['value']);
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field    = $this->name;
        $operator = $field . '_op';
        $value = (isset($formdata->$field)) ? $formdata->$field : '';
        if (array_key_exists($operator, $formdata)) {
            if ($formdata->$operator != 5 and $value == '') {
                // no data - no change except for empty filter
                return false;
            }
            return array('operator' => (int)$formdata->$operator, 'value' => $value);
        }

        return false;
    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array $data filter settings
     * @return array containing filtering condition SQL clause and params
     */
    function get_sql_filter($data) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/core/searchlib.php');

        $operator = $data['operator'];
        $value    = $data['value'];
        $query    = $this->get_field();

        if ($operator != 5 and $value === '') {
            return array('', array());
        }

        switch($operator) {
            case 0: // contains
                $keywords = totara_search_parse_keywords($value);
                return search_get_keyword_where_clause($query, $keywords);
            case 1: // does not contain
                $keywords = totara_search_parse_keywords($value);
                list($sql, $params) = search_get_keyword_where_clause($query, $keywords, true);
                return array("(({$query}) IS NULL OR {$sql})", $params);
            case 2: // equal to
                return search_get_keyword_where_clause($query, array($value), false, 'equal');
            case 3: // starts with
                return search_get_keyword_where_clause($query, array($value), false, 'startswith');
            case 4: // ends with
                return search_get_keyword_where_clause($query, array($value), false, 'endswith');
            case 5: // empty - may also be null
                return array("({$query} = '' OR ({$query}) IS NULL)", array());
            default:
                return array('', array());
        }
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {

        $operator  = $data['operator'];
        $value     = $data['value'];
        $operators = $this->getOperators();
        $label     = $this->label;

        $a = new stdClass();
        $a->label    = $label;
        $a->value    = '"' . s($value) . '"';
        $a->operator = $operators[$operator];


        switch ($operator) {
            case 0: // contains
            case 1: // doesn't contain
            case 2: // equal to
            case 3: // starts with
            case 4: // ends with
                return get_string('textlabel', 'filters', $a);
            case 5: // empty
                return get_string('textlabelnovalue', 'filters', $a);
        }

        return '';
    }
}
