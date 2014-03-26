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

// called with competency_scale.json.php?competencyid=x
//
// returns JSON containing select options for the scale
// used by that competency, e.g.:
//
// {[ {'name': 0, 'value' : 'Select a proficiency...'},
//    {'name': 1, 'value' : 'Not Competent'},
//    {'name': 2, 'value' : 'Competent with Supervison'},
//    {'name': 3, 'value' : 'Competent'} ]}

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');

$competencyid = required_param('competencyid',PARAM_INT);

$frameworkid = $DB->get_field('comp', 'frameworkid', array('id' => $competencyid));
if (!$frameworkid) {
    print_error('competencyframeworknotfound', 'totara_hierarchy');
}

$scaleid = $DB->get_field('comp_scale_assignments', 'scaleid', array('frameworkid' => $frameworkid));
if (!$scaleid) {
    print_error('frameworkscaleidnotfound', 'totara_hierarchy');
}

$sql = 'SELECT id AS name, name AS value from {comp_scale_values}
    WHERE scaleid = ?';
if ($scale_values = $DB->get_records_sql($sql, array($scaleid))) {
    // append initial pulldown option
    $picker = new stdClass();
    $picker->name = '0';
    $picker->value = get_string('selectaproficiency', 'totara_core');
    print json_encode(array($picker)+$scale_values);
} else {
    // return no data
    print json_encode(array());
}
