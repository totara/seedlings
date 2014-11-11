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
 * @author  Russell England <russell.england@catalyst-eu.net>
 * @package totara
 * @subpackage completionimport
 */

defined('MOODLE_INTERNAL') || die;

$ADMIN->add('courses',
    new admin_category('totara_completionimport',
      get_string('completionimport', 'totara_completionimport'))
);

$ADMIN->add('totara_completionimport',
        new admin_externalpage(
                'totara_completionimport_upload',
                get_string('completionimport', 'totara_completionimport'),
                new moodle_url('/totara/completionimport/upload.php'),
                array('totara/completionimport:import')));

$ADMIN->add('totara_completionimport',
        new admin_externalpage(
                'totara_completionimport_course',
                get_string('report_course', 'totara_completionimport'),
                new moodle_url('/totara/completionimport/viewreport.php', array('importname' => 'course', 'clearfilters' => 1)),
                array('totara/completionimport:import')));

$ADMIN->add('totara_completionimport',
        new admin_externalpage(
                'totara_completionimport_certification',
                get_string('report_certification', 'totara_completionimport'),
                new moodle_url('/totara/completionimport/viewreport.php', array('importname' => 'certification', 'clearfilters' => 1)),
                array('totara/completionimport:import'),
                totara_feature_disabled('certifications')
        ));

$ADMIN->add('totara_completionimport',
        new admin_externalpage(
                'totara_completionimport_reset',
                get_string('resetimport', 'totara_completionimport'),
                new moodle_url('/totara/completionimport/reset.php'),
                array('totara/completionimport:import')));