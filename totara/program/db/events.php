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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage program
 */

/**
 * this file should be used for all program event definitions and handers.
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

$observers = array(
    array(
        'eventname' => '\totara_program\event\program_assigned',
        'callback' => 'totara_program_observer::assigned',
    ),
    array(
        'eventname' => '\totara_program\event\program_unassigned',
        'callback' => 'totara_program_observer::unassigned',
    ),
    array(
        'eventname' => '\totara_program\event\program_completed',
        'callback' => 'totara_program_observer::completed',
    ),
    array(
        'eventname' => '\totara_program\event\program_courseset_completed',
        'callback' => 'totara_program_observer::courseset_completed',
    ),
    array(
        'eventname' => '\totara_core\event\user_firstlogin',
        'callback' => 'totara_program_observer::assignments_firstlogin',
    ),
    array(
        'eventname' => '\core\event\user_deleted',
        'callback' => 'totara_program_observer::user_deleted',
    ),
);
