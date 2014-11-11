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
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/report_forms.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

$id = required_param('id', PARAM_INT); // Report builder id.
$d = optional_param('d', null, PARAM_TEXT); // Delete.
$m = optional_param('m', null, PARAM_TEXT); // Move.
$fid = optional_param('fid', null, PARAM_INT); // Filter id.
$searchcolumnid = optional_param('searchcolumnid', null, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT); // Confirm delete.

admin_externalpage_setup('rbmanagereports');

$output = $PAGE->get_renderer('totara_reportbuilder');

$returnurl = new moodle_url('/totara/reportbuilder/filters.php', array('id' => $id));

$report = new reportbuilder($id, null, false, null, null, true);


// Check filterheadings and searchcolumnheadings for multilang spans. Need to set context to use format_string.
$PAGE->set_context(context_user::instance($USER->id));

$filterheadings = array();
foreach ($report->src->filteroptions as $option) {
    $key = $option->type . '-' . $option->value;
    $filterheadings[$key] = format_string($option->label);
}

$searchcolumnheadings = array();
foreach ($report->columnoptions as $option) {
    if ($option->is_searchable()) {
        $key = $option->type . '-' . $option->value;
        $searchcolumnheadings[$key] = format_string($option->name);
    }
}

// Include jquery.
local_js();
$PAGE->requires->strings_for_js(array('saving', 'confirmfilterdelete', 'confirmsearchcolumndelete', 'delete', 'moveup',
    'movedown', 'add', 'initialdisplay_error'), 'totara_reportbuilder');
$args = array('args' => '{"user_sesskey":"'.$USER->sesskey.'", "rb_reportid":'.$id.',
    "rb_filters":'.count($report->filters).', "rb_search_columns":'.count($report->searchcolumns).',
    "rb_initial_display":'.$report->initialdisplay.', "rb_filter_headings":'.json_encode($filterheadings).',
    "rb_search_column_headings":'.json_encode($searchcolumnheadings).'}');
$jsmodule = array(
    'name' => 'totara_reportbuilderfilters',
    'fullpath' => '/totara/reportbuilder/filters.js',
    'requires' => array('json'));
$PAGE->requires->js_init_call('M.totara_reportbuilderfilters.init', $args, false, $jsmodule);


// Delete fields or columns.
if ($d and $confirm) {
    if (!confirm_sesskey()) {
        totara_set_notification(get_string('error:bad_sesskey', 'totara_reportbuilder'), $returnurl);
    }
    if (isset($fid)) {
        if ($report->initialdisplay && count($report->filters) <= 1) {
                totara_set_notification(get_string('initialdisplay_error', 'totara_reportbuilder'), $returnurl);
        } else {
            if ($report->delete_filter($fid)) {
                \totara_reportbuilder\event\report_updated::create_from_report($report, 'filters')->trigger();
                totara_set_notification(get_string('filterdeleted', 'totara_reportbuilder'), $returnurl,
                    array('class' => 'notifysuccess'));
            } else {
                totara_set_notification(get_string('error:filter_not_deleted', 'totara_reportbuilder'), $returnurl);
            }
        }
    } else if (isset($searchcolumnid)) {
        if ($report->initialdisplay && count($report->filters) <= 1) {
                totara_set_notification(get_string('initialdisplay_error', 'totara_reportbuilder'), $returnurl);
        } else {
            if ($report->delete_search_column($searchcolumnid)) {
                \totara_reportbuilder\event\report_updated::create_from_report($report, 'filters')->trigger();
                totara_set_notification(get_string('searchcolumndeleted', 'totara_reportbuilder'), $returnurl,
                    array('class' => 'notifysuccess'));
            } else {
                totara_set_notification(get_string('error:search_column_not_deleted', 'totara_reportbuilder'), $returnurl);
            }
        }
    }
}

// Confirm deletion of field or column.
if ($d) {
    echo $output->header();

    if (isset($fid)) {
        $confirmurl = new moodle_url('/totara/reportbuilder/filters.php',
            array('d' => '1', 'id' => $id, 'fid' => $fid, 'confirm' => '1', 'sesskey' => $USER->sesskey));
        echo $output->confirm(get_string('confirmfilterdelete', 'totara_reportbuilder'), $confirmurl, $returnurl);
    } else if (isset($searchcolumnid)) {
        $confirmurl = new moodle_url('/totara/reportbuilder/filters.php',
            array('d' => '1', 'id' => $id, 'searchcolumnid' => $searchcolumnid, 'confirm' => '1', 'sesskey' => $USER->sesskey));
        echo $output->confirm(get_string('confirmsearchcolumndelete', 'totara_reportbuilder'), $confirmurl, $returnurl);
    }

    echo $output->footer();
    exit;
}

// Move filter.
if ($m && isset($fid)) {
    if ($report->move_filter($fid, $m)) {
        \totara_reportbuilder\event\report_updated::create_from_report($report, 'filters')->trigger();
        totara_set_notification(get_string('filtermoved', 'totara_reportbuilder'), $returnurl, array('class' => 'notifysuccess'));
    } else {
        totara_set_notification(get_string('error:filter_not_moved', 'totara_reportbuilder'), $returnurl);
    }
}

// Form definition.
$data = $report->get_all_filters_select();
$data['id'] = $id;
$data['report'] = $report;
$mform = new report_builder_edit_filters_form(null, $data);

// Form results check.
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/totara/reportbuilder/index.php'));
}
if ($fromform = $mform->get_data()) {

    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'totara_reportbuilder', $returnurl);
    }
    if (build_filters($id, $fromform)) {
        $DB->set_field('report_builder', 'toolbarsearch', !$fromform->toolbarsearchdisabled, array('id' => $id));
        reportbuilder_set_status($id);
        $report = new reportbuilder($id);
        \totara_reportbuilder\event\report_updated::create_from_report($report, 'filters')->trigger();
        totara_set_notification(get_string('filters_updated', 'totara_reportbuilder'), $returnurl,
            array('class' => 'notifysuccess'));
    } else {
        totara_set_notification(get_string('error:filters_not_updated', 'totara_reportbuilder'), $returnurl);
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

$currenttab = 'filters';
require('tabs.php');

// Display the form.
$mform->display();

// Include JS vars.
$js = "var rb_reportid = {$id}; var rb_filter_headings = " . json_encode($filterheadings) .
        "; var rb_search_column_headings = " . json_encode($searchcolumnheadings) . ";";
echo html_writer::script($js);

echo $output->footer();

/**
 * Update the report filters table with data from the submitted form
 *
 * @param integer $id Report ID to update
 * @param object $fromform Moodle form object containing the new filter data
 *
 * @return boolean True if the filters could be updated successfully
 */
function build_filters($id, $fromform) {
    global $DB;

    $transaction = $DB->start_delegated_transaction();

    // See if existing filters have changed.
    $oldfilters = $DB->get_records('report_builder_filters', array('reportid' => $id));
    foreach ($oldfilters as $fid => $oldfilter) {
        $filtername = "filter{$fid}";
        $advancedname = "advanced{$fid}";
        $headingname = "filtername{$fid}";
        $customheadingname = "customname{$fid}";
        // Update db only if filter has changed.
        if (isset($fromform->$filtername) &&
            ($fromform->$filtername != $oldfilter->type.'-'.$oldfilter->value ||
            $fromform->$advancedname != $oldfilter->advanced ||
            $fromform->$headingname != $oldfilter->filtername ||
            $fromform->$customheadingname != $oldfilter->customname)) {
            $name = isset($fromform->$headingname) ? $fromform->$headingname : '';
            $todb = new stdClass();
            $todb->id = $fid;
            $todb->advanced = $fromform->$advancedname;
            $parts = explode('-', $fromform->$filtername);
            $todb->type = $parts[0];
            $todb->value = $parts[1];
            $todb->filtername = $name;
            $todb->customname = $fromform->$customheadingname;
            $DB->update_record('report_builder_filters', $todb);
        }
    }

    // See if existing search columns have changed.
    $oldsearchcolumns = $DB->get_records('report_builder_search_cols', array('reportid' => $id));
    foreach ($oldsearchcolumns as $searchcolumnid => $oldsearchcolumn) {
        $searchcolumnname = "searchcolumn{$searchcolumnid}";
        // Update db only if search column has changed.
        if (isset($fromform->$searchcolumnname) &&
            ($fromform->$searchcolumnname != $oldsearchcolumn->type.'-'.$oldsearchcolumn->value)) {
            $todb = new stdClass();
            $todb->id = $searchcolumnid;
            $parts = explode('-', $fromform->$searchcolumnname);
            $todb->type = $parts[0];
            $todb->value = $parts[1];
            $DB->update_record('report_builder_search_cols', $todb);
        }
    }

    // Add any new filters.
    $regions = rb_filter_type::get_all_regions();
    foreach ($regions as $regionkey => $regioncode) {
        if (isset($fromform->{'new'.$regioncode.'filter'}) && $fromform->{'new'.$regioncode.'filter'} != '0') {
            $name = isset($fromform->{'new' . $regioncode . 'filtername'}) ? $fromform->{'new' . $regioncode . 'filtername'} : '';
            $todb = new stdClass();
            $todb->reportid = $id;
            $todb->advanced = isset($fromform->{'new' . $regioncode . 'advanced'}) ?
                    $fromform->{'new' . $regioncode . 'advanced'} : 0;
            $parts = explode('-', $fromform->{'new' . $regioncode . 'filter'});
            $todb->region = $regionkey;
            $todb->type = $parts[0];
            $todb->value = $parts[1];
            $todb->filtername = $name;
            $todb->customname = isset($fromform->{'new' . $regioncode . 'customname'}) ?
                    $fromform->{'new' . $regioncode . 'customname'} : 0;
            $sortorder = $DB->get_field('report_builder_filters', 'MAX(sortorder) + 1',
                    array('reportid' => $id, 'region' => $regionkey));
            if (!$sortorder) {
                $sortorder = 1;
            }
            $todb->sortorder = $sortorder;
            $DB->insert_record('report_builder_filters', $todb);
        }
    }

    // Add any new search columns.
    if (isset($fromform->newsearchcolumn) && $fromform->newsearchcolumn != '0') {
        $todb = new stdClass();
        $todb->reportid = $id;
        $parts = explode('-', $fromform->newsearchcolumn);
        $todb->type = $parts[0];
        $todb->value = $parts[1];
        $DB->insert_record('report_builder_search_cols', $todb);
    }

    $transaction->allow_commit();
    return true;
}
