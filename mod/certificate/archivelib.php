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
 * @package    mod_certificate
 * @author     Russell England <russell.england@catalyst-eu.net>
 * @copyright  Catalyst IT Ltd 2013 <http://catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 *
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Archives user's certificates for a course
 *
 * @param int $userid
 * @param int $courseid
 * @return bool always true
 */
function certificate_archive_completion($userid, $courseid) {
    global $DB;

    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $completion = new completion_info($course);

    $sql = "SELECT ci.*
              FROM {certificate_issues} ci
              JOIN {certificate} c ON c.id = ci.certificateid AND c.course = :courseid
             WHERE ci.userid = :userid";

    if ($certs = $DB->get_records_sql($sql, array('userid' => $userid, 'courseid' => $courseid))) {
        foreach ($certs as $cert) {
            $certificate = $DB->get_record('certificate', array('id' => $cert->certificateid), '*', MUST_EXIST);

            $data = clone $cert;
            $data->timearchived = time();
            $data->idarchived = $cert->id; // Not sure if this is needed but might be useful if there is a data issue later on
            $data->timecompleted = certificate_get_date_completed($certificate, $cert, $course, $userid);
            $data->grade = certificate_get_grade($certificate, $course, $userid);
            $data->outcome = certificate_get_outcome($certificate, $course, $userid);

            $newid = $DB->insert_record('certificate_issues_history', $data, true);
            if ($newid) {
                $course_module = get_coursemodule_from_instance('certificate', $cert->certificateid, $course->id);

                // Reset viewed
                $completion->set_module_viewed_reset($course_module, $userid);
                // And reset completion, in case viewed is not a required condition
                $completion->update_state($course_module, COMPLETION_INCOMPLETE, $userid);

                // Delete original
                $DB->delete_records('certificate_issues', array('id' => $cert->id));
            }
        }
        $completion->invalidatecache($courseid, $userid, true);
    }
    return true;
}

/**
 * Creates a pdf for a given certificate_issue_history id
 *
 * Note: this function closes session.
 *
 * @param int $certissueid
 * @return Pdf $pdf
 */
function certificate_print_archive_pdf($certissueid) {
    global $DB, $CFG, $USER, $SESSION;

    $certrecord = $DB->get_record('certificate_issues_history', array('id' => $certissueid), '*', MUST_EXIST);
    $certificate = $DB->get_record('certificate', array('id' => $certrecord->certificateid), '*', MUST_EXIST);
    $user = $DB->get_record('user', array('id' => $certrecord->userid), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $certificate->course), 'fullname', MUST_EXIST);
    $cm = get_coursemodule_from_instance('certificate', $certrecord->certificateid, $certificate->course);

    // Switch user.
    \core\session\manager::write_close();
    $olduser = clone($USER);
    \core\session\manager::set_user($user);
    $oldlang = isset($SESSION->lang) ? $SESSION->lang : null;
    $SESSION->lang = $USER->lang;
    moodle_setlocale();

    $pdf = null;
    // $pdf is created by certificate.php
    require($CFG->dirroot . '/mod/certificate/type/' . $certificate->certificatetype . '/certificate.php');

    // Switch user back.
    \core\session\manager::set_user($olduser);
    $SESSION->lang = $oldlang;
    moodle_setlocale();

    return $pdf;

}

/**
 * Returns a list of archived certificates
 *
 * @param array $filters
 * @param int $totalcount
 * @return array $archives - list of archived certificates
 */
function certificate_archive_get_list($filters, &$totalcount) {
    global $DB;

    $params = array();
    $wheres = array();
    $where = '';

    if (isset($filters['courseid']) && $filters['courseid']) {
        $params['courseid'] = $filters['courseid'];
        $wheres[] = 'course.id = :courseid';
    }
    if (isset($filters['coursename']) && $filters['coursename']) {
        $params['coursename'] = '%' . $DB->sql_like_escape($filters['coursename']) . '%';
        $wheres[] = $DB->sql_like('course.fullname', ':coursename', false);
    }
    if (isset($filters['certid']) && $filters['certid']) {
        $params['certid'] = $filters['certid'];
        $wheres[] = 'cert.id = :certid';
    }
    if (isset($filters['certname']) && $filters['certname']) {
        $params['certname'] = '%' . $DB->sql_like_escape($filters['certname']) . '%';
        $wheres[] = $DB->sql_like('cert.name', ':certname', false);
    }
    if (isset($filters['username']) && $filters['username']) {
        $params['username'] = '%' . $DB->sql_like_escape($filters['username']) . '%';
        $wheres[] = $DB->sql_like('u.username', ':username', false);
    }
    if (isset($filters['firstname']) && $filters['firstname']) {
        $params['firstname'] = '%' . $DB->sql_like_escape($filters['firstname']) . '%';
        $wheres[] = $DB->sql_like('u.firstname', ':firstname', false);
    }
    if (isset($filters['lastname']) && $filters['lastname']) {
        $params['lastname'] = '%' . $DB->sql_like_escape($filters['lastname']) . '%';
        $wheres[] = $DB->sql_like('u.lastname', ':lastname', false);
    }
    if (!empty($wheres)) {
        $where = 'WHERE ' . implode(' AND ', $wheres);
    }

    $sqlbody = "FROM {certificate_issues_history} ci
                JOIN {certificate} cert ON cert.id = ci.certificateid
                JOIN {course} course ON course.id = cert.course
                JOIN {user} u ON u.id = ci.userid
                {$where}";

    $sqlcount = "SELECT COUNT(*) " . $sqlbody;
    $totalcount = $DB->count_records_sql($sqlcount, $params);

    $offset = $filters['page'] * $filters['perpage'];

    $alluserfields = get_all_user_name_fields(true, 'u');

    $sql = "SELECT ci.id,
                    u.id userid,
                    u.email,
                    u.username,
                    $alluserfields,
                    course.id AS courseid,
                    course.fullname AS coursename,
                    cert.id AS certid,
                    cert.name AS certname,
                    ci.timecompleted,
                    ci.grade,
                    ci.outcome,
                    ci.timearchived
            {$sqlbody}
            ORDER BY u.lastname,
                     u.firstname,
                     course.fullname,
                     cert.name,
                     ci.timecompleted,
                     ci.timearchived";
    $archives = $DB->get_records_sql($sql, $params, $offset, $filters['perpage']);

    return $archives;
}

/**
 * Displays a table of archived certificates
 *
 * @param array $archives
 * @param array $params - contains paging parameters
 */
function certificate_archive_display_list($archives, $params) {
    global $OUTPUT;

    $table = new flexible_table('view-certificate-archive');
    $table->define_columns(array(
        'username',
        'userfullname',
        'timecompleted',
        'grade',
        'outcome',
        'timearchived',
        'options'
    ));
    $table->define_headers(array(
        get_string('username'),
        get_string('fullnameuser'),
        get_string('timecompleted', 'certificate'),
        get_string('coursegrade', 'certificate'),
        get_string('outcome', 'certificate'),
        get_string('timearchived', 'certificate'),
        get_string('options')
    ));

    $table->column_class('username', 'username');
    $table->column_class('userfullname', 'userfullname');
    $table->column_class('timecompleted', 'timecompleted');
    $table->column_class('grade', 'grade');
    $table->column_class('outcome', 'outcome');
    $table->column_class('timearchived', 'timearchived');
    $table->column_class('options', 'options');

    $table->define_baseurl(new moodle_url('/mod/certificate/viewarchive.php', $params));
    $table->sortable(false);
    $table->collapsible(false);

    $table->set_attribute('cellspacing', '0');
    $table->set_attribute('id', 'view-certificate-archive');
    $table->set_attribute('class', 'generaltable');
    $table->set_attribute('width', '100%');
    $table->setup();

    $table->initialbars($params['totalcount'] > $params['perpage']);
    $table->pagesize($params['perpage'], $params['totalcount']);

    if ($archives) {
        foreach ($archives as $archive) {
            $options = '';

            $viewurl = new moodle_url('/mod/certificate/viewarchive.php',
                array('certid' => $archive->certid, 'historyid' => $archive->id, 'output' => 'I'));
            $viewbutton = new single_button($viewurl, get_string('view'));
            $viewbutton->add_action(new popup_action('click', $viewurl,
                'view' . $archive->id, array('height' => 600, 'width' => 800)));
            $options .= html_writer::tag('span', $OUTPUT->render($viewbutton));

            $downloadurl = new moodle_url('/mod/certificate/viewarchive.php',
                array('certid' => $archive->certid, 'historyid' => $archive->id, 'output' => 'D'));
            $downloadbutton = new single_button($downloadurl,
                get_string('download'));
            $options .= html_writer::tag('span', $OUTPUT->render($downloadbutton));

            $row = array();

            $userurl = new moodle_url('/user/view.php', array('id' => $archive->userid));
            $row[] = html_writer::link($userurl, format_string($archive->username));
            $row[] = html_writer::link($userurl, format_string(fullname($archive)));

            $row[] = userdate($archive->timecompleted);
            $row[] = $archive->grade;
            $row[] = $archive->outcome;
            $row[] = userdate($archive->timearchived);
            $row[] = $options;

            $table->add_data($row);
        }
    }
    $table->print_html();

}

/**
 * Returns the date the certificate was completed for the archive record
 *
 * @param stdClass $certificate certificate record
 * @param stdClass $certrecord certificate history record
 * @param stdClass $course course record
 * @param int $userid userid
 */
function certificate_get_date_completed($certificate, $certrecord, $course, $userid) {
    global $DB;
    // Set certificate date to current time, can be overwritten later
    $date = $certrecord->timecreated;

    if ($certificate->printdate == '2') {
        // Get the enrolment end date
        $sql = "SELECT MAX(c.timecompleted) as timecompleted
                  FROM {course_completions} c
                 WHERE c.userid = :userid
                       AND c.course = :courseid";
        if ($timecompleted = $DB->get_record_sql($sql, array('userid' => $userid, 'courseid' => $course->id))) {
            if (!empty($timecompleted->timecompleted)) {
                $date = $timecompleted->timecompleted;
            }
        }
    } else if ($certificate->printdate > 2) {
        if ($modinfo = certificate_get_mod_grade($course, $certificate->printdate, $userid)) {
            $date = $modinfo->dategraded;
        }
    }
    return $date;
}

/**
 * Returns the completed date formatted according to the certificate
 * date format and the users language.
 *
 * NOTE: this is intended for use from certificate_print_archive_pdf()
 *       which switches the current USER and locales.
 *
 * @param stdClass $certificate record
 * @param int $date
 * @return string formatted date
 */
function certificate_get_date_completed_formatted($certificate, $date) {
    $certificatedate = '';

    if ($certificate->printdate > 0) {
        if ($certificate->datefmt == 1) {
            $format = get_string('dateformat1', 'certificate');
            $certificatedate = userdate($date, $format);
        } else if ($certificate->datefmt == 2) {
            $certificatedate = date(get_string('dateformat2', 'certificate'), $date);
        } else if ($certificate->datefmt == 3) {
            $format = get_string('strftimedate', 'langconfig');
            $certificatedate = userdate($date, $format);
        } else if ($certificate->datefmt == 4) {
            $format = get_string('strftimemonthyear', 'langconfig');
            $certificatedate = userdate($date, $format);
        }
    }

    return $certificatedate;
}
