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
 * Totara navigation edit page.
 *
 * @package    totara
 * @subpackage navigation
 * @author     Oleg Demeshev <oleg.demeshev@totaralms.com>
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/lib/adminlib.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/totara/core/menu/edit_form.php');

// Category id.
$id    = optional_param('id', 0, PARAM_INT);
$title = optional_param('title', '', PARAM_MULTILANG);

admin_externalpage_setup('totaranavigation');

$PAGE->set_context(\context_system::instance());

$item = \totara_core\totara\menu\menu::get($id);
$property = $item->get_property();
$node = \totara_core\totara\menu\menu::node_instance($property);

$redirecturl = new moodle_url('/totara/core/menu/index.php');
$mform = new edit_form(null, array('item' => $property));
if ($mform->is_cancelled()) {
    redirect($redirecturl);
}
if ($data = $mform->get_data()) {
    try {
        if ((int)$id > 0) {
            $item->update($data);
        } else {
            $item->create($data);
        }
        totara_set_notification(get_string('menuitem:updatesuccess', 'totara_core'), $redirecturl, array('class' => 'notifysuccess'));
    } catch (moodle_exception $e) {
        totara_set_notification($e->getMessage());
    }
}

$url = new moodle_url('/totara/core/menu/edit.php', array('id' => $id, 'sesskey' => sesskey()));
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$title = ($id ? get_string('menuitem:editingx', 'totara_core', $node->get_title()) : get_string('menuitem:addnew', 'totara_core'));
$PAGE->set_title($title);
$PAGE->navbar->add($title, $url);
$PAGE->set_heading($title);

// Display page header.
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $mform->display();
echo $OUTPUT->footer();
