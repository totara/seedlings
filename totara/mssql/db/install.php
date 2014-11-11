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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_reportbuilder
 */

/**
 * This function is run when mssql support is first installed
 *
 * Add code here that should be run when the module is first installed
 */
function xmldb_totara_mssql_install() {
    global $DB, $CFG, $OUTPUT;

    // Install group_concat stored procedure to MSSQL server.
    if ($DB->get_dbfamily() == 'mssql') {
        // Check if assembly already exists.
        if (!$DB->get_record_sql('SELECT * FROM sys.assemblies WHERE name = ?',
                array('GroupConcat'))) {
            // Assembly not exists. Try to add automatically.
            $sqllist = require($CFG->dirroot . '/totara/mssql/db/mssqlgroupconcat.php');
            try {
                foreach ($sqllist['sql'] as $sql) {
                    $DB->change_database_structure($sql);
                }
            } catch (ddl_change_structure_exception $e) {
                // Fail. Give instructions to user with required SQL code to execute.
                $message = (isset($e->error) && $e->error != '') ? $e->error : $e->getMessage();
                echo $OUTPUT->notification(get_string('mssqlgroupconcatfail', 'totara_mssql',
                        $message));
                echo html_writer::tag('textarea', str_replace('%currentdb%', $CFG->dbname,
                        $sqllist['text']), array('class' => 'sqlsnippet'));

                // Return code is ignored by installer.
                die();
            }
        }
    }

    return true;
}

/**
 * Retry the install if it failed previously.
 */
function xmldb_totara_mssql_install_recovery() {
    xmldb_totara_mssql_install();
}

/**
 * Check that database user has enough permission for database upgrade
 * @param environment_results $result
 * @return environment_results
 */
function totara_mssql_environment_check(environment_results $result) {
    global $DB;
    $status = true;
    $result->info = 'mssql_permissions';
    if ($DB->get_dbfamily() == 'mssql') {

        if (!$DB->get_record_sql('SELECT * FROM sys.assemblies WHERE name = ?',
                array('GroupConcat'))) {
            $extraperm = array('CREATE ASSEMBLY' => 'DATABASE', 'CREATE AGGREGATE' => 'DATABASE',
                'ALTER SETTINGS' => 'SERVER');
            $needperm = array();
            foreach ($extraperm as $perm => $scope) {
                $sql = "SELECT permission_name FROM fn_my_permissions (NULL, ?)
                        WHERE permission_name = ?";
                $isperm = $DB->get_record_sql($sql, array($scope, $perm));
                if (!$isperm) {
                    $needperm[] = "$perm($scope)";
                }
            }
            if (count($needperm)) {
                $list = implode(', ', $needperm);
                $status = false;
                $result->setRestrictStr(array('mssqlinsufficientpermissions', 'admin', $list));
            }
        }
        $result->setStatus($status);
        return $result;
    }
    // Not mssql.
    return null;
}
