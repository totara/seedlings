<?php // $Id$

/*
 * mod/feedback/rb_sources/rb_source_feedback_summary.php
 *
 * Report Builder source for generating summary (high-level) reports on feedback
 * activities.
 *
 * @copyright Catalyst IT Limited
 * @author Simon Coggins
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package totara
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_feedback_summary extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $sourcetitle;

    function __construct($groupid=null) {
        global $CFG;
        $this->base = '{feedback_completed}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_feedback_summary');

        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    protected function define_joinlist() {
        global $CFG, $DB;

        // get the trainer role's id (or set a dummy value)
        $trainerroleid = $DB->get_field('role', 'id', array('shortname' => 'trainer'));
        if (!$trainerroleid) {
            $trainerroleid = 0;
        }

        // to get access to position type constants
        require_once($CFG->dirroot . '/totara/hierarchy/prefix/position/lib.php');

        // joinlist for this source
        $joinlist = array(
            new rb_join(
                'feedback',
                'LEFT',
                '{feedback}',
                'feedback.id = base.feedback',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'session_value',
                'LEFT',
                // subquery as table
                "(SELECT i.feedback, v.value
                    FROM {feedback_item} i
                    JOIN {feedback_value} v
                        ON v.item=i.id AND i.typ='trainer')",
                'session_value.feedback = base.feedback',
                // potentially could be multiple trainer questions
                // in a feedback instance
                REPORT_BUILDER_RELATION_ONE_TO_MANY
            ),
            new rb_join(
                'sessiontrainer',
                'LEFT',
                '{facetoface_session_roles}',
                '(sessiontrainer.userid = ' .
                    $DB->sql_cast_char2int('session_value.value', true) . ' AND ' .
                    "sessiontrainer.roleid = $trainerroleid)",
                // potentially multiple trainers in a session
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                'session_value'
            ),
            new rb_join(
                'trainer',
                'LEFT',
                '{user}',
                'trainer.id = sessiontrainer.userid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'sessiontrainer'
            ),
            new rb_join(
                'trainer_position_assignment',
                'LEFT',
                '{pos_assignment}',
                '(trainer_position_assignment.userid = ' .
                    'sessiontrainer.userid AND
                    trainer_position_assignment.type = ' .
                    POSITION_TYPE_PRIMARY . ')',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'sessiontrainer'
            ),
            new rb_join(
                'trainer_position',
                'LEFT',
                '{pos}',
                'trainer_position.id = ' .
                    'trainer_position_assignment.positionid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'trainer_position_assignment'
            ),
            new rb_join(
                'trainer_organisation',
                'LEFT',
                '{org}',
                'trainer_organisation.id = ' .
                    'trainer_position_assignment.organisationid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'trainer_position_assignment'
            ),
        );

        // include some standard joins
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_course_table_to_joinlist($joinlist, 'feedback', 'course');
        // requires the course join
        $this->add_course_category_table_to_joinlist($joinlist,
            'course', 'category');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        // requires the position_assignment join
        $this->add_manager_tables_to_joinlist($joinlist,
            'position_assignment', 'reportstoid');
        $this->add_tag_tables_to_joinlist('course', $joinlist, 'feedback', 'course');
        return $joinlist;
    }

    protected function define_columnoptions() {
        global $DB;

        $columnoptions = array(
            new rb_column_option(
                'responses',
                'timecompleted',
                get_string('timecompleted', 'rb_source_feedback_questions'),
                'base.timemodified',
                array('displayfunc' => 'nice_datetime', 'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'feedback',
                'name',
                get_string('feedbackactivity', 'rb_source_feedback_questions'),
                'feedback.name',
                array('joins' => 'feedback',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'trainer',
                'id',
                get_string('trainerid', 'rb_source_feedback_questions'),
                'sessiontrainer.userid',
                array('joins' => 'sessiontrainer')
            ),
            new rb_column_option(
                'trainer',
                'fullname',
                get_string('trainerfullname', 'rb_source_feedback_questions'),
                $DB->sql_fullname('trainer.firstname', 'trainer.lastname'),
                array('joins' => 'trainer',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'trainer',
                'organisationid',
                get_string('trainerorgid', 'rb_source_feedback_questions'),
                'trainer_position_assignment.organisationid',
                array('joins' => 'trainer_position_assignment')
            ),
            new rb_column_option(
                'trainer',
                'organisation',
                get_string('trainerorg', 'rb_source_feedback_questions'),
                'trainer_organisation.fullname',
                array('joins' => 'trainer_organisation',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'trainer',
                'positionid',
                get_string('trainerposid', 'rb_source_feedback_questions'),
                'trainer_position_assignment.positionid',
                array('joins' => 'trainer_position_assignment')
            ),
            new rb_column_option(
                'trainer',
                'position',
                get_string('trainerpos', 'rb_source_feedback_questions'),
                'trainer_position.fullname',
                array('joins' => 'trainer_position',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
        );
        // include some standard columns
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_course_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_tag_fields_to_columns('course', $columnoptions);

        return $columnoptions;
    }


    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'feedback',
                'name',
                get_string('feedbackname', 'rb_source_feedback_questions'),
                'text'
            ),
            new rb_filter_option(
                'responses',
                'timecompleted',
                'Time completed',
                'date'
            ),
            new rb_filter_option(
                'trainer',
                'fullname',
                get_string('trainerfullname', 'rb_source_feedback_questions'),
                'text'
            ),
            new rb_filter_option(
                'trainer',
                'organisationid',
                get_string('trainerorg', 'rb_source_feedback_questions'),
                'select',
                array(
                    'selectfunc' => 'organisations_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
            new rb_filter_option(
                'trainer',
                'positionid',
                get_string('trainerpos', 'rb_source_feedback_questions'),
                'select',
                array(
                    'selectfunc' => 'positions_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
        );

        // include some standard filters
        $this->add_user_fields_to_filters($filteroptions);
        $this->add_course_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_tag_fields_to_filters('course', $filteroptions);

        return $filteroptions;
    }


    protected function define_contentoptions() {
        $contentoptions = array(
            new rb_content_option(
                'user',
                get_string('user', 'rb_source_feedback_questions'),
                array(
                    'userid' => 'base.userid',
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
                'tag',
                get_string('course', 'rb_source_feedback_questions'),
                'tagids.idlist',
                'tagids'
            ),
            new rb_content_option(
                'date',
                get_string('responsetime', 'rb_source_feedback_questions'),
                'base.timemodified'
            ),
        );
        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'userid',         // parameter name
                'base.userid'     // field
            ),
            new rb_param_option(
                'courseid',
                'feedback.course',
                'feedback'
            ),
        );

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
                'heading' => get_string('user', 'rb_source_feedback_questions'),
            ),
            array(
                'type' => 'course',
                'value' => 'courselink',
                'heading' => get_string('coursename', 'rb_source_feedback_questions'),
            ),
            array(
                'type' => 'feedback',
                'value' => 'name',
            ),
            array(
                'type' => 'responses',
                'value' => 'timecompleted',
            ),
        );

        return $defaultcolumns;
    }

    protected function define_defaultfilters() {

        $defaultfilters = array(
            array(
                'type' => 'course',
                'value' => 'fullname',
            ),
            array(
                'type' => 'user',
                'value' => 'fullname',
            ),
            array(
                'type' => 'feedback',
                'value' => 'name',
                'advanced' => 1,
            ),
            array(
                'type' => 'responses',
                'value' => 'timecompleted',
                'advanced' => 1,
            ),
        );


        return $defaultfilters;
    }


    //
    //
    // Methods for adding commonly used data to source definitions
    //
    //

    //
    // Join data
    //

    //
    // Column data
    //

    //
    // Filter data
    //

    //
    //
    // Source specific display functions
    //
    //

    //
    //
    // Source specific filter display methods
    //
    //


} // end of rb_source_feedback_summary class


