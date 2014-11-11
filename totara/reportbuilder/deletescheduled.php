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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/core/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');

require_login();

// Get params
$id = required_param('id', PARAM_INT); //ID
$confirm = optional_param('confirm', '', PARAM_INT); // Delete confirmation hash

$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_url('/totara/reportbuilder/deletescheduled.php', array('id' => $id));
$PAGE->set_totara_menu_selected('myreports');

if (!$report = $DB->get_record('report_builder_schedule', array('id' => $id))) {
    print_error('error:invalidreportscheduleid', 'totara_reportbuilder');
}

if (!reportbuilder::is_capable($report->reportid)) {
    print_error('nopermission', 'totara_reportbuilder');
}
if ($report->userid != $USER->id) {
    require_capability('totara/reportbuilder:managereports', context_system::instance());
}

$reportname = $DB->get_field('report_builder', 'fullname', array('id' => $report->reportid));

$returnurl = new moodle_url('/my/reports.php');
$deleteurl = new moodle_url('/totara/reportbuilder/deletescheduled.php', array('id' => $report->id, 'confirm' => '1', 'sesskey' => $USER->sesskey));

if ($confirm == 1) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    } else {
        $DB->delete_records('report_builder_schedule', array('id' => $report->id));
        $report = new reportbuilder($id);
        \totara_reportbuilder\event\report_updated::create_from_report($report, 'scheduled')->trigger();
        totara_set_notification(get_string('deletedscheduledreport', 'totara_reportbuilder', format_string($reportname)),
                                $returnurl, array('class' => 'notifysuccess'));
    }
}
/// Display page
$PAGE->set_title(get_string('deletescheduledreport', 'totara_reportbuilder'));
$PAGE->set_heading(format_string($SITE->fullname));
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('deletescheduledreport', 'totara_reportbuilder'));
if (!$confirm) {
    echo $OUTPUT->confirm(get_string('deletecheckschedulereport', 'totara_reportbuilder', format_string($reportname)), $deleteurl, $returnurl);

    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->footer();
