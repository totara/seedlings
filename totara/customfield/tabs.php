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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage customfield
 */

/*
 * Display tabs on report settings pages
 *
 * DO NOT REUSE THIS CODE. Any new code should create tabs in a renderer.php file. See appraisals for an example.
 *
 * Included in each settings page
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

// Assumes the currenttab variable has been set in the page.
if (!isset($currenttab)) {
    $currenttab = 'course';
}

$tabs = array();
$row = array();
$activated = array();
$inactive = array();

$row[] = new tabobject('course', new moodle_url('/totara/customfield/index.php', array('prefix' => 'course')),
        get_string('courses'));
if (totara_feature_visible('programs') || totara_feature_visible('certifications')) {
    $row[] = new tabobject('program', new moodle_url('/totara/customfield/index.php', array('prefix' => 'program')),
        get_string('programscerts', 'totara_program'));
}

$tabs[] = $row;
$activated[] = $currenttab;

// Print out tabs.
print_tabs($tabs, $currenttab, $inactive, $activated);
