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
 * @package totara
 * @subpackage totara_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/template/edit_form.php');


///
/// Setup / loading data
///

// Template id; 0 if creating new template
$id = optional_param('id', 0, PARAM_INT);
// framework id; required when creating a new template
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);

// We require either an id for editing, or a framework for creating
if (!$id && !$frameworkid) {
    print_error('incorrectparameters', 'totara_hierarchy');
}

// Make this page appear under the manage templates admin item
admin_externalpage_setup('competencymanage', '', array(), '/totara/hierarchy/prefix/competency/template/edit.php');

$context = context_system::instance();

if ($id == 0) {
    // Creating new competency template
    require_capability('totara/hierarchy:createcompetencytemplate', $context);

    $template = new stdClass();
    $template->id = 0;
    $template->description = '';
    $template->visible = 1;
    $template->frameworkid = $frameworkid;

} else {
    // Editing existing competency template
    require_capability('totara/hierarchy:updatecompetencytemplate', $context);

    if (!$template = $DB->get_record('comp_template', array('id' => $id))) {
    print_error('incorrectcompetencytemplateid', 'totara_hierarchy');
    }
}

// Load framework
if (!$framework = $DB->get_record('comp_framework', array('id' => $template->frameworkid))) {
    print_error('incorrectcompetencyframeworkid', 'totara_hierarchy');
}

// create form
$template->descriptionformat = FORMAT_HTML;
$template = file_prepare_standard_editor($template, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                          'totara_hierarchy', 'comp_template', $template->id);
$form = new competencytemplate_edit_form(null, array());
$form->set_data($template);

// cancelled
if ($form->is_cancelled()) {

    redirect("$CFG->wwwroot/totara/hierarchy/framework/view.php?prefix=competency&frameworkid=".$framework->id);

// Update data
} else if ($templatenew = $form->get_data()) {

    $time = time();

    $templatenew->timemodified = $time;
    $templatenew->usermodified = $USER->id;

    // Save
    // New template
    if ($templatenew->id == 0) {
        unset($templatenew->id);

        $templatenew->timecreated = $time;
        $templatenew->competencycount = 0;

        if (!$templatenew->id = $DB->insert_record('comp_template', $templatenew)) {
            print_error('createcompetencytemplaterecord', 'totara_hierarchy');
        }

    // Existing template
    } else {
        if (!$DB->update_record('comp_template', $templatenew)) {
            print_error('updatecompetencytemplaterecord', 'totara_hierarchy');
        }
    }

    // Log
    add_to_log(SITEID, 'competency', 'template update', "hierarchy/prefix/competency/template/view.php?id={$templatenew->id}", '');
    //fix the description field and redirect
    $templatenew = file_postupdate_standard_editor($templatenew, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_hierarchy', 'comp_template', $templatenew->id);
    $DB->set_field('comp_template', 'description', $templatenew->description, array('id' => $templatenew->id));
    redirect("$CFG->wwwroot/totara/hierarchy/framework/view.php?prefix=competency&frameworkid=".$framework->id);
    //never reached
}


/// Display page header
$PAGE->navbar->add(get_string("competencyframeworks", 'totara_hierarchy'),
                    new moodle_url('/totara/hierarchy/framework/index.php', array('prefix' => 'competency')));
$PAGE->navbar->add(format_string($framework->fullname),
                    new moodle_url('totara/hierarchy/framework/view.php', array('prefix' => 'competency', 'frameworkid' => $framework->id)));
if ($template->id == 0) {
    $heading = get_string('addnewtemplate', 'totara_hierarchy');
    $PAGE->navbar->add($heading);
} else {
    $heading = get_string('editgeneric', 'totara_hierarchy', format_string($template->fullname));
    $PAGE->navbar->add(format_string($template->fullname));
}

echo $OUTPUT->header();

echo $OUTPUT->heading($heading);

/// Finally display THE form
$form->display();

/// and proper footer
echo $OUTPUT->footer();
