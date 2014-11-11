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

namespace totara_reportbuilder\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The report updated event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string shortname: Short name of report.
 *      - string $area: part of reportbuilder affected
 * }
 *
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package totara_reportbuilder
 */
class report_updated extends \core\event\base {
    /**
     * Flag for prevention of direct create() call.
     * @var bool
     */
    protected static $preventcreatecall = true;

    /** @var \reportbuilder */
    protected $report;

    /**
     * Create instance of event.
     *
     * @param \reportbuilder $report
     * @param string $area what area of report was updated
     * @return report_updated
     */
    public static function create_from_report(\reportbuilder $report, $area) {
        $data = array(
            'context' => \context_system::instance(),
            'objectid' => $report->_id,
            'other' => array(
                'shortname' => $report->shortname,
                'area' => $area,
            ),
        );
        self::$preventcreatecall = false;
        /** @var report_updated $event */
        $event = self::create($data);
        self::$preventcreatecall = true;
        $event->report = $report;
        return $event;
    }

    /**
     * Get report instance.
     *
     * NOTE: to be used from observers only.
     *
     * @return \reportbuilder
     */
    public function get_report() {
        if ($this->is_restored()) {
            throw new \coding_exception('get_report() is intended for event observers only');
        }
        return $this->report;
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'report_builder';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventreportupdated', 'totara_reportbuilder');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $shortname = s($this->other['shortname']);
        $area = $this->other['area'];
        return "The user with id '$this->userid' updated ($area) the report '$this->objectid' ($shortname).";
    }

    /**
     * Return the legacy event log data.
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        global $CFG;
        $logurl = $this->get_url()->out(false);
        $logurl = str_replace($CFG->wwwroot . '/totara/reportbuilder/', '', $logurl);
        return array(SITEID, 'reportbuilder', 'update report', $logurl, $this->report->fullname . ' (ID=' . $this->objectid . ')');
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/totara/reportbuilder/report.php', array('id' => $this->objectid));
    }

    /**
     * Custom validation.
     *
     * @return void
     */
    protected function validate_data() {
        if (self::$preventcreatecall) {
            throw new \coding_exception('cannot call report_updated::create() directly, use report_updated::create_from_report() instead.');
        }

        parent::validate_data();
    }
}
