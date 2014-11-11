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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage plan 
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

require_login();

$id = required_param('id', PARAM_INT); // plan id
$caid = required_param('itemid', PARAM_INT); // course assignment id

$plan = new development_plan($id);
$componentname = 'course';
$component = $plan->get_component($componentname);

$fullname = $plan->name;
$pagetitle = format_string(get_string('learningplan', 'totara_plan').': '.$fullname);
dp_get_plan_base_navlinks($plan->userid);
$PAGE->navbar->add($fullname, new moodle_url('/totara/plan/view.php', array('id' => $planid)));
$PAGE->navbar->add(get_string($component->component, 'totara_plan'), $component->get_url());
$PAGE->navbar->add(get_string('assessitem', 'totara_plan'));

$PAGE->set_title($pagetitle);

echo $OUTPUT->header();

print $plan->display_plan_message_box();

echo $OUTPUT->heading($fullname);

print $plan->display_tabs($componentname);

print $component->display_back_to_index_link();

echo $OUTPUT->footer();
