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
 * @author Russell England <russell.england@catalyst-net.nz>
 * @package totara
 * @subpackage completionimport
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

class completecourse_form extends moodleform {
    public function definition() {
        global $DB;
        $mform =& $this->_form;

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'progid');
        $mform->setType('progid', PARAM_INT);

        $mform->addElement('date_selector', 'timecompleted', get_string('datecompleted', 'totara_program'));

        $mform->addElement('text', 'rplgrade', get_string('rplgrade', 'totara_program'));
        $mform->setType('rplgrade', PARAM_FLOAT);
        $mform->addRule('rplgrade', null, 'numeric', null, 'client');

        $mform->addElement('text', 'rpl', get_string('rplcomments', 'totara_program'));
        $mform->setType('rpl', PARAM_TEXT);

        $this->add_action_buttons();
    }
}
