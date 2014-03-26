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
 * @author Aaron Wells <aaronb@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

require_login();

$PAGE->set_context(context_course::instance($COURSE->id));

///
/// Setup / loading data
///

// Plan id
$id = required_param('id', PARAM_INT);

// Updated course lists
$addlist = optional_param('add', null, PARAM_SEQUENCE);
if ($addlist == null) {
    $addlist = array();
}
else {
    $addlist = explode(',', $addlist);
}

// Add course from block
$fromblock = optional_param('fromblock', false, PARAM_BOOL);

$plan = new development_plan($id);
$componentname = 'course';
$component = $plan->get_component($componentname);
$currentlist = $component->get_assigned_items();

$full = $addlist;
foreach ($currentlist as $rec) {
    $full[] = $rec->courseid;
}

///
/// Permissions check
///
require_capability('totara/plan:accessplan', context_system::instance());

if (!$component->can_update_items()) {
    notice(get_string('error:cannotupdateitems', 'totara_plan'));
}

///
/// Update component
///
$component->update_assigned_items($full);

if ($fromblock) {
    require_once($CFG->dirroot . '/blocks/totara_addtoplan/lib.php');

    if (count($addlist) == 1) {
        $courseid = $addlist[0];
        echo totara_block_addtoplan_get_content($courseid, $plan->userid);
    } else {
        echo 'Block error';
    }
} else {
    echo "OK";
}
