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

require_once '../../config.php';
require_once 'customfield_form.php';

$id      = required_param('id', PARAM_INT); // ID in facetoface_session_field.
$delete  = optional_param('d', 0, PARAM_BOOL); // Set to true to delete the given field.
$confirm = optional_param('confirm', 0, PARAM_BOOL); // Delete confirmationx.
$page    = optional_param('page', 0, PARAM_INT );

$page = optional_param('page', 0, PARAM_INT);

$contextsystem = context_system::instance();
admin_externalpage_setup('modfacetofacecustomfields');

$returnurl = new moodle_url('/mod/facetoface/customfields.php', array('page' => $page));

// Handle deletions
if ($delete and $id) {
    $field = $DB->get_record('facetoface_session_field', array('id' => $id), '*', MUST_EXIST);

    if (!$confirm or !confirm_sesskey()) {
        echo $OUTPUT->header();

        $optionsyes = array('id' => $id, 'sesskey' => $USER->sesskey, 'd' => 1, 'confirm' => 1);
        echo $OUTPUT->confirm(get_string('fielddeleteconfirm', 'facetoface', format_string($field->name)),
            new moodle_url("customfield.php", $optionsyes),
            new moodle_url($returnurl));
        echo $OUTPUT->footer();
        exit;
    }

    $transaction = $DB->start_delegated_transaction();
    $DB->delete_records('facetoface_session_field', array('id' => $id));
    $DB->delete_records('facetoface_session_data', array('fieldid' => $id));
    $transaction->allow_commit();
    redirect($returnurl);
}

if ($id == 0) {
    $field = new stdClass();
    $field->id = 0;
    $field->name = '';
    $field->shortname = '';
    $field->type = (string)CUSTOMFIELD_TYPE_TEXT;
    $field->required = '0';
    $field->showinsummary = '1';

} else {
    $field = $DB->get_record('facetoface_session_field', array('id' => $id), '*', MUST_EXIST);
    $field->possiblevalues = implode(PHP_EOL, explode(CUSTOMFIELD_DELIMITER, $field->possiblevalues));
}
$field->page = $page;

$mform = new mod_facetoface_customfield_form(null, compact('id'));
$mform->set_data($field);

if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) {

    if (!isset($fromform->possiblevalues)) {
        $fromform->possiblevalues = '';
    }
    $values_list = explode("\n", trim($fromform->possiblevalues));
    $pos_vals = array();
    foreach ($values_list as $val) {
        $trimmed_val = trim($val);
        if (strlen($trimmed_val) != 0) {
            $pos_vals[] = $trimmed_val;
        }
    }

    $todb = new stdClass();
    $todb->name = trim($fromform->name);
    $todb->shortname = trim($fromform->shortname);
    $todb->type = $fromform->type;
    $todb->defaultvalue = trim($fromform->defaultvalue);
    $todb->possiblevalues = implode(CUSTOMFIELD_DELIMITER, $pos_vals);
    $todb->required = $fromform->required;
    $todb->showinsummary = $fromform->showinsummary;

    if ($field->id) {
        $todb->id = $field->id;
        $DB->update_record('facetoface_session_field', $todb);
    } else {
        $DB->insert_record('facetoface_session_field', $todb);
    }

    redirect($returnurl);
}

echo $OUTPUT->header();

$mform->display();

echo $OUTPUT->footer();
