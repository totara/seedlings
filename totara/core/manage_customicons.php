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
 * @author Maria Torres <maria.torres@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/core/customicon_form.php');
require_once($CFG->dirroot .'/totara/core/utils.php');
require_once($CFG->dirroot .'/lib/formslib.php');
require_once($CFG->dirroot .'/totara/core/lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$heading = get_string('customicons', 'totara_core');
$url = new moodle_url('/totara/core/manage_customicons.php');

$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_context($context);
$PAGE->set_title($heading);
$PAGE->set_heading($SITE->fullname);
$PAGE->navbar->add($heading, $url);

$component = 'totara_core';
$filetypes = array('course', 'program');
$options = array('maxbytes' => $CFG->maxbytes,
    'subdirs'        => 0,
    'maxfiles'       => 99,
    'accepted_types' => 'web_image');

$data = new stdClass();
$data->id = 1;
foreach ($filetypes as $ft) {
    file_prepare_standard_filemanager($data, $ft, $options, $context, $component, $ft, 0);
}

$form = new upload_icon_form(null, array('data' => $data, 'filemanageroptions' => $options));

if ($form->is_cancelled()) {
    redirect(new moodle_url($url));
} else if ($data = $form->get_data()) {
    // Resize images before save them.
    $usercontext = context_user::instance($USER->id);
    totara_resize_images_filearea($usercontext->id, 'user', 'draft', $data->course_filemanager, 35, 35, true);
    totara_resize_images_filearea($usercontext->id, 'user', 'draft', $data->program_filemanager, 35, 35, true);
    foreach ($filetypes as $ft) {
        $formdata = file_postupdate_standard_filemanager($data, $ft, $options, $context, $component, $ft, 0);
    }
    totara_set_notification(get_string('successuploadicon', 'totara_core'), null, array('class' => 'notifysuccess'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
echo get_string('inforesizecustomicons', 'totara_core');

$form->display();

echo $OUTPUT->footer();