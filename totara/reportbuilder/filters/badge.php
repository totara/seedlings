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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage reportbuilder
 */

require_once($CFG->dirroot.'/totara/reportbuilder/filters/lib.php');

/**
 * Filter based on selecting multiple badges via a dialog
 */
class rb_filter_badge extends rb_filter_type {

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    public function get_operators() {
        return array(0 => get_string('isanyvalue', 'filters'),
                     1 => get_string('matchesanyselected', 'filters'),
                     2 => get_string('matchesallselected', 'filters'));
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
            // Container for currently selected badges.
            '<div class="list-' . $this->name . '">' .
            '</div>' . display_add_badge_link($this->name));

        if ($advanced) {
            $mform->setAdvanced($this->name.'_list');
        }

        $mform->addElement('hidden', $this->name, '');
        $mform->setType($this->name, PARAM_SEQUENCE);

        // Set default values.
        if (isset($SESSION->reportbuilder[$this->report->_id][$this->name])) {
            $defaults = $SESSION->reportbuilder[$this->report->_id][$this->name];
        }
        if (isset($defaults['value'])) {
            $mform->setDefault($this->name, $defaults['value']);
        }

    }

    public function definition_after_data(&$mform) {
        global $DB;
        if ($ids = $mform->getElementValue($this->name)) {

            if ($badges = $DB->get_records_select('badge', "id IN ($ids)")) {
                $out = html_writer::start_tag('div', array('class' => "list-".$this->name));
                foreach ($badges as $badge) {
                    $out .= display_selected_badge_item($badge, $this->name);
                }
                $out .= html_writer::end_tag('div');

                // Link to add badges.
                $out .= display_add_badge_link($this->name);

                $mform->setDefault($this->name.'_list', $out);
            }
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    public function check_data($formdata) {
        $field = $this->name;

        if (isset($formdata->$field) && !empty($formdata->$field) ) {
            return array('value' => $formdata->$field);
        }

        return false;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return string the filtering condition or null if the filter is disabled
     */
    public function get_sql_filter($data) {
        global $DB;

        $items = explode(',', $data['value']);
        $query = $this->get_field();

        // Don't filter if none selected.
        if (empty($items)) {
            // Return 1=1 instead of TRUE for MSSQL support.
            return array(' 1=1 ', array());
        }

        // Split by comma and look for any items within list.
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

        // None selected - match everything.
        if (count($res) == 0) {
            // Using 1=1 instead of TRUE for MSSQL support.
            return array(' 1=1 ', array());;
        }

        // Combine with OR logic (match any badge).
        return array('(' . implode(' OR ', $res) . ')', $params);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label($data) {
        global $DB;
        $value = $data['value'];
        $values = explode(',', $value);
        $label = $this->label;

        if (empty($values)) {
            return '';
        }

        $a = new stdClass();
        $a->label = $label;

        $selected = array();
        list($insql, $inparams) = $DB->get_in_or_equal($values);
        if ($badges = $DB->get_records_select('badge', "id {$insql}", $inparams)) {
            foreach ($badges as $badge) {
                $selected[] = '"' . format_string($badge->name) . '"';
            }
        }

        $orstring = get_string('or', 'totara_reportbuilder');
        $a->value = implode($orstring, $selected);

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
            'badges' => array('choosebadges')
        );
        $jsdetails->args = array('args' => '{"filter_to_load":"badge", "reportid":"' . $this->report->_id . '"}');

        foreach ($jsdetails->strings as $scomponent => $sstrings) {
            $PAGE->requires->strings_for_js($sstrings, $scomponent);
        }

        $PAGE->requires->js_init_call($jsdetails->initcall, $jsdetails->args, false, $jsdetails->jsmodule);
    }
}

/**
 * Given a badge object returns the HTML to display it as a filter selection
 *
 * @param object $badge A badge object containing id and name properties
 * @param string $filtername The identifying name of the current filter
 *
 * @return string HTML to display a selected item
 */
function display_selected_badge_item($badge, $filtername) {
    global $CFG, $OUTPUT;
    require_once($CFG->libdir.'/badgeslib.php');
    $strdelete = get_string('delete');
    $out = html_writer::start_tag('div', array('data-filtername' => $filtername,
                                               'data-id' => $badge->id,
                                               'class' => 'multiselect-selected-item'));
    $bclass = new badge($badge->id);
    $bcontext = $bclass->get_context();
    $out .= print_badge_image($bclass, $bcontext) . format_string($badge->name) . ' (' .
                                get_string("badgestatus_{$badge->status}", 'badges') . ')';
    $out .= html_writer::link('#', html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/delete'),
                                                          'alt' => $strdelete,
                                                          'class' => 'delete-icon')), array('title' => $strdelete));
    $out .= html_writer::end_tag('div');
    return $out;
}

/**
 * Helper function to display the 'add badges' link to the filter
 *
 * @param string $filtername Name of the form element
 *
 * @return string HTML to display the link
 */
function display_add_badge_link($filtername) {
    return html_writer::start_tag('div', array('class' => 'rb-badge-add-link')) .
           html_writer::link('#', get_string('addbadges', 'totara_reportbuilder'), array('id' => 'show-'.$filtername.'-dialog')) .
           html_writer::end_tag('div');
}
