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
 * @author Dan Marsden <dan@catalyst.net.nz>
 * @package totara
 * @subpackage blocks_totara_stats
 */

require_once($CFG->dirroot.'/blocks/totara_stats/locallib.php');

class block_totara_stats extends block_base {

    function init() {
        $this->title = get_string('pluginname', 'block_totara_stats');
    }

    function applicable_formats() {
        return array('all' => true);
    }

    function instance_allow_config() {
        return true;
    }

    function preferred_width() {
        return 210;
    }

    function specialization() {

    }

    function has_config() {
        return true;
    }

    function get_content() {
        global $CFG, $USER;

        // Check if content is cached
        if($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text   = '';
        $this->content->footer = '';

        // Hide block if user has no staff
        if (totara_get_staff()) {
            // now get sql required to return stats
            $stats = totara_stats_manager_stats($USER, $this->config);
            if (!empty($stats)) {
                $this->content->text .= get_string('statdesc', 'block_totara_stats').
                    html_writer::empty_tag('br').html_writer::empty_tag('br').
                    totara_stats_output(totara_stats_sql_helper($stats));
            }
        }

        //TODO: get stuff from reminders/notifications.

        return $this->content;
    }

    function instance_allow_multiple() {
        return true;
    }

    function cron() {
        global $CFG;
        // check if time to run cron
        // first check if cron is within 2 hours of the scheduled time
        // Calculate distance
        $midnight = usergetmidnight(time());
        $dist = ($CFG->block_totara_stats_sche_hour*3600) +      //Hours distance
                ($CFG->block_totara_stats_sche_minute*60);       //Minutes distance
        $result = $midnight + $dist;
        // if between 2 hours of $result.
        if ($result > 0 && $result < time() && $result+(60*120) > time()) {
            // check last time this cron was run
            $lastrun = (int)get_config('block_totara_stats', 'cronlastrun');
            if (empty($lastrun)) {
                // set $lastrun to one month ago: (only process one month of historical stats)
                $lastrun = time() -(60*60*24*30);
            }
            if (time() > ($lastrun + (24*60*60))) { //if at least 24 hours since last run
                require_once($CFG->dirroot.'/blocks/totara_stats/locallib.php');
                $nextrun = time();
                $stats = totara_stats_timespent($lastrun, $nextrun);
                foreach ($stats as $userid => $timespent) {
                    // insert daily stat for each user returned above into new stats table for reading.
                    totara_stats_add_event($nextrun, $userid, STATS_EVENT_TIME_SPENT, '', $timespent);
                }
                set_config('cronlastrun', $nextrun, 'block_totara_stats');
            }
        }
    }
}
