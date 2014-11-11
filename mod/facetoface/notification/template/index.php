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

$deactivate = optional_param('deactivate', 0, PARAM_INT);
$activate = optional_param('activate', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);

$contextsystem = context_system::instance();

// Check permissions.
admin_externalpage_setup('modfacetofacetemplates');

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

    $notification->status = ($notification->id == $deactivate) ? 0 : 1;
    $DB->update_record('facetoface_notification_tpl', $notification);

    redirect($redirectto->out());
}

if ($delete) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    $notification = $DB->get_record('facetoface_notification_tpl', array('id' => $delete));
    if (!$notification) {
        print_error('error:notificationtemplatedoesnotexist', 'facetoface');
    }

    if (!$confirm or !confirm_sesskey()) {
        echo $OUTPUT->header();
        $confirmurl = new moodle_url($redirectto, array('delete' => $delete, 'confirm' => 1, 'sesskey' => sesskey()));
        echo $OUTPUT->confirm(get_string('deletenotificationtemplateconfirm', 'facetoface', format_string($notification->title)), $confirmurl->out(), $redirectto);
        echo $OUTPUT->footer();
        die;
    }

    $DB->delete_records('facetoface_notification_tpl', array('id' => $delete));

    totara_set_notification(get_string('notificationtemplatedeleted', 'facetoface'), $redirectto, array('class' => 'notifysuccess'));
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

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managenotificationtemplates', 'facetoface'));

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
    $rowclass = '';

    $row[] = $note_templ->title;

    if ($note_templ->status == 1) {
        $status = get_string('active');
    } else {
        $status = get_string('inactive');
    }
    $row[] = $status;

    $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/template/edit.php', array('id' => $note_templ->id, 'page' => $page)), new pix_icon('t/edit', $str_edit));

    if ($note_templ->status == 0) {
        $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/template/index.php', array('activate' => $note_templ->id, 'sesskey' => sesskey())), new pix_icon('t/show', $str_activate));
        $rowclass = 'dimmed_text';
    } else {
        $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/template/index.php', array('deactivate' => $note_templ->id, 'sesskey' => sesskey())), new pix_icon('t/hide', $str_deactivate));
    }

    $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/notification/template/index.php', array('delete' => $note_templ->id, 'sesskey' => sesskey())), new pix_icon('t/delete', $str_remove));

    $row[] = implode($buttons, '');

    $table->add_data($row, $rowclass);
}

$table->finish_html();

// Action buttons
$addurl = new moodle_url('/mod/facetoface/notification/template/edit.php');

echo $OUTPUT->container_start('buttons');
echo $OUTPUT->single_button($addurl, get_string('add'), 'get');
echo $OUTPUT->container_end();

echo $OUTPUT->footer();
