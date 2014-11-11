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
 * Tests importing generated from a csv file
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit importcertification_testcase totara/completionimport/tests/importcertification_test.php
 *
 * @package    totara_completionimport
 * @subpackage phpunit
 * @author     Russell England <russell.england@catalyst-eu.net>
 * @copyright  Catalyst IT Ltd 2013 <http://catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/totara/completionimport/lib.php');
require_once($CFG->dirroot . '/totara/certification/lib.php');
require_once($CFG->dirroot . '/totara/cohort/lib.php');
require_once($CFG->libdir  . '/csvlib.class.php');
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');

define('CERT_HISTORY_IMPORT_USERS', 11);
define('CERT_HISTORY_IMPORT_CERTIFICATIONS', 11);
define('CERT_HISTORY_IMPORT_CSV_ROWS', 100); // Must be less than user * certification counts.

class importcertification_testcase extends reportcache_advanced_testcase {

    public function test_import() {
        global $DB, $CFG;

        $this->resetAfterTest(true);

        $importname = 'certification';
        $pluginname = 'totara_completionimport_' . $importname;
        $csvdateformat = get_default_config($pluginname, 'csvdateformat', TCI_CSV_DATE_FORMAT);
        $csvdelimiter = get_default_config($pluginname, 'csvdelimiter', TCI_CSV_DELIMITER);
        $csvseparator = get_default_config($pluginname, 'csvseparator', TCI_CSV_SEPARATOR);

        $this->setAdminUser();

        $generatorstart = time();

        // Create some programs.
        $this->assertEquals(0, $DB->count_records('prog'), "Programs table isn't empty");
        $programs = array();
        $now = time();
        $from = strtotime('-1 day', $now);
        $until = strtotime('+1 day', $now);
        for ($i = 1; $i <= CERT_HISTORY_IMPORT_CERTIFICATIONS; $i++) {
            $program = new stdClass();
            $program->certifid = $i;
            $program->fullname = 'Certification Program ' . $i;
            $program->availablefrom = $from;
            $program->availableuntil = $until;
            $program->sortorder = $i;
            $program->timecreated = $now;
            $program->timemodified = $now;
            $program->usermodified = 2;
            $program->category = 1;
            $program->shortname = 'CP' . $i;
            $program->idnumber = $i;
            $program->available = 1;
            $program->icon = 1;
            $program->exceptionssent = 0;
            $program->visible = 1;
            $program->summary = '';
            $program->endnote = '';
            $programs[] = $program;
        }
        $DB->insert_records_via_batch('prog', $programs);
        $this->assertEquals(CERT_HISTORY_IMPORT_CERTIFICATIONS, $DB->count_records('prog'),
                'Record count mismatch in program table');

        // Then some certifications.
        $this->assertEquals(0, $DB->count_records('certif'), "Certif table isn't empty");
        $sql = "INSERT INTO {certif}
                    (learningcomptype, activeperiod, windowperiod, recertifydatetype, timemodified)
                SELECT  " . CERTIFTYPE_PROGRAM . " AS learningcomptype,
                        '1 year' AS activeperiod,
                        '4 week' AS windowperiod,
                        " . CERTIFRECERT_COMPLETION . " AS recertifydatetype,
                        " . time() . " AS timemodified
                FROM {prog}";
        $DB->execute($sql);
        $this->assertEquals(CERT_HISTORY_IMPORT_CERTIFICATIONS, $DB->count_records('certif'),
                'Record count mismatch for certif');

        // Create users.
        $this->assertEquals(2, $DB->count_records('user')); // Guest + Admin.
        for ($i = 1; $i <= CERT_HISTORY_IMPORT_USERS; $i++) {
            $this->getDataGenerator()->create_user();
        }
        $this->assertEquals(CERT_HISTORY_IMPORT_USERS+2, $DB->count_records('user'),
                'Record count mismatch for users'); // Guest + Admin + generated users.

        // Generate import data - product of user and certif tables.
        $fields = array('username', 'certificationshortname', 'certificationidnumber', 'completiondate');
        $csvexport = new csv_export_writer($csvdelimiter, $csvseparator);
        $csvexport->add_data($fields);

        $uniqueid = $DB->sql_concat('u.username', 'p.shortname');
        $sql = "SELECT  {$uniqueid} AS uniqueid,
                        u.username,
                        p.shortname AS certificationshortname,
                        p.idnumber AS certificationidnumber
                FROM    {user} u,
                        {prog} p";
        $imports = $DB->get_recordset_sql($sql, null, 0, CERT_HISTORY_IMPORT_CSV_ROWS);
        if ($imports->valid()) {
            $count = 0;
            foreach ($imports as $import) {
                $data = array();
                $data['username'] = $import->username;
                $data['certificationshortname'] = $import->certificationshortname;
                $data['certificationidnumber'] = $import->certificationidnumber;
                $data['completiondate'] = date($csvdateformat, strtotime(date('Y-m-d') . ' -' . rand(1, 365) . ' days'));
                $csvexport->add_data($data);
                $count++;
            }
        }
        $imports->close();
        $this->assertEquals(CERT_HISTORY_IMPORT_CSV_ROWS, $count, 'Record count mismatch when creating CSV file');

        // Save the csv file generated by csvexport.
        $temppath = $CFG->dataroot . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR;
        if (!file_exists($temppath)) {
            mkdir($temppath, $CFG->directorypermissions, true);
        }
        $filename = tempnam($temppath, 'imp');
        copy($csvexport->path, $filename);

        $generatorstop = time();

        $importstart = time();
        import_completions($filename, $importname, $importstart, true);
        $importstop = time();

        $importtablename = get_tablename($importname);
        $this->assertEquals(CERT_HISTORY_IMPORT_CSV_ROWS, $DB->count_records($importtablename),
                'Record count mismatch in the import table ' . $importtablename);
        $this->assertEquals(0, $DB->count_records('dp_plan_evidence'),
                'There should be no evidence records');
        $this->assertEquals(CERT_HISTORY_IMPORT_CSV_ROWS, $DB->count_records('certif_completion'),
                'Record count mismatch in the certif_completion table');
        $this->assertEquals(CERT_HISTORY_IMPORT_CSV_ROWS, $DB->count_records('prog_completion'),
                'Record count mismatch in the prog_completion table');
        $this->assertEquals(CERT_HISTORY_IMPORT_CSV_ROWS, $DB->count_records('prog_user_assignment'),
                'Record count mismatch in the prog_user_assignment table');
    }

    /* Check that users are assigned to the certification with the correct assignment type.
     * When a certification is created users could be already assigned via audience, individual assignment
     * or any other assignment type. If that happens, we need to make sure we are creating the user-program
     * association correctly.
     */
    public function test_import_assignments() {
        global $DB, $CFG;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        $importname = 'certification';
        $pluginname = 'totara_completionimport_' . $importname;
        $csvdateformat = get_default_config($pluginname, 'csvdateformat', TCI_CSV_DATE_FORMAT);
        $csvdelimiter = get_default_config($pluginname, 'csvdelimiter', TCI_CSV_DELIMITER);
        $csvseparator = get_default_config($pluginname, 'csvseparator', TCI_CSV_SEPARATOR);

        $this->setAdminUser();

        $generatorstart = time();

        // Create a program.
        $this->getDataGenerator()->create_course();
        $this->assertEquals(0, $DB->count_records('prog'), "Programs table isn't empty");
        $data = array();
        $data['certifid'] = 1;
        $data['fullname'] = 'Certification Program1 ';
        $data['category'] = 1;
        $data['shortname'] = 'CP1';
        $data['idnumber'] = 1;
        $data['available'] = 1;
        $program = $this->getDataGenerator()->create_program($data);

        $this->assertEquals(1, $DB->count_records('prog'), 'Record count mismatch in program table');

        // Then a certification.
        $this->assertEquals(0, $DB->count_records('certif'), "Certif table isn't empty");
        $sql = "INSERT INTO {certif}
                    (learningcomptype, activeperiod, windowperiod, recertifydatetype, timemodified)
                SELECT  " . CERTIFTYPE_PROGRAM . " AS learningcomptype,
                        '1 year' AS activeperiod,
                        '4 week' AS windowperiod,
                        " . CERTIFRECERT_COMPLETION . " AS recertifydatetype,
                        " . time() . " AS timemodified
                FROM {prog}";
        $DB->execute($sql);
        $this->assertEquals(1, $DB->count_records('certif'), 'Record count mismatch for certif');

        // Create users.
        $this->assertEquals(2, $DB->count_records('user')); // Guest + Admin.
        $users = array();
        for ($i = 1; $i <= CERT_HISTORY_IMPORT_USERS; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user();
        }
        $this->assertEquals(CERT_HISTORY_IMPORT_USERS+2, $DB->count_records('user'),
            'Record count mismatch for users'); // Guest + Admin + generated users.

        // Associate some users to an audience - (users from 1-5).
        $this->assertEquals(0, $DB->count_records('cohort'));
        $cohort = $this->getDataGenerator()->create_cohort();
        $this->assertEquals(1, $DB->count_records('cohort'));
        $usersincohort = array();
        for ($i = 1; $i <= 5; $i++) {
            cohort_add_member($cohort->id, $users[$i]->id);
            $usersincohort[] = $users[$i]->id;
        }
        $this->assertEquals(5, $DB->count_records('cohort_members', array('cohortid' => $cohort->id)));

        // Assign audience to the certification.
        $this->getDataGenerator()->assign_to_program($program->id, ASSIGNTYPE_COHORT, $cohort->id);
        if (!empty($CFG->messaging)) {
            $this->assertDebuggingCalled(null, null, '', 5);
        }

        // Assign some users as individual to the certification - (users: 6 and 7).
        $this->getDataGenerator()->assign_to_program($program->id, ASSIGNTYPE_INDIVIDUAL, $users[6]->id);
        if (!empty($CFG->messaging)) {
            $this->assertDebuggingCalled();
        }
        $this->getDataGenerator()->assign_to_program($program->id, ASSIGNTYPE_INDIVIDUAL, $users[7]->id);
        if (!empty($CFG->messaging)) {
            $this->assertDebuggingCalled();
        }

        // Assign user 8 as an individual but set completion date in the future.
        $record = array('completiontime' => '15 2'  , 'completionevent' => COMPLETION_EVENT_FIRST_LOGIN);
        $this->getDataGenerator()->assign_to_program($program->id, ASSIGNTYPE_INDIVIDUAL, $users[8]->id, $record);

        // Generate import data - product of user and certif tables.
        $fields = array('username', 'certificationshortname', 'certificationidnumber', 'completiondate');
        $csvexport = new csv_export_writer($csvdelimiter, $csvseparator);
        $csvexport->add_data($fields);

        $uniqueid = $DB->sql_concat('u.username', 'p.shortname');
        $sql = "SELECT  {$uniqueid} AS uniqueid,
                        u.username,
                        p.shortname AS certificationshortname,
                        p.idnumber AS certificationidnumber
                FROM    {user} u,
                        {prog} p";
        $imports = $DB->get_recordset_sql($sql, null, 0, CERT_HISTORY_IMPORT_CSV_ROWS);
        if ($imports->valid()) {
            $count = 0;
            foreach ($imports as $import) {
                $data = array();
                $data['username'] = $import->username;
                $data['certificationshortname'] = $import->certificationshortname;
                $data['certificationidnumber'] = $import->certificationidnumber;
                $data['completiondate'] = date($csvdateformat, strtotime(date('Y-m-d') . ' -' . rand(1, 365) . ' days'));
                $csvexport->add_data($data);
                $count++;
            }
        }
        $imports->close();
        $this->assertEquals(CERT_HISTORY_IMPORT_USERS+2, $count, 'Record count mismatch when creating CSV file');

        // Save the csv file generated by csvexport.
        $temppath = $CFG->dataroot . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR;
        if (!file_exists($temppath)) {
            mkdir($temppath, $CFG->directorypermissions, true);
        }
        $filename = tempnam($temppath, 'imp2');
        copy($csvexport->path, $filename);

        $generatorstop = time();

        $importstart = time();
        import_completions($filename, $importname, $importstart, true);
        $importstop = time();

        // Check assignments were created correctly.
        $params = array($program->id);
        list($insql, $inparams) = $DB->get_in_or_equal($usersincohort);
        $sql = "SELECT pa.assignmenttype
                FROM {prog_user_assignment} pua
                LEFT JOIN {prog_assignment} pa ON pa.id = pua.assignmentid AND pa.programid = pua.programid
                WHERE pua.programid = ? AND userid $insql";
        $params = array_merge($params, $inparams);
        $cohortassignments = $DB->get_fieldset_sql($sql, $params);
        foreach ($cohortassignments as $assignmenttype) {
            $this->assertEquals(ASSIGNTYPE_COHORT, $assignmenttype,
                'wrong assignment type assigned. The user is already assigned to the program as a cohort member');
        }

        $params = array($program->id);
        list($insql, $inparams) = $DB->get_in_or_equal(array($users[6]->id, $users[7]->id));
        $sql = "SELECT pa.assignmenttype
                FROM {prog_user_assignment} pua
                LEFT JOIN {prog_assignment} pa ON pa.id = pua.assignmentid AND pa.programid = pua.programid
                WHERE pua.programid = ? AND userid $insql";
        $params = array_merge($params, $inparams);
        $individualassignments = $DB->get_fieldset_sql($sql, $params);
        foreach ($individualassignments as $assignmenttype) {
            $this->assertEquals(ASSIGNTYPE_INDIVIDUAL, $assignmenttype,
                'wrong assignment type assigned. The user is already assigned to the program as an individual');
        }

        // Check user 8 was assigned as individual but only has records for future assignment.
        $params = array('programid' => $program->id, 'assignmenttype' => ASSIGNTYPE_INDIVIDUAL, 'assignmenttypeid' => $users[8]->id);
        $records = $DB->get_records('prog_assignment', $params);
        $this->assertEquals(1, count($records));
        $assignment = reset($records);
        $params = array('programid' => $program->id, 'userid' => $users[8]->id, 'assignmentid' => $assignment->id);
        $this->assertTrue($DB->record_exists('prog_future_user_assignment', $params));
        $this->assertFalse($DB->record_exists('prog_user_assignment', $params));

        // Check that the rest of users who don't have previous assignments were assigned as individual.
        $params = array($program->id);
        list($insql, $inparams) = $DB->get_in_or_equal(array($users[9]->id, $users[10]->id, $users[11]->id));
        $sql = "SELECT pa.assignmenttype
                FROM {prog_user_assignment} pua
                LEFT JOIN {prog_assignment} pa ON pa.id = pua.assignmentid AND pa.programid = pua.programid
                WHERE pua.programid = ? AND userid $insql";
        $params = array_merge($params, $inparams);
        $individualassignments = $DB->get_fieldset_sql($sql, $params);
        foreach ($individualassignments as $assignmenttype) {
            $this->assertEquals(ASSIGNTYPE_INDIVIDUAL, $assignmenttype,
                'wrong assignment type assigned. The user should have been assigned as an individual');
        }

        $importtablename = get_tablename($importname);
        $this->assertEquals(CERT_HISTORY_IMPORT_USERS+2, $DB->count_records($importtablename),
            'Record count mismatch in the import table ' . $importtablename);
        $this->assertEquals(0, $DB->count_records('dp_plan_evidence'),
            'There should be no evidence records');
        $this->assertEquals(CERT_HISTORY_IMPORT_USERS+2, $DB->count_records('certif_completion'),
            'Record count mismatch in the certif_completion table');
        $this->assertEquals(CERT_HISTORY_IMPORT_USERS+2, $DB->count_records('prog_completion'),
            'Record count mismatch in the prog_completion table');
        $this->assertEquals(CERT_HISTORY_IMPORT_USERS+1, $DB->count_records('prog_user_assignment'),
            'Record count mismatch in the prog_user_assignment table'); // Because user8 doesn't have records in this table.
        $this->assertEquals(1, $DB->count_records('prog_future_user_assignment'),
            'Record count mismatch in the prog_future_user_assignment table');
    }
}
