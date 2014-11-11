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
 * @subpackage hierarchy
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/customfield/fieldlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/item/edit_form.php');
require_once($CFG->dirroot.'/totara/hierarchy/lib.php');


///
/// Setup / loading data
///

$prefix = required_param('prefix', PARAM_ALPHA);
$shortprefix = hierarchy::get_short_prefix($prefix);

// item id; 0 if creating new item
$id   = optional_param('id', 0, PARAM_INT);

// framework id; required when creating a new framework item
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$page       = optional_param('page', 0, PARAM_INT);

hierarchy::check_enable_hierarchy($prefix);

$hierarchy = hierarchy::load_hierarchy($prefix);

// We require either an id for editing, or a framework for creating
if (!$id && !$frameworkid) {
    print_error('incorrectparameters', 'totara_hierarchy');
}

// Make this page appear under the manage competencies admin item
admin_externalpage_setup($prefix.'manage', '', array('prefix' => $prefix));

$context = context_system::instance();

if ($id == 0) {
    // creating new item
    require_capability('totara/hierarchy:create'.$prefix, $context);

    $item = new stdClass();
    $item->id = 0;
    $item->description = '';
    $item->frameworkid = $frameworkid;
    $item->visible = 1;
    $item->typeid = 0;

} else {
    // editing existing item
    require_capability('totara/hierarchy:update'.$prefix, $context);

    if (!$item = $DB->get_record($shortprefix, array('id' => $id))) {
        print_error('incorrectid', 'totara_hierarchy');
    }
    $frameworkid = $item->frameworkid;
    // load custom fields data - customfield values need to be available in $item before the call to set_data
    if ($id != 0) {
        customfield_load_data($item, $prefix, $shortprefix.'_type');
    }
}

// Load framework
if (!$framework = $DB->get_record($shortprefix.'_framework', array('id' => $frameworkid))) {
    print_error('invalidframeworkid', 'totara_hierarchy', $prefix);
}
$item->framework = $framework->fullname;


///
/// Display page
///

// create form
$item->descriptionformat = FORMAT_HTML;
$item = file_prepare_standard_editor($item, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                          'totara_hierarchy', $shortprefix, $item->id);
$datatosend = array('prefix' => $prefix, 'item' => $item, 'page' => $page, 'hierarchy' => $hierarchy);
$itemform = new item_edit_form(null, $datatosend);
$itemform->set_data($item);

// cancelled
if ($itemform->is_cancelled()) {

    redirect("{$CFG->wwwroot}/totara/hierarchy/index.php?prefix=$prefix&amp;frameworkid={$item->frameworkid}&amp;page=$page");

// Update data
} else if ($itemnew = $itemform->get_data()) {

    if (isset($itemnew->changetype)) {
        redirect($CFG->wwwroot . "/totara/hierarchy/type/change.php?prefix=$prefix&amp;frameworkid={$item->frameworkid}&amp;page={$page}&typeid={$itemnew->typeid}&amp;itemid={$itemnew->id}");
    }

    $itemnew->timemodified = time();
    $itemnew->usermodified = $USER->id;

    // Format any fields unique to this type of hierarchy.
    $itemnew = $hierarchy->process_additional_item_form_fields($itemnew);

    // Save
    // Class to hold totara_set_notification info.
    $notification = new stdClass();

    if ($itemnew->id == 0) {
        // Add New item
        if ($updateditem = $hierarchy->add_hierarchy_item($itemnew, $itemnew->parentid, $itemnew->frameworkid, false)) {
            $itemnew->id = $updateditem->id;
            add_to_log(SITEID, $prefix, 'added item', "item/view.php?id={$updateditem->id}&amp;prefix={$prefix}", substr(strip_tags($updateditem->fullname), 0, 200) . " (ID {$updateditem->id})");
            $notification->text = 'added';
            $notification->url = "{$CFG->wwwroot}/totara/hierarchy/item/view.php?prefix=$prefix&id={$updateditem->id}";
            $notification->params = array('class' => 'notifysuccess');
        } else {
            $notification->text = 'error:add';
            $notification->url = "{$CFG->wwwroot}/totara/hierarchy/index.php?prefix=$prefix";
            $notification->params = array();
        }
    } else {
        // Update existing item
        $transaction = $DB->start_delegated_transaction();
        $updateditem = $hierarchy->update_hierarchy_item($itemnew->id, $itemnew, false, false);
        customfield_save_data($itemnew, $prefix, $shortprefix.'_type');
        $transaction->allow_commit();
        $notification->text = 'updated';
        $notification->url = "{$CFG->wwwroot}/totara/hierarchy/item/view.php?prefix=$prefix&id={$itemnew->id}";
        $notification->params = array('class' => 'notifysuccess');
    }
    //fix the description field and redirect
    $itemnew = file_postupdate_standard_editor($itemnew, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_hierarchy', $shortprefix, $itemnew->id);
    $DB->set_field($shortprefix, 'description', $itemnew->description, array('id' => $itemnew->id));
    totara_set_notification(get_string($notification->text . $prefix, 'totara_hierarchy', format_string($itemnew->fullname)), $notification->url, $notification->params);
}

$PAGE->navbar->add(format_string($framework->fullname), new moodle_url('/totara/hierarchy/index.php', array('prefix' => $prefix, 'frameworkid' => $framework->id)));
if ($item->id) {
    $PAGE->navbar->add(format_string($item->fullname), new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => $prefix, 'id' => $item->id)));
    $PAGE->navbar->add(get_string('edit'.$prefix, 'totara_hierarchy'));
} else {
    $PAGE->navbar->add(get_string('addnew'.$prefix, 'totara_hierarchy'));
}

/// Display page header
echo $OUTPUT->header();

if ($item->id == 0) {
    echo $OUTPUT->heading(get_string('addnew'.$prefix, 'totara_hierarchy'));
} else {
    echo $OUTPUT->heading(get_string('edit'.$prefix, 'totara_hierarchy'));
}

/// Finally display THE form
$itemform->display();

/// and proper footer
echo $OUTPUT->footer();
