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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/lib.php');
require_once($CFG->dirroot.'/totara/customfield/fieldlib.php');
require_once($CFG->libdir.'/filelib.php');

// Get data
$prefix        = required_param('prefix', PARAM_ALPHA);
$id          = required_param('id', PARAM_INT);
$edit        = optional_param('edit', -1, PARAM_BOOL);
$frameworkid = optional_param('framework', 0, PARAM_INT);
$sitecontext = context_system::instance();
$shortprefix = hierarchy::get_short_prefix($prefix);

hierarchy::check_enable_hierarchy($prefix);

$hierarchy = hierarchy::load_hierarchy($prefix);

/*
 * Setup / loading data.
 */

if (!$item = $hierarchy->get_item($id)) {
    print_error('itemdoesntexist', 'totara_hierarchy', null, $prefix);
}
$framework = $hierarchy->get_framework($item->frameworkid);

// Cache user capabilities.
extract($hierarchy->get_permissions());

if (!$canviewitems) {
    print_error('accessdenied', 'admin');
}

if ($canmanage) {
    // Setup page as admin and check permissions.
    admin_externalpage_setup($prefix.'manage', '', array('prefix' => $prefix));
} else {
    // Non admin page set up.
    $PAGE->set_context($sitecontext);
    $pagetitle = format_string($framework->fullname.' - '.$item->fullname);
    $PAGE->set_title($pagetitle);
    $PAGE->set_heading('');
    $PAGE->set_url('/totara/hierarchy/item/view.php', array('prefix' => $prefix, 'id' => $id));
    $PAGE->set_pagelayout('admin');
    if ($canviewframeworks) {
        $PAGE->navbar->add(get_string("{$prefix}frameworks", 'totara_hierarchy'),
                new moodle_url("../index.php", array('prefix' => $prefix)));
    } else {
        $PAGE->navbar->add(get_string("{$prefix}frameworks", 'totara_hierarchy'));
    }
}

///
/// Display page
///

// Run any hierarchy prefix specific code
$compfw = optional_param('framework', 0, PARAM_INT);
$setupitem = new stdClass;
$setupitem->id = $item->id;
$setupitem->frameworkid = $compfw;

$hierarchy->hierarchy_page_setup('item/view', $setupitem);

unset($setupitem);

if (!$framework = $DB->get_record($shortprefix.'_framework', array('id' => $item->frameworkid))) {
    print_error('invalidframeworkid', 'totara_hierarchy', $prefix);
}

if ($canmanageframeworks) {
    $PAGE->navbar->add(format_string($framework->fullname), new moodle_url("../index.php", array('prefix' => $prefix, 'frameworkid' => $framework->id)));
} else {
    $PAGE->navbar->add(format_string($framework->fullname));
}

$PAGE->navbar->add(format_string($item->fullname));
echo $OUTPUT->header();

$heading = format_string("{$framework->fullname} - {$item->fullname}");

// add editing icon
$str_edit = get_string('edit');

if ($canupdateitems) {
    $heading .= ' ' . $OUTPUT->action_icon(new moodle_url("edit.php",
            array('prefix' => $prefix, 'frameworkid' => $framework->id, 'id' => $item->id)),
            new pix_icon('t/edit', $str_edit, 'moodle', array('class' => 'iconsmall')));
}

echo $OUTPUT->heading($heading);
$data = $hierarchy->get_item_data($item);
$cfdata = $hierarchy->get_custom_fields($item->id);
if ($cfdata) {
    foreach ($cfdata as $cf) {
        // don't show hidden custom fields
        if ($cf->hidden) {
            continue;
        }
        $cf_class = "customfield_{$cf->datatype}";
        require_once($CFG->dirroot.'/totara/customfield/field/'.$cf->datatype.'/field.class.php');
        $data[] = array(
            'title' => $cf->fullname,
            'value' => call_user_func(array($cf_class, 'display_item_data'), $cf->data, array('prefix' => $prefix, 'itemid' => $cf->id))
        );
    }
}

$table = new html_table();

foreach ($data as $ditem) {

    // Check if empty
    if (!strlen($ditem['value'])) {
        continue;
    }

    $header = new html_table_cell(format_string($ditem['title']));
    $header->header = true;
    $cell = new html_table_cell(format_string($ditem['value']));
    $row = new html_table_row(array($header, $cell));
    $table->data[] = $row;
}

echo html_writer::table($table);

// Print extra info
$hierarchy->display_extra_view_info($item, $frameworkid);

if ($canmanageframeworks) {
    $options = array('prefix' => $prefix,'frameworkid' => $framework->id);
    $button = $OUTPUT->single_button(new moodle_url('../index.php', $options), get_string($prefix.'returntoframework', 'totara_hierarchy'), 'get');

    echo html_writer::tag('div', $button, array('class' => 'buttons'));
}
/// and proper footer
add_to_log(SITEID, $prefix, 'view item', "item/view.php?prefix=$prefix&amp;framework={$framework->id}&amp;id={$item->id}", substr(strip_tags($item->fullname), 0, 200) . " (ID {$item->id})");
echo $OUTPUT->footer();
