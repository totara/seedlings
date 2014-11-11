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
 * @package modules
 * @subpackage facetoface
 */

require_once "$CFG->dirroot/course/moodleform_mod.php";
require_once "$CFG->dirroot/mod/facetoface/lib.php";

class mod_facetoface_mod_form extends moodleform_mod {

    function definition()
    {
        global $CFG;

        $mform =& $this->_form;

        // GENERAL
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $this->add_intro_editor(true);

        if (empty($CFG->facetoface_notificationdisable)) {
            $mform->addElement('text', 'thirdparty', get_string('thirdpartyemailaddress', 'facetoface'), array('size' => '64'));
            $mform->setType('thirdparty', PARAM_NOTAGS);
            $mform->addHelpButton('thirdparty', 'thirdpartyemailaddress', 'facetoface');

            $mform->addElement('checkbox', 'thirdpartywaitlist', get_string('thirdpartywaitlist', 'facetoface'));
            $mform->addHelpButton('thirdpartywaitlist', 'thirdpartywaitlist', 'facetoface');
        } else {
            $mform->addElement('hidden', 'thirdparty', $this->_customdata['thirdparty']);
            $mform->addElement('hidden', 'thirdpartywaitlist', $this->_customdata['thirdpartywaitlist']);
        }
        $mform->setType('thirdparty', PARAM_NOTAGS);
        $mform->setType('thirdpartywaitlist', PARAM_INT);

        $display = array();
        for ($i=0; $i<=18; $i += 2) {
            $display[$i] = $i;
        }
        $mform->addElement('select', 'display', get_string('sessionsoncoursepage', 'facetoface'), $display);
        $mform->setDefault('display', 6);
        $mform->addHelpButton('display', 'sessionsoncoursepage', 'facetoface');

        $mform->addElement('checkbox', 'approvalreqd', get_string('approvalreqd', 'facetoface'));
        $mform->addHelpButton('approvalreqd', 'approvalreqd', 'facetoface');

        $mform->addElement('textarea', 'selfapprovaltandc', get_string('selfapprovaltandc', 'facetoface'), array('rows' => 7));
        $mform->setDefault('selfapprovaltandc', get_string('selfapprovaltandccontents', 'facetoface'));
        $mform->disabledIf('selfapprovaltandc', 'approvalreqd', 'eq', 0);
        $mform->addHelpButton('selfapprovaltandc', 'selfapprovaltandc', 'facetoface');

        if (has_capability('mod/facetoface:configurecancellation', $this->context)) {
            $mform->addElement('advcheckbox', 'allowcancellationsdefault', get_string('allowcancellationsdefault', 'facetoface'));
            $mform->setDefault('allowcancellationsdefault', 1);
            $mform->addHelpButton('allowcancellationsdefault', 'allowcancellationsdefault', 'facetoface');
        }

        $mform->addElement('advcheckbox', 'multiplesessions', get_string('multiplesessions', 'facetoface'), '',
                array('group' => 1), array(0, 1));
        $mform->setType('multiplesessions', PARAM_BOOL);
        $mform->addHelpButton('multiplesessions', 'multiplesessions', 'facetoface');
        $multiplesessions = get_config(null, 'facetoface_multiplesessions') ? 1 : 0;
        $mform->setDefault('multiplesessions', $multiplesessions);

        $mform->addElement('checkbox', 'declareinterest', get_string('declareinterestenable', 'facetoface'));
        $mform->addHelpButton('declareinterest', 'declareinterest', 'mod_facetoface');
        $mform->addElement('checkbox', 'interestonlyiffull', get_string('declareinterestonlyiffull', 'facetoface'));
        $mform->addHelpButton('interestonlyiffull', 'declareinterestonlyiffull', 'mod_facetoface');
        $mform->disabledIf('interestonlyiffull', 'declareinterest');

        $selectpositiononsignupglobal = get_config(null, 'facetoface_selectpositiononsignupglobal');
        if (!empty($selectpositiononsignupglobal)) {
            $mform->addElement('checkbox', 'selectpositiononsignup', get_string('selectpositiononsignup', 'facetoface'));
            $mform->addElement('checkbox', 'forceselectposition', get_string('forceselectposition', 'facetoface'));
        }

        $mform->addElement('checkbox', 'allowsignupnotedefault', get_string('allowsignupnotedefault', 'facetoface'));
        $mform->addHelpButton('allowsignupnotedefault', 'allowsignupnotedefault', 'facetoface');
        $mform->setDefault('allowsignupnotedefault', 1);

        $conf = get_config('facetoface');

        $mform->addElement('header', 'managerreserveheader', get_string('managerreserveheader', 'mod_facetoface'));

        $mform->addElement('selectyesno', 'managerreserve', get_string('managerreserve', 'mod_facetoface'));
        $mform->setDefault('managerreserve', $conf->managerreserve);
        $mform->addHelpButton('managerreserve', 'managerreserve', 'mod_facetoface');

        $mform->addElement('text', 'maxmanagerreserves', get_string('maxmanagerreserves', 'mod_facetoface'));
        $mform->setType('maxmanagerreserves', PARAM_INT);
        $mform->setDefault('maxmanagerreserves', $conf->maxmanagerreserves);
        $mform->addHelpButton('maxmanagerreserves', 'maxmanagerreserves', 'mod_facetoface');
        $mform->disabledIf('maxmanagerreserves', 'managerreserve', 'eq', 0);

        $mform->addElement('selectyesno', 'reservecancel', get_string('reservecancel', 'mod_facetoface'));
        $mform->setDefault('reservecancel', 1);
        $mform->disabledIf('reservecancel', 'managerreserve', 'eq', 0);

        $mform->addElement('text', 'reservecanceldays', get_string('reservecanceldays', 'mod_facetoface'));
        $mform->setType('reservecanceldays', PARAM_INT);
        $mform->setDefault('reservecanceldays', $conf->reservecanceldays);
        $mform->addHelpButton('reservecanceldays', 'reservecanceldays', 'mod_facetoface');
        $mform->disabledIf('reservecanceldays', 'managerreserve', 'eq', 0);
        $mform->disabledIf('reservecanceldays', 'reservecancel', 'eq', 0);

        $mform->addElement('text', 'reservedays', get_string('reservedays', 'mod_facetoface'));
        $mform->setType('reservedays', PARAM_INT);
        $mform->setDefault('reservedays', $conf->reservedays);
        $mform->addHelpButton('reservedays', 'reservedays', 'mod_facetoface');
        $mform->disabledIf('reservedays', 'managerreserve', 'eq', 0);
        $mform->addRule(array('reservedays', 'reservecanceldays'), get_string('reservegtcancel', 'mod_facetoface'),
                        'compare', 'gt', 'server');

        $mform->addElement('header', 'calendaroptions', get_string('calendaroptions', 'facetoface'));

        $calendarOptions = array(
            F2F_CAL_NONE    =>  get_string('none'),
            F2F_CAL_COURSE  =>  get_string('course'),
            F2F_CAL_SITE    =>  get_string('site')
        );
        $mform->addElement('select', 'showoncalendar', get_string('showoncalendar', 'facetoface'), $calendarOptions);
        $mform->setDefault('showoncalendar', F2F_CAL_COURSE);
        $mform->addHelpButton('showoncalendar', 'showoncalendar', 'facetoface');

        $mform->addElement('advcheckbox', 'usercalentry', get_string('usercalentry', 'facetoface'));
        $mform->setDefault('usercalentry', true);
        $mform->addHelpButton('usercalentry', 'usercalentry', 'facetoface');

        $mform->addElement('text', 'shortname', get_string('shortname'), array('size' => 32, 'maxlength' => 32));
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addHelpButton('shortname', 'shortname', 'facetoface');

        $features = new stdClass;
        $features->groups = false;
        $features->groupings = false;
        $features->groupmembersonly = false;
        $features->outcomes = false;
        $features->gradecat = false;
        $features->idnumber = true;
        $this->standard_coursemodule_elements($features);

        $this->add_action_buttons();
    }

    function data_preprocessing(&$default_values)
    {
        // Fix manager emails
        if (empty($default_values['confirmationinstrmngr'])) {
            $default_values['confirmationinstrmngr'] = null;
        }
        else {
            $default_values['emailmanagerconfirmation'] = 1;
        }

        if (empty($default_values['reminderinstrmngr'])) {
            $default_values['reminderinstrmngr'] = null;
        }
        else {
            $default_values['emailmanagerreminder'] = 1;
        }

        if (empty($default_values['cancellationinstrmngr'])) {
            $default_values['cancellationinstrmngr'] = null;
        }
        else {
            $default_values['emailmanagercancellation'] = 1;
        }

        // Set some completion default data.
        if (!empty($default_values['completionstatusrequired']) && !is_array($default_values['completionstatusrequired'])) {
            // Unpack values.
            $cvalues = json_decode($default_values['completionstatusrequired'], true);
            $default_values['completionstatusrequired'] = $cvalues;
        }

        $conf = get_config('facetoface');

        if (isset($default_values['reservecanceldays'])) {
            if ($default_values['reservecanceldays'] == 0) {
                $default_values['reservecanceldays'] = $conf->reservecanceldays;
                $default_values['reservecancel'] = 0;
            } else {
                $default_values['reservecancel'] = 1;
            }
        }
    }

    function add_completion_rules() {
        global $MDL_F2F_STATUS;
        $mform =& $this->_form;
        $items = array();

        // Require status.
        $first = true;
        $firstkey = null;
        foreach (array(MDL_F2F_STATUS_PARTIALLY_ATTENDED, MDL_F2F_STATUS_FULLY_ATTENDED) as $key) {
            $value = get_string('status_'.$MDL_F2F_STATUS[$key], 'facetoface');
            $name = null;
            $keyind = $key;
            $key = 'completionstatusrequired['.$key.']';
            if ($first) {
                $name = get_string('completionstatusrequired', 'facetoface');
                $first = false;
                $firstkey = $key;
            }
            $mform->addElement('checkbox', $key, $name, $value);
            $mform->setType($key, PARAM_BOOL);
            $items[] = $key;
        }
        $mform->addHelpButton($firstkey, 'completionstatusrequired', 'facetoface');

        return $items;
    }

    function completion_rule_enabled($data) {
        return (!empty($data['completionstatusrequired']));
    }

    function get_data($slashed = true) {
        $data = parent::get_data($slashed);

        if (!$data) {
            return false;
        }

        // Convert completionstatusrequired to a proper integer, if any.
        $total = 0;
        if (isset($data->completionstatusrequired) && is_array($data->completionstatusrequired)) {
            $data->completionstatusrequired = json_encode($data->completionstatusrequired);
        }

        if (!empty($data->completionunlocked)) {
            // Turn off completion settings if the checkboxes aren't ticked.
            $autocompletion = isset($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;

            if (isset($data->completionstatusrequired) && $autocompletion) {
                // Do nothing: completionstatusrequired has been already converted
                //             into a correct integer representation.
            } else {
                $data->completionstatusrequired = null;
            }

            if (!empty($data->completionscoredisabled) || !$autocompletion) {
                $data->completionscorerequired = null;
            }
        }

        return $data;
    }
}
