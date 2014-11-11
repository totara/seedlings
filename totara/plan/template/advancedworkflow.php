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
 * @author Alastair Munro
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
require_once($CFG->dirroot."/totara/core/js/lib/setup.php");

// Check if Learning plans are enabled.
check_learningplan_enabled();

$id = required_param('id', PARAM_INT);
$notice = optional_param('notice', 0, PARAM_INT); // notice flag
$component = optional_param('component', 'plan', PARAM_TEXT);
$currentcomponent = $component;

if ($currentcomponent == 'competency') {
    local_js();
    $PAGE->requires->js('/totara/plan/components/competency/competency.settings.js');
}

admin_externalpage_setup('managetemplates');

$template = $DB->get_record('dp_template', array('id' => $id));

$components = $DB->get_records('dp_component_settings', array('templateid' => $id), 'sortorder');
$plans = $DB->count_records('dp_plan', array('templateid' => $id));
if (!empty($plans)) {
    $templateinuse = true;
} else {
    $templateinuse = false;
}

$mform = new dp_template_advanced_workflow_form(null,
    array('id' => $id, 'component' => $component, 'templateinuse' => $templateinuse));

if ($mform->is_cancelled()) {
    // user cancelled form
}
if ($fromform = $mform->get_data()) {

    if ($component == 'plan') {
        $class = 'development_plan';
        require_once("{$CFG->dirroot}/totara/plan/settings_form.php");
    } else {
        // Include each components form file
        // Component path
        $cpath = "{$CFG->dirroot}/totara/plan/components/{$component}";
        $formfile  = "{$cpath}/settings_form.php";

        if (!is_readable($formfile)) {
            $string_properties = new stdClass();
            $string_properties->classfile = $classfile;
            $string_properties->component = $component;
            throw new PlanException(get_string('noclassfileforcomponent', 'totara_plan', $string_properties));
        }
        require_once($formfile);

        // Check class exists
        $class = "dp_{$component}_component";
        if (!class_exists($class)) {
            $string_properties = new stdClass();
            $string_properties->class = $class;
            $string_properties->component = $component;
            throw new PlanException(get_string('noclassforcomponent', 'totara_plan', $string_properties));
        }
    }
    if ($templateinuse) {
        unset($fromform->priorityscale);
    }

    $process_form = "{$class}_process_settings_form";
    $process_form($fromform, $id);
    redirect(new moodle_url('/totara/plan/template/advancedworkflow.php', array('id' => $id, 'component' => $component)));
}

$PAGE->navbar->add(format_string($template->fullname));

echo $OUTPUT->header();

if ($template) {
    echo $OUTPUT->heading($template->fullname);
} else {
    echo $OUTPUT->heading(get_string('newtemplate', 'totara_plan'));
}

$currenttab = 'workflowplan';
require('tabs.php');

echo $OUTPUT->single_button(new moodle_url('/totara/plan/template/workflow.php', array('id' => $id)), get_string('simpleworkflow', 'totara_plan'), 'get');

$mform->display();

echo $OUTPUT->footer();
