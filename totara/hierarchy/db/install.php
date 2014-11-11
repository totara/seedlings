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
 * @package totara
 * @subpackage hierarchy
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_totara_hierarchy_install() {
    global $CFG, $DB, $USER;

    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    // add organisationid and positionid fields to course completion table

    $table = new xmldb_table('course_completions');

    $field = new xmldb_field('organisationid');
    if (!$dbman->field_exists($table, $field)) {
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'course');
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('positionid');
    if (!$dbman->field_exists($table, $field)) {
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'organisationid');
        $dbman->add_field($table, $field);
    }

    totara_hierarchy_install_default_comp_scale();
    // set positionsenabled default config
    if (get_config('totara_hierarchy', 'positionsenabled') === false) {
        set_config('positionsenabled', '1,2,3', 'totara_hierarchy');
    }

    // Create a default goal scale.
    $now = time();

    $todb = new stdClass();
    $todb->name = get_string('goalscale', 'totara_hierarchy');
    $todb->description = '';
    $todb->usermodified = $USER->id;
    $todb->timemodified = $now;
    $todb->defaultid = 1;
    $scaleid = $DB->insert_record('goal_scale', $todb);

    $goal_scale_vals = array(
        array('name' => get_string('goalscaledefaultassigned', 'totara_hierarchy'), 'scaleid' => $scaleid,
              'sortorder' => 3, 'usermodified' => $USER->id, 'timemodified' => $now),
        array('name' => get_string('goalscaledefaultstarted', 'totara_hierarchy'), 'scaleid' => $scaleid,
              'sortorder' => 2, 'usermodified' => $USER->id, 'timemodified' => $now),
        array('name' => get_string('goalscaledefaultcompleted', 'totara_hierarchy'), 'scaleid' => $scaleid,
              'sortorder' => 1, 'usermodified' => $USER->id, 'timemodified' => $now, 'proficient' => 1)
    );

    foreach ($goal_scale_vals as $svrow) {
        $todb = new stdClass();
        foreach ($svrow as $key => $val) {
            // Insert default competency scale values, if non-existent.
            $todb->$key = $val;
        }
        $svid = $DB->insert_record('goal_scale_values', $todb);
    }

    unset($goal_scale_vals, $scaleid, $svid, $todb);

    return true;
}
