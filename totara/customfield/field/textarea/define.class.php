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

class customfield_define_textarea extends customfield_define_base {

    function define_form_specific(&$form) {
        global $TEXTAREA_OPTIONS;
        /// Default data
        $form->addElement('editor', 'defaultdata_editor', get_string('defaultdata', 'totara_customfield'), null, $TEXTAREA_OPTIONS);
        $form->setType('defaultdata_editor', PARAM_CLEANHTML);
        $form->addHelpButton('defaultdata_editor', 'customfielddefaultdatatextarea', 'totara_customfield');

        /// Param 1 for textarea type is the number of columns
        $form->addElement('text', 'param1', get_string('fieldcolumns', 'totara_customfield'), 'size="6"');
        $form->setDefault('param1', 30);
        $form->setType('param1', PARAM_INT);
        $form->addHelpButton('param1', 'customfieldcolumnstextarea', 'totara_customfield');

        /// Param 2 for text type is the number of rows
        $form->addElement('text', 'param2', get_string('fieldrows', 'totara_customfield'), 'size="6"');
        $form->setDefault('param2', 10);
        $form->setType('param2', PARAM_INT);
        $form->addHelpButton('param2', 'customfieldrowstextarea', 'totara_customfield');
    }

}
