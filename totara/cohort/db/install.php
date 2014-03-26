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
 * @author Piers Harding <piers@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */

// This file replaces:
//   * STATEMENTS section in db/install.xml
//   * lib.php/modulename_install() post installation hook
//   * partially defaults.php

function xmldb_totara_cohort_install() {
    global $CFG, $DB, $COHORT_ALERT;

    require_once($CFG->dirroot . '/totara/cohort/lib.php');

    $dbman = $DB->get_manager();

    $result = true;

    // Define fields to be added to cohort.
    $table = new xmldb_table('cohort');

    $field = new xmldb_field('cohorttype');
    $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timemodified');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('modifierid');
    $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'cohorttype');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('visibility');
    $field->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'modifierid');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('alertmembers');
    $field->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'visibility');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('startdate');
    $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'alertmembers');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('enddate');
    $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'startdate');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('active');
    $field->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'enddate');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('calculationstatus');
    $field->set_attributes(XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'active');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('activecollectionid');
    $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'calculationstatus');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('draftcollectionid');
    $field->set_attributes(XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'activecollectionid');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    // Add broken rule.
    $field = new xmldb_field('broken');
    $field->set_attributes(XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'draftcollectionid');
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    if (!isset($CFG->cohort_lastautoidnumber)) {
        set_config('cohort_lastautoidnumber', 0);
    }
    if (!isset($CFG->cohort_autoidformat)) {
        set_config('cohort_autoidformat', 'AUD%04d');
    }
    // Add cohort alert global config.
    if (get_config('cohort', 'alertoptions') === false) {
        set_config('alertoptions', implode(',', array_keys($COHORT_ALERT)), 'cohort');
    }

    return $result;
}
