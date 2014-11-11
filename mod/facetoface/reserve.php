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
 */
/**
 * Allocate or reserve spaces for your team
 *
 * @package   mod_facetoface
 * @copyright 2013 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot.'/mod/facetoface/lib.php');

$sid = required_param('s', PARAM_INT);
$action = optional_param('action', 'reserve', PARAM_ALPHA);
$backtoallsessions = optional_param('backtoallsessions', null, PARAM_INT);
$backtosession = optional_param('backtosession', null, PARAM_ALPHA);
$managerid = optional_param('managerid', null, PARAM_INT);

if (!$session = facetoface_get_session($sid)) {
    throw new moodle_exception('invalidsessionid', 'mod_facetoface');
}
$facetoface = $DB->get_record('facetoface', array('id' => $session->facetoface), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('facetoface', $facetoface->id, $facetoface->course, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

$url = new moodle_url('/mod/facetoface/reserve.php', array('s' => $session->id, 'action' => $action));
if ($backtoallsessions != $facetoface->id) {
    $backtoallsessions = null;
}
if ($backtoallsessions) {
    $url->param('backtoallsessions', $backtoallsessions);
}
if ($backtosession) {
    $url->param('backtosession', $backtosession);
}
if ($managerid) {
    $url->param('managerid', $managerid);
}
$PAGE->set_url($url);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);

$validactions = array('reserve', 'allocate');
if (!in_array($action, $validactions)) {
    $action = 'reserve';
}

// Handle cancel.
if ($backtoallsessions) {
    $redir = new moodle_url('/mod/facetoface/view.php', array('id' => $cm->id));
} else if ($backtosession) {
    $redir = new moodle_url('/mod/facetoface/attendees.php', array('s' => $session->id, 'backtoallsessions' => $facetoface->id,
                                                                  'action' => $backtosession));
} else {
    $redir = new moodle_url('/course/view.php', array('id' => $course->id));
}
if (optional_param('cancel', false, PARAM_BOOL)) {
    redirect($redir);
}

// Gather info about the number of reservations / allocations the manager has/can make.
if (!$managerid || $action != 'reserve' || $managerid == $USER->id) { // Can only reserve for other users, not allocate.
    $manager = $USER;
} else {
    $manager = $DB->get_record('user', array('id' => $managerid), '*', MUST_EXIST);
}
$reserveinfo = facetoface_can_reserve_or_allocate($facetoface, array($session), $context, $manager->id);
if ($reserveinfo[$action] === false) { // Current user does not have permission to do the requested action for themselves.
    if ($action != 'reserve' || empty($reserveinfo['reserveother'])) { // Not able to reserve spaces for other users either.
        print_error('nopermissionreserve', 'mod_facetoface'); // Not allowed to reserve/allocate spaces.
    }
}
if ($session->datetimeknown) {
    $signupcount = facetoface_get_num_attendees($session->id, MDL_F2F_STATUS_BOOKED);
} else {
    $signupcount = facetoface_get_num_attendees($session->id, MDL_F2F_STATUS_APPROVED);
}
$capacityleft = max(0, $session->capacity - $signupcount);
if (!$session->allowoverbook) {
    $reserveinfo = facetoface_limit_reserveinfo_to_capacity_left($session->id, $reserveinfo, $capacityleft);
}
$reserveinfo = facetoface_limit_reserveinfo_by_session_date($reserveinfo, $session);

$output = $PAGE->get_renderer('mod_facetoface');

$preform = '';
$form = '';
if ($action == 'reserve') {

    if ($reserveinfo['reservepastdeadline']) {
        $form = $output->notification(get_string('reservepastdeadline', 'mod_facetoface', $facetoface->reservedays));
    } else {

        // Handle reserve form submission.
        if (optional_param('submit', false, PARAM_BOOL)) {
            require_sesskey();
            $reserve = required_param('reserve', PARAM_INT);
            $reserve = max(0, min($reserve, $reserveinfo['maxreserve'][$session->id]));

            $diff = $reserve - $reserveinfo['reserved'][$session->id];
            if ($diff > 0) {
                $toadd = $diff;
                $book = min($capacityleft, $toadd); // Book any reservations for which there is capacity left ...
                $waitlist = $toadd - $book; // ... and add the rest to the waiting list.
                facetoface_add_reservations($session, $manager->id, $book, $waitlist);
            } else if ($diff < 0) {
                facetoface_remove_reservations($facetoface, $session, $manager->id, -$diff, ($USER->id != $manager->id));
            }

            redirect($redir);
        } else if (optional_param('cancelreservation', false, PARAM_BOOL)) {
            require_sesskey();
            facetoface_remove_reservations($facetoface, $session, $manager->id, 1, ($USER->id != $manager->id));
            redirect($redir);
        }

        $managers = array();
        if ($reserveinfo['reserveother']) {
            // Form to select which manager to reserve spaces for.
            $managers = facetoface_get_manager_list();
            $preform .= html_writer::input_hidden_params($PAGE->url);
            $preform .= html_writer::select($managers, 'managerid', $manager->id).' ';
            $preform .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'selectmanager',
                                                             'value' => get_string('selectmanager', 'mod_facetoface')));
            $preform = html_writer::tag('form', $preform, array('action' => $PAGE->url->out_omit_querystring(), 'method' => 'get'));
            $preform .= html_writer::empty_tag('br');
            $preform .= html_writer::empty_tag('br');
        }

        // Generate the reserve form.
        if (empty($reserveinfo['maxreserve'][$session->id])) {
            // No spaces left that the manager can reserve.
            if ($manager->id == $USER->id && !$reserveinfo['reserve']) {
                $form = ''; // Can only reserve for others, not for self - wait the user to select a manager.
            } else if ($capacityleft == 0) {
                $form = html_writer::tag('p', get_string('reservenocapacity', 'mod_facetoface'));
            } else if ($manager->id == $USER->id) {
                $form = html_writer::tag('p', get_string('reserveallallocated', 'mod_facetoface'));
            } else {
                $form = html_writer::tag('p', get_string('reserveallallocatedother', 'mod_facetoface'));
            }

        } else {
            $reserveopts = range(1, $reserveinfo['maxreserve'][$session->id]);
            $reserveopts = array(0 => get_string('noreservations', 'mod_facetoface')) + array_combine($reserveopts, $reserveopts);
            $waitliststart = $capacityleft + $reserveinfo['reserved'][$session->id];
            foreach ($reserveopts as $key => $value) {
                if ($key > $waitliststart) {
                    $reserveopts[$key] .= '*';
                }
            }
            if ($manager->id == $USER->id) {
                $form .= html_writer::tag('p', get_string('reserveintro', 'mod_facetoface'));
            } else {
                $form .= html_writer::tag('p', get_string('reserveintroother', 'mod_facetoface', $managers[$manager->id]));
            }
            $form .= html_writer::tag('label', get_string('reserve', 'mod_facetoface'), array('for' => 'reserve'));
            $form .= html_writer::select($reserveopts, 'reserve', $reserveinfo['reserved'][$session->id], null, array('id' => 'reserve'));
            $form .= html_writer::empty_tag('br');
            if ($reserveinfo['maxreserve'][$session->id] > $waitliststart) {
                $form .= ' '.get_string('reservecapacitywarning', 'mod_facetoface', $capacityleft);
                $form .= html_writer::empty_tag('br');
            }
            $form .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'submit', 'value' => get_string('update')));
            $form .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'cancel', 'value' => get_string('cancel')));
        }
    }


} else { // Allocate.

    // If 'allocate' - show a list of team members you could allocate + options about whether you should allocate into previous
    // reservations or allocate new spaces (yes/no for allocate from reserved spaces, with a count, if there are any)
    $team = facetoface_get_staff_to_allocate($facetoface, $session);

    if (empty($team->potential) && empty($team->current)) {
        $form .= html_writer::tag('p', get_string('allocatenoteam', 'mod_facetoface'));
    } else {
        $replacereservations = optional_param('replacereservations', true, PARAM_BOOL);
        if ($reserveinfo['reservepastdeadline']) {
            $replaceallocations = false;
        } else {
            $replaceallocations = optional_param('replaceallocations', true, PARAM_BOOL);
        }
        $error = null;

        if (optional_param('add', false, PARAM_BOOL)) {
            // Allocating users to spaces.
            require_sesskey();
            $spaces = $reserveinfo['allocate'][$session->id];
            if (!$replacereservations) {
                $spaces -= $reserveinfo['reserved'][$session->id];
            }
            $spaces = max(0, $spaces);
            $newallocations = optional_param_array('allocation', array(), PARAM_INT);
            $newallocations = array_intersect($newallocations, array_keys($team->potential));
            if (count($newallocations) > $spaces) {
                // No spaces left.
                if (!$replacereservations && $reserveinfo['reserved'][$session->id]) {
                    $error = get_string('allocationfull_noreserve', 'mod_facetoface', $spaces);
                } else {
                    $error = get_string('allocationfull_reserve', 'mod_facetoface', $spaces);
                }
            } else {
                // Allocate the spaces.
                if ($replacereservations) {
                    $newallocations = facetoface_replace_reservations($session, $facetoface, $course, $USER->id, $newallocations);
                }
                facetoface_allocate_spaces($session, $facetoface, $course, $USER->id, $newallocations, $capacityleft);

                redirect($redir);
            }

        } else if (optional_param('remove', false, PARAM_BOOL)) {
            require_sesskey();

            $removeallocations = optional_param_array('deallocation', array(), PARAM_INT);
            $removeallocations = array_intersect($removeallocations, array_keys($team->current));
            $removeallocations = array_diff($removeallocations, array_keys($team->cannotunallocate));

            facetoface_remove_allocations($session, $facetoface, $course, $removeallocations, $replaceallocations);
            redirect($redir);
        }

        if ($error) {
            $form .= $output->notification($error);
        }
        $form .= $output->session_user_selector($team, $session, $reserveinfo);

        $yesno = array(1 => get_string('yes'), 0 => get_string('no'));
        if (!empty($reserveinfo['reserved'][$session->id])) {
            $form .= html_writer::tag('label', get_string('replacereservations', 'mod_facetoface'),
                                      array('for' => 'replacereservations'));
            $form .= ' ('.$reserveinfo['reserved'][$session->id].') ';
            $form .= html_writer::select($yesno, 'replacereservations', $replacereservations, null,
                                         array('id' => 'replaceresrvations'));
            $form .= html_writer::empty_tag('br');
        }

        if (!empty($reserveinfo['allocated'][$session->id]) && !$reserveinfo['reservepastdeadline']) {
            $form .= html_writer::tag('label', get_string('replaceallocations', 'mod_facetoface'), array('for' => 'replaceallocations'));
            $form .= html_writer::select($yesno, 'replaceallocations', $replaceallocations, null, array('id' => 'replaceallocations'));
            $form .= html_writer::empty_tag('br');
        }
    }
}

// Get a list of reservations/allocations made by this manager in other sessions for this facetoface.
$otherreservations = facetoface_get_other_reservations($facetoface, $session, $manager->id);

// Wrap the form elements in a 'form' tag and add the required page params.
$baseurl = new moodle_url($PAGE->url, array('sesskey' => sesskey()));
$form .= html_writer::input_hidden_params($baseurl);
$form = html_writer::tag('form', $form, array('action' => $baseurl->out_omit_querystring(), 'method' => 'POST'));

$title = get_string($action, 'mod_facetoface');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $output->header();
echo $output->heading(format_string($facetoface->name));
echo facetoface_print_session($session, false);
echo $preform;
echo $form;
echo $output->other_reservations($otherreservations, $manager);
echo $output->footer();
