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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package tool
 * @subpackage totara_sync
 */

class rb_totarasynclog_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;

    public function __construct($data) {
        $this->url = '/admin/tool/totara_sync/admin/synclog.php';
        $this->source = 'totara_sync_log';
        $this->shortname = 'totarasynclog';
        $this->fullname = get_string('synclog', 'tool_totara_sync');
        $this->columns = array(
            array(
                'type' => 'totara_sync_log',
                'value' => 'id',
                'heading' => get_string('id', 'tool_totara_sync'),
            ),
            array(
                'type' => 'totara_sync_log',
                'value' => 'runid',
                'heading' => get_string('runid', 'tool_totara_sync'),
            ),
            array(
                'type' => 'totara_sync_log',
                'value' => 'time',
                'heading' => get_string('datetime', 'tool_totara_sync'),
            ),
            array(
                'type' => 'totara_sync_log',
                'value' => 'element',
                'heading' => get_string('element', 'tool_totara_sync'),
            ),
            array(
                'type' => 'totara_sync_log',
                'value' => 'logtype',
                'heading' => get_string('logtype', 'tool_totara_sync'),
            ),
            array(
                'type' => 'totara_sync_log',
                'value' => 'action',
                'heading' => get_string('action', 'tool_totara_sync'),
            ),
            array(
                'type' => 'totara_sync_log',
                'value' => 'info',
                'heading' => get_string('info', 'tool_totara_sync'),
            ),
        );

        $this->filters = array(
            array(
                'type' => 'totara_sync_log',
                'value' => 'runid',
                'advanced' => 0,
            ),
            array(
                'type' => 'totara_sync_log',
                'value' => 'time',
                'advanced' => 0,
            ),
            array(
                'type' => 'totara_sync_log',
                'value' => 'element',
                'advanced' => 0,
            ),
            array(
                'type' => 'totara_sync_log',
                'value' => 'logtype',
                'advanced' => 0,
            ),
            array(
                'type' => 'totara_sync_log',
                'value' => 'action',
                'advanced' => 0,
            ),
            array(
                'type' => 'totara_sync_log',
                'value' => 'info',
                'advanced' => 0,
            ),
        );

        // no restrictions
        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_NONE;

        parent::__construct();
    }

    /**
     * Check if the user is capable of accessing this report.
     * We use $reportfor instead of $USER->id and $report->get_param_value() instead of getting params
     * some other way so that the embedded report will be compatible with the scheduler (in the future).
     *
     * @param int $reportfor userid of the user that this report is being generated for
     * @param reportbuilder $report the report object - can use get_param_value to get params
     * @return boolean true if the user can access this report
     */
    public function is_capable($reportfor, $report) {
        $context = context_system::instance();
        return has_capability('tool/totara_sync:manage', $context, $reportfor);
    }
}
