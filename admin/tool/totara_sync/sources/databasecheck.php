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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage totara_sync
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot.'/admin/tool/totara_sync/sources/databaselib.php');

$PAGE->set_context(context_system::instance());
require_login();

$dbtype = required_param('dbtype', PARAM_ALPHANUMEXT);
$dbhost = optional_param('dbhost', '', PARAM_ALPHANUMEXT);
$dbname = required_param('dbname', PARAM_ALPHANUMEXT);
$dbuser = required_param('dbuser', PARAM_ALPHANUMEXT);
$dbpass = optional_param('dbpass', '', PARAM_ALPHANUMEXT);

try {
   $connection = @setup_sync_DB($dbtype, $dbhost, $dbname, $dbuser, $dbpass);
} catch (Exception $e) {
    // Echo false to return a success or failure to Javascript, even if this
    // condition fails return false will still indicate true to JS so we echo
    // a value to use a check
    echo 'false';
    return false;
}

//Check that we can query the db
if ($connection->get_records_sql('SELECT 1')) {
    echo 'true';
    return true;
}

echo 'false';
return false;
