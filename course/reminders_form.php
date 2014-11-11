<?php

require_once($CFG->libdir.'/formslib.php');

class reminder_edit_form extends moodleform {

    function definition() {
        global $USER, $CFG;

        $mform    =& $this->_form;

        $course   = $this->_customdata['course'];
        $reminder = $this->_customdata['reminder'];

        $mform->addElement('hidden', 'id', null);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'courseid', null);
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', $course->id);

        // Get activities with completion enabled
        $completion = new completion_info($course);
        $activities = $completion->get_activities();

        $choices = array();
        $choices[0] = get_string('coursecompletion');
        foreach ($activities as $a) {
            $choices[$a->id] = get_string('modulename', $a->modname).' - '.$a->name;
        }

        // Get feedback activities in the course
        $mods = get_coursemodules_in_course('feedback', $course->id);
        $rchoices = array('' => get_string('select').'...');
        if ($mods) {
            foreach ($mods as $mod) {
                $rchoices[$mod->id] = $mod->name;
            }
        }

/// form definition
//--------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('reminder', 'totara_coursecatalog'));

        $mform->addElement('text', 'title', get_string('title', 'totara_coursecatalog'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addHelpButton('title', 'title', 'totara_coursecatalog');

        $mform->addRule('title', get_string('missingtitle', 'totara_coursecatalog'), 'required', null, 'client');

        $mform->addElement('select', 'tracking', get_string('completiontotrack', 'totara_coursecatalog'), $choices);
        $mform->addHelpButton('tracking', 'tracking', 'totara_coursecatalog');
        $mform->addRule('tracking', get_string('missingfullname'), 'required', null, 'client');
        $mform->setType('tracking', PARAM_INT);

        $mform->addElement('select', 'requirement', get_string('requirement', 'totara_coursecatalog'), $rchoices);
        $mform->addHelpButton('requirement', 'requirement', 'totara_coursecatalog');
        $mform->addRule('requirement', get_string('required'), 'required', null, 'client');
        $mform->setType('requirement', PARAM_INT);

//--------------------------------------------------------------------------------
        $mform->addElement('header', 'invitation', get_string('invitation', 'totara_coursecatalog'));

        $options = range(2, 30);
        array_unshift($options, get_string('nextday', 'totara_coursecatalog'));
        array_unshift($options, get_string('sameday', 'totara_coursecatalog'));
        $mform->addElement('select', 'invitationperiod', get_string('period', 'totara_coursecatalog'), $options);
        $mform->setType('invitationperiod', PARAM_INT);
        $mform->addHelpButton('invitationperiod', 'invitationperiod', 'totara_coursecatalog');
        $mform->setDefault('invitationperiod', 0);

        $mform->addElement('text', 'invitationsubject', get_string('subject', 'totara_coursecatalog'), 'maxlength="254" size="80"');
        $mform->addHelpButton('invitationsubject', 'invitationsubject', 'totara_coursecatalog');
        $mform->setDefault('invitationsubject', get_string('invitationsubjectdefault', 'totara_coursecatalog'));
        $mform->setType('invitationsubject', PARAM_MULTILANG);

        $mform->addElement('textarea', 'invitationmessage', get_string('message', 'totara_coursecatalog'), 'rows="15" cols="70"');
        $mform->addHelpButton('invitationmessage', 'invitationmessage', 'totara_coursecatalog');
        $mform->setDefault('invitationmessage', get_string('invitationmessagedefault', 'totara_coursecatalog'));
        $mform->setType('invitationmessage', PARAM_MULTILANG);

//--------------------------------------------------------------------------------
        $mform->addElement('header', 'reminder', get_string('reminder', 'totara_coursecatalog'));

        $mform->addElement('select', 'reminderperiod', get_string('period', 'totara_coursecatalog'), $options);
        $mform->setType('reminderperiod', PARAM_INT);
        $mform->addHelpButton('reminderperiod', 'reminderperiod', 'totara_coursecatalog');
        $mform->setDefault('reminderperiod', 1);

        $mform->addElement('text', 'remindersubject', get_string('subject', 'totara_coursecatalog'), 'maxlength="254" size="80"');
        $mform->addHelpButton('remindersubject', 'remindersubject', 'totara_coursecatalog');
        $mform->setDefault('remindersubject', get_string('remindersubjectdefault', 'totara_coursecatalog'));
        $mform->setType('remindersubject', PARAM_MULTILANG);

        $mform->addElement('textarea', 'remindermessage', get_string('message', 'totara_coursecatalog'), 'rows="15" cols="70"');
        $mform->addHelpButton('remindermessage', 'remindermessage', 'totara_coursecatalog');
        $mform->setDefault('remindermessage', get_string('remindermessagedefault', 'totara_coursecatalog'));
        $mform->setType('remindermessage', PARAM_MULTILANG);

//--------------------------------------------------------------------------------
        $mform->addElement('header', 'escalation', get_string('escalation', 'totara_coursecatalog'));

        $mform->addElement('checkbox', 'escalationdontsend', get_string('dontsend', 'totara_coursecatalog'));
        $mform->setType('escalationdontsend', PARAM_INT);
        $mform->setDefault('escalationdontsend', 0);

        $mform->addElement('checkbox', 'escalationskipmanager', get_string('skipmanager', 'totara_coursecatalog'));
        $mform->setType('escalationskipmanager', PARAM_INT);
        $mform->setDefault('escalationskipmanager', 0);
        $mform->disabledIf('escalationskipmanager', 'escalationdontsend', 'checked');

        $mform->addElement('select', 'escalationperiod', get_string('period', 'totara_coursecatalog'), $options);
        $mform->setType('escalationperiod', PARAM_INT);
        $mform->addHelpButton('escalationperiod', 'reminderperiod', 'totara_coursecatalog');
        $mform->setDefault('escalationperiod', 1);
        $mform->disabledIf('escalationperiod', 'escalationdontsend', 'checked');

        $mform->addElement('text', 'escalationsubject', get_string('subject', 'totara_coursecatalog'), 'maxlength="254" size="80"');
        $mform->addHelpButton('escalationsubject', 'invitationsubject', 'totara_coursecatalog');
        $mform->setDefault('escalationsubject', get_string('escalationsubjectdefault', 'totara_coursecatalog'));
        $mform->setType('escalationsubject', PARAM_MULTILANG);
        $mform->disabledIf('escalationsubject', 'escalationdontsend', 'checked');

        $mform->addElement('textarea', 'escalationmessage', get_string('message', 'totara_coursecatalog'), 'rows="15" cols="70"');
        $mform->addHelpButton('escalationmessage', 'remindermessage', 'totara_coursecatalog');
        $mform->setDefault('escalationmessage', get_string('escalationmessagedefault', 'totara_coursecatalog'));
        $mform->setType('escalationmessage', PARAM_MULTILANG);
        $mform->disabledIf('escalationmessage', 'escalationdontsend', 'checked');

//--------------------------------------------------------------------------------
        $this->add_action_buttons();

//--------------------------------------------------------------------------------
    }

    function definition_after_data() {

        $mform    =& $this->_form;

        if (!$mform->getElementValue('id')) {
            $mform->setDefault('id', -1);
        }
    }
}
