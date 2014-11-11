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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @author Russell England <russell.england@totaralms.com>
 * @package totara
 * @subpackage plan
 */

/**
 * The form for editing evidence
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    //  It must be included from a Moodle page
}

require_once("{$CFG->libdir}/formslib.php");
require_once("{$CFG->libdir}/uploadlib.php");

class plan_evidence_edit_form extends moodleform {

    /**
     * Requires the following $_customdata to be passed in to the constructor:
     * plan, evidence, evidenceid (optional)
     *
     * @global object $CFG
     * @global object $DB
     */
    function definition() {
        global $DB, $TEXTAREA_OPTIONS, $FILEPICKER_OPTIONS;

        $mform =& $this->_form;

        // Determine permissions from evidence
        $evidenceid = isset($this->_customdata['evidenceid']) ? $this->_customdata['evidenceid'] : 0;
        $userid = isset($this->_customdata['userid']) ? $this->_customdata['userid'] : 0;
        $evidencetypeid = isset($this->_customdata['evidencetypeid']) ? $this->_customdata['evidencetypeid'] : null;
        $fileoptions = isset($this->_customdata['fileoptions']) ? $this->_customdata['fileoptions'] : $FILEPICKER_OPTIONS;

        $mform->addElement('hidden', 'id', $evidenceid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'userid', $userid);
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('evidencename', 'totara_plan'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('err_required', 'form'), 'required', '', 'client', false, false);

        $mform->addElement('editor', 'description_editor', get_string('description'), null, $TEXTAREA_OPTIONS);
        $mform->setType('description_editor', PARAM_RAW);

        // Evidence type
        $selectoptions = $DB->get_records_select_menu('dp_evidence_type', null, null, 'sortorder', 'id, name');
        if ($selectoptions) {
            $selector = array(0 => get_string('selectanevidencetype', 'totara_plan'));
            $mform->addElement('select', 'evidencetypeid', get_string('evidencetype', 'totara_plan'), $selector + $selectoptions);
            $mform->setDefault('evidencetypeid', $evidencetypeid);
            $mform->setType('evidencetypeid', PARAM_INT);
        } else {
            // if evidencetypeid set but no evidence types defined, this should pass the current value
            $mform->addElement('hidden', 'evidencetypeid', $evidencetypeid);
            $mform->setType('evidencetypeid', PARAM_INT);
            $mform->addElement('static', 'evidencetypeiderror',
                    get_string('evidencetype', 'totara_plan'), get_string('noevidencetypesdefined', 'totara_plan'));
        }

        $mform->addElement('text', 'evidencelink', get_string('evidencelink', 'totara_plan'));
        $mform->setType('evidencelink', PARAM_URL);

        $mform->addElement('text', 'institution', get_string('evidenceinstitution', 'totara_plan'));
        $mform->setType('institution', PARAM_TEXT);

        $mform->addElement('date_selector', 'datecompleted',
                get_string('evidencedatecompleted', 'totara_plan'), array('optional' => true));

        $mform->addElement('filemanager', 'attachment_filemanager',
                get_string('attachment', 'totara_plan'), null, $fileoptions);

        $this->add_action_buttons(true, empty($this->_customdata['id']) ?
                get_string('addevidence', 'totara_plan') : get_string('updateevidence', 'totara_plan'));
    }

    /**
     * Check the unlisted URL is a URL
     */
    function validation($data, $files) {

        $errors = parent::validation($data, $files);

        $evidencelink = $this->_form->_submitValues['evidencelink'];

        if (!empty($evidencelink)) {
            $evidencelinktotest = clean_param($evidencelink, PARAM_URL);
            if (empty($evidencelinktotest)) {
                $errors['evidencelink'] = get_string('evidencelinkerror', 'totara_plan');
            }
        }

        return $errors;
    }
}
