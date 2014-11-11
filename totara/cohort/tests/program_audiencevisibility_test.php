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
 * Test audience visibility in programs and certifications.
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit totara_cohort_program_audiencevisibility_testcase
 *
 */
class totara_cohort_program_audiencevisibility_testcase extends reportcache_advanced_testcase {

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
    private $program1 = null;
    private $program2 = null;
    private $program3 = null;
    private $program4 = null;
    private $program5 = null;
    private $program6 = null;
    private $program7 = null;
    private $program8 = null;
    private $program9 = null;
    private $audience1 = null;
    private $audience2 = null;
    private $category = null;
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
        $this->user9 = $this->getDataGenerator()->create_user(); // User with view hidden programs cap in syscontext.
        $this->user10 = get_admin(); // Admin user.

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

        // Create 4 programs.
        $paramsprog1 = array('fullname' => 'Visall', 'summary' => '', 'visible' => 0, 'audiencevisible' => COHORT_VISIBLE_ALL);
        $paramsprog2 = array('fullname' => 'Visenronly', 'summary' => '', 'audiencevisible' => COHORT_VISIBLE_ENROLLED);
        $paramsprog3 = array('fullname' => 'Visenrandmemb', 'summary' => '', 'visible' => 0,
                                'audiencevisible' => COHORT_VISIBLE_AUDIENCE);
        $paramsprog4 = array('fullname' => 'Visnousers', 'summary' => '', 'audiencevisible' => COHORT_VISIBLE_NOUSERS);
        $this->program1 = $this->getDataGenerator()->create_program($paramsprog1); // Visibility all.
        $this->program2 = $this->getDataGenerator()->create_program($paramsprog2); // Visibility enrolled users only.
        $this->program3 = $this->getDataGenerator()->create_program($paramsprog3); // Visibility enrolled users and members.
        $this->program4 = $this->getDataGenerator()->create_program($paramsprog4); // Visibility no users.

        // Assign capabilities for user8 and user9.
        $syscontext = context_system::instance();
        $rolestaffmanager = $DB->get_record('role', array('shortname'=>'staffmanager'));
        role_assign($rolestaffmanager->id, $this->user8->id, $syscontext->id);
        assign_capability('totara/coursecatalog:manageaudiencevisibility', CAP_ALLOW, $rolestaffmanager->id, $syscontext);
        unassign_capability('totara/program:viewhiddenprograms', $rolestaffmanager->id, $syscontext->id);

        $roletrainer = $DB->get_record('role', array('shortname'=>'teacher'));
        role_assign($roletrainer->id, $this->user9->id, $syscontext->id);
        assign_capability('totara/program:viewhiddenprograms', CAP_ALLOW, $roletrainer->id, $syscontext);

        // Assign user1 to program1 visible to all.
        $this->getDataGenerator()->assign_program($this->program1->id, array($this->user1->id));

        // Assign user1 and user2 to program2 visible to enrolled users only.
        $enrolledusers = array($this->user1->id, $this->user2->id);
        $this->getDataGenerator()->assign_program($this->program2->id, $enrolledusers);

        // Assign user2 to program3 visible to enrolled and members.
        $this->getDataGenerator()->assign_program($this->program3->id, array($this->user2->id));

        // Asign user1 and user2 to program3 visible to no users.
        $this->getDataGenerator()->assign_program($this->program4->id, $enrolledusers);

        // Set category.
        $this->category = coursecat::get(1); // Miscelaneous category.

        // Assign audience1 and audience2 to program2.
        totara_cohort_add_association($this->audience1->id, $this->program2->id,
                                        COHORT_ASSN_ITEMTYPE_PROGRAM, COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience2->id, $this->program2->id,
                                        COHORT_ASSN_ITEMTYPE_PROGRAM, COHORT_ASSN_VALUE_VISIBLE);

        // Assign audience2 to program3 and program4.
        totara_cohort_add_association($this->audience2->id, $this->program3->id,
                                        COHORT_ASSN_ITEMTYPE_PROGRAM, COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience2->id, $this->program4->id,
                                        COHORT_ASSN_ITEMTYPE_PROGRAM, COHORT_ASSN_VALUE_VISIBLE);

        // Check the assignments were created correctly.
        $params = array('cohortid' => $this->audience1->id, 'instanceid' => $this->program2->id,
                            'instancetype' => COHORT_ASSN_ITEMTYPE_PROGRAM);
        $this->assertTrue($DB->record_exists('cohort_visibility', $params));
        $params = array('cohortid' => $this->audience2->id, 'instanceid' => $this->program2->id,
                            'instancetype' => COHORT_ASSN_ITEMTYPE_PROGRAM);
        $this->assertTrue($DB->record_exists('cohort_visibility', $params));
        $params = array('cohortid' => $this->audience2->id, 'instanceid' => $this->program3->id,
                            'instancetype' => COHORT_ASSN_ITEMTYPE_PROGRAM);
        $this->assertTrue($DB->record_exists('cohort_visibility', $params));
        $params = array('cohortid' => $this->audience2->id, 'instanceid' => $this->program4->id,
                            'instancetype' => COHORT_ASSN_ITEMTYPE_PROGRAM);
        $this->assertTrue($DB->record_exists('cohort_visibility', $params));
    }

    /**
     * Data provider for the enhancedcatalog_audiencevisibility function.
     *
     * @return array $data Data to be used by test_audiencevisibility
     */
    public function users_audience_visibility() {
        $data = array(
            array('user' => 'user1', array('program1', 'program2', 'program9'),
                                        array('program3', 'program4', 'program7', 'program8'), 1),
            array('user' => 'user2', array('program1', 'program2', 'program3', 'program9'),
                                        array('program4', 'program7', 'program8'), 1),
            array('user' => 'user3', array('program1', 'program9'),
                                        array('program2', 'program3', 'program4', 'program7', 'program8'), 1),
            array('user' => 'user4', array('program1', 'program9'),
                                        array('program2', 'program3', 'program4', 'program7', 'program8'), 1),
            array('user' => 'user5', array('program1', 'program3', 'program9'),
                                        array('program2', 'program4', 'program7'),  1),
            array('user' => 'user6', array('program1', 'program3', 'program9'),
                                        array('program2', 'program4', 'program7'), 1),
            array('user' => 'user7', array('program1', 'program9'),
                                        array('program2', 'program3', 'program4', 'program7'),  1),
            array('user' => 'user8', array('program1', 'program2', 'program3', 'program4', 'program9'),
                                        array('program7', 'program8'), 1),
            array('user' => 'user9', array('program1', 'program2', 'program3', 'program4', 'program9'),
                                        array('program7', 'program8'), 1),
            array('user' => 'user10', array('program1', 'program2', 'program3', 'program4', 'program7', 'program8', 'program9'),
                                        array(), 1),
            array('user' => 'user1', array('program2', 'program4', 'program5', 'program9'),
                                        array('program1', 'program3', 'program6', 'program7', 'program8'), 0),
            array('user' => 'user2', array('program2', 'program4', 'program5', 'program9'),
                                        array('program1', 'program3', 'program6', 'program7', 'program8'), 0),
            array('user' => 'user3', array('program2', 'program4', 'program5', 'program9'),
                                        array('program1', 'program3', 'program6', 'program7', 'program8'), 0),
            array('user' => 'user5', array('program2', 'program4', 'program5', 'program9'),
                                        array('program1', 'program3', 'program6', 'program7', 'program8'), 0),
            array('user' => 'user7', array('program2', 'program4', 'program5', 'program9'),
                                        array('program1', 'program3', 'program6', 'program7', 'program8'), 0),
            array('user' => 'user8', array('program2', 'program4', 'program5', 'program9'),
                                        array('program1', 'program3', 'program6', 'program7', 'program8'), 0),
            array('user' => 'user9', array('program2', 'program4', 'program5', 'program1', 'program3', 'program6', 'program9'),
                                        array('program7', 'program8'), 0),
            array('user' => 'user10', array('program1', 'program2', 'program3', 'program4', 'program5', 'program6', 'program7',
                                            'program8', 'program9'), array(), 0),
        );
        return $data;
    }

    /**
     * Test Audicence visibility.
     * @param string $user User that will login to see the programs
     * @param array $programsvisible Array of programs visible to the user
     * @param array $programsnotvisible Array of programs not visible to the user
     * @param int $audvisibilityon Setting for audience visibility (1 => ON, 0 => OFF)
     * @dataProvider users_audience_visibility
     */
    public function test_audiencevisibility($user, $programsvisible, $programsnotvisible, $audvisibilityon) {
        global $PAGE, $CFG;
        $this->resetAfterTest(true);

        // Turns ON the audiencevisibility setting.
        set_config('audiencevisibility', $audvisibilityon);
        $this->assertEquals($CFG->audiencevisibility, $audvisibilityon);

        $this->create_programs_with_availability();
        if (!$audvisibilityon) {
            // Create new programs and enrol users to them.
            $this->create_programs_old_visibility();
        }

        // Make the test toogling the new catalog.
        for ($i = 1; $i <= 2; $i++) {
            // Toogle enhanced catalog.
            $newvalue = ($CFG->enhancedcatalog == 1) ? 0 : 1;
            set_config('enhancedcatalog', $newvalue);
            $this->assertEquals($CFG->enhancedcatalog, $newvalue);

            // Test #1: Login as $user and see what programs he can see.
            self::setUser($this->{$user});
            if ($CFG->enhancedcatalog) {
                $content = $this->get_report_result('catalogprograms', array(), false, array());
            } else {
                $programrenderer = $PAGE->get_renderer('totara_program');
                $content = $programrenderer->program_category(0, 'program');
            }

            // Check how many programs the user can see.
            $countprogramsvisbile = count($programsvisible);
            $this->assertEquals(totara_get_category_item_count($this->category->id, 'program'), $countprogramsvisbile);
            $this->assertEquals(prog_get_programs_count($this->category, 'program'), $countprogramsvisbile);

            // Check programs loaded in dialogs are the same as the visible ones.
            $this->assertEmpty($this->get_diff_programs_in_dialog($programsvisible, $this->category->id));

            // Programs visible to the user.
            foreach ($programsvisible as $program) {
                list($visible, $access, $search) = $this->get_visible_info($this->{$user}, $content, $this->{$program});
                $this->assertTrue($visible);
                // Test #2: Try to access them.
                $this->assertTrue($access);
                // Test #3: Try to do a search for programs.
                if ($CFG->enhancedcatalog) {
                    $this->assertCount(1, $search);
                    $r = array_shift($search);
                    $this->assertEquals($this->{$program}->fullname, $r->prog_progexpandlink);
                } else {
                    $this->assertInternalType('int', strpos($search, $this->{$program}->fullname));
                }
            }

            // Programs not visible to the user.
            foreach ($programsnotvisible as $program) {
                list($visible, $access, $search) = $this->get_visible_info($this->{$user}, $content, $this->{$program});
                $this->assertFalse($visible);
                // Test #2: Try to access them.
                $this->assertFalse($access);
                // Test #3: Try to do a search for programs.
                if ($CFG->enhancedcatalog) {
                    $this->assertCount(0, $search);
                } else {
                    $this->assertInternalType('int', strpos($search, 'No programs were found'));
                }
            }
        }
    }

    /**
     * Determine visibility of a program based on the content.
     * @param $user User that is accessing the program
     * @param $content Content when a user access to find programs
     * @param $program The program to evaluate
     * @return array Array that contains values related to the visibility of the program
     */
    protected function get_visible_info($user, $content, $program) {
        global $PAGE, $CFG;
        $visible = false;

        $program = new program($program->id);
        $access = $program->is_viewable($user) && $program->is_accessible($user);

        if ($CFG->enhancedcatalog) { // New catalog.
            $search = array();
            if (is_array($content)) {
                $search = totara_search_for_value($content, 'prog_progexpandlink', TOTARA_SEARCH_OP_EQUAL, $program->fullname);
                $visible = !empty($search);
            }
        } else { // Old Catalog.
            $visible = (strpos($content, $program->fullname) != false);
            $programrenderer = $PAGE->get_renderer('totara_program');
            $search = $programrenderer->search_programs(array('search' => $program->fullname), 'program');
        }

        return array($visible, $access, $search);
    }

    /**
     * Create programs with old visibility.
     */
    protected function create_programs_old_visibility() {
        // Create program with old visibility.
        $paramsprogram1 = array('fullname' => 'program5', 'summary' => '', 'visible' => 1);
        $paramsprogram2 = array('fullname' => 'program6', 'summary' => '', 'visible' => 0);
        $this->program5 = $this->getDataGenerator()->create_program($paramsprogram1); // Visible.
        $this->program6 = $this->getDataGenerator()->create_program($paramsprogram2); // Invisible.

        // Assign users to the programs.
        $this->getDataGenerator()->assign_program($this->program5->id, array($this->user1->id));
        $this->getDataGenerator()->assign_program($this->program6->id, array($this->user1->id, $this->user2->id));

        // Assign audience1 and audience2 to programs.
        totara_cohort_add_association($this->audience2->id, $this->program6->id,
                                        COHORT_ASSN_ITEMTYPE_PROGRAM, COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience1->id, $this->program5->id,
                                        COHORT_ASSN_ITEMTYPE_PROGRAM, COHORT_ASSN_VALUE_VISIBLE);
    }

    /**
     * Create programs with available fields.
     */
    protected function create_programs_with_availability() {
        // Create programs based on available fields.
        $today = time();
        $from = $today - (5 * DAYSECS);
        $until = $today - DAYSECS;
        $paramsprogram7 = array('fullname' => 'program7', 'summary' => '', 'available' => 0);
        $paramsprogram8 = array('fullname' => 'program8', 'summary' => '', 'available' => 1, 'availablefrom' => $from,
                                'availableuntil' => $until );
        $paramsprogram9 = array('fullname' => 'program9', 'summary' => '', 'available' => 1, 'availablefrom' => $from,
                                'availableuntil' => $today + DAYSECS );
        $this->program7 = $this->getDataGenerator()->create_program($paramsprogram7); // Not available (Just admin).
        $this->program8 = $this->getDataGenerator()->create_program($paramsprogram8); // Available for students(until yesterday).
        $this->program9 = $this->getDataGenerator()->create_program($paramsprogram9); // Available for students(until tomorrow).
        // Assign users to the programs.
        $this->getDataGenerator()->assign_program($this->program7->id, array($this->user1->id, $this->user2->id));
        $this->getDataGenerator()->assign_program($this->program8->id, array($this->user1->id, $this->user2->id));
        $this->getDataGenerator()->assign_program($this->program9->id, array($this->user1->id, $this->user2->id));
        // Assign audience1 to programs.
        totara_cohort_add_association($this->audience1->id, $this->program7->id, COHORT_ASSN_ITEMTYPE_PROGRAM, COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience1->id, $this->program8->id, COHORT_ASSN_ITEMTYPE_PROGRAM, COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience1->id, $this->program9->id, COHORT_ASSN_ITEMTYPE_PROGRAM, COHORT_ASSN_VALUE_VISIBLE);
    }

    /**
     * Get programs visible in dialog and compare with the one the user should see.
     * @param $programsvisible Array programs user is suposse to see in the system
     * @param $categoryid Category ID where thoses programs($programsvisible) live
     * @return array
     */
    protected function get_diff_programs_in_dialog($programsvisible, $categoryid) {
        $programs = prog_get_programs($categoryid, "fullname ASC", 'p.id, p.fullname, p.sortorder, p.visible', 'program');
        $progindialogs = array();
        $progvisible = array();
        foreach ($programs as $program) {
            $progindialogs[] = $program->id;
        }
        foreach ($programsvisible as $program) {
            $progvisible[] = $this->{$program}->id;
        }

        return array_diff($progvisible, $progindialogs);
    }
}
