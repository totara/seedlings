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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_feedback360
 */


require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/feedback360/lib.php');
require_once($CFG->dirroot . '/totara/feedback360/feedback360_forms.php');

// Check if 360 Feedbacks are enabled.
feedback360::check_feature_enabled();

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('managefeedback360');
$systemcontext = context_system::instance();
require_capability('totara/feedback360:managefeedback360', $systemcontext);

$returnurl = new moodle_url('/totara/feedback360/manage.php');

$feedback360 = new feedback360($id);
$isdraft = feedback360::is_draft($feedback360);
$defaults = $feedback360->get();
$defaults->descriptionformat = FORMAT_HTML;
$defaults = file_prepare_standard_editor($defaults, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
        'totara_feedback360', 'feedback360', $id);
$mform = new feedback360_edit_form(null, array('id' => $id, 'feedback360' => $defaults, 'readonly' => !$isdraft));

if ($mform->is_cancelled()) {
    redirect($returnurl);
}
if ($fromform = $mform->get_data()) {
    if (empty($fromform->submitbutton)) {
        totara_set_notification(get_string('error:unknownbuttonclicked', 'totara_feedback360'), $returnurl);
    }

    $todb = new stdClass();
    $todb->name = $fromform->name;
    $feedback360->set($todb);

    if ($feedback360->id < 1) {
        $feedback360->save();
    }
    $todb->description_editor = $fromform->description_editor;
    $todb = file_postupdate_standard_editor($todb, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
        'totara_feedback360', 'feedback360', $feedback360->id);
    $feedback360->description = $todb->description;
    $feedback360->save();

    add_to_log(SITEID, 'feedback360', 'update feedback360', 'general.php?id='.$id, 'General Settings: feedback360 ID=' . $id);
    if ($id > 0) {
        totara_set_notification(get_string('feedback360updated', 'totara_feedback360'), $returnurl,
                array('class' => 'notifysuccess'));
    } else {
        $stageurl = new moodle_url('/totara/feedback360/general.php', array('id' => $feedback360->id));
        totara_set_notification(get_string('feedback360updated', 'totara_feedback360'), $stageurl,
                array('class' => 'notifysuccess'));
    }
}

$output = $PAGE->get_renderer('totara_feedback360');
echo $output->header();
if ($feedback360->id) {
    echo $output->heading($feedback360->name);
    echo $output->feedback360_additional_actions($feedback360->status, $feedback360->id);
} else {
    echo $output->heading(get_string('createfeedback360heading', 'totara_feedback360'));
}

echo $output->feedback360_management_tabs($feedback360->id, 'general');

$mform->display();
echo $output->footer();
