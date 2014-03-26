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
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');


// type id; 0 if creating a new type
$prefix    = required_param('prefix', PARAM_ALPHA); // hierarchy prefix
$shortprefix = hierarchy::get_short_prefix($prefix);
$id      = optional_param('id', 0, PARAM_INT);    // type id; 0 if creating a new type

$page       = optional_param('page', 0, PARAM_INT);
$returnurl = $CFG->wwwroot . '/totara/hierarchy/type/index.php?prefix='. $prefix;

hierarchy::check_enable_hierarchy($prefix);

$hierarchy = hierarchy::load_hierarchy($prefix);

// If the hierarchy prefix has type editing files use them else use the generic files
if (file_exists($CFG->dirroot.'/totara/hierarchy/prefix/'.$prefix.'/type/edit.php')) {
    require_once($CFG->dirroot.'/totara/hierarchy/prefix/'.$prefix.'/type/edit_form.php');
    require_once($CFG->dirroot.'/totara/hierarchy/prefix/'.$prefix.'/type/edit.php');
    die;
} else {
    require_once($CFG->dirroot.'/totara/hierarchy/type/edit_form.php');
}

// Manage frameworks
admin_externalpage_setup($prefix.'typemanage');

$context = context_system::instance();

if ($id == 0) {
    // creating new type
    require_capability('totara/hierarchy:create'.$prefix.'type', $context);

    $type = new stdClass();
    $type->id = 0;
    $type->description = '';
} else {
    // editing existing type
    require_capability('totara/hierarchy:update'.$prefix.'type', $context);
    if (!$type = $hierarchy->get_type_by_id($id)) {
        print_error('incorrecttypeid', 'totara_hierarchy');
    }
}

// Include JS for icon preview
local_js(array(TOTARA_JS_ICON_PREVIEW));

// create form
$type->descriptionformat = FORMAT_HTML;
$type = file_prepare_standard_editor($type, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                          'totara_hierarchy', $shortprefix.'_type', $type->id);
$datatosend = array('prefix' => $prefix, 'page' => $page);
$typeform  = new type_edit_form(null, $datatosend);
$typeform->set_data($type);

// cancelled
if ($typeform->is_cancelled()) {

    redirect($returnurl);

// update data
} else if ($typenew = $typeform->get_data()) {

    $typenew->timemodified = time();
    $typenew->usermodified = $USER->id;
    //class to hold totara_set_notification info
    $notification = new stdClass();
    $notification->url = $returnurl;
    // new type
    if ($typenew->id == 0) {
        unset($typenew->id);
        $typenew->timecreated = time();

        if (!$typenew->id = $DB->insert_record($shortprefix.'_type', $typenew)) {
            $notification->text = $prefix . 'error:createtype';
            $notification->params = array();
        } else {
            add_to_log(SITEID, $prefix, 'create type', "type/index.php?prefix=$prefix", "{$typenew->fullname} (ID {$typenew->id})");
            $notification->text = $prefix . 'createtype';
            $notification->params = array('class' => 'notifysuccess');
        }
    // Existing type
    } else {
        if (!$DB->update_record($shortprefix.'_type', $typenew)) {
            $notification->text = $prefix . 'error:updatetype';
            $notification->params = array();
        } else {
            add_to_log(SITEID, $prefix, 'update type', "type/edit.php?id={$typenew->id}", "{$typenew->fullname}(ID {$typenew->id})");
            $notification->text = $prefix . 'updatetype';
            $notification->params = array('class' => 'notifysuccess');
        }
    }
    $typenew = file_postupdate_standard_editor($typenew, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_hierarchy', $shortprefix.'_type', $typenew->id);
    $DB->set_field($shortprefix.'_type', 'description', $typenew->description, array('id' => $typenew->id));
    totara_set_notification(get_string($notification->text, 'totara_hierarchy', $typenew->fullname), $notification->url, $notification->params);
}


/// Display page header
$PAGE->navbar->add(get_string("{$prefix}types", 'totara_hierarchy'), $returnurl);

if ($id == 0) {
    $PAGE->navbar->add(get_string('addtype', 'totara_hierarchy'));
} else {
    $PAGE->navbar->add(get_string('editgeneric', 'totara_hierarchy', format_string($type->fullname)));
}

echo $OUTPUT->header();

if ($type->id == 0) {
    echo $OUTPUT->heading(get_string('addtype', 'totara_hierarchy'));
} else {
    echo $OUTPUT->heading(get_string('editgeneric', 'totara_hierarchy', format_string($type->fullname)));
}

/// Finally display the form
$typeform->display();

echo $OUTPUT->footer();
