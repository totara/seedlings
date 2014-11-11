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
$id     = required_param('id', PARAM_INT);
// Delete confirmation hash
$delete = optional_param('delete', '', PARAM_ALPHANUM);
// Course id (if coming from the course view)
$course = optional_param('course', 0, PARAM_INT);

// Load data
$hierarchy         = new competency();
$item              = competency_evidence_type::factory($id);

// Load competency
if (!$competency = $DB->get_record('comp', array('id' => $item->competencyid))) {
    print_error('incorrectcompetencyid', 'totara_hierarchy');
}

// Check capabilities
require_capability('totara/hierarchy:update'.$hierarchy->prefix, $sitecontext);

// Setup page and check permissions
admin_externalpage_setup($hierarchy->prefix.'manage');


///
/// Display page
///

$return = optional_param('returnurl', '', PARAM_LOCALURL);

// Cancel/return url
if (empty($return)) {
    if (!$course) {
        $return = new moodle_url('/totara/hierarchy/item/view.php', array('prefix' => $hierarchy->prefix, 'id' => $item->competencyid));
    } else {
        $return = new moodle_url('/course/competency.php', array('id' => $course));
    }
}


$compname = $DB->get_field('comp', 'fullname', array('id' => $item->competencyid));
if (!$delete) {
    if (!$course) {
        $message = get_string('evidenceitemremovecheck', 'totara_hierarchy', $compname) . html_writer::empty_tag('br') . html_writer::empty_tag('br');
        $message .= $item->get_name() .' ('. $item->get_type().')';
    } else {
        $message = get_string('evidenceitemremovecheck', 'totara_hierarchy', $item->get_name()) . html_writer::empty_tag('br') . html_writer::empty_tag('br');
        $message .= format_string($compname .' ('. $item->get_type().')');
    }

    $actionurlparams = array('id' => $item->id, 'delete' => md5($item->timemodified), 'sesskey' => $USER->sesskey, 'returnurl' => $return);

    // If called from the course view
    if ($course) {
        $actionurlparams['course'] = $course;
    }
    $action = new moodle_url("/totara/hierarchy/prefix/{$hierarchy->prefix}/evidenceitem/remove.php", $actionurlparams);

    echo $OUTPUT->header();

    echo $OUTPUT->confirm($message, $action, $return);

    echo $OUTPUT->footer();
    exit;
}


///
/// Delete
///

if ($delete != md5($item->timemodified)) {
    print_error('checkvariable', 'totara_hierarchy');
}

if (!confirm_sesskey()) {
    print_error('confirmsesskeybad', 'error');
}

$item->delete($competency);

add_to_log(SITEID, 'competency', 'delete evidence', "item/view.php?id={$item->id}&amp;prefix=competency", $item->get_name()." (ID $item->id)");

$message = get_string('removed'.$hierarchy->prefix.'evidenceitem', 'totara_hierarchy', format_string($compname .' ('. $item->get_type().')'));

totara_set_notification($message, $return, array('class' => 'notifysuccess'));
