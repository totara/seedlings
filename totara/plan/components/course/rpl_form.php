<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas 
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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage plan
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once "$CFG->dirroot/lib/formslib.php";

class totara_course_rpl_form extends moodleform {

    function definition() {
        $mform =& $this->_form;
        $id = $this->_customdata['id'];
        $course = $this->_customdata['courseid'];
        $user = $this->_customdata['userid'];
        $rpltext = $this->_customdata['rpltext'];
        $rplid = $this->_customdata['rplid'];

        //hidden elements
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid', $course);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'userid', $user);
        $mform->setType('userid', PARAM_INT);
        if ($rplid) {
            $mform->addElement('hidden', 'rplid', $rplid);
            $mform->setType('rplid', PARAM_INT);
        }

        $mform->addElement('header', 'rpl_general', get_string('coursecompletion', 'totara_plan'));

        $mform->addElement('text', 'rpl', get_string('rpl', 'totara_plan'), array('maxsize' => '255', 'size' => '50'));
        $mform->setType('rpl', PARAM_TEXT);
        if ($rpltext) {
            $mform->setDefault('rpl', $rpltext);
        }

        $this->add_action_buttons();
    }
}
