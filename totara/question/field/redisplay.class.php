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
 * @author Nathan Lewis <nathan.lewis@totaralms.com>
 * @package totara
 * @subpackage totara_question
 */

/**
 * Notes:
 * Redisplay question type has to work differently from other question types.
 * During setup, it implements its own methods for:
 *      get_info  (how it is identified when editing)
 *      get_xmldb (how answers are stored in the db - none)
 *      add_field_specific_settings_elements (the editor interface)
 *      define_get, define_set (how to get and set data during setup)
 *      requires_name, requires_permissions (exclude these items during setup)
 * It redirects to the base question for these methods during setup:
 *      get_name
 * When loading a question within a module, you should replace the redisplay
 * question with the linked question. Then all other method calls will be
 * made to the linked question. No other methods should be called for this
 * question type.
 */

class question_redisplay extends question_base{

    public static function get_info() {
        return array('group' => question_manager::GROUP_OTHER,
                     'type' => get_string('questiontyperedisplay', 'totara_question'));
    }


    /**
     * Add database fields definition that represent current question
     *
     * @see question_base::get_xmldb()
     * @return array()
     */
    public function get_xmldb() {
        $fields = array();
        return $fields;
    }


    /**
     * Customfield specific settings elements
     *
     * @param MoodleQuickForm $form
     */
    protected function add_field_specific_settings_elements(MoodleQuickForm $form, $readonly, $moduleinfo) {
        $module = $this->prefix;

        if ($readonly) {
            $form->addElement('static', '', get_string('redisplay', 'totara_question'), $this->get_name());
        } else {
            $list = $module::get_redisplay_question_list($moduleinfo);

            $select = $form->createElement('selectgroups', 'linkedquestion', get_string('redisplay', 'totara_question'),
                    null, null, true);
            $select->addOptGroup(get_string('chooseexisting', 'totara_question'), array());
            $group = 0;

            $disabled = array();
            $disabled[true] = array('disabled' => 'disabled');
            $disabled[false] = array();

            foreach ($list as $item) {
                if ($item->isheading) {
                    $select->addOptGroup($item->name, array());
                    $group++;
                } else if ($item->disabled) {
                    $select->addOption($group, $item->name, $item->id, array('disabled' => 'disabled'));
                } else {
                    $select->addOption($group, $item->name, $item->id);
                }
            }

            $form->addElement($select);
            $form->addRule('linkedquestion', get_string('required'), 'required');
            $form->addHelpButton('linkedquestion', 'redisplay', 'totara_question');
            $select->setMultiple(false);
        }
    }


    /**
     * Add database fields definition that represent current question
     *
     * @see question_base::get_xmldb()
     * @return array()
     */
    public function define_get(stdClass $toform) {
        if (!isset($toform)) {
            $toform = new stdClass();
        }
        $toform->linkedquestion = $this->param1;
        return $toform;
    }


    /**
     * Set values from configuration form
     *
     * @param stdClass $fromform
     * @return stdClass $fromform
     */
    public function define_set(stdClass $fromform) {
        $this->param1 = (int)$fromform->linkedquestion;

        if (!isset($fromform->roles)) {
            $module = $this->prefix;
            $linkedquestionelement = $module::get_redisplay_question_element($this->param1);
            $fromform->roles = $linkedquestionelement->roleinfo;
        }
        return $fromform;
    }


    /**
     * If this element requires that a name be set up for its use.
     *
     * @return bool
     */
    public function requires_name() {
        return false;
    }


    /**
     * If this element requires that permissions be set up for its use.
     *
     * @return bool
     */
    public function requires_permissions() {
        return false;
    }


    /**
     * Get the name for this question field - used to identify the element during setup.
     *
     * @return string
     */
    public function get_name() {
        $module = $this->prefix;
        $linkedquestionelement = $module::get_redisplay_question_element($this->param1);
        return $linkedquestionelement->get_name();
    }


    public function add_field_form_elements(MoodleQuickForm $form) {
        print_error('error:invalidfunctioncalledinredisplay', 'totara_question');
    }

    public function add_field_specific_edit_elements(MoodleQuickForm $form) {
        print_error('error:invalidfunctioncalledinredisplay', 'totara_question');
    }

    public function add_field_specific_view_elements(MoodleQuickForm $form) {
        print_error('error:invalidfunctioncalledinredisplay', 'totara_question');
    }

    public function edit_get($dest) {
        print_error('error:invalidfunctioncalledinredisplay', 'totara_question');
    }

    public function edit_set(stdClass $data, $source) {
        print_error('error:invalidfunctioncalledinredisplay', 'totara_question');
    }

    public function edit_validate($fromform) {
        print_error('error:invalidfunctioncalledinredisplay', 'totara_question');
    }

    public function to_html($values) {
        print_error('error:invalidfunctioncalledinredisplay', 'totara_question');
    }

    public function get_title() {
        print_error('error:invalidfunctioncalledinredisplay', 'totara_question');
    }

    public function is_answerable() {
        print_error('error:invalidfunctioncalledinredisplay', 'totara_question');
    }

}
