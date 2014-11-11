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
$action = required_param('action', PARAM_TEXT);
$searchterm = optional_param('search', '', PARAM_TEXT);

$exceptions_manager = new prog_exceptions_manager($programid);

switch ($action) {
    case 'selectmultiple':

        $selectiontype = optional_param('selectiontype', SELECTIONTYPE_NONE, PARAM_INT);

        $exceptions_manager->set_selections($selectiontype, $searchterm);
        $selected_exceptions = $exceptions_manager->get_selected_exceptions();
        $numselectedexceptions = count($selected_exceptions);
        $handled_actions = $exceptions_manager->get_handled_actions_for_selection('array', $selected_exceptions);

        $_SESSION['exceptions_selectiontype'] = $selectiontype;
        //$_SESSION['exceptions_initallyselectedcount'] = $numselectedexceptions;
        $_SESSION['exceptions_added'] = array();
        $_SESSION['exceptions_removed'] = array();

        $data = array(
            'selectedcount'     => $numselectedexceptions,
            'selectiontype'     => $selectiontype,
            'handledactions'    => $handled_actions
        );

        echo json_encode($data);

    break;

    case 'selectsingle':

        $exceptionid = optional_param('exceptionid', 0, PARAM_INT);
        $checked = optional_param('checked', 'false', PARAM_TEXT);

        if (!$exception = $DB->get_record('prog_exception', array('id' => $exceptionid))) {
            $data = array(
                'error' => true,
            );
            echo json_encode($data);
            die();
        }

        $selectiontype = isset($_SESSION['exceptions_selectiontype']) ? $_SESSION['exceptions_selectiontype'] : SELECTIONTYPE_NONE;
        $manually_added_exceptions = isset($_SESSION['exceptions_added']) ? $_SESSION['exceptions_added'] : array();
        $manually_removed_exceptions = isset($_SESSION['exceptions_removed']) ? $_SESSION['exceptions_removed'] : array();

        //handle manual selection when no selection made in selection type
        if ($selectiontype == "0") {
            $selected_exceptions = array();
        } else {
            $exceptions_manager->set_selections($selectiontype, $searchterm);
            $selected_exceptions = $exceptions_manager->get_selected_exceptions();
        }

        // if the exception is being added to the selection
        if ($checked == 'true') {
            // first check if the exception being added already belongs to the global selection
            if (isset($selected_exceptions[$exceptionid])) {
                // if so, it must previously have been manually removed so we
                // need to delete it from the list of manually removed exceptions
                if (isset($manually_removed_exceptions[$exceptionid])) {
                    unset($manually_removed_exceptions[$exceptionid]);
                }
            } else { // if it isn't in the global selection add it to the list of manually added exceptions
                $manually_added_exceptions[$exceptionid] = $exception;
            }
        // if the exception is being removed from the selection
        } else {
            // first check if the exception being removed already belongs to the global selection
            if (isset($selected_exceptions[$exceptionid])) {
                // if so, add it to the list of manually removed exceptions
                $manually_removed_exceptions[$exceptionid] = $exception;
            } else { // if it isn't in the global selection then it must previously have been manually added so we need to remove it
                if (isset($manually_added_exceptions[$exceptionid])) {
                    unset($manually_added_exceptions[$exceptionid]);
                }
            }
        }
        // Add the manually added selections to the global selection
        $selected_exceptions = $selected_exceptions + $manually_added_exceptions;

        // Remove the manually removed exceptions from the global selection
        foreach ($manually_removed_exceptions as $id => $ex) {
            unset($selected_exceptions[$id]);
        }

        $numselectedexceptions = count($selected_exceptions);
        $handled_actions = $exceptions_manager->get_handled_actions_for_selection('array', $selected_exceptions);

        $_SESSION['exceptions_added'] = $manually_added_exceptions;
        $_SESSION['exceptions_removed'] = $manually_removed_exceptions;
        $_SESSION['exceptions_selectiontype'] = $selectiontype;

        $data = array(
            'error'             => false,
            'selectedcount'     => $numselectedexceptions,
            'selectiontype'     => $selectiontype,
            'handledactions'    => $handled_actions
        );

        echo json_encode($data);
    break;
}
