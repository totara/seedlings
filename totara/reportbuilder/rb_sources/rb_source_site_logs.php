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

class rb_source_site_logs extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    function __construct() {
        $this->base = '{log}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_site_logs');

        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    protected function define_joinlist() {

        $joinlist = array(
            // no none standard joins
        );

        // include some standard joins
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_course_table_to_joinlist($joinlist, 'base', 'course');
        // requires the course join
        $this->add_course_category_table_to_joinlist($joinlist,
            'course', 'category');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        // requires the position_assignment join
        $this->add_manager_tables_to_joinlist($joinlist,
            'position_assignment', 'reportstoid');
        $this->add_tag_tables_to_joinlist('course', $joinlist, 'base', 'course');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_cohort_course_tables_to_joinlist($joinlist, 'base', 'course');

        return $joinlist;
    }

    protected function define_columnoptions() {
        global $DB;

        $columnoptions = array(
            new rb_column_option(
                'log',
                'time',
                get_string('time', 'rb_source_site_logs'),
                'base.time',
                array('displayfunc' => 'nice_datetime', 'dbdatatype' => 'timestamp')
            ),
            new rb_column_option(
                'log',
                'ip',
                get_string('ip', 'rb_source_site_logs'),
                'base.ip',
                array('displayfunc' => 'iplookup')
            ),
            new rb_column_option(
                'log',
                'module',
                get_string('module', 'rb_source_site_logs'),
                'base.module',
                array('dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'log',
                'cmid',
                get_string('cmid', 'rb_source_site_logs'),
                'base.cmid'
            ),
            new rb_column_option(
                'log',
                'action',
                get_string('action', 'rb_source_site_logs'),
                $DB->sql_fullname('base.module', 'base.action'),
                array('dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'log',
                'actionlink',
                get_string('actionlink', 'rb_source_site_logs'),
                $DB->sql_fullname('base.module', 'base.action'),
                array(
                    'displayfunc' => 'link_action',
                    'defaultheading' => get_string('action', 'rb_source_site_logs'),
                    'extrafields' => array('log_module' => 'base.module', 'log_url' => 'base.url')
                )
            ),
            new rb_column_option(
                'log',
                'url',
                get_string('url', 'rb_source_site_logs'),
                'base.url',
                array('dbdatatype' => 'char',
                      'outputformat' => 'text')
            ),
            new rb_column_option(
                'log',
                'info',
                get_string('info', 'rb_source_site_logs'),
                'base.info',
                array('dbdatatype' => 'char',
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
        $this->add_cohort_user_fields_to_columns($columnoptions);
        $this->add_cohort_course_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'log',     // type
                'action',  // value
                get_string('action', 'rb_source_site_logs'),  // label
                'text',    // filtertype
                array()    // options
            )
        );

        // include some standard filters
        $this->add_user_fields_to_filters($filteroptions);
        $this->add_course_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions);
        $this->add_position_fields_to_filters($filteroptions);
        $this->add_manager_fields_to_filters($filteroptions);
        $this->add_tag_fields_to_filters('course', $filteroptions);
        $this->add_cohort_user_fields_to_filters($filteroptions);
        $this->add_cohort_course_fields_to_filters($filteroptions);

        return $filteroptions;
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
                get_string('user', 'rb_source_site_logs'),
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
                get_string('date', 'rb_source_site_logs'),
                'base.time'
            ),
        );
        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'userid',       // parameter name
                'base.userid',  // field
                null            // joins
            ),
            new rb_param_option(
                'courseid',
                'base.course'
            ),
        );

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'log',
                'value' => 'time',
            ),
            array(
                'type' => 'user',
                'value' => 'namelink',
            ),
            array(
                'type' => 'user',
                'value' => 'position',
            ),
            array(
                'type' => 'user',
                'value' => 'organisation',
            ),
            array(
                'type' => 'course',
                'value' => 'courselink',
            ),
            array(
                'type' => 'log',
                'value' => 'ip',
            ),
            array(
                'type' => 'log',
                'value' => 'actionlink',
            ),
            array(
                'type' => 'log',
                'value' => 'info',
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
            array(
                'type' => 'log',
                'value' => 'action',
                'advanced' => 1,
            ),
            array(
                'type' => 'course',
                'value' => 'fullname',
                'advanced' => 1,
            ),
            array(
                'type' => 'course_category',
                'value' => 'path',
                'advanced' => 1,
            ),
            array(
                'type' => 'user',
                'value' => 'positionpath',
                'advanced' => 1,
            ),
            array(
                'type' => 'user',
                'value' => 'organisationpath',
                'advanced' => 1,
            ),
        );

        return $defaultfilters;
    }


    protected function define_requiredcolumns() {
        $requiredcolumns = array(
            /*
            // array of rb_column objects, e.g:
            new rb_column(
                '',         // type
                '',         // value
                '',         // heading
                '',         // field
                array(),    // options
            )
            */
        );
        return $requiredcolumns;
    }

    //
    //
    // Source specific column display methods
    //
    //

    // convert a site log action into a link to that page
    function rb_display_link_action($action, $row) {
        global $CFG;
        $url = $row->log_url;
        $module = $row->log_module;
        require_once($CFG->dirroot . '/course/lib.php');
        $logurl = make_log_url($module, $url);
        return html_writer::link(new moodle_url($logurl), $action);
    }

    // convert IP address into a link to IP lookup page
    function rb_display_iplookup($ip, $row) {
        global $CFG;
        if (!isset($ip) || $ip == '') {
            return '';
        }
        $params = array('id' => $ip);
        if (isset($row->user_id)) {
            $params['user'] = $row->user_id;
        }
        $url = new moodle_url('/iplookup/index.php', $params);
        return html_writer::link($url, $ip);
    }


    //
    //
    // Source specific filter display methods
    //
    //

    // add methods here with [name] matching filter option filterfunc
    //function rb_filter_[name]() {
        // should return an associative array
        // suitable for use in a select form element
    //}

} // end of rb_source_site_logs class

