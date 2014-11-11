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
 * @author Andrew Hancox <andrewdchancox@googlemail.com> on behalf of Synergy Learning
 * @package modules
 * @subpackage facetoface
 */

namespace mod_facetoface\event;
defined('MOODLE_INTERNAL') || die();

class signupstatus_updated extends \core\event\base {
    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'facetoface_signups_status';
    }

    /**
     * Return the legacy event name.
     *
     * @return string
     */
    public static function get_legacy_eventname() {
        return 'mod_facetoface_statusupdated';
    }

    /**
     * Legacy event data if get_legacy_eventname() is not empty.
     *
     * Note: do not use directly!
     *
     * @return mixed
     */
    protected function get_legacy_eventdata() {
        $data = $this->get_data();
        $snapshot = $this->get_record_snapshot('facetoface_signups_status', $data['objectid']);
        return $snapshot;
    }
}


