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
 * @author Paul Walker <paul.walker@catalyst-eu.net>
 * @author Brian Barnes <brian.barnes@totaralms.com>
 * @package totara
 * @subpackage theme
 */

/**
 * Settings for the kiwifruitresponsive theme
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    // Logo file setting.
    $name = 'theme_kiwifruitresponsive/logo';
    $title = new lang_string('logo', 'theme_kiwifruitresponsive');
    $description = new lang_string('logodesc', 'theme_kiwifruitresponsive');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'logo');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Logo alt text.
    $name = 'theme_kiwifruitresponsive/alttext';
    $title = new lang_string('alttext', 'theme_kiwifruitresponsive');
    $description = new lang_string('alttextdesc', 'theme_kiwifruitresponsive');
    $setting = new admin_setting_configtext($name, $title, $description, '');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Favicon file setting.
    $name = 'theme_kiwifruitresponsive/favicon';
    $title = new lang_string('favicon', 'theme_kiwifruitresponsive');
    $description = new lang_string('favicondesc', 'theme_kiwifruitresponsive');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'favicon', 0, array('accepted_types' => '.ico'));
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Custom CSS file.
    $name = 'theme_kiwifruitresponsive/customcss';
    $title = new lang_string('customcss', 'theme_kiwifruitresponsive');
    $description = new lang_string('customcssdesc', 'theme_kiwifruitresponsive');
    $default = '';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $settings->add($setting);
}
