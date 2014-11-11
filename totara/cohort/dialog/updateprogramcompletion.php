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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */
/**
 * This file is the ajax handler for updating the completion settings for a program which is
 * in a cohort's enrolled learning items
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/totara/program/lib.php');
require_once($CFG->dirroot.'/cohort/lib.php');

$PAGE->set_context(context_system::instance());
require_login();
require_capability('moodle/cohort:manage', context_system::instance());

$programid = required_param('programid', PARAM_INT);
$cohortid = required_param('cohortid', PARAM_INT);

// TODO: when they add some white-listing to edit_assignments.php, add it here too

$completiontime = optional_param('completiontime', null, PARAM_TEXT);
$completionevent = optional_param('completionevent', null, PARAM_INT);
$completioninstance = optional_param('completioninstance', null, PARAM_INT);

require_sesskey();

// Load the data into an array of the form required by prog_assignment_category::update_assignments()
$data = new stdClass();
$data->id = $programid;
$data->item = array(
    ASSIGNTYPE_COHORT => array(
        $cohortid=>1,
    )
);
$data->completiontime = array(
    ASSIGNTYPE_COHORT => array(
        $cohortid => $completiontime
    )
);
$data->completionevent = array(
    ASSIGNTYPE_COHORT => array(
        $cohortid => $completionevent
    )
);
$data->completioninstance = array(
    ASSIGNTYPE_COHORT => array(
        $cohortid => $completioninstance
    )
);

$cat = new cohorts_category();
$cat->update_assignments($data, false);

// Update the assignment of learners to the program
$prog = new program($programid);
$prog->get_assignments()->init_assignments($programid);
$prog->update_learner_assignments();

echo totara_cohort_program_completion_link($cohortid, $programid);
