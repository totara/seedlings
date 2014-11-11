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
 * @package modules
 * @subpackage facetoface
 */

require_once('../../config.php');
require_once('lib.php');
require_once('interestform.php');

$f = required_param('f', PARAM_INT); // Facetoface ID.
$confirm = optional_param('confirm', false, PARAM_BOOL);

$facetoface = $DB->get_record('facetoface', array('id' => $f), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $facetoface->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $course->id, false, MUST_EXIST);

$redirecturl = new moodle_url('/course/view.php', array('id' => $course->id));

// Are we declaring or withdrawing interest?
if (facetoface_user_declared_interest($facetoface)) {
    $declare = false;
} else {
    $declare = true;
    if (!facetoface_activity_can_declare_interest($facetoface)) {
        print_error('error:cannotdeclareinterest', 'facetoface', $redirecturl);
    }
}
$context = context_module::instance($cm->id);

$PAGE->set_url('/mod/facetoface/interest.php', array('id' => $cm->id));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_cm($cm);

require_login($course);
require_capability('mod/facetoface:view', $context);

add_to_log($course->id, 'facetoface', 'view', "interest.php?id=$cm->id", $facetoface->id, $cm->id);

$title = $course->shortname . ': ' . format_string($facetoface->name);

$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
$PAGE->set_button(update_module_button($cm->id, '', get_string('modulename', 'facetoface')));

$mform = new mod_facetoface_interest_form(null, array('f' => $f, 'declare' => $declare));

if ($mform->is_cancelled()) {
    redirect($redirecturl);
} else if ($data = $mform->get_data()) {
    if ($declare) {
        $reason = isset($data->reason) ? $data->reason : '';
        facetoface_declare_interest($facetoface, $reason);
    } else {
        facetoface_withdraw_interest($facetoface);
    }
    redirect($redirecturl);
}

$pagetitle = format_string($facetoface->name);

echo $OUTPUT->header();

if (empty($cm->visible) and !has_capability('mod/facetoface:viewemptyactivities', $context)) {
    notice(get_string('activityiscurrentlyhidden'));
}
echo $OUTPUT->box_start();
if ($declare) {
    $title = get_string('declareinterestin', 'mod_facetoface', $facetoface->name);
    $question = get_string('declareinterestinconfirm', 'mod_facetoface', $facetoface->name);
} else {
    $title = get_string('declareinterestwithdrawfrom', 'mod_facetoface', $facetoface->name);
    $question = get_string('declareinterestwithdrawfromconfirm', 'mod_facetoface', $facetoface->name);
}
echo $OUTPUT->heading($title, 2);

if ($facetoface->intro) {
    echo $OUTPUT->box_start('generalbox', 'description');
    $facetoface->intro = file_rewrite_pluginfile_urls($facetoface->intro, 'pluginfile.php', $context->id, 'mod_facetoface', 'intro', null);
    echo format_text($facetoface->intro, $facetoface->introformat);
    echo $OUTPUT->box_end();
}

echo $OUTPUT->heading($question, 4);
$mform->display();

echo $OUTPUT->box_end();
echo $OUTPUT->footer($course);
