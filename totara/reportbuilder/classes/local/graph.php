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
 * @package totara_reportbuilder
 */

namespace totara_reportbuilder\local;

/**
 * Class describing report graphs.
 */
class graph {
    /** @var \stdClass record from report_builder_graph table */
    protected $graphrecord;
    /** @var \reportbuilder the relevant reportbuilder instance */
    protected $report;
    /** @var array category and data series */
    protected $values;
    /** @var int count of records processed - count() in PHP may be very slow */
    protected $processedcount;
    /** @var int index of category, -1 means simple counter */
    protected $category;
    /** @var array indexes of series columns */
    protected $series;
    /** @var int legend column index when headings used as category */
    protected $legendcolumn;
    /** @var array SVGGraph settings */
    protected $svggraphsettings;
    /** @var string SVGGraph type */
    protected $svggraphtype;
    /** @var string SVGGraph colours */
    protected $svggraphcolours;

    public function __construct(\stdClass $graphrecord, \reportbuilder $report, $isexport) {
        if ($graphrecord->reportid != $report->_id) {
            throw new \coding_exception('$record parameter is not matching $report parameter');
        }
        $this->graphrecord = $graphrecord;
        $this->report = $report;

        $this->svggraphsettings = array(
            'preserve_aspect_ratio' => 'xMidYMid meet',
            'auto_fit_parent' => true,
            'axis_font' => 'sans-serif',
            'pad_right' => 20,
            'pad_left' => 20,
            'pad_bottom' => 20,
            'axis_stroke_width' => 1,
            'axis_font_size' => 12,
            'axis_text_space' => 6,
            'show_grid' => false,
            'division_size' => 6,
            'stroke_width' => 0,
            'back_colour' => '#fff',
            'back_stroke_width' => 0,
            'marker_size' => 3,
            'line_stroke_width' => 2,
            'repeated_keys' => 'accept', // Bad luck, we cannot prevent repeated values.
            'label_font_size' => 14,
    );

        $this->processedcount = 0;
        $this->values = array();
        $this->series = array();
        $this->svggraphsettings['legend_entries'] = array();

        $columns = array();
        $columnsmap = array();
        $i = 0;
        foreach ($this->report->columns as $colkey => $column) {
            if (!$column->display_column(true)) {
                continue;
            }
            $columns[$colkey] = $column;
            $columnsmap[$colkey] = $i++;
        }
        $rawseries = json_decode($this->graphrecord->series, true);
        $series = array();
        foreach ($rawseries as $colkey) {
            $series[$colkey] = $colkey;
        }

        if ($this->graphrecord->category === 'columnheadings') {
            $this->category = -2;

            $legendcolumn = $this->graphrecord->legend;
            if ($legendcolumn and isset($columns[$legendcolumn])) {
                $this->legendcolumn = $columnsmap[$legendcolumn];
            }

            foreach ($columns as $colkey => $column) {
                if (!isset($series[$colkey])) {
                    continue;
                }
                $i = $columnsmap[$colkey];
                $this->values[$i][-2] = $this->report->format_column_heading($this->report->columns[$colkey], true);
            }

        } else {
            if (isset($columns[$this->graphrecord->category])) {
                $this->category = $columnsmap[$this->graphrecord->category];
                unset($series[$this->graphrecord->category]);

            } else { // Category value 'none' or problem detected.
                $this->category = -1;
            }

            foreach ($series as $colkey) {
                if (!isset($columns[$colkey])) {
                    continue;
                }
                $i = $columnsmap[$colkey];
                $this->series[$i] = $colkey;
            }

            $legend = array();
            foreach ($this->series as $i => $colkey) {
                $legend[] = $this->report->format_column_heading($this->report->columns[$colkey], true);
            }
            $this->svggraphsettings['legend_entries'] = $legend;
        }
    }

    public function reset_records() {
        $this->processedcount = 0;

        if ($this->category == -2) {
            $this->series = array();
            $this->svggraphsettings['legend_entries'] = array();
            foreach ($this->values as $i => $unused) {
                $prev = $this->values[$i][-2];
                $this->values[$i] = array(-2 => $prev);
            }
        } else {
            $this->values = array();
        }
    }

    public function add_record($record) {
        $recorddata = $this->report->src->process_data_row($record, 'graph', $this->report);

        if ($this->category == -2) {
            $this->series[] = $this->processedcount;
            foreach ($recorddata as $k => $val) {
                if (isset($this->legendcolumn) and $k === $this->legendcolumn) {
                    $this->svggraphsettings['legend_entries'][] = (string)$val;
                    continue;
                }
                if (!isset($this->values[$k])) {
                    continue;
                }
                if ($val === '' or !is_numeric($val)) {
                    // There is no way to plot non-numeric data, sorry.
                    // TODO: add handling of '%' here
                    $val = null;
                }
                $this->values[$k][$this->processedcount] = $val;
            }
            $this->processedcount++;
            return;
        }

        $value = array();
        if ($this->category == -1) {
            $value[-1] = $this->processedcount + 1;
        } else {
            $value[$this->category] = $recorddata[$this->category];
        }

        foreach ($this->series as $i => $key) {
            $val = $recorddata[$i];
            if ($val === '' or !is_numeric($val)) {
                // There is no way to plot non-numeric data, sorry.
                // TODO: add handling of '%' here
                $val = null;
            }
            $value[$i] = $val;
        }

        $this->values[] = $value;
        $this->processedcount++;
    }

    public function count_records() {
        return $this->processedcount;
    }

    public function get_max_records() {
        return $this->graphrecord->maxrecords;
    }

    protected function init_svggraph() {
        global $CFG;
        require_once($CFG->dirroot.'/totara/core/lib/SVGGraph/SVGGraph.php');

        $this->svggraphtype = null;

        if ($this->count_records() == 0) {
            return;
        }

        if ($this->graphrecord->type === 'pie') {
            // Rework the structure because Pie graph may use only one series.
            $legend = array();
            foreach ($this->values as $value) {
                $legend[] = $value[$this->category];
            }
            $this->svggraphsettings['legend_entries'] = $legend;
            $this->svggraphsettings['show_labels'] = true;
            $this->svggraphsettings['show_label_key'] = false;
            $this->svggraphsettings['show_label_amount'] = false;
            $this->svggraphsettings['show_label_percent'] = true;
        }

        $this->svggraphsettings['structured_data'] = true;
        $this->svggraphsettings['structure'] = array('key' => $this->category, 'value' => array_keys($this->series));
        $seriescount = count($this->series);
        $singleseries = ($seriescount === 1);

        if ($this->category == -1) {
            // Row number as category - start with 1 instead of automatic 0.
            if ($this->graphrecord->type === 'bar') {
                $this->svggraphsettings['axis_min_v'] = 1;
            } else {
                $this->svggraphsettings['axis_min_h'] = 1;
            }
        }

        if ($this->graphrecord->type === 'bar') {
            if ($seriescount <= 2) {
                $this->svggraphsettings['bar_space'] = 40;
            } else if ($seriescount <= 4) {
                $this->svggraphsettings['bar_space'] = 20;
            } else {
                $this->svggraphsettings['bar_space'] = 10;
            }
            if ($singleseries) {
                $this->svggraphtype = 'HorizontalBarGraph';
            } else {
                $this->svggraphtype = $this->graphrecord->stacked ? 'HorizontalStackedBarGraph' : 'HorizontalGroupedBarGraph';
            }

        } else if ($this->graphrecord->type === 'line') {
            if ($singleseries) {
                $this->svggraphtype = 'MultiLineGraph';
            } else {
                $this->svggraphtype = $this->graphrecord->stacked ? 'StackedLineGraph' : 'MultiLineGraph';
            }

        } else if ($this->graphrecord->type === 'scatter') {
            if ($singleseries) {
                $this->svggraphtype = 'ScatterGraph';
            } else {
                $this->svggraphtype = 'MultiScatterGraph';
            }

        } else if ($this->graphrecord->type === 'area') {
            $this->svggraphsettings['fill_under'] = true;
            $this->svggraphsettings['marker_size'] = 2;

            if ($singleseries) {
                $this->svggraphtype = 'MultiLineGraph';
            } else {
                $this->svggraphtype = $this->graphrecord->stacked ? 'StackedLineGraph' : 'MultiLineGraph';
            }

        } else if ($this->graphrecord->type === 'pie') {
            $this->svggraphtype = 'PieGraph';

        } else { // Type 'column' or unknown.
            $this->graphrecord->type = 'column';
            if ($seriescount <= 2) {
                $this->svggraphsettings['bar_space'] = 80;
            } else if ($seriescount <= 5) {
                $this->svggraphsettings['bar_space'] = 50;
            } else if ($seriescount <= 10) {
                $this->svggraphsettings['bar_space'] = 20;
            } else {
                $this->svggraphsettings['bar_space'] = 10;
            }
            if ($singleseries) {
                $this->svggraphtype = 'BarGraph';
            } else {
                $this->svggraphtype = $this->graphrecord->stacked ? 'StackedBarGraph' : 'GroupedBarGraph';
            }
        }

        // Rotate data labels if necessary.
        if ($this->count_records() > 5 and $this->graphrecord->type !== 'pie') {
            if (get_string('thisdirectionvertical', 'core_langconfig') === 'btt') {
                $this->svggraphsettings['axis_text_angle_h'] = 90;
            } else {
                $this->svggraphsettings['axis_text_angle_h'] = -90;
            }
        }

        // Colors are copied from D3 that used http://colorbrewer2.org by Cynthia Brewer, Mark Harrower and The Pennsylvania State University
        if ($seriescount == 1 and $this->graphrecord->type !== 'pie') {
            $this->svggraphcolours = array('#2ca02c'); // Green is the best colour!

        } else if ($seriescount <= 10) {
            $this->svggraphcolours = array(
                '#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd',
                '#8c564b', '#e377c2', '#7f7f7f', '#bcbd22', '#17becf');

        } else {
            $this->svggraphcolours = array(
                '#1f77b4', '#aec7e8', '#ff7f0e', '#ffbb78', '#2ca02c',
                '#98df8a', '#d62728', '#ff9896', '#9467bd', '#c5b0d5',
                '#8c564b', '#c49c94', '#e377c2', '#f7b6d2', '#7f7f7f',
                '#c7c7c7', '#bcbd22', '#dbdb8d', '#17becf', '#9edae5');
        }
    }

    protected function get_final_settings() {
        $settings = $this->svggraphsettings;

        if (isset($this->graphrecord->settings)) {
            $usersettings = parse_ini_string($this->graphrecord->settings, false);
            foreach ($usersettings as $k => $v) {
                $settings[$k] = $v;
            }
        }

        return $settings;
    }

    public function fetch_svg() {
        $this->init_svggraph();
        if (!$this->svggraphtype) {
            // Nothing to do.
            return null;
        }
        $svggraph = new \SVGGraph(1000, 400, $this->get_final_settings());
        $svggraph->Colours($this->svggraphcolours);
        $svggraph->Values($this->values);
        $data = $svggraph->Fetch($this->svggraphtype, false, false);
        return $data;
    }

    public function fetch_block_svg() {
        $this->init_svggraph();
        if (!$this->svggraphtype) {
            // Nothing to do.
            return null;
        }

        // Hack the settings a bit, but keep the originals so that we can render more svgs.
        $settings = $this->get_final_settings();

        if ($this->graphrecord->type === 'column') {
            $settings['bar_space'] = $settings['bar_space'] / 3;
            if ($settings['bar_space'] < 10) {
                $settings['bar_space'] = 10;
            }
        }

        $svggraph = new \SVGGraph(400, 400, $settings);
        $svggraph->Colours($this->svggraphcolours);
        $svggraph->Values($this->values);
        $data = $svggraph->Fetch($this->svggraphtype, false, false);
        return $data;
    }

    public function fetch_pdf_svg($portrait) {
        $this->init_svggraph();
        if (!$this->svggraphtype) {
            // Nothing to do.
            return null;
        }
        if ($portrait) {
            $svggraph = new \SVGGraph(800, 400, $this->get_final_settings());
        } else {
            $svggraph = new \SVGGraph(1200, 400, $this->get_final_settings());
        }
        $svggraph->Colours($this->svggraphcolours);
        $svggraph->Values($this->values);
        return $svggraph->Fetch($this->svggraphtype, false, false);
    }
}
