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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @author Eugene Venter <eugene@catalyst.net.nz>
 *
 * @package totara
 * @subpackage plan
 */

class block_totara_addtoplan extends block_base {

    function init() {
        $this->title = get_string('pluginname', 'block_totara_addtoplan');
    }

    function get_content() {
        global $CFG, $USER, $COURSE;

        require_once($CFG->dirroot . '/blocks/totara_addtoplan/lib.php');
        require_once($CFG->dirroot .'/totara/core/js/lib/setup.php');
        local_js();

        $args = array('args' => '{"courseid":' . $COURSE->id . '}');
        $jsmodule = array(
            'name' => 'block_totara_addtoplan',
            'fullpath' => '/blocks/totara_addtoplan/block.js',
            'requires' => array('json'));
        $this->page->requires->js_init_call('M.block_totara_addtoplan.init', $args, false, $jsmodule);

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->footer = '';

        // If they're already completed in this course, then we don't need
        // to show this block.
        require_once($CFG->dirroot . '/completion/completion_completion.php');
        $params = array('userid' => $USER->id, 'course' => $COURSE->id);
        $completion = new completion_completion($params);
        if ($completion->is_complete()) {
            $this->content->text = '';
            return $this->content;
        }

        require_once($CFG->dirroot . '/totara/plan/lib.php');
        $plans = dp_get_plans($USER->id, array(DP_PLAN_STATUS_UNAPPROVED, DP_PLAN_STATUS_PENDING, DP_PLAN_STATUS_APPROVED));
        $plans = array_keys($plans);

        // If they have no active plan, then we don't need to display this block to them.
        if (empty($plans)) {
            $this->content->text = '';
            return $this->content;
        }

        $course_include = $CFG->dirroot . '/totara/plan/components/course/course.class.php';
        if (file_exists($course_include)) {
            require_once($course_include);
        } else {
            $this->content->text = '';
            return $this->content;
        }


        $this->content->text = totara_block_addtoplan_get_content($COURSE->id, $USER->id);

        return $this->content;
    }

    public function applicable_formats() {
        return array(
            'site' => false,
            'course-view' => true);
    }

}
?>
