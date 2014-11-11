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
 * @subpackage totara_appraisal
 */

/**
 * User Paginator server-side processing
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/appraisal/lib.php');
require_once($CFG->dirroot.'/totara/appraisal/lib/assign/lib.php');
$module = required_param('module', PARAM_TEXT);
$itemid = required_param('itemid', PARAM_INT);

// Pagination variables.
$secho = required_param('sEcho', PARAM_INT);
$idisplaystart = optional_param('iDisplayStart', 0, PARAM_INT);
$idisplaylength = optional_param('iDisplayLength', 10, PARAM_INT);
$idisplaylength = ($idisplaylength == -1) ? null : $idisplaylength;
$ssearch = optional_param('sSearch', null, PARAM_TEXT);
$appraisal = new $module($itemid);
$assignclassname = "totara_assign_{$module}";
$assignclass = new $assignclassname($module, $appraisal);
$users = $assignclass->get_current_users($ssearch, $idisplaystart, $idisplaylength);
$igrandtotal = $assignclass->get_current_users_count();
$idisplaytotal = $assignclass->get_current_users_count($ssearch);
$emptyassignments = get_string('emptyassignments', 'totara_appraisal');

// Since we only have one page we can save to an array.
$userdata = array();
foreach ($users as $user) {
    $userdata[$user->id] = $user;
}
$users->close();

// Get group info for the users on this page.
$groupinfo = $assignclass->get_group_assignedvia_details(array_keys($userdata));

$aadata = array();
foreach ($userdata as $userid => $user) {
    $url = new moodle_url('/user/view.php', array('id' => $user->id));
    $link = html_writer::link($url, fullname($user));

    $assignvia = array();
    if (!empty($groupinfo[$userid])) {
        foreach ($groupinfo[$userid] as $groupid => $groupstring) {
            $assignvia[] = $groupstring;
        }
    }
    if (empty($assignvia)) {
        $assignviastring = $emptyassignments;
    } else {
        $assignviastring = implode(', ', $assignvia);
    }

    $aadata[] = array($link, $assignviastring);
}

$output = array(
        "sEcho" => $secho,
        "iTotalRecords" => $igrandtotal,
        "iTotalDisplayRecords" => $idisplaytotal,
        "aaData" => $aadata
);
echo json_encode( $output );
