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

class block_totara_report_graph extends block_base {
    protected $rawreport;

    public function init() {
        $this->title = get_string('pluginname', 'block_totara_report_graph');
    }

    protected function is_configured() {
        if (!isset($this->config->reportfor) or empty($this->config->reportorsavedid)) {
            // Nothing to do - not configured yet.
            return false;
        }

        return true;
    }

    public function specialization() {
        if (!empty($this->config->title)) {
            $this->title = format_string($this->config->title);

        } else {
            if ($this->is_configured()) {
                // Do not waste resources on fetching report name if user cannot see the graph.
                if (!isset($this->rawreport)) {
                    $this->rawreport = \block_totara_report_graph\util::get_report($this->config->reportorsavedid);
                }
                if ($this->rawreport) {
                    $this->title = format_string($this->rawreport->fullname);
                }
            }
        }
    }

    public function applicable_formats() {
        return array('all' => true);
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function has_config() {
        return false;
    }

    public function instance_allow_config() {
        return true;
    }


    public function get_content() {
        global $USER, $CFG, $SESSION;

        if ($this->content !== null) {
            // We have already been here.
            return $this->content;
        }

        if (!$this->is_configured()) {
            $this->content = '';
            return $this->content;
        }

        if (!isset($this->rawreport)) {
            $this->rawreport = \block_totara_report_graph\util::get_report($this->config->reportorsavedid);
        }

        if (!$this->rawreport) {
            // Somebody probably deleted the report or saved search.
            $this->content = '';
            return $this->content;
        }

        if (empty($this->rawreport->type)) {
            // No graph type configured, somebody must have disabled it after block set-up.

            if (!$this->page->user_is_editing()) {
                $this->content = '';
                return $this->content;
            }

            $this->content = new stdClass();
            $this->content->footer = '';

            $url = new moodle_url('/totara/reportbuilder/graph.php', array('reportid' => $this->rawreport->id));
            $this->content->text = '<div><a href="'.$url.'">'.get_string('errornograph', 'block_totara_report_graph').'</a></div>';

            return $this->content;
        }

        require_once($CFG->dirroot.'/totara/reportbuilder/lib.php');

        // This should never print error because we checked the report exists in specialisation() above but anyway...
        $this->content = new stdClass();
        $this->content->footer = '';

        if (reportbuilder::is_capable($this->rawreport->id, $USER->id)) {
            $url = new moodle_url('/totara/reportbuilder/report.php', array('id' => $this->rawreport->id));
            if (!empty($this->rawreport->savedid)) {
                $url->param('sid', $this->rawreport->savedid);
            }
            $this->content->footer = '<a href="'.$url.'">'.get_string('report', 'totara_reportbuilder').'</a>';
        }

        if (core_useragent::check_browser_version('MSIE', '6.0') and !core_useragent::check_browser_version('MSIE', '9.0')) {
            // See http://partners.adobe.com/public/developer/en/acrobat/PDFOpenParameters.pdf
            $svgurl = new moodle_url('/blocks/totara_report_graph/ajax_graph.php', array('blockid' => $this->instance->id, 'type' => 'pdf'));
            $svgurl = $svgurl . '#toolbar=0&navpanes=0&scrollbar=0&statusbar=0&viewrect=20,20,180,170';
            $nopdf = get_string('error:nopdf', 'totara_reportbuilder');
            $this->content->text = "<div class=\"rb-block-pdfgraph\"><object type=\"application/pdf\" data=\"$svgurl\" width=\"100%\" height=\"250\">$nopdf</object>";

        } else {
            // NOTE: unfortunately the inline SVGs are not self-contained and there are problems,
            //       when multiple graphs present on on page - this is the reasons for object embedding.
            $svgurl = new moodle_url('/blocks/totara_report_graph/ajax_graph.php', array('blockid' => $this->instance->id, 'type' => 'svg'));
            $nosvg = get_string('error:nosvg', 'totara_reportbuilder');
            $this->content->text = "<div class=\"rb-block-svggraph\"><object type=\"image/svg+xml\" data=\"$svgurl\" width=\"100%\" height=\"100%\">$nosvg</object>";
        }

        return $this->content;
    }
}
