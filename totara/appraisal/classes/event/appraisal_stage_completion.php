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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage totara_appraisal
 */


namespace totara_appraisal\event;
defined('MOODLE_INTERNAL') || die();

class appraisal_stage_completion extends \core\event\base {

    /**
     * Initialise the event data.
     */
    protected function init() {
        $this->data['objecttable'] = 'appraisal';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventstagecompleted', 'totara_appraisal');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The appraisal stage {$this->other['stageid']} in appraisal {$this->objectid} was completed by user {$this->userid}";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/totara/appraisal/myappraisal.php', array('appraisalid' => $this->objectid, 'subjectid' => $this->userid));
    }

    /**
     * Returns the name of the legacy event.
     *
     * @return string legacy event name
     */
    public static function get_legacy_eventname() {
        return 'appraisal_stage_completed';
    }

    /**
     * Returns the legacy event data.
     *
     * @return \stdClass the course that was created
     */
    protected function get_legacy_eventdata() {
        return $this->get_record_snapshot('appraisal_stage', $this->other['stageid']);
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array(SITEID, 'appraisal', 'stage_completed', 'myappraisal.php?appraisalid=' . $this->objectid, 'ID: ' . $this->objectid);
    }

    protected function validate_data() {
        global $CFG;

        if ($CFG->debugdeveloper) {
            parent::validate_data();

            if (!isset($this->userid)) {
                throw new \coding_exception('userid must be set.');
            }

            if (!isset($this->other['stageid'])) {
                throw new \coding_exception('stageid must be set in $other.');
            }

            if (!isset($this->other['time'])) {
                throw new \coding_exception('time must be set in $other.');
            }
        }
    }
}
