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
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');


///
/// Setup / loading data
///

$sitecontext = context_system::instance();

// Relationship id
$id       = required_param('id', PARAM_INT);
$position = required_param('position', PARAM_INT);
$frameworkid = optional_param('framework', 0, PARAM_INT);

// Delete confirmation hash
$delete = optional_param('delete', '', PARAM_ALPHANUM);

require_capability('totara/hierarchy:updateposition', $sitecontext);

// Setup page and check permissions
admin_externalpage_setup('positionmanage');

// Load assignment
if (!$assignment = $DB->get_record('pos_competencies', array('id' => $id))) {
    print_error('posassignmentnotexist', 'totara_hierarchy');
}

// Load competency
if ($assignment->competencyid) {
    $competency = $DB->get_record('comp', array('id' => $assignment->competencyid));
    $fullname = format_string($competency->fullname);
}
else {
    $template = $DB->get_record('comp_template', array('id' => $assignment->templateid));
    $fullname = format_string($template->fullname);
}


$returnurl = new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => 'position', 'id' => $position, 'framework' => $frameworkid));
$deleteurl = new moodle_url('/totara/hierarchy/prefix/position/assigncompetency/remove.php',
    array('id' => $id, 'position' => $position, 'framework' => $frameworkid, 'delete' => md5($assignment->timecreated), 'sesskey' => $USER->sesskey));

if ($delete) {
    /// Delete
    if ($delete != md5($assignment->timecreated)) {
        print_error('error:checkvariable', 'totara_hierarchy');
    }

    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }

    $DB->delete_records('pos_competencies', array('id' => $id));

    add_to_log(SITEID, 'position', 'delete competency assignment', "item/view.php?id={$position}&amp;prefix=position", "$fullname (ID $assignment->id)");
    totara_set_notification(get_string('positiondeletedassignedcompetency','totara_hierarchy'), $returnurl, array('class' => 'notifysuccess'));
} else {
    /// Display page
    echo $OUTPUT->header();
    $strdelete = get_string('competencyassigndeletecheck', 'totara_hierarchy');

    echo $OUTPUT->confirm($strdelete . html_writer::empty_tag('br') . html_writer::empty_tag('br') . format_string($fullname), $deleteurl, $returnurl);

    echo $OUTPUT->footer();
    exit;
}
