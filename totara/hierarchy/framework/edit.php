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

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/lib.php');


$prefix    = required_param('prefix', PARAM_ALPHA); // hierarchy prefix

$shortprefix = hierarchy::get_short_prefix($prefix);
$id      = optional_param('id', 0, PARAM_INT);    // 0 if creating a new framework
$context = context_system::instance();

hierarchy::check_enable_hierarchy($prefix);

$hierarchy = hierarchy::load_hierarchy($prefix);

// If the hierarchy prefix has framework editing files use them else use the generic files
if (file_exists($CFG->dirroot.'/totara/hierarchy/prefix/'.$prefix.'/framework/edit.php')) {
    require_once($CFG->dirroot.'/totara/hierarchy/prefix/'.$prefix.'/framework/edit_form.php');
    require_once($CFG->dirroot.'/totara/hierarchy/prefix/'.$prefix.'/framework/edit.php');
    die;
} else {
    require_once($CFG->dirroot.'/totara/hierarchy/framework/edit_form.php');
}

// Make this page appear under the manage 'hierarchy' admin menu
admin_externalpage_setup($prefix.'manage', '', array('prefix' => $prefix, 'id' => $id), $CFG->wwwroot.'/totara/hierarchy/framework/edit.php');

if ($id == 0) {
    // Creating new framework
    require_capability('totara/hierarchy:create'.$prefix.'frameworks', $context);

    $framework = new stdClass();
    $framework->id = 0;
    $framework->visible = 1;
    $framework->description = '';
    $framework->sortorder = $DB->get_field($shortprefix.'_framework', 'MAX(sortorder) + 1', array());
    if (!$framework->sortorder) {
        $framework->sortorder = 1;
    }
    $framework->hidecustomfields = 0;

} else {
    // Editing existing framework
    require_capability('totara/hierarchy:update'.$prefix.'frameworks', $context);

    if (!$framework = $DB->get_record($shortprefix.'_framework', array('id' => $id))) {
        print_error('invalidframeworkid', 'totara_hierarchy', $prefix);
    }
}

// create form
$framework->descriptionformat = FORMAT_HTML;
$framework = file_prepare_standard_editor($framework, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                          'totara_hierarchy', $shortprefix.'_framework', $framework->id);
$frameworkform = new framework_edit_form(null, array('prefix' => $prefix));
$frameworkform->set_data($framework);

// cancelled
if ($frameworkform->is_cancelled()) {

    redirect("$CFG->wwwroot/totara/hierarchy/framework/index.php?prefix=$prefix");

// Update data
} else if ($frameworknew = $frameworkform->get_data()) {

    $time = time();

    $frameworknew->timemodified = $time;
    $frameworknew->usermodified = $USER->id;
    // Save
    $notification = new stdClass();

    if ($frameworknew->id == 0) {
        // New framework
        unset($frameworknew->id);

        $frameworknew->timecreated = $time;

        if (!$frameworknew->id = $DB->insert_record($shortprefix.'_framework', $frameworknew)) {
           print_error('createframeworkrecord', 'totara_hierarchy', $prefix);
        }

        // Log
        add_to_log(SITEID, $prefix, 'framework create', "index.php?prefix=$prefix&amp;frameworkid={$frameworknew->id}", "$frameworknew->fullname (ID $frameworknew->id)");
        $notification->text = 'addedframework';

    } else {
        // Existing framework
        if (!$DB->update_record($shortprefix.'_framework', $frameworknew)) {
           print_error('updateframeworkrecord', 'totara_hierarchy', $prefix);
        }

        // Log
        add_to_log(SITEID, $prefix, 'framework update', "framework/view.php?prefix=$prefix&amp;frameworkid={$frameworknew->id}", "$framework->fullname (ID $framework->id)");
        $notification->text = 'updatedframework';
    }
    //fix the description field and redirect
    $frameworknew = file_postupdate_standard_editor($frameworknew, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_hierarchy', $shortprefix.'_framework', $frameworknew->id);
    $DB->set_field($shortprefix.'_framework', 'description', $frameworknew->description, array('id' => $frameworknew->id));
    totara_set_notification(get_string($prefix.$notification->text, 'totara_hierarchy', format_string($frameworknew->fullname)), "$CFG->wwwroot/totara/hierarchy/framework/index.php?prefix=$prefix", array('class' => 'notifysuccess'));

}


/// Display page header
$PAGE->navbar->add(get_string("{$prefix}frameworks", 'totara_hierarchy'),
                    new moodle_url('/totara/hierarchy/framework/index.php', array('prefix' => $prefix)));
if ($framework->id == 0) {
    $PAGE->navbar->add(get_string($prefix.'addnewframework', 'totara_hierarchy'));
} else {
    $PAGE->navbar->add(get_string('editgeneric', 'totara_hierarchy', format_string($framework->fullname)));
}

echo $OUTPUT->header();

if ($framework->id == 0) {
    echo $OUTPUT->heading(get_string($prefix.'addnewframework', 'totara_hierarchy'));
} else {
    echo $OUTPUT->heading(format_string($framework->fullname));
}

/// Finally display THE form
$frameworkform->display();

/// and proper footer
echo $OUTPUT->footer();
