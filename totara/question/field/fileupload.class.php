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

class question_fileupload extends question_base{
    /**
     * Stored value
     * @var string
     */
    protected $value = '';

    public static function get_info() {
        return array('group' => question_manager::GROUP_QUESTION,
                     'type' => get_string('questiontypefile', 'totara_question'));
    }

    /**
     * Add database fields definition that represent current customfield
     *
     * @see question_base::get_xmldb()
     * @return array()
     */
    public function get_xmldb() {
        $fields = array();
        $fields[] = new xmldb_field($this->get_prefix_db(), XMLDB_TYPE_INTEGER, 1);
        return $fields;
    }

    /**
     * Customfield specific settings elements
     *
     * @param MoodleQuickForm $form
     */
    protected function add_field_specific_settings_elements(MoodleQuickForm $form, $readonly, $moduleinfo) {
        $form->addElement('header', 'uploadheader', get_string('uploadoptions', 'totara_question'));
        $form->setExpanded('uploadheader');

        if ($readonly) {
            $form->addElement('static', 'maxnum', get_string('uploadmaxnum', 'totara_question'));
        } else {
            $form->addElement('text', 'maxnum', get_string('uploadmaxnum', 'totara_question'));
            $form->setDefault('maxnum', '1');
            $strrequired = get_string('fieldrequired', 'totara_question');
            $form->addRule('maxnum', $strrequired, 'required', null, 'client');
        }
        $form->setType('maxnum', PARAM_INT);
    }

    protected function define_validate($data, $files) {
        $err = array();
        if ($data->maxnum < 1) {
            $err['maxnum'] = get_string('uploadmaxinvalid', 'totara_question');
        }
        return $err;
    }

    public function define_get(stdClass $toform) {
        $toform->maxnum = $this->param1;
        return $toform;
    }

    public function define_set(stdClass $fromform) {
        $this->param1 = $fromform->maxnum;
        return $fromform;
    }

    /**
     * Add form elements that represent current field
     *
     * @see question_base::add_field_specific_edit_elements()
     * @param MoodleQuickForm $form Form to alter
     */
    public function add_field_specific_edit_elements(MoodleQuickForm $form) {
        global $FILEPICKER_OPTIONS;
        if (isguestuser() || !isloggedin()) {
            $form->addElement('static', '', '', get_string('unavailableforguest', 'totara_question'));
            return;
        }
        $options = $FILEPICKER_OPTIONS;
        $options['maxfiles'] = $this->param1;
        $form->addElement('filemanager', $this->get_prefix_form().'_filemanager', $this->label, null, $options);
        if ($this->required) {
            $form->addRule($this->get_prefix_form() . '_filemanager', get_string('required'), 'required');
        }
    }

    public function edit_get($dest) {
        global $FILEPICKER_OPTIONS;
        $options = $FILEPICKER_OPTIONS;
        $options['maxfiles'] = $this->param1;

        $data = new stdClass();
        if (!$this->value || isguestuser() || !isloggedin()) {
            return $data;
        }

        // Using this prefix contruction means we can only use questions in totara modules.
        $component = 'totara_' . $this->prefix;

        if ($dest == 'form') {
            $name = $this->get_prefix_form();
            $data->$name = $this->value;
            $data = file_prepare_standard_filemanager($data, $name, $options, $options['context'],
                    $component, 'quest_'.$this->id, $this->answerid);
        } else {
            $name = $this->get_prefix_db();
            $data->{$name.'_filemanager'} = $this->value;
            $data = file_postupdate_standard_filemanager($data, $name, $options, $options['context'],
                    $component, 'quest_'.$this->id, $this->answerid);
            unset($data->{$name.'_filemanager'});
        }
        return $data;
    }

    public function edit_set(stdClass $data, $source) {
        if (isguestuser() || !isloggedin()) {
            return;
        }
        if ($source == 'form') {
            $name = $this->get_prefix_form();
            $this->value = $data->{$name.'_filemanager'};
        } else {
            $name = $this->get_prefix_db();
            $this->value = $data->$name;
        }
    }

    public function to_html($value) {
        global $FILEPICKER_OPTIONS, $OUTPUT;
        if (isguestuser() || !isloggedin()) {
            return get_string('unavailableforguest', 'totara_question');
        }
        $fs = get_file_storage();
        $files = $fs->get_area_files($FILEPICKER_OPTIONS['context']->id, 'totara_' . $this->prefix, 'quest_'.$this->id,
                $this->answerid, null, false);
        $list = array();
        foreach ($files as $file) {
            $strfile = get_string('file');
            $filename = $file->get_filename();
            $icon = mimeinfo("icon", $filename);
            $pic = $OUTPUT->pix_icon("f/{$icon}", $strfile);

            $path = $file->get_contextid().'/'.$file->get_component().'/'.$file->get_filearea() . $file->get_filepath();
            $path .=  $file->get_itemid().'/'.$filename;
            $url = new moodle_url('/pluginfile.php/'.$path, array('forcedownload' => 1));
            $list[] = $OUTPUT->action_link($url, $pic . $filename, null, array('class' => "icon"));

        }
        return implode(html_writer::empty_tag('br'), $list);
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
