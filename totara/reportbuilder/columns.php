<?php // $Id$
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

define('REPORT_BUILDER_IGNORE_PAGE_PARAMETERS', true); // We are setting up report here, do not accept source params.

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/report_forms.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

$id = required_param('id', PARAM_INT); // report builder id
$d = optional_param('d', null, PARAM_TEXT); // delete
$m = optional_param('m', null, PARAM_TEXT); // move
$h = optional_param('h', null, PARAM_TEXT); // show/hide
$cid = optional_param('cid', null, PARAM_INT); //column id
$confirm = optional_param('confirm', 0, PARAM_INT); // confirm delete

admin_externalpage_setup('rbmanagereports');

$output = $PAGE->get_renderer('totara_reportbuilder');

$returnurl = new moodle_url('/totara/reportbuilder/columns.php', array('id' => $id));

$report = new reportbuilder($id, null, false, null, null, true);

// include jquery
local_js();

$allowedadvanced = $report->src->get_allowed_advanced_column_options();
$grouped = $report->src->get_grouped_column_options();
$advoptions = $report->src->get_all_advanced_column_options();

// toggle show/hide column
if ($h !== null && isset($cid)) {
    if ($report->showhide_column($cid, $h)) {
        \totara_reportbuilder\event\report_updated::create_from_report($report, 'columns')->trigger();
        totara_set_notification(get_string('column_vis_updated', 'totara_reportbuilder'), $returnurl, array('class' => 'notifysuccess'));
    } else {
        totara_set_notification(get_string('error:column_vis_not_updated', 'totara_reportbuilder'), $returnurl);
    }
}

// delete column
if ($d and $cid) {
    if ($confirm and confirm_sesskey()) {
        if ($report->delete_column($cid)) {
            \totara_reportbuilder\event\report_updated::create_from_report($report, 'columns')->trigger();
            totara_set_notification(get_string('column_deleted', 'totara_reportbuilder'), $returnurl, array('class' => 'notifysuccess'));
        } else {
            totara_set_notification(get_string('error:column_not_deleted', 'totara_reportbuilder'), $returnurl);
        }
    }
    echo $output->header();

    $confirmurl = new moodle_url('/totara/reportbuilder/columns.php', array('d' => '1', 'id' => $id, 'cid' => $cid,
                                                                            'confirm' => '1', 'sesskey' => $USER->sesskey));
    echo $output->confirm(get_string('confirmcolumndelete', 'totara_reportbuilder'), $confirmurl, $returnurl);

    echo $output->footer();
    die;
}

// Move column.
if ($m and $cid and confirm_sesskey()) {
    if ($report->move_column($cid, $m)) {
        \totara_reportbuilder\event\report_updated::create_from_report($report, 'columns')->trigger();
        totara_set_notification(get_string('column_moved', 'totara_reportbuilder'), $returnurl, array('class' => 'notifysuccess'));
    } else {
        totara_set_notification(get_string('error:column_not_moved', 'totara_reportbuilder'), $returnurl);
    }
}

// Form definition.
$mform = new report_builder_edit_columns_form(null, compact('report', 'allowedadvanced', 'grouped', 'advoptions'));

// Form results check.
if ($mform->is_cancelled()) {
    redirect($returnurl);
}
if ($fromform = $mform->get_data()) {
    if (empty($fromform->submitbutton)) {
        totara_set_notification(get_string('error:unknownbuttonclicked', 'totara_reportbuilder'), $returnurl);
    }
    if (totara_reportbuilder_build_columns($fromform, $report, $allowedadvanced, $grouped)) {
        reportbuilder_set_status($id);
        $report = new reportbuilder($id);
        \totara_reportbuilder\event\report_updated::create_from_report($report, 'columns')->trigger();
        totara_set_notification(get_string('columns_updated', 'totara_reportbuilder'), $returnurl, array('class' => 'notifysuccess'));
    } else {
        totara_set_notification(get_string('error:columns_not_updated', 'totara_reportbuilder'), $returnurl);
    }

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

$currenttab = 'columns';
require('tabs.php');


$config = new stdClass();
$config->rb_reportid = $id;
$config->rb_column_headings = $report->get_default_headings_array();
$config->rb_grouped_columns = $grouped;
$config->rb_allowed_advanced = $allowedadvanced;
$config->rb_advanced_options = $advoptions;

$jsmodule = array(
    'name' => 'totara_reportbuildercolumns',
    'fullpath' => '/totara/reportbuilder/columns.js');
$PAGE->requires->js_init_call('M.totara_reportbuildercolumns.init', array($config), false, $jsmodule);

$PAGE->requires->strings_for_js(array('saving', 'confirmcoldelete', 'hide', 'show', 'delete', 'moveup', 'movedown', 'add'),
                                'totara_reportbuilder');
$report->src->columns_page_requires();

// Display the form.
$mform->display();

echo $output->footer();
die;

/**
 * Update the report columns table with data from the submitted form
 *
 * @param object $fromform Moodle form object containing the new column data
 * @param reportbuilder $report The report object
 * @param array $allowedadvanced
 * @param array $grouped
 *
 * @return boolean True if the columns could be updated successfully
 */
function totara_reportbuilder_build_columns($fromform, reportbuilder $report, $allowedadvanced, $grouped) {
    global $DB;

    $transaction = $DB->start_delegated_transaction();

    $id = $report->_id;

    $oldcolumns = $DB->get_records('report_builder_columns', array('reportid' => $id));
    // see if existing columns have changed
    foreach ($oldcolumns as $cid => $oldcolumn) {
        $columnname = "column{$cid}";
        $advancedname = "advanced{$cid}";
        $headingname = "heading{$cid}";
        $customheadingname = "customheading{$cid}";

        if (!isset($fromform->$columnname)) {
            // This should have been already deleted by ajax,
            // but this may happen on concurrent edits.
            $DB->delete_records('report_builder_columns', array('id' => $cid));
            continue;
        }

        $parts = explode('-', $fromform->$columnname);
        $coltype = $parts[0];
        $colvalue = $parts[1];

        if ($fromform->$customheadingname) {
            $heading = $fromform->$headingname;
        } else {
            $heading = null;
        }

        if (in_array($fromform->$columnname, $grouped)) {
            $fromform->$advancedname = '';
        } else if (empty($fromform->$advancedname)) {
            $fromform->$advancedname = '';
        } else if (!in_array($fromform->$advancedname, $allowedadvanced[$fromform->$columnname], true)) {
            $fromform->$advancedname = '';
        }

        $transform = null;
        $aggregate = null;
        if (strpos($fromform->$advancedname, 'transform_') === 0) {
            $transform = str_replace('transform_', '', $fromform->$advancedname);
            $aggregate = null;
        } else if (strpos($fromform->$advancedname, 'aggregate_') === 0) {
            $transform = null;
            $aggregate = str_replace('aggregate_', '', $fromform->$advancedname);
        }

        // Update db only if column has changed.
        if ($coltype !== $oldcolumn->type || $colvalue !== $oldcolumn->value ||
            $transform !== $oldcolumn->transform ||
            $aggregate !== $oldcolumn->aggregate ||
            $heading !== $oldcolumn->heading ||
            $fromform->$customheadingname != $oldcolumn->customheading) {

            $todb = new stdClass();
            $todb->id = $cid;
            $todb->type = $coltype;
            $todb->value = $colvalue;
            $todb->transform = $transform;
            $todb->aggregate = $aggregate;
            $todb->heading = $heading;
            $todb->customheading = $fromform->$customheadingname;
            $DB->update_record('report_builder_columns', $todb);
        }
    }
    // Add any new column.
    if (!empty($fromform->newcolumns)) {
        $parts = explode('-', $fromform->newcolumns);
        $coltype = $parts[0];
        $colvalue = $parts[1];

        $todb = new stdClass();
        $todb->reportid = $id;
        $todb->type = $coltype;
        $todb->value = $colvalue;

        if (in_array($fromform->newcolumns, $grouped)) {
            $fromform->newadvanced = '';
        } else if (empty($fromform->newadvanced)) {
            $fromform->newadvanced = '';
        } else if (!in_array($fromform->newadvanced, $allowedadvanced[$fromform->newcolumns], true)) {
            $fromform->newadvanced = '';
        }

        $todb->transform = null;
        $todb->aggregate = null;
        if (strpos($fromform->newadvanced, 'transform_') === 0) {
            $todb->transform = str_replace('transform_', '', $fromform->newadvanced);
            $todb->aggregate = null;
        } else if (strpos($fromform->newadvanced, 'aggregate_') === 0) {
            $todb->transform = null;
            $todb->aggregate = str_replace('aggregate_', '', $fromform->newadvanced);
        }

        $todb->customheading = $fromform->newcustomheading;
        if ($fromform->newcustomheading) {
            $todb->heading = $fromform->newheading;
        } else {
            $todb->heading = null;
        }

        $sortorder = $DB->get_field('report_builder_columns', 'MAX(sortorder) + 1', array('reportid' => $id));
        if (!$sortorder) {
            $sortorder = 1;
        }
        $todb->sortorder = $sortorder;
        $DB->insert_record('report_builder_columns', $todb);
    }

    // Mark report as modified after any column change.
    $todb = new stdClass();
    $todb->id = $id;
    if (isset($fromform->defaultsortcolumn)) {
        // Update default column settings.
        $todb->defaultsortcolumn = $fromform->defaultsortcolumn;
        $todb->defaultsortorder = $fromform->defaultsortorder;
    }
    $todb->timemodified = time();
    $DB->update_record('report_builder', $todb);

    // Fix sortorders if necessary.
    $columns = $DB->get_records('report_builder_columns', array('reportid' => $id), 'sortorder ASC, id ASC', 'id, sortorder');
    $i = 0;
    foreach ($columns as $column) {
        $i++;
        if ($column->sortorder != $i) {
            $DB->set_field('report_builder_columns', 'sortorder', $i, array('id' => $column->id));
        }
    }
    unset($columns);

    $transaction->allow_commit();

    return true;
}
