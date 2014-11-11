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
 * @author Jake Salmon <jake.salmon@kineo.com>
 * @package totara
 * @subpackage cohort
 */

/**
 * this file should be used for all the custom event definitions and handers.
 * event names should all start with totara_.
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); //  It must be included from a Moodle page
}

$observers = array(
    array(
        'eventname' => '\totara_customfield\event\profilefield_deleted',
        'callback' => 'customfield_event_handler::profilefield_deleted',
        'includefile' => '/totara/customfield/lib.php',
    ),
    array( // Call the updated function as these need to do the same thing.
        'eventname' => '\totara_hierarchy\event\position_deleted',
        'callback' => 'totaracohort_event_handler::position_updated',
        'includefile' => '/totara/cohort/lib.php',
    ),
    array(
        'eventname' => '\totara_hierarchy\event\position_updated',
        'callback' => 'totaracohort_event_handler::position_updated',
        'includefile' => '/totara/cohort/lib.php',
    ),
    array( // Call the updated function as these need to do the same thing.
        'eventname' => '\totara_hierarchy\event\organisation_deleted',
        'callback' => 'totaracohort_event_handler::organisation_updated',
        'includefile' => '/totara/cohort/lib.php',
    ),
    array(
        'eventname' => '\totara_hierarchy\event\organisation_updated',
        'callback' => 'totaracohort_event_handler::organisation_updated',
        'includefile' => '/totara/cohort/lib.php',
    ),
);
