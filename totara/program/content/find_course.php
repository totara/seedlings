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
 * @author Tom Black <thomas.black@kineo.com>
 * @package totara
 * @subpackage program
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_courses.class.php');
require_once("{$CFG->dirroot}/totara/program/lib.php");

$PAGE->set_context(context_system::instance());
require_login();

$id = required_param('id', PARAM_INT); // Program id
$selected_courseids = optional_param('selectedcourseids', '', PARAM_SEQUENCE);
$categoryid = optional_param('parentid', 'cat0', PARAM_ALPHANUM); // Category id

require_capability('totara/program:configurecontent', program_get_context($id));

// Strip cat from begining of categoryid
$categoryid = (int) substr($categoryid, 3);

$selected = array();
if (!empty($selected_courseids)) {
    $selected_courseids_array = explode(',', $selected_courseids);
    foreach ($selected_courseids_array as $selected_courseid) {
        if ($course = $DB->get_record('course', array('id' => $selected_courseid))) {
            $selected[] = $course;
        }
    }
}

///
/// Setup dialog
///

// Load dialog content generator
$dialog = new totara_dialog_content_courses($categoryid);

$dialog->selected_title = 'currentlyselected';

// Add data
$dialog->requirecompletion = true;
$dialog->load_courses();

// Set selected items
$dialog->selected_items = $selected;

// Addition url parameters
$dialog->urlparams = array('id' => $id);

// Display page
echo $dialog->generate_markup();
