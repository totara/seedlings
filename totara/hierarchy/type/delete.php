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

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once('../lib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/lib.php');


///
/// Setup / loading data
///

$sitecontext = context_system::instance();

// Get params
$id     = required_param('id', PARAM_INT);
$prefix = required_param('prefix', PARAM_ALPHA);
// Delete confirmation hash
$delete = optional_param('delete', '', PARAM_ALPHANUM);

hierarchy::check_enable_hierarchy($prefix);

$hierarchy = hierarchy::load_hierarchy($prefix);

// Setup page and check permissions
admin_externalpage_setup($prefix.'typemanage');

require_capability('totara/hierarchy:delete'.$prefix.'type', $sitecontext);

$type = $hierarchy->get_type_by_id($id);

$back_url = "{$CFG->wwwroot}/totara/hierarchy/type/index.php?prefix=$prefix";

///
/// Display page
///

// User hasn't confirmed deletion yet
if (!$delete) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deletetype', 'totara_hierarchy', format_string($type->fullname)), 1);

    // Review if there are items assigned to this type.
    if ($itemsassigned = $DB->count_records($hierarchy->shortprefix, array('typeid' => $type->id))) {
        $strdelete = get_string('deletechecktypeassociated', 'totara_hierarchy', $itemsassigned);
    } else {
        $strdelete = get_string('deletechecktype', 'totara_hierarchy');
    }
    echo $OUTPUT->confirm($strdelete . html_writer::empty_tag('br') . html_writer::empty_tag('br'), "{$CFG->wwwroot}/totara/hierarchy/type/delete.php?prefix=$prefix&amp;id={$type->id}&amp;delete=".md5($type->timemodified)."&amp;sesskey={$USER->sesskey}", $back_url);

    echo $OUTPUT->footer();
    exit;
}


///
/// Delete type
///

if ($delete != md5($type->timemodified)) {
    print_error('error:deletetypecheckvariable', 'totara_hierarchy');
}

if (!confirm_sesskey()) {
    print_error('confirmsesskeybad', 'error');
}

$deleteresult = $hierarchy->delete_type($type->id);

if ($deleteresult === true) {
    add_to_log(SITEID, $prefix, 'delete type', "type/index.php?prefix=$prefix", "$type->fullname (ID $type->id)");
    totara_set_notification(get_string($prefix.'deletedtype', 'totara_hierarchy', $type->fullname), "{$CFG->wwwroot}/totara/hierarchy/type/index.php?prefix=$prefix", array('class' => 'notifysuccess'));
} else {
    totara_set_notification(get_string($prefix.'error:deletedtype', 'totara_hierarchy', $type->fullname), "{$CFG->wwwroot}/totara/hierarchy/type/index.php?prefix=$prefix");
}
