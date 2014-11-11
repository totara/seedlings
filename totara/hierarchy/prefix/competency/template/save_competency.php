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

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');


///
/// Setup / loading data
///

// Template id
$id = required_param('templateid', PARAM_INT);

// Competencies to assign
$assignments = required_param('add', PARAM_SEQUENCE);

// Indicates whether current related items, not in $relidlist, should be deleted
$deleteexisting = optional_param('deleteexisting', 0, PARAM_BOOL);

// Non JS parameters
$nojs = optional_param('nojs', false, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$s = optional_param('s', '', PARAM_TEXT);

// Setup page
admin_externalpage_setup('competencyframework', '', array(), '/totara/hierarchy/prefix/competency/template/update_assignments.php');

// Check permissions
$sitecontext = context_system::instance();
require_capability('totara/hierarchy:updatecompetencytemplate', $sitecontext);

// Setup hierarchy object
$hierarchy = new competency();

// Load template
if (!$template = $hierarchy->get_template($id)) {
    print_error('incorrecttemplateid', 'totara_hierarchy');
}

// Currently assigned competencies
if (!$currentlyassigned = $hierarchy->get_assigned_to_template($id)) {
    $currentlyassigned = array();
}

// Load framework
if (!$framework = $hierarchy->get_framework($template->frameworkid)) {
    print_error('competencyframeworknotfound', 'totara_hierarchy');
}

// Check if user is editing
$editingon = false;
if (!empty($USER->competencyediting)) {
    $str_remove = get_string('remove');
    $editingon = true;
}


// Parse assignments
$assignments = $assignments ? explode(',', $assignments) : array();
$time = time();

///
/// Delete removed assignments (if specified)
///
if ($deleteexisting) {
    $removeditems = array_diff(array_keys($currentlyassigned), $assignments);
    foreach ($removeditems as $ritem) {
        $hierarchy->delete_assigned_template_competency($id, $ritem);

        echo get_string('reloadpage', 'totara_hierarchy');  // Indicate that a page reload is required
    }
}

///
/// Assign competencies
///
foreach ($assignments as $assignment) {
    // Check id
    if (!is_numeric($assignment)) {
        print_error('baddatanonnumeric', 'totara_hierarchy');
    }

    // If the competency is already assigned to the template, skip it over
    if ($DB->count_records('comp_template_assignment', array('templateid' => $template->id, 'instanceid' => $assignment))) {
        continue;
    }

    // Load competency
    $competency = $hierarchy->get_item($assignment);

    // Assign
    $assign = new stdClass();
    $assign->templateid = $template->id;
    $assign->type = 1;
    $assign->instanceid = $competency->id;
    $assign->timecreated = $time;
    $assign->usermodified = $USER->id;

    $DB->insert_record('comp_template_assignment', $assign);

    // Update competency count for template
    $count = $DB->get_field('comp_template_assignment', 'COUNT(*)', array('templateid' => $template->id));
    $template->competencycount = (int) $count;

    $DB->update_record('comp_template', $template);

    if ($nojs) {
        // If JS disabled, redirect back to original page (only if session key matches)
        $url = ($s == sesskey()) ? $returnurl : $CFG->wwwroot;
        redirect($url);
    } else {

        // Return html
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', format_string($competency->fullname));

        if ($editingon) {
            echo html_writer::start_tag('td', array('style' => 'text-align: center;'));

            echo $OUTPUT->action_link(new moodle_url("/totara/hierarchy/prefix/{$hierarchy->prefix}/template/remove_assignment.php", array('templateid' => $template->id, 'assignment' => $competency->id)),
                    $OUTPUT->pix_icon('/t/delete.gif', $str_remove), null, array('class' => "iconsmall", 'title' => $str_remove));

            echo html_writer::end_tag('td');;
        }

        echo html_writer::end_tag('tr');;
    }
}
