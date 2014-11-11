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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once "$CFG->dirroot/lib/formslib.php";
require_once("$CFG->libdir/tablelib.php");

class dp_template_general_settings_form extends moodleform {

    function definition() {
        global $DB;
        $mform =& $this->_form;

        $id = $this->_customdata['id'];
        $template = $DB->get_record('dp_template', array('id' => $id));
        $templatename = $template->fullname;

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('header', 'generalsettings', get_string('generalsettings', 'totara_plan'));

        $mform->addElement('text', 'templatename', get_string('name', 'totara_plan'), 'maxlength="255"');
        $mform->setType('templatename', PARAM_TEXT);
        $mform->setDefault('templatename', $templatename);
        $mform->addRule('templatename', null, 'required');

        $mform->addElement('date_selector', 'enddate', get_string('enddate', 'totara_plan'));
        $mform->setType('enddate', PARAM_TEXT);
        $mform->setDefault('enddate', $template->enddate);
        $mform->addRule('enddate', null, 'required');
        $mform->addHelpButton('enddate', 'templateenddate', 'totara_plan', '', true);

        $this->add_action_buttons();
    }
}


class dp_template_new_form extends moodleform {
    function definition() {
        $mform =& $this->_form;

        $mform->addElement('header', 'newtemplate', get_string('newtemplate', 'totara_plan'));

        $mform->addElement('text', 'templatename', get_string('name', 'totara_plan'), 'maxlength="255"');
        $mform->setType('templatename', PARAM_TEXT);
        $mform->addRule('templatename', null, 'required');

        $mform->addElement('date_selector', 'enddate', get_string('enddate', 'totara_plan'));
        $mform->setType('enddate', PARAM_TEXT);
        $mform->addRule('enddate', null, 'required');
        $mform->setDefault('enddate', 0);
        $mform->addHelpButton('enddate', 'templateenddate', 'totara_plan', '', true);

        $this->add_action_buttons();
    }
}



class dp_template_workflow_form extends moodleform {
    function definition() {
        global $CFG, $DP_AVAILABLE_WORKFLOWS;
        $mform =& $this->_form;
        $id = $this->_customdata['id'];
        $defaultworkflow = $this->_customdata['workflow'];

        $mform->addElement('header', 'workflowsettings', get_string('workflowsettings', 'totara_plan'));

        $radiogroup = array();

        foreach ($DP_AVAILABLE_WORKFLOWS as $workflow) {
            $classfile = $CFG->dirroot . "/totara/plan/workflows/$workflow/$workflow.class.php";
            if (!is_readable($classfile)) {
                $string_parameters = new stdClass();
                $string_parameters->classfile = $classfile;
                $string_parameters->workflow = $workflow;
                throw new PlanException(get_string('noclassfileforworkflow', 'totara_plan', $string_parameters));
            }
            include_once($classfile);

            $classname = "dp_{$workflow}_workflow";
            if (!class_exists($classname)) {
                $string_parameters = new stdClass();
                $string_parameters->class = $classfile;
                $string_parameters->workflow = $workflow;
                throw new PlanException(get_string('noclassforworkflow', 'totara_plan', $string_parameters));
            }
            $wf = new $classname();
            $radiogroup[] =& $mform->createElement('radio', 'workflow', '', html_writer::tag('b', $wf->name) . html_writer::empty_tag('br') . $wf->description, $wf->classname);

        }

        $radiogroup[] =& $mform->createElement('radio', 'workflow', '', html_writer::tag('b', get_string('customworkflowname', 'totara_plan')) . html_writer::empty_tag('br') . get_string('customworkflowdesc', 'totara_plan'), 'custom');
        $mform->addGroup($radiogroup, 'radiogroup', '', html_writer::empty_tag('br') . html_writer::empty_tag('br'), false);
        $mform->setDefault('workflow', $defaultworkflow);

        $mform->registerNoSubmitButton('advancedsubmitbutton');
        $mform->addElement('submit', 'advancedsubmitbutton', get_string('advancedworkflow', 'totara_plan'));
        $mform->disabledIf('advancedsubmitbutton', 'workflow', 'neq', 'custom');

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }
}

class dp_template_advanced_workflow_form extends moodleform {
    function definition() {
        global $CFG;
        $mform =& $this->_form;
        $id = $this->_customdata['id'];
        $component = $this->_customdata['component'];
        $templateinuse = $this->_customdata['templateinuse'];
        if ($component == 'plan') {
            $class = 'development_plan';
            require_once("{$CFG->dirroot}/totara/plan/settings_form.php");
        } else {
            // Include each components form file
            // Component path
            $cpath = "{$CFG->dirroot}/totara/plan/components/{$component}";
            $formfile  = "{$cpath}/settings_form.php";

            if (!is_readable($formfile)) {
                $string_parameters = new stdClass();
                $string_parameters->classfile = $classfile;
                $string_parameters->component = $component;
                throw new PlanException(get_string('noclassfileforcomponent', 'totara_plan', $string_parameters));
            }
            include_once($formfile);
            // check class exists
            $class = "dp_{$component}_component";
            if (!class_exists($class)) {
                $string_parameters = new stdClass();
                $string_parameters->class = $class;
                $string_parameters->component = $component;
                throw new PlanException(get_string('noclassforcomponent', 'totara_plan', $string_parameters));
            }
        }
        $build_form = "{$class}_build_settings_form";
        $build_form($mform, $this->_customdata);

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'component', $component);
        $mform->setType('component', PARAM_TEXT);
        $this->add_action_buttons();
    }
}
