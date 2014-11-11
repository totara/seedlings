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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package mod_facetoface
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_facetoface_sessions extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $sourcetitle, $requiredcolumns;

    function __construct() {
        global $CFG;
        $this->base = '{facetoface_signups}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_facetoface_sessions');
        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    protected function define_joinlist() {
        global $CFG;
        require_once($CFG->dirroot .'/mod/facetoface/lib.php');

        // joinlist for this source
        $joinlist = array(
            new rb_join(
                'sessions',
                'LEFT',
                '{facetoface_sessions}',
                'sessions.id = base.sessionid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'facetoface',
                'LEFT',
                '{facetoface}',
                'facetoface.id = sessions.facetoface',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'sessions'
            ),
            new rb_join(
                'sessiondate',
                'LEFT',
                '{facetoface_sessions_dates}',
                '(sessiondate.sessionid = base.sessionid AND sessions.datetimeknown = 1)',
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                'sessions'
            ),
            new rb_join(
                'status',
                'LEFT',
                '{facetoface_signups_status}',
                '(status.signupid = base.id AND status.superceded = 0)',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'attendees',
                'LEFT',
                // subquery as table
                "(SELECT su.sessionid, count(ss.id) AS number
                    FROM {facetoface_signups} su
                    JOIN {facetoface_signups_status} ss
                        ON su.id = ss.signupid
                    WHERE ss.superceded=0 AND ss.statuscode >= 50
                    GROUP BY su.sessionid)",
                'attendees.sessionid = base.sessionid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'cancellationstatus',
                'LEFT',
                '{facetoface_signups_status}',
                '(cancellationstatus.signupid = base.id AND
                    cancellationstatus.superceded = 0 AND
                    cancellationstatus.statuscode = '.MDL_F2F_STATUS_USER_CANCELLED.')',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
            new rb_join(
                'room',
                'LEFT',
                '{facetoface_room}',
                'sessions.roomid = room.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'sessions'
            ),
            new rb_join(
                'bookedby',
                'LEFT',
                '{user}',
                'bookedby.id = base.bookedby',
                REPORT_BUILDER_RELATION_MANY_TO_ONE
            ),
            new rb_join(
                'creator',
                'LEFT',
                '{user}',
                'status.createdby = creator.id',
                REPORT_BUILDER_RELATION_MANY_TO_ONE,
                'status'
            ),
            new rb_join(
                'pos',
                'LEFT',
                '{pos}',
                'pos.id = base.positionid',
                REPORT_BUILDER_RELATION_MANY_TO_ONE
            ),
            new rb_join(
                'pos_assignment',
                'LEFT',
                '{pos_assignment}',
                'pos_assignment.id = base.positionassignmentid',
                REPORT_BUILDER_RELATION_MANY_TO_ONE
            ),
        );


        // include some standard joins
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_course_table_to_joinlist($joinlist, 'facetoface', 'course', 'INNER');
        $this->add_context_table_to_joinlist($joinlist, 'course', 'id', CONTEXT_COURSE, 'INNER');
        // requires the course join
        $this->add_course_category_table_to_joinlist($joinlist,
            'course', 'category');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        // requires the position_assignment join
        $this->add_manager_tables_to_joinlist($joinlist,
            'position_assignment', 'reportstoid');
        $this->add_tag_tables_to_joinlist('course', $joinlist, 'facetoface', 'course');

        $this->add_facetoface_session_custom_fields_to_joinlist($joinlist);
        // add joins for session custom fields and session roles
        $this->add_facetoface_session_roles_to_joinlist($joinlist);

        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_cohort_course_tables_to_joinlist($joinlist, 'facetoface', 'course');

        return $joinlist;
    }

    protected function define_columnoptions() {
        global $DB;
        $columnoptions = array(
            new rb_column_option(
                'session',                  // Type.
                'capacity',                 // Value.
                get_string('sesscapacity', 'rb_source_facetoface_sessions'),    // Name.
                'sessions.capacity',        // Field.
                array('joins' => 'sessions', 'dbdatatype' => 'integer')         // Options array.
            ),
            new rb_column_option(
                'session',
                'numattendees',
                get_string('numattendees', 'rb_source_facetoface_sessions'),
                'attendees.number',
                array('joins' => 'attendees', 'dbdatatype' => 'integer')
            ),
            new rb_column_option(
                'session',
                'details',
                get_string('sessdetails', 'rb_source_facetoface_sessions'),
                'sessions.details',
                array(
                    'joins' => 'sessions',
                    'displayfunc' => 'tinymce_textarea',
                    'extrafields' => array(
                        'filearea' => '\'session\'',
                        'component' => '\'mod_facetoface\'',
                        'fileid' => 'sessions.id',
                        'context' => '\'context_module\'',
                        'recordid' => 'sessions.facetoface'
                    ),
                    'dbdatatype' => 'text',
                    'outputformat' => 'text'
                )
            ),
            new rb_column_option(
                'session',
                'duration',
                get_string('sessduration', 'rb_source_facetoface_sessions'),
                'CASE WHEN sessions.datetimeknown = 1
                    THEN
                        (sessiondate.timefinish-sessiondate.timestart)/60
                    ELSE
                        sessions.duration
                    END',
                array(
                    'joins' => array('sessiondate','sessions'),
                    'dbdatatype' => 'decimal',
                    'displayfunc' => 'hours_minutes',
                )
            ),
            new rb_column_option(
                'status',
                'statuscode',
                get_string('status', 'rb_source_facetoface_sessions'),
                'status.statuscode',
                array(
                    'joins' => 'status',
                    'displayfunc' => 'facetoface_status',
                )
            ),
             new rb_column_option(
                'session',
                'discountcode',
                get_string('discountcode', 'rb_source_facetoface_sessions'),
                'base.discountcode',
                array('dbdatatype' => 'text',
                      'outputformat' => 'text')
            ),
             new rb_column_option(
                'session',
                'usernote',
                get_string('usernote', 'rb_source_facetoface_sessions'),
                'status.note',
                array(
                    'dbdatatype' => 'text',
                    'outputformat' => 'text',
                    'joins' => 'status',
                    'capability' => 'mod/facetoface:viewattendeesnote',
                )
            ),
            new rb_column_option(
                'session',
                'normalcost',
                get_string('normalcost', 'rb_source_facetoface_sessions'),
                'sessions.normalcost',
                array(
                    'joins' => 'sessions',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
            ),
            new rb_column_option(
                'session',
                'discountcost',
                get_string('discountcost', 'rb_source_facetoface_sessions'),
                'sessions.discountcost',
                array(
                    'joins' => 'sessions',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
            ),
            new rb_column_option(
                'facetoface',
                'name',
                get_string('ftfname', 'rb_source_facetoface_sessions'),
                'facetoface.name',
                array('joins' => 'facetoface',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'facetoface',
                'namelink',
                get_string('ftfnamelink', 'rb_source_facetoface_sessions'),
                "facetoface.name",
                array(
                    'joins' => array('facetoface','sessions'),
                    'displayfunc' => 'link_f2f',
                    'defaultheading' => get_string('ftfname', 'rb_source_facetoface_sessions'),
                    'extrafields' => array('activity_id' => 'sessions.facetoface'),
                )
            ),
            new rb_column_option(
                'status',
                'createdby',
                get_string('createdby', 'rb_source_facetoface_sessions'),
                $DB->sql_fullname('creator.firstname', 'creator.lastname'),
                array(
                    'joins' => 'creator',
                    'displayfunc' => 'link_f2f_actionedby',
                    'extrafields' => array('actionedbyid' => 'creator.id')
                    )
            ),
            new rb_column_option(
                'date',
                'sessiondate',
                get_string('sessdate', 'rb_source_facetoface_sessions'),
                'sessiondate.timestart',
                array(
                    'extrafields' => array('timezone' => 'sessiondate.sessiontimezone'),
                    'joins' =>'sessiondate',
                    'displayfunc' => 'nice_date_in_timezone',
                    'dbdatatype' => 'timestamp'
                )
            ),
            new rb_column_option(
                'date',
                'sessiondate_link',
                get_string('sessdatelink', 'rb_source_facetoface_sessions'),
                'sessiondate.timestart',
                array(
                    'joins' => 'sessiondate',
                    'displayfunc' => 'link_f2f_session',
                    'defaultheading' => get_string('sessdate', 'rb_source_facetoface_sessions'),
                    'extrafields' => array('session_id' => 'base.sessionid', 'timezone' => 'sessiondate.sessiontimezone'),
                    'dbdatatype' => 'timestamp'
                )
            ),
            new rb_column_option(
                'date',
                'datefinish',
                get_string('sessdatefinish', 'rb_source_facetoface_sessions'),
                'sessiondate.timefinish',
                array(
                    'extrafields' => array('timezone' => 'sessiondate.sessiontimezone'),
                    'joins' => 'sessiondate',
                    'displayfunc' => 'nice_date_in_timezone',
                    'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'date',
                'timestart',
                get_string('sessstart', 'rb_source_facetoface_sessions'),
                'sessiondate.timestart',
                array(
                    'extrafields' => array('timezone' => 'sessiondate.sessiontimezone'),
                    'joins' => 'sessiondate',
                    'displayfunc' => 'nice_time_in_timezone',
                    'dbdatatype' => 'timestamp'
                )
            ),
            new rb_column_option(
                'date',
                'timefinish',
                get_string('sessfinish', 'rb_source_facetoface_sessions'),
                'sessiondate.timefinish',
                array(
                    'extrafields' => array('timezone' => 'sessiondate.sessiontimezone'),
                    'joins' => 'sessiondate',
                    'displayfunc' => 'nice_time_in_timezone',
                    'dbdatatype' => 'timestamp'
                )
            ),
            new rb_column_option(
                'session',
                'cancellationdate',
                get_string('cancellationdate', 'rb_source_facetoface_sessions'),
                'cancellationstatus.timecreated',
                array('joins' => 'cancellationstatus', 'displayfunc' => 'nice_datetime', 'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'session',
                'cancellationreason',
                get_string('cancellationreason', 'rb_source_facetoface_sessions'),
                'cancellationstatus.note',
                array('joins' => 'cancellationstatus',
                      'dbdatatype' => 'text',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'session',
                'bookedby',
                get_string('bookedby', 'rb_source_facetoface_sessions'),
                $DB->sql_fullname('bookedby.firstname', 'bookedby.lastname'),
                array('joins' => 'bookedby', 'displayfunc' => 'link_f2f_bookedby',
                     'extrafields' => array('bookedby_id' => 'bookedby.id'))
            ),
            new rb_column_option(
                'room',
                'name',
                get_string('roomname', 'rb_source_facetoface_sessions'),
                'room.name',
                array('joins' => 'room',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'room',
                'building',
                get_string('building', 'rb_source_facetoface_sessions'),
                'room.building',
                array('joins' => 'room',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'room',
                'address',
                get_string('address', 'rb_source_facetoface_sessions'),
                'room.address',
                array('joins' => 'room',
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'room',
                'capacity',
                get_string('roomcapacity', 'rb_source_facetoface_sessions'),
                'room.capacity',
                array('joins' => 'room', 'dbdatatype' => 'integer')
            ),
            new rb_column_option(
                'room',
                'description',
                get_string('roomdescription', 'rb_source_facetoface_sessions'),
                'room.description',
                array('joins' => 'room',
                      'dbdatatype' => 'text',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'session',
                'positionname',
                get_string('selectedposition', 'mod_facetoface'),
                'pos.fullname',
                array('joins' => 'pos',
                    'dbdatatype' => 'text',
                    'outputformat' => 'text')
            ),
            new rb_column_option(
                'session',
                'positionassignmentname',
                get_string('selectedpositionassignment', 'mod_facetoface'),
                'pos_assignment.fullname',
                array('joins' => 'pos_assignment',
                'dbdatatype' => 'text',
                'outputformat' => 'text')
            ),
            new rb_column_option(
                'session',
                'positiontype',
                get_string('selectedpositiontype', 'mod_facetoface'),
                'base.positiontype',
                array('dbdatatype' => 'text',
                      'outputformat' => 'text',
                      'displayfunc' => 'position_type')
            ),
        );

        // include some standard columns
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_course_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions);
        $this->add_position_fields_to_columns($columnoptions);
        $this->add_manager_fields_to_columns($columnoptions);
        $this->add_tag_fields_to_columns('course', $columnoptions);

        $this->add_facetoface_session_custom_fields_to_columns($columnoptions);
        $this->add_facetoface_session_roles_to_columns($columnoptions);

        $this->add_cohort_user_fields_to_columns($columnoptions);
        $this->add_cohort_course_fields_to_columns($columnoptions);
        // Redirect the display of 'user' columns (to insert 'unassigned' when needed).
        foreach ($columnoptions as $key => $columnoption) {
            if (!($columnoption->type == 'user' && $columnoption->value == 'fullname')) {
                continue;
            }
            $columnoptions[$key]->extrafields = array('user_id' => 'auser.id');
            $columnoptions[$key]->displayfunc = 'user';
        }

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'facetoface',
                'name',
                'Face to face name',
                'text'
            ),
            new rb_filter_option(
                'status',
                'statuscode',
                get_string('status', 'rb_source_facetoface_sessions'),
                'select',
                array(
                    'selectfunc' => 'session_status_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
            new rb_filter_option(
                'date',
                'sessiondate',
                get_string('sessdate', 'rb_source_facetoface_sessions'),
                'date'
            ),
            new rb_filter_option(
                'date',
                'timestart',
                get_string('sessstart', 'rb_source_facetoface_sessions'),
                'date',
                array('includetime' => true)
            ),
            new rb_filter_option(
                'date',
                'timefinish',
                get_string('sessfinish', 'rb_source_facetoface_sessions'),
                'date',
                array('includetime' => true)
            ),
            new rb_filter_option(
                'session',
                'capacity',
                get_string('sesscapacity', 'rb_source_facetoface_sessions'),
                'number'
            ),
            new rb_filter_option(
                'session',
                'details',
                get_string('sessdetails', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'discountcode',
                get_string('discountcode', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'usernote',
                get_string('usernote', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'duration',
                get_string('sessduration', 'rb_source_facetoface_sessions'),
                'number'
            ),
            new rb_filter_option(
                'session',
                'normalcost',
                get_string('normalcost', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'discountcost',
                get_string('discountcost', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'bookedby',
                get_string('bookedby', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'reserved',
                get_string('reserved', 'rb_source_facetoface_sessions'),
                'select',
                array(
                     'selectchoices' => array(
                         '0' => get_string('reserved', 'rb_source_facetoface_sessions'),
                     )
                ),
                'base.userid'
            ),
            new rb_filter_option(
                'room',
                'name',
                get_string('roomname', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'room',
                'building',
                get_string('building', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'room',
                'address',
                get_string('address', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'room',
                'capacity',
                get_string('roomcapacity', 'rb_source_facetoface_sessions'),
                'number'
            ),
            new rb_filter_option(
                'room',
                'description',
                get_string('roomdescription', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'status',
                'createdby',
                get_string('createdby', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'session',
                'positionname',
                get_string('selectedpositionname', 'mod_facetoface'),
                'select',
                array(
                    'selectfunc' => 'positions_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                ),
                'pos.id',
                'pos'
            ),
            new rb_filter_option(
                'session',
                'positionassignment',
                get_string('selectedpositionassignment', 'mod_facetoface'),
                'text',
                array(),
                'pos_assignment.fullname',
                'pos_assignment'
            ),
            new rb_filter_option(
                'session',
                'positiontype',
                get_string('selectedpositiontype', 'mod_facetoface'),
                'select',
                array(
                    'selectfunc' => 'position_types_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                ),
                'base.positiontype'
            ),
        );

        // include some standard filters
        $this->add_user_fields_to_filters($filteroptions);
        $this->add_course_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_tag_fields_to_filters('course', $filteroptions);

        // add session custom fields to filters
        $this->add_facetoface_session_custom_fields_to_filters($filteroptions);
        // add session role fields to filters
        $this->add_facetoface_session_role_fields_to_filters($filteroptions);

        $this->add_cohort_user_fields_to_filters($filteroptions);
        $this->add_cohort_course_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    public function rb_filter_position_types_list() {
        global $CFG, $POSITION_TYPES;

        include_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');

        return $POSITION_TYPES;
    }

    protected function define_contentoptions() {
        $contentoptions = array(
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
                'user',
                get_string('user', 'rb_source_facetoface_sessions'),
                array(
                    'userid' => 'base.userid',
                    'managerid' => 'position_assignment.managerid',
                    'managerpath' => 'position_assignment.managerpath',
                    'postype' => 'position_assignment.type',
                ),
                'position_assignment'
            ),
            new rb_content_option(
                'date',
                get_string('thedate', 'rb_source_facetoface_sessions'),
                'sessiondate.timestart',
                'sessiondate'
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
                'course.id',
                'course'
            ),
            new rb_param_option(
                'status',
                'status.statuscode',
                'status'
            ),
        );

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
            ),
            array(
                'type' => 'course',
                'value' => 'courselink',
            ),
            array(
                'type' => 'date',
                'value' => 'sessiondate',
            ),
        );

        return $defaultcolumns;
    }

    protected function define_requiredcolumns() {
        $requiredcolumns = array();

        $requiredcolumns[] = new rb_column(
            'course',
            'coursevisible',
            '',
            "course.visible",
            array(
                'joins' => 'course',
                'required' => 'true',
                'hidden' => 'true'
            )
        );

        $requiredcolumns[] = new rb_column(
            'course',
            'courseaudiencevisible',
            '',
            "course.audiencevisible",
            array(
                'joins' => 'course',
                'required' => 'true',
                'hidden' => 'true')
        );

        $requiredcolumns[] = new rb_column(
            'ctx',
            'id',
            '',
            "ctx.id",
            array(
                'joins' => 'ctx',
                'required' => 'true',
                'hidden' => 'true'
            )
        );

        return $requiredcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'user',
                'value' => 'fullname',
            ),
            array(
                'type' => 'course',
                'value' => 'fullname',
                'advanced' => 1,
            ),
            array(
                'type' => 'status',
                'value' => 'statuscode',
                'advanced' => 1,
            ),
            array(
                'type' => 'date',
                'value' => 'sessiondate',
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

    /*
     * Adds any facetoface session custom fields to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated if
     *                         any session custom fields exist
     * @return boolean True if session custom fields exist
     */
    function add_facetoface_session_custom_fields_to_joinlist(&$joinlist) {
        global $CFG, $DB;
        // add all session custom fields to join list
        if ($session_fields = $DB->get_records('facetoface_session_field', null, '','id')) {
            foreach ($session_fields as $session_field) {
                $id = $session_field->id;
                $key = "session_$id";
                $joinlist[] = new rb_join(
                    $key,
                    'LEFT',
                    '{facetoface_session_data}',
                    "($key.sessionid = base.sessionid AND $key.fieldid = $id)",
                    REPORT_BUILDER_RELATION_ONE_TO_ONE
                );
            }
            return true;
        }
        return false;
    }

    /*
     * Adds any facetoface session roles to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated if
     *                         any session roles exist
     * @return boolean True if any roles exist
     */
    function add_facetoface_session_roles_to_joinlist(&$joinlist) {
        global $CFG, $DB;
        // add joins for the following roles as "session_role_X" and
        // "session_role_user_X"
        $allowedroles = get_config(null, 'facetoface_sessionroles');
        if (!isset($allowedroles) || $allowedroles == '') {
            return false;
        }
        $allowedroles = explode(',', $allowedroles);

        list($allowedrolessql, $params) = $DB->get_in_or_equal($allowedroles);

        $sessionroles = $DB->get_records_sql_menu("SELECT id,shortname FROM {role} WHERE id $allowedrolessql", $params);
        if (!$sessionroles) {
            return false;
        }

        if ($roles = $DB->get_records('role', null, '', 'id,shortname')) {
            foreach ($roles as $role) {
                if (in_array($role->shortname, $sessionroles)) {
                    $field = $role->shortname;
                    $id = $role->id;
                    $key = "session_role_$field";
                    $userkey = "session_role_user_$field";
                    $joinlist[] = new rb_join(
                        $key,
                        'LEFT',
                        '{facetoface_session_roles}',
                        "($key.sessionid = base.sessionid AND $key.roleid = $id)",
                        REPORT_BUILDER_RELATION_ONE_TO_MANY
                    );
                    $joinlist[] = new rb_join(
                        $userkey,
                        'LEFT',
                        '{user}',
                        "$userkey.id = $key.userid",
                        REPORT_BUILDER_RELATION_ONE_TO_ONE,
                        $key
                    );

                }
            }
            return true;
        }

        return false;
    }

    //
    // Column data
    //

    /*
     * Adds any session custom fields to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated if
     *                              any session custom fields exist
     * @return boolean True if session custom fields exist
     */
    function add_facetoface_session_custom_fields_to_columns(&$columnoptions) {
        global $DB;
        // add all session custom fields to column options list
        if ($session_fields = $DB->get_records('facetoface_session_field', null, '', 'id,name')) {
            foreach ($session_fields as $session_field) {
                $name = $session_field->name;
                $key = "session_$session_field->id";
                $columnoptions[] = new rb_column_option(
                    'session',
                    $key,
                    get_string('sessionx', 'rb_source_facetoface_sessions', $name),
                    $key.'.data',
                    array('joins' => $key,
                          'dbdatatype' => 'char',
                          'outputformat' => 'text')
                );
            }
            return true;
        }
        return false;
    }


    /*
     * Adds any session role fields to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated if
     *                              any session roles exist
     * @return boolean True if session roles exist
     */
    function add_facetoface_session_roles_to_columns(&$columnoptions) {
        global $CFG, $DB;
        $allowedroles = get_config(null, 'facetoface_sessionroles');
        if (!isset($allowedroles) || $allowedroles == '') {
            return false;
        }
        $allowedroles = explode(',', $allowedroles);

        list($allowedrolessql, $params) = $DB->get_in_or_equal($allowedroles);

        $sessionroles = $DB->get_records_sql("SELECT id,name,shortname
            FROM {role}
            WHERE id {$allowedrolessql}", $params);
        if (!$sessionroles) {
            return false;
        }

        foreach ($sessionroles as $sessionrole) {
            $field = $sessionrole->shortname;
            $name = $sessionrole->name;
            $key = "session_role_$field";
            $userkey = "session_role_user_$field";
            $columnoptions[] = new rb_column_option(
                'role',
                $field . '_name',
                'Session '.$name . ' Name',
                $DB->sql_fullname($userkey.'.firstname', $userkey.'.lastname'),
                array(
                    'joins' => $userkey,
                    'grouping' => 'comma_list_unique',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
            );
        }
        return true;
    }


    //
    // Filter data
    //


    /*
     * Adds some common user field to the $filteroptions array
     *
     * @param array &$filteroptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_facetoface_session_role_fields_to_filters(&$filteroptions) {
        // auto-generate filters for session roles fields
        global $CFG, $DB;
        $allowedroles = get_config(null, 'facetoface_sessionroles');
        if (!isset($allowedroles) || $allowedroles == '') {
            return false;
        }
        $allowedroles = explode(',', $allowedroles);

        list($allowedrolessql, $params) = $DB->get_in_or_equal($allowedroles);

        $sessionroles = $DB->get_records_sql("SELECT id,name,shortname
            FROM {role}
            WHERE id {$allowedrolessql}", $params);
        if (!$sessionroles) {
            return false;
        }

        foreach ($sessionroles as $sessionrole) {
            $field = $sessionrole->shortname;
            $name = $sessionrole->name;
            $key = "session_role_$field";
            $userkey = "session_role_user_$field";
            $filteroptions[] = new rb_filter_option(
                'role',
                $field . '_name',
                'Session ' . $name,
                'text'
            );
        }
        return true;
    }


    /*
     * Adds some common session custom field filters to the $filteroptions array
     *
     * @param array &$filteroptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True if there are any session custom fields
     */
    protected function add_facetoface_session_custom_fields_to_filters(&$filteroptions) {
        global $DB;
        // because session fields can be added/removed by the user
        // check that the custom field exists before making an option
        // available

        // filters to try and add

        $possible_filters = array(
            'pilot' => array(
                'text' => get_string('pilot', 'rb_source_facetoface_sessions'),
                'type' => 'select',
                'options' => array('selectchoices' => array('Yes' => get_string('yes'), 'No' => get_string('no')))
            ),
            'audit' => array(
                'text' => get_string('audit', 'rb_source_facetoface_sessions'),
                'type' => 'select',
                'options' => array('selectchoices' => array('Yes' => get_string('yes'), 'No' => get_string('no')))
            ),
            'coursedelivery' => array(
                'text' => get_string('coursedelivery', 'rb_source_facetoface_sessions'),
                'type' => 'select',
                'options' => array(
                    'selectfunc' => 'coursedelivery_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            )
        );

        // add all valid session custom fields to filter options list
        if ($session_fields = $DB->get_records('facetoface_session_field')) {
            foreach ($session_fields as $session_field) {
                foreach ($possible_filters as $key => $filter_data) {
                    if ($key == $session_field->shortname) {
                        $filter = new rb_filter_option(
                            'session',
                            'session_' . $session_field->id,
                            $filter_data['text'],
                            $filter_data['type'],
                            $filter_data['options']
                        );
                        break;
                    } else {
                        $filter = new rb_filter_option(
                            'session',
                            'session_' . $session_field->id,
                            $session_field->name,
                            'text'
                        );
                    }
                }
                $filteroptions[] = $filter;
            }
            return true;
        }
        return false;
    }

    //
    //
    // Face-to-face specific display functions
    //
    //

    public function rb_display_position_type($position, $row) {
        global $CFG, $POSITION_TYPES;

        include_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');

        return get_string('type'.$POSITION_TYPES[$position], 'totara_hierarchy');
    }

    // convert a f2f status code to a text string
    function rb_display_facetoface_status($status, $row) {
        global $CFG, $MDL_F2F_STATUS;

        include_once($CFG->dirroot.'/mod/facetoface/lib.php');

        // if status doesn't exist just return the status code
        if (!isset($MDL_F2F_STATUS[$status])) {
            return $status;
        }
        // otherwise return the string
        return get_string('status_'.facetoface_get_status($status),'facetoface');
    }

    // convert a f2f activity name into a link to that activity
    function rb_display_link_f2f($name, $row) {
        global $OUTPUT;
        $activityid = $row->activity_id;
        return $OUTPUT->action_link(new moodle_url('/mod/facetoface/view.php', array('f' => $activityid)), $name);
    }

    // convert a f2f date into a link to that session
    function rb_display_link_f2f_session($date, $row) {
        global $OUTPUT;
        $sessionid = $row->session_id;
        if ($date && is_numeric($date)) {
            if (empty($row->timezone)) {
                $targetTZ = totara_get_clean_timezone();
            } else {
                $targetTZ = $row->timezone;
            }
            $strdate = userdate($date, get_string('sessiondateformat', 'facetoface'), $targetTZ);
            return $OUTPUT->action_link(new moodle_url('/mod/facetoface/attendees.php', array('s' => $sessionid)), $strdate);
        } else {
            return '';
        }
    }

    // Output the booking managers name (linked to their profile).
    function rb_display_link_f2f_bookedby($name, $row) {
        global $OUTPUT;
        $bookedbyid = $row->bookedby_id;
        return $OUTPUT->action_link(new moodle_url('/user/view.php', array('id' => $bookedbyid)), $name);
    }

    // Output the actioning users name (linked to their profile).
    function rb_display_link_f2f_actionedby($name, $row) {
        global $OUTPUT;
        $actionedbyid = $row->actionedbyid;
        return $OUTPUT->action_link(new moodle_url('/user/view.php', array('id' => $actionedbyid)), $name);
    }

    // Override user display function to show 'Reserved' for reserved spaces.
    function rb_display_link_user($user, $row, $isexport = false) {
        if ($row->user_id) {
            return parent::rb_display_link_user($user, $row, $isexport);
        }
        return get_string('reserved', 'rb_source_facetoface_sessions');
    }

    // Override user display function to show 'Reserved' for reserved spaces.
    function rb_display_link_user_icon($user, $row, $isexport = false) {
        if ($row->user_id) {
            return parent::rb_display_link_user_icon($user, $row, $isexport);
        }
        return get_string('reserved', 'rb_source_facetoface_sessions');
    }

    // Override user display function to show 'Reserved' for reserved spaces.
    function rb_display_user($user, $row, $isexport = false) {
        if ($row->user_id) {
            return $user;
        }
        return get_string('reserved', 'rb_source_facetoface_sessions');
    }


    //
    //
    // Source specific filter display methods
    //
    //

    function rb_filter_session_status_list() {
        global $CFG,$MDL_F2F_STATUS;

        include_once($CFG->dirroot.'/mod/facetoface/lib.php');

        $output = array();
        if (is_array($MDL_F2F_STATUS)) {
            foreach ($MDL_F2F_STATUS as $code => $statusitem) {
                $output[$code] = get_string('status_'.$statusitem,'facetoface');
            }
        }
        // show most completed option first in pulldown
        return array_reverse($output, true);

    }

    function rb_filter_coursedelivery_list() {
        $coursedelivery = array();
        $coursedelivery['Internal'] = 'Internal';
        $coursedelivery['External'] = 'External';
        return $coursedelivery;
    }

    public function post_config(reportbuilder $report) {
        $userid = $report->reportfor;
        if (isset($report->embedobj->embeddedparams['userid'])) {
            $userid = $report->embedobj->embeddedparams['userid'];
        }
        $fieldalias = 'course';
        $fieldbaseid = $report->get_field('course', 'id', 'course.id');
        $fieldvisible = $report->get_field('course', 'coursevisible', 'course.visible');
        $fieldaudvis = $report->get_field('course', 'courseaudiencevisible', 'course.audiencevisible');
        $report->set_post_config_restrictions(totara_visibility_where($userid,
            $fieldbaseid, $fieldvisible, $fieldaudvis, $fieldalias, 'course', $report->is_cached()));
    }

} // end of rb_source_facetoface_sessions class

