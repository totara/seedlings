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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package mod_facetoface
 */

/**
 * this file should be used for all facetoface event definitions and handers.
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

$handlers = array (
    'user_deleted' => array(
        'handlerfile'       => '/mod/facetoface/lib.php',
        'handlerfunction'   => 'facetoface_eventhandler_user_deleted',
        'schedule'          => 'instant'
    ),
    'user_suspended' => array(
        'handlerfile'       => '/mod/facetoface/lib.php',
        'handlerfunction'   => 'facetoface_eventhandler_user_suspended',
        'schedule'          => 'instant'
    ),
    'user_unenrolled' => array(
        'handlerfile'       => '/mod/facetoface/lib.php',
        'handlerfunction'   => 'facetoface_eventhandler_user_unenrolled',
        'schedule'          => 'instant'
    ),
);

