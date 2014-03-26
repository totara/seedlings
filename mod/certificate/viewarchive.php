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
 * @subpackage certificate
 * @author     Russell England <russell.england@catalyst-eu.net>
 * @copyright  Catalyst IT Ltd 2013 <http://catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 *
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/mod/certificate/lib.php');
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->dirroot . '/mod/certificate/viewarchive_form.php');
require_once($CFG->dirroot . '/course/lib.php');

$filters['certid'] = required_param('certid', PARAM_INT); // Certificate ID
$filters['page'] = optional_param('page', 0, PARAM_INT);
$filters['perpage'] = optional_param('perpage', 20, PARAM_INT);
$filters['historyid'] = optional_param('historyid', null, PARAM_INT);    // certificate_issue_history id
$filters['username'] = optional_param('username', null, PARAM_TEXT);
$filters['lastname'] = optional_param('lastname', null, PARAM_TEXT);
$filters['firstname'] = optional_param('firstname', null, PARAM_TEXT);
$filters['output'] = optional_param('output', 'I', PARAM_ALPHA); // Default to browser

if (!$certificate = $DB->get_record('certificate', array('id' => $filters['certid']))) {
    print_error(get_string('error:certificatenotfound', 'certificate', $filters['certid']));
}
$filters['courseid'] = $certificate->course;

if (!$course = $DB->get_record('course', array('id' => $certificate->course))) {
    print_error("coursemisconf");
}

if (!$cm = get_coursemodule_from_instance('certificate', $certificate->id, $course->id)) {
    print_error("invalidcoursemodule");
}

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

if (!empty($filters['historyid']) && (!$DB->record_exists('certificate_issues_history', array('id' => $filters['historyid'])))) {
    print_error(get_string('error:certissuenotfound', 'certificate', $filters['historyid']));
}

require_capability('mod/certificate:viewarchive', $context);

$heading = get_string('viewarchive', 'certificate');
$PAGE->set_context($context);
$PAGE->set_heading(format_string($heading));
$PAGE->set_title(format_string($heading));
$PAGE->set_url('/mod/certificate/viewarchive.php', $filters);

if (!empty($filters['historyid'])) {
    // Display certificate - we are in a popup

    // Create a directory that is writeable so that TCPDF can create temp images.
    // In 2.2 onwards the function make_cache_directory was introduced, use that,
    // otherwise we will use make_upload_directory.
    if ($CFG->version >= '2011120500') {
        make_cache_directory('tcpdf');
    } else {
        make_upload_directory('cache/tcpdf');
    }

    $pdf = certificate_print_archive_pdf($filters['historyid']);
    $certfilename = $course->shortname . '_' . $certificate->name;
    $filename = clean_filename(str_replace(array('&amp;', '&'), get_string('ampersand', 'totara_core'),
                    format_string(strip_tags($certfilename), true))) . '.pdf';
    $pdf->Output($filename, $filters['output']);
    exit;
}


// Display list of archived certificates
echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$mform = new view_archive_form();

if ($formdata = $mform->get_data()) {
    // New filters so reset the page number
    $filters['page'] = 0;
    $filters['certid'] = $formdata->certid;
    $filters['username'] = $formdata->username;
    $filters['lastname'] = $formdata->lastname;
    $filters['firstname'] = $formdata->firstname;
} else {
    $formdata = new stdClass();
    $formdata->certid = $filters['certid'];
    $formdata->username = $filters['username'];
    $formdata->lastname = $filters['lastname'];
    $formdata->firstname = $filters['firstname'];
}

$mform->set_data($formdata);
$mform->display();

$totalcount = 0;
$archives = certificate_archive_get_list($filters, $totalcount);
$filters['totalcount'] = $totalcount;

echo certificate_archive_display_list($archives, $filters);

echo $OUTPUT->footer();
