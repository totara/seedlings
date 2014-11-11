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
 * @package totara
 * @subpackage facetoface
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/facetoface/room/room_form.php');

$id = optional_param('id', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

admin_externalpage_setup('modfacetofacerooms');
$systemcontext = context_system::instance();

$roomlisturl = new moodle_url('/mod/facetoface/room/manage.php', array('page' => $page));

$editoroptions = array(
    'noclean'  => false,
    'maxfiles' => EDITOR_UNLIMITED_FILES,
    'context'  => $systemcontext,
);

if ($id == 0) {
    $room = new stdClass();
    $room->id = 0;
    $room->description = '';
    $room->status = 1;
} else {
    $room = $DB->get_record('facetoface_room', array('id' => $id, 'custom' => 0), '*', MUST_EXIST);
}
$room->descriptionformat = FORMAT_HTML;
$room->page = $page;
$room = file_prepare_standard_editor($room, 'description', $editoroptions, $systemcontext, 'facetoface', 'room', $room->id);

$form = new mod_facetoface_room_form(null, array('id' => $room->id, 'editoroptions' => $editoroptions));
$form->set_data($room);

if ($form->is_cancelled()) {
    redirect($roomlisturl);
} else if ($data = $form->get_data()) {
    if (isset($data->submitbutton)) {

        $todb = new stdClass();
        $todb->name = $data->name;
        $todb->building = $data->building;
        $todb->address = $data->address;
        $todb->capacity = $data->capacity;
        $todb->type = $data->type;
        $todb->custom = 0;
        $todb->timemodified = time();

        if ($data->id == 0) {
            // Create new room.
            $todb->timecreated = $todb->timemodified;

            $room->id = $DB->insert_record('facetoface_room', $todb);

            // Update description.
            $description_data = file_postupdate_standard_editor($data, 'description', $editoroptions, $systemcontext, 'facetoface', 'room', $room->id);
            $DB->set_field('facetoface_room', 'description', $description_data->description, array('id' => $room->id));

            totara_set_notification(get_string('roomcreatesuccess', 'facetoface'), $roomlisturl, array('class' => 'notifysuccess'));
        } else {
            // Update room.
            $todb->id = $room->id;

            $DB->update_record('facetoface_room', $todb);

            // Update description
            $description_data = file_postupdate_standard_editor($data, 'description', $editoroptions, $systemcontext, 'facetoface', 'room', $room->id);
            $DB->set_field('facetoface_room', 'description', $description_data->description, array('id' => $room->id));

            totara_set_notification(get_string('roomupdatesuccess', 'facetoface'), $roomlisturl, array('class' => 'notifysuccess'));
        }
    }
}

$url = new moodle_url('/admin/settings.php', array('section' => 'modsettingfacetoface'));

if ($id == 0) {
    $page_heading = get_string('addroom', 'facetoface');
} else {
    $page_heading = get_string('editroom', 'facetoface');
}

echo $OUTPUT->header();
echo $OUTPUT->heading($page_heading);

$form->display();

echo $OUTPUT->footer();
