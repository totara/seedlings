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

// Competency id
if (!empty($CFG->competencyuseresourcelevelevidence)) {
    // Only assigning one competency
    $idlist = required_param('competency', PARAM_INT);
    $idlist = array($idlist);
} else {
    // Competencies list
    $idlist = optional_param('update', null, PARAM_SEQUENCE);
    if ($idlist == null) {
        $idlist = array();
    }
    else {
        $idlist = explode(',', $idlist);
    }
}

// Evidence type
$type = required_param('type', PARAM_TEXT);
// Evidence instance id
$instance = required_param('instance', PARAM_INT);
// Id of the course to return to
$courseid = optional_param('course', 0, PARAM_INT);

// No javascript parameters
$nojs = optional_param('nojs', false, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$s = optional_param('s', '', PARAM_TEXT);

// Indicates whether current related items, not in $relidlist, should be deleted
$deleteexisting = optional_param('deleteexisting', 0, PARAM_BOOL);

// Check perms
admin_externalpage_setup('competencymanage', '', array(), '/totara/hierarchy/item/edit.php');

$sitecontext = context_system::instance();
require_capability('totara/hierarchy:updatecompetency', $sitecontext);


$hierarchy = new competency();


///
/// Save the latest list
///
$rowclass = 'r1';
foreach ($idlist as $id) {
    // Load competency
    if (!$competency = $hierarchy->get_item($id)) {
        print_error('invalidcompetencyid', 'totara_hierarchy');
    }

    // Check type is available
    $avail_types = array('coursecompletion', 'coursegrade', 'activitycompletion');

    if (!in_array($type, $avail_types)) {
        die('type unavailable');
    }

    $data = new stdClass();
    $data->itemtype = $type;
    $evidence = competency_evidence_type::factory((array)$data);
    $evidence->iteminstance = $instance;

    $newevidenceid = $evidence->add($competency);
}

///
/// Delete removed items (if specified)
///
if ($deleteexisting) {

    $oldassigned = $hierarchy->get_course_evidence($courseid);
    $oldassigned = !empty($oldassigned) ? $oldassigned : array();

    $assignedcomps = array();
    foreach ($oldassigned as $i => $o) {
        // competencyid => evidenceitemid
        $assignedcomps[$o->id] = $i;
    }

    $removeditems = array_diff(array_keys($assignedcomps), $idlist);

    foreach ($removeditems as $ritem) {
        // Load competency
        if (!$competency = $DB->get_record('comp', array('id' => $oldassigned[$assignedcomps[$ritem]]->id))) {
            print_error('updatecompetencyitems', 'totara_hierarchy');
        }

        $item = competency_evidence_type::factory((array)$assignedcomps[$ritem]);

        $item->delete($competency);
    }
}

if ($nojs) {
    // redirect back to original page for non JS version
    if ($s == sesskey()) {
        $murl = new moodle_url($returnurl);
        $returnurl = $murl->out(false, array('nojs' => 1));
    } else {
        $returnurl = $CFG->wwwroot;
    }
    redirect($returnurl);
} else {
    echo $hierarchy->print_linked_evidence_list($courseid);
}
