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
 * @package totara
 * @subpackage plan
 */

/**
 * Workflow settings page for development plan templates
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');
require_once('template_forms.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

$id = optional_param('id', null, PARAM_INT);
$confirm = optional_param('confirm', null, PARAM_ALPHA); // notice flag

admin_externalpage_setup('managetemplates');

if (!$template = $DB->get_record('dp_template', array('id' => $id))) {
    print_error('error:invalidtemplateid', 'totara_plan');
}

if ($confirm) {
    $workflow = $confirm;
    $returnurl = $CFG->wwwroot . '/totara/plan/template/workflow.php?id=' . $id;

    $classfile = $CFG->dirroot .
        "/totara/plan/workflows/{$workflow}/{$workflow}.class.php";
    if (!is_readable($classfile)) {
        $string_parameters = new stdClass();
        $string_parameters->classfile = $classfile;
        $string_parameters->workflow = $workflow;
        throw new PlanException(get_string('noclassfileforworkflow', 'totara_plan', $string_parameters));
    }
    include_once($classfile);

    // check class exists
    $class = "dp_{$workflow}_workflow";
    if (!class_exists($class)) {
        $string_parameters = new stdClass();
        $string_parameters->class = $class;
        $string_parameters->workflow = $workflow;
        throw new PlanException(get_string('noclassforworkflow', 'totara_plan', $string_parameters));
    }

    // create an instance and save as a property for easy access
    $wf = new $class();

    if (!$wf->copy_to_db($template->id)) {
        totara_set_notification(get_string('error:update_workflow_settings', 'totara_plan'), $returnurl);
    }

    // Add checking to this method
    $DB->set_field('dp_template', 'workflow', $workflow, array('id' => $id));
    add_to_log(SITEID, 'plan', 'changed workflow', "template/workflow.php?id={$id}", "Template ID:{$id}");
    totara_set_notification(get_string('update_workflow_settings', 'totara_plan'), $returnurl, array('class' => 'notifysuccess'));

}

$mform = new dp_template_workflow_form(null,
    array('id' => $id, 'workflow' => $template->workflow));

if ($mform->is_cancelled()) {
    // user cancelled form
    redirect($CFG->wwwroot . '/totara/plan/template/workflow.php?id=' . $id);
}
else if ($mform->no_submit_button_pressed()) {
    // user pressed advanced options button
    redirect($CFG->wwwroot . '/totara/plan/template/advancedworkflow.php?id='.$id);
}

if ($fromform = $mform->get_data()) {
    $workflow = $fromform->workflow;
    $returnurl = $CFG->wwwroot . '/totara/plan/template/workflow.php?id=' . $id;
    $changeurl = $CFG->wwwroot . '/totara/plan/template/workflow.php?id=' . $id . '&amp;confirm=' . $workflow;
    if ($workflow != 'custom') {
        // handle form submission
        if ($template->workflow != $workflow) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading($template->fullname);
            $classfile = $CFG->dirroot .
                "/totara/plan/workflows/{$workflow}/{$workflow}.class.php";
            if (!is_readable($classfile)) {
                $string_parameters = new stdClass();
                $string_parameters->classfile = $classfile;
                $string_parameters->workflow = $workflow;
                throw new PlanException(get_string('noclassfileforworkflow', 'totara_plan', $string_parameters));
            }
            include_once($classfile);

            // check class exists
            $class = "dp_{$workflow}_workflow";
            if (!class_exists($class)) {
                $string_parameters = new stdClass();
                $string_parameters->class = $class;
                $string_parameters->workflow = $workflow;
                throw new PlanException(get_string('noclassforworkflow', 'totara_plan', $string_parameters));
            }

            // create an instance and save as a property for easy access
            $wf = new $class();
            $diff = $wf->list_differences($template->id);
            if (!$diff) {
                $differences = html_writer::tag('p', get_string('nochanges', 'totara_plan'));
            } else {
                $differences = dp_print_workflow_diff($diff);
            }

            $template_in_use = $DB->count_records('dp_plan', array('templateid' => $template->id)) > 0;
            $scales_locked = '';
            if ($template_in_use) {
                $scales_locked = html_writer::tag('p', html_writer::tag('b', get_string('scaleslocked', 'totara_plan')));
            }

            $changeworkflowconfirm = get_string('changeworkflowconfirm', 'totara_plan', get_string($fromform->workflow.'workflowname', 'totara_plan')) . $scales_locked . $differences;

            echo $OUTPUT->confirm($changeworkflowconfirm, $changeurl, $returnurl);
        } else {
            //If no change and saving just show notification with no processing
            totara_set_notification(get_string('update_workflow_settings', 'totara_plan'), $returnurl, array('class' => 'notifysuccess'));
        }
    } else {
        // Add checking to this method
        $DB->set_field('dp_template', 'workflow', $workflow, array('id' => $id));
        totara_set_notification(get_string('update_workflow_settings', 'totara_plan'), $returnurl, array('class' => 'notifysuccess'));
    }

} else {
    $PAGE->navbar->add(get_string("managetemplates", "totara_plan"), new moodle_url("/totara/plan/template/index.php"));
    $PAGE->navbar->add(format_string($template->fullname));

    echo $OUTPUT->header();

    echo $OUTPUT->heading(format_string($template->fullname));

    $currenttab = 'workflow';
    require('tabs.php');

    $mform->display();
}

echo $OUTPUT->footer();
