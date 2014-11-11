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
require_once($CFG->dirroot . '/totara/completionimport/lib.php');
require_once($CFG->libdir . '/csvlib.class.php');

class upload_form extends moodleform {
    public function definition() {
        global $DB;
        $mform =& $this->_form;

        $data = $this->_customdata;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'filesource');
        $mform->setType('filesource', PARAM_INT);

        if ($data->filesource == TCI_SOURCE_EXTERNAL) {
            $mform->addElement('text', 'sourcefile', get_string('sourcefile', 'totara_completionimport'));
            $mform->setType('sourcefile', PARAM_TEXT);
            $mform->addHelpButton('sourcefile', 'sourcefile', 'totara_completionimport');
            $mform->addRule('sourcefile', get_string('sourcefilerequired', 'totara_completionimport'), 'required');
        } else if ($data->filesource == TCI_SOURCE_UPLOAD) {
            $mform->addElement('filepicker',
                    'uploadfile',
                    get_string('choosefile', 'totara_completionimport'),
                    null,
                    array('accepted_types' => array('csv')));
            $mform->addRule('uploadfile', get_string('uploadfilerequired', 'totara_completionimport'), 'required');
        }

        // Evidence type.
        $options = $DB->get_records_select_menu('dp_evidence_type', null, null, 'sortorder', 'id, name');
        $selector = array(0 => get_string('selectanevidencetype', 'totara_plan'));
        $selectoptions = $selector + $options;
        $mform->addElement('select', 'evidencetype', get_string('evidencetype', 'totara_completionimport'), $selectoptions);
        $mform->setType('evidencetype', PARAM_INT);
        $mform->addHelpButton('evidencetype', 'evidencetype', 'totara_completionimport');

        $dateformats = get_dateformats();
        $mform->addElement('select', 'csvdateformat', get_string('csvdateformat', 'totara_completionimport'), $dateformats);
        $mform->setType('csvdateformat', PARAM_TEXT);

        // Function get_delimiter_list() actually returns the list of separators as in "comma *separated* values".
        $separators = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'csvseparator', get_string('csvseparator', 'totara_completionimport'), $separators);
        $mform->setType('csvseparator', PARAM_TEXT);
        if (array_key_exists('cfg', $separators)) {
            $mform->setDefault('csvseparator', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('csvseparator', 'semicolon');
        } else {
            $mform->setDefault('csvseparator', 'comma');
        }

        $delimiters = array('"' => '"', "'" => "'", '' => 'none');
        $mform->addElement('select', 'csvdelimiter', get_string('csvdelimiter', 'totara_completionimport'), $delimiters);
        $mform->setType('csvdelimiter', PARAM_TEXT);

        $encodings = textlib::get_encodings();
        $mform->addElement('select', 'csvencoding', get_string('csvencoding', 'totara_completionimport'), $encodings);
        $mform->setType('csvencoding', PARAM_TEXT);
        $mform->setDefault('csvencoding', 'UTF-8');

        $overrideactivestr = get_string('overrideactive' . $data->importname, 'totara_completionimport');
        $mform->addElement('advcheckbox', 'overrideactive' . $data->importname, $overrideactivestr);

        $this->add_action_buttons(false, get_string('upload'));

        $this->set_data($data);
    }

    /**
     * Overriding this function to get unique form id so the form can be used more than once
     *
     * @return string form identifier
     */
    protected function get_form_identifier() {
        $formid = $this->_customdata->importname . '_' . get_class($this);
        return $formid;
    }
}
