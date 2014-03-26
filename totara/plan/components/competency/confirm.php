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
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

$PAGE->set_context(context_system::instance());
require_login();

///
/// Setup / loading data
///

// Plan id
$id = required_param('id', PARAM_INT);

// Updated course lists
$idlist = optional_param('update', null, PARAM_SEQUENCE);
if ($idlist == null) {
    $idlist = array();
}
else {
    $idlist = explode(',', $idlist);
}

$plan = new development_plan($id);
$componentname = 'competency';
$component = $plan->get_component($componentname);

// Basic access control checks
if (!$component->can_update_items()) {
    print_error('error:cannotupdateitems', 'totara_plan');
}

// get array of competencies already assigned
$assigned_ids = array();
$assigned = $component->get_assigned_items();
foreach ($assigned as $item) {
    $assigned_ids[] = $item->competencyid;
}

// see what's changed
$comps_added = array_diff($idlist, $assigned_ids);
$comps_removed = array_diff($assigned_ids, $idlist);

// get linked courses for newly added competencies
$evidence = $component->get_course_evidence_items($comps_added);

// if no linked courses in new competencies, skip this page and
// move directly on to handling them
if (count($evidence) == 0) {
    print 'NOCOURSES:'.implode(',', $idlist);
    die;
}

// get names of competencies with linked courses
list($insql, $inparams) = $DB->get_in_or_equal($idlist);
$compnames = $DB->get_records_select_menu('comp', "id $insql", $inparams, 'id', 'id,fullname');

// display a form to allow the user to select required linked courses
$form = $OUTPUT->heading(get_string('confirmlinkedcourses', 'totara_plan'));
$form .= html_writer::tag('p', get_string('confirmlinkedcoursesdesc', 'totara_plan'));
$form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $id));
$form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'update', 'value' => implode(',', $idlist)));

$comp_mandatory=array();
foreach ($evidence as $compid => $linkedcourses) {
    $form .= get_string('competency', 'totara_hierarchy') . ' '. format_string($compnames[$compid]) . ':' . html_writer::empty_tag('br');

    foreach ($linkedcourses as $linkedcourse) {
        $message = '';
        if ($plan->get_component('course')->is_item_assigned($linkedcourse->courseid)) {
            $message = ' ('.get_string('alreadyassignedtoplan', 'totara_plan').')';
        }
        if ($linkedcourse->linktype == PLAN_LINKTYPE_MANDATORY) {
            $form .= html_writer::checkbox(null, '1', true, '', array('disabled' => "disabled"));
            $form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'linkedcourses[]', 'value' => "{$compid}_{$linkedcourse->courseid}"));
            $comp_mandatory[] = "{$compid}_{$linkedcourse->courseid}";
            $form .= format_string($linkedcourse->fullname) . $message .html_writer::empty_tag('br');
        } else {
            $form .= html_writer::checkbox("linkedcourses[]", "{$compid}_{$linkedcourse->courseid}", true);
            $form .= format_string($linkedcourse->fullname) . $message . html_writer::empty_tag('br');
        }
    }
}
$form .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "mandatory", 'value' => implode(',', $comp_mandatory)));
print html_writer::tag('form', $form);
