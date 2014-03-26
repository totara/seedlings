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
 * @subpackage totara_question
 */

/**
 * This form supports AJAX calls and has extensive support of question fields.
 * It is recommended to extend this class instead of moodleform when question used.
 */
abstract class question_base_form extends moodleform {
    public function __construct($action=null, $customdata=null, $method='post', $target='', $attributes=null, $editable=true,
            $formidprefix='mform') {
        global $PAGE;
        $PAGE->requires->js_module(moodleform::get_js_module());
        $this->moodleform($action, $customdata, $method, $target, $attributes, $editable, $formidprefix);
    }

    public static function get_header(question_base $element, $readonly = false) {
        if ($readonly) {
            $header = 'questionviewheader';
        } else if ($element->id > 0) {
            $header = 'questioneditheader';
        } else {
            $header = 'questionaddheader';
        }
        $info = $element->get_info();
        return get_string($header, 'totara_question', $info['type']);
    }
}

/**
 * Choose element form
 */
class question_choose_element_form extends moodleform {
    /**
     * Tablename prefix used be some elements
     * @var string
     */
    protected $prefix = '';

    /**
     * Add choose question elements select box
     *
     * @param array $excludegroups
     */
    public function definition($excludegroups = array(), $excludetypes = array()) {
        $mform =& $this->_form;
        $group = array();
        $options = array();
        $elements = question_manager::get_registered_elements();
        foreach ($elements as $key => $elem) {
            if (!empty($excludegroups) && in_array($elem['group'], $excludegroups)) {
                continue;
            }
            if (!empty($excludetypes) && in_array($key, $excludetypes)) {
                continue;
            }
            switch ($elem['group']) {
                case question_manager::GROUP_QUESTION:
                    $grouplabel = get_string('groupquestion', 'totara_question');
                    break;
                case question_manager::GROUP_REVIEW:
                    $grouplabel = get_string('groupreview', 'totara_question');
                    break;
                default:
                    $grouplabel = get_string('groupother', 'totara_question');
            }

            $options[$grouplabel][$key] = $elem['type'];
        }
        $group[] = $mform->createElement('selectgroups', 'datatype', '', $options, null, true);
        $group[] = $mform->createElement('submit', 'submitbutton', get_string('add', 'totara_question'));
        $mform->addGroup($group, 'addquestgroup', '', null, false);
    }

    public function validation($data, $files) {
        $err = array();
        if (!isset($data['datatype']) || $data['datatype'] == '') {
            $err['datatype'] = get_string('error:choosedatatype', 'totara_question');
        }
        return $err;
    }
}
