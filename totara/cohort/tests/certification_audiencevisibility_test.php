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
 * vendor/bin/phpunit totara_cohort_certification_audiencevisibility_testcase
 *
 */
class totara_cohort_certification_audiencevisibility_testcase extends reportcache_advanced_testcase {

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
    private $certif1 = null;
    private $certif2 = null;
    private $certif3 = null;
    private $certif4 = null;
    private $certif5 = null;
    private $certif6 = null;
    private $certif7 = null;
    private $certif8 = null;
    private $certif9 = null;
    private $audience1 = null;
    private $audience2 = null;
    private $category = null;
    private $cohort_generator = null;
    private $program_generator = null;

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

        // Set totara_program generator.
        $this->program_generator = $this->getDataGenerator()->get_plugin_generator('totara_program');

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

        // Create 4 certifications.
        $paramscert1 = array('fullname' => 'Visall', 'summary' => '', 'visible' => 0, 'audiencevisible' => COHORT_VISIBLE_ALL);
        $paramscert2 = array('fullname' => 'Visenronly', 'summary' => '', 'audiencevisible' => COHORT_VISIBLE_ENROLLED);
        $paramscert3 = array('fullname' => 'Visenrandmemb', 'summary' => '', 'visible' => 0,
                                'audiencevisible' => COHORT_VISIBLE_AUDIENCE);
        $paramscert4 = array('fullname' => 'Visnousers', 'summary' => '', 'audiencevisible' => COHORT_VISIBLE_NOUSERS);
        $this->certif1 = $this->getDataGenerator()->create_program($paramscert1); // Visibility all.
        $this->certif2 = $this->getDataGenerator()->create_program($paramscert2); // Visibility enrolled users only.
        $this->certif3 = $this->getDataGenerator()->create_program($paramscert3); // Visibility enrolled users and members.
        $this->certif4 = $this->getDataGenerator()->create_program($paramscert4); // Visibility no users.

        // Convert these programs in certifications.
        list($actperiod, $winperiod, $recerttype) = $this->program_generator->get_random_certification_setting();
        $this->program_generator->create_certification_settings($this->certif1->id, $actperiod, $winperiod, $recerttype);
        $this->program_generator->create_certification_settings($this->certif2->id, $actperiod, $winperiod, $recerttype);
        $this->program_generator->create_certification_settings($this->certif3->id, $actperiod, $winperiod, $recerttype);
        $this->program_generator->create_certification_settings($this->certif4->id, $actperiod, $winperiod, $recerttype);

        // Assign capabilities for user8, and user9.
        $syscontext = context_system::instance();
        $rolestaffmanager = $DB->get_record('role', array('shortname'=>'staffmanager'));
        role_assign($rolestaffmanager->id, $this->user8->id, $syscontext->id);
        assign_capability('totara/coursecatalog:manageaudiencevisibility', CAP_ALLOW, $rolestaffmanager->id, $syscontext);
        unassign_capability('totara/certification:viewhiddencertifications', $rolestaffmanager->id, $syscontext->id);

        $roletrainer = $DB->get_record('role', array('shortname'=>'teacher'));
        role_assign($roletrainer->id, $this->user9->id, $syscontext->id);
        assign_capability('totara/certification:viewhiddencertifications', CAP_ALLOW, $roletrainer->id, $syscontext);

        // Assign user1 to program1 visible to all.
        $this->getDataGenerator()->assign_program($this->certif1->id, array($this->user1->id));

        // Assign user1 and user2 to program2 visible to enrolled users only.
        $enrolledusers = array($this->user1->id, $this->user2->id);
        $this->getDataGenerator()->assign_program($this->certif2->id, $enrolledusers);

        // Assign user2 to certif3 visible to enrolled and members.
        $this->getDataGenerator()->assign_program($this->certif3->id, array($this->user2->id));

        // Asign user1 and user2 to certif4 visible to no users.
        $this->getDataGenerator()->assign_program($this->certif4->id, $enrolledusers);

        // Set category.
        $this->category = coursecat::get(1); // Miscelaneous category.

        // Assign audience1 and audience2 to program2.
        totara_cohort_add_association($this->audience1->id, $this->certif2->id, COHORT_ASSN_ITEMTYPE_CERTIF,
                                        COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience2->id, $this->certif2->id, COHORT_ASSN_ITEMTYPE_CERTIF,
                                        COHORT_ASSN_VALUE_VISIBLE);

        // Assign audience2 to certif3 and certif4.
        totara_cohort_add_association($this->audience2->id, $this->certif3->id, COHORT_ASSN_ITEMTYPE_CERTIF,
                                        COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience2->id, $this->certif4->id, COHORT_ASSN_ITEMTYPE_CERTIF,
                                        COHORT_ASSN_VALUE_VISIBLE);

        // Check the assignments were created correctly.
        $params = array('cohortid' => $this->audience1->id, 'instanceid' => $this->certif2->id,
                        'instancetype' => COHORT_ASSN_ITEMTYPE_CERTIF);
        $this->assertTrue($DB->record_exists('cohort_visibility', $params));
        $params = array('cohortid' => $this->audience2->id, 'instanceid' => $this->certif2->id,
                        'instancetype' => COHORT_ASSN_ITEMTYPE_CERTIF);
        $this->assertTrue($DB->record_exists('cohort_visibility', $params));
        $params = array('cohortid' => $this->audience2->id, 'instanceid' => $this->certif3->id,
                        'instancetype' => COHORT_ASSN_ITEMTYPE_CERTIF);
        $this->assertTrue($DB->record_exists('cohort_visibility', $params));
        $params = array('cohortid' => $this->audience2->id, 'instanceid' => $this->certif4->id,
                        'instancetype' => COHORT_ASSN_ITEMTYPE_CERTIF);
        $this->assertTrue($DB->record_exists('cohort_visibility', $params));
    }

    /**
     * Data provider for the enhancedcatalog_audiencevisibility function.
     *
     * @return array $data Array of data to be used by test_audiencevisibility.
     */
    public function users_audience_visibility() {
        $data = array(
            array('user' => 'user1', array('certif1', 'certif2', 'certif9'), array('certif3', 'certif4', 'certif7', 'certif8'), 1),
            array('user' => 'user2', array('certif1', 'certif2', 'certif3', 'certif9'), array('certif4', 'certif7', 'certif8'), 1),
            array('user' => 'user3', array('certif1', 'certif9'), array('certif2', 'certif3', 'certif4', 'certif7', 'certif8'), 1),
            array('user' => 'user4', array('certif1', 'certif9'), array('certif2', 'certif3', 'certif4', 'certif7', 'certif8'), 1),
            array('user' => 'user5', array('certif1', 'certif3', 'certif9'), array('certif2', 'certif4', 'certif7', 'certif8'),  1),
            array('user' => 'user6', array('certif1', 'certif3', 'certif9'), array('certif2', 'certif4', 'certif7', 'certif8'), 1),
            array('user' => 'user7', array('certif1', 'certif9'), array('certif2', 'certif3', 'certif4', 'certif7', 'certif8'),  1),
            array('user' => 'user8', array('certif1', 'certif2', 'certif3', 'certif4', 'certif9'), array('certif7', 'certif8'), 1),
            array('user' => 'user9', array('certif1', 'certif2', 'certif3', 'certif4', 'certif9'), array('certif7', 'certif8'), 1),
            array('user' => 'user10', array('certif1', 'certif2', 'certif3', 'certif4', 'certif7', 'certif8', 'certif9'),
                                        array(), 1),
            array('user' => 'user1', array('certif2', 'certif4', 'certif5', 'certif9'),
                                        array('certif1', 'certif3', 'certif6', 'certif7', 'certif8'), 0),
            array('user' => 'user2', array('certif2', 'certif4', 'certif5', 'certif9'),
                                        array('certif1', 'certif3', 'certif6', 'certif7', 'certif8'), 0),
            array('user' => 'user3', array('certif2', 'certif4', 'certif5', 'certif9'),
                                        array('certif1', 'certif3', 'certif6', 'certif7', 'certif8'), 0),
            array('user' => 'user5', array('certif2', 'certif4', 'certif5', 'certif9'),
                                        array('certif1', 'certif3', 'certif6', 'certif7', 'certif8'), 0),
            array('user' => 'user7', array('certif2', 'certif4', 'certif5', 'certif9'),
                                        array('certif1', 'certif3', 'certif6', 'certif7', 'certif8'), 0),
            array('user' => 'user8', array('certif2', 'certif4', 'certif5', 'certif9'),
                                        array('certif1', 'certif3', 'certif6', 'certif7', 'certif8'), 0),
            array('user' => 'user9', array('certif2', 'certif4', 'certif5', 'certif1', 'certif3', 'certif6', 'certif9'),
                                        array('certif7', 'certif8'), 0),
            array('user' => 'user10', array('certif1', 'certif2', 'certif3', 'certif4', 'certif5', 'certif6', 'certif7', 'certif8',
                                            'certif9'), array(), 0),
        );
        return $data;
    }

    /**
     * Test Audicence visibility
     * @param string $user User that will login to see the programs.
     * @param array $certificationsvisible Array of programs visible to the user
     * @param array $certificationsnotvisible Array of programs not visible to the user
     * @param int $audvisibilityon Setting for audience visibility (1 => ON, 0 => OFF)
     * @dataProvider users_audience_visibility
     */
    public function test_audiencevisibility($user, $certificationsvisible, $certificationsnotvisible, $audvisibilityon) {
        global $PAGE, $CFG;
        $this->resetAfterTest(true);

        // Turns ON the audiencevisibility setting.
        set_config('audiencevisibility', $audvisibilityon);
        $this->assertEquals($CFG->audiencevisibility, $audvisibilityon);

        $this->create_certifications_with_availability();
        if (!$audvisibilityon) {
            // Create new certifications and enrol users to them.
            $this->create_certifications_old_visibility();
        }

        // Make the test toogling the new catalog.
        for ($i = 1; $i <= 2; $i++) {
            // Toogle enhanced catalog.
            $newvalue = ($CFG->enhancedcatalog == 1) ? 0 : 1;
            set_config('enhancedcatalog', $newvalue);
            $this->assertEquals($CFG->enhancedcatalog, $newvalue);

            // Test #1: Login as $user and see what certifications he can see.
            self::setUser($this->{$user});
            if ($CFG->enhancedcatalog) {
                $content = $this->get_report_result('catalogcertifications', array(), false, array());
            } else {
                $programrenderer = $PAGE->get_renderer('totara_program');
                $content = $programrenderer->program_category(0, 'certification');
            }

            // Check how many certifications the user can see.
            $countprogramsvisbile = count($certificationsvisible);
            $this->assertEquals(totara_get_category_item_count($this->category->id, 'certification'), $countprogramsvisbile);
            $this->assertEquals(prog_get_programs_count($this->category, 'certification'), $countprogramsvisbile);

            // Check certifications loaded in dialogs are the same as the visible ones.
            $this->assertEmpty($this->get_diff_programs_in_dialog($certificationsvisible, $this->category->id));

            // Certifications visible to the user.
            foreach ($certificationsvisible as $certification) {
                list($visible, $access, $search) = $this->get_visible_info($this->{$user}, $content, $this->{$certification});
                $this->assertTrue($visible);
                // Test #2: Try to access them.
                $this->assertTrue($access);
                // Test #3: Try to do a search for certifications.
                if ($CFG->enhancedcatalog) {
                    $this->assertCount(1, $search);
                    $r = array_shift($search);
                    $this->assertEquals($this->{$certification}->fullname, $r->prog_progexpandlink);
                } else {
                    $this->assertInternalType('int', strpos($search, $this->{$certification}->fullname));
                }
            }

            // Certifications not visible to the user.
            foreach ($certificationsnotvisible as $certification) {
                list($visible, $access, $search) = $this->get_visible_info($this->{$user}, $content, $this->{$certification});
                $this->assertFalse($visible);
                // Test #2: Try to access them.
                $this->assertFalse($access);
                // Test #3: Try to do a search for certifications.
                if ($CFG->enhancedcatalog) {
                    $this->assertCount(0, $search);
                } else {
                    $this->assertInternalType('int', strpos($search, 'No programs were found'));
                }
            }
        }
    }

    /**
     * Determine visibility of a certification based on the content.
     * @param $user User that is viewing the certification
     * @param $content Content when a user access to find certifications
     * @param $certification The certification to evaluate
     * @return array Array that contains values related to the visibility of the program
     */
    protected function get_visible_info($user, $content, $certification) {
        global $PAGE, $CFG;
        $visible = false;

        $program = new program($certification->id);
        $access = $program->is_viewable($user) && $program->is_accessible($user);

        if ($CFG->enhancedcatalog) { // New catalog.
            $search = array();
            if (is_array($content)) {
                $search = totara_search_for_value($content, 'prog_progexpandlink', TOTARA_SEARCH_OP_EQUAL,
                                                    $certification->fullname);
                $visible = !empty($search);
            }
        } else { // Old Catalog.
            $visible = (strpos($content, $certification->fullname) != false);
            $programrenderer = $PAGE->get_renderer('totara_program');
            $search = $programrenderer->search_programs(array('search' => $certification->fullname), 'certification');
        }

        return array($visible, $access, $search);
    }

    /**
     * Create certifications with old visibility.
     */
    protected function create_certifications_old_visibility() {
        // Create certifications with old visibility.
        $paramsprogram1 = array('fullname' => 'certif5', 'summary' => '', 'visible' => 1);
        $paramsprogram2 = array('fullname' => 'certif6', 'summary' => '', 'visible' => 0);
        $this->certif5 = $this->getDataGenerator()->create_program($paramsprogram1); // Visible.
        $this->certif6 = $this->getDataGenerator()->create_program($paramsprogram2); // Invisible.

        // Convert these programs in certifications.
        list($actperiod, $winperiod, $recerttype) = $this->program_generator->get_random_certification_setting();
        $this->program_generator->create_certification_settings($this->certif5->id, $actperiod, $winperiod, $recerttype);
        $this->program_generator->create_certification_settings($this->certif6->id, $actperiod, $winperiod, $recerttype);

        // Assign users to the certifications.
        $this->getDataGenerator()->assign_program($this->certif5->id, array($this->user1->id));
        $this->getDataGenerator()->assign_program($this->certif6->id, array($this->user1->id, $this->user2->id));

        // Assig audience1 and audience2 to certif6 and certif5 respectively.
        totara_cohort_add_association($this->audience2->id, $this->certif6->id, COHORT_ASSN_ITEMTYPE_CERTIF,
                                        COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience1->id, $this->certif5->id, COHORT_ASSN_ITEMTYPE_CERTIF,
                                        COHORT_ASSN_VALUE_VISIBLE);
    }

    /**
     * Create programs with available fields.
     */
    protected function create_certifications_with_availability() {
        // Create programs based on available fields.
        $today = time();
        $from = $today - (5 * DAYSECS);
        $until = $today - DAYSECS;
        $paramsprogram7 = array('fullname' => 'certif7', 'summary' => '', 'available' => 0);
        $paramsprogram8 = array('fullname' => 'certif8', 'summary' => '', 'available' => 1, 'availablefrom' => $from,
                                'availableuntil' => $until );
        $paramsprogram9 = array('fullname' => 'certif9', 'summary' => '', 'available' => 1, 'availablefrom' => $from,
                                'availableuntil' => $today + DAYSECS );
        $this->certif7 = $this->getDataGenerator()->create_program($paramsprogram7); // Not available (Just admin).
        $this->certif8 = $this->getDataGenerator()->create_program($paramsprogram8); // Available for students(until yesterday).
        $this->certif9 = $this->getDataGenerator()->create_program($paramsprogram9); // Available for students(until tomorrow).

        // Convert these programs in certifications.
        list($actperiod, $winperiod, $recerttype) = $this->program_generator->get_random_certification_setting();
        $this->program_generator->create_certification_settings($this->certif7->id, $actperiod, $winperiod, $recerttype);
        $this->program_generator->create_certification_settings($this->certif8->id, $actperiod, $winperiod, $recerttype);
        $this->program_generator->create_certification_settings($this->certif9->id, $actperiod, $winperiod, $recerttype);

        // Assign users to the programs.
        $this->getDataGenerator()->assign_program($this->certif7->id, array($this->user1->id, $this->user2->id));
        $this->getDataGenerator()->assign_program($this->certif8->id, array($this->user1->id, $this->user2->id));
        $this->getDataGenerator()->assign_program($this->certif9->id, array($this->user1->id, $this->user2->id));

        // Assign audience1 to programs.
        totara_cohort_add_association($this->audience1->id, $this->certif7->id, COHORT_ASSN_ITEMTYPE_CERTIF,
                                        COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience1->id, $this->certif8->id, COHORT_ASSN_ITEMTYPE_CERTIF,
                                        COHORT_ASSN_VALUE_VISIBLE);
        totara_cohort_add_association($this->audience1->id, $this->certif9->id, COHORT_ASSN_ITEMTYPE_CERTIF,
                                        COHORT_ASSN_VALUE_VISIBLE);
    }

    /**
     * Get certifications visible in dialog and compare with the one the user should see.
     * @param $certificationsvisible Array of certifications the user is suposse to see in the system
     * @param $categoryid Category ID where thoses certifications($certificationsvisible) live
     * @return array
     */
    protected function get_diff_programs_in_dialog($certificationsvisible, $categoryid) {
        $certifications = certif_get_certifications($categoryid, "fullname ASC", 'p.id, p.fullname, p.sortorder, p.visible');
        $certifindialogs = array();
        $certifvisible = array();
        foreach ($certifications as $certification) {
            $certifindialogs[] = $certification->id;
        }
        foreach ($certificationsvisible as $certification) {
            $certifvisible[] = $this->{$certification}->id;
        }

        return array_diff($certifvisible, $certifindialogs);
    }
}
