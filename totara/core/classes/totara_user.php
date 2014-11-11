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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

namespace totara_core;
defined('MOODLE_INTERNAL') || die();

// Extending the user class for user objects used in emailing external addresses.
class totara_user extends \core_user {

    // External emails default userid.
    const EXTERNAL_USER = -45;

    // @var stdClass keep record of external email user.
    public static $externaluser = false;

    public static function get_user($userid, $fields = '*', $strictness = IGNORE_MISSING) {

        switch ($userid) {
            case self::EXTERNAL_USER:
                return self::get_external_user($strictness);
                break;
            default:
                return parent::get_user($userid, $fields, $strictness);
                break;
        }
    }

    /**
     * Helper function to return a dummy user to email external an email addess
     *
     * @param string $emailaddress      The email address to use
     * @return stdClass                 The dummy user object
     */
    public static function get_external_user($emailaddress) {
        global $CFG;

        // We haven't got an external user in cache, better create one.
        if (empty(self::$externaluser)) {
            self::$externaluser = parent::get_dummy_user_record();
            self::$externaluser->id = self::EXTERNAL_USER;
            self::$externaluser->username = "external";
            self::$externaluser->lang = $CFG->lang;
            self::$externaluser->maildisplay = 1;
            self::$externaluser->emailstop = 0;
        }

        // Set some fields for this external user object.
        self::$externaluser->email = $emailaddress;
        self::$externaluser->firstname = $emailaddress;
        return self::$externaluser;
    }
}
