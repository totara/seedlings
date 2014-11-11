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
 * Unit/functional tests to check Courses reports caching
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
global $CFG;
require_once($CFG->dirroot . '/mod/facetoface/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');
require_once($CFG->dirroot.'/tag/lib.php');

class totara_reportbuilder_rb_findcourses_embedded_cache_testcase extends reportcache_advanced_testcase {
    // testcase data
    protected $report_builder_data = array('id' => 6, 'fullname' => 'Find Courses', 'shortname' => 'findcourses',
                                           'source' => 'courses', 'hidden' => 1, 'embedded' => 1);

    protected $report_builder_columns_data = array(
                        array('id' => 25, 'reportid' => 6, 'type' => 'course', 'value' => 'courselinkicon',
                              'heading' => 'A', 'sortorder' => 1),
                        array('id' => 26, 'reportid' => 6, 'type' => 'course_category', 'value' => 'namelink',
                              'heading' => 'B', 'sortorder' => 2),
                        array('id' => 27, 'reportid' => 6, 'type' => 'course', 'value' => 'startdate',
                              'heading' => 'C', 'sortorder' => 3),
                        array('id' => 28, 'reportid' => 6, 'type' => 'course', 'value' => 'mods',
                              'heading' => 'D', 'sortorder' => 4));

    protected $report_builder_filters_data = array(
                        array('id' => 12, 'reportid' => 6, 'type' => 'course', 'value' => 'name_and_summary',
                              'sortorder' => 1, 'advanced' => 0),
                        array('id' => 14, 'reportid' => 6, 'type' => 'course_category', 'value' => 'id',
                              'sortorder' => 2, 'advanced' => 1),
                        array('id' => 15, 'reportid' => 6, 'type' => 'course', 'value' => 'startdate',
                              'sortorder' => 3, 'advanced' => 1),
                        array('id' => 42, 'reportid' => 6, 'type' => 'tags', 'value' => 'tagids',
                              'sortorder' => 4, 'advanced' => 0));

    protected $report_builder_filters_additional_data = array(
                        array('id' => 47, 'reportid' => 6, 'type' => 'course', 'value' => 'visible',
                              'sortorder' => 5, 'advanced' => 0),
                        array('id' => 48, 'reportid' => 6, 'type' => 'course', 'value' => 'language',
                              'sortorder' => 6, 'advanced' => 1),
                        array('id' => 49, 'reportid' => 6, 'type' => 'cohort', 'value' => 'enrolledcoursecohortids',
                              'sortorder' => 7, 'advanced' => 1),
                        array('id' => 50, 'reportid' => 6, 'type' => 'course', 'value' => 'shortname',
                              'sortorder' => 8, 'advanced' => 0),
                        array('id' => 51, 'reportid' => 6, 'type' => 'course', 'value' => 'mods',
                              'sortorder' => 9, 'advanced' => 0),
                        array('id' => 52, 'reportid' => 6, 'type' => 'course', 'value' => 'idnumber',
                              'sortorder' => 10, 'advanced' => 0),
                        array('id' => 53, 'reportid' => 6, 'type' => 'tags', 'value' => 'course_tag_1',
                              'sortorder' => 11, 'advanced' => 0));

    protected $report_builder_columns_additinal_data = array(
                        array('id' => 90, 'reportid'=> 6, 'type' => 'tags', 'value' => 'tagnames',
                              'heading' => 'E', 'sortorder' => 5),
                        array('id' => 91, 'reportid'=> 6, 'type' => 'course', 'value' => 'shortname',
                              'heading' => 'F', 'sortorder' => 6),
                        array('id' => 92, 'reportid'=> 6, 'type' => 'tags', 'value' => 'course_tag_1',
                              'heading' => 'G', 'sortorder' => 7),
                        array('id' => 93, 'reportid'=> 6, 'type' => 'course', 'value' => 'coursetypeicon',
                              'heading' => 'H', 'sortorder' => 8),
                        array('id' => 94, 'reportid'=> 6, 'type' => 'course_category', 'value' => 'id',
                              'heading' => 'I', 'sortorder' => 9),
                        array('id' => 95, 'reportid'=> 6, 'type' => 'tags', 'value' => 'course_tag_2',
                              'heading' => 'J', 'sortorder' => 10),
                        array('id' => 96, 'reportid'=> 6, 'type' => 'course_category', 'value' => 'name',
                              'heading' => 'K', 'sortorder' => 11),
                        array('id' => 97, 'reportid'=> 6, 'type' => 'tags', 'value' => 'course_tag_3',
                              'heading' => 'L', 'sortorder' => 12),
                        array('id' => 98, 'reportid'=> 6, 'type' => 'course', 'value' => 'name_and_summary',
                              'heading' => 'M', 'sortorder' => 13),
                        array('id' => 99, 'reportid'=> 6, 'type' => 'course', 'value' => 'visible',
                              'heading' => 'N', 'sortorder' => 14),
                        array('id' => 100, 'reportid'=> 6, 'type' => 'course', 'value' => 'fullname',
                              'heading' => 'O', 'sortorder' => 15),
                        array('id' => 101, 'reportid'=> 6, 'type' => 'course', 'value' => 'language',
                              'heading' => 'P', 'sortorder' => 16));



    // Work data
    protected $user1 = null;
    protected $user2 = null;
    protected $user3 = null;
    protected $user4 = null;
    protected $course1 = null;
    protected $course2 = null;
    protected $course3 = null;
    protected $course4 = null;
    protected static $ind = 0;

    /**
     * Prepare mock data for testing
     *
     * Common part of all test cases:
     * - Add four users
     * - Add four courses
     * - Enroll users1,2,3 on course1
     * - Enroll users2,3 on course2
     * - Enroll user3 on course4
     * - User4 not enroled
     * - Course4 haven't enroled any users
     *
     */
    protected function setUp() {
        parent::setup();

        // Common parts of test cases:
        // Create report record in database
        $this->loadDataSet($this->createArrayDataSet(array('report_builder' => array($this->report_builder_data),
                                                           'report_builder_columns' => $this->report_builder_columns_data,
                                                           'report_builder_filters' => $this->report_builder_filters_data)));

        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->user3 = $this->getDataGenerator()->create_user();
        $this->user4 = $this->getDataGenerator()->create_user();

        $this->course1 = $this->getDataGenerator()->create_course(array('fullname'=> 'Into'));
        $this->course2 = $this->getDataGenerator()->create_course(array('fullname'=> 'Basics'));
        $this->course3 = $this->getDataGenerator()->create_course(array('fullname'=> 'Advanced'));
        $this->course4 = $this->getDataGenerator()->create_course(array('fullname'=> 'Pro'));

        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course1->id);
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course1->id);
        $this->getDataGenerator()->enrol_user($this->user3->id, $this->course1->id);
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course2->id);
        $this->getDataGenerator()->enrol_user($this->user3->id, $this->course2->id);
        $this->getDataGenerator()->enrol_user($this->user4->id, $this->course3->id);
    }

    /**
     * Test courses report
     * Test case:
     * - Common part (@see: self::setUp() )
     * - Find all courses
     * - Find courses with name 'Basics'
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_courses($usecache) {
        $this->resetAfterTest();
        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }

        $result = $this->get_report_result($this->report_builder_data['shortname'], array(), $usecache);
        $this->assertCount(4, $result);

        $form = array('course-name_and_summary' => array('operator' => 0, 'value' => 'Basics'));

        $result = $this->get_report_result($this->report_builder_data['shortname'], array(), $usecache, $form);
        $this->assertCount(1, $result);
        $this->assertEquals('Basics', array_shift($result)->course_courselinkicon);
    }

    /**
     * Test courses report
     * Test case:
     * - Common part (@see: self::setUp() )
     * - tags: course1(taga), course2(taga, tagb), course3 (tagc), course4(tagb, tagc)
     * - Find courses with only taga
     * - Find course that has tagc or tagb
     * - Find course with taga and tagb
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_courses_tags($usecache) {
        global $CFG, $DB;

        if ($DB->get_dbfamily() == 'mssql') {
            $this->markTestSkipped('Skip until MSSQL GROUP_CONCAT_D is implemented');
        }
        $this->resetAfterTest();
        // Enable and create tags
        $CFG->usetags = true;
        set_config('coursetagging', 1, 'moodlecourse');
        $itags = tag_add(array('taga', 'tagb', 'tagc'), 'official');
        $tags = array_flip($itags);

        // Add tags for courses
        $taga = array_slice($tags, 0, 1, true);
        $tagab = array_slice($tags, 0, 2, true);
        $tagbc = array_slice($tags, 1, 2, true);
        $this->add_tags_info($this->course1->id, $taga);
        $this->add_tags_info($this->course2->id, $tagab);
        $this->add_tags_info($this->course3->id, array_slice($tags, 2, 1, true)); //tagc
        $this->add_tags_info($this->course4->id, $tagbc);

        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }

        // Find courses with taga
        $form = array('tags-tagids' => array('operator' => 2, 'value' => $taga));
        $result = $this->get_report_result($this->report_builder_data['shortname'], array(), $usecache, $form);
        $this->assertCount(2, $result);
        $was = array();
        foreach($result as $r) {
            $this->assertContains($r->course_courselinkicon, array($this->course1->fullname,
                                                                   $this->course2->fullname));
            $this->assertNotContains($r->course_courselinkicon, $was);
            $was[] = $r->course_courselinkicon;
        }

        // Find course that has tagc or tagb
        $form = array('tags-tagids' => array('operator' => 1, 'value' => $tagbc));
        $result = $this->get_report_result($this->report_builder_data['shortname'], array(), $usecache, $form);
        $this->assertCount(3, $result, ($usecache) ? 'cached' : 'uncached');
        $was = array();
        foreach($result as $r) {
            $this->assertContains($r->course_courselinkicon, array($this->course2->fullname,
                                                                   $this->course3->fullname,
                                                                   $this->course4->fullname));
            $this->assertNotContains($r->course_courselinkicon, $was);
            $was[] = $r->course_courselinkicon;
        }
        // Find course with taga and tagb
        $form = array(
            'tags-tagids' => array('operator' => 2, 'value' => $tagab));
        $result = $this->get_report_result($this->report_builder_data['shortname'], array(), $usecache, $form);
        $this->assertCount(1, $result);
        $this->assertEquals($this->course2->fullname, array_shift($result)->course_courselinkicon);
    }

    /**
     * Enable additional filters and check their work
     * Test case:
     * - Common part (@see: self::setUp() )
     * - Enable all filters
     * - Enable all columns
     * - Make one course searchable using all enabled filters
     * - Create report using all enabled filters
     * - Check that course (and only this course) listed in report
     *
     * @param int $usecache Use cache or not (1/0)
     * @dataProvider provider_use_cache
     */
    public function test_all_filters($usecache) {
        global $CFG, $DB;

        if ($DB->get_dbfamily() == 'mssql') {
            $this->markTestSkipped('Skip until MSSQL GROUP_CONCAT_D is implemented');
        }

        $this->resetAfterTest();
        $coursedata = array(
            'fullname' => 'Big course',
            'shortname' => 'bigcourse',
            'idnumber' => '101',
            'startdate' => mktime(0, 0, 0, date("n"), date('j'), date('Y')),
            'visible' => 1

        );
        $thatcourse = $this->getDataGenerator()->create_course($coursedata,array('createsections' => true));

        // Enable and create tags
        $CFG->usetags = true;
        set_config('coursetagging', 1, 'moodlecourse');
        $itags = tag_add(array('taga', 'tagb', 'tagc'), 'official');
        $tags = array_flip($itags);
        $this->add_tags_info($thatcourse->id, $tags);

        // Add two mods
        $this->course_add_mod('url', $thatcourse->id);
        $this->course_add_mod('chat', $thatcourse->id);

        // Enable filters and columns
        $this->loadDataSet($this->createArrayDataSet(array(
            'report_builder_columns' => $this->report_builder_columns_additinal_data,
            'report_builder_filters' => $this->report_builder_filters_additional_data)));

        if ($usecache) {
            $this->enable_caching($this->report_builder_data['id']);
        }
        // Filter criteria
        $form = array(
            'course-name_and_summary' => array('operator' => 0, 'value' => 'Big'),
            'course-startdate' => array('operator' => 1,
                    'after' => mktime(0, 0, 0, date("n"), date('j')-1, date('Y')), 'before' => 0,
                    'daysafter' => 0, 'daysbefore' => 0),
            'course_category-id' => array('operator' => 0, 'value' => '1'),
            'course-visible' => 1,
            'course-shortname' => array('operator' => 0, 'value' => 'big'),
            'course-mods' => array('operator' => 2, 'value' => array('chat' => 1, 'url' => 1)),
            'course-idnumber' => array('operator' => 0, 'value' => '101'),
            'tags-course_tag_1' => 1,
            'tags-tagids' => array('operator' => 2, 'value' => $tags)
        );
        $result = $this->get_report_result($this->report_builder_data['shortname'], array(), $usecache, $form);
        $this->assertCount(1, $result);
        $r = array_shift($result);
        $this->assertEquals($thatcourse->fullname, $r->course_courselinkicon);
    }

    /**
     * Test case:
     * - Common part (@see: self::setUp() )
     * - Cache report
     * - Add Filter (with and without join)
     * - Add Column (with and without join)
     * - Check that report status changed
     */
    public function test_cache_course_settings() {
        $this->resetAfterTest();
        $this->enable_caching($this->report_builder_data['id']);
        $this->assertEquals(RB_CACHE_FLAG_OK, $this->get_report_cache_status($this->report_builder_data['shortname'], array()));

        $this->loadDataSet($this->createArrayDataSet(array(
            'report_builder_filters' => $this->report_builder_filters_additional_data)));

        $this->assertEquals(RB_CACHE_FLAG_CHANGED, $this->get_report_cache_status($this->report_builder_data['shortname'],
                                array()));

        $this->enable_caching($this->report_builder_data['id']);
        $this->assertEquals(RB_CACHE_FLAG_OK, $this->get_report_cache_status($this->report_builder_data['shortname'], array()));

        $this->loadDataSet($this->createArrayDataSet(array(
            'report_builder_columns' => $this->report_builder_columns_additinal_data)));

        $this->assertEquals(RB_CACHE_FLAG_CHANGED, $this->get_report_cache_status($this->report_builder_data['shortname'],
                                array()));
    }
    /**
     * Attach mock tags into a course
     * @param int $courseid - id of the course
     * @param array $tags
     */
    protected function add_tags_info($courseid, $tags) {
       tag_set('course', $courseid, $tags, 'course', context_course::instance($courseid)->id);
    }

    /**
     * Add mock module to course
     * No module specific data added through this method
     *
     * @param string $mod Module name
     * @param int $courseid Course id
     * @param int $sectionnum section number (not id)
     */
    public function course_add_mod($mod, $courseid, $sectionnum = 1) {
        global $DB;
        $course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
        $module = $DB->get_record('modules', array('name'=>$mod), '*', MUST_EXIST);
        course_create_sections_if_missing($courseid, $sectionnum);
        $section = $DB->get_record('course_sections', array('course'=>$courseid, 'section' => $sectionnum), '*', MUST_EXIST);

        // first add course_module record because we need the context
        $newcm = new stdClass();
        $newcm->course           = $course->id;
        $newcm->module           = $module->id;
        $newcm->section          = $section->id;
        $newcm->added            = time();
        $newcm->instance         = 0; // not known yet, will be updated later (this is similar to restore code)
        $newcm->visible          = 1;
        $newcm->groupmode        = 0;
        $newcm->groupingid       = 0;
        $newcm->groupmembersonly = 0;
        $newcm->showdescription  = 0;

        $cmid = add_course_module($newcm);
        course_add_cm_to_section($courseid, $cmid, $sectionnum);
    }

    public function test_is_capable() {
        $this->resetAfterTest();

        // Set up report and embedded object for is_capable checks.
        $shortname = $this->report_builder_data['shortname'];
        $report = reportbuilder_get_embedded_report($shortname, array(), false, 0);
        $embeddedobject = $report->embedobj;
        $userid = $this->user1->id;

        // Test admin can access report.
        $this->assertTrue($embeddedobject->is_capable(2, $report),
                'admin cannot access report');

        // Test user can access report.
        $this->assertTrue($embeddedobject->is_capable($userid, $report),
                'user cannot access report');
    }
}
