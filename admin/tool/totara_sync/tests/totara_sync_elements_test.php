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
            $this->assertEquals($expected[1], $valid, null);
        }
    }
}
