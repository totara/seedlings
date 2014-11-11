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
 * @package totara
 * @subpackage facetoface
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');
require_once($CFG->dirroot . '/mod/facetoface/notification/lib.php');
require_once($CFG->dirroot . '/mod/facetoface/notification/edit_form.php');

// Parameters
$f = required_param('f', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$duplicate = optional_param('duplicate', 0, PARAM_INT);

if (!$facetoface = $DB->get_record('facetoface', array('id' => $f))) {
    print_error('error:incorrectfacetofaceid', 'facetoface');
}

if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
    print_error('error:coursemisconfigured', 'facetoface');
}
if (!$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $course->id)) {
    print_error('error:incorrectcoursemoduleid', 'facetoface');
}

// Setup page and check permissions
$url = new moodle_url('/mod/facetoface/notification/index.php', array('update' => $cm->id));
$PAGE->set_url($url);

$redirectto = new moodle_url('/mod/facetoface/notification/index.php', array('update' => $cm->id));
$formurl = new moodle_url('/mod/facetoface/notification/edit.php', array('f' => $f, 'id' => $id));

require_login($course, false, $cm); // needed to setup proper $COURSE
$context = context_module::instance($cm->id);
require_capability('moodle/course:manageactivities', $context);

local_js();

$templates = $DB->get_records('facetoface_notification_tpl', array('status' => 1));
$json_templates = json_encode($templates);
$args = array('args' => '{"templates":'.$json_templates.'}');

$jsmodule = array(
    'name' => 'totara_f2f_notification_template',
    'fullpath' => '/mod/facetoface/notification/get_template.js',
    'requires' => array('json', 'totara_core'));

$PAGE->requires->js_init_call('M.totara_f2f_notification_template.init', $args, false, $jsmodule);

// Load data
// Load templates
if ($id) {
    $notification = new facetoface_notification(array('id' => $id));
    if (!$notification) {
        print_error('error:notificationcouldnotbefound', 'facetoface');
    }

    $forform = $notification;
    // Booked is an integer to specify which type of booked is selected
    // if it has any non-zero value (true) then we also have to make sure
    // the checkbox is selected as well as the radiobox.
    $forform->booked_type = $forform->booked;
    $forform->booked = (bool) $forform->booked;

} else {
    $notification = new facetoface_notification();
}

// If duplicate, unset ID
if ($duplicate) {
    $id = 0;
    $notification->id = 0;
}


// Setup editors
$editoroptions = array(
    'noclean'  => false,
    'maxfiles' => EDITOR_UNLIMITED_FILES,
    'maxbytes' => $CFG->maxbytes,
    'context'  => $context,
);

$body = new stdClass();
$body->id = isset($notification) ? $notification->id : 0;
$body->body = isset($notification->body) ? $notification->body : '';
$body->bodyformat = FORMAT_HTML;
$body = file_prepare_standard_editor($body, 'body', $editoroptions, $context, 'mod_facetoface', 'notification', $id);

$managerprefix = new stdClass();
$managerprefix->id = isset($notification) ? $notification->id : 0;
$managerprefix->managerprefix = isset($notification->managerprefix) ? $notification->managerprefix : '';
$managerprefix->managerprefixformat = FORMAT_HTML;
$managerprefix = file_prepare_standard_editor($managerprefix, 'managerprefix', $editoroptions, $context, 'mod_facetoface', 'notification', $id);

// Create form
$customdata = array(
    'id' => $id,
    'templates' => $templates,
    'notification' => $notification,
    'type' => $notification->type,
    'editoroptions' => $editoroptions
);
$form = new mod_facetoface_notification_form($formurl, $customdata);


if ($id || $duplicate) {
    // Format for the text editors
    $forform->managerprefixformat = FORMAT_HTML;
    $forform->bodyformat = FORMAT_HTML;

    $forform = file_prepare_standard_editor($forform, 'body', $editoroptions, $context, 'mod_facetoface', 'notification', $id);
    $forform = file_prepare_standard_editor($forform, 'managerprefix', $editoroptions, $context, 'mod_facetoface', 'notification', $id);

    $form->set_data($forform);
}

// Process data
if ($form->is_cancelled()) {
    redirect($redirectto);
} else if ($data = $form->get_data()) {
    // Set body and managerprefix to empty string to stop errors
    $data->body = '';
    $data->managerprefix = '';

    facetoface_notification::set_properties($notification, $data);

    if (!empty($data->booked)) {
        // If one of the booked radio boxes are selected then the value
        // will be taken from booked_type instead of booked (checkbox).
        $notification->booked = $data->booked_type;
    } else {
        $notification->booked = 0;
    }

    $notification->courseid = $course->id;
    $notification->facetofaceid = $facetoface->id;
    $notification->ccmanager = (isset($data->ccmanager) ? 1 : 0);
    $notification->status = (isset($data->status) ? 1 : 0);

    $notification->save();

    $data = file_postupdate_standard_editor($data, 'body', $editoroptions, $context, 'mod_facetoface', 'notification', $notification->id);
    $DB->set_field('facetoface_notification', 'body', $data->body, array('id' => $notification->id));

    $data = file_postupdate_standard_editor($data, 'managerprefix', $editoroptions, $context, 'mod_facetoface', 'notification', $notification->id);
    $DB->set_field('facetoface_notification', 'managerprefix', $data->managerprefix, array('id' => $notification->id));

    totara_set_notification(get_string('notificationsaved', 'facetoface'), $redirectto, array('class' => 'notifysuccess'));
}

$pagetitle = format_string($facetoface->name);

if ($id) {
    $PAGE->navbar->add(get_string('edit', 'moodle'));
} else {
    $PAGE->navbar->add(get_string('add', 'moodle'));
}

$button = update_module_button($cm->id, $course->id, get_string('modulename', 'facetoface'));

$PAGE->set_title($pagetitle);
$PAGE->set_heading('');
$PAGE->set_focuscontrol('');
$PAGE->set_cacheable(true);
$PAGE->set_button($button);
echo $OUTPUT->header();

if ($id) {
    $notification_title = format_string($notification->title);
    echo $OUTPUT->heading(get_string('editnotificationx', 'facetoface', $notification_title));
} else {
    echo $OUTPUT->heading(get_string('addnotification', 'facetoface'));
}

// Check if form frozen, mention why
$isfrozen = $notification->is_frozen();
if ($isfrozen) {
    echo $OUTPUT->notification(get_string('notificationalreadysent', 'facetoface'));
}

$form->display();

if ($isfrozen) {
    echo $OUTPUT->container_start('continuebutton');
    $continueurl = clone($formurl);
    $continueurl->param('duplicate', 1);
    echo $OUTPUT->single_button($continueurl, get_string('duplicate'), 'get');
    echo $OUTPUT->single_button($redirectto, get_string('return', 'facetoface'), 'get');
    echo $OUTPUT->container_end();
}

echo $OUTPUT->footer($course);
