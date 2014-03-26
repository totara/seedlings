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
 * @subpackage customfield
 */


class customfield_datetime extends customfield_base {

    /**
     * Handles editing datetime fields
     *
     * @param object moodleform instance
     */
    function edit_field_add(&$mform) {
        // Check if the field is required
        if ($this->field->required) {
            $optional = false;
        } else {
            $optional = true;
        }

        $attributes = array(
            'startyear' => $this->field->param1,
            'stopyear'  => $this->field->param2,
            'timezone'  => 99,
            'applydst'  => true,
            'optional'  => $optional
        );

        // Check if they wanted to include time as well
        if (!empty($this->field->param3)) {
            $mform->addElement('date_time_selector', $this->inputname, format_string($this->field->fullname), $attributes);
        } else {
            $mform->addElement('date_selector', $this->inputname, format_string($this->field->fullname), $attributes);
        }

        $mform->setType($this->inputname, PARAM_INT);
        $mform->setDefault($this->inputname, time());
    }

    /**
     * Display the data for this field
     */
    static function display_item_data($data, $extradata=array()) {
        // Check if time was specifieid with a sneaky sneaky little hack :)
        if (date('G', $data) != 0) { // 12:00 am - assume no time was saved
            $format = get_string('strftimedaydatetime', 'langconfig');
        } else {
            $format = get_string('strftimedate', 'langconfig');
        }

        // Check if a date has been specified
        if (empty($data)) {
            return get_string('notset', 'totara_customfield');
        } else {
            return userdate($data, $format);
        }
    }
}
