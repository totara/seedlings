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
require_once($CFG->libdir . '/csvlib.class.php');

define('CERT_HISTORY_IMPORT_USERS', 11);
define('CERT_HISTORY_IMPORT_CERTIFICATIONS', 11);
define('CERT_HISTORY_IMPORT_CSV_ROWS', 100); // Must be less than user * certification counts.

class importcertification_testcase extends advanced_testcase {

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
                        p.id AS certificationidnumber
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
}
