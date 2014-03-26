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
 * This class is an ajax back-end for updating attendance
 */
define('AJAX_SCRIPT', true);
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot.'/mod/facetoface/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$facetofaceid = required_param('facetofaceid', PARAM_INT);
$data = required_param_array('datasubmission', PARAM_ALPHANUMEXT);

$context = context_course::instance($courseid);
require_capability('mod/facetoface:takeattendance', $context);

// Cast to object.
$data = (object) $data;
$modinfo = get_fast_modinfo($courseid);
$cm = $modinfo->instances['facetoface'][$facetofaceid];

if (facetoface_take_attendance($data)) {
    echo json_encode(array('result' => 'success'));
    add_to_log($courseid, 'facetoface', 'take attendance', "view.php?id=$cm->id", $facetofaceid, $cm->id);
} else {
    add_to_log($courseid, 'facetoface', 'take attendance (FAILED)', "view.php?id=$cm->id", $facetofaceid, $cm->id);
}

exit();
