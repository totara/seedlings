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
 * Unit/functional tests to check Team Members reports caching
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_base_content.php');

class totara_reportbuilder_rb_team_members_embedded_cache_testcase extends reportcache_advanced_testcase {
    // testcase data
    protected $report_builder_data = array('id' => 17, 'fullname' => 'Team Members', 'shortname' => 'team_members',
                                           'source' => 'user', 'hidden' => 1, 'embedded' => 1, 'contentmode' => 2);

    protected $report_builder_columns_data = array(
        array('id' => 79, 'reportid' => 17, 'type' => 'user', 'value' => 'namewithlinks',
              'heading' => 'A', 'sortorder' => 1),
        array('id' => 80, 'reportid' => 17, 'type' => 'user', 'value' => 'lastlogin',
              'heading' => 'B', 'sortorder' => 2),
        array('id' => 81, 'reportid' => 17, 'type' => 'statistics', 'value' => 'coursesstarted',
              'heading' => 'C', 'sortorder' => 3),
        array('id' => 82, 'reportid' => 17, 'type' => 'statistics', 'value' => 'coursescompleted',
              'heading' => 'D', 'sortorder' => 4),
        array('id' => 83, 'reportid' => 17, 'type' => 'statistics', 'value' => 'competenciesachieved',
              'heading' => 'E', 'sortorder' => 5),
        array('id' => 84, 'reportid' => 17, 'type' => 'user', 'value' => 'extensionswithlink',
              'heading' => 'F', 'sortorder' => 6)
    );

    protected $report_builder_settings_data = array(
        array('id' => 5, 'reportid' => 17, 'type' => 'user_content', 'name' => 'enable', 'value' => '1'),
        array('id' => 6, 'reportid' => 17, 'type' => 'user_content',  'name' => 'who', 'value' => rb_user_content::USER_DIRECT_REPORTS)
    );

    // Work data
    protected $users = array();

    /**
     * Prepare mock data for testing
     *
     * Common part of all test cases:
     * - Add 6 users
     * - Make users0 as manager for users1,3,4,
     * - Make users1 as manager for users2,5
     *
     */
    protected function setUp() {
        parent::setup();

        // Common parts of test cases:
        // Create report record in database
        $this->loadDataSet($this->createArrayDataSet(array('report_builder' => array($this->report_builder_data),
                                                           'report_builder_columns' => $this->report_builder_columns_data,
                                                           'report_builder_settings' => $this->report_builder_settings_data)));
        for ($a = 0; $a <= 5; $a++)
        {
            $data = array();
            if ($a == 1 || $a == 3 || $a == 4) {
                $data = array('managerid' => $this->users[0]->id);
            } else if ($a == 2 || $a == 5) {
                $data = array('managerid' => $this->users[1]->id);
            }
            $this->users[] = $this->getDataGenerator()->create_user($data);
        }

    }

    /**
     * Test team mebers report
     * Test case:
     * - Common part (@see: self::setUp() )
     * - Report for admin user without staff 0
     * - Users0 has three members in team
     * - Users1 has two members in team
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_team_members($usecache) {
        $this->resetAfterTest();
        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }
        $useridalias = reportbuilder_get_extrafield_alias('user', 'namewithlinks', 'userpic_email');
        $result = $this->get_report_result($this->report_builder_data['shortname'], array(), $usecache);
        $this->assertCount(0, $result);

        $this->setUser($this->users[0]);
        $result = $this->get_report_result($this->report_builder_data['shortname'], array(), $usecache);
        $was = array();
        foreach($result as $r) {
            $this->assertContains($r->id, array($this->users[1]->id, $this->users[3]->id, $this->users[4]->id));
            $this->assertNotContains($r->$useridalias, $was);
            $was[] = $r->$useridalias;
        }

        $this->setUser($this->users[1]);
        $result = $this->get_report_result($this->report_builder_data['shortname'], array(), $usecache);
        $was = array();
        foreach($result as $r) {
            $this->assertContains($r->id, array($this->users[2]->id, $this->users[5]->id));
            $this->assertNotContains($r->$useridalias, $was);
            $was[] = $r->$useridalias;
        }
    }

    public function test_is_capable() {
        $this->resetAfterTest();

        // Set up report and embedded object for is_capable checks.
        $shortname = $this->report_builder_data['shortname'];
        $report = reportbuilder_get_embedded_report($shortname, array(), false, 0);
        $embeddedobject = $report->embedobj;
        $userid = $this->users[1]->id;

        // Test admin can access report.
        $this->assertTrue($embeddedobject->is_capable(2, $report),
                'admin cannot access report');

        // Test user can access report.
        $this->assertTrue($embeddedobject->is_capable($userid, $report),
                'user cannot access report');
    }
}
