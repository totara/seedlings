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
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Page for displaying user generated reports
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

$format    = optional_param('format', '', PARAM_TEXT);
$id = required_param('id', PARAM_INT);
$sid = optional_param('sid', '0', PARAM_INT);
$debug = optional_param('debug', 0, PARAM_INT);

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/totara/reportbuilder/report.php', array('id' => $id));
$PAGE->set_totara_menu_selected('myreports');
$PAGE->set_pagelayout('noblocks');

// New report object.
$report = new reportbuilder($id, null, false, $sid);
if (!$report->is_capable($id)) {
    print_error('nopermission', 'totara_reportbuilder');
}
$report->handle_pre_display_actions();

// Embedded reports can only be viewed through their embedded url.
if ($report->embedded) {
    print_error('cannotviewembedded', 'totara_reportbuilder');
}

if ($format != '') {
    $report->export_data($format);
    die;
}

\totara_reportbuilder\event\report_viewed::create_from_report($report)->trigger();

$PAGE->requires->string_for_js('reviewitems', 'block_totara_alerts');
$report->include_js();

// display results as graph if report uses the graphical_feedback_questions source
$graph = (substr($report->source, 0, strlen('graphical_feedback_questions')) ==
    'graphical_feedback_questions');

$countfiltered = $report->get_filtered_count();
$countall = $report->get_full_count();

$fullname = format_string($report->fullname);
$pagetitle = format_string(get_string('report', 'totara_reportbuilder').': '.$fullname);

$PAGE->set_title($pagetitle);
$PAGE->set_button($report->edit_button());
$PAGE->navbar->add(get_string('myreports', 'totara_reportbuilder'), new moodle_url('/my/reports.php'));
$PAGE->navbar->add($fullname);
$PAGE->set_heading(format_string($SITE->fullname));

$output = $PAGE->get_renderer('totara_reportbuilder');

echo $output->header();

$report->display_redirect_link();

// Display heading including filtering stats.
if ($graph) {
    echo $output->heading($fullname);
} else {
    echo $output->heading("$fullname: " .
        $output->print_result_count_string($countfiltered, $countall));
}

if ($debug) {
    $report->debug($debug);
}

// print report description if set
echo $output->print_description($report->description, $report->_id);

// print filters
$report->display_search();
$report->display_sidebar_search();

// print saved search buttons if appropriate
echo $report->display_saved_search_options();

// Show results.
if ($graph) {
    print $report->print_feedback_results();
} else {
    echo $output->showhide_button($report->_id, $report->shortname);
    $report->display_table();
}

// Export button.
$output->export_select($report->_id, $sid);

echo $output->footer();
