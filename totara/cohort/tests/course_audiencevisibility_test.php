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
 * @author Maria Torres <maria.torres@totaralms.com>
 * @package totara
 * @subpackage cohort
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/totara/reportbuilder/tests/reportcache_advanced_testcase.php');
require_once($CFG->dirroot . '/totara/cohort/lib.php');
require_once($CFG->dirroot . '/totara/core/utils.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir  . '/coursecatlib.php');

/**
 * Test audience visibility in courses.
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit totara_cohort_course_audiencevisibility_testcase
 *
 */
class totara_cohort_course_audiencevisibility_testcase extends reportcache_advanced_testcase {

    private $user1 = null;
    private $user2 = null;
    private $user3 = null;
    private $user4 = null;
    private $user5 = null;
    private $user6 = null;
    private $user7 = null;
    private $user8 = null;
    private $user9 = null;
    private $user10 = null;
    private $course1 = null;
    private $course2 = null;
    private $course3 = null;
    private $course4 = null;
    private $course5 = null;
    private $course6 = null;
    private $audience1 = null;
    private $audience2 = null;
    private $cohort_generator = null;

    /**
     * Setup.
     */
    public function setUp() {
        global $DB;
        parent::setup();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Set totara_cohort generator.
        $this->cohort_generator = $this->getDataGenerator()->get_plugin_generator('totara_cohort');

        // Create some users.
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->user3 = $this->getDataGenerator()->create_user();
        $this->user4 = $this->getDataGenerator()->create_user();
        $this->user5 = $this->getDataGenerator()->create_user();
        $this->user6 = $this->getDataGenerator()->create_user();
        $this->user7 = $this->getDataGenerator()->create_user();
        $this->user8 = $this->getDataGenerator()->create_user(); // User with manage audience visibility cap in syscontext.
        $this->user9 = $this->getDataGenerator()->create_user(); // User with view hidden courses cap in syscontext.
        $this->user10 = $this->getDataGenerator()->create_user(); // User with view hidden courses cap in the course context.

        // Create audience1.
        $this->audience1 = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_STATIC));
        $this->assertTrue($DB->record_exists('cohort', array('id' => $this->audience1->id)));
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $this->audience1->id)));

        // Assign user3 and user4 to the audience1.
        $this->cohort_generator->cohort_assign_users($this->audience1->id, array($this->user3->id, $this->user4->id));
        $this->assertEquals(2, $DB->count_records('cohort_members', array('cohortid' => $this->audience1->id)));

        // Create audience2.
        $this->audience2 = $this->cohort_generator->create_cohort(array('cohorttype' => cohort::TYPE_STATIC));
        $this->assertTrue($DB->record_exists('cohort', array('id' => $this->audience2->id)));
        $this->assertEquals(0, $DB->count_records('cohort_members', array('cohortid' => $this->audience2->id)));

        // Assign user5 and user6 to the audience2.
        $this->cohort_generator->cohort_assign_users($this->audience2->id, array($this->user5->id, $this->user6->id));
        $this->assertEquals(2, $DB->count_records('cohort_members', array('cohortid' => $this->audience2->id)));

        // Create 4 couses.
        $paramscourse1 = array('fullname' => 'Visall', 'summary' => '', 'visible' => 0, 'audiencevisible' => COHORT_VISIBLE_ALL);
        $paramscourse2 = array('fullname' => 'Visenronly', 'summary' => '', 'audiencevisible' => COHORT_VISIBLE_ENROLLED);
        $paramscourse3 = array('fullname' => 'Visenrandmemb', 'summary' => '', 'visible' => 0,
                                'audiencevisible' => COHORT_VISIBLE_AUDIENCE);
        $paramscourse4 = array('fullname' => 'Visnousers', 'summary' => '', 'audiencevisible' => COHORT_VISIBLE_NOUSERS);
        $this->course1 = $this->getDataGenerator()->create_course($paramscourse1); // Visibility all.
        $this->course2 = $this->getDataGenerator()->create_course($paramscourse2); // Visibility enrolled users only.
        $this->course3 = $this->getDataGenerator()->create_course($paramscourse3); // Visibility enrolled users and members.
        $this->course4 = $this->getDataGenerator()->create_course($paramscourse4); // Visibility no users.

        // Enrol user1 into course1 visible to all.
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course1->id);

        // Enrol user1 and user2 into course2 visible to enrolled users only.
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course2->id);
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course2->id);

        // Enrol user2 into course3 visible to enrolled and members.
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course3->id);

        // Enrol user1 and user2 into course3 visible to no users.
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course4->id);
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course4->id);

        // Assign capabilities for user8, user9 and user10.
        $syscontext = context_system::instance();
        $rolestaffmanager = $DB->get_record('role', array('shortname'=>'staffmanager'));
        role_assign($rolestaffmanager->id, $this->user8->id, $syscontext->id);
        assign_capability('totara/coursecatalog:manageaudiencevisibility', CAP_ALLOW, $rolestaffmanager->id, $syscontext);
        unassign_capability('moodle/course:viewhiddencourses', $rolestaffmanager->id, $syscontext->id);

        $roletrainer = $DB->get_record('role', array('shortname'=>'teacher'));
        role_assign($roletrainer->id, $this->user9->id, $syscontext->id);
        assign_capability('moodle/course:viewhiddencourses', CAP_ALLOW, $roletrainer->id, $syscontext);

        $roleeditingtrainer = $DB->get_record('role', array('shortname'=>'editingteacher'));
        $manualplugin = enrol_get_plugin('manual');
        $maninstance = $DB->get_record('enrol', array('courseid'=>$this->course3->id, 'enrol'=>'manual'), '*', MUST_EXIST);
        $manualplugin->enrol_user($maninstance, $this->user10->id, $roleeditingtrainer->id);

        // Assig audience1 and audience2 to course2.
        totara_cohort_add_association($this->audience1->id, $this->course2->id,
                                        COHORT_ASSN_ITEMTYPE_COURSE, COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience2->id, $this->course2->id,
                                        COHORT_ASSN_ITEMTYPE_COURSE, COHORT_ASSN_VALUE_VISIBLE);

        // Assign audience2 to course3 and course4.
        totara_cohort_add_association($this->audience2->id, $this->course3->id,
                                        COHORT_ASSN_ITEMTYPE_COURSE, COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience2->id, $this->course4->id,
                                        COHORT_ASSN_ITEMTYPE_COURSE, COHORT_ASSN_VALUE_VISIBLE);

        // Check the assignments were created correctly.
        $params = array('cohortid' => $this->audience1->id, 'instanceid' => $this->course2->id,
                            'instancetype' => COHORT_ASSN_ITEMTYPE_COURSE);
        $this->assertTrue($DB->record_exists('cohort_visibility', $params));
        $params = array('cohortid' => $this->audience2->id, 'instanceid' => $this->course2->id,
                            'instancetype' => COHORT_ASSN_ITEMTYPE_COURSE);
        $this->assertTrue($DB->record_exists('cohort_visibility', $params));
        $params = array('cohortid' => $this->audience2->id, 'instanceid' => $this->course3->id,
                            'instancetype' => COHORT_ASSN_ITEMTYPE_COURSE);
        $this->assertTrue($DB->record_exists('cohort_visibility', $params));
        $params = array('cohortid' => $this->audience2->id, 'instanceid' => $this->course4->id,
                            'instancetype' => COHORT_ASSN_ITEMTYPE_COURSE);
        $this->assertTrue($DB->record_exists('cohort_visibility', $params));
    }

    /**
     * Data provider for the audiencevisibility function.
     *
     * @return array $data Data to be used by test_audiencevisibility.
     */
    public function users_audience_visibility() {
        $data = array(
            array('user' => 'user1', array('course1', 'course2'), array('course3', 'course4'), 1),
            array('user' => 'user2', array('course1', 'course2', 'course3'), array('course4'), 1),
            array('user' => 'user3', array('course1'), array('course2', 'course3', 'course4'), 1),
            array('user' => 'user4', array('course1'), array('course2', 'course3', 'course4'), 1),
            array('user' => 'user5', array('course1', 'course3'), array('course2', 'course4'), 1),
            array('user' => 'user6', array('course1', 'course3'), array('course2', 'course4'), 1),
            array('user' => 'user7', array('course1'), array('course2', 'course3', 'course4'), 1),
            array('user' => 'user8', array('course1', 'course2', 'course3', 'course4'), array(), 1),
            array('user' => 'user9', array('course1', 'course2', 'course3', 'course4'), array(), 1),
            array('user' => 'user10', array('course1', 'course3'), array('course2', 'course4'), 1),
            array('user' => 'user1', array('course2', 'course4', 'course5'), array('course1', 'course3', 'course6'), 0),
            array('user' => 'user2', array('course2', 'course4', 'course5'), array('course1', 'course3', 'course6'), 0),
            array('user' => 'user3', array('course2', 'course4', 'course5'), array('course1', 'course3', 'course6'), 0),
            array('user' => 'user5', array('course2', 'course4', 'course5'), array('course1', 'course3', 'course6'), 0),
            array('user' => 'user7', array('course2', 'course4', 'course5'), array('course1', 'course3', 'course6'), 0),
            array('user' => 'user8', array('course2', 'course4'), array('course1', 'course3', 'course6'), 0),
            array('user' => 'user9', array('course2', 'course4', 'course1', 'course3', 'course6'), array(), 0),
            array('user' => 'user10', array('course2', 'course4', 'course3'), array('course1', 'course6'), 0),
        );
        return $data;
    }

    /**
     * Test Audicence visibility.
     * @param string $user User that will login to see the courses
     * @param array $coursesvisible Array of courses visible to the user
     * @param array $coursesnotvisible Array of courses not visible to the user
     * @param $audvisibilityon Setting for audience visibility (1 => ON, 0 => OFF)
     * @dataProvider users_audience_visibility
     */
    public function test_audiencevisibility($user, $coursesvisible, $coursesnotvisible, $audvisibilityon) {
        global $PAGE, $CFG;
        $this->resetAfterTest(true);

        // Set audiencevisibility setting.
        set_config('audiencevisibility', $audvisibilityon);
        $this->assertEquals($CFG->audiencevisibility, $audvisibilityon);

        if (!$audvisibilityon) {
            // Create new courses and enrol users to them.
            $this->create_courses_old_visibility();
        }

        // Make the test toogling the new catalog.
        for ($i = 1; $i <= 2; $i++) {
            // Toogle enhanced catalog.
            $newvalue = ($CFG->enhancedcatalog == 1) ? 0 : 1;
            set_config('enhancedcatalog', $newvalue);
            $this->assertEquals($CFG->enhancedcatalog, $newvalue);

            // Test #1: Login as $user and see what courses he can see.
            self::setUser($this->{$user});
            if ($CFG->enhancedcatalog) {
                $content = $this->get_report_result('catalogcourses', array(), false, array());
            } else {
                $courserenderer = $PAGE->get_renderer('core', 'course');
                $content = $courserenderer->course_category(0);
            }

            // Courses visible to the user.
            foreach ($coursesvisible as $course) {
                list($visible, $access, $search) = $this->get_visible_info($CFG->audiencevisibility, $content, $this->{$course});
                $this->assertTrue($visible);
                // Test #2: Try to access them.
                $this->assertTrue($access);
                // Test #3: Try to do a search for courses.
                if ($CFG->enhancedcatalog) {
                    $this->assertCount(1, $search);
                    $r = array_shift($search);
                    $this->assertEquals($this->{$course}->fullname, $r->course_courseexpandlink);
                } else {
                    $this->assertInternalType('int', strpos($search, $this->{$course}->fullname));
                }
            }

            // Courses not visible to the user.
            foreach ($coursesnotvisible as $course) {
                list($visible, $access, $search) = $this->get_visible_info($CFG->audiencevisibility, $content, $this->{$course});
                $this->assertFalse($visible);
                // Test #2: Try to access them.
                $this->assertFalse($access);
                // Test #3: Try to do a search for courses.
                if ($CFG->enhancedcatalog) {
                    $this->assertCount(0, $search);
                } else {
                    $this->assertInternalType('int', strpos($search, 'No courses were found'));
                }
            }
        }
    }

    /**
     * Determine visibility of a course based on the content.
     * @param $audiencevisibility
     * @param $content Content when a user access to find certifications
     * @param $course The course to evaluate
     * @return array Array that contains values related to the visibility of the course
     */
    protected function get_visible_info($audiencevisibility, $content, $course) {
        global $PAGE, $CFG;
        $visible = false;

        if ($audiencevisibility) {
            $access = check_access_audience_visibility('course', $course);
        } else {
            $access = $course->visible ||
                has_capability('moodle/course:viewhiddencourses', context_course::instance($course->id));
        }

        if ($CFG->enhancedcatalog) { // New catalog.
            $search = array();
            if (is_array($content)) {
                $search = totara_search_for_value($content, 'course_courseexpandlink', TOTARA_SEARCH_OP_EQUAL, $course->fullname);
                $visible = !empty($search);
            }
        } else { // Old Catalog.
            $visible = (strpos($content, $course->fullname) != false);
            $courserenderer = $PAGE->get_renderer('core', 'course');
            $search = $courserenderer->search_courses(array('search' => $course->fullname));
        }

        return array($visible, $access, $search);
    }

    /**
     * Create courses with old visibility.
     */
    protected function create_courses_old_visibility() {
        // Create course with old visibility.
        $paramscourse1 = array('fullname' => 'course5', 'summary' => '', 'visible' => 1);
        $paramscourse2 = array('fullname' => 'course6', 'summary' => '', 'visible' => 0);
        $this->course5 = $this->getDataGenerator()->create_course($paramscourse1); // Visible.
        $this->course6 = $this->getDataGenerator()->create_course($paramscourse2); // Invisible.
        // Enrol users to the courses.
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course5->id);
        $this->getDataGenerator()->enrol_user($this->user1->id, $this->course6->id);
        $this->getDataGenerator()->enrol_user($this->user2->id, $this->course6->id);
        // Assig audience1 and audience2 to course6 and course 5 respectively.
        totara_cohort_add_association($this->audience2->id, $this->course6->id, COHORT_ASSN_ITEMTYPE_COURSE, COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience1->id, $this->course5->id, COHORT_ASSN_ITEMTYPE_COURSE, COHORT_ASSN_VALUE_VISIBLE);
    }
}
