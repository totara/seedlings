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
 * @package totara
 * @subpackage totara_customfield
 */

class customfield_multiselect extends customfield_base {
    /**
     * Number of items to keep in one column mode
     * If more then items will be displayed horizontally.
     */
    const MAX_ONE_COLUMN = 8;

    public $options = array();
    public $datakey;
    public $tableprefix = '';
    public $groupsize = 0;

    /**
     * Constructor method.
     * Pulls out the options for the menu from the database and sets the
     * the corresponding key for the data if it exists
     */
    public function __construct($fieldid=0, $itemid=0, $prefix, $tableprefix) {
        global $DB;
        // First call parent constructor.
        $this->customfield_base($fieldid, $itemid, $prefix, $tableprefix);
        $this->tableprefix = $tableprefix;

        // Param 1 for menu type is the options.
        if ($this->field->param1) {
            $options = json_decode($this->field->param1, true);
        }
        $this->options = array_values($options);

        if ($this->dataid) {
            // Load data settings.
            $params = $DB->get_records($this->tableprefix.'_info_data_param', array('dataid' => $this->dataid), '', 'value');
            $details = array();
            if ($params) {
                $details = $DB->get_field($this->tableprefix.'_info_data', 'data', array('id' => $this->dataid));
                if ($details != '') {
                    $details = json_decode($details, true);
                }
            }
            $values = array_keys($params);
            // Set new default if field was saved.
            foreach ($this->options as $ind => $option) {
                if (in_array(md5($option['option']), $values)) {
                    $this->options[$ind]['default'] = 1;
                    // Remove existing options.
                    unset($params[md5($option['option'])]);
                } else {
                    $this->options[$ind]['default'] = 0;
                }
            }

            // Add options that were removed from definition but exists in custom fields options.
            foreach ($params as $param) {
                $key = $param->value;
                $details[$key]['default'] = 1;
                $this->options[] = $details[$key];
            }
        }

        if (count($this->options) > self::MAX_ONE_COLUMN) {
            $this->groupsize = count($this->options);
        } else {
            $this->groupsize = 1;
        }
    }

    /**
     * Create the code snippet for this field instance
     * Overwrites the base class method
     * @param   object   moodleform instance
     */
    public function edit_field_add(&$mform) {
        $ind = 0;
        $grpcnt = 0;
        $title = format_string($this->field->fullname);
        while ($ind < count($this->options)) {
            $chkgrp = array();
            $grp = 0;
            while ($grp < $this->groupsize) {
                if (isset($this->options[$ind])) {
                    $iconhtml = totara_icon_picker_preview('course', $this->options[$ind]['icon']);
                    $chkgrp[] = $mform->createElement('advcheckbox', $this->inputname . '[' . $ind . ']', '',
                            $iconhtml . format_string($this->options[$ind]['option']));
                }
                $grp++;
                $ind++;
            }
            if (count($chkgrp)) {
                $grpname = 'grp_' . $this->fieldid . '_' . $grpcnt;
                $groupelement = $mform->addGroup($chkgrp, $grpname, $title, null, false);
                $groupelement->updateAttributes(array('class' => 'multiselect'));
                // Show title only first time.
                $title = '';
            }
            $grpcnt++;
        }
    }

    /**
     * Set the default value for this field instance
     * Overwrites the base class method
     */
    public function edit_field_set_default(&$mform) {
        foreach ($this->options as $ind => $option) {
            if ($option['default']) {
                $mform->setDefault($this->inputname . '[' . $ind . ']', 1);
            } else {
                $mform->setDefault($this->inputname . '[' . $ind . ']', 0);
            }
        }
    }

    /**
     * The data from the form returns the key. This should be converted to the
     * respective option string to be saved in database
     * Overwrites base class accessor method
     * @param   integer   the key returned from the select input in the form
     */
    public function edit_save_data_preprocess($key) {
        return json_encode($this->prepare_data($key));
    }

    /**
     * Prepare data from raw input
     * @param array $key
     * @return array
     */
    public function prepare_data($key) {
        $chosen = array_keys(array_filter($key));
        $return = array();
        foreach ($chosen as $ind) {
            $return[md5($this->options[$ind]['option'])] = $this->options[$ind];
        }
        return $return;
    }

    /**
     * The data from the form returns the key. This should be converted to the
     * respective option string to be saved in database
     * Overwrites base class accessor method
     * @param   integer   the key returned from the select input in the form
     */
    public function edit_save_data_postprocess($key) {
        global $DB;
        // Remove all params.
        if ($this->dataid) {
            $DB->delete_records($this->tableprefix . '_info_data_param', array('dataid' => $this->dataid));
        }
        foreach ($key as $ind => $value) {
            if ($value && isset($this->options[$ind])) {
                // Add new param.
                $data = new stdClass();
                $data->dataid = $this->dataid;
                $data->value = md5($this->options[$ind]['option']);
                $DB->insert_record($this->tableprefix . '_info_data_param', $data);
            }
        }
    }

    public function edit_validate_field($itemnew, $prefix, $tableprefix) {
        global $DB;
        if ($this->is_hidden()) {
            return array();
        }
        $formdata = isset($itemnew->{$this->inputname}) ? $itemnew->{$this->inputname} : array();
        $values = $this->prepare_data($formdata);
        $groupname = 'grp_'.$this->fieldid.'_0';
        if ($this->is_unique() && count($values)) {
            $unique = true;
            $count = count($values);
            $options = array();
            foreach ($values as $value) {
                $options[] = md5($value['option']);
            }
            list($optionssql, $optionsparams) = $DB->get_in_or_equal($options, SQL_PARAMS_NAMED);

            $field = "{$prefix}id";
            // Fetch all fields that have all options of current field.
            $sqlfields = "SELECT dataid, COUNT(cidp.id) AS cnt_id FROM {{$tableprefix}_info_data} cid
                            LEFT JOIN {{$tableprefix}_info_data_param} cidp ON (cid.id = cidp.dataid)
                           WHERE cid.fieldid = :fieldid
                             AND (cidp.value {$optionssql})
                             AND cid.{$field} != :instanceid
                           GROUP BY dataid
                          HAVING COUNT(cidp.id) = :cnt";
            $fieldparams = array('fieldid' => $this->fieldid, 'cnt' => $count, 'instanceid' => $itemnew->id);
            $params = array_merge($fieldparams, $optionsparams);
            $matchmincnt = $DB->get_records_sql($sqlfields, $params);

            foreach ($matchmincnt as $match) {
                // Now check that fetched fields don't have any other options.
                $sqlexact = "SELECT COUNT(id) AS cnt_id
                               FROM {{$tableprefix}_info_data_param}
                              WHERE dataid = ?";
                $matchexact = $DB->get_field_sql($sqlexact, array($match->dataid));
                if ($matchexact == $count) {
                    $unique = false;
                    break;
                }
            }
            if (!$unique) {
                return array($groupname => get_string('valuealreadyused'));
            }
        }
        // Check for required.
        if ($this->is_required() && empty($values)) {
            return array($groupname => get_string('customfieldrequired',
                    'totara_customfield'));
        }
        return array();
    }

    /**
     * Display the data for this field
     */
    public static function display_item_data($data, $extradata=array()) {
        $extradata['display'] = isset($extradata['display']) ? $extradata['display'] : '';
        $return = array();
        if ($data != '') {
            $data = json_decode($data, true);
        }

        if (!is_array($data)) {
            $data = array();
        }

        foreach ($data as $item) {
            $return[] = self::get_item_string(format_string($item['option']), $item['icon'], $extradata['display']);
        }

        if (isset($extradata['display']) && $extradata['display'] == 'list-text') {
            $glue = ', ';
        } else {
            $glue = ' ';
        }

        return implode($glue, $return);
    }

    /**
     * Get a string for the given title and icon.
     *
     * @param string $title
     * @param string $icon
     * @param type $display
     * @return string
     */
    public static function get_item_string($title, $icon, $display) {
        if ($icon == '') {
            $iconhtml = totara_icon_picker_preview('course', 'default', '', $title);
        } else {
            $iconhtml = totara_icon_picker_preview('course', $icon, '', $title);
        }

        if (isset($display) && $display == 'list-icons') {
            $result = $iconhtml;
        } else if (isset($display) && $display == 'list-text') {
            $result = $title;
        } else {
            $result = html_writer::div($iconhtml . $title);
        }
        return $result;
    }

    /**
     * HardFreeze the field if locked.
     * @param   object   instance of the moodleform class
     */
    public function edit_field_set_locked(&$mform) {
        $groupbasename = 'grp_' . $this->fieldid . '_';
        if (!$mform->elementExists($groupbasename.'0')) {
            return;
        }
        if ($this->is_locked()) {
            for ($iter = 0; $iter < $this->groupsize; $iter++) {
                $group = $mform->getElement($groupbasename.$iter);
                $elems = $group->getElements();
                foreach ($elems as $elem) {
                    $elem->freeze();
                }
            }
        }
    }

    /**
     * Sets the required flag for the field in the form object
     * @param   object   instance of the moodleform class
     */
    public function edit_field_set_required(&$mform) {
        if ($this->is_required()) {
            $mform->addRule('grp_' . $this->fieldid . '_0',
                    get_string('customfieldrequired', 'totara_customfield'), 'required', null,
                    'client');
        }
    }

}
