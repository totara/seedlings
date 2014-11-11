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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');
require_once('add_evidence_form.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/evidence/evidence.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/evidence/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

///
/// Setup / loading data
///

$userid = required_param('userid', PARAM_INT);
$id = required_param('id', PARAM_INT);
$proficiency = optional_param('proficiency', null, PARAM_INT);
$competencyid = optional_param('competencyid', 0, PARAM_INT);
$positionid = optional_param('positionid', 0, PARAM_INT);
$organisationid = optional_param('organisationid', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$nojs = optional_param('nojs', 0, PARAM_INT);

require_login();
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('noblocks');
$PAGE->set_url(qualified_me());
$PAGE->set_pagelayout('noblocks');
$plan = new development_plan($id);
$componentname = 'competency';
$component = $plan->get_component($componentname);

//Permissions check
$result = hierarchy_can_add_competency_evidence($plan, $component, $userid, $competencyid);
if ($result !== true) {
    print_error($result[0], $result[1]);
}

if ($competency_record = $DB->get_record('comp_record', array('userid' => $userid, 'competencyid' => $competencyid))) {
    $evidenceid = $competency_record->id;
    $competency_record->evidenceid = $competency_record->id;
    $competency_record->id = null;
} else {
    $evidenceid = null;
}

$fullname = $plan->name;

if ($u = $DB->get_record('user', array('id' => $userid))) {
    $toform = new stdClass();
    $toform->user = fullname($u);
} else {
    print_error('error:usernotfound','totara_plan');
}

// Check permissions
if ($component->get_setting('setproficiency') != DP_PERMISSION_ALLOW) {
    print_error('error:competencystatuspermission', 'totara_plan');
}

if (!$returnurl) {
    $returnurl = $component->get_url();
}

$mform = new totara_competency_evidence_form(null, compact('id','evidenceid','competencyid','positionid',
    'organisationid','userid','user','s','nojs','returnurl'));
$mform->set_data($competency_record);

if ($mform->is_cancelled()) {
    redirect($returnurl);
}
if ($fromform = $mform->get_data()) { // Form submitted
    if (empty($fromform->submitbutton)) {
        print_error('error:unknownbuttonclicked', 'totara_core', $returnurl);
    }

    // Setup data
    $details = new stdClass();

    $details->positionid = $fromform->positionid;
    $details->organisationid = $fromform->organisationid;

    if ($fromform->assessorid != 0) {
        $details->assessorid = $fromform->assessorid;
    }
    $details->assessorname = $fromform->assessorname;
    $details->assessmenttype = $fromform->assessmenttype;

    // Add evidence
    $result = hierarchy_add_competency_evidence($fromform->competencyid, $fromform->userid, $proficiency, $component, $details);

    if ($result) {
        redirect($returnurl);
    } else {
        redirect($returnurl, get_string('recordnotcreated', 'totara_core'));
    }

} else {
    $mform->set_data($toform);
}

///
/// Display page
///

$prefix = 'competency';
$hierarchy = new $prefix();
$hierarchy->hierarchy_page_setup('item/add');

$fullname = get_string('setcompetencystatus', 'totara_plan');
$pagetitle = format_string($fullname);

dp_get_plan_base_navlinks($plan->userid);
$PAGE->navbar->add($fullname, new moodle_url('/totara/plan/view.php', array('id' => $plan->id)));

$PAGE->set_title($pagetitle);
$PAGE->set_heading(format_string($SITE->fullname));
echo $OUTPUT->header();

// Plan menu
echo dp_display_plans_menu($plan->userid,$plan->id,$plan->role);

// Plan page content
echo $OUTPUT->container_start('', 'dp-plan-content');

print $plan->display_plan_message_box();

echo $OUTPUT->heading($fullname);
print $plan->display_tabs($prefix);

$mform->display();

echo $OUTPUT->container_end();
echo $OUTPUT->footer();
