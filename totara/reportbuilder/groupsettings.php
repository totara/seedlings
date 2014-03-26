<?php // $Id$
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
 * @subpackage reportbuilder
 */

/**
 * Page for viewing and editing details about a particular activity groups
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/groups_forms.php');
require_once($CFG->dirroot . '/totara/reportbuilder/groupslib.php');

$id = required_param('id', PARAM_INT); // id of current group

$returnurl = $CFG->wwwroot . '/totara/reportbuilder/groupsettings.php';

admin_externalpage_setup('rbactivitygroups');

$output = $PAGE->get_renderer('totara_reportbuilder');

// ensure tag based grouping is up to date before displaying page
update_tag_grouping($id);

$group = $DB->get_record('report_builder_group', array('id' => $id));
$tag = $DB->get_record('tag', array('id' => $group->assignvalue));

$feedbackmoduleid = $DB->get_field('modules', 'id', array('name' => 'feedback'));
$sql = 'SELECT f.id as feedbackid, f.name as feedback, c.id as courseid,
            c.fullname as course, cm.id as cmid, ppt.disabled, ppt.lastchecked
        FROM {report_builder_group_assign} ga
        LEFT JOIN {feedback} f ON f.id = ' .
            $DB->sql_cast_char2int('ga.itemid') . '
        LEFT JOIN {course} c ON f.course = c.id
        LEFT JOIN {course_modules} cm
            ON c.id = cm.course
            AND f.id = cm.instance
            AND cm.module = ?
        LEFT JOIN {report_builder_preproc_track} ppt
            ON ga.itemid = ppt.itemid AND ga.groupid = ppt.groupid
        WHERE ga.groupid = ?
        ORDER BY course, feedback';
$params = array($feedbackmoduleid, $id);
$activities = $DB->get_records_sql($sql, $params);

// get info about current base item
$sql = 'SELECT f.id as feedbackid, f.name as feedback, c.id as courseid,
            c.fullname as course, cm.id as cmid
        FROM {feedback} f
        LEFT JOIN {course} c ON f.course = c.id
        LEFT JOIN {course_modules} cm
            ON c.id = cm.course
            AND f.id = cm.instance
            AND cm.module = ?
        WHERE f.id = ?';
$params = array($feedbackmoduleid, $group->baseitem);
$baseitem = $DB->get_record_sql($sql, $params);

// find out which reports use this group
$likesql = $DB->sql_like('source', '?', false);
$likeparams = array('%' . $DB->sql_like_escape('_grp_'.$id));
$reports = $DB->get_records_select('report_builder', $likesql, $likeparams);


echo $output->header();

echo $output->single_button($CFG->wwwroot . '/totara/reportbuilder/groups.php', get_string('backtoallgroups', 'totara_reportbuilder'), 'get');

echo $output->heading(get_string('activitygroupingx', 'totara_reportbuilder', $group->name));

echo $output->heading(get_string('assignedactivities', 'totara_reportbuilder'), 3);

$info = new stdClass();
$info->count = count($activities);
$info->tag = $tag->name;
echo html_writer::tag('p', get_string('groupcontents', 'totara_reportbuilder', $info));

if (count($activities)) {
    echo $output->activity_group_activities_table($activities);
}

echo $output->heading(get_string('baseactivity', 'totara_reportbuilder'), 3);

$info = new stdClass();
$info->url = $CFG->wwwroot . '/mod/feedback/view.php?id=' . $baseitem->cmid;
$info->activity = $baseitem->feedback;
echo html_writer::tag('p', get_string('baseitemdesc', 'totara_reportbuilder', $info));

echo $output->heading(get_string('reports', 'totara_reportbuilder'), 3);

echo $output->activity_group_reports_table($reports);

echo $output->footer();

