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
 * Page containing column display options, displayed inside show/hide popup dialog
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot.'/message/lib.php');
require_once($CFG->dirroot.'/totara/message/lib.php');

require_login();

if (isguestuser()) {
    redirect($CFG->wwwroot);
}

$PAGE->set_context(context_system::instance());

/// Script parameters
$dismiss = optional_param('dismiss', NULL, PARAM_RAW);
$accept = optional_param('accept', NULL, PARAM_RAW);
$reject = optional_param('reject', NULL, PARAM_RAW);
$msgids = explode(',', optional_param('msgids', '', PARAM_SEQUENCE));

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
        if (!$message || $message->useridto != $USER->id || !confirm_sesskey()) {
            print_error('notyours', 'totara_message', $msgid);
        }
        $ids[$msgid] = $message;
    }
}

if ($dismiss) {
    // dismiss the message and then return
    $action = 'dismiss';
}
else if ($accept) {
    // onaccept the message and then return
    $action = 'accept';
}
else if ($reject) {
    // onreject the message and then return
    $action = 'reject';
}
print html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $action, 'value' => $action));

// process the action
print html_writer::start_tag('div', array('id' => 'totara-msgs-action'));
$tab = new html_table();
$tab->head  = array(get_string('type', 'block_totara_alerts'),
                     get_string('from', 'block_totara_alerts'),
                     get_string('statement', 'block_totara_alerts'));

$tab->attributes['class'] = 'fullwidth invisiblepadded';
$tab->data  = array();
foreach ($ids as $msgid => $msg) {
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

    $display = isset($metadata->msgtype) ? totara_message_msgtype_text($metadata->msgtype) : array('icon' => '', 'text' => '');
    $type = $display['icon'];
    $type_alt = $display['text'];

    if ($msg->useridfrom == 0) {
        $from = core_user::get_support_user();
    } else {
        $from = $DB->get_record('user', array('id' => $msg->useridfrom));
    }
    $fromname = fullname($from) . " ({$from->email})";

    $icon = $OUTPUT->pix_icon('/msgicons/'.$metadata->icon, format_string($msg->subject), 'totara_core', array('class'=>'msgicon', 'title' => format_string($msg->subject)));
    $cells = array();
    $cell = new html_table_cell(html_writer::tag('div', $icon, array('id' => 'dismiss-type')));
    $cell->attributes['class'] = 'totara-msgs-action-right';
    $cells []= $cell;
    $cell = new html_table_cell(html_writer::tag('div', $fromname, array('id' => 'dismiss-from')));
    $cell->attributes['class'] = 'totara-msgs-action-right';
    $cells []= $cell;
    $cell = new html_table_cell(html_writer::tag('div', $msg->fullmessage, array('id' => 'dismiss-statement')));
    $cell->attributes['class'] = 'totara-msgs-action-right';
    $cells []= $cell;
    $tab->data[] = new html_table_row($cells);
}
print html_writer::table($tab);
print html_writer::end_tag('div');
