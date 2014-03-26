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
 * Strings for component 'gradeexport_fusion', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   gradeexport_fusion
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['modulename'] = 'Fusion Table Export';
$string['pluginname'] = 'Fusion Table Export';
$string['fusion:publish'] = 'Publish Fusion Table grade export';
$string['fusion:view'] = 'Use Fusion table grade export';
$string['popup'] = 'Fusion Tables popup';
$string['noscript'] = 'Please enable JavaScript for this page to work';
$string['login'] = 'Login to Google Fusion tables first';
$string['tablename'] = 'Tablename';
$string['noconfig'] = 'Google API configuration not present for grade/export/fusion.  Please contact the Administrator.';
$string['oauthinfo'] = '<p>To use this plugin, you must register your site with Google, as described in the documentation <a href="{$a->docsurl}">Google OAuth 2.0 setup</a>.</p><p>As part of the registration process, you will need to enter the following URL as \'Authorized Redirect URIs\':</p><p>{$a->callbackurl}</p>Once registered, you will be provided with a client ID and secret which can be used to configure all Fusion Tables, Google Docs, Google Drive, and Picasa plugins.</p>';
$string['clientid'] = 'Client ID';
$string['clientiddesc'] = 'The Client ID specified in your configured Google API web service';
$string['secret'] = 'Secret';
$string['secretdesc'] = 'The Secret specified in your configured Google API web service';
$string['error:fusionexport'] = 'There was an error while exporting data to Google Fusion Tables: {$a}';
