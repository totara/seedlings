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
 * @subpackage totara_plan
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
* Standard HTML output renderer for totara_plan module
*/
class totara_plan_renderer extends plugin_renderer_base {

    /**
     * Displays a form allowing a manager to approve items
     *
     * @param array $require_approval Array of dp_*_component objects as returned by {@link development_plan->get_components()} that require approval
     *
     * @return HTML to be displayed
     */
    public function totara_print_approval_form($requested_items, $require_approval) {
        $output = '';

        $form_attributes = array('id' => 'dp-component-update', 'action' => qualified_me(), 'method' => 'POST');
        $output .= html_writer::start_tag('form', $form_attributes);

        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));

        foreach ($require_approval as $componentname => $component) {

            $output .= $this->output->heading(get_string($component->component.'plural', 'totara_plan'));

            $output .= $component->display_approval_list($requested_items[$componentname]);
        }

        $output .= html_writer::empty_tag('br');
        $submit_attributes = array('type' => 'submit', 'name' => 'submitbutton', 'value' => get_string('updatesettings', 'totara_plan'));
        $output .= html_writer::empty_tag('input', $submit_attributes);

        $output .= html_writer::end_tag('form');

        return $output;

    }

    /**
    * Display the add plan button
    *
    * @access public
    * @param  int    $userid the users id
    * @return string $out    the display code
    */
    function print_add_plan_button($userid) {
        $action = new moodle_url('/totara/plan/add.php', array('userid' => $userid));
        $title = get_string('createnewlearningplan', 'totara_plan');
        $out = html_writer::start_tag('div', array('class' => 'dp-add-plan-link'));
        $out .= html_writer::start_tag('form', array('action' => $action->out(), 'method' => 'get'));
        $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'userid', 'value' => $userid));
        $out .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'submit', 'value' => $title));
        $out .= html_writer::end_tag('form');
        $out .= html_writer::end_tag('div');
        return $out;
    }
}
