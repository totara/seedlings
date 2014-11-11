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
$priorityscaleid = optional_param('priorityscaleid', 0, PARAM_INT); // Priority scale id

// Make sure we have at least one or the other
if (!$id && !$priorityscaleid) {
    print_error('error:incorrectparameters', 'totara_plan');
}

// Page setup and check permissions
$context = context_system::instance();
$PAGE->set_context($context);
admin_externalpage_setup('priorityscales');

require_capability('totara/plan:managepriorityscales', $context);
if ($id == 0) {
    // Creating new scale value
    $value = new stdClass();
    $value->id = 0;
    $value->priorityscaleid = $priorityscaleid;
    $value->description = '';
    $value->sortorder = $DB->get_field('dp_priority_scale_value', 'MAX(sortorder) + 1', array('priorityscaleid' => $value->priorityscaleid));
    if (!$value->sortorder) {
        $value->sortorder = 1;
    }
} else {
    // Editing scale value
    if (!$value = $DB->get_record('dp_priority_scale_value', array('id' => $id))) {
        print_error('error:priorityscalevalueidincorrect', 'totara_plan');
    }
}

if (!$scale = $DB->get_record('dp_priority_scale', array('id' => $value->priorityscaleid))) {
    print_error('error:priorityscaleidincorrect', 'totara_plan');
}
$scale_used = dp_priority_scale_is_used($scale->id);

// Save priority scale name for display in the form
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
                                     'totara_plan', 'dp_priority_scale_value', $value->id);
$valueform = new dp_priority_scale_value_edit_form();
$valueform->set_data($value);

// cancelled
if ($valueform->is_cancelled()) {

    redirect("$CFG->wwwroot/totara/plan/priorityscales/view.php?id={$value->priorityscaleid}");

// Update data
} else if ($valuenew = $valueform->get_data()) {

    $valuenew->timemodified = time();
    $valuenew->usermodified = $USER->id;

    if (!strlen($valuenew->numericscore)) {
        $valuenew->numericscore = null;
    }

    // Save
    // New priority scale value
    if ($valuenew->id == 0) {
        unset($valuenew->id);

        $valuenew->id = $DB->insert_record('dp_priority_scale_value', $valuenew);
        // Log
        add_to_log(SITEID, 'priorityscales', 'scale value added', "view.php?id={$valuenew->priorityscaleid}");
        $notification = get_string('priorityscalevalueadded', 'totara_plan', format_string($valuenew->name));
    } else {
        // Updating priority scale value
        $DB->update_record('dp_priority_scale_value', $valuenew);
        // Log
        add_to_log(SITEID, 'priorityscales', 'scale value updated', "view.php?id={$valuenew->priorityscaleid}");
        $notification = get_string('priorityscalevalueupdated', 'totara_plan', format_string($valuenew->name));
    }
    $valuenew = file_postupdate_standard_editor($valuenew, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_plan', 'dp_priority_scale_value', $valuenew->id);
    $DB->set_field('dp_priority_scale_value', 'description', $valuenew->description, array('id' => $valuenew->id));
    totara_set_notification($notification,
                            "$CFG->wwwroot/totara/plan/priorityscales/view.php?id={$valuenew->priorityscaleid}",
                            array('class' => 'notifysuccess'));
}
// Display page header
echo $OUTPUT->header();

if ($id == 0) {
    echo $OUTPUT->heading(get_string('addnewpriorityvalue', 'totara_plan'));
} else {
    echo $OUTPUT->heading(get_string('editpriorityvalue', 'totara_plan'));
}

// Display warning if scale is in use
if ($scale_used) {
    echo $OUTPUT->container(get_string('priorityscaleinuse', 'totara_plan'), 'notifymessage');
}

$valueform->display();

/// and proper footer
echo $OUTPUT->footer();
