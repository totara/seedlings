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
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package totara
 * @subpackage reportbuilder
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/totara/cohort/lib.php');

class rb_source_program extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    protected $instancetype = 'program';

    function __construct() {
        global $CFG;
        $this->base = '{prog}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_program');
        $this->sourcewhere = $this->define_sourcewhere();
        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    protected function define_joinlist() {
        global $CFG;

        $joinlist = array(
            new rb_join(
                'ctx',
                'INNER',
                '{context}',
                'ctx.instanceid = base.id AND ctx.contextlevel = ' . CONTEXT_PROGRAM,
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
        );

        $this->add_course_category_table_to_joinlist($joinlist, 'base', 'category');
        $this->add_cohort_program_tables_to_joinlist($joinlist, 'base', 'id');

        return $joinlist;
    }

    protected function define_columnoptions() {

        // include some standard columns
        $this->add_program_fields_to_columns($columnoptions, 'base');
        $this->add_course_category_fields_to_columns($columnoptions, 'course_category', 'base', 'programcount');
        $this->add_cohort_program_fields_to_columns($columnoptions);

        return $columnoptions;
    }


    protected function define_filteroptions() {
        $filteroptions = array();

        // include some standard filters
        $this->add_program_fields_to_filters($filteroptions);
        $this->add_course_category_fields_to_filters($filteroptions, 'base', 'category');
        $this->add_cohort_program_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    protected function define_contentoptions() {
        $contentoptions = array(
            new rb_content_option(
                'prog_availability',
                get_string('availablecontent', 'rb_source_program'),
                array(
                    'base.available',
                    'base.availablefrom',
                    'base.availableuntil',
                )
            ),
        );
        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'programid',
                'base.id'
            ),
            new rb_param_option(
                'visible',
                'base.visible'
            ),
            new rb_param_option(
                'category',
                'base.category'
            ),
        );
        return $paramoptions;
    }

    protected function define_defaultcolumns() {
        $defaultcolumns = array(
            array(
                'type' => 'prog',
                'value' => 'proglinkicon',
            ),
        array(
                'type' => 'course_category',
                'value' => 'namelink',
            ),
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'prog',
                'value' => 'fullname',
                'advanced' => 0,
            ),
        array(
                'type' => 'course_category',
                'value' => 'path',
                'advanced' => 0,
            ),
        );
        return $defaultfilters;
    }

    protected function define_requiredcolumns() {
        $requiredcolumns = array();

        $requiredcolumns[] = new rb_column(
            'ctx',
            'id',
            '',
            "ctx.id",
            array('joins' => 'ctx')
        );

        // Visibility.
        $requiredcolumns[] = new rb_column(
            'base',
            'visible',
            '',
            "base.visible"
        );

        $requiredcolumns[] = new rb_column(
            'base',
            'audiencevisible',
            '',
            "base.audiencevisible"
        );

        $requiredcolumns[] = new rb_column(
            'base',
            'available',
            '',
            "base.available"
        );

        $requiredcolumns[] = new rb_column(
            'base',
            'availablefrom',
            '',
            "base.availablefrom"
        );

        $requiredcolumns[] = new rb_column(
            'base',
            'availableuntil',
            '',
            "base.availableuntil"
        );

        return $requiredcolumns;
    }

    protected function define_sourcewhere() {
        $sourcewhere = '(base.certifid IS NULL)';

        return $sourcewhere;
    }

    public function post_config(reportbuilder $report) {
        $reportfor = $report->reportfor; // ID of the user the report is for.
        $fieldalias = 'base';
        $fieldbaseid = $report->get_field('base', 'id', 'base.id');
        $fieldvisible = $report->get_field('base', 'visible', 'base.visible');
        $fieldaudvis = $report->get_field('base', 'audiencevisible', 'base.audiencevisible');
        $report->set_post_config_restrictions(totara_visibility_where($reportfor,
            $fieldbaseid, $fieldvisible, $fieldaudvis, $fieldalias, $this->instancetype, $report->is_cached()));
    }

} // End of rb_source_courses class.

