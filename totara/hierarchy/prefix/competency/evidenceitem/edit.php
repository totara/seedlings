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
 * @package totara
 * @subpackage totara_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir . '/coursecatlib.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_courses.class.php');
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

///
/// Setup / loading data
///

// competency id
$id = required_param('id', PARAM_INT);
$category = optional_param('category', 0, PARAM_INT);


// No javascript parameters
$nojs = optional_param('nojs', false, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$s = optional_param('s', '', PARAM_TEXT);

// Check perms
admin_externalpage_setup('competencymanage', '', array(), '/totara/hierarchy/item/edit.php');

$sitecontext = context_system::instance();
require_capability('totara/hierarchy:updatecompetency', $sitecontext);

if (!$competency = $DB->get_record('comp', array('id' => $id))) {
    print_error('incorrectcompetencyid', 'totara_hierarchy');
}

if (empty($CFG->competencyuseresourcelevelevidence)) {
    ///
    /// Load data
    ///
    $selected = array();
    $sql = "SELECT c.* FROM
        {comp_criteria} cc
        INNER JOIN {course} c ON cc.iteminstance = c.id
        WHERE cc.competencyid = ?";
    $assigned = $DB->get_records_sql($sql, array($id));
    $assigned = !empty($assigned) ? $assigned : array();
    foreach ($assigned as $item) {
        $item->id = $item->id;
        $selected[$item->id] = $item;
    }
}


///
/// Display page
///


if ($nojs) {
    // Non JS version

    // Load categories by parent id
    $categories = array();
    $categories = coursecat::make_categories_list();

    echo $OUTPUT->header();
    $out = html_writer::tag('h2', get_string('assignnewevidenceitem', 'totara_hierarchy'));
    $link = html_writer::link($returnurl, get_string('cancelwithoutassigning','totara_hierarchy'));
    $out .= html_writer::tag('p', $link);
    $out .= html_writer::start_tag('form', array('action' => me(), 'method' => 'get'));
    $out .= html_writer::select($categories, 'category', $category);
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "id", 'value' => $id));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "nojs", 'value' => $nojs));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "returnurl", 'value' => $returnurl));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "s", 'value' => $s));
    $out .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => "submit", 'value' => get_string('go')));
    $out .= html_writer::end_tag('form');
    echo $out;

    if ($category != 0) {
        if ($courses = $DB->get_records('course', array('category' => $category), 'sortorder')) {
            $list = array();
            foreach ($courses as $course) {
                $list[] = $OUTPUT->action_link(new moodle_url('course.php', array('id' => $course->id, 'competency' => $id, 'nojs' => $nojs, 's' => $s, 'returnurl' => urlencode($returnurl))), format_string($course->fullname));
            }
            echo html_writer::alist($list);
        } else {
            print html_writer::tag('p', get_string('nocoursesincat','totara_hierarchy'));
        }
    }
    echo $OUTPUT->footer();

} else {

    // Use parentid instead of category
    $parentid = optional_param('parentid', 'cat0', PARAM_ALPHANUM);

    // Strip cat from begining of parentid
    $parentid = (int) substr($parentid, 3);

    // Load dialog content generator
    $dialog = new totara_dialog_content_courses($parentid, false);

    // Turn on multi-select
    $dialog->type = totara_dialog_content::TYPE_CHOICE_MULTI;
    $dialog->selected_title = 'itemstoadd';

    // Show only courses with completion enabled
    $dialog->requirecompletion = true;
    $dialog->load_data();

    if (empty($CFG->competencyuseresourcelevelevidence)) {
        // Set selected items
        $dialog->selected_items = $selected;
    }

    // Addition url parameters
    $dialog->urlparams = array('id' => $id);
    // Display page
    echo $dialog->generate_markup();
}
