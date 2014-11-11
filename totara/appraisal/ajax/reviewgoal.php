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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_question
 */

define('AJAX_SCRIPT', true);
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once(dirname(dirname(__FILE__)) . '/lib.php');

$sytemcontext = context_system::instance();
$PAGE->set_context($sytemcontext);

$id = required_param('id', PARAM_INT);
$roleassignmentid = required_param('answerid', PARAM_INT);

$goalitems = optional_param('update', null, PARAM_RAW);
$idlist = (!$goalitems) ? array() : explode(',', $goalitems);
if (empty($idlist)) {
    exit;
}
$personal_idlist = array();
$company_idlist = array();
foreach ($idlist as $goalid) {
    if (strpos($goalid, 'p') !== false) {
        $personal_idlist[] = ltrim($goalid, 'p');
    } else {
        $company_idlist[] = $goalid;
    }
}

$defquestion = new appraisal_question($id);
$page = new appraisal_page($defquestion->appraisalstagepageid);
$stage = new appraisal_stage($page->appraisalstageid);
$appraisal = new appraisal($stage->appraisalid);
$roleassignment = new appraisal_role_assignment($roleassignmentid);

$otherassignments = $appraisal->get_all_assignments($roleassignment->subjectid);
unset($otherassignments[$roleassignment->appraisalrole]);
$question = new appraisal_question($id, $roleassignment);
$review = $question->get_element();
$rights = $question->roles[$roleassignment->appraisalrole];

if ($roleassignment->userid != $USER->id) {
    throw new appraisal_exception('Wrong assignment');
}

$newitems = array();
if (!empty($personal_idlist) && $review->cananswer && $review->can_select_personal()) {
    $personal_checkedids = $review->check_target_ids($personal_idlist, $roleassignment->subjectid, goal::SCOPE_PERSONAL);
    foreach ($personal_checkedids as $itemid) {
        $item = new stdClass();
        $item->itemid = $itemid;
        $item->scope = goal::SCOPE_PERSONAL;
        if (!$review->stub_exists(array('itemid' => $item->itemid, 'scope' => $item->scope))) {
            $review->prepare_stub($item);
            $newitems[$item->scope][$item->itemid] = true;
        }
    }
}
if (!empty($company_idlist) && $review->cananswer && $review->can_select_company()) {
    $company_checkedids = $review->check_target_ids($company_idlist, $roleassignment->subjectid, goal::SCOPE_COMPANY);
    foreach ($company_checkedids as $itemid) {
        $item = new stdClass();
        $item->itemid = $itemid;
        $item->scope = goal::SCOPE_COMPANY;
        if (!$review->stub_exists(array('itemid' => $item->itemid, 'scope' => $item->scope))) {
            $review->prepare_stub($item);
            $newitems[$item->scope][$item->itemid] = true;
        }
    }
}
if (empty($newitems)) {
    exit;
}

if (($rights & appraisal::ACCESS_CANVIEWOTHER) == appraisal::ACCESS_CANVIEWOTHER) {
    $question->populate_roles_element($roleassignment, $otherassignments);
}
$renderer = $PAGE->get_renderer('totara_question');
$items = $review->get_grouped_items();
foreach ($items as $scopekey => $scope) {
    foreach ($scope as $groupkey => $itemgroup) {
        if (!isset($newitems[$scopekey][$groupkey])) {
            unset($items[$scopekey][$groupkey]);
        }
    }
}
$form = new MoodleQuickForm(null, null, null);
$renderer->add_review_items($form, $items, $review);
$form->display();
