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
$PAGE->requires->strings_for_js(array('saving', 'confirmcoldelete', 'hide', 'show', 'delete', 'moveup', 'movedown', 'add'), 'totara_reportbuilder');
$args = array('args' => '{"user_sesskey":"'.$USER->sesskey.'", "rb_reportid":'.$id.', "rb_column_headings":'.json_encode($report->get_default_headings_array()).'}');
$jsmodule = array(
    'name' => 'totara_reportbuildercolumns',
    'fullpath' => '/totara/reportbuilder/columns.js',
    'requires' => array('json'));
$PAGE->requires->js_init_call('M.totara_reportbuildercolumns.init', $args, false, $jsmodule);

// toggle show/hide column
if ($h !== null && isset($cid)) {
    if ($report->showhide_column($cid, $h)) {
        $vis = $h ? 'Hide' : 'Show';
        add_to_log(SITEID, 'reportbuilder', 'update report', 'columns.php?id='. $id,
            $vis . ' Column: Report ID=' . $id . ', Column ID=' . $cid);
        totara_set_notification(get_string('column_vis_updated', 'totara_reportbuilder'), $returnurl, array('class' => 'notifysuccess'));
    } else {
        totara_set_notification(get_string('error:column_vis_not_updated', 'totara_reportbuilder'), $returnurl);
    }
}

// delete column
if ($d and $confirm) {
    if (!confirm_sesskey()) {
        totara_set_notification(get_string('error:bad_sesskey', 'totara_reportbuilder'), $returnurl);
    }

    if (isset($cid)) {
        if ($report->delete_column($cid)) {
            add_to_log(SITEID, 'reportbuilder', 'update report', 'columns.php?id='. $id,
                'Deleted Column: Report ID=' . $id . ', Column ID=' . $cid);
            totara_set_notification(get_string('column_deleted', 'totara_reportbuilder'), $returnurl, array('class' => 'notifysuccess'));
        } else {
            totara_set_notification(get_string('error:column_not_deleted', 'totara_reportbuilder'), $returnurl);
        }
    }
}

// confirm deletion column
if ($d) {

    echo $output->header();

    if (isset($cid)) {
        $confirmurl = new moodle_url('/totara/reportbuilder/columns.php', array('d' => '1', 'id' => $id, 'cid' => $cid, 'confirm' => 'l', 'sesskey' => $USER->sesskey));
        echo $output->confirm(get_string('confirmcolumndelete', 'totara_reportbuilder'), $confirmurl, $returnurl);
    }

    echo $output->footer();
    die;
}

// move column
if ($m && isset($cid)) {
    if ($report->move_column($cid, $m)) {
        add_to_log(SITEID, 'reportbuilder', 'update report', 'columns.php?id='. $id,
            'Moved Column: Report ID=' . $id . ', Column ID=' . $cid);
        totara_set_notification(get_string('column_moved', 'totara_reportbuilder'), $returnurl, array('class' => 'notifysuccess'));
    } else {
        totara_set_notification(get_string('error:column_not_moved', 'totara_reportbuilder'), $returnurl);
    }
}

// form definition
$mform = new report_builder_edit_columns_form(null, compact('id', 'report'));

// form results check
if ($mform->is_cancelled()) {
    redirect($returnurl);
}
if ($fromform = $mform->get_data()) {

    if (empty($fromform->submitbutton)) {
        totara_set_notification(get_string('error:unknownbuttonclicked', 'totara_reportbuilder'), $returnurl);
    }
    if (build_columns($id, $fromform, $report)) {
        reportbuilder_set_status($id);
        add_to_log(SITEID, 'reportbuilder', 'update report', 'columns.php?id='. $id,
            'Column Settings: Report ID=' . $id);
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

if (reportbuilder_get_status($id)) {
    echo $output->cache_pending_notification($id);
}

$currenttab = 'columns';
include_once('tabs.php');

// display the form
$mform->display();

// include JS object to define the column headings
echo html_writer::script(
    "var rb_reportid = {$id}; var rb_column_headings = " .
    json_encode($report->get_default_headings_array()) . ';');

echo $output->footer();



/**
 * Update the report columns table with data from the submitted form
 *
 * @param integer $id Report ID to update
 * @param object $fromform Moodle form object containing the new column data
 * @param object $report The report object
 *
 * @return boolean True if the columns could be updated successfully
 */
function build_columns($id, $fromform, $report) {
    global $DB;

    $transaction = $DB->start_delegated_transaction();

    $oldcolumns = $DB->get_records('report_builder_columns', array('reportid' => $id));
    // see if existing columns have changed
    foreach ($oldcolumns as $cid => $oldcolumn) {
        $columnname = "column{$cid}";
        $headingname = "heading{$cid}";
        $customheadingname = "customheading{$cid}";
        // update db only if column has changed
        if (isset($fromform->$columnname) &&
            ($fromform->$columnname != $oldcolumn->type.'-'.$oldcolumn->value ||
            $fromform->$headingname != $oldcolumn->heading ||
            $fromform->$customheadingname != $oldcolumn->customheading)) {
            $heading = isset($fromform->$headingname) ? $fromform->$headingname : '';
            $todb = new stdClass();
            $todb->id = $cid;
            $parts = explode('-', $fromform->$columnname);
            $todb->type = $parts[0];
            $todb->value = $parts[1];
            $todb->heading = $heading;
            $todb->customheading = $fromform->$customheadingname;
            $DB->update_record('report_builder_columns', $todb);
        }
    }
    // add any new columns
    if (isset($fromform->newcolumns) && $fromform->newcolumns != '0') {
        $heading = isset($fromform->newheading) ? $fromform->newheading : '';
        $todb = new stdClass();
        $todb->reportid = $id;
        $parts = explode('-', $fromform->newcolumns);
        $todb->type = $parts[0];
        $todb->value = $parts[1];
        $todb->heading = $heading;
        $todb->customheading = $fromform->newcustomheading;
        $sortorder = $DB->get_field('report_builder_columns', 'MAX(sortorder) + 1', array('reportid' => $id));
        if (!$sortorder) {
            $sortorder = 1;
        }
        $todb->sortorder = $sortorder;
        $DB->insert_record('report_builder_columns', $todb);
    }
    // update default column settings
    if (isset($fromform->defaultsortcolumn)) {
        $todb = new stdClass();
        $todb->id = $id;
        $todb->defaultsortcolumn = $fromform->defaultsortcolumn;
        $todb->defaultsortorder = $fromform->defaultsortorder;
        $DB->update_record('report_builder', $todb);
    }

    $transaction->allow_commit();

    return true;
}
