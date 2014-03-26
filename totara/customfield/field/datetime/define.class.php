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


class customfield_define_datetime extends customfield_define_base {

    /**
     * Define the setting for a datetime custom field
     *
     * @param object $form the user form
     */
    function define_form_specific(&$form) {
        // Create variables to store start and end
        $currentyear = date('Y');
        $startyear = $currentyear - 100;
        $endyear = $currentyear + 20;

        // Create array for the years
        $arryears = array();
        for ($i = $startyear; $i <= $endyear; $i++) {
            $arryears[$i] = $i;
        }

        // Add elements
        $form->addElement('select', 'param1', get_string('startyear', 'totara_customfield'), $arryears);
        $form->setType('param1', PARAM_INT);
        $form->setDefault('param1', $currentyear);

        $form->addElement('select', 'param2', get_string('endyear', 'totara_customfield'), $arryears);
        $form->setType('param2', PARAM_INT);
        $form->setDefault('param2', $currentyear + 20);

        $form->addElement('checkbox', 'param3', get_string('wanttime', 'totara_customfield'));
        $form->setType('param3', PARAM_INT);

        $form->addElement('hidden', 'defaultdata', '0');
        $form->setType('defaultdata', PARAM_INT);
    }

    /**
     * Validate the data from the custom field form
     *
     * @param   object   data from the add/edit custom field form
     * @return  array    associative array of error messages
     */
    function define_validate_specific($data, $files, $tableprefix) {
        $errors = array();

        // Make sure the start year is not greater than the end year
        if ($data->param1 > $data->param2) {
            $errors['param1'] = get_string('startyearafterend', 'totara_customfield');
        }

        return $errors;
    }

    /**
     * Preprocess data from the  custom field form before
     * it is saved.
     *
     * @param   object   data from the add/edit custom field form
     * @return  object   processed data object
     */
    function define_save_preprocess($data, $old = null) {
        if (empty($data->param3)) {
            $data->param3 = NULL;
        }

        // No valid value in the default data column needed
        $data->defaultdata = '0';

        return $data;
    }
}
