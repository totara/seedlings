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
 * @author Chris Wharton <chrisw@catalyst.net.nz>
 * @package totara
 * @subpackage enrol_totara_learningplan
 */

/**
 * Add new instance of enrol_totara_learningplan to course
 *
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

$id = required_param('id', PARAM_INT); // course id

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('moodle/course:enrolconfig', $context);
require_sesskey();

$enrol = enrol_get_plugin('totara_learningplan');

if ($enrol->get_newinstance_link($course->id)) {
    $enrol->add_instance($course);
}

redirect(new moodle_url('/enrol/instances.php', array('id' => $course->id)));
