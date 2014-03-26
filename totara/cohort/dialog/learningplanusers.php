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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage cohort
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/cohort/lib.php');

defined('MOODLE_INTERNAL') || die();

$PAGE->set_context(context_system::instance());
require_login();
require_capability('moodle/cohort:manage', context_system::instance());

$cohortid = required_param('id', PARAM_INT);
$plantemplate = required_param('plantemplate', PARAM_INT);
$manualplan = required_param('manual', PARAM_BOOL);
$autoplan = required_param('auto', PARAM_BOOL);
$completeplan = required_param('complete', PARAM_BOOL);

// Calculate the number of affected users and return
// warning message
$sql = 'SELECT COUNT(DISTINCT cm.userid)
        FROM {cohort_members} cm
        WHERE';
$params = array();
//are we excluding anyone at all?
if ($manualplan || $autoplan || $completeplan) {
    $planwhere = 'p.templateid = ?';
    $params[] = $plantemplate;
    $whereclauses = array();

    $createdby = array();
    if ($manualplan) {
        $createdby[] = PLAN_CREATE_METHOD_MANUAL;
    }
    if ($autoplan) {
        $createdby[] = PLAN_CREATE_METHOD_COHORT;
    }
    if (!empty($createdby)) {
        list($insql, $inparams) = $DB->get_in_or_equal($createdby);
        $whereclauses[] = " p.createdby $insql";
        $params = array_merge($params, $inparams);
    }

    if ($completeplan) {
        $whereclauses[] = ' p.status = ? ';
        $params[] = DP_PLAN_STATUS_COMPLETE;
    }
    //we only have two clauses now but just in case we add more
    $numclauses = count($whereclauses);
    if ($numclauses > 0) {
        $planwhere .= ' AND (';
        for ($i=0; $i<$numclauses; $i++) {
            $planwhere .= $whereclauses[$i];
            if ($i < ($numclauses - 1)) {
                $planwhere .= ' OR ';
            }
        }
        $planwhere .= ')';
    }
    //add the exclusion SQL clause
    $sql .= '
        NOT EXISTS
            (SELECT p.userid
            FROM {dp_plan} p
            WHERE ' . $planwhere . ' AND cm.userid = p.userid)
        AND ';
}

$where = ' cm.cohortid = ?';
$params[] = $cohortid;
$sql .= $where;

$count = $DB->count_records_sql($sql, $params);
if ($count > 0) {
    $html = get_string('confirmcreateplansmessage', 'totara_plan', $count);
    $data = array('html' => $html, 'nousers' => 'false');
} else {
    $html = get_string('confirmnousers', 'totara_plan');
    $data = array('html' => $html, 'nousers' => 'true');
}

echo json_encode($data);
