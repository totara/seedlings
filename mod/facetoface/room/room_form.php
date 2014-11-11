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
 * @subpackage facetoface
 */

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->dirroot}/lib/formslib.php");

class mod_facetoface_room_form extends moodleform {

    /**
     * Definition of the room form
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'page');
        $mform->setType('page', PARAM_INT);

        $mform->addElement('text', 'name', get_string('roomname', 'facetoface'), array('size' => '45'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'building', get_string('building', 'facetoface'), array('size' => '45'));
        $mform->setType('building', PARAM_TEXT);
        $mform->addRule('building', null, 'required', null, 'client');

        $mform->addElement('text', 'address', get_string('address', 'facetoface'), array('size' => '45'));
        $mform->setType('address', PARAM_TEXT);
        $mform->addRule('address', null, 'required', null, 'client');

        $mform->addElement('text', 'capacity', get_string('capacity', 'facetoface'));
        $mform->setType('capacity', PARAM_INT);
        $mform->addRule('capacity', null, 'required', null, 'client');
        $mform->addRule('capacity', null, 'numeric', null, 'client');

        $typelist = array('internal' => get_string('internal', 'facetoface'), 'external' => get_string('external', 'facetoface'));
        $mform->addElement('select', 'type', get_string('type', 'facetoface'), $typelist);
        $mform->addHelpButton('type', 'roomtype', 'facetoface');

        $mform->addElement('editor', 'description_editor', get_string('roomdescription', 'facetoface'), null, $this->_customdata['editoroptions']);

        if ($this->_customdata['id']) {
            $label = null;
        } else {
            $label = get_string('addroom', 'facetoface');
        }

        $this->add_action_buttons(true, $label);
    }
}
