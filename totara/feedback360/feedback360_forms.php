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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_feedback360
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/totara/question/libforms.php');

/**
 * Formslib template for the edit feedback360 form
 */
class feedback360_edit_form extends moodleform {

    public function definition() {
        global $TEXTAREA_OPTIONS;

        $mform =& $this->_form;
        $feedback360 = $this->_customdata['feedback360'];
        $readonly = $this->_customdata['readonly'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        if ($readonly) {
            $mform->addElement('static', 'name', get_string('name', 'totara_feedback360'));
            $mform->addElement('static', 'description_ro', get_string('description'), $feedback360->description_editor['text']);
        } else {
            $mform->addElement('text', 'name', get_string('name', 'totara_feedback360'), 'maxlength="255" size="50"');
            $mform->addRule('name', null, 'required');
            $mform->addHelpButton('name', 'name', 'totara_feedback360');

            $mform->addElement('editor', 'description_editor', get_string('description'), null, $TEXTAREA_OPTIONS);
            $mform->addHelpButton('description_editor', 'description', 'totara_feedback360');
            $mform->setType('description_editor', PARAM_CLEANHTML);

            $submittitle = get_string('createfeedback360', 'totara_feedback360');
            if ($feedback360->id > 0) {
                $submittitle = get_string('savechanges', 'moodle');
            }
            $this->add_action_buttons(true, $submittitle);
        }
        $mform->setType('name', PARAM_TEXT);

        $this->set_data($feedback360);
    }
}

/**
 * Edit allowed feedback360 recipients
 */
class feedback360_recipients_form extends moodleform {

    /**
     * Recipient bitmas to fields map
     * @var type
     */
    protected $bitdata = array();

    public function definition() {
        $this->bitdata = array('email' => feedback360::RECIPIENT_EMAIL, 'anyuser' => feedback360::RECIPIENT_ANYUSER,
            'linemanager' => feedback360::RECIPIENT_LM, 'directreport' => feedback360::RECIPIENT_MANAGER,
            'audiences' => feedback360::RECIPIENT_COHORT, 'samepos' => feedback360::RECIPIENT_POSITION,
            'sameorg' => feedback360::RECIPIENT_ORGANISATION);

        $mform =& $this->_form;
        $readonly = $this->_customdata['readonly'];
        $id = $this->_customdata['id'];

        $mform->addElement('hidden', 'id')->setValue($id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('advcheckbox', 'email', '', get_string('recipient:email', 'totara_feedback360'));
        $mform->addElement('advcheckbox', 'anyuser', '', get_string('recipient:anyuser', 'totara_feedback360'));
        $mform->addElement('advcheckbox', 'linemanager', '', get_string('recipient:linemanager', 'totara_feedback360'));
        $mform->addElement('advcheckbox', 'directreport', '', get_string('recipient:directreports', 'totara_feedback360'));
        $mform->addElement('advcheckbox', 'audiences', '', get_string('recipient:audiencies', 'totara_feedback360'));
        $mform->addElement('advcheckbox', 'samepos', '', get_string('recipient:samepos', 'totara_feedback360'));
        $mform->addElement('advcheckbox', 'sameorg', '', get_string('recipient:sameorg', 'totara_feedback360'));

        $this->add_action_buttons(true, get_string('savechanges', 'moodle'));
        if ($readonly) {
            $mform->freeze();
        }
    }

    /**
     * Take recipients bitmask field and set checkbox state accordingly
     *
     * @param stdClass $data
     */
    public function set_data($data) {
        parent::set_data($data);
        if (isset($data->recipients)) {
            foreach ($this->bitdata as $elem => $bit) {
                $this->_form->getElement($elem)->setChecked(($data->recipients & $bit) == $bit);
            }
        }
    }

    /**
     * Export recipients bitmask with other data
     *
     * @return stdClass
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $this->recipients = 0;
            foreach ($this->bitdata as $elem => $bit) {
                $data->recipients |= $this->_form->getElement($elem)->getValue() * $bit;
            }
        }
        return $data;
    }
}

/**
 * View form page
 */
class feedback360_answer_form extends moodleform {

    public function definition() {
        /* We do the work in the definition_after_data function because the definition function is called every
         * time a form is constructed, and we don't want to add elements to the form until we are sure that
         * this is the form that will be displayed, and is not just being used to retrieve submitted data.
         */
    }

    public function definition_after_data() {
        $mform = & $this->_form;

        $feedback360 = $this->_customdata['feedback360'];
        $preview = $this->_customdata['preview'];
        $resp = $this->_customdata['resp'];
        $backurl = isset($this->_customdata['backurl']) ? $this->_customdata['backurl'] : null;
        $readonly = $resp->is_completed();

        $questions = feedback360_question::get_list($feedback360->id);
        foreach ($questions as $quest) {
            $feedback_question = new feedback360_question($quest->id, $resp);
            $elem = $feedback_question->get_element();
            if ($readonly || !$elem->is_answerable()) {
                $elem->set_viewonly(true);
            } else if ($quest->required) {
                $elem->set_required(true);
            }
            $elem->label = $quest->name;
            $elem->add_field_form_elements($mform);
        }
        if (!$readonly && !$preview) {
            if ($resp->is_user()) {
                $mform->addElement('hidden', 'userid')->setValue($resp->subjectid);
                $mform->addElement('hidden', 'feedback360id')->setValue($resp->feedback360id);
            } else {
                $mform->addElement('hidden', 'email')->setValue($resp->email);
                $mform->addElement('hidden', 'token')->setValue($resp->token);
            }
            $mform->addElement('hidden', 'action')->setValue('submit');
            $this->add_action_buttons(false, get_string('submitfeedback', 'totara_feedback360'));
        } else if ($readonly && !isguestuser()) {
            if (!$backurl) {
                $backurl = new moodle_url('/totara/feedback360/index.php');
            }
            $mform->addElement('static', 'backlink', '', html_writer::link($backurl, get_string('back')));
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $feedback360 = $this->_customdata['feedback360'];
        $preview = $this->_customdata['preview'];
        $resp = $this->_customdata['resp'];
        $readonly = $resp->is_completed();

        if ($preview || $readonly) {
            return array('submitbutton' => get_string('error:readonly', 'totara_feedback360'));
        }

        $questions = feedback360_question::get_list($feedback360->id);
        foreach ($questions as $quest) {
            $feedback_question = new feedback360_question($quest->id, $resp);
            $newerrors = $feedback_question->get_element()->edit_validate($data);
            if (!empty($newerrors)) {
                $errors = array_merge($errors, $newerrors);
            }
        }

        return $errors;
    }
}

/**
 * Formslib template for the edit feedback360 choose element
 */
class feedback360_add_quest_form extends question_choose_element_form {
    public function definition() {
        $this->prefix = 'feedback360';
        $feedback360id = $this->_customdata['feedback360id'];

        $mform =& $this->_form;
        $mform->disable_form_change_checker();
        $mform->addElement('hidden', 'action')->setValue('edit');
        $mform->setType('action', PARAM_ACTION);
        $mform->addElement('hidden', 'feedback360id')->setValue($feedback360id);
        $mform->setType('feedback360id', PARAM_INT);
        $mform->addElement('hidden', 'questionid')->setValue(0);
        $mform->setType('questionid', PARAM_INT);
        parent::definition(array(question_manager::GROUP_REVIEW), array('redisplay'));
    }
}

/**
 * Manage form elements definition
 *
 */
class feedback360_quest_edit_form extends question_base_form {
    public function definition() {
        $mform =& $this->_form;
        $mform->disable_form_change_checker();
        $feedback360id = $this->_customdata['feedback360id'];
        $question = $this->_customdata['question'];
        $readonly = $this->_customdata['readonly'];

        $element = $question->get_element();
        $mform->addElement('header', 'questionheader', get_string('questionmanage', 'totara_question'));
        $mform->addElement('hidden', 'action')->setValue('edit');
        $mform->setType('action', PARAM_ACTION);
        $mform->addElement('hidden', 'id')->setValue($question->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'feedback360id')->setValue($feedback360id);
        $mform->setType('feedback360id', PARAM_INT);
        $mform->addElement('hidden', 'datatype');
        $mform->setType('datatype', PARAM_ACTION);
        $mform->getElement('datatype')->setValue($element->get_type());

        $element->add_settings_form_elements($mform, $readonly);
        if ($question->get_element()->is_answerable()) {
            $mform->addElement('advcheckbox', 'required', '', get_string('required', 'totara_question'));
        }

        if ($readonly) {
            $mform->freeze();
            $mform->addElement('static', 'backlink', '', html_writer::link(new moodle_url('/totara/feedback360/content.php',
                    array('feedback360id' => $feedback360id)), get_string('back')));
        } else {
            $this->add_action_buttons();
        }
    }

    /**
     * Change question header
     *
     * @param string $header
     */
    public function set_header($header) {
        $mform =& $this->_form;
        $mform->getElement('questionheader')->setValue($header);
    }

    public function validation($data, $files) {
        $question = $this->_customdata['question'];

        $element = $question->get_element();
        $err = $element->define_validate_all($data, $files);

        return $err;
    }

}

// The form used to select which premade form to use for a feedback request.
class request_select_form extends moodleform {

    public function definition() {
        $mform =& $this->_form;

        // Header - select form.
        $mform->addElement('header', 'newrequestform', get_string('newrequest', 'totara_feedback360'));

        $mform->addElement('hidden', 'userid', 0);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'form');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'popupurl', '');
        $mform->setType('popupurl', PARAM_TEXT);
    }

    public function set_data($data) {
        global $CFG;
        $mform =& $this->_form;

        $mform->getElement('userid')->setValue($data['userid']);

        $popupurl = $CFG->wwwroot . "/totara/feedback360/feedback.php?userid={$data['userid']}&preview=1&feedback360id=";
        $mform->getElement('popupurl')->setValue($popupurl);

        parent::set_data($data);
    }

    public function definition_after_data() {
        global $DB, $USER;
        $mform =& $this->_form;

        $userid = $mform->getElementValue('userid');
        $available_forms = feedback360::get_available_forms($userid);

        // List of forms.
        $requestforms = array();
        foreach ($available_forms as $form) {
            // If it has existing requests it should be edited from the myfeedback page instead.
            $existingrequests = $DB->count_records('feedback360_resp_assignment',
                    array('feedback360userassignmentid' => $form->assigid));
            if ($existingrequests > 0) {
                continue;
            }

            $preview_params = array('userid' => $USER->id, 'feedback360id' => $form->id, 'preview' => true);
            $preview_url = new moodle_url('/totara/feedback360/feedback.php', $preview_params);
            $preview_link = html_writer::link($preview_url, get_string('previewencased', 'totara_feedback360'),
                    array('class' => 'previewlink', 'id' => $form->id));

            $radiostr = format_string($form->name) . ' ' . $preview_link;
            $requestforms[] =& $mform->createElement('radio', 'formid', '', $radiostr, $form->assigid);
            if (!empty($form->description)) {
                $requestforms[] =& $mform->createElement('static', 'description', null, $form->description);
            } else {
                $requestforms[] =& $mform->createElement('static', 'none_break', null, '<br/>');
            }
            $requestforms[] =& $mform->createElement('static', 'none_break', null, '<br/>');
        }

        if (!empty($requestforms)) {
            $mform->addGroup($requestforms, 'formselector',
                    get_string('feedback360selectform', 'totara_feedback360'), array(' '), false);
            $mform->addHelpButton('formselector', 'feedback360selectform', 'totara_feedback360');

            $this->add_action_buttons(true, get_string('next', 'totara_feedback360'));
        } else {
            $mform->addElement('static', 'noavailableforms', null, get_string('noavailableforms', 'totara_feedback360'));
            $mform->addElement('cancel', 'cancelbutton', get_string('cancel'));
        }
    }

    public function validation($data, $files) {
        $errors = array();

        // Check form is defined.
        if (empty($data['formid'])) {
            $errors['formid'] = get_string('error:noformselected', 'totara_feedback360');
        }

        return $errors;
    }
}

class request_select_users extends moodleform {
    private $systemexisting = null;
    private $emailsexisting = null;

    public function definition() {
        global $CFG, $USER;

        $mform =& $this->_form;

        // Javascript include.
        local_js(array(
            TOTARA_JS_DATEPICKER,
        ));

        // Attach a date picker to date fields.
        build_datepicker_js(
            'input[name="duedateselector"]'
        );

        // Header - Manage users requested.
        $mform->addElement('header', 'manageuserrequests', get_string('manageuserrequests', 'totara_feedback360'));

        // Some hidden elements for the form.

        // The id of the user requesting feedback.
        $mform->addElement('hidden', 'userid', 0);
        $mform->setType('userid', PARAM_INT);
        // The action being preformed.
        $mform->addElement('hidden', 'action', 'users');
        $mform->setType('action', PARAM_ALPHA);
        // The form to create the requests for.
        $mform->addElement('hidden', 'formid', 0);
        $mform->setType('formid', PARAM_INT);
        // A list of currently unassigned system users to create requests for.
        $mform->addElement('hidden', 'systemnew', '');
        $mform->setType('systemnew', PARAM_SEQUENCE);
        // A list of currently assigned system users, used to un-assign.
        $mform->addElement('hidden', 'systemold', '');
        $mform->setType('systemold', PARAM_SEQUENCE);
        // A list of existing email assignments.
        $mform->addElement('hidden', 'emailold', '');
        $mform->setType('emailold', PARAM_TEXT);
        // A list of cancelled email assignments.
        $mform->addElement('hidden', 'emailcancel', '');
        $mform->setType('emailcancel', PARAM_TEXT);
        // A hidden datefield to complare the new vs old dates when editing.
        $mform->addElement('hidden', 'oldduedate', 0);
        $mform->setType('oldduedate', PARAM_INT);
        // A hidden field used by js to popup a preview window.
        $popupurl = $CFG->wwwroot . "/totara/feedback360/feedback.php?userid={$USER->id}&preview=1&feedback360id=";
        $mform->addElement('hidden', 'popupurl', $popupurl);
        $mform->setType('popupurl', PARAM_TEXT);

        $systemuserstr = html_writer::tag('strong', get_string('requestuserssystem', 'totara_feedback360'));
        $mform->addElement('static', 'requestuserssystem', $systemuserstr);

        // Create a place to show existing system requests.
        $mform->addElement('static', 'system_assignments', '', '');

        $mform->addElement('submit', 'addsystemusers', get_string('addsystemusers', 'totara_feedback360'),
                array('id' => 'show-systemrequest-dialog'));

        // Text area to add new emails.
        $mform->addElement('textarea', 'emailnew', get_string('emailrequestsnew', 'totara_feedback360'));
        $mform->addHelpButton('emailnew', 'emailrequestsnew', 'totara_feedback360');

        // Create a place to show existing external users.
        $mform->addElement('static', 'existing_external', '', '');

        // Target date.
        $mform->addElement('text', 'duedateselector', get_string('duedate', 'totara_feedback360'),
                 array('placeholder' => get_string('datepickerlongyearplaceholder', 'totara_core')));
        $mform->addHelpButton('duedateselector', 'duedate', 'totara_feedback360');
        $mform->setType('duedateselector', PARAM_MULTILANG);

    }

    public function set_data($data) {
        global $DB, $USER, $PAGE, $OUTPUT;

        $mform =& $this->_form;
        $renderer = $PAGE->get_renderer('totara_feedback360');

        if (!empty($data['userid'])) {
            $mform->getElement('userid')->setValue($data['userid']);
        } else {
            $mform->getElement('userid')->setValue($USER->id);
        }

        if (!empty($data['formid'])) {
            $mform->formid = $data['formid'];
            $userform = $data['formid'];
        } else {
            print_error('error:noformselected', 'totara_feedback360');
        }

        if (!empty($data['feedbackid']) && !empty($data['feedbackname'])) {
            $title = get_string('manageuserrequests', 'totara_feedback360');
            $name = $data['feedbackname'];
            $preview_params = array('userid' => $USER->id, 'feedback360id' => $data['feedbackid'], 'preview' => true);
            $preview_url = new moodle_url('/totara/feedback360/feedback.php', $preview_params);
            $preview_link = html_writer::link($preview_url, get_string('previewencased', 'totara_feedback360'),
                                              array('class' => 'previewlink', 'id' => $data['feedbackid']));
            $mform->getElement('manageuserrequests')->setValue($title . ': ' . $name . $preview_link);
        }

        if (!empty($data['systemexisting'])) {
            $this->systemexisting = $data['systemexisting'];

            $existing = array();
            $existingids = array();

            foreach ($this->systemexisting as $user) {
                $resp_params = array('userid' => $user->id, 'feedback360userassignmentid' => $userform);
                $resp = $DB->get_record('feedback360_resp_assignment', $resp_params);

                $existing[] = $renderer->system_user_record($user, $userform, $resp);
                $existingids[] = $user->id;
            }

            $existingval = html_writer::start_tag('div', array('id' => 'system_assignments', 'class' => 'replacement_box')) .
                    implode($existing, '') . html_writer::end_tag('div');

            $mform->getElement('system_assignments')->setValue($existingval);
            $mform->getElement('systemold')->setValue(implode($existingids, ','));
            $mform->getElement('systemnew')->setValue(implode($existingids, ','));
        } else {
            $emptydiv = html_writer::start_tag('div', array('id' => 'system_assignments', 'class' => 'replacement_box')) .
                    html_writer::end_tag('div');
            $mform->getElement('system_assignments')->setValue($emptydiv);
        }

        if (isset($data['nojs']) && $data['nojs']) {
            $existing = array();
            $nojs_users = isset($data['systemnew']) ? $data['systemnew'] : null;
            if (!empty($nojs_users)) {
                $this->nojs_users = explode(',', $nojs_users);
                foreach ($this->nojs_users as $u) {
                    $user = $DB->get_record('user', array('id' => $u));
                    $existing[] = $OUTPUT->container(fullname($user), 'user_record', "system_user_{$u}");
                }
                $existingval = html_writer::start_tag('div', array('id' => 'system_assignments', 'class' => 'replacement_box')) .
                        implode($existing, '') . html_writer::end_tag('div');

                $mform->getElement('system_assignments')->setValue($existingval);
            }
        }

        if (!empty($data['emailexisting'])) {
            $existing = array();
            foreach ($data['emailexisting'] as $email) {
                // Join the resp and email assignments together.
                $sql = "SELECT ra.*
                        FROM {feedback360_resp_assignment} ra
                        JOIN {feedback360_email_assignment} ea
                            ON ra.feedback360emailassignmentid = ea.id
                        WHERE ra.feedback360userassignmentid = ?
                        AND ea.email = ?";
                $resp = $DB->get_record_sql($sql, array($userform, $email));

                $existing[] = $renderer->external_user_record($email, $userform, $resp);
            }

            $mform->getElement('emailold')->setValue(implode($data['emailexisting'], ','));
            $mform->getElement('existing_external')->setValue(implode($existing, ''));
        }

        if (!empty($data['duedate'])) {
            $mform->getElement('oldduedate')->setValue($data['duedate']);
            $due = userdate($data['duedate'], get_string('datepickerlongyearphpuserdate', 'totara_core'));
            $mform->getElement('duedateselector')->setValue($due);
        }

        if (!empty($data['update'])) {
            $submitstr = get_string('update', 'totara_feedback360');
        } else {
            $submitstr = get_string('request', 'totara_feedback360');
        }

        // Add the action buttons.
        $this->add_action_buttons(true, $submitstr);

        parent::set_data($data);
    }

    public function validation($data, $files) {
        $errors = array();

        // Check form is defined.
        if (empty($data['formid']) || !is_numeric($data['formid']) || $data['formid'] < 1) {
            $errors['formid'] = get_string('error:noformselected', 'totara_feedback360');
        }

        // Trim extra whitespace/commas off the edges of the string.
        $data['emailnew'] = trim($data['emailnew']);
        $data['systemnew'] = trim(trim($data['systemnew']), ',');

        $emails = !empty($data['emailnew']) ? explode("\r\n", $data['emailnew']) : null;
        $system = explode(',', $data['systemnew']);

        // Check atleast one email/system.
        $count_emails = count($emails);
        $count_system = count($system);
        if ($count_emails + $count_system < 1) {
            $errors['systemnew'] = get_string('error:emptyuserrequests', 'totara_feedback360');
        }

        // Check email format.
        if (!empty($emails)) {
            $formaterror = array();
            foreach ($emails as $email) {
                if (!validate_email($email)) {
                    $formaterror[] = $email;
                }
            }

            // Check for duplicate emails.
            $duplicateerror = array();
            while (!empty($emails)) {
                $email = array_pop($emails);
                if (in_array($email, $emails)) {
                    $duplicateerror[] = $email;
                }
            }

            if (!empty($formaterror)) {
                $errors['emailnew'] = get_string('error:emailformat', 'totara_feedback360') . implode($formaterror, "- ");
            }

            if (!empty($duplicateerror)) {
                $errors['emailnew'] = get_string('error:emailduplicate', 'totara_feedback360') . implode($duplicateerror, "- ");
            }
        }

        // Validate the due date field.
        $dateparseformat = get_string('datepickerlongyearparseformat', 'totara_core');
        if (!empty($data['duedateselector'])) {
            // If they have set a due date check that it is in the future.
            $targetdate = $data['duedateselector'];

            if (empty($targetdate)) {
                // Carry on, the due date can be empty.
            } else if ($date = totara_date_parse_from_format($dateparseformat, $targetdate)) {
                if ($date < time()) {
                    $errors['duedateselector'] = get_string('error:duedatepast', 'totara_feedback360');
                }
                // If we are updating an existing request, check that the due date is the same or further in the future.
                if (!empty($data['oldduedate'])) {
                    $olddue = $data['oldduedate'];
                    if ($olddue > $date) {
                        $errors['duedateselector'] = get_string('error:newduedatebeforeold', 'totara_feedback360');
                    }
                }
            } else {
                // Due date is not in a parseable format.
                $errors['duedateselector'] = get_string('error:duedateformat', 'totara_feedback360');
            }
        }

        return $errors;
    }
}

class request_confirmation extends moodleform {
    public function definition() {
        $mform =& $this->_form;

        // The id of the user requesting feedback.
        $mform->addElement('hidden', 'userid', 0);
        $mform->setType('userid', PARAM_INT);
        // The action being preformed.
        $mform->addElement('hidden', 'action', 'confirm');
        $mform->setType('action', PARAM_ALPHA);
        // The date that the feedback shold be completed by as a timestamp.
        $mform->addElement('hidden', 'duedate', 0);
        $mform->setType('duedate', PARAM_INT);
        // The previous duedate to check against if it has changed.
        $mform->addElement('hidden', 'oldduedate', 0);
        $mform->setType('oldduedate', PARAM_INT);
        // A flag of wether to send due date notifications.
        $mform->addElement('hidden', 'duenotifications', false);
        $mform->setType('duenotifications', PARAM_BOOL);
        // The form to create the requests for.
        $mform->addElement('hidden', 'formid', 0);
        $mform->setType('formid', PARAM_INT);
        // A list of currently unassigned system users to create requests for.
        $mform->addElement('hidden', 'systemnew', '');
        $mform->setType('systemnew', PARAM_SEQUENCE);
        // A list of currently assigned system users to keep.
        $mform->addElement('hidden', 'systemkeep', '');
        $mform->setType('systemkeep', PARAM_SEQUENCE);
        // A list of currently assigned system users to un-assign.
        $mform->addElement('hidden', 'systemcancel', '');
        $mform->setType('systemcancel', PARAM_SEQUENCE);
        // A list of currently unasked emails to create requests for.
        $mform->addElement('hidden', 'emailnew', '');
        $mform->setType('emailnew', PARAM_TEXT);
        // A list of current assigned external users to keep.
        $mform->addElement('hidden', 'emailkeep', null);
        $mform->setType('emailkeep', PARAM_TEXT);
        // A list of currently assigned external users to cancel.
        $mform->addElement('hidden', 'emailcancel', null);
        $mform->setType('emailcancel', PARAM_TEXT);

        // Set up the header of the form.
        $mform->addElement('header', 'requestfeedback360', get_string('requestfeedback360', 'totara_feedback360'));

        // Set up the confirmation string.
        $strconfirm = get_string('requestfeedback360confirm', 'totara_feedback360');

        $mform->addElement('static', 'confirmation', '', $strconfirm);

        // Create a place to show new feedback requests.
        $mform->addElement('static', 'show_new_requests', '', '');

        // Create a place to show cancelled requests.
        $mform->addElement('static', 'show_cancelled_requests', '', '');

        // Create a place to due date notifications.
        $mform->addElement('static', 'show_date_notifications', '', '');

        // And the confirm/cancel buttons.
        $this->add_action_buttons(true, get_string('confirm'));
    }

    public function set_data($data) {
        global $DB, $USER;

        $mform =& $this->_form;

        if (!empty($data['userid'])) {
            $mform->getElement('userid')->setValue($data['userid']);
        } else {
            $mform->getElement('userid')->setValue($USER->id);
        }

        if (!empty($data['formid'])) {
            $mform->formid = $data['formid'];
        } else {
            print_error('error:noformselected', 'totara_feedback360');
        }

        $newsystem = array();
        if (!empty($data['systemnew'])) {
            $newsystem = explode(',', $data['systemnew']);
            $mform->getElement('systemnew')->setValue($data['systemnew']);
        }

        $cancelsystem = array();
        if (!empty($data['systemcancel'])) {
            $cancelsystem = explode(',', $data['systemcancel']);
            $mform->getElement('systemcancel')->setValue($data['systemcancel']);
        }

        $keepsystem = array();
        if (!empty($data['systemkeep'])) {
            $keepsystem = explode(',', $data['systemkeep']);
            $mform->getElement('systemkeep')->setValue($data['systemkeep']);
        }

        // Include the list of all external emails.
        $newexternal = array();
        if (!empty($data['emailnew'])) {
            $newexternal = explode(',', $data['emailnew']);
            $mform->getElement('emailnew')->setValue($data['emailnew']);
        }

        // Show cancellations.
        $cancelexternal = array();
        if (!empty($data['emailcancel'])) {
            $cancelexternal = explode(',', $data['emailcancel']);
            $mform->getElement('emailcancel')->setValue($data['emailcancel']);
        }

        $keepexternal = array();
        if (!empty($data['emailkeep'])) {
            $keepexternal = explode(',', $data['emailkeep']);
            $mform->getElement('emailkeep')->setValue(implode(',', $keepexternal));
        }

        $oldduedate = 0;
        if (!empty($data['oldduedate'])) {
            $oldduedate = $data['oldduedate'];
            $mform->getElement('oldduedate')->setValue($oldduedate);
        }

        $newduedate = 0;
        if (!empty($data['newduedate'])) {
            $newduedate = $data['newduedate'];
            $mform->getElement('duedate')->setValue($newduedate);
        }

        $duenotifications = false;
        if (!empty($oldduedate) && $oldduedate < $newduedate) {
            $duenotifications = true;
            $mform->getElement('duenotifications')->setValue(true);
        }

        // Display all this on the screen.
        if (!empty($newsystem) || !empty($newexternal)) {
            $newsystemname = array();
            foreach ($newsystem as $userid) {
                $newsystemname[] = fullname($DB->get_record('user', array('id' => $userid)));
            }

            $newrequests = html_writer::start_tag('div', array('class' => 'new_requests'));
            $newrequests .= get_string('requestfeedback360create', 'totara_feedback360');
            $newrequests .= html_writer::start_tag('ul') . html_writer::empty_tag('li');
            $newrequests .= implode(html_writer::empty_tag('li'), array_merge($newsystemname, $newexternal));
            $newrequests .= html_writer::end_tag('ul');
            $newrequests .= html_writer::end_tag('div');
            $mform->getElement('show_new_requests')->setValue($newrequests);
        }

        if (!empty($cancelsystem) || !empty($cancelexternal)) {
            $cancelsystemname = array();
            foreach ($cancelsystem as $userid) {
                $cancelsystemname[] = fullname($DB->get_record('user', array('id' => $userid)));
            }

            $cancelledrequests = html_writer::start_tag('div', array('class' => 'cancelled_requests'));
            $cancelledrequests .= get_string('requestfeedback360delete', 'totara_feedback360');
            $cancelledrequests .= html_writer::start_tag('ul') . html_writer::empty_tag('li');
            $cancelledrequests .= implode(html_writer::empty_tag('li'), array_merge($cancelsystemname, $cancelexternal));
            $cancelledrequests .= html_writer::end_tag('ul');
            $cancelledrequests .= html_writer::end_tag('div');
            $mform->getElement('show_cancelled_requests')->setValue($cancelledrequests);
        }

        if ($duenotifications) {
            if (!empty($keepsystem) || !empty($keepexternal)) {
                $keepsystemname = array();
                foreach ($keepsystem as $userid) {
                    $keepsystemname[] = fullname($DB->get_record('user', array('id' => $userid)));
                }

                $keeprequests = html_writer::start_tag('div', array('class' => 'duedate_reminders'));
                $keeprequests .= get_string('requestfeedback360keep', 'totara_feedback360');
                $keeprequests .= html_writer::start_tag('ul') . html_writer::empty_tag('li');
                $keeprequests .= implode(html_writer::empty_tag('li'), array_merge($keepsystemname, $keepexternal));
                $keeprequests .= html_writer::end_tag('ul');
                $keeprequests .= html_writer::end_tag('div');
                $mform->getElement('show_date_notifications')->setValue($keeprequests);
            }
        }

        parent::set_data($data);
    }
}
