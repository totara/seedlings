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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_badge_issued extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    public function __construct() {
        $this->base = '{badge_issued}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_badge_issued');

        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source.
    //
    //

    protected function define_joinlist() {
        $joinlist = array(
            new rb_join(
                'badge',
                'LEFT',
                '{badge}',
                'base.badgeid = badge.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
        );

        // Include some standard joins.
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_course_table_to_joinlist($joinlist, 'badge', 'courseid');
        // Requires the course join.
        $this->add_course_category_table_to_joinlist($joinlist,
            'course', 'category');
        $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');
        // Requires the position_assignment join.
        $this->add_manager_tables_to_joinlist($joinlist,
            'position_assignment', 'reportstoid');
        $this->add_tag_tables_to_joinlist('course', $joinlist, 'badge', 'courseid');
        $this->add_cohort_user_tables_to_joinlist($joinlist, 'base', 'userid');
        $this->add_cohort_course_tables_to_joinlist($joinlist, 'badge', 'courseid');

        return $joinlist;
    }

    protected function define_columnoptions() {
        $columnoptions = array(
            new rb_column_option(
                'base',
                'dateexpire',
                get_string('dateexpire', 'rb_source_badge_issued'),
                'base.dateexpire',
                array('displayfunc' => 'nice_date')
            ),
            new rb_column_option(
                'base',
                'dateissued',
                get_string('dateissued', 'rb_source_badge_issued'),
                'base.dateissued',
                array('displayfunc' => 'nice_date')
            ),
            new rb_column_option(
                'base',
                'issuernotified',
                get_string('issuernotified', 'rb_source_badge_issued'),
                'base.issuernotified',
                array('displayfunc' => 'nice_date')
            ),
            new rb_column_option(
                'badge',
                'idchar',
                'badgeid',
                sql_cast2char('badge.id'),
                array('joins' => 'badge', 'selectable' => false)
            ),
            new rb_column_option(
                'badge',
                'badgeimage',
                get_string('badgeimage', 'rb_source_badge_issued'),
                'badge.id',
                array('displayfunc' => 'badgeimage',
                    'extrafields' => array('userid' => 'base.userid',
                        'uniquehash' => 'base.uniquehash',
                        'badgename' => 'badge.name'),
                    'joins' => 'badge')
            ),
            new rb_column_option(
                'badge',
                'issuername',
                get_string('issuername', 'rb_source_badge_issued'),
                'badge.issuername',
                array('displayfunc' => 'issuernamelink',
                    'extrafields' => array('issuerurl' => 'badge.issuerurl'),
                    'joins' => 'badge')
            ),
            new rb_column_option(
                'badge',
                'issuercontact',
                get_string('issuercontact', 'rb_source_badge_issued'),
                'badge.issuercontact',
                array('joins' => 'badge')
            ),
            new rb_column_option(
                'badge',
                'name',
                get_string('badgename', 'rb_source_badge_issued'),
                'badge.name',
                array('joins' => 'badge')
            ),
            new rb_column_option(
                'badge',
                'type',
                get_string('badgetype', 'rb_source_badge_issued'),
                'badge.type',
                array('displayfunc' => 'badgetype', 'joins' => 'badge')
            ),
            new rb_column_option(
                'badge',
                'status',
                get_string('badgestatus', 'rb_source_badge_issued'),
                'badge.status',
                array('displayfunc' => 'badgestatus', 'joins' => 'badge')
            ),
        );

        // Include some standard columns.
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
                'base',
                'dateissued',
                get_string('dateissued', 'rb_source_badge_issued'),
                'date'
            ),
            new rb_filter_option(
                'base',
                'dateexpire',
                get_string('dateexpire', 'rb_source_badge_issued'),
                'date'
            ),
            new rb_filter_option(
                'badge',
                'name',
                get_string('badgename', 'rb_source_badge_issued'),
                'select',
                array(
                    'selectfunc' => 'badgename_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
            new rb_filter_option(
                'badge',
                'idchar',
                get_string('badges', 'rb_source_badge_issued'),
                'badge',
                array('selectfunc' => 'badges_list')
            ),
            new rb_filter_option(
                'badge',
                'issuername',
                get_string('issuername', 'rb_source_badge_issued'),
                'select',
                array(
                    'selectfunc' => 'badgeissuer_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
            new rb_filter_option(
                'badge',
                'type',
                get_string('badgetype', 'rb_source_badge_issued'),
                'select',
                array(
                    'selectfunc' => 'badgetype_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
            new rb_filter_option(
                'badge',
                'status',
                get_string('badgestatus', 'rb_source_badge_issued'),
                'multicheck',
                array(
                    'selectfunc' => 'badgestatus_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
            ),
        );

        // Include some standard filters.
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
                'date',
                get_string('dateissued', 'rb_source_badge_issued'),
                'base.dateissued'
            ),
        );
        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array();

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'user',
                'value' => 'namelink',
            ),
            array(
                'type' => 'badge',
                'value' => 'badgeimage',
            ),
            array(
                'type' => 'base',
                'value' => 'dateissued',
            ),
            array(
                'type' => 'badge',
                'value' => 'type',
            ),
            array(
                'type' => 'badge',
                'value' => 'status',
            ),
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'badge',
                'value' => 'idchar',
            ),
            array(
                'type' => 'base',
                'value' => 'dateissued',
            ),
            array(
                'type' => 'badge',
                'value' => 'type',
            ),
            array(
                'type' => 'badge',
                'value' => 'status',
            ),

        );

        return $defaultfilters;
    }

    protected function define_requiredcolumns() {
        $requiredcolumns = array(
            new rb_column(
                'badge',
                'badgeid',
                '',
                'badge.id',
                array('hidden' => true, 'joins' => 'badge')
            ),
        );
        return $requiredcolumns;
    }

    //
    //
    // Source specific column display methods.
    //
    //
    public function rb_display_issuernamelink($name, $row, $isexport) {
        global $CFG;

        $url = parse_url($CFG->wwwroot);
        if (empty($row->issuerurl) || $row->issuerurl == ($url['scheme'] . '://' . $url['host']) || substr($row->issuerurl, 0, 4) != 'http') {
            return $name;
        }

        return html_writer::tag('a', $name, array('href' => $row->issuerurl));
    }

    public function rb_display_badgetype($type, $row, $isexport) {
        global $CFG;
        require_once($CFG->libdir.'/badgeslib.php');
        return get_string("badgetype_{$type}", 'badges');
    }

    public function rb_display_badgestatus($status, $row, $isexport) {
        global $CFG;
        require_once($CFG->libdir.'/badgeslib.php');
        return get_string("badgestatus_{$status}", 'badges');
    }


    public function rb_display_badgeimage($badgeid, $row, $isexport) {
        global $CFG;

        if ($isexport) {
            return $row->badgename;
        }

        require_once($CFG->libdir.'/badgeslib.php');
        $badge = new badge($badgeid);

        return print_badge_image($badge, $badge->get_context());
    }

    //
    //
    // Source specific filter display methods.
    //
    //

    public function rb_filter_badgename_list() {
        global $DB;

        $sql = "SELECT DISTINCT b.name AS idx, b.name AS val
                FROM {badge_issued} bi
                JOIN {badge} b ON bi.badgeid = b.id";
        return $DB->get_records_sql_menu($sql);
    }

    public function rb_filter_badgeissuer_list() {
        global $DB;

        $sql = "SELECT DISTINCT b.issuername AS idx, b.issuername AS val
                FROM {badge_issued} bi
                JOIN {badge} b ON bi.badgeid = b.id";
        return $DB->get_records_sql_menu($sql);
    }

    public function rb_filter_badges_list() {
        global $DB;

        $sql = "SELECT DISTINCT b.id, b.name
            FROM {badge_issued} bi
            JOIN {badge} b ON bi.badgeid = b.id
            ORDER BY b.name";

        return $DB->get_records_sql_menu($sql);
    }

    public function rb_filter_badgetype_list() {
        global $CFG;
        require_once($CFG->libdir.'/badgeslib.php');
        return array(
            BADGE_TYPE_SITE => get_string('badgetype_'.BADGE_TYPE_SITE, 'badges'),
            BADGE_TYPE_COURSE => get_string('badgetype_'.BADGE_TYPE_COURSE, 'badges')
        );
    }

    public function rb_filter_badgestatus_list() {
        global $CFG;
        require_once($CFG->libdir.'/badgeslib.php');
        return array(
            BADGE_STATUS_INACTIVE => get_string('badgestatus_'.BADGE_STATUS_INACTIVE, 'badges'),
            BADGE_STATUS_ACTIVE => get_string('badgestatus_'.BADGE_STATUS_ACTIVE, 'badges'),
            BADGE_STATUS_INACTIVE_LOCKED => get_string('badgestatus_'.BADGE_STATUS_INACTIVE_LOCKED, 'badges'),
            BADGE_STATUS_ACTIVE_LOCKED => get_string('badgestatus_'.BADGE_STATUS_ACTIVE_LOCKED, 'badges'),
            BADGE_STATUS_ARCHIVED => get_string('badgestatus_'.BADGE_STATUS_ARCHIVED, 'badges'),
        );
    }

} // End of rb_source_badge_issued class.

