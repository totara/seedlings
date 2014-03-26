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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage facetoface
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot . '/mod/facetoface/lib.php');

// Get data

// Action
$action = required_param('action', PARAM_TEXT);

// Value
$value = required_param('value', PARAM_ALPHANUM);
$status = '';

// Validate data and capabilities
if (!confirm_sesskey()) {
    print_error('confirmsesskeybad', 'error');
}

$context = context_system::instance();

require_login(0, false);
require_capability('moodle/site:config', $context);


// Manage selection data saved in session
// Check $_SESSION variable exists
if (empty($_SESSION['f2f-rooms'])) {
    $_SESSION['f2f-rooms'] = array();
}

$sess =& $_SESSION['f2f-rooms'];

// Handle individual changes
if ($action !== 'bulk') {
    if (empty($sess['individual'])) {
        $sess['individual'] = array();
    }
    $sess['individual'][$action] = array('value' => $value, 'status' => $status);

    return;
}

// Handle special cases (action is "bulk")
if ($value == 'none') {
    // Reset
    $sess = array();

} else if ($value == 'all') {
    // Reset
    $sess = array();

    // Save "all" state
    $sess['all'] = true;

} else {
    // Reset
    $sess = array();

    // Save "status" state
    $sess[$value] = true;
}
