<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas
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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('editvalue_form.php');
require_once('lib.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

///
/// Setup / loading data
///

$id = optional_param('id', 0, PARAM_INT); // Scale value id; 0 if inserting
$objectivescaleid = optional_param('objscaleid', 0, PARAM_INT); // Objective scale id

// Make sure we have at least one or the other
if (!$id && !$objectivescaleid) {
    print_error('error:incorrectparameters', 'totara_plan');
}

// Page setup and check permissions
admin_externalpage_setup('objectivescales');

$context = context_system::instance();
$PAGE->set_context($context);

if ($id == 0) {
    // Creating new scale value
    require_capability('totara/plan:manageobjectivescales', $context);

    $value = new stdClass();
    $value->id = 0;
    $value->objscaleid = $objectivescaleid;
    $value->sortorder = $DB->get_field('dp_objective_scale_value', 'MAX(sortorder) + 1', array('objscaleid' => $value->objscaleid));
    if (!$value->sortorder) {
        $value->sortorder = 1;
    }
    $value->description = '';

} else {
    // Editing scale value
    require_capability('totara/plan:manageobjectivescales', $context);

    if (!$value = $DB->get_record('dp_objective_scale_value', array('id' => $id))) {
        print_error('error:objectivescalevalueidincorrect', 'totara_plan');
    }
}
if (!$scale = $DB->get_record('dp_objective_scale', array('id' => $value->objscaleid))) {
    print_error('error:objectivescaleidincorrect', 'totara_plan');
}

$scale_used = dp_objective_scale_is_used($scale->id);

// Save objective scale name for display in the form
$value->scalename = format_string($scale->name);

// check scale isn't being used when adding new scale values
if ($value->id == 0 && $scale_used) {
    print_error('error:cannotaddscalevalue', 'totara_plan');
}


///
/// Display page
///

// Create form
$value->descriptionformat = FORMAT_HTML;
$value = file_prepare_standard_editor($value, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                      'totara_plan', 'dp_objective_scale_value', $value->id);
$valueform = new dp_objective_scale_value_edit_form(null, array('scaleid' => $scale->id));
$valueform->set_data($value);

// cancelled
if ($valueform->is_cancelled()) {

    redirect("$CFG->wwwroot/totara/plan/objectivescales/view.php?id={$value->objscaleid}");

// Update data
} else if ($valuenew = $valueform->get_data()) {

    $valuenew->timemodified = time();
    $valuenew->usermodified = $USER->id;

    if (!strlen($valuenew->numericscore)) {
        $valuenew->numericscore = null;
    }

    // Save
    if ($valuenew->id == 0) {
        // New objective scale value
        unset($valuenew->id);
        $valuenew->id = $DB->insert_record('dp_objective_scale_value', $valuenew);
        add_to_log(SITEID, 'objectivescales', 'scale value added', "view.php?id={$valuenew->objscaleid}");
        $notification = get_string('objectivescalevalueadded', 'totara_plan', format_string($valuenew->name));
    } else {
        // Updating objective scale value
        $DB->update_record('dp_objective_scale_value', $valuenew);
        // Log
        add_to_log(SITEID, 'objectivescales', 'scale value updated', "view.php?id={$valuenew->objscaleid}");
       $notification = get_string('objectivescalevalueupdated', 'totara_plan', format_string($valuenew->name));
    }
    $valuenew = file_postupdate_standard_editor($valuenew, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_plan', 'dp_objective_scale_value', $valuenew->id);
    $DB->set_field('dp_objective_scale_value', 'description', $valuenew->description, array('id' => $valuenew->id));
    totara_set_notification($notification, "$CFG->wwwroot/totara/plan/objectivescales/view.php?id={$valuenew->objscaleid}", array('class' => 'notifysuccess'));
}

// Display page header
echo $OUTPUT->header();

if ($id == 0) {
    echo $OUTPUT->heading(get_string('addnewobjectivevalue', 'totara_plan'));
} else {
    echo $OUTPUT->heading(get_string('editobjectivevalue', 'totara_plan'));
}

// Display warning if scale is in use
if ($scale_used) {
    echo $OUTPUT->container(get_string('objectivescaleinuse', 'totara_plan'), 'notifymessage');
}

$valueform->display();

/// and proper footer
echo $OUTPUT->footer();
