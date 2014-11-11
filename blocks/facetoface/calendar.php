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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');

define('MAX_EVENTS_PER_DAY', 5);
define('MAX_WAITLISTED_SESSIONS', 7);

$timenow    = optional_param('time', time(), PARAM_INT);
$currenttab = optional_param('tab', 'm', PARAM_ALPHA);
$day        = optional_param('cal_d', strftime('%d', $timenow), PARAM_INT);
$month      = optional_param('cal_m', strftime('%m', $timenow), PARAM_INT);
$year       = optional_param('cal_y', strftime('%Y', $timenow), PARAM_INT);

$PAGE->set_context(context_system::instance());
require_login();

$baseparams = array('cal_d' => $day, 'cal_m' => $month, 'cal_y' => $year);

$sessionids = array(); // will get the list of sessions to display in the bottom box
$events = array();

$activefilters = array('defaultfields' => array(), 'customfields' => array());
$hasvalue = array_fill_keys(array('timestart', 'timefinish', 'room', 'building', 'address', 'capacity'), false);
$defaultfields = array();
$activecustomfields = array();
$cfgcalendarfilters = false;

// Separate custom fields from default ones.
if (!empty($CFG->facetoface_calendarfilters)) {
    $allfields = explode(',', $CFG->facetoface_calendarfilters);
    foreach ($allfields as $key) {
        if (is_numeric($key)) {
            $activecustomfields[] = $key;
        } else {
            $defaultfields[] = $key;
        }
    }
    $cfgcalendarfilters = true;
}

// Grab filter parameters (default and custom fields).
foreach ($defaultfields as $fieldname) {
    $currentvalue = optional_param($fieldname, '', PARAM_TEXT);
    if (!empty($currentvalue)) {
        $activefilters['defaultfields'][$fieldname] = $currentvalue;
    }
}

$customfields = facetoface_get_session_customfields();
foreach ($customfields as $field) {
    if (in_array($field->id, $activecustomfields)) {
        $fieldname = "field_{$field->shortname}";
        $currentvalue = optional_param($fieldname, '', PARAM_TEXT);
        if (!empty($currentvalue)) {
            $activefilters['customfields'][$field->id] = $currentvalue;
        }
    } else {
        unset($customfields[$field->id]);
    }
}

// Save/restore filters (default and custom fields).
if (empty($activefilters['defaultfields'])) {
    // Make sure that filter values are emptied.
    $issubmitted = optional_param('submit', '', PARAM_TEXT);
    if (!empty($issubmitted)) {
        $activefilters['defaultfields'] = array();
        set_user_preference('facetoface_calendardefaultfilters', serialize($activefilters['defaultfields']));
    } else {
        // Nothing selected: restore last filter selection from the session.
        $usersetting = get_user_preferences('facetoface_calendardefaultfilters');
        if (!empty($usersetting)) {
            if (!$activefilters['defaultfields'] = unserialize($usersetting)) {
                $activefilters['defaultfields'] = array(); // Broken serialized structure.
            }
        }
    }
} else {
    // Save current filter selection in the session.
    unset_user_preference('facetoface_calendardefaultfilters');
    set_user_preference('facetoface_calendardefaultfilters', serialize($activefilters['defaultfields']));
}

if (empty($activefilters['customfields'])) {
    // Nothing selected: restore last filter selection from the session.
    $usersetting = get_user_preferences('facetoface_calendarfilters');
    if (!empty($usersetting)) {
        if (!$activefilters['customfields'] = unserialize($usersetting)) {
            $activefilters['customfields'] = array(); // Broken serialized structure.
        }
    }
} else {
    // Save current filter selection in the session.
    unset_user_preference('facetoface_calendarfilters');
    set_user_preference('facetoface_calendarfilters', serialize($activefilters['customfields']));
}

// Check if a custom field has been removed as a filter from facetoface settings. If so, the filter is removed from active filters.
$arraydiff = array_diff_key($activefilters['customfields'], $customfields);
foreach ($arraydiff as $key => $value) {
    unset($activefilters['customfields'][$key]);
}

// Page header.
$pagetitle = get_string('trainingcalendar', 'block_facetoface');
$PAGE->navbar->add($pagetitle);
$PAGE->set_title($pagetitle);
$PAGE->set_heading($SITE->fullname);
$PAGE->set_url('/blocks/facetoface/calendar.php');
$PAGE->set_focuscontrol('');
$PAGE->set_cacheable(true);
$PAGE->set_pagelayout('standard');
echo $OUTPUT->header();

// Javascript include (if needed).
if (in_array('timestart', $defaultfields) || in_array('timefinish', $defaultfields)) {
    local_js(array(TOTARA_JS_DATEPICKER));
}

// Default field filters.
$tablecells = array();
foreach ($defaultfields as $fieldname) {
    $tablecells[] = defaultfield_chooser($fieldname);
}

// Custom field filters.
foreach ($customfields as $field) {
    if ($html = customfield_chooser($field)) {
        $tablecells[] = $html;
    }
}

if (!empty($tablecells)) {
    $label = get_string('apply', 'block_facetoface');
    $tablecells[] = html_writer::empty_tag('input', array('type' => 'submit', 'value' => $label, 'name' => 'submit'));

    $table = new html_table();
    $table->data[] = $tablecells;
    $table->tablealign = 'left';
    $table->summary = get_string('filters:tablesummary', 'block_facetoface');

    echo html_writer::start_tag('form', array('method' => 'get', 'action' => 'calendar.php'));
    echo $OUTPUT->box_start('generalbox calendarfilters');
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'tab', 'value' => $currenttab));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'cal_d', 'value' => $day));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'cal_m', 'value' => $month));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'cal_y', 'value' => $year));
    echo html_writer::table($table);
    echo $OUTPUT->box_end();
    echo html_writer::end_tag('form');
}

// Important notices.
if ($notice = get_notice($activefilters['customfields'])) {
    echo $OUTPUT->box_start();
    echo format_text($notice, FORMAT_HTML);
    echo $OUTPUT->box_end();
}

// Display settings
$courses = true; // display all courses
$groups = false; // don't show group events
$users = $USER->id; // show current user events
$courseid = SITEID;
$displayinfo = get_display_info($day, $month, $year);

get_sessions($displayinfo, $groups, $users, $courses, $activefilters, $events, $sessionids);
$waitlistedsessions = get_matching_waitlisted_sessions($activefilters);

$sessionlist_baseurl = new moodle_url('/blocks/facetoface/calendar.php#sessionlist', $baseparams);

// List of all available sessions
echo $OUTPUT->box_start('generalbox clearfix');
$m_url = new moodle_url($sessionlist_baseurl);
$m_url->param('tab', 'm');
$row[] = new tabobject('m', $m_url, get_string('tab:calendar','block_facetoface'));
$c_url = new moodle_url($sessionlist_baseurl);
$c_url->param('tab', 'c');
$row[] = new tabobject('c', $c_url, get_string('tab:bycourse','block_facetoface'));
$d_url = new moodle_url($sessionlist_baseurl);
$d_url->param('tab', 'd');
$row[] = new tabobject('d', $d_url, get_string('tab:bydate','block_facetoface'));
$tabs[] = $row;
echo html_writer::tag('a', '', array('name' => 'sessionlist'));
echo $OUTPUT->container_start();
print_tabs($tabs, $currenttab);
$sessionsbydate = get_sessions_by_date($sessionids, $displayinfo);
if ('c' == $currenttab) {
    $sessionsbycourse = get_sessions_by_course($sessionids, $displayinfo, $waitlistedsessions);
    print_sessions($sessionsbycourse, $currenttab);
}
elseif ('d' == $currenttab) {
    print_sessions($sessionsbydate, $currenttab);
}
else {
    show_month_detailed($baseparams, $displayinfo, $month, $year, $courses, $groups, $users, $courseid, $activefilters, $waitlistedsessions, $events);
}
echo $OUTPUT->container_end();
echo $OUTPUT->box_end();

echo $OUTPUT->footer();

function get_display_info($d, $m, $y) {
    $display = new stdClass;
    $display->minwday = get_user_preferences('calendar_startwday', calendar_get_starting_weekday());
    $display->maxwday = $display->minwday + 6;

    $thisdate = usergetdate(time()); // Time and day at the user's location
    if ($m == $thisdate['mon'] && $y == $thisdate['year']) {
        // Navigated to this month
        $date = $thisdate;
        $display->thismonth = true;
    }
    else {
        // Navigated to other month, let's do a nice trick and save us a lot of work...
        if (!checkdate($m, 1, $y)) {
            $date = array('mday' => 1, 'mon' => $thisdate['mon'], 'year' => $thisdate['year']);
            $display->thismonth = true;
        }
        else {
            $date = array('mday' => 1, 'mon' => $m, 'year' => $y);
            $display->thismonth = false;
        }
    }

    // Fill in the variables we 're going to use, nice and tidy
    list($day, $month, $year) = array($date['mday'], $date['mon'], $date['year']); // This is what we want to display
    $display->maxdays = calendar_days_in_month($m, $y);

    $startwday = 0;
    if (get_user_timezone_offset() < 99) {
        // We 'll keep these values as GMT here, and offset them when the time comes to query the db
        $display->tstart = gmmktime(0, 0, 0, $m, 1, $y); // This is GMT
        $display->tend = gmmktime(23, 59, 59, $m, $display->maxdays, $y); // GMT
        $startwday = gmdate('w', $display->tstart); // $display->tstart is already GMT, so don't use date(): messes with server's TZ
    } else {
        // no timezone info specified
        $display->tstart = mktime(0, 0, 0, $m, 1, $y);
        $display->tend = mktime(23, 59, 59, $m, $display->maxdays, $y);
        $startwday = date('w', $display->tstart); // $display->tstart not necessarily GMT, so use date()
    }

    // Align the starting weekday to fall in our display range
    if ($startwday < $display->minwday) {
        $startwday += 7;
    }

    $display->startwday = $startwday;
    return $display;
}


function get_sessions($display, $groups, $users, $courses, $activefilters, &$events, &$sessionids) {
    global $cfgcalendarfilters;

    // Get events from database.
    $events = calendar_get_events(usertime($display->tstart), usertime($display->tend), $users, $groups, $courses);
    if (!empty($events)) {
        // Check if any filters has been selected.
        if ($cfgcalendarfilters) {
            $defaultfieldsessionids = get_matches_defaultfields($events);
            foreach ($defaultfieldsessionids as $defaultfield) {
                $sessiondata['sessionids'][] = $defaultfield->sessionid;
                $sessiondata['timestart'][] = (int)$defaultfield->timestart;
                $sessiondata['timefinish'][] = (int)$defaultfield->timefinish;
            }
        }
        foreach ($events as $eventid => $event) {
            if (empty($event->modulename) || empty($event->instance)) {
                continue; // Nothing to check.
            }

            $eventcourse = $event->courseid;
            if (empty($eventcourse)) {
                // Try to figure out the courseid based on the module and instance properties.
                $cm = get_coursemodule_from_instance($event->modulename, $event->instance);
                $eventcourse = $cm->course;
            }

            // Check that the course is viewable.
            if (empty($eventcourse) || !totara_course_is_viewable($eventcourse)) {
                unset($events[$eventid]);
                continue;
            }

            // Check that facetoface events match all filters.
            $sessionid = (int)$event->uuid;
            if ('facetoface' == $event->modulename and $sessionid > 0) {
                $matchesallfilters = true;

                // Check if there are active filters and they are not empty.
                if ($cfgcalendarfilters && count($activefilters['defaultfields'])) {
                    if (!count($defaultfieldsessionids) || !matches_defaultfield_filters($sessiondata, $event)) {
                        unset($events[$eventid]);
                        continue;
                    }
                }

                foreach ($activefilters['customfields'] as $fieldid => $fieldvalue) {
                    if (!matches_filter($fieldid, $fieldvalue, $sessionid)) {
                        // Different value => no match.
                        $matchesallfilters = false;
                        break;
                    }
                }

                if ($matchesallfilters) {
                    $sessionids[] = $sessionid;
                }
                else {
                    unset($events[$eventid]);
                    continue; // Move to next event.
                }
            }

            // Group checks.
            $cm = get_coursemodule_from_instance($event->modulename, $event->instance);
            if (!groups_course_module_visible($cm)) {
                unset($events[$eventid]);
            }
        }
    }
}

/**
 * Get all records that match the current criteria for default fields
 *
 * @param array $events facetoface events
 *
 * @return array with records that match the current filters, empty array if no matches
 */
function get_matches_defaultfields($events) {
    global $DB, $activefilters, $hasvalue;

    $eventids = array();
    $fieldvalues = array();

    // Get the values for any active filters that is not empty.
    foreach ($activefilters['defaultfields'] as $fieldname => $value) {
        if (trim($value) && ($value != get_string('datepickerlongyearplaceholder', 'totara_core'))) {
            $fieldvalues[$fieldname] = $value;
            $hasvalue[$fieldname] = true;
        }
    }

    // Get all session ids.
    foreach ($events as $event) {
        $eventids[] = (int)$event->uuid;
    }

    if (count($eventids)) {
        list($sessionids, $params) = $DB->get_in_or_equal($eventids);
        $sql = "SELECT fsd.id AS sessiondateid, fsd.timestart, fsd.timefinish, fs.id AS sessionid
                FROM {facetoface_sessions} fs
                LEFT JOIN {facetoface_sessions_dates} fsd ON fs.id = fsd.sessionid
                LEFT JOIN {facetoface_room} fr ON fs.roomid = fr.id
                WHERE fs.id $sessionids ";

        // Check that at least one filter is not empty.
        if (count($fieldvalues)) {
            // Add constrains to the query for those fields that are not empty.
            if (array_key_exists('timestart', $fieldvalues)) {
                $sql .= "AND fsd.timestart >= ? ";
                $cleanedformatdate = str_replace('%', '', get_string('datepickerlongyearphpuserdate', 'totara_core'));
                $fieldvalues['timestart'] = totara_date_parse_from_format($cleanedformatdate, $fieldvalues['timestart']);
                $activefilters['defaultfields']['unixtimestart'] = $fieldvalues['timestart'];
            }
            if (array_key_exists('timefinish', $fieldvalues)) {
                $sql .= "AND fsd.timefinish <= ? ";
                $cleanedformatdate = str_replace('%', '', get_string('datepickerlongyearphpuserdate', 'totara_core'));
                $fieldvalues['timefinish'] = totara_date_parse_from_format($cleanedformatdate, $fieldvalues['timefinish']);
                $activefilters['defaultfields']['unixtimefinish'] = $fieldvalues['timefinish'];
            }
            if (array_key_exists('room', $fieldvalues)) {
                $sql .= "AND fr.name = ? ";
            }
            if (array_key_exists('building', $fieldvalues)) {
                $sql .= "AND fr.building = ? ";
            }
            if (array_key_exists('address', $fieldvalues)) {
                $sql .= "AND fr.address = ? ";
            }
            if (array_key_exists('capacity', $fieldvalues)) {
                $sql .= "AND fs.capacity = ?";
            }

            $params = array_merge($params, array_values($fieldvalues));
        }

        return $DB->get_records_sql($sql, $params);
    }

    return array();
}

/**
 * Check if the current event matches the criteria
 *
 * @param array $sessiondata session information for all sessions which meet the current criteria
 * @param object $event individual facetoface event
 *
 * @return boolean true if eventid matches any ids returned by get_matches_defaultfields function, false otherwise
 */
function matches_defaultfield_filters($sessiondata, $event) {
    global $hasvalue;

    if (!in_array((int)$event->uuid, $sessiondata['sessionids'])) {
        return false;
    } else if ($hasvalue['timestart'] && !in_array($event->timestart, $sessiondata['timestart'])) {
        return false;
    } else if ($hasvalue['timefinish'] && !in_array(($event->timestart + $event->timeduration), $sessiondata['timefinish'])) {
        return false;
    }

    return true;
}

/*
 * Prints calendar view with all session that match current filters and all session that you have created with a grey background
 */
function show_month_detailed($baseparams, $display, $m, $y, $courses, $groups, $users, $courseid, $activefilters, $waitlistedsessions, $events) {
    global $USER, $SESSION, $OUTPUT, $CFG;
    global $timenow;

    $calendardays = calendar_get_days();

    $weekend = CALENDAR_DEFAULT_WEEKEND;
    if (isset($CFG->calendar_weekend)) {
        $weekend = intval($CFG->calendar_weekend);
    }

    // Extract information: events vs. time
    calendar_events_by_day($events, $m, $y, $eventsbyday, $durationbyday, $typesbyday, $courses);
    echo $OUTPUT->container_start(null, 'calendarcontainer');
    echo $OUTPUT->box_start('generalbox monthlycalendar');
    $text = '';
    echo $OUTPUT->container($text, 'header');

    echo $OUTPUT->container(top_controls($m, $y), 'controls');

    // Start calendar display
    echo html_writer::start_tag('table', array('class' => 'calendarmonth', 'summary' => get_string('calendar:tablesummary', 'block_facetoface')));
    echo html_writer::start_tag('tr', array('class' => 'weekdays')); // Begin table. First row: day names

    // Print out the names of the weekdays
    for($i = $display->minwday; $i <= $display->maxwday; ++$i) {
        // This uses the % operator to get the correct weekday no matter what shift we have
        // applied to the $display->minwday : $display->maxwday range from the default 0 : 6
        $day = $calendardays[$i % 7]['fullname'];
        echo html_writer::tag('th', $day, array('scope' => 'col'));
    }

    echo html_writer::end_tag('tr') . html_writer::start_tag('tr'); // End of day names; prepare for day numbers

    // For the table display. $week is the row; $dayweek is the column.
    $week = 1;
    $dayweek = $display->startwday;

    // Paddding (the first week may have blank days in the beginning)
    for($i = $display->minwday; $i < $display->startwday; ++$i) {
        echo html_writer::tag('td', '&nbsp;', array('class' => 'nottoday')) . "\n";
    }

    // Now display all the calendar
    for($day = 1; $day <= $display->maxdays; ++$day, ++$dayweek) {
        if ($dayweek > $display->maxwday) {
            // We need to change week (table row)
            echo html_writer::end_tag('tr') . html_writer::start_tag('tr');
            $dayweek = $display->minwday;
            ++$week;
        }

        // Reset vars
        $cell = '';
        $dayhref = calendar_get_link_href(new moodle_url(CALENDAR_URL.'view.php', array('view' => 'day', 'course' => $courseid)), $day, $m, $y);

        if ($weekend & (1 << ($dayweek % 7))) {
            // Weekend. This is true no matter what the exact range is.
            $class = 'weekend';
        }
        else {
            // Normal working day.
            $class = '';
        }

        // Special visual fx if an event is defined
        if (isset($eventsbyday[$day])) {
            if (count($eventsbyday[$day]) == 1) {
                $title = get_string('oneevent', 'calendar');
            }
            else {
                $title = get_string('manyevents', 'calendar', count($eventsbyday[$day]));
            }
            $cell = $OUTPUT->container(html_writer::link($dayhref, $day, array('title' => $title)), array('class' => 'day'));
        }
        else {
            $cell = $OUTPUT->container($day, array('class' => 'day'));
        }

        // Special visual fx if an event spans many days
        if (isset($typesbyday[$day]['durationglobal'])) {
            $class .= ' duration_global';
        }
        else if (isset($typesbyday[$day]['durationcourse'])) {
            $class .= ' duration_course';
        }
        else if (isset($typesbyday[$day]['durationgroup'])) {
            $class .= ' duration_group';
        }
        else if (isset($typesbyday[$day]['durationuser'])) {
            $class .= ' duration_user';
        }

        // Special visual fx for today
        $today = strftime('%d', $timenow);
        if ($display->thismonth && $day == $today) {
            $class .= ' today';
        } else {
            $class .= ' nottoday';
        }

        // Just display it
        if (!empty($class)) {
            $class = array('class' => trim($class));
        } else {
            $class = array();
        }
        $cellid = sprintf('cell%d%02d%02d', $y, $m, $day);// outputs 'cellYYYYMMDD' string as intended
        echo html_writer::start_tag('td', array_merge(array('id' => $cellid), $class)) . $cell;

        if (isset($eventsbyday[$day])) {
            echo html_writer::start_tag('ul');
            $i = 1;
            foreach ($eventsbyday[$day] as $eventindex) {
                if ($i < MAX_EVENTS_PER_DAY or count($eventsbyday[$day]) == MAX_EVENTS_PER_DAY) {
                    // If event has a class set then add it to the event <li> tag
                    $eventattributes = array();
                    if (!empty($events[$eventindex]->class)) {
                        $eventattributes['class'] = $events[$eventindex]->class;
                    }

                    $day_string = $dayhref->out();
                    echo html_writer::tag('li', html_writer::link(new moodle_url($day_string. '#event_'.$events[$eventindex]->id), format_string($events[$eventindex]->name, true), $eventattributes));
                    $i++;
                }
                else {
                    echo html_writer::tag('li', get_string('xevents', 'block_facetoface', count($eventsbyday[$day]) - MAX_EVENTS_PER_DAY + 1));
                    break;
                }
            }
            echo html_writer::end_tag('ul');
        }
        echo html_writer::end_tag('td') . "\n";
    }

    // Paddding (the last week may have blank days at the end)
    for($i = $dayweek; $i <= $display->maxwday; ++$i) {
        echo html_writer::tag('td', '&nbsp;', array('class' => 'nottoday'));
    }
    echo html_writer::end_tag('tr') . "\n"; // Last row ends

    echo html_writer::end_tag('table') . "\n"; // Tabular display of days ends

    echo $OUTPUT->box_end();
    echo $OUTPUT->container_end();

    // Advertising side-bar
    echo $OUTPUT->box_start('generalbox calendarsidebar');
    print_waitlisted_content($waitlistedsessions);
    echo $OUTPUT->box_end();
}

function top_controls($month, $year) {
    global $OUTPUT, $currenttab;

    $content = '';

    $time = make_timestamp($year, $month, 1);
    $date = usergetdate($time);

    $data['m'] = $date['mon'];
    $data['y'] = $date['year'];

    list($prevmonth, $prevyear) = calendar_sub_month($data['m'], $data['y']);
    list($nextmonth, $nextyear) = calendar_add_month($data['m'], $data['y']);
    $prevdate = make_timestamp($prevyear, $prevmonth, 1);
    $nextdate = make_timestamp($nextyear, $nextmonth, 1);
    $content .= $OUTPUT->container_start('calendar-controls');
    $content .= html_writer::tag('div', calendar_get_link_previous(userdate($prevdate, get_string('strftimemonthyear')), "calendar.php?tab=$currenttab&amp;", 1, $prevmonth, $prevyear));
    $content .= html_writer::tag('div', html_writer::tag('span', userdate($time, get_string('strftimemonthyear'))), array('class' => 'current'));
    $content .= html_writer::tag('div', calendar_get_link_next(userdate($nextdate, get_string('strftimemonthyear')), "calendar.php?tab=$currenttab&amp;", 1, $nextmonth, $nextyear));
    $content .= $OUTPUT->container_end();
    return $content;
}

/**
 * Render the default field filters that have been selected in Face-to-face settings
 *
 * @param string $fieldname name of the field
 *
 * @return string that contains the HTML for the field
 */
function defaultfield_chooser($fieldname) {
    global $activefilters;

    $value = isset($activefilters['defaultfields'][$fieldname]) ? $activefilters['defaultfields'][$fieldname] : '';
    if (in_array($fieldname, array('timestart', 'timefinish')) && !$value) {
        $value = get_string('datepickerlongyearplaceholder', 'totara_core');
    }
    switch($fieldname) {
        case 'timestart':
            $label = html_writer::empty_tag('input', array('type' => 'text', 'size' => 10, 'name' => $fieldname, 'id' => $fieldname, 'value' => $value));
            build_datepicker_js("#$fieldname", get_string('datepickerlongyeardisplayformat', 'totara_core'));
            $fieldname = 'startdateafter';
            break;
        case 'timefinish':
            $label = html_writer::empty_tag('input', array('type' => 'text', 'size' => 10, 'name' => $fieldname, 'id' => $fieldname, 'value' => $value));
            build_datepicker_js("#$fieldname", get_string('datepickerlongyeardisplayformat', 'totara_core'));
            $fieldname = 'finishdatebefore';
            break;
        case 'room':
            $label = html_writer::empty_tag('input', array('type' => 'text', 'size' => 10, 'name' => $fieldname, 'value' => $value));
            break;
        case 'building':
            $label = html_writer::empty_tag('input', array('type' => 'text', 'size' => 10, 'name' => $fieldname, 'value' => $value));
            break;
        case 'address':
            $label = html_writer::empty_tag('input', array('type' => 'text', 'size' => 10, 'name' => $fieldname, 'value' => $value));
            break;
        case 'capacity':
            $label = html_writer::empty_tag('input', array('type' => 'text', 'size' => 1, 'name' => $fieldname, 'value' => $value));
            break;
    }

    return get_string($fieldname, 'facetoface') . ': ' . $label;
}

function customfield_chooser($field) {
    global $activefilters, $DB;

    $values = array();
    switch ($field->type) {
        case CUSTOMFIELD_TYPE_TEXT:
            $records = $DB->get_records('facetoface_session_data', array('fieldid' => $field->id), 'data', 'id, data');
            foreach ($records as $record) {
                $values[$record->data] = $record->data;
            }
            break;

        case CUSTOMFIELD_TYPE_SELECT:
        case CUSTOMFIELD_TYPE_MULTISELECT:
            $values = explode(CUSTOMFIELD_DELIMITER, $field->possiblevalues);
            break;

        default:
            return false; // Invalid type.
    }

    // Build up dropdown list of values.
    $options = array();
    if (!empty($values)) {
        foreach ($values as $value) {
            $v = clean_param(trim($value), PARAM_TEXT);
            if (!empty($v)) {
                $options[s($v)] = format_string($v);
            }
        }
    }

    $nothing = get_string('all');
    $nothingvalue = 'all';

    $fieldname = "field_$field->shortname";
    $currentvalue = $nothingvalue;
    if (!empty($activefilters['customfields'][$field->id])) {
        $currentvalue = $activefilters['customfields'][$field->id];
    }

    $dropdown = html_writer::select($options, $fieldname, $currentvalue, array($nothingvalue => $nothing));

    return format_string($field->name) . ': ' . $dropdown;
}

/**
 * Whether or not the given session matches the given filter value.
 */
function matches_filter($filterid, $filtervalue, $sessionid)
{
    global $customfields, $DB;

    if ('all' == $filtervalue) {
        return true; // Not actually a filter
    }

    // Warning: this get_field doesn't show up in the total number of DB queries, but it's called a lot!
    $sessionvalue = $DB->get_field('facetoface_session_data', 'data', array('fieldid' => $filterid, 'sessionid' => $sessionid));
    if (empty($sessionvalue)) {
        // No value at all => no match
        return false;
    }

    if (CUSTOMFIELD_TYPE_MULTISELECT == $customfields[$filterid]->type) {
        $values = explode(';', $sessionvalue);
        foreach ($values as $value) {
            if (trim($value) == $filtervalue) {
                return true;
            }
        }
        return false;
    }

    return $filtervalue == $sessionvalue;
}

function get_sessions_by_date($sessionids, $displayinfo) {
    global $DB, $activefilters, $hasvalue;

    if (empty($sessionids)) {
        return array();
    }

    // If timestart/timefinish has a date, it uses that date. It uses the current month otherwise.
    $timestart = $hasvalue['timestart'] ? $activefilters['defaultfields']['unixtimestart'] : usertime($displayinfo->tstart);
    $timeend = $hasvalue['timefinish'] ? $activefilters['defaultfields']['unixtimefinish'] : usertime($displayinfo->tend);

    list($insql, $params) = $DB->get_in_or_equal($sessionids);
    $params[] = $timestart;
    $params[] = $timeend;

    $sessions = $DB->get_records_sql("SELECT d.id, s.id AS sessionid, f.id AS facetofaceid, f.name, s.datetimeknown, d.timestart, d.timefinish, d.sessiontimezone
                                   FROM {facetoface} f
                                   JOIN {facetoface_sessions} s ON f.id = s.facetoface
                                   JOIN {facetoface_sessions_dates} d ON d.sessionid = s.id
                                  WHERE s.id {$insql} AND d.timestart >= ? AND d.timestart <= ?
                               ORDER BY d.timestart", $params);

    return $sessions;
}

// Same as previous function by sorted by activity name and including waitlisted sessions
function get_sessions_by_course($sessionids, $displayinfo, $waitlistedsessions) {
    global $DB, $activefilters, $hasvalue;

    if (empty($sessionids) && empty($waitlistedsessions)) {
        return array();
    }

    // Add IDs of wait-listed sessions.
    foreach ($waitlistedsessions as $session) {
        // If no date has been selected then add the sessionid.
        if (!$hasvalue['timestart'] && !$hasvalue['timefinish']) {
            $sessionids[] = $session->id;
        }
    }

    list($insql, $params) = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED);

    // If timestart/timefinish has a date, it uses that date. It uses the current month otherwise.
    $timestart = $hasvalue['timestart'] ? $activefilters['defaultfields']['unixtimestart'] : usertime($displayinfo->tstart);
    $timeend = $hasvalue['timefinish'] ? $activefilters['defaultfields']['unixtimefinish'] : usertime($displayinfo->tend);
    $params['timestart'] = $timestart;
    $params['timeend'] = $timeend;

    list($visibilitysql, $visibilityparams) = totara_visibility_where(null, 'c.id', 'c.visible', 'c.audiencevisible');
    $params = array_merge($params, $visibilityparams);

    $sessions = $DB->get_records_sql("SELECT d.id, s.id AS sessionid, f.id AS facetofaceid, f.name, s.datetimeknown, d.timestart, d.timefinish, d.sessiontimezone
                                   FROM {facetoface} f
                                   JOIN {facetoface_sessions} s ON f.id = s.facetoface
                                   JOIN {facetoface_sessions_dates} d ON d.sessionid = s.id
                                   JOIN {course} c ON c.id = f.course
                                   JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = " . CONTEXT_COURSE . "
                                  WHERE s.id {$insql}
                                   AND ((d.timestart >= :timestart AND d.timestart <= :timeend) OR s.datetimeknown = 0)
                                   AND {$visibilitysql}
                               ORDER BY f.name, d.timestart", $params);

    return $sessions;
}

function print_sessions($sessions, $tab) {
    global $CFG, $DB, $OUTPUT;
    global $customfields;

    if (empty($sessions)) {
        print_string('nosessions', 'block_facetoface');
        return;
    }

    $currentday = 0;
    $currenttable = new_session_table();


    foreach ($sessions as $session) {
        $sessionlink = html_writer::link(new moodle_url('/mod/facetoface/view.php', array('f' => $session->facetofaceid)), format_string($session->name));
        $sessionrow = array($sessionlink);

        $timestart = $session->timestart;
        $timefinish = $session->timefinish;

        $day = 0;
        if ($tab != 'c') {
            $day = strftime('%Y%m%d', $timestart);
        }

        if ($currentday < $day) {
            if ($currentday > 0) {
                echo html_writer::table($currenttable);
            }
            $currenttable = new_session_table();

            echo $OUTPUT->heading(strftime(get_string('strftimedate'), $timestart), 3);
            $currentday = $day;
        }

        // Custom fields
        $customdata = $DB->get_records('facetoface_session_data', array('sessionid' => $session->sessionid), '', 'fieldid, data');
        $nbcustomcolumns = 0;
        foreach ($customfields as $field) {
            if (empty($field->showinsummary)) {
                continue;
            }

            $nbcustomcolumns++;
            if (empty($customdata[$field->id])) {
                $sessionrow[] = '&nbsp;';
            }
            else {
                $sessionrow[] = format_string($customdata[$field->id]->data);
            }
        }

        $signuplink = html_writer::link(new moodle_url('/mod/facetoface/signup.php', array('s' => $session->sessionid)), get_string('moreinfo', 'facetoface'));

        if ($session->datetimeknown) {
            // Scheduled sessions
            if ('c' == $tab) {
                $sessionrow[] = userdate($timestart, get_string('strftimedate'));
            }
            $sessionrow[] = userdate($timestart, get_string('strftimetime'));
            $sessionrow[] = userdate($timefinish, get_string('strftimetime'));
        }
        elseif ('c' == $tab) {
            // Wait-listed sessions
            $sessionrow[] = get_string('wait-listed', 'facetoface');
            $sessionrow[] = '&nbsp;';
            $sessionrow[] = '&nbsp;';
        }
        else {
            // Not supposed to happen, wait-listed sessions are only for the "By Course" tab
            error_log('Warning: F2F Training Calendar found a wait-listed session in "By Date" tab');
            continue;
        }
        $sessionrow[] = $signuplink;
        $currenttable->data[] = $sessionrow;

        // Set headings
        $headings = array();
        $headings['name'] = get_string('name');
        foreach ($customfields as $field) {
            if (!empty($field->showinsummary)) {
                $headings[$field->shortname] = $field->name;
            }
        }
        if ($tab == 'c') {
            $headings['date'] = get_string('date');
        }
        $headings['starttime'] = get_string('starttime', 'block_facetoface');
        $headings['finishtime'] = get_string('finishtime', 'block_facetoface');
        $headings['details'] = get_string('details', 'facetoface');

        $currenttable->head = $headings;

        // First couple of columns should take most of the space
        $nbcolumns = count($sessionrow);
        $percent = 100.0 / ($nbcolumns - 1);
        $currenttable->size = array('*');
        for ($i=0; $i < $nbcustomcolumns; $i++) {
            $currenttable->size[] = "{$percent}%";
        }
        if ('c' == $tab) {
            $currenttable->size[] = '10em';
        }
        $currenttable->size[] = '6em';
        $currenttable->size[] = '6em';
        $currenttable->size[] = '6em';
    }
    echo html_writer::table($currenttable);
}

function new_session_table() {
    $table = new html_table();
    return $table;
}

/**
 * Return a random notice matching the current criteria
 */
function get_notice($activefilters) {
    global $CFG, $DB;
    global $customfields;

    // Get all notices
    if (!$allnotices = $DB->get_records('facetoface_notice', array(), 'id', 'id')) {
        return false; // no matches
    }

    $filterjoins = '';
    $filterwhere = '';
    $filterparams = array();

    $filternb = 1;
    foreach ($activefilters as $fieldid => $fieldvalue) {
        if ('all' == $fieldvalue) {
            continue; // Not actually a filter
        }

        $joincondition = "d1.noticeid = n.id";
        if ($filternb > 1) {
            $joincondition = "d{$filternb}.noticeid = d". ($filternb - 1) .'.noticeid';
        }
        $filterjoins .= " JOIN {facetoface_notice_data} d$filternb ON $joincondition";

        //$whereclause = "d{$filternb}.data <> '$value'";
        $whereclause = "d{$filternb}.data <> ?";
        $whereparams = array($fieldvalue);
        if (CUSTOMFIELD_TYPE_MULTISELECT == $customfields[$fieldid]->type) {
            $whereclause = $DB->sql_like("d{$filternb}.data", '?', true, true, true);
            $whereparams = array('%' . $fieldvalue . '%');
        }
        $filterwhere .= " OR (d{$filternb}.fieldid = ? AND $whereclause) ";
        $filterparams = array_merge($filterparams, array($fieldid), $whereparams);
        $filternb++;
    }

    // Get notices to filter out
    $sql = "SELECT DISTINCT n.id
                  FROM {facetoface_notice} n
                  $filterjoins
                 WHERE 1 = 0
                 $filterwhere
              ORDER BY n.id";
    $nonmatchingnotices = $DB->get_records_sql($sql, $filterparams);

    // Compute the difference
    $noticeids = array_diff(array_keys($allnotices), array_keys($nonmatchingnotices));
    if (empty($noticeids)) {
        return false;
    }

    // Select a notice at random
    shuffle($noticeids);
    $randomnoticeid = reset($noticeids);
    return $DB->get_field('facetoface_notice', 'text', array('id' => $randomnoticeid));
}

function get_matching_waitlisted_sessions($activefilters) {
    global $DB, $customfields, $hasvalue;

    $filterjoins = '';
    $filterwhere = 'AND f.showoncalendar > ?';
    $filterparams = array(F2F_CAL_NONE);

    $filternb = 1;
    foreach ($activefilters['customfields'] as $fieldid => $fieldvalue) {
        if ('all' == $fieldvalue) {
            continue; // Not actually a filter.
        }

        $joincondition = "d1.sessionid = s.id";
        if ($filternb > 1) {
            $joincondition = "d{$filternb}.sessionid = d". ($filternb - 1) .'.sessionid';
        }
        $filterjoins .= " JOIN {facetoface_session_data} d$filternb ON $joincondition";

        $whereclause = "d{$filternb}.data = ?";
        $whereparams = array($fieldvalue);
        if (CUSTOMFIELD_TYPE_MULTISELECT == $customfields[$fieldid]->type) {
            $whereclause = $DB->sql_like("d{$filternb}.data", '?', true, true, true);
            $whereparams = array('%' . $fieldvalue . '%');
        }
        $filterwhere .= " AND d{$filternb}.fieldid = ? AND $whereclause ";
        $filterparams = array_merge($filterparams, array($fieldid), $whereparams);
        $filternb++;
    }

    // If capacity is not empty then add it as a condition.
    if ($hasvalue['capacity']) {
        $filterwhere .= " AND s.capacity = ?";
        $filterparams = array_merge($filterparams, array($activefilters['defaultfields']['capacity']));
    }

    // Get all waitlisted sessions.
    $sessions = $DB->get_records_sql("SELECT s.id, s.facetoface, f.name
                                      FROM {facetoface} f
                                      JOIN {facetoface_sessions} s ON f.id = s.facetoface
                                      $filterjoins
                                      WHERE s.datetimeknown = 0
                                      $filterwhere", $filterparams);

    foreach ($sessions as $session) {
        // If date or room information is not empty then remove the session.
        if ($hasvalue['timestart'] || $hasvalue['timefinish'] || $hasvalue['room'] || $hasvalue['building'] || $hasvalue['address']) {
            unset($sessions[$session->id]);
        }
    }

    return $sessions;
}

function print_waitlisted_content($waitlistedsessions) {
    global $CFG, $DB, $OUTPUT;

    if (empty($waitlistedsessions)) {
        print_string('nowaitlistedsessions', 'block_facetoface');;
        return;
    }

    $html = '';

    // Randomly select sessions to show
    if (count($waitlistedsessions) > MAX_WAITLISTED_SESSIONS) {
        $html .= get_string('toomanywaitlistedsessions', 'block_facetoface', MAX_WAITLISTED_SESSIONS);

        shuffle($waitlistedsessions);
        $waitlistedsessions = array_slice($waitlistedsessions, 0, MAX_WAITLISTED_SESSIONS);
    }
    else {
        $html .= get_string('showingallwaitlistedsessions', 'block_facetoface');
    }

    // Show the selected sessions
    foreach ($waitlistedsessions as $session) {
        // Getting only the fields we need in order to save some memory.
        $f2f = $DB->get_record('facetoface', array('id' => $session->facetoface), 'id, course, intro');

        $visible = totara_course_is_viewable($f2f->course);

        // If the course isn't viewable then don't show it.
        if ($visible) {
            $html .= html_writer::empty_tag('hr');

            $html .= $OUTPUT->container_start();
            $sessionurl = new moodle_url('/mod/facetoface/signup.php', array('s' => $session->id));
            $html .= html_writer::link($sessionurl, format_string($session->name));
            $html .= $OUTPUT->container_end();

            $description = $f2f->intro;
            if (!empty($description)) {
                $description = format_text($description, FORMAT_HTML);
                $html .= $OUTPUT->container($description);
            }
        }
    }

    print $html;
}
