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
require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');


///
/// Params
///

// Competency id
$assignto = required_param('assignto', PARAM_INT);

// Framework id
$frameworkid = required_param('frameworkid', PARAM_INT);

// Competencies to add
$add = required_param('add', PARAM_SEQUENCE);

// Indicates whether current related items, should be deleted
$deleteexisting = optional_param('deleteexisting', 0, PARAM_BOOL);

// Non JS parameters
$nojs = optional_param('nojs', false, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$s = optional_param('s', '', PARAM_TEXT);

// Setup page
admin_externalpage_setup('positionmanage');

// Check permissions
$sitecontext = context_system::instance();
require_capability('totara/hierarchy:updateposition', $sitecontext);

// Setup hierarchy objects
$competencies = new competency();
$positions = new position();

// Load position
if (!$position = $positions->get_item($assignto)) {
    print_error('positionnotfound', 'totara_hierarchy');
}

// Currently assigned competencies
if (!$currentlyassigned = $positions->get_assigned_competency_templates($assignto, $frameworkid)) {
    $currentlyassigned = array();
}


// Parse input
$add = $add ? explode(',', $add) : array();
$time = time();

///
/// Delete removed assignments (if specified)
///
if ($deleteexisting) {
    $removeditems = array_diff(array_keys($currentlyassigned), $add);
    foreach ($removeditems as $rid) {
        $DB->delete_records('pos_competencies', array('positionid' => $position->id, 'templateid' => $rid));
        //TODO: add delete log

        echo get_string('reloadpage', 'totara_hierarchy'); // Indicate that a page reload is required
    }
}

///
/// Assign competencies
///
$str_remove = get_string('remove');

foreach ($add as $addition) {
    // Check id
    if (!is_numeric($addition)) {
        print_error('baddatanonnumeric', 'totara_hierarchy', 'id');
    }

    // If the template is already assigned to the position, skip it over
    if ($DB->count_records('pos_competencies', array('positionid' => $position->id, 'templateid' => $addition))) {
        continue;
    }

    // Load competency
    $related = $competencies->get_template($addition);

    // Load framework
    $framework = $competencies->get_framework($related->frameworkid);

    // Add relationship
    $relationship = new stdClass();
    $relationship->positionid = $position->id;
    $relationship->templateid = $related->id;
    $relationship->timecreated = $time;
    $relationship->usermodified = $USER->id;

    $relationship->id = $DB->insert_record('pos_competencies', $relationship);

    if ($nojs) {
        // If JS disabled, redirect back to original page (only if session key matches)
        $url = ($s == sesskey()) ? $returnurl : $CFG->wwwroot;
        redirect($url);
    } else {

        // Return html
        $row = new html_table_row();

        $row->cells[] = new html_table_cell($OUTPUT->action_link(new moodle_url('prefix/competency/template/view.php', array('id' => $related->id)), $related->fullname));

        $row->cells[] = new html_table_cell($OUTPUT->action_icon(new moodle_url('prefix/position/assigncompetency/remove.php', array('id' => $relationship->id, 'position' => $position->id)),
             new pix_icon('t/delete.gif', $str_remove), null, array('class' => 'iconsmall', 'title' => $str_remove)));

        echo $row;
    }
}
