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
 * @author Alastair Munro <alastair.munro@@totaralms.com>
 * @package totara
 * @subpackage totara_recent_learning
 */

/**
 * Recent learning block
 *
 * Displays recent completed courses
 */
class block_totara_recent_learning extends block_base {

    public function init() {
        $this->title   = get_string('recentlearning', 'block_totara_recent_learning');
        $this->version = 2010112300;
    }

    public function get_content() {
        global $USER, $DB, $CFG;

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        $completions = completion_info::get_all_courses($USER->id);

        $sql = "SELECT c.id,c.fullname, MAX(ra.timemodified)
            FROM {role_assignments} ra
            INNER JOIN {context} cx
                ON ra.contextid = cx.id
                AND cx.contextlevel = " . CONTEXT_COURSE . "
            LEFT JOIN {course} c
                ON cx.instanceid = c.id
            WHERE ra.userid = ?
            AND ra.roleid = ?
            GROUP BY c.id, c.fullname
            ORDER BY MAX(ra.timemodified) DESC";

        $courses = $DB->get_records_sql($sql, array($USER->id, $CFG->learnerroleid));
        if (!$courses) {
            $this->content->text = get_string('norecentlearning', 'block_totara_recent_learning');
            return $this->content;
        }
        if ($courses) {
            $table = new html_table();
            $table->attributes['class'] = 'recent_learning';

            foreach ($courses as $course) {
                $id = $course->id;
                $name = $course->fullname;
                $status = array_key_exists($id, $completions) ? $completions[$id]->status : null;
                $completion = totara_display_course_progress_icon($USER->id, $course->id, $status);
                $link = html_writer::link(new moodle_url('/course/view.php', array('id' => $id)), $name, array('title' => $name));
                $cell1 = new html_table_cell($link);
                $cell1->attributes['class'] = 'course';
                $cell2 = new html_table_cell($completion);
                $cell2->attributes['class'] = 'status';
                $table->data[] = new html_table_row(array($cell1, $cell2));
            }
            $this->content->footer = html_writer::link(new moodle_url('/totara/plan/record/courses.php', array('userid' => $USER->id)), get_string('allmycourses', 'totara_core'));
        }

        $this->content->text = html_writer::table($table);
        return $this->content;
    }
}
