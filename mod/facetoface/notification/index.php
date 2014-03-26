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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage facetoface
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/mod/facetoface/lib.php');
require_once($CFG->dirroot.'/mod/facetoface/notification/lib.php');
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

$searchterms = optional_param('notification-title', '', PARAM_TEXT);
$update = required_param('update', PARAM_INT);
$display = optional_param('display', '', PARAM_ALPHANUM);
$deactivate = optional_param('deactivate', 0, PARAM_INT);
$activate = optional_param('activate', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_TEXT);


if (!$cm = $DB->get_record('course_modules', array('id' => $update))) {
    error('This course module doesn\'t exist');
}

if (!$course = $DB->get_record("course", array('id' => $cm->course))) {
    error('This course doesn\'t exist');
}

if (!$facetoface = $DB->get_record('facetoface', array('id' => $cm->instance))) {
    error('This facetoface doesn\'t exist');
}

$url = new moodle_url('/mod/facetoface/notification/index.php', array('update' => $cm->id));
$PAGE->set_url($url);

$redirectto = new moodle_url('/mod/facetoface/notification/index.php', array('update' => $update));

if ($display !== '' && !in_array($display, array(MDL_F2F_NOTIFICATION_MANUAL, MDL_F2F_NOTIFICATION_SCHEDULED, MDL_F2F_NOTIFICATION_AUTO))) {
    redirect($redirectto);
    die();
}

require_login($course, true, $cm); // needed to setup proper $COURSE
$context = context_module::instance($cm->id);
require_capability('moodle/course:manageactivities', $context);


// Check for actions
if ($deactivate || $activate) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    $id = max($deactivate, $activate);
    $notification = new facetoface_notification(array('id' => $id), true);
    if (!$notification->id) {
        print_error('error:notificationdoesnotexist', 'facetoface');
    }

    $notification->status = $deactivate ? 0 : 1;
    $notification->update();

    redirect($redirectto);
}

// Check if we are deleting
if ($delete && $confirm) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    $notification = new facetoface_notification(array('id' => $delete), true);
    if (!$notification->id) {
        print_error('error:notificationdoesnotexist', 'facetoface');
    }

    $notification->delete();

    totara_set_notification(get_string('notificationdeleted', 'facetoface'), $redirectto, array('class' => 'notifysuccess'));
}

// Check for form submission
if (($data = data_submitted()) && !empty($data->bulk_update)) {
    // Check sesskey
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    if (in_array($data->bulk_update, array('set_active', 'set_inactive'))) {
        // Perform bulk action
        // Get all notifications
        $notifications = $DB->get_records_sql(
               'SELECT
                    id,
                    status
                FROM
                    {facetoface_notification}
                WHERE
                    facetofaceid = ?',
                array($facetoface->id));

        if (!empty($notifications)) {
            $selected = facetoface_get_selected_report_items('notification', $update, $notifications);

            foreach ($selected as $item) {
                $notification = new facetoface_notification(array('id' => $item->id), true);
                $notification->status = $data->bulk_update == 'set_active' ? 1 : 0;
                $notification->update();
            }
        }
    }

    facetoface_reset_selected_report_items('notification', $update);
    redirect($redirectto);
}

$streditinga = get_string('editinga', 'moodle', 'facetoface');
$strmodulenameplural = get_string('modulenameplural', 'facetoface');

$PAGE->set_pagelayout('standard');
$PAGE->set_title($streditinga);
$PAGE->set_heading('');
echo $OUTPUT->header();

$icon = '<img src="'.$OUTPUT->pix_url('/facetoface/icon') . '" alt=""/>';


// Print delete confirmation page
if ($delete) {
    $notification = new facetoface_notification(array('id' => $delete), true);
    if (!$notification->id) {
        print_error('error:notificationdoesnotexist', 'facetoface');
    }

    $confirmurl = clone($redirectto);
    $confirmurl->param('delete', $delete);
    $confirmurl->param('sesskey', sesskey());
    $confirmurl->param('confirm', '1');
    echo $OUTPUT->confirm(get_string('deletenotificationconfirm', 'facetoface', format_string($notification->title)), $confirmurl, $redirectto);
    echo $OUTPUT->footer($course);
    unset($confirmurl);
    die();
}

$heading = get_string('notifications', 'facetoface');
$report_data = array(
    'display'       => $display,
    'update'        => $update,
    'facetofaceid'  => $facetoface->id
);


$notifications = $DB->get_records('facetoface_notification', array('facetofaceid' => $facetoface->id), 'title,type');

echo $OUTPUT->heading_with_help($heading, 'notifications', 'facetoface');

$str_edit = get_string('edit', 'moodle');
$str_active = get_string('setactive', 'facetoface');
$str_inactive = get_string('setinactive', 'facetoface');
$str_duplicate = get_string('duplicate');
$str_delete = get_string('delete');

$columns = array();
$headers = array();
$columns[] = 'title';
$headers[] = get_string('notificationtitle', 'facetoface');
$columns[] = 'recipients';
$headers[] = get_string('recipients', 'facetoface');
$columns[] = 'type';
$headers[] = get_string('type', 'facetoface');
$columns[] = 'status';
$headers[] = get_string('status', 'facetoface');
$columns[] = 'options';
$headers[] = get_string('options', 'facetoface');

$title = 'facetoface_notifications';
$table = new flexible_table($title);
$table->define_baseurl($CFG->wwwroot . '/mod/facetoface/notification/index.php');
$table->define_columns($columns);
$table->define_headers($headers);
$table->set_attribute('class', 'generalbox mod-facetoface-notification-list');
$table->setup();

foreach ($notifications as $note) {
    $row = array();
    $buttons = array();

    $row[] = $note->title;

    // Create a notification object so we can figure out
    // the recipient string
    $notification = new facetoface_notification();
    $notification->booked = $note->booked;
    $notification->waitlisted = $note->waitlisted;
    $notification->cancelled = $note->cancelled;

    $row[] = $notification->get_recipient_description();

    //Type
    switch ($note->type) {
        case MDL_F2F_NOTIFICATION_MANUAL:
            $typestr = get_string('notificationtype_1', 'facetoface');
            break;

        case MDL_F2F_NOTIFICATION_SCHEDULED:
            $typestr = get_string('notificationtype_2', 'facetoface');
            break;

        case MDL_F2F_NOTIFICATION_AUTO:
            $typestr = get_string('notificationtype_4', 'facetoface');
            break;

        default:
            $typestr = '';
    }

    //Status
    if ($note->status == 1) {
        $statusstr = get_string('active');
    } else {
        $statusstr = get_string('inactive');
    }

    $row[] = $typestr;
    $row[] = $statusstr;

    $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/edit.php', array('f' => $facetoface->id, 'id' => $note->id)), new pix_icon('t/edit', $str_edit));

    if ($note->status == 1) {
        $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/index.php', array('update' => $update, 'deactivate' => $note->id, 'sesskey' => sesskey())), new pix_icon('t/hide', $str_inactive));
    } else {
        $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/index.php', array('update' => $update, 'activate' => $note->id, 'sesskey' => sesskey())), new pix_icon('t/show', $str_active));
    }

    if ($note->type != MDL_F2F_NOTIFICATION_AUTO) {
        $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/edit.php', array('f' => $facetoface->id, 'id' => $note->id, 'duplicate' => '1')), new pix_icon('t/copy', $str_duplicate));

        $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/index.php', array('update' => $update, 'delete' => $note->id, 'sesskey' => sesskey())), new pix_icon('t/delete', $str_delete));
    }

    $row[] = implode(' ', $buttons);

    $table->add_data($row);
}

$table->finish_html();

$addlink = new moodle_url('/mod/facetoface/notification/edit.php');

echo $OUTPUT->single_button(new moodle_url($addlink, array('f' => $cm->instance)), get_string('add'), 'get');
echo $OUTPUT->footer($course);
