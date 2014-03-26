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
 * @package    mod
 * @subpackage feedback
 * @author     Russell England <russell.england@catalyst-eu.net>
 * @copyright  Catalyst IT Ltd 2013 <http://catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 *
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/mod/feedback/lib.php');
require_once($CFG->dirroot . '/mod/feedback/viewarchive_form.php');
require_once($CFG->dirroot . '/course/lib.php');

$filters['feedbackid'] = required_param('feedbackid', PARAM_INT); // Feedback ID
$filters['page'] = optional_param('page', 0, PARAM_INT);
$filters['perpage'] = optional_param('perpage', 20, PARAM_INT);
$filters['historyid'] = optional_param('historyid', null, PARAM_INT);    // feedback_completed_history id
$filters['username'] = optional_param('username', null, PARAM_TEXT);
$filters['lastname'] = optional_param('lastname', null, PARAM_TEXT);
$filters['firstname'] = optional_param('firstname', null, PARAM_TEXT);

if (!$feedback = $DB->get_record('feedback', array('id' => $filters['feedbackid']))) {
    print_error(get_string('error:feedbacknotfound', 'feedback', $filters['feedbackid']));
}
$filters['courseid'] = $feedback->course;

if (!$course = $DB->get_record('course', array('id' => $feedback->course))) {
    print_error("coursemisconf");
}

if (!$cm = get_coursemodule_from_instance('feedback', $feedback->id, $course->id)) {
    print_error("invalidcoursemodule");
}

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

if (!empty($filters['historyid']) && (!$DB->record_exists('feedback_completed_history', array('id' => $filters['historyid'])))) {
    print_error(get_string('error:completedhistorynotfound', 'feedback', $filters['historyid']));
}

require_capability('mod/feedback:viewarchive', $context);

$heading = get_string('viewarchive', 'feedback');
$PAGE->set_context($context);
$PAGE->set_heading(format_string($heading));
$PAGE->set_title(format_string($heading));
$PAGE->set_url('/mod/feedback/viewarchive.php', $filters);

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

if (!empty($filters['historyid'])) {
    // Display feedback answers

    $sql = 'SELECT c.id as completedid,
                    c.timemodified,
                    f.id as feedbackid,
                    f.name AS feedbackname,
                    f.autonumbering,
                    u.firstname,
                    u.lastname,
                    course.shortname coursename
            FROM {feedback_completed_history} c
            INNER JOIN {feedback} f ON f.id = c.feedback
            INNER JOIN {user} u ON u.id = c.userid
            INNER JOIN {course} course ON course.id = f.course
            WHERE c.id = :completedid';
    if (!$feedback = $DB->get_record_sql($sql, array('completedid' => $filters['historyid']))) {
        echo $OUTPUT->notify(get_string('error:completedhistorynotfound', 'feedback', $filters['historyid']));
    } else {
        echo $OUTPUT->heading(format_text($feedback->coursename . ' : ' . $feedback->feedbackname));

        $sql = 'SELECT i.*, v.value
                FROM {feedback_item} i
                LEFT JOIN {feedback_value_history} v ON v.item = i.id AND v.completed = :completedid
                WHERE i.feedback = :feedbackid
                AND i.typ <> :typ
                ORDER BY i.position';
        if ($items = $DB->get_records_sql($sql, array('completedid' => $filters['historyid'], 'feedbackid' => $feedback->feedbackid, 'typ' => 'pagebreak'))) {
            $align = right_to_left() ? 'right' : 'left';

            echo $OUTPUT->heading(userdate($feedback->timemodified).' ('.fullname($feedback).')', 3);

            echo $OUTPUT->box_start('feedback_items');
            $count = 0;
            foreach ($items as $item) {
                echo $OUTPUT->box_start();
                if ($feedback->autonumbering && $item->hasvalue) {
                    echo $OUTPUT->box_start();
                    echo ++$count;
                    echo $OUTPUT->box_end();
                }

                echo $OUTPUT->box_start('box generalbox');
                feedback_print_item_show_value($item, $item->value);
                echo $OUTPUT->box_end();
                echo $OUTPUT->box_end();
            }
            echo $OUTPUT->box_end();
        }
    }
} else {

    // Display list of archived feedback

    $mform = new view_archive_form();

    if ($formdata = $mform->get_data()) {
        // New filters so reset the page number
        $filters['page'] = 0;
        $filters['feedbackid'] = $formdata->feedbackid;
        $filters['username'] = $formdata->username;
        $filters['lastname'] = $formdata->lastname;
        $filters['firstname'] = $formdata->firstname;
    } else {
        $formdata = new stdClass();
        $formdata->feedbackid = $filters['feedbackid'];
        $formdata->username = $filters['username'];
        $formdata->lastname = $filters['lastname'];
        $formdata->firstname = $filters['firstname'];
    }

    $mform->set_data($formdata);
    $mform->display();

    $totalcount = 0;
    $archives = feedback_archive_get_list($filters, $totalcount);
    $filters['totalcount'] = $totalcount;

    echo feedback_archive_display_list($archives, $filters);
}
echo $OUTPUT->footer();
