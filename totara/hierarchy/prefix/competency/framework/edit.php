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

require_once($CFG->dirroot.'/totara/hierarchy/lib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/scale/lib.php');

// This page is being included from another, which is why $prefix and $id are already defined.
$shortprefix = hierarchy::get_short_prefix($prefix);
// Make this page appear under the manage 'hierarchy' admin menu
$adminurl = $CFG->wwwroot.'/totara/hierarchy/framework/edit.php?prefix='.$prefix.'&id='.$id;
admin_externalpage_setup($prefix.'manage', '', array(), $adminurl);
$context = context_system::instance();

if ($id == 0) {
    // Creating new framework
    require_capability('totara/hierarchy:create'.$prefix.'frameworks', $context);

    // Don't show the page if there are no scales
    if (!competency_scales_available()) {

        /// Display page header
        echo $OUTPUT->header();
        notice(get_string('nocompetencyscales','totara_hierarchy'), "{$CFG->wwwroot}/totara/hierarchy/framework/index.php?prefix=competency" );
        echo $OUTPUT->footer();
        die();
    }

    $framework = new stdClass();
    $framework->id = 0;
    $framework->visible = 1;
    $framework->description = '';
    $framework->sortorder = $DB->get_field($shortprefix.'_framework', 'MAX(sortorder) + 1', array());
    $framework->hidecustomfields = 0;
    if (!$framework->sortorder) {
        $framework->sortorder = 1;
    }
    $framework->scale = array();

} else {
    // Editing existing framework
    require_capability('totara/hierarchy:update'.$prefix.'frameworks', $context);

    if (!$framework = $DB->get_record($shortprefix.'_framework', array('id' => $id))) {
        print_error('incorrectframework', 'totara_hierarchy', $prefix);
    }

    // Load scale assignments
    $scales = $DB->get_records($shortprefix.'_scale_assignments', array('frameworkid' => $framework->id));
    $framework->scale = array();
    if ($scales) {
        foreach ($scales as $scale) {
            $framework->scale[] = $scale->scaleid;
        }
    }
}

// create form
$framework->descriptionformat = FORMAT_HTML;
$framework = file_prepare_standard_editor($framework, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
             'totara_hierarchy', $shortprefix.'_framework', $framework->id);
$frameworkform = new framework_edit_form(null, array('frameworkid' => $id));
$frameworkform->set_data($framework);

// cancelled
if ($frameworkform->is_cancelled()) {

    redirect("$CFG->wwwroot/totara/hierarchy/framework/index.php?prefix=$prefix");

// Update data
} else if ($frameworknew = $frameworkform->get_data()) {

    // Validate that the selected framework contains at least one framework value
    if (!isset($frameworknew->scale) || 0 == $DB->count_records('comp_scale_values', array('scaleid' => $frameworknew->scale))) {
        print_error('competencyframeworknotfound', 'totara_hierarchy');
    }

    $time = time();

    $frameworknew->timemodified = $time;
    $frameworknew->usermodified = $USER->id;

    // Save
    // New framework
    if ($frameworknew->id == 0) {
        unset($frameworknew->id);

        $frameworknew->timecreated = $time;

        if (!$frameworknew->id = $DB->insert_record($shortprefix.'_framework', $frameworknew)) {
            print_error('createframeworkrecord', 'totara_hierarchy', $prefix);
        }

    // Existing framework
    } else {
        if (!$DB->update_record($shortprefix.'_framework', $frameworknew)) {
            print_error('createframeworkrecord', 'totara_hierarchy', $prefix);
        }
    }
    //fix the description field
    $frameworknew = file_postupdate_standard_editor($frameworknew, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_hierarchy', $shortprefix.'_framework', $frameworknew->id);
    $DB->set_field($shortprefix.'_framework', 'description', $frameworknew->description, array('id' => $frameworknew->id));
    // Handle scale assignments
    // Get new assignments
    if (isset($frameworknew->scale)) {
        $scales_new = array_diff(array($frameworknew->scale), $framework->scale);
        foreach ($scales_new as $key) {
            $assignment = new stdClass();
            $assignment->scaleid = $key;
            $assignment->frameworkid = $frameworknew->id;
            $assignment->timemodified = $time;
            $assignment->usermodified = $USER->id;
            if (!$DB->insert_record($shortprefix.'_scale_assignments', $assignment)) {
                print_error('addscaleassignment', 'totara_hierarchy');
            }
        }

        // Get removed assignments
        $scales_removed = array_diff($framework->scale, array($frameworknew->scale));
    }
    else {
        $scales_removed = $framework->scale;
    }

    foreach ($scales_removed as $key) {
        if (!$DB->delete_records($shortprefix.'_scale_assignments', array('scaleid' => $key, 'frameworkid' => $frameworknew->id))) {
            print_error('deletescaleassignment', 'totara_hierarchy');
        }
    }


    // Reload from db
    $frameworknew = $DB->get_record($shortprefix.'_framework', array('id' => $frameworknew->id));

    // Log
    // New framework
    if ($framework->id == 0) {
        add_to_log(SITEID, $prefix, 'framework create', "framework/view.php?prefix=$prefix&amp;frameworkid={$frameworknew->id}", "$frameworknew->fullname (ID $frameworknew->id)");
    } else {
        add_to_log(SITEID, $prefix, 'framework update', "framework/view.php?prefix=$prefix&amp;frameworkid={$frameworknew->id}", "$framework->fullname (ID $framework->id)");
    }

    redirect("$CFG->wwwroot/totara/hierarchy/framework/index.php?prefix=$prefix&id=" . $frameworknew->id);
    //never reached
}


/// Display page header
$PAGE->navbar->add(get_string("{$prefix}frameworks", 'totara_hierarchy'),
                    new moodle_url('/totara/hierarchy/framework/index.php', array('prefix' => $prefix)));
if ($id == 0) {
    $PAGE->navbar->add(get_string($prefix.'addnewframework', 'totara_hierarchy'));
} else {
    $PAGE->navbar->add(format_string($framework->fullname));
}

echo $OUTPUT->header();

if ($framework->id == 0) {
    echo $OUTPUT->heading(get_string($prefix.'addnewframework', 'totara_hierarchy'));
} else {
    echo $OUTPUT->heading(format_string($framework->fullname), 1);
}

/// Finally display THE form
$frameworkform->display();

/// and proper footer
echo $OUTPUT->footer();
