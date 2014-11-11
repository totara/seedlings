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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Maria Torres <maria.torres@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Page containing list of saved searches for this report
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/core/utils.php');
require_once('report_forms.php');

require_login();
if (isguestuser()) {
    redirect(get_login_url());
}

// This is the custom half ajax Totara page, we MUST send some headers here at least...
send_headers('text/html', true);

$id = optional_param('id', null, PARAM_INT); // Id for report.
$sid = optional_param('sid', null, PARAM_INT); // Id for saved search.
$action = optional_param('action', 'show', PARAM_ALPHANUMEXT); // Action to be executed.
$confirm = optional_param('confirm', false, PARAM_BOOL); // Confirm delete.
$returnurl = new moodle_url('/totara/reportbuilder/savedsearches.php', array('id' => $id));;

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/totara/reportbuilder/savedsearches.php', array('id' => $id, 'sid' => $sid));
$PAGE->set_totara_menu_selected('myreports');

$output = $PAGE->get_renderer('totara_reportbuilder');

$report = new reportbuilder($id, null, false, $sid);

// Get info about the saved search we are dealing with.
if ($sid) {
    $conditions = array('id' => $sid, 'reportid' => $id, 'userid' => $USER->id);
    $search = $DB->get_record('report_builder_saved', $conditions, '*');
    if (!$search) {
        print_error('error:invalidsavedsearchid', 'totara_reportbuilder');
    }
}

if (!$report->is_capable($id)) {
    print_error('nopermission', 'totara_reportbuilder');
}

$pagetitle = format_string(get_string('savesearch', 'totara_reportbuilder') . ': ' . $report->fullname);
$PAGE->set_title($pagetitle);

if ($action === 'delete') {
    if (!$sid) {
        redirect($returnurl);
    }
    if ($confirm) {
        require_sesskey();
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('report_builder_schedule', array('savedsearchid' => $sid));
        $DB->delete_records('report_builder_saved', array('id' => $sid));
        $transaction->allow_commit();
        redirect($returnurl);
    }
    $messageend = '';
    // Is this saved search being used in any scheduled reports?
    if ($scheduled = $DB->get_records('report_builder_schedule', array('savedsearchid' => $sid))) {
        // Display a message and list of scheduled reports using this saved search.
        ob_start();
        totara_print_scheduled_reports(false, false, array("rbs.savedsearchid = ?", array($sid)));
        $out = ob_get_contents();
        ob_end_clean();

        $messageend = get_string('savedsearchinscheduleddelete', 'totara_reportbuilder', $out) . str_repeat(html_writer::empty_tag('br'), 2);
    }

    echo $output->heading(get_string('savedsearches', 'totara_reportbuilder'), 1);

    $messageend .= get_string('savedsearchconfirmdelete', 'totara_reportbuilder', format_string($search->name));

    // Prompt to delete.
    $params = array('id' => $id, 'sid' => $sid, 'action' => 'delete', 'confirm' => 'true', 'sesskey' => $USER->sesskey);
    $confirmurl = new moodle_url('/totara/reportbuilder/savedsearches.php', $params);
    echo $output->confirm($messageend, $confirmurl, $returnurl);
    die;
}

if ($action === 'edit') {
    if (!$sid) {
        redirect($returnurl);
    }

    $data = clone($search);
    $data->sid = $data->id;
    $data->id = $data->reportid;
    $data->action = 'edit';

    $mform = new report_builder_save_form(null, array('report' => $report, 'data' => $data));

    if ($data = $mform->get_data()) {
        $todb = new stdClass();
        $todb->id = $data->sid;
        $todb->name = $data->name;
        $todb->ispublic = $data->ispublic;
        $todb->timemodified = time();
        $DB->update_record('report_builder_saved', $todb);
        redirect($returnurl);
    }

    echo $output->heading(get_string('savedsearches', 'totara_reportbuilder'), 1);
    $mform->display();
    die;
}

// Show users searches.
echo $output->heading(get_string('savedsearches', 'totara_reportbuilder'), 1);

$searches = $DB->get_records('report_builder_saved', array('userid' => $USER->id, 'reportid' => $id));
if (!empty($searches)) {
    echo $output->saved_searches_table($searches, $report);
} else {
    echo html_writer::tag('p', get_string('error:nosavedsearches', 'totara_reportbuilder'));
}
