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

require_once($CFG->libdir.'/formslib.php');

class edit_scale_form extends moodleform {
    public function definition() {
        global $TEXTAREA_OPTIONS;
        $mform =& $this->_form;
        // Visible elements.
        $mform->addElement('header', 'general', get_string('scale'));

        $mform->addElement('text', 'name', get_string('name'), 'size="40" maxlength="255"');
        $mform->addHelpButton('name', 'goalscalescalename', 'totara_hierarchy');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        // If it's a new scale, get them to define scale values.
        if ($this->_customdata['scaleid'] == 0) {
            $mform->addElement('textarea', 'scalevalues', get_string('scalevalues', 'totara_hierarchy')
                . html_writer::empty_tag('br')
                . html_writer::start_tag('i')
                . '(' . get_string('goalnotescalevalueentry', 'totara_hierarchy'). ')'
                . html_writer::end_tag('i'), 'rows="5" cols="30"');
            $mform->addHelpButton('scalevalues', 'goalscalescalevalues', 'totara_hierarchy');
            $mform->addRule('scalevalues', get_string('required'), 'required', null, 'server');
            $mform->setType('scalevalues', PARAM_TEXT);
        } else {
            $linkurl = new moodle_url('view.php', array('id' => clean_param($this->_customdata['scaleid'], PARAM_INT), 'prefix' => 'goal'));
            $link = html_writer::link($linkurl, get_string('linktoscalevaluesgoal', 'totara_hierarchy'));
            $html = html_writer::start_tag('div', array('class' => 'fitem'));
            $html .= html_writer::tag('div', '&nbsp;', array('class' => 'fitemtitle'));
            $html .= html_writer::tag('div', $link, array('class' => 'felement'));
            $html .= html_writer::end_tag('div');
            $mform->addElement('html', $html);
        }

        $mform->addElement('editor', 'description_editor', get_string('description'), null, $TEXTAREA_OPTIONS);
        $mform->setType('description_editor', PARAM_CLEANHTML);
        // Hidden params.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'prefix', 'goal');
        $mform->setType('prefix', PARAM_ALPHA);

        // Buttons.
        $this->add_action_buttons();
    }

    /**
     * Carries out validation of submitted form values.
     *
     * @param array $valuenew array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($valuenew, $files) {
        $err = array();
        $valuenew = (object) $valuenew;

        // Make sure at least one scale value is defined.
        if (isset($valuenew->scalevalues) && trim($valuenew->scalevalues) == '') {
            $err['scalevalues'] = get_string('required');
        }

        if (count($err) > 0) {
            return $err;
        }

        return true;
    }
}
