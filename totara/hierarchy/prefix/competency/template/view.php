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

$id          = required_param('id', PARAM_INT);
$edit        = optional_param('edit', -1, PARAM_BOOL);
$sitecontext = context_system::instance();

$hierarchy         = new competency();
$item              = $hierarchy->get_template($id);
$framework         = $hierarchy->get_framework($item->frameworkid);

// Get assigned competencies
$competencies = $hierarchy->get_assigned_to_template($id);

// Cache user capabilities
$can_edit   = has_capability('totara/hierarchy:update'.$hierarchy->prefix.'template', $sitecontext);

if ($can_edit) {
    $options = array('id' => $item->id);
    $navbaritem = $hierarchy->get_editing_button($edit, $options);
    $editingon = !empty($USER->{$hierarchy->prefix.'editing'});
} else {
    $navbaritem = '';
}

// Make this page appear under the manage items admin menu
admin_externalpage_setup($hierarchy->prefix.'manage', $navbaritem);

$sitecontext = context_system::instance();
require_capability('totara/hierarchy:view'.$hierarchy->prefix, $sitecontext);


///
/// Display page
///

// Run any hierarchy prefix specific code
$hierarchy->hierarchy_page_setup('template/view', $item);

/// Display page header
$PAGE->navbar->add(get_string("competencyframeworks", 'totara_hierarchy'),
                    new moodle_url('/totara/hierarchy/framework/index.php', array('prefix' => 'competency')));
$PAGE->navbar->add(format_string($framework->fullname),
                    new moodle_url('/totara/hierarchy/framework/view.php', array('prefix' => 'competency', 'frameworkid' => $framework->id)));
$PAGE->navbar->add(format_string($item->fullname));

echo $OUTPUT->header();

$heading = $item->fullname;

// If editing on, add edit icon
if ($editingon) {
    $str_edit = get_string('edit');
    $str_remove = get_string('remove');

    $heading .= $OUTPUT->action_icon(new moodle_url("prefix/{$hierarchy->prefix}/template/edit.php", array('id' => $item->id)),
        new pix_icon('t/edit.gif',$str_edit), null, array('class' => 'iconsmall', 'title' => $str_edit));
}

echo $OUTPUT->heading($heading, 1);

echo html_writer::tag('p', format_text($item->description, FORMAT_HTML));


///
/// Display assigned competencies
///
echo $OUTPUT->heading(get_string('assignedcompetencies', 'totara_hierarchy'));

if ($competencies) {
    $table = new html_table();
    $table->id = 'list-assignment';
    $table->data = array();
    // Headers
    $table->head = array(get_string('name'));
    $table->align = array('left');
    if ($editingon) {
        $table->head[] = get_string('options', 'totara_hierarchy');
        $table->align[] = 'center';
    }

    foreach ($competencies as $competency) {
        $row = array();
        $row[] = $competency->competency;
        if ($editingon) {

          $row[] = $OUTPUT->action_icon(new moodle_url("prefix/{$hierarchy->prefix}/template/remove_assignment.php", array('templateid' => $item->id, 'assignment' => $competencyid)),
              new pix_icon('t/delete', $str_remove), null, array('class' => 'iconsmall', 'title' => $str_remove));
        }

        $table->data[] = $row;
    }
    echo html_writer::table($table);
} else {
    echo html_writer::tag('p', get_string('noassignedcompetenciestotemplate', 'totara_hierarchy'));
}

// Navigation / editing buttons

$out = html_writer::start_tag('div', array('class' => 'buttons'));
// Display assign competency button
if ($can_edit) {
    $out .= html_writer::script('var ' . $hierarchy->prefix . '_template_id = '. $item->id .';');
    $out .= html_writer::empty_tag('br');
    $out .= html_writer::start_tag('div', array('class' => 'singlebutton'));
    $action = new moodle_url('/totara/hierarchy/prefix/' . $hierarchy->prefix . '/template/find_competency.php', array('templateid' => $item->id));
    $out .= html_writer::start_tag('form', array('action' => $action->out(), 'method' => 'get'));
    $out .= html_writer::start_tag('div');
    $out .= html_writer::empty_tag('input', array('type' => 'submit', 'id' => "show-assignment-dialog", 'value' => get_string('assignnewcompetency', 'totara_hierarchy')));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "templateid", 'value' => $item->id));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "nojs", 'value' => '1'));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "returnurl", 'value' => qualified_me()));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "s", 'value' => sesskey()));
    $out .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => "frameworkid", 'value' => $item->frameworkid));
    $out .= html_writer::end_tag('div');
    $out .= html_writer::end_tag('form');
    $out .= html_writer::end_tag('div');
}
$out .= html_writer::end_tag('div');
echo $out;
/// and proper footer
echo $OUTPUT->footer();
