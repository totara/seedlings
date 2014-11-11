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
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @author Jake Salmon <jake.salmon@kineo.com>
 * @package totara
 * @subpackage program
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/totara/program/lib.php');


$programid = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_INT);
$searchterm = optional_param('search', '', PARAM_TEXT);

$selectiontype = isset($_SESSION['exceptions_selectiontype']) ? $_SESSION['exceptions_selectiontype'] : SELECTIONTYPE_NONE;
$manually_added_exceptions = isset($_SESSION['exceptions_added']) ? $_SESSION['exceptions_added'] : array();
$manually_removed_exceptions = isset($_SESSION['exceptions_removed']) ? $_SESSION['exceptions_removed'] : array();

$exceptions_manager = new prog_exceptions_manager($programid);
$exceptions_manager->set_selections($selectiontype, $searchterm);
$selected_exceptions = $exceptions_manager->get_selected_exceptions();

// Add the manually added selections to the global selection
$selected_exceptions = $selected_exceptions + $manually_added_exceptions;

// Remove the manually removed exceptions from the global selection
foreach ($manually_removed_exceptions as $id => $ex) {
    unset($selected_exceptions[$id]);
}

// Create a list to hold the ids of any exceptions that fail to be resolved
$failed_ids = array();

if (!empty($selected_exceptions)) {
    foreach ($selected_exceptions as $exception_ob) {
        $exception = null;

        // Get an instance of the correct exception class
        if (isset($exceptions_manager->exceptiontype_classnames[$exception_ob->exceptiontype])) {
            // Create an instance
            $exception = new $exceptions_manager->exceptiontype_classnames[$exception_ob->exceptiontype]($exception_ob->programid, $exception_ob);
        }
        else {
            // Else do nothing..
            die();
        }

        // Handle the exception. This will delete the exception if it is successfully
        // handled and return true. If this exception does not have a handler for
        // the specified action it will also return true.  Otherwise it will return false.
        $success = $exception->handle($action);

        if (!$success) {
            // report this to the user
            $failed_ids[] = $exception->id;
        }
    }
}

unset($_SESSION['exceptions_selectiontype']);
unset($_SESSION['exceptions_added']);
unset($_SESSION['exceptions_removed']);
$_SESSION['exceptions_resolved'] = true; // set a flag to indicate that issues have been resolved

if (count($failed_ids) == 0) {
    totara_set_notification(get_string('successfullyresolvedexceptions', 'totara_program'), null, array('class' => 'notifysuccess'));
}
else {
    totara_set_notification(get_string('failedtoresolve', 'totara_program') . ': ' . implode(', ', $failed_ids));
}
