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

class question_fixedtext extends question_base{
    public static function get_info() {
        return array('group' => question_manager::GROUP_OTHER,
                     'type' => get_string('questiontypefixedtext', 'totara_question'));
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
     * Add database fields definition that represent current question
     *
     * @see question_base::get_xmldb()
     * @return array()
     */
    public function define_get(stdClass $toform) {
        global $TEXTAREA_OPTIONS;

        if (!isset($toform)) {
            $toform = new stdClass();
        }

        $toform->fixedtextformat = FORMAT_HTML;
        $toform->fixedtext = $this->param1;
        $toform = file_prepare_standard_editor($toform, 'fixedtext', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                'totara_'.$this->prefix, 'quest_'.$this->id, 0);
        return $toform;
    }


    /**
     * Set values from configuration form
     *
     * @param stdClass $fromform
     * @return stdClass $fromform
     */
    public function define_set(stdClass $fromform) {
        global $TEXTAREA_OPTIONS;
        $fromform = file_postupdate_standard_editor($fromform, 'fixedtext', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                'totara_'.$this->prefix, 'quest_'.$this->getid(), 0);
        $this->param1 = $fromform->fixedtext;
        $fs = get_file_storage();
        $fs->delete_area_files($TEXTAREA_OPTIONS['context']->id, 'totara_'.$this->prefix, 'draft');
        return $fromform;
    }


    /**
     * Question specific settings elements
     *
     * @param MoodleQuickForm $form
     */
    protected function add_field_specific_settings_elements(MoodleQuickForm $form, $readonly, $moduleinfo) {
        global $TEXTAREA_OPTIONS;
        if ($readonly) {
            $this->add_field_specific_view_elements($form);
        } else {
            $form->addElement('editor', 'fixedtext_editor', get_string('questiontypefixedtext', 'totara_question'), null,
                    $TEXTAREA_OPTIONS);
            $form->setType('fixedtext_editor', PARAM_CLEANHTML);
            $form->addRule('fixedtext_editor', get_string('required'), 'required', null, 'client');
        }
    }


    /**
     * Add form elements that represent current field
     *
     * @see question_base::add_field_specific_edit_elements()
     * @param MoodleQuickForm $form Form to alter
     */
    public function add_field_specific_edit_elements(MoodleQuickForm $form) {
        $this->add_field_specific_view_elements($form);
    }


    /**
     * Add form elements related to questions to form for user answers
     * Default implementation for first mapped field.
     * Override for all other cases.
     *
     * @param MoodleQuickForm $form
     */
    public function add_field_specific_view_elements(MoodleQuickForm $form) {
        global $TEXTAREA_OPTIONS;
        $fixedtext_editor = file_rewrite_pluginfile_urls($this->param1, 'pluginfile.php',
                $TEXTAREA_OPTIONS['context']->id, 'totara_'.$this->prefix, 'quest_'.$this->getid(), 0, $TEXTAREA_OPTIONS);
        $fixedtext_editor = format_text($fixedtext_editor, FORMAT_MOODLE);
        $form->addElement('static', $this->get_prefix_form(), $this->name, $fixedtext_editor);
        $this->render_without_label($form, $this->get_prefix_form());
    }

    /**
     * If this element has any answerable form fields, or it's a view only (informational or static) element.
     *
     * @see question_base::is_answerable()
     * @return bool
     */
    public function is_answerable() {
        return false;
    }

    public function duplicate(question_base $old) {
        global $TEXTAREA_OPTIONS;
        $data = new stdClass();
        $data->fixedtext = $old->param1;
        $data->fixedtextformat = FORMAT_HTML;
        $data = file_prepare_standard_editor($data, 'fixedtext', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
            'totara_'.$this->prefix, 'quest_'.$old->id, 0);
        $data = file_postupdate_standard_editor($data, 'fixedtext', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
            'totara_'.$this->prefix, 'quest_'.$this->id, 0);

        $this->storage->param1 = $data->fixedtext;
        $this->storage->save();
    }

    public function delete() {
        global $TEXTAREA_OPTIONS;
        $fs = get_file_storage();
        $fs->delete_area_files($TEXTAREA_OPTIONS['context']->id, 'totara_'.$this->prefix, 'quest_'.$this->id, 0);
        parent::delete();
    }

    /**
     * Get the name for this question field - used to identify the element during setup.
     *
     * @return string
     */
    public function get_name() {
        $text = format_string($this->param1);
        return (strlen($text) > 40) ? substr($text, 0, 30) . '...' : $text;
    }

    /**
     * Get the title to display for this question field - shown to the user when answering.
     *
     * @return string
     */
    public function get_title() {
        return '';
    }

}
