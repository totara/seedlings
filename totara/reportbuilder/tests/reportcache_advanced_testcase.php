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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 *
 * Base class for testing reports with cache support
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/cron.php');
require_once($CFG->dirroot . '/totara/program/program.class.php');
require_once($CFG->dirroot . '/totara/customfield/definelib.php');
require_once($CFG->dirroot . '/totara/customfield/field/multiselect/define.class.php');
require_once($CFG->dirroot . '/totara/customfield/field/multiselect/field.class.php');
require_once($CFG->dirroot . '/totara/certification/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/tests/generator/lib.php');

abstract class reportcache_advanced_testcase extends advanced_testcase {
    use totara_reportbuilder\phpunit\report_testing;

    /** @var totara_reportbuilder_cache_generator $generator */
    protected static $generator = null;

    /**
     *  Get report recordset
     * @param mixed $shortname string or id
     * @param array $data Report parameters
     * @param bool $ensurecache Make assertion that result was taken from cache
     * @param array $form Search form parameters
     * @return stdClass[]
     */
    protected function get_report_result($shortname, $data, $ensurecache = false, $form = array()) {
        global $DB, $SESSION;
        $SESSION->reportbuilder = array();
        if (is_numeric($shortname)) {
            $report = new reportbuilder($shortname);
        } else {
            $report = reportbuilder_get_embedded_report($shortname, $data, false, 0);
        }
        if ($form) {
            $SESSION->reportbuilder[$report->_id] = $form;
        }
        list($sql, $params, $cache) = $report->build_query(false, true);
        if ($ensurecache) {
            $this->assertArrayHasKey('cachetable', $cache);
            $this->assertStringMatchesFormat('{report_builder_cache_%d_%d}', $cache['cachetable']);
        }
        $result = $DB->get_records_sql($sql, $params);
        return $result;
    }

    /**
     * Returns the caching status of report.
     *
     * @param string|int $shortname use string for embedded reports and integer for custom reports
     * @param array $data embedded report data
     * @return int one of the RB_CACHE_FLAG_* constants
     */
    protected function get_report_cache_status($shortname, $data) {
        global $SESSION;
        $SESSION->reportbuilder = array();
        if (is_numeric($shortname)) {
            $report = new reportbuilder($shortname);
        } else {
            $report = reportbuilder_get_embedded_report($shortname, $data, false, 0);
        }
        return $report->get_cache_status();
    }

    /**
     * Data provider for testing both report states cached and uncached
     * @return array or array of params
     */
    public function provider_use_cache() {
        return array(array(0), array(1));
    }

    /**
     * Get data generator
     * @static Late static binding of overloaded generator
     * @return totara_reportbuilder_cache_generator
     */
    public static function getDataGenerator() {
        if (is_null(static::$generator)) {
            static::$generator = new totara_reportbuilder_cache_generator();
        }
        return static::$generator;
    }
}
