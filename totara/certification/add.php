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
 * Page for adding a certification - then go to program/course/competency for details
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once('edit_form.php');
require_once($CFG->dirroot . '/totara/program/lib.php');

// Check if certifications are enabled.
check_certification_enabled();

require_login();

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', 'add', PARAM_TEXT);
$categoryid = optional_param('category', 0, PARAM_INT); // Course category - can be changed in edit form.

$systemcontext = context_system::instance();
$actualurl = new moodle_url('/totara/certification/add.php', array('category' => $categoryid, 'action' => $action, 'id'=> $id));

// Integrate into the admin tree only if the user can create certifications at the top level,
// otherwise the admin block does not appear to this user, and you get an error.
if (has_capability('totara/certification:createcertification', $systemcontext)) {
    admin_externalpage_setup('managecertifications', '', null, $actualurl);
} else {
    $PAGE->set_context($systemcontext);
    $PAGE->set_url($actualurl);
    $PAGE->set_title(get_string('createnewcertification', 'totara_certification'));
    $PAGE->set_heading(get_string('createnewcertification', 'totara_certification'));
}

$currenturl = qualified_me();
$indexurl = new moodle_url('/program/index.php', array('viewtype' => 'certification'));

if ($action == 'add') {
    if ($categoryid) { // Creating new certification in this category.
        if (!$category = $DB->get_record('course_categories', array('id' => $categoryid))) {
            print_error('error:categoryidwasincorrect', 'totara_certification');
        }
        require_capability('totara/certification:createcertification', context_coursecat::instance($category->id));
    } else {
        print_error('error:categorymustbespecified', 'totara_certification');
    }

    // While there is only a program type of certification go straight to program-add rather than show selection page.
    redirect(new moodle_url('/totara/program/add.php', array('category' => $categoryid, 'iscertif' => 1)));
} else {
    print_error('error:invalidaction', 'totara_certification', '', $action);
}


// Data and actions.
if ($form->is_cancelled()) {
    redirect($indexurl);
}

// Handle form submit.
if ($data = $form->get_data()) {
    if (isset($data->savechanges)) {
        if ($data->action == 'add') {
            if ($data->comptype == CERTIFTYPE_PROGRAM ) {
                $addurl = new moodle_url('/totara/program/add.php', array('category' => $categoryid, 'iscertif' => 1));
            }
            redirect($addurl);
        } else {
            print_error('error:invalidaction', 'totara_certification', '', $action);
        }
    }
}

// Display.
$heading = get_string('createnewcertification', 'totara_certification');
$pagetitle = format_string(get_string('certification', 'totara_certification') . ': ' . $heading);
prog_add_base_navlinks();
$PAGE->navbar->add($heading);

echo $OUTPUT->header();

echo $OUTPUT->container_start('certification add', 'certification-add');

$context = context_coursecat::instance($category->id);

echo $OUTPUT->heading($heading);

$form->display();

echo $OUTPUT->container_end();

echo $OUTPUT->footer();
