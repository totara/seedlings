<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas
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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once($CFG->dirroot.'/lib/formslib.php');

class dp_priority_scale_value_edit_form extends moodleform {

    // Define the form
    function definition() {
        global $TEXTAREA_OPTIONS;

        $mform =& $this->_form;
        /// Add some extra hidden fields
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'priorityscaleid');
        $mform->setType('priorityscaleid', PARAM_INT);
        $mform->addElement('hidden', 'sortorder');
        $mform->setType('sortorder', PARAM_INT);

        /// Print the required moodle fields first
        $mform->addElement('header', 'moodle', get_string('general'));

        $mform->addElement('static', 'scalename', get_string('priorityscale', 'totara_plan'));
        $mform->addHelpButton('scalename', 'priorityscaleassign', 'totara_plan', '', true);

        $mform->addElement('text', 'name', get_string('priorityscalevaluename', 'totara_plan'), 'maxlength="100" size="20"');
        $mform->addHelpButton('name', 'priorityscalevaluename', 'totara_plan', '', true);
        $mform->addRule('name', get_string('missingpriorityscalevaluename', 'totara_plan'), 'required', null, 'client');
        $mform->setType('name', PARAM_MULTILANG);

        $mform->addElement('text', 'idnumber', get_string('priorityscalevalueidnumber', 'totara_plan'), 'maxlength="100"  size="10"');
        $mform->addHelpButton('idnumber', 'priorityscalevalueidnumber', 'totara_plan', '', true);
        $mform->setType('idnumber', PARAM_TEXT);

        $mform->addElement('text', 'numericscore', get_string('priorityscalevaluenumeric', 'totara_plan'), 'maxlength="100"  size="10"');
        $mform->addHelpButton('numericscore', 'priorityscalevaluenumeric', 'totara_plan', '', true);
        $mform->setType('numericscore', PARAM_NUMBER);
        $mform->addRule('numericscore', null, 'numeric', null, 'client');

        $mform->addElement('editor', 'description_editor', get_string('description'), null, $TEXTAREA_OPTIONS);
        $mform->setType('description_editor', PARAM_CLEANHTML);

        $this->add_action_buttons();
    }

    function validation($data, $files) {
        $errors = array();
        $data = (object)$data;

        if (!empty($data->idnumber) && totara_idnumber_exists('dp_priority_scale_value', $data->idnumber, $data->id)) {
            $errors['idnumber'] = get_string('idnumberexists', 'totara_core');
        }

        return $errors;
    }
}
