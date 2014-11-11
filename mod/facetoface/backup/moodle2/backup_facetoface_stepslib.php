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

  //------------------------------------------------------------------
  // This is the "graphical" structure of the Facet-to-face module:
  //
  //                          facetoface_notifications
  //               +-------(CL, pk->id, fk->facetofaceid)
  //               |
  //               |
  //          facetoface                  facetoface_sessions
  //         (CL, pk->id)-------------(CL, pk->id, fk->facetoface)
  //                                          |  |  |  |
  //                                          |  |  |  |
  //            facetoface_signups------------+  |  |  |
  //        (UL, pk->id, fk->sessionid)          |  |  |
  //                     |                       |  |  |
  //         facetoface_signups_status           |  |  |
  //         (UL, pk->id, fk->signupid)          |  |  |
  //                                             |  |  |
  //                                             |  |  |
  //         facetoface_session_roles------------+  |  |
  //        (UL, pk->id, fk->sessionid)             |  |
  //                                                |  |
  //                                                |  |
  //     facetoface_session_field                   |  |
  //          (SL, pk->id)  |                       |  |
  //                        |                       |  |
  //             facetoface_session_data------------+  |
  //    (CL, pk->id, fk->sessionid, fk->fieldid)       |
  //                                                   |
  //                                    facetoface_sessions_dates
  //                                    (CL, pk->id, fk->session)
  //
  // Meaning: pk->primary key field of the table
  //          fk->foreign key to link with parent
  //          SL->system level info
  //          CL->course level info
  //          UL->user level info
  //
//------------------------------------------------------------------

class backup_facetoface_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');


        // Define each element separated
        $facetoface = new backup_nested_element('facetoface', array('id'), array(
            'name', 'intro', 'introformat', 'thirdparty', 'thirdpartywaitlist', 'display',
            'timecreated', 'timemodified', 'shortname', 'showoncalendar', 'approvalreqd', 'usercalentry',
            'multiplesessions', 'completionstatusrequired', 'managerreserve', 'maxmanagerreserves', 'reservecanceldays',
            'reservedays', 'selfapprovaltandc', 'declareinterest', 'interestonlyiffull', 'selectpositiononsignup',
            'forceselectposition'));
        $notifications = new backup_nested_element('notifications');

        $notification = new backup_nested_element('notification', array('id'), array(
            'type', 'conditiontype', 'scheduleunit', 'scheduleamount', 'scheduletime', 'ccmanager', 'managerprefix',
            'title', 'body', 'booked', 'waitlisted', 'cancelled', 'courseid', 'facetofaceid', 'status',
            'issent', 'timemodified', 'usermodified'));

        $sessions = new backup_nested_element('sessions');

        $session = new backup_nested_element('session', array('id'), array(
            'facetoface', 'capacity', 'allowoverbook', 'details', 'datetimeknown', 'duration', 'normalcost',
            'discountcost', 'roomid', 'room_name', 'room_building', 'room_address', 'room_custom', 'timecreated',
            'timemodified', 'selfapproval', 'mincapacity', 'cutoff', 'waitlisteveryone'));

        $signups = new backup_nested_element('signups');

        $signup = new backup_nested_element('signup', array('id'), array(
            'sessionid', 'userid', 'mailedreminder', 'discountcode', 'notificationtype', 'archived', 'bookedby',
            'positionid', 'positiontype', 'positionassignmentid'));

        $signups_status = new backup_nested_element('signups_status');

        $signup_status = new backup_nested_element('signup_status', array('id'), array(
            'signupid', 'statuscode', 'superceded', 'grade', 'note', 'advice', 'createdby', 'timecreated'));

        $session_roles = new backup_nested_element('session_roles');

        $session_role = new backup_nested_element('session_role', array('id'), array(
            'sessionid', 'roleid', 'userid'));

        $customfields = new backup_nested_element('custom_fields');

        $customfield = new backup_nested_element('custom_field', array('id'), array(
            'field_name', 'field_type', 'field_data'));

        $sessions_dates = new backup_nested_element('sessions_dates');

        $sessions_date = new backup_nested_element('sessions_date', array('id'), array(
            'sessionid', 'sessiontimezone', 'timestart', 'timefinish'));

        $interests = new backup_nested_element('interests');

        $interest = new backup_nested_element('interest', array('id'), array(
            'facetoface', 'userid', 'timedeclared', 'reason'));

        // Build the tree
        $facetoface->add_child($notifications);
        $notifications->add_child($notification);

        $facetoface->add_child($sessions);
        $sessions->add_child($session);

        $session->add_child($signups);
        $signups->add_child($signup);

        $signup->add_child($signups_status);
        $signups_status->add_child($signup_status);

        $session->add_child($session_roles);
        $session_roles->add_child($session_role);

        $session->add_child($customfields);
        $customfields->add_child($customfield);

        $session->add_child($sessions_dates);
        $sessions_dates->add_child($sessions_date);

        $facetoface->add_child($interests);
        $interests->add_child($interest);

        // Define sources
        $facetoface->set_source_table('facetoface', array('id' => backup::VAR_ACTIVITYID));

        $notification->set_source_table('facetoface_notification', array('facetofaceid' => backup::VAR_PARENTID));

        $session->set_source_sql('SELECT s.id, s.facetoface, s.capacity, s.allowoverbook, s.details, s.datetimeknown,
                                         s.duration, s.normalcost, s.discountcost, s.roomid, r.name AS room_name,
                                         r.building AS room_building, r.custom AS room_custom, r.address AS room_address,
                                         s.timecreated, s.timemodified, s.usermodified, s.selfapproval, s.mincapacity, s.cutoff
                                        FROM {facetoface_sessions} s
                                        LEFT JOIN {facetoface_room} r ON s.roomid = r.id
                                       WHERE s.facetoface = ?', array(backup::VAR_PARENTID));

        $sessions_date->set_source_table('facetoface_sessions_dates', array('sessionid' => backup::VAR_PARENTID));

        if ($userinfo) {
            $signup->set_source_table('facetoface_signups', array('sessionid' => backup::VAR_PARENTID));

            $signup_status->set_source_table('facetoface_signups_status', array('signupid' => backup::VAR_PARENTID));

            $session_role->set_source_table('facetoface_session_roles', array('sessionid' => backup::VAR_PARENTID));

            $interest->set_source_table('facetoface_interest', array('facetoface' => backup::VAR_PARENTID));
        }

        $customfield->set_source_sql('SELECT d.id, f.shortname AS field_name, f.type AS field_type, d.data AS field_data
                                        FROM {facetoface_session_field} f
                                        JOIN {facetoface_session_data} d ON d.fieldid = f.id
                                       WHERE d.sessionid = ?', array(backup::VAR_PARENTID));

        // Define id annotations
        $signup->annotate_ids('user', 'userid');

        $signup->annotate_ids('user', 'bookedby');

        $session_role->annotate_ids('role', 'roleid');

        $session_role->annotate_ids('user', 'userid');

        $interest->annotate_ids('user', 'userid');

        // Define file annotations
        // None for F2F

        // Return the root element (facetoface), wrapped into standard activity structure
        return $this->prepare_activity_structure($facetoface);
    }
}
