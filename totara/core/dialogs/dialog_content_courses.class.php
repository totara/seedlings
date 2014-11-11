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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package totara
 * @subpackage totara_core/dialogs
 */

/**
 * Course/category dialog generator
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content.class.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/totara/coursecatalog/lib.php');
require_once($CFG->libdir.'/datalib.php');
require_once($CFG->libdir.'/coursecatlib.php');

/**
 * Class for generating single select course dialog markup
 *
 * @access  public
 */
class totara_dialog_content_courses extends totara_dialog_content {

    /**
     * Type of search to perform (generally relates to dialog type)
     *
     * @access  public
     * @var     string
     */
    public $searchtype = 'coursecompletion';

    /**
     * Current category (e.g., show children of this category)
     *
     * @access  public
     * @var     integer
     */
    public $categoryid;


    /**
     * Categories at this level (indexed by category ID)
     *
     * @access  public
     * @var     array
     */
    public $categories = array();


    /**
     * Courses at this level
     *
     * @access  public
     * @var     array
     */
    public $courses = array();

    /**
     * Flag to require results to have completion enabled
     *
     * @access  public
     * @var     bool
     */
    public $requirecompletion = false;


    /**
     * Flag to require results to have completion criteria enabled
     *
     * @access  public
     * @var     bool
     */
    public $requirecompletioncriteria = false;

    /**
     * Set current category
     *
     * @see     totara_dialog_hierarchy::categoryid
     *
     * @access  public
     * @param   $categoryid     int     Category id
     * @param   $autoload       bool    Optional (true means run load_data())
     */
    public function __construct($categoryid, $autoload = true) {

        $this->categoryid = $categoryid;

        // If category ID doesn't equal 0, must be only loading the tree
        if ($this->categoryid > 0) {
            $this->show_treeview_only = true;
        }
        if ($autoload) {
            $this->load_data();
        }
    }

    /**
     * Load data
     *
     * @access  public
     * @return  void
     */
    public function load_data() {
        // Load child categories
        $this->load_categories();

        // Load child courses
        $this->load_courses();
    }


    /**
     * Load categories to display
     *
     * @access  public
     */
    public function load_categories() {
        global $DB;

        // If category 0, make fake object
        if (!$this->categoryid) {
            $parent = new stdClass();
            $parent->id = 0;
        }
        else {
            // Load category
            if (!$parent = $DB->get_record('course_categories', array('id' => $this->categoryid))) {
                print_error('error:categoryidincorrect', 'totara_core');
            }
        }

        // Load child categories
        $categories = coursecat::get($parent->id)->get_children();

        $category_ids = array();
        foreach ($categories as $cat) {
            $category_ids[] = $cat->id;
        }

        // Get item counts for categories
        $category_item_counts = (count($category_ids) > 0) ? totara_get_category_item_count($category_ids, 'course') : array();

        // Fix array to be indexed by prefixed id's (so it doesn't conflict with course id's)
        foreach ($categories as $category) {
            $item_count = array_key_exists($category->id, $category_item_counts) ? $category_item_counts[$category->id] : 0;

            if ($item_count > 0) {
                $c = new stdClass();
                $c->id = 'cat'.$category->id;
                $c->fullname = $category->name;

                $this->categories[$c->id] = $c;
            }
        }

        // Also fill parents array
        $this->parent_items = $this->categories;

        // Make categories unselectable
        $this->disabled_items = $this->parent_items;
    }


    /**
     * Load courses to display
     *
     * @access  public
     * @param   array  $where  Alternate where clause in the for array($condition, $params)
     */
    public function load_courses($where = false) {
        global $DB;
        $params = array();
        if ($this->categoryid) {
            $coursecat = coursecat::get($this->categoryid);
            if ($where === false) {
                $where = '';

                if ($this->requirecompletion || $this->requirecompletioncriteria) {
                    $where = " enablecompletion = :enablecompletion AND ";
                    $params['enablecompletion'] = COMPLETION_ENABLED;
                    if ($this->requirecompletioncriteria) {
                        $where .= "
                            id IN (
                                SELECT
                                    course
                                FROM
                                    {course_completion_criteria} ccc
                                INNER JOIN
                                    {course} c
                                 ON c.id = ccc.course
                                WHERE
                                    c.category = :completioncat
                            )
                            AND
                        ";
                        $params['completioncat'] = $this->categoryid;
                    }
                }

                $where .= " category = :dialogcoursecat ";
                $params['dialogcoursecat'] = $this->categoryid;
                $this->courses = $coursecat->get_course_records($where, $params, array());
            } else {
                $this->courses = $coursecat->get_courses();
            }
        }
    }

    /**
     * Generate markup, but first merge categories and courses together
     *
     * @access  public
     * @return  string
     */
    public function generate_markup() {

        // Merge categories and courses (courses to follow categories)
        $categories = is_array($this->categories) ? $this->categories : array();
        $courses = is_array($this->courses) ? $this->courses : array();

        $this->items = array_merge($categories, $courses);

        return parent::generate_markup();
    }
}
