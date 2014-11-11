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
 * Generic filter for numbers.
 */
class rb_filter_number extends rb_filter_type {

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    function getOperators() {
        return array(0 => get_string('isequalto', 'filters'),
                     1 => get_string('isnotequalto', 'filters'),
                     2 => get_string('isgreaterthan', 'filters'),
                     3 => get_string('islessthan', 'filters'),
                     4 => get_string('isgreaterorequalto', 'filters'),
                     5 => get_string('islessthanorequalto', 'filters'));
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
        $mform->addHelpButton($grp->_name, 'filternumber', 'filters');
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
        if (isset($formdata->$operator)) {
            if ($value == '') {
                // no data
                return false;
            }
            return array('operator' => (int)$formdata->$operator, 'value' => $value);
        }

        return false;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return array containing filtering condition SQL clause and params
     */
    function get_sql_filter($data) {
        $operator = $data['operator'];
        $value    = (float) $data['value'];
        $query    = $this->get_field();

        if ($value === '') {
            return array('', array());
        }

        $uniqueparam = rb_unique_param('fn');
        switch($operator) {
            case 0: // equal
                $res = "= :{$uniqueparam}"; break;
            case 1: // not equal
                $res = "!= :{$uniqueparam}"; break;
            case 2: // greater than
                $res = "> :{$uniqueparam}"; break;
            case 3: // less than
                $res = "< :{$uniqueparam}"; break;
            case 4: // greater or equal to
                $res = ">= :{$uniqueparam}"; break;
            case 5: // less than or equal to
                $res = "<= :{$uniqueparam}"; break;
            default:
                return array('', array());
        }
        $params = array($uniqueparam => $value);

        // this will cope with empty values but not anything that can't be cast to a float
        // make sure the source column only contains numbers!
        $sql = 'CASE WHEN (' . $query . ') IS NULL THEN 0 ELSE ' . sql_cast2float($query) . ' END ' . $res;

        return array($sql, $params);
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
            case 5: // empty
                return get_string('textlabel', 'filters', $a);
        }

        return '';
    }
}
