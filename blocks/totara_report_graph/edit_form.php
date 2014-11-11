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
 * @package block_totara_report_graph
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/edit_form.php');

class block_totara_report_graph_edit_form extends block_edit_form {
    /** @var array reports I can access */
    protected $myreports = array();
    /** @var array my own saves from reports I can access */
    protected $mysaves = array();
    /** @var array users I am always allowed to set */
    protected $myusers = array();

    /**
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $USER, $DB, $CFG;
        require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/totara_report_graph/block_totara_report_graph.php');

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('title', 'block_totara_report_graph'));
        $mform->setType('config_title', PARAM_TEXT);

        $prevreport = false;
        if (!empty($this->block->config->reportorsavedid)) {
            $prevreport = \block_totara_report_graph\util::get_report($this->block->config->reportorsavedid);
        }

        // Report selection.

        $reportoptions = array(0 => get_string('choosedots', 'core'));
        if ($prevreport and !$prevreport->savedid) {
            // Always show current option even if not allowed to see the report or graph not present.
            $reportoptions[$prevreport->id] = format_string($prevreport->fullname);
        }
        $sql = "SELECT r.id, r.fullname
                  FROM {report_builder} r
                  JOIN {report_builder_graph} g ON g.reportid = r.id
                 WHERE g.type <> ''";
        $reports = $DB->get_records_sql($sql);
        foreach ($reports as $report) {
            if (!reportbuilder::is_capable($report->id, $USER->id)) {
                continue;
            }
            $reportoptions[$report->id] = format_string($report->fullname);
            $this->myreports[$report->id] = $report->id;
        }

        // Saved report selection.

        $savedoptions = array();
        if ($prevreport and $prevreport->savedid) {
            // Always show current option even if not allowed to see the report or graph not present.
            $title = format_string($prevreport->fullname);
            if ($prevreport->userid != $USER->id) {
                $user = $DB->get_record('user', array('id' => $prevreport->userid));
                $title = fullname($user) . ' (' . $title . ')';
            }
            $savedoptions[-$prevreport->savedid] = $title;
        }
        $sql = "SELECT s.id, s.name, s.reportid
                  FROM {report_builder_saved} s
                  JOIN {report_builder} r ON r.id = s.reportid
                  JOIN {report_builder_graph} g ON g.reportid = r.id
                 WHERE s.ispublic <> 0 AND s.userid = :userid AND  g.type <> ''";
        $params = array('userid' => $USER->id);
        $saves = $DB->get_records_sql($sql, $params);
        foreach ($saves as $saved) {
            if (empty($this->myreports[$saved->reportid])) {
                continue;
            }
            $savedoptions[-$saved->id] = format_string($saved->name);
            $this->mysaves[$saved->id] = $saved->id;
        }

        $options = array();
        $options[get_string('reports', 'totara_reportbuilder')] = $reportoptions;
        if ($savedoptions) {
            $options[get_string('savedsearches', 'totara_reportbuilder')] = $savedoptions;
        }

        $mform->addElement('selectgroups', 'config_reportorsavedid', get_string('report', 'totara_reportbuilder'), $options);

        // View report data as user selection.

        $guest = guest_user();
        $options = array(
            $USER->id => get_string('reportforme', 'block_totara_report_graph', fullname($USER)),
            0 => get_string('reportforcurrent', 'block_totara_report_graph'),
            $guest->id => get_string('reportforguest', 'block_totara_report_graph'),
        );
        $this->myusers = array_combine(array_keys($options), array_keys($options));

        if ($prevreport and isset($this->block->config->reportfor)) {
            // Add previous user if valid account - this must be changed if report id or saved id is changed.
            if (!isset($options[$this->block->config->reportfor])) {
                if ($user = $DB->get_record('user', array('id' => $this->block->config->reportfor, 'deleted' => 0))) {
                    $options[$user->id] = get_string('reportforother', 'block_totara_report_graph', fullname($user));
                }
            }
        }

        $mform->addElement('select', 'config_reportfor', get_string('reportfor', 'block_totara_report_graph'), $options);
        $mform->addHelpButton('config_reportfor', 'reportfor', 'block_totara_report_graph');
        $mform->setDefault('config_reportfor', $USER->id);

        // Cache lifetime.

        $options = array(
            60*1 => '1 ' . get_string('minute'),
            60*10 => '10 ' . get_string('minutes'),
            60*30 => '30 ' . get_string('minutes'),
            60*60*1 => '1 ' . get_string('hour'),
            60*60*3 => '3 ' . get_string('hours'),
            60*60*6 => '6 ' . get_string('hours'),
            60*60*12 => '12 ' . get_string('hours'),
            60*60*24 => '1 ' . get_string('day'),
        );
        $mform->addElement('select', 'config_cachettl', get_string('cachettl', 'block_totara_report_graph'), $options);
        $mform->setDefault('config_cachettl', 60*60*1);
    }

    function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);

        $prevreport = false;
        if (!empty($this->block->config->reportorsavedid)) {
            $prevreport = \block_totara_report_graph\util::get_report($this->block->config->reportorsavedid);
        }
        if (isset($this->block->config->reportfor)) {
            $prevreportfor = $this->block->config->reportfor;
        } else {
            $prevreportfor = -1; // Intentionally invalid value - this is used for new value detection.
        }
        $reportfor = $data['config_reportfor'];

        // Purge caches for this block before and after the change.

        $cache = cache::make('block_totara_report_graph', 'graph');
        if (!empty($this->block->config->reportorsavedid) and isset($this->block->config->reportfor)) {
            $key = \block_totara_report_graph\util::get_cache_key($this->block->config->reportorsavedid, $this->block->config->reportfor);
            $cache->delete($key);
        }
        if (!empty($data['config_reportorsavedid'])) {
            $key = \block_totara_report_graph\util::get_cache_key($data['config_reportorsavedid'], $data['config_reportfor']);
            $cache->delete($key);
        }

        // Validate the data.

        if ($data['config_reportorsavedid'] > 0) {
            $reportid = $data['config_reportorsavedid'];
            if (isset($this->myreports[$reportid])) {
                // I can use this report.
                if (!$prevreport or $prevreport->id != $reportid) {
                    if (!isset($this->myusers[$reportfor])) {
                        // After any report change user cannot keep the original report for user.
                        $errors['config_reportorsavedid'] = get_string('errorconfigreportfor', 'block_totara_report_graph');
                        return $errors;
                    }
                }
            } else {
                // I cannot use this report - this must be a previously selected report by other user or report access change.
                if (!$prevreport or $prevreport->id != $reportid) {
                    $errors['config_reportorsavedid'] = get_string('errorconfigreport', 'block_totara_report_graph');
                    return $errors;
                } else {
                    // User did not change report selected by other user - that means they cannot change the user.
                    if ($prevreportfor != $reportfor) {
                        // After any report change user cannot keep the original visibility for one other user.
                        $errors['config_reportfor'] = get_string('errorconfigreportfor', 'block_totara_report_graph');
                        return $errors;
                    }
                }
            }
            if ($reportfor == $USER->id) {
                if (!reportbuilder::is_capable($reportid, $USER->id)) {
                    // I cannot access this report any more, weird.
                    $errors['config_reportorsavedid'] = get_string('errorconfigreportfor', 'block_totara_report_graph');
                    return $errors;
                }
            }

        } else if ($data['config_reportorsavedid'] < 0) {
            $savedid = -$data['config_reportorsavedid'];
            $newreport = \block_totara_report_graph\util::get_report(-$savedid);
            $reportid = $newreport->id;
            if (isset($this->mysaves[$savedid])) {
                // I can use this save.
                if (!$prevreport or $prevreport->savedid != $savedid) {
                    if (!isset($this->myusers[$reportfor])) {
                        // After any saved report change user cannot keep the original report for user.
                        $errors['config_reportorsavedid'] = get_string('errorconfigreportfor', 'block_totara_report_graph');
                        return $errors;
                    }
                }
            } else {
                // I cannot use this saved report - this must be a previously selected saved report by other user or report access change.
                if (!$prevreport or $prevreport->savedid != $savedid) {
                    $errors['config_reportorsavedid'] = get_string('errorconfigreport', 'block_totara_report_graph');
                    return $errors;
                } else {
                    // User did not change report selected by other user - that means they cannot change the user.
                    if ($prevreportfor != $reportfor) {
                        // After any report change user cannot keep the original visibility for one other user.
                        $errors['config_reportfor'] = get_string('errorconfigreportfor', 'block_totara_report_graph');
                        return $errors;
                    }
                }
            }

        } else {
            // This is fine, no graph will be displayed,
            // this means we do not care about the visibility setting now.
            $reportid = 0;
        }

        if ($reportid and $reportfor == $USER->id) {
            if (!reportbuilder::is_capable($reportid, $USER->id)) {
                // I cannot access this report any more, weird.
                $errors['config_reportorsavedid'] = get_string('errorconfigreportfor', 'block_totara_report_graph');
                return $errors;
            }
        }

        return $errors;
    }
}
