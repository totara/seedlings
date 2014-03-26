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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage facetoface
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');
require_once($CFG->dirroot . '/totara/core/dialogs/dialog_content.class.php');

$sessionid = required_param('sessionid', PARAM_INT);
$timeslots = required_param('timeslots', PARAM_TEXT);
$timeslotsarray = json_decode($timeslots);

require_login();
$PAGE->set_context(context_system::instance());

if (empty($timeslotsarray)) {
    print_error('notimeslotsspecified', 'facetoface');
}

// Setup / loading data
// Get all rooms
$sql = "SELECT
            r.*
        FROM
            {facetoface_room} r
        WHERE
            r.custom = 0
        ORDER BY
            r.name,
            r.building,
            r.address";

if ($rooms = $DB->get_records_sql($sql)) {
    $allrooms = array();
    foreach ($rooms as $room) {
        $roomobject = new stdClass();
        $roomobject->id = $room->id;
        $roomobject->fullname = get_string('predefinedroom', 'facetoface', $room);
        $allrooms[$room->id] = $roomobject;
    }
    $availablerooms = facetoface_get_available_rooms($timeslotsarray, 'id', array($sessionid));
    if ($unavailablerooms = array_diff(array_keys($allrooms), array_keys($availablerooms))) {
        $unavailablerooms = array_combine($unavailablerooms, $unavailablerooms);  // make array keys and values the same
        //add alreadybooked string to fullname
        foreach ($unavailablerooms as $key => $unavailable) {
            if (isset($allrooms[$key])) {
                $allrooms[$key]->fullname .= get_string('roomalreadybooked', 'facetoface');
            }
        }
    }
} else {
    $allrooms = array();
    $unavailablerooms = array();
}

// Display page
$dialog = new totara_dialog_content();
$dialog->searchtype = 'facetoface_room';
$dialog->items = $allrooms;
$dialog->disabled_items = $unavailablerooms;
$dialog->lang_file = 'facetoface';
$dialog->customdata['timeslots'] = $timeslots;
$dialog->customdata['sessionid'] = $sessionid;
$dialog->string_nothingtodisplay = 'error:nopredefinedrooms';

echo $dialog->generate_markup();
