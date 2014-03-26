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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */

require_once($CFG->dirroot.'/lib/formslib.php');

class type_edit_form extends moodleform {

    // Define the form
    function definition() {
        global $CFG, $TEXTAREA_OPTIONS;

        $mform =& $this->_form;

        $strgeneral  = get_string('general');
        $prefix   = $this->_customdata['prefix'];
        $page  = $this->_customdata['page'];

        /// Add some extra hidden fields
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'prefix', $prefix);
        $mform->setType('prefix', PARAM_ALPHA);
        $mform->addElement('hidden', 'frameworkid');
        $mform->setType('frameworkid', PARAM_INT);
        $mform->addElement('hidden', 'page', $page);
        $mform->setType('page', PARAM_INT);

        /// Print the required moodle fields first
        $mform->addElement('header', 'moodle', $strgeneral);

        $mform->addElement('text', 'fullname', get_string('fullnametype', 'totara_hierarchy'), 'maxlength="254" size="50"');
        $mform->addElement('text', 'idnumber', get_string($prefix.'typeidnumber', 'totara_hierarchy'), 'maxlength="100"  size="10"');
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addHelpButton('fullname', 'fullnametype', 'totara_hierarchy', '', true);
        $mform->addRule('fullname', get_string($prefix.'missingnametype', 'totara_hierarchy'), 'required', null, 'client');
        $mform->setType('fullname', PARAM_MULTILANG);

        if (HIERARCHY_DISPLAY_SHORTNAMES) {
            $mform->addElement('text', 'shortname', get_string('shortnametype', 'totara_hierarchy'), 'maxlength="100" size="20"');
            $mform->addHelpButton('shortname', 'shortnametype', 'totara_hierarchy');
            $mform->addRule('shortname', get_string('missingshortnametype', 'totara_hierarchy'), 'required', null, 'client');
            $mform->setType('shortname', PARAM_MULTILANG);
        }

        $mform->addElement('editor', 'description_editor', get_string($prefix. 'typedescription', 'totara_hierarchy'), null, $TEXTAREA_OPTIONS);
        $mform->addHelpButton('description_editor', $prefix. 'typedescription', 'totara_hierarchy');
        $mform->setType('description_editor', PARAM_CLEANHTML);

        $this->add_action_buttons();
    }

    function validation($data, $files) {
        $errors = array();
        $data = (object)$data;

        if (!empty($data->idnumber)) {
            $prefix = hierarchy::get_short_prefix($data->prefix);
            if (totara_idnumber_exists($prefix . '_type', $data->idnumber, $data->id)) {
                $errors['idnumber'] = get_string('idnumberexists', 'totara_core');
            }
        }

        return $errors;
    }
}
