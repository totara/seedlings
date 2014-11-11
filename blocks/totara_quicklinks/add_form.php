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
 * Block for displaying user-defined links
 *
 * @package   totara
 * @author    Brian Barnes <brian.barnes@totaralms.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/formslib.php');
class totara_quicklinks_add_quicklink_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'blockaction', 'add');
        $mform->setType('blockaction', PARAM_RAW);
        $mform->addElement('hidden', 'blockinstanceid', $this->_customdata['blockinstanceid']);
        $mform->setType('blockinstanceid', PARAM_RAW);
        $mform->addElement('text', 'linktitle', get_string('linktitle', 'block_totara_quicklinks'));
        $mform->addRule('linktitle', null, 'required', null, 'client');
        $mform->setType('linktitle', PARAM_TEXT);
        $mform->addElement('text', 'linkurl', get_string('url', 'block_totara_quicklinks'));
        $mform->addRule('linkurl', null, 'required', null, 'client');
        $mform->setType('linkurl', PARAM_URL);

        $this->add_action_buttons(false, 'Add link');
    }
}
