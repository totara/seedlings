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
 * @package modules
 * @subpackage facetoface
 */

defined('MOODLE_INTERNAL') || die();

require_once "$CFG->dirroot/lib/formslib.php";
require_once "$CFG->dirroot/mod/facetoface/lib.php";

class mod_facetoface_customfield_form extends moodleform {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name'), 'maxlength="255" size="50"');
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('text', 'shortname', get_string('shortname'), 'maxlength="255" size="25"');
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->setType('shortname', PARAM_TEXT);

        $options = array(CUSTOMFIELD_TYPE_TEXT        => get_string('field:text', 'facetoface'),
                         CUSTOMFIELD_TYPE_SELECT      => get_string('field:select', 'facetoface'),
                         CUSTOMFIELD_TYPE_MULTISELECT => get_string('field:multiselect', 'facetoface'),
                         );
        $mform->addElement('select', 'type', get_string('setting:type', 'facetoface'), $options);
        $mform->addRule('type', null, 'required', null, 'client');

        $mform->addElement('text', 'defaultvalue', get_string('setting:defaultvalue', 'facetoface'), 'maxlength="255" size="30"');
        $mform->setType('defaultvalue', PARAM_TEXT);

        $mform->addElement('textarea', 'possiblevalues', get_string('setting:possiblevalues', 'facetoface'), 'rows="5" cols="30"');
        $mform->setType('possiblevalues', PARAM_RAW);
        $mform->disabledIf('possiblevalues', 'type', 'eq', 0);

        $mform->addElement('advcheckbox', 'required', get_string('required'));
        $mform->addElement('advcheckbox', 'showinsummary', get_string('setting:showinsummary', 'facetoface'));

        if ($this->_customdata['id']) {
            $label = null;
        } else {
            $label = get_string('addnewfieldlink', 'facetoface');
        }

        $this->add_action_buttons(true, $label);
    }

    public function validation($data, $files) {
        global $DB;

        $errors = array();
        $where  = "id <> ? AND LOWER(shortname) = LOWER(?)";
        $params = array($data['id'], $data['shortname']);

        if ($DB->record_exists_select('facetoface_session_field', $where, $params)) {
            $errors['shortname']= get_string('error:shortnametaken', 'facetoface');
        }

        return $errors;
    }
}
