<?php

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.com                                            //
//                                                                       //
// Copyright (C) 1999 onwards Martin Dougiamas  http://dougiamas.com     //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

require_once('../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_courses.class.php');

///
/// Setup / loading data
///

// Course id
$id = required_param('id', PARAM_INT);

// Category id
$categoryid = optional_param('parentid', 'cat0', PARAM_ALPHANUM);

// Strip cat from begining of categoryid
$categoryid = (int) substr($categoryid, 3);

// Basic access control checks
if ($id) { // editing course

    if($id == SITEID){
        // don't allow editing of  'site course' using this from
        print_error('cannoteditsiteform');
    }

    if (!$course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }

    require_login($course->id);
    require_capability('moodle/course:update', context_course::instance($course->id));

}

///
/// Load data
///

// Disabled courses are those that:
// - are not the current course
// - are not already a dependency of this course
// - do not have this course as a dependency
$sql = "
    SELECT
        c.id,
        c.fullname
    FROM
        {course} c
    LEFT JOIN
        {course_completion_criteria} cc
     ON cc.courseinstance = c.id
    AND cc.course = ?
    LEFT JOIN
        {course_completion_criteria} ccc
     ON ccc.course = c.id
    AND cc.courseinstance = ?
    WHERE
        c.enablecompletion = ?
    AND (
        c.id = ?
        OR ccc.id IS NOT NULL
        OR cc.id IS NOT NULL
        )
    AND c.visible = 1
    ORDER BY
        c.sortorder ASC
";
$parms = array($id, $id, COMPLETION_ENABLED, $id);
$disabled = $DB->get_records_sql($sql, $parms);

///
/// Setup dialog
///
// Load dialog content generator
$dialog = new totara_dialog_content_courses($categoryid, false);
$dialog->requirecompletioncriteria = true;
$dialog->load_data();

// Add disabled data
$dialog->disabled_items += $disabled;

// Addition url parameters
$dialog->urlparams = array('id' => $id);

// Display page
echo $dialog->generate_markup();
