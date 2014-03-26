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
 * @package totara
 * @subpackage program
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/program/lib.php');

require_login();

$programid = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$extensionrequest = optional_param('extrequest', false, PARAM_BOOL);
$extensiondate = optional_param('extdate', '', PARAM_TEXT);
$extensionreason = optional_param('extreason', '', PARAM_TEXT);

$PAGE->set_context(context_program::instance($programid));

if ($USER->id != $userid) {
    @header('HTTP/1.0 404 Not Found');
    echo get_string('error:cannotrequestextnotuser', 'totara_program');
    return false;
}

$program = new program($programid);

if (!$extensionrequest || !$extensiondate || !$extensionreason) {
    @header('HTTP/1.0 404 Not Found');
    echo get_string('error:processingextrequest', 'totara_program');
    return false;
}

if (!$manager = totara_get_manager($userid)) {
    @header('HTTP/1.0 404 Not Found');
    echo get_string('extensionrequestfailed:nomanager', 'totara_program');
    return false;
}

$timearray = explode('/', $extensiondate);
$day = $timearray[0];
$month = $timearray[1];
$year = $timearray[2];
$extensiontime = mktime(0, 0, 0, $month, $day, $year);

$extensiondata = array(
    'extensiondate'         => $extensiontime,
    'extensiondatestr'      => $extensiondate,
    'extensionreason'       => $extensionreason,
    'programfullname'       => format_string($program->fullname)
);

$extensiondate_timestamp = totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'), $extensiondate);  // convert to timestamp

$extension = new stdClass;
$extension->programid = $program->id;
$extension->userid = $userid;
$extension->extensiondate = $extensiondate_timestamp;

// Validated extension date to make sure it is after
// current due date and not in the past
if ($prog_completion = $DB->get_record('prog_completion', array('programid' => $program->id, 'userid' => $userid, 'coursesetid' => 0))) {
    $duedate = empty($prog_completion->timedue) ? 0 : $prog_completion->timedue;

    if ($extensiondate_timestamp < $duedate) {
        @header('HTTP/1.0 404 Not Found');
        echo get_string('extensionearlierthanduedate', 'totara_program');
        return false;
    }
} else {
    @header('HTTP/1.0 404 Not Found');
    echo get_string('error:noprogramcompletionfound', 'totara_program');
    return false;
}

$now = time();
if ($extensiondate_timestamp < $now) {
    @header('HTTP/1.0 404 Not Found');
    echo get_string('extensionbeforenow', 'totara_program');
    return false;
}

$extension->extensionreason = $extensionreason;
$extension->status = 0;

if ($extensionid = $DB->insert_record('prog_extension', $extension)) {

    $data = array();
    $data['extensionid'] = $extensionid;


    // Get user to send message to
    $user = $DB->get_record('user', array('id' => $userid));
    $userfullname = fullname($user);
    //send request in managers language
    $strmgr = get_string_manager();
    $extension_message = new prog_extension_request_message($program->id, $extension->userid, null, null, $data);
    $managermessagedata = $extension_message->get_manager_message_data();
    $managermessagedata->subject = $strmgr->get_string('extensionrequest', 'totara_program', $userfullname, $manager->lang);
    $managermessagedata->fullmessage = $strmgr->get_string('extensionrequestmessage', 'totara_program', (object)$extensiondata, $manager->lang);
    $managermessagedata->contexturlname = $strmgr->get_string('manageextensionrequests', 'totara_program', null, $manager->lang);
    $managermessagedata->infobutton = $strmgr->get_string('extensioninfo_button', 'totara_program', null, $manager->lang);
    $managermessagedata->infotext = $strmgr->get_string('extensioninfo_text', 'totara_program', null, $manager->lang);

    $managermessagedata->acceptbutton = $strmgr->get_string('extensionacceptbutton', 'totara_program', null, $manager->lang);
    $managermessagedata->accepttext = $strmgr->get_string('extensionaccepttext', 'totara_program', null, $manager->lang);

    $managermessagedata->rejectbutton = $strmgr->get_string('extensionrejectbutton', 'totara_program', null, $manager->lang);
    $managermessagedata->rejecttext = $strmgr->get_string('extensionrejecttext', 'totara_program', null, $manager->lang);

    if ($extension_message->send_message($manager, $user)) {
        // Calcuate the new time and print pending extension text
        echo get_string('pendingextension', 'totara_program');
    } else {
        @header('HTTP/1.0 404 Not Found');
        echo get_string('extensionrequestnotsent', 'totara_program');
        return false;
    }

} else {
    @header('HTTP/1.0 404 Not Found');
    echo get_string('extensionrequestfailed', 'totara_program');
    return false;
}
