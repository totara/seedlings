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
 * @package    mod
 * @subpackage certificate
 * @copyright  Catalyst IT Ltd 2013 <http://catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 *
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

class mod_certificate_view_archive_form extends moodleform {

    public function definition() {
        $mform =& $this->_form;

        $mform->addElement('header', 'details', get_string('filteroptions', 'certificate'));

        $mform->addElement('hidden', 'certid');
        $mform->setType('certid', PARAM_INT);

        $mform->addElement('text', 'username', get_string('usernamefilter', 'feedback'));
        $mform->setType('username', PARAM_TEXT);

        $mform->addElement('text', 'firstname', get_string('firstnamefilter', 'feedback'));
        $mform->setType('firstname', PARAM_TEXT);

        $mform->addElement('text', 'lastname', get_string('lastnamefilter', 'feedback'));
        $mform->setType('lastname', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('submit'));
    }

}
