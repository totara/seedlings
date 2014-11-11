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

namespace block_totara_report_graph;

/**
 * Class util for report graph block.
 *
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package block_totara_report_graph
 */
class util {

    /**
     * Get the svg
     * @param \reportbuilder $report
     * @return null
     */
    protected static function get_svg(\reportbuilder $report) {
        global $DB;

        $graphrecord = $DB->get_record('report_builder_graph', array('reportid' => $report->_id));
        if (empty($graphrecord->type)) {
            return null;
        }

        $graph = new \totara_reportbuilder\local\graph($graphrecord, $report, false);
        list($sql, $params, $cache) = $report->build_query(false, true);

        $order = '';
        if ($report->defaultsortcolumn) {
            $order = "$report->defaultsortcolumn $report->defaultsortorder";
        }
        if ($records = $DB->get_recordset_sql($sql.$order, $params, 0, $graph->get_max_records())) {
            foreach ($records as $record) {
                $graph->add_record($record);
            }
        }

        return $graph->fetch_block_svg();
    }

    /**
     * Cache key helper.
     *
     * @param int $reportorsavedid
     * @param int $reportfor
     * @return string key
     */
    public static function get_cache_key($reportorsavedid, $reportfor) {
        global $USER;

        if (empty($reportfor)) {
            // Value 0 means current user.
            $reportfor = $USER->id;
        }

        return 'r' . str_replace('-', '_', $reportorsavedid) . 'f' . $reportfor;
    }

    /**
     * Get raw report record from database.
     * @param int $reportorsavedid
     * @return \stdClass
     */
    public static function get_report($reportorsavedid) {
        global $DB;

        // Fetch report even if type not set - users may fiddle with the setting in reportbuilder.

        if ($reportorsavedid > 0) {
            $sql = "SELECT r.id, r.fullname, r.timemodified AS rtimemodified, g.type,
                           NULL AS savedid, NULL AS userid, 0 AS gtimemodified
                     FROM {report_builder} r
                     JOIN {report_builder_graph} g ON g.reportid = r.id
                    WHERE r.id = :reportid";
            $report = $DB->get_record_sql($sql, array('reportid' => $reportorsavedid), IGNORE_MISSING);

        } else if ($reportorsavedid < 0) {
            $sql = "SELECT r.id, s.name AS fullname, r.timemodified AS rtimemodified, g.type,
                           s.id AS savedid, s.userid, g.timemodified AS gtimemodified
                      FROM {report_builder} r
                      JOIN {report_builder_graph} g ON g.reportid = r.id
                      JOIN {report_builder_saved} s ON s.reportid = r.id
                     WHERE s.id = :savedid AND s.ispublic <> 0";
            $report = $DB->get_record_sql($sql, array('savedid' => - $reportorsavedid), IGNORE_MISSING);

        } else {
            $report = false;
        }

        return $report;
    }

    /**
     * Get svg markup data.
     *
     * NOTE: Session must be already closed!
     *
     * @param int $blockid
     * @param \stdClass $config
     * @return string svg data, dies on error
     */
    public static function get_svg_data($blockid, $config) {
        global $SESSION;

        if (!isset($config->reportfor) or empty($config->reportorsavedid)) {
            error_log($blockid . ': not configured');
            die;
        }

        $rawreport = self::get_report($config->reportorsavedid);

        if (empty($rawreport->type)) {
            error_log($blockid . ': no graph type');
            die;
        }

        $cache = \cache::make('block_totara_report_graph', 'graph');
        $key = self::get_cache_key($config->reportorsavedid, $config->reportfor);

        if ($cacheddata = $cache->get($key)) {
            if (empty($cacheddata->svgdata)) {
                // No cache yet.
            } else if ($cacheddata->rtimemodified != $rawreport->rtimemodified or $cacheddata->gtimemodified != $rawreport->gtimemodified) {
                // The report or graph was changed.
            } else if ($cacheddata->timecreated < time() - $config->cachettl) {
                // The cache is too old.
            } else {
                // Yay - we can use the cached data!
                return $cacheddata->svgdata;
            }
        }

        try {
            unset($SESSION->reportbuilder[$rawreport->id]); // Not persistent - we closed session already.
            $reportfor = $config->reportfor ? $config->reportfor : null;
            $report = new \reportbuilder($rawreport->id, null, false, $rawreport->savedid, $reportfor);
            $svgdata = self::get_svg($report);

            if (!$svgdata) {
                error_log($blockid . ': no graph data');
                die;
            }

            // If we go this far than make sure we save the result to the cache no matter what the user does.
            ignore_user_abort(true);
            $cacheddata = new \stdClass();
            $cacheddata->svgdata = $svgdata;
            $cacheddata->timecreated = time();
            $cacheddata->rtimemodified = $rawreport->rtimemodified;
            $cacheddata->gtimemodified = $rawreport->gtimemodified;
            $cache->set($key, $cacheddata);
            if (connection_aborted()) {
                die;
            }
            ignore_user_abort(false);

            // Finally return the SVG data.
            return $svgdata;

        } catch (\Exception $e) {
            error_log($blockid . ': report error: ' . $e->getMessage());
            die;
        }
    }

    /**
     * Send SVG file and die.
     * @param string $svgdata
     * @void does not return
     */
    public static function send_svg($svgdata) {
        send_headers('image/svg+xml', false);
        echo '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n";
        echo '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . "\n";
        echo $svgdata;
        die;
    }

    /**
     * Send PDF data and die.
     * @param string $svgdata
     * @void does not return
     */
    public static function send_pdf($svgdata) {
        global $CFG;

        // TODO: we could add some pdf caching here
        require_once $CFG->libdir . '/pdflib.php';
        $pdf = new \PDF('L', 'mm', 'B7', true, 'UTF-8');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->ImageSVG('@'.$svgdata, 10, 10, 400, 400);
        $pdfdata = $pdf->Output('graph.pdf', 'S');

        send_headers('application/pdf', false);
        echo $pdfdata;
        die;
    }
}
