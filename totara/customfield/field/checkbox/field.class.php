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

class customfield_checkbox extends customfield_base {

    /**
     * Constructor method.
     * Pulls out the options for the checkbox from the database and sets the
     * the corresponding key for the data if it exists
     */
    function customfield_checkbox($fieldid=0, $item, $prefix, $tableprefix) {
        global $DB;

        //first call parent constructor
        $this->customfield_base($fieldid, $item, $prefix, $tableprefix);

        if (!empty($this->field)) {
            $datafield = $DB->get_field($tableprefix.'_info_data', 'data', array($prefix.'id' => $item->id, 'fieldid' => $this->fieldid));
            if ($datafield !== false) {
                $this->data = $datafield;
            } else {
                $this->data = $this->field->defaultdata;
            }
        }
    }

    function edit_field_add(&$form) {
        /// Create the form field
        $checkbox = &$form->addElement('advcheckbox', $this->inputname, format_string($this->field->fullname));
        if ($this->data == '1') {
            $checkbox->setChecked(true);
        }
        $form->setType($this->inputname, PARAM_BOOL);
        if ($this->is_required()) {
            $form->addRule($this->inputname, get_string('customfieldrequired', 'totara_customfield'), 'nonzero', null, 'client');
        }
    }

    /**
     * Display the data for this field
     */
    static function display_item_data($data, $extradata=array()) {
        $options = new stdClass();
        $options->para = false;
        if (intval($data) === 1) {
            return get_string('yes');
        } else {
            return get_string('no');
        }
    }

}
