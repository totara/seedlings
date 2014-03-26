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

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

$searchterms = optional_param('room-name', '', PARAM_TEXT);
$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_TEXT);
$format = optional_param('format', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);

if ($format != '') {
    add_to_log(SITEID, 'reportbuilder', 'export embedded report', '', $report->fullname);
    $report->export_data($format);
    die;
}

// Check permissions
$PAGE->set_url($CFG->wwwroot . '/mod/facetoface/room/manage.php');
$PAGE->set_context(context_system::instance());
require_login(0, false);
require_capability('moodle/site:config', context_system::instance());

$returnurl = new moodle_url('/admin/settings.php', array('section' => 'modsettingfacetoface'));
$redirectto = new moodle_url('/mod/facetoface/room/manage.php');

// Handle actions
if ($delete && $confirm) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    if (!$room = $DB->get_record('facetoface_room', array('id' => $delete))) {
        print_error('error:roomdoesnotexist', 'facetoface');
    }

    $room_in_use = $DB->count_records_select('facetoface_sessions', "roomid = :id", array('id'=>$delete));

    if ($room_in_use) {
        print_error('error:roomisinuse', 'facetoface');
    }

    $DB->delete_records('facetoface_room', array('id' => $delete));

    totara_set_notification(get_string('roomdeleted', 'facetoface'), $redirectto, array('class' => 'notifysuccess'));
}

// Check for form submission
if (($data = data_submitted()) && !empty($data->bulk_update)) {
    // Check sesskey
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    if ($data->bulk_update == 'delete') {
        // Perform bulk delete action
        if ($rooms = $DB->get_records('facetoface_room', null, '', 'id')) {

            $selected = facetoface_get_selected_report_items('room', null, $rooms);

            foreach ($selected as $item) {
                $DB->delete_records('facetoface_room', array('id' => $item->id));
            }
        }
    }

    facetoface_reset_selected_report_items('room');
    redirect($redirectto);
}

// Display
local_js(array(
    TOTARA_JS_DIALOG,
    )
);

$PAGE->set_title(get_string('rooms', 'facetoface'));
$PAGE->set_heading('');
$PAGE->set_focuscontrol('');
$PAGE->set_cacheable(true);
$PAGE->navbar->add(get_string('rooms', 'facetoface'));
navigation_node::override_active_url($returnurl);
echo $OUTPUT->header();

// Print delete confirmation page
if ($delete) {
    if (!$room = $DB->get_record('facetoface_room', array('id' => $delete))) {
        print_error('error:roomdoesnotexist', 'facetoface');
    }

    echo $OUTPUT->confirm(get_string('deleteroomconfirm', 'facetoface', format_string($room->name)), "{$redirectto}?delete={$delete}&amp;sesskey=".sesskey()."&amp;confirm=1", $redirectto);
    echo $OUTPUT->footer();
    die();
}

$heading = get_string('rooms', 'facetoface');

echo $OUTPUT->heading($heading);

$str_edit = get_string('edit', 'moodle');
$str_remove = get_string('delete', 'moodle');
$str_remove_inuse = get_string('removeroominuse', 'facetoface');

$columns = array();
$headers = array();
$columns[] = 'name';
$headers[] = get_string('roomname', 'facetoface');
$columns[] = 'building';
$headers[] = get_string('building', 'facetoface');
$columns[] = 'address';
$headers[] = get_string('address', 'facetoface');
$columns[] = 'capacity';
$headers[] = get_string('capacity', 'facetoface');
$columns[] = 'options';
$headers[] = get_string('options', 'facetoface');

$title = 'facetoface_rooms';
$table = new flexible_table($title);
$table->define_baseurl($CFG->wwwroot . '/mod/facetoface/room/manage.php');
$table->define_columns($columns);
$table->define_headers($headers);
$table->set_attribute('class', 'generalbox mod-facetoface-room-list');
$table->sortable(true, 'name');
$table->no_sorting('options');
$table->setup();

if ($sort = $table->get_sql_sort()) {
    $sort = ' ORDER BY ' . $sort;
}

$sql = 'SELECT * FROM {facetoface_room} WHERE custom = 0';

$perpage = 25;

$totalcount = $DB->count_records_select('facetoface_room', 'custom = 0');

$table->initialbars($totalcount > $perpage);
$table->pagesize($perpage, $totalcount);

$rooms = $DB->get_records_sql($sql.$sort, array(), $table->get_page_start(), $table->get_page_size());

$rooms_in_use = array_keys($DB->get_records_sql('SELECT DISTINCT(roomid) FROM {facetoface_sessions}'));

foreach ($rooms as $room) {
    $row = array();
    $buttons = array();

    $row[] = $room->name;
    $row[] = $room->building;
    $row[] = $room->address;
    $row[] = $room->capacity;

    $editbutton = '';
    $deletebutton = '';

    $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/room/edit.php', array('id' => $room->id)), new pix_icon('t/edit', $str_edit));

    if (in_array($room->id, $rooms_in_use)) {
        $buttons[] = $OUTPUT->pix_icon('t/delete_gray', $str_remove_inuse, null, array('class' => 'disabled_action_icon'));
    } else {
        $buttons[] = $OUTPUT->action_icon(new moodle_url('/mod/facetoface/room/manage.php', array('delete' => $room->id)), new pix_icon('t/delete', $str_remove));
    }

    $row[] = implode($buttons, '');

    $table->add_data($row);
}

$table->finish_html();

// Action buttons
$addurl = new moodle_url('/mod/facetoface/room/edit.php');
$backurl = new moodle_url("/{$CFG->admin}/settings.php", array('section' => 'modsettingfacetoface'));

$addbutton = $OUTPUT->single_button($addurl, get_string('add'), 'get', array('class' => 'f2f-button'));
$backbutton = $OUTPUT->single_button($backurl, get_string('back'), 'get', array('class' => 'f2f-button'));

echo $OUTPUT->container($addbutton . $backbutton, 'continuebutton');
echo $OUTPUT->footer();
