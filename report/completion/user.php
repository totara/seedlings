<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Display user completion report
 *
 * @package    report
 * @subpackage completion
 * @copyright  2009 Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/report/completion/lib.php');
require_once($CFG->libdir.'/completionlib.php');

$userid   = required_param('id', PARAM_INT);
$courseid = required_param('course', PARAM_INT);

$user = $DB->get_record('user', array('id'=>$userid, 'deleted'=>0), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);

$coursecontext   = context_course::instance($course->id);
$personalcontext = context_user::instance($user->id);

$PAGE->set_course($course);

if (!completion_can_view_data($user->id, $course->id)) {
    // this should never happen
    error('Can not access user completion report');
}

$stractivityreport = get_string('activityreport');

$PAGE->set_pagelayout('admin');
$PAGE->set_url('/report/completion/user.php', array('id'=>$user->id, 'course'=>$course->id));
$PAGE->navigation->extend_for_user($user);
$PAGE->navigation->set_userid_for_parent_checks($user->id); // see MDL-25805 for reasons and for full commit reference for reversal when fixed.
$PAGE->set_title("$course->shortname: $stractivityreport");
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();


// Display course completion user report
// Categorize courses by their status

// Grab all courses the user is enrolled in and their completion status
if ($course->id == SITEID) {
    $course_completions = completion_info::get_all_courses($user->id);
}
else {
    $ccompletion = new completion_completion(
        array(
            'userid'    => $user->id,
            'course'    => $course->id
        )
    );

    $course_completions = array($ccompletion);
}

// Categorize courses by their status
$courses = array(
    'inprogress'    => array(),
    'complete'      => array(),
    'notyetstarted' => array()
);

// Sort courses by the user's status in each
$num_completions = 0;
foreach ($course_completions as $course_completion) {

    // Get status
    $status = completion_completion::get_status($course_completion);

    // Combine complete and completeviarpl
    if ($status == 'completeviarpl') {
        $status = 'complete';
    }

    // If the user's status in the course hasn't been aggregated yet,
    // it probably means the user has been enrolled in the course
    // but the cron job hasn't run yet.
    if ($status == ''){
        $status = 'notyetstarted';
    }

    $c = (object) array('id' => $course_completion->course);
    $cinfo = new completion_info($c);
    if ($cinfo->has_criteria()) {
        $courses[$status][] = $cinfo;
        ++$num_completions;
    }
}

// Check if results were empty
if (!$num_completions) {
    if ($course->id != SITEID) {
        $error = get_string('nocompletions', 'report_completion');
    } else {
        $error = get_string('nocompletioncoursesenroled', 'report_completion');
    }

    echo $OUTPUT->notification($error);
    echo $OUTPUT->footer();
    die();
}

// Loop through course status groups
foreach ($courses as $type => $infos) {

    // If there are courses with this status
    if (!empty($infos)) {

        // If showing all courses
        if ($course->id == SITEID) {
            echo '<h2>'.get_string($type, 'report_completion').'</h2>';
        } else {
            echo '<h2>'.format_string($course->fullname).': '.get_string($type, 'completion').'</h2>';
        }

        echo '<table class="generalbox logtable boxaligncenter course-completion-table" width="100%">';
        echo '<tr class="ccheader">';
        echo '<th class="c0 header" scope="col">'.get_string('course').'</th>';
        echo '<th class="c1 header" scope="col">'.get_string('requiredcriteria', 'completion').'</th>';
        echo '<th class="c2 header" scope="col">'.get_string('status').'</th>';
        echo '<th class="c3 header" scope="col" width="15%">'.get_string('info').'</th>';

        if ($type === 'complete') {
            echo '<th class="c4 header" scope="col">'.get_string('completiondate', 'report_completion').'</th>';
        }

        echo '</tr>';
        $oddeven = 0;

        // For each course
        foreach ($infos as $c_info) {

            // Get course info
            $c_course = $DB->get_record('course', array('id' => $c_info->course_id));
            $course_context = context_course::instance($c_course->id, MUST_EXIST);
            $course_name = format_string($c_course->fullname, true, array('context' => $course_context));

            // Get completions
            $completions = $c_info->get_completions($user->id);

            // Save row data
            $rows = array();

            // For aggregating activity completion
            $activities = array();
            $activities_complete = 0;

            // For aggregating dependencies
            $dependencies = array();
            $dependencies_complete = 0;

            // Loop through course criteria
            foreach ($completions as $completion) {
                $criteria = $completion->get_criteria();
                $complete = $completion->is_complete();

                // Activities are a special case, so cache them and leave them till last
                if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_ACTIVITY) {
                    $activities[$criteria->moduleinstance] = $complete;

                    if ($complete) {
                        $activities_complete++;
                    }

                    continue;
                }

                // Dependencies are also a special case, so cache them and leave them till last
                if ($criteria->criteriatype == COMPLETION_CRITERIA_TYPE_COURSE) {
                    $dependencies[$criteria->courseinstance] = $complete;

                    if ($complete) {
                        $dependencies_complete++;
                    }

                    continue;
                }

                $row = array();
                $row['title'] = $criteria->get_title();
                $row['status'] = $completion->get_status();
                $rows[] = $row;
            }

            // Aggregate activities
            if (!empty($activities)) {
                $activities_stats = new stdClass();
                $activities_stats->completed = $activities_complete;
                $activities_stats->total = count($activities);

                $row = array();
                $row['title'] = get_string('activitiescomplete', 'report_completion');
                $row['status'] = get_string('xofy', 'report_completion', $activities_stats);
                $rows[] = $row;
            }

            // Aggregate dependencies
            if (!empty($dependencies)) {
                $dependencies_stats = new stdClass();
                $dependencies_stats->completed = $dependencies_complete;
                $dependencies_stats->total = count($dependencies);

                $row = array();
                $row['title'] = get_string('dependenciescompleted', 'completion');
                $row['status'] = get_string('xofy', 'report_completion', $dependencies_stats);
                array_splice($rows, 0, 0, array($row));
            }

            $first_row = true;

            // Print table
            foreach ($rows as $row) {

                $oddeven = $oddeven % 2;

                // Display course name on first row
                if ($first_row) {
                    echo '<tr class="r' . $oddeven .'"><td class="cell c0"><a href="'.$CFG->wwwroot.'/course/view.php?id='.$c_course->id.'">'.format_string($course_name).'</a></td>';
                } else {
                    echo '<tr class="r' . $oddeven .'"><td class="cell c0"></td>';
                }

                ++$oddeven;

                echo '<td class="cell c1">';
                echo $row['title'];
                echo '</td><td class="cell c2">';

                switch ($row['status']) {
                    case get_string('yes'):
                        echo get_string('complete');
                        break;

                    case get_string('no'):
                        echo get_string('incomplete', 'report_completion');
                        break;

                    default:
                        echo $row['status'];
                }

                // Display link on first row
                echo '</td><td class="cell c3">';
                if ($first_row) {
                    echo '<a href="'.$CFG->wwwroot.'/blocks/completionstatus/details.php?course='.$c_course->id.'&user='.$user->id.'">'.get_string('detailedview', 'report_completion').'</a>';
                }
                echo '</td>';

                // Display completion date for completed courses on first row
                if ($type === 'complete' && $first_row) {
                    $params = array(
                        'userid'    => $user->id,
                        'course'  => $c_course->id
                    );

                    $ccompletion = new completion_completion($params);
                    echo '<td class="cell c4">'.userdate($ccompletion->timecompleted, get_string('strftimedate', 'langconfig')).'</td>';
                }

                $first_row = false;
                echo '</tr>';
            }
        }

        echo '</table>';
    }

}

echo $OUTPUT->footer();
// Trigger a user report viewed event.
$event = \report_completion\event\user_report_viewed::create(array('context' => $coursecontext, 'relateduserid' => $userid));
$event->trigger();
