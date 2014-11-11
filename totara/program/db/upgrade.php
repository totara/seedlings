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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package totara
 * @subpackage program
 */

/**
 * Local db upgrades for Totara Core
 */

require_once($CFG->dirroot.'/totara/core/db/utils.php');


/**
 * Local database upgrade script
 *
 * @param   integer $oldversion Current (pre-upgrade) local db version timestamp
 * @return  boolean $result
 */
function xmldb_totara_program_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    if ($oldversion < 2012070600) {
        //doublecheck organisationid and positionid tables exist in prog_completion tables (T-9752)
        $table = new xmldb_table('prog_completion');
        $field = new xmldb_field('organisationid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timecompleted');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('positionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'organisationid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('prog_completion_history');
        $field = new xmldb_field('organisationid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'recurringcourseid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('positionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'organisationid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        totara_upgrade_mod_savepoint(true, 2012070600, 'totara_program');
    }

    if ($oldversion < 2012072700) {

        // a bug in the lang strings would have resulted in too many % symbols being stored in
        // the program messages - update any incorrect messages
        $sql = "UPDATE {prog_message} SET messagesubject = REPLACE(messagesubject, '%%programfullname%%', '%programfullname%'),
                mainmessage = REPLACE(" . $DB->sql_compare_text('mainmessage', 1024) . ", '%%programfullname%%', '%programfullname%')";
        $DB->execute($sql);
        totara_upgrade_mod_savepoint(true, 2012072700, 'totara_program');
    }

    if ($oldversion < 2012072701) {
        // Fix context levels on program capabilities
        $like_sql = $DB->sql_like('name', '?');
        $params = array(CONTEXT_PROGRAM, 'totara/program%');
        $DB->execute("UPDATE {capabilities} SET contextlevel = ? WHERE $like_sql", $params);
        totara_upgrade_mod_savepoint(true, 2012072701, 'totara_program');
    }

    if ($oldversion < 2012080300) {
        //get program enrolment plugin
        $program_plugin = enrol_get_plugin('totara_program');

        // add enrollment plugin to all courses associated with programs
        $program_courses = prog_get_courses_associated_with_programs();
        foreach ($program_courses as $course) {
            //add plugin
            $program_plugin->add_instance($course);
        }
        totara_upgrade_mod_savepoint(true, 2012080300, 'totara_program');
    }

    if ($oldversion < 2012080301) {
        //set up role assignment levels
        //allow all roles except guest, frontpage and authenticateduser to be assigned at Program level
        $roles = $DB->get_records('role', array(), '', 'id, archetype');
        $rcl = new stdClass();
        foreach ($roles as $role) {
            if (isset($role->archetype) && ($role->archetype != 'guest' && $role->archetype != 'user' && $role->archetype != 'frontpage')) {
                $rolecontextlevels[$role->id] = CONTEXT_PROGRAM;
                $rcl->roleid = $role->id;
                $rcl->contextlevel = CONTEXT_PROGRAM;
                $DB->insert_record('role_context_levels', $rcl, false);
            }
        }
        totara_upgrade_mod_savepoint(true, 2012080301, 'totara_program');
    }

    if ($oldversion < 2012081500) {
        // update completion fields to support signed values
        // as no completion date set uses -1
        $table = new xmldb_table('prog_assignment');
        $field = new xmldb_field('completiontime', XMLDB_TYPE_INTEGER, 10, false, XMLDB_NOTNULL, null, '0', 'includechildren');
        $dbman->change_field_unsigned($table, $field);

        $table = new xmldb_table('prog_completion');
        $field = new xmldb_field('timedue', XMLDB_TYPE_INTEGER, 10, false, XMLDB_NOTNULL, null, '0', 'timestarted');
        $dbman->change_field_unsigned($table, $field);

        $table = new xmldb_table('prog_completion_history');
        $field = new xmldb_field('timedue', XMLDB_TYPE_INTEGER, 10, false, XMLDB_NOTNULL, null, '0', 'timestarted');
        $dbman->change_field_unsigned($table, $field);

        totara_upgrade_mod_savepoint(true, 2012081500, 'totara_program');
    }

    if ($oldversion < 2012081501) {
        // Allow positionid to be null in prog_pos_assignment
        $table = new xmldb_table('prog_pos_assignment');
        $field = new xmldb_field('positionid', XMLDB_TYPE_INTEGER, 10, false, null, null, null, 'userid');
        $dbman->change_field_notnull($table, $field);
    }

    if ($oldversion < 2012081503) {
        // Clean up exceptions where users are no longer assigned.
        $exceptionids = $DB->get_fieldset_sql("SELECT e.id
                                      FROM {prog_exception} e
                                      LEFT JOIN {prog_assignment} a ON e.assignmentid = a.id
                                      LEFT JOIN {prog_user_assignment} ua ON ua.assignmentid = a.id AND e.userid = ua.userid
                                      WHERE ua.id IS NULL");
        if (!empty($exceptionids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($exceptionids);
            $DB->execute("DELETE
                          FROM {prog_exception}
                          WHERE id {$insql}
                         ", $inparams);
        }
        totara_upgrade_mod_savepoint(true, 2012081503, 'totara_program');
    }

    // Looks like the previous update block is missing a step, fix it and add a new one.
    if ($oldversion < 2013090900) {
        // Clean up exceptions where users are no longer assigned.
        $exceptionids = $DB->get_fieldset_sql("SELECT e.id
                                      FROM {prog_exception} e
                                      LEFT JOIN {prog_assignment} a ON e.assignmentid = a.id
                                      LEFT JOIN {prog_user_assignment} ua ON ua.assignmentid = a.id AND e.userid = ua.userid
                                      WHERE ua.id IS NULL");
        if (!empty($exceptionids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($exceptionids);
            $DB->execute("DELETE
                            FROM {prog_exception}
                            WHERE id {$insql}
                            ", $inparams);
        }
        totara_upgrade_mod_savepoint(true, 2013090900, 'totara_program');
    }

    // Add audiencevisible column to programs.
    if ($oldversion < 2013091000) {
        $table = new xmldb_table('prog');
        $field = new xmldb_field('audiencevisible', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 2);

        // Conditionally launch add field audiencevisible to program table.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        totara_upgrade_mod_savepoint(true, 2013091000, 'totara_program');
    }

    if ($oldversion < 2013092100) {
        // Certification id - if null then its a normal program, if not null then its a certification.
        $table = new xmldb_table('prog');
        $field = new xmldb_field('certifid', XMLDB_TYPE_INTEGER, '10');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key cerifid (foreign) to be added to prog.
        $table = new xmldb_table('prog');
        $key = new xmldb_key('cerifid', XMLDB_KEY_FOREIGN, array('certifid'), 'certif', array('id'));

        // Launch add key cerifid
        if (!$dbman->find_key_name($table, $key)) {
            $dbman->add_key($table, $key);
        }

        // Define field certifpath to be added to prog_courseset. Default is CERTIFPATH_STD.
        $table = new xmldb_table('prog_courseset');
        $field = new xmldb_field('certifpath', XMLDB_TYPE_INTEGER, '2', null, null, null, '1', 'label');

        // Conditionally launch add field certifpath
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field certifcount to be added to course_categories.
        $table = new xmldb_table('course_categories');
        $field = new xmldb_field('certifcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'programcount');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Update counts - can't use an update query because databases handle update differently when using a join/from
        // eg: Mysql uses JOIN, Postgresql uses FROM
        // Joining on category ensures the category exists
        $sql = 'SELECT cat.id,
                    SUM(CASE WHEN p.certifid IS NULL THEN 1 ELSE 0 END) AS programcount,
                    SUM(CASE WHEN p.certifid IS NULL THEN 0 ELSE 1 END) AS certifcount
                FROM {prog} p
                JOIN {course_categories} cat ON cat.id = p.category
                GROUP BY cat.id';
        $cats = $DB->get_records_sql($sql);
        foreach ($cats as $cat) {
            $DB->update_record('course_categories', $cat, true);
        }

        // program savepoint reached
        totara_upgrade_mod_savepoint(true, 2013092100, 'totara_program');
    }

    // Drop unused 'prog_exception_data' table and 'locked' field in 'prog_message' table.
    if ($oldversion < 2013101500) {
        $table = new xmldb_table('prog_exception_data');

        // Conditionally drop the table.
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $table = new xmldb_table('prog_message');
        $field = new xmldb_field('locked');

        // Conditionally drop the field.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Main savepoint reached.
        totara_upgrade_mod_savepoint(true, 2013101500, 'totara_program');
    }

    if ($oldversion < 2014022000) {
        // Fix nullability of positionid field in prog_pos_assignment.
        $table = new xmldb_table('prog_pos_assignment');
        $field = new xmldb_field('positionid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, 'userid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }
        totara_upgrade_mod_savepoint(true, 2014022000, 'totara_program');
    }

    // Add customfield support to programs.
    if ($oldversion < 2014030500) {
        // Define table prog_info_field to be created.
        $table = new xmldb_table('prog_info_field');

        // Adding fields to table prog_info_field.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('datatype', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('hidden', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('locked', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('required', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('forceunique', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('defaultdata', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('param1', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('param2', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('param3', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('param4', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('param5', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('fullname', XMLDB_TYPE_CHAR, '1024', null, null, null, null);

        // Adding keys to table prog_info_field.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for prog_info_field.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table prog_info_data to be created.
        $table = new xmldb_table('prog_info_data');

        // Adding fields to table prog_info_data.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('programid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table prog_info_data.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('fieldid', XMLDB_KEY_FOREIGN, array('fieldid'), 'prog_info_field', array('id'));

        // Conditionally launch create table for prog_info_data.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('prog_info_data_param');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('dataid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('value', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('dataid', XMLDB_KEY_FOREIGN, array('dataid'), 'prog_info_data', array('id'));
        $table->add_index('value', null, array('value'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Main savepoint reached.
        totara_upgrade_mod_savepoint(true, 2014030500, 'totara_program');
    }

    if ($oldversion < 2014030600) {
        // Add reason for denying or approving a program extension.
        $table = new xmldb_table('prog_extension');
        $field = new xmldb_field('reasonfordecision', XMLDB_TYPE_TEXT);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Main savepoint reached.
        totara_upgrade_mod_savepoint(true, 2014030600, 'totara_program');
    }

    if ($oldversion < 2014061600) {
        // Drop unused categoryid field accidentally added during 2.6 (2014030500) upgrade.
        $table = new xmldb_table('prog_info_field');
        $field = new xmldb_field('categoryid', XMLDB_TYPE_INTEGER, 20);
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Main savepoint reached.
        totara_upgrade_mod_savepoint(true, 2014061600, 'totara_program');
    }

    return true;
}
