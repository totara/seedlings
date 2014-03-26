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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Peter Bulmer <peterb@catalyst.net.nz>
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @author Russell England <russell.england@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
require_once('edit_form.php');
require_once('lib.php');

require_login();

$evidenceid = required_param('id', PARAM_INT); // evidence assignment id
$rolstatus = optional_param('status', 'all', PARAM_ALPHA);
if (!in_array($rolstatus, array('active','completed','all'))) {
    $rolstatus = 'all';
}

if (!$evidence = $DB->get_record('dp_plan_evidence', array('id' => $evidenceid))) {
    print_error('error:evidenceidincorrect', 'totara_plan');
}
$userid = $evidence->userid;

if (!$user = $DB->get_record('user', array('id' => $userid))) {
    print_error('usernotfound', 'totara_plan');
}

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('noblocks');
$PAGE->set_url('/totara/plan/record/evidence/view.php', array('id' => $evidenceid));

if ($USER->id != $userid && !totara_is_manager($userid) && !has_capability('totara/plan:accessanyplan', context_system::instance())) {
    print_error('error:cannotviewpage', 'totara_plan');
}

if ($USER->id != $userid) {
    $strheading = get_string('recordoflearningfor', 'totara_core') . fullname($user, true);
    $usertype = 'manager';
} else {
    $strheading = get_string('recordoflearning', 'totara_core');
    $usertype = 'learner';
}

// get subheading name for display
$indexurl = new moodle_url('/totara/plan/record/index.php', array('userid' => $userid));
$PAGE->navbar->add(get_string('mylearning', 'totara_core'), new moodle_url('/my/learning.php'));
$PAGE->navbar->add($strheading, $indexurl);
$PAGE->navbar->add(get_string('allevidence', 'totara_plan'));

$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);
echo $OUTPUT->header();

echo dp_display_plans_menu($userid, 0, $usertype, 'evidence/index', $rolstatus);

echo $OUTPUT->container_start('', 'dp-plan-content');

echo $OUTPUT->heading($strheading, 1);

dp_print_rol_tabs($rolstatus, 'evidence', $userid);

echo html_writer::tag('p', $OUTPUT->action_link($indexurl,
        get_string('backtoallx', 'totara_plan', get_string('evidenceplural', 'totara_plan'))));

echo display_evidence_detail($evidenceid);

echo list_evidence_in_use($evidenceid);

echo $OUTPUT->container_end();

echo $OUTPUT->footer();