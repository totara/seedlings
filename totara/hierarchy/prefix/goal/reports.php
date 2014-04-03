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
 * @subpackage totara_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/lib.php');
require_once($CFG->dirroot . '/totara/hierarchy/prefix/goal/renderer.php');

// Check if Goals are enabled.
goal::check_feature_enabled();

admin_externalpage_setup('goalreport');
$systemcontext = context_system::instance();

require_capability('totara/hierarchy:viewgoalreport', $systemcontext);

$output = $PAGE->get_renderer('hierarchy_goal');

echo $output->header();

echo $output->heading(get_string('goalreports', 'totara_hierarchy'));
$goal = new goal();
$goalframeworks = $goal->get_frameworks();
echo $output->report_frameworks($goalframeworks);

echo $output->footer();
