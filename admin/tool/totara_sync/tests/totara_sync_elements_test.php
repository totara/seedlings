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
 * @subpackage totara_sync
 *
 * Unit tests for admin/tool/totara_sync
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    //  It must be included from a Moodle page.
}

global $CFG;
require_once($CFG->dirroot . '/admin/tool/totara_sync/sources/source_org_csv.php');
require_once($CFG->dirroot . '/admin/tool/totara_sync/admin/forms.php');
require_once($CFG->dirroot . '/admin/tool/totara_sync/elements/user.php');
require_once($CFG->dirroot . '/admin/tool/totara_sync/sources/source_user_csv.php');

class totara_sync_elements_test extends PHPUnit_Framework_TestCase {

    protected function setUp() {
        parent::setup();
    }

    /**
     * Test elements path validation and canonization
     *
     * For best coverage must be run in both Unix and Windows environments
     */
    public function test_elements_path() {
        $suffix = '/test/csv';
        $suffixos = str_replace('/', DIRECTORY_SEPARATOR, $suffix);
        $paths = array(__DIR__ => array(__DIR__ . $suffixos, true),
            '/pathmustnotexist' => array('/pathmustnotexist' . $suffix, false),
            '/path$not valid'=> array('/path$not valid' . $suffix, false),
            'c:\\pathmustnotexists' => array('c:\\pathmustnotexists' . $suffix, false)
        );

        if (DIRECTORY_SEPARATOR == '\\') {
            $paths['c:\\pathmustnotexists'][1] = true;
        }

        error_reporting(E_ALL & ~E_STRICT);
        $source = new totara_sync_source_org_csv();
        $form = new totara_sync_config_form();
        foreach ($paths as $path => $expected) {
            $source->filesdir = $path;
            $valid = $form->validation(array('fileaccess' => FILE_ACCESS_DIRECTORY, 'filesdir' => $path), null);
            $valid = empty($valid);

            $this->assertEquals($expected[0], $source->get_canonical_filesdir($suffix));
            $this->assertEquals($expected[1], $valid, "unexpected result for path: $path");
        }
    }

    /**
     * Test that the user sync function inserts, updates and deletes the correct records.
     */
    public function test_user_sync() {
        global $DB;

        $generator = new testing_data_generator();

        // We run the tests twice, with sourceallrecords set to true or false.
        $key = 0;
        foreach (array(1, 0) as $sourceallrecords) {

            // Initialise the source. We could use csv or database, it's an arbitrary choice since we only use the base class methods.
            $source = new totara_sync_source_user_csv();
            if (!$sourceallrecords) {
                // Modify the "import_deleted" value in the source's config variable, which is protected.
                $reflection = new ReflectionClass($source);
                $reflection_property = $reflection->getProperty('config');
                $reflection_property->setAccessible(true);
                $sourceconfig = $reflection_property->getValue($source);
                $sourceconfig->import_deleted = true;
                $reflection_property->setValue($source, $sourceconfig);
            }

            // Create the sync table.
            $temptable = $source->prepare_temp_table();
            $synctable = $temptable->getName();

            // Insert data.
            $expectedresults = array();
            $expectedcount = $DB->count_records('user'); // Start with the users that already exist in the user table.
            foreach (array(1, 0) as $userexists) {
                foreach (array(1, 0) as $userdeleted) {
                    foreach (array(1, 0) as $usersync) {
                        foreach (array(1, 0) as $useridnumber) {
                            foreach (array(1, 0) as $syncexists) {
                                foreach (array(1, 0) as $syncidnumber) {
                                    $syncdeleteoptions = $sourceallrecords ? array(0) : array(1, 0);
                                    foreach ($syncdeleteoptions as $syncdeleted) {
                                        $key++;
                                        $expectedresult = new stdClass();
                                        $expectedresult->userexists = $userexists;
                                        $expectedresult->userdeleted = $userdeleted;
                                        $expectedresult->usersync = $usersync;
                                        $expectedresult->useridnumber = $useridnumber;
                                        $expectedresult->syncexists = $syncexists;
                                        $expectedresult->syncidnumber = $syncidnumber;
                                        $expectedresult->syncdeleted = $syncdeleted;
                                        if ($userexists) {
                                            // The user record exists, so create it.
                                            $userrecord = new stdClass();
                                            $userrecord->username = 'tsetorigusername' . $key;
                                            $userrecord->firstname = 'origname' . $key;
                                            $userrecord->totarasync = $usersync;
                                            if ($useridnumber) {
                                                $userrecord->idnumber = 'key' . $key;
                                            }

                                            $newuser = $generator->create_user($userrecord);
                                            $userrecord->deleted = $userdeleted;
                                            if ($userrecord->deleted) {
                                                // We delete after creating because deleting during creating doesn't work.
                                                delete_user($newuser);
                                            }
                                            $expectedcount++;

                                            $expectedresult->id = $newuser->id;
                                            $expectedresult->username = $userrecord->username;
                                            $expectedresult->firstname = $userrecord->firstname;
                                            $expectedresult->deleted = $userrecord->deleted;
                                            $expectedresult->totarasync = $userrecord->totarasync;
                                            if ($useridnumber) {
                                                $expectedresult->idnumber = $userrecord->idnumber;
                                            }
                                        }
                                        if ($syncexists) {
                                            // The sync record exists, so create it.
                                            $syncrecord = new stdClass();
                                            $syncrecord->timemodified = 0;
                                            $syncrecord->username = 'tsetsyncusername' . $key;
                                            $syncrecord->deleted = $syncdeleted;
                                            if ($syncidnumber) {
                                                $syncrecord->idnumber = 'key' . $key;
                                            } else {
                                                $syncrecord->idnumber = '';
                                            }

                                            // Check the conditions first in case we need to alter the inserted sync record.
                                            if ($userexists) {
                                                // There is also a user record, so we try to update.
                                                if ($usersync && $useridnumber) {
                                                    // The user record can be updated.
                                                    if ($syncidnumber) {
                                                        // An idnumber must be supplied for the changes to occur.
                                                        $expectedresult->idnumber = $syncrecord->idnumber;
                                                        $expectedresult->deleted = $syncrecord->deleted;
                                                        if (!$syncrecord->deleted) {
                                                            // Don't update the record if the sync record is set to delete.
                                                            $expectedresult->username = $syncrecord->username;
                                                        }
                                                    } else {
                                                        // The new record has no idnumber, so it is invalid.
                                                    }
                                                } else {
                                                    // The user record cannot be updated.
                                                    if ($syncidnumber) {
                                                        // The sync record is valid, so would create an extra user record.
                                                        // We want to avoid this situation for testing.
                                                        $syncrecord->idnumber = '';
                                                    }
                                                }
                                            } else {
                                                // There is no user record to update, so try to create a record.
                                                if ($syncidnumber && !$syncdeleted) {
                                                    // The new record should be created.
                                                    $expectedresult->username = $syncrecord->username;
                                                    $expectedresult->idnumber = $syncrecord->idnumber;
                                                    $expectedresult->deleted = $syncrecord->deleted;
                                                    $expectedresult->totarasync = true;
                                                    $expectedcount++;
                                                } else {
                                                    // The sync record has no idnumber or is a delete record for a
                                                    // non-existing user record, so it is invalid.
                                                }
                                            }

                                            // Create the sync record.
                                            if ($sourceallrecords) {
                                                unset($syncrecord->deleted);
                                                $syncrecord->syncid = $DB->insert_record($synctable, $syncrecord);
                                            } else {
                                                $syncrecord->syncid = $DB->insert_record($synctable, $syncrecord);
                                            }
                                        } else {
                                            // The sync record doesn't exist.
                                            if ($sourceallrecords && $usersync) {
                                                // So the user record should be not deleted.
                                                $expectedresult->deleted = false;
                                            }
                                        }
                                        // Add to the expected results array if we expect a result.
                                        if (!empty($expectedresult->id) || !empty($expectedresult->idnumber)) {
                                            $expectedresults[] = $expectedresult;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Clone the sync table now that it has the data in it.
            $synctable_clone = $source->get_sync_table_clone();

            // Create sync element with some functions mocked.
            $element = $this->getMock('totara_sync_element_user',
                    array('get_source', 'get_source_sync_table', 'get_source_sync_table_clone'));

            $element->expects($this->any())
                    ->method('get_source')
                    ->will($this->returnValue($source));

            $element->expects($this->any())
                    ->method('get_source_sync_table')
                    ->will($this->returnValue($synctable));

            $element->expects($this->any())
                    ->method('get_source_sync_table_clone')
                    ->will($this->returnValue($synctable_clone));

            $element->config->allow_create = true;
            $element->config->allow_update = true;
            $element->config->allow_delete = true;
            $element->config->sourceallrecords = $sourceallrecords;

            // Run sync.
            $element->sync();

            // Check that each expected record is present.
            foreach ($expectedresults as $expectedresult) {
                // Set up a string that describes the settings.
                $settings = "\n" .
                        "sourceallrecords = {$sourceallrecords}\n" .
                        "userexists = {$expectedresult->userexists}\n" .
                        "userdeleted = {$expectedresult->userdeleted}\n" .
                        "usersync = {$expectedresult->usersync}\n" .
                        "useridnumber = {$expectedresult->useridnumber}\n" .
                        "syncexists = {$expectedresult->syncexists}\n" .
                        "syncidnumber = {$expectedresult->syncidnumber}\n" .
                        "syncdeleted = {$expectedresult->syncdeleted}\n";

                // Check that the expected record exists and that there is just one.
                if (!empty($expectedresult->id)) {
                    $records = $DB->get_records('user', array('id' => $expectedresult->id));
                    $this->assertEquals(1, count($records),
                            "Incorrect number of users found with match on id = {$expectedresult->id}\n{$settings}");
                } else {
                    $records = $DB->get_records('user', array('idnumber' => $expectedresult->idnumber));
                    $this->assertEquals(1, count($records),
                            "Incorrect number of users found with match on idnumber = {$expectedresult->idnumber}\n{$settings}");
                }
                $finalresult = reset($records);

                // Check that the record contains the expected values.
                if (!empty($expectedresult->id)) {
                    $this->assertEquals($expectedresult->id, $finalresult->id,
                            "Unexpected result for id\n{$settings}");
                }
                if (!empty($expectedresult->idnumber)) {
                    $this->assertEquals($expectedresult->idnumber, $finalresult->idnumber,
                            "Unexpected result for idnumber\n{$settings}");
                }
                if (!empty($expectedresult->username)) {
                    $this->assertEquals($expectedresult->username, $finalresult->username,
                            "Unexpected result for username\n{$settings}");
                }
                if (!empty($expectedresult->firstname)) {
                    $this->assertEquals($expectedresult->firstname, $finalresult->firstname,
                            "Unexpected result for firstname\n{$settings}");
                }
                if (!empty($expectedresult->deleted)) {
                    $this->assertEquals((bool)$expectedresult->deleted, (bool)$finalresult->deleted,
                            "Unexpected result for deleted\n{$settings}");
                }
                if (!empty($expectedresult->totarasync)) {
                    $this->assertEquals($expectedresult->totarasync, $finalresult->totarasync,
                            "Unexpected result for totarasync\n{$settings}");
                }
            }

            // Check that there are no extra users.
            // If this assert fails, it might help to find the extra users by iterating over the users table and checking
            // that each user either existed before the test started or is in the expectedresults array.
            $finalcount = $DB->count_records('user');
            $this->assertEquals($expectedcount, $finalcount, 'Wrong number of users');
        }
    }
}
