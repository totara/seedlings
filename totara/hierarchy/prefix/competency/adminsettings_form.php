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
 * @subpackage totara_hierarchy
 */

require_once($CFG->libdir.'/formslib.php');

class competency_global_settings_form extends moodleform
{
    function definition() {
        $mform =& $this->_form;
        $mform->addElement('checkbox', 'competencyuseresourcelevelevidence', get_string('useresourcelevelevidence', 'totara_hierarchy'));
        $mform->setDefault('competencyuseresourcelevelevidence', 0);

        $this->add_action_buttons(false);
    }
}
