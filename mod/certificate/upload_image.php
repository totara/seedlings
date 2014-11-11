<?php

// This file is part of the Certificate module for Moodle - http://moodle.org/
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
 * Handles uploading files
 *
 * @package    mod_certificate
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/certificate/locallib.php');
require_once($CFG->dirroot.'/mod/certificate/upload_image_form.php');
require_once($CFG->dirroot . '/repository/lib.php');

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$struploadimage = get_string('uploadimage', 'certificate');

$PAGE->set_url('/admin/settings.php', array('section' => 'modsettingcertificate'));
$PAGE->set_pagetype('admin-setting-modsettingcertificate');
$PAGE->set_pagelayout('admin');
$PAGE->set_context($context);
$PAGE->set_title($struploadimage);
$PAGE->set_heading($SITE->fullname);
$PAGE->navbar->add($struploadimage);

$supportedtypes = array('.jpe', '.jpeIE', '.jpeg', '.jpegIE', '.jpg', '.jpgIE', '.png', '.pngIE');

$filetypes = array('border', 'watermark', 'seal', 'signature');
$context = context_system::instance();
$options = array('subdirs' => 0,
                 'maxbytes' => $CFG->maxbytes,
                 'maxfiles' => -1,
                 'accepted_types' => $supportedtypes,
                 'return_types' => FILE_INTERNAL);

$data = new stdClass();
$data->id = 1;
foreach ($filetypes as $ft) {
    file_prepare_standard_filemanager($data, $ft, $options, $context, 'mod_certificate', $ft, 3);
}

$upload_form = new mod_certificate_upload_image_form(null, array('data' => $data, 'options' => $options));

if ($upload_form->is_cancelled()) {
    redirect(new moodle_url('/mod/certificate/upload_image.php'));
} else if ($formdata = $upload_form->get_data()) {
    foreach ($filetypes as $ft) {
        $formdata = file_postupdate_standard_filemanager($formdata, $ft, $options, $context, 'mod_certificate', $ft, 3);
    }

    redirect(new moodle_url('/mod/certificate/upload_image.php'), get_string('changessaved'));
}

echo $OUTPUT->header();
echo $upload_form->display();
echo $OUTPUT->footer();
