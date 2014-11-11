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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage reportbuilder
 */

/*
 * Displays current users reports and scheduled reports
 *
 */

require_once(dirname(dirname(__FILE__)) . '/config.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');

require_login();

$edit = optional_param('edit', -1, PARAM_BOOL);

$strheading = get_string('myreports', 'totara_core');

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_pagetype('my-reports');
$PAGE->set_title($strheading);
$PAGE->set_heading(format_string($SITE->fullname));
$PAGE->set_url(new moodle_url('/my/reports.php'));
$PAGE->set_totara_menu_selected('myreports');
$PAGE->navbar->add($strheading);

if (!isset($USER->editing)) {
    $USER->editing = 0;
}
if ($PAGE->user_allowed_editing()) {
    $editbutton = $OUTPUT->edit_button($PAGE->url);
    $PAGE->set_button($editbutton);

    if ($edit == 1 && confirm_sesskey()) {
        $USER->editing = 1;
        $url = new moodle_url($PAGE->url, array('notifyeditingon' => 1));
        redirect($url);
    } else if ($edit == 0 && confirm_sesskey()) {
        $USER->editing = 0;
        redirect($PAGE->url);
    }
} else {
    $USER->editing = 0;
}
echo $OUTPUT->header();

add_to_log(SITEID, 'my', 'reports', 'reports.php');

echo $OUTPUT->heading($strheading);

echo $OUTPUT->container_start(null, 'myreports_section');
echo totara_print_report_manager();
echo $OUTPUT->container_end();

if (reportbuilder_get_reports()){
    echo $OUTPUT->container_start(null, 'scheduledreports_section');
    echo $OUTPUT->container_start(null, 'scheduledreports_section_inner');
    echo html_writer::empty_tag('br');
    echo $OUTPUT->heading(get_string('scheduledreports', 'totara_reportbuilder'), 2, null, 'scheduled');

    totara_print_scheduled_reports();
    echo $OUTPUT->container_end();
    echo $OUTPUT->container_end();
}

echo $OUTPUT->footer();
