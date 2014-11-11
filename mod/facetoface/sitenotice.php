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
require_once 'sitenotice_form.php';

$id      = required_param('id', PARAM_INT); // ID in facetoface_notice
$d       = optional_param('d', 0, PARAM_BOOL); // set to true to delete the given notice
$confirm = optional_param('confirm', 0, PARAM_BOOL); // delete confirmation
$page    = optional_param('page', 0, PARAM_INT );

$contextsystem = context_system::instance();
admin_externalpage_setup('modfacetofacesitenotices');

$returnurl = new moodle_url('/mod/facetoface/sitenotices.php', array('page' => $page));

// Handle deletions.
if ($id and $d) {
    $notice = $DB->get_record('facetoface_notice', array('id' => $id), '*', MUST_EXIST);

    if (!$confirm or !confirm_sesskey()) {
        echo $OUTPUT->header();

        $info = new stdClass();
        $info->name = format_string($notice->name);
        $info->text = format_text($notice->text, FORMAT_HTML);
        $optionsyes = array('id' => $id, 'sesskey' => $USER->sesskey, 'd' => 1, 'confirm' => 1);
        echo $OUTPUT->confirm(get_string('noticedeleteconfirm', 'facetoface', $info),
            new moodle_url("sitenotice.php", $optionsyes),
            new moodle_url($returnurl));
        echo $OUTPUT->footer();
        exit;
    }

    $transaction = $DB->start_delegated_transaction();
    $DB->delete_records('facetoface_notice', array('id' => $id));
    $DB->delete_records('facetoface_notice_data', array('noticeid' => $id));
    $transaction->allow_commit();
    redirect($returnurl);
}

// Setup editors.
$editoroptions = array(
    'noclean'  => false,
    'maxfiles' => 0,
    'context'  => $contextsystem,
);

if ($id == 0) {
    $notice = new stdClass();
    $notice->id = 0;
    $notice->name = '';
    $notice->text = '';
} else {
    $notice = $DB->get_record('facetoface_notice', array('id' => $id), '*', MUST_EXIST);
}
$notice->page = $page;
$notice->textformat = FORMAT_HTML;
$notice = file_prepare_standard_editor($notice, 'text', $editoroptions, $contextsystem, null, null, $id);

$customfields = facetoface_get_session_customfields();
foreach ($customfields as $field) {
    $fieldname = "custom_$field->shortname";
    $notice->$fieldname = facetoface_get_customfield_value($field, $notice->id, 'notice');
}

$mform = new mod_facetoface_sitenotice_form(null, compact('id', 'customfields', 'editoroptions'));
$mform->set_data($notice);
if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) {
    $fromform = file_postupdate_standard_editor($fromform, 'text', $editoroptions, $contextsystem, 'mod_facetoface', null, null);

    $todb = new stdClass();
    $todb->name = trim($fromform->name);
    $todb->text = trim($fromform->text);

    $transaction = $DB->start_delegated_transaction();
    if ($notice->id) {
        $todb->id = $fromform->id;
        $DB->update_record('facetoface_notice', $todb);
    } else {
        $fromform->id = $DB->insert_record('facetoface_notice', $todb);
    }

    foreach ($customfields as $field) {
        $fieldname = "custom_$field->shortname";
        if (empty($fromform->$fieldname)) {
            $fromform->$fieldname = ''; // need to be able to clear fields
        }
        facetoface_save_customfield_value($field->id, $fromform->$fieldname, $notice->id, 'notice');
    }
    $transaction->allow_commit();
    redirect($returnurl);
}

echo $OUTPUT->header();

$mform->display();

echo $OUTPUT->footer();
