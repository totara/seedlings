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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once("{$CFG->libdir}/formslib.php");

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

class plan_edit_form extends moodleform {

    function definition() {
        global $CFG, $USER, $DB, $TEXTAREA_OPTIONS;

        $mform =& $this->_form;
        $action = $this->_customdata['action'];
        if (isset($this->_customdata['plan'])) {
            $plan = $this->_customdata['plan'];
        }

        if ($action != 'add') {
            // Add some hidden fields
            $mform->addElement('hidden', 'id');
            $mform->setType('id', PARAM_INT);
        } else {
            // Get userid that we need for template permissions check on add
            $role = $this->_customdata['role'];

            // Get plan templates
            $templates = dp_get_templates();
        }

        $canselectplan = has_capability('totara/plan:canselectplantemplate', context_system::instance());

        if ($action == 'add') {
            if ($canselectplan) {
                $template_options = array();
                $template_default = 0;
                $default_template_id = 0;

                $allowed_templates = dp_template_has_permission('plan', 'create', $role, DP_PERMISSION_ALLOW);

                foreach ($templates as $t) {
                    if (in_array($t->id, $allowed_templates)) {
                        $template_options[$t->id] = $t->fullname;
                        if ($t->isdefault == 1) {
                            $default_template_id = $t->id;
                        }
                    }
                }

                if (count($allowed_templates) == 1) {
                    $template_id = array_shift($allowed_templates);
                    $template = $DB->get_record('dp_template', array('id' => $template_id));
                } else {
                    $template = $DB->get_record('dp_template', array('id' => $default_template_id));
                }
            } else {
                $template = $DB->get_record('dp_template', array('isdefault' => 1));
            }
        }

        $mform->addElement('hidden', 'userid', $USER->id);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'status', 0);
        $mform->setType('status', PARAM_INT);
        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_TEXT);

        if ($action == 'delete') {
            // Only show delete confirmation
            $mform->addElement('html', get_string('checkplandelete', 'totara_plan', $plan->name));
            $buttonarray = array();
            $buttonarray[] = $mform->createElement('submit', 'deleteyes', get_string('yes'));
            $buttonarray[] = $mform->createElement('submit', 'deleteno', get_string('no'));
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');

            return;
        }
        if ($action == 'complete') {
            // Only show complete plan confirmation
            $mform->addElement('html', get_string('checkplancomplete11', 'totara_plan', $plan->name));
            $buttonarray = array();
            $buttonarray[] = $mform->createElement('submit', 'completeyes', get_string('completeplan', 'totara_plan'));
            $buttonarray[] = $mform->createElement('submit', 'completeno', get_string('no'));
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');

            return;
        }

        if ($action == 'add') {
            if ($canselectplan) {
                $mform->addElement('select', 'templateid', get_string('plantemplate', 'totara_plan'), $template_options);
                $mform->setDefault('templateid', $default_template_id);
            } else {
                // Set default template if user doesn't have permissions to choose
                $mform->addElement('hidden', 'templateid', $template->id);
            }
        }

        $mform->addElement('text', 'name', get_string('planname', 'totara_plan'), array('size' => 50));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('err_required', 'form'), 'required', '', 'client', false, false);
        if ($action == 'add' && isset($template->fullname)) {
            $mform->setDefault('name', $template->fullname);
        }

        if ($action == 'view') {
            $plan->description = file_rewrite_pluginfile_urls($plan->description, 'pluginfile.php', context_system::instance()->id, 'totara_plan', 'dp_plan', $plan->id);
            $mform->addElement('static', 'description', get_string('plandescription', 'totara_plan'), format_text($plan->description, FORMAT_HTML));
        } else {
            $mform->addElement('editor', 'description_editor', get_string('plandescription', 'totara_plan'), null, $TEXTAREA_OPTIONS);
            $mform->setType('description_editor', PARAM_CLEANHTML);
        }

        $mform->addElement('text', 'startdate', get_string('datestarted', 'totara_plan'), array('placeholder' => get_string('datepickerlongyearplaceholder', 'totara_core')));
        $mform->setType('startdate', PARAM_TEXT);
        $mform->addRule('startdate', get_string('err_required', 'form'), 'required', '', 'client', false, false);
        if ($action == 'add') {
            $mform->setDefault('startdate', userdate(time(), get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false));
        }

        $mform->addElement('text', 'enddate', get_string('completiondate', 'totara_plan'), array('placeholder' => get_string('datepickerlongyearplaceholder', 'totara_core')));
        $mform->setType('enddate', PARAM_TEXT);
        $mform->addRule('enddate', get_string('err_required', 'form'), 'required', '', 'client', false, false);
        if ($action == 'add' && isset($template->enddate)) {
            $mform->setDefault('enddate', userdate($template->enddate, get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false));
        }

        if ($action == 'view') {
            $mform->hardFreeze(array('name', 'startdate', 'enddate'));
            $buttonarray = array();
            if ($plan->get_setting('update') == DP_PERMISSION_ALLOW && $plan->status != DP_PLAN_STATUS_COMPLETE) {
                $buttonarray[] = $mform->createElement('submit', 'edit', get_string('editdetails', 'totara_plan'));
            }
            if ($plan->get_setting('delete') == DP_PERMISSION_ALLOW) {
                $buttonarray[] = $mform->createElement('submit', 'delete', get_string('deleteplan', 'totara_plan'));
            }
            if ($plan->get_setting('completereactivate') >= DP_PERMISSION_ALLOW && $plan->status == DP_PLAN_STATUS_APPROVED) {
                $buttonarray[] = $mform->createElement('submit', 'complete', get_string('plancomplete', 'totara_plan'));
            }

            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');
        } else {
            switch ($action) {
            case 'add':
                $actionstr = 'createplan';
                break;
            case 'edit':
                $actionstr = 'updateplan';
                break;
            default:
                $actionstr = null;
            }
            $this->add_action_buttons(true, get_string($actionstr, 'totara_plan'));
        }
    }

    /**
     * Carries out validation of submitted form values
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    function validation($data, $files) {
        $mform =& $this->_form;
        $result = array();

        $action = $this->_customdata['action'];
        if (in_array($action, array('add', 'edit'))) {
            // Validate edit form.
            $startdate = isset($data['startdate']) ? $data['startdate'] : '';
            $enddate = isset($data['enddate']) ? $data['enddate'] : '';

            $datepattern = get_string('datepickerlongyearregexphp', 'totara_core');
            if (preg_match($datepattern, $enddate, $matches) == 0) {
                $errstr = get_string('error:dateformat','totara_plan', get_string('datepickerlongyearplaceholder', 'totara_core'));
                $result['enddate'] = $errstr;
                unset($errstr);
            } else if (preg_match($datepattern, $startdate, $matches) == 0) {
                $errstr = get_string('error:dateformat','totara_plan', get_string('datepickerlongyearplaceholder', 'totara_core'));
                $result['startdate'] = $errstr;
                unset($errstr);
            } else if (totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'), $startdate) > totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'), $enddate)) {
                // Enforce start date before finish date.
                $errstr = get_string('error:creationaftercompletion', 'totara_plan');
                $result['enddate'] = $errstr;
                unset($errstr);
            }
        }

        return $result;
    }

}
