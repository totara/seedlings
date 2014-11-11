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
 * @author Rusell England <russell.england@catalyst-eu.net>
 * @package totara
 * @subpackage completionimport
 */

/**
 * Local db upgrades for Totara completion import.
 */

require_once($CFG->dirroot.'/totara/core/db/utils.php');

/**
 * Local database upgrade script
 *
 * @param   integer $oldversion Current (pre-upgrade) local db version timestamp
 * @return  boolean $result
 */
function xmldb_totara_completionimport_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2014030400) {
        // Add index to username column to improve query performance.

        $table = new xmldb_table('totara_compl_import_course');
        $index = new xmldb_index('compimpcou_username_ix', XMLDB_INDEX_NOTUNIQUE, array('username'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('totara_compl_import_cert');
        $index = new xmldb_index('compimpcer_username_ix', XMLDB_INDEX_NOTUNIQUE, array('username'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
    }

    return true;
}
