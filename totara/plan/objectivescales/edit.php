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

// Get parameters
$id = optional_param('id', 0, PARAM_INT); // Objective id; 0 if creating a new objective
// Page setup and check permissions
admin_externalpage_setup('objectivescales');
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('totara/plan:manageobjectivescales', $context);

if ($id == 0) {
    // creating new idp objective
    $objective = new stdClass();
    $objective->id = 0;
    $objective->description = '';
} else {
    // editing existing idp objective
    if (!$objective = $DB->get_record('dp_objective_scale', array('id' => $id))) {
        print_error('error:objectivescaledidincorrect', 'totara_plan');
    }
}


///
/// Handle form data
///
$objective->descriptionformat = FORMAT_HTML;
$objective = file_prepare_standard_editor($objective, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                         'totara_plan', 'dp_objective_scale', $objective->id);
$mform = new edit_objective_form(
        null, // method (default)
        array( // customdata
            'objectiveid' => $id
        )
);
$mform->set_data($objective);

// If cancelled
if ($mform->is_cancelled()) {

    redirect("$CFG->wwwroot/totara/plan/objectivescales/index.php");

// Update data
} else if ($objectivenew = $mform->get_data()) {

    $objectivenew->timemodified = time();
    $objectivenew->usermodified = $USER->id;
    $objectivenew->sortorder = 1 + $DB->get_field_sql("select max(sortorder) from {dp_objective_scale}");

    if (empty($objectivenew->id)) {
        // New objective
        unset($objectivenew->id);
        //set editor field to empty, will be updated properly later
        $objectivenew->description = '';
        $transaction = $DB->start_delegated_transaction();
        $objectivenew->id = $DB->insert_record('dp_objective_scale', $objectivenew);
        $objectivevalues = explode("\n", trim($objectivenew->objectivevalues));
        unset($objectivenew->objectivevalues);
        $sortorder = 1;
        $objectiveidlist = array();
        foreach ($objectivevalues as $objectiveval) {
            if (strlen(trim($objectiveval)) != 0) {
                $objectivevalrec = new stdClass();
                $objectivevalrec->objscaleid = $objectivenew->id;
                $objectivevalrec->name = trim($objectiveval);
                $objectivevalrec->sortorder = $sortorder;
                $objectivevalrec->timemodified = time();
                $objectivevalrec->usermodified = $USER->id;
                // Set the "achieved" objective value to the most competent one
                $objectivevalrec->achieved = ($sortorder == 1) ? 1 : 0;
                $objectiveidlist[] = $DB->insert_record('dp_objective_scale_value', $objectivevalrec);
                $sortorder++;
            }
        }

        // Set the default objective value to the least competent one
        if (count($objectiveidlist)) {
            $objectivenew->defaultid = $objectiveidlist[count($objectiveidlist)-1];
            $DB->update_record('dp_objective_scale', $objectivenew);
        }
        $notification = get_string('objectivescaleadded', 'totara_plan', format_string($objectivenew->name));
        $transaction->allow_commit();
    } else {
        // Existing objective
        $DB->update_record('dp_objective_scale', $objectivenew);
        add_to_log(SITEID, 'objectivescales', 'updated', "view.php?id=$objectivenew->id");
        $notification = get_string('objectivescaleupdated', 'totara_plan', format_string($objectivenew->name));
    }

    //update description
    $objectivenew = file_postupdate_standard_editor($objectivenew, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_plan', 'dp_objective_scale', $objectivenew->id);
    $DB->set_field('dp_objective_scale', 'description', $objectivenew->description, array('id' => $objectivenew->id));
    // Log
    add_to_log(SITEID, 'objectivescales', 'added', "view.php?id=$objectivenew->id");
    totara_set_notification($notification,
        "$CFG->wwwroot/totara/plan/objectivescales/view.php?id={$objectivenew->id}",
        array('class' => 'notifysuccess'));
}

/// Print Page
$PAGE->navbar->add(get_string("objectivescales", 'totara_plan'), new moodle_url('/totara/plan/objectivescales/index.php'));
if ($id == 0) { // Add
    $PAGE->navbar->add(get_string('objectivesscalecreate', 'totara_plan'));
    $heading = get_string('objectivesscalecreate', 'totara_plan');
} else {    //Edit
    $PAGE->navbar->add(get_string('editobjective', 'totara_plan', format_string($objective->name)));
    $heading = get_string('editobjective', 'totara_plan', format_string($objective->name));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
$mform->display();

echo $OUTPUT->footer();
