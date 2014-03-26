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
 * @package totara
 * @subpackage program
 */

$handlers = array (
    'program_assigned' => array (
         'handlerfile'      => '/totara/program/lib.php',
         'handlerfunction'  => 'prog_eventhandler_program_assigned',
         'schedule'         => 'instant'
     ),
    'program_unassigned' => array (
         'handlerfile'      => '/totara/program/lib.php',
         'handlerfunction'  => 'prog_eventhandler_program_unassigned',
         'schedule'         => 'instant'
     ),
    'program_completed' => array (
         'handlerfile'      => '/totara/program/lib.php',
         'handlerfunction'  => 'prog_eventhandler_program_completed',
         'schedule'         => 'instant'
     ),
    'program_courseset_completed' => array (
         'handlerfile'      => '/totara/program/lib.php',
         'handlerfunction'  => 'prog_eventhandler_courseset_completed',
         'schedule'         => 'instant'
     ),
    'user_firstaccess' => array (
         'handlerfile'      => '/totara/program/lib.php',
         'handlerfunction'  => 'prog_assignments_firstlogin',
         'schedule'         => 'instant'
     ),
     'user_deleted' => array(
         'handlerfile'       => '/totara/program/lib.php',
         'handlerfunction'   => 'prog_eventhandler_user_deleted',
         'schedule'          => 'instant'
     ),
);
