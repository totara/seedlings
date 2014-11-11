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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage plan
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

abstract class dp_base_workflow {

    function __construct() {

        // check that child classes implement required properties
        $properties = array(
            'classname',

            //course config options
            'cfg_course_duedatemode',
            'cfg_course_prioritymode',

            //competency config options
            'cfg_competency_autoassignpos',
            'cfg_competency_autoassignorg',
            'cfg_competency_autoassigncourses',
            'cfg_competency_duedatemode',
            'cfg_competency_prioritymode',

            //objective config options
            'cfg_objective_duedatemode',
            'cfg_objective_prioritymode',

            //plan permission settings
            'perm_plan_view_learner',
            'perm_plan_view_manager',
            'perm_plan_create_learner',
            'perm_plan_create_manager',
            'perm_plan_update_learner',
            'perm_plan_update_manager',
            'perm_plan_delete_learner',
            'perm_plan_delete_manager',
            'perm_plan_approve_learner',
            'perm_plan_approve_manager',
            'perm_plan_completereactivate_learner',
            'perm_plan_completereactivate_manager',

            //course permission settings
            'perm_course_updatecourse_learner',
            'perm_course_updatecourse_manager',
            'perm_course_commenton_learner',
            'perm_course_commenton_manager',
            'perm_course_setpriority_learner',
            'perm_course_setpriority_manager',
            'perm_course_setduedate_learner',
            'perm_course_setduedate_manager',
            'perm_course_setcompletionstatus_learner',
            'perm_course_setcompletionstatus_manager',

            //competency permission settings
            'perm_competency_updatecompetency_learner',
            'perm_competency_updatecompetency_manager',
            'perm_competency_commenton_learner',
            'perm_competency_commenton_manager',
            'perm_competency_setpriority_learner',
            'perm_competency_setpriority_manager',
            'perm_competency_setduedate_learner',
            'perm_competency_setduedate_manager',
            'perm_competency_setproficiency_learner',
            'perm_competency_setproficiency_manager',

            //objective permission settings
            'perm_objective_updateobjective_learner',
            'perm_objective_updateobjective_manager',
            'perm_objective_commenton_learner',
            'perm_objective_commenton_manager',
            'perm_objective_setpriority_learner',
            'perm_objective_setpriority_manager',
            'perm_objective_setduedate_learner',
            'perm_objective_setduedate_manager',
            'perm_objective_setproficiency_learner',
            'perm_objective_setproficiency_manager',
        );
        foreach ($properties as $property) {
            if (!property_exists($this, $property)) {
                $msg = new stdClass();
                $msg->class = get_class($this);
                $msg->property = $property;
                throw new Exception(get_string('error:propertymustbeset', 'totara_plan', $msg));
            }
        }
        // reserve the name 'custom' for use by the system
        if ($this->classname == 'custom') {
            throw new Exception(get_string('error:cantcreatecustomworkflow', 'totara_plan'));
        }

        // get name and description lang string based on name
        $this->name = get_string($this->classname . 'workflowname', 'totara_plan');
        $this->description = get_string($this->classname . 'workflowdesc', 'totara_plan');
    }


    /**
     * Returns a list of differences between the workflow's settings
     * and the current database settings used to let the user know
     * what will change if they switch workflows.
     *
     * @param int templateid id of the current template
     * @return array diff an array of changes
     */
    function list_differences($templateid) {
        global $DB;
        $diff = array();
        if (!$course_settings = $DB->get_record('dp_course_settings', array('templateid' => $templateid))) {
            print_error('error:missingcoursesettings', 'totara_plan');
        }

        if (!$competency_settings = $DB->get_record('dp_competency_settings', array('templateid' => $templateid))) {
            print_error('error:missingcompetencysettings', 'totara_plan');
        }

        if (!$objective_settings = $DB->get_record('dp_objective_settings', array('templateid' => $templateid))) {
            print_error('error:missingobjectivesettings', 'totara_plan');
        }

        if (!$program_settings = $DB->get_record('dp_program_settings', array('templateid' => $templateid))) {
            print_error('error:missingprogramsettings', 'totara_plan');
        }
        $template_in_use = $DB->count_records('dp_plan', array('templateid' => $templateid)) > 0;

        foreach (get_object_vars($this) as $property => $value) {
            $parts = explode('_', $property);
            if ($parts[0] == 'cfg') {
                switch($parts[1]) {
                case 'program':
                    $attribute = $parts[2];
                    if ($value != $course_settings->$attribute) {
                        $diff[$property] = array('before' => $course_settings->$attribute, 'after' => $value);
                    }
                    break;
                case 'course':
                    $attribute = $parts[2];
                    if ($value != $course_settings->$attribute) {
                        if ($attribute == 'priorityscale') {
                            $before = $DB->get_field('dp_priority_scale', 'name', array('id' => $course_settings->$attribute));
                            $after = $DB->get_field('dp_priority_scale', 'name', array('id' => $value));
                            if (!$template_in_use) {
                                $diff[$property] = array('before' => $before, 'after' => $after);
                            }
                        } else {
                            $diff[$property] = array('before' => $course_settings->$attribute, 'after' => $value);
                        }
                    }
                    break;
                case 'competency':
                    $attribute = $parts[2];
                    if ($value != $competency_settings->$attribute) {
                        if ($attribute == 'priorityscale') {
                            $before = $DB->get_field('dp_priority_scale', 'name', array('id' => $competency_settings->$attribute));
                            $after = $DB->get_field('dp_priority_scale', 'name', array('id' => $value));
                            if (!$template_in_use) {
                                $diff[$property] = array('before' => $before, 'after' => $after);
                            }
                        } else {
                            $diff[$property] = array('before' => $competency_settings->$attribute, 'after' => $value);
                        }
                    }
                    break;
                case 'objective':
                    $attribute = $parts[2];
                    if ($value != $objective_settings->$attribute) {
                        if ($attribute == 'priorityscale') {
                            $before = $DB->get_field('dp_priority_scale', 'name', array('id' => $competency_settings->$attribute));
                            $after = $DB->get_field('dp_priority_scale', 'name', array('id' => $value));
                            if (!$template_in_use) {
                                $diff[$property] = array('before' => $before, 'after' => $after);
                            }
                        } else if ($attribute == 'objectivescale') {
                            $before = $DB->get_field('dp_objective_scale', 'name', array('id' => $competency_settings->$attribute));
                            $after = $DB->get_field('dp_objective_scale', 'name', array('id' => $value));
                            if (!$template_in_use) {
                                $diff[$property] = array('before' => $before, 'after' => $after);
                            }
                        } else {
                            $diff[$property] = array('before' => $objective_settings->$attribute, 'after' => $value);
                        }
                    }
                    break;
                }

            } else if ($parts[0] == 'perm') {
                $sql = "SELECT value FROM {dp_permissions} WHERE templateid = ? AND role = ? AND component = ? AND action = ?";
                $dbval = $DB->get_field_sql($sql, array($templateid, $parts[3], $parts[1], $parts[2]));

                if ($value != $dbval) {
                    $diff[$property] = array('before' => $dbval, 'after' => $value);
                }

            }
        }
        return $diff;
    }

    /**
     * Copies all the settings and permissions for a workflow to
     * the database, overriding existing values
     *
     * @param int $templateid id of the current template
     * @return bool
     */
    function copy_to_db($templateid) {
        global $CFG, $DB;

        $returnurl = $CFG->wwwroot . '/totara/plan/template/workflow?id=' . $templateid;

        if (!$templateid) {
            print_error('error:templateid', 'totara_plan');
        }

        $plan_todb = new stdClass();
        if ($plan_settings = $DB->get_record('dp_plan_settings', array('templateid' => $templateid))) {
            $plan_todb->id = $plan_settings->id;
        }
        $course_todb = new stdClass();
        if ($course_settings = $DB->get_record('dp_course_settings', array('templateid' => $templateid))) {
            $course_todb->id = $course_settings->id;
        }
        $competency_todb = new stdClass();
        if ($competency_settings = $DB->get_record('dp_competency_settings', array('templateid' => $templateid))) {
            $competency_todb->id = $competency_settings->id;
        }
        $objective_todb = new stdClass();
        if ($objective_settings = $DB->get_record('dp_objective_settings', array('templateid' => $templateid))) {
            $objective_todb->id = $objective_settings->id;
        }
        $program_todb = new stdClass();
        if ($program_settings = $DB->get_record('dp_program_settings', array('templateid' => $templateid))) {
            $program_todb->id = $program_settings->id;
        }
        $template_in_use = $DB->count_records('dp_plan', array('templateid' => $templateid)) > 0;

            $transaction = $DB->start_delegated_transaction();

            foreach (get_object_vars($this) as $property => $value) {
                $parts = explode('_', $property);
                if ($parts[0] == 'cfg') {
                    switch($parts[1]) {
                    case 'plan':
                        $plan_todb->$parts[2] = $value;
                        break;
                    case 'course':
                        if ($parts[2] == 'priorityscale') {
                            if (!$template_in_use) {
                                $course_todb->$parts[2] = $value;
                            }
                        } else {
                            $course_todb->$parts[2] = $value;
                        }
                        break;
                    case 'competency':
                        if ($parts[2] == 'priorityscale') {
                            if (!$template_in_use) {
                                $competency_todb->$parts[2] = $value;
                            }
                        } else {
                            $competency_todb->$parts[2] = $value;
                        }
                        break;
                    case 'objective':
                        if ($parts[2] == 'priorityscale' || $parts[2] == 'objectivescale') {
                            if (!$template_in_use) {
                                $objective_todb->$parts[2] = $value;
                            }
                        } else {
                            $objective_todb->$parts[2] = $value;
                        }
                        break;
                    case 'program':
                        if ($parts[2] == 'priorityscale') {
                            if (!$template_in_use) {
                                $program_todb->$parts[2] = $value;
                            }
                        } else {
                            $program_todb->$parts[2] = $value;
                        }
                    }
                } else if ($parts[0] == 'perm') {
                    $perm_todb = new stdClass();
                    $perm_todb->templateid = $templateid;
                    $perm_todb->role = $parts[3];
                    $perm_todb->component = $parts[1];
                    $perm_todb->action = $parts[2];
                    $perm_todb->value = $value;
                    $sql = "SELECT * FROM {dp_permissions} WHERE templateid = ? AND role = ? AND component = ? AND action = ?";
                    if ($record = $DB->get_record_sql($sql, array($templateid, $parts[3], $parts[1], $parts[2]), IGNORE_MISSING)) {
                        //update
                        $perm_todb->id = $record->id;
                        $DB->update_record('dp_permissions', $perm_todb);
                    } else {
                        //insert
                        $newid = $DB->insert_record('dp_permissions', $perm_todb);
                    }
                }
            }
            //Write settings to tables
            if ($plan_settings) {
                $DB->update_record('dp_plan_settings', $plan_todb);
            } else {
                $plan_todb->templateid = $templateid;
                $DB->insert_record('dp_plan_settings', $plan_todb);
            }
            if ($course_settings) {
                $DB->update_record('dp_course_settings', $course_todb);
            } else {
                $course_todb->templateid = $templateid;
                $DB->insert_record('dp_course_settings', $course_todb);
            }
            if ($competency_settings) {
                $DB->update_record('dp_competency_settings', $competency_todb);
            } else {
                $competency_todb->templateid = $templateid;
                $DB->insert_record('dp_competency_settings', $competency_todb);
            }
            if ($objective_settings) {
                $DB->update_record('dp_objective_settings', $objective_todb);
            } else {
                $objective_todb->templateid = $templateid;
                $DB->insert_record('dp_objective_settings', $objective_todb);
            }
            if ($program_settings) {
                $DB->update_record('dp_program_settings', $program_todb);
            } else {
                $program_todb->templateid = $templateid;
                $DB->insert_record('dp_program_settings', $program_todb);
            }
            $transaction->allow_commit();
        return true;

    }
}
