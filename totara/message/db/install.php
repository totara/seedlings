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
 * @subpackage message
 */

// This file replaces:
//   * STATEMENTS section in db/install.xml
//   * lib.php/modulename_install() post installation hook
//   * partially defaults.php

function xmldb_totara_message_install() {
    global $DB;

    $dbman = $DB->get_manager();

    // T-9573 : add indexes to message_working processorid and unreadmessageid fields
    $table = new xmldb_table('message_working');
    $index = new xmldb_index('unreadmessageid', XMLDB_INDEX_NOTUNIQUE, array('unreadmessageid'));
    // Conditionally launch add index unreadmessageid
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }
    $index = new xmldb_index('processorid', XMLDB_INDEX_NOTUNIQUE, array('processorid'));
    // Conditionally launch add index processorid
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }

    return true;
}
