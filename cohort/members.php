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
 * @author Jake Salmon <jake.salmon@kineo.com>
 * @package totara
 * @subpackage cohort
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once($CFG->dirroot.'/totara/reportbuilder/lib.php');

$id     = optional_param('id', false, PARAM_INT);
$sid = optional_param('sid', '0', PARAM_INT);
$format = optional_param('format','',PARAM_TEXT); //export format
$debug  = optional_param('debug', false, PARAM_BOOL);

$context = context_system::instance();

$PAGE->set_context($context);

$url = new moodle_url('/cohort/members.php', array('id' => $id, 'format' => $format, 'debug' => $debug));
admin_externalpage_setup('cohorts', '', null, $url, array('pagelayout' => 'report'));

if (!$id) {
    echo $OUTPUT->header();
    $url = new moodle_url('/cohort/index.php');
    echo $OUTPUT->container(get_string('cohortmembersselect', 'totara_cohort', $url->out()));
    echo $OUTPUT->footer();
    exit;
}

$cohort = $DB->get_record('cohort',array('id' => $id), '*', MUST_EXIST);

$report = reportbuilder_get_embedded_report('cohort_members', array('cohortid' => $id), false, $sid);

if ($format != '') {
    $report->export_data($format);
    die;
}

$strheading = get_string('viewmembers', 'totara_cohort');
totara_cohort_navlinks($cohort->id, $cohort->name, $strheading);
echo $OUTPUT->header();
if ($debug) {
    $report->debug($debug);
}
if (isset($id)) {
    echo $OUTPUT->heading(format_string($cohort->name));
    echo cohort_print_tabs('viewmembers', $cohort->id, $cohort->cohorttype, $cohort);
}

$report->display_search();
$report->display_sidebar_search();

// Print saved search buttons if appropriate.
echo $report->display_saved_search_options();

$report->display_table();
$output = $PAGE->get_renderer('totara_reportbuilder');
$output->export_select($report->_id, $sid);

echo $OUTPUT->footer();
