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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Display tabs on report settings pages
 *
 * Included in each settings page
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

// assumes the report id variable has been set in the page
if (!isset($currenttab)) {
    $currenttab = 'general';
}

$tabs = array();
$row = array();
$activated = array();
$inactive = array();

$row[] = new tabobject('general', $CFG->wwwroot . '/totara/reportbuilder/general.php?id=' . $id, get_string('general'));
$row[] = new tabobject('columns', $CFG->wwwroot . '/totara/reportbuilder/columns.php?id=' . $id, get_string('columns', 'totara_reportbuilder'));
$row[] = new tabobject('graph', $CFG->wwwroot . '/totara/reportbuilder/graph.php?reportid=' . $id, get_string('graph', 'totara_reportbuilder'));
$row[] = new tabobject('filters', $CFG->wwwroot . '/totara/reportbuilder/filters.php?id=' . $id, get_string('filters', 'totara_reportbuilder'));
$row[] = new tabobject('content', $CFG->wwwroot . '/totara/reportbuilder/content.php?id=' . $id, get_string('content', 'totara_reportbuilder'));
// hide access tab for embedded reports
if (!$report->embeddedurl) {
    $row[] = new tabobject('access', $CFG->wwwroot . '/totara/reportbuilder/access.php?id=' . $id, get_string('access', 'totara_reportbuilder'));
}
$row[] = new tabobject('performance', $CFG->wwwroot . '/totara/reportbuilder/performance.php?id=' . $id, get_string('performance', 'totara_reportbuilder'));

$tabs[] = $row;
$activated[] = $currenttab;

// print out tabs
print_tabs($tabs, $currenttab, $inactive, $activated);
