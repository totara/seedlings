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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage
 */

defined('MOODLE_INTERNAL') || die();

class rb_source_column_extra_id extends rb_base_source {
    public $base, $joinlist, $columnoptions, $filteroptions;

    function __construct() {
        $this->base = '{course}';
        $this->joinlist = $this->define_joinlist();
        $this->columnoptions = $this->define_columnoptions();
        $this->filteroptions = $this->define_filteroptions();
        parent::__construct();
    }

    /**
     * Add column with extrafield name 'id'
     *
     * @return array of rb_column
     */
    protected function define_columnoptions() {
        $columnoptions = array();
        $this->add_course_fields_to_columns($columnoptions, 'base');
        $columnoptions[] = new rb_column_option('course_category', 'cat_id', 'Course Category Id',
                'course_category.id', array('extrafields' =>  array('id' => 'base.id')));
        $this->add_course_category_fields_to_columns($columnoptions, 'course_category', 'base');
        return $columnoptions;
    }

    protected function define_joinlist() {
        $joinlist = array();
        $this->add_course_category_table_to_joinlist($joinlist,
            'base', 'category');
        return $joinlist;
    }

    protected function define_filteroptions() {
        $filteroptions = array();
        $this->add_course_fields_to_filters($filteroptions, 'base', 'id');
        $this->add_course_category_fields_to_filters($filteroptions, 'base', 'category');
        return $filteroptions;
    }
}

