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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package blocks
 * @subpackage facetoface
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
 * * Standard HTML output renderer for block_facetoface module
 * */
class block_facetoface_renderer extends plugin_renderer_base {

    public function print_dates($dates, $includebookings, $includegrades=false, $includestatus=false, $includecourseid=false, $includetrainers=false, $showlocation=true) {
        global $CFG, $USER;

        $output = '';

        $courselink = $CFG->wwwroot.'/course/view.php?id=';
        $facetofacelink = $CFG->wwwroot.'/mod/facetoface/view.php?f=';
        $attendeelink = $CFG->wwwroot.'/mod/facetoface/attendees.php?s=';
        $bookinghistoryurl = new moodle_url('/blocks/facetoface/bookinghistory.php');

        $output .= html_writer::start_tag('table', array('border' => "1", 'cellpadding' => "5", 'summary' => get_string('sessiondatestable', 'block_facetoface'))) . html_writer::start_tag('tr');

        // include the course id in the display
        if ($includecourseid) {
            $output .= html_writer::tag('th', get_string('idnumbercourse'));
        }

        $output .= html_writer::tag('th', get_string('course'));
        $output .= html_writer::tag('th', get_string('name'));
        if ($showlocation) {
            $output .= html_writer::tag('th', get_string('location'));
        }
        $output .= html_writer::tag('th', get_string('date','block_facetoface'));
        $output .= html_writer::tag('th', get_string('time', 'block_facetoface'));

        if ($includebookings) {
            $output .= html_writer::tag('th', get_string('nbbookings', 'block_facetoface'));
        }

        // include the grades/status in the display
        if ($includegrades || $includestatus) {
            $output .= html_writer::tag('th', get_string('status'));
        }

        $output .= html_writer::end_tag('tr');
        $even = false; // used to colour rows
        foreach ($dates as $date) {

            // include the grades in the display
            if ($includegrades) {
                $grade = facetoface_get_grade($date->userid, $date->courseid, $date->facetofaceid);
            }

            if ($even) {
                $output .= html_writer::start_tag('tr', array('style' => 'background-color: #CCCCCC', 'valign' => 'top'));
            }
            else {
                $output .= html_writer::start_tag('tr', array('valign' => 'top'));
            }
            $even = !$even;
            if ($includecourseid) {
                $output .= html_writer::tag('td', $date->cidnumber);
            }
            $output .= html_writer::tag('td', html_writer::link($courselink.$date->courseid, format_string($date->coursename)));

            $output .= html_writer::tag('td', html_writer::link($facetofacelink.$date->facetofaceid, format_string($date->name)));
            if ($showlocation) {
                $location = isset($date->location) ? $date->location : '';
                $output .= html_writer::tag('td', format_string($location));
            }

            $output .= html_writer::start_tag('td');
            if ($date->datetimeknown) {
                $sessiondates = $date->alldates;
                foreach ($sessiondates as $sessiondate) {
                    $sessionobj = facetoface_format_session_times($sessiondate->timestart, $sessiondate->timefinish, $sessiondate->sessiontimezone);
                    if ($sessionobj->startdate == $sessionobj->enddate) {
                        $output .= $sessionobj->startdate . html_writer::empty_tag('br');
                    } else {
                        $output .= $sessionobj->startdate . ' - ' . $sessionobj->enddate . html_writer::empty_tag('br');
                    }
                }
                $output .= html_writer::end_tag('td');
                $output .= html_writer::start_tag('td');
                foreach ($sessiondates as $sessiondate) {
                    $sessionobj = facetoface_format_session_times($sessiondate->timestart, $sessiondate->timefinish, $sessiondate->sessiontimezone);
                    $output .= $sessionobj->starttime . ' - ' . $sessionobj->endtime . ' ' . $sessionobj->timezone . html_writer::empty_tag('br');
                }
            } else {
                $output .= get_string('datenotset', 'block_facetoface');
                $output .= html_writer::end_tag('td');
                $output .= html_writer::start_tag('td');
            }
            $output .= html_writer::end_tag('td');

            if ($includebookings) {
                $output .= html_writer::tag('td', html_writer::link($attendeelink.$date->sessionid, (isset($date->nbbookings)? format_string($date->nbbookings) : 0)));
            }

            // include the grades/status in the display
            foreach (array($includegrades, $includestatus) as $col) {
                if ($col) {
                    $bookinghistoryurl->params(array('session' => $date->sessionid, 'userid' => $date->userid));
                    $output .= html_writer::tag('td', html_writer::link($bookinghistoryurl, get_string('status:' . facetoface_get_status($date->status), 'block_facetoface')));
                }
            }

            $output .= html_writer::end_tag('tr');
        }
        $output .= html_writer::end_tag('table');

        return $output;
    }
}

?>
