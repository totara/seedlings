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
 * @author Moises Burgos <moises.burgos@totaralms.com>
 * @package totara
 * @subpackage admin_user
 */

/**
 * Script for bulk enabling/disabling totara sync checkbox for users
 */

require_once('../../config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once($CFG->libdir . '/adminlib.php');

$confirm = optional_param('confirm', 0, PARAM_BOOL);
$enable = optional_param('enable', 0, PARAM_BOOL);

require_login();
admin_externalpage_setup('userbulk');
require_capability('moodle/user:update', context_system::instance());

$return = $CFG->wwwroot . '/' . $CFG->admin . '/user/user_bulk.php';

if (empty($SESSION->bulk_users)) {
    redirect($return);
}

echo $OUTPUT->header();
$errornotifications = '';
$badusernotification = array();

if ($confirm) {
    require_sesskey();
    $errorcounter = 0;
    $usercount = 0;
    // Slice the amount of users if there are more than 1000 users (Oracle limitation).
    while ($users = totara_pop_n($SESSION->bulk_users, 1000)) {
        // Set the keys of users to be the values for ease of removal. get_in_or_equal uses array_values so should cause no issues.
        $users = array_combine($users, $users);
        list($in, $params) = $DB->get_in_or_equal($users);
        // Only enable sync if the user has an idnumber.
        if ($enable) {
            $badusers = $DB->get_recordset_select('user', "idnumber = '' AND id $in", $params);
            foreach ($badusers as $baduser) {
                $badusernotification[] = fullname($baduser, true);
                $errorcounter++;
                unset($users[$baduser->id]);
            }
        }
        // Recalculate the in_or_equal with the remaining good users (if any).
        if (!empty($users)) {
            list($in, $params) = $DB->get_in_or_equal($users);
            $rs = $DB->get_recordset_select('user', "id $in", $params);
            foreach ($rs as $user) {
                if (!$DB->set_field('user', 'totarasync', $enable, array('id' => $user->id))) {
                    $errornotifications .= $OUTPUT->notification(get_string('toggletotarasyncerror', 'totara_core', fullname($user, true)), 'notifynotice');
                    $errorcounter++;
                } else {
                    $usercount++;
                }
            }
            $rs->close();
        }
    }

    // Show info on bad users
    if(!empty($badusernotification)) {
        echo $OUTPUT->notification(get_string('toggletotarasyncerror:noidnumber', 'totara_core', implode(',', $badusernotification)), 'notifynotice');
    }

    // Show users who could not be updated if any.
    if ($errornotifications) {
        echo $errornotifications;
    }
    // Check if at least one user was updated.
    if (($usercount) > 0) {
        echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
    }
    echo $OUTPUT->continue_button($return);
} else {
    $userlist = array();
    $bulkusers = $SESSION->bulk_users;
    while ($users = totara_pop_n($bulkusers, 1000)) {
        // Check the selected users have idnumbers.
        list($in, $params) = $DB->get_in_or_equal($users);
        // Warn if the user has no idnumber - note we may still want to disable sync on users who should not have it turned on.
        $badusers = $DB->get_recordset_select('user', "idnumber = '' AND id $in", $params);
        foreach ($badusers as $baduser) {
            $badusernotification[] = fullname($baduser, true);
        }
        $userlist += $DB->get_records_select_menu('user', "id $in", $params, 'fullname', 'id,'.$DB->sql_fullname().' AS fullname', 0, MAX_BULK_USERS);
    }

    // Show info on bad users.
    if(!empty($badusernotification)) {
        echo $OUTPUT->notification(get_string('toggletotarasyncerror:noidnumber', 'totara_core', implode(',', $badusernotification)), 'notifynotice');
    }

    $usernames = implode(', ', $userlist);
    if (count($SESSION->bulk_users) > MAX_BULK_USERS) {
        $usernames .= ', ...';
    }
    echo $OUTPUT->heading(get_string('confirmation', 'admin'));

    $options = array(0 => get_string('disable'), 1 => get_string('enable'));
    $formcontinue = $OUTPUT->single_button(new moodle_url('user_bulk_toggletotarasync.php', array('confirm' => 1)), get_string('continue'));
    $formcancel = $OUTPUT->single_button(new moodle_url('user_bulk.php'), get_string('cancel'), 'get');

    echo $OUTPUT->box_start('generalbox', 'notice');
    echo html_writer::tag('p', get_string('enabledisabletotarasync', 'totara_core', $usernames));
    echo html_writer::start_tag('form', array('action' => me(), 'method' => 'get'));
    echo html_writer::select($options, 'enable', 1, null);
    echo html_writer::tag('div', $formcontinue . $formcancel, array('class' => 'buttons'));
    echo html_writer::end_tag('form');
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();
