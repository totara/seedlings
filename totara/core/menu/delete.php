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

use \totara_core\totara\menu\menu as menu;

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/lib/adminlib.php');

// Menu item id.
$id = optional_param('id', 0, PARAM_INT);
$confirm = optional_param('confirm', false, PARAM_BOOL);

admin_externalpage_setup('totaranavigation');

$context = context_system::instance();
$PAGE->set_context($context);

$canremove = true;
$item = menu::get($id);
$property = $item->get_property();
$node = menu::node_instance($property);
$title = $node->get_title();
$error = get_string('error:menuitemcannotremove', 'totara_core', $title);
$returnurl = new moodle_url('/totara/core/menu/index.php');

if ($confirm) {
    require_sesskey();
    try {
        $item->delete();
        totara_set_notification(get_string('menuitem:deletesuccess', 'totara_core'), $returnurl, array('class' => 'notifysuccess'));
    } catch (moodle_exception $e) {
        $canremove = false;
        $error = $e->getMessage();
    }
}

$children = $item->get_children();
$childtoremove = '';
if ($children) {
    $childtoremove = html_writer::start_tag('ul');
    foreach ($children as $child) {
        $property = $child->get_property();
        $childnode = menu::node_instance($property);
        if ($child->custom == menu::DB_ITEM) {
            $childtoremove .= html_writer::tag('li', $childnode->get_title());
        } else {
            $canremove = false;
            $childtoremove .= html_writer::tag('li', $childnode->get_title() . get_string('error:menuitemcannotremovechild', 'totara_core'));
        }
    }
    $childtoremove .= html_writer::end_tag('ul');
}

$url = new moodle_url('/totara/core/menu/delete.php', array('id' => $id));
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($title);
$PAGE->navbar->add($title, $url);
$PAGE->set_heading($title);

// Display page header.
echo $OUTPUT->header();

$url = new moodle_url('/totara/core/menu/delete.php', array('id' => $id, 'confirm' => 'true'));
$continue = new single_button($url, get_string('continue'), 'post');
$cancel = new single_button($returnurl, get_string('cancel'), 'get');

if ($canremove) {
    echo $OUTPUT->box_start('notifynotice');
    echo html_writer::tag('p', get_string('menuitem:delete', 'totara_core', $title));
    if ($children) {
        echo html_writer::tag('p', get_string('menuitem:deletechildren', 'totara_core', $title));
        echo $childtoremove;
    }
} else {
    echo $OUTPUT->box_start('notifyproblem');
    echo html_writer::tag('p', $error);
    if ($children && !isset($e)) {
        echo $childtoremove;
    }
}
echo $OUTPUT->box_end();
if ($canremove) {
    echo html_writer::tag('div', $OUTPUT->render($continue) . $OUTPUT->render($cancel), array('class' => 'buttons'));
} else {
    echo html_writer::tag('div', $OUTPUT->render($cancel), array('class' => 'buttons'));
}
echo $OUTPUT->footer();
