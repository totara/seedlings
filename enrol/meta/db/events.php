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
 * Meta course enrolment plugin event handler definition.
 *
 * @package enrol_meta
 * @category event
 * @copyright 2010 Petr Skoda {@link http://skodak.org}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// List of observers.
$observers = array(
/*
    'role_assigned_bulk' => array (
        'handlerfile'      => '/enrol/meta/locallib.php',
        'handlerfunction'  => array('enrol_meta_handler', 'role_assigned_bulk'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'role_unassigned' => array (
        'handlerfile'      => '/enrol/meta/locallib.php',
        'handlerfunction'  => array('enrol_meta_handler', 'role_unassigned'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'role_unassigned_bulk' => array (
        'handlerfile'      => '/enrol/meta/locallib.php',
        'handlerfunction'  => array('enrol_meta_handler', 'role_unassigned_bulk'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'user_enrolled' => array (
        'handlerfile'      => '/enrol/meta/locallib.php',
        'handlerfunction'  => array('enrol_meta_handler', 'user_enrolled'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'user_enrolled_bulk' => array (
        'handlerfile'      => '/enrol/meta/locallib.php',
        'handlerfunction'  => array('enrol_meta_handler', 'user_enrolled_bulk'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'user_unenrolled' => array (
        'handlerfile'      => '/enrol/meta/locallib.php',
        'handlerfunction'  => array('enrol_meta_handler', 'user_unenrolled'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'user_unenrolled_bulk' => array (
        'handlerfile'      => '/enrol/meta/locallib.php',
        'handlerfunction'  => array('enrol_meta_handler', 'user_unenrolled_bulk'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'user_enrol_modified' => array (
        'handlerfile'      => '/enrol/meta/locallib.php',
        'handlerfunction'  => array('enrol_meta_handler', 'user_enrol_modified'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'user_enrol_modified_bulk' => array (
        'handlerfile'      => '/enrol/meta/locallib.php',
        'handlerfunction'  => array('enrol_meta_handler', 'user_enrol_modified_bulk'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'course_deleted' => array (
        'handlerfile'      => '/enrol/meta/locallib.php',
        'handlerfunction'  => array('enrol_meta_handler', 'course_deleted'),
        'schedule'         => 'instant',
        'internal'         => 1,
*/
    array(
        'eventname'   => '\core\event\user_enrolment_created',
        'callback'    => 'enrol_meta_observer::user_enrolment_created',
    ),
    array(
        'eventname'   => '\core\event\user_enrolment_deleted',
        'callback'    => 'enrol_meta_observer::user_enrolment_deleted',
    ),
    array(
        'eventname'   => '\core\event\user_enrolment_updated',
        'callback'    => 'enrol_meta_observer::user_enrolment_updated',
    ),
    array(
        'eventname'   => '\core\event\role_assigned',
        'callback'    => 'enrol_meta_observer::role_assigned',
    ),
    array(
        'eventname'   => '\core\event\role_unassigned',
        'callback'    => 'enrol_meta_observer::role_unassigned',
    ),
    array(
        'eventname'   => '\core\event\course_deleted',
        'callback'    => 'enrol_meta_observer::course_deleted',
    ),
);
