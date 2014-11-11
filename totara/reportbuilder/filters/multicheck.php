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
 * Generic filter based on a multiple checkboxes
 */
class rb_filter_multicheck extends rb_filter_type {

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
     * @return rb_filter_multicheck object
     */
    public function __construct($type, $value, $advanced, $region, $report) {
        parent::__construct($type, $value, $advanced, $region, $report);

        if (!isset($this->options['selectfunc'])) {
            if (!isset($this->options['selectchoices'])) {
                debugging("No selectchoices provided for filter '{$this->name}' in source '" .
                    get_class($report->src) . "'", DEBUG_DEVELOPER);
                $this->options['selectchoices'] = array();
            }
        }

        if (!isset($this->options['attributes'])) {
            $this->options['attributes'] = array();
        }

        if (!isset($this->options['concat'])) {
            $this->options['concat'] = false;
        }

        if (!isset($this->options['simplemode'])) {
            $this->options['simplemode'] = false;
        }
        // Override simplemode when instantfiltering in the sidebar with counts.
        if (isset($this->options['showcounts']) && $this->region == rb_filter_type::RB_FILTER_REGION_SIDEBAR) {
            $this->options['simplemode'] = true;
        }
    }

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    function get_operators() {
        return array(0 => get_string('isanyvalue', 'filters'),
                     1 => get_string('matchesanyselected', 'filters'),
                     2 => get_string('matchesallselected', 'filters'));
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        global $OUTPUT, $SESSION;
        $grplabel = $label = format_string($this->label);
        $advanced = $this->advanced;
        $options = $this->options['selectchoices'];
        $attr = $this->options['attributes'];
        $simplemode = $this->options['simplemode'];

        // don't display the filter if there are no options
        if (count($options) == 0) {
            $mform->addElement('static', $this->name, $label, get_string('nofilteroptions', 'totara_reportbuilder'));
            if ($advanced) {
                $mform->setAdvanced($this->name);
            }
            return;
        }

        if ($simplemode) {
            $mform->addElement('hidden', $this->name . '_op', 1);
        } else {
            $mform->addElement('select', $this->name . '_op', $label, $this->get_operators());
            $mform->addHelpButton($this->name . '_op', 'filtercheckbox', 'filters');
            $grplabel = '';
        }
        $mform->setType($this->name . '_op', PARAM_INT);

        // this class is used by the CSS to arrange the checkboxes nicely
        $mform->addElement('html', $OUTPUT->container_start('multicheck-items'));
        $objs = array();
        foreach ($options as $id => $name) {
            $elem = $mform->createElement('advcheckbox', $this->name . '[' . $id . ']', null, $name,
                    array_merge(array('group' => 1), $attr));
            $elem->updateAttributes(array('data-ind' => $id));
            $objs[] = $elem;
            $mform->setType($this->name . '[' . $id . ']', PARAM_TEXT);
            $mform->disabledIf($this->name . '[' . $id . ']', $this->name . '_op', 'eq', 0);
        }
        $mform->addGroup($objs, $this->name . '_grp', $grplabel, '', false);
        $mform->addElement('html', $OUTPUT->container_end());

        if ($advanced) {
            $mform->setAdvanced($this->name . '_op');
            $mform->setAdvanced($this->name . '_grp');
        }

        // set default values
        if (isset($SESSION->reportbuilder[$this->report->_id][$this->name])) {
            $defaults = $SESSION->reportbuilder[$this->report->_id][$this->name];
        }
        if (isset($defaults['operator'])) {
            $mform->setDefault($this->name . '_op', $defaults['operator']);
        }
        // contains an array which will set multiple checkboxes
        if (isset($defaults['value'])) {
            $mform->setDefault($this->name, $defaults['value']);
        }
    }

    /**
     * Update form elements with counts of each filter element will give if set
     * @param moodlequickform $mform
     */
    public function set_counts($mform, $counts) {
        $options = $this->options['selectchoices'];
        $grpname = $this->name . '_grp';
        $filteruniquename = $this->options['showcounts']['dataalias'];

        if ($mform->elementExists($grpname)) {
            $group = $mform->getElement($grpname);
            $elements = $group->getElements();

            foreach ($elements as $elem) {
                $ind = $elem->getAttribute('data-ind');
                if (isset($options[$ind])) {
                    $fieldname = strtolower("mcc_{$filteruniquename}_{$ind}");
                    if (!isset($counts->$fieldname)) {
                        $countname = "{$options[$ind]} (0)";
                    } else {
                        $countname = "{$options[$ind]} ({$counts->$fieldname})";
                    }
                    $elem->setText($countname);
                }
            }
        }
    }

    private function is_option_set($option) {
        global $SESSION;

        return isset($SESSION->reportbuilder[$this->report->_id][$this->name]) &&
               $SESSION->reportbuilder[$this->report->_id][$this->name]['value'][$option];
    }

    /**
     * Get showcount params.
     *
     * @return returns the showcount parameters, otherwise false.
     * If true then filter needs to define save_temp_data, restore_temp_data, set_counts.
     */
    public function get_showcount_params() {
        return isset($this->options['showcounts']) ? $this->options['showcounts'] : false;
    }

    /**
     * Generate the sql snipets needed to construct the sidebar filter record counts.
     *
     * @param array of rb_filters $otherfilters (includes this filter!)
     * @return array of (array of strings, string, array of strings, array of strings, array of strings)
     */
    public function get_counts_sql($otherfilters) {
        $countscolumns = array();
        $filterscolumns = array();
        $optionsfound = array();
        $sqlparams = array();

        $iscached = $this->report->is_cached();
        $isgrouped = $this->report->grouped;
        $showcountparams = $this->options['showcounts'];
        $filteruniquename = $showcountparams['dataalias'];

        foreach ($this->options['selectchoices'] as $option => $unused) {
            $fxvy = strtolower("{$filteruniquename}_{$option}");

            // Set up countscolumns.
            $otherfiltercondition = "";
            foreach ($otherfilters as $otherfilter) {
                if ($otherfilter != $this) {
                    $otheruniquename = $otherfilter->options['showcounts']['dataalias'];
                    $otherfxt = "total_{$otheruniquename}";
                    $inactivename = "i_{$filteruniquename}_" . md5("{$otheruniquename}_{$option}");
                    $shorterinactivename = strtolower(substr($inactivename, 0, 30));
                    $otherfiltercondition .= "            * ({$otherfxt} + :{$shorterinactivename})\n";

                    // Set up the filter parameters.
                    if ($otherfilter->has_data()) {
                        $sqlparams[$shorterinactivename] = 0;
                    } else {
                        $sqlparams[$shorterinactivename] = 1;
                    }
                }
            }
            $countscolumns[] = "   SUM( CASE WHEN ({$fxvy}\n{$otherfiltercondition}" .
                               "        ) > 0 THEN 1 ELSE 0 END ) AS mcc_{$fxvy}";

            $shortercheckedname = substr("c_{$fxvy}", 0, 30);

            // Set up the option parameters.
            $sqlparams[$shortercheckedname] = $this->is_option_set($option);

            // Set up filterscolumns.
            if ($iscached || $isgrouped) {
                $data = array('operator' => 1, 'value' => array($option => 1)); // Enable only this option.
                list($optionsql, $optionparams) = $this->get_sql_filter($data, $isgrouped);
                $sqlparams = array_merge($sqlparams, $optionparams);
                $filterscolumns[] = "         SUM(CASE WHEN {$optionsql} THEN 1 ELSE 0 END ) AS {$fxvy}";
            } else {
                $filterscolumns[] = "         SUM(CASE WHEN {$showcountparams['dataalias']}." .
                                    "{$showcountparams['datafield']} = '{$option}' THEN 1 ELSE 0 END ) AS {$fxvy}";
            }

            // Set up optionsfound for filtersplustotalscolumns.
            $optionsfound[] = "      (:{$shortercheckedname} * {$fxvy})";
        }

        // Set up filtersplustotalscolumns.
        $fxt = "total_{$filteruniquename}";
        $optionsfound = empty($optionsfound) ? array('0') : $optionsfound;
        $filtersplustotalscolumn = implode(" +\n", $optionsfound) . " AS " . $fxt;

        // Set up showcountjoins.
        $showcountjoins = array();
        if (!$iscached && !$isgrouped) { // Grouped reports will include all joins in the base query.
            foreach ($showcountparams['joins'] as $countjoin) {
                $showcountjoins[] = "      {$countjoin}";
            }
        }

        return array($countscolumns, $filtersplustotalscolumn, $filterscolumns, $showcountjoins, $sqlparams);
    }


    /**
     * Save temporary saved data
     *
     * @param int $data the data to set
     */
    public function save_temp_data() {
        global $SESSION;

        $fieldname = $this->name;
        if (!isset($SESSION->reportbuilder[$this->report->_id][$fieldname . '_was'])) {
            $SESSION->reportbuilder[$this->report->_id][$fieldname . '_was'] =
                    isset($SESSION->reportbuilder[$this->report->_id][$fieldname]) ?
                    $SESSION->reportbuilder[$this->report->_id][$fieldname] : false;
        }
        unset($SESSION->reportbuilder[$this->report->_id][$fieldname]);
    }

    /**
     * Restores temporary saved filter data
     */
    public function restore_temp_data() {
        global $SESSION;

        $fieldname = $this->name;
        if (isset($SESSION->reportbuilder[$this->report->_id][$fieldname . '_was'])) {
            if ($SESSION->reportbuilder[$this->report->_id][$fieldname . '_was'] === false) {
                unset($SESSION->reportbuilder[$this->report->_id][$fieldname]);
            } else {
                $SESSION->reportbuilder[$this->report->_id][$fieldname] =
                        $SESSION->reportbuilder[$this->report->_id][$fieldname . '_was'];
            }
            unset($SESSION->reportbuilder[$this->report->_id][$fieldname . '_was']);
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
        if (isset($formdata->$operator) && $formdata->$operator != 0) {
            $found = false;
            foreach ($formdata->$field as $data) {
                if ($data) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
            return array('operator' => (int)$formdata->$operator,
                         'value'    => (array)$formdata->$field);
        }

        return false;
    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array $data filter settings
     * @param bool $usefieldalias use fieldalias rather than field - used for counting with grouped reports
     * @return array containing filtering condition SQL clause and params
     */
    function get_sql_filter($data, $usefieldalias = false) {
        global $DB;

        $operator = $data['operator'];
        $items    = $data['value'];
        $query = $usefieldalias ? $this->fieldalias : $this->field;
        $simplemode = $this->options['simplemode'];

        switch($operator) {
            case 1:
                $glue = ' OR ';
                break;
            case 2:
                $glue = ' AND ';
                break;
            default:
                // return 1=1 instead of TRUE for MSSQL support
                return array(' 1=1 ', array());
        }

        // Query is of the form "1|2|3|4", by concatenating pipes to
        // either end we can match any item with a single LIKE, instead
        // of having to handle end matches separately.
        if ($this->options['concat']) {
            $query = $DB->sql_concat("'|'", $query, "'|'");
        }

        $res = array();
        $params = array();
        if (is_array($items)) {
            $count = 1;
            foreach ($items as $id => $selected) {
                if ($selected) {
                    if ($this->options['concat']) {
                        $uniqueparam = rb_unique_param("fmccontains_{$count}_");
                        $filter = "( " . $DB->sql_like($query,
                            ":{$uniqueparam}") . ") \n";
                        $params[$uniqueparam] = '%|' . $DB->sql_like_escape($id) . '|%';
                    } else {
                        $uniqueparam = rb_unique_param("fmcequal_{$count}_");
                        $filter = "( {$query} = :{$uniqueparam} )\n";
                        $params[$uniqueparam] = $id;
                    }

                    $res[] = $filter;

                    $count++;
                }
            }
        }
        // None selected.
        if (count($res) == 0) {
            // Using 1=1 instead of TRUE for MSSQL support.
            if ($simplemode) {
                // When using simplemode we perform no filtering if nothing is selected.
                return array(' 1=1 ', array());
            } else {
                return array(' 1=0 ', array());
            }
        }

        return array('(' . implode($glue, $res) . ')', $params);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $operators = $this->get_operators();
        $operator  = $data['operator'];
        $value     = $data['value'];
        $label = $this->label;
        $selectchoices = $this->options['selectchoices'];

        if (empty($operator)) {
            return '';
        }

        $a = new stdClass();
        $a->label    = $label;
        $checked = array();
        foreach ($value as $key => $selected) {
            if ($selected) {
                $name = array_key_exists($key, $selectchoices) ?
                $selectchoices[$key] : $key;
                $formatname = trim(html_entity_decode(strip_tags($name)), chr(0xC2).chr(0xA0)); // chr(0xC2).chr(0xA0) = &nbsp;
                $checked[] = '"' . $formatname . '"';
            }
        }
        $a->value    = implode(', ', $checked);
        $a->operator = $operators[$operator];

        return get_string('selectlabel', 'filters', $a);
    }
}

