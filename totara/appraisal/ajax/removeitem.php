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
 * @subpackage totara_appraisal
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/totara/appraisal/lib.php');

$sytemcontext = context_system::instance();
$PAGE->set_context($sytemcontext);

$appraisalreviewdataid = required_param('id', PARAM_INT);
$reviewdata = $DB->get_record('appraisal_review_data', array('id' => $appraisalreviewdataid));

if (!confirm_sesskey()) {
    print_error('invalidsesskey');
}

// Check that the subject/role are valid in the given appraisal.
$defquestion = new appraisal_question($reviewdata->appraisalquestfieldid);
$page = new appraisal_page($defquestion->appraisalstagepageid);
$stage = new appraisal_stage($page->appraisalstageid);
$appraisal = new appraisal($stage->appraisalid);
$roleassignment = new appraisal_role_assignment($reviewdata->appraisalroleassignmentid);

if (!$appraisal->can_access($roleassignment)) {
    throw new moodle_exception('error:cannotaccessappraisal');
}

// Check if other roles have already provided answers, preventing the deletion.
$roles = appraisal::get_related_roleassignmentids($reviewdata->appraisalroleassignmentid);
unset($roles[$reviewdata->appraisalroleassignmentid]);
if (!empty($roles)) {
    list($rolessql, $roleids) = $DB->get_in_or_equal($roles);

    $sql = "SELECT *
              FROM {appraisal_review_data}
             WHERE itemid = ?
               AND appraisalquestfieldid = ?
               AND appraisalroleassignmentid " . $rolessql . "
               AND NOT " . $DB->sql_isempty('appraisal_review_data', 'content', true, true);
    $params = array_merge(array($reviewdata->itemid, $reviewdata->appraisalquestfieldid), $roleids);

    if ($reviewdata->scope > 0) {
        $sql .= ' AND scope = ?';
        $params[] = $reviewdata->scope;
    }
    $otherroleanswers = $DB->get_records_sql($sql, $params);
} else {
    $otherroleanswers = array();
}

if (empty($otherroleanswers)) {
    $DB->delete_records('appraisal_review_data', array('itemid' => $reviewdata->itemid, 'scope' => $reviewdata->scope,
            'appraisalquestfieldid' =>  $reviewdata->appraisalquestfieldid));
    if (is_ajax_request($_SERVER)) {
        echo ('success');
    }
} else {
    if (is_ajax_request($_SERVER)) {
        echo ('failed - locked because other role has provided answer');
    }
}
