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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/authlib.php');

/**
 * Google OpenId authentication plugin.
**/
class auth_plugin_gauth extends auth_plugin_base {

    /**
    * Constructor.
    */
    function auth_plugin_gauth() {
        $this->authtype = 'gauth';
        $this->config = get_config('auth/gauth');
    }

    /**
    * Returns true if the username and password work and false if they are
    * wrong or don't exist.
    *
    * @param string $username The username (with system magic quotes)
    * @param string $password The password (with system magic quotes)
    * @return bool Authentication success or failure.
    */
    function user_login($username, $password) {
        // if true, user_login was initiated by gauth/index.php
        if($GLOBALS['gauth_login']) {
            unset($GLOBALS['gauth_login']);
            return TRUE;
        }
        return FALSE;
    }


    /**
    * Returns the user information for 'external' users. In this case the
    * attributes provided by Identity Provider
    *
    * @return array $result Associative array of user data
    */
    function get_userinfo($username) {
        if($login_attributes = $GLOBALS['gauth_login_attributes']) {
            $attributemap = $this->get_attributes();
            $country_codes = $this->country_codes();
            $result = array();

            foreach ($attributemap as $key => $value) {
                if(isset($login_attributes[$value]) && $attribute = $login_attributes[$value]) {
                    if ($key == 'country') {
                        if (isset($country_codes['bycode'][$attribute])) {
                            $result[$key] = clean_param($attribute, PARAM_TEXT);
                        }
                        else {
                            if (isset($country_codes['bynames'][$attribute])) {
                                $result[$key] = clean_param($country_codes['bynames'][$attribute], PARAM_TEXT);
                            }
                            // else we don't know what this country is so ignore it
                        }
                    }
                    else {
                        $result[$key] = clean_param($attribute, PARAM_TEXT);
                    }
                } else {
                    $result[$key] = clean_param($value, PARAM_TEXT); // allows user to set a hardcode default
                }
            }
            return $result;
        }

        return FALSE;
    }


    /**
    * Returns the list of country codes
    *
    * @return array $names of country codes indexed both ways
    */
    function country_codes() {
        global $CFG, $SESSION;

        $string = array();

        $lang = (isset($SESSION->lang) ? $SESSION->lang : $CFG->lang);
        include($CFG->dirroot.'/lang/'.$lang.'/countries.php');

        $names = array('bynames' => array(), 'bycode' => array());
        foreach ($string as $k => $v) {
            $names['bynames'][$v] = $k;
        }
        $names['bycode'] = $string;

        return $names;
    }


    /*
    * Returns array containing attribute mappings between Moodle and Google.
    */
    function get_attributes() {
        $configarray = (array) $this->config;

        $fields = array("firstname", "lastname", "email", "phone1", "phone2",
            "department", "address", "city", "country", "description",
            "idnumber", "lang", "guid");

        $moodleattributes = array();
        foreach ($fields as $field) {
            if (isset($configarray["field_map_$field"])) {
                $moodleattributes[$field] = $configarray["field_map_$field"];
            }
        }
        return $moodleattributes;
    }

    /**
    * Returns true if this authentication plugin is 'internal'.
    *
    * @return bool
    */
    function is_internal() {
        return false;
    }

    /**
    * Returns true if this authentication plugin can change the user's
    * password.
    *
    * @return bool
    */
    function can_change_password() {
        return false;
    }

    function loginpage_hook() {
        // Prevent username from being shown on login page after logout
        $GLOBALS['CFG']->nolastloggedin = true;

        return;
    }

    function logoutpage_hook() {
        global $SESSION;
    }

    /**
    * Prints a form for configuring this authentication plugin.
    *
    * This function is called from admin/auth.php, and outputs a full page with
    * a form for configuring this plugin.
    *
    * @param array $page An object containing all the data for this page.
    */

    function config_form($config, $err, $user_fields) {
        include "config.html";
    }

    /**
    * Processes and stores configuration data for this authentication plugin.
    *
    *
    * @param object $config Configuration object
    */
    function process_config($config) {
        // set to defaults if undefined
        if (!isset ($config->username)) {
            $config->username = 'mail';
        }
        if (!isset ($config->userfield)) {
            $config->userfield = 'username';
        }
        if (!isset ($config->casesensitive)) {
            $config->casesensitive = '';
        }
        if (!isset ($config->createusers)) {
            $config->createusers = '';
        }
        if (!isset ($config->duallogin)) {
            $config->duallogin = '';
        }
        if (!isset ($config->domainname)) {
            $config->domainname = '';
        }
        if (!isset ($config->domainspecificlogin)) {
            $config->domainspecificlogin = '';
        }

        // save settings
        set_config('username',            $config->username,            'auth/gauth');
        set_config('userfield',           $config->userfield,           'auth/gauth');
        set_config('casesensitive',       $config->casesensitive,       'auth/gauth');
        set_config('createusers',         $config->createusers,         'auth/gauth');
        set_config('duallogin',           $config->duallogin,           'auth/gauth');
        set_config('domainname',          $config->domainname,          'auth/gauth');
        set_config('domainspecificlogin', $config->domainspecificlogin, 'auth/gauth');

        return true;
    }

    /**
    * Cleans and returns first of potential many values (multi-valued attributes)
    *
    * @param string $string Possibly multi-valued attribute from Identity Provider
    */
    function get_first_string($string) {
        $list = explode(';', $string);
        $clean_string = trim($list[0]);

        return $clean_string;
    }

}
