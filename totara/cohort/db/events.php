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

$handlers = array (

    /* Cohort event handlers */
    'profilefield_deleted' => array (
         'handlerfile'      => '/cohort/lib.php',
         'handlerfunction'  => 'cohort_profilefield_deleted_handler',
         'schedule'         => 'instant'
     ),
    'position_updated' => array (
         'handlerfile'      => '/cohort/lib.php',
         'handlerfunction'  => 'cohort_position_updated_handler',
         'schedule'         => 'instant'
     ),
    'position_deleted' => array ( // Call the updated function as these need to do the same thing
         'handlerfile'      => '/cohort/lib.php',
         'handlerfunction'  => 'cohort_position_updated_handler',
         'schedule'         => 'instant'
     ),
    'organisation_updated' => array (
         'handlerfile'      => '/cohort/lib.php',
         'handlerfunction'  => 'cohort_organisation_updated_handler',
         'schedule'         => 'instant'
     ),
    'organisation_deleted' => array ( // Call the updated function as these need to do the same thing
         'handlerfile'      => '/cohort/lib.php',
         'handlerfunction'  => 'cohort_organisation_updated_handler',
         'schedule'         => 'instant'
     ),
);
