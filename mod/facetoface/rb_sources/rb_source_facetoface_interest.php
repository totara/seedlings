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

class rb_source_facetoface_interest extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $sourcetitle;

    public function __construct() {
        $this->base = '{facetoface_interest}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_facetoface_interest');
        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source.
    //
    //

    protected function define_joinlist() {
        global $CFG;
        require_once($CFG->dirroot .'/mod/facetoface/lib.php');

        // Joinlist for this source.
        $joinlist = array(
            new rb_join(
                'facetoface',
                'INNER',
                '{facetoface}',
                'facetoface.id = base.facetoface',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
        );

        // Include some standard joins.
        $this->add_user_table_to_joinlist($joinlist, 'base', 'userid');
        $this->add_course_table_to_joinlist($joinlist, 'facetoface', 'course');
        $this->add_course_category_table_to_joinlist($joinlist, 'course', 'category');

        return $joinlist;
    }

    protected function define_columnoptions() {
        $columnoptions = array(
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
                    'joins' => array('facetoface'),
                    'displayfunc' => 'link_f2f',
                    'defaultheading' => get_string('ftfname', 'rb_source_facetoface_sessions'),
                    'extrafields' => array('activity_id' => 'facetoface.id'),
                )
            ),
            new rb_column_option(
                'facetoface',
                'timedeclared',
                get_string('declareinterestreportdate', 'rb_source_facetoface_interest'),
                'base.timedeclared',
                array(
                    'displayfunc' => 'nice_date_in_timezone'
                )
            ),
            new rb_column_option(
                'facetoface',
                'reason',
                get_string('declareinterestreportreason', 'rb_source_facetoface_interest'),
                'base.reason',
                array(
                    'dbdatatype' => 'char',
                    'outputformat' => 'text',
                )
            ),
        );

        // Include some standard columns.
        $this->add_user_fields_to_columns($columnoptions);
        $this->add_course_fields_to_columns($columnoptions);
        $this->add_course_category_fields_to_columns($columnoptions);
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
                get_string('ftfname', 'rb_source_facetoface_sessions'),
                'text'
            ),
            new rb_filter_option(
                'facetoface',
                'reason',
                get_string('declareinterestreportreason', 'rb_source_facetoface_interest'),
                'text'
            ),
            new rb_filter_option(
                'facetoface',
                'timedeclared',
                get_string('declareinterestreportdate', 'rb_source_facetoface_interest'),
                'date',
                array('includetime' => true)
            ),
        );

        // Include some standard filters.
        $this->add_user_fields_to_filters($filteroptions);
        $this->add_course_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
                new rb_param_option(
                    'facetofaceid',
                    'base.facetoface'
                ),
        );

        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'facetoface',
                'value' => 'namelink',
            ),
            array(
                'type' => 'user',
                'value' => 'namelink',
            ),
            array(
                'type' => 'user',
                'value' => 'email',
            ),
            array(
                'type' => 'facetoface',
                'value' => 'timedeclared',
            ),
            array(
                'type' => 'facetoface',
                'value' => 'reason',
            ),
        );

        return $defaultcolumns;
    }

    // Convert a f2f activity name into a link to that activity.
    public function rb_display_link_f2f($name, $row) {
        global $OUTPUT;
        $activityid = $row->activity_id;
        return $OUTPUT->action_link(new moodle_url('/mod/facetoface/view.php', array('f' => $activityid)), $name);
    }
}
