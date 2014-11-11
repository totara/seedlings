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
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');

class competency_edit_form extends item_edit_form {

    // Load data for the form
    function definition_hierarchy_specific() {
        global $DB;

        $mform =& $this->_form;
        $item = $this->_customdata['item'];

        // Get all aggregation methods
        global $COMP_AGGREGATION;
        $aggregations = array();
        foreach ($COMP_AGGREGATION as $title => $key) {
            $aggregations[$key] = get_string('aggregationmethod'.$key, 'totara_hierarchy');
        }

        // Get the name of the framework's scale. (Note this code expects there
        // to be only one scale per framework, even though the DB structure
        // allows there to be multiple since we're using a go-between table)
        $scaledesc = $DB->get_field_sql("
            SELECT s.name
            FROM
                {{$this->hierarchy->shortprefix}_scale} s,
                {{$this->hierarchy->shortprefix}_scale_assignments} a
            WHERE
                a.frameworkid = ?
                and a.scaleid = s.id
        ", array($item->frameworkid));

        $mform->addElement('select', 'aggregationmethod', get_string('aggregationmethod', 'totara_hierarchy'), $aggregations);
        $mform->addHelpButton('aggregationmethod', 'competencyaggregationmethod', 'totara_hierarchy');
        $mform->addRule('aggregationmethod', get_string('aggregationmethod', 'totara_hierarchy'), 'required', null);

        $mform->addElement('static', 'scalename', get_string('scale'), ($scaledesc)?$scaledesc:get_string('none'));

        $mform->addHelpButton('scalename', 'competencyscale', 'totara_hierarchy');
    }
}
