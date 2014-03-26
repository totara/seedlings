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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_customfield
 */

class customfield_file extends customfield_base {


    function edit_load_item_data(&$item) {
        global $FILEPICKER_OPTIONS;
        $this->data = file_prepare_standard_filemanager($item, $this->inputname, $FILEPICKER_OPTIONS, $FILEPICKER_OPTIONS['context'],
                                                           'totara_customfield', $this->prefix . '_filemgr', $this->dataid);
    }

    /**
     * Saves the data coming from form
     * @param   mixed   data coming from the form
     * @param   string  name of the prefix (ie, competency)
     * @return  mixed   returns data id if success of db insert/update, false on fail, 0 if not permitted
     */
    function edit_save_data($itemnew, $prefix, $tableprefix) {
        global $DB, $FILEPICKER_OPTIONS;
        $formelement = $this->inputname . "_filemanager";
        if (!isset($itemnew->$formelement)) {
            // field not present in form, probably locked and invisible - skip it
            return;
        }

        //like the texteditors, we need to manipulate the records first with dummy data to ensure we have an id, then update later
        $data = new stdClass();
        $data->{$prefix.'id'} = $itemnew->id;
        $data->fieldid      = $this->field->id;
        $data->data = '';
        if ($dataid = $DB->get_field($tableprefix.'_info_data', 'id', array('id' => $this->dataid, $prefix.'id' => $itemnew->id, 'fieldid' => $data->fieldid))) {
            $data->id = $dataid;
            $DB->update_record($tableprefix.'_info_data', $data);
        } else {
            $data->id = $DB->insert_record($tableprefix.'_info_data', $data);
        }
        //process files, update the data record
        $itemnew = file_postupdate_standard_filemanager($itemnew, $this->inputname, $FILEPICKER_OPTIONS, $FILEPICKER_OPTIONS['context'],
                                                                      'totara_customfield', $this->prefix . '_filemgr', $data->id);
        $data->data = $data->id;
        $DB->update_record($tableprefix.'_info_data', $data);

    }

    function edit_field_add(&$mform) {
        global $FILEPICKER_OPTIONS;
        /// Create the file picker
        $mform->addElement('filemanager', $this->inputname.'_filemanager', format_string($this->field->fullname), null, $FILEPICKER_OPTIONS);
    }

    /**
    * Sets the required flag for the field in the form object
    * @param   object   instance of the moodleform class
    */
    function edit_field_set_required(&$mform) {
        if ($this->is_required()) {
            $mform->addRule($this->inputname.'_filemanager', get_string('customfieldrequired', 'totara_customfield'), 'required', null, 'client');
        }
    }

    function edit_field_set_locked(&$mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }
        if ($this->is_locked()) {
            $mform->hardFreeze($this->inputname);
            $mform->disabledif($this->inputname, 1);
            $mform->setConstant($this->inputname, $this->data);
        }
    }

    /**
     * Display the data for this field
     */
    static function display_item_data($data, $extradata=array()) {
        global $OUTPUT;

        if (empty($data)) {
            return $data;
        }
        if (!isset($extradata['prefix']) || empty($extradata['prefix'])) {
            return $data;
        }
        if (!isset($extradata['isexport'])) {
            $extradata['isexport'] = false;
        }
        $context = context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'totara_customfield', $extradata['prefix'] . '_filemgr', $data, null, false);
        if (count($files) < 1) {
            return get_string('nofileselected', 'totara_customfield');
        } else {
            //get the first file in this array (assoc array keyed by internal moodle hashes so use array_shift)
            $file = array_shift($files);
            $strfile = get_string('file');
            $filename = $file->get_filename();
            if ($extradata['isexport']) {
                return $filename;
            } else {
                $icon = mimeinfo("icon", $filename);
                $pic = $OUTPUT->pix_icon("f/{$icon}", $strfile);
                $url = new moodle_url("/pluginfile.php/{$file->get_contextid()}/{$file->get_component()}/{$file->get_filearea()}" . $file->get_filepath() . $file->get_itemid().'/'.$filename);
                return $OUTPUT->action_link($url, $pic . $filename, null, array('class' => "icon"));
            }
        }

    }
}
