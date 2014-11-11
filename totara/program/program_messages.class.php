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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

define('MESSAGETYPE_ENROLMENT', 1);
define('MESSAGETYPE_EXCEPTION_REPORT', 2);
define('MESSAGETYPE_UNENROLMENT', 3);
define('MESSAGETYPE_PROGRAM_DUE', 4);
define('MESSAGETYPE_EXTENSION_REQUEST', 5);
define('MESSAGETYPE_PROGRAM_OVERDUE', 6);
define('MESSAGETYPE_PROGRAM_COMPLETED', 7);
define('MESSAGETYPE_COURSESET_DUE', 8);
define('MESSAGETYPE_COURSESET_OVERDUE', 9);
define('MESSAGETYPE_COURSESET_COMPLETED', 10);
define('MESSAGETYPE_LEARNER_FOLLOWUP', 11);
define('MESSAGETYPE_RECERT_WINDOWOPEN', 12);
define('MESSAGETYPE_RECERT_WINDOWDUECLOSE', 13);
define('MESSAGETYPE_RECERT_FAILRECERT', 14);


class prog_messages_manager {

    // The $formdataobject is an object that will contains the values of any
    // submitted data so that the message edit form can be populated when it
    // is first displayed
    public $formdataobject;

    protected $programid;
    protected $messages;
    protected $messages_deleted_ids;

    // Used to determine if the messages have been changed since it was last saved
    protected $messageschanged = false;

    private $message_classnames = array(
        MESSAGETYPE_ENROLMENT               => 'prog_enrolment_message',
        MESSAGETYPE_EXCEPTION_REPORT        => 'prog_exception_report_message',
        MESSAGETYPE_UNENROLMENT             => 'prog_unenrolment_message',
        MESSAGETYPE_PROGRAM_DUE             => 'prog_program_due_message',
        MESSAGETYPE_PROGRAM_OVERDUE         => 'prog_program_overdue_message',
        MESSAGETYPE_EXTENSION_REQUEST       => 'prog_extension_request_message',
        MESSAGETYPE_PROGRAM_COMPLETED       => 'prog_program_completed_message',
        MESSAGETYPE_COURSESET_DUE           => 'prog_courseset_due_message',
        MESSAGETYPE_COURSESET_OVERDUE       => 'prog_courseset_overdue_message',
        MESSAGETYPE_COURSESET_COMPLETED     => 'prog_courseset_completed_message',
        MESSAGETYPE_LEARNER_FOLLOWUP        => 'prog_learner_followup_message',
        MESSAGETYPE_RECERT_WINDOWOPEN       => 'prog_recert_windowopen_message',
        MESSAGETYPE_RECERT_WINDOWDUECLOSE   => 'prog_recert_windowdueclose_message',
        MESSAGETYPE_RECERT_FAILRECERT       => 'prog_recert_failrecert_message',
    );

    function __construct($programid, $newprogram = false) {
        global $DB;
        $this->programid = $programid;
        $this->messages = array();
        $this->messages_deleted_ids = array();
        $this->formdataobject = new stdClass();

        $messages = $DB->get_records('prog_message', array('programid' => $programid), 'sortorder ASC');
        if (count($messages) > 0) {
            foreach ($messages as $message) {

                if (!array_key_exists($message->messagetype, $this->message_classnames)) {
                    throw new ProgramMessageException(get_string('meesagetypenotfound', 'totara_program'));
                }

                $message_class = $this->message_classnames[$message->messagetype];
                $messageob = new $message_class($programid, $message);

                $this->messages[] = $messageob;
            }
        } else if ($newprogram) {
            // If it is a new program, create the default messages.
            $enrolment_message_class = $this->message_classnames[MESSAGETYPE_ENROLMENT];
            $enrolment_message = new $enrolment_message_class($programid);
            $enrolment_message->messagesubject = get_string('defaultenrolmentmessage_subject', 'totara_program');
            $enrolment_message->mainmessage = get_string('defaultenrolmentmessage_message', 'totara_program');
            $this->messages[] = $enrolment_message;

            $exception_report_message_class = $this->message_classnames[MESSAGETYPE_EXCEPTION_REPORT];
            $exception_report_message = new $exception_report_message_class($programid);
            $exception_report_message->messagesubject = get_string('defaultexceptionreportmessage_subject', 'totara_program');
            $exception_report_message->mainmessage = get_string('defaultexceptionreportmessage_message', 'totara_program');
            $this->messages[] = $exception_report_message;

            // The default message must be saved at this point.
            $this->save_messages();
        }

        $this->fix_message_sortorder($this->messages);
    }

    /**
     * Used by usort to sort the messages in the $messages array
     * by their sortorder properties
     *
     * @param <type> $a
     * @param <type> $b
     * @return <type>
     */
    static function cmp_message_sortorder( $a, $b ) {
        if ($a->sortorder ==  $b->sortorder ) { return 0 ; }
        return ($a->sortorder < $b->sortorder) ? -1 : 1;
    }

    /**
     * Get the messages
     *
     * @return <type>
     */
    public function get_messages() {
        return $this->messages;
    }

    /**
     * Deletes all messages for this program and removes all traces of sent
     * messages from the message log
     *
     * @return bool
     */
    public function delete() {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        // delete the history of all sent messages
        foreach ($this->messages as $message) {
            $DB->delete_records('prog_messagelog', array('messageid' => $message->id));
        }
        // delete all messages
        $DB->delete_records('prog_message', array('programid' => $this->programid));

        $transaction->allow_commit();

        return true;
    }

    /**
     * Makes sure that an array of messages is in order in terms of each
     * message's sortorder property and resets the sortorder properties to ensure
     * that it begins from 1 and there are no gaps in the order.
     *
     * Also adds properties to enable the first and last set in the array to be
     * easily detected.
     *
     * @param <type> $messages
     */
    public function fix_message_sortorder(&$messages=null) {

        if ($messages == null) {
            $messages = $this->messages;
        }

        usort($messages, array('prog_messages_manager', 'cmp_message_sortorder'));

        $pos = 1;
        foreach ($messages as $message) {

            $message->sortorder = $pos;

            unset($message->isfirstmessage);
            if ($pos == 1) {
                $message->isfirstmessage = true;
            }

            unset($message->islastmessage);
            if ($pos == count($messages)) {
                $message->islastmessage = true;
            }

            $pos++;
        }
    }

    /**
     * Recieves the data submitted from the program messages form and sets up
     * the messages in an array so that they can be manipulated and/or
     * re-displayed in the form
     *
     * @param <type> $formdata
     * @return <type>
     */
    public function setup_messages($formdata) {

        $message_prefixes = $this->get_message_prefixes($formdata);

        // If the form has been submitted then it's likely that some changes are
        // being made to the messages so we mark the messages as changed (this
        // is used by javascript to determine whether or not to warn te user
        // if they try to leave the page without saving first
        $this->messageschanged = true;

        $this->messages = array();

        foreach ($message_prefixes as $prefix) {

            $messagetype = $formdata->{$prefix.'messagetype'};

            if (!array_key_exists($messagetype, $this->message_classnames)) {
                throw new ProgramMessageException(get_string('meesagetypenotfound', 'totara_program'));
            }

            $message_class = $this->message_classnames[$messagetype];
            $message = new $message_class($this->programid, null, $prefix);

            $message->init_form_data($prefix, $formdata);
            $this->messages[] = $message;
        }

        $this->messages_deleted_ids = $this->get_deleted_messages($formdata);
        return true;

    }

    /**
     * Returns the sort order of the last message.
     *
     * @return <type>
     */
    public function get_last_message_pos() {
        $sortorder = null;
        foreach ($this->messages as $message) {
            $sortorder = max($sortorder, $message->sortorder);
        }
        return $sortorder;
    }

    /**
     * Retrieves the form name prefixes of all the existing messages from
     * the submitted data and returns an array containing all the form name
     * prefixes
     *
     * @param object $formdata The submitted form data
     * @return array
     */
    public function get_message_prefixes($formdata) {
        if (!isset($formdata->messageprefixes) || empty($formdata->messageprefixes)) {
            return array();
        } else {
            return explode(',', $formdata->messageprefixes);
        }
    }

    /**
     * Retrieves the ids of any deleted messages from the submitted data and
     * returns an array containing the id numbers or an empty array
     *
     * @param <type> $formdata
     * @return <type>
     */
    public function get_deleted_messages($formdata) {
        if (!isset($formdata->deletedmessages) || empty($formdata->deletedmessages)) {
            return array();
        }
        return explode(',', $formdata->deletedmessages);
    }

    /**
     * Determines whether or not an action button was clicked and, if so,
     * determines which message the action refers to (based on the message sortorder)
     * and returns the message order number.
     *
     * @param string $action The action that this relates to (moveup, movedown, delete, etc)
     * @param object $formdata The submitted form data
     * @return int|obj|false Returns message order number if a matching action was found or false for no action
     */
    public function check_message_action($action, $formdata) {

        $message_prefixes = $this->get_message_prefixes($formdata);

        // if a submit button was clicked, try to determine if it relates to a
        // message and, if so, return the message sort order
        foreach ($message_prefixes as $prefix) {
            if (isset($formdata->{$prefix.$action})) {
                return $formdata->{$prefix.'sortorder'};
            }
        }

        return false;
    }

    public function save_messages() {
        global $DB;
        $this->fix_message_sortorder($this->messages);

        // first delete any messages from the database that have been marked for deletion
        foreach ($this->messages_deleted_ids as $messageid) {

            if ($message = $DB->get_record('prog_message', array('id' => $messageid))) {
                // delete any logged messages sent for this message
                $DB->delete_records('prog_messagelog', array('messageid' => $messageid));
                // delete the message
                $DB->delete_records('prog_message', array('id' => $messageid));
            }
        }

        // then save the new and changed messages
        foreach ($this->messages as $message) {
            if (!$message->save_message()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Moves a message up one place in the array of messages
     *
     * @param <type> $messagetomove_sortorder
     * @return <type>
     */
    public function move_message_up($messagetomove_sortorder) {

        foreach ($this->messages as $current_message) {

            if ($current_message->sortorder == $messagetomove_sortorder) {
                $messagetomoveup = $current_message;
            }

            if ($current_message->sortorder == $messagetomove_sortorder-1) {
                $messagetomovedown = $current_message;
            }
        }

        if ($messagetomoveup && $messagetomovedown) {
            $moveup_sortorder = $messagetomoveup->sortorder;
            $movedown_sortorder = $messagetomovedown->sortorder;
            $messagetomoveup->sortorder = $movedown_sortorder;
            $messagetomovedown->sortorder = $moveup_sortorder;
            $this->fix_message_sortorder($this->messages);
            return true;
        }

        return false;
    }

    /**
     * Moves a message down one place in the array of message
     *
     * @param <type> $messagetomove_sortorder
     * @return <type>
     */
    public function move_message_down($messagetomove_sortorder) {

        foreach ($this->messages as $current_message) {

            if ($current_message->sortorder == $messagetomove_sortorder) {
                $messagetomovedown = $current_message;
            }

            if ($current_message->sortorder == $messagetomove_sortorder+1) {
                $messagetomoveup = $current_message;
            }
        }

        if ($messagetomovedown && $messagetomoveup) {
            $movedown_sortorder = $messagetomovedown->sortorder;
            $moveup_sortorder = $messagetomoveup->sortorder;
            $messagetomovedown->sortorder = $moveup_sortorder;
            $messagetomoveup->sortorder = $movedown_sortorder;
            $this->fix_message_sortorder($this->messages);
            return true;
        }

        return false;
    }

    /**
     * Adds a new message to the array of messages.
     *
     * @param <type> $messagetype
     * @return <type>
     */
    public function add_message($messagetype) {

        $lastmessagepos = $this->get_last_message_pos();

        if (!array_key_exists($messagetype, $this->message_classnames)) {
            throw new ProgramMessageException(get_string('meesagetypenotfound', 'totara_program'));
        }

        $message_class = $this->message_classnames[$messagetype];
        $message = new $message_class($this->programid);

        if ($lastmessagepos !== null) {
            $message->sortorder = $lastmessagepos + 1;
        } else {
            $message->sortorder = 1;
        }

        $this->messages[] = $message;
        $this->fix_message_sortorder($this->messages);
        return true;
    }

    /**
     * Deletes a message from the array of messages. If the message
     * has no id number (i.e. it does not yet exist in the database) it is
     * removed from the array but if it has an id number it is marked as
     * deleted but not actually removed from the array until the messages are
     * saved
     *
     * @param <type> $messagetodelete_sortorder
     */
    public function delete_message($messagetodelete_sortorder) {

        $new_messages = array();
        $messagefound = false;

        foreach ($this->messages as $message) {
            if ($message->sortorder == $messagetodelete_sortorder) {
                $messagefound = true;
                if ($message->id > 0) { // if this message already exists in the database
                    $this->messages_deleted_ids[] = $message->id;
                }
            } else {
                $new_messages[] = $message;
            }
        }

        if ($messagefound) {
            $this->messages = $new_messages;
            $this->fix_message_sortorder($this->messages);
            return true;
        }

        return false;
    }

    public function update_messages() {
        $this->fix_message_sortorder($this->messages);
    }

    /**
     * Returns an HTML string suitable for displaying as the label for the
     * messages in the program overview form
     *
     * @return string
     */
    public function display_form_label() {
        $out = '';
        $out .= get_string('instructions:messages1', 'totara_program');
        return $out;
    }

    /**
     * Returns an HTML string suitable for displaying as the element body
     * for the messages in the program overview form
     *
     * @return string
     */
    public function display_form_element() {

        $out = '';

        if (count($this->messages)) {
            $messagecount = 0;
            foreach ($this->messages as $message) {
                $messageclassname = $this->message_classnames[$message->messagetype];
                $styleclass = ($messagecount % 2 == 0) ? 'even' : 'odd';
                $component = (substr($messageclassname, 0, 11) == 'prog_recert' ? 'totara_certification' : 'totara_program');
                $out .= html_writer::tag('p', get_string($messageclassname, $component), array('class' => $styleclass));
                $messagecount++;
            }
        } else {
            $out .= get_string('noprogrammessages', 'totara_program');
        }

        return $out;
    }

    public function get_message_form_template(&$mform, &$template_values, $messages=null, $updateform=true) {
        global $DB, $OUTPUT;

        if ($messages == null) {
            $messages = $this->messages;
        }

        $templatehtml = '';
        $nummessages = count($messages);
        $canaddmessage = true;

        // This update button is at the start of the form so that it catches any
        // 'return' key presses in text fields and acts as the default submit
        // behaviour. This is not official browser behaviour but in most browsers
        // this should result in this button being submitted (where a form has
        // multiple submit buttons like this one)
        if ($updateform) {
            $mform->addElement('submit', 'update', get_string('update', 'totara_program'));
            $template_values['%update%'] = array('name'=>'update', 'value'=>null);
        }
        $templatehtml .= '%update%'."\n";

        // Add the program id
        if ($updateform) {
            $mform->addElement('hidden', 'id');
            $mform->setType('id', PARAM_INT);
            $template_values['%programid%'] = array('name'=>'id', 'value'=>null);
        }
        $templatehtml .= '%programid%'."\n";
        $this->formdataobject->id = $this->programid;

        // Add a hidden field to show if the messages have been changed
        // (used by javascript to determine whether or not to display a
        // dialog when the user leaves the page)
        $messageschanged = $this->messageschanged ? '1' : '0';
        if ($updateform) {
            $mform->addElement('hidden', 'messageschanged', $messageschanged);
            $mform->setType('messageschanged', PARAM_BOOL);
            $mform->setConstant('messageschanged', $messageschanged);
            $template_values['%messageschanged%'] = array('name'=>'messageschanged', 'value'=>null);
        }
        $templatehtml .= '%messageschanged%'."\n";
        $this->formdataobject->messageschanged = $messageschanged;

        // Add the deleted message ids
        if ($this->messages_deleted_ids) {
            $deletedmessageidsarray = array();
            foreach ($this->messages_deleted_ids as $deleted_message_id) {
                $deletedmessageidsarray[] = $deleted_message_id;
            }
            $deletedmessageidsstr = implode(',', $deletedmessageidsarray);
            if ($updateform) {
                $mform->addElement('hidden', 'deletedmessages', $deletedmessageidsstr);
                $mform->setType('deletedmessages', PARAM_SEQUENCE);
                $mform->setConstant('deletedmessages', $deletedmessageidsstr);
                $template_values['%deletedmessages%'] = array('name'=>'deletedmessages', 'value'=>null);
            }
            $templatehtml .= '%deletedmessages%'."\n";
            $this->formdataobject->deletedmessages = $deletedmessageidsstr;
        }

        $templatehtml .= $OUTPUT->heading(get_string('programmessages', 'totara_program'));
        $templatehtml .= html_writer::tag('p', get_string('instructions:programmessages', 'totara_program'));

        $templatehtml .= html_writer::start_tag('div', array('id' => 'messages'));

        if ($nummessages == 0) { // if there are no messages yet
            $templatehtml .= html_writer::tag('p', get_string('noprogrammessages', 'totara_program'));
        } else {
            $messageprefixesarray = array();
            foreach ($messages as $message) {
                $messageprefixesarray[] = $message->get_message_prefix();
                // Add the messages
                $templatehtml .= $message->get_message_form_template($mform, $template_values, $this->formdataobject, $updateform);
            }

            // Add the set prefixes
            $messageprefixesstr = implode(',', $messageprefixesarray);
            if ($updateform) {
                $mform->addElement('hidden', 'messageprefixes', $messageprefixesstr);
                $mform->setType('messageprefixes', PARAM_TEXT);
                $mform->setConstant('messageprefixes', $messageprefixesstr);
                $template_values['%messageprefixes%'] = array('name'=>'messageprefixes', 'value'=>null);
            }
            $templatehtml .= '%messageprefixes%'."\n";
            $this->formdataobject->messageprefixes = $messageprefixesstr;
        }

        $templatehtml .= html_writer::end_tag('div');

        if ($canaddmessage) {
            // Add the add message drop down
            if ($updateform) {
                $messageoptions = array(
                    MESSAGETYPE_ENROLMENT => get_string('enrolment', 'totara_program'),
                    MESSAGETYPE_EXCEPTION_REPORT => get_string('exceptionsreport', 'totara_program'),
                    MESSAGETYPE_UNENROLMENT => get_string('unenrolment', 'totara_program'),
                    MESSAGETYPE_PROGRAM_DUE => get_string('programdue', 'totara_program'),
                    MESSAGETYPE_PROGRAM_OVERDUE => get_string('programoverdue', 'totara_program'),
                    MESSAGETYPE_PROGRAM_COMPLETED => get_string('programcompleted', 'totara_program'),
                    MESSAGETYPE_COURSESET_DUE => get_string('coursesetdue', 'totara_program'),
                    MESSAGETYPE_COURSESET_OVERDUE => get_string('coursesetoverdue', 'totara_program'),
                    MESSAGETYPE_COURSESET_COMPLETED => get_string('coursesetcompleted', 'totara_program'),
                    MESSAGETYPE_LEARNER_FOLLOWUP => get_string('learnerfollowup', 'totara_program')
                );

                // add extra messages if a certification-program
                $prog = $DB->get_record('prog', array('id' => $this->programid));
                if ($prog->certifid > 0) {
                    $messageoptions[MESSAGETYPE_RECERT_WINDOWOPEN]     = get_string('recertwindowopen', 'totara_certification');
                    $messageoptions[MESSAGETYPE_RECERT_WINDOWDUECLOSE] = get_string('recertwindowdueclose', 'totara_certification');
                    $messageoptions[MESSAGETYPE_RECERT_FAILRECERT]     = get_string('recertfailrecert', 'totara_certification');
                }

                $mform->addElement('select', 'messagetype', get_string('addnew', 'totara_program'), $messageoptions, array('id'=>'messagetype'));
                $mform->setType('messagetype', PARAM_INT);
                $template_values['%messagetype%'] = array('name'=>'messagetype', 'value'=>null);
            }
            $templatehtml .= html_writer::tag('label', get_string('addnew', 'totara_program'), array('for' => 'messagetype'));
            $templatehtml .= '%messagetype%';
            $templatehtml .= get_string('toprogram', 'totara_program');

            // Add the add content button
            if ($updateform) {
                $mform->addElement('submit', 'addmessage', get_string('add'), array('id'=>'addmessage'));
                $template_values['%addmessage%'] = array('name'=>'addmessage', 'value'=>null);
            }
            $templatehtml .= '%addmessage%'."\n";
        }

        $templatehtml .= html_writer::empty_tag('br');

        // Add the save and return button
        if ($updateform) {
            $mform->addElement('submit', 'savechanges', get_string('savechanges'), array('class'=>'return-overview'));
            $template_values['%savechanges%'] = array('name'=>'savechanges', 'value'=>null);
        }
        $templatehtml .= '%savechanges%'."\n";

        // Add the cancel button
        if ($updateform) {
            $mform->addElement('cancel', 'cancel', get_string('cancel', 'totara_program'));
            $template_values['%cancel%'] = array('name'=>'cancel', 'value'=>null);
        }
        $templatehtml .= '%cancel%'."\n";

        return $templatehtml;

    }

}

class ProgramMessageException extends Exception { }
