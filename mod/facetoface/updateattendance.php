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
 * @subpackage cohort/rules
 */

/**
 * This file is an ajax back-end for updating attendance
 */
define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot.'/mod/facetoface/lib.php');

$data = required_param_array('datasubmission', PARAM_ALPHANUMEXT);

require_sesskey();

$data = (object)$data;
if (empty($data->s)) {
    print_error('error:incorrectcoursemodulesession', 'facetoface');
}
if (!$session = facetoface_get_session((int)$data->s)) {
    print_error('error:incorrectcoursemodulesession', 'facetoface');
}
if (!$facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface))) {
    print_error('error:incorrectfacetofaceid', 'facetoface');
}
if (!$course = $DB->get_record('course', array('id' => $facetoface->course))) {
    print_error('error:coursemisconfigured', 'facetoface');
}
if (!$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $course->id)) {
    print_error('error:incorrectcoursemodule', 'facetoface');
}
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('mod/facetoface:takeattendance', $context);

if (facetoface_take_attendance($data)) {
    echo json_encode(array('result' => 'success'));
    add_to_log($course->id, 'facetoface', 'take attendance', "view.php?id=$cm->id", $facetoface->id, $cm->id);
} else {
    add_to_log($course->id, 'facetoface', 'take attendance (FAILED)', "view.php?id=$cm->id", $facetoface->id, $cm->id);
}

exit();
