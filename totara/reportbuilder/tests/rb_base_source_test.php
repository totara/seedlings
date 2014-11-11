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
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/tests/mock/rb_source_column_extra_id.php');

class totara_reportbuilder_rb_base_source_testcase extends PHPUnit_Framework_TestCase {
    /**
     * Test that report builder source doesn't allow 'id' as columns extrafields alias
     */
    public function test_column_extra_id() {
        try {
            $report = new rb_source_column_extra_id();
        } catch (ReportBuilderException $e) {
            $this->assertEquals(101, $e->getCode());
            return;
        }
        $this->fail('Column extrafields alias named \'id\' should not be allowed');
    }
}
