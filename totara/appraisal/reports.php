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
 * @subpackage totara_appraisal
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/appraisal/lib.php');

// Check if Appraisals are enabled.
appraisal::check_feature_enabled();

admin_externalpage_setup('reportappraisals');
$systemcontext = context_system::instance();
require_capability('totara/appraisal:manageappraisals', $systemcontext);

$output = $PAGE->get_renderer('totara_appraisal');

echo $output->header();

echo $output->heading(get_string('activeappraisals', 'totara_appraisal'));
$activeappraisals = appraisal::get_active_with_stats();
echo $output->report_active_table($activeappraisals);

echo $output->heading(get_string('inactiveappraisals', 'totara_appraisal'));
$inactiveappraisals = appraisal::get_inactive_with_stats();
echo $output->report_inactive_table($inactiveappraisals);

echo $output->footer();
