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
 * Page containing general report settings
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/report_forms.php');
require_once($CFG->dirroot . '/totara/core/lib/scheduler.php');

$id = required_param('id', PARAM_INT); // Report builder id.

admin_externalpage_setup('rbmanagereports');

$output = $PAGE->get_renderer('totara_reportbuilder');

$returnurl = $CFG->wwwroot . "/totara/reportbuilder/general.php?id=$id";

$report = new reportbuilder($id);
$schedule = array();
if ($report->cache) {
    $cache = reportbuilder_get_cached($id);
    $scheduler = new scheduler($cache, array('nextevent' => 'nextreport'));
    $schedule = $scheduler->to_array();
}

// Form definition.
$record = new stdClass();
$record->id = $report->_id;
$record->fullname = $report->fullname;
$record->description = $report->description;
$record->descriptionformat = FORMAT_HTML;
$record->hidden = $report->hidden;
$record->recordsperpage = $report->recordsperpage;
$record = file_prepare_standard_editor($record, 'description', $TEXTAREA_OPTIONS, context_system::instance(),
    'totara_reportbuilder', 'report_builder', $record->id);

$mform = new report_builder_edit_form(null, array('report' => $report, 'record' => $record));

// form results check
if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/totara/reportbuilder/index.php');
}
if ($fromform = $mform->get_data()) {

    if (empty($fromform->submitbutton)) {
        totara_set_notification(get_string('error:unknownbuttonclicked', 'totara_reportbuilder'), $returnurl);
    }

    $todb = new stdClass();
    $todb->id = $id;
    $todb->timemodified = time();
    $todb->fullname = $fromform->fullname;
    $todb->hidden = $fromform->hidden;
    $todb->description_editor = $fromform->description_editor;
    // ensure we show between 1 and 9999 records
    $rpp = min(9999, max(1, (int) $fromform->recordsperpage));
    $todb->recordsperpage = $rpp;
    $todb = file_postupdate_standard_editor($todb, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
        'totara_reportbuilder', 'report_builder', $todb->id);

    $DB->update_record('report_builder', $todb);

    $report = new reportbuilder($id);
    \totara_reportbuilder\event\report_updated::create_from_report($report, 'general')->trigger();
    totara_set_notification(get_string('reportupdated', 'totara_reportbuilder'), $returnurl, array('class' => 'notifysuccess'));
}

echo $output->header();

echo $output->container_start('reportbuilder-navlinks');
echo $output->view_all_reports_link() . ' | ';
echo $output->view_report_link($report->report_url());
echo $output->container_end();

echo $output->heading(get_string('editreport', 'totara_reportbuilder', format_string($report->fullname)));

if ($report->get_cache_status() > 0) {
    echo $output->cache_pending_notification($id);
}

$currenttab = 'general';
require('tabs.php');

// display the form
$mform->display();

echo $output->footer();
