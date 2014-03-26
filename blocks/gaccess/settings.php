<?php
/**
* @copyright  Copyright (c) 2009 Moodlerooms Inc. (http://www.moodlerooms.com)
* Copyright (C) 2011 Catalyst IT Ltd (http://www.catalyst.net.nz)
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see http://opensource.org/licenses/gpl-3.0.html.
*
* @author     Chris Stones
* @author     Piers Harding
* @license    http://opensource.org/licenses/gpl-3.0.html     GNU Public License
*/

/**
 * GAccess Settings
 *
 * @author Chris Stones
 *         based off Mark's code
 * @version $Id$
 * @package block_gmail
 **/

defined('MOODLE_INTERNAL') or die();

require_once "$CFG->libdir/adminlib.php";

$configs   = array();

$configs[] = new admin_setting_configtext('domainname', get_string('domainnamestr', 'block_gaccess'), get_string('domainnameinfo', 'block_gaccess'), '', PARAM_RAW, 30);
$configs[] = new admin_setting_configcheckbox('newwinlink', get_string('newwinlink', 'block_gaccess'), get_string('newwinlink_desc', 'block_gaccess'), '1');
$configs[] = new admin_setting_configcheckbox('gmail', get_string('gmail', 'block_gaccess'), get_string('gmail_desc', 'block_gaccess'), '0');
$configs[] = new admin_setting_configcheckbox('docs', get_string('docs', 'block_gaccess'), get_string('docs_desc', 'block_gaccess'), '0');
$configs[] = new admin_setting_configcheckbox('calendar', get_string('calendar', 'block_gaccess'), get_string('calendar_desc', 'block_gaccess'), '0');

// Define the config plugin so it is saved to
// the config_plugin table then add to the settings page
foreach ($configs as $config) {
    $config->plugin = 'blocks/gaccess';
    $settings->add($config);
}
