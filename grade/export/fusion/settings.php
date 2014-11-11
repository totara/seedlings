<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Link to CSV course upload
 *
 * @package    tool
 * @subpackage uploadcourse
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @copyright  2011 Piers Harding
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/googleapi.php');

$a = new stdClass;
$a->docsurl = get_docs_url('Google_OAuth_2.0_setup');
$a->callbackurl = google_oauth::callback_url()->out(false);

//default display settings
$settings->add(new admin_setting_heading('gradeexport_fusion/displaysettings', get_string('oauthinfo', 'gradeexport_fusion', $a), ''));

$settings->add(new admin_setting_configtext('gradeexport_fusion/clientid',
                get_string('clientid', 'gradeexport_fusion'), get_string('clientiddesc', 'gradeexport_fusion'),
                '', PARAM_TEXT));
$settings->add(new admin_setting_configtext('gradeexport_fusion/secret',
                get_string('secret', 'gradeexport_fusion'), get_string('clientiddesc', 'gradeexport_fusion'),
                '', PARAM_TEXT));

