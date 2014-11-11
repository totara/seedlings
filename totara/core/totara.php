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
 * @author Jonathan Newman <jonathan.newman@catalyst.net.nz>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

define('PUBLIC_KEY_PATH', $CFG->dirroot . '/totara_public.pem');
define('TOTARA_SHOWFEATURE', 1);
define('TOTARA_HIDEFEATURE', 2);
define('TOTARA_DISABLEFEATURE', 3);

/**
 * This function loads the program settings that are available for the user
 *
 * @param object $navinode The navigation_node to add the settings to
 * @param bool $forceopen If set to true the course node will be forced open
 * @return navigation_node|false
 */
function totara_load_program_settings($navinode, $context, $forceopen = false) {
    $program = new program($context->instanceid);
    $exceptions = $program->get_exception_count();
    $exceptioncount = $exceptions ? $exceptions : 0;

    $adminnode = $navinode->add(get_string('programadministration', 'totara_program'), null, navigation_node::TYPE_COURSE, null, 'progadmin');
    if ($forceopen) {
        $adminnode->force_open();
    }
    // Standard tabs.
    if (has_capability('totara/program:viewprogram', $context)) {
        $url = new moodle_url('/totara/program/edit.php', array('id' => $program->id, 'action' => 'view'));
        $adminnode->add(get_string('overview', 'totara_program'), $url, navigation_node::TYPE_SETTING, null,
                    'progoverview', new pix_icon('i/settings', get_string('overview', 'totara_program')));
    }
    if (has_capability('totara/program:configuredetails', $context)) {
        $url = new moodle_url('/totara/program/edit.php', array('id' => $program->id, 'action' => 'edit'));
        $adminnode->add(get_string('details', 'totara_program'), $url, navigation_node::TYPE_SETTING, null,
                    'progdetails', new pix_icon('i/settings', get_string('details', 'totara_program')));
    }
    if (has_capability('totara/program:configurecontent', $context)) {
        $url = new moodle_url('/totara/program/edit_content.php', array('id' => $program->id));
        $adminnode->add(get_string('content', 'totara_program'), $url, navigation_node::TYPE_SETTING, null,
                    'progcontent', new pix_icon('i/settings', get_string('content', 'totara_program')));
    }
    if (has_capability('totara/program:configureassignments', $context)) {
        $url = new moodle_url('/totara/program/edit_assignments.php', array('id' => $program->id));
        $adminnode->add(get_string('assignments', 'totara_program'), $url, navigation_node::TYPE_SETTING, null,
                    'progassignments', new pix_icon('i/settings', get_string('assignments', 'totara_program')));
    }
    if (has_capability('totara/program:configuremessages', $context)) {
        $url = new moodle_url('/totara/program/edit_messages.php', array('id' => $program->id));
        $adminnode->add(get_string('messages', 'totara_program'), $url, navigation_node::TYPE_SETTING, null,
                    'progmessages', new pix_icon('i/settings', get_string('messages', 'totara_program')));
    }
    if (($exceptioncount > 0) && has_capability('totara/program:handleexceptions', $context)) {
        $url = new moodle_url('/totara/program/exceptions.php', array('id' => $program->id, 'page' => 0));
        $adminnode->add(get_string('exceptions', 'totara_program', $exceptioncount), $url, navigation_node::TYPE_SETTING, null,
                    'progexceptions', new pix_icon('i/settings', get_string('exceptionsreport', 'totara_program')));
    }
    if ($program->certifid && has_capability('totara/certification:configurecertification', $context)) {
        $url = new moodle_url('/totara/certification/edit_certification.php', array('id' => $program->id));
        $adminnode->add(get_string('certification', 'totara_certification'), $url, navigation_node::TYPE_SETTING, null,
                    'certification', new pix_icon('i/settings', get_string('certification', 'totara_certification')));
    }
    // Roles and permissions.
    $usersnode = $adminnode->add(get_string('users'), null, navigation_node::TYPE_CONTAINER, null, 'users');
    // Override roles.
    if (has_capability('moodle/role:review', $context)) {
        $url = new moodle_url('/admin/roles/permissions.php', array('contextid' => $context->id));
    } else {
        $url = null;
    }
    $permissionsnode = $usersnode->add(get_string('permissions', 'role'), $url, navigation_node::TYPE_SETTING, null, 'override');
    // Add assign or override roles if allowed.
    if (is_siteadmin()) {
        if (has_capability('moodle/role:assign', $context)) {
            $url = new moodle_url('/admin/roles/assign.php', array('contextid' => $context->id));
            $permissionsnode->add(get_string('assignedroles', 'role'), $url, navigation_node::TYPE_SETTING, null,
                    'roles', new pix_icon('t/assignroles', get_string('assignedroles', 'role')));
        }
    }
    // Check role permissions.
    if (has_any_capability(array('moodle/role:assign', 'moodle/role:safeoverride', 'moodle/role:override', 'moodle/role:assign'), $context)) {
        $url = new moodle_url('/admin/roles/check.php', array('contextid' => $context->id));
        $permissionsnode->add(get_string('checkpermissions', 'role'), $url, navigation_node::TYPE_SETTING, null,
                    'permissions', new pix_icon('i/checkpermissions', get_string('checkpermissions', 'role')));
    }
    // Just in case nothing was actually added.
    $usersnode->trim_if_empty();
    $adminnode->trim_if_empty();
}

/**
 * Returns the major Totara version of this site (which may be different from Moodle in older versions)
 *
 * Totara version numbers consist of three numbers (four for emergency releases)separated by a dot,
 * for example 1.9.11 or 2.0.2. The first two numbers, like 1.9 or 2.0, represent so
 * called major version. This function extracts the major version from
 * the $TOTARA->version variable defined in the main version.php.
 *
 * @return string|false the major version like '2.3', false if could not be determined
 */
function totara_major_version() {
    global $CFG;

    $release = null;
    require($CFG->dirroot.'/version.php');
    if (empty($TOTARA)) {
        return false;
    }

    if (preg_match('/^[0-9]+\.[0-9]+/', $release, $matches)) {
        return $matches[0];
    } else {
        return false;
    }
}

/**
 * Setup version information for installs and upgrades
 *
 * Moodle and Totara version numbers consist of three numbers (four for emergency releases)separated by a dot,
 * for example 1.9.11 or 2.0.2. The first two numbers, like 1.9 or 2.0, represent so
 * called major version. This function extracts the Moodle and Totara version info for use in checks and messages
 * @param $version the moodle version number from the root version.php
 * @param $release the moodle release string from the root version.php
 * @return object containing moodle and totara version info
 */
function totara_version_info($version, $release) {
    global $CFG, $TOTARA;

    $a = new stdClass();
    $a->existingtotaraversion = false;
    $a->newtotaraversion = $TOTARA->version;
    $a->newversion = '';
    $a->oldversion = '';
    if (!empty($CFG->totara_release)) {
        $a->canupgrade = true;
        if (isset($CFG->totara_version)) {
            //on at least Totara 2.2, check it is 2.2.13 or greater
            $parts = explode(" ", $CFG->totara_release);
            $a->existingtotaraversion = trim($parts[0]);
            $a->canupgrade = version_compare($a->existingtotaraversion, '2.2.13', '>=');
        } else {
            //$CFG->totara_version was not set in 1.1 or early 2.2 releases
            $a->canupgrade = false;
        }
        // if upgrading from totara, require v2.2.13 or greater
        if (!$a->canupgrade) {
            $a->totaraupgradeerror = 'error:cannotupgradefromtotara';
            return $a;
        }
    } else if (empty($CFG->local_postinst_hasrun) &&
            !empty($CFG->version) && $CFG->version < 2011120507) {
        //upgrading from moodle, require at least v2.2.7
        $a->totaraupgradeerror = 'error:cannotupgradefrommoodle';
        return $a;
    } else if ($version < $CFG->version) {
        // The original Moodle install is newer than Totara.
        $a->oldversion = $CFG->version;
        $a->newversion = $version;
        $a->totaraupgradeerror = 'error:cannotupgradefromnewermoodle';
        return $a;
    }

    // If a Moodle core upgrade:
    if ($version > $CFG->version) {
        $moodleprefix = get_string('moodlecore', 'totara_core').':';
        $a->oldversion .= "{$moodleprefix}<br />{$CFG->release}";
        $a->newversion .= "{$moodleprefix}<br />{$release}";
    }

    // If a Totara core upgrade
    if (!isset($CFG->totara_build) || (isset($CFG->totara_build) && version_compare($a->newtotaraversion, $a->existingtotaraversion, '>'))) {
        $totaraprefix = get_string('totaracore','totara_core').':';
        $moodlespacing = ($version > $CFG->version) ? '<br /><br />' : '';
        // If a Moodle and a Totara upgrade, tidy up the markup
        if (!isset($CFG->totara_build)) {
            //upgrading from versions prior to M2.2.3 or T2.2.13 is no longer possible
            //so if totara_build is not set this must be an upgrade from vanilla Moodle
            $a->newversion .= "{$moodlespacing}{$totaraprefix}<br />{$TOTARA->release}";
        } else {
            $a->oldversion .= "{$moodlespacing}{$totaraprefix}<br />{$CFG->totara_release}";
            $a->newversion .= "{$moodlespacing}{$totaraprefix}<br />{$TOTARA->release}";
        }
    }
    return $a;
}

/**
 * Import the latest timezone information - code taken from admin/tool/timezoneimport
 * @return bool success or failure
 */
function totara_import_timezonelist() {
    global $CFG, $OUTPUT;
    require_once($CFG->libdir.'/adminlib.php');
    require_once($CFG->libdir.'/datalib.php');
    require_once($CFG->libdir.'/filelib.php');
    require_once($CFG->libdir.'/olson.php');

    // Try to find a source of timezones to import from.
    $importdone = false;

    // First, look for an Olson file locally.
    $source = $CFG->tempdir.'/olson.txt';
    if (!$importdone and is_readable($source)) {
        if ($timezones = olson_to_timezones($source)) {
            update_timezone_records($timezones);
            $importdone = $source;
        }
    }

    // Next, look for a CSV file locally.
    $source = $CFG->tempdir.'/timezone.txt';
    if (!$importdone and is_readable($source)) {
        if ($timezones = get_records_csv($source, 'timezone')) {
            update_timezone_records($timezones);
            $importdone = $source;
        }
    }

    // Otherwise, let's try moodle.org's copy.
    $source = 'http://download.moodle.org/timezone/';
    if (!$importdone && ($content=download_file_content($source))) {
        if ($file = fopen($CFG->tempdir.'/timezone.txt', 'w')) {            // Make local copy
            fwrite($file, $content);
            fclose($file);
            if ($timezones = get_records_csv($CFG->tempdir.'/timezone.txt', 'timezone')) {  // Parse it
                update_timezone_records($timezones);
                $importdone = $source;
            }
            unlink($CFG->tempdir.'/timezone.txt');
        }
    }

    // Final resort, use the copy included in Moodle.
    $source = $CFG->dirroot.'/lib/timezone.txt';
    if (!$importdone and is_readable($source)) {  // Distribution file
        if ($timezones = get_records_csv($source, 'timezone')) {
            update_timezone_records($timezones);
            $importdone = $source;
        }
    }

    if ($importdone) {
        echo $OUTPUT->notification(get_string('importtimezonessuccess', 'totara_core', $importdone), 'notifysuccess');
    } else {
        echo $OUTPUT->notification(get_string('error:importtimezonesfailed', 'totara_core'), 'notifyproblem');
    }

    return $importdone;
}
/**
 * gets a clean timezone array compatible with PHP DateTime, DateTimeZone etc functions
 * @param bool $assoc return a simple numerical index array or an associative array
 * @return array a clean timezone list that can be used safely
 */
function totara_get_clean_timezone_list($assoc=false) {
    $zones = DateTimeZone::listIdentifiers();
    if ($assoc == false) {
        return $zones;
    } else {
        return array_combine($zones, $zones);
    }
}

/**
 * gets a list of bad timezones with the most likely proper named location zone
 * @return array a bad timezone list key=>bad value=>replacement
 */
function totara_get_bad_timezone_list() {
    $zones = array();
    //unsupported but common abbreviations
    $zones['EST'] = 'America/New_York';
    $zones['EDT'] = 'America/New_York';
    $zones['EST5EDT'] = 'America/New_York';
    $zones['CST'] = 'America/Chicago';
    $zones['CDT'] = 'America/Chicago';
    $zones['CST6CDT'] = 'America/Chicago';
    $zones['MST'] = 'America/Denver';
    $zones['MDT'] = 'America/Denver';
    $zones['MST7MDT'] = 'America/Denver';
    $zones['PST'] = 'America/Los_Angeles';
    $zones['PDT'] = 'America/Los_Angeles';
    $zones['PST8PDT'] = 'America/Los_Angeles';
    $zones['HST'] = 'Pacific/Honolulu';
    $zones['WET'] = 'Europe/London';
    $zones['GMT'] = 'Europe/London';
    $zones['EET'] = 'Europe/Kiev';
    $zones['FET'] = 'Europe/Minsk';
    $zones['CET'] = 'Europe/Amsterdam';
    //now the stupid Moodle offset zones. If an offset does not really exist then set to nearest
    $zones['-13.0'] = 'Pacific/Apia';
    $zones['-12.5'] = 'Pacific/Apia';
    $zones['-12.0'] = 'Pacific/Kwajalein';
    $zones['-11.5'] = 'Pacific/Niue';
    $zones['-11.0'] = 'Pacific/Midway';
    $zones['-10.5'] = 'Pacific/Rarotonga';
    $zones['-10.0'] = 'Pacific/Honolulu';
    $zones['-9.5'] = 'Pacific/Marquesas';
    $zones['-9.0'] = 'America/Anchorage';
    $zones['-8.5'] = 'America/Anchorage';
    $zones['-8.0'] = 'America/Los_Angeles';
    $zones['-7.5'] = 'America/Los_Angeles';
    $zones['-7.0'] = 'America/Denver';
    $zones['-6.5'] = 'America/Denver';
    $zones['-6.0'] = 'America/Chicago';
    $zones['-5.5'] = 'America/Chicago';
    $zones['-5.0'] = 'America/New_York';
    $zones['-4.5'] = 'America/Caracas';
    $zones['-4.0'] = 'America/Santiago';
    $zones['-3.5'] = 'America/St_Johns';
    $zones['-3.0'] = 'America/Sao_Paulo';
    $zones['-2.5'] = 'America/Sao_Paulo';
    $zones['-2.0'] = 'Atlantic/South_Georgia';
    $zones['-1.5'] = 'Atlantic/Cape_Verde';
    $zones['-1.0'] = 'Atlantic/Cape_Verde';
    $zones['-0.5'] = 'Europe/London';
    $zones['0.0'] = 'Europe/London';
    $zones['0.5'] = 'Europe/London';
    $zones['1.0'] = 'Europe/Amsterdam';
    $zones['1.5'] = 'Europe/Amsterdam';
    $zones['2.0'] = 'Europe/Helsinki';
    $zones['2.5'] = 'Europe/Minsk';
    $zones['3.0'] = 'Asia/Riyadh';
    $zones['3.5'] = 'Asia/Tehran';
    $zones['4.0'] = 'Asia/Dubai';
    $zones['4.5'] = 'Asia/Kabul';
    $zones['5.0'] = 'Asia/Karachi';
    $zones['5.5'] = 'Asia/Kolkata';
    $zones['6.0'] = 'Asia/Dhaka';
    $zones['6.5'] = 'Asia/Rangoon';
    $zones['7.0'] = 'Asia/Bangkok';
    $zones['7.5'] = 'Asia/Singapore';
    $zones['8.0'] = 'Australia/Perth';
    $zones['8.5'] = 'Australia/Perth';
    $zones['9.0'] = 'Asia/Tokyo';
    $zones['9.5'] = 'Australia/Adelaide';
    $zones['10.0'] = 'Australia/Sydney';
    $zones['10.5'] = 'Australia/Lord_Howe';
    $zones['11.0'] = 'Pacific/Guadalcanal';
    $zones['11.5'] = 'Pacific/Norfolk';
    $zones['12.0'] = 'Pacific/Auckland';
    $zones['12.5'] = 'Pacific/Auckland';
    $zones['13.0'] = 'Pacific/Apia';
    return $zones;
}
/**
 * gets a clean timezone attempting to compensate for some Moodle 'special' timezones
 * where the returned zone is compatible with PHP DateTime, DateTimeZone etc functions
 * @param string/float $tz either a location identifier string or, in some Moodle special cases, a number
 * @return string a clean timezone that can be used safely
 */
function totara_get_clean_timezone($tz=null) {
    global $CFG, $DB;

    $cleanzones = DateTimeZone::listIdentifiers();
    if (empty($tz)) {
        $tz = get_user_timezone();
    }

    //if already a good zone, return
    if (in_array($tz, $cleanzones, true)) {
        return $tz;
    }
    //for when all else fails
    $default = 'Europe/London';
    //try to handle UTC offsets, and numbers including '99' (server local time)
    //note: some old versions of moodle had GMT offsets stored as floats
    if (is_numeric($tz)) {
        if (intval($tz) == 99) {
            //check various config settings to try and resolve to something useful
            if (isset($CFG->forcetimezone) && $CFG->forcetimezone != 99) {
                $tz = $CFG->forcetimezone;
            } else if (isset($CFG->timezone) && $CFG->timezone != 99) {
                $tz = $CFG->timezone;
            }
        }
        if (intval($tz) == 99) {
            //no useful CFG settings, try a system call
            $tz = date_default_timezone_get();
        }
        //do we have something useful yet?
        if (in_array($tz, $cleanzones, true)) {
            return $tz;
        }
        //check the bad timezone replacement list
        if (is_float($tz)) {
            $tz = number_format($tz, 1);
        }
        $badzones = totara_get_bad_timezone_list();
        //does this exist in our replacement list?
        if (in_array($tz, array_keys($badzones))) {
            return $badzones[$tz];
        }
    }
    //everything has failed, set to London
    return $default;
}

/**
 * checks the md5 of the zip file, grabbed from download.moodle.org,
 * against the md5 of the local language file from last update
 * @param string $lang
 * @param string $md5check
 * @return bool
 */
function local_is_installed_lang($lang, $md5check) {
    global $CFG;
    $md5file = $CFG->dataroot.'/lang/'.$lang.'/'.$lang.'.md5';
    if (file_exists($md5file)){
        return (file_get_contents($md5file) == $md5check);
    }
    return false;
}

/**
 * Runs on every upgrade to get the latest language packs from Totara language server
 *
 * Code mostly refactored from admin/tool/langimport/index.php
 *
 * @return  void
 */
function totara_upgrade_installed_languages() {
    global $CFG, $OUTPUT;
    require_once($CFG->libdir.'/adminlib.php');
    require_once($CFG->libdir.'/filelib.php');
    require_once($CFG->libdir.'/componentlib.class.php');
    set_time_limit(0);
    $notice_ok = array();
    $notice_error = array();
    $installer = new lang_installer();

    if (!$availablelangs = $installer->get_remote_list_of_languages()) {
        echo $OUTPUT->notification(get_string('cannotdownloadtotaralanguageupdatelist', 'totara_core'), 'notifyproblem');
        return;
    }
    $md5array = array();    // (string)langcode => (string)md5
    foreach ($availablelangs as $alang) {
        $md5array[$alang[0]] = $alang[1];
    }

    // filter out unofficial packs
    $currentlangs = array_keys(get_string_manager()->get_list_of_translations(true));
    $updateablelangs = array();
    foreach ($currentlangs as $clang) {
        if (!array_key_exists($clang, $md5array)) {
            $notice_ok[] = get_string('langpackupdateskipped', 'tool_langimport', $clang);
            continue;
        }
        $dest1 = $CFG->dataroot.'/lang/'.$clang;
        $dest2 = $CFG->dirroot.'/lang/'.$clang;

        if (file_exists($dest1.'/langconfig.php') || file_exists($dest2.'/langconfig.php')){
            $updateablelangs[] = $clang;
        }
    }

    // then filter out packs that have the same md5 key
    $neededlangs = array();   // all the packs that needs updating
    foreach ($updateablelangs as $ulang) {
        if (!local_is_installed_lang($ulang, $md5array[$ulang])) {
            $neededlangs[] = $ulang;
        }
    }

    make_temp_directory('');
    make_upload_directory('lang');

    // install all needed language packs
    $installer->set_queue($neededlangs);
    $results = $installer->run();
    $updated = false;    // any packs updated?
    foreach ($results as $langcode => $langstatus) {
        switch ($langstatus) {
        case lang_installer::RESULT_DOWNLOADERROR:
            $a       = new stdClass();
            $a->url  = $installer->lang_pack_url($langcode);
            $a->dest = $CFG->dataroot.'/lang';
            echo $OUTPUT->notification(get_string('remotedownloaderror', 'error', $a), 'notifyproblem');
            break;
        case lang_installer::RESULT_INSTALLED:
            $updated = true;
            $notice_ok[] = get_string('langpackinstalled', 'tool_langimport', $langcode);
            break;
        case lang_installer::RESULT_UPTODATE:
            $notice_ok[] = get_string('langpackuptodate', 'tool_langimport', $langcode);
            break;
        }
    }

    if ($updated) {
        $notice_ok[] = get_string('langupdatecomplete', 'tool_langimport');
    } else {
        $notice_ok[] = get_string('nolangupdateneeded', 'tool_langimport');
    }

    unset($installer);
    get_string_manager()->reset_caches();
    //display notifications
    $delimiter = (CLI_SCRIPT) ? "\n" : html_writer::empty_tag('br');
    if (!empty($notice_ok)) {
        $info = implode($delimiter, $notice_ok);
        echo $OUTPUT->notification($info, 'notifysuccess');
    }

    if (!empty($notice_error)) {
        $info = implode($delimiter, $notice_error);
        echo $OUTPUT->notification($info, 'notifyproblem');
    }
}

/**
 * Save a notification message for displaying on the subsequent page view
 *
 * Optionally supply a url for redirecting to before displaying the message
 * and/or an options array.
 *
 * Currently the options array only supports a 'class' entry for passing as
 * the second parameter to notification()
 *
 * @param   string  $message    Message to display
 * @param   string  $redirect   Url to redirect to (optional)
 * @param   array   $options    Options array (optional)
 * @return  void
 */
function totara_set_notification($message, $redirect = null, $options = array()) {

    // Check options is an array
    if (!is_array($options)) {
        print_error('error:notificationsparamtypewrong', 'totara_core');
    }

    // Add message to options array
    $options['message'] = $message;

    // Add to notifications queue
    totara_queue_append('notifications', $options);

    // Redirect if requested
    if ($redirect !== null) {
        // Cancel redirect for AJAX scripts.
        if (is_ajax_request($_SERVER)) {
            ajax_result(true, totara_queue_shift('notifications'));
        } else {
            redirect($redirect);
        }
        exit();
    }
}

/**
 * Return an array containing any notifications in $SESSION
 *
 * Should be called in the theme's header
 *
 * @return  array
 */
function totara_get_notifications() {
    return totara_queue_shift('notifications', true);
}


/**
 * Add an item to a totara session queue
 *
 * @param   string  $key    Queue key
 * @param   mixed   $data   Data to add to queue
 * @return  void
 */
function totara_queue_append($key, $data) {
    global $SESSION;

    if (!isset($SESSION->totara_queue)) {
        $SESSION->totara_queue = array();
    }

    if (!isset($SESSION->totara_queue[$key])) {
        $SESSION->totara_queue[$key] = array();
    }

    $SESSION->totara_queue[$key][] = $data;
}


/**
 * Return part or all of a totara session queue
 *
 * @param   string  $key    Queue key
 * @param   boolean $all    Flag to return entire session queue (optional)
 * @return  mixed
 */
function totara_queue_shift($key, $all = false) {
    global $SESSION;

    // Value to return if no items in queue
    $return = $all ? array() : null;

    // Check if an items in queue
    if (empty($SESSION->totara_queue) || empty($SESSION->totara_queue[$key])) {
        return $return;
    }

    // If returning all, grab all and reset queue
    if ($all) {
        $return = $SESSION->totara_queue[$key];
        $SESSION->totara_queue[$key] = array();
        return $return;
    }

    // Otherwise pop oldest item from queue
    return array_shift($SESSION->totara_queue[$key]);
}



/**
 *  Calls module renderer to return markup for displaying a progress bar for a user's course progress
 *
 * Optionally with a link to the user's profile if they have the correct permissions
 *
 * @access  public
 * @param   $userid     int
 * @param   $courseid   int
 * @param   $status     int     COMPLETION_STATUS_ constant
 * @return  string
 */
function totara_display_course_progress_icon($userid, $courseid, $status) {
    global $PAGE, $COMPLETION_STATUS;

    $renderer = $PAGE->get_renderer('totara_core');
    $content = $renderer->display_course_progress_icon($userid, $courseid, $status);
    return $content;
}

/**
 *  Adds the current icon and icon select dropdown to a moodle form
 *  replaces all the old totara/icon classes
 *
 * @access  public
 * @param   object $mform Reference to moodle form object.
 * @param   string $action Form action - add, edit or view.
 * @param   string $type Program, course or message icons.
 * @param   string $currenticon Value currently stored in db.
 * @param   int    $nojs 1 if Javascript is disabled.
 * @param   bool   $fieldset If true, include a 'header' around the icon picker.
 * @return  void
*/
function totara_add_icon_picker(&$mform, $action, $type, $currenticon='default', $nojs=0, $fieldset=true) {
    global $CFG;
    //get all icons of this type from core
    $replace = array('.png' => '', '_' => ' ', '-' => ' ');
    $iconhtml = totara_icon_picker_preview($type, $currenticon);

    if ($fieldset) {
        $mform->addElement('header', 'iconheader', get_string($type.'icon', 'totara_core'));
    }
    if ($nojs == 1) {
        $mform->addElement('static', 'currenticon', get_string('currenticon', 'totara_core'), $iconhtml);
        if ($action=='add' || $action=='edit') {
            $path = $CFG->dirroot . '/totara/core/pix/' . $type . 'icons';
            foreach (scandir($path) as $icon) {
                if ($icon == '.' || $icon == '..') { continue;}
                $iconfile = str_replace('.png', '', $icon);
                $iconname = strtr($icon, $replace);
                $icons[$iconfile] = ucwords($iconname);
            }
            $mform->addElement('select', 'icon', get_string('icon', 'totara_core'), $icons);
            $mform->setDefault('icon', $currenticon);
            $mform->setType('icon', PARAM_TEXT);
        }
    } else {
        $buttonhtml = '';
        if ($action=='add' || $action=='edit') {
            $buttonhtml = html_writer::empty_tag('input', array('type' => 'button', 'value' => get_string('chooseicon', 'totara_program'), 'id' => 'show-icon-dialog'));
            $mform->addElement('hidden', 'icon');
            $mform->setType('icon', PARAM_TEXT);
        }
        $mform->addElement('static', 'currenticon', get_string('currenticon', 'totara_core'), $iconhtml . $buttonhtml);
    }
    if ($fieldset) {
        $mform->setExpanded('iconheader');
    }
}

/**
 *  Adds the current icon and icon select dropdown to a moodle form
 *  replaces all the old totara/icon classes
 *
 * @access  public
 * @param   object $mform Reference to moodle form object.
 * @param   string $action Form action - add, edit or view.
 * @param   string $type Program, course or message icons.
 * @param   string $currenticon Value currently stored in db.
 * @param   int    $nojs 1 if Javascript is disabled.
 * @param   mixed  $ind index to add to icon title
 * @return  array of created elements
 */
function totara_create_icon_picker(&$mform, $action, $type, $currenticon = '', $nojs = 0, $ind = '') {
    global $CFG;
    $return = array();
    if ($currenticon == '') {
        $currenticon = 'default';
    }
    // Get all icons of this type from core.
    $replace = array('.png' => '', '_' => ' ', '-' => ' ');
    $iconhtml = totara_icon_picker_preview($type, $currenticon, $ind);

    if ($nojs == 1) {
        $return['currenticon'.$ind] = $mform->createElement('static', 'currenticon',
                get_string('currenticon', 'totara_core'), $iconhtml);
        if ($action == 'add' || $action == 'edit') {
            $path = $CFG->dirroot . '/totara/core/pix/' . $type . 'icons';
            foreach (scandir($path) as $icon) {
                if ($icon == '.' || $icon == '..') {
                    continue;
                }
                $iconfile = str_replace('.png', '', $icon);
                $iconname = strtr($icon, $replace);
                $icons[$iconfile] = ucwords($iconname);
            }
            $return['icon'.$ind] = $mform->createElement('select', 'icon',
                    get_string('icon', 'totara_core'), $icons);
            $mform->setDefault('icon', $currenticon);
        }
    } else {
        $linkhtml = '';
        if ($action == 'add' || $action == 'edit') {
            $linkhtml = html_writer::tag('a', get_string('chooseicon', 'totara_program'),
                    array('href' => '#', 'data-ind'=> $ind, 'id' => 'show-icon-dialog' . $ind,
                          'class' => 'show-icon-dialog'));
            $return['icon'.$ind] = $mform->createElement('hidden', 'icon', '',
                    array('id'=>'icon' . $ind));
        }
        $return['currenticon' . $ind] = $mform->createElement('static', 'currenticon', '',
                get_string('icon', 'totara_program') . $iconhtml . $linkhtml);
    }
    return $return;
}

/**
 * Render preview of icon
 *
 * @param string $type type of icon (course or program)
 * @param string $currenticon current icon
 * @param string $ind index of icon on page (when several icons previewed)
 * @param string $alt alternative text for icon
 * @return string HTML
 */
function totara_icon_picker_preview($type, $currenticon, $ind = '', $alt = '') {
    list($src, $alt) = totara_icon_url_and_alt($type, $currenticon, $alt);

    $iconhtml = html_writer::empty_tag('img', array('src' => $src, 'id' => 'icon_preview' . $ind,
            'class' => "course_icon", 'alt' => $alt, 'title' => $alt));

    return $iconhtml;
}

/**
 * Get the url and alternate text of icon.
 *
 * @param string $type type of icon (course or program)
 * @param string $icon icon key (name for built-in icon or hash for user image)
 * @param string $alt alternative text for icon (overrides calculated alt text)
 * @return string HTML
 */
function totara_icon_url_and_alt($type, $icon, $alt = '') {
    global $OUTPUT, $DB, $PAGE;

    $component = 'totara_core';
    $src = '';

    // See if icon is a custom icon.
    if ($customicon = $DB->get_record('files', array('pathnamehash' => $icon))) {
        $fs = get_file_storage();
        $context = context_system::instance();
        if ($file = $fs->get_file($context->id, $component, $type, $customicon->itemid, '/', $customicon->filename)) {
            $icon = $customicon->filename;
            $src = moodle_url::make_pluginfile_url($file->get_contextid(), $component,
                $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $customicon->filename, true);
        }
    }

    if (empty($src)) {
        $iconpath = $type . 'icons/';
        $imagelocation = $PAGE->theme->resolve_image_location($iconpath. $icon, $component);
        if (empty($icon) || empty($imagelocation)) {
            $icon = 'default';
        }
        $src = $OUTPUT->pix_url('/' . $iconpath . $icon, $component);
    }

    $replace = array('.png' => '', '_' => ' ', '-' => ' ');
    $alt = ($alt != '') ? $alt : ucwords(strtr($icon, $replace));

    return array($src, $alt);
}

/**
* print out the Totara My Team nav section
*/
function totara_print_my_team_nav() {
    global $CFG, $USER, $PAGE;

    $managerroleid = $CFG->managerroleid;

    // return users with this user as manager
    $staff = totara_get_staff();
    $teammembers = ($staff) ? count($staff) : 0;

    //call renderer
    $renderer = $PAGE->get_renderer('totara_core');
    $content = $renderer->print_my_team_nav($teammembers);
    return $content;
}

/**
* print out the table of visible reports
*/
function totara_print_report_manager() {
    global $CFG, $USER, $PAGE, $reportbuilder_permittedreports;
    require_once($CFG->dirroot.'/totara/reportbuilder/lib.php');

    if (!isset($reportbuilder_permittedreports) || !is_array($reportbuilder_permittedreports)) {
        $reportbuilder_permittedreports = reportbuilder::get_permitted_reports();
    }

    $context = context_system::instance();
    $canedit = has_capability('totara/reportbuilder:managereports',$context);

    if (count($reportbuilder_permittedreports) > 0) {
        $renderer = $PAGE->get_renderer('totara_core');
        $returnstr = $renderer->print_report_manager($reportbuilder_permittedreports, $canedit);
    } else {
        $returnstr = get_string('nouserreports', 'totara_reportbuilder');
    }
    return $returnstr;
}

/**
* Returns markup for displaying saved scheduled reports
*
* Optionally without the options column and add/delete form
* Optionally with an additional sql WHERE clause
* @access  public
* @param   $showoptions   bool
* @param   $showaddform   bool
* @param   $sqlclause     array in the form array($where, $params)

*/
function totara_print_scheduled_reports($showoptions=true, $showaddform=true, $sqlclause=array()) {
    global $CFG, $DB, $USER, $PAGE, $REPORT_BUILDER_EXPORT_OPTIONS, $REPORT_BUILDER_EXPORT_FILESYSTEM_OPTIONS;

    require_once($CFG->dirroot . '/totara/reportbuilder/lib.php');
    require_once($CFG->dirroot . '/totara/core/lib/scheduler.php');
    require_once($CFG->dirroot . '/calendar/lib.php');
    require_once($CFG->dirroot . '/totara/reportbuilder/scheduled_forms.php');


    $sql = "SELECT rbs.*, rb.fullname
            FROM {report_builder_schedule} rbs
            JOIN {report_builder} rb
            ON rbs.reportid=rb.id
            WHERE rbs.userid=?";

    $parameters = array($USER->id);

    if (!empty($sqlclause)) {
        list($conditions, $params) = $sqlclause;
        $parameters = array_merge($parameters, $params);
        $sql .= " AND " . $conditions;
    }
    //note from M2.0 these functions return an empty array, not false
    $scheduledreports = $DB->get_records_sql($sql, $parameters);
    //pre-process before sending to renderer
    foreach ($scheduledreports as $sched) {
        //data column
        if ($sched->savedsearchid != 0){
            $sched->data = $DB->get_field('report_builder_saved', 'name', array('id' => $sched->savedsearchid));
        }
        else {
            $sched->data = get_string('alldata', 'totara_reportbuilder');
        }
        //format column
        $key = array_search($sched->format, $REPORT_BUILDER_EXPORT_OPTIONS);
        $sched->format = get_string($key . 'format','totara_reportbuilder');
        // Export column.
        $key = array_search($sched->exporttofilesystem, $REPORT_BUILDER_EXPORT_FILESYSTEM_OPTIONS);
        $sched->exporttofilesystem = get_string($key, 'totara_reportbuilder');
        //schedule column
        if (isset($sched->frequency) && isset($sched->schedule)){
            $schedule = new scheduler($sched);
            $formatted = $schedule->get_formatted();
        } else {
            $formatted = get_string('schedulenotset', 'totara_reportbuilder');
        }
        $sched->schedule = $formatted;
    }

    if (count($scheduledreports) > 0) {
        $renderer = $PAGE->get_renderer('totara_core');
        echo $renderer->print_scheduled_reports($scheduledreports, $showoptions);
    } else {
        echo get_string('noscheduledreports', 'totara_reportbuilder') . html_writer::empty_tag('br') . html_writer::empty_tag('br');
    }

    if ($showaddform) {
        $mform = new scheduled_reports_add_form($CFG->wwwroot . '/totara/reportbuilder/scheduled.php', array());
        $mform->display();
    }
}

function totara_print_my_courses() {
    global $USER, $PAGE, $COMPLETION_STATUS;
    $content = '';
    $courses = completion_info::get_all_courses($USER->id, 10);
    $displaycourses = array();
    if ($courses) {
        foreach ($courses as $course) {
            $displaycourse = new stdClass();
            $displaycourse->course = $course->course;
            $displaycourse->name = format_string($course->name);
            $enrolled = $course->timeenrolled;
            $completed = $course->timecompleted;
            $starteddate = '';
            if ($course->timestarted != 0) {
                $starteddate = userdate($course->timestarted, get_string('strfdateshortmonth', 'langconfig'));
            }
            $displaycourse->starteddate = $starteddate;
            $displaycourse->enroldate = isset($enrolled) && $enrolled != 0 ? userdate($enrolled, get_string('strfdateshortmonth', 'langconfig')) : null;
            $displaycourse->completeddate = isset($completed) && $completed != 0 ? userdate($completed, get_string('strfdateshortmonth', 'langconfig')) : null;
            $displaycourse->status = $course->status ? $course->status : COMPLETION_STATUS_NOTYETSTARTED;
            $displaycourses[] = $displaycourse;
        }
    }
    $renderer = $PAGE->get_renderer('totara_core');
    echo $renderer->print_my_courses($displaycourses, $USER->id);
}


/**
 * Check if a user is a manager of another user
 *
 * @param int $userid       ID of user
 * @param int $managerid    ID of a potential manager to check (optional)
 * @param int $postype      Type of the position to check (POSITION_TYPE_* constant). Defaults to all positions (optional)
 * @return boolean true if user $userid is managed by user $managerid
 *
 * If managerid is not set, uses the current user
**/
function totara_is_manager($userid, $managerid=null, $postype=null) {
    global $DB, $USER;

    $userid = (int) $userid;
    $now = time();

    if (!isset($managerid)) {
        // Use logged in user as default
        $managerid = $USER->id;
    }

    if ($DB->record_exists_select('temporary_manager', 'userid = ? AND tempmanagerid = ? AND expirytime > ?',
            array($userid, $managerid, $now))) {

        // This is a temporary manager of the user.
        return true;
    }

    $params = array($userid, $managerid);
    if ($postype) {
        $postypewhere = "AND pa.type = ?";
        $params[] = $postype;
    } else {
        $postypewhere = '';
    }

    return $DB->record_exists_select('pos_assignment', "userid = ? AND managerid = ?" . $postypewhere, $params);
}

/**
 * Returns the staff of the specified user
 *
 * @param int $userid ID of a user to get the staff of
 * @param mixed $postype Type of the position to check (POSITION_TYPE_* constant). Defaults to primary position (optional)
 * @return array Array of userids of staff who are managed by user $userid , or false if none
 *
 * If $userid is not set, returns staff of current user
**/
function totara_get_staff($userid=null, $postype=null) {
    global $CFG, $DB, $USER;

    require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');

    $postype = ($postype === null) ? POSITION_TYPE_PRIMARY : (int) $postype;
    $now = time();

    $userid = !empty($userid) ? (int) $userid : $USER->id;
    // this works because:
    // - old pos_assignment records are deleted when a user is deleted by {@link delete_user()}
    //   so no need to check if the record is for a real user
    // - there is a unique key on (type, userid) on pos_assignment so no need to use
    //   DISTINCT on the userid
    $staff = $DB->get_fieldset_select('pos_assignment', 'userid', "type = ? AND managerid = ?", array($postype, $userid));

    // Get temporary staff.
    if (!empty($CFG->enabletempmanagers) && $postype == POSITION_TYPE_PRIMARY) {
        $tempstaff = $DB->get_fieldset_select('temporary_manager', 'userid', 'tempmanagerid = ? AND expirytime > ?',
            array($userid, $now));

        $staff = array_unique(array_merge($staff, $tempstaff));
    }

    return (empty($staff)) ? false : $staff;
}

/**
 * Find out a user's manager.
 *
 * @param int $userid Id of the user whose manager we want
 * @param int $postype Type of the position we want the manager for (POSITION_TYPE_* constant). Defaults to primary position(optional)
 * @param boolean $skiptemp Skip check and return of temporary manager
 * @param boolean $skipreal Skip check and return of real manager
 * @return mixed False if no manager. Manager user object from mdl_user if the user has a manager.
 */
function totara_get_manager($userid, $postype=null, $skiptemp=false, $skipreal=false) {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');

    $postype = ($postype === null) ? POSITION_TYPE_PRIMARY : (int) $postype;
    $userid = (int) $userid;
    $now = time();

    if (!empty($CFG->enabletempmanagers) && $postype == POSITION_TYPE_PRIMARY && !$skiptemp) {
        // Temporary manager.
        $sql = "SELECT u.*, tm.expirytime
                  FROM {temporary_manager} tm
            INNER JOIN {user} u ON tm.tempmanagerid = u.id
                 WHERE tm.userid = ? AND tm.expirytime > ?";
        if ($tempmanager = $DB->get_record_sql($sql, array($userid, $now))) {
            return $tempmanager;
        }
    }

    if (!$skipreal) {
        $sql = "
            SELECT manager.*
              FROM {pos_assignment} pa
        INNER JOIN {user} manager
                ON pa.managerid = manager.id
             WHERE pa.userid = ?
               AND pa.type = ?";

        // Return a manager if they have one otherwise false.
        return $DB->get_record_sql($sql, array($userid, $postype));
    }

    return false;
}

/**
 * Find the manager of the user's 'most primary' position.
 *
 * @param int $userid Id of the user whose manager we want
 * @return mixed False if no manager. Manager user object from mdl_user if the user has a manager.
 */
function totara_get_most_primary_manager($userid = false) {
    global $DB, $USER;

    if ($userid === false) {
        $userid = $USER->id;
    }

    $enabletempmanagers = get_config(null, 'enabletempmanagers');
    if (!empty($enabletempmanagers)) {
        if ($tempmanager = totara_get_manager($userid, null, false, true)) {
            $mostprimarymanagers[$userid] = $tempmanager;
            return $tempmanager;
        }
    }

    $sql = "SELECT u.*
                  FROM {pos_assignment} pa
                  JOIN {user} u ON u.id = pa.managerid
                 WHERE pa.userid = :userid
                    AND (pa.timevalidfrom is null OR pa.timevalidfrom <= :from)
                    AND (pa.timevalidto is null OR pa.timevalidto >= :to)
              ORDER BY pa.type ASC";

    if ($manager = $DB->get_record_sql($sql, array('userid' => $userid, 'from' => time(), 'to' => time()), IGNORE_MULTIPLE)) {
        return $manager;
    }

    return false;
}

/**
 * Update/set a temp manager for the specified user
 *
 * @param int $userid Id of user to set temp manager for
 * @param int $managerid Id of temp manager to be assigned to user.
 * @param int $expiry Temp manager expiry epoch timestamp
 */
function totara_update_temporary_manager($userid, $managerid, $expiry) {
    global $CFG, $DB, $USER;

    if (!$user = $DB->get_record('user', array('id' => $userid))) {
        return false;
    }

    $usercontext = context_user::instance($userid);
    $realmanager = totara_get_manager($userid, null, true);
    $oldtempmanager = $DB->get_record('temporary_manager', array('userid' => $userid));

    if (!$newtempmanager = $DB->get_record('user', array('id' => $managerid))) {
        return false;
    }

    // Set up messaging.
    require_once($CFG->dirroot.'/totara/message/messagelib.php');
    $msg = new stdClass();
    $msg->userfrom = $USER;
    $msg->msgstatus = TOTARA_MSG_STATUS_OK;
    $msg->contexturl = $CFG->wwwroot.'/user/positions.php?user='.$userid.'&courseid='.SITEID;
    $msg->contexturlname = get_string('xpositions', 'totara_core', fullname($user));
    $msgparams = (object)array('staffmember' => fullname($user), 'tempmanager' => fullname($newtempmanager),
        'expirytime' => userdate($expiry, get_string('datepickerlongyearphpuserdate', 'totara_core')), 'url' => $msg->contexturl);

    if (!empty($oldtempmanager) && $newtempmanager->id == $oldtempmanager->tempmanagerid) {
        if ($oldtempmanager->expirytime == $expiry) {
            // Nothing to do here.
            return true;
        } else {
            // Update expiry time.
            $oldtempmanager->expirytime = $expiry;
            $oldtempmanager->timemodified = time();

            $DB->update_record('temporary_manager', $oldtempmanager);

            // Expiry change notifications.

            // Notify staff member.
            $msg->userto = $user;
            $msg->subject = get_string('tempmanagerexpiryupdatemsgstaffsubject', 'totara_core', $msgparams);
            $msg->fullmessage = get_string('tempmanagerexpiryupdatemsgstaff', 'totara_core', $msgparams);
            $msg->fullmessagehtml = get_string('tempmanagerexpiryupdatemsgstaff', 'totara_core', $msgparams);
            tm_alert_send($msg);

            // Notify real manager.
            if (!empty($realmanager)) {
                $msg->userto = $realmanager;
                $msg->subject = get_string('tempmanagerexpiryupdatemsgmgrsubject', 'totara_core', $msgparams);
                $msg->fullmessage = get_string('tempmanagerexpiryupdatemsgmgr', 'totara_core', $msgparams);
                $msg->fullmessagehtml = get_string('tempmanagerexpiryupdatemsgmgr', 'totara_core', $msgparams);
                $msg->roleid = $CFG->managerroleid;
                tm_alert_send($msg);
            }

            // Notify temp manager.
            $msg->userto = $newtempmanager;
            $msg->subject = get_string('tempmanagerexpiryupdatemsgtmpmgrsubject', 'totara_core', $msgparams);
            $msg->fullmessage = get_string('tempmanagerexpiryupdatemsgtmpmgr', 'totara_core', $msgparams);
            $msg->fullmessagehtml = get_string('tempmanagerexpiryupdatemsgtmpmgr', 'totara_core', $msgparams);
            $msg->roleid = $CFG->managerroleid;
            tm_alert_send($msg);

            return true;
        }
    }

    $transaction = $DB->start_delegated_transaction();

    // Unassign the current temporary manager.
    totara_unassign_temporary_manager($userid);

    // Assign new temporary manager.
    $record = new stdClass();
    $record->userid = $userid;
    $record->tempmanagerid = $managerid;
    $record->expirytime = $expiry;
    $record->timemodified = time();
    $record->usermodified = $USER->id;

    $record->id = $DB->insert_record('temporary_manager', $record);

    // Assign/update temp manager role assignment.
    role_assign($CFG->managerroleid, $managerid, $usercontext->id, '', 0, time());

    $transaction->allow_commit();

    // Send assignment notifications.

    // Notify staff member.
    $msg->userto = $user;
    $msg->subject = get_string('tempmanagerassignmsgstaffsubject', 'totara_core', $msgparams);
    $msg->fullmessage = get_string('tempmanagerassignmsgstaff', 'totara_core', $msgparams);
    $msg->fullmessagehtml = get_string('tempmanagerassignmsgstaff', 'totara_core', $msgparams);
    tm_alert_send($msg);

    // Notify real manager.
    if (!empty($realmanager)) {
        $msg->userto = $realmanager;
        $msg->subject = get_string('tempmanagerassignmsgmgrsubject', 'totara_core', $msgparams);
        $msg->fullmessage = get_string('tempmanagerassignmsgmgr', 'totara_core', $msgparams);
        $msg->fullmessagehtml = get_string('tempmanagerassignmsgmgr', 'totara_core', $msgparams);
        $msg->roleid = $CFG->managerroleid;
        tm_alert_send($msg);
    }

    // Notify temp manager.
    $msg->userto = $newtempmanager;
    $msg->subject = get_string('tempmanagerassignmsgtmpmgrsubject', 'totara_core', $msgparams);
    $msg->fullmessage = get_string('tempmanagerassignmsgtmpmgr', 'totara_core', $msgparams);
    $msg->fullmessagehtml = get_string('tempmanagerassignmsgtmpmgr', 'totara_core', $msgparams);
    $msg->roleid = $CFG->managerroleid;
    tm_alert_send($msg);
}

/**
 * Unassign the temporary manager of the specified user
 *
 * @param int $userid
 * @return boolean true on success
 * @throws Exception
 */
function totara_unassign_temporary_manager($userid) {
    global $DB, $CFG;

    if (!$tempmanager = $DB->get_record('temporary_manager', array('userid' => $userid))) {
        // Nothing to do.
        return true;
    }
    $realmanager = totara_get_manager($userid, null, true);

    $transaction = $DB->start_delegated_transaction();

    // Unassign temp manager from user's context.
    if (empty($realmanager) || $tempmanager->tempmanagerid != $realmanager->id) {
        // Unassign old temp manager, if this is not somehow the real manager as well.
        $usercontext = context_user::instance($userid);
        role_unassign($CFG->managerroleid, $tempmanager->tempmanagerid, $usercontext->id);
    }

    // Delete temp manager record.
    $DB->delete_records('temporary_manager', array('id' => $tempmanager->id));

    $transaction->allow_commit();

    return true;
}

/**
 * Find out a user's teamleader (manager's manager).
 *
 * @param int $userid Id of the user whose teamleader we want
 * @param int $postype Type of the position we want the teamleader for (POSITION_TYPE_* constant).
 *                     Defaults to primary position(optional)
 * @return mixed False if no teamleader. Teamleader user object from mdl_user if the user has a teamleader.
 */
function totara_get_teamleader($userid, $postype=null) {
    $manager = totara_get_manager($userid, $postype);
    if ($manager) {
        // We use the default postype for the manager's manager.
        return totara_get_manager($manager->id);
    } else {
        return false;
    }
}


/**
 * Find out a user's appraiser.
 *
 * @param int $userid Id of the user whose appraiser we want
 * @param int $postype Type of the position we want the appraiser for (POSITION_TYPE_* constant).
 *                     Defaults to primary position(optional)
 * @return mixed False if no appraiser. Appraiser user object from mdl_user if the user has a appraiser.
 */
function totara_get_appraiser($userid, $postype=null) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/totara/hierarchy/prefix/position/lib.php');
    $postype = ($postype === null) ? POSITION_TYPE_PRIMARY : (int) $postype;

    $userid = (int) $userid;
    $sql = "
        SELECT appraiser.*
          FROM {pos_assignment} pa
    INNER JOIN {user} appraiser
            ON pa.appraiserid = appraiser.id
         WHERE pa.userid = ?
           AND pa.type = ?";

    // Return an appraiser if they have one otherwise false.
    return $DB->get_record_sql($sql, array($userid, $postype));
}


/**
* returns unix timestamp from a date string depending on the date format
*
* @param string $format e.g. "d/m/Y" - see date_parse_from_format for supported formats
* @param string $date a date to be converted e.g. "12/06/12"
* @return int unix timestamp (0 if fails to parse)
*/
function totara_date_parse_from_format ($format, $date) {

    global $CFG;
    $tz = isset($CFG->timezone) ? $CFG->timezone : 99;
    $timezone = get_user_timezone_offset($tz);
    $dateArray = array();
    $dateArray = date_parse_from_format($format, $date);
    if (is_array($dateArray) && isset($dateArray['error_count']) &&
        $dateArray['error_count'] == 0) {
        if (abs($timezone) > 13) {
            $time = mktime($dateArray['hour'],
                    $dateArray['minute'],
                    $dateArray['second'],
                    $dateArray['month'],
                    $dateArray['day'],
                    $dateArray['year']);
        } else {
            $time = gmmktime($dateArray['hour'],
                    $dateArray['minute'],
                    $dateArray['second'],
                    $dateArray['month'],
                    $dateArray['day'],
                    $dateArray['year']);
            $time = usertime($time, $timezone);
        }
        return $time;
    } else {
        return 0;
    }
}


/**
 * Check if the HTTP request was of type POST
 *
 * This function is useful as sometimes the $_POST array can be empty
 * if it's size exceeded post_max_size
 *
 * @access  public
 * @return  boolean
 */
function totara_is_post_request() {
    return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
}


/**
 * Download stored errorlog as a zip
 *
 * @access  public
 * @return  void
 */
function totara_errors_download() {
    global $DB;

    // Load errors from database
    $errors = $DB->get_records('errorlog');
    if (!$errors) {
        $errors = array();
    }

    // Format them nicely as strings
    $content = '';
    foreach ($errors as $error) {
        $error = (array) $error;
        foreach ($error as $key => $value) {
            $error[$key] = str_replace(array("\t", "\n"), ' ', $value);
        }

        $content .= implode("\t", $error);
        $content .= "\n";
    }

    send_temp_file($content, 'totara-error.log', true);
}


/**
 * Generate markup for search box
 *
 * Gives ability to specify courses, programs and/or categories in the results
 * as well as the ability to limit by category
 *
 * @access  public
 * @param   string  $value      Search value
 * @param   bool    $return     Return results (always true in M2.0, param left until all calls elsewhere cleaned up!)
 * @param   string  $type       Type of results ('all', 'course', 'program', 'certification', 'category')
 * @param   int     $category   Parent category (0 means all, -1 means global search)
 * @return  string|void
 */
function print_totara_search($value = '', $return = true, $type = 'all', $category = -1) {

    global $CFG, $DB, $PAGE;
    $return = ($return) ? $return : true;

    static $count = 0;

    $count++;

    $id = 'totarasearch';

    if ($count > 1) {
        $id .= '_'.$count;
    }

    $action = "{$CFG->wwwroot}/course/search.php";

    // If searching in a category, indicate which category
    if ($category > 0) {
        // Get category name
        $categoryname = $DB->get_field('course_categories', 'name', array('id' => $category));
        if ($categoryname) {
            $strsearch = get_string('searchx', 'totara_core', $categoryname);
        } else {
            $strsearch = get_string('search');
        }
    } else {
        if ($type == 'course') {
            $strsearch = get_string('searchallcourses', 'totara_coursecatalog');
        } elseif ($type == 'program') {
            $strsearch = get_string('searchallprograms', 'totara_coursecatalog');
        } elseif ($type == 'certification') {
            $strsearch = get_string('searchallcertifications', 'totara_coursecatalog');
        } elseif ($type == 'category') {
            $strsearch = get_string('searchallcategories', 'totara_coursecatalog');
        } else {
            $strsearch = get_string('search');
            $type = '';
        }
    }

    $hiddenfields = array(
        'viewtype' => $type,
        'category' => $category,
    );
    $formid = 'searchtotara';
    $inputid = 'navsearchbox';
    $value = s($value, true);
    $strsearch = s($strsearch);

    $renderer = $PAGE->get_renderer('totara_core');
    $output = $renderer->print_totara_search($action, $hiddenfields, $strsearch, $value, $formid, $inputid);

    return $output;
}


/**
 * Displays a generic editing on/off button suitable for any page
 *
 * @param string $settingname Name of the $USER property used to determine if the button should display on or off
 * @param array $params Associative array of additional parameters to pass (optional)
 *
 * @return string HTML to display the button
 */
function totara_print_edit_button($settingname, $params = array()) {
    global $CFG, $USER, $OUTPUT;

    $currentstate = isset($USER->$settingname) ?
        $USER->$settingname : null;

    // Work out the appropriate action.
    if (empty($currentstate)) {
        $label = get_string('turneditingon');
        $edit = 'on';
    } else {
        $label = get_string('turneditingoff');
        $edit = 'off';
    }

    // Generate the button HTML.
    $params[$settingname] = $edit;
    return $OUTPUT->single_button(new moodle_url(qualified_me(), $params), $label, 'get');
}


/**
 * Return a language string in the local language for a given user
 *
 * @deprecated Use get_string() with 4th parameter instead
 *
 */
function get_string_in_user_lang($user, $identifier, $module='', $a=NULL, $extralocations=NULL) {
    debugging('get_string_in_user_lang() is deprecated. Use get_string() with 4th param instead', DEBUG_DEVELOPER);
    return get_string($identifier, $module, $a, $user->lang);
}

/**
 * Returns the SQL to be used in order to CAST one column to CHAR
 *
 * @param string fieldname the name of the field to be casted
 * @return string the piece of SQL code to be used in your statement.
 */
function sql_cast2char($fieldname) {

    global $DB;

    $sql = '';

    switch ($DB->get_dbfamily()) {
        case 'mysql':
            $sql = ' CAST(' . $fieldname . ' AS CHAR) COLLATE utf8_bin';
            break;
        case 'postgres':
            $sql = ' CAST(' . $fieldname . ' AS VARCHAR) ';
            break;
        case 'mssql':
            $sql = ' CAST(' . $fieldname . ' AS NVARCHAR(MAX)) ';
            break;
        case 'oracle':
            $sql = ' TO_CHAR(' . $fieldname . ') ';
            break;
        default:
            $sql = ' ' . $fieldname . ' ';
    }

    return $sql;
}


/**
 * Returns the SQL to be used in order to CAST one column to FLOAT
 *
 * @param string fieldname the name of the field to be casted
 * @return string the piece of SQL code to be used in your statement.
 */
function sql_cast2float($fieldname) {
    global $DB;

    $sql = '';

    switch ($DB->get_dbfamily()) {
        case 'mysql':
            $sql = ' CAST(' . $fieldname . ' AS DECIMAL(20,2)) ';
            break;
        case 'mssql':
        case 'postgres':
            $sql = ' CAST(' . $fieldname . ' AS FLOAT) ';
            break;
        case 'oracle':
            $sql = ' TO_BINARY_FLOAT(' . $fieldname . ') ';
            break;
        default:
            $sql = ' ' . $fieldname . ' ';
    }

    return $sql;
}


/**
 * Assign a user a position assignment and create/delete role assignments as required
 *
 * @param $assignment position_assignment object, include old reportstoid field (if any) and
 *                    new managerid
 * @param $unittest set to true if using for unit tests (optional)
 */
function assign_user_position($assignment, $unittest=false) {
    global $CFG, $DB;

    $transaction = $DB->start_delegated_transaction();

    // Get old user id.
    if (!empty($assignment->reportstoid)) {
        $old_managerid = $DB->get_field('role_assignments', 'userid', array('id' => $assignment->reportstoid));
    } else {
        $old_managerid = null;
    }
    $managerchanged = false;
    if ($old_managerid != $assignment->managerid) {
        $managerchanged = true;
    }
    // TODO SCANMSG: Need to figure out how to re-add start time and end time into manager role assignment
    //          now that the role_assignment record no longer has start/end fields. See:
    //          http://docs.moodle.org/dev/New_enrolments_in_2.0
    //          and mdl_enrol and mdl_user_enrolments.

    // Skip this bit during testing as we don't have all the required tables for role assignments.
    if (!$unittest) {
        // Get context.
        $context = context_user::instance($assignment->userid);
        // Get manager role id.
        $roleid = $CFG->managerroleid;
        // Delete role assignment if there was a manager but it changed.
        if ($old_managerid && $managerchanged) {
            role_unassign($roleid, $old_managerid, $context->id);
        }
        // Create new role assignment if there is now and a manager but it changed.
        if ($assignment->managerid && $managerchanged) {
            // Assign manager to user.
            $raid = role_assign(
                $roleid,
                $assignment->managerid,
                $context->id
            );
            // Update reportstoid.
            $assignment->reportstoid = $raid;
        }
    }
    // Store the date of this assignment.
    require_once($CFG->dirroot.'/totara/program/lib.php');
    prog_store_position_assignment($assignment);
    // Save assignment.
    $assignment->save($managerchanged);
    $transaction->allow_commit();

}

/**
* Loops through the navigation options and returns an array of classes
*
* The array contains the navigation option name as a key, and a string
* to be inserted into a class as the value. The string is either
* ' selected' if the option is currently selected, or an empty string ('')
*
* @param array $navstructure A nested array containing the structure of the menu
* @param string $primary_selected The name of the primary option
* @param string $secondary_selected The name of the secondary option
*
* @return array Array of strings, keyed on option names
*/
function totara_get_nav_select_classes($navstructure, $primary_selected, $secondary_selected) {

    $selectedstr = ' selected';
    $selected = array();
    foreach($navstructure as $primary => $secondaries) {
        if($primary_selected == $primary) {
            $selected[$primary] = $selectedstr;
        } else {
            $selected[$primary] = '';
        }
        foreach($secondaries as $secondary) {
            if($secondary_selected == $secondary) {
                $selected[$secondary] = $selectedstr;
            } else {
                $selected[$secondary] = '';
            }
        }
    }
    return $selected;
}

/**
 * Builds Totara menu, returns an array of objects that
 * represent the stucture of the menu
 *
 * The parents must be defined before the children so we
 * can correctly figure out which items should be selected
 *
 * @return Array of menu item objects
 */
function totara_build_menu() {
    $rs = \totara_core\totara\menu\menu::get_nodes();
    $tree = array();
    foreach ($rs as $id => $item) {
        // If parent exists and parent visibility is always hide then hide all children.
        if (!is_null($item->parentvisibility) && $item->parentvisibility == \totara_core\totara\menu\menu::HIDE_ALWAYS) {
            continue;
        // Otherwise, if parent is show when required, check if it is currently visible.
        } else if ($item->parentvisibility == \totara_core\totara\menu\menu::SHOW_WHEN_REQUIRED) {
            $classname = $item->parent;
            if (!is_null($classname) && class_exists($classname)) {
                $parentnode = new $classname(array());
                if ($parentnode->get_visibility() != \totara_core\totara\menu\menu::SHOW_ALWAYS) {
                    continue;
                }
            }
        }
        $node = \totara_core\totara\menu\menu::node_instance($item);
        // Silently ignore bad nodes - they might have been removed
        // from the code but not purged from the DB yet.
        if ($node === false) {
            continue;
        }
        // Check each node's visibility.
        if ($node->get_visibility() != \totara_core\totara\menu\menu::SHOW_ALWAYS) {
            continue;
        }

        $tree[] = (object)array(
            'name'     => $node->get_name(),
            'linktext' => $node->get_title(),
            'parent'   => $node->get_parent(),
            'url'      => $node->get_url(),
            'target'   => $node->get_targetattr()
        );
    }

    return $tree;
}

function totara_upgrade_menu() {
    $TOTARAMENU = new \totara_core\totara\menu\build();
    $plugintypes = core_component::get_plugin_types();
    foreach ($plugintypes as $plugin => $path) {
        $pluginname = core_component::get_plugin_list_with_file($plugin, 'db/totaramenu.php');
        if (!empty($pluginname)) {
            foreach ($pluginname as $name => $file) {
                require_once($file);
            }
        }
    }
    $TOTARAMENU->upgrade();
}

/**
 * Install the Totara MyMoodle blocks
 *
 * @return bool
 */
function totara_reset_mymoodle_blocks() {
    global $DB, $SITE;

    // get the id of the default mymoodle page
    $mypageid = $DB->get_field_sql('SELECT id FROM {my_pages} WHERE userid IS null AND private = 1');

    // build new block array
    $blocks = array(
        (object)array(
            'blockname'=> 'totara_tasks',
            'parentcontextid' => $SITE->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => $mypageid,
            'defaultweight' => 1,
            'configdata' => '',
            'defaultregion' => 'content'
        ),
        (object)array(
            'blockname'=> 'totara_alerts',
            'parentcontextid' => $SITE->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => $mypageid,
            'defaultweight' => 1,
            'configdata' => '',
            'defaultregion' => 'content',
        ),
        (object)array(
            'blockname'=> 'totara_stats',
            'parentcontextid' => $SITE->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => $mypageid,
            'defaultweight' => 1,
            'configdata' => '',
            'defaultregion' => 'side-post',
        )
    );

    // insert blocks
    foreach ($blocks as $b) {
        $blockid = $DB->insert_record('block_instances', $b);
        context_block::instance($blockid);
    }

    //A separate set up for a quicklinks block as it needs additional data to be added on install
    $blockinstance = new stdClass();
    $blockinstance->blockname = 'totara_quicklinks';
    $blockinstance->parentcontextid = SITEID;
    $blockinstance->showinsubcontexts = 0;
    $blockinstance->pagetypepattern = 'my-index';
    $blockinstance->subpagepattern = $mypageid;
    $blockinstance->defaultregion = 'side-post';
    $blockinstance->defaultweight = 1;
    $blockinstance->configdata = '';
    $blockinstance->id = $DB->insert_record('block_instances', $blockinstance);

    // Ensure the block context is created.
    context_block::instance($blockinstance->id);

    // If the new instance was created, allow it to do additional setup
    if ($block = block_instance('totara_quicklinks', $blockinstance)) {
        $block->instance_create();
    }

    return 1;
}


/**
 * Color functions used by totara themes for auto-generating colors
 */

/**
 * Given a hex color code lighten or darken the color by the specified
 * percentage and return the hex code of the new color
 *
 * @param string $color Hex color code in format '#abc' or '#aabbcc'
 * @param integer $percent Number between -100 and 100, negative to darken
 * @return string 6 digit hex color code for resulting color
 */
function totara_brightness($color, $percent) {
    // convert 3 digit color codes into 6 digit form
    $pattern = '/^#([[:xdigit:]])([[:xdigit:]])([[:xdigit:]])$/';
    $color = preg_replace($pattern, '#$1$1$2$2$3$3', $color );

    // don't change if color format not recognised
    $pattern = '/^#([[:xdigit:]]{2})([[:xdigit:]]{2})([[:xdigit:]]{2})$/';
    if (!preg_match($pattern, $color, $matches)) {
        debugging("Bad hex color '{$color}' passed to totara_brightness().", DEBUG_DEVELOPER);
        return $color;
    }
    $red = hexdec($matches[1]);
    $green = hexdec($matches[2]);
    $blue = hexdec($matches[3]);

    if ($percent >= 0) {
        $red = floor($red + (255 - $red) * $percent / 100);
        $green = floor($green + (255 - $green) * $percent / 100);
        $blue = floor($blue + (255 - $blue) * $percent / 100);
    } else {
        // remember $percent is negative
        $red = floor($red + $red * $percent / 100);
        $green = floor($green + $green * $percent / 100);
        $blue = floor($blue + $blue * $percent / 100);
    }

    return '#' .
        str_pad(dechex($red), 2, '0', STR_PAD_LEFT) .
        str_pad(dechex($green), 2, '0', STR_PAD_LEFT) .
        str_pad(dechex($blue), 2, '0', STR_PAD_LEFT);
}


/**
 * Given a hex color code lighten or darken the color by the specified
 * number of hex points and return the hex code of the new color
 *
 * This differs from {@link totara_brightness} in that the scaling is
 * linear (until pure white or black is reached). *
 *
 * @param string $color Hex color code in format '#abc' or '#aabbcc'
 * @param integer $amount Number between -255 and 255, negative to darken
 * @return string 6 digit hex color code for resulting color
 */
function totara_brightness_linear($color, $amount) {
    // convert 3 digit color codes into 6 digit form
    $pattern = '/^#([[:xdigit:]])([[:xdigit:]])([[:xdigit:]])$/';
    $color = preg_replace($pattern, '#$1$1$2$2$3$3', $color );

    // don't change if color format not recognised
    $pattern = '/^#([[:xdigit:]]{2})([[:xdigit:]]{2})([[:xdigit:]]{2})$/';
    if (!preg_match($pattern, $color, $matches)) {
        debugging("Bad hex color '{$color}' passed to totara_brightness_linear().", DEBUG_DEVELOPER);
        return $color;
    }
    $red = hexdec($matches[1]);
    $green = hexdec($matches[2]);
    $blue = hexdec($matches[3]);

    // max and min ensure colour remains within range
    $red = max(min($red + $amount, 255), 0);
    $green = max(min($green + $amount, 255), 0);
    $blue = max(min($blue + $amount, 255), 0);

    return '#' .
        str_pad(dechex($red), 2, '0', STR_PAD_LEFT) .
        str_pad(dechex($green), 2, '0', STR_PAD_LEFT) .
        str_pad(dechex($blue), 2, '0', STR_PAD_LEFT);
}

/**
 * Given a hex color code return the hex code for either white or black,
 * depending on which color will have the most contrast compared to $color
 *
 * @param string $color Hex color code in format '#abc' or '#aabbcc'
 * @return string 6 digit hex color code for resulting color
 */
function totara_readable_text($color) {
    // convert 3 digit color codes into 6 digit form
    $pattern = '/^#([[:xdigit:]])([[:xdigit:]])([[:xdigit:]])$/';
    $color = preg_replace($pattern, '#$1$1$2$2$3$3', $color );

    // don't change if color format not recognised
    $pattern = '/^#([[:xdigit:]]{2})([[:xdigit:]]{2})([[:xdigit:]]{2})$/';
    if (!preg_match($pattern, $color, $matches)) {
        debugging("Bad hex color '{$color}' passed to totara_readable_text().", DEBUG_DEVELOPER);
        return $color;
    }
    $red = hexdec($matches[1]);
    $green = hexdec($matches[2]);
    $blue = hexdec($matches[3]);

    // get average intensity
    $avg = array_sum(array($red, $green, $blue)) / 3;
    return ($avg >= 153) ? '#000000' : '#FFFFFF';
}

/**
 * Given a hex color code return the rgba shadow that will work best on text
 * that is the readable-text color
 *
 * This is useful for adding shadows to text that uses the readable-text color.
 *
 * @param string $color Hex color code in format '#abc' or '#aabbcc'
 * @return string rgba() colour to provide an appropriate shadow for readable-text
 */
function totara_readable_text_shadow($color) {
    if (totara_readable_text($color) == '#FFFFFF') {
        return 'rgba(0, 0, 0, 0.75)';
    } else {
        return 'rgba(255, 255, 255, 0.75)';
    }
}
/**
 * Given a hex color code return the hex code for a desaturated version of
 * $color, which has the same brightness but is greyscale
 *
 * @param string $color Hex color code in format '#abc' or '#aabbcc'
 * @return string 6 digit hex color code for resulting greyscale color
 */
function totara_desaturate($color) {
    // convert 3 digit color codes into 6 digit form
    $pattern = '/^#([[:xdigit:]])([[:xdigit:]])([[:xdigit:]])$/';
    $color = preg_replace($pattern, '#$1$1$2$2$3$3', $color );

    // don't change if color format not recognised
    $pattern = '/^#([[:xdigit:]]{2})([[:xdigit:]]{2})([[:xdigit:]]{2})$/';
    if (!preg_match($pattern, $color, $matches)) {
        debugging("Bad hex color '{$color}' passed to desaturate().", DEBUG_DEVELOPER);
        return $color;
    }
    $red = hexdec($matches[1]);
    $green = hexdec($matches[2]);
    $blue = hexdec($matches[3]);

    // get average intensity
    $avg = array_sum(array($red, $green, $blue)) / 3;

    return '#' . str_repeat(str_pad(dechex($avg), 2, '0', STR_PAD_LEFT), 3);
}

/**
 * Given an array of the form:
 * array(
 *   // setting name => default value
 *   'linkcolor' => '#dddddd',
 * );
 * perform substitutions on the css provided
 *
 * @param string $css CSS to substitute settings variables
 * @param object $theme Theme object
 * @param array $substitutions Array of settingname/defaultcolor pairs
 * @return string CSS with replacements
 */
function totara_theme_generate_autocolors($css, $theme, $substitutions) {

    // each element added here will generate a new color
    // with the key appended to the existing setting name
    // and with the color passed through the function with the arguments
    // supplied via an array:
    $autosettings = array(
        'lighter' => array('brightness_linear', 15),
        'darker' => array('brightness_linear', -15),
        'light' => array('brightness_linear', 25),
        'dark' => array('brightness_linear', -25),
        'lighter-perc' => array('brightness', 15),
        'darker-perc' => array('brightness', -15),
        'light-perc' => array('brightness', 25),
        'dark-perc' => array('brightness', -25),
        'readable-text' => array('readable_text'),
        'readable-text-shadow' => array('readable_text_shadow'),
    );

    $find = array();
    $replace = array();
    foreach ($substitutions as $setting => $defaultcolor) {
        $value = isset($theme->settings->$setting) ? $theme->settings->$setting : $defaultcolor;
        $find[] = "[[setting:{$setting}]]";
        $replace[] = $value;

        foreach ($autosettings as $suffix => $modification) {
            if (!is_array($modification) || count($modification) < 1) {
                continue;
            }
            $function_name = 'totara_' . array_shift($modification);
            $function_args = $modification;
            array_unshift($function_args, $value);

            $find[] = "[[setting:{$setting}-$suffix]]";
            $replace[] = call_user_func_array($function_name, $function_args);
        }

        if ($setting == 'headerbgc') {
            $find[] = "[[setting:heading-on-headerbgc]]";
            $replace[] = (totara_readable_text($value) == '#000000' ? '#444444' : '#b3b3b3');

            $find[] = "[[setting:text-on-headerbgc]]";
            $replace[] = (totara_readable_text($value) == '#000000' ? '#444444' : '#cccccc');
        }
    }
    return str_replace($find, $replace, $css);
}

/**
 * Encrypt any string using totara public key
 *
 * @param string $plaintext
 * @param string $key Public key If not set totara public key will be used
 * @return string Encrypted data
 */
function encrypt_data($plaintext, $key = '') {
    global $CFG;
    require_once($CFG->dirroot.'/totara/core/lib/phpseclib/Crypt/RSA.php');

    $rsa = new Crypt_RSA();
    if ($key == '') {
        $key = file_get_contents(PUBLIC_KEY_PATH);
    }
    if (!$key) {
        return false;
    }
    $rsa->loadKey($key);
    $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
    $ciphertext = $rsa->encrypt($plaintext);
    return $ciphertext;
}

/**
 * Get course/program icon for displaying in course/program page.
 *
 * @param $instanceid
 * @return string icon URL.
 */
function totara_get_icon($instanceid, $icontype) {
    global $DB, $OUTPUT, $PAGE;

    $component = 'totara_core';
    $urlicon = '';

    if ($icontype == TOTARA_ICON_TYPE_COURSE) {
        $icon = $DB->get_field('course', 'icon', array('id' => $instanceid));
    } else {
        $icon = $DB->get_field('prog', 'icon', array('id' => $instanceid));
    }

    if ($customicon = $DB->get_record('files', array('pathnamehash' => $icon))) {
        $fs = get_file_storage();
        $context = context_system::instance();
        if ($file = $fs->get_file($context->id, $component, $icontype, $customicon->itemid, '/', $customicon->filename)) {
            $urlicon = moodle_url::make_pluginfile_url($file->get_contextid(), $component,
                $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $customicon->filename, true);
        }
    }

    if (empty($urlicon)) {
        $iconpath = $icontype . 'icons/';
        $imagelocation = $PAGE->theme->resolve_image_location($iconpath . $icon, $component);
        if (empty($icon) || empty($imagelocation)) {
            $icon = 'default';
        }
        $urlicon = $OUTPUT->pix_url('/' . $iconpath . $icon, $component);
    }

    return $urlicon->out();
}

/**
 * Determine if the current request is an ajax request
 *
 * @param array $server A $_SERVER array
 * @return boolean
 */
function is_ajax_request($server) {
    return (isset($server['HTTP_X_REQUESTED_WITH']) && strtolower($server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
}

/**
 * Totara specific initialisation
 * Currently needed only for AJAX scripts
 * Caution: Think before change to avoid conflict with other $CFG->moodlepageclass affecting code (for example installation scripts)
 */
function totara_setup() {
    global $CFG;
    if (is_ajax_request($_SERVER)) {
        $CFG->moodlepageclassfile = $CFG->dirroot.'/totara/core/pagelib.php';
        $CFG->moodlepageclass = 'totara_page';
    }
}

/**
 * Checks if idnumber already exists.
 * Used when adding new or updating exisiting records.
 *
 * @param string $table Name of the table
 * @param string $idnumber IDnumber to check
 * @param int $itemid Item id. Zero value means new item.
 *
 * @return bool True if idnumber already exists
 */
function totara_idnumber_exists($table, $idnumber, $itemid = 0) {
    global $DB;

    if (!$itemid) {
        $duplicate = $DB->record_exists($table, array('idnumber' => $idnumber));
    } else {
        $duplicate = $DB->record_exists_select($table, 'idnumber = :idnumber AND id != :itemid',
                                               array('idnumber' => $idnumber, 'itemid' => $itemid));
    }

    return $duplicate;
}

/**
 * List of strings which can be used with 'totara_feature_*() functions'.
 *
 * Update this list if you add/remove settings in admin/settings/subsystems.php.
 *
 * @return array Array of strings of supported features (should have a matching "enable{$feature}" config setting).
 */
function totara_advanced_features_list() {
    return array(
        'goals',
        'appraisals',
        'feedback360',
        'learningplans',
        'programs',
        'certifications',
    );
}

/**
 * Check the state of a particular Totara feature against the specified state.
 *
 * Used by the totara_feature_*() functions to see if some Totara functionality is visible/hidden/disabled.
 *
 * @param string $feature Name of the feature to check, must match options from {@link totara_advanced_features_list()}.
 * @param integer $stateconstant State to check, must match one of TOTARA_*FEATURE constants defined in this file.
 * @return bool True if the feature's config setting is in the specified state.
 */
function totara_feature_check_state($feature, $stateconstant) {
    global $CFG;

    if (!in_array($feature, totara_advanced_features_list())) {
        throw new coding_exception("'{$feature}' not supported by Totara feature checking code.");
    }

    $cfgsetting = "enable{$feature}";
    return (isset($CFG->$cfgsetting) && $CFG->$cfgsetting == $stateconstant);
}

/**
 * Check to see if a feature is set to be visible in Advanced Features
 *
 * @param string $feature The name of the feature from the list in {@link totara_feature_check_support()}.
 * @return bool True if the feature is set to be visible.
 */
function totara_feature_visible($feature) {
    return totara_feature_check_state($feature, TOTARA_SHOWFEATURE);
}

/**
 * Check to see if a feature is set to be disabled in Advanced Features
 *
 * @param string $feature The name of the feature from the list in {@link totara_feature_check_support()}.
 * @return bool True if the feature is disabled.
 */
function totara_feature_disabled($feature) {
    return totara_feature_check_state($feature, TOTARA_DISABLEFEATURE);
}

/**
 * Check to see if a feature is set to be hidden in Advanced Features
 *
 * @param string $feature The name of the feature from the list in {@link totara_feature_check_support()}.
 * @return bool True if the feature is hidden.
 */
function totara_feature_hidden($feature) {
    return totara_feature_check_state($feature, TOTARA_HIDEFEATURE);
}
