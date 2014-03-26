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
require_once $CFG->libdir.'/adminlib.php';
require_once 'edit_form.php';
require_once($CFG->dirroot . '/totara/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

///
/// Setup / loading data
///

// Get paramters
$id = optional_param('id', 0, PARAM_INT); // Priority id; 0 if creating a new priority
// Page setup and check permissions
admin_externalpage_setup('priorityscales');
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('totara/plan:managepriorityscales', $context);
if ($id == 0) {
    // creating new Learning Plan priority
    $priority = new stdClass();
    $priority->id = 0;
    $priority->description = '';
} else {
    // editing existing Learning Plan priority
    if (!$priority = $DB->get_record('dp_priority_scale', array('id' => $id))) {
        print_error('error:priorityscaleidincorrect', 'totara_plan');
    }
}

///
/// Handle form data
///
$priority->descriptionformat = FORMAT_HTML;
$priority = file_prepare_standard_editor($priority, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                         'totara_plan', 'dp_priority_scale', $priority->id);
$mform = new edit_priority_form(
        null, // method (default)
        array( // customdata
            'priorityid' => $id
        )
);
$mform->set_data($priority);

// If cancelled
if ($mform->is_cancelled()) {

    redirect("$CFG->wwwroot/totara/plan/priorityscales/index.php");

// Update data
} else if ($prioritynew = $mform->get_data()) {

    $prioritynew->timemodified = time();
    $prioritynew->usermodified = $USER->id;
    $prioritynew->sortorder = 1 + $DB->get_field_sql("SELECT MAX(sortorder) FROM {dp_priority_scale}");

    if (empty($prioritynew->id)) {
        // New priority
        unset($prioritynew->id);
        //set editor field to empty, will be updated properly later
        $prioritynew->description = '';
        $transaction = $DB->start_delegated_transaction();
        $prioritynew->id = $DB->insert_record('dp_priority_scale', $prioritynew);
        $priorityvalues = explode("\n", trim($prioritynew->priorityvalues));
        unset($prioritynew->priorityvalues);
        $sortorder = 1;
        $priorityidlist = array();
        foreach ($priorityvalues as $priorityval) {
            if (strlen(trim($priorityval)) != 0) {
                $priorityvalrec = new stdClass();
                $priorityvalrec->priorityscaleid = $prioritynew->id;
                $priorityvalrec->name = trim($priorityval);
                $priorityvalrec->sortorder = $sortorder;
                $priorityvalrec->timemodified = time();
                $priorityvalrec->usermodified = $USER->id;
                $priorityidlist[] = $DB->insert_record('dp_priority_scale_value', $priorityvalrec);
                $sortorder++;
            }
        }
        // Set the default priority value to the least competent one, and the
        // "proficient" priority value to the most competent one
        if (count($priorityidlist)) {
            $prioritynew->defaultid = $priorityidlist[count($priorityidlist)-1];
            $prioritynew->proficient = $priorityidlist[0];
            $DB->update_record('dp_priority_scale', $prioritynew);
        }
        $notification = get_string('priorityscaleadded', 'totara_plan', format_string($prioritynew->name));
        $transaction->allow_commit();
    } else {
        // Existing priority
        $DB->update_record('dp_priority_scale', $prioritynew);
        add_to_log(SITEID, 'priorityscales', 'updated', "view.php?id=$prioritynew->id");
        $notification = get_string('priorityscaleupdated', 'totara_plan', format_string($prioritynew->name));
    }
    $prioritynew = file_postupdate_standard_editor($prioritynew, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_plan', 'dp_priority_scale', $prioritynew->id);
    $DB->set_field('dp_priority_scale', 'description', $prioritynew->description, array('id' => $prioritynew->id));
    // Log
    add_to_log(SITEID, 'priorityscales', 'added', "view.php?id=$prioritynew->id");
    totara_set_notification($notification,
        "$CFG->wwwroot/totara/plan/priorityscales/view.php?id={$prioritynew->id}",
        array('class' => 'notifysuccess'));
}

/// Print Page
$PAGE->navbar->add(get_string("priorityscales", 'totara_plan'), new moodle_url('/totara/plan/priorityscales/index.php'));

if ($id == 0) { // Add
    $PAGE->navbar->add(get_string('priorityscalecreate', 'totara_plan'));
    $heading = get_string('priorityscalecreate', 'totara_plan');
} else {    //Edit
    $PAGE->navbar->add(get_string('editpriority', 'totara_plan', format_string($priority->name)));
    $heading = get_string('editpriority', 'totara_plan', format_string($priority->name));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
$mform->display();

echo $OUTPUT->footer();
