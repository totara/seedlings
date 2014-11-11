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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Page for setting up scheduled reports
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/scheduled_forms.php');

require_login();
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url('/totara/reportbuilder/scheduled.php');
$PAGE->set_totara_menu_selected('myreports');

$reportid = optional_param('reportid', 0, PARAM_INT); //report that a schedule is being added for
$id = optional_param('id', 0, PARAM_INT); //id if editing schedule

$myreportsurl = $CFG->wwwroot . '/my/reports.php';
$returnurl = $CFG->wwwroot . '/totara/reportbuilder/scheduled.php';
$output = $PAGE->get_renderer('totara_reportbuilder');

if ($id == 0) {
    // Try to create report object to catch invalid data.
    $report = new reportbuilder($reportid);
    $schedule = new stdClass();
    $schedule->id = 0;
    $schedule->reportid = $reportid;
    $schedule->frequency = null;
    $schedule->schedule = null;
    $schedule->exporttofilesystem = null;
    $schedule->userid = $USER->id;
} else {
    if (!$schedule = $DB->get_record('report_builder_schedule', array('id' => $id))) {
        print_error('error:invalidreportscheduleid', 'totara_reportbuilder');
    }

    $report = new reportbuilder($schedule->reportid);
}

if (!reportbuilder::is_capable($schedule->reportid)) {
    print_error('nopermission', 'totara_reportbuilder');
}
if ($schedule->userid != $USER->id) {
    require_capability('totara/reportbuilder:managereports', context_system::instance());
}

$savedsearches = $report->get_saved_searches($schedule->reportid, $USER->id);
if (!isset($report->src->redirecturl)) {
    $savedsearches[0] = get_string('alldata', 'totara_reportbuilder');
}

// Form definition.
$mform = new scheduled_reports_new_form(
    null,
    array(
        'id' => $id,
        'report' => $report,
        'frequency' => $schedule->frequency,
        'schedule' => $schedule->schedule,
        'savedsearches' => $savedsearches,
        'exporttofilesystem' => $schedule->exporttofilesystem
    )
);

$mform->set_data($schedule);

if ($mform->is_cancelled()) {
    redirect($myreportsurl);
}
if ($fromform = $mform->get_data()) {
    if (empty($fromform->submitbutton)) {
        totara_set_notification(get_string('error:unknownbuttonclicked', 'totara_reportbuilder'), $returnurl);
    }

    if ($fromform->id) {
        if ($newid = add_scheduled_report($fromform)) {
            totara_set_notification(get_string('updatescheduledreport', 'totara_reportbuilder'), $myreportsurl, array('class' => 'notifysuccess'));
        }
        else {
            totara_set_notification(get_string('error:updatescheduledreport', 'totara_reportbuilder'), $returnurl);
        }
    }
    else {
        if ($newid = add_scheduled_report($fromform)) {
            totara_set_notification(get_string('addedscheduledreport', 'totara_reportbuilder'), $myreportsurl, array('class' => 'notifysuccess'));
        }
        else {
            totara_set_notification(get_string('error:addscheduledreport', 'totara_reportbuilder'), $returnurl);
        }
    }
}

if ($id == 0) {
    $pagename = 'addscheduledreport';
} else {
    $pagename = 'editscheduledreport';
}

$PAGE->set_title(get_string($pagename, 'totara_reportbuilder'));
$PAGE->set_cacheable(true);
$PAGE->navbar->add(get_string('myreports', 'totara_reportbuilder'), new moodle_url('/my/reports.php'));
$PAGE->navbar->add(get_string($pagename, 'totara_reportbuilder'));
echo $output->header();

echo $output->heading(get_string($pagename, 'totara_reportbuilder'));

$mform->display();

echo $output->footer();

function add_scheduled_report($fromform) {
    global $DB, $USER;

    if (isset($fromform->reportid) && isset($fromform->format) && isset($fromform->frequency)) {
        $report = new stdClass();
        $report->schedule = $fromform->schedule;
        $report->frequency = $fromform->frequency;
        $scheduler = new scheduler($report);
        $nextevent = $scheduler->next(null, false);

        $transaction = $DB->start_delegated_transaction();
        $todb = new stdClass();
        if ($id = $fromform->id) {
            $todb->id = $id;
        }
        $todb->reportid = $fromform->reportid;
        $todb->savedsearchid = $fromform->savedsearchid;
        $todb->userid = $USER->id;
        $todb->format = $fromform->format;
        $todb->exporttofilesystem = $fromform->emailsaveorboth;
        $todb->frequency = $fromform->frequency;
        $todb->schedule = $fromform->schedule;
        $todb->nextreport = $nextevent->get_scheduled_time();
        if (!$id) {
            $newid = $DB->insert_record('report_builder_schedule', $todb);
        } else {
            $DB->update_record('report_builder_schedule', $todb);
            $newid = $todb->id;
        }
        $transaction->allow_commit();

        return $newid;
    }
    return false;
}
