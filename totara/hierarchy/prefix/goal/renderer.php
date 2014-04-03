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
 * @subpackage totara_hierarchy
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once('lib.php');

/**
 * Output renderer for totara_appraisals module
 */
class hierarchy_goal_renderer extends plugin_renderer_base {

    /**
     * Renders a table that allow selection of a frameworks and link to the goals summary report.
     *
     * @param array $goalframeworks array of goal framework objects
     * @return string HTML table
     */
    public function report_frameworks($goalframeworks = array()) {
        if (empty($goalframeworks)) {
            return get_string('goalnoframeworks', 'totara_hierarchy');
        }

        $tableheader = array(get_string('goalframework', 'totara_hierarchy'),
                             get_string('goalcount', 'totara_hierarchy'),
                             get_string('goalreports', 'totara_hierarchy'));

        $goalframeworkstable = new html_table();
        $goalframeworkstable->summary = '';
        $goalframeworkstable->head = $tableheader;
        $goalframeworkstable->data = array();
        $goalframeworkstable->attributes = array('class' => 'generaltable');

        $totalgoals = 0;
        foreach ($goalframeworks as $goalframework) {
            $row = array();

            $frameworkurl = new moodle_url('/totara/hierarchy/index.php',
                    array('prefix' => 'goal', 'frameworkid' => $goalframework->id));
            $summaryreporturl = new moodle_url('/totara/hierarchy/prefix/goal/summaryreport.php',
                    array('goalframeworkid' => $goalframework->id, 'clearfilters' => 1));

            $row[] = html_writer::link($frameworkurl, format_string($goalframework->fullname));

            $goals = goal::get_framework_items($goalframework->id);
            $row[] = count($goals);
            $totalgoals += count($goals);

            $row[] = html_writer::link($summaryreporturl, get_string('goalsummaryreport', 'totara_hierarchy'));

            $goalframeworkstable->data[] = $row;
        }

        // Totals row.
        $row = array();

        $frameworksurl = new moodle_url('/totara/hierarchy/framework/index.php', array('prefix' => 'goal'));
        $statusreporturl = new moodle_url('/totara/hierarchy/prefix/goal/statusreport.php', array('clearfilters' => 1));

        $row[] = html_writer::link($frameworksurl, get_string('goalallframeworks', 'totara_hierarchy'));

        $row[] = $totalgoals;

        $row[] = html_writer::link($statusreporturl, get_string('goalstatusreport', 'totara_hierarchy'));

        $goalframeworkstable->data[] = $row;

        return html_writer::table($goalframeworkstable);
    }


    /**
     * Renders a table containing goal frameworks for the summary report
     *
     * @param int $summaryreportid id of the report
     * @param array $frameworks array of goal frameworks
     * @return string HTML table
     */
    public function summary_report_table($summaryreportid, $frameworks = array()) {
        if (empty($frameworks)) {
            return get_string('goalnoframeworks', 'totara_hierarchy');
        }

        $tableheader = array(get_string('goalframework', 'totara_hierarchy'),
                             get_string('goalcount', 'totara_hierarchy'));

        $table = new html_table();
        $table->summary = '';
        $table->head = $tableheader;
        $table->data = array();
        $table->attributes = array('class' => 'generaltable');

        $data = array();
        foreach ($frameworks as $framework) {
            $row = array();

            $summaryreporturl = new moodle_url('/totara/reportbuilder/report.php',
                    array('id' => $summaryreportid, 'goalframeworkid' => $framework->id, 'clearfilters' => 1));

            $row[] = html_writer::link($summaryreporturl, format_string($framework->fullname));

            $goals = goal::get_framework_items($framework->id);
            $row[] = count($goals);

            $data[] = $row;
        }
        $table->data = $data;

        return html_writer::table($table);
    }


}
