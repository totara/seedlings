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
 * @subpackage hierarchy
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/lib.php');
require_once($CFG->dirroot.'/totara/hierarchy/type/changelib.php');


///
/// Setup / loading data
///

// Get params
$prefix        = required_param('prefix', PARAM_SAFEDIR);
$typeid     = required_param('typeid', PARAM_INT);
$itemid      = optional_param('itemid', null, PARAM_INT);
$page        = optional_param('page', 0, PARAM_INT);
$shortprefix = hierarchy::get_short_prefix($prefix);

hierarchy::check_enable_hierarchy($prefix);

$hierarchy = hierarchy::load_hierarchy($prefix);

// the form can be used to modify individual items and whole types
// set some variables to manage the differences in behaviours between
// the two cases
if ($itemid) {
    $item = $DB->get_record($shortprefix, array('id' => $itemid));
    $item_sql = "AND id = ?";
    $item_param = array($itemid);
    $returnurl = $CFG->wwwroot . '/totara/hierarchy/item/edit.php?prefix=' .
        $prefix . '&amp;id=' . $itemid. '&amp;page=' . $page;
    $returnparams = array('prefix' => $prefix, 'id' => $itemid, 'page' => $page);
    $optype = 'item'; // used for switching lang strings
    $adminpage = $prefix . 'manage';
} else {
    $item_sql = '';
    $item_param = array();
    $returnurl = $CFG->wwwroot . '/totara/hierarchy/type/index.php?prefix=' . $prefix . '&amp;page=' . $page;
    $returnparams = array('prefix' => $prefix, 'page' => $page);
    $optype = 'bulk';
    $adminpage = $prefix . 'typemanage';
}

// Setup page and check permissions
admin_externalpage_setup($adminpage, null, array('prefix' => $prefix));

// make sure the itemid is valid (if provided)
if ($itemid && !$item) {
    print_error('error:invaliditemid', 'totara_hierarchy');
}

// how many items in the type being changed?
$params = array_merge(array($typeid), $item_param);
$select = "typeid = ? {$item_sql}";
$affected_item_count = $DB->count_records_select($shortprefix, $select, $params);

// redirect with a message if there are no items in that type
if ($affected_item_count == 0) {
    totara_set_notification(get_string('error:nonefound' . $optype, 'totara_hierarchy'), $returnurl);
}

///
/// Load data for type details
///

// Get types for this page
$types = $hierarchy->get_types(array('item_count' => 1));

// get custom fields for this hierarchy, and re-group by typeid
$rs = $DB->get_recordset($shortprefix . '_type_info_field', array(), 'typeid');
$cfs_by_type = totara_group_records($rs, 'typeid');
$cfs_by_type = ($cfs_by_type) ? $cfs_by_type : array();

// take out the custom field info for the type being changed
if (array_key_exists($typeid, $cfs_by_type)) {
    $current_type_cfs = $cfs_by_type[$typeid];
    unset($cfs_by_type[$typeid]);
} else {
    $current_type_cfs = null;
}
$rs->close();

///
/// Generate / display page
///

// Breadcrumbs (different if changing a single item vs. whole type)
if ($itemid) {
    $framework = $DB->get_record($shortprefix.'_framework', array('id' => $item->frameworkid));
    $PAGE->navbar->add(get_string("{$prefix}frameworks", 'totara_hierarchy'), new moodle_url('/totara/hierarchy/framework/index.php', array('prefix' => $prefix)));
    $PAGE->navbar->add(format_string($framework->fullname), new moodle_url('/totara/hierarchy/index.php', array('prefix' => $prefix, 'frameworkid' => $framework->id)));
    $PAGE->navbar->add(format_string($item->fullname), new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => $prefix, 'id' => $item->id)));
    $PAGE->navbar->add(get_string('edit'.$prefix, 'totara_hierarchy'), new moodle_url('/totara/hierarchy/item/edit.php', array('prefix' => $prefix, 'id' => $itemid)));
    $PAGE->navbar->add(get_string('changetype', 'totara_hierarchy'));

} else {
    $PAGE->navbar->add(get_string('types', 'totara_hierarchy'), new moodle_url('/totara/hierarchy/type/index.php', array('prefix' => $prefix)));
    $PAGE->navbar->add(get_string('bulktypechanges', 'totara_hierarchy'));
}

echo $OUTPUT->header();

echo $OUTPUT->single_button(new moodle_url($returnurl, $returnparams), get_string('cancel'), 'get');

// step 1 of 2
// form for picking the new type
// only show if newtype is not yet specified

$a = new stdClass();
if ($itemid) {
    $a->name = format_string($item->fullname);
} else {
    $itemstr = ($affected_item_count == 1) ? strtolower(get_string($prefix, 'totara_hierarchy')) :
        strtolower(get_string($prefix.'plural', 'totara_hierarchy'));
    $a->num = $affected_item_count;
    $a->items = $itemstr;
}
echo $OUTPUT->heading(get_string('reclassify1of2' . $optype, 'totara_hierarchy', $a), 1);

echo $OUTPUT->container_start();
echo html_writer::start_tag('p');
echo get_string('reclassify1of2desc', 'totara_hierarchy');
// if there's data to transfer, let people know they'll get the chance to move it
// in step 2
if ($current_type_cfs) {
    echo ' ' . get_string('reclassifytransferdata', 'totara_hierarchy');
}
echo html_writer::end_tag('p');
echo $OUTPUT->container_end();

echo $OUTPUT->heading(get_string('currenttype', 'totara_hierarchy'), 3, 'hierarchy-bulk-type');

$table = new html_table();

// Setup column headers
$table->head = array('',
    get_string('name'),
    get_string('customfields', 'totara_customfield'));

$row = array();
$row[] = '&nbsp;';
$row[] = hierarchy_get_type_name($typeid, $shortprefix);
$row[] = ($cfs = hierarchy_get_formatted_custom_fields($current_type_cfs)) ?
    implode(html_writer::empty_tag('br'), $cfs) :
    get_string('nocustomfields', 'totara_hierarchy');
$table->data[] = $row;

echo html_writer::table($table);

// empty table data ready for the list of new type
$table->data = array();

foreach ($types as $type) {
    // don't show current type
    if ($type->id == $typeid) {
        continue;
    }

    $row = array();

    // button to pick this type
    $row[] = $OUTPUT->single_button(new moodle_url('/totara/hierarchy/type/changeconfirm.php', array('prefix' => $prefix, 'typeid' => $typeid, 'newtypeid' => $type->id, 'itemid' => $itemid, 'page' => $page)), get_string('choose'), 'get');

    // type name
    $row[] = format_string($type->fullname);

    // custom fields in this type
    $cfdata = (array_key_exists($type->id, $cfs_by_type)) ? $cfs_by_type[$type->id] : null;
    $row[] = ($cfs = hierarchy_get_formatted_custom_fields($cfdata)) ?
        implode(html_writer::empty_tag('br'), $cfs) :
        get_string('nocustomfields', 'totara_hierarchy');

    $table->data[] = $row;
}

// add 'unclassified' as an option (unless that's the old type)
if ($typeid != 0) {
    $row = array();
    $row[] = $OUTPUT->single_button(new moodle_url('/totara/hierarchy/type/changeconfirm.php', array('prefix' => $prefix, 'typeid' => $typeid, 'newtypeid' => 0, 'itemid' => $itemid)), get_string('choose'), 'get');
    $row[] = get_string('unclassified', 'totara_hierarchy');
    $row[] = get_string('nocustomfields', 'totara_hierarchy');
    $table->data[] = $row;
}


echo $OUTPUT->heading(get_string('newtype', 'totara_hierarchy'), 3, 'hierarchy-bulk-type');

echo html_writer::table($table);

echo $OUTPUT->footer();
