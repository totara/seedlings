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
 * @subpackage totara_appraisal
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/appraisal/lib.php');
require_once($CFG->dirroot . '/totara/appraisal/appraisal_forms.php');

// Check if Appraisals are enabled.
appraisal::check_feature_enabled();

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('manageappraisals');
$systemcontext = context_system::instance();
require_capability('totara/appraisal:manageappraisals', $systemcontext);

$returnurl = new moodle_url('/totara/appraisal/manage.php');

$appraisal = new appraisal($id);
$defaults = $appraisal->get();
$defaults->descriptionformat = FORMAT_HTML;
// Changes to filearea, component and id should be reflected.
$defaults = file_prepare_standard_editor($defaults, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
        'totara_appraisal', 'appraisal', $id);
$mform = new appraisal_edit_form(null, array('id' => $id, 'appraisal' => $defaults,
        'readonly' => !appraisal::is_draft($appraisal->id)));

if ($mform->is_cancelled()) {
    redirect($returnurl);
}
if ($fromform = $mform->get_data()) {
    if (empty($fromform->submitbutton)) {
        totara_set_notification(get_string('error:unknownbuttonclicked', 'totara_appraisal'), $returnurl);
    }

    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    $appraisal->name = $fromform->name;
    if ($appraisal->id < 1) {
        $appraisal->save();
    }
    $todb = new stdClass();
    $todb->description_editor = $fromform->description_editor;
    $todb = file_postupdate_standard_editor($todb, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
        'totara_appraisal', 'appraisal', $appraisal->id);
    $appraisal->description = $todb->description;
    $appraisal->save();

    add_to_log(SITEID, 'appraisal', 'update appraisal', 'general.php?id='.$id, 'General Settings: Appraisal ID=' . $id);
    if ($id < 0) {
        totara_set_notification(get_string('appraisalupdated', 'totara_appraisal'), $returnurl, array('class' => 'notifysuccess'));
    } else {
        $stageurl = new moodle_url('/totara/appraisal/stage.php', array('appraisalid' => $appraisal->id));
        totara_set_notification(get_string('appraisalupdated', 'totara_appraisal'), $stageurl, array('class' => 'notifysuccess'));
    }
}

$title = $PAGE->title . ': ' . $appraisal->name;
$PAGE->set_title($title);
$PAGE->set_heading($appraisal->name);
$PAGE->navbar->add($appraisal->name);
$output = $PAGE->get_renderer('totara_appraisal');
echo $output->header();
if ($appraisal->id) {
    echo $output->heading($appraisal->name);
    echo $output->appraisal_additional_actions($appraisal->status, $appraisal->id);
} else {
    echo $output->heading(get_string('createappraisalheading', 'totara_appraisal'));
}

echo $output->appraisal_management_tabs($appraisal->id, 'general');

$mform->display();
echo $output->footer();
