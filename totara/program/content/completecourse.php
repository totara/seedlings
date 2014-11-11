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
 * @package    totara
 * @subpackage program
 * @author     Russell England <russell.england@catalyst-eu.net>
 */

/**
 * @todo : make this a dialog
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/totara/program/lib.php');
require_once($CFG->dirroot . '/totara/program/content/completecourse_form.php');
require_once($CFG->dirroot . '/completion/completion_completion.php');

$userid = required_param('userid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$programid = required_param('progid', PARAM_INT);

require_login();

if (!$program = new program($programid)) {
    print_error('error:programid', 'totara_program');
}

// Check if programs or certifications are enabled.
if ($program->certifid) {
    check_certification_enabled();
} else {
    check_program_enabled();
}

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

// Permissions.
$usercontext = context_user::instance($userid);
if ((!totara_is_manager($userid)
    || !has_capability('totara/program:markstaffcoursecomplete', $usercontext)) && !is_siteadmin()) {
    // If this doesn't then we have show a permissions error.
    print_error('error:notmanagerornopermissions', 'totara_program');
}

$coursecontext = context_course::instance($course->id);

$params = array();
$params['userid'] = $userid;
$params['courseid'] = $courseid;
$params['progid'] = $programid;

$heading = get_string('completecourse', 'totara_program');
$PAGE->set_context($coursecontext);
$PAGE->set_heading(format_string($heading));
$PAGE->set_title(format_string($heading));
$PAGE->set_url('/totara/program/content/completecourse.php', $params);
prog_add_required_learning_base_navlinks($userid);
if (!$progname = $DB->get_field('prog', 'fullname', array('id' => $programid))) {
    print_error('invalidprogid');
}
$progurl = new moodle_url('/totara/program/required.php', array('userid' => $userid, 'id' => $programid));

$PAGE->navbar->add(format_string($progname), $progurl);
$PAGE->navbar->add($heading);

$completion = new completion_completion(array('userid' => $userid, 'course' => $courseid));
if ($completion->is_complete()) {
    // Toggle as incomplete
    $completion->delete();
    totara_set_notification(get_string('incompletecourse', 'totara_program'), $progurl, array('class' => 'notifysuccess'));
}

$mform = new completecourse_form();

if ($mform->is_cancelled()) {
    redirect($progurl);
} else if ($data = $mform->get_data()) {
    // Save and return to prog
    $completion->rpl = $data->rpl;
    $completion->rplgrade = $data->rplgrade;
    $completion->mark_complete($data->timecompleted);
    if (!empty($data->rpl)) {
        $message = get_string('completedcourserpl', 'totara_program');
    } else {
        $message = get_string('completedcoursemanual', 'totara_program');
    }
    totara_set_notification($message, $progurl, array('class' => 'notifysuccess'));
} else {
    $data = new stdClass();
    $data->courseid = $courseid;
    $data->userid = $userid;
    $data->progid = $programid;
    $mform->set_data($data);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$mform->display();

echo $OUTPUT->footer();
