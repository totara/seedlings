<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2009 Catalyst IT LTD
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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Francois Marier <francois@catalyst.net.nz>
 * @package blocks
 * @subpackage facetoface
 */

require_once($CFG->dirroot . '/mod/facetoface/lib.php');
define('TRAINER_CACHE_TIMEOUT', 15); // in minutes

/**
 * Group the Session dates together instead of having separate sessions
 * when it spans multiple days
 * */
function group_session_dates($sessions) {

    $retarray = array();

    foreach ($sessions as $session) {
        if (!array_key_exists($session->sessionid,$retarray)) {
            $alldates = array();

            // clone the session object so we don't override the existing object
            $newsession = clone($session);
            $newsession->timestart = $newsession->timestart;
            $newsession->timefinish = $newsession->timefinish;
            $newsession->sessiontimezone = $newsession->sessiontimezone;
            $retarray[$newsession->sessionid] = $newsession;
        } else {
            if ($session->timestart < $retarray[$session->sessionid]->timestart) {
                $retarray[$session->sessionid]->timestart = $session->timestart;
            }

            if ($session->timefinish > $retarray[$session->sessionid]->timefinish) {
                $retarray[$session->sessionid]->timefinish = $session->timefinish;
            }
            $retarray[$session->sessionid]->sessiontimezone = $session->sessiontimezone;
        }

        // ensure that we have the correct status (enrolled, cancelled) for the submission
        if (isset($session->status) and $session->status == 0) {
           $retarray[$session->sessionid]->status = $session->status;
        }

        $alldates[$session->id] = new stdClass();
        $alldates[$session->id]->timestart = $session->timestart;
        $alldates[$session->id]->timefinish = $session->timefinish;
        $alldates[$session->id]->sessiontimezone = $session->sessiontimezone;
        $retarray[$session->sessionid]->alldates = $alldates;
    }
    return $retarray;
}

/**
 * Separate out the dates from $sessions that finished before the current time
 * */
function past_session_dates($sessions) {

    $retarray = array();
    $timenow = time();

    if (!empty($sessions)) {
        foreach ($sessions as $session) {
            // check if the finish time is before the current time
            if ($session->timefinish < $timenow) {
                $retarray[$session->sessionid] = clone($session);
            }
        }
    }
    return $retarray;
}

/**
 * Separate out the dates from $sessions that finish after the current time
 * */
function future_session_dates($sessions) {

    $retarray = array();
    $timenow = time();

    if (!empty($sessions)) {
        foreach ($sessions as $session) {
            // check if the finish time is after the current time
            if ($session->timefinish >= $timenow) {
                $retarray[$session->sessionid] = clone($session);
            }
        }
    }
    return $retarray;
}

/**
 * Export the given session dates into an ODF/Excel spreadsheet
 */
function export_spreadsheet($dates, $format, $includebookings) {
    global $CFG;

    $timenow = time();
    $timeformat = str_replace(' ', '_', get_string('strftimedate'));
    $downloadfilename = clean_filename('facetoface_'.userdate($timenow, $timeformat));

    if ('ods' === $format) {
        // OpenDocument format (ISO/IEC 26300)
        require_once($CFG->dirroot.'/lib/odslib.class.php');
        $downloadfilename .= '.ods';
        $workbook = new MoodleODSWorkbook('-');
    }
    else {
        // Excel format
        require_once($CFG->dirroot.'/lib/excellib.class.php');
        $downloadfilename .= '.xls';
        $workbook = new MoodleExcelWorkbook('-');
    }

    $workbook->send($downloadfilename);
    $worksheet =& $workbook->add_worksheet(get_string('sessionlist', 'block_facetoface'));

    // Heading (first row)
    $worksheet->write_string(0, 0, get_string('course'));
    $worksheet->write_string(0, 1, get_string('name'));
    //$worksheet->write_string(0, 2, get_string('location'));
    $worksheet->write_string(0, 3, get_string('timestart', 'facetoface'));
    $worksheet->write_string(0, 4, get_string('timefinish', 'facetoface'));
    if ($includebookings) {
        $worksheet->write_string(0, 5, get_string('nbbookings', 'block_facetoface'));
    }

    if (!empty($dates)) {
        $i = 0;
        foreach ($dates as $date) {
            $i++;

            $worksheet->write_string($i, 0, $date->coursename);
            $worksheet->write_string($i, 1, $date->name);
            // TODO: make export gracefully handle location not existing
            //$worksheet->write_string($i, 2, $date->location);
            if ('ods' == $format) {
                $worksheet->write_date($i, 3, $date->timestart);
                $worksheet->write_date($i, 4, $date->timefinish);
            }
            else {
                $worksheet->write_string($i, 3, trim(userdate($date->timestart, get_string('strftimedatetime'))));
                $worksheet->write_string($i, 4, trim(userdate($date->timefinish, get_string('strftimedatetime'))));
            }
            if ($includebookings) {
                $worksheet->write_number($i, 5, isset($date->nbbookings) ? $date->nbbookings : 0);
            }
        }
    }

    $workbook->close();
}

/**
 *  Return a list of users who match the given search
 *  Fields searched are:
 *  - username,
 *  - firstname, lastname as fullname,
 *  - email
 */
function get_users_search($search) {
    global $CFG, $DB;

    $searchvalues = split(' ',trim($search));
    $sort = 'firstname, lastname, username, email ASC';
    $searchfields = array('firstname', 'lastname', 'username', 'email');

    list($where, $searchparams) = facetoface_search_get_keyword_where_clause($searchvalues, $searchfields);
    $sql = "SELECT u.* FROM {user} u WHERE {$where} ORDER BY {$sort}";

    $records = $DB->get_records_sql($sql, $searchparams);

    return $records;
}

/**
 * Add the location info
 */
function add_location_info(&$sessions) {
    global $CFG, $DB;

    if (!$sessions) {
        return false;
    }

    $locationfieldid = $DB->get_field('facetoface_session_field', 'id', array('shortname' => 'location'));
    if (!$locationfieldid) {
        return false;
    }

    $alllocations = $DB->get_records_sql('SELECT d.sessionid, d.data
              FROM {facetoface_sessions} s
              JOIN {facetoface_session_data} d ON d.sessionid = s.id
             WHERE d.fieldid = ?', array($locationfieldid));

    foreach ($sessions as $session) {
        if (!empty($alllocations[$session->sessionid])) {
            $session->location = $alllocations[$session->sessionid]->data;
        }
        else {
            $session->location = '';
        }
    }

    return true;
}

/**
 * Prints form items with the names $day, $month and $year
 *
 * @param int $filtername - the name of the filter to set up i.e coursename, courseid, location, trainer
 * @param int $currentvalue
 * @param boolean $return
 */
function print_facetoface_filters($startdate, $enddate, $currentcoursename, $currentcourseid, $currentlocation, $currenttrainer) {
    global $CFG, $DB;

    $coursenames = array();
    $sessions = array();
    $locations = array();
    $courseids = array();
    $trainers = array();

    $results = $DB->get_records_sql("SELECT s.id AS sessionid, c.id as courseid, c.idnumber, c.fullname,
                                       f.id AS facetofaceid
                                    FROM {course} c
                                    JOIN {facetoface} f ON f.course = c.id
                                    JOIN {facetoface_sessions} s ON f.id = s.facetoface
                                    WHERE c.visible = 1
                                    GROUP BY c.id, c.idnumber, c.fullname, s.id, f.id
                                    ORDER BY c.fullname ASC");

    add_location_info($results);

    if (!empty($results)) {
        foreach ($results as $result) {
            // create unique list of coursenames
            if (!array_key_exists($result->fullname, $coursenames)) {
                $coursenames[$result->fullname] = $result->fullname;
            }

            // created unique list of locations
            if (isset($result->location)) {
                if (!array_key_exists($result->location, $locations)) {
                    $locations[$result->location] = $result->location;
                }
            }

            // create unique list of courseids
            if (!array_key_exists($result->idnumber, $courseids) and $result->idnumber) {
                $courseids[$result->idnumber] = $result->idnumber;
            }

            // create unique list of trainers
            // check if $trainers hasn't already been populated by the cached list
            if (empty($trainers)) {
                if (isset($result->trainers)) {
                    foreach ($result->trainers as $trainer) {
                        if (!array_key_exists($trainer,$trainers)) {
                            $trainers[$trainer] = $trainer;
                        }
                    }
                }
            }
        }
    }

    // Build or print result
    $table = new html_table();
    $table->tablealign = 'left';
    $table->data[] = array(html_writer::tag('label', get_string('daterange', 'block_facetoface'), array('for' => 'menustartdate')),
                           html_writer::select_time('days', 'startday', $startdate) .
                           html_writer::select_time('months', 'startmonth', $startdate) .
                           html_writer::select_time('years', 'startyear', $startdate) . ' ' . strtolower(get_string('to')) . ' ' .
                           html_writer::select_time('days', 'endday', $enddate) .
                           html_writer::select_time('months', 'endmonth', $enddate) .
                           html_writer::select_time('years', 'endyear', $enddate));
    $table->data[] = array(html_writer::tag('label', get_string('coursefullname','block_facetoface').':', array('for' => 'menucoursename')),
                           html_writer::select($coursenames, 'coursename', $currentcoursename, array('' => get_string('all'))));
    if ($locations) {
        $table->data[] = array(html_writer::tag('label', get_string('location', 'facetoface').':', array('for' => 'menulocation')),
            html_writer::select($locations, 'location', $currentlocation, array('' => get_string('all'))));
    }
    echo html_writer::table($table);
}

/**
 * Add the trainer info
 */
function add_trainer_info(&$sessions) {
    global $CFG, $DB;

    $moduleid = $DB->get_field('modules', 'id', array('name' => 'facetoface'));
    $alltrainers = array(); // all possible trainers for filter dropdown

    // find role id for trainer
    $trainerroleid = $DB->get_field('role', 'id', array('shortname' => 'facilitator'));

    foreach ($sessions as $session) {
        // individual session trainers
        $sessiontrainers = array();

        // get trainers for this session from session_roles table
        // set to null if trainer role id not found
        $sess_trainers = (isset($trainerroleid)) ? $DB->get_records_select('facetoface_session_roles', "sessionid = ? AND roleid = ?", array($session->sessionid, $trainerroleid)) : null;

        // check if the module instance has already had trainer info added
        if (!array_key_exists($session->cmid, $alltrainers)) {
            $context = context_module::instance($session->cmid);

            if ($sess_trainers && is_array($sess_trainers)) {
                foreach ($sess_trainers as $sess_trainer) {
                    $user = $DB->get_record('user', array('id' => $sess_trainer->userid));
                    $fullname = fullname($user);
                    if (!array_key_exists($fullname, $sessiontrainers)) {
                        $sessiontrainers[$fullname] = $fullname;
                    }
                }
                if (!empty($sessiontrainers)) {
                    asort($sessiontrainers);
                    $session->trainers = $sessiontrainers;
                    $alltrainers[$session->cmid] = $sessiontrainers;
                } else {
                    $session->trainers = '';
                    $alltrainers[$session->cmid] = '';
                }
            }
        } else {
            if (!empty($alltrainers[$session->cmid])) {
                $session->trainers = $alltrainers[$session->cmid];
            } else {
                $session->trainers = '';
            }
        }
    }

    // cache the trainerlist with an expiry of 15 minutes to help speed up the db load
    $cachevalue = serialize($alltrainers);
    $expiry = time() + TRAINER_CACHE_TIMEOUT * 60;
    set_cache_flag('blocks/facetoface', 'trainers', $cachevalue, $expiry);

}

/**
 * Return an SQL WHERE clause to search for the given keywords
 *
 * @param array $keywords Array of strings to search for
 * @param array $fields Array of SQL fields to search against
 * @param int $type bound param type SQL_PARAMS_QM or SQL_PARAMS_NAMED
 * @param string $prefix named parameter placeholder prefix (unique counter value is appended to each parameter name)
 *
 * @return array Containing SQL WHERE clause and parameters
 */
function facetoface_search_get_keyword_where_clause($keywords, $fields, $type=SQL_PARAMS_QM, $prefix='param') {
    global $DB;

    $queries = array();
    $params = array();
    static $FACETOFACE_SEARCH_PARAM_COUNTER = 1;
    foreach ($keywords as $keyword) {
        $matches = array();
        foreach ($fields as $field) {
            if ($type == SQL_PARAMS_QM) {
                $matches[] = $DB->sql_like($field, '?', false);
                $params[] = '%' . $DB->sql_like_escape($keyword) . '%';
            } else {
                $paramname = $prefix . $FACETOFACE_SEARCH_PARAM_COUNTER;
                $matches[] = $DB->sql_like($field, ":$paramname", false);
                $params[$paramname] = '%' . $DB->sql_like_escape($keyword) . '%';

                $FACETOFACE_SEARCH_PARAM_COUNTER++;
            }
        }
        // look for each keyword in any field
        $queries[] = '(' . implode(' OR ', $matches) . ')';
    }
    // all keywords must be found in at least one field
    return array(implode(' AND ', $queries), $params);
}
