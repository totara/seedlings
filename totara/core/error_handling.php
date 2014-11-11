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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once("{$CFG->dirroot}/version.php");

/**
 * Setup error/exception handlers for Totara
 *
 * @access  public
 * @return  void
 */
function totara_setup_error_handlers() {
    set_error_handler('totara_error_handler');
    set_exception_handler('totara_exception_handler');
}


/**
 * Totara error handler
 *
 * @access  public
 * @param   $errno      int     Error number
 * @param   $errstr     string  Error message
 * @param   $errfile    string  File error occured in (optional)
 * @param   $errline    int     Line in file error occured in (optional)
 * @param   $errcontext array   Array of variable in errors context (optional)
 * @return  bool
 */
function totara_error_handler($errno, $errstr, $errfile = '', $errline = 0, $errcontext = array()) {
    global $CFG, $DB, $TOTARA;
    $dbman = $DB->get_manager();

    // Do not record suppressed errors (or any others if error reporting disabled)
    if (!error_reporting()) {
        return false;
    }

    // Restore old error handler to prevent loop
    restore_error_handler();

    // Record count of inserted errors on this page
    static $insertcount;
    if (!isset($insertcount)) {
        $insertcount = 0;
    }

    // Only log error in database if Totara is installed and it would recorded at "DEVELOPER" level
    // and we have made less than 100 inserts so far this page view
    if (!empty($CFG->local_postinst_hasrun) && ($errno & DEBUG_DEVELOPER) && $insertcount < 100) {

        // Cache hashes of previous errors to prevent duplicates in table
        static $previous_errors = null;
        if (is_null($previous_errors)) {
            // Load hashes from db if table exists (in case of error during upgrade or install)
            $table = new xmldb_table('errorlog');
            if ($dbman->table_exists($table)) {
                $previous_errors = $DB->get_fieldset_select('errorlog', 'hash', '');
            }

            if (!$previous_errors) {
                $previous_errors = array();
            }
        }

        $description = serialize(array($errno, $errstr, $errfile, $errline));

        // Create "unique index" on error level, file and line number to prevent mass duplicates
        // Used to include error description but that thwarted duplicate detection due to things
        // like array indexes
        $hash = md5(serialize(array($errno, $errfile, $errline)));

        // Check if hash does not already exists in database
        if (!in_array($hash, $previous_errors)) {

            // Record error
            $error = new stdClass();
            $error->timeoccured = time();
            $error->version = $TOTARA->version;
            $error->build = $TOTARA->build;
            $error->details = $description;
            $error->hash = $hash;

            // Only if the table exists (in case of error during upgrade or install)
            $table = new xmldb_table('errorlog');
            if ($dbman->table_exists($table)) {
                $DB->insert_record('errorlog', $error);
                ++$insertcount;
            }
        }
    }

    // Respond to error appropriately
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        // Restore this error handler
        set_error_handler('totara_error_handler');
        return false;
    }

    switch ($errno) {
        case E_NOTICE:
        case E_USER_NOTICE:
            $errors = "Notice";
            break;
        case E_WARNING:
        case E_USER_WARNING:
            $errors = "Warning";
            break;
        case E_ERROR:
        case E_USER_ERROR:
            $errors = "Fatal Error";
            break;
        case E_STRICT:
            $errors = "Strict";
            break;
        default:
            $errors = "Unknown";
            break;
    }

    // Print/log message just as PHP normally would
    if (ini_get("display_errors")) {
        printf("<br />\n<b>%s</b>: %s in <b>%s</b> on line <b>%d</b><br /><br />\n", $errors, $errstr, $errfile, $errline);
    }

    if (ini_get('log_errors')) {
        error_log(sprintf("PHP %s: %s in %s on line %d", $errors, $errstr, $errfile, $errline));
    }

    // If a fatal error, exit
    if (in_array($errno, array(E_ERROR, E_USER_ERROR))) {
        exit(1);
    }

    // Restore this error handler
    set_error_handler('totara_error_handler');
    return true;
}


/**
 * Totara exception handler
 *
 * @access  public
 * @param   $exception  Exception
 * @return  bool
 */
function totara_exception_handler($exception) {
    // Restore default exception handler to prevent a loop
    restore_exception_handler();

    // Extract error details from exception
    $errno = E_ERROR;
    $errstr = get_class($exception).': ['.$exception->getCode().']: '.$exception->getMessage();
    $errfile = $exception->getFile();
    $errline = $exception->getLine();

    $result = totara_error_handler($errno, $errstr, $errfile, $errline);

    // Restore this exception handler
    set_exception_handler('totara_exception_handler');
    return $result;
}


/**
 * Cron task for clearing out older error log entries
 *
 * To prevent the table getting too big
 */
function totara_crop_error_log() {
    global $DB;

    // Get 100th from end errorlog id
    $errorlog_maxid = $DB->get_records_sql("
        SELECT id
          FROM {errorlog}
         ORDER BY id DESC
    ", 100, 1);

    $errorlog_maxid = $errorlog_maxid ? array_pop($errorlog_maxid) : false;

    // Crop errorlog table at 100 entries
    if ($errorlog_maxid) {
        $errorlog_sql = "
            DELETE FROM {errorlog}
             WHERE id <= ?
        ";
        mtrace('Cropping errorlog table at 100 entries');
        $DB->execute($errorlog_sql, array($errorlog_maxid->id));
    }

}
