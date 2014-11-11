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
 * @author Andrew Hancox <andrewdchancox@googlemail.com> on behalf of Synergy Learning
 * @package totara
 * @subpackage enrol_totara_facetoface
 */

/**
 * Face-to-Face Direct enrolment plugin - support for user totara_facetoface unenrolment.
 */

require_once(__DIR__ . '/../../config.php');

$enrolid = required_param('enrolid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$instance = $DB->get_record('enrol', array('id' => $enrolid, 'enrol' => 'totara_facetoface'), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login();
if (!is_enrolled($context)) {
    redirect(new moodle_url('/'));
}
require_login($course);

$plugin = enrol_get_plugin('totara_facetoface');

// Security defined inside following function.
if (!$plugin->get_unenrolself_link($instance)) {
    redirect(new moodle_url('/course/view.php', array('id' => $course->id)));
}

$PAGE->set_url('/enrol/totara_facetoface/unenrolself.php', array('enrolid' => $instance->id));
$PAGE->set_title($plugin->get_instance_name($instance));

if ($confirm and confirm_sesskey()) {
    $plugin->unenrol_user($instance, $USER->id);
    add_to_log($course->id, 'course', 'unenrol', '../enrol/users.php?id='.$course->id, $course->id);
    redirect(new moodle_url('/index.php'));
}

echo $OUTPUT->header();
$yesurl = new moodle_url($PAGE->url, array('confirm' => 1, 'sesskey' => sesskey()));
$nourl = new moodle_url('/course/view.php', array('id' => $course->id));
$message = get_string('unenrolselfconfirm', 'enrol_totara_facetoface', format_string($course->fullname));
echo $OUTPUT->confirm($message, $yesurl, $nourl);
echo $OUTPUT->footer();
