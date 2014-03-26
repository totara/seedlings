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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/goal/lib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/organisation/lib.php');
require_once($CFG->dirroot.'/totara/cohort/lib.php');

// Set up some parameters.
// Instance id of the org/pos/cohort.
$assignto = required_param('assignto', PARAM_INT);

// Assignment module type 'pos/org/cohort'.
$assigntype = required_param('assigntype', PARAM_INT);

// Framework id.
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);

// Goals to add.
$add = optional_param('add', false, PARAM_BOOL);
$listofvalues = required_param('selected', PARAM_SEQUENCE);

// Whether or not to include all the children of the additions.
$includechildren = optional_param('includechildren', false, PARAM_BOOL);

// Non JS parameters.
$nojs = optional_param('nojs', false, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$sesskey = optional_param('sesskey', '', PARAM_TEXT);

// Check permissions.
$sitecontext = context_system::instance();
$straddgoals = get_string('addgoals', 'totara_hierarchy');
require_sesskey();

// You must have some form of managegoals permission to see this page.
$admin = has_capability('totara/hierarchy:managegoalassignments', $sitecontext);
$manager = false;
$self = false;

if ($assigntype == GOAL_ASSIGNMENT_INDIVIDUAL) {
    $user_context = context_user::instance($assignto);
        $manager = totara_is_manager($assignto) && has_capability('totara/hierarchy:managestaffcompanygoal', $user_context);
        $self = $USER->id == $assignto && has_capability('totara/hierarchy:manageowncompanygoal', $user_context);
}

if (!($admin || $manager || $self)) {
    print_error('error:addgoals', 'totara_hierarchy');
}

// Set up some assignment type specific varibles.
$type = goal::goal_assignment_type_info($assigntype);
$module = new $type->fullname();
$goal = new goal();

// Make sure the url is set (if js is on) for the log.
if (empty($returnurl)) {
    switch ($assigntype) {
        case GOAL_ASSIGNMENT_INDIVIDUAL:
            $returnurl = "/totara/hierarchy/prefix/goal/mygoals.php?userid={$assignto}";
            break;
        case GOAL_ASSIGNMENT_AUDIENCE:
            $returnurl = "/cohort/view.php?id={$assignto}";
            break;
        case GOAL_ASSIGNMENT_POSITION:
        case GOAL_ASSIGNMENT_ORGANISATION:
            $returnurl = "/totara/hierarchy/item/view.php?prefix={$type->fullname}&id={$assignto}";
            break;
    }
}

// String of params needed in non-js url strings.
$urlparams = array('assignto' => $assignto,
                   'assigntype' => $assigntype,
                   'frameworkid' => $frameworkid,
                   'nojs' => $nojs,
                   'returnurl' => $returnurl,
                   'includechildren' => $includechildren,
             );

// Set up the page.
$PAGE->set_url(new moodle_url('/totara/hierarchy/prefix/goal/assign/assign.php'), $urlparams);
$PAGE->set_context($sitecontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_totara_menu_selected('mygoals');
$PAGE->set_title($straddgoals);
$PAGE->set_heading($straddgoals);

if (!$assigntype == GOAL_ASSIGNMENT_INDIVIDUAL) {
    // Load an instance of grouptype.
    if (!$moditem = $module->get_item($assignto)) {
        print_error($type->fullname . 'notfound', 'totara_hierarchy');
    }
}

if ($add) {
    // Parse input.
    $items = $listofvalues ? explode(',', $listofvalues) : array();
    $time = time();

    // Assign goals.
    foreach ($items as $item) {
        // Check if it is already assigned.
        if (goal::currently_assigned($assigntype, $assignto, $item)) {
            print_error('error:alreadyassigned', 'totara_hierarchy');
        }

        // Check id.
        if (!is_numeric($item)) {
            print_error('baddatanonnumeric', 'totara_hierarchy');
        }

        // So we can use it as a dynamic class field.
        $field = $type->field;

        // Add relationship.
        $relationship = new stdClass();
        $relationship->$field = $assignto;
        $relationship->assigntype = $assigntype;
        $relationship->goalid = $item;
        $relationship->timemodified = $time;
        $relationship->usermodified = $USER->id;
        $relationship->includechildren = $includechildren;

        if ($assigntype == GOAL_ASSIGNMENT_INDIVIDUAL) {
            // Make sure the assignment id is set.
            $relationship->assignmentid = 0;

            // Set up the default scale value.
            $sql = "SELECT s.defaultid
                    FROM {goal} g
                    JOIN {goal_scale_assignments} sa
                        ON g.frameworkid = sa.frameworkid
                    JOIN {goal_scale} s
                        ON sa.scaleid = s.id
                    WHERE g.id = ?";
            $scale = $DB->get_record_sql($sql, array($item));

            $scale_default = new stdClass();
            $scale_default->goalid = $item;
            $scale_default->userid = $assignto;
            $scale_default->scalevalueid = $scale->defaultid;

            // Create the individual assignment.
            $DB->insert_record($type->table, $relationship);
            $goalrecords = goal::get_goal_items(array('goalid' => $item, 'userid' => $assignto), goal::SCOPE_COMPANY);
            if (empty($goalrecords)) {
                goal::insert_goal_item($scale_default, goal::SCOPE_COMPANY);
            }
        } else {
            // Make the assignment, then create all the current user assignments.
            $relationship->id = $DB->insert_record($type->table, $relationship);
            $goal->create_user_assignments($assigntype, $relationship, $relationship->includechildren);
        }

        add_to_log(SITEID, $type->fullname, 'create goal assignments', $returnurl, "{$type->fullname} (ID {$assignto})");
    }

    // Set up returning the html and closing the dialog.
    if ($nojs) {
        // If JS disabled, redirect back to original page (only if session key matches).
        $url = ($sesskey == sesskey()) ? $returnurl : $CFG->wwwroot;
        redirect($url);
    } else {

        $out = '';
        switch ($assigntype) {
            case GOAL_ASSIGNMENT_INDIVIDUAL:
                $renderer = $PAGE->get_renderer('totara_hierarchy');
                $out .= $renderer->mygoals_company_table($assignto, true);
                break;
            case GOAL_ASSIGNMENT_AUDIENCE:
                $cohort = $DB->get_record('cohort', array('id' => $assignto));
                $out .= cohort::display_goal_table($cohort, true);
                break;
            case GOAL_ASSIGNMENT_POSITION:
            case GOAL_ASSIGNMENT_ORGANISATION:
                $renderer = $PAGE->get_renderer('totara_hierarchy');
                $addgoalurl = new moodle_url('/totara/hierarchy/prefix/goal/assign/find.php',
                        array('assignto' => $assignto, 'assigntype' => GOAL_ASSIGNMENT_POSITION));
                $out .= $renderer->print_assigned_goals($type->fullname, $type->shortname, $addgoalurl, $assignto);
                break;
        }

        echo "DONE$out";
        exit();
    }
}
