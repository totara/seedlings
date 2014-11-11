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
 * @package    mod_certificate
 * @author     Russell England <russell.england@catalyst-eu.net>
 * @copyright  Catalyst IT Ltd 2013 <http://catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

/**
 * Function for Totara specific DB changes to core Moodle modules.
 *
 * Put code here rather than in db/upgrade.php if you need to change core
 * Moodle database schema for Totara-specific changes.
 *
 * This is executed during EVERY upgrade. Make sure your code can be
 * re-executed EVERY upgrade without problems.
 *
 * Do not use savepoints in this code.
 *
 * @param int $oldversion Current (pre-upgrade) local db version timestamp.
 */
function mod_certificate_totara_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Define table certificate_issues_history to be created.
    $table = new xmldb_table('certificate_issues_history');

    // Conditionally launch create table for certificate_issues_history.
    if (!$dbman->table_exists($table)) {

        // Adding fields to table certificate_issues_history.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('certificateid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('code', XMLDB_TYPE_CHAR, '40', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('grade', XMLDB_TYPE_CHAR, '300', null, null, null, null);
        $table->add_field('outcome', XMLDB_TYPE_CHAR, '300', null, null, null, null);
        $table->add_field('timearchived', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('idarchived', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table certificate_issues_history.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('certificateid', XMLDB_KEY_FOREIGN, array('certificateid'), 'certificate', array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

        $dbman->create_table($table);
    }
}
