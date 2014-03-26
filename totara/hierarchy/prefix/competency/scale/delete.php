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
require_once('lib.php');


///
/// Setup / loading data
///

// Get params
$id = required_param('id', PARAM_INT);
$prefix = required_param('prefix', PARAM_ALPHA);
// Delete confirmation hash
$delete = optional_param('delete', '', PARAM_ALPHANUM);

// Cache user capabilities.
$sitecontext = context_system::instance();

// Permissions.
require_capability('totara/hierarchy:deletecompetencyscale', $sitecontext);

// Set up the page.
admin_externalpage_setup($prefix.'manage');

if (!$scale = $DB->get_record('comp_scale', array('id' => $id))) {
    print_error('incorrectcompetencyscaleid', 'totara_hierarchy');
}

$returnurl = "{$CFG->wwwroot}/totara/hierarchy/framework/index.php?prefix=competency";
$deleteurl = "{$CFG->wwwroot}/totara/hierarchy/prefix/competency/scale/delete.php?id={$scale->id}&amp;delete=".md5($scale->timemodified)."&amp;sesskey={$USER->sesskey}&amp;prefix=competency";

// Can't delete if the scale is in use or assigned
if (competency_scale_is_used($id)) {
    print_error('error:nodeletecompetencyscaleinuse', 'totara_hierarchy', $returnurl);
}
if (competency_scale_is_assigned($id)) {
    print_error('error:nodeletecompetencyscaleassigned', 'totara_hierarchy', $returnurl);
}

if (!$delete) {
    echo $OUTPUT->header();
    $strdelete = get_string('deletecheckscale', 'totara_hierarchy');

    echo $OUTPUT->confirm($strdelete . html_writer::empty_tag('br') . html_writer::empty_tag('br') . format_string($scale->name), $deleteurl, $returnurl);

    echo $OUTPUT->footer();
    exit;
}


///
/// Delete competency scale
///

if ($delete != md5($scale->timemodified)) {
    print_error('checkvariable', 'totara_hierarchy');
}

if (!confirm_sesskey()) {
    print_error('confirmsesskeybad', 'error');
}

$transaction = $DB->start_delegated_transaction();

// Delete assignment of scale to frameworks
$DB->delete_records('comp_scale_assignments', array('scaleid' => $scale->id));

// Delete scale values
$DB->delete_records('comp_scale_values', array('scaleid' => $scale->id));

// Delete scale itself
$DB->delete_records('comp_scale', array('id' => $scale->id));

$transaction->allow_commit();

add_to_log(SITEID, 'competency', 'delete competency scale', "framework/index.php?id={$scale->id}&amp;prefix=competency", "$scale->name (ID $scale->id)");

// redirect
totara_set_notification(get_string('deletedcompetencyscale', 'totara_hierarchy', format_string($scale->name)), $CFG->wwwroot . '/totara/hierarchy/framework/index.php?prefix=competency', array('class' => 'notifysuccess'));
