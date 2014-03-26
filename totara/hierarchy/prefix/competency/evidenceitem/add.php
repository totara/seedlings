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
$id = required_param('competency', PARAM_INT);
// Evidence type
$type = required_param('type', PARAM_TEXT);
// Evidence instance id
$instance = required_param('instance', PARAM_INT);

// No javascript parameters
$nojs = optional_param('nojs', false, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$s = optional_param('s', '', PARAM_TEXT);

// Indicates whether current related items, not in $relidlist, should be deleted
$deleteexisting = optional_param('deleteexisting', 0, PARAM_BOOL);

if (empty($CFG->competencyuseresourcelevelevidence)) {

    // Updated course lists
    $idlist = optional_param('update', null, PARAM_SEQUENCE);
    if ($idlist == null) {
        $idlist = array();
    }
    else {
        $idlist = explode(',', $idlist);
    }
}

// Check perms
admin_externalpage_setup('competencymanage', '', array(), '/totara/hierarchy/item/edit.php');

$sitecontext = context_system::instance();
require_capability('totara/hierarchy:updatecompetency', $sitecontext);
$can_edit = has_capability('totara/hierarchy:updatecompetency', $sitecontext);

// Load competency
$competency = $DB->get_record('comp', array('id' => $id));

// Check type is available
$avail_types = array('coursecompletion', 'coursegrade', 'activitycompletion');

if (!in_array($type, $avail_types)) {
    die('type unavailable');
}

if (!empty($CFG->competencyuseresourcelevelevidence)) {
    $data = new stdClass();
    $data->itemtype = $type;
    $evidence = competency_evidence_type::factory((array)$data);
    $evidence->iteminstance = $instance;

    $newevidenceid = $evidence->add($competency);
}

if ($nojs) {
    // redirect for non JS version
    if ($s == sesskey()) {
        $murl = new moodle_url($returnurl);
        $returnurl = $murl->out(false, array('nojs' => 1));
    } else {
        $returnurl = $CFG->wwwroot;
    }
    redirect($returnurl);
} else {
    ///
    /// Delete removed courses (if specified)
    ///
    if ($deleteexisting && !empty($idlist)) {

        $assigned = $DB->get_records('comp_criteria', array('competencyid' => $id));
        $assigned = !empty($assigned) ? $assigned : array();

        foreach ($assigned as $ritem) {
            if (!in_array($ritem->iteminstance, $idlist)) {
                $data = new stdClass();
                $data->id = $ritem->id;
                $data->itemtype = $ritem->itemtype;
                $evidence = competency_evidence_type::factory((array)$data);
                $evidence->iteminstance = $ritem->iteminstance;
                $evidence->delete($competency);
            }
        }
    }

    // HTML to return for JS version
    if (empty($CFG->competencyuseresourcelevelevidence)) {
        foreach ($idlist as $instance) {
            $data = new stdClass();
            $data->itemtype = $type;
            $evidence = competency_evidence_type::factory((array)$data);
            $evidence->iteminstance = $instance;

            $newevidenceid = $evidence->add($competency);
        }

        $editingon = 1;
        $evidence = $DB->get_records('comp_criteria', array('competencyid' => $id));
        $str_edit = get_string('edit');
        $str_remove = get_string('remove');
        $item = $competency;

        $renderer = $PAGE->get_renderer('totara_hierarchy');
        echo $renderer->print_competency_view_evidence($item, $evidence, $can_edit);

    } else {  //resource-level evidence functionality
        // If $newevidenceid is false, it means the evidence item wasn't added, so
        // return nothing
        if ($newevidenceid !== false) {

            $row = new html_table_row(array($evidence->get_name(), $evidence->get_type(), $evidence->get_activity_type()));

            if ($can_edit) {

                $str_edit = get_string('edit');
                $str_remove = get_string('remove');

                $link = $OUTPUT->action_icon(new moodle_url('prefix/competency/evidenceitem/remove.php', array('id' => $evidence->id, 'title' => $str_remove)),
                         new pix_url('t/delete'), array('class' => 'iconsmall', 'alt' => '$str_remove'));

                $cell4 = new html_table_cell($link);
                $cell4->attributes['style'] = 'text-align: center';

                $row->cells[] = $cell4;
                $data = array($row);
            }
        }
        echo $OUTPUT->table($data);
    }
}
