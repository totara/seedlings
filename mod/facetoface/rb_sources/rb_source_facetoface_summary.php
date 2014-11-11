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
 * @author Michael Gwynne <michael.gwynne@kineo.com>
 * @package mod_facetoface
 */

global $CFG;

require_once($CFG->dirroot . '/mod/facetoface/lib.php');

class rb_source_facetoface_summary extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $sourcetitle;

    function __construct() {
        $this->base = '{facetoface}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_facetoface_summary');

        parent::__construct();
    }

    function define_joinlist() {
        $joinlist = array(
            new rb_join(
                'sessions',
                'INNER',
                '{facetoface_sessions}',
                "sessions.facetoface = base.id",
                REPORT_BUILDER_RELATION_MANY_TO_ONE
            ),
            new rb_join(
                'sessiondate',
                'LEFT',
                '{facetoface_sessions_dates}',
                '(sessiondate.sessionid = sessions.id AND sessions.datetimeknown = 1)',
                REPORT_BUILDER_RELATION_ONE_TO_MANY,
                'sessions'
            ),
            new rb_join(
                'attendees',
                'LEFT',
                "(SELECT su.sessionid, su.userid, ss.id AS ssid, ss.statuscode
                    FROM {facetoface_signups} su
                    JOIN {facetoface_signups_status} ss
                        ON su.id = ss.signupid
                    WHERE ss.superceded = 0)",
                'attendees.sessionid = sessions.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'sessions'
            ),
        );

        $this->add_course_table_to_joinlist($joinlist, 'base', 'course');
        $this->add_course_category_table_to_joinlist($joinlist, 'course', 'category');
        $this->add_position_tables_to_joinlist($joinlist, 'attendees', 'userid');
        $this->add_facetoface_session_custom_fields_to_joinlist($joinlist);

        return $joinlist;
    }

    function define_columnoptions() {
        $columnoptions = array(
            new rb_column_option(
                'course',
                'fullname',
                get_string('coursename', 'totara_reportbuilder'),
                "course.fullname",
                array('joins' => 'course')
            ),
            new rb_column_option(
                'session',
                'capacity',
                get_string('sesscapacity', 'rb_source_facetoface_sessions'),
                'sessions.capacity',
                array('joins' => 'sessions', 'dbdatatype' => 'integer')
            ),
            new rb_column_option(
                'session',
                'numattendees',
                get_string('numattendees', 'rb_source_facetoface_sessions'),
                '(CASE WHEN attendees.statuscode >= ' . MDL_F2F_STATUS_APPROVED . ' THEN 1 ELSE NULL END)',
                array(
                    'joins' => array('attendees', 'sessions'),
                    'grouping' => 'count',
                    'dbdatatype' => 'integer'
                )
            ),
            new rb_column_option(
                'session',
                'totalnumattendees',
                get_string('totalnumattendees', 'rb_source_facetoface_summary'),
                '(CASE WHEN attendees.statuscode >= ' . MDL_F2F_STATUS_REQUESTED . ' THEN 1 ELSE NULL END)',
                array(
                    'joins' => array('attendees', 'sessions'),
                    'grouping' => 'count',
                    'dbdatatype' => 'integer'
                )
            ),
            new rb_column_option(
                'session',
                'waitlistattendees',
                get_string('waitlistattendees', 'rb_source_facetoface_summary'),
                '(CASE WHEN attendees.statuscode = ' . MDL_F2F_STATUS_REQUESTED . ' THEN 1 ELSE NULL END)',
                array(
                    'joins' => array('attendees', 'sessions'),
                    'grouping' => 'count',
                    'dbdatatype' => 'integer'
                )
            ),
            new rb_column_option(
                'session',
                'numspaces',
                get_string('numspaces', 'rb_source_facetoface_summary'),
                '(CASE WHEN attendees.statuscode >= ' . MDL_F2F_STATUS_APPROVED . ' THEN 1 ELSE NULL END)',
                array('joins' => array('attendees', 'sessions'),
                    'grouping' => 'count',
                    'displayfunc' => 'session_spaces',
                    'extrafields' => array('overall_capacity' => 'sessions.capacity'),
                    'dbdatatype' => 'integer'
                )
            ),
            new rb_column_option(
                'session',
                'cancelledattendees',
                get_string('cancelledattendees', 'rb_source_facetoface_summary'),
                '(CASE WHEN attendees.statuscode IN (' . MDL_F2F_STATUS_USER_CANCELLED . ', ' . MDL_F2F_STATUS_SESSION_CANCELLED . ') THEN 1 ELSE NULL END)',
                array(
                    'joins' => array('attendees', 'sessions'),
                    'grouping' => 'count',
                    'dbdatatype' => 'integer'
                )
            ),
            new rb_column_option(
                'session',
                'fullyattended',
                get_string('fullyattended', 'rb_source_facetoface_summary'),
                '(CASE WHEN attendees.statuscode = ' . MDL_F2F_STATUS_FULLY_ATTENDED . ' THEN 1 ELSE NULL END)',
                array(
                    'joins' => array('attendees', 'sessions'),
                    'grouping' => 'count',
                    'dbdatatype' => 'integer'
                )
            ),
            new rb_column_option(
                'session',
                'partiallyattended',
                get_string('partiallyattended', 'rb_source_facetoface_summary'),
                '(CASE WHEN attendees.statuscode = ' . MDL_F2F_STATUS_PARTIALLY_ATTENDED . ' THEN 1 ELSE NULL END)',
                array(
                    'joins' => array('attendees', 'sessions'),
                    'grouping' => 'count',
                    'dbdatatype' => 'integer'
                )
            ),
            new rb_column_option(
                'session',
                'noshowattendees',
                get_string('noshowattendees', 'rb_source_facetoface_summary'),
                '(CASE WHEN attendees.statuscode = ' . MDL_F2F_STATUS_NO_SHOW . ' THEN 1 ELSE NULL END)',
                array(
                    'joins' => array('attendees', 'sessions'),
                    'grouping' => 'count',
                    'dbdatatype' => 'integer'
                )
            ),
            new rb_column_option(
                'session',
                'declinedattendees',
                get_string('declinedattendees', 'rb_source_facetoface_summary'),
                '(CASE WHEN attendees.statuscode = ' . MDL_F2F_STATUS_DECLINED . ' THEN 1 ELSE NULL END)',
                array(
                    'joins' => array('attendees', 'sessions'),
                    'grouping' => 'count',
                    'dbdatatype' => 'integer'
                )
            ),
            new rb_column_option(
                'session',
                'details',
                get_string('sessdetails', 'rb_source_facetoface_sessions'),
                'sessions.details',
                array('joins' => 'sessions')
            ),
            new rb_column_option(
                'facetoface',
                'name',
                get_string('ftfname', 'rb_source_facetoface_sessions'),
                'base.name'
            ),
            new rb_column_option(
                'facetoface',
                'namelink',
                get_string('ftfnamelink', 'rb_source_facetoface_sessions'),
                "base.name",
                array(
                    'joins' => array('base', 'sessions'),
                    'displayfunc' => 'link_f2f',
                    'defaultheading' => get_string('ftfname', 'rb_source_facetoface_sessions'),
                    'extrafields' => array('activity_id' => 'sessions.facetoface'),
                )
            ),
            new rb_column_option(
                'facetoface',
                'intro',
                get_string('f2fdesc', 'rb_source_facetoface_summary'),
                'base.intro'
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
                    'extrafields' => array('session_id' => 'sessions.id', 'timezone' => 'sessiondate.sessiontimezone'),
                    'joins' => 'sessiondate',
                    'displayfunc' => 'link_f2f_session',
                    'dbdatatype' => 'timestamp'
                )
            ),
        );

        // Include some standard columns.
        $this->add_facetoface_session_custom_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'course',
                'fullname',
                 get_string('coursename', 'totara_reportbuilder'),
                'text'
            ),
            new rb_filter_option(
                'facetoface',
                'name',
                get_string('ftfname', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'date',
                'sessiondate',
                get_string('sessdate', 'rb_source_facetoface_sessions'),
                'date'
            ),
        );

        // Add session custom fields to filters.
        $this->add_course_category_fields_to_filters($filteroptions);
        $this->add_facetoface_session_custom_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    protected function define_contentoptions() {
        $contentoptions = array(
            new rb_content_option(
                'current_org',                      // class name
                get_string('currentorg', 'rb_source_facetoface_sessions'),  // title
                'organisation.path',                // field
                'organisation'                      // joins
            ),
            new rb_content_option(
                'user',
                get_string('user', 'rb_source_facetoface_sessions'),
                array(
                    'userid' => 'attendees.userid',
                    'managerid' => 'position_assignment.managerid',
                    'managerpath' => 'position_assignment.managerpath',
                    'postype' => 'position_assignment.type',
                ),
                array('attendees', 'position_assignment')
            ),
        );
        return $contentoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'course',
                'value' => 'fullname',
            ),
            array(
                'type' => 'facetoface',
                'value' => 'namelink',
            ),
        );

        return $defaultcolumns;
    }

    /**
     * Adds any facetoface session custom fields to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated if
     *                         any session custom fields exist
     * @return boolean True if session custom fields exist
     */
    function add_facetoface_session_custom_fields_to_joinlist(&$joinlist) {
        global $DB;
        // Add all session custom fields to join list.
        if ($session_fields = $DB->get_records('facetoface_session_field', null, '','id')) {
            foreach ($session_fields as $session_field) {
                $id = $session_field->id;
                $key = "session_$id";
                $joinlist[] = new rb_join(
                    $key,
                    'LEFT',
                    '{facetoface_session_data}',
                    "($key.sessionid = sessions.id AND $key.fieldid = $id)",
                    REPORT_BUILDER_RELATION_ONE_TO_ONE,
                    'sessions'
                );
            }
            return true;
        }
        return false;
    }

    /**
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
                    $key . '.data',
                    array('joins' => array('sessions', $key))
                );
            }
            return true;
        }
        return false;
    }

    /**
     * Adds some common session custom field filters to the $filteroptions array
     *
     * @param array &$filteroptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True if there are any session custom fields
     */
    protected function add_facetoface_session_custom_fields_to_filters(&$filteroptions) {
        global $DB;
        // Because session fields can be added/removed by the user,
        // check that the custom field exists before making an option available.

        // Add all valid session custom fields to filter options list.
        if ($session_fields = $DB->get_records('facetoface_session_field')) {
            foreach ($session_fields as $session_field) {
                $filter = new rb_filter_option(
                    'session',
                    'session_' . $session_field->id,
                    $session_field->name,
                    'text'
                );
                $filteroptions[] = $filter;
            }
            return true;
        }
        return false;
    }

    /**
     * Convert a f2f activity name into a link to that activity.
     *
     * @param string $name Name of of activity
     * @param object $row Report row
     * @return string Display html
     */
    function rb_display_link_f2f($name, $row) {
        global $OUTPUT;
        $activityid = $row->activity_id;
        return $OUTPUT->action_link(new moodle_url('/mod/facetoface/view.php', array('f' => $activityid)), $name);
    }

    /**
     * Convert a f2f date into a link to that session.
     *
     * @param string $date Date of session
     * @param object $row Report row
     * @return string Display html
     */
    function rb_display_link_f2f_session($date, $row) {
        global $OUTPUT;
        $sessionid = $row->session_id;
        if ($date && is_numeric($date)) {
            if (empty($row->timezone)) {
                $targetTZ = totara_get_clean_timezone();
            } else {
                $targetTZ = $row->timezone;
            }
            $date = userdate($date, get_string('nice_date_in_timezone_format', 'totara_reportbuilder'), $targetTZ);
            return $OUTPUT->action_link(new moodle_url('/mod/facetoface/attendees.php', array('s' => $sessionid)), $date);
        } else {
            return '';
        }
    }

    /**
     * Spaces left on session.
     *
     * @param string $count Number of signups
     * @param object $row Report row
     * @return string Display html
     */
    function rb_display_session_spaces($count, $row) {
        return $row->overall_capacity - $count;
    }

    /**
     * Required columns.
     */
    protected function define_requiredcolumns() {
        // Session_id is needed so when grouping we can keep the information grouped by sessions.
        // This is done to cover the case when we have several sessions which are identical.
        $requiredcolumns = array(
            new rb_column(
                'sessions',
                'id',
                '',
                "sessions.id",
                array('joins' => 'sessions')
            )
        );
        return $requiredcolumns;
    }
}
