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

$id = required_param('id', PARAM_INT);

admin_externalpage_setup('managefeedback360');
$systemcontext = context_system::instance();
require_capability('totara/feedback360:managefeedback360', $systemcontext);

redirect(new moodle_url('/totara/feedback360/general.php', array('id' => $id)));

$returnurl = new moodle_url('/totara/feedback360/recipients.php', array('id' => $id));

$feedback360 = new feedback360($id);
$isdraft = feedback360::is_draft($feedback360);

$mform = new feedback360_recipients_form(null, array('id' => $id, 'readonly' => !$isdraft));

if ($mform->is_cancelled()) {
    redirect($returnurl);
}
if ($fromform = $mform->get_data()) {
    if (empty($fromform->submitbutton)) {
        totara_set_notification(get_string('error:unknownbuttonclicked', 'totara_feedback360'), $returnurl);
    }
    $feedback360->recipients = $fromform->recipients;
    $feedback360->save();
    totara_set_notification(get_string('recipientsupdated', 'totara_feedback360'), $returnurl, array('class' => 'notifysuccess'));
} else {
    $mform->set_data($feedback360->get());
}

$output = $PAGE->get_renderer('totara_feedback360');
echo $output->header();
echo $output->heading($feedback360->name);
echo $output->feedback360_additional_actions($feedback360->status, $feedback360->id);

echo $output->feedback360_management_tabs($feedback360->id, 'recipients');

echo html_writer::tag('span', get_string('recipientdesc', 'totara_feedback360'));
$mform->display();
echo $output->footer();
