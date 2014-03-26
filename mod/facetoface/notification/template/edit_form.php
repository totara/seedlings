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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package modules
 * @subpackage facetoface
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');

class mod_facetoface_notification_template_form extends moodleform {

    function definition() {
        global $CFG;

        $mform =& $this->_form;

        $editoroptions = $this->_customdata['editoroptions'];

        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title', get_string('title', 'facetoface'), array('size' => 50));
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->setType('title', PARAM_TEXT);

        $mform->addElement('editor', 'body_editor', get_string('body', 'facetoface'));
        $mform->setType('body_editor', PARAM_RAW);

        $mform->addElement('editor', 'managerprefix_editor', get_string('managerprefix', 'facetoface'));
        $mform->setType('managerprefix', PARAM_RAW);

        $mform->addElement('advcheckbox', 'status', get_string('status'));
        $mform->setType('status', PARAM_INT);
        $mform->addHelpButton('status', 'notificationtemplatestatus', 'facetoface');

        $this->add_action_buttons(true, get_string('save', 'admin'));
    }
}
