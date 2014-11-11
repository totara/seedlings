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

class rb_source_courses extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    function __construct() {
        $this->base = '{course}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        $this->contentoptions = $this->define_contentoptions();
        $this->paramoptions = $this->define_paramoptions();
        $this->defaultcolumns = $this->define_defaultcolumns();
        $this->defaultfilters = $this->define_defaultfilters();
        $this->defaulttoolbarsearchcolumns = $this->define_defaultsearchcolumns();
        $this->requiredcolumns = $this->define_requiredcolumns();
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_courses');

        parent::__construct();
    }

    //
    //
    // Methods for defining contents of source
    //
    //

    protected function define_joinlist() {

        $joinlist = array(
            new rb_join(
                'mods',
                'LEFT',
                '(SELECT cm.course, ' .
                sql_group_concat(sql_cast2char('m.name'), '|', true) .
                " AS list FROM {course_modules} cm LEFT JOIN {modules} m ON m.id = cm.module GROUP BY cm.course)",
                'mods.course = base.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE
            ),
        );

        // Include some standard joins.
        $this->add_context_table_to_joinlist($joinlist, 'base', 'id', CONTEXT_COURSE, 'INNER');
        $this->add_course_category_table_to_joinlist($joinlist,
            'base', 'category');
        $this->add_tag_tables_to_joinlist('course', $joinlist, 'base', 'id');
        $this->add_cohort_course_tables_to_joinlist($joinlist, 'base', 'id');

        return $joinlist;
    }

    protected function define_columnoptions() {
        $columnoptions = array(
            new rb_column_option(
                'course',
                'mods',
                get_string('content', 'rb_source_courses'),
                "mods.list",
                array('joins' => 'mods', 'displayfunc' => 'modicons')
            ),
        );

        // Include some standard columns.
        $this->add_course_fields_to_columns($columnoptions, 'base');
        $this->add_course_category_fields_to_columns($columnoptions, 'course_category', 'base');
        $this->add_tag_fields_to_columns('course', $columnoptions);
        $this->add_cohort_course_fields_to_columns($columnoptions);

        return $columnoptions;
    }

    protected function define_filteroptions() {
        $filteroptions = array(
            new rb_filter_option(
                'course',         // type
                'mods',           // value
                get_string('coursecontent', 'rb_source_courses'), // label
                'multicheck',     // filtertype
                array(            // options
                    'selectfunc' => 'modules_list',
                    'concat' => true, // Multicheck filter need to know that we work with concatenated values
                    'simplemode' => true,
                    'showcounts' => array(
                            'joins' => array("LEFT JOIN (SELECT course, name FROM {course_modules} cm " .
                                                          "LEFT JOIN {modules} m ON m.id = cm.module) course_mods_filter ".
                                                    "ON base.id = course_mods_filter.course"),
                            'dataalias' => 'course_mods_filter',
                            'datafield' => 'name')
                )
            )
        );

        // Include some standard filters.
        $this->add_course_fields_to_filters($filteroptions, 'base', 'id');
        $this->add_course_category_fields_to_filters($filteroptions, 'base', 'category');
        $this->add_tag_fields_to_filters('course', $filteroptions);
        $this->add_cohort_course_fields_to_filters($filteroptions);

        return $filteroptions;
    }

    protected function define_contentoptions() {
        $contentoptions = array(

            new rb_content_option(
                'date',
                get_string('startdate', 'rb_source_courses'),
                'base.startdate'
            ),
        );
        return $contentoptions;
    }

    protected function define_paramoptions() {
        $paramoptions = array(
            new rb_param_option(
                'courseid',
                'base.id'
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
                'type' => 'course',
                'value' => 'courselink',
            ),
        );
        return $defaultcolumns;
    }

    protected function define_defaultfilters() {
        $defaultfilters = array(
            array(
                'type' => 'course',
                'value' => 'fullname',
                'advanced' => 0,
            ),
            array(
                'type' => 'course_category',
                'value' => 'path',
                'advanced' => 0,
            )
        );

        return $defaultfilters;
    }

    protected function define_defaultsearchcolumns() {
        $defaultsearchcolumns = array(
            array(
                'type' => 'course',
                'value' => 'fullname',
            ),
            array(
                'type' => 'course',
                'value' => 'summary',
            ),
        );

        return $defaultsearchcolumns;
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
        $requiredcolumns[] = new rb_column(
            'base',
            'category',
            '',
            "base.category"
        );
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
        return $requiredcolumns;
    }


    //
    //
    // Source specific column display methods
    //
    //

    function rb_display_modicons($mods, $row, $isexport = false) {
        global $OUTPUT, $CFG;
        $modules = explode('|', $mods);

        // Sort module list before displaying to make
        // cells all consistent
        sort($modules);

        $out = array();
        $glue = '';
        foreach ($modules as $module) {
            if (empty($module)) {
                continue;
            }
            $name = (get_string_manager()->string_exists('pluginname', $module)) ?
                get_string('pluginname', $module) : ucfirst($module);
            if ($isexport) {
                $out[] = $name;
                $glue = ', ';
            } else {
                $glue = '';
                if (file_exists($CFG->dirroot . '/mod/' . $module . '/pix/icon.gif') ||
                    file_exists($CFG->dirroot . '/mod/' . $module . '/pix/icon.png')) {
                    $out[] = $OUTPUT->pix_icon('icon', $name, $module);
                } else {
                    $out[] = $name;
                }
            }
        }
        return implode($glue, $out);
    }


    public function post_config(reportbuilder $report) {
        // Don't include the front page (site-level course).
        $categorysql = $report->get_field('base', 'category', 'base.category') . " <> :sitelevelcategory";
        $categoryparams = array('sitelevelcategory' => 0);

        $reportfor = $report->reportfor; // ID of the user the report is for.
        $fieldalias = 'base';
        $fieldbaseid = $report->get_field('base', 'id', 'base.id');
        $fieldvisible = $report->get_field('base', 'visible', 'base.visible');
        $fieldaudvis = $report->get_field('base', 'audiencevisible', 'base.audiencevisible');
        list($visiblesql, $visibleparams) = totara_visibility_where($reportfor,
                $fieldbaseid, $fieldvisible, $fieldaudvis, $fieldalias, 'course', $report->is_cached());

        // Combine the results.
        $report->set_post_config_restrictions(array($categorysql . " AND " . $visiblesql,
            array_merge($categoryparams, $visibleparams)));
    }

} // End of rb_source_courses class.
