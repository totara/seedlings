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
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package totara
 * @subpackage program
 */

require_once("{$CFG->libdir}/formslib.php");

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

// Define a form class to edit the program messages
class program_messages_edit_form extends moodleform {

    public $template_html = '';
    public $template_values = array();
    public $interpolated_html = '';

    protected $renderer;

    function definition() {
        global $CFG, $USER;

        $mform =& $this->_form;

        $program = $this->_customdata['program'];
        $messagesmanager = $program->get_messagesmanager();
        $messages = $messagesmanager->get_messages();

/// form definition
//--------------------------------------------------------------------------------

        // the form definition is passed off to the program messages manager at this point
        // so that the form template can be rendered at the same time as the form
        // is defined. This allows the form to be displayed in a non-standard
        // layout
        $this->template_html = $messagesmanager->get_message_form_template($mform, $this->template_values, $messages);

    }

    /**
     * Print html form.
     */
    function display() {

        //finalize the form definition if not yet done
        if (!$this->_definition_finalized) {
            $this->_definition_finalized = true;
            $this->definition_after_data();
        }

        // Add error messages to the form before rendering it
        $this->add_errors();

        // Substitute the place holder strings with the real form values in the form template
        $this->interpolate();

        $this->_form->getValidationScript();

        // display the html
        echo $this->renderer->toHtml($this->interpolated_html);
    }

    /**
     * Replaces the place holders in the generated temple with the actual form
     * fields and their values
     */
    function interpolate() {

        $mform = $this->_form;

        // Define the renderer that the form will use to display itself
        $this->renderer = new HTML_QuickForm_Renderer_QuickHtml();

        // Do the magic of creating the form.  NOTE: order is important here: this must
        // be called after creating the form elements, but before rendering them.
        $mform->accept($this->renderer);

        $this->interpolated_html = $this->template_html;

        $template_values = $this->template_values;

        foreach ($template_values as $replacestr => $namevaluepair) {
            $elementname = $namevaluepair['name'];
            $elementvalue = $namevaluepair['value'];
            $this->interpolated_html = str_replace($replacestr, $this->renderer->elementToHtml($elementname, $elementvalue), $this->interpolated_html);
        }

    }

    /**
     * Adds the list of errors to the template so that they are displayed to
     * the user
     */
    function add_errors() {

        $mform = $this->_form;
        $html = '';

        if (isset($mform->_errors) && !empty($mform->_errors)) {

            $mform->addElement('static', 'errors');
            $mform->setConstant('errors', get_string('errorsinform', 'totara_program'));
            $this->template_values['%errors%'] = array('name' => 'errors', 'value' => null);
            $html .= html_writer::start_tag('div', array('class' => 'error')) . '%errors%' . html_writer::end_tag('div');

            $html .= html_writer::start_tag('ul', array('id' => 'errors'));
            foreach ($mform->_errors as $error_element => $error_message) {
                $mform->addElement('static', $error_element.'_error');
                $mform->setConstant($error_element.'_error', $error_message);
                $this->template_values['%'.$error_element.'_error%'] = array('name' => $error_element.'_error', 'value' => null);
                $html .= html_writer::start_tag('li', array('class' => 'error')) .'%'.$error_element.'_error%' . html_writer::end_tag('li');
            }
            $html .= html_writer::end_tag('ul');

            $this->template_html = $html.$this->template_html;
        }
    }

    /**
     * Carries out validation of submitted form values
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    function validation($data, $files) {

        $mform = $this->_form;
        $errors = array();

        foreach ($data as $elementname => $elementvalue) {

            // check for time allowance issues
            if (preg_match('/[0-9]messagesubject/', $elementname)) {
                $messagesubject = $elementvalue;
                if ($messagesubject == '') {
                    $errors[$elementname] = get_string('error:messagesubject_empty', 'totara_program');
                }
            }

            // check for course sets with no courses
            if (preg_match('/[0-9]mainmessage/', $elementname)) {
                $mainmessage = $elementvalue;
                if (empty($mainmessage)) {
                    $errors[$elementname] = get_string('error:mainmessage_empty', 'totara_program');
                }
            }
        }

        return $errors;
    }
}
