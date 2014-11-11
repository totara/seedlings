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
 * @subpackage program
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

$id = optional_param('id', 0, PARAM_INT);
$edit = optional_param('edit', 'off', PARAM_TEXT);
$iscertif = optional_param('iscertif', 0, PARAM_BOOL);
if ($id) {
    $iscertif = ($DB->get_field('prog', 'certifid', array('id' => $id)) ? 1 : 0);
}

if (!isset($currenttab)) {
    $currenttab = 'details';
}

if (isset($programcontext)) {
    $context = $programcontext;
} else if (isset($program)) {
    $context = $program->get_context();
} else if (isset($systemcontext)) {
    $context = $systemcontext;
} else {
    $context = context_system::instance();
}

$toprow = array();
$secondrow = array();
$activated = array();
$inactive = array();

// Overview Tab
$toprow[] = new tabobject('overview', $CFG->wwwroot.'/totara/program/edit.php?id='.$id, get_string('overview', 'totara_program'));
if (substr($currenttab, 0, 7) == 'overview'){
    $activated[] = 'overview';
}

// Details Tab
if (has_capability('totara/program:configuredetails', $context)) {
    //disable details link if creating a new program to avoid fatal error
    $url = ($id == 0) ? '#' : $CFG->wwwroot.'/totara/program/edit.php?id='.$id.'&amp;action=edit';
    $toprow[] = new tabobject('details', $url, get_string('details', 'totara_program'));
    if (substr($currenttab, 0, 7) == 'details'){
        $activated[] = 'details';
    }
}

// Content Tab
if (has_capability('totara/program:configurecontent', $context)) {
    $toprow[] = new tabobject('content', $CFG->wwwroot.'/totara/program/edit_content.php?id='.$id, get_string('content', 'totara_program'));
    if (substr($currenttab, 0, 7) == 'content'){
        $activated[] = 'content';
    }
}

// Assignments Tab
if (has_capability('totara/program:configureassignments', $context)) {
    $toprow[] = new tabobject('assignments', $CFG->wwwroot.'/totara/program/edit_assignments.php?id='.$id, get_string('assignments', 'totara_program'));
    if (substr($currenttab, 0, 11) == 'assignments'){
        $activated[] = 'assignments';
    }
}

// Messages Tab
if (has_capability('totara/program:configuremessages', $context)) {
    $toprow[] = new tabobject('messages', $CFG->wwwroot.'/totara/program/edit_messages.php?id='.$id, get_string('messages', 'totara_program'));
    if (substr($currenttab, 0, 8) == 'messages'){
        $activated[] = 'messages';
    }
}

// Certification Tab
if ($iscertif && has_capability('totara/certification:configurecertification', $context)
    && totara_feature_visible('certifications')) {
    $toprow[] = new tabobject('certification', $CFG->wwwroot.'/totara/certification/edit_certification.php?id='.$id,
                    get_string('certification', 'totara_certification'));
    if (substr($currenttab, 0, 13) == 'certification') {
        $activated[] = 'certification';
    }
}


// Exceptions Report Tab
// Only show if there are exceptions or you are on the exceptions tab already
if (has_capability('totara/program:handleexceptions', $context) && ($exceptions || (substr($currenttab, 0, 10) == 'exceptions'))) {
    $exceptioncount = $exceptions ? $exceptions : '0';
    $toprow[] = new tabobject('exceptions', $CFG->wwwroot.'/totara/program/exceptions.php?id='.$id, get_string('exceptions', 'totara_program', $exceptioncount));
    if (substr($currenttab, 0, 10) == 'exceptions'){
        $activated[] = 'exceptions';
    }
}

if (!$id) {
    $inactive += array('overview', 'content', 'assignments', 'messages', 'certification');
}

$tabs = array($toprow);

// print out tabs
print_tabs($tabs, $currenttab, $inactive, $activated);
