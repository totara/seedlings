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
 * @author Maria Torres <maria.torres@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot .'/totara/core/utils.php');

/**
 * Form to upload icons.
 *
 */
class upload_icon_form extends moodleform {

    /**
     * Defines the form
     */
    public function definition() {
        $mform = $this->_form;
        $data = $this->_customdata['data'];
        $options = $this->_customdata['filemanageroptions'];

        $mform->addElement('hidden', 'id', $data->id);
        $mform->setType('id', PARAM_INT);

        $coursegroup = array();
        $coursegroup[] = $mform->createElement('header', 'course_icon', get_string('courseicon', 'totara_core'));
        $coursegroup[] = $mform->createElement('filemanager', 'course_filemanager', get_string('courseicon', 'totara_core'), null, $options);

        $programgroup = array();
        $programgroup[] = $mform->createElement('header', 'prog_icon', get_string('programicon', 'totara_core'));
        $programgroup[] = $mform->createElement('filemanager', 'program_filemanager', get_string('programicon', 'totara_core'), null, $options);

        $mform->addGroup($coursegroup, 'courseicon_group', '', null, false);
        $mform->addGroup($programgroup, 'programicon_group', '', null, false);

        $this->add_action_buttons();
        $this->set_data($data);
    }
}

