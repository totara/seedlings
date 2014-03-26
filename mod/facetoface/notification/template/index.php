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

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

$searchterms = optional_param('template-title', '', PARAM_TEXT);
$deactivate = optional_param('deactivate', 0, PARAM_INT);
$activate = optional_param('activate', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);

// Setup page and check permissions
$contextsystem = context_system::instance();
$PAGE->set_url($CFG->wwwroot . '/mod/facetoface/notification/template/index.php');
$PAGE->set_context($contextsystem);

require_login(0, false);
require_capability('moodle/site:config', $contextsystem);

$redirectto = new moodle_url('/mod/facetoface/notification/template/');

// Check for actions
if ($deactivate || $activate) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    $id = max($deactivate, $activate);
    $notification = $DB->get_record('facetoface_notification_tpl', array('id' => $id));
    if (!$notification) {
        print_error('error:notificationtemplatedoesnotexist', 'facetoface');
    }

    $notification->status = $deactivate ? 0 : 1;
    $DB->update_record('facetoface_notification_tpl', $notification);

    redirect($redirectto->out());
}

if ($delete && $confirm) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    $notification = $DB->get_record('facetoface_notification_tpl', array('id' => $delete));
    if (!$notification) {
        print_error('error:notificationtemplatedoesnotexist', 'facetoface');
    }

    $DB->delete_records('facetoface_notification_tpl', array('id' => $delete));

    totara_set_notification(get_string('notificationtemplatedeleted', 'facetoface'), $redirectto->out(), array('class' => 'notifysuccess'));
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
            "
                SELECT
                    id,
                    status
                FROM
                    {facetoface_notification_tpl}
            "
        );

        if (!empty($notifications)) {
            $selected = facetoface_get_selected_report_items('template', null, $notifications);

            foreach ($selected as $item) {
                $item->status = $data->bulk_update == 'set_active' ? 1 : 0;
                $DB->update_record('facetoface_notification_tpl', $item);
            }
        }
    }
    facetoface_reset_selected_report_items('template');

    redirect($redirectto->out());
}


// Header
local_js();

$str_edit = get_string('edit', 'moodle');
$str_remove = get_string('delete', 'moodle');
$str_activate = get_string('activate', 'facetoface');
$str_deactivate = get_string('deactivate', 'facetoface');

$url = new moodle_url('/admin/settings.php', array('section' => 'modsettingfacetoface'));

$PAGE->set_title(get_string('notificationtemplates', 'facetoface'));
$PAGE->set_heading('');
$PAGE->set_focuscontrol('');
$PAGE->set_cacheable(true);
$PAGE->navbar->add(get_string('notificationtemplates', 'facetoface'));
navigation_node::override_active_url($url);
echo $OUTPUT->header();

// Print delete confirmation page
if ($delete) {
    $notification = $DB->get_record('facetoface_notification_tpl', array('id' => $delete));
    if (!$notification) {
        print_error('error:notificationtemplatedoesnotexist', 'facetoface');
    }

    $confirmurl = clone($redirectto);
    $confirmurl->param('delete', $delete);
    $confirmurl->param('sesskey', sesskey());
    $confirmurl->param('confirm', '1');
    echo $OUTPUT->confirm(get_string('deletenotificationtemplateconfirm', 'facetoface', format_string($notification->title)), $confirmurl->out(), $redirectto);
    echo $OUTPUT->footer();
    die();
}


$heading = get_string('notificationtemplates', 'facetoface');
$report_data = array();

echo $OUTPUT->heading($heading);

$notification_templates = $DB->get_records('facetoface_notification_tpl', array(), 'id');

$columns = array();
$headers = array();
$columns[] = 'title';
$headers[] = get_string('notificationtitle', 'facetoface');
$columns[] = 'status';
$headers[] = get_string('status');
$columns[] = 'options';
$headers[] = get_string('options', 'facetoface');

$title = 'facetoface_notification_templates';

$table = new flexible_table($title);
$table->define_baseurl($CFG->wwwroot . '/mod/facetoface/notification/template/index.php');
$table->define_columns($columns);
$table->define_headers($headers);
$table->set_attribute('class', 'generalbox mod-facetoface-notification-template-list');
$table->sortable(true, 'title');
$table->no_sorting('options');
$table->setup();

if ($sort = $table->get_sql_sort()) {
    $sort = ' ORDER BY ' . $sort;
}

$sql = 'SELECT * FROM {facetoface_notification_tpl}';

$perpage = 25;

$totalcount = $DB->count_records('facetoface_notification_tpl');

$table->initialbars($totalcount > $perpage);
$table->pagesize($perpage, $totalcount);

$notification_templates = $DB->get_records_sql($sql.$sort, array(), $table->get_page_start(), $table->get_page_size());

foreach ($notification_templates as $note_templ) {
    $row = array();
    $buttons = array();

    $row[] = $note_templ->title;

    if ($note_templ->status == 1) {
        $status = get_string('active');
    } else {
        $status = get_string('inactive');
    }
    $row[] = $status;

    $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/template/edit.php', array('id' => $note_templ->id)), new pix_icon('t/edit', $str_edit));

    if ($note_templ->status == 0) {
        $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/template/index.php', array('activate' => $note_templ->id, 'sesskey' => sesskey())), new pix_icon('t/show', $str_activate));
    } else {
        $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/template/index.php', array('deactivate' => $note_templ->id, 'sesskey' => sesskey())), new pix_icon('t/hide', $str_deactivate));
    }

    $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/template/index.php', array('delete' => $note_templ->id, 'sesskey' => sesskey())), new pix_icon('t/delete', $str_remove));

    $row[] = implode($buttons, '');

    $table->add_data($row);
}

$table->finish_html();

// Action buttons
$addurl = new moodle_url('/mod/facetoface/notification/template/edit.php');
$backurl = new moodle_url('/' . $CFG->admin . '/settings.php', array('section' => 'modsettingfacetoface'));

$addbutton = $OUTPUT->single_button($addurl, get_string('add'), 'get', array('class' => 'f2f-button'));
$backbutton = $OUTPUT->single_button($backurl, get_string('back'), 'get', array('class' => 'f2f-button'));

echo $OUTPUT->container($addbutton . $backbutton, 'continuebutton');
echo $OUTPUT->footer();
