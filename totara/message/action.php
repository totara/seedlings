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
 * @author Piers Harding <piers@catalyst.net.nz>
 * @package totara
 * @subpackage message
 */

/**
 * For listing message histories between any two users
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once('lib.php');

require_login();
$PAGE->set_context(context_system::instance());

if (isguestuser() || !confirm_sesskey()) {
    redirect($CFG->wwwroot);
}

/// Script parameters
$returnto = optional_param('returnto', $CFG->wwwroot, PARAM_LOCALURL);
$dismiss = optional_param('dismiss', NULL, PARAM_RAW);
$accept = optional_param('accept', NULL, PARAM_RAW);
$reject = optional_param('reject', NULL, PARAM_RAW);
$msgids = explode(',', optional_param('msgids', array(), PARAM_RAW));

// hunt for Message Ids in the POST parameters
foreach ($_POST as $parm => $value) {
    if (preg_match('/^totara\_message\_(\d+)$/', $parm)) {
        $msgid = optional_param($parm, NULL, PARAM_INT);
        if ($msgid) {
            $msgids[]=$msgid;
        }
    }
}

// validate each of the messages
$ids = array();
foreach ($msgids as $msgid) {
    // check message ownership
    if ($msgid) {
        $message = $DB->get_record('message', array('id' => $msgid));
        if (!$message || $message->useridto != $USER->id) {
            print_error('notyours', 'totara_message', $msgid);
        }

        $metadata = $DB->get_record('message_metadata', array('messageid' => $msgid));

        // cannot run reject on message with no onreject
        if ($reject && (!isset($metadata->onreject) || !$metadata->onreject)) {
            continue;
        }

        // cannot run accept on message with no accept
        if ($accept && (!isset($metadata->onaccept) || !$metadata->onaccept)) {
            continue;
        }

        // cannot run accept on message type LINK in bulk action
        if ($accept && isset($metadata->onaccept) && $metadata->msgtype == TOTARA_MSG_TYPE_LINK) {
            continue;
        }

        $ids[$msgid] = $message;
    }
}

// process the action
foreach ($ids as $msgid => $message) {

    if ($dismiss) {
        // dismiss the message and then return
        tm_message_dismiss($msgid);
    }
    else if ($accept) {
        // onaccept the message and then return
        tm_message_task_accept($msgid);
    }
    else if ($reject) {
        // onreject the message and then return
        tm_message_task_reject($msgid);
    }
}

// send them home
redirect($returnto);
