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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage totara_question
 */

class question_ratingnumeric extends question_base{
    /**
     * Display types
     */
    const DISPLAY_SLIDER = 1;
    const DISPLAY_INPUT = 2;

    public static function get_info() {
        return array('group' => question_manager::GROUP_QUESTION,
                     'type' => get_string('questiontyperatingnum', 'totara_question'));
    }

    /**
     * Add database fields definition that represent current customfield
     *
     * @see question_base::get_xmldb()
     * @return array()
     */
    public function get_xmldb() {
        $fields = array();
        $fields[$this->get_prefix_form()] = new xmldb_field($this->get_prefix_db(), XMLDB_TYPE_INTEGER, 10);
        return $fields;
    }

    /**
     * Customfield specific settings elements
     *
     * @param MoodleQuickForm $form
     */
    protected function add_field_specific_settings_elements(MoodleQuickForm $form, $readonly, $moduleinfo) {
        // Form for choosing range.
        $form->addElement('header', 'rangeheader', get_string('answerrange', 'totara_question'));
        $form->setExpanded('rangeheader');

        if ($readonly) {
            $form->addElement('static', 'rangefrom', get_string('rangefrom', 'totara_question'));
            $form->addElement('static', 'rangeto', get_string('rangeto', 'totara_question'));
        } else {
            $form->addElement('text', 'rangefrom', get_string('rangefrom', 'totara_question'));
            $form->addElement('text', 'rangeto', get_string('rangeto', 'totara_question'));
            $strrequired = get_string('fieldrequired', 'totara_question');
            $form->addRule('rangefrom', $strrequired, 'required', null, 'client');
            $form->addRule('rangeto', $strrequired, 'required', null, 'client');
        }
        $form->setType('rangefrom', PARAM_INT);
        $form->setType('rangeto', PARAM_INT);

        $setdefault = array();
        $setdefault[] = $form->createElement('advcheckbox', 'setdefault', 0, get_string('setdefault', 'totara_question'));
        if ($readonly) {
            $setdefault[] = $form->createElement('static', 'defaultvalue');
        } else {
            $setdefault[] = $form->createElement('text', 'defaultvalue');
            $form->disabledIf('defaultvalue', 'setdefault');
        }
        $form->setType('defaultvalue', PARAM_INT);
        $form->addGroup($setdefault, 'setdefaultgroup', '', null, false);

        $list = array();
        $list[] = $form->createElement('radio', 'list', '', get_string('rangeslider', 'totara_question'), self::DISPLAY_SLIDER);
        $list[] = $form->createElement('radio', 'list', '', get_string('rangeinput', 'totara_question'), self::DISPLAY_INPUT);
        $form->addGroup($list, 'listtype', get_string('displaysettings', 'totara_question'), array('<br/>'), false);

        // Set default.
        $form->setDefault('list', self::DISPLAY_SLIDER);
    }

    protected function define_validate($data, $files) {
        $err = array();
        if ($data->rangefrom >= $data->rangeto) {
            $err['rangefrom'] = get_string('rangeinvalid', 'totara_question');
        } else if (($data->rangeto - $data->rangefrom) > 1000) {
            $err['rangefrom'] = get_string('rangelimit', 'totara_question');
        }

        if (isset($data->setdefault) && $data->setdefault) {
            if ($data->defaultvalue < $data->rangefrom || $data->defaultvalue > $data->rangeto) {
                $err['setdefaultgroup'] = get_string('defaultvalueoutrange', 'totara_question');
            }
        }
        return $err;
    }

    public function define_get(stdClass $toform) {
        $toform->rangefrom = $this->param1['rangefrom'];
        $toform->rangeto = $this->param1['rangeto'];
        if (isset($this->defaultdata) && $this->defaultdata != null) {
            $toform->defaultvalue = $this->defaultdata;
            $toform->setdefault = 1;
        }
        if ($this->param2) {
            $toform->list = $this->param2;
        }
        return $toform;
    }

    public function define_set(stdClass $fromform) {
        $param1 = array();
        $param1['rangefrom'] = $fromform->rangefrom;
        $param1['rangeto'] = $fromform->rangeto;
        $this->param1 = $param1;
        if ($fromform->setdefault) {
            $this->defaultdata = $fromform->defaultvalue;
        } else {
            $this->defaultdata = null;
        }
        $this->param2 = $fromform->list;
        return $fromform;
    }

    /**
     * Validate elements input
     *
     * @see question_base::edit_validate
     * @return array
     */
    public function edit_validate($fromform) {
        $errors = parent::edit_validate($fromform);

        if (!empty($fromform[$this->get_prefix_form()])) {
            if ($fromform[$this->get_prefix_form()] < $this->get_min() || $fromform[$this->get_prefix_form()] > $this->get_max()) {
                $errors[$this->get_prefix_form()] = get_string('valueoutsiderange', 'totara_question');
            }
        } else if ($this->required) {
            $errors[$this->get_prefix_form()] = get_string('valueoutsiderange', 'totara_question');
        }

        return $errors;
    }

    /**
     * Return minimum value for range
     *
     * @return int
     */
    public function get_min() {
        if (is_array($this->param1) && isset($this->param1['rangefrom'])) {
            return $this->param1['rangefrom'];
        }
    }

    /**
     * Return maximum value for range
     *
     * @return int
     */
    public function get_max() {
        if (is_array($this->param1) && isset($this->param1['rangeto'])) {
            return $this->param1['rangeto'];
        }
    }

    /**
     * Add form elements that represent current field
     *
     * @see question_base::add_field_specific_edit_elements()
     * @param MoodleQuickForm $form Form to alter
     */
    public function add_field_specific_edit_elements(MoodleQuickForm $form) {
        global $PAGE;

        $to = $this->param1['rangeto'];
        $from = $this->param1['rangefrom'];
        $default = isset($this->defaultdata) && $this->defaultdata ? $this->defaultdata : null;

        switch ($this->param2) {
            case self::DISPLAY_SLIDER:
                local_js();

                $args = array('args' => '{"slider_field_name": "' . $this->get_prefix_form(). '"}');

                $jsmodule = array(
                    'name' => 'totara_question_ratingnumeric',
                    'fullpath' => '/totara/question/field/ratingnumeric.js',
                    'requires' => array('json'));

                $PAGE->requires->js_init_call('M.totara_question_ratingnumeric.init', $args, false, $jsmodule);

                $elements = array();
                for ($i = $from; $i <= $to; $i++) {
                    $elements[$i] = $i;
                }
                $form->addElement('select', $this->get_prefix_form(), $this->label, $elements);
                break;
            case self::DISPLAY_INPUT:
                $form->addElement('text', $this->get_prefix_form(), $this->label);
                $form->setType($this->get_prefix_form(), PARAM_INT);
                $form->addRule($this->get_prefix_form(), get_string('err_numeric', 'form'), 'numeric', null, 'client');
                $form->addElement('static', $this->get_prefix_form() . '_range', null,
                        get_string('ratingrequiredrange', 'totara_question', array('from' => $from, 'to' => $to)));
                break;
        }
        if (!$form->exportValue($this->get_prefix_form())) {
            $form->setDefault($this->get_prefix_form(), $default);
        }
        if ($this->required) {
            $form->addRule($this->get_prefix_form(), get_string('required'), 'required');
        }
    }

    /**
     * If this element has any answerable form fields, or it's a view only (informational or static) element.
     *
     * @see question_base::is_answerable()
     * @return bool
     */
    public function is_answerable() {
        return true;
    }
}
