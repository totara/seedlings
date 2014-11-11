<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * tool_generator course backend code.
 *
 * @package tool_generator
 * @copyright 2013 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Backend code for the 'make large course' tool.
 *
 * @package tool_generator
 * @copyright 2013 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_generator_course_backend extends tool_generator_backend {
    /**
     * @var array Number of sections in course
     */
    private static $paramsections = array(1, 10, 100, 500, 1000, 2000);
    /**
     * @var array Number of assignments in course
     */
    private static $paramassignments = array(1, 10, 100, 500, 1000, 2000);
    /**
     * @var array Number of Page activities in course
     */
    private static $parampages = array(1, 50, 200, 1000, 5000, 10000);
    /**
     * @var array Number of students enrolled in course
     */
    private static $paramusers = array(1, 100, 1000, 10000, 50000, 100000);
    /**
     * Total size of small files: 1KB, 1MB, 10MB, 100MB, 1GB, 2GB.
     *
     * @var array Number of small files created in a single file activity
     */
    private static $paramsmallfilecount = array(1, 64, 128, 1024, 16384, 32768);
    /**
     * @var array Size of small files (to make the totals into nice numbers)
     */
    private static $paramsmallfilesize = array(1024, 16384, 81920, 102400, 65536, 65536);
    /**
     * Total size of big files: 8KB, 8MB, 80MB, 800MB, 8GB, 16GB.
     *
     * @var array Number of big files created as individual file activities
     */
    private static $parambigfilecount = array(1, 2, 5, 10, 10, 10);
    /**
     * @var array Size of each large file
     */
    private static $parambigfilesize = array(8192, 4194304, 16777216, 83886080,
            858993459, 1717986918);
    /**
     * @var array Number of forum discussions
     */
    private static $paramforumdiscussions = array(1, 10, 100, 500, 1000, 2000);
    /**
     * @var array Number of forum posts per discussion
     */
    private static $paramforumposts = array(2, 2, 5, 10, 10, 10);
    /**
     * @var array Number of programs to be created
     */
    private static $paramprogramscount = array(1, 2, 5, 10, 10, 10);
    /**
     * @var array Number of certifications to be created
     */
    private static $paramcertificationscount = array(1, 2, 5, 10, 10, 10);
    /**
     * @var array Number of activities to be created based on the course size
     */
    private static $paramactivitiescount = array(2, 3, 4, 5, 6, 7);
    /**
     * @var array kind of activities to generate.
     */
    private static $activities = array('certificate', 'feedback', 'quiz', 'scorm', 'choice', 'facetoface', 'book');
    /**
     * @var array Number of positions to be used.
     */
    private static $parampositionscount = array(1, 2, 2, 2, 2, 2);
    /**
     * @var array Number of organisations to be used.
     */
    private static $paramorganisationscount = array(1, 2, 2, 2, 2, 2);
    /**
     * @var array Number of manager accounts to be used.
     */
    private static $parammanagersaccount = array(1, 2, 5, 10, 10, 10);
    /**
     * @var array Number of audience to be used/created.
     */
    private static $paramaudience = array(1, 2, 2, 2, 4, 4);

    /**
     * @var string Course shortname
     */
    private $shortname;

    /**
     * @var testing_data_generator Data generator
     */
    protected $generator;

    /**
     * @var cohort_generator Data generator for hierarchy
     */
    protected $cohort_generator;

    /**
     * @var hierarchy_generator Data generator for hierarchy
     */
    protected $hierarchy_generator;

    /**
     * @var program_generator Data generator for program
     */
    protected $program_generator;

    /**
     * @var stdClass Course object
     */
    private $course;

    /**
     * @var array Array from test user number (1...N) to userid in database
     */
    private $userids;

    /**
     * @var array Array of programs id
     */
    private $programids = array();

    /**
     * @var array Array of programs id
     */
    private $certificationids = array();

    /**
     * @var array Array of managers id
     */
    private $managerids = array();

    /**
     * @var array Array of organisations id
     */
    private $organisationids = array();

    /**
     * @var array Array of positions id
     */
    private $positionids = array();

    /**
     * @var array Array of audiences id
     */
    private $audienceids = array();

    /**
     * @var array Array of activities created.
     */
    private $activitiescreated = array();

    /**
     * @const string To identify manager account
     */
    const MANAGER_TOOL_GENERATOR = 'manager';

    /**
     * @const string To identify student account
     */
    const USER_TOOL_GENERATOR = 'user';

    /**
     * Constructs object ready to create course.
     *
     * @param string $shortname Course shortname
     * @param int $size Size as numeric index
     * @param bool $fixeddataset To use fixed or random data
     * @param int|bool $filesizelimit The max number of bytes for a generated file
     * @param bool $progress True if progress information should be displayed
     */
    public function __construct($shortname, $size, $fixeddataset = false, $filesizelimit = false, $progress = true) {

        // Set parameters.
        $this->shortname = $shortname;

        parent::__construct($size, $fixeddataset, $filesizelimit, $progress);
    }

    /**
     * Returns the relation between users and course sizes.
     *
     * @return array
     */
    public static function get_users_per_size() {
        return self::$paramusers;
    }

    /**
     * Gets a list of size choices supported by this backend.
     *
     * @return array List of size (int) => text description for display
     */
    public static function get_size_choices() {
        $options = array();
        for ($size = self::MIN_SIZE; $size <= self::MAX_SIZE; $size++) {
            $options[$size] = get_string('coursesize_' . $size, 'tool_generator');
        }
        return $options;
    }

    /**
     * Checks that a shortname is available (unused).
     *
     * @param string $shortname Proposed course shortname
     * @return string An error message if the name is unavailable or '' if OK
     */
    public static function check_shortname_available($shortname) {
        global $DB;
        $fullname = $DB->get_field('course', 'fullname',
                array('shortname' => $shortname), IGNORE_MISSING);
        if ($fullname !== false) {
            // I wanted to throw an exception here but it is not possible to
            // use strings from moodle.php in exceptions, and I didn't want
            // to duplicate the string in tool_generator, so I changed this to
            // not use exceptions.
            return get_string('shortnametaken', 'moodle', $fullname);
        }
        return '';
    }

    /**
     * Runs the entire 'make' process.
     *
     * @return int Course id
     */
    public function make() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/lib/phpunit/classes/util.php');

        raise_memory_limit(MEMORY_EXTRA);

        if ($this->progress && !CLI_SCRIPT) {
            echo html_writer::start_tag('ul');
        }

        $entirestart = microtime(true);

        // Start transaction.
        $transaction = $DB->start_delegated_transaction();

        // Get generator.
        $this->generator = phpunit_util::get_data_generator();

        // Make course.
        $this->course = $this->create_course();

        // Create students accounts.
        $this->create_users(self::USER_TOOL_GENERATOR, self::$paramusers[$this->size]);

        $this->create_totara_objects();

        // Create pages, small and big files, and forum.
        set_time_limit(0);
        $this->create_assignments();
        $this->create_pages();
        $this->create_small_files();
        $this->create_big_files();
        $this->create_forum();

        // Log total time.
        $this->log('coursecompleted', round(microtime(true) - $entirestart, 1));

        if ($this->progress && !CLI_SCRIPT) {
            echo html_writer::end_tag('ul');
        }

        // Commit transaction and finish.
        $transaction->allow_commit();
        return $this->course->id;
    }

    /**
     * Set custom data generators
     *
     */
    protected function set_customs_generators() {
        $this->hierarchy_generator = $this->generator->get_plugin_generator('totara_hierarchy');
        $this->cohort_generator = $this->generator->get_plugin_generator('totara_cohort');
        $this->program_generator = $this->generator->get_plugin_generator('totara_program');
        $this->completion_generator = $this->generator->get_plugin_generator('core_completion');
    }

    /**
     * Create Totara objects, such as: audiences, positions, organisations, managers,
     * assign primary position to students, create programs, etc.
     *
     */
    protected function create_totara_objects() {
        // Set custom data generators.
        $this->set_customs_generators();
        // Enable completion tracking.
        $this->completion_generator->enable_completion_tracking($this->course);
        // Create some activities.
        $this->create_activities();
        // Set completion for the activities created.
        $this->completion_generator->set_activity_completion($this->course->id, $this->activitiescreated, COMPLETION_AGGREGATION_ALL);
        // Create manager accounts.
        $this->create_users(self::MANAGER_TOOL_GENERATOR, self::$parammanagersaccount[$this->size]);
        // Create position framework.
        $posframework = $this->hierarchy_generator->create_framework('pos', 'pos_framework_tool_generator');
        // Create organisation framework.
        $orgframework = $this->hierarchy_generator->create_framework('org', 'org_framework_tool_generator');
        // Create/get organisations.
        $this->organisationids = $this->hierarchy_generator->get_hierarchies('organisation', $orgframework, self::$paramorganisationscount[$this->size]);
        // Create/get positions.
        $this->positionids = $this->hierarchy_generator->get_hierarchies('position', $posframework, self::$parampositionscount[$this->size]);
        // Create primary position for all students.
        $this->create_primary_position($this->userids, $this->managerids, $this->positionids, $this->organisationids);
        // Create audiences.
        $this->audienceids = $this->cohort_generator->create_audiences(self::$paramaudience[$this->size], $this->userids);
        // Create programs.
        if (totara_feature_visible('programs')) {
            // Create programs.
            $this->create_programs();
            // Assign users to program via cohort method.
            $this->assign_users_to_programs(ASSIGNTYPE_COHORT, $this->programids);
        }

        if (totara_feature_visible('certifications')) {
            // Create certifications
            $this->create_certifications();
            // Assign users to program via cohort method.
            $this->assign_users_to_programs(ASSIGNTYPE_COHORT, $this->certificationids);
        }

        $this->program_generator->fix_program_sortorder();
    }

    /**
     * Creates the actual course.
     *
     * @return stdClass Course record
     */
    private function create_course() {
        $this->log('createcourse', $this->shortname);
        $courserecord = array('shortname' => $this->shortname,
                'fullname' => get_string('fullname', 'tool_generator',
                    array('size' => get_string('shortsize_' . $this->size, 'tool_generator'))),
                'numsections' => self::$paramsections[$this->size]);
        return $this->generator->create_course($courserecord, array('createsections' => true));
    }

    /**
     * Creates a number of user accounts and enrols them on the course.
     * Note: Existing user accounts that were created by this system are
     * reused if available.
     *
     * @param string $usertype Type of user (user, manager, teacher, etc)
     * @param int $count Number of user account to create
     */
    private function create_users($usertype, $count) {
        global $DB;

        $username = $usertype . '_tool_generator_';

        // Get existing users in order. We will 'fill up holes' in this up to
        // the required number.
        $this->log('checkaccounts', $count);
        $nextnumber = 1;
        $rs = $DB->get_recordset_select('user', $DB->sql_like('username', '?'), array($username . '%'), 'username', 'id, username');
        foreach ($rs as $record) {
            // Extract number from username.
            $matches = tool_generator_backend::get_number_match($username, $record->username);
            if (empty($matches)) {
                continue;
            }

            // Create missing users in range up to this.
            $number = (int) $matches[1];
            if ($number != $nextnumber) {
                $this->create_user_accounts($nextnumber, min($number - 1, $count), $usertype);
            } else {
                $this->{$usertype .'ids'}[$number] = (int) $record->id;
            }

            // Stop if we've got enough users.
            $nextnumber = $number + 1;
            if ($number >= $count) {
                break;
            }
        }
        $rs->close();

        // Create users from end of existing range.
        if ($nextnumber <= $count) {
            $this->create_user_accounts($nextnumber, $count, $usertype);
        }

        if ($usertype === self::USER_TOOL_GENERATOR) { // Enrol users.
            $this->log('enrol', $count, true);
            $enrolplugin = enrol_get_plugin('manual');
            $instances = enrol_get_instances($this->course->id, true);
            foreach ($instances as $instance) {
                if ($instance->enrol === 'manual') {
                    break;
                }
            }
            if ($instance->enrol !== 'manual') {
                throw new coding_exception('No manual enrol plugin in course');
            }
            $role = $DB->get_record('role', array('shortname' => 'student'), '*', MUST_EXIST);

            for ($number = 1; $number <= $count; $number++) {
                // Enrol user.
                $enrolplugin->enrol_user($instance, $this->{$usertype . 'ids'}[$number], $role->id);
                $this->dot($number, $count);
            }
        }

        // Sets the pointer at the beginning to be aware of the users we use.
        reset($this->{$usertype . 'ids'});

        $this->end_log();
    }

    /**
     * Creates user accounts with a numeric range.
     *
     * @param int $first Number of first user
     * @param int $last Number of last user
     * @param int $usertype Type of user: common user or manager
     */
    private function create_user_accounts($first, $last, $usertype) {
        global $CFG;

        $this->log('createaccounts', (object)array('from' => $first, 'to' => $last), true);
        $count = $last - $first + 1;
        $done = 0;
        for ($number = $first; $number <= $last; $number++, $done++) {

            $username = $usertype . '_tool_generator_';
            $username = $username . str_pad($number, 6, '0', STR_PAD_LEFT);

            // Create user account.
            $record = array('firstname' => get_string('firstname', 'tool_generator'),
                    'lastname' => $number, 'username' => $username);

            // We add a user password if it has been specified.
            if (!empty($CFG->tool_generator_users_password)) {
                $record['password'] = $CFG->tool_generator_users_password;
            }

            $user = $this->generator->create_user($record);
            $this->{$usertype . 'ids'}[$number] = (int) $user->id;
            $this->dot($done, $count);
        }
        $this->end_log();
    }

    /**
     * Creates a number of Assignment activities.
     */
    private function create_assignments() {
        // Set up generator.
        $assigngenerator = $this->generator->get_plugin_generator('mod_assign');

        // Create assignments.
        $number = self::$paramassignments[$this->size];
        $this->log('createassignments', $number, true);
        for ($i = 0; $i < $number; $i++) {
            $record = array('course' => $this->course);
            $options = array('section' => $this->get_target_section());
            $assigngenerator->create_instance($record, $options);
            $this->dot($i, $number);
        }

        $this->end_log();
    }

    /**
     * Assign users to programs.
     *
     * @param string $method type of assignment (position, organisation, individuals, cohort)
     */
    private function assign_users_to_programs($method, $programids) {
        $this->log('assignusers');
        $programscount = count($programids);

        switch ($method) {
            case ASSIGNTYPE_COHORT:
                $item = $this->audienceids;
            break;
            case ASSIGNTYPE_POSITION:
                $item = $this->positionids;
            break;
            case ASSIGNTYPE_ORGANISATION:
                $item = $this->organisationids;
            break;
            case ASSIGNTYPE_INDIVIDUAL:
                $item = $this->userids;
            break;
        }

        if (count($item) > $programscount) {
            $size = floor(count($item) / $programscount);
            $items = array_chunk($item, $size);
        } else {
            $items = array_chunk($item, 1);
        }

        $itemscount = count($items);
        foreach ($programids as $programid) {
            $this->program_generator->assign_users_by_method($programid, $method, $items[rand(0, $itemscount-1)]);
        }
        $this->end_log();
    }

    /**
     * Creates a number of Page activities.
     */
    private function create_pages() {
        // Set up generator.
        $pagegenerator = $this->generator->get_plugin_generator('mod_page');

        // Create pages.
        $number = self::$parampages[$this->size];
        $this->log('createpages', $number, true);
        for ($i = 0; $i < $number; $i++) {
            $record = array('course' => $this->course);
            $options = array('section' => $this->get_target_section());
            $pagegenerator->create_instance($record, $options);
            $this->dot($i, $number);
        }

        $this->end_log();
    }

    /**
     * Creates one resource activity with a lot of small files.
     */
    private function create_small_files() {
        $count = self::$paramsmallfilecount[$this->size];
        $this->log('createsmallfiles', $count, true);

        // Create resource with default textfile only.
        $resourcegenerator = $this->generator->get_plugin_generator('mod_resource');
        $record = array('course' => $this->course,
                'name' => get_string('smallfiles', 'tool_generator'));
        $options = array('section' => 0);
        $resource = $resourcegenerator->create_instance($record, $options);

        // Add files.
        $fs = get_file_storage();
        $context = context_module::instance($resource->cmid);
        $filerecord = array('component' => 'mod_resource', 'filearea' => 'content',
                'contextid' => $context->id, 'itemid' => 0, 'filepath' => '/');
        for ($i = 0; $i < $count; $i++) {
            $filerecord['filename'] = 'smallfile' . $i . '.dat';

            // Generate random binary data (different for each file so it
            // doesn't compress unrealistically).
            $data = self::get_random_binary($this->limit_filesize(self::$paramsmallfilesize[$this->size]));

            $fs->create_file_from_string($filerecord, $data);
            $this->dot($i, $count);
        }

        $this->end_log();
    }

    /**
     * Creates programs for the course.
     *
     * @return nothing but saving the programs id created.
     */
    private function create_programs() {
        $count = self::$paramprogramscount[$this->size];
        $done = 0;
        $this->log('createprograms', $count, true);
        // Create programs.
        for ($i = 1; $i <= $count; $i++, $done++) {
            $programname = 'program_toolgenerator_' . $this->course->id . str_pad($i, 6, '0', STR_PAD_LEFT);
            $data = array('fullname' => $programname, 'shortname' => $programname);
            if ($newprogram = $this->program_generator->create_program($data)) {
                $this->programids[] = $newprogram->id;
                $this->dot($done, $count);
            }
        }

        // Assign this course to the programs created.
        foreach ($this->programids as $programid) {
            $this->program_generator->add_courseset_program($programid, array($this->course->id));
        }

        $this->end_log();
    }

    /**
     * Creates programs for the course.
     *
     * @return nothing but saving the programs id created.
     */
    private function create_certifications() {
        $count = self::$paramcertificationscount[$this->size];
        $done = 0;
        $this->log('createcertifications', $count, true);
        // Create programs.
        for ($i = 1; $i <= $count; $i++, $done++) {
            $programname = 'certification_toolgenerator_' . $this->course->id . str_pad($i, 6, '0', STR_PAD_LEFT);
            $data = array('fullname' => $programname, 'shortname' => $programname);
            if ($newprogram = $this->program_generator->create_program($data)) {
                // Get random activeperiod, windowperiod and recertifydatetype.
                list($actperiod, $winperiod, $recerttype) = $this->program_generator->get_random_certification_setting();
                // Covert this program in a certification.
                $this->program_generator->create_certification_settings($newprogram->id, $actperiod, $winperiod, $recerttype);
                $this->certificationids[] = $newprogram->id;
                $this->dot($done, $count);
            }
        }

        // Assign this course to the certifications created.
        // Make this course the certification and recertification path.
        foreach ($this->certificationids as $certificationid) {
            $this->program_generator->add_courseset_program($certificationid, array($this->course->id));
            $this->program_generator->add_courseset_program($certificationid, array($this->course->id), CERTIFPATH_RECERT);
        }

        $this->end_log();
    }

    /**
     * Creates activities for this course.
     *
     */
    protected function create_activities() {
        // Set up generator.
        $activitiescount = count(self::$activities) - 1;
        $number = self::$paramactivitiescount[$this->size];
        $this->log('createactivities', $number, true);
        for ($i = 1; $i <= $number; $i++) {
            $mod = 'mod_' . self::$activities[rand(0, $activitiescount)];
            $modgenerator = $this->generator->get_plugin_generator($mod);
            $record = array('course' => $this->course->id);
            $options = array(
                'section' => 0,
                'completion' => COMPLETION_TRACKING_AUTOMATIC,
                'completionview' => COMPLETION_VIEW_REQUIRED
            );
            if ($activity = $modgenerator->create_instance($record, $options)) {
                // Some activities has content. Check if the current activity has the create_content method implemented.
                $ref = new ReflectionClass($modgenerator);
                $method = $ref->getMethod('create_content');
                if ($method->class === get_class($modgenerator)) {
                    $modgenerator->create_content($activity);
                }
                $this->activitiescreated[] = $activity;
            }
            $this->dot($i, $number);
        }
        $this->end_log();
    }

    /**
     * Creates primary position for the given users.
     *
     * @param array $users Array of userids
     * @param array $managerids Array of managerids
     * @param array $positionids Array of positionids
     * @param array $organisationids Array of organisationids
     */
    private function create_primary_position($users, $managerids, $positionids, $organisationids) {
        $done = 0;
        $count = count($users);
        $this->log('assignhierarchy', $count);
        $managerscount = count($managerids);
        $positionscount = count($positionids);
        $organisationscount = count($organisationids);
        foreach ($users as $user) {
            $done++;
            $managerid = $managerids[rand(1, $managerscount)];
            $positionid = $positionids[rand(1, $positionscount)];
            $organisationid = $organisationids[rand(1, $organisationscount)];
            $this->hierarchy_generator->assign_primary_position($user, $managerid, $positionid, $organisationid);
            $this->dot($done, $count);
        }
        $this->end_log();
    }

    /**
     * Creates a string of random binary data. The start of the string includes
     * the current time, in an attempt to avoid large-scale repetition.
     *
     * @param int $length Number of bytes
     * @return Random data
     */
    private static function get_random_binary($length) {

        $data = microtime(true);
        if (strlen($data) > $length) {
            // Use last digits of data.
            return substr($data, -$length);
        }
        $length -= strlen($data);
        for ($j = 0; $j < $length; $j++) {
            $data .= chr(rand(1, 255));
        }
        return $data;
    }

    /**
     * Creates a number of resource activities with one big file each.
     */
    private function create_big_files() {
        global $CFG;

        // Work out how many files and how many blocks to use (up to 64KB).
        $count = self::$parambigfilecount[$this->size];
        $filesize = $this->limit_filesize(self::$parambigfilesize[$this->size]);
        $blocks = ceil($filesize / 65536);
        $blocksize = floor($filesize / $blocks);

        $this->log('createbigfiles', $count, true);

        // Prepare temp area.
        $tempfolder = make_temp_directory('tool_generator');
        $tempfile = $tempfolder . '/' . rand();

        // Create resources and files.
        $fs = get_file_storage();
        $resourcegenerator = $this->generator->get_plugin_generator('mod_resource');
        for ($i = 0; $i < $count; $i++) {
            // Create resource.
            $record = array('course' => $this->course,
                    'name' => get_string('bigfile', 'tool_generator', $i));
            $options = array('section' => $this->get_target_section());
            $resource = $resourcegenerator->create_instance($record, $options);

            // Write file.
            $handle = fopen($tempfile, 'w');
            if (!$handle) {
                throw new coding_exception('Failed to open temporary file');
            }
            for ($j = 0; $j < $blocks; $j++) {
                $data = self::get_random_binary($blocksize);
                fwrite($handle, $data);
                $this->dot($i * $blocks + $j, $count * $blocks);
            }
            fclose($handle);

            // Add file.
            $context = context_module::instance($resource->cmid);
            $filerecord = array('component' => 'mod_resource', 'filearea' => 'content',
                    'contextid' => $context->id, 'itemid' => 0, 'filepath' => '/',
                    'filename' => 'bigfile' . $i . '.dat');
            $fs->create_file_from_pathname($filerecord, $tempfile);
        }

        unlink($tempfile);
        $this->end_log();
    }

    /**
     * Creates one forum activity with a bunch of posts.
     */
    private function create_forum() {
        global $DB;

        $discussions = self::$paramforumdiscussions[$this->size];
        $posts = self::$paramforumposts[$this->size];
        $totalposts = $discussions * $posts;

        $this->log('createforum', $totalposts, true);

        // Create empty forum.
        $forumgenerator = $this->generator->get_plugin_generator('mod_forum');
        $record = array('course' => $this->course,
                'name' => get_string('pluginname', 'forum'));
        $options = array('section' => 0);
        $forum = $forumgenerator->create_instance($record, $options);

        // Add discussions and posts.
        $sofar = 0;
        for ($i = 0; $i < $discussions; $i++) {
            $record = array('forum' => $forum->id, 'course' => $this->course->id,
                    'userid' => $this->get_target_user());
            $discussion = $forumgenerator->create_discussion($record);
            $parentid = $DB->get_field('forum_posts', 'id', array('discussion' => $discussion->id), MUST_EXIST);
            $sofar++;
            for ($j = 0; $j < $posts - 1; $j++, $sofar++) {
                $record = array('discussion' => $discussion->id,
                        'userid' => $this->get_target_user(), 'parent' => $parentid);
                $forumgenerator->create_post($record);
                $this->dot($sofar, $totalposts);
            }
        }

        $this->end_log();
    }

    /**
     * Gets a section number.
     *
     * Depends on $this->fixeddataset.
     *
     * @return int A section number from 1 to the number of sections
     */
    private function get_target_section() {

        if (!$this->fixeddataset) {
            $key = rand(1, self::$paramsections[$this->size]);
        } else {
            // Using section 1.
            $key = 1;
        }

        return $key;
    }

    /**
     * Gets a user id.
     *
     * Depends on $this->fixeddataset.
     *
     * @return int A user id for a random created user
     */
    private function get_target_user() {

        if (!$this->fixeddataset) {
            $userid = $this->userids[rand(1, self::$paramusers[$this->size])];
        } else if ($userid = current($this->userids)) {
            // Moving pointer to the next user.
            next($this->userids);
        } else {
            // Returning to the beginning if we reached the end.
            $userid = reset($this->userids);
        }

        return $userid;
    }

    /**
     * Restricts the binary file size if necessary
     *
     * @param int $length The total length
     * @return int The limited length if a limit was specified.
     */
    private function limit_filesize($length) {

        // Limit to $this->filesizelimit.
        if (is_numeric($this->filesizelimit) && $length > $this->filesizelimit) {
            $length = floor($this->filesizelimit);
        }

        return $length;
    }

}
