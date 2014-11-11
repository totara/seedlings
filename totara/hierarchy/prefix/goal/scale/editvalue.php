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

require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('editvalue_form.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/scale/lib.php');
require_once($CFG->dirroot . '/totara/hierarchy/lib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/lib.php');

// Check if Goals are enabled.
goal::check_feature_enabled();

//
// Setup / loading data.
//

// Scale value id; 0 if inserting.
$id = optional_param('id', 0, PARAM_INT);
$prefix = required_param('prefix', PARAM_ALPHA);
// Goal scale id.
$scaleid = optional_param('scaleid', 0, PARAM_INT);

// Cache user capabilities.
$sitecontext = context_system::instance();

// Set up the page.
admin_externalpage_setup($prefix.'manage');

// Make sure we have at least one or the other.
if (!$id && !$scaleid) {
    print_error('incorrectparameters', 'totara_hierarchy');
}

if ($id == 0) {
    // Creating new scale value.
    require_capability('totara/hierarchy:creategoalscale', $sitecontext);

    $value = new stdClass();
    $value->id = 0;
    $value->description = '';
    $value->scaleid = $scaleid;
    $value->sortorder = $DB->get_field('goal_scale_values', 'MAX(sortorder) + 1', array('scaleid' => $value->scaleid));
    if (!$value->sortorder) {
        $value->sortorder = 1;
    }

} else {
    // Editing scale value.
    require_capability('totara/hierarchy:updategoalscale', $sitecontext);

    if (!$value = $DB->get_record('goal_scale_values', array('id' => $id))) {
        print_error('incorrectgoalscalevalueid', 'totara_hierarchy');
    }
}

if (!$scale = $DB->get_record('goal_scale', array('id' => $value->scaleid))) {
        print_error('incorrectgoalscaleid', 'totara_hierarchy');
}

$scale_used = goal_scale_is_used($scale->id);

// Save scale name for display in the form.
$value->scalename = format_string($scale->name);

// Check scale isn't being used when adding new scale values.
if ($value->id == 0 && $scale_used) {
    print_error('usedscale', 'totara_hierarchy');
}

//
// Display page.
//

// Create form.
$value->descriptionformat = FORMAT_HTML;
$value = file_prepare_standard_editor($value, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                          'totara_hierarchy', 'goal_scale_values', $value->id);
$value->numericscore = isset($value->numericscore) ? format_float($value->numericscore, 5, true, true) : '';
$valueform = new goalscalevalue_edit_form(null, array('scaleid' => $scale->id, 'id' => $id));
$valueform->set_data($value);

if ($valueform->is_cancelled()) {
    // Cancelled.
    redirect("$CFG->wwwroot/totara/hierarchy/prefix/goal/scale/view.php?id={$value->scaleid}&amp;prefix=goal");

} else if ($valuenew = $valueform->get_data()) {
    // Update data.
    $valuenew->timemodified = time();
    $valuenew->usermodified = $USER->id;
    $valuenew->numericscore = unformat_float($valuenew->numericscore);

    if (!strlen($valuenew->numericscore)) {
        $valuenew->numericscore = null;
    }

    // Save.
    // Class to hold totara_set_notification info.
    $notification = new stdClass();
    if ($valuenew->id == 0) {
        // New scale value.
        unset($valuenew->id);

        if ($valuenew->id = $DB->insert_record('goal_scale_values', $valuenew)) {
            // Log.
            $urlpart = "prefix/goal/scale/view.php?id={$valuenew->scaleid}&amp;prefix=goal";
            add_to_log(SITEID, 'goal', 'added scale value', $urlpart);
            $notification->text = 'scalevalueadded';
            $notification->url = "$CFG->wwwroot/totara/hierarchy/" . $urlpart;
            $notification->params = array('class' => 'notifysuccess');
        } else {
            print_error('createscalevaluerecord', 'totara_hierarchy');
        }

    } else {
        // Updating scale value.
        if ($DB->update_record('goal_scale_values', $valuenew)) {
            // Log.
            $urlpart = "prefix/goal/scale/view.php?id={$valuenew->scaleid}&amp;prefix=goal";
            add_to_log(SITEID, 'goal', 'update scale value', $urlpart);
            $notification->text = 'scalevalueupdatedgoal';
            $notification->url = "$CFG->wwwroot/totara/hierarchy/" . $urlpart;
            $notification->params = array('class' => 'notifysuccess');
        } else {
            print_error('updatescalevaluerecord', 'totara_hierarchy');
        }
    }

    // Fix the description field and redirect.
    $valuenew = file_postupdate_standard_editor($valuenew, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
        'totara_hierarchy', 'goal_scale_values', $valuenew->id);
    $DB->set_field('goal_scale_values', 'description', $valuenew->description, array('id' => $valuenew->id));
    totara_set_notification(get_string($notification->text, 'totara_hierarchy', format_string($valuenew->name)),
                        $notification->url, $notification->params);
}

// Display page header.
echo $OUTPUT->header();

if ($id == 0) {
    echo $OUTPUT->heading(get_string('addnewscalevalue', 'totara_hierarchy'));
} else {
    echo $OUTPUT->heading(get_string('editscalevalue', 'totara_hierarchy'));
}

// Display warning if scale is in use.
if ($scale_used) {
    echo $OUTPUT->container(get_string('goalscaleinuse', 'totara_hierarchy'), 'notifysuccess');
}

$valueform->display();

// And proper footer.
echo $OUTPUT->footer();
