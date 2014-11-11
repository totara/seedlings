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
 * @author Maria Torres <maria.torres@.totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Generic filter based on a hierarchy.
 */
class rb_filter_category extends rb_filter_type {

    /**
     * Returns an array of comparison operators.
     * @return array of comparison operators
     */
    public function get_operators() {
        return array(0 => get_string('isanyvalue', 'filters'),
                     1 => get_string('isequalto', 'filters'),
                     2 => get_string('isnotequalto', 'filters'));
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    public function setupForm(&$mform) {
        global $SESSION;
        $label = format_string($this->label);
        $advanced = $this->advanced;

        $objs = array();
        $objs[] =& $mform->createElement('select', $this->name.'_op', null, $this->get_operators());
        $objs[] =& $mform->createElement('static', 'title'.$this->name, '',
            html_writer::tag('span', '', array('id' => $this->name . 'title', 'class' => 'dialog-result-title')));
        $mform->setType($this->name.'_op', PARAM_TEXT);

        // Can't use a button because id must be 'show-*-dialog' and formslib appends 'id_' to ID.
        $objs[] =& $mform->createElement('static', 'selectorbutton',
            '',
            html_writer::empty_tag('input', array('type' => 'button',
                'class' => 'rb-filter-button rb-filter-choose-category',
                'value' => get_string('choosecatplural', 'totara_reportbuilder'),
                'id' => 'show-' . $this->name . '-dialog')));
        $objs[] =& $mform->createElement('checkbox', $this->name . '_rec', '', get_string('includesubcategories', 'filters'));
        $mform->setType($this->name . '_rec', PARAM_TEXT);

        // Container for currently selected items.
        $content = html_writer::tag('div', '', array('class' => 'rb-filter-content-list list-' . $this->name));
        $objs[] =& $mform->createElement('static', $this->name.'_list', '', $content);

        // Create a group for the elements.
        $grp =& $mform->addElement('group', $this->name.'_grp', $label, $objs, '', false);
        $mform->addHelpButton($grp->_name, 'reportbuilderdialogfilter', 'totara_reportbuilder');

        if ($advanced) {
            $mform->setAdvanced($this->name.'_grp');
            $mform->setAdvanced($this->name.'_list');
        }

        $mform->addElement('hidden', $this->name, '');
        $mform->setType($this->name, PARAM_SEQUENCE);

        // Set default values.
        if (isset($SESSION->reportbuilder[$this->report->_id][$this->name])) {
            $defaults = $SESSION->reportbuilder[$this->report->_id][$this->name];
        }
        if (isset($defaults['operator'])) {
            $mform->setDefault($this->name . '_op', $defaults['operator']);
        }
        if (isset($defaults['value'])) {
            $mform->setDefault($this->name, $defaults['value']);
        }
        if (isset($defaults['recursive'])) {
            $mform->setDefault($this->name . '_rec', $defaults['recursive']);
        }
    }

    /**
     * Definition after data.
     * @param object $mform a MoodleForm object to setup
     */
    public function definition_after_data(&$mform) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/lib/coursecatlib.php');

        if ($ids = $mform->getElementValue($this->name)) {
            if ($categories = $DB->get_records_select('course_categories', "id IN ($ids)")) {
                $names = coursecat::make_categories_list();
                $out = html_writer::start_tag('div', array('class' => 'rb-filter-content-list list-' . $this->name));
                foreach ($categories as $category) {
                    $out .= display_selected_category_item($names, $category, $this->name);
                }
                $out .= html_writer::end_tag('div');

                $mform->setDefault($this->name . '_list', $out);
            }
        }
    }


    /**
     * Retrieves data from the form data.
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    public function check_data($formdata) {
        $field = $this->name;
        $operator = $field . '_op';
        $recursive = $field . '_rec';

        if (isset($formdata->$field) && $formdata->$field != '') {
            $data = array('operator' => (int)$formdata->$operator,
                          'value'    => (string)$formdata->$field);
            if (isset($formdata->$recursive)) {
                $data['recursive'] = (int)$formdata->$recursive;
            } else {
                $data['recursive'] = 0;
            }

            return $data;
        }

        return false;
    }

    /**
     * Returns the condition to be used with SQL where.
     * @param array $data filter settings
     * @return array containing filtering condition SQL clause and params
     */
    public function get_sql_filter($data) {
        global $DB;

        $items = explode(',', $data['value']);
        $query = $this->get_field();
        $operator  = $data['operator'];
        $recursive = (isset($data['recursive']) && $data['recursive']) ? '/%' : '';

        // Operators: 1 => Is equal to, 2 => Is not equal to.
        switch($operator) {
            case 1:
                $notlike = false;
                break;
            case 2:
                $notlike = true;
                break;
            default:
                // Return 1=1 instead of TRUE for MSSQL support.
                return array(' 1=1 ', array());
        }

        // None selected - match everything.
        if (empty($items)) {
            // Using 1=1 instead of TRUE for MSSQL support.
            return array(' 1=1 ', array());
        }

        $count = 1;
        $sql = '';
        $params = array();
        foreach ($items as $itemid) {
            if ($count > 1) { // Don't add on first iteration.
                $sql .= ($notlike) ? ' AND ' : ' OR ';
            }
            $path = $DB->get_field('course_categories', 'path', array('id' => $itemid));
            $uniqueparam  = rb_unique_param("ccp_{$count}");
            $uniqueparam2 = rb_unique_param("ccp2_{$count}");
            if ($operator == 2) {
                $sql .= '((' . $DB->sql_like($query, ":{$uniqueparam}", true, true, $notlike) .
                                                ") AND ( {$query} <> :{$uniqueparam2} ))";
                $params[$uniqueparam] = $DB->sql_like_escape($path) . $recursive;
                $params[$uniqueparam2] = $path;
            } else {
                $sql .= '((' . $DB->sql_like($query, ":{$uniqueparam}", true, true, $notlike) .
                                                ") OR ( {$query} = :{$uniqueparam2} ))";
                $params[$uniqueparam] = $DB->sql_like_escape($path) . $recursive;
                $params[$uniqueparam2] = $path;
            }
            $count++;
        }

        return array(' ( ' .$sql . ')', $params);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label($data) {
        global $DB;

        $operators = $this->get_operators();
        $operator  = $data['operator'];
        $recursive = $data['recursive'];
        $value  = $data['value'];
        $values = explode(',', $value);
        $label  = $this->label;

        if (empty($operator) || empty($value)) {
            return '';
        }

        $a = new stdClass();
        $a->label = $label;

        $selected = array();
        list($insql, $inparams) = $DB->get_in_or_equal($values);
        if ($categories = $DB->get_records_select('course_categories', "id {$insql}", $inparams)) {
            foreach ($categories as $category) {
                $selected[] = '"' . format_string($category->name) . '"';
            }
        }

        $orandstr = ($operator == 1) ? 'or': 'and';
        $a->value = implode(get_string($orandstr, 'totara_reportbuilder'), $selected);
        if ($recursive) {
            $a->value .= get_string('andchildren', 'totara_hierarchy');
        }
        $a->operator = $operators[$operator];

        return get_string('selectlabel', 'filters', $a);
    }

    /**
     * Include Js for this filter.
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
            'totara_reportbuilder' => array('choosecatplural'),
        );
        $jsdetails->args = array('args' => '{"filter_to_load":"category"}');

        foreach ($jsdetails->strings as $scomponent => $sstrings) {
            $PAGE->requires->strings_for_js($sstrings, $scomponent);
        }

        $PAGE->requires->js_init_call($jsdetails->initcall, $jsdetails->args, false, $jsdetails->jsmodule);
    }
}

/**
 * Given a category item object returns the HTML to display it as a filter selection.
 *
 * @param object $item A category object containing id and name properties
 * @param string $filtername The identifying name of the current filter
 *
 * @return string HTML to display a selected item
 */
function display_selected_category_item($names, $item, $filtername) {
    global $OUTPUT;

    $strdelete = get_string('delete');
    $itemname = (isset($names[$item->id])) ? $names[$item->id] : $item->name;
    $out = html_writer::start_tag('div', array('data-filtername' => $filtername,
                                               'data-id' => $item->id,
                                               'class' => 'multiselect-selected-item'));
    $out .= format_string($itemname);
    $out .= $OUTPUT->action_icon('#', new pix_icon('/t/delete', $strdelete, 'moodle'), null,
        array('class' => 'action-icon delete'));

    $out .= html_writer::end_tag('div');
    return $out;
}
