<?php //$Id$
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
 * @package totara
 * @subpackage reportbuilder
 */

require_once($CFG->dirroot.'/totara/reportbuilder/filters/lib.php');

/**
 * Filter based on selecting multiple cohorts via a dialog
 */
class rb_filter_cohort extends rb_filter_type {

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    function get_operators() {
        return array(0 => get_string('isanyvalue','filters'),
                     1 => get_string('matchesanyselected','filters'),
                     2 => get_string('matchesallselected','filters'));
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    public function setupForm(&$mform) {
        global $SESSION;
        $label = format_string($this->label);
        $advanced = $this->advanced;

        $mform->addElement('static', $this->name.'_list', $label,
            // container for currently selected cohorts
            '<div class="list-' . $this->name . '">' .
            '</div>' . display_add_cohort_link($this->name));

        if ($advanced) {
            $mform->setAdvanced($this->name.'_list');
        }

        $mform->addElement('hidden', $this->name, '');
        $mform->setType($this->name, PARAM_SEQUENCE);

        // set default values
        if (isset($SESSION->reportbuilder[$this->report->_id][$this->name])) {
            $defaults = $SESSION->reportbuilder[$this->report->_id][$this->name];
        }
        if (isset($defaults['value'])) {
            $mform->setDefault($this->name, $defaults['value']);
        }

    }

    function definition_after_data(&$mform) {
        global $DB;
        if ($ids = $mform->getElementValue($this->name)) {

            if ($cohorts = $DB->get_records_select('cohort', "id IN ($ids)")) {
                $out = html_writer::start_tag('div', array('class' => "list-".$this->name));
                foreach ($cohorts as $cohort) {
                    $out .= display_selected_cohort_item($cohort, $this->name);
                }
                $out .= html_writer::end_tag('div');

                // link to add cohorts
                $out .= display_add_cohort_link($this->name);

                $mform->setDefault($this->name.'_list', $out);
            }
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field    = $this->name;

        if (isset($formdata->$field) && !empty($formdata->$field) ) {
            return array('value'    => $formdata->$field);
        }

        return false;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    function get_sql_filter($data) {
        global $DB;

        $items    = explode(',', $data['value']);
        $query    = $this->get_field();

        // don't filter if none selected
        if (empty($items)) {
            // return 1=1 instead of TRUE for MSSQL support
            return array(' 1=1 ', array());
        }

        // split by comma and look for any items
        // within list
        $res = array();
        $params = array();
        if (is_array($items)) {
            $count = 1;
            foreach ($items as $id) {

                $uniqueparam = rb_unique_param("fcohequal_{$count}_");
                $equals = "{$query} = :{$uniqueparam}";
                $params[$uniqueparam] = $id;

                $uniqueparam = rb_unique_param("fcohendswith_{$count}_");
                $endswithlike = $DB->sql_like($query, ":{$uniqueparam}");
                $params[$uniqueparam] = '%|' . $DB->sql_like_escape($id);

                $uniqueparam = rb_unique_param("fcohstartswith_{$count}_");
                $startswithlike = $DB->sql_like($query, ":{$uniqueparam}");
                $params[$uniqueparam] = $DB->sql_like_escape($id) . '|%';

                $uniqueparam = rb_unique_param("fcohcontains{$count}_");
                $containslike = $DB->sql_like($query, ":{$uniqueparam}");
                $params[$uniqueparam] = '%|' . $DB->sql_like_escape($id) . '|%';

                $res[] = "( {$equals} OR \n" .
                    "    {$endswithlike} OR \n" .
                    "    {$startswithlike} OR \n" .
                    "    {$containslike} )\n";

                $count++;

            }
        }

        // none selected - match everything
        if (count($res) == 0) {
            // using 1=1 instead of TRUE for MSSQL support
            return array(' 1=1 ', array());;
        }

        // combine with OR logic (match any cohort)
        return array('(' . implode(' OR ', $res) . ')', $params);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        global $DB;
        $value     = $data['value'];
        $values = explode(',', $value);
        $label = $this->label;

        if (empty($values)) {
            return '';
        }

        $a = new stdClass();
        $a->label    = $label;

        $selected = array();
        list($insql, $inparams) = $DB->get_in_or_equal($values);
        if ($cohorts = $DB->get_records_select('cohort', "id {$insql}", $inparams)) {
            foreach ($cohorts as $cohort) {
                $selected[] = '"' . format_string($cohort->name) . '"';
            }
        }

        $orstring = get_string('or', 'totara_reportbuilder');
        $a->value    = implode($orstring, $selected);

        return get_string('selectlabelnoop', 'filters', $a);
    }

    /**
     * Include Js for this filter
     *
     */
    public function include_js() {
        global $PAGE;

        $code = array();
        $code[] = TOTARA_JS_DIALOG;
        $code[] = TOTARA_JS_TREEVIEW;
        local_js($code);

        $jsdetails = new stdClass();
        $jsdetails->initcall = 'M.totara_reportbuilder_filterdialogs.init';
        $jsdetails->jsmodule = array('name' => 'totara_reportbuilder_filterdialogs',
            'fullpath' => '/totara/reportbuilder/filter_dialogs.js');
        $jsdetails->strings = array(
            'totara_cohort' => array('choosecohorts')
        );
        $jsdetails->args = array('args' => '{"filter_to_load":"cohort"}');

        foreach ($jsdetails->strings as $scomponent => $sstrings) {
            $PAGE->requires->strings_for_js($sstrings, $scomponent);
        }

        $PAGE->requires->js_init_call($jsdetails->initcall, $jsdetails->args, false, $jsdetails->jsmodule);
    }
}

/**
 * Given a cohort object returns the HTML to display it as a filter selection
 *
 * @param object $cohort A cohort object containing id and name properties
 * @param string $filtername The identifying name of the current filter
 *
 * @return string HTML to display a selected item
 */
function display_selected_cohort_item($cohort, $filtername) {
    global $OUTPUT;
    $strdelete = get_string('delete');
    $out = html_writer::start_tag('div', array('data-filtername' => $filtername,
                                               'data-id' => $cohort->id,
                                               'class' => 'multiselect-selected-item'));
    $out .= format_string($cohort->name);
    $out .= html_writer::link('#', html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/delete'),
                                                          'alt' => $strdelete,
                                                          'class' => 'delete-icon')), array('title' => $strdelete));
    $out .= html_writer::end_tag('div');
    return $out;
}

/**
 * Helper function to display the 'add cohorts' link to the filter
 *
 * @param string $filtername Name of the form element
 *
 * @return string HTML to display the link
 */
function display_add_cohort_link($filtername) {
    return html_writer::start_tag('div', array('class' => 'rb-cohort-add-link')) .
           html_writer::link('#', get_string('addcohorts', 'totara_reportbuilder'), array('id' => 'show-'.$filtername.'-dialog')) .
           html_writer::end_tag('div');
}
