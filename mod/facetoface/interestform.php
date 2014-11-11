<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 *
 * @copyright 2014 Yair Spielmann, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once($CFG->libdir.'/formslib.php');

class mod_facetoface_interest_form extends moodleform {
    public function definition() {
        global $CFG;

        $mform =& $this->_form;

        $mform->addElement('hidden', 'f', $this->_customdata['f']);
        $mform->setType('f', PARAM_INT);
        if ($this->_customdata['declare']) {
            $mform->addElement('textarea', 'reason', get_string('declareinterstreason', 'facetoface'), array('rows' => 5, 'cols' => 80));
            $mform->setType('reason', PARAM_TEXT);
        }
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('confirm'));
        $buttonarray[] = $mform->createElement('cancel', 'cancel', get_string('cancel'));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }
}
