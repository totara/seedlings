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

define('STATS_EVENT_TIME_SPENT', 1);

define('STATS_EVENT_COURSE_STARTED', 2);

define('STATS_EVENT_COURSE_COMPLETE', 3);

define('STATS_EVENT_COMP_ACHIEVED', 4);

define('STATS_EVENT_OBJ_ACHIEVED', 5);


/**
 * adds an event to the totara stats table.
 *
 * @param int $time - standard timestamp
 * @param int $userid - userid this is related to
 * @param int $eventtype - see defines above for possible values
 * @param string $data - stores string related data for this event
 * @param int $data2 - stores int related data for this event - eg time, id of record.
 * @return boolean (result of insert_record)
 */
function totara_stats_add_event($time, $userid, $eventtype, $data=null, $data2=null) {
    global $DB;
    $newevent = new stdClass();

    $newevent->timestamp = $time;
    $newevent->userid = $userid;
    $newevent->eventtype = $eventtype;
    $newevent->data = $data; //string for events with more info.
    $newevent->data2 = $data2; //integer for timebased events to allow easy sql usage.

    return $DB->insert_record('block_totara_stats', $newevent);
}

/**
 * removes an event from the totara stats table
 *
 * @param int $userid - userid this is related to
 * @param int $eventtype - see defines above for possible values
 * @param int $data2 - stores int related data for this event - eg time, id of record.
 * @return boolean (result of insert_record)
 */
function totara_stats_remove_event($userid, $eventtype, $data2) {
    global $DB;
    return $DB->delete_records('block_totara_stats', array('userid' => $userid, 'eventtype' => $eventtype, 'data2' => $data2));
}

/**
 * used by block cron to obtain daily usage stats.
 *
 * @param int $from - timestamp for start of stats generation
 * @param int $to - timestamp for end of stats generation
 * @return array
 */
function totara_stats_timespent($from, $to) {
    global $CFG;
    $minutesbetweensession = 30; //used to define new session
    if (!empty($CFG->block_totara_stats_minutesbetweensession)) {
        $minutesbetweensession = $CFG->block_totara_stats_minutesbetweensession;
    }
    //calculate timespent by each user
    $logs = totara_stats_get_logs($from, $to);
    $totalTime = array();
    $lasttime = array();
        if (!empty($logs)){
            foreach($logs as $aLog){
                if (empty($lasttime[$aLog->userid])) {
                    $lasttime[$aLog->userid] = $from;
                }
                if (!isset($totalTime[$aLog->userid])) {
                    $totalTime[$aLog->userid] = 0;
                }

                $delta = $aLog->time - $lasttime[$aLog->userid];
                if ($delta < $minutesbetweensession * MINSECS){
                    $totalTime[$aLog->userid] = $totalTime[$aLog->userid] + $delta;
                }
                $lasttime[$aLog->userid] = $aLog->time;
            }
        }
    $logs->close();
    return $totalTime;
}

/**
 * used to return stats for manager stats view
 *
 * @param object $user - Full $USER record (usually from $USER)
 * @return array
 */
function totara_stats_manager_stats($user, $config=null) {
    global $DB;

    //TODO - create a way of setting timeframes
    $to = time();
    $from = $to - (60*60*24*30); //30 days in the past.
    $numhours = 12;
    if (!empty($config->statlearnerhours_hours)) {
        $numhours = (int)$config->statlearnerhours_hours;
    }

    //might need to be careful with length of sql query limit - list of userids could be very large.

    // return users with this user as manager
    $staff = totara_get_staff($user->id);
    list($staffsqlin, $params) = $DB->get_in_or_equal($staff, SQL_PARAMS_NAMED, 'stf');
    $commonsql = " AND userid {$staffsqlin}
                   AND timestamp > :from AND timestamp < :to ";
    $params['from'] = $from;
    $params['to'] = $to;
    unset($staff, $staffsqlin);

    $statssql = array();
    if (empty($config) || !empty($config->statlearnerhours)) {
        $statssql[1] = new stdClass();
        $statssql[1]->sql = "SELECT count(DISTINCT userid) FROM {block_totara_stats} ".
                            "WHERE eventtype = ". STATS_EVENT_TIME_SPENT.$commonsql.
                            "AND userid IN (SELECT bts2.userid FROM {block_totara_stats} bts2 GROUP BY bts2.userid HAVING sum(bts2.data2) > " . $numhours*60*60 . ")";
        $statssql[1]->sqlparams = $params;
        $statssql[1]->string = 'statlearnerhours';
        $statssql[1]->stringparam = new stdClass();
        $statssql[1]->stringparam->hours = $numhours; //extra params used by this particular query - could be configurable in future?
    }
    if (empty($config) || !empty($config->statcoursesstarted)) {
        $statssql[2] = new stdClass();
        $statssql[2]->sql = "SELECT count(*) FROM {block_totara_stats} ".
                            "WHERE eventtype = ". STATS_EVENT_COURSE_STARTED.$commonsql;
        $statssql[2]->sqlparams = $params;
        $statssql[2]->string = 'statcoursesstarted';
    }
    if (empty($config) || !empty($config->statcoursescompleted)) {
        $statssql[3] = new stdClass();
        $statssql[3]->sql = "SELECT count(*) FROM {block_totara_stats} ".
                            "WHERE eventtype = ". STATS_EVENT_COURSE_COMPLETE.$commonsql;
        $statssql[3]->sqlparams = $params;
        $statssql[3]->string = 'statcoursescompleted';
    }
    if (empty($config) || !empty($config->statcompachieved)) {
        $statssql[4] = new stdClass();
        $statssql[4]->sql = "SELECT count(*) FROM {block_totara_stats} ".
                            "WHERE eventtype = ". STATS_EVENT_COMP_ACHIEVED.$commonsql;
        $statssql[4]->sqlparams = $params;
        $statssql[4]->string = 'statcompachieved';
    }
    if (empty($config) || !empty($config->statobjachieved)) {
        $statssql[5] = new stdClass();
        $statssql[5]->sql = "SELECT count(*) FROM {block_totara_stats} ".
                            "WHERE eventtype = ". STATS_EVENT_OBJ_ACHIEVED.$commonsql;
        $statssql[5]->sqlparams = $params;
        $statssql[5]->string = 'statobjachieved';
    }
    return $statssql;
}

/**
 * takes an array of sql queries/lang strings and returns an object with the counts/strings to display in a block - helper function
 *
 * @param object $statsql - object from totara_stats_***_stats functions.
 * @return array
 */
function totara_stats_sql_helper($statsql) {
    global $DB, $OUTPUT;
    $results = array();
    $i = 0;
    foreach ($statsql as $stat) {
        $stringparam = new stdClass();
        if (!empty($stat->stringparam)) {
            $stringparam = $stat->stringparam;
        }
        $stringparam->count = $DB->count_records_sql($stat->sql, $stat->sqlparams);
        $data = new stdClass();
        $data->displaystring = get_string($stat->string, 'block_totara_stats', $stringparam);
        $data->icon = $OUTPUT->pix_icon($stat->string, $data->displaystring, 'block_totara_stats');
        $results[$i] = $data;
        $i++;
    }
    return $results;
}

/**
 * used to display stats in a block. - takes input from a call to totara_stats_helper
 *
 * @param array $stats - array from totara_stats_helper
 * @return string
 */
function totara_stats_output($stats) {
    $return = '';
    if (!empty($stats)) {
        $table = new html_table();
        foreach ($stats as $key => $stat) {
            $rowclass = ($key % 2) ? 'noshade' : 'shade';
            $cell1 = new html_table_cell($stat->icon);
            $cell1->attributes['class'] = 'staticon';
            $cell2 = new html_table_cell(html_writer::tag('p', $stat->displaystring));
            $row = new html_table_row(array($cell1, $cell2));
            $row->attributes['class'] = $rowclass;
            $table->data[] = $row;
        }
        $return = html_writer::table($table);
    }
    return $return;
}

/**
 * obtains log data from logs tables - used by totara_stats_timespent
 *
 * @param int $from - timestamp for start of stats generation
 * @param int $to - timestamp for end of stats generation
 * @param int $courseid (optional) - course id of stats to return
 * @return recordset
 */
function totara_stats_get_logs($from, $to, $courseid=null) {
    global $DB;

    $parms = array($from, $to);
    $courseclause = '';
    if (!is_null($courseid)) {
        $courseclause = " AND course = ? ";
        $parms[]= $courseid;
    }

    $sql = "SELECT * FROM {log}
            WHERE time > ? AND time < ?
            $courseclause
            ORDER BY userid, time";

    $rs = $DB->get_recordset_sql($sql, $parms);
    return $rs;
}
