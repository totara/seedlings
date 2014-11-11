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
 * @package totara
 * @subpackage program
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/totara/program/lib.php');

$PAGE->set_context(context_system::instance());
require_login();

$id = required_param('id', PARAM_INT); // The program id
$htmltype = required_param('htmltype', PARAM_TEXT); // The type of html to return
$nojs = optional_param('nojs', 0, PARAM_INT);

$program = new program($id);

// Permissions check
if (!has_capability('totara/program:configurecontent', $program->get_context())) {
    exit;
}
// Check if programs or certifications are enabled.
if ($program->certifid) {
    check_certification_enabled();
} else {
    check_program_enabled();
}

$programcontent = $program->get_content();

if ($htmltype == 'multicourseset') { // if a new mulitcourse set is being added

    $courseids_str = required_param('courseids', PARAM_TEXT); // the ids of the courses to be added to the new set
    $suffix=  required_param('suf', PARAM_TEXT);
    $sortorder = required_param('sortorder', PARAM_INT); // the sort order of the new set
    $setprefixes_ce = required_param('setprefixes_ce', PARAM_TEXT); // the prefixes of the existing course sets
    $setprefixes_rc = required_param('setprefixes_rc', PARAM_TEXT); // the prefixes of the existing course sets
    $html = '';
    // retrieve the courses to be added to this course set
    $courseids = explode(':', $courseids_str);

    // create a new course set object containing the courses
    $newcourseset = new multi_course_set($id);
    $newcourseset->sortorder = $sortorder;
    $newcourseset->completiontype = COMPLETIONTYPE_ALL;
    $newcourseset->courses = array();
    $newcourseset->islastset = true;
    $newcourseset->label = get_string('legend:courseset', 'totara_program', $sortorder);

    foreach ($courseids as $courseid) {
        if ($course = $DB->get_record('course', array('id' => $courseid))) {
            $newcourseset->courses[] = $course;
        }
    }

    $coursesetprefix = $newcourseset->get_set_prefix();
    if ($suffix== '_ce') {
        $newcourseset->certifpath = CERTIFPATH_CERT;
        $setprefixesstr_ce = empty($setprefixes_ce) ? $coursesetprefix : $setprefixes_ce.','.$coursesetprefix;
        $setprefixesstr_rc = $setprefixes_rc;
    } else {
        $setprefixesstr_rc = empty($setprefixes_rc) ? $coursesetprefix : $setprefixes_rc.','.$coursesetprefix;
        $newcourseset->certifpath = CERTIFPATH_RECERT;
        $setprefixesstr_ce = $setprefixes_ce;
    }

    // retrieve the html for the new set
    $html .= $newcourseset->print_set_minimal();

    $data = array(
        'html'          => $html,
        'setprefixes_ce'   => $setprefixesstr_ce,
        'setprefixes_rc'   => $setprefixesstr_rc
    );
    echo json_encode($data);

} else if ($htmltype == 'competencyset') {
    // Std programs only
    $competencyid = required_param('competencyid', PARAM_INT);
    $sortorder = required_param('sortorder', PARAM_INT);
    $setprefixes_ce = required_param('setprefixes_ce', PARAM_TEXT); // the prefixes of the existing course sets

    $html = '';

    $newcourseset = new competency_course_set($id);
    $newcourseset->competencyid = $competencyid;
    $newcourseset->sortorder = $sortorder;
    $newcourseset->completiontype = $newcourseset->get_completion_type();
    $newcourseset->islastset = true;
    $newcourseset->label = get_string('legend:courseset', 'totara_program', $sortorder);

    $html .= $newcourseset->print_set_minimal();

    $coursesetprefix = $newcourseset->get_set_prefix();
    $setprefixesstr_ce = empty($setprefixes_ce) ? $coursesetprefix : $setprefixes_ce.','.$coursesetprefix;

    $data = array(
        'html'           => $html,
        'setprefixes_ce' => $setprefixesstr_ce
    );

    echo json_encode($data);

} else if ($htmltype == 'recurringset') {
    // Std programs only
    $courseid = required_param('courseid', PARAM_INT);
    $setprefixes_ce = required_param('setprefixes_ce', PARAM_TEXT); // the prefixes of the existing course sets

    $newcourseset = new recurring_course_set($id);
    $newcourseset->sortorder = 1;
    $newcourseset->isfirstset = true;
    $newcourseset->islastset = true;
    $newcourseset->label = get_string('legend:recurringcourseset', 'totara_program');

    if ($course = $DB->get_record('course', array('id' => $courseid))) {
        $newcourseset->course = $course;
    }

    $html = $newcourseset->print_set_minimal();

    $coursesetprefix = $newcourseset->get_set_prefix();
    $setprefixesstr_ce = empty($setprefixes_ce) ? $coursesetprefix : $setprefixes_ce.','.$coursesetprefix;

    $data = array(
        'html'           => $html,
        'setprefixes_ce' => $setprefixesstr_ce
    );

    echo json_encode($data);

} else if ($htmltype == 'amendcourses') {

    $courseids_str = required_param('courseids', PARAM_SEQUENCE); // the selected course ids
    $coursesetid = required_param('coursesetid', PARAM_INT);
    $sortorder = required_param('sortorder', PARAM_INT);
    $completiontype = required_param('completiontype', PARAM_INT);
    $coursesetprefix = required_param('coursesetprefix', PARAM_TEXT); // the prefix of the course set

    $courseids = explode(',', $courseids_str); // an array containing the selected course ids

    $setob = $DB->get_record('prog_courseset', array('id' => $coursesetid));

    $newcourseset = new multi_course_set($id, $setob, $coursesetprefix);
    $newcourseset->sortorder = $sortorder;
    $newcourseset->completiontype = $completiontype;

    // work out if we need to mark any courses as deleted
    if (!empty($newcourseset->courses)) {
        foreach ($newcourseset->courses as $course) {
            if (!in_array($course->id, $courseids)) {
                $newcourseset->courses_deleted_ids[] = $course->id;
            }
        }
    }

    // reset the courses array
    $newcourseset->courses = array();

    // add the selected courses to the course set object
    foreach ($courseids as $courseid) {
        if ($courseid && $course = $DB->get_record('course', array('id' => $courseid))) {
            $newcourseset->courses[] = $course;
        }
    }

    if ($nojs) {
        $deletedcourseshtml = $newcourseset->print_deleted_courses();
        $newcourseset->save_courses();
        $returnurl = new moodle_url('/totara/program/edit_content.php', array('id' => $id));
        redirect($returnurl);
    } else {
        $courselisthtml = $newcourseset->print_courses();
        $deletedcourseshtml = $newcourseset->print_deleted_courses();
        $data = array(
            'courselisthtml'        => $courselisthtml,
            'deletedcourseshtml'    => $deletedcourseshtml
        );

        echo json_encode($data);
    }
} else if ($htmltype == 'removecourse') {

    $courseid = required_param('courseid', PARAM_INT); // the selected course id
    $coursesetid = required_param('coursesetid', PARAM_INT);

    if ($setob = $DB->get_record('prog_courseset', array('id' => $coursesetid))) {
        $DB->delete_records('prog_courseset_course', array('courseid' => $courseid, 'coursesetid' => $coursesetid));
    }

    $returnurl = new moodle_url('/totara/program/edit_content.php', array('id' => $id));
    redirect($returnurl);
}
