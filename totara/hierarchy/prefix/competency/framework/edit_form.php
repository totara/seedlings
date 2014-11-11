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

class framework_edit_form extends moodleform {

    // Define the form
    function definition() {
        global $CFG, $DB, $TEXTAREA_OPTIONS;

        $mform =& $this->_form;
        $strgeneral  = get_string('general');

        /// Load competency scales
        $scales = array();
        $scales_raw = competency_scales_available();

        foreach ($scales_raw as $scale) {
            $scales[$scale->id] = format_string($scale->name);
        }

        /// Add some extra hidden fields
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'visible');
        $mform->setType('visible', PARAM_INT);
        $mform->addElement('hidden', 'sortorder');
        $mform->setType('sortorder', PARAM_INT);
        $mform->addElement('hidden', 'hidecustomfields');
        $mform->setType('hidecustomfields', PARAM_INT);
        $mform->addElement('hidden', 'prefix', 'competency');
        $mform->setType('prefix', PARAM_ALPHA);

        /// Print the required moodle fields first
        $mform->addElement('header', 'moodle', $strgeneral);
        $mform->addHelpButton('moodle', 'competencyframeworkgeneral', 'totara_hierarchy');

        $mform->addElement('text', 'fullname', get_string('competencyframeworkfullname', 'totara_hierarchy'), 'maxlength="254" size="50"');
        $mform->addHelpButton('fullname', 'competencyframeworkfullname', 'totara_hierarchy');
        $mform->addRule('fullname', get_string('competencymissingnameframework', 'totara_hierarchy'), 'required', null, 'client');
        $mform->setType('fullname', PARAM_MULTILANG);

        if (HIERARCHY_DISPLAY_SHORTNAMES) {
            $mform->addElement('text', 'shortname', get_string('shortnameframework', 'totara_hierarchy'), 'maxlength="100" size="20"');
            $mform->addHelpButton('shortname', 'competencyframeworkshortname', 'totara_hierarchy');
            $mform->addRule('shortname', get_string('missingshortnameframework', 'totara_hierarchy'), 'required', null, 'client');
            $mform->setType('shortname', PARAM_MULTILANG);
        }

        $mform->addElement('text', 'idnumber', get_string('competencyframeworkidnumber', 'totara_hierarchy'), 'maxlength="100"  size="10"');
        $mform->addHelpButton('idnumber', 'competencyframeworkidnumber', 'totara_hierarchy');
        $mform->setType('idnumber', PARAM_TEXT);

        $mform->addElement('editor', 'description_editor', get_string('competencyframeworkdescription', 'totara_hierarchy'), null, $TEXTAREA_OPTIONS);
        $mform->addHelpButton('description_editor', 'competencyframeworkdescription', 'totara_hierarchy');
        $mform->setType('description_editor', PARAM_CLEANHTML);

        $mform->addElement('select', 'scale', get_string('scale'), $scales);
        $mform->addHelpButton('scale', 'competencyframeworkscale', 'totara_hierarchy');
        $mform->addRule('scale', get_string('missingscale', 'totara_hierarchy'), 'required', null, 'client');

        // Don't allow reassigning the scale, if the framework has at least one competency
        if (isset($this->_customdata['frameworkid']) && $DB->count_records('comp', array('frameworkid' => $this->_customdata['frameworkid']))) {
            $mform->getElement('scale')->freeze();
        }

        $this->add_action_buttons();
    }

    function validation($data, $files) {
        $errors = array();
        $data = (object)$data;

        if (!empty($data->idnumber) && totara_idnumber_exists('comp_framework', $data->idnumber, $data->id)) {
            $errors['idnumber'] = get_string('idnumberexists', 'totara_core');
        }

        return $errors;
    }
}
