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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage plan 
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

$id = required_param('id', PARAM_INT);
$edit = optional_param('edit', 'off', PARAM_TEXT);

if (!isset($currenttab)) {
    $currenttab = 'competencies';
}

$toprow = array();
$secondrow = array();
$activated = array();
$inactive = array();

// General Tab

$toprow[] = new tabobject('general', $CFG->wwwroot.'/totara/plan/template/general.php?id='.$id, get_string('general', 'totara_plan'));
if (substr($currenttab, 0, 7) == 'general') {
    $activated[] = 'general';
}

// Components Tab
$toprow[] = new tabobject('components', $CFG->wwwroot.'/totara/plan/template/components.php?id='.$id, get_string('components', 'totara_plan'));
if (substr($currenttab, 0, 10) == 'components') {
    $activated[] = 'components';
}

// Workflow Tab
$toprow[] = new tabobject('workflow', $CFG->wwwroot.'/totara/plan/template/workflow.php?id='.$id, get_string('workflow', 'totara_plan'));
if (substr($currenttab, 0, 8) == 'workflow') {
    $activated[] = 'workflow';
}
if ($currenttab == 'workflowplan') {
    $secondrow[] = new tabobject('advancedworkflow', $CFG->wwwroot.'/totara/plan/template/advancedworkflow.php?component=plan&amp;id='.$id, get_string('plan', 'totara_plan'));

    // Check if we are on this tab
    if ($currentcomponent == 'plan') {
        $currenttab = 'advancedworkflow';
    }

    // add one tab per active component
    if ($components) {
        foreach ($components as $component) {
            if (!$component->enabled) {
                continue;
            }

            $configsetting = get_config(null, 'dp_'.$component->component);
            $compname = $configsetting ? $configsetting : get_string($component->component.'plural', 'totara_plan');
            $secondrow[] = new tabobject('workflow'.$component->component, $CFG->wwwroot.'/totara/plan/template/advancedworkflow.php?component='.$component->component.'&amp;id='.$id, $compname);

            if ($component->component == $currentcomponent) {
                $currenttab = 'workflow'.$component->component;
            }
        }
    }
}

if (!empty($secondrow)) {
    $tabs = array($toprow, $secondrow);
} else {
    $tabs = array($toprow);
}

// print out tabs
print_tabs($tabs, $currenttab, $inactive, $activated);
