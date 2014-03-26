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
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @package totara
 * @subpackage plan
 */

/**
 * Generate header including plan details
 *
 * Only included via development_plan::print_header()
 *
 * The following variables will be set:
 *
 * - $this              Plan instance
 * - $CFG               Config global
 * - $currenttab        Current tab
 * - $navlinks          Additional breadcrumbs (optional)
 */
global $PAGE, $OUTPUT;
(defined('MOODLE_INTERNAL') && isset($this)) || die();
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

// Check if this is a component
if (array_key_exists($currenttab, $this->get_components())) {
    $component = $this->get_component($currenttab);
    $is_component = true;
}
else {
    $is_component = false;
}

$fullname = $this->name;
$pagetitle = format_string(get_string('learningplan', 'totara_plan').': '.$fullname);

//Javascript include
local_js(array(
    TOTARA_JS_DATEPICKER,
    TOTARA_JS_PLACEHOLDER
));


$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
echo $OUTPUT->header();

// Run post header hook (if this is a component)
if ($is_component) {
    $component->post_header_hook();
}


// Plan menu
echo dp_display_plans_menu($this->userid, $this->id, $this->role);

// Plan page content
echo $OUTPUT->container_start('', 'dp-plan-content');

echo $this->display_plan_message_box();

$heading = html_writer::tag('span', get_string('plan', 'totara_plan') . ':', array('class' => 'dp-plan-prefix'));
echo $OUTPUT->heading($heading . ' ' . $fullname);

print $this->display_tabs($currenttab);

if ($printinstructions) {
    //
    // Display instructions
    //
    $instructions = '';
    if ($this->role == 'manager') {
        $instructions .= get_string($currenttab.'_instructions_manager', 'totara_plan') . ' ';
    } else {
        $instructions .= get_string($currenttab.'_instructions_learner', 'totara_plan') . ' ';
    }

    // If this a component
    if ($is_component) {
        $instructions .= get_string($currenttab.'_instructions_detail', 'totara_plan') . ' ';

        if (!$this->is_active() || $component->get_setting('update'.$currenttab) > DP_PERMISSION_REQUEST) {
            $instructions .= get_string($currenttab.'_instructions_add11', 'totara_plan') . ' ';
        }
        if ($this->is_active() && $component->get_setting('update'.$currenttab) == DP_PERMISSION_REQUEST) {
            $instructions .= get_string($currenttab.'_instructions_request', 'totara_plan') . ' ';
        }
    }

    print $OUTPUT->container($instructions, "instructional_text");
}
