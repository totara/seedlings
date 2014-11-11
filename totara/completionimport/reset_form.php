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
 * @author Russell England <russell.england@catalyst-net.nz>
 * @package totara
 * @subpackage completionimport
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

class reset_form extends moodleform {
    public function definition() {
        $mform =& $this->_form;

        $mform->addElement('checkbox', 'course', get_string('resetcourse', 'totara_completionimport'));
        $mform->setType('course', PARAM_BOOL);

        if (!totara_feature_disabled('certifications')) {
            $mform->addElement('checkbox', 'certification', get_string('resetcertification', 'totara_completionimport'));
            $mform->setType('certification', PARAM_BOOL);
        }

        $this->add_action_buttons(false, get_string('resetabove', 'totara_completionimport'));

    }

}