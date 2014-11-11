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
 * @author Eugene Venter<eugene@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_courses.class.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_programs.class.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_certifications.class.php');
require_once("{$CFG->dirroot}/cohort/lib.php");

$sitecontext = context_system::instance();
$PAGE->set_context($sitecontext);
require_login();
require_capability('moodle/cohort:manage', $sitecontext);

$cohortid = required_param('cohortid', PARAM_INT);
$type = required_param('type', PARAM_INT);
$value = optional_param('v', COHORT_ASSN_VALUE_ENROLLED, PARAM_INT);
$categoryid = optional_param('parentid', 'cat0', PARAM_ALPHANUM); // Category id
// Strip cat from begining of categoryid
$categoryid = (int) substr($categoryid, 3);

$assigned = totara_cohort_get_associations($cohortid, $type, $value);
$selected = array();
foreach ($assigned as $item) {
    $item->id = $item->instanceid;
    $selected[$item->instanceid] = $item;
}
unset($assigned);

///
/// Setup dialog
///

// Load dialog content generator
switch ($type) {
    case COHORT_ASSN_ITEMTYPE_COURSE:
        $dialog = new totara_dialog_content_courses($categoryid);
        break;
    case COHORT_ASSN_ITEMTYPE_PROGRAM:
        $dialog = new totara_dialog_content_programs($categoryid);
        break;
    case COHORT_ASSN_ITEMTYPE_CERTIF:
        $dialog = new totara_dialog_content_certifications($categoryid);
        break;
    default:
        print_error('learningtypenotrecognised');
        break;
}

// Set type to multiple
$dialog->type = totara_dialog_content::TYPE_CHOICE_MULTI;

$dialog->selected_title = 'itemstoadd';
//show all courses
$dialog->requirecompletioncriteria = false;
$dialog->requirecompletion = false;

// Add data
switch ($type) {
    case COHORT_ASSN_ITEMTYPE_COURSE:
        $dialog->load_courses();
        break;
    case COHORT_ASSN_ITEMTYPE_PROGRAM:
        $dialog->load_programs();
        break;
    case COHORT_ASSN_ITEMTYPE_CERTIF:
        $dialog->load_certifications();
        break;
    default:
        break;
}

// Set selected items
$dialog->selected_items = $selected;

// Addition url parameters
$dialog->urlparams = array('cohortid' => $cohortid, 'type' => $type);

// Display page
echo $dialog->generate_markup();
