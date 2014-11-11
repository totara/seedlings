<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas
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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A report builder source for the "user" table.
 */
class rb_source_user extends rb_base_source {

    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;
    /**
     * Whether the "staff_facetoface_sessions" report exists or not (used to determine
     * whether or not to display icons that link to it)
     * @var boolean
     */
    private $staff_f2f;

    /**
     * Constructor
     */
    public function __construct() {
        global $DB;
        $this->base = '{user}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = array();
        $this->staff_f2f = $DB->get_field('report_builder', 'id', array('shortname' => 'staff_facetoface_sessions'));
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_user');

        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    /**
     * Creates the array of rb_join objects required for this->joinlist
     *
     * @return array
     */
    protected function define_joinlist() {

        $joinlist = array(
            new rb_join(
                'totara_stats_comp_achieved',
                'LEFT',
                "(SELECT userid, count(data2) AS number
                    FROM {block_totara_stats}
                    WHERE eventtype = 4
                    GROUP BY userid)",
                'base.id = totara_stats_comp_achieved.userid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'totara_stats_courses_started',
                'LEFT',
                "(SELECT userid, COUNT(DISTINCT data2) as number
                    FROM {block_totara_stats}
                    WHERE eventtype = 2
                    GROUP BY userid)",
                'base.id = totara_stats_courses_started.userid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'totara_stats_courses_completed',
                'LEFT',
                "(SELECT userid, count(DISTINCT data2) AS number
                    FROM {block_totara_stats}
                    WHERE eventtype = 3
                    GROUP BY userid)",
                'base.id = totara_stats_courses_completed.userid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'prog_extension_count',
                'LEFT',
                "(SELECT userid, count(*) as extensioncount
                    FROM {prog_extension} pe
                    WHERE pe.userid = userid AND pe.status = 0
                    GROUP BY pe.userid)",
                'base.id = prog_extension_count.userid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            )
        );

        $this->add_user_table_to_joinlist($joinlist, 'base', 'id');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'id');
        $this->add_manager_tables_to_joinlist($joinlist, 'position_assignment', 'reportstoid');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'id');

        return $joinlist;
    }

    /**
     * Creates the array of rb_column_option objects required for
     * $this->columnoptions
     *
     * @return array
     */
    protected function define_columnoptions() {
        global $DB;

        $columnoptions = array();
        $this->add_user_fields_to_columns($columnoptions, 'base');
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);

        // A column to display a user's profile picture
        $columnoptions[] = new rb_column_option(
                        'user',
                        'userpicture',
                        get_string('userspicture', 'rb_source_user'),
                        'base.id',
                        array(
                            'displayfunc' => 'user_picture',
                            'noexport' => true,
                            'defaultheading' => get_string('picture', 'rb_source_user'),
                            'extrafields' => array(
                                'userpic_picture' => 'base.picture',
                                'userpic_firstname' => 'base.firstname',
                                'userpic_firstnamephonetic' => 'base.firstnamephonetic',
                                'userpic_middlename' => 'base.middlename',
                                'userpic_lastname' => 'base.lastname',
                                'userpic_lastnamephonetic' => 'base.lastnamephonetic',
                                'userpic_alternatename' => 'base.alternatename',
                                'userpic_imagealt' => 'base.imagealt',
                                'userpic_email' => 'base.email'
                            )
                        )
        );

        // A column to display the "My Learning" icons for a user
        $columnoptions[] = new rb_column_option(
                        'user',
                        'userlearningicons',
                        get_string('mylearningicons', 'rb_source_user'),
                        'base.id',
                        array(
                            'displayfunc' => 'learning_icons',
                            'noexport' => true,
                            'defaultheading' => get_string('options', 'rb_source_user')
                        )
        );

        // A column to display the number of achieved competencies for a user
        $columnoptions[] = new rb_column_option(
                        'statistics',
                        'competenciesachieved',
                        get_string('usersachievedcompcount', 'rb_source_user'),
                        'totara_stats_comp_achieved.number',
                        array(
                            'displayfunc' => 'count',
                            'joins' => 'totara_stats_comp_achieved',
                            'dbdatatype' => 'integer',
                        )
        );

        // A column to display the number of started courses for a user
        $columnoptions[] = new rb_column_option(
                        'statistics',
                        'coursesstarted',
                        get_string('userscoursestartedcount', 'rb_source_user'),
                        'totara_stats_courses_started.number',
                        array(
                            'displayfunc' => 'count',
                            'joins' => 'totara_stats_courses_started',
                            'dbdatatype' => 'integer',
                        )
        );

        // A column to display the number of completed courses for a user
        $columnoptions[] = new rb_column_option(
                        'statistics',
                        'coursescompleted',
                        get_string('userscoursescompletedcount', 'rb_source_user'),
                        'totara_stats_courses_completed.number',
                        array(
                            'displayfunc' => 'count',
                            'joins' => 'totara_stats_courses_completed',
                            'dbdatatype' => 'integer',
                        )
        );

        $columnoptions[] = new rb_column_option(
                        'user',
                        'namewithlinks',
                        get_string('usernamewithlearninglinks', 'rb_source_user'),
                        $DB->sql_fullname("base.firstname", "base.lastname"),
                        array(
                            'displayfunc' => 'user_with_links',
                            'defaultheading' => get_string('user', 'rb_source_user'),
                            'extrafields' => array(
                                'user_id' => 'base.id',
                                'userpic_picture' => 'base.picture',
                                'userpic_firstname' => 'base.firstname',
                                'userpic_firstnamephonetic' => 'base.firstnamephonetic',
                                'userpic_middlename' => 'base.middlename',
                                'userpic_lastname' => 'base.lastname',
                                'userpic_lastnamephonetic' => 'base.lastnamephonetic',
                                'userpic_alternatename' => 'base.alternatename',
                                'userpic_imagealt' => 'base.imagealt',
                                'userpic_email' => 'base.email'
                            ),
                            'dbdatatype' => 'char',
                            'outputformat' => 'text'
                        )
        );

        $columnoptions[] = new rb_column_option(
                        'user',
                        'extensionswithlink',
                        get_string('extensions', 'totara_program'),
                        'prog_extension_count.extensioncount',
                        array(
                            'joins' => 'prog_extension_count',
                            'displayfunc' => 'extension_link',
                        )
        );

        $this->add_cohort_user_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    /**
     * Creates the array of rb_filter_option objects required for $this->filteroptions
     * @return array
     */
    protected function define_filteroptions() {
        $filteroptions = array();

        $this->add_user_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_cohort_user_fields_to_filters($filteroptions);

        return $filteroptions;
    }


    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelinkicon',
            ),
            array(
                'type' => 'user',
                'value' => 'username',
            ),
            array(
                'type' => 'user',
                'value' => 'lastlogin',
            ),
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'user',
                'value' => 'fullname',
            ),
        );

        return $defaultfilters;
    }
    /**
     * Creates the array of rb_content_option object required for $this->contentoptions
     * @return array
     */
    protected function define_contentoptions() {
        // Include the rb_user_content content options for this report
        $contentoptions = array(
            new rb_content_option(
                'user',
                get_string('user', 'rb_source_user'),
                array(
                    'userid' => 'base.id',
                    'managerid' => 'position_assignment.managerid',
                    'managerpath' => 'position_assignment.managerpath',
                    'postype' => 'position_assignment.type',
                ),
                'position_assignment'
            ),
            new rb_content_option(
                'current_pos',
                get_string('currentpos', 'totara_reportbuilder'),
                'position.path',
                'position'
            ),
            new rb_content_option(
                'current_org',
                get_string('currentorg', 'totara_reportbuilder'),
                'organisation.path',
                'organisation'
            ),
            new rb_content_option(
                'date',
                get_string('timecreated', 'rb_source_user'),
                'base.timecreated'
            ),
        );
        return $contentoptions;
    }

    /**
     * A rb_column_options->displayfunc helper function to display the
     * "My Learning" icons for each user row
     *
     * @global object $CFG
     * @param integer $itemid ID of the user
     * @param object $row The rest of the data for the row
     * @return string
     */
    public function rb_display_learning_icons($itemid, $row) {
        global $CFG, $OUTPUT;

        static $systemcontext;
        if (!isset($systemcontext)) {
            $systemcontext = context_system::instance();
        }

        $disp = html_writer::start_tag('span', array('style' => 'white-space:nowrap;'));

        // Learning Records icon
        $disp .= html_writer::start_tag('a', array('href' => $CFG->wwwroot . '/totara/plan/record/index.php?userid='.$itemid));
        $disp .= html_writer::empty_tag('img',
            array('src' => $OUTPUT->pix_url('record', 'totara_core'), 'title' => get_string('learningrecords', 'totara_core')));
        $disp .= html_writer::end_tag('a');

        // Face To Face Bookings icon
        if ($this->staff_f2f) {
            $disp .= html_writer::start_tag('a', array('href' => $CFG->wwwroot . '/my/bookings.php?userid='.$itemid));
            $disp .= html_writer::empty_tag('img',
                array('src' => $OUTPUT->pix_url('bookings', 'totara_core'), 'title' => get_string('f2fbookings', 'totara_core')));
            $disp .= html_writer::end_tag('a');
        }

        // Individual Development Plans icon
        if (has_capability('totara/plan:accessplan', $systemcontext)) {
            $disp .= html_writer::start_tag('a', array('href' => $CFG->wwwroot . '/totara/plan/index.php?userid='.$itemid));
            $disp .= html_writer::empty_tag('img',
                array('src' => $OUTPUT->pix_url('plan', 'totara_core'), 'title' => get_string('learningplans', 'totara_plan')));
            $disp .= html_writer::end_tag('a');
        }

        $disp .= html_writer::end_tag('span');
        return $disp;
    }


    function rb_display_extension_link($extensioncount, $row, $isexport) {
        global $CFG;
        if (empty($extensioncount)) {
            return '0';
        }
        if (isset($row->user_id) && !$isexport) {
            return html_writer::link("{$CFG->wwwroot}/totara/program/manageextensions.php?userid={$row->user_id}", $extensioncount);
        } else {
            return $extensioncount;
        }
    }


    function rb_display_user_with_links($user, $row, $isexport = false) {
        global $CFG, $OUTPUT, $USER;
        $userid = $row->user_id;

        if ($isexport) {
            return $user;
        }

        $picuser = new stdClass();
        $picuser->id = $userid;
        $picuser->picture = $row->userpic_picture;
        $picuser->imagealt = $row->userpic_imagealt;
        $picuser->firstname = $row->userpic_firstname;
        $picuser->firstnamephonetic = $row->userpic_firstnamephonetic;
        $picuser->middlename = $row->userpic_middlename;
        $picuser->lastname = $row->userpic_lastname;
        $picuser->lastnamephonetic = $row->userpic_lastnamephonetic;
        $picuser->alternatename = $row->userpic_alternatename;
        $picuser->email = $row->userpic_email;
        $user_pic = $OUTPUT->user_picture($picuser, array('courseid' => 1));

        $recordstr = get_string('records', 'rb_source_user');
        $requiredstr = get_string('required', 'rb_source_user');
        $planstr = get_string('plans', 'rb_source_user');
        $profilestr = get_string('profile', 'rb_source_user');
        $bookingstr = get_string('bookings', 'rb_source_user');
        $appraisalstr = get_string('appraisals', 'totara_appraisal');
        $feedback360str = get_string('feedback360', 'totara_feedback360');
        $goalstr = get_string('goalplural', 'totara_hierarchy');
        $rol_link = html_writer::link("{$CFG->wwwroot}/totara/plan/record/index.php?userid={$userid}", $recordstr);
        $required_link = html_writer::link(new moodle_url('/totara/program/required.php',
                array('userid' => $userid)), $requiredstr);
        $plan_link = html_writer::link("{$CFG->wwwroot}/totara/plan/index.php?userid={$userid}", $planstr);
        $profile_link = html_writer::link("{$CFG->wwwroot}/user/view.php?id={$userid}", $profilestr);
        $booking_link = html_writer::link("{$CFG->wwwroot}/my/bookings.php?userid={$userid}", $bookingstr);
        $appraisal_link = html_writer::link("{$CFG->wwwroot}/totara/appraisal/index.php?subjectid={$userid}", $appraisalstr);
        $feedback_link = html_writer::link("{$CFG->wwwroot}/totara/feedback360/index.php?userid={$userid}", $feedback360str);
        $goal_link = html_writer::link("{$CFG->wwwroot}/totara/hierarchy/prefix/goal/mygoals.php?userid={$userid}", $goalstr);

        require_once($CFG->dirroot . '/totara/plan/lib.php');
        $show_plan_link = totara_feature_visible('learningplans') && dp_can_view_users_plans($userid);
        $links = html_writer::start_tag('ul');
        $links .= $show_plan_link ? html_writer::tag('li', $plan_link) : '';
        $links .= html_writer::tag('li', $profile_link);
        $links .= html_writer::tag('li', $booking_link);
        $links .= html_writer::tag('li', $rol_link);
        // Hide link for temporary managers.
        $tempman = totara_get_manager($userid, null, false, true);
        if ((!$tempman || $tempman->id != $USER->id) && totara_feature_visible('appraisals')) {
            $links .= html_writer::tag('li', $appraisal_link);
        }

        if (totara_feature_visible('feedback360')) {
            $links .= html_writer::tag('li', $feedback_link);
        }

        if (totara_feature_visible('goals')) {
            $links .= html_writer::tag('li', $goal_link);
        }

        if (totara_feature_visible('programs') || totara_feature_visible('certifications')) {
            $links .= html_writer::tag('li', $required_link);
        }

        $links .= html_writer::end_tag('ul');

        $user_tag = html_writer::link(new moodle_url("/user/profile.php", array('id' => $userid)), $user, array('class' => 'name'));

        $return = $user_pic . $user_tag . $links;

        return $return;
    }

    function rb_display_count($result) {
        return $result ? $result : 0;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'deleted',
                'base.deleted'
            ),
        );

        return $paramoptions;
    }
}

// end of rb_source_user class

