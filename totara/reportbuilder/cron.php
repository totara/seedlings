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

require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/groupslib.php');
require_once($CFG->dirroot . '/totara/core/lib/scheduler.php');

/**
 * Run the cron functions required by report builder
 *
 * @param integer $grp ID of a group to run on. Runs on all groups if not set
 *
 * @return boolean True if completes successfully, false otherwise
 */
function reportbuilder_cron($grp=null) {
    global $DB;
    // if no ID provided, run on all groups
    if (!$grp) {
        $groups = $DB->get_records('report_builder_group', null, 'id');
    } else {
        // otherwise run on the group provided
        $data = $DB->get_record('report_builder_group', array('id' => $grp));
        if ($data) {
            $groups = array($data);
        } else {
            $groups = array();
        }
    }

    foreach ($groups as $group) {

        $preproc = $group->preproc;
        $groupid = $group->id;

        // create instance of preprocessor
        if (!$pp = reportbuilder::get_preproc_object($preproc, $groupid)) {
            mtrace('Warning: preprocessor "' . $preproc . '" not found.');
            continue;
        }

        // check for items where tags have been added or removed
        update_tag_grouping($groupid);

        // get list of items and when they were last processed
        $trackinfo = $pp->get_track_info();

        // get a list of items that need processing
        $items = $pp->get_group_items();

        mtrace("Running '$preproc' pre-processor on group '{$group->name}' (" .
              count($items) . ' items).');

        foreach ($items as $item) {

            // get track info about this item if it exists
            if (array_key_exists($item, $trackinfo)) {
                $lastchecked = $trackinfo[$item]->lastchecked;
                $disabled = $trackinfo[$item]->disabled;
            } else {
                $lastchecked = null;
                $disabled = 0;
            }

            // skip processing if item is disabled
            if ($disabled) {
                mtrace('Skipping disabled item '.$item);
                continue;
            }

            $message = '';
            // try processing the item, if it goes wrong disable
            // it to prevent future attempts to process it
            if (!$pp->run($item, $lastchecked, $message)) {
                $pp->disable_item($item);
                mtrace($message);
            }
        }

    }

    process_reports_cache();
    process_scheduled_reports();

    return true;
}

/**
 * Get an array of all the sources used by reports on this site
 *
 * @return Array Array of sources that have active reports
 */
function rb_get_active_sources() {
    global $DB;
    $sql = 'SELECT DISTINCT source FROM {report_builder}';
    return $DB->get_fieldset_sql($sql, null);
}

/**
 * Generate cached reports
 *
 */
function process_reports_cache() {
    global $CFG, $DB;

    if (isset($CFG->enablereportcaching) && $CFG->enablereportcaching == 0) {
        reportbuilder_purge_all_cache(true);
    }
    $caches = reportbuilder_get_all_cached();
    foreach ($caches as $cache) {
        // for disabled cache just ensure to remove cache table
        if (!$cache->cache) {
            if ($cache->reportid) {
                mtrace('Disable caching for report: ' . $cache->fullname);
                reportbuilder_purge_cache($cache, true);
            }
            continue;
        }

        $schedule = new scheduler($cache, array('nextevent' => 'nextreport'));
        if ($schedule->is_time()) {
            $schedule->next();

            mtrace("Caching report '$cache->fullname'...");
            $track_start = microtime(true);

            $result = reportbuilder_generate_cache($cache->reportid);

            if ($result) {
                $t = sprintf ("%.2f", (microtime(true) - $track_start));
                mtrace("report '$cache->fullname' done in $t seconds");
            } else {
                mtrace("report '$cache->fullname' failed");
            }
        }

        if ($schedule->is_changed()) {
            if (!$cache->id) {
                $DB->insert_record('report_builder_cache', $schedule->to_object());
            } else {
                $DB->update_record('report_builder_cache', $schedule->to_object());
            }
        }
    }
}

/**
 * Process Scheduled reports
 *
 */
function process_scheduled_reports() {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/calendar/lib.php');

    $sql = "SELECT rbs.*, rb.fullname, u.timezone
            FROM {report_builder_schedule} rbs
            JOIN {report_builder} rb
            ON rbs.reportid = rb.id
            JOIN {user} u
            ON rbs.userid = u.id";

    $scheduledreports = $DB->get_records_sql($sql);

    mtrace('Processing ' . count($scheduledreports) . ' scheduled reports');

    foreach ($scheduledreports as $report) {
        $reportname = $report->fullname;

        // set the next report time if its not yet set
        $schedule = new scheduler($report, array('nextevent' => 'nextreport'));

        if ($schedule->is_time()) {
            $schedule->next();

            // If exporting to file is turned off at system level, do not save reports.
            $exportsetting = get_config('reportbuilder', 'exporttofilesystem');
            $exporttofilesystem = $origexportsetting = $report->exporttofilesystem;

            switch ($exporttofilesystem) {
                case REPORT_BUILDER_EXPORT_EMAIL_AND_SAVE:
                    if ($exportsetting == 0) {
                        // Export turned off, email only.
                        $report->exporttofilesystem = REPORT_BUILDER_EXPORT_EMAIL;
                        mtrace('ReportID:(' . $report->id . ') Option: Email and save but save disabled so email only');
                    } else {
                        mtrace('ReportID:(' . $report->id . ') Option: Email and save scheduled report to file.');
                    }
                    break;
                case REPORT_BUILDER_EXPORT_SAVE:
                    if ($exportsetting == 0) {
                        // Export turned off, ignore.
                        mtrace('ReportID:(' . $report->id . ') Option: Save scheduled report but export disabled, skipping');
                        continue 2;
                    } else{
                        mtrace('ReportID:(' . $report->id . ') Option: Save scheduled report to file system only.');
                    }
                    break;
                default:
                    mtrace('ReportID:(' . $report->id . ') Option: Email scheduled report.');
            }

            //Send email
            if (reportbuilder_send_scheduled_report($report)) {
                mtrace('Sent email for report ' . $report->id);
            } else if ($exporttofilesystem == REPORT_BUILDER_EXPORT_SAVE) {
                mtrace('No scheduled report email has been send');
            } else {
                mtrace('Failed to send email for report ' . $report->id);
            }

            // TODO: this should be probably replaced by events triggered in reportbuilder_send_scheduled_report()
            add_to_log(SITEID, 'reportbuilder', 'dailyreport', null, "$reportname (ID $report->id)");

            // Restore original export setting if we have changed it because file export is disabled.
            if ($report->exporttofilesystem != $origexportsetting) {
                $report->exporttofilesystem = $origexportsetting;
            }
            if (!$DB->update_record('report_builder_schedule', $report)) {
                mtrace('Failed to update next report field for scheduled report id:' . $report->id);
            }
        } else if ($schedule->is_changed()) {
            $DB->update_record('report_builder_schedule', $schedule->to_object());
        }
    }
}

