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
 * @author Andrew Hancox <andrewdchancox@googlemail.com> on behalf of Synergy Learning
 * @package modules
 * @subpackage facetoface
 */

require_once("$CFG->dirroot/lib/formslib.php");

class signup_tsandcs_form extends moodleform {

    public function definition() {
        $mform = & $this->_form;
        $tsandcs = $this->_customdata['tsandcs'];
        $s = $this->_customdata['s'];

        $mform->addElement('header', '', get_string('selfapprovaltandc', 'mod_facetoface'));

        $mform->addElement('html', format_text($tsandcs, FORMAT_PLAIN));

        $mform->addElement('hidden', 's', $s);

        $mform->addElement('submit', 'confirm', get_string('close', 'mod_facetoface'));
    }
}