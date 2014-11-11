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

class question_fixedimage extends question_base{
    public static function get_info() {
        return array('group' => question_manager::GROUP_OTHER,
                     'type' => get_string('questiontypefixedimage', 'totara_question'));
    }

    /**
     * Add database fields definition that represent current customfield
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
        global $FILEPICKER_OPTIONS;
        $options = $FILEPICKER_OPTIONS;

        if (!isset($toform)) {
            $toform = new stdClass();
        }

        if (is_array($this->param1)) {
            $toform->image_filemanager = $this->param1['image'];
            $toform->description = $this->param1['description'];
        }
        $toform = file_prepare_standard_filemanager($toform, 'image', $options, $options['context'], 'totara_'.$this->prefix,
            'quest_'.$this->id, 0);

        return $toform;
    }


    /**
     * Set values from configuration form
     *
     * @param stdClass $fromform
     * @return stdClass $fromform
     */
    public function define_set(stdClass $fromform) {
        global $FILEPICKER_OPTIONS;
        $options = $FILEPICKER_OPTIONS;

        $fromform = file_postupdate_standard_filemanager($fromform, 'image', $options, $options['context'], 'totara_'.$this->prefix,
                'quest_'.$this->getid(), 0);

        $options['image'] = $this->getid();
        $options['description'] = isset($fromform->description) ? $fromform->description : '';

        $this->param1 = $options;
        return $this;
    }


    /**
     * Customfield specific settings elements
     *
     * @param MoodleQuickForm $form
     */
    protected function add_field_specific_settings_elements(MoodleQuickForm $form, $readonly, $moduleinfo) {
        global $FILEPICKER_OPTIONS;

        $options = $FILEPICKER_OPTIONS;
        $options['accepted_types'] = 'web_image';
        if ($readonly) {
            $this->add_field_specific_view_elements($form);
        } else {
            $form->addElement('filemanager', 'image_filemanager', get_string('image', 'totara_question'), null, $options);
            $form->addRule('image_filemanager', get_string('required'), 'required', null, 'client');

            $form->addElement('textarea', 'description', get_string('description'), array('cols' => 60, 'rows' => 5));
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
        global $CFG, $FILEPICKER_OPTIONS;

        require_once($CFG->libdir . '/resourcelib.php');
        $contextid = $FILEPICKER_OPTIONS['context']->id;
        $content = '';

        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'totara_'.$this->prefix, 'quest_'.$this->id);
        foreach ($files as $draftfile) {
            if (!$draftfile->is_directory()) {
                $path = '/'.$contextid.'/'.'totara_'.$this->prefix.'/'.'quest_'.$this->id.$draftfile->get_filepath().
                        $draftfile->get_itemid().'/'.$draftfile->get_filename();
                $fullurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, false);
                $content .= resourcelib_embed_image($fullurl, '');
            }
        }

        $form->addElement('static', $this->get_prefix_form(), $this->name, $content);
        $form->addElement('static', $this->get_prefix_form().'description', '', format_string($this->param1['description']));

        // Remove label from form elements to get rid of empty space.
        $this->render_without_label($form, $this->get_prefix_form());
        $this->render_without_label($form, $this->get_prefix_form().'description');
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


    /**
     * Clone question properties (if they are stored in third party tables)
     * @param question_base $old old question instance
     */
    public function duplicate(question_base $old) {
        global $FILEPICKER_OPTIONS;

        $data = new stdClass();
        $data = file_prepare_standard_filemanager($data, 'file', $FILEPICKER_OPTIONS, $FILEPICKER_OPTIONS['context'],
            'totara_'.$this->prefix, 'quest_'.$old->id, 0);
        file_postupdate_standard_filemanager($data, 'file', $FILEPICKER_OPTIONS, $FILEPICKER_OPTIONS['context'],
            'totara_'.$this->prefix, 'quest_'.$this->id, 0);

    }

    public function delete() {
        global $FILEPICKER_OPTIONS;
        $fs = get_file_storage();
        $fs->delete_area_files($FILEPICKER_OPTIONS['context']->id, 'totara_'.$this->prefix, 'quest_'.$this->id, 0);
        parent::delete();
    }

    /**
     * Get the name for this question field - used to identify the element during setup.
     *
     * @return string
     */
    public function get_name() {
        global $CFG, $FILEPICKER_OPTIONS;

        $text = format_string($this->param1['description']);

        if (strlen($text) == 0) {
            require_once($CFG->libdir . '/resourcelib.php');

            $contextid = $FILEPICKER_OPTIONS['context']->id;

            $fs = get_file_storage();
            $files = $fs->get_area_files($contextid, 'totara_'.$this->prefix, 'quest_'.$this->id);
            foreach ($files as $draftfile) {
                if (!$draftfile->is_directory()) {
                    return $draftfile->get_filename();
                }
            }
        } else {
            return (strlen($text) > 40) ? substr($text, 0, 30) . '...' : $text;
        }
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
