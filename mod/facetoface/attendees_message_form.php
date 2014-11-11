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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Francois Marier <francois@catalyst.net.nz>
 * @package modules
 * @subpackage facetoface
 */
defined('MOODLE_INTERNAL') || die();


require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/facetoface/lib.php');

class mod_facetoface_attendees_message_form extends moodleform {

    function definition() {
        global $CFG;

        $mform =& $this->_form;

        $mform->addElement('hidden', 's', $this->_customdata['s']);
        $mform->setType('s', PARAM_INT);

        $mform->addElement('header', 'recipientgroupsheader', get_string('messagerecipientgroups', 'facetoface'));

        // Display select recipient by status
        $statuses = array(
            MDL_F2F_STATUS_USER_CANCELLED,
            MDL_F2F_STATUS_WAITLISTED,
            MDL_F2F_STATUS_BOOKED,
            MDL_F2F_STATUS_NO_SHOW,
            MDL_F2F_STATUS_PARTIALLY_ATTENDED,
            MDL_F2F_STATUS_FULLY_ATTENDED
        );

        $json_users = array();
        $attendees = array();
        foreach ($statuses as $status) {
            // Get count of users with this status
            $count = facetoface_get_num_attendees($this->_customdata['s'], $status, '=');

            if (!$count) {
                continue;
            }

            $users = facetoface_get_users_by_status($this->_customdata['s'], $status);
            $json_users[$status] = $users;
            $attendees = array_merge($attendees, $users);

            $title = facetoface_get_status($status);

            $mform->addElement('checkbox', 'recipient_group['.$status.']', get_string('status_'.$title, 'facetoface') . ' - ' . get_string('xusers', 'facetoface', $count), null, array('id' => 'id_recipient_group_'.$status));
        }

        // Display individual recipient selectors
        $mform->addElement('header', 'recipientsheader', get_string('messagerecipients', 'facetoface'));

        $options = array();
        foreach ($attendees as $a) {
            $options[$a->id] = fullname($a);
        }
        $mform->addElement('select', 'recipients', get_string('individuals', 'facetoface'), $options,  array('size' => 5));
        $mform->addElement('hidden', 'recipients_selected');
        $mform->setType('recipients_selected', PARAM_SEQUENCE);
        $mform->addElement('button', 'recipient_custom', get_string('editmessagerecipientsindividually', 'facetoface'));
        $mform->addElement('checkbox', 'cc_managers', get_string('messagecc', 'facetoface'));

        $mform->addElement('header', 'messageheader', get_string('messageheader', 'facetoface'));

        $mform->addElement('text', 'subject', get_string('messagesubject', 'facetoface'));
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');

        $mform->addElement('editor', 'body', get_string('messagebody', 'facetoface'));
        $mform->setType('body', PARAM_CLEANHTML);
        $mform->addRule('body', get_string('required'), 'required', null, 'client');

        $json_users = json_encode($json_users);
        $mform->addElement('html', '<script type="text/javascript">var recipient_groups = '.$json_users.'</script>');

        // Add action buttons
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('sendmessage', 'facetoface'));
        $buttonarray[] = $mform->createElement('cancel', 'cancel', get_string('discardmessage', 'facetoface'));

        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }
}
