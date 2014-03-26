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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_appraisal
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->dirroot . '/lib/formslib.php');
require_once('lib.php');

/**
 * Formslib template for the edit appraisal form
 */
class appraisal_edit_form extends moodleform {

    public function definition() {
        global $TEXTAREA_OPTIONS;

        $mform = & $this->_form;
        $appraisal = $this->_customdata['appraisal'];
        $readonly = $this->_customdata['readonly'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        if ($readonly) {
            $mform->addElement('static', 'name', get_string('name', 'totara_appraisal'));
            $mform->addElement('static', null, get_string('description'), $appraisal->description_editor['text']);
        } else {
            $mform->addElement('text', 'name', get_string('name', 'totara_appraisal'), 'maxlength="255" size="50"');
            $mform->addRule('name', null, 'required');
            $mform->addHelpButton('name', 'name', 'totara_appraisal');

            $mform->addElement('editor', 'description_editor', get_string('description'), null, $TEXTAREA_OPTIONS);
            $mform->addHelpButton('description_editor', 'description', 'totara_appraisal');

            $submittitle = get_string('createappraisal', 'totara_appraisal');
            if ($appraisal->id > 0) {
                $submittitle = get_string('savechanges', 'moodle');
            }
            $this->add_action_buttons(true, $submittitle);
        }
        $mform->setType('name', PARAM_TEXT);
        $mform->setType('description_editor', PARAM_CLEANHTML);

        $this->set_data($appraisal);
    }

}

/**
 * Formslib template for the close appraisal form
 */
class appraisal_close_form extends moodleform {

    public function definition() {
        global $TEXTAREA_OPTIONS;

        $mform = & $this->_form;
        $appraisal = $this->_customdata['appraisal'];
        $appraisal->sendalert = true;
        $appraisal->alerttitle = get_string('closealerttitledefault', 'totara_appraisal', $appraisal);

        $mform->addElement('hidden', 'id', $appraisal->id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action', 'close');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('checkbox', 'sendalert', get_string('closesendalert', 'totara_appraisal'));
        $mform->setType('sendalert', PARAM_BOOL);

        $mform->addElement('text', 'alerttitle', get_string('closealerttitle', 'totara_appraisal'),
                'maxlength="255" size="50"');
        $mform->setType('alerttitle', PARAM_TEXT);

        $mform->addElement('editor', 'alertbody_editor', get_string('closealertbody', 'totara_appraisal'), null,
                $TEXTAREA_OPTIONS);
        $mform->setType('alertbody_editor', PARAM_CLEANHTML);

        $this->add_action_buttons(true, get_string('closeappraisal', 'totara_appraisal'));

        $this->set_data($appraisal);
    }

}

/**
 * View form page
 */
class appraisal_answer_form extends moodleform {

    public function definition() {
        /* We do the work in the definition_after_data function because the definition function is called every
         * time a form is constructed, and we don't want to add elements to the form until we are sure that
         * this is the form that will be displayed, and is not just being used to retrieve submitted data.
         */
    }

    public function definition_after_data() {
        $mform = & $this->_form;

        $appraisal = $this->_customdata['appraisal'];
        $page = $this->_customdata['page'];
        $roleassignment = $this->_customdata['roleassignment'];
        $action = $this->_customdata['action'];
        $preview = $this->_customdata['preview'];
        $islastpage = $this->_customdata['islastpage'];
        $otherassignments = $this->_customdata['otherassignments'];
        $readonly = isset($this->_customdata['readonly']) ? $this->_customdata['readonly'] : false;
        $spaces = isset($this->_customdata['spaces']) ? $this->_customdata['spaces'] : false;
        $nouserpic = isset($this->_customdata['nouserpic']) ? $this->_customdata['nouserpic'] : false;

        $stage = new appraisal_stage($page->appraisalstageid);
        $stageiscomplete = $stage->is_completed($roleassignment);
        $pageislocked = $readonly || $page->is_locked($roleassignment, $preview);
        $showformfields = !$pageislocked && $stage->can_be_answered($roleassignment->appraisalrole);
        $showsubmitbutton = $showformfields && !$preview;
        $isactivepage = ($roleassignment->activepageid == $page->id) && !$stageiscomplete;

        $mform->addElement('hidden', 'pageid')->setValue($page->id);
        $mform->addElement('hidden', 'role')->setValue($roleassignment->appraisalrole);
        $mform->addElement('hidden', 'subjectid')->setValue($roleassignment->subjectid);
        $mform->addElement('hidden', 'appraisalid')->setValue($appraisal->id);
        $mform->addElement('hidden', 'stageid')->setValue($page->appraisalstageid);
        $mform->addElement('hidden', 'action')->setValue($action);
        $mform->addElement('hidden', 'preview')->setValue($preview);

        $questions = appraisal_question::fetch_page_role($page->id, $roleassignment);

        // Set required property.
        $rolecodestrings = appraisal::get_roles();
        foreach ($questions as $question) {
            $isviewonlyquestion = true;
            $elem = $question->get_element();
            $rights = $question->roles[$roleassignment->appraisalrole];

            if (($rights & appraisal::ACCESS_CANANSWER) == appraisal::ACCESS_CANANSWER) {
                $isviewonlyquestion = false;
                if (!$showformfields || $question->is_locked($roleassignment)) {
                    $elem->set_viewonly(true);
                } else if (($rights & appraisal::ACCESS_MUSTANSWER) == appraisal::ACCESS_MUSTANSWER) {
                    $elem->set_required(true);
                }

                $viewerroles = $question->get_roles_involved(appraisal::ACCESS_CANVIEWOTHER);
                $elem->label = get_string('role_answer_you', 'totara_appraisal');

                if (!empty($viewerroles)) {
                    $elem->viewers = array();
                    foreach ($viewerroles as $viewerrole) {
                        if ($viewerrole != $roleassignment->appraisalrole) {
                            $elem->viewers[] = get_string($rolecodestrings[$viewerrole], 'totara_appraisal');
                        }
                    }
                }
            }

            if (($rights & appraisal::ACCESS_CANVIEWOTHER) == appraisal::ACCESS_CANVIEWOTHER) {
                if (!$question->populate_roles_element($roleassignment, $otherassignments, $nouserpic)) {
                    $isviewonlyquestion = false;
                }
            }

            if ((($rights & appraisal::ACCESS_CANANSWER) != appraisal::ACCESS_CANANSWER) && !$isviewonlyquestion) {
                $elem->cananswer = false;
            }

            if ($isviewonlyquestion) {
                $elem->set_viewonly(true);
            } else if ($spaces) {
                $spaceelem = $mform->addElement('static', '', ' ', ' ');
                $spaceelem->_type = 'whitespace';
                $spaceelem->_elementTemplateType = 'default';
            }

            $elem->set_preview($preview);

            $elem->add_field_form_elements($mform);
        }

        $button = null;
        if ($showsubmitbutton) {
            if ($isactivepage) {
                if ($islastpage) {
                    $mform->addElement('hidden', 'submitaction')->setValue('completestage');
                    $button = 'completestage';
                } else {
                    $mform->addElement('hidden', 'submitaction')->setValue('next');
                    $button = 'next';
                }
            } else if ($page->can_be_answered($roleassignment->appraisalrole)) {
                $mform->addElement('hidden', 'submitaction')->setValue('savechanges');
                $button = 'savechanges';
            }
        }
        if ($button) {
            $this->add_action_buttons(false, get_string($button, 'totara_appraisal'));
        }
    }

    public function reset_form_sent() {
        $page = $this->_customdata['page'];
        $roleassignment = $this->_customdata['roleassignment'];

        $questions = appraisal_question::fetch_page_role($page->id, $roleassignment);

        foreach ($questions as $question) {
            $elem = $question->get_element();
            $elem->reset_form_sent();
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $roleassignment = $this->_customdata['roleassignment'];

        $questions = appraisal_question::fetch_page_role($data['pageid'], $roleassignment);
        foreach ($questions as $question) {
            if (!$question->is_locked($roleassignment)) {
                $newerrors = $question->get_element()->edit_validate($data);
                if (!empty($newerrors)) {
                    $errors = array_merge($errors, $newerrors);
                }
            }
        }

        return $errors;
    }

}

/**
 * Formslib template for the edit appraisal stage form
 */
class appraisal_stage_edit_form extends moodleform {

    public function definition() {
        global $TEXTAREA_OPTIONS;

        $mform = & $this->_form;
        $stage = $this->_customdata['stage'];
        $action = $this->_customdata['action'];
        $readonly = $this->_customdata['readonly'];

        if ($readonly) {
            $mform->addElement('header', 'stageheader', get_string('viewstageheading', 'totara_appraisal'));
        } else if ($stage->id > 0) {
            $mform->addElement('header', 'stageheader', get_string('editstageheading', 'totara_appraisal'));
        } else {
            $mform->addElement('header', 'stageheader', get_string('addstage', 'totara_appraisal'));
        }
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'appraisalid');
        $mform->setType('appraisalid', PARAM_INT);
        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_ACTION);

        if ($readonly) {
            $mform->addElement('static', 'name', get_string('name', 'totara_appraisal'), 'maxlength="255" size="50"');
        } else {
            $mform->addElement('text', 'name', get_string('name', 'totara_appraisal'), 'maxlength="255" size="50"');
        }
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');
        $mform->addHelpButton('name', 'namestage', 'totara_appraisal');

        $mform->addElement('editor', 'description_editor', get_string('description'), null, $TEXTAREA_OPTIONS);
        $mform->setType('description_editor', PARAM_CLEANHTML);
        $mform->addHelpButton('description_editor', 'descriptionstage', 'totara_appraisal');
        $submittitle = ($stage->id > 0) ? get_string('savechanges', 'moodle') : get_string('addstage', 'totara_appraisal');

        if ($readonly) {
            $mform->addElement('static', 'timedue', get_string('completeby', 'totara_appraisal'));
        } else {
            $mform->addElement('text', 'timedue', get_string('completeby', 'totara_appraisal'),
                    array('placeholder' => get_string('datepickerlongyearplaceholder', 'totara_core')));
            $mform->addElement('static', 'timedue_hint', '', get_string('completebystage_help', 'totara_appraisal'));
        }
        $mform->setType('timedue', PARAM_TEXT);

        // Rights matrix for roles.
        $mform->addElement('header', 'lockstageheader', get_string('locks', 'totara_appraisal'));
        $roles = appraisal::get_roles();
        $rolegroup = array();
        foreach ($roles as $role => $rolename) {
            $rolegroup[] = & $mform->createElement('advcheckbox', $role, null, get_string($rolename, 'totara_appraisal'));
        }
        $mform->addElement('group', 'locks', get_string('locks', 'totara_appraisal'), $rolegroup, array('<br/> '));
        $mform->addElement('static', 'locks_hint', '', get_string('locks_help', 'totara_appraisal'));

        // Initial pages, only when it is a new stage.
        if (!$stage->id > 0) {
            $mform->addElement('header', 'initialpagesheader', get_string('stageinitialpagesheader', 'totara_appraisal'));
            $mform->addElement('textarea', 'stageinitialpagetitles', get_string('stageinitialpagetitles', 'totara_appraisal'),
                    array('cols' => '90', 'rows' => '5'));
            $mform->addHelpButton('stageinitialpagetitles', 'stageinitialpagetitles', 'totara_appraisal');
        }

        if ($readonly) {
            $backurl = new moodle_url('/totara/appraisal/stage.php', array('appraisalid'=>$stage->appraisalid));
            $backurl->set_anchor('id='.$stage->id);
            $mform->addElement('static', 'backlink', '', html_writer::link($backurl, get_string('back')));
            $mform->freeze();
        } else {
            $this->add_action_buttons(true, $submittitle);
        }
        $this->set_data($stage);
    }

    /**
     * Form data validation
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $result = array();
        $timeduestr = isset($data['timedue']) ? $data['timedue'] : '';
        $timedue = totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'), $timeduestr);

        $isplaceholder = $timeduestr == get_string('datepickerlongyearplaceholder', 'totara_core');
        if (0 == $timedue && !$isplaceholder && $timeduestr !== '') {
            $result['timedue'] = get_string('error:dateformat', 'totara_appraisal',
                    get_string('datepickerlongyearplaceholder', 'totara_core'));
        }
        return $result;
    }

}

/**
 * Formslib template for the edit appraisal stage form
 */
class appraisal_stage_page_edit_form extends moodleform {

    public function definition() {
        $mform = & $this->_form;
        $page = $this->_customdata['page'];

        if ($page->id > 0) {
            $mform->addElement('header', 'stageheader', get_string('editpageheading', 'totara_appraisal'));
        } else {
            $mform->addElement('header', 'stageheader', get_string('createpageheading', 'totara_appraisal'));
        }
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'appraisalstageid');
        $mform->setType('appraisalstageid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name', 'totara_appraisal'), 'maxlength="255" size="50"');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');
        $mform->addHelpButton('name', 'namestage', 'totara_appraisal');

        $submittitle = get_string('addpage', 'totara_appraisal');
        if ($page->id > 0) {
            $submittitle = get_string('savechanges', 'moodle');
        }
        $this->add_action_buttons(true, $submittitle);
        $this->set_data($page);
    }

}

/**
 * Formslib template for the edit appraisal choose element
 */
class appraisal_add_quest_form extends question_choose_element_form {

    public function definition() {
        $this->prefix = 'appraisal';
        $mform = & $this->_form;
        $mform->disable_form_change_checker();
        $pageid = $this->_customdata['pageid'];
        $prev_permissions = $this->_customdata['prev_perms'];
        $mform->addElement('static', 'prev_quest_roles', null,
            '<script type="text/javascript">var prevRoles = [' . $prev_permissions . '];</script>');
        $mform->addElement('hidden', 'appraisalstagepageid');
        $mform->setType('appraisalstagepageid', PARAM_INT);
        $mform->getElement('appraisalstagepageid')->setValue($pageid);
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ACTION);
        $mform->getElement('action')->setValue('edit');
        parent::definition();
    }

}

/**
 * Manage form elements definition
 *
 */
class appraisal_quest_edit_form extends question_base_form {

    public function definition() {
        global $OUTPUT;

        $mform = & $this->_form;
        $mform->disable_form_change_checker();
        $id = $this->_customdata['id'];
        $pageid = $this->_customdata['page']->id;
        $stagename = format_string($this->_customdata['stage']->name);
        $pagename = format_string($this->_customdata['page']->name);
        $question = $this->_customdata['question'];
        $notfirst = $this->_customdata['notfirst'];
        $readonly = $this->_customdata['readonly'];

        $element = $question->get_element();
        $mform->addElement('header', 'questionheader', get_string('questionmanage', 'totara_question'));
        $mform->addElement('static', 'stagetitle', get_string('stagename', 'totara_appraisal', $stagename));
        $mform->addElement('static', 'pagetitle', get_string('pagename', 'totara_appraisal', $pagename));

        $mform->addElement('hidden', 'appraisalstagepageid');
        $mform->setType('appraisalstagepageid', PARAM_INT);
        $mform->getElement('appraisalstagepageid')->setValue($pageid);
        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ACTION);
        $mform->getElement('action')->setValue('edit');
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->getElement('id')->setValue($id);
        $mform->addElement('hidden', 'datatype');
        $mform->setType('datatype', PARAM_ACTION);
        $mform->getElement('datatype')->setValue($element->get_type());

        $info = new stdClass();
        $info->pageid = $pageid;
        $element->add_settings_form_elements($mform, $readonly, $info);
        if ($element->requires_permissions()) {
            $requiredstr = html_writer::empty_tag('img', array('title' => get_string('requiredelement', 'form'),
                    'src' => $OUTPUT->pix_url('req'), 'alt' => get_string('requiredelement', 'form'), 'class'=>'req'));
            $mform->addElement('header', 'perms', get_string('permissions', 'totara_appraisal') . $requiredstr);
            $mform->setExpanded('perms');
            if ($element->is_answerable()) {
                if ($id < 1 && $notfirst) {
                    $this->add_clone_roles();
                }
                $this->add_role_matrix();
            } else {
                $this->add_role_viewers();
            }
        }
        if ($readonly) {
            $mform->freeze();
        } else {
            $this->add_action_buttons();
        }
    }

    /**
     * Add role access matrix to question field definition form
     */
    protected function add_role_matrix() {
        $mform = & $this->_form;
        $headcolumns = html_writer::tag('th', get_string('role', 'totara_appraisal'), array('class' => 'header')) .
            html_writer::tag('th', get_string('answer', 'totara_appraisal'), array('class' => 'header')) .
            html_writer::tag('th', get_string('required', 'totara_appraisal'), array('class' => 'header')) .
            html_writer::tag('th', get_string('viewother', 'totara_appraisal'), array('class' => 'header'));
        $header = html_writer::tag('thead', html_writer::tag('tr', $headcolumns));
        $mform->addElement('html', html_writer::start_tag('table', array('class' => 'role_matrix')) . $header .
            html_writer::start_tag('tbody'));

        $permission_cananswer = appraisal::ACCESS_CANANSWER;
        $permission_required = appraisal::ACCESS_MUSTANSWER;
        $permission_viewother = appraisal::ACCESS_CANVIEWOTHER;

        $roles = appraisal::get_roles();
        $odd = false;
        foreach ($roles as $roleid => $name) {
            $odd = !$odd;
            $rowclass = ($odd) ? 'r0' : 'r1';
            $strrolename = html_writer::start_tag('tr', array('class' => $rowclass)) .
                html_writer::tag('td', get_string($name, 'totara_appraisal')) .
                html_writer::start_tag('td', array('class' => 'cell'));
            $strnextcell = html_writer::end_tag('td') . html_writer::start_tag('td', array('class' => 'cell'));
            $strclosingrow = html_writer::end_tag('td') . html_writer::end_tag('tr');
            $mform->addElement('html', $strrolename);
            $mform->addElement('advcheckbox', "roles[{$roleid}][{$permission_cananswer}]", '', '');
            $mform->addElement('html', $strnextcell);
            $mform->addElement('advcheckbox', "roles[{$roleid}][{$permission_required}]", '', '');
            $mform->disabledIf("roles[{$roleid}][{$permission_required}]", "roles[{$roleid}][$permission_cananswer]");
            $mform->addElement('html', $strnextcell);
            $mform->addElement('advcheckbox', "roles[{$roleid}][$permission_viewother]", '', '');
            $mform->addElement('html', $strclosingrow);
        }
        $mform->addElement('html', html_writer::start_tag('tr') .
                html_writer::start_tag('td', array('class' => 'cell', 'colspan' => '4')));
        $mform->addElement('static', 'roleserr', '');
        $mform->addElement('html', html_writer::end_tag('td') . html_writer::end_tag('tr'));

        $mform->addElement('html', html_writer::end_tag('tbody') . html_writer::end_tag('table'));
        $mform->addElement('html', get_string('roleaccessnotice', 'totara_appraisal'));
    }

    /**
     * Add role viewers list to question field definition form (who can see this element, equivalent to viewother)
     */
    protected function add_role_viewers() {
        $mform = & $this->_form;
        $headcolumns = html_writer::tag('th', get_string('role', 'totara_appraisal'), array('class' => 'header')) .
            html_writer::tag('th', get_string('visibility', 'totara_appraisal'), array('class' => 'header'));
        $header = html_writer::tag('thead', html_writer::tag('tr', $headcolumns));
        $mform->addElement('html', html_writer::start_tag('table', array('class' => 'role_matrix')) . $header .
            html_writer::start_tag('tbody'));

        $permission_viewother = appraisal::ACCESS_CANVIEWOTHER;

        $roles = appraisal::get_roles();
        $odd = false;
        foreach ($roles as $roleid => $name) {
            $odd = !$odd;
            $rowclass = ($odd) ? 'r0' : 'r1';
            $strrolename = html_writer::start_tag('tr', array('class' => $rowclass)) .
                    html_writer::tag('td', get_string($name, 'totara_appraisal')) .
                    html_writer::start_tag('td', array('class' => 'cell'));
            $strclosingrow = html_writer::end_tag('td') . html_writer::end_tag('tr');
            $mform->addElement('html', $strrolename);
            $mform->addElement('advcheckbox', "roles[{$roleid}][{$permission_viewother}]", '', '');
            $mform->addElement('html', $strclosingrow);
        }
        $mform->addElement('html', html_writer::start_tag('tr') .
                html_writer::start_tag('td', array('class' => 'cell', 'colspan' => '4')));
        $mform->addElement('static', 'roleserr', '');
        $mform->addElement('html', html_writer::end_tag('td') . html_writer::end_tag('tr'));

        $mform->addElement('html', html_writer::end_tag('tbody') . html_writer::end_tag('table'));
    }

    public function validation($data, $files) {
        $question = $this->_customdata['question'];
        $element = $question->get_element();
        $err = $element->define_validate_all($data, $files);

        // Check roles.
        if ($element->requires_permissions()) {
            $accesskey = appraisal::ACCESS_CANVIEWOTHER;
            $strerr = get_string('error:viewrequired', 'totara_appraisal');
            if ($element->is_answerable()) {
                $accesskey = appraisal::ACCESS_CANANSWER;
                $strerr = get_string('error:writerequired', 'totara_appraisal');
            }
            if (!isset($data['cloneprevroles']) || !$data['cloneprevroles']) {
                if (!$data['roles'][appraisal::ROLE_LEARNER][$accesskey] &&
                    !$data['roles'][appraisal::ROLE_MANAGER][$accesskey] &&
                    !$data['roles'][appraisal::ROLE_TEAM_LEAD][$accesskey] &&
                    !$data['roles'][appraisal::ROLE_APPRAISER][$accesskey]) {
                    $err['roleserr'] = $strerr;
                }
            }
        }

        return $err;
    }

    /**
     * Set role access same as preceding question
     */
    public function add_clone_roles() {
        $mform = & $this->_form;
        $mform->addElement('advcheckbox', "cloneprevroles", get_string('sameaspreceding', 'totara_appraisal'));
    }

    /**
     * Change question header
     *
     * @param string $header
     */
    public function set_header($header) {
        $mform = & $this->_form;
        $mform->getElement('questionheader')->setValue($header);
    }

    /**
     * Override set_data function to cope with roles manager
     * @param stdClass|array $data
     */
    public function set_data($default_values) {
        if (is_object($default_values)) {
            $default_values = (array) $default_values;
        }
        $rightanswer = appraisal::ACCESS_CANANSWER;
        $rightrequired = appraisal::ACCESS_MUSTANSWER;
        $rightviewother = appraisal::ACCESS_CANVIEWOTHER;
        $roles = appraisal::get_roles();
        foreach ($roles as $roleid => $name) {
            if (isset($default_values['roles'][$roleid])) {
                if (is_numeric($default_values['roles'][$roleid])) {
                    $rights = $default_values['roles'][$roleid];
                    $default_values['roles'][$roleid] = array();
                    $default_values['roles'][$roleid][$rightviewother] = ($rights & $rightviewother) == $rightviewother;
                    $default_values['roles'][$roleid][$rightanswer] = ($rights & $rightanswer) == $rightanswer;
                    $default_values['roles'][$roleid][$rightrequired] = ($rights & $rightrequired) == $rightrequired;
                }
            }
        }
        parent::set_data($default_values);
    }

}

/**
 * Event notification add/edit form
 */
class appraisal_message_form extends moodleform {

    public function definition() {
        global $OUTPUT;

        $mform = & $this->_form;
        $appraisalid = $this->_customdata['appraisalid'];
        $messageid = $this->_customdata['messageid'];
        $readonly = $this->_customdata['readonly'];
        if ($readonly) {
            $mform->freeze();
        }

        $mform->addElement('hidden', 'id')->setValue($appraisalid);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'messageid')->setValue($messageid);
        $mform->setType('messageid', PARAM_INT);
        $mform->addElement('hidden', 'action')->setValue('edit');
        $mform->setType('action', PARAM_ALPHA);

        // Events list -> appraisal activation, + all stages.
        $eventslist = array('0' => get_string('eventactivation', 'totara_appraisal'));
        $stages = appraisal_stage::get_list($appraisalid);
        foreach ($stages as $stage) {
            $eventslist[$stage->id] = get_string('eventstage', 'totara_appraisal', format_string($stage->name));
        }
        $typeslist = array(appraisal_message::EVENT_STAGE_COMPLETE => get_string('eventstagecomplete', 'totara_appraisal'),
            appraisal_message::EVENT_STAGE_DUE => get_string('eventstagedue', 'totara_appraisal'));

        $eventgrp = array();
        $eventgrp[] = $mform->createElement('select', 'eventid', '', $eventslist);
        $eventgrp[] = $mform->createElement('select', 'eventtype', '', $typeslist);

        $mform->disabledIf('eventtype', 'eventid', 'eq', 0);
        $mform->addGroup($eventgrp, 'event', get_string('event', 'totara_appraisal'), null, false);

        // Timing.
        $timegrp = array();
        $timegrp[] = $mform->createElement('radio', 'timing', '', get_string('eventtimenow', 'totara_appraisal'), '0');
        $timegrp[] = $mform->createElement('radio', 'timing', '', get_string('eventtimebefore', 'totara_appraisal'), '-1');
        $timegrp[] = $mform->createElement('radio', 'timing', '', get_string('eventtimeafter', 'totara_appraisal'), '1');

        $mform->addGroup($timegrp, 'timinggrp', get_string('eventtiming', 'totara_appraisal'), html_writer::empty_tag('br'));

        // How much.
        $deltatypes = array(appraisal_message::PERIOD_DAY => get_string('perioddays', 'totara_appraisal'),
            appraisal_message::PERIOD_WEEK => get_string('periodweeks', 'totara_appraisal'),
            appraisal_message::PERIOD_MONTH => get_string('periodmonths', 'totara_appraisal'));
        $deltagrp = array();
        $deltagrp[] = $mform->createElement('text', 'delta', '', array('class' => 'appraisal-event-time'));
        $deltagrp[] = $mform->createElement('select', 'deltaperiod', '', $deltatypes);
        $mform->setType('delta', PARAM_INT);
        $mform->disabledIf('delta', 'timinggrp[timing]', 'eq', 0);
        $mform->disabledIf('deltaperiod', 'timinggrp[timing]', 'eq', 0);
        $mform->addGroup($deltagrp, 'deltagrp', get_string('periodchoose', 'totara_appraisal'), null, false);

        // Recipients.
        $roles = appraisal::get_roles();
        $rolesgrp = array();
        foreach ($roles as $role => $rolename) {
            $name = get_string($rolename, 'totara_appraisal');
            $rolesgrp[] = $mform->createElement('advcheckbox', $role, '', $name);
        }
        $mform->addGroup($rolesgrp, 'rolegrp', get_string('eventrecipients', 'totara_appraisal'), html_writer::empty_tag('br'));
        $mform->addRule('rolegrp', null, 'required');

        // Send for completed.
        $compgrp = array();
        $compgrp[] = $mform->createElement('advcheckbox', 'stageis', '', get_string('eventsendstagecompleted', 'totara_appraisal'));
        $compgrp[] = $mform->createElement('radio', 'complete', '', get_string('eventstageisincomplete', 'totara_appraisal'), '-1');
        $compgrp[] = $mform->createElement('radio', 'complete', '', get_string('eventstageiscomplete', 'totara_appraisal'), '1');
        $mform->addGroup($compgrp, 'completegrp', '', html_writer::empty_tag('br'));
        $mform->disabledIf('completegrp[stageis]', 'eventtype', 'eq', appraisal_message::EVENT_STAGE_COMPLETE);
        $mform->disabledIf('completegrp[stageis]', 'eventid', 'eq', 0);
        $mform->disabledIf('completegrp[complete]', 'eventtype', 'eq', appraisal_message::EVENT_STAGE_COMPLETE);
        $mform->disabledIf('completegrp[complete]', 'eventid', 'eq', 0);
        $mform->disabledIf('completegrp[complete]', 'completegrp[stageis]');

        // Message recipients.
        $messageall = array('all' => get_string('eventsendroleall', 'totara_appraisal'),
            'each' => get_string('eventsendroleeach', 'totara_appraisal'));
        $mform->addElement('select', 'messagetoall', '', $messageall);

        // Required field icon. This is a bit of a hack.
        $requiredstr = html_writer::empty_tag('img', array('title' => get_string('requiredelement', 'form'),
                'src' => $OUTPUT->pix_url('req'), 'alt' => get_string('requiredelement', 'form'), 'class'=>'req'));

        // Messages.
        $mform->addElement('text', 'messagetitle[0]',
                get_string('eventmessagetitle', 'totara_appraisal') . $requiredstr,
                array('class' => 'appraisal-event-title hide-disabled'));
        $mform->setType('messagetitle[0]', PARAM_CLEANHTML);
        $mform->addElement('textarea', 'messagebody[0]',
                get_string('eventmessagebody', 'totara_appraisal') . $requiredstr,
                array('class' => 'appraisal-event-body hide-disabled'));
        $mform->setType('messagebody[0]', PARAM_CLEANHTML);
        $mform->disabledIf('messagetitle[0]', 'messagetoall', 'eq', 'each');
        $mform->disabledIf('messagebody[0]', 'messagetoall', 'eq', 'each');

        foreach ($roles as $role => $rolename) {
            $name = get_string($rolename, 'totara_appraisal');
            $mform->addElement('text', "messagetitle[$role]",
                    get_string('eventmessageroletitle', 'totara_appraisal', $name) . $requiredstr,
                    array('class' => 'appraisal-event-title hide-disabled'));
            $mform->setType("messagetitle[$role]", PARAM_CLEANHTML);
            $mform->addElement('textarea', "messagebody[$role]",
                    get_string('eventmessagerolebody', 'totara_appraisal', $name) . $requiredstr,
                    array('class' => 'appraisal-event-body hide-disabled'));
            $mform->setType("messagebody[$role]", PARAM_CLEANHTML);
            $mform->disabledIf("messagetitle[$role]", 'messagetoall', 'eq', 'all');
            $mform->disabledIf("messagebody[$role]", 'messagetoall', 'eq', 'all');
            $mform->disabledIf("messagetitle[$role]", "rolegrp[$role]");
            $mform->disabledIf("messagebody[$role]", "rolegrp[$role]");
        }
        if ($readonly) {
            $mform->addElement('static', 'backlink', '', html_writer::link(new moodle_url('/totara/appraisal/message.php',
                    array('id'=> $appraisalid)), get_string('back')));
        }
        $this->add_action_buttons(true, get_string('savechanges', 'moodle'));
    }

    public function validation($data, $files) {
        $err = array();
        if ($data['eventid'] == 0) {
            // Appraisal activation checked.
            // No before.
            if ($data['timinggrp']['timing'] < 0) {
                $err["timinggrp[timing]"] = get_string('error:beforedisabled', 'totara_appraisal');
            }
        } else {
            if ($data['eventtype'] == 'stage_completion') {
                // Stage completion.
                // No before.
                if ($data['timinggrp']['timing'] < 0) {
                    $err["timinggrp[timing]"] = get_string('error:beforedisabled', 'totara_appraisal');
                }
            } else {
                // Stage due.
                // Time not set.
                if ($data['timinggrp']['timing'] != 0) {
                    $isdeltaperiod = in_array($data['deltaperiod'], array(appraisal_message::PERIOD_DAY,
                        appraisal_message::PERIOD_WEEK, appraisal_message::PERIOD_MONTH));
                    if (!$isdeltaperiod || (int) $data['delta'] < 1) {
                        $err['deltagrp'] = get_string('error:numberrequired', 'totara_appraisal');
                    }
                }
            }
        }

        // At least one role is selected.
        $selectedroles = array_filter($data['rolegrp']);
        if (empty($selectedroles)) {
            $err['rolegrp'] = get_string('error:rolemessage', 'totara_appraisal');
        }

        if ($data['messagetoall'] == 'all') {
            // Common message must be not empty.
            if (trim($data['messagetitle'][0]) == '') {
                $err['messagetitle[0]'] = get_string('error:messagetitleyrequired', 'totara_appraisal');
            }
            if (trim($data['messagebody'][0]) == '') {
                $err['messagebody[0]'] = get_string('error:messagebodyrequired', 'totara_appraisal');
            }
        } else {
            // Each role message must be not empty.
            foreach ($selectedroles as $key => $role) {
                if (trim($data['messagetitle'][$key]) == '') {
                    $err["messagetitle[$key]"] = get_string('error:messagetitleyrequired', 'totara_appraisal');
                }
                if (trim($data['messagebody'][$key]) == '') {
                    $err["messagebody[$key]"] = get_string('error:messagebodyrequired', 'totara_appraisal');
                }
            }
        }
        return $err;
    }

    /**
     * Hide frozen empty messages
     * Run this function after set_data().
     */
    public function filter_frozen_messages() {
        $mform = & $this->_form;
        if ($mform->isFrozen()) {
            $roles = appraisal::get_roles();
            $roles[0] = 1;
            foreach ($roles as $role => $rolename) {
                $title = $mform->getElement("messagetitle[$role]")->getValue();
                $body = $mform->getElement("messagebody[$role]")->getValue();
                if (empty($title) && empty($body)) {
                    $mform->removeElement("messagetitle[$role]");
                    $mform->removeElement("messagebody[$role]");
                }
            }

        }
    }

}


/**
 * Choose stage to print form
 */
class appraisal_print_stages_form extends moodleform {
    public function definition() {
        $mform = & $this->_form;
        $stages = $this->_customdata['stages'];
        $appraisalid = $this->_customdata['appraisalid'];
        $role = $this->_customdata['role'];
        $subjectid = $this->_customdata['subjectid'];

        $mform->addElement('hidden', 'appraisalid')->setValue($appraisalid);
        $mform->setType('appraisalid', PARAM_INT);
        $mform->addElement('hidden', 'role')->setValue($role);
        $mform->setType('role', PARAM_INT);
        $mform->addElement('hidden', 'subjectid')->setValue($subjectid);
        $mform->setType('subjectid', PARAM_INT);

        $stageselect = array();
        foreach ($stages as $stagedata) {
            $stageselect[] = $elem = $mform->createElement('advcheckbox', $stagedata->id, '', format_string($stagedata->name));
            $elem->setChecked(true);
        }
        $group = $mform->addGroup($stageselect, 'stages',  get_string('sectioninclude', 'totara_appraisal'),
                html_writer::empty_tag('br'));
        $group->setAttributes(array('id' => 'stages-list'));
        $mform->addElement('advcheckbox', 'spaces', '', get_string('leavespace', 'totara_appraisal'));
    }

}
