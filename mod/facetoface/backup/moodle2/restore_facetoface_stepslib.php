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
 * @package mod_facetoface
 */

/**
 * Structure step to restore one facetoface activity
 */
class restore_facetoface_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('facetoface', '/activity/facetoface');
        $paths[] = new restore_path_element('facetoface_notification', '/activity/facetoface/notifications/notification');
        $paths[] = new restore_path_element('facetoface_session', '/activity/facetoface/sessions/session');
        $paths[] = new restore_path_element('facetoface_sessions_dates', '/activity/facetoface/sessions/session/sessions_dates/sessions_date');
        $paths[] = new restore_path_element('facetoface_session_custom_fields', '/activity/facetoface/sessions/session/custom_fields/custom_field');
        if ($userinfo) {
            $paths[] = new restore_path_element('facetoface_signup', '/activity/facetoface/sessions/session/signups/signup');
            $paths[] = new restore_path_element('facetoface_signups_status', '/activity/facetoface/sessions/session/signups/signup/signups_status/signup_status');
            $paths[] = new restore_path_element('facetoface_session_roles', '/activity/facetoface/sessions/session/session_roles/session_role');
            $paths[] = new restore_path_element('facetoface_interest', '/activity/facetoface/interests/interest');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_facetoface($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // insert the facetoface record
        $newitemid = $DB->insert_record('facetoface', $data);
        $this->apply_activity_instance($newitemid);
    }


    protected function process_facetoface_notification($data) {
        global $DB, $USER;

        $data = (object)$data;
        $oldid = $data->id;

        $data->facetofaceid = $this->get_new_parentid('facetoface');
        $data->course = $this->get_courseid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->usermodified = isset($USER->id) ? $USER->id : get_admin()->id;

        // Insert the notification record.
        $newitemid = $DB->insert_record('facetoface_notification', $data);
    }


    protected function process_facetoface_session($data) {
        global $DB, $USER;

        $data = (object)$data;
        $oldid = $data->id;

        $data->facetoface = $this->get_new_parentid('facetoface');

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->usermodified = isset($USER->id) ? $USER->id : get_admin()->id;
        // Check if the session has any predefined or custom room.
        if ((int)$data->roomid > 0) {
            // If it is a custom room, create a new record.
            if ((int)$data->room_custom == 1) {
                $data->roomid = $this->create_facetoface_room($data);
            } else {
                // Check if a predefined room exists.
                $rooms = $DB->get_records('facetoface_room', array('name' => $data->room_name,
                    'building' => $data->room_building, 'address' => $data->room_address, 'custom' => 0), '', 'id');
                if (count($rooms) > 0) {
                    if (count($rooms) > 1) {
                        debugging("Room [{$data->room_name}, {$data->room_building}, {$data->room_address}] matches more ".
                            "than one predefined room and we can't identify which - arbitrarily selecting one of them");
                    }
                    $data->roomid = reset($rooms)->id;
                } else {
                    // Create a new predefined room record.
                    debugging("Room [{$data->room_name}, {$data->room_building}, {$data->room_address}] ".
                        "in face to face session does not exist - creating as predefined room");
                    $data->roomid = $this->create_facetoface_room($data);
                }
            }
        } else {
            // F2F session has no room.
            $data->roomid = 0;
        }

        // insert the entry record
        $newitemid = $DB->insert_record('facetoface_sessions', $data);
        $this->set_mapping('facetoface_session', $oldid, $newitemid, true); // childs and files by itemname
    }

    private function create_facetoface_room($data) {
        global $DB;
        $customroom = new stdClass();
        $customroom->name = $data->room_name;
        $customroom->building = $data->room_building;
        $customroom->address = $data->room_address;
        $customroom->capacity = $data->capacity;
        $customroom->custom = (int)$data->room_custom;
        $customroom->timecreated = $data->timecreated;
        $customroom->timemodified = $data->timemodified;
        $roomid = $DB->insert_record('facetoface_room', $customroom);
        return $roomid;
    }

    protected function process_facetoface_signup($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('facetoface_session');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!empty($data->bookedby)) {
            $data->bookedby = $this->get_mappingid('user', $data->bookedby);
        }

        // insert the entry record
        $newitemid = $DB->insert_record('facetoface_signups', $data);
        $this->set_mapping('facetoface_signup', $oldid, $newitemid, true); // childs and files by itemname
    }


    protected function process_facetoface_signups_status($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->signupid = $this->get_new_parentid('facetoface_signup');

        $data->timecreated = $this->apply_date_offset($data->timecreated);

        // insert the entry record
        $newitemid = $DB->insert_record('facetoface_signups_status', $data);
    }


    protected function process_facetoface_session_roles($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('facetoface_session');
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->roleid = $this->get_mappingid('role', $data->roleid);

        // insert the entry record
        $newitemid = $DB->insert_record('facetoface_session_roles', $data);
    }


    protected function process_facetoface_session_custom_fields($data) {
        global $DB;

        $data = (object)$data;

        if ($data->field_data) {
            if (!$field = $DB->get_record('facetoface_session_field', array('shortname' => $data->field_name))) {
                debugging("Custom field [{$data->field_name}] in face to face session cannot be restored " .
                        "because it doesn't exist in the target database");
            } else if ($field->type != $data->field_type) {
                debugging("Custom field [{$data->field_name}] in face to face session cannot be restored " .
                        "because there is a data type mismatch - " .
                        "target type = [{$field->type}] <> restore type = [{$data->field_type}]");
            } else {
                if ($customfield = $DB->get_record('facetoface_session_data',
                        array('fieldid' => $field->id, 'sessionid' => $this->get_new_parentid('facetoface_session')))) {
                    $customfield->data = $data->field_data;
                    $DB->update_record('facetoface_session_data', $customfield);
                } else {
                    $customfield = new stdClass();
                    $customfield->sessionid = $this->get_new_parentid('facetoface_session');
                    $customfield->fieldid = $field->id;
                    $customfield->data    = $data->field_data;
                    $DB->insert_record('facetoface_session_data', $customfield);
                }
            }
        }
    }


    protected function process_facetoface_session_field($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // insert the entry record
        $newitemid = $DB->insert_record('facetoface_session_field', $data);
    }


    protected function process_facetoface_sessions_dates($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_new_parentid('facetoface_session');

        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timefinish = $this->apply_date_offset($data->timefinish);

        // insert the entry record
        $newitemid = $DB->insert_record('facetoface_sessions_dates', $data);
    }


    protected function process_facetoface_interest($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->facetoface = $this->get_new_parentid('facetoface');
        $data->userid = $this->get_mappingid('user', $data->userid);

        // Insert the entry record.
        $newitemid = $DB->insert_record('facetoface_interest', $data);
    }

    protected function after_execute() {
        // Face-to-face doesn't have any related files
        //
        // Add facetoface related files, no need to match by itemname (just internally handled context)
    }
}
