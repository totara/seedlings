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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 *
 * @package totara
 * @subpackage reportbuilder
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/googleapi.php');
require_once($CFG->dirroot . '/grade/export/fusion/fusionlib.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT); // Report id.
$sid = optional_param('sid', 0, PARAM_INT); // Report search id.

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/totara/reportbuilder/fusionexporter.php', array('id' => $id));

$report = new reportbuilder($id, null, false, $sid);
if (!$report->is_capable($id)) {
    print_error('nopermission', 'totara_reportbuilder');
}

$returnurl = new moodle_url('/totara/reportbuilder/fusionexporter.php', array('id' => $id, 'sid' => $sid));
$returnurl->param('sesskey', sesskey());

// Check the config.
$clientid = get_config('gradeexport_fusion', 'clientid');
$secret = get_config('gradeexport_fusion', 'secret');

$fusionrealm = 'https://www.googleapis.com/auth/fusiontables';
$googleoauth = new google_oauth($clientid, $secret, $returnurl, $fusionrealm);
if (!$googleoauth->is_logged_in()) {
    $url = $googleoauth->get_login_url();
    redirect($url, get_string('login', 'gradeexport_fusion'), 2);
}
$oauth = new fusion_grade_export_oauth_fusion($googleoauth);
$oauth->show_tables();

$errors = array();
$columns = $report->columns;
$shortname = $report->shortname;
$count = $report->get_filtered_count();
list($query, $params) = $report->build_query(false, true);
$query .= flexible_table::get_sort_for_table($shortname);

// Array of filters that have been applied for including in report where possible.
$restrictions = $report->get_restriction_descriptions();

$fields = array();
foreach ($columns as $column) {
    // Check that column should be included.
    if ($column->display_column(true)) {
        $type = 'STRING';
        $displayfunc = $column->get_displayfunc();
        if ($displayfunc === 'nice_date') {
            $type = 'DATETIME';
        } else if ($displayfunc == 'number') {
            $type = 'NUMBER';
        }
        $fields[clean_column_name($report->format_column_heading($column, true))] = $type;
    }
}

$tablename = preg_replace('/\s/u', '_', clean_filename(trim($shortname))).' '.date("Y-m-d H:i:s", strtotime('+0 days'));
if (!$oauth->table_exists($tablename)) {
    $errors = $oauth->create_table($tablename, $fields);
}

$tables = $oauth->show_tables();

// Switch off the timeout as this could easily be long running.
@set_time_limit(0);

// Process the output.
if ($records = $DB->get_recordset_sql($query, $params)) {
    $rows = array();
    foreach ($records as $record) {
        $rows[] = $report->src->process_data_row($record, 'fusion', $report);
    }
    // Add last rows.
    $errors = array_merge($errors, $oauth->insert_rows($tablename, $rows));
    $records->close();
}

if (empty($errors)) {
    // All done, redirect to show the table.

    $table = $oauth->table_by_name($tablename, true);
    $table_id = $table->tableId;
    redirect('https://www.google.com/fusiontables/DataSource?docid=' . $table_id);
    exit;
} else {
    $errormessages = array();

    foreach ($errors as $error) {
        $errormessages[] = $error->message;
    }

    $brtag = html_writer::empty_tag('br');
    $errordetails = implode($brtag, $errormessages);
    $url = $report->report_url();

    totara_set_notification(get_string('error:fusionexport', 'gradeexport_fusion', format_string($errordetails)), $url);
}


/**
 * Clean column of invalid characters for fusion tables
 *
 * @param string $name String to be cleaned
 * @return string
 */
function clean_column_name($name) {
    $name = preg_replace('/[^a-zA-Z0-9\_ ]/u', ' ', $name);
    $name = preg_replace('/\s+/u', ' ', $name);
    $name = preg_replace('/\s/u', '_', $name);
    return $name;
}
