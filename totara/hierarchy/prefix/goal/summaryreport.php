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
 * @subpackage totara_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/lib.php');

// Check if Goals are enabled.
goal::check_feature_enabled();

$sid = optional_param('sid', '0', PARAM_INT);
$format = optional_param('format', '', PARAM_TEXT);
$debug = optional_param('debug', 0, PARAM_INT);

$url = new moodle_url('/totara/hierarchy/prefix/goal/summaryreport.php', array('format' => $format, 'debug' => $debug));
admin_externalpage_setup('goalreport', '', null, $url);

$context = context_system::instance();
$canedit = has_capability('totara/hierarchy:editgoalreport', $context);

if (!$report = reportbuilder_get_embedded_report('goal_summary', null, false, $sid)) {
    print_error('error:couldnotgenerateembeddedreport', 'totara_reportbuilder');
}

$goalframeworkid = $report->get_param_value('goalframeworkid');

if (!$goalframeworkid) {
    echo $OUTPUT->header();
    $overviewurl = new moodle_url('/totara/hierarchy/prefix/goal/reports.php');
    echo $OUTPUT->container(get_string('selectaframework', 'rb_source_goal_summary', $overviewurl->out()));
    echo $OUTPUT->footer();
    exit;
}

$renderer = $PAGE->get_renderer('totara_reportbuilder');

if ($format != '') {
    $report->export_data($format);
    die;
}

if ($canedit) {
    $PAGE->set_button($report->edit_button());
} else {
    $PAGE->set_button(null);
}
echo $renderer->header();

if ($debug) {
    $report->debug($debug);
}

$countfiltered = $report->get_filtered_count();
$countall = $report->get_full_count();

$goalframework = $DB->get_record('goal_framework', array('id' => $goalframeworkid));

$heading = get_string('goalsummaryreportforx', 'totara_hierarchy', $goalframework->fullname);
$heading .= $renderer->print_result_count_string($countfiltered, $countall);
echo $renderer->heading($heading);

echo $renderer->print_description($report->description, $report->_id);

$report->include_js();

$report->display_search();
$report->display_sidebar_search();

// Print saved search buttons if appropriate.
echo $report->display_saved_search_options();

echo $renderer->showhide_button($report->_id, $report->shortname);
$report->display_table();
$renderer->export_select($report->_id, $sid);

echo $renderer->footer();
