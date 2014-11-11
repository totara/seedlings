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
 * @subpackage totara_plan
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

class dp_manager_role extends dp_base_role {
    function user_has_role($userid=null) {
        global $USER;
        // use current user if none given
        if (!isset($userid)) {
            $userid = $USER->id;
        }
        // are they the manager of this plan's owner?
        if (totara_is_manager($this->plan->userid, $userid)) {
            return 'manager';

        // Are they an administrative super-user?
        } else if (has_capability('totara/plan:accessanyplan', context_system::instance(), $userid )) {
            return 'manager';
        } else {
            return false;
        }
    }
}
