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
 *
 * @package totara_core
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>

 */
namespace totara_core\event;
defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for completion.
 */
class module_completion extends \core\event\base {
    /**
     * Triggered when 'completion_criteria_calc' event is triggered.
     *
     * @param \core\event\criteria_completion $event
     */
    /**
     * Initialise required event data properties.
     */
    protected function init() {
        $this->context = \context_system::instance();
        $this->data['objecttable'] = 'course_modules_completion';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventmodulecompletion', 'totara_core');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return 'User with id ' . $this->other['userid'] . ' completed activity in course ' . $this->other['course'];
    }

    /**
     * Return name of the legacy event, which is replaced by this event.
     *
     * @return string legacy event name
     */
    public static function get_legacy_eventname() {
        return 'completion_criteria_calc';
    }

    /**
     * Return completion_criteria_calc legacy event data.
     *
     * @return \stdClass user data.
     */
    protected function get_legacy_eventdata() {
        $user = $this->get_record_snapshot('course_modules_completion', $this->data['objectid']);
        return $user;
    }

    /**
     * Returns array of parameters to be passed to legacy add_to_log() function.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array(SITEID, 'user', 'activitycompletion', "course/view.php?id=".$this->data['other']['course'], $this->data['other']['userid']);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        global $CFG;
        if ($CFG->debugdeveloper) {
            parent::validate_data();
            if (!isset($this->data['other']['moduleinstance'])) {
                throw new \coding_exception('moduleinstance must be set in $event.');
            }
            if (!isset($this->data['other']['criteriatype'])) {
                throw new \coding_exception('criteriatype must be set in $event.');
            }
            if (!isset($this->data['other']['userid'])) {
                throw new \coding_exception('userid must be set in $event.');
            }
            if (!isset($this->data['other']['course'])) {
                throw new \coding_exception('course must be set in $event.');
            }
        }
    }
}
