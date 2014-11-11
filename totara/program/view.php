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
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package totara
 * @subpackage program
 */

/**
 * Program progress view page
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once('lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

require_login();

$id = required_param('id', PARAM_INT); // program id
$viewtype = optional_param('viewtype', 'program', PARAM_TEXT); // Type of a page, program or certification.

if (!$program = new program($id)) {
    print_error('error:programid', 'totara_program');
}

if (!$program->is_viewable()) {
    print_error('error:inaccessible', 'totara_program');
}

// Check if programs or certifications are enabled.
if ($program->certifid) {
    check_certification_enabled();
    $identifier = 'editcertif';
    $component = 'totara_certification';
} else {
    check_program_enabled();
    $identifier = 'editprogramdetails';
    $component = 'totara_program';
}

$PAGE->set_context(context_program::instance($program->id));
$PAGE->set_url('/totara/program/view.php', array('id' => $id, 'viewtype' => $viewtype));
$PAGE->set_pagelayout('noblocks');
add_to_log(SITEID, 'program', 'view', "view.php?id={$program->id}&amp;userid={$USER->id}", $program->fullname);

//Javascript include
local_js(array(
    TOTARA_JS_DIALOG,
    TOTARA_JS_PLACEHOLDER
));

// Get extension dialog content
$PAGE->requires->strings_for_js(array('pleaseentervaliddate', 'pleaseentervalidreason', 'extensionrequest', 'cancel', 'ok'), 'totara_program');
$PAGE->requires->strings_for_js(array('datepickerlongyeardisplayformat', 'datepickerlongyearplaceholder', 'datepickerlongyearregexjs'), 'totara_core');
$notify_html = trim($OUTPUT->notification(get_string("extensionrequestsent", "totara_program"), "notifysuccess"));
$notify_html_fail = trim($OUTPUT->notification(get_string("extensionrequestnotsent", "totara_program"), null));
$args = array('args'=>'{"id":'.$program->id.', "userid":'.$USER->id.', "user_fullname":'.json_encode(fullname($USER)).', "notify_html_fail":'.json_encode($notify_html_fail).', "notify_html":'.json_encode($notify_html).'}');
$jsmodule = array(
     'name' => 'totara_programview',
     'fullpath' => '/totara/program/view/program_view.js',
     'requires' => array('json', 'totara_core')
     );
$PAGE->requires->js_init_call('M.totara_programview.init',$args, false, $jsmodule);

///
/// Display
///

$isadmin = has_capability('moodle/category:manage', context_coursecat::instance($program->category));

$category_breadcrumbs = prog_get_category_breadcrumbs($program->category, $viewtype);

$heading = $program->fullname;
$pagetitle = format_string(get_string('program', 'totara_program').': '.$heading);
if ($isadmin) {
    $PAGE->navbar->add(get_string('manageprograms', 'admin'), new moodle_url('/totara/program/manage.php', array('viewtype' => $viewtype)));
} else {
    $PAGE->navbar->add(get_string('findprograms', 'totara_program'), new moodle_url('/totara/program/index.php', array('viewtype' => $viewtype)));
}

foreach ($category_breadcrumbs as $crumb) {
    $PAGE->navbar->add($crumb['name'], $crumb['link']);
}

$PAGE->navbar->add($heading);

$PAGE->set_title($pagetitle);
$PAGE->set_heading(format_string($SITE->fullname));
echo $OUTPUT->header();

if ($isadmin) {
    echo $OUTPUT->single_button(new moodle_url('/totara/program/edit.php', array('id' => $program->id)),
        get_string($identifier, $component), 'GET', array('class' => 'navbutton'));
}

// Program page content.
echo $OUTPUT->container_start('', 'view-program-content');

echo $OUTPUT->heading($heading);

// A user assigned this program should always see their progress.
if (!empty($CFG->audiencevisibility)) {
    if ($program->user_is_assigned($USER->id)) {
        echo $program->display($USER->id);
    } else if ($program->is_viewable()) {
        echo $program->display();
    } else {
        echo $OUTPUT->notification(get_string('error:inaccessible', 'totara_program'));
    }
} else {
    if ($program->user_is_assigned($USER->id)) {
        echo $program->display($USER->id);
    } else {
        echo $program->display();
    }
}

echo $OUTPUT->container_end();

echo $OUTPUT->footer();
