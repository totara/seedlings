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
 *
 * Unit/functional tests to check faceted search of programs
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}
global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');

class totara_reportbuilder_rb_catalogprograms_embedded_cache_testcase extends reportcache_advanced_testcase {
    // Testcase data.
    protected $report_builder_data = array('id' => 61, 'fullname' => 'Programs', 'shortname' => 'catalogprograms',
                                           'source' => 'program', 'hidden' => 1, 'embedded' => 1,
                                           'toolbarsearch' => 1);

    protected $report_builder_columns_data = array(
            array('id' => 254, 'reportid' => 61, 'type' => 'prog', 'value' => 'proglinkicon',
                  'heading' => 'A', 'sortorder' => 1),
            array('id' => 255, 'reportid' => 61, 'type' => 'prog', 'value' => 'summary',
                  'heading' => 'B', 'sortorder' => 2));

    protected $report_builder_filters_data = array(
            array('id' => 175, 'reportid' => 61, 'type' => 'prog', 'value' => 'fullname',
                  'sortorder' => 1, 'advanced' => 0));

    protected $report_builder_cf_filters_data = array(
            array('id' => 176, 'reportid' => 61, 'type' => 'prog', 'value' => 'custom_field_?_text',
                  'sortorder' => 3, 'advanced' => 1),
            array('id' => 177, 'reportid' => 61, 'type' => 'prog', 'value' => 'custom_field_?_text',
                  'sortorder' => 4, 'advanced' => 0));

    // Work data.
    protected $user1 = null;
    protected $user2 = null;
    protected $program1 = null;
    protected $program2 = null;
    protected $program3 = null;
    protected $program4 = null;

    /**
     * Prepare mock data for testing
     *
     * Common part of all test cases:
     * - Add four programs
     */
    protected function setUp() {
        parent::setup();

        // Common parts of test cases.
        // Create report record in database.
        $this->loadDataSet($this->createArrayDataSet(
            array('report_builder' => array($this->report_builder_data),
                  'report_builder_columns' => $this->report_builder_columns_data,
                  'report_builder_filters' => $this->report_builder_filters_data)));

        $this->program1 = $this->getDataGenerator()->create_program(array('fullname'=> 'Intro'));
        $this->program2 = $this->getDataGenerator()->create_program(array('fullname'=> 'Basics'));
        $this->program3 = $this->getDataGenerator()->create_program(array('fullname'=> 'Advanced'));
        $this->program4 = $this->getDataGenerator()->create_program(array('fullname'=> 'Pro'));
    }

    /**
     * Test programs report
     * Test case:
     * - Add two multi-select customfields (cf1 and cf2)
     * - Add two options to each multi-select customfield (op<1> cf"1", op[2]-cf[1], op1cf2, op2cf2)
     * - Enable op<1> cf"1" for program1
     * - Enable op[2]-cf[1] and op2cf2 for program2
     * - Enable op<1> cf"1", op[2]-cf[1], op1cf2, op2cf2 for program3
     * - Program4 has no enabled customfield options
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_programs($usecache) {
        $this->resetAfterTest();

        $cfids = $this->getDataGenerator()->add_multiselect_cf(array('cf1' => array('op<1> cf"1"', 'op[2]-cf[1]'),
                    'cf2' => array('op1cf2', 'op2cf2')), 'prog');
        $this->getDataGenerator()->set_multiselect_cf($this->program1, $cfids['cf1'],
                array('op<1> cf"1"'), 'program', 'prog');
        $this->getDataGenerator()->set_multiselect_cf($this->program2, $cfids['cf1'],
                array('op[2]-cf[1]'), 'program', 'prog');
        $this->getDataGenerator()->set_multiselect_cf($this->program2, $cfids['cf2'],
                array('op2cf2'), 'program', 'prog');
        $this->getDataGenerator()->set_multiselect_cf($this->program3, $cfids['cf1'],
                array('op<1> cf"1"', 'op[2]-cf[1]'), 'program', 'prog');
        $this->getDataGenerator()->set_multiselect_cf($this->program3, $cfids['cf2'],
                array('op1cf2', 'op2cf2'), 'program', 'prog');

        // Add CF filters.
        $this->report_builder_cf_filters_data[0]['value'] = "custom_field_{$cfids['cf1']}_text";
        $this->report_builder_cf_filters_data[1]['value'] = "custom_field_{$cfids['cf2']}_text";
        $this->loadDataSet($this->createArrayDataSet(array(
                        'report_builder_filters' => $this->report_builder_cf_filters_data)));

        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }

        // No restrictions.
        $result = $this->get_report_result($this->report_builder_data['shortname'], array(),
                $usecache);
        $this->assertCount(4, $result);

        // Check one cf, one option.
        $form = array(
            "prog-custom_field_{$cfids['cf1']}_text" =>
                array('operator' => 1, 'value' => array(md5('op<1> cf"1"') => 1)));

        $result = $this->get_report_result($this->report_builder_data['shortname'], array(),
                $usecache, $form);
        $this->assertCount(2, $result);
        foreach ($result as $res) {
            $this->assertTrue(in_array($res->prog_proglinkicon,
                    array('Intro', 'Advanced')));
        }

        // Check both cf.
        $form = array("prog-custom_field_{$cfids['cf1']}_text" =>
                        array('operator' => 1, 'value' => array(md5('op[2]-cf[1]') => 1)),
                      "prog-custom_field_{$cfids['cf2']}_text" =>
                        array('operator' => 1, 'value' => array(md5('op2cf2') => 1)));

        $result = $this->get_report_result($this->report_builder_data['shortname'], array(),
                $usecache, $form);
        $this->assertCount(2, $result);
        foreach ($result as $res) {
            $this->assertTrue(in_array($res->prog_proglinkicon,
                    array('Basics', 'Advanced')));
        }

        // Check only second cf, both options.
        $form = array("prog-custom_field_{$cfids['cf1']}_text" =>
                        array('operator' => 1, 'value' => array(md5('op<1> cf"1"') => 1)),
                      "prog-custom_field_{$cfids['cf2']}_text" =>
                        array('operator' => 1,
                              'value' => array(md5('op1cf2') => 1, md5('op2cf2') => 1)));

        $result = $this->get_report_result($this->report_builder_data['shortname'], array(),
                $usecache, $form);
        $this->assertCount(1, $result);
        foreach ($result as $res) {
            $this->assertTrue(in_array($res->prog_proglinkicon,
                    array('Advanced')));
        }
    }
}
