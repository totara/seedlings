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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage totara_sync
 */

defined('MOODLE_INTERNAL') || die;

define('TOTARA_SYNC_DBROWS', 10000);
define('FILE_ACCESS_DIRECTORY', 0);
define('FILE_ACCESS_UPLOAD', 1);
define('TOTARA_SYNC_LOGTYPE_MAX_NOTIFICATIONS', 50);

/**
 * Finds the run id of the latest sync run
 *
 * @return int latest runid
 */
function latest_runid() {
    global $DB;

    $runid = $DB->get_field_sql('SELECT MAX(runid) FROM {totara_sync_log}');

    if (!empty($runid)) {
        return $runid;
    } else {
        return 0;
    }
}

/**
 * Run the cron for syncing Totara elements with external sources
 *
 * This can be run separately from the main cron via run_cron.php
 *
 * @param boolean $forcerun force sync to run, ignoring configured schedule
 * @access public
 * @return void
 */
function tool_totara_sync_cron($forcerun=false) {
    global $CFG, $OUTPUT;

    if (!$forcerun) {
        // Check if the time is ripe for the sync to run.
        $config = get_config('totara_sync');
        if (empty($config->cronenable)) {
            return false;
        }
        if (!empty($config->nextcron) && $config->nextcron > time()) {
            // Sync should not be run yet.
            return false;
        } else {
            // Set time for next run and allow to proceed.
            require_once($CFG->dirroot . '/totara/core/lib/scheduler.php');
            $scheduler = new scheduler($config, array('nextevent' => 'nextcron'));
            $scheduler->next();
            set_config('nextcron', $scheduler->get_scheduled_time(), 'totara_sync');
        }
    }

    // First run through the sanity checks.
    $configured = true;

    $fileaccess = get_config('totara_sync', 'fileaccess');
    if ($fileaccess == FILE_ACCESS_DIRECTORY && !$filesdir = get_config('totara_sync', 'filesdir')) {
        $configured = false;
        echo $OUTPUT->notification(get_string('nofilesdir', 'tool_totara_sync'), 'notifyproblem');
    }
    // Check enabled sync element objects
    $elements = totara_sync_get_elements(true);
    if (empty($elements)) {
        $configured = false;
        echo $OUTPUT->notification(get_string('noenabledelements', 'tool_totara_sync'), 'notifyproblem');
    } else {
        foreach ($elements as $element) {
            $elname = $element->get_name();
            //check a source is enabled
            if (!$sourceclass = get_config('totara_sync', 'source_' . $elname)) {
                $configured = false;
                echo $OUTPUT->notification($elname . " " . get_string('sourcenotfound', 'tool_totara_sync'));
            }
            //check source has configs - note get_config returns an object
            if ($sourceclass) {
                $configs = get_config($sourceclass);
                $props = get_object_vars($configs);
                if(empty($props)) {
                    $configured = false;
                    echo $OUTPUT->notification($elname . " " . get_string('nosourceconfig', 'tool_totara_sync'), 'notifyproblem');
                }
            }
        }
    }

    if (!$configured) {
        echo $OUTPUT->notification(get_string('syncnotconfigured', 'tool_totara_sync'), 'notifyproblem');
        return false;
    }

    $status = true;
    foreach ($elements as $element) {
        try {
            if (!method_exists($element, 'sync')) {
                // Skip if no sync() method exists
                continue;
            }

            // Finally, start element syncing
            $status = $status && $element->sync();
        } catch (totara_sync_exception $e) {
            $msg = $e->getMessage();
            $msg .= !empty($e->debuginfo) ? " - {$e->debuginfo}" : '';
            totara_sync_log($e->tsync_element, $msg, $e->tsync_logtype, $e->tsync_action);
            $element->get_source()->drop_table();
            continue;
        } catch (dml_exception $e) {
            totara_sync_log($element->get_name(), $e->error, 'error', 'unknown');
            $element->get_source()->drop_table();
            continue;
        } catch (Exception $e) {
            totara_sync_log($element->get_name(), $e->getMessage(), 'error', 'unknown');
            $element->get_source()->drop_table();
            continue;
        }

        $element->get_source()->drop_table();
    }
    totara_sync_notify();

    return $status;
}

/**
 * Method for adding sync log messages
 *
 * @param string $element element name
 * @param string $info the log message
 * @param string $type the log message type
 * @param string $action the action which caused the log message
 * @param boolean $showmessage shows error messages on the main page when running sync if it is true
 */
function totara_sync_log($element, $info, $type='info', $action='', $showmessage=true) {
    global $DB, $OUTPUT;

    static $sync_runid = null;

    if ($sync_runid == null) {
        $sync_runid = latest_runid() + 1;
    }

    $todb = new stdClass;
    $todb->element = $element;
    $todb->logtype = $type;
    $todb->action = $action;
    $todb->info = $info;
    $todb->time = time();
    $todb->runid = $sync_runid;

    if ($showmessage && ($type == 'warn' || $type == 'error')) {
        $typestr = get_string($type, 'tool_totara_sync');
        $class = $type == 'warn' ? 'notifynotice' : 'notifyproblem';
        echo $OUTPUT->notification($typestr . ':' . $element . ' - ' . $info, $class);
    }

    return $DB->insert_record('totara_sync_log', $todb);
}

/**
 * Get the sync file paths for all elements
 *
 * @return array of filepaths
 */
function totara_sync_get_element_files() {
    global $CFG;

    // Get all available sync element files
    $edir = $CFG->dirroot.'/admin/tool/totara_sync/elements/';
    $pattern = '/(.*?)\.php$/';
    $files = preg_grep($pattern, scandir($edir));
    $filepaths = array();
    foreach ($files as $key => $val) {
        $filepaths[] = $edir . $val;
    }
    return $filepaths;
}

/**
 * Get sync elements
 *
 * @param boolean $onlyenabled only return enabled elements
 *
 * @return array of element objects
 */
function totara_sync_get_elements($onlyenabled=false) {
    global $CFG;

    $efiles = totara_sync_get_element_files();

    $elements = array();
    foreach ($efiles as $filepath) {
        $element = basename($filepath, '.php');
        if ($onlyenabled) {
            if (!get_config('totara_sync', 'element_'.$element.'_enabled')) {
                continue;
            }
        }

        require_once($filepath);

        $elementclass = 'totara_sync_element_'.$element;
        if (!class_exists($elementclass)) {
            // Skip if the class does not exist
            continue;
        }

        $elements[$element] = new $elementclass;
    }

    return $elements;
}

/**
 * Get a specified element object
 *
 * @param string $element the element name
 *
 * @return stdClass the element object
 */
function totara_sync_get_element($element) {
    $elements = totara_sync_get_elements();

    if (!in_array($element, array_keys($elements))) {
        return false;
    }

    return $elements[$element];
}

function totara_sync_make_dirs($dirpath) {
    global $CFG;

    $dirarray = explode('/', $dirpath);
    $currdir = '';
    foreach ($dirarray as $dir) {
        $currdir = $currdir.$dir.'/';
        if (!file_exists($currdir)) {
            if (!mkdir($currdir, $CFG->directorypermissions)) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Cleans the values and returns as an array
 *
 * @param array $fields
 * @param string $encoding the encoding type that string is being converted from to utf-8
 * @return array $fields
 */
function totara_sync_clean_fields($fields, $encoding) {
    if ($encoding !== 'UTF-8') {
        foreach ($fields as $key => $value) {
            $value = textlib::convert(trim($value), $encoding, 'UTF-8');
            $fields[$key] = clean_param($value, PARAM_TEXT);
        }
    } else {
        $fields = array_map('trim', $fields);
        $fields = clean_param_array($fields, PARAM_TEXT);
    }
    return $fields;
}

/**
 * Perform bulk inserts into specified table
 *
 * @param string $table table name
 * @param array $datarows an array of row arrays
 *
 * @return boolean
 */
function totara_sync_bulk_insert($table, $datarows) {
    global $CFG, $DB;

    if (empty($datarows)) {
        return true;
    }

    $length = 1000;
    $chunked_datarows = array_chunk($datarows, $length);

    unset($datarows);

    foreach ($chunked_datarows as $key=>$chunk) {
        $sql = "INSERT INTO {{$table}} ("
            .implode(',',array_keys($chunk[0]))
            . ') VALUES ';

        $all_values= array();
        $sql_rows = array();
        foreach ($chunk as $row){
            $sql_rows[]= "(". str_repeat("?,", (count(array_keys($chunk[0])) - 1)) ."?)";
            $all_values = array_merge($all_values, array_values($row));
        }
        unset($row);
        $sql .= implode(',', $sql_rows);
        unset ($sql_rows);

        // Execute insert SQL
        if (!$DB->execute($sql, array_values($all_values))) {
            return false;
        }
        unset ($sql);
        unset($all_values);
        unset($chunked_datarows[$key]);
        unset($chunk);
    }

    unset($chunked_datarows);

    return true;
}

/**
 * Notify admin users or admin user of any sync failures since last notification.
 *
 * Note that this function must be only executed from the cron script
 *
 * @return bool true if executed, false if not
 */
function totara_sync_notify() {
    global $CFG, $DB;

    $now = time();
    $dateformat = get_string('strftimedateseconds', 'langconfig');
    $notifyemails = get_config('totara_sync', 'notifymailto');
    $notifyemails = !empty($notifyemails) ? explode(',', $notifyemails) : array();
    $notifytypes = get_config('totara_sync', 'notifytypes');
    $notifytypes = !empty($notifytypes) ? explode(',', $notifytypes) : array();

    if (empty($notifyemails) || empty($notifytypes)) {
        set_config('lastnotify', $now, 'totara_sync');
        return false;
    }

    // The same users as login failures.
    if (!$lastnotify = get_config('totara_sync', 'lastnotify')) {
        $lastnotify = 0;
    }

    // Get most recent log messages of type.
    list($sqlin, $params) = $DB->get_in_or_equal($notifytypes);
    $params = array_merge($params, array($lastnotify));
    $logitems = $DB->get_records_select('totara_sync_log', "logtype {$sqlin} AND time > ?", $params,
                                        'time DESC', '*', 0, TOTARA_SYNC_LOGTYPE_MAX_NOTIFICATIONS);
    if (!$logitems) {
        // Nothing to report.
        return true;
    }

    // Build email message.
    $logcount = count($logitems);
    $sitename = get_site();
    $sitename = format_string($sitename->fullname);
    $notifytypes_str = array_map(create_function('$type', "return get_string(\$type.'plural', 'tool_totara_sync');"), $notifytypes);
    $subject = get_string('notifysubject', 'tool_totara_sync', $sitename);

    $a = new stdClass();
    $a->logtypes = implode(', ', $notifytypes_str);
    $a->count = $logcount;
    $a->since = date_format_string($lastnotify, $dateformat);
    $message = get_string('notifymessagestart', 'tool_totara_sync', $a);
    $message .= "\n\n";
    foreach ($logitems as $logentry) {
        $logentry->time = date_format_string($logentry->time, $dateformat);
        $logentry->logtype = get_string($logentry->logtype, 'tool_totara_sync');
        $message .= get_string('notifymessage', 'tool_totara_sync', $logentry) . "\n\n";
    }
    $message .= "\n" . get_string('viewsyncloghere', 'tool_totara_sync',
            $CFG->wwwroot . '/admin/tool/totara_sync/admin/synclog.php');

    // Send emails.
    mtrace("\n{$logcount} relevant totara sync log messages since " .
            date_format_string($lastnotify, $dateformat)) . ". Sending notifications...";
    $supportuser = core_user::get_support_user();
    foreach ($notifyemails as $emailaddress) {
        $userto = \totara_core\totara_user::get_external_user(trim($emailaddress));
        email_to_user($userto, $supportuser, $subject, $message);
    }

    // Update lastnotify with current time.
    set_config('lastnotify', $now, 'totara_sync');

    return true;
}

class totara_sync_exception extends moodle_exception {
    public $tsync_element;
    public $tsync_action;
    public $tsync_logtype;

    /**
     * Constructor
     * @param string $errorcode The name of the string from error.php to print
     * @param object $a Extra words and phrases that might be required in the error string
     * @param string $debuginfo optional debugging information
     * @param string $logtype optional totara sync log type
     */
    public function __construct($element, $action, $errorcode, $a = null, $debuginfo = null, $logtype = 'error') {
        $this->tsync_element = $element;
        $this->tsync_action = $action;
        $this->tsync_logtype = $logtype;

        parent::__construct($errorcode, 'tool_totara_sync', $link='', $a, $debuginfo);
    }
}
