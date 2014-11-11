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

class customfield_define_menu extends customfield_define_base {

    function define_form_specific(&$form) {
        /// Param 1 for menu type contains the options
        $form->addElement('textarea', 'param1', get_string('menuoptions', 'totara_customfield'), array('rows' => 6, 'cols' => 40));
        $form->setType('param1', PARAM_MULTILANG);
        $form->addHelpButton('param1', 'customfieldmenuoptions', 'totara_customfield');

        /// Default data
        $form->addElement('text', 'defaultdata', get_string('defaultdata', 'totara_customfield'), 'size="50"');
        $form->setType('defaultdata', PARAM_MULTILANG);
        $form->addHelpButton('defaultdata', 'customfielddefaultdatamenu', 'totara_customfield');
    }

    function define_validate_specific($data, $files, $tableprefix) {
        $err = array();

        $data->param1 = trim(str_replace("\r", '', $data->param1));

        /// Check that we have at least 2 options
        if (($options = explode("\n", $data->param1)) === false) {
            $err['param1'] = get_string('menunooptions', 'totara_customfield');
        } elseif (count($options) < 2) {
            $err['param1'] = get_string('menutoofewoptions', 'totara_customfield');

        /// Check the default data exists in the options
        } elseif (!empty($data->defaultdata) and !in_array($data->defaultdata, $options)) {
            $err['defaultdata'] = get_string('menudefaultnotinoptions', 'totara_customfield');
        }
        return $err;
    }

    function define_save_preprocess($data, $old = null) {
        $data->param1 = trim(str_replace("\r", '', $data->param1));

        return $data;
    }

}
