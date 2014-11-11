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
 * @subpackage totara_customfield
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/customfield/indexlib.php');
require_once($CFG->dirroot.'/totara/customfield/fieldlib.php');
require_once($CFG->dirroot.'/totara/customfield/definelib.php');
require_once($CFG->dirroot.'/totara/hierarchy/lib.php');

require_login();

$prefix         = required_param('prefix', PARAM_ALPHA);        // hierarchy name or mod name
$typeid         = optional_param('typeid', '0', PARAM_INT);    // typeid if hierarchy
$action         = optional_param('action', '', PARAM_ALPHA);    // param for some action
$id             = optional_param('id', 0, PARAM_INT); // id of a custom field

$sitecontext = context_system::instance();

// use $prefix to determine where to get custom field data from
if (in_array($prefix, array('course', 'program'))) {
    if ($prefix == 'program') {
        $tableprefix = $shortprefix = 'prog';
        if (totara_feature_disabled('programs') &&
            totara_feature_disabled('certifications')) {
            print_error('programsandcertificationsdisabled', 'totara_program');
        }
    } else {
        $tableprefix = $shortprefix = $prefix;
    }
    $adminpagename = 'coursecustomfields';

    $can_add = has_capability('totara/core:create'.$prefix.'customfield', $sitecontext);
    $can_edit = has_capability('totara/core:update'.$prefix.'customfield', $sitecontext);
    $can_delete = has_capability('totara/core:delete'.$prefix.'customfield', $sitecontext);
} else {
    // Confirm the hierarchy prefix exists
    $hierarchy = hierarchy::load_hierarchy($prefix);
    $shortprefix = hierarchy::get_short_prefix($prefix);
    $adminpagename = $prefix . 'typemanage';
    $tableprefix = $shortprefix.'_type';

    $can_add = has_capability('totara/hierarchy:create'.$prefix.'customfield', $sitecontext);
    $can_edit = has_capability('totara/hierarchy:update'.$prefix.'customfield', $sitecontext);
    $can_delete = has_capability('totara/hierarchy:delete'.$prefix.'customfield', $sitecontext);
}

$PAGE->set_url('/totara/customfield/index.php');
$PAGE->set_context($sitecontext);
$PAGE->set_pagelayout('admin');

$redirectoptions = array('prefix' => $prefix);
if ($typeid) {
    $redirectoptions['typeid'] = $typeid;
}
if ($id) {
    $redirectoptions['id'] = $id;
}

$redirect = new moodle_url('/totara/customfield/index.php', $redirectoptions);

// get some relevant data
if ($typeid) {
    $type = $hierarchy->get_type_by_id($typeid);
}

// set up breadcrumbs trail
if ($typeid) {
    $pagetitle = format_string(get_string($prefix.'depthcustomfields', 'totara_hierarchy'));

    $PAGE->navbar->add(get_string($prefix.'types', 'totara_hierarchy'),
                        new moodle_url('/totara/hierarchy/type/index.php', array('prefix' => $prefix)));

    $PAGE->navbar->add(format_string($type->fullname));

} else if ($prefix == 'course') {
    $pagetitle = format_string(get_string('coursecustomfields', 'totara_customfield'));
    $PAGE->navbar->add(get_string('coursecustomfields', 'totara_customfield'));
} else if ($prefix == 'program') {
    $pagetitle = format_string(get_string('programcertcustomfields', 'totara_customfield'));
    $PAGE->navbar->add(get_string('programcertcustomfields', 'totara_customfield'));
}

$navlinks = $PAGE->navbar->has_items();

// set the capability prefix
$capability_prefix = (in_array($prefix, array('course', 'program'))) ? 'core' : 'hierarchy';

admin_externalpage_setup($adminpagename, '', array('prefix' => $prefix));

// check if any actions need to be performed
switch ($action) {
   case 'movefield':
        require_capability('totara/'.$capability_prefix.':update'.$prefix.'customfield', $sitecontext);
        $id  = required_param('id', PARAM_INT);
        $dir = required_param('dir', PARAM_ALPHA);

        if (confirm_sesskey()) {
            customfield_move_field($id, $dir, $tableprefix, $prefix);
        }
        redirect($redirect);
        break;
    case 'deletefield':
        require_capability('totara/'.$capability_prefix.':delete'.$prefix.'customfield', $sitecontext);
        $id      = required_param('id', PARAM_INT);
        $confirm = optional_param('confirm', 0, PARAM_BOOL);

        if (data_submitted() and $confirm and confirm_sesskey()) {
            customfield_delete_field($id, $tableprefix);
            redirect($redirect);
        }

        //ask for confirmation
        $datacount = $DB->count_records($tableprefix.'_info_data', array('fieldid' => $id));
        switch ($datacount) {
        case 0:
            $deletestr = get_string('confirmfielddeletionnodata', 'totara_customfield');
            break;
        case 1:
            $deletestr = get_string('confirmfielddeletionsingle', 'totara_customfield');
            break;
        default:
            $deletestr = get_string('confirmfielddeletionplural', 'totara_customfield', $datacount);
        }
        $optionsyes = array ('id'=>$id, 'confirm'=>1, 'action'=>'deletefield', 'sesskey'=>sesskey(), 'typeid'=>$typeid);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('deletefield', 'totara_customfield'));
        $formcontinue = new single_button(new moodle_url($redirect, $optionsyes), get_string('yes'), 'post');
        $formcancel = new single_button(new moodle_url($redirect, $redirectoptions), get_string('no'), 'get');
        echo $OUTPUT->confirm($deletestr, $formcontinue, $formcancel);
        echo $OUTPUT->footer();
        die;
        break;
    case 'editfield':
        $id       = optional_param('id', 0, PARAM_INT);
        $datatype = optional_param('datatype', '', PARAM_ALPHA);

        if ($id == 0) {
            require_capability('totara/'.$capability_prefix.':create'.$prefix.'customfield', $sitecontext);
        } else {
            require_capability('totara/'.$capability_prefix.':update'.$prefix.'customfield', $sitecontext);
        }

        customfield_edit_field($id, $datatype, $typeid, $redirect, $tableprefix, $prefix, $navlinks);
        die;
        break;
    default:
}

// Display page header.
echo $OUTPUT->header();

if (in_array($prefix, array('course', 'program'))) {
    // Show tab.
    $currenttab = $prefix;
    include_once('tabs.php');
} else {
    // Print return to type link.
    $url = $OUTPUT->action_link(new moodle_url('/totara/hierarchy/type/index.php', array('prefix' => $prefix, 'typeid' => $typeid)),
                                "&laquo; " . get_string('alltypes', 'totara_hierarchy'));
    echo html_writer::tag('p', $url);
}

if ($prefix == 'course') {
    $heading = get_string('coursecustomfields', 'totara_customfield');
} else if ($prefix == 'program') {
    $heading = get_string('programcertcustomfields', 'totara_customfield');
} else {
    $heading = format_string($type->fullname);
}
echo $OUTPUT->heading($heading);

// show custom fields for the given type
$table = new html_table();
$table->head  = array(get_string('customfield', 'totara_customfield'), get_string('type', 'totara_hierarchy'));
if ($can_edit || $can_delete) {
    $table->head[] = get_string('edit');
}
if ($prefix == 'course') {
    $table->id = 'customfields_course';
} else if ($prefix == 'program') {
    $table->id = 'customfields_program';
} else {
    $table->id = 'customfields_'.$hierarchy->prefix;
}
$table->data = array();

$where = ($typeid) ? array('typeid' => $typeid) : array();
$fields = customfield_get_defined_fields($tableprefix, $where);

$fieldcount = count($fields);

foreach ($fields as $field) {
    $row = array(format_string($field->fullname), get_string('customfieldtype'.$field->datatype, 'totara_customfield'));
    if ($can_edit || $can_delete) {
        $row[] = customfield_edit_icons($field, $fieldcount, $typeid, $prefix, $can_edit, $can_delete);
    }
    $table->data[] = $row;
}
if (count($table->data)) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nocustomfieldsdefined', 'totara_customfield'));
}
echo html_writer::empty_tag('br');
// Create a new custom field dropdown menu
$options = customfield_list_datatypes();

if ($can_add) {
    if (in_array($prefix, array('course', 'program'))) {
        $select = new single_select(new moodle_url('/totara/customfield/index.php', array('prefix' => $prefix, 'id' => 0, 'action' => 'editfield', 'datatype' => '')), 'datatype', $options, '', array(''=>'choosedots'), 'newfieldform');
        $select->set_label(get_string('createnewcustomfield', 'totara_customfield'));
        echo $OUTPUT->render($select);
    } else {
        $select = new single_select(new moodle_url('/totara/customfield/index.php', array('prefix' => $prefix, 'id' => 0, 'action' => 'editfield', 'typeid' => $typeid, 'datatype' => '')), 'datatype', $options, '', array(''=>'choosedots'), 'newfieldform');
        $select->set_label(get_string('createnewcustomfield', 'totara_customfield'));
        echo $OUTPUT->render($select);
    }
}

echo $OUTPUT->footer();
