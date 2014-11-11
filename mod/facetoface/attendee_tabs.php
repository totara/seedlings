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
 * @package totara
 * @subpackage facetoface
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

// Setup tabs
$tabs = array();
$activated = array();
$currenttab = array();

if (in_array('attendees', $allowed_actions)) {
    $tabs[] = new tabobject(
            'attendees',
            $baseurl->out(),
            get_string('attendees', 'facetoface')
    );
    unset($actionurl);
}

if (in_array('waitlist', $allowed_actions)) {
    $actionurl = clone($baseurl);
    $actionurl->param('action', 'waitlist');
    $tabs[] = new tabobject(
            'waitlist',
            $actionurl->out(),
            get_string('wait-list', 'facetoface')
    );
    unset($actionurl);
}

if (in_array('cancellations', $allowed_actions)) {
    $actionurl = clone($baseurl);
    $actionurl->param('action', 'cancellations');
    $tabs[] = new tabobject(
            'cancellations',
            $actionurl->out(),
            get_string('cancellations', 'facetoface')
    );
    unset($actionurl);
}

if (in_array('takeattendance', $allowed_actions)) {
    $actionurl = clone($baseurl);
    $actionurl->param('action', 'takeattendance');
    $tabs[] = new tabobject(
            'takeattendance',
            $actionurl->out(),
            get_string('takeattendance', 'facetoface')
    );
    unset($actionurl);
}

if (in_array('approvalrequired', $allowed_actions)) {
    $actionurl = clone($baseurl);
    $actionurl->param('action', 'approvalrequired');
    $tabs[] = new tabobject(
            'approvalrequired',
            $actionurl->out(),
            get_string('approvalreqd', 'facetoface')
    );
    unset($actionurl);
}

if (in_array('messageusers', $allowed_actions)) {
    $actionurl = clone($baseurl);
    $actionurl->param('action', 'messageusers');
    $tabs[] = new tabobject(
            'messageusers',
            $actionurl->out(),
            get_string('messageusers', 'facetoface')
    );
    unset($actionurl);
}

$activated[] = $action;
$currenttab[] = $action;

// Inactive tabs: get difference between allowed and available tabs
$inactive = array_diff($allowed_actions, $available_actions);

print_tabs(array($tabs), $currenttab, $inactive, $activated);
