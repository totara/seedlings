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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once $CFG->libdir.'/formslib.php';

class edit_priority_form extends moodleform {
    function definition() {
        global $OUTPUT, $TEXTAREA_OPTIONS;
        $mform =& $this->_form;
        // visible elements
        $mform->addElement('header', 'general', get_string('priority', 'totara_plan'));

        $mform->addElement('text', 'name', get_string('name'), 'size="40" maxlength="230"');
        $mform->addHelpButton('name', 'priorityscalename', 'totara_plan');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        // If it's a new priority, give them the option to define priority values.
        if ($this->_customdata['priorityid'] == 0) {
            $mform->addElement('static', 'priorityvaluesexplain', '', get_string('explainpriorityscalevals', 'totara_plan'));
            $mform->addElement('textarea', 'priorityvalues', get_string('priorityvalues', 'totara_plan'), 'rows="5" cols="30"');
            $mform->addRule('priorityvalues', get_string('required'), 'required', null, 'server');
            $mform->addHelpButton('priorityvalues', 'priorityscalevalues', 'totara_plan');
            $mform->setType('priorityvalues', PARAM_TEXT);
        } else {
            $linkurl = new moodle_url('view.php', array('id' => clean_param($this->_customdata['priorityid'], PARAM_INT)));
            $link = html_writer::link($linkurl, get_string('linktopriorityvalues', 'totara_plan'));
            $mform->addElement('html', $OUTPUT->container(
                $OUTPUT->container('&nbsp;', "fitemtitle") .
                $OUTPUT->container($link, "felement"),
                "fitem") . html_writer::empty_tag('br'));
        }
        $mform->addElement('editor', 'description_editor', get_string('description'), null, $TEXTAREA_OPTIONS);
        $mform->setType('description_editor', PARAM_CLEANHTML);

        // hidden params
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

//-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }


    /**
     * Carries out validation of submitted form values
     *
     * @param array $valuenew array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    function validation($valuenew, $files) {
        $err = array();
        $valuenew = (object) $valuenew;

        // make sure at least one priority scale value is defined
        if (isset($valuenew->priorityvalues) && trim($valuenew->priorityvalues) == '') {
            $err['priorityvalues'] = get_string('required');
        }

        if (count($err) > 0) {
            return $err;
        }

        return true;
    }

}
