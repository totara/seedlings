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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package tool
 * @subpackage tool_totara_timezonefix
 */
/**
 * Strings for component 'tool_totara_timezonefix', language 'en', branch 'TOTARA_22'
 *
 * @package    tool
 * @subpackage timezonefix

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


$string['pluginname'] = 'Check User Timezones';
$string['infomessage'] = 'This tool checks the timezones specified in the profiles of your users. In order for timezones to work correctly a location-based timezone should be specified, e.g. America/New_York, Europe/London, Asia/Singapore. Some timezone abbreviations (e.g. CET, EST) and UTC offsets (e.g +/-4.5) will not calculate Daylight Savings changes correctly.<br><br>This tool will allow you to change unsuported timezones to an approved format.';
$string['badusertimezonemessage'] = 'Some users have timezones specified in their profiles which are no longer supported. Timezones should be set to a location-based string, e.g. America/New_York, Europe/London, Asia/Singapore. Use the Check User Timezones tool found in Site Administration -> Location to fix timezones for all users.';
$string['nobadusertimezones'] = 'All user profile timezones are correct';
$string['numbadusertimezones'] = 'Timezones need to be adjusted for {$a} users';
$string['badzone'] = 'Unsupported Timezone';
$string['numusers'] = 'Number of Users';
$string['replacewith'] = 'Change To';
$string['updatetimezones'] = 'Update timezones';

$string['updatetimezonesuccess'] = 'Timezone {$a->badzone} changed to {$a->replacewith} successfully';
$string['error:updatetimezone'] = 'An error occured when attempting to change timezone {$a->badzone} to {$a->replacewith}';
$string['error:unknownzones'] = 'There are unknown timezones set for {$a->numusers} users! The following timezones are set in user profiles but are not valid timezone identifiers:<br />{$a->badzonelist}';
