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
 * Generic filter based on a hierarchy.
 */
class rb_filter_hierarchy extends rb_filter_type {

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
     * @return rb_filter_hierarchy object
     */
    public function __construct($type, $value, $advanced, $region, $report) {
        parent::__construct($type, $value, $advanced, $region, $report);

        // Refers to the name of the main table e.g. 'pos', 'org' or 'comp'
        if (!isset($this->options['hierarchytype'])) {
            // hierarchy type required for this filter
            throw new ReportBuilderException(get_string('hierarchyfiltermusthavetype',
                'totara_reportbuilder',
                (object)array('type' => $type, 'value' => $value, 'source' => get_class($report->src))));
        }
    }

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    function get_operators() {
        return array(0 => get_string('isanyvalue', 'filters'),
                     1 => get_string('isequalto', 'filters'),
                     2 => get_string('isnotequalto', 'filters'));
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    function setupForm(&$mform) {
        global $SESSION;
        $label = format_string($this->label);
        $advanced = $this->advanced;
        $type = $this->options['hierarchytype'];

        // manually disable buttons - can't use disabledIf because
        // button isn't created using form element
        $attr = "onChange=\"if (this.value == 0) {
            $('input[name=" . $this->name . "_rec]').attr('disabled', true);
            $('#show-" . $this->name . "-dialog').attr('disabled', true);
        } else {
            $('input[name=" . $this->name . "_rec]').removeAttr('disabled');
            $('#show-" . $this->name . "-dialog').removeAttr('disabled');
        }\"";
        $objs = array();
        $objs[] =& $mform->createElement('select', $this->name.'_op', null, $this->get_operators(), $attr);
        $objs[] =& $mform->createElement('static', 'title'.$this->name, '',
            html_writer::tag('span', '', array('id' => $this->name . 'title', 'class' => 'dialog-result-title')));
        $mform->setType($this->name.'_op', PARAM_TEXT);
        // can't use a button because id must be 'show-*-dialog' and
        // formslib appends 'id_' to ID
        // TODO change dialogs to bind to any id
        $objs[] =& $mform->createElement('static', 'selectorbutton',
            '',
            html_writer::empty_tag('input', array('type' => 'button',
                'class' => 'rb-filter-button rb-filter-choose-' . $type,
                'value' => get_string('choose' . $type, 'totara_reportbuilder'),
                'id' => 'show-' . $this->name . '-dialog')));
        $objs[] =& $mform->createElement('checkbox', $this->name . '_rec', '', get_string('includesubcategories', 'filters'));
        $mform->setType($this->name . '_rec', PARAM_TEXT);

        $grp =& $mform->addElement('group', $this->name.'_grp', $label, $objs, '', false);
        $mform->addHelpButton($grp->_name, 'reportbuilderdialogfilter', 'totara_reportbuilder');
        if ($advanced) {
            $mform->setAdvanced($this->name.'_grp');
        }

        $mform->addElement('hidden', $this->name);
        $mform->setType($this->name, PARAM_TEXT);

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
        if (isset($defaults['recursive'])) {
            $mform->setDefault($this->name . '_rec', $defaults['recursive']);
        }

    }

    function definition_after_data(&$mform) {
        global $DB;
        $type = $this->options['hierarchytype'];

        if ($id = $mform->getElementValue($this->name)) {
            if ($title = $DB->get_field($type, 'fullname', array('id' => $id))) {
                $mform->setDefault('title'.$this->name,
                html_writer::tag('span', $title, array('id' => $this->name . 'title', 'class' => 'dialog-result-title')));
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
        $operator = $field.'_op';
        $recursive = $field.'_rec';

        if (isset($formdata->$field) &&
            $formdata->$field != '') {
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
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return array containing filtering condition SQL clause and params
     */
    function get_sql_filter($data) {
        global $DB;

        $operator = $data['operator'];
        $recursive = (isset($data['recursive'])
            && $data['recursive']) ? '/%' : '';
        $value    = $data['value'];
        $query    = $this->get_field();
        $type = $this->options['hierarchytype'];

        switch($operator) {
            case 1:
                $not = false;
                break;
            case 2:
                $not = true;
                break;
            default:
                // return 1=1 instead of TRUE for MSSQL support
                return array(' 1=1 ', array());
        }

        $path = $DB->get_field($type, 'path', array('id' => $value));
        $params = array();
        $uniqueparam = rb_unique_param("fh{$operator}_");
        $uniqueparam2 = rb_unique_param("fh{$operator}2_");
        if ($operator == 2) {
            // check for null case for is not operator
            $sql = '(((' . $DB->sql_like($query, ":{$uniqueparam}", true, true, $not) . ") AND ( {$query} <> :{$uniqueparam2} )) OR ({$query}) IS NULL)";
            $params[$uniqueparam] = $DB->sql_like_escape($path) . $recursive;
            $params[$uniqueparam2] = $path;
        } else {
            $sql = '((' . $DB->sql_like($query, ":{$uniqueparam}", true, true, $not) . ") OR ( {$query} = :{$uniqueparam2} ))";
            $params[$uniqueparam] = $DB->sql_like_escape($path) . $recursive;
            $params[$uniqueparam2] = $path;
        }

        return array($sql, $params);
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        global $DB;

        $operators = $this->get_operators();
        $operator  = $data['operator'];
        $recursive = $data['recursive'];
        $value     = $data['value'];
        $label = $this->label;
        $type = $this->options['hierarchytype'];

        if (empty($operator) || $value == '') {
            return '';
        }

        $itemname = $DB->get_field($type, 'fullname', array('id' => $value));

        $a = new stdClass();
        $a->label    = $label;
        $a->value    = '"'.s($itemname).'"';
        if ($recursive) {
            $a->value .= get_string('andchildren', 'totara_hierarchy');
        }
        $a->operator = $operators[$operator];

        return get_string('selectlabel', 'filters', $a);
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
            'totara_hierarchy' => array('chooseposition', 'selected', 'chooseorganisation', 'currentlyselected', 'selectcompetency'),
            'totara_reportbuilder' => array('chooseorgplural', 'chooseposplural', 'choosecompplural'),
        );
        $title = $this->type . '-' . $this->value;
        $currentlyselected = json_encode(dialog_display_currently_selected(get_string('currentlyselected', 'totara_hierarchy'), $title));
        $arg = "\"{$title}-currentlyselected\":{$currentlyselected}";
        $jsdetails->args = array('args' => '{"filter_to_load":"hierarchy",' . $arg . ',"hierarchytype":"' .
                                            $this->options['hierarchytype'] . '"}');

        foreach ($jsdetails->strings as $scomponent => $sstrings) {
            $PAGE->requires->strings_for_js($sstrings, $scomponent);
        }

        $PAGE->requires->js_init_call($jsdetails->initcall, $jsdetails->args, false, $jsdetails->jsmodule);
    }
}
