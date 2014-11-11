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
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Page for returning report table for AJAX call
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');

$id = required_param('id', PARAM_INT);
$debug = optional_param('debug', 0, PARAM_INT);
$searched = optional_param_array('submitgroup', array(), PARAM_ALPHANUM);

if ($CFG->forcelogin) {
    ajax_require_login();
}
$PAGE->set_context(context_system::instance());

// New report object.
$report = new reportbuilder($id, null, false, 0);
if (!$report->is_capable($id)) {
    print_error('nopermission', 'totara_reportbuilder');
}

if (!empty($report->embeddedurl)) {
    $PAGE->set_url($report->embeddedurl);
} else {
    $PAGE->set_url('/totara/reportbuilder/report.php', array('id' => $id));
}
$PAGE->set_totara_menu_selected('myreports');
$PAGE->set_pagelayout('noblocks');

\totara_reportbuilder\event\report_viewed::create_from_report($report)->trigger();

$override_initial = isset($searched['addfilter']);
$hide_initial_display = ($report->initialdisplay == RB_INITIAL_DISPLAY_HIDE && !$override_initial);
$countfiltered = 0;
$countall = 0;

if (!$hide_initial_display || $report->is_report_filtered()) {
    $countfiltered = $report->get_filtered_count(true);
    $countall = $report->get_full_count();
}

$output = $PAGE->get_renderer('totara_reportbuilder');

if ($debug) {
    $report->debug($debug);
}

// Construct the output which consists of a report, header and (eventually) sidebar filter counts.
// We put the data in a container so that jquery can search inside it.
echo html_writer::start_div('instantreportcontainer');

// Show report results.
$report->display_table();
$report->display_sidebar_search();

// Display heading including filtering stats.
echo $output->print_result_count_string($countfiltered, $countall);

// Close the container.
echo html_writer::end_div();
