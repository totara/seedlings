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
 * Add reportbuilder administration menu settings
 */
require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
global $REPORT_BUILDER_EXPORT_OPTIONS;

$ADMIN->add('reports', new admin_category('totara_reportbuilder', get_string('reportbuilder','totara_reportbuilder')), 'comments');

// Main report builder settings.
$rb = new admin_settingpage('rbsettings',
                            new lang_string('globalsettings','totara_reportbuilder'),
                            array('totara/reportbuilder:managereports'));

foreach ($REPORT_BUILDER_EXPORT_OPTIONS as $option => $code) {
    $formatbyname[$code] = new lang_string('export' . $option, 'totara_reportbuilder');
    $defaultformats[$code] = 1;
}

$rb->add(new admin_setting_configmulticheckbox('reportbuilder/exportoptions', new lang_string('exportoptions', 'totara_reportbuilder'),
         new lang_string('reportbuilderexportoptions_help', 'totara_reportbuilder'), $defaultformats, $formatbyname));

$rb->add(new admin_setting_configcheckbox('reportbuilder/exporttofilesystem', new lang_string('exporttofilesystem', 'totara_reportbuilder'),
         new lang_string('reportbuilderexporttofilesystem_help', 'totara_reportbuilder'), false));

$rb->add(new admin_setting_configdirectory('reportbuilder/exporttofilesystempath', new lang_string('exportfilesystempath', 'totara_reportbuilder'),
         new lang_string('exportfilesystempath_help', 'totara_reportbuilder'), ''));

$rb->add(new admin_setting_configdaymonthpicker('reportbuilder/financialyear', new lang_string('financialyear', 'totara_reportbuilder'),
         new lang_string('reportbuilderfinancialyear_help', 'totara_reportbuilder'), array('d'=> 1, 'm'=>7)));

// Add all above settings to the report builder settings node.
$ADMIN->add('totara_reportbuilder', $rb);

// Add links to report builder reports.
$ADMIN->add('totara_reportbuilder', new admin_externalpage('rbmanagereports', new lang_string('managereports','totara_reportbuilder'),
            new moodle_url('/totara/reportbuilder/index.php'), array('totara/reportbuilder:managereports')));
