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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @author Russell England <russell.england@totaralms.com>
 * @package totara
 * @subpackage plan
 */

/**
 * Edit evidence
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once('edit_form.php');
require_once('lib.php');

require_login();

$userid = optional_param('userid', $USER->id, PARAM_INT);
$evidenceid = optional_param('id', 0, PARAM_INT);
$deleteflag = optional_param('d', false, PARAM_BOOL);
$deleteconfirmed = optional_param('delete', false, PARAM_BOOL);
$rolstatus = optional_param('status', 'all', PARAM_ALPHA);
if (!in_array($rolstatus, array('active','completed','all'))) {
    $rolstatus = 'all';
}

//Javascript include
local_js(array(
    TOTARA_JS_DATEPICKER,
    TOTARA_JS_PLACEHOLDER
));

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('noblocks');
$PAGE->set_url('/totara/plan/record/evidence/edit.php',
        array('id' => $evidenceid, 'userid' => $userid, 'status' => $rolstatus));

if (!$user = $DB->get_record('user', array('id' => $userid))) {
    print_error('usernotfound', 'totara_plan');
}

if (!empty($evidenceid)) {
    // Editing or deleting, check record exists
    if (!$item = $DB->get_record('dp_plan_evidence', array('id' => $evidenceid))) {
        print_error('error:evidenceidincorrect', 'totara_plan');
    } else {
        // Check if its readonly
        if ($item->readonly) {
            print_error('evidence_readonly', 'totara_plan');
        }
        // Check that the user owns this evidence
        $userid = $item->userid;
    }
}

// users can only view their own and their staff's pages
if ($USER->id != $userid && !totara_is_manager($userid) && !has_capability('totara/plan:accessanyplan', context_system::instance())) {
    print_error('error:cannotviewpage', 'totara_plan');
}

if ($USER->id == $userid) {
    // Own evidence
    $strheading = get_string('recordoflearning', 'totara_core');
    $usertype = 'learner';
} else {
    // Admin / manager
    $strheading = get_string('recordoflearningfor', 'totara_core') . fullname($user, true);
    $usertype = 'manager';
}

$indexurl = new moodle_url('/totara/plan/record/evidence/index.php', array('userid' => $userid));
$backlink = html_writer::tag('p', $OUTPUT->action_link($indexurl,
        get_string('backtoallx', 'totara_plan', get_string("evidenceplural", 'totara_plan'))));

if (!empty($evidenceid) || $deleteflag) {
    if ($deleteflag) {
        $action = 'delete';
    } else {
        $action = 'edit';
    }
    $itemurl = new moodle_url('/totara/plan/record/evidence/view.php', array('id' => $evidenceid));
} else {
    // New evidence, initialise values
    $item = new stdClass();
    $item->id = 0;
    $item->name = '';
    $item->description = '';
    $item->evidencetypeid = null;
    $action = 'add';
    $itemurl = $indexurl;
}

if ($deleteflag && $deleteconfirmed) {
    // Deletion confirmed
    require_sesskey();

    $transaction = $DB->start_delegated_transaction();
    $result = $DB->delete_records('dp_plan_evidence', array('id' => $item->id));
    $result = $result && $DB->delete_records('dp_plan_evidence_relation', array('evidenceid' => $item->id));
    $transaction->allow_commit();

    if (!$result) {
        print_error('error:evidencedeleted', 'totara_plan');
    } else {
        $fs = get_file_storage();
        $fs->delete_area_files($TEXTAREA_OPTIONS['context']->id, 'totara_plan', 'attachment', $item->id);

        add_to_log(SITEID, 'plan', 'deleted evidence',
                new moodle_url('/totara/plan/record/evidence/edit.php', array('id' => $item->id)),
                "{$item->name} (ID:{$item->id})");
        totara_set_notification(get_string('evidencedeleted', 'totara_plan'),
                $indexurl, array('class' => 'notifysuccess'));
    }
}

$item->descriptionformat = FORMAT_HTML;
$item = file_prepare_standard_editor($item, 'description',
        $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_plan', 'dp_plan_evidence', $item->id);

$fileoptions = $FILEPICKER_OPTIONS;
$fileoptions['maxfiles'] = 10;

$item = file_prepare_standard_filemanager($item, 'attachment',
        $fileoptions, $FILEPICKER_OPTIONS['context'], 'totara_plan', 'attachment', $item->id);

$mform = new plan_evidence_edit_form(
    null,
    array(
        'id' => $item->id,
        'userid' => $userid,
        'fileoptions' => $fileoptions
    )
);
$mform->set_data($item);

if ($data = $mform->get_data()) {
    if (!empty($data->evidencelink) && substr($data->evidencelink, 0, 7) != 'http://') {
        $data->evidencelink = 'http://' . $data->evidencelink;
    }
    $data->timemodified = time();
    $data->userid = $userid;

    // Settings for postupdate
    $data->description       = '';

    if (empty($data->id)) {
        // Create a new record
        $data->timecreated = $data->timemodified;
        $data->usermodified = $USER->id;
        $data->planid = 0;
        $data->id = $DB->insert_record('dp_plan_evidence', $data);
        $result = 'added';
    } else {
        // Update a record
        $DB->update_record('dp_plan_evidence', $data);
        $result = 'updated';
    }

    // save and relink embedded images
    $data = file_postupdate_standard_editor($data, 'description',
            $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_plan', 'dp_plan_evidence', $data->id);

    // process files, update the data record
    $data = file_postupdate_standard_filemanager($data, 'attachment',
            $fileoptions, $FILEPICKER_OPTIONS['context'], 'totara_plan', 'attachment', $data->id);

    $DB->update_record('dp_plan_evidence', $data);

    add_to_log(SITEID, 'plan', "{$result} evidence",
            new moodle_url('/totara/plan/record/evidence/edit.php', array('id' => $data->id)),
            "{$item->name} (ID:{$data->id})");
    totara_set_notification(get_string('evidence' . $result, 'totara_plan'), $itemurl, array('class' => 'notifysuccess'));
} else if ($mform->is_cancelled()) {
    if ($action == 'add') {
        redirect($indexurl);
    } else {
        redirect($itemurl);
    }
}

$PAGE->navbar->add(get_string('mylearning', 'totara_core'), '/my/learning.php');
$PAGE->navbar->add($strheading, '/totara/plan/record/index.php');
$PAGE->navbar->add(get_string('allevidence', 'totara_plan'));
$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);

echo $OUTPUT->header();

echo dp_display_plans_menu($userid, 0, $usertype, 'evidence/index', $rolstatus);

echo $OUTPUT->container_start('', 'dp-plan-content');

echo $OUTPUT->heading($strheading, 1);

dp_print_rol_tabs($rolstatus, 'evidence', $userid);

switch($action){
    case 'add':
    case 'edit':
        echo $OUTPUT->heading(get_string($action . 'evidence', 'totara_plan'));
        echo $backlink;
        $mform->display();
        break;

    case 'delete':
        echo $OUTPUT->heading(get_string($action . 'evidence', 'totara_plan'));
        echo $backlink;
        echo display_evidence_detail($item->id, true);
        $params = array('id' => $item->id, 'userid'=>$userid, 'd' => '1', 'delete' => '1', 'sesskey' => sesskey());
        $deleteurl = new moodle_url('/totara/plan/record/evidence/edit.php', $params);
        echo list_evidence_in_use($item->id);
        echo $OUTPUT->confirm(get_string('deleteevidenceareyousure', 'totara_plan'), $deleteurl, $indexurl);
        break;
}

echo $OUTPUT->container_end();
echo $OUTPUT->footer();
