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
 * @package totara
 * @subpackage cohort
 */
/**
 * This page displays the report of "orphaned users", who are not contained in any cohort
 */
require('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once($CFG->dirroot.'/totara/reportbuilder/lib.php');

$context = context_system::instance();

$sid = optional_param('sid', '0', PARAM_INT);
$format = optional_param('format', '', PARAM_TEXT); // export format
$debug  = optional_param('debug', false, PARAM_BOOL);

$PAGE->set_context($context);

$report = reportbuilder_get_embedded_report('cohort_orphaned_users', null, false, $sid);
// Handle a request for export
if($format!='') {
    $report->export_data($format);
    die;
}

$url = new moodle_url('/totara/cohort/orphanreport.php', array('format' => $format, 'debug' => $debug));
admin_externalpage_setup('cohorts', '', null, $url, array('pagelayout' => 'report'));

\totara_reportbuilder\event\report_viewed::create_from_report($report)->trigger();

$strcohorts = get_string('cohorts', 'totara_cohort');
echo $OUTPUT->header();


if($debug) {
    $report->debug($debug);
}
echo $OUTPUT->heading(get_string('orphanedusers', 'totara_cohort'));
echo $OUTPUT->container(get_string('orphanhelptext', 'totara_cohort'));

$report->display_search();
$report->display_sidebar_search();

// Print saved search buttons if appropriate.
echo $report->display_saved_search_options();

$report->display_table();

$output = $PAGE->get_renderer('totara_reportbuilder');
$output->export_select($report->_id, $sid);

echo $OUTPUT->footer();
