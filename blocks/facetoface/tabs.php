<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2009 Catalyst IT LTD
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
 * @author Francois Marier <francois@catalyst.net.nz>
 * @package blocks
 * @subpackage facetoface
 */

if (!isset($currenttab)) {
    $currenttab = 'attending';
}

$tabs = array();
$row = array();
$activated = array();
$inactive = array();

$row[] = new tabobject('attending', new moodle_url('/blocks/facetoface/mysignups.php', $urlparams), get_string('bookings','block_facetoface'));
$row[] = new tabobject('attendees', new moodle_url('/blocks/facetoface/mysessions.php', $urlparams), get_string('sessions','block_facetoface'));

$tabs[] = $row;
$activated[] = $currenttab;

/// Print out the tabs and continue!
print_tabs($tabs, $currenttab, $inactive, $activated);
