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

require_once "$CFG->dirroot/lib/formslib.php";

class mod_facetoface_signup_form extends moodleform {

    function definition() {
        global $CFG;

        $mform =& $this->_form;
        $manageremail = $this->_customdata['manageremail'];
        $showdiscountcode = $this->_customdata['showdiscountcode'];

        $mform->addElement('hidden', 's', $this->_customdata['s']);
        $mform->setType('s', PARAM_INT);
        $mform->addElement('hidden', 'backtoallsessions', $this->_customdata['backtoallsessions']);
        $mform->setType('backtoallsessions', PARAM_TEXT);

        if ($manageremail === false) {
            $mform->addElement('hidden', 'manageremail', '');
        } else {
            $mform->addElement('html', get_string('manageremailinstructionconfirm', 'facetoface')); // instructions

            $mform->addElement('text', 'manageremail', get_string('manageremail', 'facetoface'), 'size="35"');
            $mform->addRule('manageremail', null, 'required', null, 'client');
            $mform->addRule('manageremail', null, 'email', null, 'client');
        }
        $mform->setType('manageremail', PARAM_TEXT);

        if ($showdiscountcode) {
            $mform->addElement('text', 'discountcode', get_string('discountcode', 'facetoface'), 'size="6"');
            $mform->addHelpButton('discountcode', 'discountcodelearner', 'facetoface');
        } else {
            $mform->addElement('hidden', 'discountcode', '');
        }
        $mform->setType('discountcode', PARAM_TEXT);

        if (empty($CFG->facetoface_notificationdisable)) {
            $options = array(MDL_F2F_BOTH => get_string('notificationboth', 'facetoface'),
                             MDL_F2F_TEXT => get_string('notificationemail', 'facetoface'),
                             MDL_F2F_NONE => get_string('notificationnone', 'facetoface'),
                             );
            $mform->addElement('select', 'notificationtype', get_string('notificationtype', 'facetoface'), $options);
            $mform->addHelpButton('notificationtype', 'notificationtype', 'facetoface');
            $mform->addRule('notificationtype', null, 'required', null, 'client');
            $mform->setDefault('notificationtype', MDL_F2F_BOTH);
        } else {
            $mform->addElement('hidden', 'notificationtype', MDL_F2F_NONE);
        }
        $mform->setType('notificationtype', PARAM_INT);

        $this->add_action_buttons(true, get_string('signup', 'facetoface'));
    }

    function validation($data, $files)
    {
        $errors = parent::validation($data, $files);

        $manageremail = $data['manageremail'];
        if (!empty($manageremail)) {
            if (!facetoface_check_manageremail($manageremail)) {
                $errors['manageremail'] = facetoface_get_manageremailformat();
            }
        }

        return $errors;
    }
}
