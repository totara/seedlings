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
 * @subpackage hierarchy
 */

/**
 * Moodle Formslib templates for hierarchy forms
 */

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/position/lib.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

class position_settings_form extends moodleform {
    function definition() {
        global $POSITION_CODES;

        $mform =& $this->_form;

        $mform->addElement('header', 'positions', get_string('positions', 'totara_hierarchy'));

        $posoptions = get_config('totara_hierarchy', 'positionsenabled');
        $pos_defaults = explode(',', $posoptions);

        $group = array();
        $sitecontext = context_system::instance();
        foreach ($POSITION_CODES as $option => $code) {
            $group[] =& $mform->createElement('checkbox', $option, '', get_string('type' . $option, 'totara_hierarchy'));
            if ($posoptions) {
                if (in_array($code, $pos_defaults)) {
                    $mform->setDefault($option, 1);
                } else {
                    $mform->setDefault($option, 0);
                }
            }
        }
        $mform->addGroup($group, 'positionsenabled', get_string('positionsenabled', 'totara_hierarchy'), html_writer::empty_tag('br'), false);
        $mform->addHelpButton('positionsenabled', 'positionsenabled', 'totara_hierarchy');

        $this->add_action_buttons();
    }
}
