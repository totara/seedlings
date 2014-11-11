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
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @package totara
 * @subpackage facetoface
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class attendee_position_form extends moodleform {
    public function definition() {
        $mform = & $this->_form;
        $applicablepositions = $this->_customdata['applicablepositions'];
        $fullname = $this->_customdata['fullname'];
        $selectedposition = $this->_customdata['selectedposition'];
        $userid = $this->_customdata['userid'];
        $sessionid = $this->_customdata['sessionid'];

        $mform->addElement('header', 'userpositionheader', get_string('userpositionheading', 'facetoface', $fullname));

        if (count($applicablepositions) == 0) {
            $mform->addElement('static', null, null, get_string('noposition', 'mod_facetoface'));
            $mform->createElement('cancel');
        } else {
            $mform->addElement('hidden', 'id', $userid);
            $mform->setType('id', PARAM_INT);

            $mform->addElement('hidden', 's', $sessionid);
            $mform->setType('s', PARAM_INT);

            $mform->addElement('html', html_writer::tag('p', '&nbsp;', array('id' => 'attendee_note_err', 'class' => 'error')));

            $posselectelement = $mform->addElement('select', 'selectposition', get_string('selectposition', 'mod_facetoface'));

            foreach ($applicablepositions as $posassignment) {
                $label = position::position_label($posassignment);
                $posselectelement->addOption($label, $posassignment->id);
            }
            $posselectelement->setSelected($selectedposition);

            $mform->setType('selectposition', PARAM_INT);

            $this->add_action_buttons(true, get_string('updateposition', 'facetoface'));
        }
    }
}
