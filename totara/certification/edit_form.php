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
 * @author Jon Sharp <jonathans@catalyst-eu.net>
 * @package totara
 * @subpackage certification
 */

require_once("{$CFG->libdir}/formslib.php");
require_once('lib.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

class certification_add_form extends moodleform {

    public function definition() {
        global $CFG, $USER, $OUTPUT;

        $mform =& $this->_form;
        $action = $this->_customdata['action'];
        $category = $this->_customdata['category'];
        $systemcontext = context_system::instance();
        $categorycontext = context_coursecat::instance($category->id);

        // Add some hidden fields.
        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_TEXT);

        $comptypeoptions = array(
            CERTIFTYPE_PROGRAM => get_string('program', 'totara_certification'),
        );
        $mform->addElement('select', 'comptype', get_string('comptype', 'totara_certification'), $comptypeoptions);

        $mform->addHelpButton('comptype', 'comptype', 'totara_certification');
        $mform->setDefault('comptype', CERTIFTYPE_PROGRAM);
        $mform->setType('comptype', PARAM_INT);

        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'savechanges', get_string('add'), 'class="certification-add"');
        $buttonarray[] = $mform->createElement('cancel', 'cancel', get_string('cancel'),
                                                'class="certification-cancel"');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');

    }
}
