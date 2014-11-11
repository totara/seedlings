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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package modules
 * @subpackage facetoface
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');

class mod_facetoface_notification_form extends moodleform {

    function definition() {
        global $CFG;
        $mform =& $this->_form;

        $editoroptions = $this->_customdata['editoroptions'];

        $notification = $this->_customdata['notification'];
        $isfrozen = $notification->is_frozen();

        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);

        // If frozen, display details at top
        // Hide scheduling/recipient selectors for automatic notifications
        if ($isfrozen || $notification->type == MDL_F2F_NOTIFICATION_AUTO) {
            $html = '';
            $description = $notification->get_condition_description();
            $recipients = $notification->get_recipient_description();

            $mform->addElement('static', '', get_string('scheduling', 'facetoface'), $description);
            $mform->addElement('static', '', get_string('recipients', 'facetoface'), $recipients);
            $mform->addElement('hidden', 'type', $notification->type);
            $mform->setType('type', PARAM_INT);
        } else {
            // For non automatic notifications, display schedule/recipient picker
            $mform->addElement('radio', 'type', get_string('scheduling', 'facetoface'), get_string('sendnow', 'facetoface'), MDL_F2F_NOTIFICATION_MANUAL);
            $mform->addElement('radio', 'type', '', get_string('sendlater', 'facetoface'), MDL_F2F_NOTIFICATION_SCHEDULED);
            $mform->setDefault('type', MDL_F2F_NOTIFICATION_MANUAL);
            $mform->setType('type', PARAM_INT);

            $sched_units = array(
                MDL_F2F_SCHEDULE_UNIT_HOUR  => get_string('hours'),
                MDL_F2F_SCHEDULE_UNIT_DAY   => get_string('days'),
                MDL_F2F_SCHEDULE_UNIT_WEEK  => get_string('weeks')
            );

            $sched_types = array(
                MDL_F2F_CONDITION_BEFORE_SESSION => get_string('beforestartofsession', 'facetoface'),
                MDL_F2F_CONDITION_AFTER_SESSION  => get_string('afterendofsession', 'facetoface')
            );

            $group = array();
            $group[] = $mform->createElement('select', 'scheduleamount', '', range(0, 24));
            $group[] = $mform->createElement('select', 'scheduleunit', '', $sched_units);
            $group[] = $mform->createElement('select', 'conditiontype', '', $sched_types);

            $mform->addGroup($group, 'schedule', '', array(' '), false);
            $mform->disabledIf('schedule', 'type', 'ne', MDL_F2F_NOTIFICATION_SCHEDULED);

            $mform->addElement('html', '<br /><br />');

            $group = array();
            $group[] = $mform->createElement('advcheckbox', 'booked', get_string('status_booked', 'facetoface'));
            $group[] = $mform->createElement('radio', 'booked_type', '',
                    get_string('recipients_allbooked', 'facetoface'), MDL_F2F_RECIPIENTS_ALLBOOKED);
            $group[] = $mform->createElement('radio', 'booked_type', '',
                    get_string('recipients_attendedonly', 'facetoface'), MDL_F2F_RECIPIENTS_ATTENDED);
            $group[] = $mform->createElement('radio', 'booked_type', '',
                    get_string('recipients_noshowsonly', 'facetoface'), MDL_F2F_RECIPIENTS_NOSHOWS);

            $group[] = $mform->createElement('advcheckbox', 'waitlisted', get_string('status_waitlisted', 'facetoface'));
            $group[] = $mform->createElement('advcheckbox', 'cancelled', get_string('status_user_cancelled', 'facetoface'));

            $mform->addGroup($group, 'recipients', get_string('recipients', 'facetoface'), '', false);

            $mform->setType('booked', PARAM_BOOL);
            $mform->setDefault('booked', true);
            $mform->disabledIf('booked_type', 'booked', 'notchecked');
            $mform->setDefault('booked_type', MDL_F2F_RECIPIENTS_ALLBOOKED);
            $mform->setType('booked_type', PARAM_INT);

            $mform->setType('waitlisted', PARAM_BOOL);
            $mform->setType('cancelled', PARAM_BOOL);

            $renderer =& $mform->defaultRenderer();
            $elementtemplate = '<div class="fitem">{element} <label>{label}</label></div>';
            $renderer->setGroupElementTemplate($elementtemplate, 'recipients');
        }

        $mform->addElement('html', '<br /><br />');


        // Display template picker
        if (!$isfrozen && $this->_customdata['templates']) {
            $tpls = array();
            $tpls[0] = get_string('notrequired', 'facetoface');
            foreach ($this->_customdata['templates'] as $tpl) {
                $tpls[$tpl->id] = $tpl->title;
            }

            $mform->addElement('select', 'template', get_string('template', 'facetoface'), $tpls);
        }

        // Display message content settings.
        $mform->addElement('text', 'title', get_string('title', 'facetoface'), array('size' => 50));
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->setType('title', PARAM_TEXT);

        $mform->addElement('editor', 'body_editor', get_string('body', 'facetoface'));
        $mform->addHelpButton('body_editor', 'body', 'facetoface');
        $mform->setType('body', PARAM_RAW);

        if (!$isfrozen) {
            $mform->addElement('html', '<br /><br />');

            $group = array();
            $group[] = $mform->createElement('checkbox', 'ccmanager');
            $group[] = $mform->createElement('static', '', '', get_string('ccmanager_note', 'facetoface'));
            $mform->addGroup($group, 'ccmanager_group', get_string('ccmanager', 'facetoface'), ' ', false);
            $mform->setType('ccmanager', PARAM_INT);

            $mform->addElement('editor', 'managerprefix_editor', get_string('managerprefix', 'facetoface'));
            $mform->setType('managerprefix_editor', PARAM_RAW);
        } else {
            if ($notification->ccmanager) {
                $mform->addElement('editor', 'managerprefix_editor', get_string('managerprefix', 'facetoface'));
                $mform->setType('managerprefix_editor', PARAM_RAW);
            }
        }

        // Enable checkbox.
        $mform->addElement('checkbox', 'status', get_string('status'));
        $mform->setType('status', PARAM_INT);

        // Is form frozen?
        if ($isfrozen) {
            $mform->hardFreeze();
        }

        $this->add_action_buttons(true, get_string('save', 'admin'));
    }


    /**
     * Validate form data
     *
     * @access  public
     * @param   array   $data
     * @param   array   $files
     * @return  array
     */
    function validation($data, $files) {
        $errors = array();

        $mform =& $this->_form;

        if ($mform->elementExists('recipients')) {
            $recipients = $mform->getElement('recipients');
            $elements = $recipients->getElements();

            $rc = array('booked', 'waitlisted', 'cancelled');
            $has_val = false;
            foreach ($elements as $element) {
                if (in_array($element->getName(), $rc)) {
                    if ($element->getValue()) {
                        $has_val = true;
                        break;
                    }
                }
            }

            if (!$has_val) {
                $errors['recipients'] = get_string('error:norecipientsselected', 'facetoface');
            }
        }

        return $errors;
    }
}
