<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
 * Copyright (C) 1999 onwards Martin Dougiamas
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
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_courses.class.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');

$PAGE->set_context(context_system::instance());
// Check if Learning plans are enabled.
check_learningplan_enabled();

require_login();

///
/// Setup / loading data
///

// Plan id
$id = required_param('id', PARAM_INT);

// Category id
$categoryid = optional_param('parentid', 'cat0', PARAM_ALPHANUM);

// Strip cat from begining of categoryid
$categoryid = (int) substr($categoryid, 3);


///
/// Load plan
///
require_capability('totara/plan:accessplan', context_system::instance());

$plan = new development_plan($id);
$component = $plan->get_component('course');

// Access control check
if (!$permission = $component->can_update_items()) {
    print_error('error:cannotupdatecourses', 'totara_plan');
}

$selected = array();
$unremovable = array();

foreach ($component->get_assigned_items() as $item) {
    $item->id = $item->courseid;
    $selected[$item->courseid] = $item;

    if (!$component->can_delete_item($item)) {
        $unremovable[$item->courseid] = $item;
    }
}


///
/// Setup dialog
///

// Load dialog content generator
$dialog = new totara_dialog_content_courses($categoryid);

// Set type to multiple
$dialog->type = totara_dialog_content::TYPE_CHOICE_MULTI;
$dialog->selected_title = 'itemstoadd';

// Add data
$dialog->load_courses();

// Set selected items
$dialog->selected_items = $selected;

// Set unremovable items
$dialog->unremovable_items = $unremovable;

// Addition url parameters
$dialog->urlparams = array('id' => $id);

// Display page
echo $dialog->generate_markup();
