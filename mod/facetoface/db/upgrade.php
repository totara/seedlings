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

// This file keeps track of upgrades to
// the facetoface module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

/**
 *
 * Sends message to administrator listing all updated
 * duplicate custom fields
 * @param array $data
 */
function facetoface_send_admin_upgrade_msg($data) {
    global $SITE;
    //No data - no need to send email
    if (empty($data)) {
        return;
    }

    $table = new html_table();
    $table->head = array('Custom field ID',
                         'Custom field original shortname',
                         'Custom field new shortname');
    $table->data = $data;
    $table->align = array ('center', 'center', 'center');

    $title    = "$SITE->fullname: Face to Face upgrade info";
    $note = 'During the last site upgrade the face-to-face module has been modified. It now
requires session custom fields to have unique shortnames. Since some of your
custom fields had duplicate shortnames, they have been renamed to remove
duplicates (see table below). This could impact on your email messages if you
reference those custom fields in the message templates.';
    $message  = html_writer::start_tag('html') . html_writer::start_tag('head') . html_writer::tag('title', $title) . html_writer::end_tag('head');
    $message .= html_writer::start_tag('body') . html_writer::tag('p', $note). html_writer::table($table,true) . html_writer::end_tag('body') . html_writer::end_tag('html');

    $admin = get_admin();

    email_to_user($admin,
                  $admin,
                  $title,
                  '',
                  $message);

}

function xmldb_facetoface_upgrade($oldversion=0) {
    global $CFG, $USER, $DB, $OUTPUT;

    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    require_once($CFG->dirroot . '/mod/facetoface/lib.php');

    $result = true;

    if ($result && $oldversion < 2008050500) {
        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('thirdpartywaitlist');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'thirdparty');
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2008061000) {
        $table = new xmldb_table('facetoface_submissions');
        $field = new xmldb_field('notificationtype');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timemodified');
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2008080100) {
        echo $OUTPUT->notification(get_string('upgradeprocessinggrades', 'facetoface'), 'notifysuccess');
        require_once $CFG->dirroot.'/mod/facetoface/lib.php';

        $transaction = $DB->start_delegated_transaction();
        $DB->debug = false; // too much debug output

        // Migrate the grades to the gradebook
        $sql = "SELECT f.id, f.name, f.course, s.grade, s.timegraded, s.userid,
            cm.idnumber as cmidnumber
            FROM {facetoface_submissions} s
            JOIN {facetoface} f ON s.facetoface = f.id
            JOIN {course_modules} cm ON cm.instance = f.id
            JOIN {modules} m ON m.id = cm.module
            WHERE m.name='facetoface'";
        if ($rs = $DB->get_recordset_sql($sql)) {
            foreach ($rs as $facetoface) {
                $grade = new stdclass();
                $grade->userid = $facetoface->userid;
                $grade->rawgrade = $facetoface->grade;
                $grade->rawgrademin = 0;
                $grade->rawgrademax = 100;
                $grade->timecreated = $facetoface->timegraded;
                $grade->timemodified = $facetoface->timegraded;

                $result = $result && (GRADE_UPDATE_OK == facetoface_grade_item_update($facetoface, $grade));
            }
            $rs->close();
        }
        $DB->debug = true;

        // Remove the grade and timegraded fields from facetoface_submissions
        if ($result) {
            $table = new xmldb_table('facetoface_submissions');
            $field1 = new xmldb_field('grade');
            $field2 = new xmldb_field('timegraded');
            $result = $result && $dbman->drop_field($table, $field1, false, true);
            $result = $result && $dbman->drop_field($table, $field2, false, true);
        }

        $transaction->allow_commit();
    }

    if ($result && $oldversion < 2008090800) {

        // Define field timemodified to be added to facetoface_submissions
        $table = new xmldb_table('facetoface_submissions');
        $field = new xmldb_field('timecancelled');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, 0, 'timemodified');

        // Launch add field
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2009111300) {
        // New fields necessary for the training calendar
        $table = new xmldb_table('facetoface');
        $field1 = new xmldb_field('shortname');
        $field1->set_attributes(XMLDB_TYPE_CHAR, '32', null, null, null, null, 'timemodified');
        $result = $result && $dbman->add_field($table, $field1);

        $field2 = new xmldb_field('description');
        $field2->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'shortname');
        $result = $result && $dbman->add_field($table, $field2);

        $field3 = new xmldb_field('showoncalendar');
        $field3->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'description');
        $result = $result && $dbman->add_field($table, $field3);
    }

    if ($result && $oldversion < 2009111600) {

        $table1 = new xmldb_table('facetoface_session_field');
        $table1->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table1->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table1->add_field('shortname', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table1->add_field('type', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table1->add_field('possiblevalues', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);
        $table1->add_field('required', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table1->add_field('defaultvalue', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table1->add_field('isfilter', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table1->add_field('showinsummary', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table1->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && $dbman->create_table($table1);

        $table2 = new xmldb_table('facetoface_session_data');
        $table2->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table2->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('data', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table2->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && $dbman->create_table($table2);
    }

    if ($result && $oldversion < 2009111900) {
        // Remove unused field
        $table = new xmldb_table('facetoface_sessions');
        $field = new xmldb_field('closed');
        $result = $result && $dbman->drop_field($table, $field);
    }

    // Migration of old Location, Venue and Room fields
    if ($result && $oldversion < 2009112300) {
        // Create three new custom fields
        $newfield1 = new stdClass();
        $newfield1->name = 'Location';
        $newfield1->shortname = 'location';
        $newfield1->type = 0; // free text
        $newfield1->required = 1;
        if (!$locationfieldid = $DB->insert_record('facetoface_session_field', $newfield1)) {
            $result = false;
        }

        $newfield2 = new stdClass();
        $newfield2->name = 'Venue';
        $newfield2->shortname = 'venue';
        $newfield2->type = 0; // free text
        $newfield2->required = 1;
        if (!$venuefieldid = $DB->insert_record('facetoface_session_field', $newfield2)) {
            $result = false;
        }

        $newfield3 = new stdClass();
        $newfield3->name = 'Room';
        $newfield3->shortname = 'room';
        $newfield3->type = 0; // free text
        $newfield3->required = 1;
        $newfield3->showinsummary = 0;
        if (!$roomfieldid = $DB->insert_record('facetoface_session_field', $newfield3)) {
            $result = false;
        }

        // Migrate data into the new fields
        $olddebug = $DB->debug;
        $DB->debug = false; // too much debug output

        if ($rs = $DB->get_recordset('facetoface_sessions', array(), '', 'id, location, venue, room')) {
            foreach ($rs as $session) {
                $locationdata = new stdClass();
                $locationdata->sessionid = $session->id;
                $locationdata->fieldid = $locationfieldid;
                $locationdata->data = $session->location;
                $result = $result && $DB->insert_record('facetoface_session_data', $locationdata);

                $venuedata = new stdClass();
                $venuedata->sessionid = $session->id;
                $venuedata->fieldid = $venuefieldid;
                $venuedata->data = $session->venue;
                $result = $result && $DB->insert_record('facetoface_session_data', $venuedata);

                $roomdata = new stdClass();
                $roomdata->sessionid = $session->id;
                $roomdata->fieldid = $roomfieldid;
                $roomdata->data = $session->room;
                $result = $result && $DB->insert_record('facetoface_session_data', $roomdata);
            }
            $rs->close();
        }

        $DB->debug = $olddebug;

        // Drop the old fields
        $table = new xmldb_table('facetoface_sessions');
        $oldfield1 = new xmldb_field('location');
        $result = $result && $dbman->drop_field($table, $oldfield1);
        $oldfield2 = new xmldb_field('venue');
        $result = $result && $dbman->drop_field($table, $oldfield2);
        $oldfield3 = new xmldb_field('room');
        $result = $result && $dbman->drop_field($table, $oldfield3);
    }

    // Migration of old Location, Venue and Room placeholders in email templates
    if ($result && $oldversion < 2009112400) {
        $transaction = $DB->start_delegated_transaction();

        $olddebug = $DB->debug;
        $DB->debug = false; // too much debug output

        $templatedfields = array('confirmationsubject', 'confirmationinstrmngr', 'confirmationmessage',
            'cancellationsubject', 'cancellationinstrmngr', 'cancellationmessage',
            'remindersubject', 'reminderinstrmngr', 'remindermessage',
            'waitlistedsubject', 'waitlistedmessage');

        if ($rs = $DB->get_recordset('facetoface', array(), '', 'id, ' . implode(', ', $templatedfields))) {
            foreach ($rs as $activity) {
                $todb = new stdClass();
                $todb->id = $activity->id;

                foreach ($templatedfields as $fieldname) {
                    $s = $activity->$fieldname;
                    $s = str_replace('[location]', '[session:location]', $s);
                    $s = str_replace('[venue]', '[session:venue]', $s);
                    $s = str_replace('[room]', '[session:room]', $s);
                    $todb->$fieldname = $s;
                }

                $result = $result && $DB->update_record('facetoface', $todb);
            }
            $rs->close();
        }

        $DB->debug = $olddebug;

        $transaction->allow_commit();
    }

    if ($result && $oldversion < 2009120900) {
        // Create Calendar events for all existing Face-to-face sessions
        try {
            $transaction = $DB->start_delegated_transaction();

            if ($records = $DB->get_records('facetoface_sessions', '', '', '', 'id, facetoface')) {
                // Remove all exising site-wide events (there shouldn't be any)
                foreach ($records as $record) {
                    if (!facetoface_remove_session_from_calendar($record, SITEID)) {
                        $result = false;
                        throw new Exception('Could not remove session from site calendar');
                        break;
                    }
                }

                // Add new site-wide events
                foreach ($records as $record) {
                    $session = facetoface_get_session($record->id);
                    $facetoface = $DB->get_record('facetoface', 'id', $record->facetoface);

                    if (!facetoface_add_session_to_calendar($session, $facetoface, 'site')) {
                        $result = false;
                        throw new Exception('Could not add session to site calendar');
                        break;
                    }
                }
            }
            $transaction->allow_commit();
        } catch (Exception $e) {
            $transaction->rollback($e);
        }

    }

    if ($result && $oldversion < 2009122901) {

    /// Create table facetoface_session_roles
        $table = new xmldb_table('facetoface_session_roles');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('roleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('sessionid', XMLDB_KEY_FOREIGN, array('sessionid'), 'facetoface_sessions', array('id'));
        $result = $result && $dbman->create_table($table);

    /// Create table facetoface_signups
        $table = new xmldb_table('facetoface_signups');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mailedreminder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('discountcode', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('notificationtype', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('sessionid', XMLDB_KEY_FOREIGN, array('sessionid'), 'facetoface_sessions', array('id'));
        $result = $result && $dbman->create_table($table);

    /// Create table facetoface_signups_status
        $table = new xmldb_table('facetoface_signups_status');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('signupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('statuscode', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('superceded', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('grade', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, '0');
        $table->add_field('note', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('signupid', XMLDB_KEY_FOREIGN, array('signupid'), 'facetoface_signups', array('id'));
        $result = $result && $dbman->create_table($table);

    /// Migrate submissions to signups
        $table = new xmldb_table('facetoface_submissions');
        if ($dbman->table_exists($table)) {
            require_once $CFG->dirroot.'/mod/facetoface/lib.php';

            $transaction = $DB->start_delegated_transaction();

            // Get all submissions and loop through
            $rs = $DB->get_recordset('facetoface_submissions');

            foreach ($rs as $submission) {

                // Insert signup
                $signup = new stdClass();
                $signup->sessionid = $submission->sessionid;
                $signup->userid = $submission->userid;
                $signup->mailedreminder = $submission->mailedreminder;
                $signup->discountcode = $submission->discountcode;
                $signup->notificationtype = $submission->notificationtype;

                $id = $DB->insert_record('facetoface_signups', $signup);

                $signup->id = $id;

                // Check facetoface still exists (some of them are missing)
                // Also, we need the course id so we can load the grade
                $facetoface = $DB->get_record('facetoface', 'id', $submission->facetoface);
                if (!$facetoface) {
                    // If facetoface delete, ignore as it's of no use to us now
                    mtrace('Could not find facetoface instance '.$submission->facetoface);
                    continue;
                }

                // Get grade
                $grade = facetoface_get_grade($submission->userid, $facetoface->course, $facetoface->id);

                // Create initial "booked" signup status
                $status = new stdClass();
                $status->signupid = $signup->id;
                $status->statuscode = MDL_F2F_STATUS_BOOKED;
                $status->superceded = ($grade->grade > 0 || $submission->timecancelled) ? 1 : 0;
                $status->createdby = $USER->id;
                $status->timecreated = $submission->timecreated;
                $status->mailed = 0;

                $DB->insert_record('facetoface_signups_status', $status);

                // Create attended signup status
                if ($grade->grade > 0) {
                    $status->statuscode = MDL_F2F_STATUS_FULLY_ATTENDED;
                    $status->grade = $grade->grade;
                    $status->timecreated = $grade->dategraded;
                    $status->superceded = $submission->timecancelled ? 1 : 0;

                    $DB->insert_record('facetoface_signups_status', $status);
                }

                // If cancelled, create status
                if ($submission->timecancelled) {
                    $status->statuscode = MDL_F2F_STATUS_USER_CANCELLED;
                    $status->timecreated = $submission->timecancelled;
                    $status->superceded = 0;

                    $DB->insert_record('facetoface_signups_status', $status);
                }
            }

            $rs->close();
            $transaction->allow_commit();

            /// Drop table facetoface_submissions
            $table = new xmldb_table('facetoface_submissions');
            $result = $result && $dbman->drop_table($table);
        }

    // New field necessary for overbooking
        $table = new xmldb_table('facetoface_sessions');
        $field1 = new xmldb_field('allowoverbook');
        $field1->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'capacity');
        $result = $result && $dbman->add_field($table, $field1);
    }

    if ($result && $oldversion < 2010012000) {
        // New field for storing recommendations/advice
        $table = new xmldb_table('facetoface_signups_status');
        $field1 = new xmldb_field('advice');
        $field1->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null);
        $result = $result && $dbman->add_field($table, $field1);
    }

    if ($result && $oldversion < 2010012001) {
        // New field for storing manager approval requirement
        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('approvalreqd');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'showoncalendar');
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2010012700) {
        // New fields for storing request emails
        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('requestsubject');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'reminderperiod');
        $result = $result && $dbman->add_field($table, $field);

        $field = new xmldb_field('requestinstrmngr');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'requestsubject');
        $result = $result && $dbman->add_field($table, $field);

        $field = new xmldb_field('requestmessage');
        $field->set_attributes(XMLDB_TYPE_TEXT, 'medium', null, null, null, null, 'requestinstrmngr');
        $result = $result && $dbman->add_field($table, $field);
    }

    if ($result && $oldversion < 2010051000) {
        // Create Calendar events for all existing Face-to-face sessions
        $transaction = $DB->start_delegated_transaction();

        if ($records = $DB->get_records('facetoface_sessions', '', '', '', 'id, facetoface')) {
            // Remove all exising site-wide events (there shouldn't be any)
            foreach ($records as $record) {
                facetoface_remove_session_from_calendar($record, SITEID);
            }

            // Add new site-wide events
            foreach ($records as $record) {
                $session = facetoface_get_session($record->id);
                $facetoface = $DB->get_record('facetoface', 'id', $record->facetoface);

                facetoface_add_session_to_calendar($session, $facetoface, 'site');
            }
        }

        $transaction->allow_commit();

        // Add tables required for site notices
        $table1 = new xmldb_table('facetoface_notice');
        $table1->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table1->add_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table1->add_field('text', XMLDB_TYPE_TEXT, 'medium', null, null, null, null);
        $table1->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $result = $result && $dbman->create_table($table1);

        $table2 = new xmldb_table('facetoface_notice_data');
        $table2->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table2->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('noticeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table2->add_field('data', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table2->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table2->add_index('facetoface_notice_date_fieldid', XMLDB_INDEX_NOTUNIQUE, array('fieldid'));
        $result = $result && $dbman->create_table($table2);
    }

    if ($result && $oldversion < 2010100400) {
        // Remove unused mailed field
        $table = new xmldb_table('facetoface_signups_status');
        $field = new xmldb_field('mailed');
        if ($dbman->field_exists($table, $field)) {
            $result = $result && $dbman->drop_field($table, $field, false, true);
        }

    }

    // 2.0 upgrade line -----------------------------------

    if ($oldversion < 2011120701) {
        // Update existing select fields to use new seperator
        $badrows = $DB->get_records_sql(
            "
                SELECT
                    *
                FROM
                    {facetoface_session_field}
                WHERE
                    possiblevalues LIKE '%;%'
                AND possiblevalues NOT LIKE '%" . CUSTOMFIELD_DELIMITER . "%'
                AND type IN (".CUSTOMFIELD_TYPE_SELECT.",".CUSTOMFIELD_TYPE_MULTISELECT.")
            "
        );

        if ($badrows) {
            $transaction = $DB->start_delegated_transaction();

            foreach ($badrows as $bad) {
                $fixedrow = new stdClass();
                $fixedrow->id = $bad->id;
                $fixedrow->possiblevalues = str_replace(';', CUSTOMFIELD_DELIMITER, $bad->possiblevalues);
                $DB->update_record('facetoface_session_field', $fixedrow);
            }

            $transaction->allow_commit();
        }

        $bad_data_rows = $DB->get_records_sql(
            "
                SELECT
                    sd.id, sd.data
                FROM
                    {facetoface_session_field} sf
                JOIN
                    {facetoface_session_data} sd
                  ON
                    sd.fieldid=sf.id
                WHERE
                    sd.data LIKE '%;%'
                AND sd.data NOT LIKE '%". CUSTOMFIELD_DELIMITER ."%'
                AND sf.type = ".CUSTOMFIELD_TYPE_MULTISELECT
        );

        if ($bad_data_rows) {
            $transaction = $DB->start_delegated_transaction();

            foreach ($bad_data_rows as $bad) {
                $fixedrow = new stdClass();
                $fixedrow->id = $bad->id;
                $fixedrow->data = str_replace(';', CUSTOMFIELD_DELIMITER, $bad->data);
                $DB->update_record('facetoface_session_data', $fixedrow);
            }

            $transaction->allow_commit();
        }

        upgrade_mod_savepoint(true, 2011120701, 'facetoface');
    }

    if ($oldversion < 2011120702) {
        $table = new xmldb_table('facetoface_session_field');
        $index = new xmldb_index('ind_session_field_unique');
        $index->set_attributes(XMLDB_INDEX_UNIQUE, array('shortname'));

        if ($dbman->table_exists($table)) {
            //do we need to check for duplicates?
            if (!$dbman->index_exists($table, $index)) {

                //check for duplicate records and make them unique
                $replacements = array();

                $transaction = $DB->start_delegated_transaction();

                $sql = 'SELECT
                            l.id,
                            l.shortname
                        FROM
                            {facetoface_session_field} l,
                            ( SELECT
                                    MIN(id) AS id,
                                    shortname
                              FROM
                                    {facetoface_session_field}
                              GROUP BY
                                    shortname
                              HAVING COUNT(*)>1
                             ) a
                        WHERE
                            l.id<>a.id
                        AND l.shortname = a.shortname
                ';

                $rs = $DB->get_recordset_sql($sql, null);

                //$rs = facetoface_tbl_duplicate_values('facetoface_session_field','shortname');
                if ($rs !== false) {
                    foreach ($rs as $item) {
                        $data = (object)$item;
                        //randomize the value
                        $data->shortname = $DB->escape($data->shortname.'_'.$data->id);
                        $DB->update_record('facetoface_session_field', $data);
                        $replacements[]=array($item['id'], $item['shortname'], $data->shortname);
                    }
                }

                $transaction->allow_commit();
                facetoface_send_admin_upgrade_msg($replacements);

                //Apply the index
                $dbman->add_index($table, $index);
            }
        }

        upgrade_mod_savepoint(true, 2011120702, 'facetoface');
    }

    if ($oldversion < 2011120703) {

        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('intro', XMLDB_TYPE_TEXT, 'big', null, null, null, null, 'name');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add the introformat field
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'intro');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('description');
        if ($dbman->field_exists($table, $field)) {

            // Move all data from description to intro
            $facetofaces = $DB->get_records('facetoface');
            foreach ($facetofaces as $facetoface) {
                $facetoface->intro = $facetoface->description;
                $facetoface->introformat = FORMAT_HTML;
                $DB->update_record('facetoface', $facetoface);
            }

            // Remove the old description field
            $dbman->drop_field($table, $field);
        }

        // facetoface savepoint reached
        upgrade_mod_savepoint(true, 2011120703, 'facetoface');
    }

    if ($oldversion < 2012140605) {
        //Remove additional html anchor reference from existing manager approval request message formats
        $links = array(
            '[Teilnehmerlink]#unbestätigt' => '[Teilnehmerlink]',
            '[attendeeslink]#unapproved' => '[attendeeslink]',
            '[enlaceasistentes] # no aprobados' => '[enlaceasistentes]',
            '[เชื่อมโยงผู้เข้าร่วมประชุม] อนุมัติ #' => '[เชื่อมโยงผู้เข้าร่วมประชุม]',
        );
        //mssql has a problem with ntext columns being used in REPLACE function calls
        $dbfamily = $DB->get_dbfamily();
        foreach ($links as $key => $replacement) {
            if ($dbfamily == 'mssql') {
                $sql = "UPDATE {facetoface} SET requestinstrmngr = CAST(REPLACE(CAST(requestinstrmngr as nvarchar(max)), ?, ?) as ntext)";
            } else {
                $sql = "UPDATE {facetoface} SET requestinstrmngr = REPLACE(requestinstrmngr, ?, ?)";
            }
            $result = $result && $DB->execute($sql, array($key, $replacement));
        }
        $stringmanager = get_string_manager();
        $langs = array("de", "en", "es", "fi", "fr", "he", "hu", "it", "ja", "nl", "pl", "pt_br",
            "sv", "th", "zh_cn");
        $strings = array("cancellationinstrmngr", "confirmationinstrmngr", "requestinstrmngr", "reminderinstrmngr");

        foreach ($langs as $lang) {
            $sql = "UPDATE {facetoface} SET ";
            $params = array();

            foreach ($strings as $str) {
                $remove = $stringmanager->get_string('setting:default' . $str . 'copybelow', 'facetoface', null, $lang);
                if ($dbfamily == 'mssql') {
                    $sql .= "{$str} = CAST(REPLACE(CAST({$str} as nvarchar(max)), ?, '') as ntext)";
                } else {
                    $sql .= "{$str} = REPLACE({$str}, ?, '')";
                }
                $params[] = $remove;

                if ($str != "reminderinstrmngr") {
                    $sql .= ", ";
                }
            }

            $result = $result && $DB->execute($sql, $params);
        }
        // facetoface savepoint reached

        upgrade_mod_savepoint(true, 2012140605, 'facetoface');
    }

    if ($oldversion < 2012140609) {
        //add a field for the user calendar entry checkbox
        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('usercalentry');
        $field->set_attributes(XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, 1);

        //just double check the field doesn't somehow exist
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        //update the existing showoncalendar field, change true to F2F_CAL_SITE
        $sql = 'UPDATE {facetoface}
                SET showoncalendar = ?
                WHERE showoncalendar = ?';
        $DB->execute($sql, array(F2F_CAL_SITE, F2F_CAL_COURSE));

        upgrade_mod_savepoint(true, 2012140609, 'facetoface');
    }

    if ($oldversion < 2013013000) {
        //add the usermodified field to sessions
        $table = new xmldb_table('facetoface_sessions');
        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '20', null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        //add the sessiontimezone field to sessions_dates
        $table = new xmldb_table('facetoface_sessions_dates');
        $field = new xmldb_field('sessiontimezone', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'sessionid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        //fix if no users had bad timezones set
        //first get default zone
        $fixsessions = false;

        $badzones = totara_get_bad_timezone_list();
        $goodzones = totara_get_clean_timezone_list();
        //see what the site config is
        if (isset($CFG->forcetimezone)) {
            $default = $CFG->forcetimezone;
        } else if (isset($CFG->timezone)) {
            $default = $CFG->timezone;
        }
        if($default == 99) {
            //both set to server local time so get system tz
            $default = date_default_timezone_get();
        }
        //only fix if the site setting is not a Moodle offset, and is in the approved list
        if (!is_float($default) && in_array($default, $goodzones)) {
            $fixsessions = true;
        }

        if ($fixsessions) {
            //check no users have deprecated or totally unknown timezones
            list($insql, $inparams) = $DB->get_in_or_equal(array_keys($badzones));
            $sql = "SELECT count(id) from {user} WHERE timezone $insql";
            $badusers = $DB->count_records_sql($sql, $inparams);
            $fullzones = array_merge(array_keys($badzones), array_values($goodzones));
            $fullzones[] = 99;
            list($insql, $inparams) = $DB->get_in_or_equal($fullzones, SQL_PARAMS_QM, 'param', false);
            $sql = "SELECT count(id) from {user} WHERE timezone $insql";
            $unknownusercount = $DB->count_records_sql($sql, $inparams);

            if ($badusers > 0 || $unknownusercount > 0) {
                //some users have bad timezones set
                //output a notice and direct to the new admin tool
                $info = get_string('badusertimezonemessage', 'tool_totara_timezonefix');
                echo $OUTPUT->notification($info, 'notifynotice');
            } else {
                //only if the site timezone is sensible AND no users have bad zones
                $sql = 'UPDATE {facetoface_sessions_dates} SET sessiontimezone = ?';
                $DB->execute($sql, array($default));
            }
        }
        //sessions created before this upgrade may still need fixing
        $sql = "SELECT count(id) from {facetoface_sessions_dates} WHERE sessiontimezone IS NULL OR " . $DB->sql_compare_text('sessiontimezone', 255) . " = ?";
        $unfixedsessions = $DB->count_records_sql($sql, array(''));
        if ($unfixedsessions > 0) {
            $info = get_string('timezoneupgradeinfomessage', 'facetoface');
            echo $OUTPUT->notification($info, 'notifynotice');
        }
        upgrade_mod_savepoint(true, 2013013000, 'facetoface');
    }
    if ($oldversion < 2013013001) {

        // Define table facetoface_notification_tpl to be created
        $table = new xmldb_table('facetoface_notification_tpl');

        // Set up the comment for the notification templates table.
        $table->setComment('Face-to-face notification templates');

        // Adding fields to table facetoface_notification_tpl
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('body', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL, null, null);
        $table->add_field('managerprefix', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table facetoface_notification_tpl
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table facetoface_notification_tpl
        $table->add_index('title', XMLDB_INDEX_UNIQUE, array('title'));
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, array('status'));

        // Launch create table for facetoface_notification_tpl
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2013013001, 'facetoface');
    }

    if ($result && $oldversion < 2013013002) {

        // Define table facetoface_notification to be created
        $table = new xmldb_table('facetoface_notification');

        // Set up the comment for the facetoface notification table.
        $table->setComment('Facetoface notifications');

        // Adding fields to table facetoface_notification
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('type', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
        $table->add_field('conditiontype', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('scheduleunit', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('scheduleamount', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('scheduletime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('ccmanager', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('managerprefix', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('body', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL, null, null);
        $table->add_field('booked', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('waitlisted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('cancelled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('facetofaceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('issent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table facetoface_notification
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $table->add_key('facetofaceid', XMLDB_KEY_FOREIGN, array('facetofaceid'), 'facetoface', array('id'));

        // Adding indexes to table facetoface_notification
        $table->add_index('type', XMLDB_INDEX_NOTUNIQUE, array('type'));
        $table->add_index('title', XMLDB_INDEX_NOTUNIQUE, array('title'));
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, array('status'));
        $table->add_index('issent', XMLDB_INDEX_NOTUNIQUE, array('issent'));

        // Launch create table for facetoface_notification
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2013013002, 'facetoface');
    }

    if ($oldversion < 2013013003) {

        // Define table facetoface_notification_sent to be created
        $table = new xmldb_table('facetoface_notification_sent');

        // Set up the comment for the facetoface notifications sent table.
        $table->setComment('Face-to-face notification reciepts');

        // Adding fields to table facetoface_notification_sent
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('notificationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

        // Adding keys to table facetoface_notification_sent
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('notificationid', XMLDB_KEY_FOREIGN, array('notificationid'), 'facetoface_notification', array('id'));
        $table->add_key('sessionid', XMLDB_KEY_FOREIGN, array('sessionid'), 'facetoface_sessions', array('id'));

        // Launch create table for facetoface_notification_sent
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2013013003, 'facetoface');
    }

    if ($oldversion < 2013013004) {
        // Move existing face-to-face messages to the new notification system
        // Get facetoface's
        $facetofaces = $DB->get_records('facetoface');
        if ($facetofaces) {
            // Loop over facetofaces
            foreach ($facetofaces as $facetoface) {
                // Get each message and create notification
                $defaults = array();
                $defaults['facetofaceid'] = $facetoface->id;
                $defaults['courseid'] = $facetoface->course;
                $defaults['type'] = MDL_F2F_NOTIFICATION_AUTO;
                $defaults['booked'] = 0;
                $defaults['waitlisted'] = 0;
                $defaults['cancelled'] = 0;
                $defaults['issent'] = 0;
                $defaults['status'] = 1;
                $defaults['ccmanager'] = 0;

                $confirmation = new facetoface_notification($defaults, false);
                $confirmation->title = $facetoface->confirmationsubject;
                $confirmation->body = text_to_html($facetoface->confirmationmessage);
                $confirmation->conditiontype = MDL_F2F_CONDITION_BOOKING_CONFIRMATION;
                if (!empty($facetoface->confirmationinstrmngr)) {
                    $confirmation->ccmanager = 1;
                    $confirmation->managerprefix = text_to_html($facetoface->confirmationinstrmngr);
                }
                $result = $result && $confirmation->save();

                $waitlist = new facetoface_notification($defaults, false);
                $waitlist->title = $facetoface->waitlistedsubject;
                $waitlist->body = text_to_html($facetoface->waitlistedmessage);
                $waitlist->conditiontype = MDL_F2F_CONDITION_WAITLISTED_CONFIRMATION;
                $result = $result && $waitlist->save();

                $cancellation = new facetoface_notification($defaults, false);
                $cancellation->title = $facetoface->cancellationsubject;
                $cancellation->body = text_to_html($facetoface->cancellationmessage);
                $cancellation->conditiontype = MDL_F2F_CONDITION_CANCELLATION_CONFIRMATION;
                if (!empty($facetoface->cancellationinstrmngr)) {
                    $cancellation->ccmanager = 1;
                    $cancellation->managerprefix = text_to_html($facetoface->cancellationinstrmngr);
                }
                $result = $result && $cancellation->save();

                $reminder = new facetoface_notification($defaults, false);
                $reminder->title = $facetoface->remindersubject;
                $reminder->body = text_to_html($facetoface->remindermessage);
                $reminder->conditiontype = MDL_F2F_CONDITION_BEFORE_SESSION;
                $reminder->scheduleunit = MDL_F2F_SCHEDULE_UNIT_DAY;
                $reminder->scheduleamount = $facetoface->reminderperiod;
                if (!empty($facetoface->reminderinstrmngr)) {
                    $reminder->ccmanager = 1;
                    $reminder->managerprefix = text_to_html($facetoface->reminderinstrmngr);
                }
                $result = $result && $reminder->save();

                if (!empty($facetoface->approvalreqd)) {
                    $request = new facetoface_notification($defaults, false);
                    $request->title = $facetoface->requestsubject;
                    $request->body = text_to_html($facetoface->requestmessage);
                    $request->conditiontype = MDL_F2F_CONDITION_BOOKING_REQUEST;
                    if (!empty($facetoface->requestinstrmngr)) {
                        $request->ccmanager = 1;
                        $request->managerprefix = text_to_html($facetoface->requestinstrmngr);
                    }
                    $result = $result && $request->save();
                }
            }
        }

        // Copy over templates from lang files
        $tpl_confirmation = new stdClass();
        $tpl_confirmation->status = 1;
        $tpl_confirmation->title = get_string('setting:defaultconfirmationsubjectdefault', 'facetoface');
        $tpl_confirmation->body = text_to_html(get_string('setting:defaultconfirmationmessagedefault', 'facetoface'));
        $tpl_confirmation->managerprefix = text_to_html(get_string('setting:defaultconfirmationinstrmngrdefault', 'facetoface'));
        $DB->insert_record('facetoface_notification_tpl', $tpl_confirmation);

        $tpl_cancellation = new stdClass();
        $tpl_cancellation->status = 1;
        $tpl_cancellation->title = get_string('setting:defaultcancellationsubjectdefault', 'facetoface');
        $tpl_cancellation->body = text_to_html(get_string('setting:defaultcancellationmessagedefault', 'facetoface'));
        $tpl_cancellation->managerprefix = text_to_html(get_string('setting:defaultcancellationinstrmngrdefault', 'facetoface'));
        $DB->insert_record('facetoface_notification_tpl', $tpl_cancellation);

        $tpl_waitlist = new stdClass();
        $tpl_waitlist->status = 1;
        $tpl_waitlist->title = get_string('setting:defaultwaitlistedsubjectdefault', 'facetoface');
        $tpl_waitlist->body = text_to_html(get_string('setting:defaultwaitlistedmessagedefault', 'facetoface'));
        $DB->insert_record('facetoface_notification_tpl', $tpl_waitlist);

        $tpl_reminder = new stdClass();
        $tpl_reminder->status = 1;
        $tpl_reminder->title = get_string('setting:defaultremindersubjectdefault', 'facetoface');
        $tpl_reminder->body = text_to_html(get_string('setting:defaultremindermessagedefault', 'facetoface'));
        $tpl_reminder->managerprefix = text_to_html(get_string('setting:defaultreminderinstrmngrdefault', 'facetoface'));
        $DB->insert_record('facetoface_notification_tpl', $tpl_reminder);

        $tpl_request = new stdClass();
        $tpl_request->status = 1;
        $tpl_request->title = get_string('setting:defaultrequestsubjectdefault', 'facetoface');
        $tpl_request->body = text_to_html(get_string('setting:defaultrequestmessagedefault', 'facetoface'));
        $tpl_request->managerprefix = text_to_html(get_string('setting:defaultrequestinstrmngrdefault', 'facetoface'));
        $DB->insert_record('facetoface_notification_tpl', $tpl_request);

        // Drop columns from facetoface table
        if ($result) {
            $msg_cols = array(
                'confirmationsubject',
                'confirmationinstrmngr',
                'confirmationmessage',
                'waitlistedsubject',
                'waitlistedmessage',
                'cancellationsubject',
                'cancellationinstrmngr',
                'cancellationmessage',
                'remindersubject',
                'reminderinstrmngr',
                'remindermessage',
                'reminderperiod',
                'requestsubject',
                'requestinstrmngr',
                'requestmessage'
            );

            $table = new xmldb_table('facetoface');
            foreach ($msg_cols as $mc) {
                $field = new xmldb_field($mc);
                if ($dbman->field_exists($table, $field)) {
                    $dbman->drop_field($table, $field);
                }
            }
        }

        upgrade_mod_savepoint(true, 2013013004, 'facetoface');
    }

    if ($oldversion < 2013013005) {
        $table = new xmldb_table('facetoface_signups');
        $field = new xmldb_field('mailedreminder');

        if (!$dbman->field_exists($table, $field)) {
            // Get all sessions with reminders sent that have had
            // the reminder converted to the new style notification
            $sessions = $DB->get_records_sql(
                "
                SELECT
                    fs.sessionid,
                    ss.facetoface AS facetofaceid,
                    fn.id AS notificationid
                FROM
                    {facetoface_signups} fs
                INNER JOIN
                    {facetoface_sessions} ss
                 ON fs.sessionid = ss.id
                INNER JOIN
                    {facetoface_notification} fn
                 ON fn.facetofaceid = ss.facetoface
                WHERE
                    fs.mailedreminder = 1
                AND fn.type = ".MDL_F2F_NOTIFICATION_AUTO."
                AND fn.conditiontype = ".MDL_F2F_CONDITION_BEFORE_SESSION."
                AND fn.scheduletime IS NOT NULL
                GROUP BY
                    fs.sessionid,
                    ss.facetoface,
                    fn.id
                "
            );

            if ($sessions) {
                // Add entries to sent table
                foreach ($sessions as $session) {
                    $record = new stdClass();
                    $record->sessionid = $session->sessionid;
                    $record->notificationid = $session->notificationid;
                    $DB->insert_record('facetoface_notification_sent', $record);
                }
            }

            // Drop column from signups table, already checked it exists.
            $dbman->drop_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2013013005, 'facetoface');
    }

    if ($oldversion < 2013013006) {

        // Define table facetoface_room to be created
        $table = new xmldb_table('facetoface_room');

        // Set up comment for the facetoface room table.
        $table->setComment('Table for storing pre-defined facetoface room data');

        // Adding fields to table facetoface_room
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('building', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('address', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('capacity', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('type', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('custom', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        // Adding keys to table facetoface_room
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table facetoface_room
        $table->add_index('custom', XMLDB_INDEX_NOTUNIQUE, array('custom'));

        // Launch create table for facetoface_room
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add roomid field to facetoface_sessions table
        $table = new xmldb_table('facetoface_sessions');

        $field = new xmldb_field('roomid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'discountcost');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Populate new sesion room table with the data from the session custom fields
        $rs = $DB->get_recordset('facetoface_sessions', array(), '', 'id, capacity');

        $fieldmappings = array('room' => 'name', 'venue' => 'building', 'location' => 'address');

        foreach ($rs as $session) {
            $sql = "SELECT f.shortname, d.data
                FROM {facetoface_session_data} d
                INNER JOIN {facetoface_session_field} f ON d.fieldid = f.id
                WHERE d.sessionid = ?
                AND f.shortname IN('room', 'venue', 'location')";
            if ($data = $DB->get_records_sql($sql, array($session->id))) {
                $todb = new stdClass;
                $todb->custom = 1;
                $todb->capacity = $session->capacity;
                foreach ($data as $d) {
                    $todb->{$fieldmappings[$d->shortname]} = $d->data;
                }
                if (!$roomid = $DB->insert_record('facetoface_room', $todb)) {
                    error('Could not populate session room data from custom fields');
                }
                $todb = new stdClass;
                $todb->id = $session->id;
                $todb->roomid = $roomid;
                if (!$DB->update_record('facetoface_sessions', $todb)) {
                    error('Could not update session roomid');
                }
            }
        }

        // Remove location, room and venue custom fields and data
        $DB->delete_records_select('facetoface_session_data',
            "fieldid IN(
                SELECT id FROM {facetoface_session_field}
                WHERE shortname IN('room', 'venue', 'location'))");

        $DB->delete_records_select('facetoface_session_field',
            "shortname IN('room', 'venue', 'location')");

        upgrade_mod_savepoint(true, 2013013006, 'facetoface');
    }

    if ($oldversion < 2013013007) {

        // original table name - to long for XMLDB editor
        $table = new xmldb_table('facetoface_notification_history');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'facetoface_notification_hist');
        }

        // create new table instead
        $table = new xmldb_table('facetoface_notification_hist');

        // Set up the comment for the facetoface notification history table.
        $table->setComment('Notifications history (stores ical event information)');

        // Adding fields to table facetoface_notification_hist
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->add_field('notificationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('sessiondateid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('ical_uid', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);
        $table->add_field('ical_method', XMLDB_TYPE_CHAR, '32', null, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table facetoface_notification_hist
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('notificationid', XMLDB_KEY_FOREIGN, array('notificationid'), 'facetoface_notification', array('id'));
        $table->add_key('sessionid', XMLDB_KEY_FOREIGN, array('sessionid'), 'facetoface_sessions', array('id'));
        $table->add_key('sessiondateid', XMLDB_KEY_FOREIGN, array('sessiondateid'), 'facetoface_sessions_dates', array('id'));
        $table->add_index('f2f_hist_userid_idx', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        // Launch create table for facetoface_notification_hist
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2013013007, 'facetoface');
    }

    if ($oldversion < 2013070900) {
        // Change the cost fields to varchars instead of integers.
        $table = new xmldb_table('facetoface_sessions');
        $costfield = new xmldb_field('normalcost', XMLDB_TYPE_CHAR, '255', null, true, null, '0','duration');
        $discountfield = new xmldb_field('discountcost', XMLDB_TYPE_CHAR, '255', null, true, null, '0','normalcost');
        $dbman->change_field_type($table, $costfield);
        $dbman->change_field_type($table, $discountfield);
        upgrade_mod_savepoint(true, 2013070900, 'facetoface');
    }

    if ($oldversion < 2013070901) {

        // Add manager decline notification template.
        if ($dbman->table_exists('facetoface_notification_tpl')) {
            $decline = new stdClass();
            $decline->status = 1;
            $decline->title = get_string('setting:defaultdeclinesubjectdefault', 'facetoface');
            $decline->body = text_to_html(get_string('setting:defaultdeclinemessagedefault', 'facetoface'));
            $decline->managerprefix = text_to_html(get_string('setting:defaultdeclineinstrmngrdefault', 'facetoface'));

            $DB->insert_record('facetoface_notification_tpl', $decline);
        }

        upgrade_mod_savepoint(true, 2013070901, 'facetoface');
    }

    // Re-adding the rooms upgrades because of version conflicts with 2.2, see T-11146.
    if ($oldversion < 2013090200) {

        // Define table facetoface_notification_tpl to be created
        $table = new xmldb_table('facetoface_notification_tpl');

        // Set up the comment for the notification templates table.
        $table->setComment('Face-to-face notification templates');

        // Adding fields to table facetoface_notification_tpl
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('body', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL, null, null);
        $table->add_field('managerprefix', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table facetoface_notification_tpl
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table facetoface_notification_tpl
        $table->add_index('title', XMLDB_INDEX_UNIQUE, array('title'));
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, array('status'));

        // Launch create table for facetoface_notification_tpl
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2013090200, 'facetoface');
    }

    if ($result && $oldversion < 2013090201) {

        // Define table facetoface_notification to be created
        $table = new xmldb_table('facetoface_notification');

        // Set up the comment for the facetoface notification table.
        $table->setComment('Facetoface notifications');

        // Adding fields to table facetoface_notification
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('type', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
        $table->add_field('conditiontype', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('scheduleunit', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('scheduleamount', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('scheduletime', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('ccmanager', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('managerprefix', XMLDB_TYPE_TEXT, 'big', null, null, null, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('body', XMLDB_TYPE_TEXT, 'big', null, XMLDB_NOTNULL, null, null);
        $table->add_field('booked', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('waitlisted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, '0');
        $table->add_field('cancelled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('facetofaceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('issent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table facetoface_notification
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
        $table->add_key('facetofaceid', XMLDB_KEY_FOREIGN, array('facetofaceid'), 'facetoface', array('id'));

        // Adding indexes to table facetoface_notification
        $table->add_index('type', XMLDB_INDEX_NOTUNIQUE, array('type'));
        $table->add_index('title', XMLDB_INDEX_NOTUNIQUE, array('title'));
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, array('status'));
        $table->add_index('issent', XMLDB_INDEX_NOTUNIQUE, array('issent'));

        // Launch create table for facetoface_notification
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2013090201, 'facetoface');
    }

    if ($oldversion < 2013090202) {

        // Define table facetoface_notification_sent to be created
        $table = new xmldb_table('facetoface_notification_sent');

        // Set up the comment for the facetoface notifications sent table.
        $table->setComment('Face-to-face notification reciepts');

        // Adding fields to table facetoface_notification_sent
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('notificationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

        // Adding keys to table facetoface_notification_sent
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('notificationid', XMLDB_KEY_FOREIGN, array('notificationid'), 'facetoface_notification', array('id'));
        $table->add_key('sessionid', XMLDB_KEY_FOREIGN, array('sessionid'), 'facetoface_sessions', array('id'));

        // Launch create table for facetoface_notification_sent
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2013090202, 'facetoface');
    }

    if ($oldversion < 2013090203) {
        // Move existing face-to-face messages to the new notification system

        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('confirmationinstrmngr');
        if ($dbman->field_exists($table, $field)) {
            // If this field still exists the notifications haven't been transfered yet.
            $facetofaces = $DB->get_records('facetoface');
            if ($facetofaces) {
                // Loop over facetofaces
                foreach ($facetofaces as $facetoface) {

                    // Get each message and create notification
                    $defaults = array();
                    $defaults['facetofaceid'] = $facetoface->id;
                    $defaults['courseid'] = $facetoface->course;
                    $defaults['type'] = MDL_F2F_NOTIFICATION_AUTO;
                    $defaults['booked'] = 0;
                    $defaults['waitlisted'] = 0;
                    $defaults['cancelled'] = 0;
                    $defaults['issent'] = 0;
                    $defaults['status'] = 1;
                    $defaults['ccmanager'] = 0;

                    $confirmation = new facetoface_notification($defaults, false);
                    $confirmation->title = $facetoface->confirmationsubject;
                    $confirmation->body = text_to_html($facetoface->confirmationmessage);
                    $confirmation->conditiontype = MDL_F2F_CONDITION_BOOKING_CONFIRMATION;
                    if (!empty($facetoface->confirmationinstrmngr)) {
                        $confirmation->ccmanager = 1;
                        $confirmation->managerprefix = text_to_html($facetoface->confirmationinstrmngr);
                    }
                    $result = $result && $confirmation->save();

                    $waitlist = new facetoface_notification($defaults, false);
                    $waitlist->title = $facetoface->waitlistedsubject;
                    $waitlist->body = text_to_html($facetoface->waitlistedmessage);
                    $waitlist->conditiontype = MDL_F2F_CONDITION_WAITLISTED_CONFIRMATION;
                    $result = $result && $waitlist->save();

                    $cancellation = new facetoface_notification($defaults, false);
                    $cancellation->title = $facetoface->cancellationsubject;
                    $cancellation->body = text_to_html($facetoface->cancellationmessage);
                    $cancellation->conditiontype = MDL_F2F_CONDITION_CANCELLATION_CONFIRMATION;
                    if (!empty($facetoface->cancellationinstrmngr)) {
                        $cancellation->ccmanager = 1;
                        $cancellation->managerprefix = text_to_html($facetoface->cancellationinstrmngr);
                    }
                    $result = $result && $cancellation->save();

                    $reminder = new facetoface_notification($defaults, false);
                    $reminder->title = $facetoface->remindersubject;
                    $reminder->body = text_to_html($facetoface->remindermessage);
                    $reminder->conditiontype = MDL_F2F_CONDITION_BEFORE_SESSION;
                    $reminder->scheduleunit = MDL_F2F_SCHEDULE_UNIT_DAY;
                    $reminder->scheduleamount = $facetoface->reminderperiod;
                    if (!empty($facetoface->reminderinstrmngr)) {
                        $reminder->ccmanager = 1;
                        $reminder->managerprefix = text_to_html($facetoface->reminderinstrmngr);
                    }
                    $result = $result && $reminder->save();

                    if (!empty($facetoface->approvalreqd)) {
                        $request = new facetoface_notification($defaults, false);
                        $request->title = $facetoface->requestsubject;
                        $request->body = text_to_html($facetoface->requestmessage);
                        $request->conditiontype = MDL_F2F_CONDITION_BOOKING_REQUEST;
                        if (!empty($facetoface->requestinstrmngr)) {
                            $request->ccmanager = 1;
                            $request->managerprefix = text_to_html($facetoface->requestinstrmngr);
                        }
                        $result = $result && $request->save();
                    }
                }
            }

            // Drop columns from facetoface table
            if ($result) {
                $msg_cols = array(
                    'confirmationsubject',
                    'confirmationinstrmngr',
                    'confirmationmessage',
                    'waitlistedsubject',
                    'waitlistedmessage',
                    'cancellationsubject',
                    'cancellationinstrmngr',
                    'cancellationmessage',
                    'remindersubject',
                    'reminderinstrmngr',
                    'remindermessage',
                    'reminderperiod',
                    'requestsubject',
                    'requestinstrmngr',
                    'requestmessage'
                );

                $table = new xmldb_table('facetoface');
                foreach ($msg_cols as $mc) {
                    $field = new xmldb_field($mc);
                    if ($dbman->field_exists($table, $field)) {
                        $dbman->drop_field($table, $field);
                    }
                }
            }
        }

        // If the templates tables exists but there aren't any templates.
        if ($dbman->table_exists('facetoface_notification_tpl')) {
            $count_templates = $DB->count_records('facetoface_notification_tpl');
            if ($count_templates == 0) {
                // Copy over templates from lang files
                $tpl_confirmation = new stdClass();
                $tpl_confirmation->status = 1;
                $tpl_confirmation->title = get_string('setting:defaultconfirmationsubjectdefault', 'facetoface');
                $tpl_confirmation->body = text_to_html(get_string('setting:defaultconfirmationmessagedefault', 'facetoface'));
                $tpl_confirmation->managerprefix = text_to_html(get_string('setting:defaultconfirmationinstrmngrdefault', 'facetoface'));
                $DB->insert_record('facetoface_notification_tpl', $tpl_confirmation);

                $tpl_cancellation = new stdClass();
                $tpl_cancellation->status = 1;
                $tpl_cancellation->title = get_string('setting:defaultcancellationsubjectdefault', 'facetoface');
                $tpl_cancellation->body = text_to_html(get_string('setting:defaultcancellationmessagedefault', 'facetoface'));
                $tpl_cancellation->managerprefix = text_to_html(get_string('setting:defaultcancellationinstrmngrdefault', 'facetoface'));
                $DB->insert_record('facetoface_notification_tpl', $tpl_cancellation);

                $tpl_waitlist = new stdClass();
                $tpl_waitlist->status = 1;
                $tpl_waitlist->title = get_string('setting:defaultwaitlistedsubjectdefault', 'facetoface');
                $tpl_waitlist->body = text_to_html(get_string('setting:defaultwaitlistedmessagedefault', 'facetoface'));
                $DB->insert_record('facetoface_notification_tpl', $tpl_waitlist);

                $tpl_reminder = new stdClass();
                $tpl_reminder->status = 1;
                $tpl_reminder->title = get_string('setting:defaultremindersubjectdefault', 'facetoface');
                $tpl_reminder->body = text_to_html(get_string('setting:defaultremindermessagedefault', 'facetoface'));
                $tpl_reminder->managerprefix = text_to_html(get_string('setting:defaultreminderinstrmngrdefault', 'facetoface'));
                $DB->insert_record('facetoface_notification_tpl', $tpl_reminder);

                $tpl_request = new stdClass();
                $tpl_request->status = 1;
                $tpl_request->title = get_string('setting:defaultrequestsubjectdefault', 'facetoface');
                $tpl_request->body = text_to_html(get_string('setting:defaultrequestmessagedefault', 'facetoface'));
                $tpl_request->managerprefix = text_to_html(get_string('setting:defaultrequestinstrmngrdefault', 'facetoface'));
                $DB->insert_record('facetoface_notification_tpl', $tpl_request);

                $tpl_decline = new stdClass();
                $tpl_decline->status = 1;
                $tpl_decline->title = get_string('setting:defaultdeclinesubjectdefault', 'facetoface');
                $tpl_decline->body = text_to_html(get_string('setting:defaultdeclinemessagedefault', 'facetoface'));
                $tpl_decline->managerprefix = text_to_html(get_string('setting:defaultdeclineinstrmngrdefault', 'facetoface'));
                $DB->insert_record('facetoface_notification_tpl', $tpl_decline);
            }
        }

        upgrade_mod_savepoint(true, 2013090203, 'facetoface');
    }

    if ($oldversion < 2013090204) {
        // Get all sessions with reminders sent that have had
        // the reminder converted to the new style notification
        $table = new xmldb_table('facetoface_signups');
        $field = new xmldb_field('mailedreminder');
        if ($dbman->field_exists($table, $field)) {
            $sessions = $DB->get_records_sql(
                "
                SELECT
                    fs.sessionid,
                    ss.facetoface AS facetofaceid,
                    fn.id AS notificationid
                FROM
                    {facetoface_signups} fs
                INNER JOIN
                    {facetoface_sessions} ss
                 ON fs.sessionid = ss.id
                INNER JOIN
                    {facetoface_notification} fn
                 ON fn.facetofaceid = ss.facetoface
                WHERE
                    fs.mailedreminder = 1
                AND fn.type = ".MDL_F2F_NOTIFICATION_AUTO."
                AND fn.conditiontype = ".MDL_F2F_CONDITION_BEFORE_SESSION."
                AND fn.scheduletime IS NOT NULL
                GROUP BY
                    fs.sessionid,
                    ss.facetoface,
                    fn.id
                "
            );

            // If the notification_sent table exists but is empty.
            if ($dbman->table_exists('facetoface_notification_sent')) {
                $count_notifications = $DB->count_records('facetoface_notification_sent');
                if ($count_notifications == 0) {
                    // Loop through all the sessions.
                    if ($sessions) {
                        // And add entries to sent table
                        foreach ($sessions as $session) {
                            $record = new stdClass();
                            $record->sessionid = $session->sessionid;
                            $record->notificationid = $session->notificationid;
                            $DB->insert_record('facetoface_notification_sent', $record);
                        }
                    }
                }
            }

            // Drop column from signups table
            $dbman->drop_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2013090204, 'facetoface');
    }

    if ($oldversion < 2013090205) {

        // Define table facetoface_room to be created
        $table = new xmldb_table('facetoface_room');

        // Set up comment for the facetoface room table.
        $table->setComment('Table for storing pre-defined facetoface room data');

        // Adding fields to table facetoface_room
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('building', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('address', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('capacity', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('type', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, 'small', null, null, null, null);
        $table->add_field('custom', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');

        // Adding keys to table facetoface_room
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table facetoface_room
        $table->add_index('custom', XMLDB_INDEX_NOTUNIQUE, array('custom'));

        // Launch create table for facetoface_room
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add roomid field to facetoface_sessions table
        $table = new xmldb_table('facetoface_sessions');

        $field = new xmldb_field('roomid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'discountcost');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Populate new sesion room table with the data from the session custom fields
            $rs = $DB->get_recordset('facetoface_sessions', array(), '', 'id, capacity');

            $fieldmappings = array('room' => 'name', 'venue' => 'building', 'location' => 'address');

            foreach ($rs as $session) {
                $sql = "SELECT f.shortname, d.data
                    FROM {facetoface_session_data} d
                    INNER JOIN {facetoface_session_field} f ON d.fieldid = f.id
                    WHERE d.sessionid = ?
                    AND f.shortname IN('room', 'venue', 'location')";
                if ($data = $DB->get_records_sql($sql, array($session->id))) {
                    $todb = new stdClass;
                    $todb->custom = 1;
                    $todb->capacity = $session->capacity;
                    foreach ($data as $d) {
                        $todb->{$fieldmappings[$d->shortname]} = $d->data;
                    }
                    if (!$roomid = $DB->insert_record('facetoface_room', $todb)) {
                        error('Could not populate session room data from custom fields');
                    }
                    $todb = new stdClass;
                    $todb->id = $session->id;
                    $todb->roomid = $roomid;
                    if (!$DB->update_record('facetoface_sessions', $todb)) {
                        error('Could not update session roomid');
                    }
                }
            }

            // Remove location, room and venue custom fields and data
            $DB->delete_records_select('facetoface_session_data',
                "fieldid IN(
                    SELECT id FROM {facetoface_session_field}
                    WHERE shortname IN('room', 'venue', 'location'))");

            $DB->delete_records_select('facetoface_session_field',
                "shortname IN('room', 'venue', 'location')");
        }

        upgrade_mod_savepoint(true, 2013090205, 'facetoface');
    }

    if ($oldversion < 2013090206) {

        // original table name - to long for XMLDB editor
        $table = new xmldb_table('facetoface_notification_history');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'facetoface_notification_hist');
        }

        // create new table instead
        $table = new xmldb_table('facetoface_notification_hist');

        // Set up the comment for the facetoface notification history table.
        $table->setComment('Notifications history (stores ical event information)');

        // Adding fields to table facetoface_notification_hist
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->add_field('notificationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('sessiondateid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->add_field('ical_uid', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);
        $table->add_field('ical_method', XMLDB_TYPE_CHAR, '32', null, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table facetoface_notification_hist
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('notificationid', XMLDB_KEY_FOREIGN, array('notificationid'), 'facetoface_notification', array('id'));
        $table->add_key('sessionid', XMLDB_KEY_FOREIGN, array('sessionid'), 'facetoface_sessions', array('id'));
        $table->add_key('sessiondateid', XMLDB_KEY_FOREIGN, array('sessiondateid'), 'facetoface_sessions_dates', array('id'));
        $table->add_index('f2f_hist_userid_idx', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        // Launch create table for facetoface_notification_hist
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2013090206, 'facetoface');
    }

    if ($oldversion < 2013092000) {
        // Define field archived to be added to facetoface_signups.
        $table = new xmldb_table('facetoface_signups');
        $field = new xmldb_field('archived', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'notificationtype');

        // Conditionally launch add field archived.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field multiplesessions to be added to facetoface.
        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('multiplesessions', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'usercalentry');

        // Conditionally launch add field multiplesessions.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2013092000, 'facetoface');
    }

    if ($oldversion < 2013101500) {
        // Define field "advice" to be dropped.
        $table = new xmldb_table('facetoface_signups_status');
        $field = new xmldb_field('advice');

        // Conditionally drop field.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2013101500, 'facetoface');
    }

    if ($oldversion < 2013102100) {

        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('completionstatusrequired', XMLDB_TYPE_CHAR, '255');

        // Conditionally add field.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2013102100, 'facetoface');
    }

    if ($oldversion < 2013103000) {
        // Adding foreign keys.
        $tables = array(
            'facetoface' => array(
                new xmldb_key('face_cou_fk', XMLDB_KEY_FOREIGN, array('course'), 'course', 'id')),
            'facetoface_session_roles' => array(
                new xmldb_key('facesessrole_use_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', 'id')),
            'facetoface_sessions' => array(
                new xmldb_key('facesess_roo_fk', XMLDB_KEY_FOREIGN, array('roomid'), 'facetoface_room', 'id'),
                new xmldb_key('facesess_use_fk', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', 'id')),
            'facetoface_signups' => array(
                new xmldb_key('facesign_use_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', 'id')),
            'facetoface_signups_status' => array(
                new xmldb_key('facesignstat_cre_fk', XMLDB_KEY_FOREIGN, array('createdby'), 'user', 'id')),
            'facetoface_session_data' => array(
                new xmldb_key('facesessdata_fie_fk', XMLDB_KEY_FOREIGN, array('fieldid'), 'facetoface_session_field', 'id'),
                new xmldb_key('facesessdata_ses_fk', XMLDB_KEY_FOREIGN, array('sessionid'), 'facetoface_sessions', 'id')),
            'facetoface_notice_data' => array(
                new xmldb_key('facenotidata_fie_fk', XMLDB_KEY_FOREIGN, array('fieldid'), 'facetoface_session_field', 'id'),
                new xmldb_key('facenotidata_not_fk', XMLDB_KEY_FOREIGN, array('noticeid'), 'facetoface_notice', 'id')),
            'facetoface_notification' => array(
                new xmldb_key('facenoti_use_fk', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', 'id')),
            'facetoface_notification_hist' => array(
                new xmldb_key('facenotihist_use_fk', XMLDB_KEY_FOREIGN, array('userid'), 'user', 'id')));


        foreach ($tables as $tablename => $keys) {
            $table = new xmldb_table($tablename);
            foreach ($keys as $key) {
                $dbman->add_key($table, $key);
            }
        }

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2013103000, 'facetoface');
    }

    if ($oldversion < 2013120100) {

        $strmgr = get_string_manager();
        $langs = array_keys($strmgr->get_list_of_translations());
        foreach ($langs as $lang) {

            if ($lang == 'en' || $strmgr->get_string('facetoface', 'facetoface', null, $lang) !== $strmgr->get_string('facetoface', 'facetoface', null, 'en')) {

                $f2flabel = $strmgr->get_string('facetoface', 'facetoface', null, $lang);
                $courselabel = $strmgr->get_string('course', 'moodle', null, $lang);

                $body_key = "/{$courselabel}:\s*\[facetofacename\]/";
                $body_replacement = "{$courselabel}:   [coursename]<br />\n{$f2flabel}:   [facetofacename]";

                $title_key = "/{$courselabel}/";
                $title_replacement = "{$f2flabel}";

                $managerprefix_key = "/{$courselabel}:\s*\[facetofacename\]/";
                $managerprefix_replacement = "{$courselabel}:   [coursename]<br />\n{$f2flabel}:   [facetofacename]";

                $records = $DB->get_records('facetoface_notification_tpl', null, '', 'id, title, body, managerprefix');
                foreach($records as $row) {

                    $row->body = preg_replace($body_key, $body_replacement, $row->body);
                    $row->title = preg_replace($title_key, $title_replacement, $row->title);
                    $row->managerprefix = preg_replace($managerprefix_key, $managerprefix_replacement, $row->managerprefix);
                    $result = $DB->update_record('facetoface_notification_tpl', $row);
                }

                $records = $DB->get_records('facetoface_notification', null, '', 'id, title, body, managerprefix');
                foreach($records as $row) {

                    $row->body = preg_replace($body_key, $body_replacement, $row->body);
                    $row->title = preg_replace($title_key, $title_replacement, $row->title);
                    $row->managerprefix = preg_replace($managerprefix_key, $managerprefix_replacement, $row->managerprefix);
                    $result = $DB->update_record('facetoface_notification', $row);
                }
            }
        }
        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2013120100, 'facetoface');
    }

    if ($oldversion < 2014021300) {
        $table = new xmldb_table('facetoface_session_field');
        $field = new xmldb_field('isfilter');

        if ($dbman->field_exists($table, $field)) {
            // Get custom fields marked as filters.
            $selectedfilters = $DB->get_fieldset_select('facetoface_session_field', 'id', 'isfilter = 1');
            // Activate room, building, and address as default filters.
            $selectedfilters = array_merge($selectedfilters, array('room', 'building', 'address'));
            $calendarfilters = count($selectedfilters) ? implode(',', $selectedfilters) : '';
            // Store the selected filters in the DB.
            set_config('facetoface_calendarfilters', $calendarfilters);
            // Remove isfilter field (now unnecessary).
            $dbman->drop_field($table, $field);
        }

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2014021300, 'facetoface');
    }

    // Add extra 'manager reservations' settings.
    if ($oldversion < 2014022000) {

        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('managerreserve', XMLDB_TYPE_INTEGER, '4', null, null, null, '0', 'completionstatusrequired');
        $field->setComment('Can managers make reservations/bookings on behalf of their team');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('maxmanagerreserves', XMLDB_TYPE_INTEGER, '7', null, null, null, '1', 'managerreserve');
        $field->setComment('How many reservations can each manager make');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('reservecanceldays', XMLDB_TYPE_INTEGER, '7', null, null, null, '1', 'maxmanagerreserves');
        $field->setComment('Number days before the session when all unconfirmed reservations are deleted');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('reservedays', XMLDB_TYPE_INTEGER, '7', null, null, null, '2', 'reservecanceldays');
        $field->setComment('Number days before the session when reservations are closed');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Record the ID of managers when they reserve/book spaces on a session.
        // Define field bookedby to be added to facetoface_signups.
        $table = new xmldb_table('facetoface_signups');
        $field = new xmldb_field('bookedby', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'archived');
        $field->setComment('The manager who reserved / booked this space');

        // Conditionally launch add field bookedby.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Insert the templates for the new notification types.
        // Cancel reservation.
        $tpl_cancelreservation = new stdClass();
        $tpl_cancelreservation->status = 1;
        $tpl_cancelreservation->ccmanager = 0;
        $tpl_cancelreservation->title = get_string('setting:defaultcancelreservationsubjectdefault', 'facetoface');
        $tpl_cancelreservation->body = text_to_html(get_string('setting:defaultcancelreservationmessagedefault', 'facetoface'));
        $DB->insert_record('facetoface_notification_tpl', $tpl_cancelreservation);

        // Cancel all reservations.
        $tpl_cancelallreservations = new stdClass();
        $tpl_cancelallreservations->status = 1;
        $tpl_cancelallreservations->ccmanager = 0;
        $tpl_cancelallreservations->title = get_string('setting:defaultcancelallreservationssubjectdefault', 'facetoface');
        $tpl_cancelallreservations->body = text_to_html(get_string('setting:defaultcancelallreservationsmessagedefault', 'facetoface'));
        $DB->insert_record('facetoface_notification_tpl', $tpl_cancelallreservations);

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2014022000, 'facetoface');
    }

    if ($oldversion < 2014050400) {
        $del_users = $DB->get_fieldset_select('user', 'id', 'deleted = ?', array(1));
        $sus_users = $DB->get_fieldset_select('user', 'id', 'deleted = ? AND suspended = ?', array(0, 1));

        foreach ($del_users as $user) {
            // Cancel already deleted users facetoface signups.
            if ($signups = $DB->get_records('facetoface_signups', array('userid' => $user))) {
                foreach ($signups as $signup) {
                    $session = facetoface_get_session($signup->sessionid);
                    // Using $null, null fails because of passing by reference.
                    facetoface_user_cancel($session, $user, false, $null, get_string('userdeletedcancel', 'facetoface'));
                }
            }
        }

        foreach ($sus_users as $user) {
            // Cancel already suspended users facetoface signups.
            if ($signups = $DB->get_records('facetoface_signups', array('userid' => $user))) {
                foreach ($signups as $signup) {
                    $session = facetoface_get_session($signup->sessionid);
                    // Using $null, null fails because of passing by reference.
                    facetoface_user_cancel($session, $user, false, $null, get_string('usersuspendedcancel', 'facetoface'));
                }
            }
        }

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2014050400, 'facetoface');
    }

    if ($oldversion < 2014061600) {

        // Create the a userid field for the facetoface_notification_sent table.
        $table = new xmldb_table('facetoface_notification_sent');
        $field = new xmldb_field('userid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);

        // Only run the upgrade if the userid field doesn't exist.
        if (!$dbman->field_exists($table, $field)) {
            // Set time to unlimited as this could take a while.
            set_time_limit(0);

            // Wrap this in a transaction so we can't possibly wipe old records without adding the new.
            $transaction = $DB->start_delegated_transaction();

            // Get all facetoface notification sent records to be updated.
            $sql = "SELECT fns.*, fn.facetofaceid, fn.type, fn.conditiontype,
                        fn.booked, fn.waitlisted, fn.cancelled, fn.status, fn.issent
                    FROM {facetoface_notification_sent} fns
                    JOIN {facetoface_notification} fn
                    ON fns.notificationid = fn.id
                    WHERE fn.issent != 0
                    ORDER BY fn.facetofaceid, fns.sessionid";
            $notifications = $DB->get_records_sql($sql);
            $notificationssent = array();

            $total = count($notifications);
            if ($total > 0) {
                $index = 0;
                $pbar = new progress_bar('notificationsentupgrade', 500, true);
            }

            // Clear the old records out of the table before putting the new records in.
            $DB->delete_records('facetoface_notification_sent');

            // Add the userid field and foreign key to the facetoface_notification_sent table.
            $dbman->add_field($table, $field);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

            // Create new sent records for ALL previous records to prevent resending spam.
            foreach ($notifications as $notification) {
                $index++;
                $recipients = array();
                $status = array();

                // Attempt to match the correct set of recipients.
                switch ($notification->type) {
                    case MDL_F2F_NOTIFICATION_MANUAL :
                    case MDL_F2F_NOTIFICATION_SCHEDULED :
                        // Manual and scheduled notifications are user made and should have one of these set.
                        if (!empty($notification->booked)) {
                            // Need to check which type of booked recipients.
                            if ($notification->booked == MDL_F2F_RECIPIENTS_ALLBOOKED) {
                                $status[] = MDL_F2F_STATUS_BOOKED;
                            } else if ($notification->booked == MDL_F2F_RECIPIENTS_ATTENDED) {
                                // Exclude partially attended, see _get_recipients() in the facetoface notification class.
                                $status[] = MDL_F2F_STATUS_FULLY_ATTENDED;
                            } else if ($notification->booked == MDL_F2F_RECIPIENTS_NOSHOWS) {
                                $status[] = MDL_F2F_STATUS_NO_SHOW;
                            }
                        }

                        if (!empty($notification->waitlisted)) {
                            $status[] = MDL_F2F_STATUS_WAITLISTED;
                        }

                        if (!empty($notification->cancelled)) {
                            $status[] = MDL_F2F_STATUS_USER_CANCELLED;
                        }

                        // Default to all users if we don't have the data, to stop any potential resending.
                        if (empty($status)) {
                            $status[] = MDL_F2F_STATUS_BOOKED;
                            $status[] = MDL_F2F_STATUS_WAITLISTED;
                        }

                        break;
                    case MDL_F2F_NOTIFICATION_AUTO :
                        $trainers = array();
                        $trainers[] = MDL_F2F_CONDITION_TRAINER_CONFIRMATION;
                        $trainers[] = MDL_F2F_CONDITION_TRAINER_SESSION_CANCELLATION;
                        $trainers[] = MDL_F2F_CONDITION_TRAINER_SESSION_UNASSIGNMENT;

                        if (in_array($notification->conditiontype, $trainers)) {
                            $params = array('sessionid' => $notification->sessionid);
                            $recipients = $DB->get_fieldset_select('facetoface_session_roles', 'userid', $params);
                        } else if ($notification->conditiontype == MDL_F2F_CONDITION_CANCELLATION_CONFIRMATION) {
                            $status[] = MDL_F2F_STATUS_USER_CANCELLED;
                        } else if ($notification->conditiontype == MDL_F2F_CONDITION_BOOKING_REQUEST) {
                            $status[] = MDL_F2F_STATUS_REQUESTED;
                        } else if ($notification->conditiontype == MDL_F2F_CONDITION_BOOKING_CONFIRMATION) {
                            $status[] = MDL_F2F_STATUS_APPROVED;
                        } else if ($notification->conditiontype == MDL_F2F_CONDITION_DECLINE_CONFIRMATION) {
                            $status[] = MDL_F2F_STATUS_DECLINED;
                        } else if ($notification->conditiontype == MDL_F2F_CONDITION_WAITLISTED_CONFIRMATION) {
                            $status[] = MDL_F2F_STATUS_WAITLISTED;
                        } else {
                            $status[] = MDL_F2F_STATUS_WAITLISTED;
                            $status[] = MDL_F2F_STATUS_BOOKED;
                        }
                        break;
                }

                // Don't bother getting any recipients if there aren't any status set.
                if (!empty($status)) {
                    list($statussql, $statusparams) = $DB->get_in_or_equal($status);
                    $sql = "SELECT DISTINCT fs.userid
                            FROM {facetoface_signups} fs
                            JOIN {facetoface_signups_status} fss
                            ON fss.signupid = fs.id
                            WHERE fs.sessionid = ?
                            AND fss.superceded <> 1
                            AND fss.statuscode {$statussql}";
                    $params = array_merge(array($notification->sessionid), $statusparams);
                    $recipients = $DB->get_records_sql($sql, $params);
                }

                foreach ($recipients as $recipient) {
                    // Create records to be added to the facetoface_notification_sent table.
                    $record = new stdClass;
                    $record->notificationid = $notification->notificationid;
                    $record->sessionid = $notification->sessionid;
                    $record->userid = $recipient->userid;
                    $notificationssent[] = $record;
                }
                $pbar->update($index, $total, "Creating new notification sent data - record $index/$total");
            }

            // Split array into chuncks of 500 and bulk insert.
            $todbs = array_chunk($notificationssent, 500);
            foreach ($todbs as $todb) {
                $DB->insert_records_via_batch('facetoface_notification_sent', $todb);
            }

            $transaction->allow_commit();
        }


        upgrade_mod_savepoint(true, 2014061600, 'facetoface');
    }

    if ($oldversion < 2014082200) {

        // Changing the default of field waitlisted on table facetoface_notification to 0.
        $table = new xmldb_table('facetoface_notification');
        $field = new xmldb_field('waitlisted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'booked');
        $dbman->change_field_default($table, $field);

        // Define key userid (foreign) to be dropped form facetoface_notification_sent.
        $table = new xmldb_table('facetoface_notification_sent');
        $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $dbman->drop_key($table, $key); // We cannot check for key existence, just drop and recreate later.

        // Changing the default of field userid on table facetoface_notification_sent to 0.
        $table = new xmldb_table('facetoface_notification_sent');
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'sessionid');
        $dbman->change_field_default($table, $field);

        // Define key userid (foreign) to be readded to facetoface_notification_sent.
        $table = new xmldb_table('facetoface_notification_sent');
        $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $dbman->add_key($table, $key);

        // Define key facesess_use_fk (foreign) to be dropped form facetoface_sessions.
        $table = new xmldb_table('facetoface_sessions');
        $key = new xmldb_key('facesess_use_fk', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', array('id'));
        $dbman->drop_key($table, $key); // We cannot check for key existence, just drop and recreate later.

        // Make sure there are no nulls before changing to not null.
        $DB->execute("UPDATE {facetoface_sessions} SET usermodified = 0 WHERE usermodified IS NULL");

        // Changing nullability of field usermodified on table facetoface_sessions to not null.
        $table = new xmldb_table('facetoface_sessions');
        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '18', null, XMLDB_NOTNULL, null, null, 'timemodified');
        $dbman->change_field_notnull($table, $field);

        // Define key facesess_use_fk (foreign) to be added to facetoface_sessions.
        $table = new xmldb_table('facetoface_sessions');
        $key = new xmldb_key('facesess_use_fk', XMLDB_KEY_FOREIGN, array('usermodified'), 'user', array('id'));
        $dbman->add_key($table, $key);

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2014082200, 'facetoface');
    }

    if ($oldversion < 2014091700) {
        // Fix the default settings on the standard scheduled notifications.
        $bookedconditions = array(MDL_F2F_CONDITION_BEFORE_SESSION,
                                MDL_F2F_CONDITION_AFTER_SESSION,
                                MDL_F2F_CONDITION_SESSION_DATETIME_CHANGE,
                                );
        list($statussql, $statusparams) = $DB->get_in_or_equal($bookedconditions);
        $params = array_merge(array(1, MDL_F2F_NOTIFICATION_AUTO), $statusparams);
        $sql = "UPDATE {facetoface_notification}
                SET booked = ?
                WHERE type = ?
                AND conditiontype $statussql";
        $DB->execute($sql, $params);

        // Now fix the three standard cancellation messages.
        $cancelconditions = array(MDL_F2F_CONDITION_CANCELLATION_CONFIRMATION,
                                MDL_F2F_CONDITION_RESERVATION_CANCELLED,
                                MDL_F2F_CONDITION_RESERVATION_ALL_CANCELLED);
        list($statussql, $statusparams) = $DB->get_in_or_equal($cancelconditions);
        $params = array_merge(array(1, MDL_F2F_NOTIFICATION_AUTO), $statusparams);
        $sql = "UPDATE {facetoface_notification}
                SET cancelled = ?
                WHERE type = ?
                AND conditiontype $statussql";
        $DB->execute($sql, $params);

        // Inform waitlisted learners of session datetime changes.
        $sql = "UPDATE {facetoface_notification}
                SET waitlisted = ?
                WHERE type = ?
                AND conditiontype = ?";
        $DB->execute($sql, array(1, MDL_F2F_NOTIFICATION_AUTO, MDL_F2F_CONDITION_SESSION_DATETIME_CHANGE));
        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2014091700, 'facetoface');
    }
    
    // Totara 2.6.x upgrade line - bump all version numbers below after merge from t2-release-26 if necessary.
    
    // Add new selfapproval and selfapprovaltandc fields.
    if ($oldversion < 2014092300) {

        // Define field selfapproval to be added to facetoface_sessions.
        $table = new xmldb_table('facetoface_sessions');
        $field = new xmldb_field('selfapproval', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'usermodified');
        $field->setComment('Allow self approval.');

        // Conditionally launch add field selfapproval.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field selfapprovaltandc to be added to facetoface_sessions.
        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('selfapprovaltandc', XMLDB_TYPE_TEXT, 'big', null, null, null, null, 'reservedays');
        $field->setComment('Terms and conditions to display when to users when self approval is enabled');

        // Conditionally launch add field selfapprovaltandc and set to default value.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            $defaultvalue = get_string('selfapprovaltandccontents', 'facetoface');
            $DB->execute("UPDATE {facetoface} SET selfapprovaltandc = ?", array($defaultvalue));
        }

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2014092300, 'facetoface');
    }

    if ($oldversion < 2014092301) {

        // Define field declareinterest to be added to facetoface.
        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('declareinterest', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'completionstatusrequired');
        $field->setComment('Allow users to declare interest in the facetoface');

        // Conditionally launch add field declareinterest.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('interestonlyiffull', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'declareinterest');
        $field->setComment('Only allow users to declare interest if all sessions are full');

        // Conditionally launch add field interestonlyiffull.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table facetoface_interest to be created.
        $table = new xmldb_table('facetoface_interest');
        $table->setComment('Users who have declared interest in a facetoface session');

        // Adding fields to table facetoface_interest.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('facetoface', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timedeclared', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);

        // Adding keys to table facetoface_interest.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table facetoface_interest.
        $table->add_index('facetoface', XMLDB_INDEX_NOTUNIQUE, array('facetoface'));
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        // Conditionally launch create table for facetoface_interest.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2014092301, 'facetoface');
    }

    // Add new 'mincapacity' and 'cutoff' fields.
    if ($oldversion < 2014100900) {

        $table = new xmldb_table('facetoface_sessions');

        $field = new xmldb_field('mincapacity', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'usermodified');
        $field->setComment('The minimum number of people for this session to take place.');

        // Conditionally launch add field mincapacity.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Field defaults to 24 hours (86400 seconds).
        $field = new xmldb_field('cutoff', XMLDB_TYPE_INTEGER, '10', null, null, null, '86400', 'mincapacity');
        $field->setComment('The number of seconds before the session start by which the minimum capacity should be reached');

        // Conditionally launch add field cutoff.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014100900, 'facetoface');
    }

    if ($oldversion < 2014100901) {
        // Define field allowcancellationsdefault to be added to facetoface.
        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('allowcancellationsdefault', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'reservedays');

        // Conditionally launch add field allowcancellationsdefault.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field allowcancellations to be added to facetoface_sessions.
        $table = new xmldb_table('facetoface_sessions');
        $field = new xmldb_field('allowcancellations', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'roomid');

        // Conditionally launch add field allowcancellations.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2014100901, 'facetoface');
    }

    if ($oldversion < 2014100902) {
        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('selectpositiononsignup', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'interestonlyiffull');
        $field->setComment('Users with multiple positions will select one on signup');

        // Conditionally launch add field selectpositiononsignup.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('forceselectposition', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'selectpositiononsignup');
        $field->setComment('Error if no suitable position is available when signing up');

        // Conditionally launch add field forceselectposition.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('facetoface_signups');
        $field = new xmldb_field('positionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'bookedby');
        $field->setComment('If required, the position the user is doing the training for');

        // Conditionally launch add field positionid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('positiontype', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'positionid');
        $field->setComment('If required, the position type (prim, sec, asp) the user is doing the training for');

        // Conditionally launch add field positiontype.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('positionassignmentid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'positiontype');
        $field->setComment('If required, the position assignment the user is doing the training for');

        // Conditionally launch add field positiontype.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2014100902, 'facetoface');
    }

    if ($oldversion < 2014102100) {

        $table = new xmldb_table('facetoface_sessions');
        $field = new xmldb_field('waitlisteveryone', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'allowoverbook');
        $field->setComment('Will everyone be added to the waiting list');

        // Conditionally launch add field waitlisteveryone.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014102100, 'facetoface');
    }

    if ($oldversion < 2014102200) {

        $table = new xmldb_table('facetoface');
        $field = new xmldb_field('allowsignupnotedefault', XMLDB_TYPE_INTEGER, 1, null, XMLDB_NOTNULL, null, 1);
        $field->setComment("Allow 'User sign-up note' default");

        // Just double check the field doesn't somehow exist.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field selfapproval to be added to facetoface_sessions.
        $table = new xmldb_table('facetoface_sessions');
        $field = new xmldb_field('availablesignupnote', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $field->setComment('User sign-up note');

        // Conditionally launch add field selfapproval.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Facetoface savepoint reached.
        upgrade_mod_savepoint(true, 2014102200, 'facetoface');
    }

    return $result;
}
