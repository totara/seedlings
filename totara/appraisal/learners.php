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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package totara
 * @subpackage appraisal
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/appraisal/lib.php');
require_once($CFG->dirroot.'/totara/appraisal/lib/assign/lib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

// Check if Appraisals are enabled.
appraisal::check_feature_enabled();

// Get the appraisal id.
$appraisalid = required_param('appraisalid', PARAM_INT);
$module = 'appraisal';
$appraisal = new appraisal($appraisalid);
$assign = new totara_assign_appraisal($module, $appraisal);

// Capability checks.
$systemcontext = context_system::instance();
$canassign = has_capability('totara/appraisal:assignappraisaltogroup', $systemcontext);
$canviewusers = has_capability('totara/appraisal:viewassignedusers', $systemcontext);

$deleteid = optional_param('deleteid', null, PARAM_ALPHANUMEXT);
if ($deleteid && $canassign && ($appraisal->status == appraisal::STATUS_DRAFT)) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }
    list($grp, $aid) = explode("_", $deleteid);
    $assign->delete_assigned_group($grp, $aid);
}

admin_externalpage_setup('manageappraisals');
// Setup the JS.
totara_setup_assigndialogs($module, $appraisalid, $canviewusers);
$output = $PAGE->get_renderer('totara_appraisal');
echo $output->header();
if ($appraisal->id) {
    echo $output->heading($appraisal->name);
    echo $output->appraisal_additional_actions($appraisal->status, $appraisal->id);
}

echo $output->appraisal_management_tabs($appraisal->id, 'learners');

echo $output->heading(get_string('assigncurrentgroups', 'totara_appraisal'));

if ($canassign) {
    if ($appraisal->status == appraisal::STATUS_DRAFT) {
        $options = array_merge(array("" => get_string('assigngroup', 'totara_core')),
                $assign->get_assignable_grouptype_names());
        echo html_writer::select($options, 'groupselector', null, null,
                array('class' => 'group_selector', 'itemid' => $appraisalid));
    } else {
        echo get_string('appraisalactivenochangesallowed', 'totara_appraisal');
    }
}

$currentassignments = $assign->get_current_assigned_groups();

echo $output->display_assigned_groups($currentassignments, $appraisalid);

echo $output->heading(get_string('assigncurrentusers', 'totara_appraisal'));

if ($canviewusers) {
    echo $output->display_user_datatable();
}

echo $output->footer();
