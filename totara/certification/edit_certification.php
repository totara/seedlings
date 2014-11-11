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
 * @author Jon Sharp <jonathans@catalyst-eu.net>
 * @package totara
 * @subpackage certification
 */

/**
 * Program view page
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once('HTML/QuickForm/Renderer/QuickHtml.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/totara/program/lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once('edit_certification_form.php');

// Check if certifications are enabled.
check_certification_enabled();

$id = required_param('id', PARAM_INT); // Certification id.

require_login();

$systemcontext = context_system::instance();
$program = new program($id);
$programcontext = $program->get_context();
$timeallowance = new stdClass();
$timeallowance->seconds = $program->content->get_total_time_allowance(CERTIFPATH_RECERT);
$timeallowance->timestring = prog_format_seconds($timeallowance->seconds, true);

$certification = $DB->get_record('certif', array('id' => $program->certifid));

if (!$certification) {
    print_error(get_string('nocertifdetailsfound', 'totara_certification'));
}

$iscertif = 1;

if (!has_capability('totara/certification:configurecertification', $programcontext)) {
    print_error('error:nopermissions', 'totara_program');
}

$PAGE->set_url(new moodle_url('/totara/certification/edit_certification.php', array('id' => $id)));
$PAGE->set_context($programcontext);
$PAGE->set_title(format_string($program->fullname));
$PAGE->set_heading(format_string($program->fullname));

// Javascript include.
local_js(array(
    TOTARA_JS_DIALOG)
);

$currenturl = qualified_me();
$currenturl_noquerystring = strip_querystring($currenturl);
$viewurl = $currenturl_noquerystring."?id={$id}";
$overviewurl = $CFG->wwwroot."/totara/certification/edit_certification.php?id={$id}&action=view";
$customdata = array('certification' => $certification, 'timeallowance' => $timeallowance);
$form = new edit_certification_form($currenturl, $customdata, 'post', '', array('name'=>'form_certif_details'));

if ($form->is_cancelled()) {
    totara_set_notification(get_string('programupdatecancelled', 'totara_program'), $overviewurl,
                                                                                array('class' => 'notifysuccess'));
}

// This is where we validate and check the submitted data before saving it.
if ($data = $form->get_data()) {

    if (isset($data->savechanges)) {
        $certification->activeperiod = $data->activenum.' '.$data->activeperiod;
        $certification->windowperiod = $data->windownum.' '.$data->windowperiod;
        $certification->timemodified = time();
        $certification->recertifydatetype = $data->recertifydatetype;
        $DB->update_record('certif', $certification);

        totara_set_notification(get_string('certificationdetailssaved', 'totara_certification'),
                new moodle_url('/totara/certification/edit_certification.php', array('id' => $program->id)),
                array('class' => 'notifysuccess'));
    }

}

// Log this request.
add_to_log(SITEID, 'certification', 'edit', "edit_certification.php?id={$program->id}", $program->fullname);


// Display.

$heading = format_string($program->fullname);
$heading .= ' ('.get_string('certification', 'totara_certification').')';

// Javascript includes.
$PAGE->requires->strings_for_js(array('editcertif', 'saveallchanges', 'confirmchanges',
                 'youhaveunsavedchanges', 'youhaveunsavedchanges', 'tosaveall'), 'totara_certification');
$args = array('args'=>'{"id":'.$program->id.'}');
$jsmodule = array(
     'name' => 'totara_certificationconfirm',
     'fullpath' => '/totara/certification/certification_confirm.js',
     'requires' => array('json'));
$PAGE->requires->js_init_call('M.totara_certificationconfirm.init', $args, false, $jsmodule);

echo $OUTPUT->header();

echo $OUTPUT->container_start('certification details', 'cf'.$id);

echo $OUTPUT->heading($heading);

$renderer = $PAGE->get_renderer('totara_certification');

// Display the current status.
echo $program->display_current_status();
$exceptions = $program->get_exception_count();
$currenttab = 'certification';
require_once($CFG->dirroot . '/totara/program/tabs.php');


// Display the form.
$form->display();

echo $renderer->get_cancel_button(array('id' => $program->id));

echo $OUTPUT->container_end();

echo $OUTPUT->footer();
