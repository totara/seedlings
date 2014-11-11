<?php
/**
* Copyright (C) 2011 Catalyst IT Ltd
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
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @package    auth/gauth
* @author     Catalyst IT Ltd
* @author     Piers Harding
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
* @copyright  (C) 2011 Catalyst IT Ltd http://catalyst.net.nz
*
*/
/**
 * index.php - landing page for auth/gauth based Google OpenId login
 *
 * @author  Piers Harding - made quite a number of changes
 * @version 1.0
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package auth/gauth
 */

global $CFG, $USER, $SESSION;

// do the normal Moodle bootstraping so we have access to all config and the DB
require_once('../../config.php');

// Check plugin is active
if (!is_enabled_auth('gauth')) {
    print_error(get_string("notconfigured", "auth_gauth"));
}

// get the plugin config for gauth
$pluginconfig = get_config('auth/gauth');
if (!isset($pluginconfig->userfield) || empty($pluginconfig->userfield)) {
    $pluginconfig->userfield = 'username';
}
unset($SESSION->GAUTHSessionControlled);

// pull in LightOpenID
require_once('openid.php');


// save the jump target - this is checked later that it
// starts with $CFG->wwwroot, and cleaned
if (isset($_GET['wantsurl'])) {
    $SESSION->wantsurl = $_GET['wantsurl'];
}

// do the OpenId negotiation
$data = false;
$parts = parse_url($CFG->wwwroot);
$host = null;
if (!empty($parts['host'])) {
    $host = $parts['host'];
}
else {
    auth_gauth_err('Host is not set: '.$CFG->wwwroot);
    print_error(get_string("auth_gauth_invalid_host", "auth_gauth"));
    die();
}
try {
    $openid = new LightOpenID($host);
    if (!$openid->mode) {
        $openid->identity = 'https://www.google.com/accounts/o8/id';
        $openid->required = array(
          'namePerson',
          'namePerson/first',
          'namePerson/last',
          'namePerson/friendly',
          'contact/email',
          'contact/country/home',
          'pref/language',
        );
        // Do gapps specific login page if info supplied.
        if (!empty($pluginconfig->domainspecificlogin)) {
            $openid->discover('https://www.google.com/accounts/o8/site-xrds?hd='.$pluginconfig->domainname);
        }
        auth_gauth_err('handing off to Google: '.$openid->authUrl());
        header('Location: ' . $openid->authUrl());
        die();
    } else if($openid->mode == 'cancel') {
        auth_gauth_err('Cancelled');
        print_error(get_string("auth_gauth_user_cancel", "auth_gauth"));
    } else if($openid->validate()) {
        auth_gauth_err('Validated');
        $data = $openid->getAttributes();
        $data['openid'] = $openid->identity;
    } else {
        auth_gauth_err('validation failed');
        print_error(get_string("auth_gauth_user_not_loggedin", "auth_gauth"));
    }
} catch (ErrorException $e) {
    // Major failure.
    auth_gauth_err('auth/gauth: failure: '.$e->getMessage());
    print_error(get_string("auth_gauth_openid_failure", "auth_gauth", $e->getMessage()));
}

if (empty($data)) {
    print_error(get_string("auth_gauth_openid_empty", "auth_gauth"));
    die();
}

// check for a wantsurl in the existing Moodle session
$wantsurl = isset($SESSION->wantsurl) ? $SESSION->wantsurl : FALSE;
if (empty($wantsurl) && isset($SESSION->wantsurl)) {
    $wantsurl = $SESSION->wantsurl;
}
unset($SESSION->wantsurl);

// Valid session. Register or update user in Moodle, log him on, and redirect to Moodle front
// realign Google data
$data['firstname'] = $data['namePerson/first'];
$data['lastname']  = $data['namePerson/last'];
$data['email']     = $data['contact/email'];
$data['lang']      = (isset ($data['pref/language']) ? strtoupper(array_shift(explode('-', $data['pref/language']))) : '');
if (isset($data['contact/country/home'])) {
    $data['country'] = $data['contact/country/home'];
}
else {
    $data['country']   = (isset ($data['pref/language']) ? strtoupper(array_pop(explode('-', $data['pref/language']))) : '');
}

if (empty($data['firstname']) || empty($data['lastname']) || empty($data['email'])) {
    print_error(get_string("auth_gauth_openid_key_empty", "auth_gauth"));
    die();
}

// check domain
if (!empty($pluginconfig->domainname)) {
    $domains = explode(',', $pluginconfig->domainname);
    $pass = false;
    foreach ($domains as $domain) {
        auth_gauth_err('openid: checking - '.$domain.'/'.$data['email']);
        if (preg_match('/'.$domain.'$/', $data['email'])) {
            $pass = true;
            auth_gauth_err('openid: domain matched - '.$domain.'/'.$data['email']);
            break;
        }
    }
    if (!$pass) {
        print_error(get_string("auth_gauth_invalid_domain", "auth_gauth") . $data['email']);
    }
}

auth_gauth_err('data: '.var_export($data, true));

// we require the plugin to know that we are now doing a gauth login in hook puser_login
$GLOBALS['gauth_login'] = TRUE;

// make variables accessible to gauth->get_userinfo. Information will be requested from authenticate_user_login -> create_user_record / update_user_record
$GLOBALS['gauth_login_attributes'] = $data;

// check user name attribute actually passed
if(!isset($data[$pluginconfig->username])) {
    auth_gauth_err('auth failed due to missing username gauth attribute: '.$pluginconfig->username);
    print_error(get_string("auth_gauth_username_error", "auth_gauth"));
}

// check that there isn't anything nasty in the username
if ($pluginconfig->casesensitive) {
    $username = $data[$pluginconfig->username];
}
else {
    $username = strtolower($data[$pluginconfig->username]);
}
auth_gauth_err('username: '.var_export($username, true));
if ($username != clean_param($username, PARAM_TEXT)) {
    auth_gauth_err('auth failed due to illegal characters in username: '.$username);
    print_error('pluginauthfailedusername', 'auth_gauth', '', clean_param($data[$pluginconfig->username], PARAM_TEXT));
}

// just passes time as a password. User will never log in directly to moodle with this password anyway or so we hope?
$username = auth_gauth_addsingleslashes($username);
auth_gauth_err('username NOW: '.var_export($username, true));
auth_gauth_err('username field: '.var_export($pluginconfig->userfield, true));

// check if users are allowed to be created and if the user exists
$user_data =  get_complete_user_data($pluginconfig->userfield, $username);
auth_gauth_err('userdata: '.var_export($user_data, true));

if (isset($pluginconfig->createusers)) {
    if (!$pluginconfig->createusers && ! $user_data) {
        print_error('pluginauthfailed', 'auth_gauth', '', $pluginconfig->userfield.'/'.$data[$pluginconfig->username]);
    }
}
// swap username for Moodle one - if exists
if ($user_data) {
    $username = $user_data->username;
}

if (isset($pluginconfig->duallogin) && $pluginconfig->duallogin) {
    $USER = auth_gauth_authenticate_user_login($username, time());
}
else {
    $USER = authenticate_user_login($username, time());
}

// check that the signin worked
if ($USER == false) {
    print_error('pluginauthfailed', 'auth_gauth', '', $data[$pluginconfig->username]);
}
auth_gauth_err('auth_gauth: USER logged in: '.var_export($USER, true));

$USER->loggedin = true;
$USER->site     = $CFG->wwwroot;

// complete the user login sequence
$USER = get_complete_user_data('id', $USER->id);
complete_user_login($USER);

// just fast copied this from some other module - might not work...
if (isset($wantsurl) and (strpos($wantsurl, $CFG->wwwroot) === 0)) {
    $urltogo = clean_param($wantsurl, PARAM_URL);
} else {
    $urltogo = $CFG->wwwroot.'/';
}
auth_gauth_err('auth_gauth: jump to: '.$urltogo);

// flag this as a gauth based login
$SESSION->GAUTHSessionControlled = true;
add_to_log(SITEID, 'user', 'login', "view.php?id=$USER->id&course=".SITEID, $USER->id, 0, $USER->id);
redirect($urltogo);


/**
 * Copied from moodlelib:authenticate_user_login()
 *
 * WHY? because I need to hard code the plugins to auth_gauth, and this user
 * may be set to any number of other types of login method
 *
 * First of all - make sure that they aren't nologin - we don't mess with that!
 *
 *
 * Given a username and password, this function looks them
 * up using the currently selected authentication mechanism,
 * and if the authentication is successful, it returns a
 * valid $user object from the 'user' table.
 *
 * Uses auth_ functions from the currently active auth module
 *
 * After authenticate_user_login() returns success, you will need to
 * log that the user has logged in, and call complete_user_login() to set
 * the session up.
 *
 * @uses $CFG
 * @param string $username  User's username (with system magic quotes)
 * @param string $password  User's password (with system magic quotes)
 * @return user|flase A {@link $USER} object or false if error
 */
function auth_gauth_authenticate_user_login($username, $password) {

    global $CFG, $DB;

    // ensure that only gauth auth module is chosen
    $authsenabled = get_enabled_auth_plugins();

    if ($user = get_complete_user_data('username', $username, $CFG->mnet_localhost_id)) {
        $auth = empty($user->auth) ? 'manual' : $user->auth;  // use manual if auth not set
        if (!empty($user->suspended)) {
            add_to_log(SITEID, 'login', 'error', 'index.php', $username);
            auth_gauth_err('[client '.getremoteaddr()."]  $CFG->wwwroot  Suspended Login:  $username  ".$_SERVER['HTTP_USER_AGENT']);
            return false;
        }
        if ($auth=='nologin' or !is_enabled_auth($auth)) {
            add_to_log(0, 'login', 'error', 'index.php', $username);
            auth_gauth_err('[client '.getremoteaddr()."]  $CFG->wwwroot  Disabled Login:  $username  ".$_SERVER['HTTP_USER_AGENT']);
            return false;
        }
    } else {
        // check if there's a deleted record (cheaply)
        if ($DB->get_field('user', 'id', array('username' => $username, 'deleted' => 1))) {
            auth_gauth_err('[client '.$_SERVER['REMOTE_ADDR']."]  $CFG->wwwroot  Deleted Login:  $username  ".$_SERVER['HTTP_USER_AGENT']);
            return false;
        }

        $auths = $authsenabled;
        $user = new object();
        $user->id = 0;     // User does not exist
    }

    // hard code only gauth module
    $auths = array('gauth');
    foreach ($auths as $auth) {
        $authplugin = get_auth_plugin($auth);

        // on auth fail fall through to the next plugin
        auth_gauth_err($auth.' plugin');
        if (!$authplugin->user_login($username, $password)) {
            continue;
        }

        // successful authentication
        if ($user->id) {                          // User already exists in database
            if (empty($user->auth)) {             // For some reason auth isn't set yet
                $DB->set_field('user', 'auth', $auth, array('username' => $username));
                $user->auth = $auth;
            }
            if (empty($user->firstaccess)) { //prevent firstaccess from remaining 0 for manual account that never required confirmation

                $DB->set_field('user','firstaccess', $user->timemodified, array('id' => $user->id));
                $user->firstaccess = $user->timemodified;
            }

            // we don't want to upset the existing authentication schema for the user
            if ($authplugin->is_synchronised_with_external()) { // update user record from external DB
                $user = update_user_record($username);
            }
        } else {
            // if user not found, create him
            $user = create_user_record($username, $password, $auth);
        }

        $authplugin->sync_roles($user);

        foreach ($authsenabled as $hau) {
            $hauth = get_auth_plugin($hau);
            $hauth->user_authenticated_hook($user, $username, $password);
        }

        if (empty($user->id)) {
            return false;
        }

        if (!empty($user->suspended)) {
            // just in case some auth plugin suspended account
            add_to_log(SITEID, 'login', 'error', 'index.php', $username);
            auth_gauth_err('[client '.getremoteaddr()."]  $CFG->wwwroot  Suspended Login:  $username  ".$_SERVER['HTTP_USER_AGENT']);
            return false;
        }

        return $user;
    }

    // failed if all the plugins have failed
    add_to_log(0, 'login', 'error', 'index.php', $username);
    if (debugging('', DEBUG_ALL)) {
        auth_gauth_err('[client '.getremoteaddr()."]  $CFG->wwwroot  Failed Login:  $username  ".$_SERVER['HTTP_USER_AGENT']);
    }
    return false;
}

/**
 * Add slashes for single quotes and backslashes
 * so they can be included in single quoted string
 * (for config.php)
 */
function auth_gauth_addsingleslashes($input){
    return preg_replace("/(['\\\])/", "\\\\$1", $input);
}

/**
 *  error log wrapper
 * @param string $msg
 */
function auth_gauth_err($msg) {
    global $CFG;

    // check if we are debugging
    if (! $CFG->debug == DEBUG_DEVELOPER) {
        return;
    }
    error_log('auth/gauth: '.$msg);
}
