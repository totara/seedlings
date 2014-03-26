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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Francois Marier <francois@catalyst.net.nz>
 * @package modules
 * @subpackage facetoface
 */
defined('MOODLE_INTERNAL') || die();


/**
 * Return list of marked submissions that have not been mailed out for currently enrolled students
 */
function facetoface_get_unmailed_reminders() {
    global $CFG, $DB;

    $submissions = $DB->get_records_sql("
        SELECT
            su.*,
            f.course,
            f.id as facetofaceid,
            f.name as facetofacename,
            se.duration,
            se.normalcost,
            se.discountcost,
            se.details,
            se.datetimeknown
        FROM
            {facetoface_signups} su
        INNER JOIN
            {facetoface_signups_status} sus
         ON su.id = sus.signupid
        AND sus.superceded = 0
        AND sus.statuscode = ?
        JOIN
            {facetoface_sessions} se
         ON su.sessionid = se.id
        JOIN
            {facetoface} f
         ON se.facetoface = f.id
        WHERE
            su.mailedreminder = 0
        AND se.datetimeknown = 1
    ", array(MDL_F2F_STATUS_BOOKED));

    if ($submissions) {
        foreach ($submissions as $key => $value) {
            $submissions[$key]->duration = facetoface_minutes_to_hours($submissions[$key]->duration);
            $submissions[$key]->sessiondates = facetoface_get_session_dates($value->sessionid);
        }
    }

    return $submissions;
}


/**
 * Returns the ICAL data for a facetoface meeting.
 *
 * @param integer $method The method, @see {{MDL_F2F_INVITE}}
 * @return stdClass Object that contains a filename in dataroot directory and ical template
 */
function facetoface_get_ical_attachment($method, $facetoface, $session, $user) {
    global $CFG, $DB;

    // Get user object if only id is given
    $user = (is_object($user) ? $user : $DB->get_record('user', array('id' => $user)));

    // First, generate all the VEVENT blocks
    $VEVENTS = '';
    foreach ($session->sessiondates as $date) {
        // Date that this representation of the calendar information was created -
        // we use the time the session was created
        // http://www.kanzaki.com/docs/ical/dtstamp.html
        $DTSTAMP = facetoface_ical_generate_timestamp($session->timecreated);

        // UIDs should be globally unique
        $urlbits = parse_url($CFG->wwwroot);
        $sql = "SELECT COUNT(*)
            FROM {facetoface_signups} su
            INNER JOIN {facetoface_signups_status} sus ON su.id = sus.signupid
            WHERE su.userid = ?
                AND su.sessionid = ?
                AND sus.superceded = 1
                AND sus.statuscode = ? ";
        $params = array($user->id, $session->id, MDL_F2F_STATUS_USER_CANCELLED);
        $UID =
            $DTSTAMP .
            '-' . substr(md5($CFG->siteidentifier . $session->id . $date->id), -8) .   // Unique identifier, salted with site identifier
            '-' . $DB->count_records_sql($sql, $params) .                              // New UID if this is a re-signup ;)
            '@' . $urlbits['host'];                                                    // Hostname for this moodle installation

        $DTSTART = facetoface_ical_generate_timestamp($date->timestart);
        $DTEND   = facetoface_ical_generate_timestamp($date->timefinish);

        // FIXME: currently we are not sending updates if the times of the
        // sesion are changed. This is not ideal!
        $SEQUENCE = ($method & MDL_F2F_CANCEL) ? 1 : 0;

        $SUMMARY     = str_replace("\\n", "\\n ", facetoface_ical_escape($facetoface->name, true));
        $icaldescription = get_string('icaldescription', 'facetoface', $facetoface);
        if (!empty($session->details)) {
            $icaldescription .= $session->details;
        }
        $DESCRIPTION = facetoface_ical_escape($icaldescription, true);

        // Get the location data from custom fields if they exist
        $room = facetoface_get_session_room($session->id);
        $locationstring = '';
        if (!empty($room->name)) {
            $locationstring .= $room->name;
        }
        if (!empty($room->building)) {
            if (!empty($locationstring)) {
                $locationstring .= "\n";
            }
            $locationstring .= $room->building;
        }
        if (!empty($room->address)) {
            if (!empty($locationstring)) {
                $locationstring .= "\n";
            }
            $locationstring .= $room->address;
        }

        // NOTE: Newlines are meant to be encoded with the literal sequence
        // '\n'. But evolution presents a single line text field for location,
        // and shows the newlines as [0x0A] junk. So we switch it for commas
        // here. Remember commas need to be escaped too.
        $LOCATION    = str_replace('\n', '\, ', facetoface_ical_escape($locationstring));

        $ORGANISEREMAIL = get_config(NULL, 'facetoface_fromaddress');

        $ROLE = 'REQ-PARTICIPANT';
        $CANCELSTATUS = '';
        if ($method & MDL_F2F_CANCEL) {
            $ROLE = 'NON-PARTICIPANT';
            $CANCELSTATUS = "\nSTATUS:CANCELLED";
        }

        $icalmethod = ($method & MDL_F2F_INVITE) ? 'REQUEST' : 'CANCEL';

        // FIXME: if the user has input their name in another language, we need
        // to set the LANGUAGE property parameter here
        $USERNAME = fullname($user);
        $MAILTO   = $user->email;

        $VEVENTS .= "BEGIN:VEVENT\r\n";
        $VEVENTS .= "ORGANIZER;CN={$ORGANISEREMAIL}:MAILTO:{$ORGANISEREMAIL}\r\n";
        $VEVENTS .= "DTSTART:{$DTSTART}\r\n";
        $VEVENTS .= "DTEND:{$DTEND}\r\n";
        $VEVENTS .= "LOCATION:{$LOCATION}\r\n";
        $VEVENTS .= "TRANSP:OPAQUE{$CANCELSTATUS}\r\n";
        $VEVENTS .= "SEQUENCE:{$SEQUENCE}\r\n";
        $VEVENTS .= "UID:{$UID}\r\n";
        $VEVENTS .= "DTSTAMP:{$DTSTAMP}\r\n";
        $VEVENTS .= "DESCRIPTION:{$DESCRIPTION}\r\n";
        $VEVENTS .= "SUMMARY:{$SUMMARY}\r\n";
        $VEVENTS .= "PRIORITY:5\r\n";
        $VEVENTS .= "CLASS:PUBLIC\r\n";
        $VEVENTS .= "ATTENDEE;CUTYPE=INDIVIDUAL;ROLE={$ROLE};PARTSTAT=NEEDS-ACTION;\r\n";
        $VEVENTS .= " RSVP=FALSE;CN={$USERNAME};LANGUAGE=en:MAILTO:{$MAILTO}\r\n";
        $VEVENTS .= "END:VEVENT\r\n";
    }

    $template  = "BEGIN:VCALENDAR\r\n";
    $template .= "VERSION:2.0\r\n";
    $template .= "PRODID:-//Moodle//NONSGML Facetoface//EN\r\n";
    $template .= "METHOD:{$icalmethod}\r\n";
    $template .= "{$VEVENTS}";
    $template .= "END:VCALENDAR\r\n";

    $tempfilename = md5($template);
    $tempfilepathname = $CFG->dataroot . '/' . $tempfilename;
    file_put_contents($tempfilepathname, $template);

    $ical = new stdClass();
    $ical->file = $tempfilename;
    $ical->content = $template;
    return $ical;
}


/**
 * Used by facetoface_get_ical_attachment
 * @seconds string signed number, e.g. -343242 or +343242
 * Convert no. of seconds to hhmmss format
 */
function facetoface_format_secs_to_his($seconds) {
    if ( '-' == substr($seconds, 0, 1)) {
        $prefix  = '-';
        $seconds = substr($seconds, 1);
    } else if ( '+' == substr($seconds, 0, 1)) {
        $prefix  = '+';
        $seconds = substr($seconds, 1);
    } else {
        $prefix  = '+';
    }

    $output = '';
    $hour = (int)floor($seconds/3600);
    if (10 > $hour) {
      $hour  = '0'.$hour;
    }

    $seconds = $seconds % 3600;

    $min = (int)floor($seconds/60);
    if (10 > $min) {
      $min = '0'.$min;
    }

    $output  = $hour.$min;
    $seconds = $seconds % 60;
    if (0 < $seconds) {
        if (9 < $seconds) {
            $output .= $seconds;
        } else {
            $output .= '0'.$seconds;
        }
    }

    return $prefix.$output;
}


/**
 * Generates a timestamp for Ical
 *
 */
function facetoface_ical_generate_timestamp($timestamp) {
    return gmdate('Ymd', $timestamp) . 'T' . gmdate('His', $timestamp) . 'Z';
}


/**
 * Escapes data of the text datatype in ICAL documents.
 *
 * See RFC2445 or http://www.kanzaki.com/docs/ical/text.html or a more readable definition
 */
function facetoface_ical_escape($text, $converthtml=false) {
    if (empty($text)) {
        return '';
    }

    if ($converthtml) {
        $text = html_to_text($text, 0);
    }

    $text = str_replace(
        array('\\',   "\n", ';',  ',', '"'),
        array('\\\\', '\n', '\;', '\,', '\"'),
        $text
    );

    // Text should be wordwrapped at 75 octets, and there should be one
    // whitespace after the newline that does the wrapping
    $text = wordwrap($text, 74, " \n ", true);

    return $text;
}
