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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage totara_plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/lib.php');

// Check if Goals are enabled.
goal::check_feature_enabled();

// Permissions.
require_sesskey();

$scope = required_param('scope', PARAM_INT);
$scalevalueid = required_param('scalevalueid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$nojs = optional_param('nojs', false, PARAM_BOOL);
$itemid = required_param('goalitemid', PARAM_INT);

$goalitem = goal::get_goal_item(array('id' => $itemid), $scope);

$goal = new goal();
if (!$permissions = $goal->get_permissions(null, $userid)) {
    echo get_string('error:viewusergoals', 'totara_hierarchy');
    exit;
}

extract($permissions);

// Check if they have permission to edit this goal.
if ($scope == goal::SCOPE_PERSONAL && !$can_edit[$goalitem->assigntype]) {
    echo get_string('error:updatescalevalue', 'totara_hierarchy');
    exit;
}
if ($scope == goal::SCOPE_COMPANY && !$can_edit_company) {
    echo get_string('error:updatescalevalue', 'totara_hierarchy');
    exit;
}

$todb = new stdClass();
$todb->id = $itemid;
$todb->scalevalueid = $scalevalueid;
$result = goal::update_goal_item($todb, $scope);

$return = new moodle_url('/totara/hierarchy/prefix/goal/mygoals.php', array('userid' => $userid));

if ($result) {
    if ($nojs) {
        $message = get_string('updatescalevaluesuccess', 'totara_hierarchy');
        totara_set_notification($message, $return, array('class' => 'notifysuccess'));
    }
    echo "OK";
} else {
    if ($nojs) {
        $message = get_string('updatescalevaluefailure', 'totara_hierarchy');
        totara_set_notification($message, $return, array('class' => 'notifyproblem'));
    }
    echo get_string('error:updatingscalevalue', 'totara_hierarchy');
}
