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
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/evidenceitem/type/abstract.php');


///
/// Setup / loading data
///

$sitecontext = context_system::instance();

// Get params
$id      = required_param('id', PARAM_INT); // Competency ID
$related = required_param('related', PARAM_INT); // Related competency ID

// Delete confirmation hash
$delete = optional_param('delete', '', PARAM_ALPHANUM);

// Load data
$hierarchy         = new competency();

// The relationship could be recorded in one of two directions
$item = $DB->get_record('comp_relations', array('id1' => $id, 'id2' => $related), '*', IGNORE_MISSING); //hack to return false for next step
if (!$item) {
    $item = $DB->get_record('comp_relations', array('id2' => $id, 'id1' => $related));
}

// If the relationship's not recorded in either direction
if (!$item) {
    print_error('competencyrelationshipnotfound', 'totara_hierarchy');
}

// Load related competency
if (!$rcompetency = $DB->get_record('comp', array('id' => $related))) {
    print_error('incorrectcompetencyid', 'totara_hierarchy');
}

// Check capabilities
require_capability('totara/hierarchy:update'.$hierarchy->prefix, $sitecontext);

// Setup page and check permissions
admin_externalpage_setup($hierarchy->prefix.'manage');


///
/// Display page
///

echo $OUTPUT->header();

// Cancel/return url
$return = "{$CFG->wwwroot}/totara/hierarchy/item/view.php?prefix={$hierarchy->prefix}&id={$id}";


if (!$delete) {
    $message = get_string('relateditemremovecheck', 'totara_hierarchy'). html_writer::empty_tag('br') . html_writer::empty_tag('br');
    $message .= format_string($rcompetency->fullname);

    $action = "{$CFG->wwwroot}/totara/hierarchy/prefix/competency/related/remove.php?id={$id}&amp;related={$related}&amp;delete=".md5($rcompetency->timemodified)."&amp;sesskey={$USER->sesskey}";

    echo $OUTPUT->confirm($message, $action, $return);

    echo $OUTPUT->footer();
    exit;
}


///
/// Delete
///

if ($delete != md5($rcompetency->timemodified)) {
    print_error('checkvariable', 'totara_hierarchy');
}

if (!confirm_sesskey()) {
    print_error('confirmsesskeybad', 'error');
}

// Delete relationship
$DB->delete_records('comp_relations', array('id1' => $id, 'id2' => $related));
$DB->delete_records('comp_relations', array('id2' => $id, 'id1' => $related));

add_to_log(SITEID, 'competency', 'delete related', "item/view.php?id=$id", $rcompetency->fullname." (ID $related)");

$message = get_string('removed'.$hierarchy->prefix.'relateditem', 'totara_hierarchy', format_string($rcompetency->fullname));

echo $OUTPUT->heading($message);
echo $OUTPUT->continue_button($return);
echo $OUTPUT->footer();
