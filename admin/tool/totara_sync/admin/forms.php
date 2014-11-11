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
 * @subpackage totara_sync
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir . '/formslib.php');

/**
 * Formslib template for the element settings form
 */
class totara_sync_element_settings_form extends moodleform {
    function definition() {
        global $CFG;
        $mform =& $this->_form;
        $element = $this->_customdata['element'];
        $elementname = $element->get_name();

        // Source selection
        if ($sources = $element->get_sources()) {
            $sourceoptions = array('' => get_string('select'));
            foreach ($sources as $s) {
                $sourceoptions[$s->get_name()] = get_string('displayname:'.$s->get_name(), 'tool_totara_sync');
            }
            $mform->addElement('select', 'source_'.$elementname,
                get_string('source', 'tool_totara_sync'), $sourceoptions);
            $mform->setDefault('source_'.$elementname, get_config('totara_sync', 'source_'.$elementname));
        } else {
            $mform->addElement('static', 'nosources', '('.get_string('nosources', 'tool_totara_sync').')');
            if (!$element->has_config()) {
                return;
            }
        }
        try {
            $source = $element->get_source();
            if ($source->has_config()) {
                $mform->addElement('static', 'configuresource', '', html_writer::link(new moodle_url('/admin/tool/totara_sync/admin/sourcesettings.php', array('element' => $element->get_name(), 'source' => $source->get_name())), get_string('configuresource', 'tool_totara_sync')));
            }
        } catch (totara_sync_exception $e) {
            // do nothing, as no source is present :D
        }

        // Element configuration
        if ($element->has_config()) {
            $element->config_form($mform);
        }

        $this->add_action_buttons(false);
    }
}


/**
 * Formslib template for the source settings form
 */
class totara_sync_source_settings_form extends moodleform {

    protected $elementname = '';

    function definition() {
        $mform =& $this->_form;
        $source = $this->_customdata['source'];
        $this->elementname = $this->_customdata['elementname'];
        $sourcename = $source->get_name();

        // Source configuration
        if ($source->config_form($mform) !== false) {
            $this->add_action_buttons(false);
        }
    }

    function set_data($data) {
        //these are set in config_form
        unset($data->import_idnumber);
        unset($data->import_timemodified);

        if ($this->elementname == 'pos' || $this->elementname == 'org') {
            unset($data->import_fullname);
            unset($data->import_frameworkidnumber);
        }
        if ($this->elementname == 'user') {
            unset($data->import_username);
            unset($data->import_deleted);
        }
        parent::set_data($data);
    }
}


/**
 * Form for general sync settings
 */
class totara_sync_config_form extends moodleform {
    function definition() {
        global $CFG;

        $mform = $this->_form;

        // File access.
        if (has_capability('tool/totara_sync:setfileaccess', context_system::instance())) {
            $mform->addElement('header', 'fileheading', get_string('files', 'tool_totara_sync'));
            $dir = get_string('fileaccess_directory', 'tool_totara_sync');
            $upl = get_string('fileaccess_upload', 'tool_totara_sync');
            $mform->addElement('select', 'fileaccess', get_string('fileaccess', 'tool_totara_sync'),
                array(FILE_ACCESS_DIRECTORY => $dir, FILE_ACCESS_UPLOAD => $upl));
            $mform->setType('fileaccess', PARAM_INT);
            $mform->setDefault('fileaccess', $dir);
            $mform->addHelpButton('fileaccess', 'fileaccess', 'tool_totara_sync');
            $mform->addElement('text', 'filesdir', get_string('filesdir', 'tool_totara_sync'), array('size' => 50));
            $mform->setType('filesdir', PARAM_TEXT);
            $mform->disabledIf('filesdir', 'fileaccess', 'eq', FILE_ACCESS_UPLOAD);
        }

        // Notifications.
        $mform->addElement('header', 'notificationheading', get_string('notifications', 'tool_totara_sync'));
        $mform->addElement('checkbox', 'notifytypes[error]', get_string('notifytypes', 'tool_totara_sync'),
                get_string('errorplural', 'tool_totara_sync'));
        $mform->addElement('checkbox', 'notifytypes[warn]', '', get_string('warnplural', 'tool_totara_sync'));

        $mform->addElement('text', 'notifymailto', get_string('notifymailto', 'tool_totara_sync'));
        $mform->setType('notifymailto', PARAM_TEXT);
        $mform->setDefault('notifymailto', $CFG->supportemail);
        $mform->addHelpButton('notifymailto', 'notifymailto', 'tool_totara_sync');
        $mform->setExpanded('notificationheading');

        // Schedule.
        $mform->addElement('header', 'scheduleheading', get_string('schedule', 'tool_totara_sync'));
        $mform->addElement('advcheckbox', 'cronenable', get_string('enablescheduledsync', 'tool_totara_sync'));
        $mform->setDefault('cronenable', 1);
        $mform->addElement('scheduler', 'schedulegroup', get_string('schedule', 'tool_totara_sync'));
        $mform->disabledIf('schedulegroup', 'cronenable', 'notchecked');
        $mform->setExpanded('scheduleheading');

        $this->add_action_buttons(false);
    }

    /**
     * Check if path is well-formed (no validation for existence)
     * @param array $data
     * @param array $files
     * @return boolean
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (DIRECTORY_SEPARATOR == '\\') {
            $pattern = '/^[a-z0-9 \/\.\-_\\\\\\:]{1,}$/i';
        } else {
            // Character '@' is used in Jenkins workspaces, it might be used on other servers too.
            $pattern = '/^[a-z0-9@ \/\.\-_]{1,}$/i';
        }

        if ($data['fileaccess'] == FILE_ACCESS_DIRECTORY && isset($data['filesdir'])) {
            $filesdir = trim($data['filesdir']);
            if (!preg_match($pattern, $filesdir)) {
                $errors['filesdir'] = get_string('pathformerror', 'tool_totara_sync');
            } else if (!is_dir($filesdir)) {
                $errors['filesdir'] = get_string('notadirerror', 'tool_totara_sync', $filesdir);
            } else if (!is_writable($filesdir)) {
                $errors['filesdir'] = get_string('readonlyerror', 'tool_totara_sync', $filesdir);
            }
        }

        if (!empty($data['notifymailto'])) {
            $emailaddresses = array_map('trim', explode(',', $data['notifymailto']));
            foreach ($emailaddresses as $mailaddress) {
                if (!validate_email($mailaddress)) {
                    $errors['notifymailto'] = get_string('invalidemailaddress', 'tool_totara_sync', format_string($mailaddress));
                    break;
                }
            }
        }

        return $errors;
    }
}


/**
 * Form for uploading of source sync files
 */
class totara_sync_source_files_form extends moodleform {
    function definition() {
        global $CFG, $USER, $FILEPICKER_OPTIONS;
        $mform =& $this->_form;
        require_once($CFG->dirroot.'/admin/tool/totara_sync/lib.php');

        $elements = totara_sync_get_elements($onlyenabled=true);
        if (!count($elements)) {
            $mform->addElement('html', html_writer::tag('p',
                get_string('noenabledelements', 'tool_totara_sync')));
            return;
        }

        foreach ($elements as $e) {
            $name = $e->get_name();
            if (!has_capability("tool/totara_sync:upload{$name}", context_system::instance())) {
                continue;
            }
            $mform->addElement('header', "header_{$name}",
                get_string("displayname:{$name}", 'tool_totara_sync'));
            $mform->setExpanded("header_{$name}");

            try {
                $source = $e->get_source();
            } catch (totara_sync_exception $e) {
                $link = "{$CFG->wwwroot}/admin/tool/totara_sync/admin/elementsettings.php?element={$name}";
                $mform->addElement('html', html_writer::tag('p',
                    get_string('nosourceconfigured', 'tool_totara_sync', $link)));
                continue;
            }

            if (!$source->uses_files()) {
                $mform->addElement('html', html_writer::tag('p',
                    get_string('sourcedoesnotusefiles', 'tool_totara_sync')));
                continue;
            }


            $mform->addElement('filepicker', $name,
            get_string('displayname:'.$source->get_name(), 'tool_totara_sync'), 'size="40"');

            if (get_config('totara_sync', 'fileaccess') == FILE_ACCESS_UPLOAD) {
                $usercontext = context_user::instance($USER->id);
                $systemcontext = context_system::instance();
                $fs = get_file_storage();

                //check for existing draft area to prevent massive duplication
                $existing_files = $fs->get_area_files($systemcontext->id, 'totara_sync', $name);
                if (sizeof($existing_files) > 0) {
                    $file = reset($existing_files);
                    $draftid = !empty($file) ? $file->get_itemid() : 0;
                    $existing_draft = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid);

                    //if no existing draft area, make one
                    if (sizeof($existing_draft) < 1) {
                        //create draft area to set as the value for mform->filepicker
                        file_prepare_draft_area($draftid, $systemcontext->id, 'totara_sync', $name, null, $FILEPICKER_OPTIONS);
                        $file_record = array('contextid' => $usercontext->id, 'component' => 'user', 'filearea'=> 'draft', 'itemid' => $draftid);

                        //add existing file(s) to the draft area
                        foreach ($existing_files as $file) {
                            if ($file->is_directory()) {
                                continue;
                            }
                            $fs->create_file_from_storedfile($file_record, $file);
                            $mform->addElement('static', '', '',
                                get_string('note:syncfilepending', 'tool_totara_sync'));
                        }
                    }
                    //set the filepicker value to the draft area
                    $mform->getElement($name)->setValue($draftid);
                }
            }
        }

        $this->add_action_buttons(false, get_string('upload'));
    }

    /**
     * Does this form element have a file?
     *
     * @param string $elname
     * @return boolean
     */
    function hasFile($elname) {
        global $USER;

        $elements = totara_sync_get_elements($onlyenabled=true);
        // element must exist
        if (!in_array($elname, array_keys($elements))) {
            return false;
        }

        // source must be configured
        try {
            $source = $elements[$elname]->get_source();
        } catch (totara_sync_exception $e) {
            return false;
        }

        $values = $this->_form->exportValues($elname);
        if (empty($values[$elname])) {
            return false;
        }
        $draftid = $values[$elname];
        $fs = get_file_storage();
        $context = context_user::instance($USER->id);
        if (!$files = $fs->get_area_files($context->id, 'user', 'draft', $draftid, 'id DESC', false)) {
            return false;
        }
        return true;
    }
}
