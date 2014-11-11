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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */

/**
 * This file defines the two totara_dialog_content subclasses used in browselearning.php;
 * one to show courses, the other to show programs
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot .'/totara/core/dialogs/dialog_content_courses.class.php');
require_once($CFG->dirroot .'/totara/core/dialogs/dialog_content_programs.class.php');
require_once($CFG->dirroot .'/cohort/lib.php');

/**
 * Class for generating single select course dialog markup
 *
 * @access  public
 */
class totara_dialog_content_cohort_courses extends totara_dialog_content_courses {

    /**
     * Switching frameworks
     *
     * @access  public
     * @var     boolean
     */
    public $switch_frameworks = false;

    /**
     * Load courses to display
     *
     * @access  public
     * @param   string  $where  Alternate where clause
     */
    public function load_courses($where = false) {
        parent::load_courses($where);

        // Put a "c" in front of the id for each course, so that later Javascript can use this
        // to distinguish selected courses from selected programs
        if ($this->courses) {
            foreach ($this->courses as $id => $course) {
                $course->id = "c{$course->id}";
                $this->courses[$course->id] = $course;
                unset($this->courses[$id]);
            }
        }
    }


    protected function _prepend_markup() {
        return display_dialog_selector(
            array(
                COHORT_ASSN_ITEMTYPE_COURSE => get_string('learningitemcourses', 'totara_cohort'),
                COHORT_ASSN_ITEMTYPE_PROGRAM => get_string('learningitemprograms', 'totara_cohort')
            ),
            COHORT_ASSN_ITEMTYPE_COURSE,
            'simpleframeworkpicker'
        );
    }


    /**
     * Should we show the treeview root?
     *
     * @access  protected
     * @return  boolean
     */
    protected function _show_treeview_root() {
        return !$this->show_treeview_only || $this->switch_frameworks;
    }

}

/**
 * Class for generating single select program dialog markup
 *
 * @access  public
 */
class totara_dialog_content_cohort_programs extends totara_dialog_content_programs {

    public $switch_frameworks = false;

    /**
     * Load programs to display
     *
     * @access  public
     */
    public function load_programs() {
        parent::load_programs();

        // Put a "p" in front of the id for each program, so that later Javascript can use this
        // to distinguish selected programs from selected courses
        if ($this->programs) {
            foreach ($this->programs as $id => $program) {
                $program->id = "p{$program->id}";
                $this->programs[$program->id] = $program;
                unset($this->programs[$id]);
            }
        }
    }

    public function _prepend_markup() {
        return display_dialog_selector(
            array(
                COHORT_ASSN_ITEMTYPE_COURSE => get_string('learningitemcourses', 'totara_cohort'),
                COHORT_ASSN_ITEMTYPE_PROGRAM => get_string('learningitemprograms', 'totara_cohort')
            ),
            COHORT_ASSN_ITEMTYPE_PROGRAM,
            'simpleframeworkpicker'
        );
    }


    /**
     * Should we show the treeview root?
     *
     * @access  protected
     * @return  boolean
     */
    protected function _show_treeview_root() {
        return !$this->show_treeview_only || $this->switch_frameworks;
    }

}
