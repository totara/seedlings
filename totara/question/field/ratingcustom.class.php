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

class question_ratingcustom extends multichoice {
    /**
     * Display types
     */
    const DISPLAY_RADIO = 1;
    const DISPLAY_MENU = 2;

    protected $value = arraY();

    public function __construct($storage, $subjectid = 0, $answerid = 0) {
        parent::__construct($storage, $subjectid, $answerid);
        $this->scaletype = self::SCALE_TYPE_RATING;
    }


    public static function get_info() {
        return array('group' => question_manager::GROUP_QUESTION,
                     'type' => get_string('questiontyperatingcustom', 'totara_question'));
    }

    /**
     * Add database fields definition that represent current customfield
     *
     * @see question_base::get_xmldb()
     * @return array()
     */
    public function get_xmldb() {
        $fields = parent::get_xmldb();
        $fields[] = new xmldb_field($this->get_prefix_db() . 'score', XMLDB_TYPE_CHAR, '255');
        return $fields;
    }


    /**
     * Validate custom element configuration
     * @param stdClass $data
     * @param array $files
     */
    public function define_validate($data, $files) {
        $err = parent::define_validate($data, $files);

        for ($i = 0; $i < count($data->choice); $i++) {
            $choice = $data->choice[$i];
            if (trim($choice['option']) != "" && $choice['score'] == "") {
                $err["choice[{$i}]"] = get_string('ratingchoicemusthavescore', 'totara_question');
            } else if ($choice['score'] != "" && trim($choice['option']) == "") {
                $err["choice[{$i}]"] = get_string('ratingscoremusthavechoice', 'totara_question');
            }
        }

        return $err;
    }


    /**
     * Customfield specific settings elements
     *
     * @param MoodleQuickForm $form
     * @return question_multichoice2 $this
     */
    protected function add_field_specific_settings_elements(MoodleQuickForm $form, $readonly, $moduleinfo) {
        $this->add_choices_menu($form, $readonly);

        // Add select type.
        $list = array();
        $list[] = $form->createElement('radio', 'list', '', get_string('multichoiceradio', 'totara_question'), 1);
        $list[] = $form->createElement('radio', 'list', '', get_string('multichoicemenu', 'totara_question'), 2);
        $form->addGroup($list, 'listtype', get_string('displaysettings', 'totara_question'), array('<br/>'), true);

        $form->setDefault('listtype[list]', self::DISPLAY_RADIO);

        return $this;
    }


    /**
     * Return minimum value for range
     *
     * @return int
     */
    public function get_min() {
        global $DB;

        return $DB->get_field($this->prefix . '_scale_value', 'MIN(score)', array($this->prefix . 'scaleid' => $this->param1));
    }

    /**
     * Return maximum value for range
     *
     * @return int
     */
    public function get_max() {
        global $DB;

        return $DB->get_field($this->prefix . '_scale_value', 'MAX(score)', array($this->prefix . 'scaleid' => $this->param1));
    }


    /**
     * Override take answer from object
     *
     * @see question_base::get_data()
     * @param string $dest
     * @return stdClass
     */
    public function edit_get($dest) {
        global $DB;

        $data = new stdClass();

        if (empty($this->value)) {
            return $data;
        }

        if ($dest == 'form') {
            switch ($this->param2) {
                case self::DISPLAY_RADIO:
                    $name = $this->get_prefix_form();
                    break;
                case self::DISPLAY_MENU:
                    $name = $this->get_prefix_form();
                    break;
            }
            $data->$name = $this->value['key'];
            $data->{$name . 'score'} = $DB->get_field($this->prefix . '_scale_value', 'score', array('id' => $this->value['key']));
        } else {
            $name = $this->get_prefix_db();
            $data->$name = $this->value['key'];
            $data->{$name . 'score'} = $this->value['score'];
        }

        return $data;
    }


    public function edit_set(stdClass $data, $source) {
        global $DB;

        $this->value = array();
        parent::edit_set($data, $source);

        if ($source == 'form') {
            $name = $this->get_prefix_form();
            if (isset($data->$name) && ($data->$name != self::ISANSWERED_TRUE)) {
                $this->value['key'] = $data->$name;
                $this->value['score'] = $DB->get_field($this->prefix . '_scale_value', 'score', array('id' => $this->value['key']));
            } else {
                $this->value['key'] = self::ISANSWERED_TRUE;
                $this->value['score'] = null;
            }
        } else {
            $name = $this->get_prefix_db();
            $this->value['key'] = $data->$name;
            $this->value['score'] = $data->{$name . 'score'};
        }
    }


    /**
     * Add a choices menu header to the form.
     *
     * @param bool $readonly
     */
    protected function add_choices_menu_header($form, $readonly) {
        $header = array();
        if ($readonly) {
            $header[] = $form->createElement('static', '', '', '<div class="question-rating-scale-checkbox">&nbsp;</div>');
        }
        $header[] = $form->createElement('static', 'choice', '', html_writer::tag('span',
                html_writer::tag('b', get_string('choice', 'totara_question')),
                array('class' => 'question-rating-scale-header')));
        $header[] = $form->createElement('static', 'rating', '', html_writer::tag('span',
                html_writer::tag('b', get_string('score', 'totara_question')),
                array('class' => 'question-rating-scale-header')));
        $form->addGroup($header, 'choiceheader');
    }


    /**
     * Add a choices menu item to the form.
     *
     * @param bool $readonly
     */
    protected function add_choices_menu_item($form, $i, $readonly) {
        $choice = array();
        if ($readonly) {
            $choice[] = $form->createElement('static', '', '', '<div class="question-rating-scale-checkbox">');
            $choice[] = $form->createElement('advcheckbox', 'default');
            $choice[] = $form->createElement('static', '', '', '</div><div class="question-rating-scale-static">');
            $choice[] = $form->createElement('static', 'option');
            $choice[] = $form->createElement('static', '', '', '</div><div class="question-rating-scale-static">');
            $choice[] = $form->createElement('static', 'score');
            $choice[] = $form->createElement('static', '', '', '</div>');
        } else {
            $choice[] = $form->createElement('text', 'option');
            $choice[] = $form->createElement('text', 'score');
            $choice[] = $form->createElement('advcheckbox', 'default', '',
                    get_string('defaultmake', 'totara_question'), array('class' => 'makedefault'));
        }

        $form->addGroup($choice, "choice[$i]");
        $form->setType("choice[$i][option]", PARAM_TEXT);
        $form->setType("choice[$i][score]", PARAM_TEXT);
        $form->addGroupRule("choice[$i]",
                array("score" => array(array(get_string('error:scorenumeric', 'totara_question'), 'numeric', '', 'client'))));
    }

}
