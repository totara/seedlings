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

require_once($CFG->dirroot.'/totara/message/messagelib.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
 * This is a simple class representing the data suitable to be passed to the
 * totara_alert_send method which is used extensively by the program
 * messaging functionality
 */
class prog_message_data {

    public $userto, $userfrom, $roleid;
    public $subject, $fullmessage;
    public $contexturl, $contexturlname;
    public $sendemail, $msgtype, $urgency;

    public function __construct($messagedata) {

        (isset($messagedata['userto']))            && ($this->userto = $messagedata['userto']);
        (isset($messagedata['userfrom']))          && ($this->userfrom = $messagedata['userfrom']);
        (isset($messagedata['roleid']))            && ($this->roleid = $messagedata['roleid']);
        (isset($messagedata['subject']))           && ($this->subject = $messagedata['subject']);
        (isset($messagedata['fullmessage']))       && ($this->fullmessage = $messagedata['fullmessage']);
        (isset($messagedata['contexturl']))        && ($this->contexturl = $messagedata['contexturl']);
        (isset($messagedata['contexturlname']))    && ($this->contexturlname = $messagedata['contexturlname']);
        (isset($messagedata['icon']))              && ($this->icon = $messagedata['icon']);

        $this->msgtype   = isset($messagedata['msgtype']) ? $messagedata['msgtype'] : TOTARA_MSG_TYPE_UNKNOWN;
        $this->urgency   = isset($messagedata['urgency']) ? $messagedata['urgency'] : TOTARA_MSG_URGENCY_NORMAL;

    }
}

abstract class prog_message {

    public $id, $programid, $messagetype, $sortorder;
    public $messagesubject, $mainmessage;
    public $notifymanager, $managermessage;
    public $triggertime, $triggerperiod, $triggernum;
    public $isfirstmessage, $islastmessage;
    public $studentrole, $managerrole;
    public $uniqueid;

    protected $fieldsetlegend;
    protected $studentmessagedata, $managermessagedata;
    protected $triggereventstr;

    protected $replacementvars = array();
    protected $helppage = '';

    const messageprefixstr = 'message_';

    public function __construct($programid, $messageob=null, $uniqueid=null) {
        global $CFG;

        if (is_object($messageob)) {
            $this->id = $messageob->id;
            $this->programid = $messageob->programid;
            $this->sortorder = $messageob->sortorder;
            $this->messagesubject = $messageob->messagesubject;
            $this->mainmessage = $messageob->mainmessage;
            $this->notifymanager = $messageob->notifymanager;
            $this->managermessage = $messageob->managermessage;
            $this->triggertime = $messageob->triggertime;
        } else {
            $this->id = 0;
            $this->programid = $programid;
            $this->sortorder = 0;
            $this->messagesubject = '';
            $this->mainmessage = '';
            $this->notifymanager = false;
            $this->managermessage = '';
            $this->triggertime = 0;
        }

        $tiggertime = program_utilities::duration_explode($this->triggertime);
        $this->triggernum = $tiggertime->num;
        $this->triggerperiod = $tiggertime->period;

        $this->fieldsetlegend = '';

        if ($uniqueid) {
            $this->uniqueid = $uniqueid;
        } else {
            $this->uniqueid = rand();
        }

    $this->studentrole = $CFG->learnerroleid;
    $this->managerrole = $CFG->managerroleid;

    if (!$this->studentrole) {
        print_error('error:failedtofindstudentrole', 'totara_program');
    }
    if (!$this->managerrole) {
        print_error('error:failedtofindmanagerrole', 'totara_program');
    }

    }

    public function init_form_data($formnameprefix, $formdata) {
        $this->id = $formdata->{$formnameprefix.'id'};
        $this->programid = $formdata->id;
        $this->messagetype = $formdata->{$formnameprefix.'messagetype'};
        $this->sortorder = $formdata->{$formnameprefix.'sortorder'};
        $this->messagesubject = $formdata->{$formnameprefix.'messagesubject'};
        $this->mainmessage = $formdata->{$formnameprefix.'mainmessage'};

        $this->notifymanager = isset($formdata->{$formnameprefix.'notifymanager'}) ? $formdata->{$formnameprefix.'notifymanager'} : false;;
        $this->managermessage = isset($formdata->{$formnameprefix.'managermessage'}) ? $formdata->{$formnameprefix.'managermessage'} : '';
        $this->triggerperiod = isset($formdata->{$formnameprefix.'triggerperiod'}) ? $formdata->{$formnameprefix.'triggerperiod'} : 0;
        $this->triggernum = isset($formdata->{$formnameprefix.'triggernum'}) ? $formdata->{$formnameprefix.'triggernum'} : 0;
        $this->triggertime = program_utilities::duration_implode($this->triggernum, $this->triggerperiod);
    }

    public function get_message_prefix() {
        return $this->uniqueid;
    }

    public function get_student_message_data() {
        return $this->studentmessagedata;
    }

    public function get_manager_message_data() {
        return $this->managermessagedata;
    }

    public function check_message_action($action, $formdata) {
        return false;
    }

    public function save_message() {
        global $DB;
        //Create object to save
        $message_todb = new stdClass();
        $message_todb->programid = $this->programid;
        $message_todb->messagetype = $this->messagetype;
        $message_todb->sortorder = $this->sortorder;
        $message_todb->notifymanager = $this->notifymanager;
        $message_todb->triggertime = $this->triggertime;

        if ($this->id > 0) { // if this message already exists in the database
            $message_todb->id = $this->id;
            $message_todb->messagesubject = $this->messagesubject;
            $message_todb->mainmessage = $this->mainmessage;
            $message_todb->managermessage = $this->managermessage;
            $DB->update_record('prog_message', $message_todb);
            return true;
        } else {
            $message_todb->messagesubject = $this->messagesubject;
            $message_todb->mainmessage = $this->mainmessage;
            $message_todb->managermessage = $this->managermessage;
            $id = $DB->insert_record('prog_message', $message_todb);
            $this->id = $id;
            return true;
        }
    }

    /**
     * Set replacement variables used when sending a message.
     *
     * @param object $recipient A user record
     * @param array $options An optional array containing options for the message
     * @return void.
     */
    public function set_replacementvars($recipient, $options = array()) {
        global $DB;

        $userid = $recipient->id;
        $programid = $this->programid;
        $coursesetid = isset($options['coursesetid']) ? $options['coursesetid'] : 0;

        // Get text to scan for placeholders.
        $messagedata = $this->studentmessagedata->subject . $this->studentmessagedata->fullmessage;
        if ($manager = totara_get_manager($recipient->id)) {
            $messagedata .= $this->managermessagedata->subject . $this->managermessagedata->fullmessage;
        }

        // Placeholders available.
        $placeholders = array('setlabel', 'programfullname', 'certificationfullname', 'duedate',
            'completioncriteria', 'userfullname', 'username', 'managername', 'manageremail');

        // Scan for placeholders in the message and delete those which are not used.
        foreach ($placeholders as $key => $value) {
            if (strpos($messagedata, "%{$value}%") === false) {
                unset($placeholders[$key]);
            }
        }

        // Initialise data needed to calculate completion fields.
        if (in_array('duedate', $placeholders) || in_array('completioncriteria', $placeholders)) {
            $formatdate = get_string('datepickerlongyearphpuserdate', 'totara_core');
            $deletecompletionfield = false;
            if ($assignment = $DB->get_record('prog_user_assignment', array('programid' => $programid, 'userid' => $userid))) {
                if (!$progassignment = $DB->get_record('prog_assignment', array('id' => $assignment->assignmentid))) {
                    $deletecompletionfield = true;
                }
            } else {
                $deletecompletionfield = true;
            }

            // If program assignment record not found, delete completion date and completion criteria from placeholders.
            if ($deletecompletionfield) {
                if ($pos = array_search('duedate', $placeholders)) {
                    unset($placeholders[$pos]);
                }
                if ($pos = array_search('completioncriteria', $placeholders)) {
                    unset($placeholders[$pos]);
                }
            }
        }

        // Get program fullname needed for programfullname and certificationfullname options.
        if (in_array('programfullname', $placeholders) || in_array('certificationfullname', $placeholders)) {
            if ($programfullname = $DB->get_field('prog', 'fullname', array('id' => $programid))) {
                $programfullname = format_string($programfullname, true, array('context' => context_user::instance($userid)));
            }
        }

        foreach ($placeholders as $placeholder) {
            switch ($placeholder) {
                case 'programfullname';
                    $this->replacementvars['programfullname'] = $programfullname;
                    break;
                case 'setlabel';
                    $setlabel = $DB->get_field('prog_courseset', 'label', array('id' => $coursesetid));
                    $this->replacementvars['setlabel'] = ($setlabel) ? $setlabel : '';
                    break;
                case 'certificationfullname';
                    $this->replacementvars['certificationfullname'] = $programfullname;
                    break;
                case 'duedate';
                    // Get completion date.
                    $completiontime = $DB->get_field('prog_completion', 'timedue', array('programid' => $programid, 'userid' => $userid));
                    $duedate = get_string('duedatenotset', 'totara_program');
                    if ($completiontime && $completiontime != COMPLETION_TIME_NOT_SET) {
                        $duedate = userdate($completiontime, $formatdate, $recipient->timezone, false);
                    }
                    $this->replacementvars['duedate']   = $duedate;
                    break;
                case 'completioncriteria';
                    $time = $progassignment->completiontime;
                    $event = $progassignment->completionevent;
                    $instance = $progassignment->completioninstance;

                    // Get completion criteria.
                    if ($progassignment->completionevent == COMPLETION_EVENT_NONE) {
                        $ccriteria = get_string('completioncriterianotdefined', 'totara_program');
                        if ($time != COMPLETION_TIME_NOT_SET) {
                            $formatedtime = trim(userdate($time, $formatdate, $recipient->timezone, false));
                            $ccriteria = prog_assignment_category::build_completion_string($formatedtime, $event, $instance);
                        }
                    } else {
                        $parts = program_utilities::duration_explode($time);
                        $formatedtime = $parts->num . ' ' . $parts->period;
                        $ccriteria = prog_assignment_category::build_completion_string($formatedtime, $event, $instance);
                    }
                    $this->replacementvars['completioncriteria'] =  $ccriteria;
                    break;
                case 'userfullname';
                    $this->replacementvars['userfullname'] = fullname($recipient);
                    break;
                case 'username';
                    $this->replacementvars['username'] = $recipient->username;
                    break;
                case 'managername';
                    $this->replacementvars['managername'] = ($manager) ? fullname($manager) : '';
                    break;
                case 'manageremail';
                    $this->replacementvars['manageremail'] = ($manager) ? obfuscate_mailto($manager->email) : '';
                    break;
                default:
                    break;
            }
        }
    }

    public function replacevars($text) {
        foreach ($this->replacementvars as $search => $replace) {
            $text = str_replace("%$search%", $replace, $text);
        }
        return $text;
    }

    /**
     * Sends a generic alert message using the Totara message/alert framework
     *
     * @param object $messagedata See tm_alert_send and tm_message_send for details of what this object should contain
     * @return boole Success status
     */
    public static function send_generic_alert($messagedata) {

        (!isset($messagedata->msgtype))     && $messagedata->msgtype    = TOTARA_MSG_TYPE_UNKNOWN;
        (!isset($messagedata->urgency))     && $messagedata->urgency    = TOTARA_MSG_URGENCY_NORMAL;

        if (tm_alert_send($messagedata)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Sends the message to the specified recipient
     *
     * @param object $recipient A user record
     * @param object $sender An optional user record
     * @param array $options An optional array containing options for the message
     * @return bool Success
     */
    abstract public function send_message($recipient, $sender=null, $options=array());

    /**
     * Defines the form elements for a message
     *
     * @param <type> $mform
     * @param <type> $template_values
     * @param <type> $formdataobject
     * @param <type> $updateform
     */
    abstract public function get_message_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true);

    /**
     * Defines the hidden form elements that are common to all message types
     *
     * @param <type> $mform
     * @param <type> $template_values
     * @param <type> $formdataobject
     * @param <type> $updateform
     * @return <type>
     */
    public function get_generic_hidden_fields_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {

        $prefix = $this->get_message_prefix();

        $templatehtml = '';

        // Add the message set id
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'id', $this->id);
            $mform->setType($prefix.'id', PARAM_INT);
            $mform->setConstant($prefix.'id', $this->id);
            $template_values['%'.$prefix.'id%'] = array('name'=>$prefix.'id', 'value'=>null);
        }
        $templatehtml .= '%'.$prefix.'id%'."\n";
        $formdataobject->{$prefix.'id'} = $this->id;

        // Add the message sort order
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'sortorder', $this->sortorder);
            $mform->setType($prefix.'sortorder', PARAM_INT);
            $mform->setConstant($prefix.'sortorder', $this->sortorder);
            $template_values['%'.$prefix.'sortorder%'] = array('name'=>$prefix.'sortorder', 'value'=>null);
        }
        $templatehtml .= '%'.$prefix.'sortorder%'."\n";
        $formdataobject->{$prefix.'sortorder'} = $this->sortorder;

        // Add the message type
        if ($updateform) {
            $mform->addElement('hidden', $prefix.'messagetype', $this->messagetype);
            $mform->setType($prefix.'messagetype', PARAM_INT);
            $mform->setConstant($prefix.'messagetype', $this->messagetype);
            $template_values['%'.$prefix.'messagetype%'] = array('name'=>$prefix.'messagetype', 'value'=>null);
        }
        $templatehtml .= '%'.$prefix.'messagetype%'."\n";
        $formdataobject->{$prefix.'messagetype'} = $this->messagetype;

        return $templatehtml;
    }

    /**
     * Defines the default subject and message body form elements that
     * several message types use
     *
     * @param object $mform
     * @param array $template_values
     * @param object $formdataobject
     * @param bool $updateform
     * @return string HTML Fragment
     */
    public function get_generic_basic_fields_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;
        $prefix = $this->get_message_prefix();

        $templatehtml = '';

        // Add the message subject
        $safe_messagesubject = format_string($this->messagesubject);
        if ($updateform) {
            $mform->addElement('text', $prefix.'messagesubject', '', array('size'=>'50', 'maxlength'=>'255'));
            $mform->setType($prefix.'messagesubject', PARAM_TEXT);
            $template_values['%'.$prefix.'messagesubject%'] = array('name'=>$prefix.'messagesubject', 'value'=>null);
        }
        $helpbutton = $OUTPUT->help_icon('messagesubject', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fline'));
        $templatehtml .= html_writer::tag('div', html_writer::tag('label', get_string('label:subject', 'totara_program') . ' ' . $helpbutton, array('for' => $prefix.'messagesubject')), array('class' => 'flabel'));
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'messagesubject%', array('class' => 'fitem'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'messagesubject'} = $safe_messagesubject;

        // Add the main message
        $safe_mainmessage = format_string($this->mainmessage);
        if ($updateform) {
            $mform->addElement('textarea', $prefix.'mainmessage', '', array('cols'=>'40', 'rows'=>'5'));
            $mform->setType($prefix.'mainmessage', PARAM_TEXT);
            $template_values['%'.$prefix.'mainmessage%'] = array('name'=>$prefix.'mainmessage', 'value'=>null);
        }
        $helpbutton = $OUTPUT->help_icon('mainmessage', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fline'));
        $templatehtml .= html_writer::tag('div', html_writer::tag('label', get_string('label:message', 'totara_program') . ' ' . $helpbutton, array('for' => $prefix.'mainmessage')), array('class' => 'flabel'));
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'mainmessage%', array('class' => 'fitem'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'mainmessage'} = $safe_mainmessage;

        return $templatehtml;
    }

    /**
     * Defines the subject and message body form elements along with the
     * manager message field that several message types use
     *
     * @param <type> $mform
     * @param <type> $template_values
     * @param <type> $formdataobject
     * @param <type> $updateform
     * @return <type>
     */
    public function get_generic_manager_fields_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;
        $prefix = $this->get_message_prefix();

        $templatehtml = '';

        // Add the notify manager checkbox
        $attributes = array();
        if (isset($this->notifymanager) && $this->notifymanager == true) {
            $attributes['checked'] = "checked";
        }
        if ($updateform) {
            $mform->addElement('checkbox', $prefix.'notifymanager', '', '', $attributes);
            $mform->setType($prefix.'notifymanager', PARAM_BOOL);
            $template_values['%'.$prefix.'notifymanager%'] = array('name'=>$prefix.'notifymanager', 'value'=>null);
        }
        $helpbutton = $OUTPUT->help_icon('notifymanager', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fline'));
        $templatehtml .= html_writer::tag('div', html_writer::tag('label', get_string('label:sendnoticetomanager', 'totara_program') . ' ' . $helpbutton, array('for' => $prefix.'notifymanager')), array('class' => 'flabel'));
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'notifymanager%', array('class' => 'fitem'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'notifymanager'} = (bool)$this->notifymanager;

        // Add the manager message
        $safe_managermessage = format_string($this->managermessage);
        if ($updateform) {
            $mform->addElement('textarea', $prefix.'managermessage', $safe_managermessage, array('cols'=>'40', 'rows'=>'5'));
            //$mform->disabledIf($prefix.'managermessage', $prefix.'notifymanager', 'notchecked');
            $mform->setType($prefix.'managermessage', PARAM_TEXT);
            $template_values['%'.$prefix.'managermessage%'] = array('name'=>$prefix.'managermessage', 'value'=>null);
        }
        $helpbutton = $OUTPUT->help_icon('managermessage', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fline'));
        $templatehtml .= html_writer::tag('div', html_writer::tag('label', get_string('label:noticeformanager', 'totara_program') . ' ' . $helpbutton, array('for' => $prefix.'managermessage')), array('class' => 'flabel'));
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'managermessage%', array('class' => 'fitem'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'managermessage'} = $safe_managermessage;

        return $templatehtml;
    }

    /**
     * Defines the time picker form elements that several message types use
     *
     * @param <type> $mform
     * @param <type> $template_values
     * @param <type> $formdataobject
     * @param <type> $updateform
     * @return <type>
     */
    public function get_generic_trigger_fields_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;
        $prefix = $this->get_message_prefix();

        $templatehtml = '';

        // Add the trigger period selection group
        if ($updateform) {

            $mform->addElement('text', $prefix.'triggernum', '', array('size'=>4, 'maxlength'=>3));
            $mform->setType($prefix.'triggernum', PARAM_INT);
            $mform->setDefault($prefix.'triggernum', '1');
            //$mform->addRule($prefix.'triggernum', get_string('required'), 'required', null, 'server');

            $timeallowanceoptions = program_utilities::get_standard_time_allowance_options();
            $mform->addElement('select', $prefix.'triggerperiod', '', $timeallowanceoptions);
            $mform->setType($prefix.'triggerperiod', PARAM_INT);

            $template_values['%'.$prefix.'triggernum%'] = array('name'=>$prefix.'triggernum', 'value'=>null);
            $template_values['%'.$prefix.'triggerperiod%'] = array('name'=>$prefix.'triggerperiod', 'value'=>null);
        }
        $helpbutton = $OUTPUT->help_icon('trigger', 'totara_program');
        $templatehtml .= html_writer::start_tag('div', array('class' => 'fline'));
        $templatehtml .= html_writer::tag('div', html_writer::tag('label', get_string('label:trigger', 'totara_program') . ' ' . $helpbutton, array('for' => $prefix.'triggernum')), array('class' => 'flabel'));
        $templatehtml .= html_writer::tag('div', '%'.$prefix.'triggernum% %' . $prefix . 'triggerperiod% ' . $this->triggereventstr, array('class' => 'fitem'));
        $templatehtml .= html_writer::end_tag('div');
        $formdataobject->{$prefix.'triggernum'} = $this->triggernum;
        $formdataobject->{$prefix.'triggerperiod'} = $this->triggerperiod;

        return $templatehtml;
    }

    /**
     * Defines the fieldset button elements that several message types use
     *
     * @param <type> $mform
     * @param <type> $template_values
     * @param <type> $formdataobject
     * @param <type> $updateform
     * @return <type>
     */
    public function get_generic_message_buttons_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {

        $prefix = $this->get_message_prefix();

        $templatehtml = '';

        $templatehtml .= html_writer::start_tag('div', array('class' => 'messagebuttons'));

        // Add the move up button for this message
        if ($updateform) {
            $attributes = array();
            $attributes['class'] = 'btn-cancel moveup fieldsetbutton';
            if (isset($this->isfirstmessage)) {
                $attributes['disabled'] = 'disabled';
                $attributes['class'] .= 'disabled';
            }
            $mform->addElement('submit', $prefix.'moveup', get_string('moveup', 'totara_program'), $attributes);
            $template_values['%'.$prefix.'moveup%'] = array('name'=>$prefix.'moveup', 'value'=>null);
        }
        $templatehtml .= '%'.$prefix.'moveup%'."\n";

        // Add the move down button for this message
        if ($updateform) {
            $attributes = array();
            $attributes['class'] = 'btn-cancel movedown fieldsetbutton';
            if (isset($this->islastmessage)) {
                $attributes['disabled'] = 'disabled';
                $attributes['class'] .= 'disabled';
            }
            $mform->addElement('submit', $prefix.'movedown', get_string('movedown', 'totara_program'), $attributes);
            $template_values['%'.$prefix.'movedown%'] = array('name'=>$prefix.'movedown', 'value'=>null);
        }
        $templatehtml .= '%'.$prefix.'movedown%'."\n";

         // Add the delete button for this message
        if ($updateform) {
            $mform->addElement('submit', $prefix.'delete', get_string('delete', 'totara_program'),
                array('class'=>"btn-cancel delete fieldsetbutton deletedmessagebutton"));
            $template_values['%'.$prefix.'delete%'] = array('name'=>$prefix.'delete', 'value'=>null);
        }
        $templatehtml .= '%'.$prefix.'delete%'."\n";

        $templatehtml .= html_writer::end_tag('div');

        return $templatehtml;
    }
}

abstract class prog_noneventbased_message extends prog_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {
        global $CFG;

        parent::__construct($programid, $messageob, $uniqueid);

        $studentmessagedata = array(
            'roleid'            => $this->studentrole,
            'subject'           => $this->messagesubject,
            'fullmessage'       => $this->mainmessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->studentmessagedata = new prog_message_data($studentmessagedata);

    }

    /**
     * Sends the message to the specified recipient
     *
     * @param object $recipient A user record
     * @param object $sender An optional user record
     * @param array $options An optional array containing options for the message
     * @return bool Success
     */
    public function send_message($recipient, $sender=null, $options=array()) {
        global $CFG, $USER;

        $result = true;

        $manager = totara_get_manager($recipient->id);
        $this->set_replacementvars($recipient, $options);

        // Verify the $sender of the email.
        if ($sender == null) { //null check on $sender, default to manager or no-reply accordingly
            $sender = ($manager && $manager->id == $USER->id) ? $manager : core_user::get_support_user();
        } else if ($sender->id == $USER->id) { //make sure $sender is currently logged in
            $sender = $USER;
        } else if ($manager && $USER->id == $manager->id) { //$sender is not logged in, see if it is their manager
            $sender = $manager;
        } else { // Last option, the no-reply address.
            $sender = core_user::get_support_user();
        }

        // Send the message to the learner.
        $studentdata = new stdClass();
        $studentdata->userto = $recipient;
        $studentdata->userfrom = $sender;
        $studentdata->subject = $this->replacevars($this->studentmessagedata->subject);
        $studentdata->fullmessage = $this->replacevars($this->studentmessagedata->fullmessage);
        $studentdata->contexturl = $this->studentmessagedata->contexturl;
        $studentdata->icon = 'program-regular';
        $studentdata->msgtype = TOTARA_MSG_TYPE_PROGRAM;
        $result = $result && tm_alert_send($studentdata);

        // Send the message to the manager.
        if ($result && $this->notifymanager && $manager) {
            $managerdata = new stdClass();
            $managerdata->userto = $manager;
            //ensure the message is actually coming from $user, default to support
            $managerdata->userfrom = ($USER->id == $recipient->id) ? $recipient : core_user::get_support_user();
            $managerdata->subject = $this->replacevars($this->managermessagedata->subject);
            $managerdata->fullmessage = $this->replacevars($this->managermessagedata->fullmessage);
            $managerdata->contexturl = $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid.'&amp;userid='.$recipient->id;
            $managerdata->icon = 'program-regular';
            $managerdata->msgtype = TOTARA_MSG_TYPE_PROGRAM;
            $result = $result && tm_alert_send($managerdata);
        }

        return $result;
    }

    public function get_message_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;
        $prefix = $this->get_message_prefix();

        $helpbutton = $OUTPUT->help_icon($this->helppage, 'totara_program');

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('fieldset', array('id' => $prefix, 'class' => 'message surround'));
        $templatehtml .= html_writer::tag('legend', $this->fieldsetlegend . ' ' . $helpbutton);

        $templatehtml .= $this->get_generic_hidden_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_message_buttons_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_basic_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_manager_fields_template($mform, $template_values, $formdataobject, $updateform);

        $templatehtml .= html_writer::end_tag('fieldset');

        return $templatehtml;
    }
}


/**
 * Abstract class representing a standard message type which allows an event
 * to be specified as a point in time before/after which the message will be
 * sent
 */
abstract class prog_eventbased_message extends prog_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {
        global $CFG;

        parent::__construct($programid, $messageob, $uniqueid);

        $studentmessagedata = array(
            'roleid'            => $this->studentrole,
            'subject'           => $this->messagesubject,
            'fullmessage'       => $this->mainmessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->studentmessagedata = new prog_message_data($studentmessagedata);

    }

    public function save_message() {
        global $DB;
        // check if the trigger time has changed and delete all message logs for
        // this message if so
        if ($this->id > 0) { // if this message already exists in the database
            $triggertime = $DB->get_field('prog_message', 'triggertime', array('id' => $this->id));
            if ($triggertime != $this->triggertime) {
                $DB->delete_records('prog_messagelog', array('messageid' => $this->id));
            }
        }

        return parent::save_message();
    }

    /**
     * Sends the message to the specified recipient
     *
     * @param object $recipient A user record
     * @param object $sender An optional user record
     * @param array $options An optional array containing options for the message
     * @return bool Success
     */
    public function send_message($recipient, $sender=null, $options=array()) {
        global $CFG, $DB, $USER;

        $result = true;

        $coursesetid = isset($options['coursesetid']) ? $options['coursesetid'] : 0;
        // Only send the message if it has not already been sent to the recipient.
        if ($DB->get_record('prog_messagelog', array('messageid' => $this->id, 'userid' => $recipient->id, 'coursesetid' => $coursesetid), 'id', IGNORE_MULTIPLE)) {
            return true;
        }

        $manager = totara_get_manager($recipient->id);
        $this->set_replacementvars($recipient, $options);

        //verify the $sender of the email
        if ($sender == null) { //null check on $sender, default to manager or no-reply accordingly
            $sender = ($manager && $manager->id == $USER->id) ? $manager : core_user::get_support_user();
        } else if ($sender->id == $USER->id) { //make sure $sender is currently logged in
            $sender = $USER;
        } else if ($manager && $USER->id == $manager->id) { //$sender is not logged in, see if it is their manager
            $sender = $manager;
        } else { //last option, the no-reply address
            $sender = core_user::get_support_user();
        }

        // send the message to the learner
        $studentdata = new stdClass();
        $studentdata->userto = $recipient;
        $studentdata->userfrom = $sender;
        $studentdata->subject = $this->replacevars($this->studentmessagedata->subject);
        $studentdata->fullmessage = $this->replacevars($this->studentmessagedata->fullmessage);
        $studentdata->contexturl = $this->studentmessagedata->contexturl;
        $studentdata->icon = 'program-regular';
        $studentdata->msgtype = TOTARA_MSG_TYPE_PROGRAM;
        $result = $result && tm_alert_send($studentdata);

        // if the message was sent, add a record to the message log to
        // prevent it from being sent again
        if ($result) {
            $ob = new stdClass();
            $ob->messageid = $this->id;
            $ob->userid = $recipient->id;
            $ob->coursesetid = $coursesetid;
            $ob->timeissued = time();
            $DB->insert_record('prog_messagelog', $ob);
        }

        // send the message to the manager
        if ($result && $this->notifymanager && $manager) {
            $managerdata = new stdClass();
            $managerdata->userto = $manager;
            //ensure the message is actually coming from $user, default to support
            $managerdata->userfrom = ($USER->id == $recipient->id) ? $recipient : core_user::get_support_user();
            $managerdata->subject = $this->replacevars($this->managermessagedata->subject);
            $managerdata->fullmessage = $this->replacevars($this->managermessagedata->fullmessage);
            $managerdata->contexturl = $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid.'&amp;userid='.$recipient->id;
            $managerdata->icon = 'program-regular';
            $managerdata->msgtype = TOTARA_MSG_TYPE_PROGRAM;
            $result = $result && tm_alert_send($managerdata);
        }

        return $result;
    }

    public function get_message_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;
        $prefix = $this->get_message_prefix();

        $helpbutton = $OUTPUT->help_icon($this->helppage, 'totara_program');

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('fieldset', array('id' => $prefix, 'class' => 'message surround'));
        $templatehtml .= html_writer::tag('legend', $this->fieldsetlegend . ' ' . $helpbutton);

        $templatehtml .= $this->get_generic_hidden_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_message_buttons_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_trigger_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_basic_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_manager_fields_template($mform, $template_values, $formdataobject, $updateform);

        $templatehtml .= html_writer::end_tag('fieldset');

        return $templatehtml;
    }
}

class prog_enrolment_message extends prog_noneventbased_message {

   public function __construct($programid, $messageob=null, $uniqueid=null) {
        global $CFG;

        parent::__construct($programid, $messageob, $uniqueid);

        $this->messagetype = MESSAGETYPE_ENROLMENT;
        $this->helppage = 'enrolmentmessage';
        $this->sortorder = 1;
        $this->fieldsetlegend = get_string('legend:enrolmentmessage', 'totara_program');

        $managermessagedata = array(
            'roleid'            => $this->managerrole,
            'subject'           => get_string('learnerenrolled', 'totara_program'),
            'fullmessage'       => $this->managermessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->managermessagedata = new prog_message_data($managermessagedata);
    }

    public function get_message_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;
        $prefix = $this->get_message_prefix();

        $helpbutton = $OUTPUT->help_icon($this->helppage, 'totara_program');

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('fieldset', array('id' => $prefix, 'class' => 'message surround'));
        $templatehtml .= html_writer::tag('legend', $this->fieldsetlegend . ' ' . $helpbutton);

        $templatehtml .= $this->get_generic_hidden_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_message_buttons_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_basic_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_manager_fields_template($mform, $template_values, $formdataobject, $updateform);

        $templatehtml .= html_writer::end_tag('fieldset');

        return $templatehtml;
    }
}

class prog_exception_report_message extends prog_noneventbased_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {
        global $CFG;

        parent::__construct($programid, $messageob, $uniqueid);

        $this->messagetype = MESSAGETYPE_EXCEPTION_REPORT;
        $this->helppage = 'exceptionreportmessage';
        $this->sortorder = 2;
        $this->fieldsetlegend = get_string('legend:exceptionreportmessage', 'totara_program');

        $studentmessagedata = array(
            'roleid'            => $this->studentrole,
            'subject'           => $this->messagesubject,
            'fullmessage'       => $this->mainmessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/exceptions.php?id='.$this->programid,
            'contexturlname'    => get_string('viewexceptions', 'totara_program'),
        );

        $this->studentmessagedata = new prog_message_data($studentmessagedata);
    }

    public function get_message_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;
        $prefix = $this->get_message_prefix();

        $helpbutton = $OUTPUT->help_icon($this->helppage, 'totara_program');

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('fieldset', array('id' => $prefix, 'class' => 'message surround'));
        $templatehtml .= html_writer::tag('legend', $this->fieldsetlegend . ' ' . $helpbutton);

        $templatehtml .= $this->get_generic_hidden_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_message_buttons_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_basic_fields_template($mform, $template_values, $formdataobject, $updateform);

        $templatehtml .= html_writer::end_tag('fieldset');

        return $templatehtml;
    }
}

class prog_unenrolment_message extends prog_noneventbased_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {
        global $CFG;

        parent::__construct($programid, $messageob, $uniqueid);

        $this->messagetype = MESSAGETYPE_UNENROLMENT;
        $this->helppage = 'unenrolmentmessage';
        $this->fieldsetlegend = get_string('legend:unenrolmentmessage', 'totara_program');

        $studentmessagedata = array(
            'roleid'            => $this->studentrole,
            'subject'           => $this->messagesubject,
            'fullmessage'       => $this->mainmessage,
        );

        $this->studentmessagedata = new prog_message_data($studentmessagedata);

        $managermessagedata = array(
            'roleid'            => $this->managerrole,
            'subject'           => get_string('learnerunenrolled', 'totara_program'),
            'fullmessage'       => $this->managermessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->managermessagedata = new prog_message_data($managermessagedata);
    }
}

class prog_program_completed_message extends prog_noneventbased_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {
        global $CFG;

        parent::__construct($programid, $messageob, $uniqueid);

        $this->messagetype = MESSAGETYPE_PROGRAM_COMPLETED;
        $this->helppage = 'programcompletedmessage';
        $this->fieldsetlegend = get_string('legend:programcompletedmessage', 'totara_program');

        $managermessagedata = array(
            'roleid'            => $this->managerrole,
            'subject'           => get_string('programcompleted', 'totara_program'),
            'fullmessage'       => $this->managermessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->managermessagedata = new prog_message_data($managermessagedata);
    }
}

class prog_courseset_completed_message extends prog_noneventbased_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {
        global $CFG;

        parent::__construct($programid, $messageob, $uniqueid);

        $this->messagetype = MESSAGETYPE_COURSESET_COMPLETED;
        $this->helppage = 'coursesetcompletedmessage';
        $this->fieldsetlegend = get_string('legend:coursesetcompletedmessage', 'totara_program');

        $managermessagedata = array(
            'roleid'            => $this->managerrole,
            'subject'           => get_string('coursesetcompleted', 'totara_program'),
            'fullmessage'       => $this->managermessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->managermessagedata = new prog_message_data($managermessagedata);
    }
}

class prog_program_due_message extends prog_eventbased_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {
        global $CFG;

        parent::__construct($programid, $messageob, $uniqueid);

        $this->messagetype = MESSAGETYPE_PROGRAM_DUE;
        $this->helppage = 'programduemessage';
        $this->fieldsetlegend = get_string('legend:programduemessage', 'totara_program');
        $this->triggereventstr = get_string('beforeprogramisdue', 'totara_program');

        $managermessagedata = array(
            'roleid'            => $this->managerrole,
            'subject'           => get_string('programdue', 'totara_program'),
            'fullmessage'       => $this->managermessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->managermessagedata = new prog_message_data($managermessagedata);
    }
}

class prog_courseset_due_message extends prog_eventbased_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {
        global $CFG;

        parent::__construct($programid, $messageob, $uniqueid);

        $this->messagetype = MESSAGETYPE_COURSESET_DUE;
        $this->helppage = 'coursesetduemessage';
        $this->fieldsetlegend = get_string('legend:coursesetduemessage', 'totara_program');
        $this->triggereventstr = get_string('beforesetisdue', 'totara_program');

        $managermessagedata = array(
            'roleid'            => $this->managerrole,
            'subject'           => get_string('coursesetdue', 'totara_program'),
            'fullmessage'       => $this->managermessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->managermessagedata = new prog_message_data($managermessagedata);
    }
}

class prog_program_overdue_message extends prog_eventbased_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {

        parent::__construct($programid, $messageob, $uniqueid);
        global $CFG;

        $this->messagetype = MESSAGETYPE_PROGRAM_OVERDUE;
        $this->helppage = 'programoverduemessage';
        $this->fieldsetlegend = get_string('legend:programoverduemessage', 'totara_program');
        $this->triggereventstr = get_string('afterprogramisdue', 'totara_program');

        $managermessagedata = array(
            'roleid'            => $this->managerrole,
            'subject'           => get_string('programoverdue', 'totara_program'),
            'fullmessage'       => $this->managermessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->managermessagedata = new prog_message_data($managermessagedata);
    }
}

class prog_courseset_overdue_message extends prog_eventbased_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {

        parent::__construct($programid, $messageob, $uniqueid);
        global $CFG;

        $this->messagetype = MESSAGETYPE_COURSESET_OVERDUE;
        $this->helppage = 'coursesetoverduemessage';
        $this->fieldsetlegend = get_string('legend:coursesetoverduemessage', 'totara_program');
        $this->triggereventstr = get_string('aftersetisdue', 'totara_program');

        $managermessagedata = array(
            'roleid'            => $this->managerrole,
            'subject'           => get_string('coursesetoverdue', 'totara_program'),
            'fullmessage'       => $this->managermessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->managermessagedata = new prog_message_data($managermessagedata);
    }
}

class prog_learner_followup_message extends prog_eventbased_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {

        parent::__construct($programid, $messageob, $uniqueid);
        global $CFG;

        $this->messagetype = MESSAGETYPE_LEARNER_FOLLOWUP;
        $this->helppage = 'learnerfollowupmessage';
        $this->fieldsetlegend = get_string('legend:learnerfollowupmessage', 'totara_program');
        $this->triggereventstr = get_string('afterprogramiscompleted', 'totara_program');
        $this->notifymanager = false;
    }

    public function get_message_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;
        $prefix = $this->get_message_prefix();

        $helpbutton = $OUTPUT->help_icon($this->helppage, 'totara_program');

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('fieldset', array('id' => $prefix, 'class' => 'message surround'));
        $templatehtml .= html_writer::tag('legend', $this->fieldsetlegend . ' ' . $helpbutton);

        $templatehtml .= $this->get_generic_hidden_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_message_buttons_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_trigger_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_basic_fields_template($mform, $template_values, $formdataobject, $updateform);

        $templatehtml .= html_writer::end_tag('fieldset');

        return $templatehtml;
    }
}

/**
 * The prog_extension_request_message class is a little different from most
 * messages because it cannot be edited by a program creator. It is a fixed
 * message that gets sent when a learner requests an extension to a program.
 * The message is only sent to the learner's manager.
 */
class prog_extension_request_message extends prog_noneventbased_message {

    public function __construct($programid, $userid, $messageob=null, $uniqueid=null, $data) {
        global $CFG;

        parent::__construct($programid, $messageob, $uniqueid);

        $this->messagetype = MESSAGETYPE_EXTENSION_REQUEST;
        $this->helppage = 'extensionrequestmessage';
        $this->fieldsetlegend = get_string('legend:extensionrequestmessage', 'totara_program');
        $this->userid = $userid;
        $this->extensiondata = $data;

        $managermessagedata = array(
            'roleid'            => $this->managerrole,
            'subject'           => $this->messagesubject,
            'fullmessage'       => $this->mainmessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/manageextensions.php?userid='.$this->userid,
            'contexturlname'    => get_string('manageextensionrequests', 'totara_program'),
        );

        $this->managermessagedata = new prog_message_data($managermessagedata);
    }

    public function send_message($recipient, $sender=null, $options=array()) {
        global $CFG, $USER;

        //ensure that $sender is defined and logged in, default to support
        if ($sender == null || ($USER->id != $sender->id)) {
            $sender == core_user::get_support_user();
        }

        // send the message to the Manager
        $managerdata = new stdClass();
        $managerdata->userto = $recipient;
        $managerdata->userfrom = $sender;
        $managerdata->subject = $this->replacevars($this->managermessagedata->subject);
        $managerdata->fullmessage = $this->replacevars($this->managermessagedata->fullmessage);

        if (!empty($this->managermessagedata->acceptbutton)) {
            $onaccept = new stdClass();
            $onaccept->action = 'prog_extension';
            $onaccept->text = $this->managermessagedata->accepttext;
            $onaccept->data = array();
            $onaccept->data['userid'] = $this->userid;
            $onaccept->data['extensionid'] = $this->extensiondata['extensionid'];
            $onaccept->data['programid'] = $this->programid;
            $onaccept->acceptbutton = $this->managermessagedata->acceptbutton;
            $managerdata->onaccept = $onaccept;
        }
        if (!empty($this->managermessagedata->rejectbutton)) {
            $onreject = new stdClass();
            $onreject->action = 'prog_extension';
            $onreject->text = $this->managermessagedata->rejecttext;
            $onreject->data = array();
            $onreject->data['userid'] = $this->userid;
            $onreject->data['extensionid'] = $this->extensiondata['extensionid'];
            $onreject->data['programid'] = $this->programid;
            $onreject->rejectbutton = $this->managermessagedata->rejectbutton;
            $managerdata->onreject = $onreject;
        }

        if (!empty($this->managermessagedata->infobutton)) {
            $oninfo = new stdClass();
            $oninfo->action = 'prog_extension';
            $oninfo->text = $this->managermessagedata->infotext;
            $oninfo->data = array('userid' => $this->userid);
            $oninfo->data['redirect'] = $this->managermessagedata->contexturl;
            $oninfo->infobutton = $this->managermessagedata->infobutton;
            $managerdata->oninfo = $oninfo;
        }

        $result = tm_task_send($managerdata);

        return $result;
    }

    public function get_message_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;
        $prefix = $this->get_message_prefix();

        $helpbutton = $OUTPUT->help_icon($this->helppage, 'totara_program');

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('fieldset', array('id' => $prefix, 'class' => 'message surround'));
        $templatehtml .= html_writer::tag('legend', $this->fieldsetlegend . ' ' . $helpbutton);
        $templatehtml .= $this->get_generic_hidden_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_message_buttons_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_basic_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= html_writer::end_tag('fieldset');

        return $templatehtml;
    }
}


/**
 * Certifciation messages
 *
 * @author jonathans@catalyst-eu.net
 *
 */

class prog_recert_windowopen_message extends prog_eventbased_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {

        parent::__construct($programid, $messageob, $uniqueid);
        global $CFG;

        $this->messagetype = MESSAGETYPE_RECERT_WINDOWOPEN;
        $this->helppage = 'recertwindowopenmessage';
        $this->fieldsetlegend = get_string('legend:recertwindowopenmessage', 'totara_certification');
        $managermessagedata = array(
            'roleid'            => $this->managerrole,
            'subject'           => get_string('recertwindowopen', 'totara_certification'),
            'fullmessage'       => $this->managermessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->managermessagedata = new prog_message_data($managermessagedata);
    }

    public function get_message_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;
        $prefix = $this->get_message_prefix();

        $helpbutton = $OUTPUT->help_icon($this->helppage, 'totara_certification');

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('fieldset', array('id' => $prefix, 'class' => 'message surround'));
        $templatehtml .= html_writer::tag('legend', $this->fieldsetlegend . ' ' . $helpbutton);

        $templatehtml .= $this->get_generic_hidden_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_message_buttons_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_basic_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_manager_fields_template($mform, $template_values, $formdataobject, $updateform);

        $templatehtml .= html_writer::end_tag('fieldset');

        return $templatehtml;
    }
}


class prog_recert_windowdueclose_message extends prog_eventbased_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {

        parent::__construct($programid, $messageob, $uniqueid);
        global $CFG;

        $this->messagetype = MESSAGETYPE_RECERT_WINDOWDUECLOSE;
        $this->helppage = 'recertwindowdueclosemessage';
        $this->fieldsetlegend = get_string('legend:recertwindowdueclosemessage', 'totara_certification');
        $this->triggereventstr = get_string('beforewindowduetoclose', 'totara_certification');
        $managermessagedata = array(
            'roleid'            => $this->managerrole,
            'subject'           => get_string('recertwindowdueclose', 'totara_certification'),
            'fullmessage'       => $this->managermessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->managermessagedata = new prog_message_data($managermessagedata);
    }

    public function get_message_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;
        $prefix = $this->get_message_prefix();

        $helpbutton = $OUTPUT->help_icon($this->helppage, 'totara_certification');

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('fieldset', array('id' => $prefix, 'class' => 'message surround'));
        $templatehtml .= html_writer::tag('legend', $this->fieldsetlegend . ' ' . $helpbutton);

        $templatehtml .= $this->get_generic_hidden_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_message_buttons_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_trigger_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_basic_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_manager_fields_template($mform, $template_values, $formdataobject, $updateform);

        $templatehtml .= html_writer::end_tag('fieldset');

        return $templatehtml;
    }
}


class prog_recert_failrecert_message extends prog_eventbased_message {

    public function __construct($programid, $messageob=null, $uniqueid=null) {

        parent::__construct($programid, $messageob, $uniqueid);
        global $CFG;

        $this->messagetype = MESSAGETYPE_RECERT_FAILRECERT;
        $this->helppage = 'recertfailrecertmessage';
        $this->fieldsetlegend = get_string('legend:recertfailrecertmessage', 'totara_certification');
        $managermessagedata = array(
            'roleid'            => $this->managerrole,
            'subject'           => get_string('recertfailrecert', 'totara_certification'),
            'fullmessage'       => $this->managermessage,
            'contexturl'        => $CFG->wwwroot.'/totara/program/view.php?id='.$this->programid,
            'contexturlname'    => get_string('launchprogram', 'totara_program'),
        );

        $this->managermessagedata = new prog_message_data($managermessagedata);
    }

    public function get_message_form_template(&$mform, &$template_values, &$formdataobject, $updateform=true) {
        global $OUTPUT;
        $prefix = $this->get_message_prefix();

        $helpbutton = $OUTPUT->help_icon($this->helppage, 'totara_certification');

        $templatehtml = '';
        $templatehtml .= html_writer::start_tag('fieldset', array('id' => $prefix, 'class' => 'message surround'));
        $templatehtml .= html_writer::tag('legend', $this->fieldsetlegend . ' ' . $helpbutton);

        $templatehtml .= $this->get_generic_hidden_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_message_buttons_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_basic_fields_template($mform, $template_values, $formdataobject, $updateform);
        $templatehtml .= $this->get_generic_manager_fields_template($mform, $template_values, $formdataobject, $updateform);

        $templatehtml .= html_writer::end_tag('fieldset');

        return $templatehtml;
    }
}
