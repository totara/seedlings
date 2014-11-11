<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas
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
 * @author Piers Harding <piers@catalyst.net.nz>
 * @author Luis Rodrigues
 * @package totara
 * @subpackage message
 */

/**
 * messagelib.php - Contains generic messaging functions for the message system
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/message/lib.php');
require_once($CFG->dirroot.'/totara/message/lib.php');
require_once($CFG->libdir.'/eventslib.php');

if (!isset($CFG->message_contacts_refresh)) {  // Refresh the contacts list every 60 seconds
    $CFG->message_contacts_refresh = 60;
}
if (!isset($CFG->message_chat_refresh)) {      // Look for new comments every 5 seconds
    $CFG->message_chat_refresh = 5;
}
if (!isset($CFG->message_offline_time)) {
    $CFG->message_offline_time = 300;
}


// message status constants
define('TOTARA_MSG_STATUS_UNDECIDED', 0);
define('TOTARA_MSG_STATUS_OK', 1);
define('TOTARA_MSG_STATUS_NOTOK', 2);

// message type constants
define('TOTARA_MSG_TYPE_UNKNOWN', 0);
define('TOTARA_MSG_TYPE_COURSE', 1);
define('TOTARA_MSG_TYPE_FORUM', 2);
define('TOTARA_MSG_TYPE_GRADING', 3);
define('TOTARA_MSG_TYPE_CHAT', 4);
define('TOTARA_MSG_TYPE_LESSON', 5);
define('TOTARA_MSG_TYPE_QUIZ', 6);
define('TOTARA_MSG_TYPE_FACE2FACE', 7);
define('TOTARA_MSG_TYPE_SURVEY', 8);
define('TOTARA_MSG_TYPE_SCORM', 9);
define('TOTARA_MSG_TYPE_LINK', 10);
define('TOTARA_MSG_TYPE_PROGRAM', 11);

// Message email constants (used to override default message processor behaviour):
// Send email via processor as normal (default)
define('TOTARA_MSG_EMAIL_YES', 0);
// Override to prevent the message sending an email (even if the user has asked for it)
define('TOTARA_MSG_EMAIL_NO', 1);
// Prevent the email processor sending an email, but manually send it using email_to_user() directly
// this is useful if you need to send a message and you want the email to have an attachment (which
// is not currently supported by normal messages).
define('TOTARA_MSG_EMAIL_MANUAL', 2);

// message type shortnames
global $TOTARA_MESSAGE_TYPES;
$TOTARA_MESSAGE_TYPES = array(
    TOTARA_MSG_TYPE_UNKNOWN => 'unknown',
    TOTARA_MSG_TYPE_COURSE => 'course',
    TOTARA_MSG_TYPE_PROGRAM => 'program',
    TOTARA_MSG_TYPE_FORUM => 'forum',
    TOTARA_MSG_TYPE_GRADING => 'grading',
    TOTARA_MSG_TYPE_CHAT => 'chat',
    TOTARA_MSG_TYPE_LESSON => 'lesson',
    TOTARA_MSG_TYPE_QUIZ => 'quiz',
    TOTARA_MSG_TYPE_FACE2FACE => 'face2face',
    TOTARA_MSG_TYPE_SURVEY => 'survey',
    TOTARA_MSG_TYPE_SCORM => 'scorm',
    TOTARA_MSG_TYPE_LINK => 'link',
);

// list of supported categories
global $TOTARA_MESSAGE_CATEGORIES;
$TOTARA_MESSAGE_CATEGORIES = array_merge(array_keys($TOTARA_MESSAGE_TYPES),
        array('facetoface', 'learningplan', 'objective', 'resource'));

// message urgency constants
define('TOTARA_MSG_URGENCY_LOW', -4);
define('TOTARA_MSG_URGENCY_NORMAL', 0);
define('TOTARA_MSG_URGENCY_URGENT', 4);


/**
 * Called when a message provider wants to send a message.
 * This functions checks the user's processor configuration to send the given type of message,
 * then tries to send it.
 *
 * Required parameter $eventdata structure:
 *  modulename     -
 *  userfrom object the user sending the message
 *  userto object the message recipient
 *  subject string the message subject
 *  fullmessage - the full message in a given format
 *  fullmessageformat  - the format if the full message (FORMAT_MOODLE, FORMAT_HTML, ..)
 *  fullmessagehtml  - the full version (the message processor will choose with one to use)
 *  smallmessage - the small version of the message
 *  contexturl - if this is a alert then you can specify a url to view the event. For example the forum post the user is being notified of.
 *  contexturlname - the display text for contexturl
 *  msgstatus - int Message Status see TOTARA_MSG_STATUS* constants
 *  msgtype - int Message Type see TOTARA_MSG_TYPE* constants
 *
 * @param object $eventdata information about the message (modulename, userfrom, userto, ...)
 * @return boolean success
 */
function tm_message_send($eventdata) {
    global $CFG, $DB, $USER;

    if (empty($CFG->messaging)) {
        // Messaging currently disabled
        return true;
    }

    if (is_int($eventdata->userto)) {
        debugging('tm_message_send() userto is a user ID when it should be a user object', DEBUG_DEVELOPER);
        $eventdata->userto = $DB->get_record('user', array('id' => $eventdata->userto), '*', MUST_EXIST);
    }

    if (is_int($eventdata->userfrom)) {
        debugging('tm_message_send() userfrom is a user ID when it should be a user object', DEBUG_DEVELOPER);
        $eventdata->userfrom = $DB->get_record('user', array('id' => $eventdata->userfrom), '*', MUST_EXIST);
    }

    // must have msgtype, urgency and msgstatus
    if (!isset($eventdata->msgstatus)) {
        debugging('tm_message_send() msgstatus not set', DEBUG_DEVELOPER);
        return false;
    }
    if (!isset($eventdata->urgency)) {
        debugging('tm_message_send() urgency not set', DEBUG_DEVELOPER);
        return false;
    }
    if (!isset($eventdata->msgtype)) {
        debugging('tm_message_send() msgtype not set', DEBUG_DEVELOPER);
        return false;
    }

    // map alert to notification
    if (!empty($eventdata->alert)) {
        $eventdata->notification = $eventdata->alert;
    }
    else if (empty($eventdata->notification)) {
        $eventdata->notification = 0;
    }

    if (!isset($eventdata->smallmessage)) {
        $eventdata->smallmessage = null;
    }

    if (!isset($eventdata->contexturl)) {
        $eventdata->contexturl = null;
    } else if ($eventdata->contexturl instanceof moodle_url) {
        $eventdata->contexturl = $eventdata->contexturl->out();
    }

    if (!isset($eventdata->contexturlname)) {
        $eventdata->contexturlname = null;
    }

    if (!isset($eventdata->timecreated)) {
        $eventdata->timecreated = time();
    }

    //after how long inactive should the user be considered logged off?
    if (isset($CFG->block_online_users_timetosee)) {
        $timetoshowusers = $CFG->block_online_users_timetosee * 60;
    } else {
        $timetoshowusers = 300;//5 minutes
    }

    // Work out if the user is logged in or not
    if ((time() - $timetoshowusers) < (isset($eventdata->userto->lastaccess) ? $eventdata->userto->lastaccess : 0)) {
        $userstate = 'loggedin';
    } else {
        $userstate = 'loggedoff';
    }

    // Find out what processors are defined currently
    // When a user doesn't have settings none gets return, if he doesn't want contact "" gets returned
    $preferencename = 'message_provider_'.$eventdata->component.'_'.$eventdata->name.'_'.$userstate;

    $processor = get_user_preferences($preferencename, null, $eventdata->userto->id);
    if (empty($processor)) { //this user never had a preference, save default
        tm_message_set_default_message_preferences($eventdata->userto);
    }

    // call core message processing - this will trigger either output_totara_task output_totara_alert
    $eventdata->savedmessageid = message_send($eventdata);

    if (!$eventdata->savedmessageid || $eventdata->savedmessageid == 0) {
        debugging('Error inserting message: '.var_export($eventdata, TRUE), DEBUG_DEVELOPER);
        return false;
    }

    return $eventdata->savedmessageid;
}

/**
 * Create the message metadata structure, which contains workflow information
 *
 * @param object $eventdata
 * @param int $processorid
 * @return $messageid
 */
function tm_insert_metadata($eventdata, $processorid) {
    global $DB;

    // check if metadata record already exists (from other message provider alert/task)
    if ($DB->record_exists('message_metadata', array('messageid' => $eventdata->savedmessageid))) {
      return $eventdata->savedmessageid;
    }

    // add the metadata record
    $eventdata->onaccept = isset($eventdata->onaccept) ? serialize($eventdata->onaccept) : null;
    $eventdata->onreject = isset($eventdata->onreject) ? serialize($eventdata->onreject) : null;
    $eventdata->oninfo = isset($eventdata->oninfo) ? serialize($eventdata->oninfo) : null;
    $eventdata->msgtype = isset($eventdata->msgtype) ? $eventdata->msgtype : TOTARA_MSG_TYPE_UNKNOWN;
    $eventdata->msgstatus = isset($eventdata->msgstatus) ? $eventdata->msgstatus : TOTARA_MSG_STATUS_UNDECIDED;
    $eventdata->urgency = isset($eventdata->urgency) ? $eventdata->urgency : TOTARA_MSG_URGENCY_NORMAL;

    if (isset($eventdata->icon)) {
        $eventdata->icon = clean_param($eventdata->icon, PARAM_FILE);
    }
    else {
        $eventdata->icon = 'default';
    }

    $metadata = new stdClass();
    $metadata->messageid        = $eventdata->savedmessageid;
    $metadata->msgtype          = $eventdata->msgtype;
    $metadata->msgstatus        = $eventdata->msgstatus;
    $metadata->urgency          = $eventdata->urgency;
    $metadata->processorid      = $processorid;
    $metadata->icon             = $eventdata->icon;
    $metadata->onaccept         = $eventdata->onaccept;
    $metadata->onreject         = $eventdata->onreject;
    $metadata->oninfo           = $eventdata->oninfo;
    $DB->insert_record('message_metadata', $metadata);
    return $eventdata->savedmessageid;
}

/**
 * send a alert
 *
 * Required parameter $alert structure:
 *  userfrom object the user sending the message - optional
 *  userto object the message recipient
 *  fullmessage
 *  msgtype
 *  msgstatus
 *  urgency
 *
 * @param object $eventdata information about the message (userfrom, userto, ...)
 * @return boolean success
 */
function tm_alert_send($eventdata) {
    global $CFG;

    if (empty($CFG->messaging)) {
        // Messaging currently disabled
        return true;
    }

    if (!isset($eventdata->userto)) {
        // cant send without a target user
        debugging('tm_alert_send() userto is not set', DEBUG_DEVELOPER);
        return false;
    }
    (!isset($eventdata->msgtype)) && $eventdata->msgtype = TOTARA_MSG_TYPE_UNKNOWN;
    (!isset($eventdata->msgstatus)) && $eventdata->msgstatus = TOTARA_MSG_STATUS_UNDECIDED;
    (!isset($eventdata->urgency)) && $eventdata->urgency = TOTARA_MSG_URGENCY_NORMAL;
    (!isset($eventdata->sendemail)) && $eventdata->sendemail = TOTARA_MSG_EMAIL_YES;

    $eventdata->component         = 'totara_message';
    $eventdata->name              = 'alert';
    if (empty($eventdata->userfrom)) {
        $eventdata->userfrom      = $eventdata->userto;
    }

    if (empty($eventdata->subject)) {
        $eventdata->subject       = '';
    }
    if (empty($eventdata->fullmessageformat)) {
        $eventdata->fullmessageformat = FORMAT_PLAIN;
    }
    if (empty($eventdata->fullmessagehtml)) {
        $eventdata->fullmessagehtml   = nl2br($eventdata->fullmessage);
    }
    $eventdata->notification = 1;

    if (!isset($eventdata->contexturl)) {
        $eventdata->contexturl     = '';
        $eventdata->contexturlname = '';
    }

    $result = tm_message_send($eventdata);

    //--------------------------------

    // Manually send the email using email_to_user(). This is necessary in cases where there is an attachment (which cannot be handled by the messaging system)
    // We still should observe their messaging email preferences.

    // We can't handle attachments when logged on
    $alertemailpref = get_user_preferences('message_provider_totara_message_alert_loggedoff', null, $eventdata->userto->id);
    if ($result && strpos($alertemailpref, 'email') !== false && $eventdata->sendemail == TOTARA_MSG_EMAIL_MANUAL) {

        $string_manager = get_string_manager();

        // Send alert email
        if (empty($eventdata->subject)) {
            $eventdata->subject = strlen($eventdata->fullmessage) > 80 ? substr($eventdata->fullmessage, 0, 78).'...' : $eventdata->fullmessage;
        }

        if ($eventdata->contexturl) {
            $eventdata->fullmessagehtml .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . $string_manager->get_string('viewdetailshere', 'totara_message', $eventdata->contexturl, $eventdata->userto->lang);
        }

        // Add footer to email
        $eventdata->fullmessagehtml .= html_writer::empty_tag('br') . html_writer::empty_tag('br') . $string_manager->get_string('alertfooter2', 'totara_message', $CFG->wwwroot."/message/edit.php", $eventdata->userto->lang);

        // Setup some more variables
        $fromaddress = !empty($eventdata->fromaddress) ? $eventdata->fromaddress : '';
        $attachment = !empty($eventdata->attachment) ? $eventdata->attachment : '';
        $attachmentname = !empty($eventdata->attachmentname) ? $eventdata->attachmentname : '';

        $userfrom = !empty($eventdata->fromemailuser) ? $eventdata->fromemailuser : $eventdata->userfrom;

        switch ($eventdata->userto->mailformat) {
            case FORMAT_MOODLE: // 0 is current user preference email plain format value
            case FORMAT_PLAIN:
                $fullmessagehtml = '';
                break;

            default: // FORMAT_HTML
                $fullmessagehtml = format_text($eventdata->fullmessagehtml, FORMAT_HTML);
                break;
        }

        $result = email_to_user(
            $eventdata->userto,
            $userfrom,
            $eventdata->subject,
            html_to_text($eventdata->fullmessage),
            $fullmessagehtml,
            $attachment,
            $attachmentname,
            true,
            $fromaddress
        );
    }

    //---------------------------------

    return $result;
}

/**
 * send a task
 *
 * Required parameter $eventdata structure:
 *  userfrom object the user sending the message - optional
 *  userto object the message recipient
 *  fullmessage
 *
 * @param object $task information about the message (userfrom, userto, ...)
 * @return boolean success
 */
function tm_task_send($eventdata) {
    global $CFG;

    if (empty($CFG->messaging)) {
        // Messaging currently disabled
        return true;
    }

    if (!isset($eventdata->userto)) {
        // cant send without a target user
        debugging('tm_task_send() userto is not set', DEBUG_DEVELOPER);
        return false;
    }
    (!isset($eventdata->msgtype)) && $eventdata->msgtype = TOTARA_MSG_TYPE_UNKNOWN;
    (!isset($eventdata->msgstatus)) && $eventdata->msgstatus = TOTARA_MSG_STATUS_UNDECIDED;
    (!isset($eventdata->urgency)) && $eventdata->urgency = TOTARA_MSG_URGENCY_NORMAL;
    (!isset($eventdata->sendemail)) && $eventdata->sendemail = TOTARA_MSG_EMAIL_YES;
    (!isset($eventdata->onaccept)) && $eventdata->onaccept = null;
    (!isset($eventdata->onreject)) && $eventdata->onreject = null;

    $eventdata->component         = 'totara_message';
    $eventdata->name              = 'task';
    if (!isset($eventdata->userfrom) || !$eventdata->userfrom) {
        $eventdata->userfrom      = $eventdata->userto;
    }

    if (!isset($eventdata->subject)) {
        $eventdata->subject       = '';
    }
    if (empty($eventdata->fullmessageformat)) {
        $eventdata->fullmessageformat = FORMAT_HTML;
    }
    if (empty($eventdata->fullmessagehtml)) {
        $eventdata->fullmessagehtml   = nl2br($eventdata->fullmessage);
    }
    $eventdata->notification = 1;

    if (!isset($eventdata->contexturl)) {
        $eventdata->contexturl     = '';
        $eventdata->contexturlname = '';
    }
    if (!empty($eventdata->contexturl)) {
        $eventdata->fullmessagehtml .= html_writer::empty_tag('br').html_writer::empty_tag('br').get_string('viewdetailshere', 'totara_message', $eventdata->contexturl);
    }

    $result = tm_message_send($eventdata);

    return $result;
}

/**
 * send a custom task that initiates a workflow based on
 * the contexturl set
 *
 * Required parameter $eventdata structure:
 *  userfrom object the user sending the message - optional
 *  userto object the message recipient
 *  subject
 *  fullmessage
 * Optional parameter $eventdata structure:
 *  acceptbutton - affirmative action button label text
 *  accepttext - text that goes on the affirmative action screen
 *
 *  example from plan approval:
 *          $event = new stdClass;
 *          $event->userfrom = $learner;
 *          $event->contexturl = $this->get_display_url();
 *          $event->contexturlname = $this->name;
 *          $event->icon = 'learningplan-request.png';
 *          $a = new stdClass;
 *          $a->learner = fullname($learner);
 *          $a->plan = s($this->name);
 *          $event->subject = get_string('plan-request-manager-short', 'totara_plan', $a);
 *          $event->fullmessage = get_string('plan-request-manager-long', 'totara_plan', $a);
 *          $event->acceptbutton = get_string('approve', 'totara_plan').' '.get_string('plan', 'totara_plan');
 *          $event->accepttext = get_string('approveplantext', 'totara_plan');
 *
 *
 * @param object $task information about the message (userfrom, userto, ...)
 * @return boolean success
 */
function tm_workflow_send($eventdata) {
    global $CFG;

    if (empty($CFG->messaging)) {
        // Messaging currently disabled
        return true;
    }

    if (!isset($eventdata->userto)) {
        // cant send without a target user
        debugging('tm_task_send() userto is not set', DEBUG_DEVELOPER);
        return false;
    }
    $eventdata->msgtype = TOTARA_MSG_TYPE_LINK; // tells us how to treat the display
    (!isset($eventdata->msgstatus)) && $eventdata->msgstatus = TOTARA_MSG_STATUS_UNDECIDED;
    (!isset($eventdata->urgency)) && $eventdata->urgency = TOTARA_MSG_URGENCY_NORMAL;

    $eventdata->component         = 'totara_message';
    $eventdata->name              = 'task';
    if (!isset($eventdata->userfrom) || !$eventdata->userfrom) {
        $eventdata->userfrom      = $eventdata->userto;
    }

    if (!isset($eventdata->subject)) {
        $eventdata->subject       = '';
    }
    if (empty($eventdata->fullmessageformat)) {
        $eventdata->fullmessageformat = FORMAT_PLAIN;
    }
    if (empty($eventdata->fullmessagehtml)) {
        $eventdata->fullmessagehtml   = nl2br($eventdata->fullmessage);
    }
    $eventdata->notification = 1;

    if (!isset($eventdata->contexturl)) {
        debugging('tm_message_workflow_send() must have have contexturl', DEBUG_DEVELOPER);
        return false;
    }
    if (!empty($eventdata->acceptbutton)) {
        $onaccept = new stdClass();
        $onaccept->action = 'plan';
        $onaccept->text = $eventdata->accepttext;
        $onaccept->data = $eventdata->data;
        $onaccept->acceptbutton = $eventdata->acceptbutton;
        $eventdata->onaccept = $onaccept;
    }
    if (!empty($eventdata->rejectbutton)) {
        $onreject = new stdClass();
        $onreject->action = 'plan';
        $onreject->text = $eventdata->rejecttext;
        $onreject->data = $eventdata->data;
        $onreject->rejectbutton = $eventdata->rejectbutton;
        $eventdata->onreject = $onreject;
    }
    if (!empty($eventdata->infobutton)) {
        $oninfo = new stdClass();
        $oninfo->action = 'plan';
        $oninfo->text = $eventdata->infotext;
        $oninfo->data = $eventdata->data;
        $oninfo->data['redirect'] = $eventdata->contexturl;
        $oninfo->infobutton = $eventdata->infobutton;
        $eventdata->oninfo = $oninfo;
    }

    if ($eventdata->contexturl) {
        $eventdata->fullmessagehtml .= html_writer::empty_tag('br').html_writer::empty_tag('br').get_string('viewdetailshere', 'totara_message', $eventdata->contexturl);
    }

    $result = tm_message_send($eventdata);

    return $result;
}

/**
 * Dismiss a message - this will move a message from message_working to message_read
 * without doing any of the workflow processing in message_metadata
 *
 * @param int $id message id
 * @return boolean success
 */
function tm_message_dismiss($id) {
    global $DB;

    $message = $DB->get_record('message', array('id' => $id));
    if ($message) {
        $result = tm_message_mark_message_read($message, time());
        return $result;
    }
    else {
        return false;
    }
}

/**
 * accept a task - this will invoke the task onaccept action
 * saved against this message
 *
 * @param int $id message id
 * @param string $reasonfordecision Reason for granting the request
 * @return boolean success
 */
function tm_message_task_accept($id, $reasonfordecision) {
    global $DB;

    $message = $DB->get_record('message', array('id' => $id));
    if ($message) {
        // get the event data
        $eventdata = totara_message_eventdata($id, 'onaccept');
        if (!empty($reasonfordecision)) {
            $eventdata->data['reasonfordecision'] = $reasonfordecision;
        }
        // grab the onaccept handler
        if (isset($eventdata->action)) {
            $plugin = tm_message_workflow_object($eventdata->action);
            if (!$plugin) {
                return false;
            }

            // run the onaccept phase
            $result = $plugin->onaccept($eventdata->data, $message);
        }

        // finally - dismiss this message as it has now been processed
        $result = tm_message_mark_message_read($message, time());
        return $result;
    }
    else {
        return false;
    }
}

/**
 * Redirect to a task's context URL
 *
 * @param int $id message id
 * @return boolean success
 */
function tm_message_task_link($id) {
    global $DB;

    $message = $DB->get_record('message', array('id' => $id));
    if ($message) {
        // get the event data
        $eventdata = totara_message_eventdata($id, 'oninfo');

        // grab the onaccept handler
        if (isset($eventdata->action)) {
            $plugin = tm_message_workflow_object($eventdata->action);
            if (!$plugin) {
                return false;
            }

            // run the onaccept phase
            $result = $plugin->onaccept($eventdata->data, $message);
        }

        // finally - dismiss this message as it has now been processed
        $result = tm_message_mark_message_read($message, time());
        return $result;
    }
    else {
        return false;
    }
}


/**
 * reject a task - this will invoke the task onreject action
 * saved against this message
 *
 * @param int $id message id
 * @param string $reasonfordecision Reason for rejecting the request
 * @return boolean success
 */
function tm_message_task_reject($id, $reasonfordecision) {
    global $DB;

    $message = $DB->get_record('message', array('id' => $id));
    if ($message) {
        // Get the event data.
        $eventdata = totara_message_eventdata($id, 'onreject');
        if (!empty($reasonfordecision)) {
            $eventdata->data['reasonfordecision'] = $reasonfordecision;
        }
        // grab the onaccept handler
        if (isset($eventdata->action)) {
            $plugin = tm_message_workflow_object($eventdata->action);
            if (!$plugin) {
                return false;
            }

            // run the onreject phase
            $result = $plugin->onreject($eventdata->data, $message);
        }

        // finally - dismiss this message as it has now been processed
        $result = tm_message_mark_message_read($message, time());
        return $result;
    }
    else {
        return false;
    }
}


/**
 * instantiate workflow object
 *
 * @param string $action workflow object action name
 * @return object
 */
function tm_message_workflow_object($action) {
    global $CFG;

    require_once($CFG->dirroot.'/totara/message/workflow/lib.php');
    $file = $CFG->dirroot.'/totara/message/workflow/plugins/'.$action.'/workflow_'.$action.'.php';
    if (!file_exists($file)) {
        debugging('tm_message_task_accept() plugin does not exist: '.$action, DEBUG_DEVELOPER);
        return false;
    }
    require_once($file);

    // create the object
    $ctlclass = 'totara_message_workflow_'.$action;
    if (class_exists($ctlclass)) {
        $plugin = new $ctlclass();
        return $plugin;
    }
    else {
        debugging('tm_message_task_accept() plugin class does not exist: '.$ctlclass, DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * get the current list of messages by type - alert/task
 *
 * @param string $type - message type
 * @param string $order_by - order by clause
 * @param object $userto user table record for user required
 * @param bool $limit Apply the block limit
 * @return array of messages
 */
function tm_messages_get($type, $order_by=false, $userto=false, $limit=true) {
        global $USER, $DB;

        // select only particular type
        $processor = $DB->get_record('message_processors', array('name' => $type));
        if (empty($processor)) {
            return false;
        }

        // sort out for which user
        if ($userto) {
            $userid = $userto->id;
        }
        else {
            $userid = $USER->id;
        }

        // do we sort?
        if ($order_by) {
            $order_by = ' ORDER BY '.$order_by;
        }
        else {
            $order_by = ' ';
        }

        // do we apply a limit?
        if ($limit) {
            $limit = TOTARA_MSG_ALERT_LIMIT;
        }

        // hunt for messages
        $msgs = $DB->get_records_sql('SELECT
            m.id, m.useridfrom,
            m.subject,
            m.fullmessage,
            m.timecreated,
            d.msgstatus,
            d.msgtype,
            d.urgency,
            d.icon,
            m.contexturl,
            m.contexturlname
            FROM ({message} m INNER JOIN  {message_working} w ON m.id = w.unreadmessageid) LEFT JOIN {message_metadata} d ON (d.messageid = m.id)
            WHERE m.useridto = ? AND w.processorid = ?' .$order_by,
            array($userid, $processor->id), 0, $limit);

        return $msgs;
}

/**
 * get the current count of messages by type - alert/task
 *
 * @param string $type - message type
 * @param object $userto user table record for user required
 * @return int count of messages
 */
function tm_messages_count($type, $userto=false) {
        global $USER, $DB;

        // select only particular type
        $processor = $DB->get_record('message_processors', array('name' => $type));
        if (empty($processor)) {
            return false;
        }

        // sort out for which user
        if ($userto) {
            $userid = $userto->id;
        }
        else {
            $userid = $USER->id;
        }

        $where = 'm.useridto = ? AND w.processorid = ?';
        $params = array($userid, $processor->id);

        // hunt for messages
        $msgs = $DB->get_records_sql('SELECT m.id AS count
            FROM ({message} m
            INNER JOIN {message_working} w ON m.id = w.unreadmessageid)
            LEFT JOIN {message_metadata} d ON (d.messageid = m.id)
            WHERE ' . $where,
            array($userid, $processor->id));
        return count($msgs);
}

/**
 * Set the default config values for totara ouput types on install
 *
 * @param string $output
 */
function tm_set_preference_defaults() {
    set_config('totara_task_provider_totara_message_task_permitted', 'permitted', 'message');
    set_config('totara_alert_provider_totara_message_alert_permitted', 'permitted', 'message');
    set_config('totara_alert_provider_totara_message_task_permitted', 'disallowed', 'message');
    set_config('message_provider_totara_message_alert_loggedin', 'totara_alert,email', 'message');
    set_config('message_provider_totara_message_alert_loggedoff', 'totara_alert,email', 'message');
    set_config('message_provider_totara_message_task_loggedin', 'totara_task,email', 'message');
    set_config('message_provider_totara_message_task_loggedoff', 'totara_task,email', 'message');
    set_config('popup_provider_totara_message_task_permitted', 'disallowed', 'message');
    set_config('email_provider_totara_message_task_permitted', 'permitted', 'message');
    set_config('jabber_provider_totara_message_task_permitted', 'disallowed', 'message');
    set_config('totara_task_provider_totara_message_alert_permitted', 'disallowed', 'message');
    set_config('popup_provider_totara_message_alert_permitted', 'permitted', 'message');
    set_config('email_provider_totara_message_alert_permitted', 'permitted', 'message');
    set_config('jabber_provider_totara_message_alert_permitted', 'permitted', 'message');
}

/// $messagearray is an array of objects
/// $field is a valid property of object
/// $value is the value $field should equal to be counted
/// if $field is empty then return count of the whole array
/// if $field is non-existent then return 0;
function tm_message_count_messages($messagearray, $field='', $value='') {
    if (!is_array($messagearray)) {
        return 0;
    }
    if ($field == '' or empty($messagearray)) {
        return count($messagearray);
    }

    $count = 0;
    foreach ($messagearray as $message) {
        $count += ($message->$field == $value) ? 1 : 0;
    }
    return $count;
}

/**
 * Returns the count of unread messages for user. Either from a specific user or from all users.
 * @global <type> $USER
 * @global <type> $DB
 * @param object $user1 the first user. Defaults to $USER
 * @param object $user2 the second user. If null this function will count all of user 1's unread messages.
 * @return int the count of $user1's unread messages
 */
function tm_message_count_unread_messages($user1=null, $user2=null) {
    global $USER, $DB;

    if (empty($user1)) {
        $user1 = $USER;
    }

    if (!empty($user2)) {
        return $DB->count_records_select('message', "useridto = ? AND useridfrom = ?",
                        array($user1->id, $user2->id), "COUNT('id')");
    }
    else {
        return $DB->count_records_select('message', "useridto = ?",
                        array($user1->id), "COUNT('id')");
    }
}

/**
 * marks ALL messages being sent from $fromuserid to $touserid as read
 * @param int $touserid the id of the message recipient
 * @param int $fromuserid the id of the message sender
 * @return void
 */
function tm_message_mark_messages_read($touserid, $fromuserid) {
    global $DB;

    $sql = 'SELECT m.* FROM {message} m WHERE m.useridto=:useridto AND m.useridfrom=:useridfrom';
    $messages = $DB->get_recordset_sql($sql, array('useridto' => $touserid, 'useridfrom' => $fromuserid));

    foreach ($messages as $message) {
        tm_message_mark_message_read($message, time());
    }
    $messages->close();
}

/**
 * Mark a single message as read
 * @param message an object with an object property ie $message->id which is an id in the message table
 * @param int $timeread the timestamp for when the message should be marked read. Usually time().
 * @param bool $messageworkingempty Is the message_working table already confirmed empty for this message?
 * @return void
 */
function tm_message_mark_message_read($message, $timeread, $messageworkingempty=false) {
    global $DB;

    $message->timeread = $timeread;
    $messageid = $message->id;
    $messagereadid = message_mark_message_read($message, $timeread, $messageworkingempty);

    // modify the metadata record to point to the read message instead
    $metadataid = $DB->get_field('message_metadata', 'id', array('messageid' => $messageid));
    if ($metadataid) {
        $todb = new stdClass();
        $todb->id = $metadataid;
        $todb->messageid = null; // remove message id
        $todb->messagereadid = $messagereadid; // add the read id
        $DB->update_record('message_metadata', $todb);
    }
}

/**
 * Set default message preferences.
 * @param $user - User to set message preferences
 */
function tm_message_set_default_message_preferences($user) {
    global $DB;

    $defaultonlineprocessor = 'email';
    $defaultofflineprocessor = 'email';
    $offlineprocessortouse = $onlineprocessortouse = null;

    //look for the pre-2.0 preference if it exists
    $oldpreference = get_user_preferences('message_showmessagewindow', -1, $user->id);
    //if they elected to see popups or the preference didnt exist
    $usepopups = (intval($oldpreference)==1 || intval($oldpreference)==-1);

    if ($usepopups) {
        $defaultonlineprocessor = 'popup';
    }

    $providers = $DB->get_records('message_providers');
    $preferences = array();
    if (!$providers) {
        $providers = array();
    }

    foreach ($providers as $providerid => $provider) {

        //force some specific defaults for some types of message
        if ($provider->name == 'instantmessage') {
            //if old popup preference was set to 1 or is missing use popups for IMs
            if ($usepopups) {
                $onlineprocessortouse = 'popup';
                $offlineprocessortouse = 'email,popup';
            }
        }
        else if ($provider->name == 'posts') {
            //forum posts
            $offlineprocessortouse = $onlineprocessortouse = 'email';
        }
        else if ($provider->name == 'alert') {
            //totara alert
            $offlineprocessortouse = $onlineprocessortouse = 'totara_alert,email';
        }
        else if ($provider->name == 'task') {
            //totara task
            $offlineprocessortouse = $onlineprocessortouse = 'totara_task,email';
        }
        else {
            $onlineprocessortouse = $defaultonlineprocessor;
            $offlineprocessortouse = $defaultofflineprocessor;
        }

        $preferences['message_provider_'.$provider->component.'_'.$provider->name.'_loggedin'] = $onlineprocessortouse;
        $preferences['message_provider_'.$provider->component.'_'.$provider->name.'_loggedoff'] = $offlineprocessortouse;
    }

    return set_user_preferences($preferences, $user->id);
}
