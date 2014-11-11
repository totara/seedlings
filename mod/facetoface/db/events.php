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
 * @author David Curry <david.curry@totaralms.com>
 * @package mod_facetoface
 */

/**
 * this file should be used for all facetoface event definitions and handers.
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

$observers = array(
    array(
        'eventname' => '\core\event\user_deleted',
        'callback' => 'facetoface_event_handler::user_deleted',
        'includefile' => '/mod/facetoface/lib.php',
    ),
    array(
        'eventname' => '\totara_core\event\user_suspended',
        'callback' => 'facetoface_event_handler::user_suspended',
        'includefile' => '/mod/facetoface/lib.php',
    ),
    array(
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => 'facetoface_event_handler::user_unenrolled',
        'includefile' => '/mod/facetoface/lib.php',
    ),
);
