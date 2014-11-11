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

class rb_tasks_embedded extends rb_base_embedded {

    public $url, $source, $fullname, $filters, $columns;
    public $contentmode, $contentsettings, $embeddedparams;
    public $hidden, $accessmode, $accesssettings, $shortname;

    public function __construct($data) {
        $userid = array_key_exists('userid', $data) ? $data['userid'] : null;

        $this->url = '/totara/message/tasks.php';
        $this->source = 'totaramessages';
        $this->shortname = 'tasks';
        $this->fullname = get_string('tasks', 'totara_message');
        $this->columns = array(
            array(
                'type' => 'message_values',
                'value' => 'msgtype',
                'heading' => get_string('type', 'rb_source_totaramessages'),
            ),
            array(
                'type' => 'user',
                'value' => 'namelink',
                'heading' => get_string('name', 'rb_source_totaramessages'),
            ),
            array(
                'type' => 'message_values',
                'value' => 'statement',
                'heading' => get_string('details', 'rb_source_totaramessages'),
            ),
                array(
                'type' => 'message_values',
                'value' => 'sent',
                'heading' => get_string('sent', 'rb_source_totaramessages'),
            ),
        );

        $this->filters = array(
            array(
                    'type' => 'user',
                    'value' => 'fullname',
                    'advanced' => 1,
                ),
            array(
                    'type' => 'message_values',
                    'value' => 'category',
                    'advanced' => 0,
                ),
            array(
                    'type' => 'message_values',
                    'value' => 'statement',
                    'advanced' => 1,
                ),
            array(
                    'type' => 'message_values',
                    'value' => 'sent',
                    'advanced' => 1,
                ),
        );

        // no restrictions
        $this->contentmode = REPORT_BUILDER_CONTENT_MODE_NONE;

        // only show tasks, not notifications
        $this->embeddedparams = array(
            'name' => 'totara_task'
        );
        // also limited to single user
        if (isset($userid)) {
            $this->embeddedparams['userid'] = $userid;
        }
        // also limited by role
        if (isset($roleid)) {
            $this->embeddedparams['roleid'] = $roleid;
        }

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
        global $USER;
        // If no user param passed, assume current user only.
        if (!($subjectid = $report->get_param_value('userid'))) {
            $subjectid = $USER->id;
        }
        // Users can only view their own and their staff's pages or if they are an admin.
        return ($reportfor == $subjectid ||
                totara_is_manager($subjectid, $reportfor) ||
                has_capability('totara/message:viewallmessages', context_system::instance(), $reportfor));
    }
}
