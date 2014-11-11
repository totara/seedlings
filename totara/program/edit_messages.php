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

/**
 * Program view page
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once 'HTML/QuickForm/Renderer/QuickHtml.php';
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once('edit_messages_form.php');

$id = required_param('id', PARAM_INT); // program id

require_login();

$systemcontext = context_system::instance();
$program = new program($id);
$iscertif = $program->certifid ? true : false;

// Check if programs or certifications are enabled.
if ($iscertif) {
    check_certification_enabled();
} else {
    check_program_enabled();
}

$programcontext = $program->get_context();

if (!has_capability('totara/program:configuremessages', $programcontext)) {
    print_error('error:nopermissions', 'totara_program');
}

$PAGE->set_url(new moodle_url('/totara/program/edit_messages.php', array('id' => $id)));
$PAGE->set_context($programcontext);
$PAGE->set_title(format_string($program->fullname));
$PAGE->set_heading(format_string($program->fullname));

// Javascript include.
local_js(array(
    TOTARA_JS_DIALOG
));

$programmessagemanager = $program->get_messagesmanager();

$currenturl = qualified_me();
$currenturl_noquerystring = strip_querystring($currenturl);
$viewurl = $currenturl_noquerystring."?id={$id}";
$overviewurl = $CFG->wwwroot."/totara/program/edit.php?id={$id}&action=view";

// if the form has been submitted we need to make sure that the program object
// contains all the submitted data before the form is created and validated as
// the form is defined based on the status of the program object. This MUST
// only READ data from the database and MUST NOT WRITE anything as nothing has
// been checked or validated yet.
if ($rawdata = data_submitted()) {
    require_sesskey();

    if (!$programmessagemanager->setup_messages($rawdata)) {
        print_error('error:setupprogrammessages', 'totara_program');
    }

    if (isset($rawdata->addmessage)) {
        if (!$programmessagemanager->add_message($rawdata->messagetype)) {
            echo $OUTPUT->notification(get_string('error:unableaddmessagetypeunrecog', 'totara_program'));
        }
    } else if (isset($rawdata->update)) {
        $programmessagemanager->update_messages();
        echo $OUTPUT->notification(get_string('progmessageupdated', 'totara_program'));
    } else if ($messagenumber = $programmessagemanager->check_message_action('delete', $rawdata)) {
        if (!$programmessagemanager->delete_message($messagenumber)) {
            echo $OUTPUT->notification(get_string('error:unabledeletemessagenotfound', 'totara_program'));
        }
    } else if ($messagenumber = $programmessagemanager->check_message_action('update', $rawdata)) {
        $programmessagemanager->update_messages();
    } else if ($messagenumber = $programmessagemanager->check_message_action('moveup', $rawdata)) {
        $programmessagemanager->move_message_up($messagenumber);
    } else if ($messagenumber = $programmessagemanager->check_message_action('movedown', $rawdata)) {
        $programmessagemanager->move_message_down($messagenumber);
    }

}

$messageseditform = new program_messages_edit_form($currenturl, array('program'=>$program), 'post', '', array('name'=>'form_prog_messages'));

// this removes the 'mform' class which is set be default on the form and which
// causes problems with the styling
// TODO SCANMSG This may cause issues when styling
//$messageseditform->_form->updateAttributes(array('class'=>''));

if ($messageseditform->is_cancelled()) {
    totara_set_notification(get_string('programupdatecancelled', 'totara_program'), $overviewurl, array('class' => 'notifysuccess'));
}

// if the form has not been submitted, fill in the saved values and defaults
if (!$rawdata) {
    $messageseditform->set_data($programmessagemanager->formdataobject);
}

// This is where we validate and check the submitted data before saving it
if ($data = $messageseditform->get_data()) {

    if (isset($data->savechanges)) {

        // first set up the messages manager using the checked and validated form data
        if (!$programmessagemanager->setup_messages($data)) {
            print_error('error:setupprogrammessages', 'totara_program');
        }

        // log this request
        add_to_log(SITEID, 'program', 'update messages', "edit_messages.php?id={$program->id}", $program->fullname);

        $prog_update = new stdClass();
        $prog_update->id = $id;
        $prog_update->timemodified = time();
        $prog_update->usermodified = $USER->id;
        $DB->update_record('prog', $prog_update);

        // then save the messages
        if (!$programmessagemanager->save_messages($data)) {
            totara_set_notification(get_string('programupdatefail', 'totara_program'), $currenturl);
        } else {
            totara_set_notification(get_string('programmessagessaved', 'totara_program'), 'edit_messages.php?id='.$id, array('class' => 'notifysuccess'));
        }
    }

}

// log this request
add_to_log(SITEID, 'program', 'view messages', "edit_messages.php?id={$program->id}", $program->fullname);

// Display.
$heading = format_string($program->fullname);

if ($iscertif) {
    $heading .= ' ('.get_string('certification','totara_certification').')';
}

// Javascript includes.
$PAGE->requires->strings_for_js(array('editmessages','saveallchanges',
         'confirmmessagechanges','youhaveunsavedchanges','youhaveunsavedchanges',
         'tosavemessages'),
      'totara_program');
$args = array('args'=>'{"id":'.$program->id.'}');
$jsmodule = array(
     'name' => 'totara_programmessages',
     'fullpath' => '/totara/program/messages/program_messages.js',
     'requires' => array('json'));
$PAGE->requires->js_init_call('M.totara_programmessages.init',$args, false, $jsmodule);

echo $OUTPUT->header();

echo $OUTPUT->container_start('program messages', 'program-messages');

echo $OUTPUT->heading($heading);

$renderer = $PAGE->get_renderer('totara_program');
// Display the current status
echo $program->display_current_status();
$exceptions = $program->get_exception_count();
$currenttab = 'messages';
require('tabs.php');


// Display the form
$messageseditform->display();

echo $renderer->get_cancel_button(array('id' => $program->id));

echo $OUTPUT->container_end();

echo $OUTPUT->footer();
