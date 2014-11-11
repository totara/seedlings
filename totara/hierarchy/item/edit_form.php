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

class item_edit_form extends moodleform {

    // Define the form
    function definition() {
        global $TEXTAREA_OPTIONS;
        $mform =& $this->_form;

        $prefix = $this->_customdata['prefix'];

        $shortprefix = hierarchy::get_short_prefix($prefix);
        $item = $this->_customdata['item'];
        $page = $this->_customdata['page'];
        $dialog = !empty($this->_customdata['dialog']);

        $this->hierarchy = $hierarchy = new $prefix();

        $framework = $hierarchy->get_framework($item->frameworkid);
        $items     = $hierarchy->get_items();
        $types   = $hierarchy->get_types();
        $type   = $hierarchy->get_type_by_id($item->typeid);
        $typename = ($type) ? $type->fullname : get_string('unclassified', 'totara_hierarchy');

        /// Add some extra hidden fields
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'prefix', $prefix);
        $mform->setType('prefix', PARAM_ALPHA);
        $mform->addElement('hidden', 'frameworkid');
        $mform->setType('frameworkid', PARAM_INT);
        $mform->addElement('hidden', 'visible');
        $mform->setType('visible', PARAM_INT);
        $mform->addElement('hidden', 'sortorder');
        $mform->setType('sortorder', PARAM_INT);
        $mform->addElement('hidden', 'page', $page);
        $mform->setType('page', PARAM_INT);

        $mform->addElement('text', 'framework', get_string($prefix.'framework', 'totara_hierarchy'));
        $mform->hardFreeze('framework');
        $mform->setType('framework', PARAM_TEXT);

        $parents = $hierarchy->get_parent_list($items, $item->id);
        // If we only have one possible parent, it must be the top level, so hide the
        // pulldown
        if (count($parents) <= 1) {
            $mform->addElement('hidden', 'parentid', 0);
        } else {
            $mform->addElement('select', 'parentid', get_string('parent', 'totara_hierarchy'), $parents, totara_select_width_limiter());
            $mform->addHelpButton('parentid', $prefix.'parent', 'totara_hierarchy');
        }
        $mform->setType('parentid', PARAM_INT);

        $mform->addElement('text', 'fullname', get_string('name'), 'maxlength="1024" size="50"');
        $mform->addRule('fullname', get_string($prefix.'missingname', 'totara_hierarchy'), 'required', null);
        $mform->setType('fullname', PARAM_MULTILANG);

        if (HIERARCHY_DISPLAY_SHORTNAMES) {
            $mform->addElement('text', 'shortname', get_string($prefix.'shortname', 'totara_hierarchy'), 'maxlength="100" size="20"');
            $mform->addHelpButton('shortname', $prefix.'shortname', 'totara_hierarchy');
            $mform->addRule('shortname', get_string($prefix.'missingshortname', 'totara_hierarchy'), 'required', null);
            $mform->setType('shortname', PARAM_MULTILANG);
        }

        $mform->addElement('text', 'idnumber', get_string($prefix.'idnumber', 'totara_hierarchy'), 'maxlength="100"  size="10"');
        $mform->addHelpButton('idnumber', $prefix.'idnumber', 'totara_hierarchy');
        $mform->setType('idnumber', PARAM_TEXT);

        // If we are in a dialog, hide the htmleditor. It messes with the jquery code
        if (!$dialog) {
            $mform->addElement('editor', 'description_editor', get_string('description', 'totara_hierarchy'), null, $TEXTAREA_OPTIONS);
            $mform->addHelpButton('description_editor', $prefix.'description', 'totara_hierarchy');
            $mform->setType('description_editor', PARAM_CLEANHTML);
        }

        if ($item->id) {

            $group = array();
            // display current type (static)
            $group[] = $mform->createElement('static', 'type', '');
            // and provide a button for changing type
            $group[] = $mform->createElement('submit', 'changetype', get_string('changetype', 'totara_hierarchy'));
            $mform->addGroup($group, 'typegroup', get_string('type', 'totara_hierarchy'), array(' &nbsp; '), false);

            $mform->setDefault('type', $typename);
            $mform->addHelpButton('typegroup', $prefix.'type', 'totara_hierarchy');

            // store the actual type ID
            $mform->addElement('hidden', 'typeid', $item->typeid);
            $mform->setType('typeid', PARAM_INT);

        } else {
            // new item
            if ($types) {
                // show type picker if there are choices
                $select = array('0' => '');
                foreach ($types as $type) {
                    $select[$type->id] = $type->fullname;
                }
                $mform->addElement('select', 'typeid', get_string('type', 'totara_hierarchy'), $select, totara_select_width_limiter());
                $mform->addHelpButton('typeid', $prefix.'type', 'totara_hierarchy');
            } else {
                // no types exist
                // default to 'unclassified'
                $mform->addElement('hidden', 'typeid', '0');
                $mform->setType('typeid', PARAM_INT);
            }
        }

        if (get_config('totara_sync', "element_{$shortprefix}_enabled")) {
            $mform->addElement('advcheckbox', 'totarasync', get_string('totarasync', 'tool_totara_sync').'?');
            $mform->addHelpButton('totarasync', 'totarasync', 'tool_totara_sync');
        }

        // See if any hierarchy specific form definition exists.
        $hierarchy->add_additional_item_form_fields($mform);

        // Next show the custom fields if we're editing an existing items (otherwise we don't know the typeid).
        if ($item->id && $item->typeid != 0) {
            customfield_definition($mform, $item, $prefix, $item->typeid, $shortprefix.'_type');
        }

        $this->add_action_buttons();
    }

    function set_data($data) {
        // Set the data for any fields specific to the hierarchy type.
        $hierarchy = $this->_customdata['hierarchy'];
        $hierarchy->set_additional_item_form_fields($data);

        parent::set_data($data);
    }

    function definition_after_data() {
        global $DB;

        $mform =& $this->_form;
        $itemid = $mform->getElementValue('id');
        $prefix   = $mform->getElementValue('prefix');
        $shortprefix = hierarchy::get_short_prefix($prefix);

        if ($item = $DB->get_record($shortprefix, array('id' => $itemid))) {

            customfield_definition_after_data($mform, $item, $prefix, $item->typeid, $shortprefix.'_type');

        }

    }

    function validation($itemnew, $files) {
        global $DB;

        $hierarchy = $this->_customdata['hierarchy'];
        $errors = parent::validation($itemnew, $files);
        $itemnew = (object)$itemnew;
        $shortprefix = hierarchy::get_short_prefix($itemnew->prefix);

        // Check any fields unique to this hierarchy type.
        $errors += $hierarchy->validate_additional_item_form_fields($itemnew);

        if ($itemnew->id) {
            // Check custom fields.
            $errors += customfield_validation($itemnew, $itemnew->prefix, $shortprefix.'_type');
        }

        if (!empty($itemnew->idnumber) && totara_idnumber_exists($shortprefix, $itemnew->idnumber, $itemnew->id)) {
            $errors['idnumber'] = get_string('idnumberexists', 'totara_core');
        }

        return $errors;
    }

}
