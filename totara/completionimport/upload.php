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
 * @subpackage completionimport
 * @author     Russell England <russell.england@catalyst-eu.net>
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/completionimport/upload_form.php');
require_once($CFG->dirroot . '/totara/completionimport/lib.php');
require_once($CFG->libdir . '/adminlib.php');

$filesource = optional_param('filesource', null, PARAM_INT);
if ($filesource === null) {
    $filesource = get_default_config('totara_completionimport', 'filesource', TCI_SOURCE_UPLOAD);
}

require_login();

$context = context_system::instance();
require_capability('totara/completionimport:import', $context);

$PAGE->set_context($context);

// Create the forms before $OUTPUT.
$coursedata = get_config_data($filesource, 'course');
$coursedata->importname = 'course';
$courseform = new upload_form(null, $coursedata);

$certdata = get_config_data($filesource, 'certification');
$certdata->importname = 'certification';
$certform = new upload_form(null, $certdata);

$importname = '';
if ($data = $courseform->get_data()) {
    $importname = 'course';
} else if ($data = $certform->get_data()) {
    $importname = 'certification';
}
if (!empty($importname)) {
    $heading = get_string('importing', 'totara_completionimport', $importname);
} else {
    $heading = get_string('pluginname', 'totara_completionimport');
}

if (!in_array($filesource, array(TCI_SOURCE_EXTERNAL, TCI_SOURCE_UPLOAD))) {
    print_error('error:invalidfilesource', 'totara_completionimport');
} else {
    set_config('filesource', $filesource, 'totara_completionimport');
}

$PAGE->set_heading(format_string($heading));
$PAGE->set_title(format_string($heading));
$PAGE->set_url('/totara/completionimport/upload.php');
admin_externalpage_setup('totara_completionimport_upload');

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

if (!empty($importname)) {
    // Lets do it!
    require_sesskey();

    // Save the form settings for next time.
    set_config_data($data, $importname);

    // Get the temporary path.
    if (!($temppath = get_temppath())) {
        echo $OUTPUT->footer();
        exit;
    }

    // Create a temporary file name.
    if (!($tempfilename = tempnam($temppath, get_tempprefix($importname)))) {
        echo $OUTPUT->notification(get_string('cannotcreatetempname', 'totara_completionimport'), 'notifyproblem');
        echo $OUTPUT->footer();
        exit;
    }
    $tempfilename .= '.csv';

    // Move the file to the temporary filename.
    if ($filesource == TCI_SOURCE_EXTERNAL) {
        // File should already be uploaded by FTP.
        if (!move_sourcefile($data->sourcefile)) {
            echo $OUTPUT->footer();
            unlink($tempfilename);
            exit;
        }
    } else if ($filesource == TCI_SOURCE_UPLOAD) {
        // Uploading via a form.
        if ($importname == 'course') {
            if (!$courseform->save_file('uploadfile', $tempfilename, true)) {
                echo $OUTPUT->notification(get_string('cannotsaveupload', 'totara_completionimport', $tempfilename),
                        'notifyproblem');
                echo $OUTPUT->footer();
                unlink($tempfilename);
                exit;
            }
        } else if ($importname == 'certification') {
            if (!$certform->save_file('uploadfile', $tempfilename, true)) {
                echo $OUTPUT->notification(get_string('cannotsaveupload', 'totara_completionimport', $tempfilename),
                        'notifyproblem');
                echo $OUTPUT->footer();
                unlink($tempfilename);
                exit;
            }
        }
    } else {
        echo $OUTPUT->notification(get_string('invalidfilesource', 'totara_completionimport', $filesource), 'notifyproblem');
        echo $OUTPUT->footer();
        unlink($tempfilename);
        exit;
    }

    // Importtime is used to filter the import table for this run.
    $importtime = time();
    import_completions($tempfilename, $importname, $importtime);

    display_report_link($importname, $importtime);
    echo $OUTPUT->footer();
    exit;
}

// Display upload course heading + fields to import.
echo $OUTPUT->heading(get_string('uploadcourse', 'totara_completionimport'), 3);
$columnnames = implode(',', get_columnnames('course'));
echo format_text(get_string('uploadcourseintro', 'totara_completionimport', $columnnames));
$courseform->display();

// Display upload certification heading + fields to import.
if (totara_feature_visible('certifications')) {
    echo $OUTPUT->heading(get_string('uploadcertification', 'totara_completionimport'), 3);
    $columnnames = implode(',', get_columnnames('certification'));
    echo format_text(get_string('uploadcertificationintro', 'totara_completionimport', $columnnames));
    $certform->display();
}

if ($filesource == TCI_SOURCE_EXTERNAL) {
    $importurl = new moodle_url('/totara/completionimport/upload.php', array('filesource' => TCI_SOURCE_UPLOAD));
    echo html_writer::link($importurl, format_string(get_string('uploadvia_form', 'totara_completionimport')));
} else {
    $importurl = new moodle_url('/totara/completionimport/upload.php', array('filesource' => TCI_SOURCE_EXTERNAL));
    echo html_writer::link($importurl, format_string(get_string('uploadvia_directory', 'totara_completionimport')));
}

echo $OUTPUT->footer();
