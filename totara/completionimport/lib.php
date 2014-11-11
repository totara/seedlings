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
 * @author Russell England <russell.england@catalyst-eu.net>
 * @package totara
 * @subpackage completionimpot
 */

defined('MOODLE_INTERNAL') || die;

// TCI = Totara Completion Import.
define('TCI_SOURCE_EXTERNAL', 0);
define('TCI_SOURCE_UPLOAD', 1);

define('TCI_CSV_DELIMITER', '"'); // Default for fgetcsv() although the naming in fgetcsv is the wrong way around IMHO.
define('TCI_CSV_SEPARATOR', 'comma'); // Default for fgetcsv() although the naming in fgetcsv is the wrong way around IMHO.
define('TCI_CSV_DATE_FORMAT', 'Y-m-d'); // Default date format.
define('TCI_CSV_ENCODING', 'UTF8'); // Default file encoding.

/**
 * Returns a 3 character prefix for a temporary file name
 *
 * @param string $importname
 * @return string 3 character prefix
 */
function get_tempprefix($importname) {
    $prefix = array(
        'course' => 'cou',
        'certification'  => 'cer'
    );
    return $prefix[$importname];
}

/**
 * Returns an array of column names for the specific import
 *
 * @param string $importname
 * @return array column names
 */
function get_columnnames($importname) {
    $columns = array();
    $columns['course'] = array(
        'username',
        'courseshortname',
        'courseidnumber',
        'completiondate',
        'grade'
    );
    $columns['certification'] = array(
        'username',
        'certificationshortname',
        'certificationidnumber',
        'completiondate'
    );
    return $columns[$importname];
}

/**
 * Returns the import table name for a specific import
 *
 * @param string $importname
 * @return string tablename
 */
function get_tablename($importname) {
    $tablenames = array(
        'course' => 'totara_compl_import_course',
        'certification' => 'totara_compl_import_cert'
    );
    return $tablenames[$importname];
}

/**
 * Returns the SQL to compare the shortname if not empty or idnumber if shortname is empty
 * @global object $DB
 * @param string $relatedtable eg: "{course}" if a table or 'c' if its an alias
 * @param string $importtable eg: "{totara_compl_import_course}" or "i"
 * @param string $shortnamefield courseshortname or certificationshortname
 * @param string $idnumberfield courseidnumber or certificationidnumber
 * @return string Where condition
 */
function get_shortnameoridnumber($relatedtable, $importtable, $shortnamefield, $idnumberfield) {
    global $DB;

    $notemptyshortname = $DB->sql_isnotempty($importtable, "{$importtable}.{$shortnamefield}", true, false);
    $notemptyidnumber = $DB->sql_isnotempty($importtable, "{$importtable}.{$idnumberfield}", true, false);
    $emptyshortname = $DB->sql_isempty($importtable, "{$importtable}.{$shortnamefield}", true, false);
    $emptyidnumber = $DB->sql_isempty($importtable, "{$importtable}.{$idnumberfield}", true, false);
    $shortnameoridnumber = "
        ({$notemptyshortname} AND {$notemptyidnumber}
            AND {$relatedtable}.shortname = {$importtable}.{$shortnamefield}
            AND {$relatedtable}.idnumber = {$importtable}.{$idnumberfield})
        OR ({$notemptyshortname} AND {$emptyidnumber}
            AND {$relatedtable}.shortname = {$importtable}.{$shortnamefield})
        OR ({$emptyshortname} AND {$notemptyidnumber}
            AND {$relatedtable}.idnumber = {$importtable}.{$idnumberfield})
        ";
    return $shortnameoridnumber;
}

/**
 * Returns the standard filter for the import table and related parameters
 *
 * @global object $USER
 * @param int $importtime time() of the import
 * @param string $alias alias to use
 * @return array array($sql, $params)
 */
function get_importsqlwhere($importtime, $alias = 'i.') {
    global $USER;
    $sql = "WHERE {$alias}importuserid = :userid
            AND {$alias}timecreated = :timecreated
            AND {$alias}importerror = 0
            AND {$alias}timeupdated = 0
            AND {$alias}importevidence = 0 ";
    $params = array('userid' => $USER->id, 'timecreated' => $importtime);
    return array($sql, $params);
}

/**
 * Gets the config value, sets the value if it doesn't exist
 *
 * @param string $pluginname name of plugin
 * @param string $configname name of config value
 * @param mixed $default config value
 * @return mixed either the current config value or the default
 */
function get_default_config($pluginname, $configname, $default) {
    $configvalue = get_config($pluginname, $configname);
    if ($configvalue == null) {
        $configvalue = $default;
        set_config($configname, $configvalue, $pluginname);
    }
    return $configvalue;
}

/**
 * Checks the fields in the first line of the csv file, for required columns or unknown columns
 *
 * @global object $CFG
 * @param string $filename name of file to open
 * @param string $importname name of import
 * @param array $columnnames column names to check
 * @return array of errors, blank if no errors
 */
function check_fields_exist($filename, $importname) {
    global $CFG;

    require_once($CFG->libdir . '/csvlib.class.php');

    $errors = array();
    $pluginname = 'totara_completionimport_' . $importname;
    $columnnames = get_columnnames($importname);

    $csvdelimiter = get_default_config($pluginname, 'csvdelimiter', TCI_CSV_DELIMITER);
    $csvseparator = csv_import_reader::get_delimiter(get_default_config($pluginname, 'csvseparator', TCI_CSV_SEPARATOR));
    $csvencoding = get_default_config($pluginname, 'csvencoding', TCI_CSV_ENCODING);

    if (!is_readable($filename)) {
        $errors[] = get_string('unreadablefile', 'totara_completionimport', $filename);
    } else if (!$handle = fopen($filename, 'r')) {
        $errors[] = get_string('erroropeningfile', 'totara_completionimport', $filename);
    } else {
        // Read the first line.
        $csvfields = fgetcsv($handle, 0, $csvseparator, $csvdelimiter);
        if (empty($csvfields)) {
            $errors[] = get_string('emptyfile', 'totara_completionimport', $filename);
        } else {
            // Clean and convert to UTF-8 and check for unknown field.
            foreach ($csvfields as $key => $value) {
                $csvfields[$key] = clean_param(trim($value), PARAM_TEXT);
                $csvfields[$key] = textlib::convert($value, $csvencoding, 'utf-8');
                if (!in_array($value, $columnnames)) {
                    $field = new stdClass();
                    $field->filename = $filename;
                    $field->columnname = $value;
                    $errors[] = get_string('unknownfield', 'totara_completionimport', $field);
                }
            }

            // Check for required fields.
            foreach ($columnnames as $columnname) {
                if (!in_array($columnname, $csvfields)) {
                    $field = new stdClass();
                    $field->filename = $filename;
                    $field->columnname = $columnname;
                    $errors[] = get_string('missingfield', 'totara_completionimport', $field);
                }
            }
        }
        fclose($handle);
    }
    return $errors;
}

/**
 * Imports csv data into the relevant import table
 *
 * Doesn't do any sanity checking of data at the stage, its a simple import
 *
 * @global object $CFG
 * @global object $DB
 * @param string $tempfilename full name of csv file to open
 * @param string $importname name of import
 * @param int $importtime time of run
 */
function import_csv($tempfilename, $importname, $importtime) {
    global $CFG, $DB, $USER;

    require_once($CFG->libdir . '/csvlib.class.php');
    require_once($CFG->dirroot . '/totara/completionimport/csv_iterator.php');

    $tablename = get_tablename($importname);
    $columnnames = get_columnnames($importname);

    $pluginname = 'totara_completionimport_' . $importname;
    $csvdelimiter = get_default_config($pluginname, 'csvdelimiter', TCI_CSV_DELIMITER);
    $csvseparator = csv_import_reader::get_delimiter(get_default_config($pluginname, 'csvseparator', TCI_CSV_SEPARATOR));
    $csvencoding = get_default_config($pluginname, 'csvencoding', TCI_CSV_ENCODING);

    // Assume that file checks and column name checks have already been done.
    $importcsv = new csv_iterator($tempfilename, $csvseparator, $csvdelimiter, $csvencoding, $columnnames, $importtime);
    $DB->insert_records_via_batch($tablename, $importcsv);

    // Remove any empty rows at the end of the import file.
    // But leave empty rows in the middle for error reporting.
    // Here mainly because of a PHP bug in csv_iterator.
    // But also to remove any unneccessary empty lines at the end of the csv file.
    $sql = "SELECT id, rownumber
            FROM {{$tablename}}
            WHERE importuserid = :userid
            AND timecreated = :timecreated
            AND " . $DB->sql_compare_text('importerrormsg') . " = :importerrormsg
            ORDER BY id DESC";
    $params = array('userid' => $USER->id, 'timecreated' => $importtime, 'importerrormsg' => 'emptyrow;');
    $emptyrows = $DB->get_records_sql($sql, $params);
    $rownumber = 0;
    $deleteids = array();
    foreach ($emptyrows as $emptyrow) {
        if ($rownumber == 0) {
            $rownumber = $emptyrow->rownumber;
        } else if (--$rownumber != $emptyrow->rownumber) {
            // Not at the end any more.
            break;
        }
        $deleteids[] = $emptyrow->id;
    }

    if (!empty($deleteids)) {
        list($deletewhere, $deleteparams) = $DB->get_in_or_equal($deleteids);
        $DB->delete_records_select($tablename, 'id ' . $deletewhere, $deleteparams);
    }
}

/**
 * Sanity check on data imported from the csv file
 *
 * @global object $DB
 * @param string $importname name of import
 * @param int $importtime time of this import
 */
function import_data_checks($importname, $importtime) {
    global $DB, $CFG;

    list($sqlwhere, $stdparams) = get_importsqlwhere($importtime, '');

    $shortnamefield = $importname . 'shortname';
    $idnumberfield = $importname . 'idnumber';

    $tablename = get_tablename($importname);
    $columnnames = get_columnnames($importname);
    $pluginname = 'totara_completionimport_' . $importname;
    $csvdateformat = get_default_config($pluginname, 'csvdateformat', TCI_CSV_DATE_FORMAT);

    if (in_array('username', $columnnames)) {
        // Blank User names.
        $params = array_merge($stdparams, array('errorstring' => 'blankusername;'));
        $sql = "UPDATE {{$tablename}}
                SET importerrormsg = " . $DB->sql_concat('importerrormsg', ':errorstring') . "
                {$sqlwhere}
                AND " . $DB->sql_isempty($tablename, 'username', true, false);
        $DB->execute($sql, $params);

        // Missing User names.
        // Reference to mnethostid in subquery allows us to benefit from an index on user table.
        // This tool does not support importing historic records from networked sites
        // so local site id alway used.
        $params = array_merge($stdparams,
            array('errorstring' => 'usernamenotfound;', 'mnetlocalhostid' => $CFG->mnet_localhost_id));
        $sql = "UPDATE {{$tablename}}
                SET importerrormsg = " . $DB->sql_concat('importerrormsg', ':errorstring') . "
                {$sqlwhere}
                AND " . $DB->sql_isnotempty($tablename, 'username', true, false) . "
                AND NOT EXISTS (SELECT {user}.id FROM {user}
                WHERE {user}.username = {{$tablename}}.username AND {user}.mnethostid = :mnetlocalhostid)";
        $DB->execute($sql, $params);
    }

    if (in_array('completiondate', $columnnames)) {
        // Blank completion date.
        $params = array_merge($stdparams, array('errorstring' => 'blankcompletiondate;'));
        $sql = "UPDATE {{$tablename}}
                SET importerrormsg = " . $DB->sql_concat('importerrormsg', ':errorstring') . "
                {$sqlwhere}
                AND " . $DB->sql_isempty($tablename, 'completiondate', true, false);
        $DB->execute($sql, $params);

        // Check for invalid completion date.
        if (!empty($csvdateformat)) {
            // There is a date format so check it.
            $sql = "SELECT id, completiondate
                    FROM {{$tablename}}
                    {$sqlwhere}
                    AND " . $DB->sql_isnotempty($tablename, 'completiondate', true, false);

            $timecompleteds = $DB->get_recordset_sql($sql, $stdparams);
            if ($timecompleteds->valid()) {
                foreach ($timecompleteds as $timecompleted) {
                    if (!totara_date_parse_from_format($csvdateformat, $timecompleted->completiondate)) {
                        $sql = "UPDATE {{$tablename}}
                                SET importerrormsg = " . $DB->sql_concat('importerrormsg', ':errorstring') . "
                                WHERE id = :importid";
                        $DB->execute($sql, array('errorstring' => 'invalidcompletiondate;', 'importid' => $timecompleted->id));
                    }
                }
            }
            $timecompleteds->close();
        }
    }

    if (in_array('grade', $columnnames)) {
        // Assuming the grade is mandatory, so check for blank grade.
        $params = array_merge($stdparams, array('errorstring' => 'blankgrade;'));
        $sql = "UPDATE {{$tablename}}
                SET importerrormsg = " . $DB->sql_concat('importerrormsg', ':errorstring') . "
                {$sqlwhere}
                AND " . $DB->sql_isempty($tablename, 'grade', true, false);
        $DB->execute($sql, $params);
    }

    // Duplicates.
    if (in_array($importname . 'username', $columnnames) && in_array($shortnamefield, $columnnames)
            && in_array($idnumberfield, $columnnames)) {
        $sql = "SELECT " . $DB->sql_concat('username', $shortnamefield, $idnumberfield) . " AS uniqueid,
                    username,
                    {$shortnamefield},
                    {$idnumberfield},
                    COUNT(*) AS count
                FROM {{$tablename}}
                {$sqlwhere}
                GROUP BY username, {$shortnamefield}, {$idnumberfield}
                HAVING COUNT(*) > 1";
        $duplicategroups = $DB->get_recordset_sql($sql, $stdparams);
        if ($duplicategroups->valid()) {
            foreach ($duplicategroups as $duplicategroup) {
                // Keep the first record, consider the others as duplicates.
                $sql = "SELECT id
                        FROM {{$tablename}}
                        {$sqlwhere}
                        AND username = :username
                        AND {$shortnamefield} = :shortname
                        AND {$idnumberfield} = :idnumber
                        ORDER BY id";
                $params = array(
                        'username' => $duplicategroup->username,
                        'shortname' => $duplicategroup->$shortnamefield,
                        'idnumber' => $duplicategroup->$idnumberfield
                    );
                $params = array_merge($stdparams, $params);
                $keepid = $DB->get_field_sql($sql, $params, IGNORE_MULTIPLE);

                $params['keepid'] = $keepid;
                $params['errorstring'] = 'duplicate;';
                $sql = "UPDATE {{$tablename}}
                        SET importerrormsg = " . $DB->sql_concat('importerrormsg', ':errorstring') . "
                        {$sqlwhere}
                        AND id <> :keepid
                        AND username = :username
                        AND {$shortnamefield} = :shortname
                        AND {$idnumberfield} = :idnumber";
                $DB->execute($sql, $params);
            }
        }
        $duplicategroups->close();
    }

    // Unique ID numbers.
    if (in_array($shortnamefield, $columnnames) && in_array($idnumberfield, $columnnames)) {
        // I 'think' the count has to be included in the select even though we only need having count().
        $notemptyidnumber = $DB->sql_isnotempty($tablename, "{{$tablename}}.{$idnumberfield}", true, false);
        $sql = "SELECT u.{$idnumberfield}, COUNT(*) AS shortnamecount
                FROM (SELECT DISTINCT {$shortnamefield}, {$idnumberfield}
                        FROM {{$tablename}}
                        {$sqlwhere}
                        AND {$notemptyidnumber}) u
                GROUP BY u.{$idnumberfield}
                HAVING COUNT(*) > 1";
        $idnumbers = $DB->get_records_sql($sql, $stdparams);
        $idnumberlist = array_keys($idnumbers);

        if (count($idnumberlist)) {
            list($idsql, $idparams) = $DB->get_in_or_equal($idnumberlist, SQL_PARAMS_NAMED, 'param');

            $params = array_merge($stdparams, $idparams);
            $params['errorstring'] = 'duplicateidnumber;';
            $sql = "UPDATE {{$tablename}}
                    SET importerrormsg = " . $DB->sql_concat('importerrormsg', ':errorstring') . "
                    {$sqlwhere}
                    AND {$idnumberfield} {$idsql}";
            $DB->execute($sql, $params);
        }
    }

    if (in_array($shortnamefield, $columnnames) && in_array($idnumberfield, $columnnames)) {
        // Blank shortname and id number.
        $params = array_merge($stdparams, array('errorstring' => $importname . 'blankrefs;'));
        $sql = "UPDATE {{$tablename}}
                SET importerrormsg = " . $DB->sql_concat('importerrormsg', ':errorstring') . "
                {$sqlwhere}
                AND " . $DB->sql_isempty($tablename, $shortnamefield, true, false) . "
                AND " . $DB->sql_isempty($tablename, $idnumberfield, true, false);
        $DB->execute($sql, $params);

        if (in_array($importname, array('course'))) {
            // Course exists but there is no manual enrol record.
            $params = array('enrolname' => 'manual', 'errorstring' => 'nomanualenrol;');
            $params = array_merge($stdparams, $params);
            $shortnameoridnumber = get_shortnameoridnumber("{course}", "{{$tablename}}", $shortnamefield, $idnumberfield);
            $sql = "UPDATE {{$tablename}}
                    SET importerrormsg = " . $DB->sql_concat('importerrormsg', ':errorstring') . "
                    {$sqlwhere}
                    AND EXISTS (SELECT {course}.id
                                FROM {course}
                                WHERE {$shortnameoridnumber})
                    AND NOT EXISTS (SELECT {enrol}.id
                                FROM {enrol}
                                JOIN {course} ON {course}.id = {enrol}.courseid
                                WHERE {enrol}.enrol = :enrolname
                                AND {$shortnameoridnumber})";
            $DB->execute($sql, $params);
        }
    }

    // Set import error so we ignore any records that have an error message from above.
    $params = array_merge($stdparams, array('importerror' => 1));
    $sql = "UPDATE {{$tablename}}
            SET importerror = :importerror
            {$sqlwhere}
            AND " . $DB->sql_isnotempty($tablename, 'importerrormsg', true, true); // Note text = true.
    $DB->execute($sql, $params);
}

/**
 * Generic function for creating evidence from mismatched courses / certifications.
 *
 * @global object $DB
 * @global object $USER
 * @param string $importname name of import
 * @param int $importtime time of import
 */
function create_evidence($importname, $importtime) {
    global $DB;

    list($sqlwhere, $params) = get_importsqlwhere($importtime);

    $tablename = get_tablename($importname);
    $shortnamefield = $importname . 'shortname';
    $idnumberfield = $importname . 'idnumber';

    if ($importname == 'course') {
        // Add any missing courses to other training (evidence).
        $shortnameoridnumber = get_shortnameoridnumber('c', 'i', $shortnamefield, $idnumberfield);
        $sql = "SELECT i.id as importid, u.id userid, i.{$shortnamefield}, i.{$idnumberfield}, i.completiondate, i.grade
                FROM {{$tablename}} i
                JOIN {user} u ON u.username = i.username
                {$sqlwhere}
                  AND NOT EXISTS (SELECT c.id
                                FROM {course} c
                                WHERE {$shortnameoridnumber})";
    } else if ($importname == 'certification') {
        // Add any missing certifications to other training (evidence).
        $shortnameoridnumber = get_shortnameoridnumber('p', 'i', $shortnamefield, $idnumberfield);
        $sql = "SELECT i.id as importid, u.id userid, i.{$shortnamefield},  i.{$idnumberfield}, i.completiondate
                FROM {{$tablename}} i
                JOIN {user} u ON u.username = i.username
                LEFT JOIN {prog} p ON {$shortnameoridnumber}
                    AND p.certifid IS NOT NULL
                {$sqlwhere}
                AND p.id IS NULL";
    }

    $extraparams = array();
    $pluginname = 'totara_completionimport_' . $importname;
    // Note the order of these must match the order of parameters in create_evidence_item().
    $extraparams['evidencetype'] = get_default_config($pluginname, 'evidencetype', null);
    $extraparams['csvdateformat'] = get_default_config($pluginname, 'csvdateformat', TCI_CSV_DATE_FORMAT);
    $extraparams['tablename'] = $tablename;
    $extraparams['shortnamefield'] = $shortnamefield;
    $extraparams['idnumberfield'] = $idnumberfield;
    $extraparams['importname'] = $importname;

    $evidences = $DB->get_recordset_sql($sql, $params);
    $DB->insert_records_via_batch('dp_plan_evidence', $evidences, 'create_evidence_item', $extraparams);
    $evidences->close();
}

/**
 * Processor for insert batch iterator
 *
 * @global object $USER
 * @global object $DB
 * @param object $item record object
 * @param int $evidencetype default evidence type
 * @param string $csvdateformat csv date format
 * @param string $tablename name of import table
 * @param string $shortnamefield name of short name field, either certificationshortname or courseshortname
 * @param string $idnumberfield name of id number, either certificationidnumber or courseidnumber
 * @return object $data record to insert
 */
function create_evidence_item($item, $evidencetype, $csvdateformat, $tablename, $shortnamefield, $idnumberfield, $importname) {
    global $USER, $DB;

    $timecompleted = null;
    $timestamp = totara_date_parse_from_format($csvdateformat, $item->completiondate);
    if (!empty($timestamp)) {
        $timecompleted = $timestamp;
    }

    $itemname = '';
    if (!empty($item->$shortnamefield)) {
        $itemname = get_string('evidence_shortname_' . $importname, 'totara_completionimport', $item->$shortnamefield);
    } else if (!empty($item->$idnumberfield)) {
        $itemname = get_string('evidence_idnumber_' . $importname, 'totara_completionimport', $item->$idnumberfield);
    }

    $description = '';
    foreach ($item as $field => $value) {
        if (!in_array($field, array('userid'))) {
            $description .= html_writer::tag('p', get_string('evidence_' . $field, 'totara_completionimport', $value));
        }
    }

    $data = new stdClass();
    $data->name = $itemname;
    $data->description = $description;
    $data->datecompleted = $timecompleted;
    $data->evidencetypeid = $evidencetype;
    $data->timemodified = time();
    $data->userid = $item->userid;
    $data->timecreated = $data->timemodified;
    $data->usermodified = $USER->id;
    $data->readonly = 1;

    $update = new stdClass();
    $update->id = $item->importid;
    $update->timeupdated = time();
    $update->importevidence = 1;
    $DB->update_record($tablename, $update, true);

    return $data;
}

/**
 * Import the course completion data
 *
 * 1. Gets records from the import table that have no errors or haven't gone to evidence
 * 2. Bulk enrol users - used enrol_cohort_sync() in /enrol/cohort/locallib.php as a reference
 * 3. Course completion stuff copied from process_course_completion_crit_compl()
 *    and process_course_completions() both in /backup/moodle2/restore_stepslib.php
 * @global object $DB
 * @global object $CFG
 * @param string $importname name of import
 * @param int $importtime time of import
 * @return array
 */
function import_course($importname, $importtime) {
    global $DB, $CFG;

    require_once($CFG->libdir . '/enrollib.php'); // Used for enroling users on courses.

    $errors = array();
    $updateids = array();
    $users = array();
    $enrolledusers = array();
    $completions = array();
    $stats = array();
    $deletedcompletions = array();
    $completion_history = array();
    $historicalduplicate = array();
    $historicalrecordindb = array();

    $pluginname = 'totara_completionimport_' . $importname;
    $csvdateformat = get_default_config($pluginname, 'csvdateformat', TCI_CSV_DATE_FORMAT);
    $overridecurrentcompletion = get_default_config($pluginname, 'overrideactive' . $importname, false);

    list($sqlwhere, $params) = get_importsqlwhere($importtime);
    $params['enrolname'] = 'manual';

    $tablename = get_tablename($importname);
    $shortnameoridnumber = get_shortnameoridnumber('c', 'i', 'courseshortname', 'courseidnumber');
    $sql = "SELECT i.id as importid,
                    i.completiondate,
                    i.grade,
                    c.id as courseid,
                    u.id as userid,
                    e.id as enrolid,
                    ue.id as userenrolid,
                    ue.status as userenrolstatus,
                    cc.id as coursecompletionid,
                    cc.timestarted,
                    cc.timeenrolled,
                    cc.timecompleted as currenttimecompleted
            FROM {{$tablename}} i
            JOIN {user} u ON u.username = i.username
            JOIN {course} c ON {$shortnameoridnumber}
            JOIN {enrol} e ON e.courseid = c.id AND e.enrol = :enrolname
            LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = u.id)
            LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
            {$sqlwhere}
            ORDER BY courseid, userid, completiondate desc, grade desc";

    $courses = $DB->get_recordset_sql($sql, $params);
    if ($courses->valid()) {
        $plugin = enrol_get_plugin('manual');
        $timestart = $importtime;
        $timeend = 0;
        $enrolcount = 1;
        $enrolid = 0;
        $currentuser = 0;
        $currentcourse = 0;

        foreach ($courses as $course) {
            if (empty($enrolid) || ($enrolid != $course->enrolid) || (($enrolcount % BATCH_INSERT_MAX_ROW_COUNT) == 0)) {
                // Delete any existing course completions we are overriding.
                if (!empty($deletedcompletions)) {
                    $DB->delete_records_list('course_completions', 'id', $deletedcompletions);
                    $deletedcompletions = array();
                }

                if (!empty($completions)) {
                    // Batch import completions.
                    $DB->insert_records_via_batch('course_completions', $completions);
                    $completions = array();
                }

                if (!empty($stats)) {
                    // Batch import block_totara_stats.
                    $DB->insert_records_via_batch('block_totara_stats', $stats);
                    $stats = array();
                }

                if (!empty($completion_history)) {
                    // Batch import completions.
                    $DB->insert_records_via_batch('course_completion_history', $completion_history);
                    $completion_history = array();
                }

                // New enrol record or reached the next batch insert.
                if (!empty($users)) {
                    // Batch enrol users.
                    $instance = $DB->get_record('enrol', array('id' => $enrolid));
                    $plugin->enrol_user_bulk($instance, $users, $instance->roleid, $timestart, $timeend);
                    $enrolcount = 0;
                    $users = array();
                }

                if (!empty($updateids)) {
                    // Update the timeupdated.
                    list($insql, $params) = $DB->get_in_or_equal($updateids, SQL_PARAMS_NAMED, 'param');
                    $params['timeupdated'] = $importtime;
                    $sql = "UPDATE {{$tablename}}
                            SET timeupdated = :timeupdated
                            WHERE id {$insql}";
                    $DB->execute($sql, $params);
                    unset($updateids);
                    $updateids = array();
                }

                if (!empty($historicalduplicate)) {
                    // Update records as duplicated.
                    update_errors_import($historicalduplicate, 'duplicate;', $tablename);
                    $historicalduplicate = array();
                }

                if (!empty($historicalrecordindb)) {
                    // Update records as already in db.
                    update_errors_import($historicalrecordindb, 'completiondatesame;', $tablename);
                    $historicalrecordindb = array();
                }

                // Reset enrol instance after enroling the users.
                $enrolid = $course->enrolid;
                $instance = $DB->get_record('enrol', array('id' => $enrolid));
            }

            $timecompleted = null;
            $timestamp = totara_date_parse_from_format($csvdateformat, $course->completiondate);
            if (!empty($timestamp)) {
                $timecompleted = $timestamp;
            }

            $timeenrolled = $course->timeenrolled;
            $timestarted = $course->timestarted;

            if (empty($course->userenrolid) || ($course->userenrolstatus == ENROL_USER_SUSPENDED)) {
                // User isn't already enrolled or has been suspended, so add them to the enrol list.
                $user = new stdClass();
                $user->userid = $course->userid;
                $user->courseid = $course->courseid;
                // Only add users if they have not been marked already to be enrolled.
                if (!array_key_exists($user->userid, $enrolledusers) || !in_array($user->courseid, $enrolledusers[$user->userid])) {
                    $users[] = $user;
                    if (array_key_exists($user->userid, $enrolledusers)) {
                        array_push($enrolledusers[$user->userid], $user->courseid);
                    } else {
                        $enrolledusers[$user->userid] = array($user->courseid);
                    }
                }
                $timeenrolled = $timecompleted;
                $timestarted = $timecompleted;
            } else if (!empty($timecompleted)) {
                // Best guess at enrollment times.
                if (($timeenrolled > $timecompleted) || (empty($timeenrolled))) {
                    $timeenrolled = $timecompleted;
                }
                if (($timestarted > $timecompleted) || (empty($timestarted))) {
                    $timestarted = $timecompleted;
                }
            }
            // Create completion record.
            $completion = new stdClass();
            $completion->rpl = get_string('rpl', 'totara_completionimport', $course->grade);
            $completion->rplgrade = $course->grade;
            $completion->status = COMPLETION_STATUS_COMPLETEVIARPL;
            $completion->timeenrolled = $timeenrolled;
            $completion->timestarted = $timestarted;
            $completion->timecompleted = $timecompleted;
            $completion->reaggregate = 0;
            $completion->userid = $course->userid;
            $completion->course = $course->courseid;
            // Create block_totara_stats records
            $stat = new stdClass();
            $stat->userid = $course->userid;
            $stat->timestamp = time();
            $stat->eventtype = STATS_EVENT_COURSE_COMPLETE;
            $stat->data = '';
            $stat->data2 = $course->courseid;

            $priorkey = "{$completion->userid}_{$completion->course}";
            $historyrecord = null;

            // Now that records have been ordered we know that every time we enter here it's a new completion record.
            if ($course->userid != $currentuser || $course->courseid != $currentcourse) {
                // User or course has changed or they are empty. Update the current user and course.
                $currentuser = $course->userid;
                $currentcourse = $course->courseid;
                if (empty($course->coursecompletionid)) {
                    $completions[$priorkey] = $completion; // Completion should be the first record
                    $stats[$priorkey] = $stat;
                } else if ($completion->timecompleted >= $course->currenttimecompleted && $overridecurrentcompletion) {
                    $deletedcompletions[] = $course->coursecompletionid;
                    $completions[$priorkey] = $completion;
                    $stats[$priorkey] = $stat;
                }
            } else {
                $historyrecord = $completion;
            }

            // Save historical records.
            if (!is_null($historyrecord)) {
                $priorhistorykey = "{$historyrecord->course}_{$historyrecord->userid}_{$historyrecord->timecompleted}";
                $history = new StdClass();
                $history->courseid = $historyrecord->course;
                $history->userid = $historyrecord->userid;
                $history->timecompleted = $historyrecord->timecompleted;
                $history->grade = $historyrecord->rplgrade;
                if (!array_key_exists($priorhistorykey, $completion_history)) {
                    $params = array(
                        'courseid' => $history->courseid,
                        'userid' => $history->userid,
                        'timecompleted' => $history->timecompleted
                    );
                    if (!$DB->record_exists('course_completion_history', $params)) {
                        $completion_history[$priorhistorykey] = $history;
                    } else {
                        $historicalrecordindb[] = $course->importid;
                    }
                } else {
                    $historicalduplicate[] =  $course->importid;
                }
            }

            $updateids[] = $course->importid;
            $enrolcount++;
        }
    }
    $courses->close();
    // Delete any existing course completions we are overriding.
    if (!empty($deletedcompletions)) {
        $DB->delete_records_list('course_completions', 'id', $deletedcompletions);
        $deletedcompletions = array();
    }

    if (!empty($completions)) {
        // Batch import completions.
        $DB->insert_records_via_batch('course_completions', $completions);
        $completions = array();
    }

    if (!empty($stats)) {
        // Batch import block_totara_stats.
        $DB->insert_records_via_batch('block_totara_stats', $stats);
        $stats = array();
    }

    if (!empty($completion_history)) {
        // Batch import completions.
        $DB->insert_records_via_batch('course_completion_history', $completion_history);
        $completion_history = array();
    }

    // Add any remaining records.
    if (!empty($users)) {
        // Batch enrol users.
        $plugin->enrol_user_bulk($instance, $users, $instance->roleid, $timestart, $timeend);
        $enrolcount = 0;
        $users = array();
    }

    if (!empty($updateids)) {
        // Update the timeupdated.
        list($insql, $params) = $DB->get_in_or_equal($updateids, SQL_PARAMS_NAMED, 'param');
        $params['timeupdated'] = $importtime;
        $sql = "UPDATE {{$tablename}}
                SET timeupdated = :timeupdated
                WHERE id {$insql}";
        $DB->execute($sql, $params);
        $updateids = array();
    }

    if (!empty($historicalduplicate)) {
        // Update records as duplicated.
        update_errors_import($historicalduplicate, 'duplicate;', $tablename);
        $historicalduplicate = array();
    }

    if (!empty($historicalrecordindb)) {
        // Update records as already in db.
        update_errors_import($historicalrecordindb, 'completiondatesame;', $tablename);
        $historicalrecordindb = array();
    }

    return $errors;
}

/**
 * Assign users to certifications and complete them
 *
 * Doesn't seem to be a bulk function for this so inserting directly into the tables
 *
 * @global object $DB
 * @global object $CFG
 * @param string $importname name of import
 * @param int $importtime time of import
 * @return array of errors if any
 */
function import_certification($importname, $importtime) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/totara/program/program.class.php');

    $errors = array();
    $updateids = array();
    $cc = array();
    $deleted = array();
    $pc = array();
    $pchistory = array();
    $cchistory = array();
    $pua = array();
    $users = array();
    // Arrays to hold info on previously-processed records for program/user pairs in this batch.
    // In certifications an upload may contain multiple records for a program for one user going back historically.
    $priorcc = array();
    $priorpc = array();
    $priorua = array();
    $pluginname = 'totara_completionimport_' . $importname;
    $csvdateformat = get_default_config($pluginname, 'csvdateformat', TCI_CSV_DATE_FORMAT);
    $overrideactivecertification = get_default_config($pluginname, 'overrideactive' . $importname, false);

    list($sqlwhere, $stdparams) = get_importsqlwhere($importtime);
    $params = array();
    $params['assignmenttype2'] = ASSIGNTYPE_INDIVIDUAL;
    $params = array_merge($params, $stdparams);

    $tablename = get_tablename($importname);

    // Create missing program assignments for individuals, in a form that will work for insert_records_via_batch().
    // Note: Postgres objects to manifest constants being used as parameters where they are the left hand.
    // of an SQL clause (eg 5 AS assignmenttype) so manifest constants are placed in the query directly (better anyway!).
    $shortnameoridnumber = get_shortnameoridnumber('p', 'i', 'certificationshortname', 'certificationidnumber');
    $sql = "SELECT DISTINCT p.id AS programid,
            ".ASSIGNTYPE_INDIVIDUAL." AS assignmenttype,
            u.id AS assignmenttypeid,
            0 AS includechildren,
            ".COMPLETION_TIME_NOT_SET." AS completiontime,
            ".COMPLETION_EVENT_NONE." AS completionevent,
            0 AS completioninstance
            FROM {{$tablename}} i
            JOIN {user} u ON u.username = i.username
            JOIN {prog} p ON {$shortnameoridnumber}
            {$sqlwhere}
            AND NOT EXISTS (SELECT pa.id FROM {prog_user_assignment} pa
                WHERE pa.programid = p.id AND pa.userid = u.id)
            AND NOT EXISTS (SELECT pfa.id FROM {prog_future_user_assignment} pfa
                WHERE pfa.programid = p.id AND pfa.userid = u.id )";

    $assignments = $DB->get_recordset_sql($sql, $params);

    $DB->insert_records_via_batch('prog_assignment', $assignments);
    $assignments->close();

    // Now get the records to import.
    $params = array_merge(array('assignmenttype' => ASSIGNTYPE_INDIVIDUAL), $stdparams);
    $sql = "SELECT DISTINCT i.id as importid,
                    i.completiondate,
                    p.id AS progid,
                    c.id AS certifid,
                    c.recertifydatetype,
                    c.activeperiod,
                    c.windowperiod,
                    cc.timeexpires,
                    u.id AS userid,
                    pa.id AS assignmentid,
                    cc.id AS ccid,
                    pc.id AS pcid,
                    pua.id AS puaid,
                    pfa.id AS pfaid,
                    cc.timecompleted AS currenttimecompleted
            FROM {{$tablename}} i
            LEFT JOIN {prog} p ON {$shortnameoridnumber}
            LEFT JOIN {certif} c ON c.id = p.certifid
            LEFT JOIN {user} u ON u.username = i.username
            LEFT JOIN {prog_assignment} pa ON pa.programid = p.id
            LEFT JOIN {prog_user_assignment} pua ON pua.assignmentid = pa.id AND pua.userid = u.id AND pua.programid = p.id
            LEFT JOIN {prog_future_user_assignment} pfa ON pfa.assignmentid = pa.id AND pfa.userid = u.id AND pfa.programid = p.id
            LEFT JOIN {certif_completion} cc ON cc.certifid = c.id AND cc.userid = u.id
            LEFT JOIN {prog_completion} pc ON pc.programid = p.id AND pc.userid = u.id AND pc.coursesetid = 0
            {$sqlwhere}
            AND ((pa.assignmenttype = :assignmenttype AND pa.assignmenttypeid = u.id)
              OR (pfa.userid = u.id AND pfa.assignmentid IS NOT NULL)
              OR (pua.userid = u.id AND pua.assignmentid IS NOT NULL))
            ORDER BY p.id";

    $insertcount = 1;
    $programid = 0;
    $programs = $DB->get_recordset_sql($sql, $params);

    if ($programs->valid()) {
        foreach ($programs as $program) {
            if (empty($programid) || ($programid != $program->progid) || (($insertcount++ % BATCH_INSERT_MAX_ROW_COUNT) == 0)) {
                // Insert a batch for a given programid (as need to insert user roles with program context).
                if (!empty($deleted)) {
                    $DB->delete_records_list('certif_completion', 'id', $deleted);
                    unset($deleted);
                    $deleted = array();
                }
                if (!empty($cc)) {
                    $DB->insert_records_via_batch('certif_completion', $cc);
                    unset($cc);
                    $cc = array();
                }
                if (!empty($cchistory)) {
                    $DB->insert_records_via_batch('certif_completion_history', $cchistory);
                    unset($cchistory);
                    $cchistory = array();
                }
                if (!empty($pc)) {
                    $DB->insert_records_via_batch('prog_completion', $pc);
                    unset($pc);
                    $pc = array();
                }
                if (!empty($pchistory)) {
                    $DB->insert_records_via_batch('prog_completion_history', $pchistory);
                    unset($pchistory);
                    $pchistory = array();
                }
                if (!empty($pua)) {
                    $DB->insert_records_via_batch('prog_user_assignment', $pua);
                    unset($pua);
                    $pua = array();
                }
                if (!empty($users)) {
                    $context = context_program::instance($programid);
                    role_assign_bulk($CFG->learnerroleid, $users, $context->id);
                    unset($users);
                    $users = array();
                }
                if (!empty($updateids)) {
                    // Update the timeupdated.
                    list($updateinsql, $params) = $DB->get_in_or_equal($updateids, SQL_PARAMS_NAMED, 'param');
                    $params['timeupdated'] = $importtime;
                    $sql = "UPDATE {{$tablename}}
                            SET timeupdated = :timeupdated
                            WHERE id {$updateinsql}";
                    $DB->execute($sql, $params);
                    unset($updateids);
                    $updateids = array();
                }

                $programid = $program->progid;
            }

            // Create Certification completion record.
            $ccdata = new stdClass();
            $ccdata->certifid = $program->certifid;
            $ccdata->userid = $program->userid;
            $ccdata->certifpath = CERTIFPATH_RECERT;
            $ccdata->status = CERTIFSTATUS_COMPLETED;
            $ccdata->renewalstatus = CERTIFRENEWALSTATUS_NOTDUE;

            $errorsql = "UPDATE {{$tablename}}
                SET importerrormsg = " . $DB->sql_concat('importerrormsg', ':errorstring') . ",
                    importerror = :importerror
                    WHERE id = :importid";

            $now = time();

            // Do recert times.
            $timecompleted = totara_date_parse_from_format($csvdateformat, $program->completiondate);
            if (!$timecompleted) {
                $timecompleted = now();
            }
            // In imports we always use CERTIFRECERT_COMPLETION, instead of the user's value from $program->recertifydatetype.
            // That is because when importing we only have the completion date so "use certification expiry date" doesn't make
            // sense. See T-11684.
            $base = get_certiftimebase(CERTIFRECERT_COMPLETION, $program->timeexpires, $timecompleted);
            $ccdata->timeexpires = get_timeexpires($base, $program->activeperiod);
            $ccdata->timewindowopens = get_timewindowopens($ccdata->timeexpires, $program->windowperiod);

            $ccdata->timecompleted = $timecompleted;
            $ccdata->timemodified = $now;
            $priorkey = "{$ccdata->certifid}_{$ccdata->userid}";
            $priorhistorykey = "{$ccdata->certifid}_{$ccdata->userid}_{$ccdata->timeexpires}";
            $addtopending = false;
            // Active record not complete, delete the current completion record and
            // put the imported one in its place.
            if (empty($program->currenttimecompleted)) {
                if (!is_null($program->ccid)) {
                    $deleted[] = $program->ccid;
                }
                $addtopending = true;
            } else if ($ccdata->timecompleted > $program->currenttimecompleted) {
                // The imported record is newer than the current record.
                if ($ccdata->timeexpires > $now && $ccdata->timewindowopens > $now) { // Not due.
                    if (!is_null($program->ccid)) {
                        $deleted[] = $program->ccid;
                    }
                    $addtopending = true;
                } else if ($ccdata->timeexpires > $now && $ccdata->timewindowopens <= $now) { // Due.
                    // Check config variable here to see if we want to override.
                    if ($overrideactivecertification) {
                        if (!is_null($program->ccid)) {
                            $deleted[] = $program->ccid;
                        }
                        $addtopending = true;
                    } else {
                        // Don't override and generate an import error.
                        $params = array('errorstring' => 'certificationdueforrecert;', 'importerror' => 1, 'importid' => $program->importid);

                        $DB->execute($errorsql, $params);
                        continue;
                    }
                } else {
                    // Certification has already expired, don't change the active record.
                    // Flag as error and add an entry to the import log.
                    $params = array('errorstring' => 'certificationexpired;', 'importerror' => 1, 'importid' => $program->importid);

                    $DB->execute($errorsql, $params);
                    continue;
                }
            } else if ($ccdata->timecompleted < $program->currenttimecompleted) {
                // The imported record is older than the current record.
                // Put the imported record directly into the history table and
                // leave the active record unchanged.
                $chparams = array('certifid'    => $ccdata->certifid,
                    'userid'      => $ccdata->userid,
                    'timeexpires' => $ccdata->timeexpires);

                if (!array_key_exists($priorhistorykey, $cchistory) && !$DB->record_exists('certif_completion_history', $chparams)) {
                    $cchistory[$priorhistorykey] = $ccdata;
                }
            } else {
                // The imported record and the active record have exactly the same date
                // Flag as error and add an entry to the import log.
                $params = array('errorstring' => 'completiondatesame;', 'importerror' => 1, 'importid' => $program->importid);

                $DB->execute($errorsql, $params);
                continue;
            }

            if ($addtopending) {
                // Scan the pending completions in this batch for an existing program/user match
                // then compare completion dates to make sure older completions go into the cchistory array.
                if (isset($priorcc[$priorkey])) {
                    if ($ccdata->timecompleted > $priorcc[$priorkey]->timecompleted) {
                        // Newer record, swap out and put the existing in history instead.
                        $cchistory[$priorhistorykey] = $priorcc[$priorkey];
                        $priorcc[$priorkey] = $ccdata;
                        $cc[$priorkey] = $ccdata;
                    } else if ($ccdata->timecompleted < $priorcc[$priorkey]->timecompleted) {
                        // Older record, put directly in history.
                        $cchistory[$priorhistorykey] = $ccdata;
                    }
                } else {
                    // No prior matching record exists in this batch, add to pending completions.
                    $cc[$priorkey] = $ccdata;
                    $priorcc[$priorkey] = $ccdata;
                }
            }

            // Program completion.
            $pcdata = new stdClass();
            $pcdata->programid = $program->progid;
            $pcdata->userid = $program->userid;
            $pcdata->coursesetid = 0;
            $pcdata->status = STATUS_PROGRAM_COMPLETE; // Assume complete.
            $pcdata->timestarted = $timecompleted;
            $pcdata->timedue = $timecompleted;
            $pcdata->timecompleted = $timecompleted;

            if (empty($program->pcid)) {
                // New program completion record.
                if (isset($priorpc[$priorkey])) {
                    if ($pcdata->timecompleted > $priorpc[$priorkey]->timecompleted) {
                        // Newer record, swap out and put the existing in history instead.
                        $pchistory[] = $priorpc[$priorkey];
                        $priorpc[$priorkey] = $pcdata;
                        $pc[$priorkey] = $pcdata;
                    } else if ($pcdata->timecompleted < $priorcc[$priorkey]->timecompleted) {
                        // Older record, put directly in history.
                        $pchistory[] = $pcdata;
                    }
                } else {
                    // No prior matching record exists in this batch, add to pending completions.
                    $pc[$priorkey] = $pcdata;
                    $priorpc[$priorkey] = $pcdata;
                }
            } else {
                // There is an existing record so put into history.
                $pchistory[] = $pcdata;
            }

            // Program user assignment if not already assigned in this batch.
            $puadata = new stdClass();
            $puadata->programid = $program->progid;
            $puadata->userid = $program->userid;
            $puadata->assignmentid = $program->assignmentid;
            $puadata->timeassigned = time();
            $puadata->exceptionstatus = PROGRAM_EXCEPTION_RESOLVED;

            if (empty($program->pfaid)) {
                if (empty($program->puaid)) {
                    if (!isset($priorua[$priorkey])) {
                        $pua[] = $puadata;
                        $priorua[$priorkey] = $puadata;
                    }
                } else {
                    // Do not waste time updating record again if we have already processed this user.
                    if (!isset($priorua[$priorkey])) {
                        $puadata->id = $program->puaid;
                        $DB->update_record('prog_user_assignment', $puadata);
                        $priorua[$priorkey] = $puadata;
                    }
                }
            }

            // User array for role addition.
            if (!in_array($program->userid, $users)) {
                $users[] = $program->userid;
            }

            // Totara_compl_import_cert ids.
            $updateids[] = $program->importid;
        }
    }
    $programs->close();

    if (!empty($deleted)) {
        $DB->delete_records_list('certif_completion', 'id', $deleted);
        unset($deleted);
        $deleted = array();
    }
    if (!empty($cc)) {
        $DB->insert_records_via_batch('certif_completion', $cc);
        unset($cc);
        $cc = array();
    }
    if (!empty($cchistory)) {
        $DB->insert_records_via_batch('certif_completion_history', $cchistory);
        unset($cchistory);
        $cchistory = array();
    }
    if (!empty($pc)) {
        $DB->insert_records_via_batch('prog_completion', $pc);
        unset($pc);
        $pc = array();
    }
    if (!empty($pchistory)) {
        $DB->insert_records_via_batch('prog_completion_history', $pchistory);
        unset($pchistory);
        $pchistory = array();
    }
    if (!empty($pua)) {
        $DB->insert_records_via_batch('prog_user_assignment', $pua);
        unset($pua);
        $pua = array();
    }
    if (!empty($users)) {
        $context = context_program::instance($programid);
        role_assign_bulk($CFG->learnerroleid, $users, $context->id);
        unset($users);
        $users = array();
    }
    if (!empty($updateids)) {
        // Update the timeupdated.
        list($insql, $params) = $DB->get_in_or_equal($updateids, SQL_PARAMS_NAMED, 'param');
        $params['timeupdated'] = $importtime;
        $sql = "UPDATE {{$tablename}}
                SET timeupdated = :timeupdated
                WHERE id {$insql}";
        $DB->execute($sql, $params);
        unset($updateids);
        $updateids = array();
    }

    return $errors;
}

/**
 * Returns a list of possible date formats
 * Based on the list at http://en.wikipedia.org/wiki/Date_format_by_country
 *
 * @return array
 */
function get_dateformats() {
    $separators = array('-', '/', '.', ' ');
    $endians = array('yyyy~mm~dd', 'yy~mm~dd', 'dd~mm~yyyy', 'dd~mm~yy', 'mm~dd~yyyy', 'mm~dd~yy');
    $formats = array();
    foreach ($endians as $endian) {
        foreach ($separators as $separator) {
            $display = str_replace( '~', $separator, $endian);
            $format = str_replace('yyyy', 'Y', $display);
            $format = str_replace('yy', 'y', $format); // Don't think 2 digit years should be allowed.
            $format = str_replace('mm', 'm', $format);
            $format = str_replace('dd', 'd', $format);
            $formats[$format] = $display;
        }
    }
    return $formats;
}

/**
 * Displays import results and a link to view the import errors
 *
 * @global object $OUTPUT
 * @global object $DB
 * @global object $USER
 * @param string $importname name of import
 * @param int $importtime time of import
 */
function display_report_link($importname, $importtime) {
    global $OUTPUT, $DB, $USER;

    $tablename = get_tablename($importname);

    $sql = "SELECT COUNT(*) AS totalrows,
            COALESCE(SUM(importerror), 0) AS totalerrors,
            COALESCE(SUM(importevidence), 0) AS totalevidence
            FROM {{$tablename}}
            WHERE timecreated = :timecreated
            AND importuserid = :userid";
    $totals = $DB->get_record_sql($sql, array('timecreated' => $importtime, 'userid' => $USER->id));

    echo $OUTPUT->heading(get_string('importresults', 'totara_completionimport'));
    if ($totals->totalrows) {
        echo html_writer::tag('p', get_string('importerrors', 'totara_completionimport', $totals->totalerrors));
        echo html_writer::tag('p', get_string('importevidence', 'totara_completionimport', $totals->totalevidence));
        echo html_writer::tag('p', get_string('import' . $importname, 'totara_completionimport',
                $totals->totalrows - $totals->totalerrors - $totals->totalevidence));
        echo html_writer::tag('p', get_string('importtotal', 'totara_completionimport', $totals->totalrows));

        $viewurl = new moodle_url('/totara/completionimport/viewreport.php',
                array('importname' => $importname, 'timecreated' => $importtime, 'importuserid' => $USER->id));
        $viewlink = html_writer::link($viewurl, format_string(get_string('report_' . $importname, 'totara_completionimport')));
        echo html_writer::tag('p', $viewlink);
    } else {
        echo html_writer::tag('p', get_string('importnone', 'totara_completionimport'));
    }

}

/**
 * Returns the temporary path for for the temporary file - creates the directory if it doesn't exist
 *
 * @global object $CFG
 * @global object $OUTPUT
 * @return boolean|string false if fails or full name of path
 */
function get_temppath() {
    global $CFG, $OUTPUT;
    // Create the temporary path if it doesn't already exist.
    $temppath = $CFG->dataroot . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'totara_completionimport';
    if (!file_exists($temppath)) {
        if (!mkdir($temppath, $CFG->directorypermissions, true)) {
            echo $OUTPUT->notification(get_string('cannotcreatetemppath', 'totara_completionimport', $temppath), 'notifyproblem');
            return false;
        }
    }
    $temppath .= DIRECTORY_SEPARATOR;
    return $temppath;
}

/**
 * Returns the config data for the upload form
 *
 * Each upload form has its own set of data
 *
 * @param int $filesource Method of upload, either upload via form or external directory
 * @param type $importname
 * @return stdClass $data
 */
function get_config_data($filesource, $importname) {
    $pluginname = 'totara_completionimport_' . $importname;
    $data = new stdClass();
    $data->filesource = $filesource;
    $data->sourcefile = get_config($pluginname, 'sourcefile');
    $data->evidencetype = get_default_config($pluginname, 'evidencetype', null);
    $data->csvdateformat = get_default_config($pluginname, 'csvdateformat', TCI_CSV_DATE_FORMAT);
    $data->csvdelimiter = get_default_config($pluginname, 'csvdelimiter', TCI_CSV_DELIMITER);
    $data->csvseparator = get_default_config($pluginname, 'csvseparator', TCI_CSV_SEPARATOR);
    $data->csvencoding = get_default_config($pluginname, 'csvencoding', TCI_CSV_ENCODING);
    $overridesetting = 'overrideactive' . $importname;
    $data->$overridesetting = get_default_config($pluginname, 'overrideactive' . $importname, 0);
    return $data;
}

/**
 * Saves the data from the upload form
 *
 * @param object $data
 * @param string $importname name of import
 */
function set_config_data($data, $importname) {
    $pluginname = 'totara_completionimport_' . $importname;
    set_config('evidencetype', $data->evidencetype, $pluginname);
    if ($data->filesource == TCI_SOURCE_EXTERNAL) {
        set_config('sourcefile', $data->sourcefile, $pluginname);
    }
    set_config('csvdateformat', $data->csvdateformat, $pluginname);
    set_config('csvdelimiter', $data->csvdelimiter, $pluginname);
    set_config('csvseparator', $data->csvseparator, $pluginname);
    set_config('csvencoding', $data->csvencoding, $pluginname);
    $overridesetting = 'overrideactive' . $importname;
    set_config('overrideactive' . $importname, $data->$overridesetting, $pluginname);
}

/**
 * Moves the external source file to the temporary directory
 *
 * @global object $OUTPUT
 * @param string $filename source file
 * @param string $tempfilename destination file
 * @return boolean true if successful, false if fails
 */
function move_sourcefile($filename, $tempfilename) {
    global $OUTPUT;
    // Check if file is accessible.
    $handle = false;
    if (!is_readable($filename)) {
        echo $OUTPUT->notification(get_string('unreadablefile', 'totara_completionimport', $filename), 'notifyproblem');
        return false;
    } else if (!$handle = fopen($filename, 'r')) {
        echo $OUTPUT->notification(get_string('erroropeningfile', 'totara_completionimport', $filename), 'notifyproblem');
        return false;
    } else if (!flock($handle, LOCK_EX | LOCK_NB)) {
        echo $OUTPUT->notification(get_string('fileisinuse', 'totara_completionimport', $filename), 'notifyproblem');
        fclose($handle);
        return false;
    }
    // Don't need the handle any more so close it.
    fclose($handle);

    if (!rename($filename, $tempfilename)) {
        $a = new stdClass();
        $a->fromfile = $filename;
        $a->tofile = $tempfilename;
        echo $OUTPUT->notification(get_string('cannotmovefiles', 'totara_completionimport', $a), 'notifyproblem');
        return false;
    }

    return true;
}

/**
 * Main import of completions
 *
 * 1. Check the required columns exist in the csv file
 * 2. Import the csv file into the import table
 * 3. Run data checks on the import table
 * 4. Any missing courses / certifications are created as evidence in the record of learning
 * 5. Anything left over is imported into courses or certifications
 *
 * @global object $OUTPUT
 * @global object $DB
 * @param string $tempfilename name of temporary csv file
 * @param string $importname name of import
 * @param int $importtime time of import
 * @param bool $quiet If true, suppress outputting messages (for tests).
 * @return boolean
 */
function import_completions($tempfilename, $importname, $importtime, $quiet = false) {
    global $OUTPUT, $DB;

    // Increase memory limit.
    raise_memory_limit(MEMORY_EXTRA);

    // Stop time outs, this might take a while.
    set_time_limit(0);

    if ($errors = check_fields_exist($tempfilename, $importname)) {
        // Source file header doesn't have the required fields.
        if (!$quiet) {
            echo $OUTPUT->notification(get_string('missingfields', 'totara_completionimport'), 'notifyproblem');
            echo html_writer::alist($errors);
        }
        unlink($tempfilename);
        return false;
    }

    if ($errors = import_csv($tempfilename, $importname, $importtime)) {
        // Something went wrong with import.
        if (!$quiet) {
            echo $OUTPUT->notification(get_string('csvimportfailed', 'totara_completionimport'), 'notifyproblem');
            echo html_writer::alist($errors);
        }
        unlink($tempfilename);
        return false;
    }
    // Don't need the temporary file any more.
    unlink($tempfilename);
    if (!$quiet) {
        echo $OUTPUT->notification(get_string('csvimportdone', 'totara_completionimport'), 'notifysuccess');
    }

    // Data checks - no errors returned, it adds errors to each row in the import table.
    import_data_checks($importname, $importtime);

    // Start transaction, we are dealing with live data now...
    $transaction = $DB->start_delegated_transaction();

    // Put into evidence any courses / certifications not found.
    create_evidence($importname, $importtime);

    // Run the specific course enrolment / certification assignment.
    $functionname = 'import_' . $importname;
    $errors = $functionname($importname, $importtime);
    if (!empty($errors)) {
        if (!$quiet) {
            echo $OUTPUT->notification(get_string('error:' . $functionname, 'totara_completionimport'), 'notifyproblem');
            echo html_writer::alist($errors);
        }
        return false;
    }
    if (!$quiet) {
        echo $OUTPUT->notification(get_string('dataimportdone_' . $importname, 'totara_completionimport'), 'notifysuccess');
    }

    // End the transaction.
    $transaction->allow_commit();

    return true;
}

/**
 * Deletes the import data from the import table
 *
 * @param string $importname name of import
 */
function reset_import($importname) {
    global $DB, $OUTPUT, $USER;
    $tablename = get_tablename($importname);
    if ($DB->delete_records($tablename, array('importuserid' => $USER->id))) {
        echo $OUTPUT->notification(get_string('resetcomplete', 'totara_completionimport', $importname), 'notifysuccess');
    } else {
        echo $OUTPUT->notification(get_string('resetfailed', 'totara_completionimport', $importname), 'notifyproblem');
    }
}

/**
 * Update errors ocurred in the historic import.
 *
 * @param array $records Array of ids that need to be updated with the error message
 * @param string $errormessage message for the error ocurred
 * @param string $tablename Name of the import table
 * @return bool result of the update operation
 */
function update_errors_import($records, $errormessage, $tablename) {
    global $DB;

    if (empty($records)) {
        return false;
    }

    list($insql, $params) = $DB->get_in_or_equal($records, SQL_PARAMS_NAMED, 'param');
    $params['errorstring'] = $errormessage;
    $params['importerror'] = 1;
    $sql = "UPDATE {{$tablename}}
            SET importerrormsg = " . $DB->sql_concat('importerrormsg', ':errorstring') . ",
                importerror = :importerror
            WHERE id {$insql}";
    return $DB->execute($sql, $params);
}
