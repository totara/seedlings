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
 * @package totara
 * @subpackage cohort
 */
/**
 * This file defines the form for editing the list of rules for a dynamic cohort.
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

require_once($CFG->dirroot . '/lib/formslib.php');

class cohort_rules_form extends moodleform {
    function definition() {
        global $CFG;
        $mform =& $this->_form;
        $strdelete = get_string('delete');
        $cohort = $this->_customdata['cohort'];
        $rulesets = $this->_customdata['rulesets'];

        $mform->addElement('hidden', 'id', $cohort->id);
        $mform->setType('id', PARAM_INT);

        // The menu for the operator between rulesets.
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'cohortoperator', '', get_string('cohortoperatorandlabel', 'totara_cohort'), COHORT_RULES_OP_AND);
        $radiogroup[] =& $mform->createElement('radio', 'cohortoperator', '', get_string('cohortoperatororlabel', 'totara_cohort'), COHORT_RULES_OP_OR);
        $mform->addGroup($radiogroup, 'cohortoperator', get_string('cohortoperatorlabel', 'totara_cohort'), '<br />', false);
        $mform->addHelpButton('cohortoperator', 'cohortoperatorlabel', 'totara_cohort');
        $mform->setDefault('cohortoperator', COHORT_RULES_OP_AND);
        $mform->setType('cohortoperator', PARAM_INT);

        $firstruleset = true;

        foreach ($rulesets as $ruleset) {
            $id = $ruleset->id;

            if ($firstruleset) {
                $firstruleset = false;
            } else {
                $opstr = '<div class="cohort-oplabel" id="oplabel'.$id.'">';
                switch ($cohort->rulesetoperator) {
                    case COHORT_RULES_OP_AND:
                        $opstr .= get_string('andcohort', 'totara_cohort');
                        break;
                    case COHORT_RULES_OP_OR:
                        $opstr .= get_string('orcohort', 'totara_cohort');
                        break;
                    default:
                        $opstr .= $cohort->rulesetoperator;
                }
                $opstr .= '</div>';
                $mform->addElement('static', "operator{$id}", $opstr, '');
                $mform->closeHeaderBefore("operator{$id}");
            }

            $mform->addElement('header', "cohort-ruleset-header{$id}", $ruleset->name);

            // The menu for the operator in this ruleset.
            $radiogroup = array();
            $radioname = "rulesetoperator[{$id}]";
            $radiogroup[] =& $mform->createElement('radio', $radioname, '', get_string('cohortoperatorandlabel', 'totara_cohort'), COHORT_RULES_OP_AND);
            $radiogroup[] =& $mform->createElement('radio', $radioname, '', get_string('cohortoperatororlabel', 'totara_cohort'), COHORT_RULES_OP_OR);
            $mform->addGroup($radiogroup, $radioname, get_string('rulesetoperatorlabel', 'totara_cohort'), '<br />', false);
//            $mform->setDefault($radioname, COHORT_RULES_OP_AND);
            $mform->setType($radioname, PARAM_INT);

            $mform->addElement('html', '<div class="rulelist fitem"><table class="rule_table">');
            $firstrule = true;
            foreach ($ruleset->rules as $rulerec) {
                $rule = cohort_rules_get_rule_definition($rulerec->ruletype, $rulerec->name);
                if ($rule) {
                    $rule->sqlhandler->fetch($rulerec->id);
                    $rule->ui->setParamValues($rule->sqlhandler->paramvalues);
                    $mform->addElement('html', cohort_rule_form_html($rulerec->id,
                        $rule->ui->getRuleDescription($rulerec->id, false), $rulerec->ruletype, $rulerec->name, $firstrule,
                        $ruleset->operator, false));
                } else { // Broken rule.
                    $a = new stdClass();
                    $a->type = $rulerec->ruletype;
                    $a->name = $rulerec->name;
                    $content = get_string('cohortbrokenrule', 'totara_cohort', $a);
                    $description = html_writer::tag('b', $content, array('class' => 'error'));
                    $mform->addElement('html', cohort_rule_form_html($rulerec->id, $description, $rulerec->ruletype, $rulerec->name,
                        $firstrule, $ruleset->operator, true));
                }
                $firstrule = false;
            }
            $mform->addElement('html', '</table></div>');

            // todo: what should the label for this select be?
            $mform->addElement(
                'selectgroups',
                "addrulemenu{$id}",
                '',
                cohort_rules_get_menu_options(),
                array(
                    'class' => 'rule_selector new_rule_selector ignoredirty',
                    'data-idtype' => 'ruleset',
                    'data-id' => $ruleset->id,
                )
            );
        }

        // The menu to add a new ruleset
        $mform->addElement('header', 'addruleset', get_string('addruleset', 'totara_cohort'));
        $mform->addElement(
            'selectgroups',
            'addrulesetmenu',
            '',
            cohort_rules_get_menu_options(),
            array(
                'class' => 'rule_selector new_rule_selector ignoredirty',
                'data-idtype' => 'cohort',
                'data-id' => $cohort->id,
            )
        );
        $mform->setDefault('addrulesetmenu', 'default');

        // todo: Need to ajaxify the and/or radios so that we can get rid of these buttons altogether
        $this->add_action_buttons(true, get_string('updateoperatorsbutton', 'totara_cohort'));
    }
}

/**
 * Formslib template for the global settings form
 */
class cohort_global_settings_form extends moodleform {
    function definition() {
        global $COHORT_ALERT;
        $mform =& $this->_form;

        $mform->addElement('header', 'settings', get_string('globalsettings', 'totara_cohort'));

        $alertoptions = get_config('cohort', 'alertoptions');
        $alertoptions = $alertoptions !== '' ? explode(',', $alertoptions) : array();

        $group = array();
        foreach ($COHORT_ALERT as $code => $option) {
            $group[] =& $mform->createElement('checkbox', 'alert' . $code, '', $option);
            if (in_array($code, $alertoptions)) {
                $mform->setDefault('alert' . $code, 1);
            } else {
                $mform->setDefault('alert' . $code, 0);
            }
        }
        $mform->addGroup($group, 'alertoptions', get_string('cohortalertoptions', 'totara_cohort'),
            html_writer::empty_tag('br'), false);
        $mform->addHelpButton('alertoptions', 'cohortalertoptions', 'totara_cohort');

        $this->add_action_buttons();
    }

}


/**
 * Formslib template for cohort learning plan settings from
 */
class cohort_learning_plan_settings_form extends moodleform {
    function definition() {
        $mform =& $this->_form;

        $cohort = $this->_customdata['data'];

        $mform->addElement('hidden', 'cohortid', $cohort->id);
        $mform->setType('cohortid', PARAM_INT);

        $templates = dp_get_templates();

        $default_template = dp_get_default_template();

        $template_options = array();
        foreach ($templates as $template) {
            $template_options[$template->id] = format_string($template->fullname);
        }

        $mform->addElement('select', 'plantemplate', get_string('plantemplate', 'totara_plan'), $template_options);
        $mform->setDefault('plantemplate', $default_template->id);

        $exludegroup = array();
        $excludegroup[] =& $mform->createElement('advcheckbox', 'manualplan', '', get_string('createforexistingmanualplan', 'totara_cohort'));
        $excludegroup[] =& $mform->createElement('advcheckbox', 'autoplan', '', get_string('createforexistingautoplan', 'totara_cohort'));
        $excludegroup[] =& $mform->createElement('advcheckbox', 'completeplan', '', get_string('createforexistingcompleteplan', 'totara_cohort'));

        // Set all checkboxs to be checked by default
        $mform->setDefault('manualplan', 1);
        $mform->setDefault('autoplan', 1);
        $mform->setDefault('completeplan', 1);

        $mform->addGroup($excludegroup, 'exclude', get_string('excludeuserswho', 'totara_cohort'), html_writer::empty_tag('br'), false);
        $mform->addHelpButton('exclude', 'excludeuserswho', 'totara_cohort');

        $plan_statuses = array (
            DP_PLAN_STATUS_UNAPPROVED => get_string('unapproved', 'totara_plan'),
            DP_PLAN_STATUS_APPROVED => get_string('approved', 'totara_plan')
        );
        $mform->addElement('select', 'planstatus', get_string('createplanstatus', 'totara_cohort'), $plan_statuses);

        $this->add_action_buttons(false, get_string('createplans', 'totara_cohort'));
    }
}
