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
 * Totara navigation edit form page
 *
 * @author Oleg Demeshev <oleg.demeshev@totaralms.com>
 * @package totara
 * @subpackage navigation
 */

use \totara_core\totara\menu\menu as menu;

class edit_form extends moodleform {

    public function definition() {

        $mform = & $this->_form;
        $item = (object)$this->_customdata['item'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'custom');
        $mform->setType('custom', PARAM_INT);

        $mform->addElement('html', html_writer::tag('hr', ''));

        $options = menu::make_menu_list($item->id);
        $mform->addElement('select', 'parentid', get_string('menuitem:formitemparent', 'totara_core'), $options);
        $mform->setType('parentid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('menuitem:formitemtitle', 'totara_core'), 'maxlength="1024"');
        $mform->addRule('title', get_string('required'), 'required', null);
        $mform->setType('title', PARAM_MULTILANG);
        $mform->addHelpButton('title', 'menuitem:formitemtitle', 'totara_core');

        $options = menu::get_visibility_list();
        if ($item->custom == menu::DB_ITEM) {
            unset($options[menu::SHOW_WHEN_REQUIRED]);
        } else {
            unset($options[menu::SHOW_ALWAYS]);
        }
        $mform->addElement('select', 'visibility', get_string('menuitem:formitemvisibility', 'totara_core'), $options);
        $mform->setType('visibility', PARAM_INT);

        if ($item->custom == menu::DB_ITEM) {
            $mform->addElement('text', 'url', get_string('menuitem:formitemurl', 'totara_core'), 'maxlength="255"');
            $mform->setType('url', PARAM_TEXT);
            $mform->addHelpButton('url', 'menuitem:formitemurl', 'totara_core');
        }

        $options = array(menu::TARGET_ATTR_SELF, menu::TARGET_ATTR_BLANK);
        $mform->addElement('advcheckbox', 'targetattr', get_string('menuitem:formitemtargetattr', 'totara_core'), '', null, $options);
        $mform->addHelpButton('targetattr', 'menuitem:formitemtargetattr', 'totara_core');

        if ($item->id > 0) {
            $strsubmit = get_string('savechanges');
        } else {
            $strsubmit = get_string('menuitem:addnew', 'totara_core');
        }
        $this->add_action_buttons(true, $strsubmit);

        $defaults = new stdClass();
        $defaults->id = $item->id;
        $defaults->parentid = $item->parentid;
        $defaults->title = $item->title;
        $defaults->url = $item->url;
        $defaults->custom = $item->custom;
        $defaults->visibility = $item->visibility;
        $defaults->targetattr = $item->targetattr;

        $this->set_data($defaults);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $errors = array_merge(menu::validation((object)$data), $errors);
        return $errors;
    }
}
