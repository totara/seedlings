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

require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot.'/totara/hierarchy/lib.php');

class item_bulkadd_form extends moodleform {

    // Define the form
    function definition() {
        global $CFG;

        $mform =& $this->_form;

        $prefix = $this->_customdata['prefix'];
        $shortprefix = hierarchy::get_short_prefix($prefix);
        $page = $this->_customdata['page'];
        $frameworkid = $this->_customdata['frameworkid'];

        $hierarchy = new $prefix();

        $framework = $hierarchy->get_framework($frameworkid);
        $items     = $hierarchy->get_items();
        $types   = $hierarchy->get_types();

        $parents = array();

        // Add top as an option
        $parents[0] = get_string('top', 'totara_hierarchy');

        if ($items) {
            // Cache breadcrumbs
            $breadcrumbs = array();

            foreach ($items as $parent) {
                //add using same spacing style as the bulkitems->move available & selected multiselects
                $parents[$parent->id] = str_repeat('&nbsp;', 4 * ($parent->depthlevel - 1)) . format_string($parent->fullname);
            }
        }

        /// Add some extra hidden fields
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'prefix', $prefix);
        $mform->setType('prefix', PARAM_SAFEDIR);
        $mform->addElement('hidden', 'frameworkid', $frameworkid);
        $mform->setType('frameworkid', PARAM_INT);
        $mform->addElement('hidden', 'page', $page);
        $mform->setType('page', PARAM_INT);

        $mform->addElement('static', 'framework', get_string($prefix.'framework', 'totara_hierarchy'), $framework->fullname);

        $mform->addElement('select', 'parentid', get_string('parent', 'totara_hierarchy'), $parents, totara_select_width_limiter());
        $mform->addRule('parentid', null, 'required');
        $mform->setType('parentid', PARAM_INT);
        $mform->addHelpButton('parentid', $prefix.'parent', 'totara_hierarchy');
        if ($types) {
            // new item
            // show type picker if there are choices
            $select = array('0' => '');
            foreach ($types as $type) {
                $select[$type->id] = $type->fullname;
            }
            $mform->addElement('select', 'typeid', get_string('type', 'totara_hierarchy'), $select);
            $mform->addHelpButton('typeid', $prefix.'type', 'totara_hierarchy');
        } else {
            // new item
            // but no types exist
            // default to 'unclassified'
            $mform->addElement('hidden', 'typeid', '0');
            $mform->setType('typeid', PARAM_INT);
        }


        $mform->addElement('textarea', 'itemnames', get_string('enternamesoneperline', 'totara_hierarchy', get_string($prefix, 'totara_hierarchy')), 'rows="15" cols="50"');
        $mform->addRule('itemnames', null, 'required');
        $mform->setType('itemnames', PARAM_TEXT);

        // See if any hierarchy specific form definition exists
        $hierarchy->add_additional_item_form_fields($mform);

        $this->add_action_buttons();
    }

}
