<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2014 onwards Totara Learning Solutions LTD
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
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

define('REPORT_BUILDER_IGNORE_PAGE_PARAMETERS', true); // We are setting up report here, do not accept source params.

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/report_forms.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

$PAGE->requires->jquery();
$PAGE->requires->yui_module('moodle-totara_reportbuilder-graphicalreporting', 'M.reportbuilder.graphicalreport.init');

$id = required_param('reportid', PARAM_INT);

admin_externalpage_setup('rbmanagereports');

/** @var totara_reportbuilder_renderer|core_renderer $output */
$output = $PAGE->get_renderer('totara_reportbuilder');

$returnurl = new moodle_url('/totara/reportbuilder/graph.php', array('reportid' => $id));

$report = new reportbuilder($id, null, false, null, null, true);
$graph = $DB->get_records('report_builder_graph', array('reportid' => $id));
if (!$graph) {
    $graph = new stdClass();
    $graph->id = 0;
    $graph->reportid = $id;
    $graph->type = '';
    $graph->orientation = 'C';
    $graph->stacked = 0;
    $graph->maxrecords = 500;
    $graph->category = 'none';
    foreach ($report->columns as $key => $column) {
        if (!$column->display_column(true)) {
            continue;
        }
        $graph->category = $key;
        $graph->legend = $key;
        break;
    }

} else {
    while (count($graph) > 1) {
        // Only one graph allowed for now, delete all duplicates that were created accidentally.
        $delgraph = array_pop($graph);
        $DB->delete_records('report_builder_graph', array('id' => $delgraph->id));
    }
    $graph = reset($graph);
    if ($graph->category === 'columnheadings') {
        $graph->orientation = 'R';
        $graph->category = $graph->legend;
    } else {
        $graph->orientation = 'C';
        if ($graph->category !== 'none') {
            $graph->legend = $graph->category;
        }
    }
    if ($series = json_decode($graph->series, true)) {
        $graph->series = $series;
    } else {
        $graph->series = array();
    }
}

$mform = new report_builder_edit_graph_form(null, compact('report', 'graph'));

if ($mform->is_cancelled()) {
    redirect($returnurl);
}

if ($fromform = $mform->get_data()) {
    if (empty($fromform->type)) {
        if ($graph->id) {
            $DB->set_field('report_builder_graph', 'type', '', array('id' => $graph->id));
            $DB->set_field('report_builder_graph', 'timemodified', time(), array('id' => $graph->id));
        } else {
            $DB->delete_records('report_builder_graph', array('reportid' => $graph->reportid));
        }

    } else {
        if ($fromform->orientation === 'C') {
            unset($fromform->legend);
        } else {
            $fromform->category = 'columnheadings';
        }
        $fromform->series = json_encode($fromform->series);
        $fromform->timemodified = time();

        if ($graph->id) {
            $fromform->id = $graph->id;
            $DB->update_record('report_builder_graph', $fromform);
        } else {
            $fromform->id = $DB->insert_record('report_builder_graph', $fromform);
        }
    }

    totara_set_notification(get_string('graph_updated', 'totara_reportbuilder'), $returnurl, array('class' => 'notifysuccess'));
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

$currenttab = 'graph';
include('tabs.php');

$mform->display();

echo $output->footer();
